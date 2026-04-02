<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;

Route::get('/', function () {
  return view('welcome');
});

Route::get('dashboard', function () {
  return redirect()->route('organizations.index');
})->name('dashboard');

Route::prefix('organizations')->name('organizations.')->controller(OrganizationController::class)->group(function () {
  Route::get('/', 'index')->name('index');
  Route::get('/manage', 'manage')->name('manage');

  Route::get('/register', 'showRegistrationForm')->name('register');
  Route::post('/register', 'storeRegistration')->middleware('auth')->name('register.store');

  Route::get('/renew', 'showRenewalForm')->name('renew');
  Route::post('/renew', 'storeRenewal')->name('renew.store');

  Route::get('/profile', 'profile')->name('profile');
  Route::put('/profile', 'updateProfile')->name('profile.update');

  Route::get('/activity-calendar-submission', 'showActivityCalendarSubmission')
    ->middleware('auth')
    ->name('activity-calendar-submission');
  Route::post('/activity-calendar-submission', 'storeActivityCalendarSubmission')
    ->middleware('auth')
    ->name('activity-calendar-submission.store');
});

Route::prefix('auth')->controller(AuthController::class)->group(function () {
  Route::get('register', 'showRegister')->name('register');
  Route::post('register', 'register')->name('register.submit');

  Route::get('login', 'showLogin')->name('login');
  Route::post('login', 'login')->name('login.submit');
});

Route::post('auth/logout', function () {
  Auth::logout();
  request()->session()->invalidate();
  request()->session()->regenerateToken();
  return redirect()->route('login');
})->name('logout');

Route::prefix('officer')->name('officer.')->group(function () {
  Route::get('dashboard', function () {
    return redirect()->route('organizations.index');
  })->name('dashboard');
});
