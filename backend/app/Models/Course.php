<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $table = 'courses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'syllabus' => 'array', 'published' => 'boolean'];
    }

    public function offerings()
    {
        return $this->hasMany(CourseOffering::class);
    }
}
