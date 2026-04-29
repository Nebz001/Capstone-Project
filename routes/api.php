<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProposalAttachmentController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\ProposalRevisionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public auth endpoints (mobile)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Signed file-stream endpoint
|--------------------------------------------------------------------------
| Lives outside the auth:sanctum group on purpose: the URL itself is a
| time-limited Laravel signed URL (issued by ProposalAttachmentController)
| so the mobile app can hand it to the OS file viewer without having to
| reattach the Sanctum bearer token. The `signed` middleware rejects any
| tampered or expired link before the controller runs.
*/
Route::get('/attachments/{attachment}/stream', [ProposalAttachmentController::class, 'stream'])
    ->middleware('signed')
    ->name('api.attachments.stream');

/*
|--------------------------------------------------------------------------
| Mobile JSON API
|--------------------------------------------------------------------------
| All endpoints below require a valid Sanctum bearer token (or, for SPA
| clients, a session that Sanctum recognises). Web routes are unaffected:
| the controllers in this group live under App\Http\Controllers\Api and
| never render Blade or redirects, so the existing web controllers / Blade
| views / approver workflow remain unchanged.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/approvals/pending', [ApprovalController::class, 'pending']);

    Route::get('/proposals/{proposal}', [ProposalController::class, 'show']);
    Route::get('/proposals/{proposal}/workflow', [ProposalController::class, 'workflow']);
    Route::get('/proposals/{proposal}/attachments', [ProposalAttachmentController::class, 'index']);

    Route::post('/proposals/{proposal}/approve', [ApprovalController::class, 'approve']);
    Route::post('/proposals/{proposal}/return', [ApprovalController::class, 'returnForRevision']);
    Route::post('/proposals/{proposal}/reject', [ApprovalController::class, 'reject']);

    Route::get('/proposals/{proposal}/revisions', [ProposalRevisionController::class, 'index']);
    Route::get('/proposals/{proposal}/field-reviews', [ProposalRevisionController::class, 'fieldReviews']);
    Route::post('/proposals/{proposal}/field-reviews', [ProposalRevisionController::class, 'storeFieldReviews']);

    Route::get('/attachments/{attachment}/view', [ProposalAttachmentController::class, 'view']);
    Route::get('/attachments/{attachment}/download', [ProposalAttachmentController::class, 'download']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
