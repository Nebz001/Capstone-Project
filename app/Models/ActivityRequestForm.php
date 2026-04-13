<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRequestForm extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'activity_calendar_entry_id',
        'rso_name',
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
        'request_letter_has_rationale',
        'request_letter_has_objectives',
        'request_letter_has_program',
        'request_letter_path',
        'speaker_resume_path',
        'post_survey_form_path',
        'used_for_proposal_at',
    ];

    protected function casts(): array
    {
        return [
            'nature_of_activity' => 'array',
            'activity_types' => 'array',
            'activity_date' => 'date',
            'proposed_budget' => 'decimal:2',
            'request_letter_has_rationale' => 'boolean',
            'request_letter_has_objectives' => 'boolean',
            'request_letter_has_program' => 'boolean',
            'used_for_proposal_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityCalendarEntry(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendarEntry::class, 'activity_calendar_entry_id');
    }
}
