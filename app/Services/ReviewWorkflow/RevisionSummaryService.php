<?php

namespace App\Services\ReviewWorkflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Generic revision summary builder for the officer-side detail page.
 *
 * Behaves like `OrganizationRegistrationRevisionSummaryService` but accepts
 * any reviewable model + the JSON column name where field reviews live.
 * Registration deliberately keeps its dedicated service to avoid risking
 * regressions in the finished module — this one powers
 * Renewal / Calendar / Proposal / Report.
 *
 * Output shape mirrors the registration service so the existing officer
 * detail blade already renders it correctly:
 *   [
 *     'groups' => [ { section_key, section_title, title, items: [...] }, ... ],
 *     'field_notes' => [ "section.field" => "note", ... ],
 *     'general_remarks' => string|null,
 *     'has_revisions' => bool,
 *   ]
 */
class RevisionSummaryService
{
    /**
     * @param  Model  $reviewable                 Any model with a *_field_reviews JSON column.
     * @param  string  $fieldReviewsColumn        e.g. "renewal_field_reviews", "admin_field_reviews".
     * @param  array<string, string>  $sectionTitles  Optional override for section_key => title.
     * @param  string|null  $generalRemarks       Free-text remarks already loaded from the model.
     * @param  callable|null  $itemHrefResolver   Receives ($sectionKey, $fieldKey, $anchorId) → string|null.
     * @param  array<string, bool>  $resolvedItemSet (section_key.field_key => true) for items
     *         that the officer already resubmitted, so they get filtered out of the active list.
     * @return array{groups: array<int, array<string, mixed>>, field_notes: array<string, string>, general_remarks: ?string, has_revisions: bool}
     */
    public function build(
        Model $reviewable,
        string $fieldReviewsColumn,
        array $sectionTitles = [],
        ?string $generalRemarks = null,
        ?callable $itemHrefResolver = null,
        array $resolvedItemSet = [],
    ): array {
        $rawFieldReviews = $reviewable->getAttribute($fieldReviewsColumn);
        $fieldReviews = is_array($rawFieldReviews) ? $rawFieldReviews : [];

        $groups = [];
        $fieldNotes = [];

        foreach ($fieldReviews as $sectionKey => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            $sectionKeyValue = (string) $sectionKey;

            $items = [];
            foreach ($fields as $fieldKey => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
                if (! in_array($status, ['flagged', 'revision', 'needs_revision', 'for_revision'], true)) {
                    continue;
                }

                $note = trim((string) ($row['note'] ?? ''));
                if ($note === '') {
                    $note = 'Revision requested.';
                }

                $fieldKeyValue = (string) $fieldKey;
                if ($fieldKeyValue === '') {
                    continue;
                }

                if (isset($resolvedItemSet[$sectionKeyValue.'.'.$fieldKeyValue])) {
                    continue;
                }

                $fieldLabel = trim((string) ($row['label'] ?? ''));
                if ($fieldLabel === '') {
                    $fieldLabel = ucwords(str_replace('_', ' ', $fieldKeyValue));
                }

                $anchorId = $this->buildAnchorId($sectionKeyValue, $fieldKeyValue);
                $href = $itemHrefResolver !== null
                    ? $itemHrefResolver($sectionKeyValue, $fieldKeyValue, $anchorId)
                    : null;

                $items[] = [
                    'field_key' => $fieldKeyValue,
                    'field_label' => $fieldLabel,
                    'field' => $fieldLabel,
                    'note' => $note,
                    'anchor_id' => $sectionKeyValue === 'requirements' ? $anchorId : '',
                    'href' => $href,
                ];
                $fieldNotes[$sectionKeyValue.'.'.$fieldKeyValue] = $note;
            }

            if ($items === []) {
                continue;
            }

            $sectionTitle = $sectionTitles[$sectionKeyValue]
                ?? ucwords(str_replace('_', ' ', $sectionKeyValue));

            $groups[] = [
                'section_key' => $sectionKeyValue,
                'section_title' => $sectionTitle,
                'title' => $sectionTitle,
                'items' => $items,
            ];
        }

        $generalRemarksFinal = $generalRemarks !== null ? trim($generalRemarks) : '';
        $generalRemarksFinal = $generalRemarksFinal !== '' ? $generalRemarksFinal : null;

        Log::info('Review workflow: revision summary built', [
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => (int) $reviewable->getKey(),
            'group_count' => count($groups),
            'has_general_remarks' => $generalRemarksFinal !== null,
        ]);

        return [
            'groups' => $groups,
            'field_notes' => $fieldNotes,
            'general_remarks' => $generalRemarksFinal,
            'has_revisions' => $groups !== [],
        ];
    }

    private function buildAnchorId(string $sectionKey, string $fieldKey): string
    {
        $section = $this->sanitizeAnchorSegment($sectionKey);
        $field = $this->sanitizeAnchorSegment($fieldKey);
        $prefix = $sectionKey === 'requirements' ? 'revision-file-' : 'revision-field-';

        return $prefix.$section.'-'.$field;
    }

    private function sanitizeAnchorSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: 'field';

        return trim($value, '-') ?: 'field';
    }
}
