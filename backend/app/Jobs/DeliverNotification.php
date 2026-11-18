<?php

namespace App\Jobs;

use App\Models\Outbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $outboxId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $row = Outbox::lockForUpdate()->find($this->outboxId);
            if (! $row || in_array($row->status, ['sent', 'logged'], true)) {
                return;
            }
            $logged = in_array(config('mail.default'), ['log', 'array'], true);
            if (! $logged) {
                Mail::raw($row->body, fn ($m) => $m->to($row->recipient)->subject($row->subject));
            } else {
                Log::info('Notification retained locally; no email sent.', ['outbox_id' => $row->id]);
            }
            $row->update(['status' => $logged ? 'logged' : 'sent', 'attempts' => $this->attempts(), 'sent_at' => $logged ? null : now(), 'last_error' => null]);
            if (str_starts_with($row->event_key, 'contact:')) {
                DB::table('contact_messages')->where('id', (int) substr($row->event_key, 8))->update(['delivery_status' => $logged ? 'logged' : 'sent']);
            }
        });
    }

    public function failed(?\Throwable $error): void
    {
        Outbox::where('id', $this->outboxId)->update(['status' => 'failed', 'last_error' => 'Delivery failed; inspect provider configuration.']);
    }
}
