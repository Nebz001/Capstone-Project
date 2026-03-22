<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});

// Organizations
Route::redirect('/organizations/register', '/organizations/register-organization');

Route::get('/organizations/register-organization', function () {
  return view('organizations.register-organization');
})->name('register-organization');

Route::redirect('/organizations/activity-calendar', '/organizations/activity-calendar-submission');

Route::get('/organizations/activity-calendar-submission', function () {
  return view('organizations.activity-calendar-submission');
})->name('activity-calendar-submission');

// Authentication
Route::get('auth/register', function () {
  return view('auth.register');
})->name('register');

Route::post('auth/register', function (Request $request) {
  return redirect()
    ->route('register')
    ->with('success', 'Account created successfully.');
})->name('register.submit');

Route::get('auth/login', function () {
  return view('auth.login');
})->name('login');

Route::post('auth/login', function () {
  return redirect()
    ->route('login')
    ->with('success', 'Logged in successfully.');
})->name('login.submit');
