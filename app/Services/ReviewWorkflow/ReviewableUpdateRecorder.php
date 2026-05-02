<?php

namespace App\Services\ReviewWorkflow;

use App\Models\ModuleRevisionFieldUpdate;
use App\Models\OrganizationRevisionFieldUpdate;
use App\Models\OrganizationSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Reusable bridge for the per-field "officer resubmitted this" audit table.
 *
 * The Registration / Renewal flow already writes to
 * `organization_revision_field_updates` (FK on `organization_submissions.id`).
 * Calendars / Proposals / Reports cannot use that FK, so we introduced the
 * polymorphic `module_revision_field_updates` table for them.
 *
 * This service hides that storage split behind one API. Callers pass the
 * reviewable record (any model) plus the section/field/value/file metadata
 * and we route the write to the correct backing table — without ever
 * mutating registration's own helpers.
 *
 * The reads (`pendingForReviewable`, `latestForReviewable`,
 * `acknowledgeReviewedFields`) are also unified so the admin "Updated" diff
 * and the auto-acknowledge-on-save logic can live in one place.
 */
class ReviewableUpdateRecorder
{
    /**
     * Record that the officer just resubmitted ($sectionKey, $fieldKey) on
     * $reviewable, capturing what changed (text-style values OR file meta).
     *
     * Idempotent for the same (reviewable, section, field): we updateOrCreate,
     * so re-uploading the same file replaces the row's `new_*` columns and
     * resets the acknowledgement so the admin sees it as fresh.
     *
     * @param  array<string, mixed>|null  $oldFileMeta
     * @param  array<string, mixed>|null  $newFileMeta
     */
    public function recordFieldUpdate(
        Model $reviewable,
        int $userId,
        string $sectionKey,
        string $fieldKey,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $oldFileMeta = null,
        ?array $newFileMeta = null,
    ): void {
        $payload = [
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'old_file_meta' => $oldFileMeta,
            'new_file_meta' => $newFileMeta,
            'resubmitted_at' => now(),
            'resubmitted_by' => $userId,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ];

        if ($reviewable instanceof OrganizationSubmission) {
            OrganizationRevisionFieldUpdate::query()->updateOrCreate(
                [
                    'organization_submission_id' => (int) $reviewable->id,
                    'section_key' => $sectionKey,
                    'field_key' => $fieldKey,
                ],
                $payload
            );

            return;
        }

        ModuleRevisionFieldUpdate::query()->updateOrCreate(
            [
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id' => (int) $reviewable->getKey(),
                'section_key' => $sectionKey,
                'field_key' => $fieldKey,
            ],
            $payload
        );
    }

    /**
     * @return Collection<int, OrganizationRevisionFieldUpdate|ModuleRevisionFieldUpdate>
     */
    public function pendingForReviewable(Model $reviewable): Collection
    {
        return $this->baseQueryFor($reviewable)
            ->whereNull('acknowledged_at')
            ->get();
    }

    /**
     * Latest row per (section_key, field_key) for $reviewable, regardless of
     * acknowledgement state — used by the admin show page to render the
     * "Updated" diff and decide whether to reset that field's review state to
     * pending.
     *
     * @return Collection<int, OrganizationRevisionFieldUpdate|ModuleRevisionFieldUpdate>
     */
    public function latestForReviewable(Model $reviewable): Collection
    {
        return $this->baseQueryFor($reviewable)
            ->orderByDesc('resubmitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($row): string => (string) $row->section_key.'.'.(string) $row->field_key)
            ->values();
    }

    /**
     * Build the `(section_key.field_key) => diff metadata` map the admin
     * `module-show.blade.php` uses to render `Updated` badges + old → new
     * previews. Mirrors the shape produced by `AdminController::showRenewal`.
     *
     * @param  Collection<int, OrganizationRevisionFieldUpdate|ModuleRevisionFieldUpdate>  $latestUpdates
     * @return array<string, array<string, array{is_updated: bool, old_value: ?string, new_value: ?string, old_file_meta: ?array<string, mixed>, new_file_meta: ?array<string, mixed>, resubmitted_at: ?string}>>
     */
    public function diffMapFromLatestUpdates(Collection $latestUpdates): array
    {
        $map = [];
        foreach ($latestUpdates as $row) {
            $map[(string) $row->section_key][(string) $row->field_key] = [
                'is_updated' => $row->acknowledged_at === null,
                'old_value' => $row->old_value,
                'new_value' => $row->new_value,
                'old_file_meta' => is_array($row->old_file_meta) ? $row->old_file_meta : null,
                'new_file_meta' => is_array($row->new_file_meta) ? $row->new_file_meta : null,
                'resubmitted_at' => optional($row->resubmitted_at)->toDateTimeString(),
            ];
        }

        return $map;
    }

    /**
     * Auto-acknowledge any unack'd updates whose matching field review just
     * left `pending` — i.e. the admin re-rated the resubmitted item as
     * Passed or Revision again. Mirrors
     * `AdminController::autoAcknowledgeReviewedRegistrationFieldUpdates`.
     *
     * @param  array<string, mixed>  $fieldReviews
     */
    public function acknowledgeReviewedFields(Model $reviewable, array $fieldReviews, int $adminId): void
    {
        $pending = $this->pendingForReviewable($reviewable);
        if ($pending->isEmpty()) {
            return;
        }

        $acknowledged = 0;
        foreach ($pending as $update) {
            $status = (string) data_get($fieldReviews, $update->section_key.'.'.$update->field_key.'.status', 'pending');
            if ($status === 'pending') {
                continue;
            }
            $update->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $adminId,
            ]);
            $acknowledged++;
        }

        if ($acknowledged > 0) {
            Log::info('Review workflow: acknowledged officer field updates', [
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id' => (int) $reviewable->getKey(),
                'acknowledged_count' => $acknowledged,
                'admin_id' => $adminId,
            ]);
        }
    }

    /**
     * Number of unacknowledged officer updates currently outstanding for
     * $reviewable. Used by the index/list pages to decide whether to show the
     * "Updated" badge instead of the raw status.
     */
    public function pendingUpdateCount(Model $reviewable): int
    {
        return $this->baseQueryFor($reviewable)
            ->whereNull('acknowledged_at')
            ->count();
    }

    /**
     * Return the underlying Eloquent query builder for the right backing
     * table, scoped to $reviewable. Single source of truth so the
     * polymorphic vs. submission-bound split lives in exactly one place.
     */
    private function baseQueryFor(Model $reviewable): Builder
    {
        if ($reviewable instanceof OrganizationSubmission) {
            return OrganizationRevisionFieldUpdate::query()
                ->where('organization_submission_id', (int) $reviewable->id);
        }

        return ModuleRevisionFieldUpdate::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', (int) $reviewable->getKey());
    }
}
