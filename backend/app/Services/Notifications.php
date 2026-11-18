<?php

namespace App\Services;

use App\Models\CourseRegistration;
use App\Models\Outbox;

class Notifications
{
    public function record($record, string $event, ?string $token = null): void
    {
        if (! $record->customer->email) {
            return;
        }
        $ar = $record->locale === 'ar';
        $subject = $ar ? 'تحديث حجزكِ لدى REMI Nail Spa' : 'Your REMI Nail Spa reservation';
        $statuses = ['pending' => 'بانتظار الموافقة', 'confirmed' => 'مؤكد', 'cancelled' => 'ملغى', 'completed' => 'مكتمل', 'no_show' => 'لم تحضر'];
        $body = ($ar ? 'مرجع الحجز: ' : 'Booking reference: ').$record->reference."\n".($ar ? 'الحالة: ' : 'Status: ').($ar ? ($statuses[$record->status] ?? $record->status) : $record->status);
        $name = $record->service_name ?? $record->course_title;
        if ($name) {
            $body .= "\n".($name[$record->locale] ?? $name['en']);
        }
        if ($record instanceof CourseRegistration) {
            foreach ($record->offering->sessions as $session) {
                $body .= "\n".$session->starts_at->setTimezone(app(Settings::class)->get('timezone', 'UTC'))->format('Y-m-d H:i T');
            }
        }
        if ($record->starts_at) {
            $body .= "\n".$record->starts_at->setTimezone(app(Settings::class)->get('timezone', 'UTC'))->format('Y-m-d H:i T');
        }
        if ($token) {
            $body .= "\n".rtrim(config('app.frontend_url'), '/').'/'.$record->locale.'/manage#token='.rawurlencode($token);
        }
        Outbox::create(['event_key' => $record->getTable().':'.$record->id.':'.$event.':'.bin2hex(random_bytes(8)), 'recipient' => $record->customer->email, 'locale' => $record->locale, 'subject' => $subject, 'body' => $body]);
    }
}
