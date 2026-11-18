<?php

namespace App\Services;

use App\Exceptions\DomainConflict;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Rules\JordanPhone;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Bookings
{
    public function __construct(private Availability $availability, private Settings $settings, private GuestAccess $access, private Notifications $notifications) {}

    public function reserve(array $data, ?Appointment $existing = null, bool $admin = false): array
    {
        return DB::transaction(function () use ($data, $existing, $admin) {
            // All scheduling/configuration writers lock staff rows in this same global order.
            $locked = SalonCapacity::lock();
            if (! $this->settings->ready() && ! $admin) {
                throw new DomainConflict($this->settings->bookingEnabled() ? 'booking_unavailable' : 'booking_closed', 'Online booking is not available. Please contact the salon.', 422);
            }
            if (! in_array($this->settings->get('timezone'), timezone_identifiers_list(), true) || ! preg_match('/^[A-Z]{3}$/', $this->settings->get('currency', ''))) {
                throw new DomainConflict('booking_closed', 'Configure the salon timezone and currency.', 422);
            }
            $service = $existing ? Service::withTrashed()->findOrFail($existing->service_id) : Service::findOrFail($data['service_id']);
            if ($existing) {
                $existing = Appointment::lockForUpdate()->findOrFail($existing->id);
                if (! in_array($existing->status, ['pending', 'confirmed'], true)) {
                    throw new DomainConflict('invalid_status', 'Only confirmed appointments can be rescheduled.');
                }
                if (! $admin) {
                    $this->checkPolicy($existing, 'reschedule_hours');
                }
                $service->duration_minutes = $existing->duration_minutes;
                $service->buffer_before = (int) $existing->starts_at->diffInMinutes($existing->occupied_start, true);
                $service->buffer_after = (int) $existing->ends_at->diffInMinutes($existing->occupied_end, true);
            }
            $start = Time::parse($data['starts_at'])->utc();
            $candidateIds = $this->availability->candidates($service, $data['staff_id'] ?? null)->pluck('staff.id');
            $staff = $locked->first(fn ($s) => $candidateIds->contains($s->id) && $this->availability->reason($service, $s, $start, $existing?->id) === null);
            if (! $staff) {
                throw new DomainConflict('slot_unavailable', 'The requested time is no longer available. Please choose another.');
            }
            [$end,$occupiedStart,$occupiedEnd] = $this->availability->interval($service, $start);
            if ($existing) {
                $record = $existing;
                $oldStart = $record->starts_at->toIso8601String();
                $record->fill(['staff_id' => $staff->id, 'starts_at' => $start, 'ends_at' => $end, 'occupied_start' => $occupiedStart, 'occupied_end' => $occupiedEnd]);
                // Preserve booked duration/price/policy; new timing must use original duration and buffers.
                $duration = $record->duration_minutes;
                $before = $record->getOriginal('starts_at')->diffInMinutes($record->getOriginal('occupied_start'), true);
                $after = $record->getOriginal('ends_at')->diffInMinutes($record->getOriginal('occupied_end'), true);
                $snapshotService = clone $service;
                $snapshotService->duration_minutes = $duration;
                $snapshotService->buffer_before = (int) $before;
                $snapshotService->buffer_after = (int) $after;
                if ($this->availability->reason($snapshotService, $staff, $start, $record->id) !== null) {
                    throw new DomainConflict('slot_unavailable', 'The original service duration does not fit this time.');
                }
                [$end,$occupiedStart,$occupiedEnd] = $this->availability->interval($snapshotService, $start);
                $record->fill(['ends_at' => $end, 'occupied_start' => $occupiedStart, 'occupied_end' => $occupiedEnd]);
                $record->save();
                DB::table('appointment_status_events')->insert(['appointment_id' => $record->id, 'status' => $record->status, 'event' => 'rescheduled', 'details' => json_encode(['from' => $oldStart, 'to' => $start->toIso8601String()]), 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
                $this->notifications->record($record, 'rescheduled');

                return ['data' => $this->access->present($record)];
            }
            $customer = Customer::create(['name' => $data['name'], 'email' => ! empty($data['email']) ? strtolower($data['email']) : null, 'phone' => JordanPhone::normalize($data['phone'] ?? ''), 'marketing_consent' => (bool) ($data['marketing_consent'] ?? false)]);
            $record = Appointment::create(['reference' => 'RM-'.strtoupper(Str::random(12)), 'customer_id' => $customer->id, 'service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => $start, 'ends_at' => $end, 'occupied_start' => $occupiedStart, 'occupied_end' => $occupiedEnd, 'service_name' => $service->name, 'price_minor' => $service->price_minor, 'currency' => $this->settings->get('currency'), 'duration_minutes' => $service->duration_minutes, 'policy_snapshot' => $this->settings->policies(), 'status' => 'confirmed', 'locale' => $data['locale'], 'notes' => $data['notes'] ?? null, 'source' => $admin ? 'admin' : 'website']);
            if (! $admin && $this->settings->get('require_approval', false)) {
                $record->update(['status' => 'pending']);
            }
            DB::table('appointment_status_events')->insert(['appointment_id' => $record->id, 'status' => $record->status, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $token = $this->access->issue($record);
            $this->notifications->record($record, $record->status, $token);

            return ['data' => $this->access->present($record), 'management_token' => $token];
        }, 3);
    }

    public function checkPolicy(Appointment $record, string $key): void
    {
        if (Time::now('UTC')->greaterThanOrEqualTo($record->starts_at->subHours((int) ($record->policy_snapshot[$key] ?? 24)))) {
            throw new DomainConflict('policy_cutoff', 'The self-service change window has closed. Please contact the salon.', 422);
        }
    }

    public function cancel(Appointment $record, bool $admin = false): array
    {
        return DB::transaction(function () use ($record, $admin) {
            SalonCapacity::lock();
            $record = Appointment::lockForUpdate()->findOrFail($record->id);
            if (! in_array($record->status, ['pending', 'confirmed'], true)) {
                throw new DomainConflict('invalid_status', 'Only confirmed appointments can be cancelled.');
            }
            if (! $admin) {
                $this->checkPolicy($record, 'cancellation_hours');
            }
            $record->update(['status' => 'cancelled']);
            DB::table('appointment_status_events')->insert(['appointment_id' => $record->id, 'status' => 'cancelled', 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $this->notifications->record($record, 'cancelled');

            return ['data' => $this->access->present($record)];
        }, 3);
    }
}
