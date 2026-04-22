<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'step_order',
        'role_id',
        'assigned_to',
        'status',
        'is_current_step',
        'review_comments',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'role_id' => 'integer',
            'assigned_to' => 'integer',
            'is_current_step' => 'boolean',
            'acted_at' => 'datetime',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'workflow_step_id');
    }
}
