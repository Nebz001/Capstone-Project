<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalFieldReview extends Model
{
    protected $fillable = [
        'activity_proposal_id',
        'workflow_step_id',
        'reviewer_id',
        'field_key',
        'field_label',
        'status',
        'comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'activity_proposal_id' => 'integer',
            'workflow_step_id' => 'integer',
            'reviewer_id' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'activity_proposal_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
