<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrunePrivateData extends Command
{
    protected $signature = 'remi:prune-private-data {--days=30}';

    protected $description = 'Prune expired tokens and old delivered messages/replay responses according to an operator-selected retention period';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if (! $days || $days < 1) {
            return self::FAILURE;
        }
        DB::table('guest_management_tokens')->where('expires_at', '<', now())->delete();
        DB::table('idempotency_records')->where('created_at', '<', now()->subDays($days))->delete();
        DB::table('notification_outbox')->whereIn('status', ['sent', 'logged'])->where('updated_at', '<', now()->subDays($days))->delete();
        $this->info('Expired private data pruned. Customer records, contacts, and booking history were retained.');

        return self::SUCCESS;
    }
}
