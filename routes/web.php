<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});

Route::redirect('/organizations/register', '/organizations/register-organization');

Route::get('/organizations/register-organization', function () {
  return view('organizations.register-organization');
})->name('register-organization');

Route::get('/organizations/activity-calendar', function () {
  return view('organizations.activity-calendar');
});
