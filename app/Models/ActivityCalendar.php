<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActivityCalendar extends Model
{
    protected $fillable = [
        'organization_id',
        'submitted_by',
        'academic_term_id',
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
            'submission_date' => 'date',
            'academic_term_id' => 'integer',
            'submitted_by' => 'integer',
            'current_approval_step' => 'integer',
            'admin_field_reviews' => 'array',
            'admin_section_reviews' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class, 'activity_calendar_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ActivityCalendarEntry::class)->orderBy('activity_date')->orderBy('id');
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
