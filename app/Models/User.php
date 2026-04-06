<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Human-readable account role for admin listings (maps users.role_type).
     */
    public function roleDisplayLabel(): string
    {
        return match ($this->role_type) {
            'ORG_OFFICER' => 'Student officer',
            'APPROVER' => 'Signatory',
            'ADMIN' => 'Administrator',
            default => $this->role_type ?? 'Unknown',
        };
    }

    /**
     * Exclude SDAO dashboard accounts (config/sdao.php admin_accounts) from general user listings.
     */
    public function scopeWithoutSdaoAdminAccounts(Builder $query): void
    {
        $emails = collect(config('sdao.admin_accounts', []))
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        if ($emails === []) {
            return;
        }

        $query->whereNot(function (Builder $q) use ($emails): void {
            $q->where('role_type', 'ADMIN')->whereIn('email', $emails);
        });
    }

    // ── Relationships ──────────────────────────────────────────

    public function organizationOfficers(): HasMany
    {
        return $this->hasMany(OrganizationOfficer::class);
    }

    /**
     * Resolve the organization this user belongs to via their active officer record.
     * Loads the organization row directly from the database so status and profile fields
     * always reflect the latest saved values (not a stale relation instance).
     */
    public function currentOrganization(): ?Organization
    {
        $officer = $this->organizationOfficers()
            ->where('officer_status', 'ACTIVE')
            ->orderByDesc('id')
            ->first();

        if (! $officer) {
            return null;
        }

        return Organization::query()->find($officer->organization_id);
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

    /** Announcements this user has dismissed after viewing (login modal). */
    public function dismissedAnnouncements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class)->withTimestamps();
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

    /**
     * SDAO Super Admin: full admin access plus admin-side RSO submission routes (see admin.submissions.*).
     * Same identity as {@see isSdaoAdmin()} (ADMIN + config/sdao.php admin_accounts).
     */
    public function isSuperAdmin(): bool
    {
        return $this->isSdaoAdmin();
    }

    /**
     * Officer is cleared for org-level actions when validation is Approved or Active
     * (DB may use either value depending on workflow).
     */
    public function isOfficerValidated(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->role_type !== 'ORG_OFFICER') {
            return false;
        }

        $status = strtoupper((string) ($this->officer_validation_status ?? ''));

        return in_array($status, ['APPROVED', 'ACTIVE'], true);
    }
}
