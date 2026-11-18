<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\CourseRegistration;
use App\Models\Customer;
use App\Models\Outbox;
use App\Services\SalonCapacity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CustomerData extends Command
{
    protected $signature = 'remi:customer-data {action : export or anonymize} {email}';

    protected $description = 'Export customer records privately or anonymize resolved records after operator identity verification';

    public function handle(): int
    {
        $action = $this->argument('action');
        $email = strtolower($this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($action, ['export', 'anonymize'], true)) {
            return self::FAILURE;
        }
        $customers = Customer::where('email', $email)->get();
        $ids = $customers->pluck('id');
        $appointments = Appointment::whereIn('customer_id', $ids)->get();
        $registrations = CourseRegistration::whereIn('customer_id', $ids)->get();
        if ($action === 'export') {
            $path = 'exports/'.bin2hex(random_bytes(16)).'.json';
            Storage::disk('local')->put($path, json_encode(['customers' => $customers, 'appointments' => $appointments, 'registrations' => $registrations, 'contact_messages' => DB::table('contact_messages')->where('email', $email)->get()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Private export created: '.Storage::disk('local')->path($path));

            return self::SUCCESS;
        }
        if (! $this->confirm('Has the requester’s identity been verified and have applicable retention obligations been reviewed?')) {
            return self::FAILURE;
        }
        if ($appointments->contains(fn ($a) => $a->status === 'confirmed' && $a->starts_at->isFuture()) || $registrations->contains(fn ($r) => $r->status === 'confirmed')) {
            $this->error('Resolve active bookings and registrations first.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($customers, $appointments, $registrations, $email) {
            SalonCapacity::lock();
            foreach ($customers as $customer) {
                $customer->update(['name' => 'Anonymized customer', 'email' => 'anonymous-'.bin2hex(random_bytes(10)).'@invalid.example', 'phone' => null, 'marketing_consent' => false]);
            }
            Appointment::whereIn('id', $appointments->pluck('id'))->update(['notes' => null, 'internal_notes' => null]);
            CourseRegistration::whereIn('id', $registrations->pluck('id'))->update(['notes' => null]);
            foreach (['appointment' => $appointments->pluck('id'), 'registration' => $registrations->pluck('id')] as $type => $ids) {
                DB::table('guest_management_tokens')->where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
            }
            Outbox::where('recipient', $email)->delete();
            DB::table('contact_messages')->where('email', $email)->delete();
            // Encrypted replay records contain contact snapshots. Remove only records scoped to this email.
            foreach (['appointment:', 'registration:'] as $prefix) {
                DB::table('idempotency_records')->where('scope', hash('sha256', $prefix.$email))->delete();
            }
            foreach ($appointments as $appointment) {
                DB::table('idempotency_records')->where('scope', hash('sha256', 'reschedule:'.$appointment->id))->delete();
            }
        });
        $this->info('Contact details and notes anonymized. Appointment history and financial snapshots retained.');

        return self::SUCCESS;
    }
}
