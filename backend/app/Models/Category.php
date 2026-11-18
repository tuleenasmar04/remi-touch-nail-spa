<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $table = 'service_categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'published' => 'boolean', 'sort_order' => 'integer'];
    }
}
