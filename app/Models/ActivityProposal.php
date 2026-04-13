<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ActivityProposal extends Model
{
    protected $fillable = [
        'organization_id',
        'calendar_id',
        'activity_calendar_entry_id',
        'user_id',
        'form_organization_name',
        'organization_logo_path',
        'school_code',
        'department_program',
        'academic_year',
        'activity_title',
        'activity_description',
        'proposed_start_date',
        'proposed_end_date',
        'proposed_time',
        'venue',
        'overall_goal',
        'specific_objectives',
        'criteria_mechanics',
        'program_flow',
        'estimated_budget',
        'source_of_funding',
        'external_funding_support_path',
        'budget_materials_supplies',
        'budget_food_beverage',
        'budget_other_expenses',
        'budget_breakdown_items',
        'resume_resource_persons_path',
        'submission_date',
        'proposal_status',
    ];

    protected function casts(): array
    {
        return [
            'proposed_start_date' => 'date',
            'proposed_end_date' => 'date',
            'submission_date' => 'date',
            'estimated_budget' => 'decimal:2',
            'budget_materials_supplies' => 'decimal:2',
            'budget_food_beverage' => 'decimal:2',
            'budget_other_expenses' => 'decimal:2',
            'budget_breakdown_items' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendar::class, 'calendar_id');
    }

    public function calendarEntry(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendarEntry::class, 'activity_calendar_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvalWorkflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class, 'proposal_id');
    }

    public function activityReport(): HasOne
    {
        return $this->hasOne(ActivityReport::class, 'proposal_id');
    }

    public function communicationThreads(): HasMany
    {
        return $this->hasMany(CommunicationThread::class, 'proposal_id');
    }
}
