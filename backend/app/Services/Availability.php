<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CourseSession;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable as Time;

class Availability
{
    public function __construct(private Settings $settings) {}

    public function candidates(Service $service, ?int $staffId = null)
    {
        return $service->staff()->where('published', true)->when($staffId, fn ($q) => $q->where('staff.id', $staffId))->orderBy('staff.id');
    }

    public function interval(Service $service, Time $start): array
    {
        $end = $start->addMinutes($service->duration_minutes);

        return [$end, $start->subMinutes($service->buffer_before), $end->addMinutes($service->buffer_after)];
    }

    public function reason(Service $service, Staff $staff, Time $start, ?int $ignore = null): ?string
    {
        if ((! $ignore && (! $service->published || ! $service->category?->published || ! $service->bookable)) || ! $service->duration_minutes || ! $staff->published || ! $service->staff()->where('staff.id', $staff->id)->exists()) {
            return 'unavailable';
        }
        $now = Time::now('UTC');
        if ($start->lessThan($now->addMinutes((int) $this->settings->get('minimum_notice_minutes', 120))) || $start->greaterThan($now->addDays((int) $this->settings->get('booking_horizon_days', 60)))) {
            return 'outside_window';
        }
        [$end,$occupiedStart,$occupiedEnd] = $this->interval($service, $start);
        $zone = $this->settings->get('timezone');
        $local = $start->setTimezone($zone);
        $interval = max(5, (int) $this->settings->get('slot_interval_minutes', 15));
        if ($local->second !== 0 || ($local->hour * 60 + $local->minute) % $interval !== 0) {
            return 'unaligned';
        }
        $withinHours = WorkingHour::where('weekday', $local->dayOfWeek)->get()->contains(function ($hours) use ($local, $zone, $occupiedStart, $occupiedEnd) {
            $a = Time::parse($local->toDateString().' '.$hours->starts_at, $zone)->utc();
            $b = Time::parse($local->toDateString().' '.$hours->ends_at, $zone)->utc();

            return $occupiedStart->greaterThanOrEqualTo($a) && $occupiedEnd->lessThanOrEqualTo($b);
        });
        $override = ScheduleBlock::where('kind', 'override')->where('starts_at', '<=', $occupiedStart)->where('ends_at', '>=', $occupiedEnd)->exists();
        if (! $withinHours && ! $override) {
            return 'closed';
        }
        if (ScheduleBlock::where('kind', 'block')->where('starts_at', '<', $occupiedEnd)->where('ends_at', '>', $occupiedStart)->exists()) {
            return 'blocked';
        }
        if (CourseSession::where('staff_id', $staff->id)->where('starts_at', '<', $occupiedEnd)->where('ends_at', '>', $occupiedStart)->exists()) {
            return 'blocked';
        }
        if (Appointment::whereIn('status', ['pending', 'confirmed', 'completed', 'no_show'])->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->where('occupied_start', '<', $occupiedEnd)->where('occupied_end', '>', $occupiedStart)->exists()) {
            return 'booked';
        }

        return null;
    }

    public function slots(Service $service, string $date, ?int $staffId = null, bool $forAdmin = false): array
    {
        if ((! $forAdmin && ! $this->settings->ready()) || ! $service->published || ! $service->bookable) {
            return [];
        }
        $staff = $this->candidates($service, $staffId)->get();
        if ($staff->isEmpty()) {
            return [];
        }
        $zone = $this->settings->get('timezone');
        $day = Time::createFromFormat('!Y-m-d', $date, $zone);
        if (! $day || $day->toDateString() !== $date) {
            return [];
        }
        $next = $day->addDay();
        $slots = [];
        $interval = max(5, (int) $this->settings->get('slot_interval_minutes', 15));
        // Iterate real UTC instants, not wall-clock strings: repeated DST hours remain distinct.
        for ($time = $day->utc(); $time->lessThan($next->utc()); $time = $time->addMinute()) {
            $wall = $time->setTimezone($zone);
            if (($wall->hour * 60 + $wall->minute) % $interval !== 0) {
                continue;
            }
            $reasons = $staff->map(fn ($s) => $this->reason($service, $s, $time));
            if ($reasons->every(fn ($r) => in_array($r, ['closed', 'outside_window', 'unaligned', 'unavailable'], true))) {
                continue;
            }
            $available = $reasons->containsStrict(null);
            $slots[] = ['starts_at' => $time->toIso8601String(), 'available' => $available, 'reason' => $available ? null : ($reasons->contains('booked') ? 'booked' : 'blocked')];
        }

        return $slots;
    }
}
