<?php

namespace App\Support;

use App\Models\ActivityProposal;

/**
 * Builds approval-routing step lists for RSO-facing progress UI.
 * Flows follow NU Lipa SDAO conventions: 3-step (SDAO pipeline) vs 8-step (activity proposal).
 */
final class SubmissionRoutingProgress
{
    /** @var list<string> */
    public const ACTIVITY_PROPOSAL_LABELS = [
        'Submitted',
        'Adviser',
        'Program Chair',
        'Dean',
        'SDAO',
        'Assistant Director',
        'Academic Director',
        'Executive Director',
    ];

    /** @var list<string> */
    public const SIMPLE_SDAO_LABELS = [
        'Submitted',
        'SDAO',
        'Final Status',
    ];

    /**
     * Registration, renewal, or activity calendar — Submitted → SDAO → Final Status.
     *
     * @return list<array{label: string, state: string}>
     */
    public static function stagesForSimpleSdaoPipeline(?string $rawStatus): array
    {
        $u = strtoupper((string) $rawStatus);

        return match (true) {
            $u === 'DRAFT' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'current'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'pending'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'pending'],
            ],
            in_array($u, ['PENDING', 'UNDER_REVIEW'], true) => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'current'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'pending'],
            ],
            $u === 'APPROVED' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'success'],
            ],
            $u === 'REJECTED' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'danger'],
            ],
            in_array($u, ['REVISION', 'REVISION_REQUIRED'], true) => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'warning'],
            ],
            default => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'current'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'pending'],
            ],
        };
    }

    /**
     * After activity report — same 3-stage shape, report-specific statuses.
     *
     * @return list<array{label: string, state: string}>
     */
    public static function stagesForActivityReport(?string $rawStatus): array
    {
        $u = strtoupper((string) $rawStatus);

        return match (true) {
            $u === 'PENDING' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'current'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'pending'],
            ],
            $u === 'REVIEWED' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'current'],
            ],
            $u === 'APPROVED' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'success'],
            ],
            $u === 'REJECTED' => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'danger'],
            ],
            default => [
                ['label' => self::SIMPLE_SDAO_LABELS[0], 'state' => 'completed'],
                ['label' => self::SIMPLE_SDAO_LABELS[1], 'state' => 'current'],
                ['label' => self::SIMPLE_SDAO_LABELS[2], 'state' => 'pending'],
            ],
        };
    }

    /**
     * Full activity proposal routing (8 steps).
     *
     * @return list<array{label: string, state: string}>
     */
    public static function stagesForActivityProposal(ActivityProposal $proposal): array
    {
        $proposal->loadMissing([
            'approvalWorkflows' => fn ($q) => $q->orderBy('approval_level'),
            'approvalWorkflows.office',
        ]);

        if ($proposal->approvalWorkflows->isNotEmpty()) {
            return self::proposalStagesFromWorkflows($proposal);
        }

        return self::proposalStagesFromStatus($proposal->proposal_status);
    }

    public static function summaryForSimpleSdao(?string $rawStatus): string
    {
        return match (strtoupper((string) $rawStatus)) {
            'DRAFT' => 'Complete and submit this document so it can enter the SDAO review queue.',
            'PENDING' => 'SDAO has received your submission and will process it in turn.',
            'UNDER_REVIEW' => 'SDAO is reviewing your submission.',
            'APPROVED' => 'This submission is approved and complete for this stage.',
            'REJECTED' => 'This submission was not approved. Check remarks and resubmit if applicable.',
            'REVISION', 'REVISION_REQUIRED' => 'SDAO requires changes. Update your submission and send it back for review.',
            default => 'Track where this document sits in the SDAO process.',
        };
    }

    public static function summaryForActivityReport(?string $rawStatus): string
    {
        return match (strtoupper((string) $rawStatus)) {
            'PENDING' => 'Your report is queued for SDAO review.',
            'REVIEWED' => 'SDAO has reviewed your report; watch for the final disposition.',
            'APPROVED' => 'This after-activity report is approved.',
            'REJECTED' => 'This report was not approved. Review feedback and resubmit if allowed.',
            default => 'Monitor SDAO review progress for this report.',
        };
    }

    public static function summaryForActivityProposal(?string $rawStatus): string
    {
        return match (strtoupper((string) $rawStatus)) {
            'DRAFT' => 'This proposal is still a draft. Submit it when ready so routing can begin.',
            'PENDING' => 'Your proposal is moving through the campus approval sequence before SDAO.',
            'UNDER_REVIEW' => 'SDAO is reviewing your proposal. Earlier routing steps are complete.',
            'APPROVED' => 'Your proposal is approved. Scheduled dates appear on the activity calendar when applicable.',
            'REJECTED' => 'This proposal was not approved. Open the full record for context and next steps.',
            'REVISION', 'REVISION_REQUIRED' => 'Updates are required. Open the proposal form to revise and resubmit.',
            default => 'Monitor this card for the latest status of your activity proposal.',
        };
    }

    /**
     * @return list<array{label: string, state: string}>
     */
    private static function proposalStagesFromStatus(?string $raw): array
    {
        $u = strtoupper((string) $raw);
        $labels = self::ACTIVITY_PROPOSAL_LABELS;
        $n = count($labels);
        $states = array_fill(0, $n, 'pending');

        if ($u === 'DRAFT') {
            $states[0] = 'current';

            return self::zipProposalStages($labels, $states);
        }

        $states[0] = 'completed';

        if ($u === 'APPROVED') {
            for ($i = 1; $i < $n - 1; $i++) {
                $states[$i] = 'completed';
            }
            $states[$n - 1] = 'success';

            return self::zipProposalStages($labels, $states);
        }

        if ($u === 'REJECTED') {
            for ($i = 1; $i < $n - 1; $i++) {
                $states[$i] = 'completed';
            }
            $states[$n - 1] = 'danger';

            return self::zipProposalStages($labels, $states);
        }

        if (in_array($u, ['REVISION', 'REVISION_REQUIRED'], true)) {
            for ($i = 1; $i < $n - 1; $i++) {
                $states[$i] = 'completed';
            }
            $states[$n - 1] = 'warning';

            return self::zipProposalStages($labels, $states);
        }

        if ($u === 'PENDING') {
            $states[1] = 'current';

            return self::zipProposalStages($labels, $states);
        }

        if ($u === 'UNDER_REVIEW') {
            for ($i = 1; $i <= 3; $i++) {
                $states[$i] = 'completed';
            }
            $states[4] = 'current';

            return self::zipProposalStages($labels, $states);
        }

        $states[1] = 'current';

        return self::zipProposalStages($labels, $states);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $states
     * @return list<array{label: string, state: string}>
     */
    private static function zipProposalStages(array $labels, array $states): array
    {
        $out = [];
        foreach ($labels as $i => $label) {
            $out[] = ['label' => $label, 'state' => $states[$i] ?? 'pending'];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, state: string}>
     */
    private static function proposalStagesFromWorkflows(ActivityProposal $proposal): array
    {
        $labels = self::ACTIVITY_PROPOSAL_LABELS;
        $n = count($labels);
        $states = array_fill(0, $n, 'pending');

        $draft = strtoupper((string) ($proposal->proposal_status ?? '')) === 'DRAFT';
        if ($draft) {
            $states[0] = 'current';

            return self::zipProposalStages($labels, $states);
        }

        $states[0] = 'completed';

        $mappedAny = false;
        foreach ($proposal->approvalWorkflows as $w) {
            $idx = self::matchOfficeToProposalRouteIndex($w->office?->office_name);
            if ($idx === null) {
                continue;
            }
            $mappedAny = true;
            $idx = max(1, min($n - 1, $idx));
            $ds = strtoupper((string) $w->decision_status);
            $states[$idx] = match (true) {
                $ds === 'APPROVED' => 'completed',
                $ds === 'REJECTED' => 'danger',
                $ds === 'REVISION_REQUIRED' => 'warning',
                $w->current_step => 'current',
                default => 'pending',
            };
        }

        if (! $mappedAny) {
            return self::proposalStagesFromStatus($proposal->proposal_status);
        }

        self::finalizeProposalWorkflowStates($states, $n);

        return self::zipProposalStages($labels, $states);
    }

    /**
     * @param  array<int, string>  $states
     */
    private static function finalizeProposalWorkflowStates(array &$states, int $n): void
    {
        $terminalAt = -1;
        for ($i = $n - 1; $i >= 1; $i--) {
            if (in_array($states[$i], ['danger', 'warning', 'success'], true)) {
                $terminalAt = $i;
                break;
            }
        }

        if ($terminalAt > 0) {
            for ($j = 1; $j < $terminalAt; $j++) {
                if ($states[$j] === 'pending') {
                    $states[$j] = 'completed';
                }
            }

            return;
        }

        $currentAt = -1;
        for ($i = 1; $i < $n; $i++) {
            if ($states[$i] === 'current') {
                $currentAt = $i;
                break;
            }
        }

        if ($currentAt > 0) {
            for ($j = 1; $j < $currentAt; $j++) {
                if ($states[$j] === 'pending') {
                    $states[$j] = 'completed';
                }
            }

            return;
        }

        $lastCompleted = 0;
        for ($i = 1; $i < $n; $i++) {
            if ($states[$i] === 'completed') {
                $lastCompleted = $i;
            }
        }

        if ($lastCompleted + 1 < $n && $states[$lastCompleted + 1] === 'pending') {
            $states[$lastCompleted + 1] = 'current';
        }
    }

    private static function matchOfficeToProposalRouteIndex(?string $officeName): ?int
    {
        if ($officeName === null || trim($officeName) === '') {
            return null;
        }

        $t = mb_strtolower($officeName);

        return match (true) {
            str_contains($t, 'executive director') => 7,
            str_contains($t, 'academic director') => 6,
            str_contains($t, 'assistant director') => 5,
            str_contains($t, 'program chair') || str_contains($t, 'programme chair') => 2,
            str_contains($t, 'sdao') || str_contains($t, 'student development') || str_contains($t, 'student affairs') => 4,
            str_contains($t, 'dean') && ! str_contains($t, 'director') => 3,
            str_contains($t, 'adviser') || str_contains($t, 'advisor') => 1,
            default => null,
        };
    }
}
