<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});

Route::redirect('/organizations/register', '/organizations/register-organization');

Route::get('/organizations/register-organization', function () {
  return view('organizations.register-organization');
})->name('register-organization');

Route::redirect('/organizations/activity-calendar', '/organizations/activity-calendar-submission');

Route::get('/organizations/activity-calendar-submission', function () {
  return view('organizations.activity-calendar-submission');
})->name('activity-calendar-submission');
