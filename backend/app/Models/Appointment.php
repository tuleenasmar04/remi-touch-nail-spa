<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'occupied_start' => 'immutable_datetime', 'occupied_end' => 'immutable_datetime', 'service_name' => 'array', 'policy_snapshot' => 'array', 'price_minor' => 'integer'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
