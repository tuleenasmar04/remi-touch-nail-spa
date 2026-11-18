<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseRegistration extends Model
{
    protected $table = 'course_registrations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['course_title' => 'array', 'policy_snapshot' => 'array', 'price_minor' => 'integer'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function offering()
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id')->withTrashed();
    }
}
