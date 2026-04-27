<?php

namespace App\Policies;

use App\Models\OrganizationAdviser;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationSubmission;
use App\Models\User;

/**
 * Authorization rules for OrganizationSubmission and the files attached to it.
 *
 * The "viewFile" ability is the canonical check used by every controller that
 * streams a submission attachment (registration / renewal requirement files,
 * etc.). It is intentionally permissive across the *roles* that have a
 * legitimate reason to see a submission's documents:
 *
 *   - Admin-level roles (admin / SDAO super admin, plus the legacy
 *     "osa_director" / "dean" roles if they exist) can view all submissions.
 *   - Active organization officers can view files of their own organization.
 *   - Active organization advisers (relieved_at IS NULL) can view files of any
 *     organization they currently advise.
 *
 * Anyone else (or guest) is denied. Route middleware is intentionally NOT the
 * sole gatekeeper — this policy is enforced inside controller actions so the
 * rules cannot be bypassed by changing the URL prefix.
 */
class OrganizationSubmissionPolicy
{
    /**
     * Admin-style role names that are allowed to view every submission's files.
     *
     * `admin` is the canonical SDAO admin role used elsewhere in the codebase.
     * The other names are listed for forwards compatibility with role variants
     * the schema may grow into; unknown role names are simply ignored.
     */
    private const ADMIN_ROLE_NAMES = [
        'admin',
        'sdao_admin',
        'sdao_director',
        'osa_director',
        'osa',
        'dean',
    ];

    public function viewFile(User $user, OrganizationSubmission $submission): bool
    {
        if ($this->isAdminRole($user)) {
            return true;
        }

        $organizationId = (int) $submission->organization_id;
        if ($organizationId <= 0) {
            return false;
        }

        if ($this->isActiveOfficerOf($user, $organizationId)) {
            return true;
        }

        if ($this->isActiveAdviserOf($user, $organizationId)) {
            return true;
        }

        return false;
    }

    /**
     * Same rules as viewFile — submissions are only viewable by users with a
     * legitimate organization tie. Kept as a separate ability so callers can
     * grow the rules independently in the future.
     */
    public function view(User $user, OrganizationSubmission $submission): bool
    {
        return $this->viewFile($user, $submission);
    }

    private function isAdminRole(User $user): bool
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        if (method_exists($user, 'isAdminRole') && $user->isAdminRole()) {
            return true;
        }

        $roleName = (string) ($user->role->name ?? '');

        return $roleName !== '' && in_array($roleName, self::ADMIN_ROLE_NAMES, true);
    }

    private function isActiveOfficerOf(User $user, int $organizationId): bool
    {
        return OrganizationOfficer::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    private function isActiveAdviserOf(User $user, int $organizationId): bool
    {
        return OrganizationAdviser::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->whereNull('relieved_at')
            ->exists();
    }
}
