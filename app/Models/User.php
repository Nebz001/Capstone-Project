<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'school_id',
        'email',
        'password',
        'role_type',
        'account_status',
        'officer_validation_status',
        'officer_validation_notes',
        'officer_validated_at',
        'officer_validated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'officer_validated_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // ── Relationships ──────────────────────────────────────────

    public function organizationOfficers(): HasMany
    {
        return $this->hasMany(OrganizationOfficer::class);
    }

    /**
     * Resolve the organization this user belongs to via their active officer record.
     */
    public function currentOrganization(): ?Organization
    {
        return $this->organizationOfficers()
            ->where('officer_status', 'ACTIVE')
            ->with('organization')
            ->first()
            ?->organization;
    }

    public function organizationRegistrations(): HasMany
    {
        return $this->hasMany(OrganizationRegistration::class);
    }

    public function organizationRenewals(): HasMany
    {
        return $this->hasMany(OrganizationRenewal::class);
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class);
    }

    public function approvalWorkflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class);
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_validated_by');
    }

    /**
     * Whether this user is an SDAO web admin (dashboard / signatory flows).
     * Requires ADMIN role and an email listed in config/sdao.php.
     */
    public function isSdaoAdmin(): bool
    {
        if ($this->role_type !== 'ADMIN') {
            return false;
        }

        $emails = collect(config('sdao.admin_accounts', []))
            ->pluck('email')
            ->all();

        return in_array($this->email, $emails, true);
    }

    public function isOfficerValidated(): bool
    {
        return $this->role_type === 'ORG_OFFICER' && $this->officer_validation_status === 'APPROVED';
    }
}
