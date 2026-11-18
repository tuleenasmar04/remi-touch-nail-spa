<?php

namespace App\Services;

use App\Exceptions\DomainConflict;
use App\Models\Appointment;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentManagement
{
    public function update(int $id, array $data, int $ownerId): array
    {
        return DB::transaction(function () use ($id, $data, $ownerId): array {
            SalonCapacity::lock();
            $record = Appointment::lockForUpdate()->findOrFail($id);
            $before = $record->only(['status', 'starts_at', 'ends_at', 'internal_notes']);
            $status = $data['status'];
            $active = in_array($record->status, ['pending', 'confirmed'], true);
            $moved = isset($data['starts_at']) && ! CarbonImmutable::parse($data['starts_at'])->equalTo($record->starts_at);
            if ($moved) {
                if (! $active || ! in_array($status, ['pending', 'confirmed'], true)) {
                    throw new DomainConflict('invalid_transition', 'Reschedule an active booking before changing its final status.');
                }
                app(Bookings::class)->reserve($data, $record, true);
                $record->refresh();
            }
            if ($status !== $record->status) {
                if (! $active) {
                    throw new DomainConflict('invalid_transition', 'Final appointment statuses cannot be reopened.');
                }
                if ($status === 'cancelled') {
                    app(Bookings::class)->cancel($record, true);
                    $record->refresh();
                } else {
                    $allowed = $record->status === 'pending' ? ['confirmed'] : ['completed', 'no_show'];
                    if (! in_array($status, $allowed, true) || (in_array($status, ['completed', 'no_show'], true) && $record->ends_at->isFuture())) {
                        throw new DomainConflict('invalid_transition', 'This status transition is not allowed.');
                    }
                    $record->update(['status' => $status]);
                    DB::table('appointment_status_events')->insert(['appointment_id' => $id, 'status' => $status, 'event' => 'status', 'user_id' => $ownerId, 'details' => json_encode(['previous_status' => $before['status']]), 'created_at' => now(), 'updated_at' => now()]);
                    app(Notifications::class)->record($record, $status);
                }
            }
            if (array_key_exists('internal_notes', $data)) {
                $record->update(['internal_notes' => $data['internal_notes']]);
            }
            AuditLog::create(['user_id' => $ownerId, 'action' => 'update', 'subject_type' => 'appointments', 'subject_id' => $id, 'changes' => ['previous_status' => $before['status'], 'status' => $record->status, 'rescheduled' => $moved, 'internal_notes_updated' => array_key_exists('internal_notes', $data)]]);

            return ['data' => $record->fresh()->load('customer')];
        }, 3);
    }
}
