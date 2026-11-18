<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentBlock extends Model
{
    use SoftDeletes;

    protected $table = 'content_blocks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'array', 'published' => 'boolean'];
    }
}
