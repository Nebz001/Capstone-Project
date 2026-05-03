<?php

namespace App\Services;

use App\Models\OrganizationRevisionFieldUpdate;
use App\Models\OrganizationSubmission;
use Illuminate\Support\Facades\Log;

class OrganizationRegistrationRevisionSummaryService
{
    /**
     * @return array{
     *   groups: array<int, array{section_key: string, section_title: string, title: string, items: array<int, array{field_key: string, field_label: string, field: string, note: string, anchor_id: string, href: ?string}>}>,
     *   field_notes: array<string, string>,
     *   general_remarks: string|null,
     *   has_revisions: bool
     * }
     */
    public function buildForSubmission(OrganizationSubmission $submission): array
    {
        $fieldReviews = $submission->isRenewal()
            ? (is_array($submission->renewal_field_reviews) ? $submission->renewal_field_reviews : [])
            : (is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : []);

        $pendingUpdateRows = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->whereNull('acknowledged_at')
            ->get(['section_key', 'field_key', 'new_file_meta']);

        $pendingRevisionItemSet = [];
        $pendingRequirementUpdateKeySet = [];
        foreach ($pendingUpdateRows as $row) {
            $sectionKey = (string) ($row->section_key ?? '');
            $fieldKey = (string) ($row->field_key ?? '');
            if ($this->isNonReviewableApplicationRegistrationField($sectionKey, $fieldKey)) {
                continue;
            }
            if ($this->isNonReviewableOrganizationalRegistrationField($sectionKey, $fieldKey)) {
                continue;
            }
            if ($sectionKey !== '' && $fieldKey !== '') {
                $pendingRevisionItemSet[$sectionKey.'.'.$fieldKey] = true;
            }
            if ($sectionKey === 'requirements' && is_array($row->new_file_meta) && $fieldKey !== '') {
                $pendingRequirementUpdateKeySet[$fieldKey] = true;
            }
        }

        $sectionTitles = $submission->isRenewal()
            ? [
                'overview' => 'Application Information',
                'contact' => 'Account and Contact Information',
                'requirements' => 'Requirements Attached',
            ]
            : [
                'application' => 'Application Information',
                'contact' => 'Account and Contact Information',
                'adviser' => 'Adviser Information',
                'organizational' => 'Organization Information',
                'requirements' => 'Requirements Attached',
            ];

        $groups = [];
        $fieldNotes = [];
        foreach ($fieldReviews as $sectionKey => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            $items = [];
            foreach ($fields as $fieldKey => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if ($sectionKey === 'adviser' && (string) $fieldKey === 'status') {
                    continue;
                }
                if ($this->isNonReviewableApplicationRegistrationField((string) $sectionKey, (string) $fieldKey)) {
                    continue;
                }
                if ($this->isNonReviewableOrganizationalRegistrationField((string) $sectionKey, (string) $fieldKey)) {
                    continue;
                }
                $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
                if (! in_array($status, ['flagged', 'revision', 'needs_revision', 'for_revision'], true)) {
                    continue;
                }
                $rawNote = $row['note'] ?? null;
                if ($rawNote === null || $rawNote === false) {
                    continue;
                }
                if (is_int($rawNote) || is_float($rawNote)) {
                    if ($rawNote === 0) {
                        continue;
                    }
                }
                $note = trim((string) $rawNote);
                if ($note === '' || preg_match('/^(0+)(\\.0+)?$/', $note) === 1) {
                    continue;
                }

                $sectionKeyValue = (string) $sectionKey;
                $fieldKeyValue = (string) $fieldKey;
                if ($fieldKeyValue !== '' && isset($pendingRevisionItemSet[$sectionKeyValue.'.'.$fieldKeyValue])) {
                    continue;
                }
                if ($sectionKeyValue === 'requirements' && $fieldKeyValue !== '' && isset($pendingRequirementUpdateKeySet[$fieldKeyValue])) {
                    continue;
                }

                $fieldLabel = trim((string) ($row['label'] ?? ''));
                if ($fieldLabel === '') {
                    $fieldLabel = ucwords(str_replace('_', ' ', $fieldKeyValue));
                }
                $anchorId = $sectionKeyValue === 'requirements'
                    ? 'revision-file-'.$this->sanitizeAnchorSegment($sectionKeyValue).'-'.$this->sanitizeAnchorSegment($fieldKeyValue)
                    : 'revision-field-'.$this->sanitizeAnchorSegment($sectionKeyValue).'-'.$this->sanitizeAnchorSegment($fieldKeyValue);
                $items[] = [
                    'field_key' => $fieldKeyValue,
                    'field_label' => $fieldLabel,
                    'field' => $fieldLabel,
                    'note' => $note,
                    'anchor_id' => $anchorId,
                    'href' => ($sectionKeyValue === 'requirements' && ! $submission->isRenewal())
                        ? route('organizations.submitted-documents.registrations.show', $submission).'?revision_target='.$anchorId
                        : null,
                ];
                $fieldNotes[$sectionKeyValue.'.'.$fieldKeyValue] = $note;
            }

            if ($items === []) {
                continue;
            }
            $sectionTitle = $sectionTitles[(string) $sectionKey] ?? ucwords(str_replace('_', ' ', (string) $sectionKey));
            $groups[] = [
                'section_key' => (string) $sectionKey,
                'section_title' => $sectionTitle,
                'title' => $sectionTitle,
                'items' => $items,
            ];
        }

        $generalRemarks = trim((string) ($submission->additional_remarks ?? ''));
        $generalRemarks = $generalRemarks !== '' ? $generalRemarks : null;

        Log::info('Active revision summary built', [
            'registration_id' => (int) $submission->id,
            'organization_id' => (int) ($submission->organization_id ?? 0),
            'application_count' => count(array_values(array_filter($groups, fn (array $g): bool => (string) ($g['section_key'] ?? '') !== 'requirements'))),
            'requirements_count' => count(array_values(array_filter($groups, fn (array $g): bool => (string) ($g['section_key'] ?? '') === 'requirements'))),
            'has_general_remarks' => (bool) $generalRemarks,
        ]);

        return [
            'groups' => $groups,
            'field_notes' => $fieldNotes,
            'general_remarks' => $generalRemarks,
            'has_revisions' => $groups !== [],
        ];
    }

    private function isNonReviewableApplicationRegistrationField(string $sectionKey, string $fieldKey): bool
    {
        return $sectionKey === 'application'
            && in_array($fieldKey, ['academic_year', 'submission_date', 'submitted_by'], true);
    }

    private function isNonReviewableOrganizationalRegistrationField(string $sectionKey, string $fieldKey): bool
    {
        return $sectionKey === 'organizational'
            && in_array($fieldKey, ['date_organized', 'founded_date', 'founded_at', 'date_created', 'created_at'], true);
    }

    private function sanitizeAnchorSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: 'field';

        return trim($value, '-') ?: 'field';
    }
}
