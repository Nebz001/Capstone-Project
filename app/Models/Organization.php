<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function profileRevisions(): HasMany
    {
        return $this->hasMany(OrganizationProfileRevision::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(OrganizationSubmission::class);
    }

    public function activityRequestForms(): HasMany
    {
        return $this->hasMany(ActivityRequestForm::class);
    }
}
