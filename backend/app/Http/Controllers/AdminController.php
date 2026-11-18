<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainConflict;
use App\Http\Requests\BookingRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseSession;
use App\Models\GalleryItem;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\WorkingHour;
use App\Policies\AdminPolicy;
use App\Rules\JordanPhone;
use App\Services\AppointmentManagement;
use App\Services\Bookings;
use App\Services\Registrations;
use App\Services\SalonCapacity;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const RESOURCES = ['services' => Service::class, 'categories' => Category::class,  'working-hours' => WorkingHour::class, 'schedule-blocks' => ScheduleBlock::class, 'appointments' => Appointment::class, 'courses' => Course::class, 'course-offerings' => CourseOffering::class, 'registrations' => CourseRegistration::class, 'content-blocks' => ContentBlock::class, 'gallery' => GalleryItem::class, 'settings' => SiteSetting::class, 'audit-logs' => AuditLog::class];

    private function authorizeResource(Request $r, string $resource, bool $write = false): string
    {
        if ($resource === 'contact-messages') {
            abort_unless(! $write && app(AdminPolicy::class)->view($r->user()), 403);

            return ContactMessage::class;
        }
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        $policy = app(AdminPolicy::class);
        abort_unless($write ? $policy->manage($r->user(), $resource) : $policy->view($r->user()), 403);

        return self::RESOURCES[$resource];
    }

    public function index(Request $r, string $resource)
    {
        $class = $this->authorizeResource($r, $resource);
        $query = $class::query()->orderByDesc('id');
        $search = mb_substr((string) $r->query('search', ''), 0, 120);
        if ($search !== '') {
            $column = match ($resource) {
                'appointments','registrations' => 'reference','settings','content-blocks' => 'key','audit-logs' => 'action','courses' => 'title',default => 'name'
            };
            if (in_array($resource, ['appointments', 'registrations'], true)) {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.JordanPhone::normalize($search).'%'));
                });
            } elseif (Schema::hasColumn((new $class)->getTable(), $column)) {
                $query->where($column, 'like', '%'.$search.'%');
            }
        }
        if ($resource === 'appointments') {
            $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => array_merge(['nullable', 'date_format:Y-m-d'], $r->filled('from') ? ['after_or_equal:from'] : []), 'status' => 'nullable|in:pending,confirmed,completed,cancelled,no_show', 'service_id' => 'nullable|integer']);
            foreach (['status', 'staff_id', 'service_id'] as $filter) {
                if ($r->filled($filter)) {
                    $query->where($filter, $r->query($filter));
                }
            }
            if ($r->filled('from')) {
                $query->where('starts_at', '>=', CarbonImmutable::parse($r->query('from'), app(Settings::class)->get('timezone', 'Asia/Amman'))->utc());
            }
            if ($r->filled('to')) {
                $query->where('starts_at', '<', CarbonImmutable::parse($r->query('to'), app(Settings::class)->get('timezone', 'Asia/Amman'))->addDay()->utc());
            }
            $query->with('customer');
        }
        if ($resource === 'registrations') {
            $query->with('customer');
        }
        if ($resource === 'course-offerings') {
            $query->with('sessions');
        }
        $page = $query->paginate(20);
        if ($resource === 'services') {
            $page->getCollection()->each(fn ($s) => $s->setAttribute('staff_ids', $s->staff()->pluck('staff.id')));
        }
        if ($resource === 'staff') {
            $page->getCollection()->each(fn ($s) => $s->setAttribute('service_ids', $s->services()->pluck('services.id')));
        }

        return $page;
    }

    private function rules(string $resource): array
    {
        $localized = ['required', 'array:en,ar'];
        $name = ['name' => $localized, 'name.en' => 'required|string|max:200', 'name.ar' => 'required|string|max:200'];
        $published = ['published' => 'required|boolean'];

        return match ($resource) {
            'services' => $name + $published + ['description' => $localized, 'description.en' => 'required|string|max:5000', 'description.ar' => 'required|string|max:5000', 'category_id' => 'required|integer|exists:service_categories,id', 'price_minor' => 'nullable|integer|min:0|max:999999999', 'duration_minutes' => 'nullable|integer|min:5|max:720', 'buffer_before' => 'required|integer|min:0|max:180', 'buffer_after' => 'required|integer|min:0|max:180', 'bookable' => 'required|boolean', 'image_url' => 'nullable|string|max:2048', 'staff_ids' => 'array', 'staff_ids.*' => 'integer|exists:staff,id'],
            'categories' => $name + $published + ['sort_order' => 'integer|min:0|max:1000'],
            'staff' => $name + $published + ['service_ids' => 'array', 'service_ids.*' => 'integer|exists:services,id'],
            'working-hours' => ['staff_id' => 'nullable|integer|exists:staff,id', 'weekday' => 'required|integer|min:0|max:6', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at'],
            'schedule-blocks' => ['staff_id' => 'nullable|integer|exists:staff,id', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'kind' => 'required|in:block,override', 'reason' => 'nullable|string|max:255'],
            'courses' => ['title' => $localized, 'title.en' => 'required|string|max:200', 'title.ar' => 'required|string|max:200', 'description' => $localized, 'description.en' => 'required|string|max:10000', 'description.ar' => 'required|string|max:10000', 'syllabus' => 'nullable|array:en,ar', 'syllabus.en' => 'nullable|string|max:15000', 'syllabus.ar' => 'nullable|string|max:15000', 'image_url' => 'nullable|string|max:2048'] + $published,
            'course-offerings' => ['course_id' => 'required|integer|exists:courses,id', 'capacity' => 'required|integer|min:1|max:1000', 'price_minor' => 'nullable|integer|min:0|max:999999999', 'registration_opens_at' => 'required|date', 'registration_closes_at' => 'required|date|after:registration_opens_at', 'cancellation_hours' => 'required|integer|min:0|max:8760', 'sessions' => 'required|array|min:1|max:100', 'sessions.*.starts_at' => 'required|date', 'sessions.*.ends_at' => 'required|date|after:sessions.*.starts_at', 'sessions.*.staff_id' => 'nullable|integer|exists:staff,id'] + $published,
            'content-blocks' => ['key' => ['required', Rule::in(['hero_title', 'hero_description', 'introduction', 'footer_story', 'privacy', 'policy', 'seo_title', 'seo_description'])], 'value' => $localized, 'value.en' => 'required|string|max:20000', 'value.ar' => 'required|string|max:20000'] + $published,
            'gallery' => ['image_url' => 'required|string|max:2048', 'alt' => $localized, 'alt.en' => 'required|string|max:500', 'alt.ar' => 'required|string|max:500', 'caption' => 'nullable|array:en,ar', 'caption.en' => 'nullable|string|max:1000', 'caption.ar' => 'nullable|string|max:1000', 'category_id' => 'nullable|integer|exists:service_categories,id'] + $published,
            'settings' => ['key' => ['required', Rule::in(['salon_name', 'logo_url', 'hero_image_url', 'timezone', 'currency', 'country', 'address', 'phone', 'email', 'directions_url', 'instagram_url', 'facebook_url', 'booking_enabled', 'require_approval', 'slot_interval_minutes', 'minimum_notice_minutes', 'booking_horizon_days', 'cancellation_hours', 'reschedule_hours'])], 'value' => 'present'],
            default => [],
        };
    }

    private function setting(array $data): void
    {
        $key = $data['key'];
        $value = $data['value'];
        if ($key === 'phone' && $value) {
            validator(['value' => $value], ['value' => [new JordanPhone]])->validate();

            return;
        }
        $rule = match ($key) {
            'booking_enabled','require_approval' => 'required|boolean','timezone' => ['required', Rule::in(timezone_identifiers_list())],'currency' => 'required|string|regex:/^[A-Z]{3}$/','slot_interval_minutes' => 'required|integer|min:5|max:120','minimum_notice_minutes' => 'required|integer|min:0|max:10080','booking_horizon_days' => 'required|integer|min:1|max:365','cancellation_hours','reschedule_hours' => 'required|integer|min:0|max:8760','email' => 'nullable|email|max:254',default => 'nullable|string|max:2048'
        };
        validator(['value' => $value], ['value' => $rule])->validate();
        if (in_array($key, ['directions_url', 'instagram_url', 'facebook_url'], true) && $value) {
            validator(['value' => $value], ['value' => 'url:http,https'])->validate();
        }
    }

    private function validateMedia(?string $url): void
    {
        if ($url && ! str_starts_with($url, '/storage/media/') && ! preg_match('#^https://#', $url)) {
            throw new DomainConflict('invalid_media', 'Images must use uploaded media or an HTTPS URL.', 422);
        }
    }

    public function store(Request $r, string $resource)
    {
        return $this->write($r, $resource);
    }

    public function update(Request $r, string $resource, int $id)
    {
        return $this->write($r, $resource, $id);
    }

    private function write(Request $r, string $resource, ?int $id = null)
    {
        $class = $this->authorizeResource($r, $resource, true);
        abort_if($resource === 'audit-logs', 403);
        if ($resource === 'appointments') {
            $r->merge(['phone' => JordanPhone::normalize((string) $r->input('phone', ''))]);

            return $this->appointment($r, $id);
        }
        if ($resource === 'registrations') {
            abort_unless($id, 405);
            $d = $r->validate(['status' => 'required|in:pending,confirmed,cancelled,completed,no_show']);
            $record = CourseRegistration::findOrFail($id);
            $result = app(Registrations::class)->updateStatus($record, $d['status'], $r->user()->id);

            return $result;
        }
        $rules = $this->rules($resource);
        if (in_array($resource, ['settings', 'content-blocks'], true)) {
            $rules['key'][] = Rule::unique((new $class)->getTable(), 'key')->ignore($id);
        }
        $data = $r->validate($rules);
        if ($resource === 'settings') {
            if ($data['key'] === 'phone' && $data['value']) {
                $data['value'] = JordanPhone::normalize($data['value']);
            }
            $this->setting($data);
            if (in_array($data['key'], ['booking_enabled', 'require_approval'], true)) {
                $data['value'] = (bool) $data['value'];
            }
        }
        foreach (['image_url', 'logo_url', 'hero_image_url'] as $key) {
            if (isset($data[$key])) {
                $this->validateMedia($data[$key]);
            }
        }
        if ($resource === 'settings' && in_array($data['key'], ['logo_url', 'hero_image_url'], true)) {
            $this->validateMedia($data['value']);
        }
        if ($resource === 'services' && $data['bookable'] && (! $data['duration_minutes'] || $data['price_minor'] === null)) {
            throw new DomainConflict('service_incomplete', 'Bookable services require a duration and price.', 422);
        }

        return DB::transaction(function () use ($r, $resource, $class, $id, $data) {
            SalonCapacity::lock();
            $record = $id ? $class::lockForUpdate()->findOrFail($id) : new $class;
            if ($resource === 'settings' && $id && $record->key !== $data['key']) {
                throw new DomainConflict('immutable_key', 'Create another setting instead of renaming its key.', 422);
            }
            $extras = array_intersect_key($data, array_flip(['staff_ids', 'service_ids', 'sessions']));
            $values = array_diff_key($data, $extras);
            if (in_array($resource, ['working-hours', 'schedule-blocks'], true)) {
                $this->checkScheduleChange($resource, $record, $values);
            }
            if ($resource === 'course-offerings') {
                if ($id && $data['capacity'] < $record->registrations()->whereIn('status', ['pending', 'confirmed'])->count()) {
                    throw new DomainConflict('capacity_below_registrations', 'Capacity cannot be below active registrations.');
                }
                $this->validateSessions($extras['sessions'], $id);
            }
            if ($resource === 'working-hours') {
                $values['staff_id'] = SalonCapacity::resourceId();
            }
            if ($resource === 'schedule-blocks') {
                $values['staff_id'] = $values['kind'] === 'override' ? SalonCapacity::resourceId() : null;
            }
            $record->fill($values);
            $record->save();
            if ($resource === 'services') {
                $record->staff()->sync([SalonCapacity::resourceId()]);
            }
            if ($resource === 'staff' && isset($extras['service_ids'])) {
                $record->services()->sync($extras['service_ids']);
            }
            if ($resource === 'course-offerings') {
                $record->sessions()->delete();
                foreach ($extras['sessions'] as $session) {
                    $record->sessions()->create($session);
                }
            }
            $this->audit($r, $id ? 'update' : 'create', $resource, $record->id);

            return response()->json(['data' => $record], $id ? 200 : 201);
        }, 3);
    }

    private function validateSessions(array $sessions, ?int $offeringId): void
    {
        foreach ($sessions as $i => $s) {
            if (! empty($s['staff_id'])) {
                $overlap = Appointment::where('staff_id', $s['staff_id'])->whereIn('status', ['pending', 'confirmed'])->where('occupied_start', '<', $s['ends_at'])->where('occupied_end', '>', $s['starts_at'])->exists();
                $other = CourseSession::where('staff_id', $s['staff_id'])->when($offeringId, fn ($q) => $q->where('offering_id', '!=', $offeringId))->where('starts_at', '<', $s['ends_at'])->where('ends_at', '>', $s['starts_at'])->exists();
                if ($overlap || $other) {
                    throw new DomainConflict('instructor_conflict', 'The instructor already has a booking or course at that time.');
                }
                foreach (array_slice($sessions, 0, $i) as $previous) {
                    if (($previous['staff_id'] ?? null) === $s['staff_id'] && $previous['starts_at'] < $s['ends_at'] && $previous['ends_at'] > $s['starts_at']) {
                        throw new DomainConflict('instructor_conflict', 'Course sessions overlap.');
                    }
                }
            }
        }
    }

    private function checkScheduleChange(string $resource, $record, array $values): void
    {
        if ($resource === 'schedule-blocks') {
            if ($values['kind'] === 'block' && Appointment::whereIn('status', ['pending', 'confirmed'])->where('occupied_start', '<', $values['ends_at'])->where('occupied_end', '>', $values['starts_at'])->exists()) {
                throw new DomainConflict('schedule_conflict', 'Resolve appointments in this interval before blocking it.');
            }
        }
        if ($resource === 'working-hours' && $record->exists && Appointment::where('staff_id', $record->staff_id)->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>', now())->exists()) {
            throw new DomainConflict('schedule_conflict', 'Resolve future appointments before replacing a working-hours rule. Add new hours without removing existing coverage.');
        }
    }

    private function appointment(Request $r, ?int $id)
    {
        if (! $id) {
            $rules = (new BookingRequest)->rules();
            $rules['locale'] = 'sometimes|in:en,ar';
            unset($rules['policy_accepted']);
            $data = $r->validate($rules);
            $data['locale'] ??= 'en';
            $result = app(Bookings::class)->reserve($data, null, true);
            $this->audit($r, 'create', 'appointments', null);

            return $result;
        }
        $data = $r->validate(['status' => 'required|in:pending,confirmed,completed,cancelled,no_show', 'internal_notes' => 'nullable|string|max:5000', 'starts_at' => ['nullable', 'date', 'regex:/T.*(Z|[+-]\\d{2}:\\d{2})$/'], 'staff_id' => 'nullable|integer|exists:staff,id']);

        return app(AppointmentManagement::class)->update($id, $data, $r->user()->id);
    }

    public function destroy(Request $r, string $resource, int $id)
    {
        $class = $this->authorizeResource($r, $resource, true);
        abort_if(in_array($resource, ['appointments', 'registrations', 'audit-logs', 'settings'], true), 405);

        return DB::transaction(function () use ($r, $resource, $class, $id) {
            SalonCapacity::lock();
            $record = $class::lockForUpdate()->findOrFail($id);
            if (in_array($resource, ['working-hours'], true) && Appointment::whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>', now())->when($resource === 'services', fn ($q) => $q->where('service_id', $id))->when($resource === 'staff', fn ($q) => $q->where('staff_id', $id))->when($resource === 'working-hours', fn ($q) => $q->where('staff_id', $record->staff_id))->exists()) {
                throw new DomainConflict('active_bookings', 'Resolve future bookings before archiving this record.');
            }
            if ($resource === 'course-offerings' && $record->registrations()->whereIn('status', ['pending', 'confirmed'])->exists()) {
                throw new DomainConflict('active_registrations', 'Resolve active registrations before archiving this offering.');
            }
            $record->delete();
            $this->audit($r, 'archive', $resource, $id);

            return ['message' => 'Archived.'];
        }, 3);
    }

    public function statistics(Request $r, Settings $settings)
    {
        abort_unless(app(AdminPolicy::class)->view($r->user()), 403);
        $filter = $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => array_merge(['nullable', 'date_format:Y-m-d'], $r->filled('from') ? ['after_or_equal:from'] : [])]);
        $zone = $settings->get('timezone', 'Asia/Amman');
        $today = CarbonImmutable::today($zone);
        $query = Appointment::query();
        if (! empty($filter['from'])) {
            $query->where('starts_at', '>=', CarbonImmutable::parse($filter['from'], $zone)->utc());
        }
        if (! empty($filter['to'])) {
            $query->where('starts_at', '<', CarbonImmutable::parse($filter['to'], $zone)->addDay()->utc());
        }
        $counts = ['total' => (clone $query)->count(), 'today' => (clone $query)->where('starts_at', '>=', $today->utc())->where('starts_at', '<', $today->addDay()->utc())->count()];
        foreach (['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $status) {
            $counts[$status] = (clone $query)->where('status', $status)->count();
        }
        $counts['upcoming'] = (clone $query)->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>=', now())->count();
        $counts['registrations'] = CourseRegistration::whereIn('status', ['pending', 'confirmed'])->count();
        $revenue = (clone $query)->whereIn('status', ['confirmed', 'completed'])->selectRaw('currency, SUM(price_minor) AS amount_minor')->groupBy('currency')->get()->map(fn ($row) => ['currency' => $row->currency, 'amount_minor' => (int) $row->amount_minor]);

        return ['data' => $counts, 'estimated_revenue' => $revenue, 'timezone' => $zone];
    }

    public function upload(Request $r)
    {
        abort_unless(app(AdminPolicy::class)->manage($r->user(), 'services'), 403);
        $r->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192|dimensions:max_width=6000,max_height=6000']);
        $file = $r->file('image');
        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            abort(422, 'Invalid image.');
        }
        ob_start();
        imagewebp($source, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($source);
        $path = 'media/'.bin2hex(random_bytes(20)).'.webp';
        Storage::disk('public')->put($path, $bytes);
        DB::table('media_assets')->insert(['path' => $path, 'mime' => 'image/webp', 'size' => strlen($bytes), 'user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($r, 'upload', 'media', null);

        return ['url' => '/storage/'.$path];
    }

    private function audit(Request $r, string $action, string $type, ?int $id): void
    {
        AuditLog::create(['user_id' => $r->user()->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes' => ['fields' => array_keys($r->except(['password', 'token']))]]);
    }
}
