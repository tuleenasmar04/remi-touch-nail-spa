<?php

namespace App\Services;

use App\Exceptions\DomainConflict;
use App\Models\Appointment;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestAccess
{
    public function issue(Appointment|CourseRegistration $record): string
    {
        $token = Str::random(64);
        $type = $record instanceof Appointment ? 'appointment' : 'registration';
        DB::table('guest_management_tokens')->where('subject_type', $type)->where('subject_id', $record->id)->update(['revoked_at' => now()]);
        DB::table('guest_management_tokens')->insert(['token_hash' => hash('sha256', $token), 'subject_type' => $type, 'subject_id' => $record->id, 'expires_at' => now()->addDays(90), 'created_at' => now(), 'updated_at' => now()]);

        return $token;
    }

    public function exchange(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $row = DB::table('guest_management_tokens')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            if (! $row) {
                throw new DomainConflict('invalid_guest_token', 'This management link has expired or is invalid.', 403);
            }
            DB::table('guest_management_tokens')->where('id', $row->id)->update(['revoked_at' => now()]);
            session()->put('guest', ['type' => $row->subject_type, 'id' => $row->subject_id, 'expires' => now()->addMinutes(30)->timestamp]);
            $record = ($row->subject_type === 'appointment' ? Appointment::class : CourseRegistration::class)::findOrFail($row->subject_id);

            return ['type' => $row->subject_type, 'data' => $this->present($record)];
        });
    }

    public function authorize(string $reference, string $type): Appointment|CourseRegistration
    {
        $grant = session('guest');
        if (! $grant || $grant['type'] !== $type || $grant['expires'] < now()->timestamp) {
            throw new DomainConflict('guest_access_required', 'Please open a valid management link.', 403);
        }
        $class = $type === 'appointment' ? Appointment::class : CourseRegistration::class;
        $record = $class::where('reference', $reference)->where('id', $grant['id'])->first();
        if (! $record) {
            throw new DomainConflict('guest_access_required', 'This session cannot manage that booking.', 403);
        }

        return $record;
    }

    public function present($record): array
    {
        return array_merge($record->only(['reference', 'service_id', 'service_name', 'course_title', 'starts_at', 'ends_at', 'price_minor', 'currency', 'status', 'policy_snapshot', 'duration_minutes']), ['customer' => $record->customer->only(['name', 'email', 'phone'])]);
    }
}
