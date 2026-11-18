<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['marketing_consent' => 'boolean'];
    }
}
