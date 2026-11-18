<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Policies\AdminPolicy;
use App\Services\Availability;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function index(Request $request, Settings $settings): array
    {
        abort_unless(app(AdminPolicy::class)->view($request->user()), 403);
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $zone = $settings->get('timezone', 'Asia/Amman');
        $start = CarbonImmutable::parse($data['from'], $zone)->startOfDay();
        $end = CarbonImmutable::parse($data['to'], $zone)->addDay()->startOfDay();
        abort_if($start->diffInDays($end) > 62, 422, 'Calendar range cannot exceed 62 days.');
        $appointments = Appointment::with('customer')->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->orderBy('starts_at')->get();

        return ['timezone' => $zone, 'appointments' => $appointments, 'blocks' => ScheduleBlock::where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->get(), 'working_hours' => WorkingHour::orderBy('weekday')->orderBy('starts_at')->get()];
    }

    public function history(Request $request, int $id): array
    {
        abort_unless(app(AdminPolicy::class)->view($request->user()), 403);
        $appointment = Appointment::with('customer')->findOrFail($id);

        return ['data' => $appointment, 'events' => DB::table('appointment_status_events')->where('appointment_id', $id)->orderBy('id')->get(), 'audit' => DB::table('audit_logs')->where('subject_type', 'appointments')->where('subject_id', $id)->orderBy('id')->get()];
    }

    public function availability(Request $request, Availability $availability): array
    {
        abort_unless(app(AdminPolicy::class)->view($request->user()), 403);
        $data = $request->validate(['service_id' => 'required|integer|exists:services,id', 'date' => 'required|date_format:Y-m-d']);

        return ['slots' => $availability->slots(Service::findOrFail($data['service_id']), $data['date'], null, true)];
    }
}
