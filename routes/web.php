<?php

use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminSubmissionController;
use App\Http\Controllers\ApproverDashboardController;
use App\Http\Controllers\AnnouncementDismissController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationAdviserController;
use App\Http\Controllers\OrganizationNotificationController;
use App\Http\Controllers\OrganizationOfficerController;
use App\Http\Controllers\OrganizationSubmittedDocumentsController;
use App\Models\Organization;
use App\Models\OrganizationSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $approvedOrganizations = Organization::query()
        ->where('status', 'active')
        ->orderBy('organization_name')
        ->get(['organization_name', 'college_department', 'organization_type']);

    return view('welcome', compact('approvedOrganizations'));
});

Route::get('dashboard', function (Request $request) {
    if ($request->user()?->isAdminRole()) {
        return redirect()->route('admin.dashboard');
    }

    if ($request->user()?->isRoleBasedApprover()) {
        return redirect()->route('approver.dashboard');
    }

    return redirect()->route('organizations.index');
})->middleware('auth')->name('dashboard');

/*
 * File-streaming routes for submitted documents.
 *
 * These deliberately sit OUTSIDE the `officer.portal` middleware group so that
 * authenticated non-officer roles (admins, advisers, etc.) can still resolve
 * the file URLs generated for officer-facing screens. Authorization is
 * enforced inside each controller action via the OrganizationSubmissionPolicy
 * (and the equivalent ownership checks for activity calendars / proposals /
 * reports), so widening the route group does not loosen access — it just
 * stops the middleware from redirecting non-officers away with a 302 (which
 * surfaces to the user as a broken / 404-looking "View file" link).
 */
Route::prefix('organizations')->name('organizations.')->middleware(['auth'])->group(function () {
    Route::get('/advisers/search', [OrganizationAdviserController::class, 'searchAdvisers'])
        ->name('advisers.search');
    Route::post('/submitted-documents/{submission}/adviser/renominate', [OrganizationAdviserController::class, 'renominateForSubmission'])
        ->name('submitted-documents.adviser.renominate');
    Route::controller(OrganizationSubmittedDocumentsController::class)->group(function () {
        Route::get('/submitted-documents/registrations/{submission}/files/{key}', 'streamSubmittedRegistrationRequirementFile')
            ->name('submitted-documents.registrations.file')
            ->where('key', '[a-z0-9_]+');
        Route::get('/submitted-documents/renewals/{submission}/files/{key}', 'streamSubmittedRenewalRequirementFile')
            ->name('submitted-documents.renewals.file')
            ->where('key', '[a-z0-9_]+');
        Route::get('/submitted-documents/activity-calendars/{calendar}/file', 'streamSubmittedActivityCalendarMainFile')->name('submitted-documents.calendars.file');
        Route::get('/submitted-documents/activity-proposals/{proposal}/files/{key}', 'streamSubmittedActivityProposalFile')
            ->name('submitted-documents.proposals.file')
            ->where('key', '[a-z]+');
        Route::get('/submitted-documents/after-activity-reports/{report}/files/{key}', 'streamSubmittedAfterActivityReportFile')
            ->name('submitted-documents.reports.file')
            ->where('key', '[a-z0-9_]+');
    });
});

Route::middleware(['auth'])->get('/api/users/search-advisers', [OrganizationAdviserController::class, 'searchAdvisers'])
    ->name('api.users.search-advisers');

