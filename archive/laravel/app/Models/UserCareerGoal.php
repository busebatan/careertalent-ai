<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCareerGoal extends Model
{
    protected $fillable = ['user_id', 'career_role_id', 'aspiration_note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careerRole(): BelongsTo
    {
        return $this->belongsTo(CareerRole::class);
    }
}
