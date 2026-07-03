<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareerRole extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'required_skills',
        'weeks_template',
    ];

    protected function casts(): array
    {
        return [
            'required_skills' => 'array',
        ];
    }

    public function careerGoals(): HasMany
    {
        return $this->hasMany(UserCareerGoal::class);
    }
}
