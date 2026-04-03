<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Models\Organization;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;

Route::get('/', function () {
  return view('welcome');
});

Route::get('dashboard', function () {
  return redirect()->route('organizations.index');
})->middleware('auth')->name('dashboard');

Route::prefix('organizations')->name('organizations.')->middleware('auth')->controller(OrganizationController::class)->group(function () {
  Route::get('/', 'index')->name('index');
  Route::get('/manage', 'manage')->name('manage');

  Route::get('/register', 'showRegistrationForm')->name('register');
  Route::post('/register', 'storeRegistration')->name('register.store');

  Route::get('/renew', 'showRenewalForm')->name('renew');
  Route::post('/renew', 'storeRenewal')->name('renew.store');

  Route::get('/profile', 'profile')->name('profile');
  Route::put('/profile', 'updateProfile')->name('profile.update');

  Route::get('/activity-calendar-submission', 'showActivityCalendarSubmission')
    ->name('activity-calendar-submission');
  Route::post('/activity-calendar-submission', 'storeActivityCalendarSubmission')
    ->name('activity-calendar-submission.store');
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

Route::prefix('officer')->name('officer.')->group(function () {
  Route::get('dashboard', function () {
    return redirect()->route('organizations.index');
  })->middleware('auth')->name('dashboard');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->controller(AdminController::class)->group(function () {
  Route::get('/dashboard', 'dashboard')->name('dashboard');
  Route::get('/calendar', 'centralizedCalendar')->name('calendar');
  Route::get('/officer-accounts', 'officerAccounts')->name('officer-accounts.index');
  Route::get('/officer-accounts/{user}', 'showOfficerAccount')->name('officer-accounts.show');
  Route::patch('/officer-accounts/{user}', 'updateOfficerValidation')->name('officer-accounts.update');

  Route::get('/registrations', 'registrations')->name('registrations.index');
  Route::get('/registrations/{registration}', 'showRegistration')->name('registrations.show');

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