Route::prefix('organizations')->name('organizations.')->middleware(['auth', 'officer.portal'])->group(function () {
    Route::controller(OrganizationSubmittedDocumentsController::class)->group(function () {
        Route::get('/submitted-documents', 'index')->name('submitted-documents');
        Route::get('/submitted-documents/registrations/{submission}', 'showSubmittedRegistration')->name('submitted-documents.registrations.show');
        Route::post('/submitted-documents/registrations/{submission}/files/{key}/replace', 'replaceSubmittedRegistrationRequirementFile')
            ->name('submitted-documents.registrations.file.replace')
            ->where('key', '[a-z0-9_]+');
        Route::post('/submitted-documents/registrations/{submission}/resubmit', 'resubmitRegistrationRevisionFiles')
            ->name('submitted-documents.registrations.resubmit');
        Route::get('/submitted-documents/renewals/{submission}', 'showSubmittedRenewal')->name('submitted-documents.renewals.show');
        Route::get('/submitted-documents/activity-calendars/{calendar}', 'showSubmittedActivityCalendar')->name('submitted-documents.calendars.show');
        Route::get('/submitted-documents/activity-proposals/{proposal}', 'showSubmittedActivityProposal')->name('submitted-documents.proposals.show');
        Route::get('/activity-submission/activity-calendars/{calendar}', 'showSubmittedActivityCalendar')->name('activity-submission.calendars.show');
        Route::get('/activity-submission/activity-proposals/{proposal}', 'showSubmittedActivityProposal')->name('activity-submission.proposals.show');
        Route::get('/submitted-documents/after-activity-reports/{report}', 'showSubmittedAfterActivityReport')->name('submitted-documents.reports.show');
    });

    Route::controller(OrganizationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/manage', 'manage')->name('manage');

        Route::get('/register', 'showRegistrationForm')->name('register');
        Route::post('/register', 'storeRegistration')->name('register.store');

        Route::get('/renew', 'showRenewalForm')->name('renew');
        Route::post('/renew', 'storeRenewal')->name('renew.store');

        Route::get('/profile', 'profile')->name('profile');
        Route::put('/profile', 'updateProfile')->name('profile.update');

        Route::get('/activity-submission', 'showActivitySubmissionHub')->name('activity-submission');

        Route::get('/activity-calendar-submission', 'showActivityCalendarSubmission')
            ->name('activity-calendar-submission');
        Route::post('/activity-calendar-submission', 'storeActivityCalendarSubmission')
            ->name('activity-calendar-submission.store');

        Route::get('/activity-proposal-submission', 'showActivityProposalSubmission')
            ->name('activity-proposal-submission');
        Route::get('/activity-proposal-request', 'showActivityProposalRequest')
            ->name('activity-proposal-request');
        Route::post('/activity-proposal-request', 'storeActivityProposalRequest')
            ->name('activity-proposal-request.store');
        Route::post('/activity-proposal-submission', 'storeActivityProposalSubmission')
            ->name('activity-proposal-submission.store');

        Route::get('/submit-report', 'showSubmitReportHub')->name('submit-report');
        Route::get('/after-activity-report', 'showAfterActivityReportForm')->name('after-activity-report');
        Route::post('/after-activity-report', 'storeAfterActivityReport')->name('after-activity-report.store');
    });

    Route::controller(OrganizationOfficerController::class)->group(function () {
        Route::get('/officers', 'index')->name('officers.index');
        Route::post('/officers/secretary', 'storeSecretary')->name('officers.secretary.store');
        Route::patch('/officers/secretary/{officer}/deactivate', 'deactivateSecretary')->name('officers.secretary.deactivate');
        Route::post('/officers/secretary/replace', 'replaceSecretary')->name('officers.secretary.replace');
    });

    Route::controller(OrganizationNotificationController::class)->group(function () {
        Route::get('/notifications', 'index')->name('notifications.index');
        Route::get('/notifications/{notification}/open', 'open')->name('notifications.open');
        Route::post('/notifications/{notification}/mark-read', 'markRead')->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', 'markAllRead')->name('notifications.mark-all-read');
    });
});

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::get('register', 'showRegister')->name('register');
    Route::post('register', 'register')->name('register.submit');

    Route::get('login', 'showLogin')->name('login');
    Route::post('login', 'login')->name('login.submit');
});

Route::post('auth/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::post('announcements/dismiss', [AnnouncementDismissController::class, 'store'])
    ->middleware('auth')
    ->name('announcements.dismiss');

Route::prefix('officer')->name('officer.')->group(function () {
    Route::get('dashboard', function () {
        return redirect()->route('organizations.index');
    })->middleware('auth')->name('dashboard');
});

Route::prefix('approver')->name('approver.')->middleware('auth')->controller(ApproverDashboardController::class)->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/assignments/{step}', 'showAssignment')->name('assignments.show');
    Route::get('/assignments/{step}/proposal-files/{key}', 'streamAssignmentProposalFile')
        ->name('assignments.proposals.file')
        ->where('key', '[a-z_]+');
    Route::patch('/assignments/{step}', 'decide')->name('assignments.decide');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::controller(AdminAnnouncementController::class)->group(function () {
        Route::get('/announcements', 'index')->name('announcements.index');
        Route::get('/announcements/create', 'create')->name('announcements.create');
        Route::post('/announcements', 'store')->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', 'edit')->name('announcements.edit');
        Route::put('/announcements/{announcement}', 'update')->name('announcements.update');
        Route::delete('/announcements/{announcement}', 'destroy')->name('announcements.destroy');
    });
});

