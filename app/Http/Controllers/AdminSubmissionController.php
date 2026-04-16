<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSubmissionController extends Controller
{
    public function showRegistration(Request $request): View
    {
        $this->authorizeSdaoAdmin($request);

        return view('organizations.register', [
            'layout' => 'layouts.admin',
            'showPageIntro' => false,
            'registerStoreRoute' => 'admin.submissions.register.store',
            'backRoute' => route('admin.dashboard'),
            'backLabel' => 'Back to Admin Dashboard',
            'pageTitle' => 'Register Organization',
            'pageHeading' => 'Register Organization',
            'pageSubheading' => 'File a new student organization registration from the SDAO admin portal. The submission is recorded under your admin account.',
            'officerValidationPending' => false,
            'alreadyLinkedToOrganization' => false,
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $this->authorizeSdaoAdmin($request);

        return app(OrganizationController::class)->storeRegistrationForAdmin($request);
    }

    public function showRenew(Request $request): View
    {
        $this->authorizeSdaoAdmin($request);

        return view('organizations.renew', [
            'layout' => 'layouts.admin',
            'showPageIntro' => false,
            'renewStoreRoute' => 'admin.submissions.renew.store',
            'backRoute' => route('admin.dashboard'),
            'submissionContext' => 'admin',
            'pageHeading' => 'Renew Organization',
            'pageSubheading' => 'Submit a renewal from the admin portal. Enter the registered organization name exactly as it appears in the directory. The submission is recorded under your admin account.',
            'organization' => null,
            'schoolCodeDefault' => null,
            'officerValidationPending' => false,
            'renewalBlockedNoOrganization' => false,
        ]);
    }

    public function storeRenew(Request $request): RedirectResponse
    {
        $this->authorizeSdaoAdmin($request);

        return app(OrganizationController::class)->storeRenewal($request);
    }

    public function showActivityCalendar(Request $request): View
    {
        $this->authorizeSdaoAdmin($request);

        $organization = null;
        $lookupOrganizationNameError = null;

        if ($request->filled('lookup_organization_name')) {
            $organization = app(OrganizationController::class)
                ->resolveOrganizationByRegisteredName($request->string('lookup_organization_name')->toString());
            if (! $organization) {
                $lookupOrganizationNameError = 'No registered organization matches this name.';
            }
        }

        $latestCalendar = null;
        $calendarSubmittedLocked = false;

        if ($organization) {
            $latestCalendar = $organization->activityCalendars()
                ->with([
                    'entries' => fn ($query) => $query->orderBy('activity_date')->orderBy('id'),
                ])
                ->latest('submission_date')
                ->latest('id')
                ->first();

            $calendarSubmittedLocked = $latestCalendar !== null
                && strtoupper((string) $latestCalendar->calendar_status) !== 'REVISION';
        }

        return view('organizations.activity-calendar-submission', [
            'layout' => 'layouts.admin',
            'showPageIntro' => false,
            'submissionContext' => 'admin',
            'calendarStoreRoute' => 'admin.submissions.activity-calendar.store',
            'backRoute' => route('admin.dashboard'),
            'pageTitle' => 'Submit Activity Calendar',
            'pageHeading' => 'Submit Activity Calendar',
            'pageSubheading' => 'Add a term activity calendar from the admin portal. The RSO name on the form must match a registered organization. The submission is recorded under your admin account.',
            'lookupOrganizationName' => $request->query('lookup_organization_name', ''),
            'lookupOrganizationNameError' => $lookupOrganizationNameError,
            'organization' => $organization,
            'latestCalendar' => $latestCalendar,
            'calendarSubmittedLocked' => $calendarSubmittedLocked,
            'officerValidationPending' => false,
        ]);
    }

    public function storeActivityCalendar(Request $request): RedirectResponse
    {
        $this->authorizeSdaoAdmin($request);

        return app(OrganizationController::class)->storeActivityCalendarSubmission($request);
    }

    public function showActivityProposal(Request $request): View|RedirectResponse
    {
        $this->authorizeSdaoAdmin($request);

        $organization = null;
        $lookupOrganizationNameError = null;

        if ($request->filled('lookup_organization_name')) {
            $organization = app(OrganizationController::class)
                ->resolveOrganizationByRegisteredName($request->string('lookup_organization_name')->toString());
            if (! $organization) {
                $lookupOrganizationNameError = 'No registered organization matches this name.';
            }
        }

        $adminExtras = [
            'layout' => 'layouts.admin',
            'showPageIntro' => false,
            'submissionContext' => 'admin',
            'proposalStoreRoute' => 'admin.submissions.activity-proposal.store',
            'activityProposalGetRoute' => 'admin.submissions.activity-proposal',
            'backRoute' => route('admin.dashboard'),
            'pageTitle' => 'Submit Activity Proposal',
            'pageHeading' => 'Submit Activity Proposal',
            'pageSubheading' => 'Create an activity proposal from the admin portal. Load calendar data by registered organization name, then complete the form. The submission is recorded under your admin account.',
            'lookupOrganizationName' => $request->query('lookup_organization_name', ''),
            'lookupOrganizationNameError' => $lookupOrganizationNameError,
        ];

        if (! $organization) {
            return view('organizations.activity-proposal-submission', array_merge([
                'organization' => null,
                'schoolOptions' => OrganizationController::schoolCodeLabelMap(),
                'schoolPrefill' => null,
                'officerValidationPending' => false,
                'calendarEntry' => null,
                'linkedProposal' => null,
                'proposalCalendar' => null,
                'prefill' => [],
            ], $adminExtras));
        }

        $viewData = app(OrganizationController::class)->activityProposalFormViewData($request, $organization, true);

        if ($viewData instanceof RedirectResponse) {
            return $viewData;
        }

        return view('organizations.activity-proposal-submission', array_merge($viewData, $adminExtras));
    }

    public function storeActivityProposal(Request $request): RedirectResponse
    {
        $this->authorizeSdaoAdmin($request);

        return app(OrganizationController::class)->storeActivityProposalSubmission($request);
    }

    private function authorizeSdaoAdmin(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isSdaoAdmin()) {
            abort(403, 'Only authorized SDAO admins can access this section.');
        }
    }
}
