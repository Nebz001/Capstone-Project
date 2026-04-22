<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'approval_level',
    ];

    public function approvalWorkflowSteps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
