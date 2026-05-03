<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Organization extends Model
{
    protected $fillable = [
        'organization_name',
        'acronym',
        'organization_type',
        'college_department',
        'purpose',
        'logo_path',
        'founded_date',
        'status',
        'is_profile_locked',
    ];

    protected function casts(): array
    {
        return [
            'founded_date' => 'date',
            'is_profile_locked' => 'boolean',
            'status' => 'string',
        ];
    }

    /**
     * Canonical status for comparisons and UI (matches DB enum values, case-safe).
     */
    public function normalizedOrganizationStatus(): string
    {
        $raw = $this->status;

        if ($raw === null || $raw === '') {
            return 'PENDING';
        }

        return strtoupper((string) $raw);
    }

    public function isOrganizationPending(): bool
    {
        return $this->normalizedOrganizationStatus() === 'PENDING';
    }

    /**
     * An organization is considered accredited/active when SDAO has approved its latest registration.
     * Treat ACTIVE as the canonical approved state; `suspended` and `inactive` revoke access to
     * submission workflows that require an active registration.
     */
    public function isApprovedOrganization(): bool
    {
        return $this->normalizedOrganizationStatus() === 'ACTIVE';
    }

    /**
     * True when the organization has at least one registration submission that SDAO has approved.
     * Renewal eligibility and activity submissions should key off this, not just `status`,
     * because `status` may still be `pending` on a freshly created org whose registration has
     * not yet been approved, and PostgreSQL enum comparisons are case-sensitive.
     */
    public function hasApprovedRegistration(): bool
    {
        return $this->submissions()
            ->where('type', OrganizationSubmission::TYPE_REGISTRATION)
            ->where('status', OrganizationSubmission::STATUS_APPROVED)
            ->exists();
    }

    /**
     * SDAO has asked the officer to update organization profile fields (separate from accreditation status).
     */
    public function isProfileRevisionRequested(): bool
    {
        return $this->profileRevisions()
            ->where('status', 'open')
            ->exists();
    }

    /**
     * Officers may edit organization profile only when SDAO has opened a profile revision window.
     * Revision must take precedence over "pending" accreditation so corrections can be made when requested.
     */
    public function canEditProfile(): bool
    {
        $user = auth()->user();
        if ($user && $user->isSuperAdmin()) {
            $picked = (int) request()->integer('organization_id');
            if ($picked > 0 && $picked === (int) $this->id) {
                return true;
            }
        }

        if ($this->isProfileRevisionRequested()) {
            return true;
        }

        if ($this->isOrganizationPending()) {
            return false;
        }

        return false;
    }

    public function profileEditBlockedMessage(): string
    {
        $user = auth()->user();
        if ($user && $user->isSuperAdmin()) {
            $picked = (int) request()->integer('organization_id');
            if ($picked > 0 && $picked === (int) $this->id) {
                return '';
            }
        }

        if ($this->isProfileRevisionRequested()) {
            return '';
        }

        if ($this->isOrganizationPending()) {
            return 'Profile editing is unavailable while your organization is under pending review, unless SDAO requests profile updates.';
        }

        return 'You can only edit this profile when SDAO requests revisions to your organization information.';
    }

    public function officers(): HasMany
    {
        return $this->hasMany(OrganizationOfficer::class);
    }

    public function activityCalendars(): HasMany
    {
        return $this->hasMany(ActivityCalendar::class);
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class);
    }

    public function communicationThreads(): HasMany
    {
        return $this->hasMany(CommunicationThread::class);
    }

    public function advisers(): HasMany
    {
        return $this->hasMany(OrganizationAdviser::class);
    }

    public function currentAdviser(): HasOne
    {
        return $this->hasOne(OrganizationAdviser::class)
            ->where(function ($query): void {
                $query->whereRaw('LOWER(status) = ?', ['approved'])
                    ->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->whereNull('relieved_at')
            ->latestOfMany('id');
    }

    public function profileRevisions(): HasMany
    {
        return $this->hasMany(OrganizationProfileRevision::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(OrganizationSubmission::class);
    }

    public function approvedInTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'approved_in_term_id');
    }

    public function validUntilTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'valid_until_term_id');
    }

    public function isEligibleForRenewal(): bool
    {
        if ($this->normalizedOrganizationStatus() !== 'ACTIVE') {
            return false;
        }

        if (SystemSetting::activeSemester() !== 'term_1') {
            return false;
        }

        $currentAcademicYear = SystemSetting::activeAcademicYear();
        $currentStartYear = $this->academicYearStart($currentAcademicYear);
        if ($currentStartYear === null) {
            return false;
        }

        $approvalStartYear = $this->approvedAcademicYearStartForRenewal();
        if ($approvalStartYear === null) {
            return false;
        }

        // Renewal window opens only when the academic year advances.
        if ($currentStartYear <= $approvalStartYear) {
            return false;
        }

        $alreadyRenewedThisAcademicYear = $this->submissions()
            ->renewals()
            ->whereHas('academicTerm', fn ($q) => $q->where('academic_year', $currentAcademicYear))
            ->whereNotIn('status', [OrganizationSubmission::STATUS_REJECTED])
            ->exists();
        if ($alreadyRenewedThisAcademicYear) {
            return false;
        }

        $hasActiveRenewalInProgress = $this->submissions()
            ->renewals()
            ->whereIn('status', [
                OrganizationSubmission::STATUS_DRAFT,
                OrganizationSubmission::STATUS_PENDING,
                OrganizationSubmission::STATUS_UNDER_REVIEW,
            ])
            ->exists();

        return ! $hasActiveRenewalInProgress;
    }

    public function renewalIneligibilityReason(): ?string
    {
        if ($this->normalizedOrganizationStatus() !== 'ACTIVE') {
            return 'Only approved and active organizations can submit a renewal.';
        }

        if (SystemSetting::activeSemester() !== 'term_1') {
            return 'Organization renewal is only available during the 1st Term.';
        }

        $currentAcademicYear = SystemSetting::activeAcademicYear();
        $currentStartYear = $this->academicYearStart($currentAcademicYear);
        if ($currentStartYear === null) {
            return 'No active academic term found.';
        }

        $approvalAcademicYear = $this->approvedAcademicYearForRenewal();
        $approvalStartYear = $this->academicYearStart($approvalAcademicYear);
        if ($approvalStartYear === null) {
            return 'Renew Organization becomes available only after your organization has an approved registration on file. Please wait for SDAO to approve your registration first.';
        }

        if ($currentStartYear <= $approvalStartYear) {
            return 'You cannot renew yet because your organization was newly registered this academic year. Renewal will be available in the 1st Term of the next academic year.';
        }

        $alreadyRenewedThisAcademicYear = $this->submissions()
            ->renewals()
            ->whereHas('academicTerm', fn ($q) => $q->where('academic_year', $currentAcademicYear))
            ->whereNotIn('status', [OrganizationSubmission::STATUS_REJECTED])
            ->exists();
        if ($alreadyRenewedThisAcademicYear) {
            return 'Your organization has already submitted a renewal for this academic year.';
        }

        $hasActiveRenewalInProgress = $this->submissions()
            ->renewals()
            ->whereIn('status', [
                OrganizationSubmission::STATUS_DRAFT,
                OrganizationSubmission::STATUS_PENDING,
                OrganizationSubmission::STATUS_UNDER_REVIEW,
            ])
            ->exists();
        if ($hasActiveRenewalInProgress) {
            return 'You already have a renewal submission in progress.';
        }

        return null;
    }

    private function approvedAcademicYearForRenewal(): ?string
    {
        if (Schema::hasColumn($this->getTable(), 'approved_in_term_id')) {
            $approvedInTermId = (int) ($this->getAttribute('approved_in_term_id') ?? 0);
            if ($approvedInTermId > 0) {
                $term = $this->relationLoaded('approvedInTerm')
                    ? $this->getRelation('approvedInTerm')
                    : $this->approvedInTerm()->first();
                $academicYear = trim((string) ($term?->academic_year ?? ''));
                if ($academicYear !== '') {
                    return $academicYear;
                }
            }
        }

        $latestApprovedRegistration = $this->submissions()
            ->registrations()
            ->where('status', OrganizationSubmission::STATUS_APPROVED)
            ->whereNotNull('academic_term_id')
            ->with('academicTerm:id,academic_year')
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $academicYear = trim((string) ($latestApprovedRegistration?->academicTerm?->academic_year ?? ''));

        return $academicYear !== '' ? $academicYear : null;
    }

    private function approvedAcademicYearStartForRenewal(): ?int
    {
        return $this->academicYearStart($this->approvedAcademicYearForRenewal());
    }

    private function academicYearStart(?string $academicYear): ?int
    {
        $value = trim((string) $academicYear);
        if (preg_match('/^(\d{4})-\d{4}$/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    public function activityRequestForms(): HasMany
    {
        return $this->hasMany(ActivityRequestForm::class);
    }
}
