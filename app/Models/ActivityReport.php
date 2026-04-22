<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActivityReport extends Model
{
    protected $fillable = [
        'activity_proposal_id',
        'organization_id',
        'submitted_by',
        'report_submission_date',
        'accomplishment_summary',
        'status',
        'current_approval_step',
        'event_title',
        'event_starts_at',
        'event_ends_at',
        'activity_chairs',
        'prepared_by',
        'program_content',
        'evaluation_report',
        'participants_reached_percent',
    ];

    protected function casts(): array
    {
        return [
            'report_submission_date' => 'date',
            'event_starts_at' => 'datetime',
            'event_ends_at' => 'datetime',
            'activity_proposal_id' => 'integer',
            'submitted_by' => 'integer',
            'current_approval_step' => 'integer',
            'participants_reached_percent' => 'decimal:2',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->activityProposal();
    }

    public function activityProposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'activity_proposal_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->submittedBy();
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function effectiveEventTitle(): ?string
    {
        return $this->event_title;
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function workflowSteps(): MorphMany
    {
        return $this->morphMany(ApprovalWorkflowStep::class, 'approvable')->orderBy('step_order');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }
}
