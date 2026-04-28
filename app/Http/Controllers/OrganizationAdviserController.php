<?php

namespace App\Http\Controllers;

use App\Models\OrganizationAdviser;
use App\Models\OrganizationSubmission;
use App\Models\User;
use App\Services\OrganizationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationAdviserController extends Controller
{
    private const ADVISER_ROLE_NAMES = ['adviser'];

    public function searchAdvisers(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if ($query === '') {
            return response()->json([]);
        }

        $rows = User::query()
            ->whereHas('role', function ($roleQuery): void {
                $roleQuery->whereIn('name', self::ADVISER_ROLE_NAMES);
            })
            ->where(function ($q) use ($query): void {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('school_id', 'like', "%{$query}%");
            })
            ->select('id', 'first_name', 'last_name', 'email', 'school_id')
            ->limit(10)
            ->get()
            ->map(function (User $user): array {
                $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

                return [
                    'id' => (int) $user->id,
                    'full_name' => $fullName !== '' ? $fullName : ($user->email ?? 'Adviser'),
                    'first_name' => (string) ($user->first_name ?? ''),
                    'last_name' => (string) ($user->last_name ?? ''),
                    'email' => (string) ($user->email ?? ''),
                    'school_id' => (string) ($user->school_id ?? ''),
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function reviewAdviser(Request $request, OrganizationAdviser $adviser): RedirectResponse
    {
        abort_unless($request->user()?->isSdaoAdmin(), 403);

        $validated = $request->validate([
            'action' => ['required', 'in:approved,rejected'],
            'rejection_notes' => ['required_if:action,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        $action = (string) $validated['action'];
        $rejectionNotes = trim((string) ($validated['rejection_notes'] ?? ''));

        $adviser->update([
            'status' => $action,
            'reviewed_by' => (int) $request->user()->id,
            'reviewed_at' => now(),
            'rejection_notes' => $action === 'rejected' ? $rejectionNotes : null,
            'relieved_at' => $action === 'approved' ? null : $adviser->relieved_at,
        ]);

        $submission = $adviser->submission;
        if ($submission instanceof OrganizationSubmission && $submission->submittedBy) {
            $title = $action === 'approved' ? 'Faculty Adviser Approved' : 'Faculty Adviser Rejected';
            $message = $action === 'approved'
                ? 'Your nominated faculty adviser has been approved.'
                : 'Your nominated faculty adviser was rejected. Reason: '.$rejectionNotes.'. Please nominate a new adviser.';

            app(OrganizationNotificationService::class)->createForUser(
                $submission->submittedBy,
                $title,
                $message,
                'info',
                $submission->isRenewal()
                    ? route('organizations.submitted-documents.renewals.show', $submission)
                    : route('organizations.submitted-documents.registrations.show', $submission),
                $adviser
            );
        }

        return back()->with('success', 'Adviser '.($action === 'approved' ? 'approved' : 'rejected').' successfully.');
    }

    public function renominateForSubmission(Request $request, OrganizationSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()?->effectiveRoleType() === 'ORG_OFFICER', 403);
        abort_unless((int) $submission->organization_id === (int) ($request->user()?->currentOrganization()?->id ?? 0), 403);

        $validated = $request->validate([
            'adviser_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $adviserUserId = (int) $validated['adviser_user_id'];
        $eligible = User::query()
            ->whereKey($adviserUserId)
            ->whereHas('role', fn ($query) => $query->whereIn('name', self::ADVISER_ROLE_NAMES))
            ->exists();
        if (! $eligible) {
            return back()->withErrors([
                'adviser_user_id' => 'Selected faculty adviser is not eligible.',
            ])->withInput();
        }

        $latest = OrganizationAdviser::query()
            ->where('organization_id', $submission->organization_id)
            ->where('submission_id', (int) $submission->id)
            ->latest('id')
            ->first();
        if ($latest && $latest->status === 'rejected' && $latest->relieved_at === null) {
            $latest->update(['relieved_at' => now()->toDateString()]);
        }

        $nomination = OrganizationAdviser::query()->create([
            'organization_id' => (int) $submission->organization_id,
            'submission_id' => (int) $submission->id,
            'user_id' => $adviserUserId,
            'assigned_at' => now()->toDateString(),
            'status' => 'pending',
            'relieved_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_notes' => null,
        ]);

        $submission->update([
            'adviser_name' => $nomination->user?->full_name ?: trim((string) ($nomination->user?->first_name ?? '').' '.(string) ($nomination->user?->last_name ?? '')),
        ]);

        return back()->with('success', 'New faculty adviser nomination submitted for review.');
    }
}

