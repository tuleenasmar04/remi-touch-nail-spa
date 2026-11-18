<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $table = 'services';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'published' => 'boolean', 'bookable' => 'boolean', 'price_minor' => 'integer', 'duration_minutes' => 'integer', 'buffer_before' => 'integer', 'buffer_after' => 'integer'];
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
