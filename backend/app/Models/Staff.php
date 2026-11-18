<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use SoftDeletes;

    protected $table = 'staff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'published' => 'boolean'];
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }
}
