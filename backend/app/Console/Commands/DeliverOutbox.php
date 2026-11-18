<?php

namespace App\Console\Commands;

use App\Jobs\DeliverNotification;
use App\Models\Outbox;
use Illuminate\Console\Command;

class DeliverOutbox extends Command
{
    protected $signature = 'remi:deliver-outbox';

    protected $description = 'Deliver pending notification records with bounded retries';

    public function handle(): int
    {
        Outbox::where('status', 'pending')->orderBy('id')->limit(100)->pluck('id')->each(fn ($id) => DeliverNotification::dispatch($id)->afterCommit());

        return self::SUCCESS;
    }
}
