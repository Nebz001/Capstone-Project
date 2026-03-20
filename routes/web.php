<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('welcome');
});

Route::get('/organizations/register', function () {
  return view('organizations.register');
});

Route::get('/organizations/activity-calendar', function () {
  return view('organizations.activity-calendar');
});
