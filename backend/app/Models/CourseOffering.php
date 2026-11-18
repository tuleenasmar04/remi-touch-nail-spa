<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOffering extends Model
{
    use SoftDeletes;

    protected $table = 'course_offerings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'registration_opens_at' => 'immutable_datetime', 'registration_closes_at' => 'immutable_datetime', 'price_minor' => 'integer', 'capacity' => 'integer'];
    }

    public function sessions()
    {
        return $this->hasMany(CourseSession::class, 'offering_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function registrations()
    {
        return $this->hasMany(CourseRegistration::class, 'offering_id');
    }
}
