<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActivityRequestForm extends Model
{
    protected $fillable = [
        'organization_id',
        'submitted_by',
        'rso_name',
        'activity_calendar_entry_id',
        'promoted_to_proposal_id',
        'promoted_at',
        'activity_title',
        'partner_entities',
        'nature_of_activity',
        'nature_other',
        'activity_types',
        'activity_type_other',
        'target_sdg',
        'proposed_budget',
        'budget_source',
        'activity_date',
        'venue',
    ];

    protected function casts(): array
    {
        return [
            'nature_of_activity' => 'array',
            'activity_types' => 'array',
            'activity_date' => 'date',
            'proposed_budget' => 'decimal:2',
            'promoted_at' => 'datetime',
        ];
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

    public function activityCalendarEntry(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendarEntry::class, 'activity_calendar_entry_id');
    }

    public function promotedToProposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'promoted_to_proposal_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
