<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActivityProposal extends Model
{
    protected $fillable = [
        'organization_id',
        'activity_calendar_id',
        'activity_calendar_entry_id',
        'submitted_by',
        'academic_term_id',
        'school_code',
        'program',
        'activity_title',
        'activity_description',
        'proposed_start_date',
        'proposed_end_date',
        'proposed_start_time',
        'proposed_end_time',
        'venue',
        'overall_goal',
        'specific_objectives',
        'criteria_mechanics',
        'program_flow',
        'target_sdg',
        'estimated_budget',
        'source_of_funding',
        'submission_date',
        'status',
        'current_approval_step',
        'admin_field_reviews',
        'admin_section_reviews',
        'admin_review_remarks',
    ];

    protected function casts(): array
    {
        return [
            'proposed_start_date' => 'date',
            'proposed_end_date' => 'date',
            'submission_date' => 'date',
            'academic_term_id' => 'integer',
            'school_code' => 'string',
            'activity_calendar_id' => 'integer',
            'activity_calendar_entry_id' => 'integer',
            'submitted_by' => 'integer',
            'current_approval_step' => 'integer',
            'estimated_budget' => 'decimal:2',
            'admin_field_reviews' => 'array',
            'admin_section_reviews' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function calendar(): BelongsTo
    {
        return $this->activityCalendar();
    }

    public function activityCalendar(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendar::class, 'activity_calendar_id');
    }

    public function calendarEntry(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendarEntry::class, 'activity_calendar_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->submittedBy();
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function activityReport(): HasOne
    {
        return $this->hasOne(ActivityReport::class, 'activity_proposal_id');
    }

    public function redesignedActivityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class, 'activity_proposal_id');
    }

    public function communicationThreads(): HasMany
    {
        return $this->hasMany(CommunicationThread::class, 'subject_id')
            ->where('subject_type', self::class);
    }

    public function promotedFromRequestForms(): HasMany
    {
        return $this->hasMany(ActivityRequestForm::class, 'promoted_to_proposal_id');
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(ProposalBudgetItem::class, 'activity_proposal_id');
    }

    public function fieldReviews(): HasMany
    {
        return $this->hasMany(ProposalFieldReview::class, 'activity_proposal_id');
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
