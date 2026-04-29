<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'school_id',
        'email',
        'password',
        'role_id',
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
            'role_id' => 'integer',
            'officer_validated_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function roleDisplayLabel(): string
    {
        return match ($this->effectiveRoleType()) {
            'ORG_OFFICER' => 'Student officer',
            'APPROVER' => 'Signatory',
            'ADMIN' => 'Administrator',
            default => $this->role?->display_name ?? 'Unknown',
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
            $q->whereHas('role', fn (Builder $r) => $r->where('name', 'admin'))
                ->whereIn('email', $emails);
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
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $officer) {
            return null;
        }

        return Organization::query()->find($officer->organization_id);
    }

    public function activityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class, 'submitted_by');
    }

    public function submittedActivityProposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class, 'submitted_by');
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class, 'submitted_by');
    }

    public function submittedActivityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class, 'submitted_by');
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class, 'sent_by');
    }

    public function organizationAdviserAssignments(): HasMany
    {
        return $this->hasMany(OrganizationAdviser::class);
    }

    public function requestedOrganizationProfileRevisions(): HasMany
    {
        return $this->hasMany(OrganizationProfileRevision::class, 'requested_by');
    }

    public function organizationSubmissions(): HasMany
    {
        return $this->hasMany(OrganizationSubmission::class, 'submitted_by');
    }

    public function activityRequestForms(): HasMany
    {
        return $this->hasMany(ActivityRequestForm::class);
    }

    public function submittedActivityRequestForms(): HasMany
    {
        return $this->hasMany(ActivityRequestForm::class, 'submitted_by');
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    public function assignedApprovalWorkflowSteps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'assigned_to');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'actor_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function effectiveRoleType(): ?string
    {
        return match ($this->role?->name) {
            'rso_president' => 'ORG_OFFICER',
            'adviser',
            'program_chair',
            'dean',
            'academic_director',
            'executive_director',
            'sdao_staff' => 'APPROVER',
            'admin' => 'ADMIN',
            default => null,
        };
    }

    /**
     * Canonical redesigned-schema admin-role check (roles.name = admin).
     */
    public function isAdminRole(): bool
    {
        return (string) $this->role?->name === 'admin';
    }

    /**
     * SDAO web admin identity (legacy method name kept for compatibility).
     */
    public function isSdaoAdmin(): bool
    {
        return $this->isAdminRole();
    }

    /**
     * Super admin identity for admin-side modules.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdminRole();
    }

    /**
     * Role-scoped approver accounts that review routed documents (non-SDAO admin).
     */
    public function isRoleBasedApprover(): bool
    {
        return in_array((string) $this->role?->name, ['adviser', 'program_chair', 'dean'], true);
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

        if ($this->effectiveRoleType() !== 'ORG_OFFICER') {
            return false;
        }

        $status = strtoupper((string) ($this->officer_validation_status ?? ''));

        return in_array($status, ['APPROVED', 'ACTIVE'], true);
    }
}