Route::prefix('admin')->name('admin.')->middleware('auth')->controller(AdminSubmissionController::class)->group(function () {
    Route::get('/submissions/register', 'showRegistration')->name('submissions.register');
    Route::post('/submissions/register', 'storeRegistration')->name('submissions.register.store');
    Route::get('/submissions/renew', 'showRenew')->name('submissions.renew');
    Route::post('/submissions/renew', 'storeRenew')->name('submissions.renew.store');
    Route::get('/submissions/activity-calendar', 'showActivityCalendar')->name('submissions.activity-calendar');
    Route::post('/submissions/activity-calendar', 'storeActivityCalendar')->name('submissions.activity-calendar.store');
    Route::get('/submissions/activity-proposal', 'showActivityProposal')->name('submissions.activity-proposal');
    Route::post('/submissions/activity-proposal', 'storeActivityProposal')->name('submissions.activity-proposal.store');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->controller(AdminController::class)->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::patch('/settings/active-term', 'updateActiveTerm')->name('settings.active-term');
    Route::patch('/settings/academic-year', 'updateAcademicYear')->name('settings.academic-year');
    Route::get('/calendar', 'centralizedCalendar')->name('calendar');
    Route::get('/accounts', 'userAccounts')->name('accounts.index');
    Route::get('/accounts/{user}', 'showUserAccount')->name('accounts.show');
    Route::patch('/accounts/{user}', 'updateUserAccountOfficerValidation')->name('accounts.update');

    Route::get('/registrations', 'registrations')->name('registrations.index');
    Route::get('/registrations/{submission}/requirements/{key}', 'showRegistrationRequirementFile')
        ->name('registrations.requirement-file')
        ->where('key', '[a-z0-9_]+');
    Route::get('/registrations/{submission}/requirements/{key}/download', 'downloadRegistrationRequirementFile')
        ->name('registrations.requirement-file.download')
        ->where('key', '[a-z0-9_]+');
    Route::get('/registrations/{submission}', 'showRegistration')->name('registrations.show');
    Route::get('/registrations/{submission}/field-updates/{fieldUpdate}/{version}/file', 'showRegistrationFieldUpdateFile')
        ->name('registrations.field-updates.file')
        ->where('version', 'old|new');
    Route::get('/registrations/{submission}/field-updates/{fieldUpdate}/{version}/file/download', 'downloadRegistrationFieldUpdateFile')
        ->name('registrations.field-updates.file.download')
        ->where('version', 'old|new');
    Route::patch('/registrations/{submission}/review-draft', 'saveRegistrationReviewDraft')->name('registrations.review-draft');
    Route::patch('/registrations/{submission}/field-updates/{fieldUpdate}/acknowledge', 'acknowledgeRegistrationFieldUpdate')->name('registrations.field-updates.acknowledge');
    Route::patch('/registrations/{submission}/status', 'updateRegistrationStatus')->name('registrations.update-status');

    Route::get('/renewals', 'renewals')->name('renewals.index');
    Route::get('/renewals/{submission}/requirements/{key}', 'showRenewalRequirementFile')
        ->name('renewals.requirement-file')
        ->where('key', '[a-z0-9_]+');
    Route::get('/renewals/{submission}/requirements/{key}/download', 'downloadRenewalRequirementFile')
        ->name('renewals.requirement-file.download')
        ->where('key', '[a-z0-9_]+');
    Route::get('/renewals/{submission}', 'showRenewal')->name('renewals.show');
    Route::patch('/renewals/{submission}/review-draft', 'saveRenewalReviewDraft')->name('renewals.review-draft');
    Route::patch('/renewals/{submission}/status', 'updateRenewalStatus')->name('renewals.update-status');

    Route::get('/activity-calendars', 'calendars')->name('calendars.index');
    Route::get('/activity-calendars/{calendar}', 'showCalendar')->name('calendars.show');
    Route::get('/activity-calendars/{calendar}/file', 'streamCalendarFile')->name('calendars.file');
    Route::patch('/activity-calendars/{calendar}/review-draft', 'saveCalendarReviewDraft')->name('calendars.review-draft');
    Route::patch('/activity-calendars/{calendar}/status', 'updateCalendarStatus')->name('calendars.update-status');

    Route::get('/activity-proposals', 'proposals')->name('proposals.index');
    Route::get('/activity-proposals/{proposal}', 'showProposal')->name('proposals.show');
    Route::get('/activity-proposals/{proposal}/files/{key}', 'streamProposalFile')
        ->name('proposals.file')
        ->where('key', '[a-z_]+');
    Route::patch('/activity-proposals/{proposal}/review-draft', 'saveProposalReviewDraft')->name('proposals.review-draft');
    Route::patch('/activity-proposals/{proposal}/status', 'updateProposalStatus')->name('proposals.update-status');
    Route::patch('/activity-proposals/{proposal}/workflow', 'updateProposalWorkflow')->name('proposals.workflow');

    Route::get('/after-activity-reports', 'reports')->name('reports.index');
    Route::get('/after-activity-reports/{report}', 'showReport')->name('reports.show');
    Route::patch('/after-activity-reports/{report}/review-draft', 'saveReportReviewDraft')->name('reports.review-draft');
    Route::patch('/after-activity-reports/{report}/status', 'updateReportStatus')->name('reports.update-status');

    Route::post('/organizations/{organization}/request-profile-revision', 'requestOrganizationProfileRevision')
        ->name('organizations.request-profile-revision');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->controller(OrganizationAdviserController::class)->group(function () {
    Route::patch('/advisers/{adviser}/review', 'reviewAdviser')->name('advisers.review');
});

Route::bind('submission', function (string $value): OrganizationSubmission {
    return OrganizationSubmission::query()->findOrFail((int) $value);
});

/*
 * Local-only debug helper for verifying that an attachment row points to a
 * real object in Supabase Storage and that the generated public URL is well-
 * formed. Disabled in non-local environments to avoid leaking metadata.
 */
if (app()->environment('local')) {
    Route::get('/debug-attachment/{id}', function ($id) {
        $attachment = \App\Models\Attachment::findOrFail($id);
        $disk = \Illuminate\Support\Facades\Storage::disk('supabase');

        $storedPath = (string) $attachment->stored_path;
        $publicUrl = rtrim((string) env('SUPABASE_STORAGE_PUBLIC_URL'), '/')
            .'/'.trim((string) env('SUPABASE_STORAGE_BUCKET'), '/')
            .'/'.ltrim($storedPath, '/');

        return response()->json([
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'stored_path_from_database' => $attachment->stored_path,
            'bucket' => env('SUPABASE_STORAGE_BUCKET'),
            'public_url' => $publicUrl,
            'exists_in_supabase_storage' => $disk->exists($storedPath),
            'is_bad_local_path' => str_contains($storedPath, 'C:')
                || str_contains($storedPath, 'localhost')
                || str_contains($storedPath, '127.0.0.1')
                || str_contains($storedPath, 'storage/app')
                || str_contains($storedPath, 'rest/v1'),
        ]);
    });

    /*
     * End-to-end smoke test for the Supabase S3 disk. Hits Storage::put +
     * exists with a tiny throwaway object so misconfigured credentials fail
     * fast (no 30-second InstanceProfileProvider hang) and reveal exactly
     * which config key is missing. Never exposes key/secret values.
     */
    Route::get('/debug-supabase-storage', function () {
        $diskConfig = config('filesystems.disks.supabase', []);
        if (! is_array($diskConfig)) {
            $diskConfig = [];
        }

        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $required) {
            if (empty($diskConfig[$required])) {
                return response()->json([
                    'ok' => false,
                    'error' => "Missing Supabase storage config: {$required}",
                    'config' => [
                        'has_key' => ! empty($diskConfig['key']),
                        'has_secret' => ! empty($diskConfig['secret']),
                        'region' => $diskConfig['region'] ?? null,
                        'bucket' => $diskConfig['bucket'] ?? null,
                        'endpoint' => $diskConfig['endpoint'] ?? null,
                    ],
                ], 500);
            }
        }

        $path = 'debug/test-'.now()->format('YmdHis').'.txt';
        $disk = \Illuminate\Support\Facades\Storage::disk('supabase');

        try {
            $disk->put($path, 'Supabase storage test '.now()->toIso8601String());
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'put() failed: '.$e->getMessage(),
                'exception' => class_basename($e),
                'path' => $path,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'path' => $path,
            'exists' => $disk->exists($path),
            'public_url' => rtrim((string) env('SUPABASE_STORAGE_PUBLIC_URL'), '/')
                .'/'.trim((string) env('SUPABASE_STORAGE_BUCKET'), '/')
                .'/'.ltrim($path, '/'),
            'config' => [
                'has_key' => ! empty($diskConfig['key']),
                'has_secret' => ! empty($diskConfig['secret']),
                'region' => $diskConfig['region'] ?? null,
                'bucket' => $diskConfig['bucket'] ?? null,
                'endpoint' => $diskConfig['endpoint'] ?? null,
            ],
        ]);
    });
}
