<?php

namespace App\Services\ReviewWorkflow;

use App\Models\Attachment;
use App\Models\OrganizationSubmission;
use App\Models\User;
use App\Support\OrganizationStoragePath;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generic Supabase-backed attachment replacement, mirroring the
 * `applyRegistrationRequirementReplacement` pattern used for Registration.
 *
 * Responsibilities:
 *   - Resolve the previous attachment for ($reviewable, $fileType) and snap
 *     its metadata for the audit trail.
 *   - Stream the uploaded file to Supabase under a caller-provided folder.
 *   - Insert a new `attachments` row pointing at the new stored path.
 *   - Record the change in either `organization_revision_field_updates` or
 *     `module_revision_field_updates` via ReviewableUpdateRecorder.
 *   - Optionally bump the reviewable's status from `revision` →
 *     `under_review` so the admin list reflects the resubmission.
 *
 * Registration's existing controller path is NOT modified — we only extract
 * the reusable building blocks here. The original
 * `applyRegistrationRequirementReplacement` continues to write the same rows
 * with the same shape.
 */
class AttachmentReplacementService
{
    public function __construct(
        private readonly ReviewableUpdateRecorder $updateRecorder,
    ) {
    }

    /**
     * @param  Model              $reviewable    The model being reviewed.
     * @param  User               $user          The officer performing the resubmission.
     * @param  string             $fileType      Attachment.file_type to use for the new row (e.g. "registration_requirement:by_laws").
     * @param  string             $sectionKey    Field-review section_key (e.g. "requirements", "step1_request_form").
     * @param  string             $fieldKey      Field-review field_key (e.g. "by_laws", "request_letter").
     * @param  UploadedFile       $upload        The freshly uploaded file from the officer.
     * @param  Closure            $folderResolver fn(): string returning the Supabase folder for this reviewable.
     * @param  Closure|null       $statusBump    Optional callback fn(Model $reviewable): void to flip status to under_review.
     * @return array{stored_path: string, attachment_id: int} Audit info for logs / flash messages.
     */
    public function replace(
        Model $reviewable,
        User $user,
        string $fileType,
        string $sectionKey,
        string $fieldKey,
        UploadedFile $upload,
        Closure $folderResolver,
        ?Closure $statusBump = null,
    ): array {
        $diskName = 'supabase';
        $disk = Storage::disk($diskName);
        if (! $disk) {
            throw new RuntimeException('Supabase disk is not configured.');
        }

        $oldAttachment = Attachment::query()
            ->where('attachable_type', $reviewable->getMorphClass())
            ->where('attachable_id', (int) $reviewable->getKey())
            ->where(function ($query) use ($fileType, $fieldKey): void {
                $query->where('file_type', $fileType)
                    ->orWhere('file_type', $fieldKey)
                    ->orWhere('file_type', 'like', '%:'.$fieldKey);
            })
            ->latest('id')
            ->first();

        $oldMeta = $oldAttachment !== null ? $this->snapshotAttachmentMeta($oldAttachment) : null;

        $folder = trim((string) $folderResolver());
        if ($folder === '') {
            throw new RuntimeException('Refusing to upload reviewable file: empty folder resolved.');
        }
        $storedPath = $upload->store($folder, $diskName);
        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('Failed to upload replacement file to Supabase.');
        }

        $newMeta = [
            'original_name' => (string) $upload->getClientOriginalName(),
            'stored_path' => (string) $storedPath,
            'mime_type' => (string) ($upload->getClientMimeType() ?: ''),
            'file_size_kb' => (int) ceil(((int) $upload->getSize()) / 1024),
        ];

        $newAttachmentId = 0;

        DB::transaction(function () use (
            $reviewable, $user, $fileType, $sectionKey, $fieldKey,
            $upload, $storedPath, $oldMeta, $newMeta, $statusBump, &$newAttachmentId,
        ): void {
            $attachment = Attachment::query()->create([
                'attachable_type' => $reviewable->getMorphClass(),
                'attachable_id' => (int) $reviewable->getKey(),
                'uploaded_by' => (int) $user->id,
                'file_type' => $fileType,
                'original_name' => (string) $upload->getClientOriginalName(),
                'stored_path' => (string) $storedPath,
                'mime_type' => (string) ($upload->getClientMimeType() ?: ''),
                'file_size_kb' => (int) ceil(((int) $upload->getSize()) / 1024),
            ]);
            $newAttachmentId = (int) $attachment->id;

            $this->updateRecorder->recordFieldUpdate(
                reviewable: $reviewable,
                userId: (int) $user->id,
                sectionKey: $sectionKey,
                fieldKey: $fieldKey,
                oldFileMeta: $oldMeta,
                newFileMeta: $newMeta,
            );

            if ($statusBump !== null) {
                $statusBump($reviewable);
            }

            $reviewable->touch();
        });

        Log::info('Review workflow: attachment replaced by officer', [
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => (int) $reviewable->getKey(),
            'section_key' => $sectionKey,
            'field_key' => $fieldKey,
            'file_type' => $fileType,
            'stored_path' => $storedPath,
            'attachment_id' => $newAttachmentId,
        ]);

        return [
            'stored_path' => $storedPath,
            'attachment_id' => $newAttachmentId,
        ];
    }

    /**
     * Resolve the canonical Supabase folder for a registration / renewal
     * submission, falling back to a deterministic prefix if the helper
     * service can't find an organization for any reason.
     */
    public function organizationSubmissionFolder(OrganizationSubmission $submission): string
    {
        $organization = $submission->organization()->first();
        $bucketHelper = app(OrganizationStoragePath::class);

        if ($organization && $submission->isRegistration()) {
            return $bucketHelper->registrationFolder($organization);
        }
        if ($organization && $submission->isRenewal()) {
            return $bucketHelper->renewalFolder($organization);
        }

        $prefix = $submission->isRenewal() ? 'renewal' : 'registration';

        return ((int) $submission->organization_id).'/'.$prefix;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotAttachmentMeta(Attachment $attachment): array
    {
        return [
            'attachment_id' => (int) $attachment->id,
            'original_name' => (string) ($attachment->original_name ?? ''),
            'stored_path' => (string) ($attachment->stored_path ?? ''),
            'mime_type' => (string) ($attachment->mime_type ?? ''),
            'file_size_kb' => $attachment->file_size_kb !== null ? (int) $attachment->file_size_kb : null,
        ];
    }
}
