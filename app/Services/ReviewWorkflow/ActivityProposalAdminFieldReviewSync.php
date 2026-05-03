<?php

namespace App\Services\ReviewWorkflow;

use App\Models\ActivityProposal;
use App\Models\ProposalFieldReview;
use Illuminate\Support\Collection;

/**
 * Mirrors role-approver field decisions (proposal_field_reviews) into the
 * officer-facing admin_field_reviews JSON so Submitted Documents can surface
 * revision notes, file replace gates, and RevisionSummaryService banners.
 */
class ActivityProposalAdminFieldReviewSync
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function labelSchema(): array
    {
        return [
            'step1_request_form' => [
                'step1_proposal_option' => 'Proposal Option',
                'step1_rso_name' => 'RSO Name',
                'step1_activity_title' => 'Title of Activity',
                'step1_linked_activity_calendar' => 'Linked Activity Calendar',
                'step1_calendar_activity_row' => 'Calendar Activity Row',
                'step1_partner_entities' => 'Partner Entities',
                'step1_nature_of_activity' => 'Nature of Activity',
                'step1_type_of_activity' => 'Type of Activity',
                'step1_target_sdg' => 'Target SDG',
                'step1_proposed_budget' => 'Step 1 Proposed Budget',
                'step1_budget_source' => 'Step 1 Budget Source',
                'step1_activity_date' => 'Date of Activity',
                'step1_venue' => 'Venue',
                'step1_request_letter' => 'Upload Request Letter',
                'step1_speaker_resume' => 'Resume of Speaker',
                'step1_post_survey_form' => 'Sample Post-Survey Form',
            ],
            'step2_submission' => [
                'step2_organization_logo' => 'Organization Logo',
                'step2_organization' => 'Organization (Form)',
                'step2_academic_year' => 'Academic Year',
                'step2_department' => 'Department',
                'step2_program' => 'Program',
                'step2_activity_title' => 'Project / Activity Title',
                'step2_proposed_dates' => 'Proposed Dates',
                'step2_proposed_time' => 'Proposed Time',
                'step2_venue' => 'Venue',
                'step2_overall_goal' => 'Overall Goal',
                'step2_specific_objectives' => 'Specific Objectives',
                'step2_criteria_mechanics' => 'Criteria / Mechanics',
                'step2_program_flow' => 'Program Flow',
                'step2_budget_total' => 'Proposed Budget (Total)',
                'step2_source_of_funding' => 'Source of Funding',
                'step2_budget_table' => 'Detailed Budget Table',
                'step2_external_funding_support' => 'External Funding Support',
                'step2_submitted' => 'Submitted',
            ],
            'additional' => [
                'step2_resume_resource_persons' => 'Resume of Resource Person/s',
            ],
        ];
    }

    public static function adminSectionForFieldKey(string $fieldKey): ?string
    {
        if ($fieldKey === 'step2_resume_resource_persons') {
            return 'additional';
        }
        if (str_starts_with($fieldKey, 'step1_')) {
            return 'step1_request_form';
        }
        if (str_starts_with($fieldKey, 'step2_')) {
            return 'step2_submission';
        }

        return null;
    }

    /**
     * @param  Collection<string, ProposalFieldReview>  $persistedReviews
     * @param  list<string>  $reviewableKeys
     */
    public function syncFromProposalFieldReviews(ActivityProposal $proposal, Collection $persistedReviews, array $reviewableKeys): void
    {
        $schema = self::labelSchema();
        $admin = is_array($proposal->admin_field_reviews) ? $proposal->admin_field_reviews : [];

        foreach ($reviewableKeys as $fieldKey) {
            $review = $persistedReviews->get($fieldKey);
            if (! $review instanceof ProposalFieldReview) {
                continue;
            }
            $section = self::adminSectionForFieldKey($fieldKey);
            if ($section === null || ! isset($schema[$section])) {
                continue;
            }
            $label = $schema[$section][$fieldKey] ?? (string) $review->field_label;

            if ($review->status === 'revision') {
                $admin[$section][$fieldKey] = [
                    'label' => $label,
                    'status' => 'flagged',
                    'note' => (string) ($review->comment ?? ''),
                ];
            } else {
                $admin[$section][$fieldKey] = [
                    'label' => $label,
                    'status' => 'passed',
                    'note' => null,
                ];
            }
        }

        $proposal->admin_field_reviews = $admin;
        $proposal->save();
    }
}
