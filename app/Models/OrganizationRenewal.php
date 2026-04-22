<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrganizationRenewal extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'academic_year',
        'contact_person',
        'contact_no',
        'contact_email',
        'submission_date',
        'renewal_document',
        'renewal_notes',
        'renewal_status',
        // requirements checklist
        'req_letter_of_intent',
        'req_application_form',
        'req_by_laws',
        'req_officers_list',
        'req_dean_endorsement',
        'req_proposed_projects',
        'req_past_projects',
        'req_financial_statement',
        'req_evaluation_summary',
        'req_others',
        'req_others_specify',
        'requirement_files',
        // endorsement
        'endorsed_by_adviser',
        'endorsed_by_dean',
        'received_by_sdao',
        'received_by_crso',
        'endorsement_date',
        'endorsement_time',
        // approval
        'approval_decision',
        'approved_by_sdao',
        'approved_by_crso',
        'approval_date',
        'additional_remarks',
    ];

    protected function casts(): array
    {
        return [
            'submission_date'        => 'date',
            'endorsement_date'       => 'date',
            'approval_date'          => 'date',
            'req_letter_of_intent'   => 'boolean',
            'req_application_form'   => 'boolean',
            'req_by_laws'            => 'boolean',
            'req_officers_list'      => 'boolean',
            'req_dean_endorsement'   => 'boolean',
            'req_proposed_projects'  => 'boolean',
            'req_past_projects'      => 'boolean',
            'req_financial_statement' => 'boolean',
            'req_evaluation_summary' => 'boolean',
            'req_others'             => 'boolean',
            'requirement_files'      => 'array',
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

    public function workflowSteps(): MorphMany
    {
        return $this->morphMany(ApprovalWorkflowStep::class, 'approvable')->orderBy('step_order');
    }

    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable');
    }
}
