<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'workflow_step_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'comments',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'workflow_step_id' => 'integer',
            'actor_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
