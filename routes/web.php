<?php

use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementDismissController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationSubmittedDocumentsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('dashboard', function () {
    return redirect()->route('organizations.index');
})->middleware('auth')->name('dashboard');

Route::prefix('organizations')->name('organizations.')->middleware('auth')->group(function () {
    Route::controller(OrganizationSubmittedDocumentsController::class)->group(function () {
        Route::get('/submitted-documents', 'index')->name('submitted-documents');
        Route::get('/submitted-documents/registrations/{registration}', 'showSubmittedRegistration')->name('submitted-documents.registrations.show');
        Route::get('/submitted-documents/registrations/{registration}/files/{key}', 'streamSubmittedRegistrationRequirementFile')
            ->name('submitted-documents.registrations.file')
            ->where('key', '[a-z0-9_]+');
        Route::get('/submitted-documents/renewals/{renewal}', 'showSubmittedRenewal')->name('submitted-documents.renewals.show');
        Route::get('/submitted-documents/renewals/{renewal}/files/{key}', 'streamSubmittedRenewalRequirementFile')
            ->name('submitted-documents.renewals.file')
            ->where('key', '[a-z0-9_]+');
        Route::get('/submitted-documents/activity-calendars/{calendar}', 'showSubmittedActivityCalendar')->name('submitted-documents.calendars.show');
        Route::get('/submitted-documents/activity-calendars/{calendar}/file', 'streamSubmittedActivityCalendarMainFile')->name('submitted-documents.calendars.file');
        Route::get('/submitted-documents/activity-proposals/{proposal}', 'showSubmittedActivityProposal')->name('submitted-documents.proposals.show');
        Route::get('/submitted-documents/activity-proposals/{proposal}/files/{key}', 'streamSubmittedActivityProposalFile')
            ->name('submitted-documents.proposals.file')
            ->where('key', '[a-z]+');
        Route::get('/activity-submission/activity-calendars/{calendar}', 'showSubmittedActivityCalendar')->name('activity-submission.calendars.show');
        Route::get('/activity-submission/activity-proposals/{proposal}', 'showSubmittedActivityProposal')->name('activity-submission.proposals.show');
        Route::get('/submitted-documents/after-activity-reports/{report}', 'showSubmittedAfterActivityReport')->name('submitted-documents.reports.show');
        Route::get('/submitted-documents/after-activity-reports/{report}/files/{key}', 'streamSubmittedAfterActivityReportFile')
            ->name('submitted-documents.reports.file')
            ->where('key', '[a-z0-9_]+');
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
        Route::post('/activity-proposal-submission', 'storeActivityProposalSubmission')
            ->name('activity-proposal-submission.store');

        Route::get('/submit-report', 'showSubmitReportHub')->name('submit-report');
        Route::get('/after-activity-report', 'showAfterActivityReportForm')->name('after-activity-report');
        Route::post('/after-activity-report', 'storeAfterActivityReport')->name('after-activity-report.store');
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

Route::prefix('admin')->name('admin.')->middleware('auth')->controller(AdminController::class)->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/calendar', 'centralizedCalendar')->name('calendar');
    Route::get('/officer-accounts', 'officerAccounts')->name('officer-accounts.index');
    Route::get('/officer-accounts/{user}', 'showOfficerAccount')->name('officer-accounts.show');
    Route::patch('/officer-accounts/{user}', 'updateOfficerValidation')->name('officer-accounts.update');

    Route::get('/registrations', 'registrations')->name('registrations.index');
    Route::get('/registrations/{registration}/requirements/{key}', 'showRegistrationRequirementFile')
        ->name('registrations.requirement-file')
        ->where('key', '[a-z0-9_]+');
    Route::get('/registrations/{registration}', 'showRegistration')->name('registrations.show');
    Route::patch('/registrations/{registration}/status', 'updateRegistrationStatus')->name('registrations.update-status');

    Route::get('/renewals', 'renewals')->name('renewals.index');
    Route::get('/renewals/{renewal}', 'showRenewal')->name('renewals.show');

    Route::get('/activity-calendars', 'calendars')->name('calendars.index');
    Route::get('/activity-calendars/{calendar}', 'showCalendar')->name('calendars.show');

    Route::get('/activity-proposals', 'proposals')->name('proposals.index');
    Route::get('/activity-proposals/{proposal}', 'showProposal')->name('proposals.show');

    Route::get('/after-activity-reports', 'reports')->name('reports.index');
    Route::get('/after-activity-reports/{report}', 'showReport')->name('reports.show');

    Route::post('/organizations/{organization}/request-profile-revision', 'requestOrganizationProfileRevision')
        ->name('organizations.request-profile-revision');
});
