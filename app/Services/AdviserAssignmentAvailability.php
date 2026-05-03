<?php

namespace App\Services;

use App\Models\OrganizationAdviser;
use App\Models\OrganizationSubmission;
use Illuminate\Support\Facades\DB;

/**
 * Enforces one active faculty-adviser assignment per user across RSOs,
 * based on {@see OrganizationAdviser} rows tied to non-rejected submissions.
 */
class AdviserAssignmentAvailability
{
    public const UNAVAILABLE_REASON = 'Already assigned to another RSO';

    public const VALIDATION_MESSAGE = 'This adviser is already assigned to another organization.';

    /**
     * True when the adviser user already has a blocking assignment on another (or same, when not excluded) organization.
     *
     * @param  ?int  $exceptOrganizationId  Ignore assignments for this organization (e.g. current org on renewal / renominate).
     */
    public function isAdviserUnavailable(int $adviserUserId, ?int $exceptOrganizationId = null): bool
    {
        if ($adviserUserId <= 0) {
            return true;
        }

        return isset($this->unavailableAdviserUserIdsMap([$adviserUserId], $exceptOrganizationId)[$adviserUserId]);
    }

    /**
     * @param  array<int>  $adviserUserIds
     * @return array<int, true> user_id => true for advisers who cannot take a new assignment
     */
    public function unavailableAdviserUserIdsMap(array $adviserUserIds, ?int $exceptOrganizationId = null): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $adviserUserIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $query = OrganizationAdviser::query()
            ->whereIn('user_id', $ids)
            ->whereNull('relieved_at')
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) != ?', ['rejected']);
            });

        if ($exceptOrganizationId !== null && $exceptOrganizationId > 0) {
            $query->where('organization_id', '!=', $exceptOrganizationId);
        }

        $query->where(function ($q): void {
            $q->where(function ($sub): void {
                $sub->whereNotNull('submission_id')
                    ->whereExists(function ($exists): void {
                        $exists->select(DB::raw('1'))
                            ->from('organization_submissions as os')
                            ->whereColumn('os.id', 'organization_advisers.submission_id')
                            ->whereNull('os.deleted_at')
                            ->where('os.status', '!=', OrganizationSubmission::STATUS_REJECTED);
                    });
            })->orWhereNull('submission_id');
        });

        $blocked = $query->distinct()->pluck('user_id');

        $map = [];
        foreach ($blocked as $uid) {
            $map[(int) $uid] = true;
        }

        return $map;
    }
}
