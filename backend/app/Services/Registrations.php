<?php

namespace App\Services;

use App\Exceptions\DomainConflict;
use App\Models\AuditLog;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Customer;
use App\Rules\JordanPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Registrations
{
    public function __construct(private Settings $settings, private GuestAccess $access, private Notifications $notifications) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $offering = CourseOffering::lockForUpdate()->findOrFail($data['offering_id']);
            if (! $this->settings->ready()) {
                throw new DomainConflict('registration_closed', 'Registration is not configured.', 422);
            }
            if (! $offering->published || ! $offering->course?->published || now()->lt($offering->registration_opens_at) || now()->gte($offering->registration_closes_at) || ! $offering->sessions()->exists()) {
                throw new DomainConflict('registration_closed', 'Registration is closed.', 422);
            }
            if ($offering->registrations()->whereIn('status', ['pending', 'confirmed'])->count() >= $offering->capacity) {
                throw new DomainConflict('course_full', 'This course is now full.');
            }
            $customer = Customer::create(['name' => $data['name'], 'email' => ! empty($data['email']) ? strtolower($data['email']) : null, 'phone' => JordanPhone::normalize($data['phone'] ?? '')]);
            $record = CourseRegistration::create(['reference' => 'RC-'.strtoupper(Str::random(12)), 'offering_id' => $offering->id, 'customer_id' => $customer->id, 'course_title' => $offering->course->title, 'price_minor' => $offering->price_minor, 'currency' => $this->settings->get('currency'), 'policy_snapshot' => ['cancellation_hours' => $offering->cancellation_hours], 'status' => 'confirmed', 'locale' => $data['locale'], 'notes' => $data['notes'] ?? null]);
            $token = $this->access->issue($record);
            $this->notifications->record($record, 'confirmed', $token);

            return ['data' => $this->access->present($record), 'management_token' => $token];
        }, 3);
    }

    public function cancel(CourseRegistration $record, bool $admin = false): array
    {
        return DB::transaction(function () use ($record, $admin) {
            $offering = CourseOffering::withTrashed()->lockForUpdate()->findOrFail($record->offering_id);
            $record = CourseRegistration::lockForUpdate()->findOrFail($record->id);
            if (! in_array($record->status, ['pending', 'confirmed'], true)) {
                throw new DomainConflict('invalid_status', 'Only confirmed registrations can be cancelled.');
            }
            $start = $offering->sessions()->orderBy('starts_at')->first()?->starts_at;
            if (! $admin && (! $start || now()->gte($start->subHours($record->policy_snapshot['cancellation_hours'])))) {
                throw new DomainConflict('policy_cutoff', 'The cancellation window has closed.', 422);
            }
            $record->update(['status' => 'cancelled']);
            $this->notifications->record($record, 'cancelled');

            return ['data' => $this->access->present($record)];
        }, 3);
    }

    public function updateStatus(CourseRegistration $record, string $status, int $ownerId): array
    {
        return DB::transaction(function () use ($record, $status, $ownerId): array {
            $offering = CourseOffering::withTrashed()->lockForUpdate()->findOrFail($record->offering_id);
            $record = CourseRegistration::lockForUpdate()->findOrFail($record->id);
            if ($status === $record->status) {
                return ['data' => $record->load('customer')];
            }
            if ($status === 'cancelled') {
                $result = $this->cancel($record, true);
            } else {
                if (in_array($status, ['pending', 'confirmed'], true) && ! in_array($record->status, ['pending', 'confirmed'], true)) {
                    if ($offering->registrations()->whereIn('status', ['pending', 'confirmed'])->count() >= $offering->capacity) {
                        throw new DomainConflict('course_full', 'This course is full.');
                    }
                }
                if (in_array($status, ['completed', 'no_show'], true)) {
                    $end = $offering->sessions()->max('ends_at');
                    if (! $end || now()->lt($end) || ! in_array($record->status, ['pending', 'confirmed'], true)) {
                        throw new DomainConflict('invalid_transition', 'Attendance can only be recorded after the course ends.');
                    }
                }
                $record->update(['status' => $status]);
                $this->notifications->record($record, $status);
                $result = ['data' => $record->load('customer')];
            }
            AuditLog::create(['user_id' => $ownerId, 'action' => 'status', 'subject_type' => 'registrations', 'subject_id' => $record->id, 'changes' => ['status' => $status]]);

            return $result;
        }, 3);
    }
}
