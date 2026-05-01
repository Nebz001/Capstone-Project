<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationOfficer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OrganizationOfficerController extends Controller
{
    public function index(Request $request): View
    {
        $context = $this->resolveOfficerContext($request);
        $organization = $context['organization'];
        $currentOfficer = $context['current_officer'];
        $isPresident = $context['is_president'];

        $presidentOfficer = OrganizationOfficer::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(position_title) = ?', ['president'])
            ->where('status', 'active')
            ->latest('id')
            ->first();

        $activeSecretary = $this->activeSecretary($organization->id);
        $inactiveSecretaryHistory = OrganizationOfficer::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(position_title) = ?', ['secretary'])
            ->where('status', 'inactive')
            ->latest('updated_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('organizations.officers.index', [
            'organization' => $organization,
            'currentOfficer' => $currentOfficer,
            'presidentOfficer' => $presidentOfficer,
            'activeSecretary' => $activeSecretary,
            'inactiveSecretaryHistory' => $inactiveSecretaryHistory,
            'canManageSecretary' => $isPresident,
            'isSecretaryViewer' => ! $isPresident && strtolower((string) ($currentOfficer?->position_title ?? '')) === 'secretary',
        ]);
    }

    public function storeSecretary(Request $request): RedirectResponse
    {
        $context = $this->resolveOfficerContext($request);
        $organization = $context['organization'];
        $this->ensurePresidentCanManage($context['is_president']);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($organization, $validated): void {
            if ($this->activeSecretary($organization->id)) {
                throw ValidationException::withMessages([
                    'email' => 'This organization already has an active secretary account.',
                ]);
            }

            $user = $this->resolveOrCreateSecretaryUser($validated);

            OrganizationOfficer::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'position_title' => 'Secretary',
                'status' => 'active',
                'term_start' => now()->toDateString(),
                'term_end' => null,
            ]);
        });

        return back()->with('success', 'Secretary account created and linked to your organization.');
    }

    public function deactivateSecretary(Request $request, OrganizationOfficer $officer): RedirectResponse
    {
        $context = $this->resolveOfficerContext($request);
        $organization = $context['organization'];
        $this->ensurePresidentCanManage($context['is_president']);

        abort_unless((int) $officer->organization_id === (int) $organization->id, 403);
        abort_unless(strtolower((string) $officer->position_title) === 'secretary', 422);
        abort_unless((string) $officer->status === 'active', 422);

        DB::transaction(function () use ($officer): void {
            $officer->update([
                'status' => 'inactive',
                'term_end' => now()->toDateString(),
            ]);

            $hasOtherActiveOfficerLinks = OrganizationOfficer::query()
                ->where('user_id', $officer->user_id)
                ->where('status', 'active')
                ->exists();

            if (! $hasOtherActiveOfficerLinks) {
                $officer->user?->update([
                    'account_status' => 'INACTIVE',
                ]);
            }
        });

        return back()->with('success', 'Secretary account deactivated successfully.');
    }

    public function replaceSecretary(Request $request): RedirectResponse
    {
        $context = $this->resolveOfficerContext($request);
        $organization = $context['organization'];
        $this->ensurePresidentCanManage($context['is_president']);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($organization, $validated): void {
            $activeSecretary = $this->activeSecretary($organization->id);
            if ($activeSecretary) {
                $activeSecretary->update([
                    'status' => 'inactive',
                    'term_end' => now()->toDateString(),
                ]);
            }

            $user = $this->resolveOrCreateSecretaryUser($validated);

            OrganizationOfficer::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'position_title' => 'Secretary',
                'status' => 'active',
                'term_start' => now()->toDateString(),
                'term_end' => null,
            ]);
        });

        return back()->with('success', 'Secretary account replaced successfully.');
    }

    /**
     * @return array{organization: Organization, current_officer: OrganizationOfficer, is_president: bool}
     */
    private function resolveOfficerContext(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->effectiveRoleType() === 'ORG_OFFICER', 403);

        $currentOfficer = OrganizationOfficer::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();
        abort_unless($currentOfficer instanceof OrganizationOfficer, 403, 'You are not allowed to manage officer accounts.');

        $organization = Organization::query()->find($currentOfficer->organization_id);
        abort_unless($organization instanceof Organization, 403, 'You are not allowed to manage officer accounts.');

        return [
            'organization' => $organization,
            'current_officer' => $currentOfficer,
            'is_president' => strtolower((string) $currentOfficer->position_title) === 'president',
        ];
    }

    private function ensurePresidentCanManage(bool $isPresident): void
    {
        if (! $isPresident) {
            abort(403, 'You are not allowed to manage officer accounts.');
        }
    }

    private function activeSecretary(int $organizationId): ?OrganizationOfficer
    {
        return OrganizationOfficer::query()
            ->with('user')
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(position_title) = ?', ['secretary'])
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array{first_name:string,last_name:string,school_id:string,email:string,password:string}  $validated
     */
    private function resolveOrCreateSecretaryUser(array $validated): User
    {
        $email = strtolower(trim((string) $validated['email']));
        $schoolId = trim((string) $validated['school_id']);
        $firstName = trim((string) $validated['first_name']);
        $lastName = trim((string) $validated['last_name']);

        $existingByEmail = User::query()->where('email', $email)->first();
        $existingBySchool = User::query()->where('school_id', $schoolId)->first();

        if ($existingByEmail && $existingBySchool && (int) $existingByEmail->id !== (int) $existingBySchool->id) {
            throw ValidationException::withMessages([
                'email' => 'Email and School ID belong to different existing accounts.',
            ]);
        }

        $user = $existingByEmail ?: $existingBySchool;
        if ($user) {
            $activeOfficerLink = OrganizationOfficer::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
            if ($activeOfficerLink) {
                throw ValidationException::withMessages([
                    'email' => 'The selected user already has an active organization officer assignment.',
                ]);
            }

            $user->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'school_id' => $schoolId,
                'email' => $email,
                'password' => Hash::make((string) $validated['password']),
                'role_type' => 'ORG_OFFICER',
                'role_id' => $this->organizationOfficerRoleId(),
                'account_status' => 'ACTIVE',
                'officer_validation_status' => 'APPROVED',
            ]);

            return $user->fresh();
        }

        return User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'school_id' => $schoolId,
            'email' => $email,
            'password' => Hash::make((string) $validated['password']),
            'role_type' => 'ORG_OFFICER',
            'role_id' => $this->organizationOfficerRoleId(),
            'account_status' => 'ACTIVE',
            'officer_validation_status' => 'APPROVED',
        ]);
    }

    private function organizationOfficerRoleId(): ?int
    {
        $roleId = Role::query()->where('name', 'rso_president')->value('id');

        return $roleId ? (int) $roleId : null;
    }
}

