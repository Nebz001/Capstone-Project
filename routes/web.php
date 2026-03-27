<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationOfficer\DashboardController;

Route::get('/', function () {
  return view('welcome');
});

Route::prefix('organizations')->group(function () {
  // Organizations
  Route::redirect('register', 'organizations/register-organization');

  Route::get('register-organization', function () {
    return view('organizations.register-organization');
  })->name('register-organization');

  Route::redirect('activity-calendar', 'organizations/activity-calendar-submission');

  Route::get('activity-calendar-submission', function () {
    return view('organizations.activity-calendar-submission');
  })->name('activity-calendar-submission');
});

Route::prefix('auth')->controller(AuthController::class)->group(function () {
  // Authentication
  Route::get('register', 'showRegister')->name('register');
  Route::post('register', 'register')->name('register.submit');

  Route::get('login', 'showLogin')->name('login');
  Route::post('login', 'login')->name('login.submit');
});

// Logout — accessible to authenticated users
Route::post('auth/logout', function () {
  Auth::logout();
  request()->session()->invalidate();
  request()->session()->regenerateToken();
  return redirect()->route('login');
})->name('logout');

// Organization Officer Dashboard
// TODO: add ->middleware('auth') once authentication is fully implemented
Route::prefix('officer')->name('officer.')->group(function () {
  Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
