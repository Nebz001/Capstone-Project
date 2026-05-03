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
        'adviser_name',
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

    public function adviserNominations(): HasMany
    {
        return $this->hasMany(OrganizationAdviser::class, 'submission_id');
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

    /**
     * Renewal admin review synthetic adviser status: approved only when SDAO marked
     * Full Name, School ID, and Email as passed in {@see $renewal_field_reviews} (adviser section).
     *
     * @return 'approved'|'pending'
     */
    public function renewalDerivedAdviserReviewStatus(): string
    {
        $adviserSection = data_get($this->renewal_field_reviews, 'adviser');
        if (! is_array($adviserSection)) {
            return 'pending';
        }
        foreach (['adviser_full_name', 'adviser_school_id', 'adviser_email'] as $key) {
            $st = strtolower(trim((string) data_get($adviserSection, $key.'.status', 'pending')));
            if ($st !== 'passed') {
                return 'pending';
            }
        }

        return 'approved';
    }

    /**
     * Organization-portal renewal detail: same derivation as admin, plus a safe fallback when
     * the submission is already approved and the stored section review marks adviser as verified
     * (avoids stale "Pending" if field-level JSON is incomplete).
     *
     * @return 'approved'|'pending'
     */
    public function renewalPortalDerivedAdviserSdaoStatus(): string
    {
        if ($this->renewalDerivedAdviserReviewStatus() === 'approved') {
            return 'approved';
        }
        if ($this->status === self::STATUS_APPROVED) {
            $sectionStatus = strtolower(trim((string) data_get($this->renewal_section_reviews, 'adviser.status', '')));
            if ($sectionStatus === 'verified') {
                return 'approved';
            }
        }

        return 'pending';
    }
}
