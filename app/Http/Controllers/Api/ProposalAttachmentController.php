<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\ActivityRequestForm;
use App\Models\Attachment;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalAttachmentController extends Controller
{
    /**
     * Disks we will probe (in order) when locating an attachment file. Mirrors
     * the disks the existing web upload flows use: proposal/request-form
     * uploads currently land on `public`, but admin requirement uploads land
     * on `supabase`. Probing both lets the API serve attachments uploaded
     * before the system standardised on a single disk.
     *
     * @var array<int, string>
     */
    private const ATTACHMENT_DISKS = ['public', 'supabase'];

    private const SIGNED_URL_TTL_MINUTES = 15;

    public function __construct(
        private readonly ApprovalWorkflowService $workflow
    ) {
    }

    /**
     * GET /api/proposals/{proposal}/attachments
     */
    public function index(Request $request, ActivityProposal $proposal): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $this->workflow->canViewProposal($user, $proposal)) {
            return response()->json(['message' => 'You do not have access to this proposal.'], 403);
        }

        $proposal->load('attachments');

        $requestForm = $this->relatedRequestFormForProposal($proposal);
        $requestFormAttachments = $requestForm
            ? $requestForm->attachments()->get()
            : collect();

        $all = $proposal->attachments
            ->concat($requestFormAttachments)
            ->unique(fn (Attachment $a): int => (int) $a->id)
            ->values();

        $data = $all->map(fn (Attachment $attachment): array => [
            'id' => (int) $attachment->id,
            'file_type' => (string) $attachment->file_type,
            'original_name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'file_size_kb' => $attachment->file_size_kb !== null ? (int) $attachment->file_size_kb : null,
            'view_url' => URL::to('/api/attachments/'.$attachment->id.'/view'),
            'download_url' => URL::to('/api/attachments/'.$attachment->id.'/download'),
        ])->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/attachments/{attachment}/view
     */
    public function view(Request $request, Attachment $attachment): JsonResponse
    {
        if (! $this->userCanAccessAttachment($request, $attachment)) {
            return response()->json(['message' => 'You do not have access to this attachment.'], 403);
        }

        [$disk, $diskName] = $this->resolveAttachmentDisk($attachment);
        if (! $disk) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        $url = $this->buildSignedUrl($attachment, $disk, $diskName, false);
        if ($url === null) {
            return response()->json(['message' => 'Unable to generate file URL.'], 500);
        }

        return response()->json([
            'url' => $url,
            'expires_in_minutes' => self::SIGNED_URL_TTL_MINUTES,
        ]);
    }

    /**
     * GET /api/attachments/{attachment}/download
     */
    public function download(Request $request, Attachment $attachment): JsonResponse
    {
        if (! $this->userCanAccessAttachment($request, $attachment)) {
            return response()->json(['message' => 'You do not have access to this attachment.'], 403);
        }

        [$disk, $diskName] = $this->resolveAttachmentDisk($attachment);
        if (! $disk) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        $url = $this->buildSignedUrl($attachment, $disk, $diskName, true);
        if ($url === null) {
            return response()->json(['message' => 'Unable to generate file URL.'], 500);
        }

        return response()->json([
            'url' => $url,
            'filename' => (string) ($attachment->original_name ?: basename((string) $attachment->stored_path)),
            'mime_type' => $attachment->mime_type ?? 'application/octet-stream',
            'expires_in_minutes' => self::SIGNED_URL_TTL_MINUTES,
        ]);
    }

    /**
     * GET /api/attachments/{attachment}/stream  (signed-only, no auth)
     *
     * Stream the attachment bytes through Laravel for disks that don't expose
     * native temporary URLs (e.g. local `public`). Authorization is enforced
     * by the Laravel signed URL we issued earlier — the route is registered
     * with the `signed` middleware so the framework rejects tampered or
     * expired links before this method runs.
     */
    public function stream(Request $request, Attachment $attachment): StreamedResponse
    {
        $mode = $request->query('mode') === 'download' ? 'attachment' : 'inline';

        [$disk, $diskName] = $this->resolveAttachmentDisk($attachment);
        if (! $disk || ! is_string($attachment->stored_path) || trim((string) $attachment->stored_path) === '') {
            abort(404);
        }

        $relativePath = trim((string) $attachment->stored_path);
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        $filename = (string) ($attachment->original_name ?: basename($relativePath));
        $headers = [];
        if ($attachment->mime_type) {
            $headers['Content-Type'] = (string) $attachment->mime_type;
        }

        return $disk->response($relativePath, $filename, $headers, $mode);
    }

    private function userCanAccessAttachment(Request $request, Attachment $attachment): bool
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $owner = $attachment->attachable;
        if ($owner instanceof ActivityProposal) {
            return $this->workflow->canViewProposal($user, $owner);
        }

        if ($owner instanceof ActivityRequestForm) {
            $owningProposal = ActivityProposal::query()
                ->where('organization_id', $owner->organization_id)
                ->where('submitted_by', $owner->submitted_by)
                ->latest('id')
                ->first();
            if ($owningProposal && $this->workflow->canViewProposal($user, $owningProposal)) {
                return true;
            }

            if ((int) $owner->submitted_by === (int) $user->id) {
                return true;
            }

            return $user->organizationOfficers()
                ->where('organization_id', $owner->organization_id)
                ->where('status', 'active')
                ->exists();
        }

        return $user->isAdminRole();
    }

    /**
     * @return array{0: \Illuminate\Filesystem\FilesystemAdapter|null, 1: string|null}
     */
    private function resolveAttachmentDisk(Attachment $attachment): array
    {
        $path = is_string($attachment->stored_path) ? trim($attachment->stored_path) : '';
        if ($path === '') {
            return [null, null];
        }

        foreach (self::ATTACHMENT_DISKS as $diskName) {
            try {
                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    return [$disk, $diskName];
                }
            } catch (\Throwable $e) {
                Log::warning('Skipping disk while resolving API attachment', [
                    'disk' => $diskName,
                    'attachment_id' => (int) $attachment->id,
                    'error' => $e->getMessage(),
                    'exception' => class_basename($e),
                ]);
            }
        }

        return [null, null];
    }

    private function buildSignedUrl(Attachment $attachment, FilesystemAdapter $disk, ?string $diskName, bool $forDownload): ?string
    {
        $path = trim((string) $attachment->stored_path);

        try {
            $options = [];
            if ($forDownload) {
                $safeFilename = $this->safeDownloadFilename($attachment);
                $options['ResponseContentDisposition'] = 'attachment; filename="'.$safeFilename.'"';
                $options['ResponseContentType'] = $attachment->mime_type ?: 'application/octet-stream';
            }

            return $disk->temporaryUrl(
                $path,
                Carbon::now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
                $options
            );
        } catch (\Throwable $e) {
            Log::info('Falling back to Laravel signed URL for attachment (disk lacks temporaryUrl).', [
                'disk' => $diskName,
                'attachment_id' => (int) $attachment->id,
                'exception' => class_basename($e),
            ]);
        }

        try {
            return URL::temporarySignedRoute(
                'api.attachments.stream',
                Carbon::now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
                [
                    'attachment' => (int) $attachment->id,
                    'mode' => $forDownload ? 'download' : 'view',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to build signed attachment URL.', [
                'attachment_id' => (int) $attachment->id,
                'error' => $e->getMessage(),
                'exception' => class_basename($e),
            ]);

            return null;
        }
    }

    private function safeDownloadFilename(Attachment $attachment): string
    {
        $name = (string) ($attachment->original_name ?: basename((string) $attachment->stored_path));
        $clean = (string) preg_replace('/[\x00-\x1F"\\\\]/u', '', $name);

        return $clean !== '' ? $clean : 'download';
    }

    /**
     * Lightweight version of ApproverDashboardController's request-form lookup
     * — kept private here so the API doesn't need to touch the web controller.
     */
    private function relatedRequestFormForProposal(ActivityProposal $proposal): ?ActivityRequestForm
    {
        $base = ActivityRequestForm::query()
            ->where('organization_id', $proposal->organization_id)
            ->where('submitted_by', $proposal->submitted_by)
            ->whereNotNull('promoted_at');

        if ($proposal->activity_calendar_entry_id) {
            $hit = (clone $base)
                ->where('activity_calendar_entry_id', $proposal->activity_calendar_entry_id)
                ->latest('promoted_at')
                ->latest('id')
                ->first();
            if ($hit) {
                return $hit;
            }
        }

        return (clone $base)
            ->where('activity_title', (string) ($proposal->activity_title ?? ''))
            ->latest('promoted_at')
            ->latest('id')
            ->first();
    }
}
