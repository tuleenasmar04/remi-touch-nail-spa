<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalonCapacity
{
    public static function lock(): Collection
    {
        DB::table('platform_mutexes')->where('name', 'schedule')->lockForUpdate()->firstOrFail();

        return Staff::orderBy('id')->lockForUpdate()->get();
    }

    public static function resourceId(): int
    {
        return (int) Staff::where('is_salon_resource', true)->firstOrFail()->id;
    }
}
