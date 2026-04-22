<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionRequirement extends Model
{
    public const KEY_BY_TYPE = [
        OrganizationSubmission::TYPE_REGISTRATION => [
            'letter_of_intent',
            'application_form',
            'by_laws',
            'updated_list_of_officers_founders',
            'dean_endorsement_faculty_adviser',
            'proposed_projects_budget',
            'others',
        ],
        OrganizationSubmission::TYPE_RENEWAL => [
            'letter_of_intent',
            'application_form',
            'by_laws_updated_if_applicable',
            'updated_list_of_officers_founders_ay',
            'dean_endorsement_faculty_adviser',
            'proposed_projects_budget',
            'past_projects',
            'financial_statement_previous_ay',
            'evaluation_summary_past_projects',
            'others',
        ],
    ];

    protected $fillable = [
        'submission_id',
        'requirement_key',
        'label',
        'is_submitted',
    ];

    protected function casts(): array
    {
        return [
            'is_submitted' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubmission::class, 'submission_id');
    }

    public static function requirementKeysForType(string $type): array
    {
        return self::KEY_BY_TYPE[$type] ?? [];
    }
}
