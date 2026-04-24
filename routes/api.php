<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\YourController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
  Route::get('/user', function (Request $request) {
    return $request->user();
  });
  Route::apiResource('/your-resource', YourController::class);
});
