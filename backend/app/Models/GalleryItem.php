<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GalleryItem extends Model
{
    use SoftDeletes;

    protected $table = 'gallery_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['alt' => 'array', 'caption' => 'array', 'published' => 'boolean'];
    }
}
