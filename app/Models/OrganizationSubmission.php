<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationSubmission extends Model
{
    use SoftDeletes;

    public const TYPE_REGISTRATION = 'registration';
    public const TYPE_RENEWAL = 'renewal';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVISION = 'revision';

    protected $fillable = [
        'organization_id',
        'submitted_by',
        'academic_term_id',
        'type',
        'contact_person',
        'contact_no',
        'contact_email',
        'submission_date',
        'notes',
        'status',
        'current_approval_step',
        'additional_remarks',
        'approval_decision',
        'registration_field_reviews',
        'registration_section_reviews',
        'renewal_field_reviews',
        'renewal_section_reviews',
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'current_approval_step' => 'integer',
            'registration_field_reviews' => 'array',
            'registration_section_reviews' => 'array',
            'renewal_field_reviews' => 'array',
            'renewal_section_reviews' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function user(): BelongsTo
    {
        return $this->submittedBy();
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(SubmissionRequirement::class, 'submission_id');
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

    public function scopeRegistrations($query)
    {
        return $query->where('type', self::TYPE_REGISTRATION);
    }

    public function scopeRenewals($query)
    {
        return $query->where('type', self::TYPE_RENEWAL);
    }

    public function isRegistration(): bool
    {
        return $this->type === self::TYPE_REGISTRATION;
    }

    public function isRenewal(): bool
    {
        return $this->type === self::TYPE_RENEWAL;
    }

    /**
     * Legacy uppercase status representation for old UI helpers.
     */
    public function legacyStatus(): string
    {
        return match ($this->status) {
            self::STATUS_UNDER_REVIEW => 'UNDER_REVIEW',
            self::STATUS_APPROVED => 'APPROVED',
            self::STATUS_REJECTED => 'REJECTED',
            self::STATUS_REVISION => 'REVISION',
            self::STATUS_DRAFT => 'DRAFT',
            default => 'PENDING',
        };
    }
}
