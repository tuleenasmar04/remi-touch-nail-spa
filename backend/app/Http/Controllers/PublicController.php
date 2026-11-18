<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\GalleryItem;
use App\Models\Outbox;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Rules\JordanPhone;
use App\Services\Availability;
use App\Services\Bookings;
use App\Services\GuestAccess;
use App\Services\Idempotency;
use App\Services\Notifications;
use App\Services\Registrations;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function catalog(Settings $settings)
    {
        $publicKeys = ['salon_name', 'logo_url', 'hero_image_url', 'timezone', 'currency', 'country', 'address', 'phone', 'email', 'directions_url', 'instagram_url', 'facebook_url'];
        $s = array_intersect_key($settings->all(), array_flip($publicKeys));
        $s['booking_enabled'] = $settings->bookingEnabled();
        $s['booking_ready'] = $settings->ready();
        $s['working_hours'] = WorkingHour::orderBy('weekday')->orderBy('starts_at')->get(['weekday', 'starts_at', 'ends_at']);
        $s['demo_mode'] = app()->environment('local') && str_starts_with($s['country'] ?? '', 'DEMO');
        $services = Service::where('published', true)->whereHas('category', fn ($q) => $q->where('published', true))->get();

        return response()->json(['settings' => $s, 'services' => $services, 'categories' => Category::where('published', true)->orderBy('sort_order')->get(), 'staff' => [], 'shared_capacity' => true, 'gallery' => GalleryItem::where('published', true)->get(), 'content' => ContentBlock::where('published', true)->pluck('value', 'key'), 'courses' => Course::where('published', true)->get()->map(function ($c) {
            $c->setRelation('offerings', $c->offerings()->where('published', true)->with('sessions')->get()->map(function ($o) {
                $o->remaining = max(0, $o->capacity - $o->registrations()->whereIn('status', ['pending', 'confirmed'])->count());
                $o->registration_open = now()->gte($o->registration_opens_at) && now()->lt($o->registration_closes_at);

                return $o;
            }));

            return $c;
        })]);
    }

    public function availability(Request $r, Availability $availability)
    {
        $d = $r->validate(['service_id' => 'required|integer|exists:services,id', 'staff_id' => 'nullable|integer|exists:staff,id', 'date' => 'required|date_format:Y-m-d']);

        return ['slots' => $availability->slots(Service::findOrFail($d['service_id']), $d['date'], $d['staff_id'] ?? null)];
    }

    public function book(BookingRequest $r, Idempotency $idempotency, Bookings $bookings)
    {
        return response()->json($idempotency->execute(hash('sha256', 'appointment:'.$r->validated('phone')), $r->header('Idempotency-Key', ''), $r->validated(), fn () => $bookings->reserve($r->validated())), 201);
    }

    public function register(Request $r, Idempotency $idempotency, Registrations $registrations)
    {
        $r->merge(['phone' => JordanPhone::normalize((string) $r->input('phone', ''))]);
        $d = $r->validate(['offering_id' => 'required|integer|exists:course_offerings,id', 'name' => 'required|string|max:120', 'email' => 'nullable|email:rfc|max:254', 'phone' => ['required', new JordanPhone], 'notes' => 'nullable|string|max:1000', 'locale' => 'required|in:en,ar', 'policy_accepted' => 'required|accepted']);

        return response()->json($idempotency->execute(hash('sha256', 'registration:'.$d['phone']), $r->header('Idempotency-Key', ''), $d, fn () => $registrations->register($d)), 201);
    }

    public function contact(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:254', 'message' => 'required|string|max:5000', 'locale' => 'required|in:en,ar']);
        DB::transaction(function () use ($d) {
            $id = DB::table('contact_messages')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            $recipient = app(Settings::class)->get('email');
            if ($recipient) {
                Outbox::create(['event_key' => 'contact:'.$id, 'recipient' => $recipient, 'locale' => $d['locale'], 'subject' => $d['locale'] === 'ar' ? 'رسالة تواصل جديدة · REMI' : 'New contact message · REMI', 'body' => $d['name']."\n".$d['email']."\n\n".$d['message']]);
                DB::table('contact_messages')->where('id', $id)->update(['delivery_status' => 'queued']);
            }
        });

        return response()->json(['message' => app()->getLocale() === 'ar' ? 'تم استلام رسالتكِ.' : 'Your message has been received.'], 201);
    }

    public function exchange(Request $r, GuestAccess $access)
    {
        $d = $r->validate(['token' => 'required|string|size:64']);
        $r->session()->regenerate();

        return $access->exchange($d['token']);
    }

    public function recover(Request $r, GuestAccess $access, Notifications $notifications)
    {
        $d = $r->validate(['reference' => 'required|string|max:32', 'email' => 'required|email|max:254', 'locale' => 'required|in:en,ar']);
        DB::transaction(function () use ($d, $access, $notifications) {
            $class = str_starts_with($d['reference'], 'RC-') ? CourseRegistration::class : Appointment::class;
            $record = $class::where('reference', $d['reference'])->whereHas('customer', fn ($q) => $q->where('email', strtolower($d['email'])))->lockForUpdate()->first();
            if ($record) {
                $notifications->record($record, 'recovery', $access->issue($record));
            }
        });

        return ['message' => 'If the details match a booking, a management link will be sent.'];
    }

    public function cancel(Request $r, string $reference, GuestAccess $access, Bookings $bookings)
    {
        return $bookings->cancel($access->authorize($reference, 'appointment'));
    }

    public function reschedule(Request $r, string $reference, GuestAccess $access, Bookings $bookings, Idempotency $idempotency)
    {
        $d = $r->validate(['starts_at' => ['required', 'date', 'regex:/T.*(Z|[+-]\d{2}:\d{2})$/'], 'staff_id' => 'nullable|integer|exists:staff,id', 'policy_accepted' => 'required|accepted']);
        $record = $access->authorize($reference, 'appointment');

        return $idempotency->execute(hash('sha256', 'reschedule:'.$record->id), $r->header('Idempotency-Key', ''), $d, fn () => $bookings->reserve($d, $record));
    }

    public function cancelRegistration(Request $r, string $reference, GuestAccess $access, Registrations $registrations)
    {
        return $registrations->cancel($access->authorize($reference, 'registration'));
    }
}
