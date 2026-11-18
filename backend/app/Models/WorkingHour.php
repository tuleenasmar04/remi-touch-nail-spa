<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingHour extends Model
{
    protected $table = 'working_hours';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
