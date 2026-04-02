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
        'user_id',
        'activity_title',
        'activity_description',
        'proposed_start_date',
        'proposed_end_date',
        'venue',
        'estimated_budget',
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
