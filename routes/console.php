<?php

use App\Models\ActivityProposal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:backfill-role-id {--dry-run : Preview changes without updating users.role_id}', function () {
    $dryRun = (bool) $this->option('dry-run');

    if (! Schema::hasColumns('users', ['id', 'email', 'role_type', 'role_id'])) {
        $this->error('Users table is missing one or more required columns: id, email, role_type, role_id.');

        return self::FAILURE;
    }

    $requiredRoles = [
        'rso_president',
        'adviser',
        'program_chair',
        'dean',
        'academic_director',
        'executive_director',
        'sdao_staff',
        'admin',
    ];

    /** @var array<string, int> $roleIdsByName */
    $roleIdsByName = Role::query()
        ->whereIn('name', $requiredRoles)
        ->pluck('id', 'name')
        ->all();

    $missingRoles = array_values(array_diff($requiredRoles, array_keys($roleIdsByName)));
    if ($missingRoles !== []) {
        $this->error('Cannot continue. Missing rows in roles table: '.implode(', ', $missingRoles));
        $this->line('Run roles seeder first: php artisan db:seed --class=RolesTableSeeder');

        return self::FAILURE;
    }

    $adminEmails = collect(config('sdao.admin_accounts', []))
        ->pluck('email')
        ->filter()
        ->map(fn ($email) => mb_strtolower(trim((string) $email)))
        ->values()
        ->all();

    $stats = [
        'processed' => 0,
        'mapped' => 0,
        'updated' => 0,
        'already_mapped' => 0,
        'skipped_unclear' => 0,
        'manual_review' => 0,
    ];

    $manualReviewRows = [];

    $this->line($dryRun
        ? 'Running in dry-run mode (no database changes).'
        : 'Backfilling users.role_id with conservative mapping rules.'
    );

    User::query()
        ->select(['id', 'email', 'role_type', 'role_id'])
        ->orderBy('id')
        ->chunkById(200, function ($users) use (
            $dryRun,
            $adminEmails,
            $roleIdsByName,
            &$stats,
            &$manualReviewRows
        ): void {
            foreach ($users as $user) {
                $stats['processed']++;

                $suggestedRole = null;
                $reason = null;

                if ($user->role_type === 'ORG_OFFICER') {
                    $officer = DB::table('organization_officers')
                        ->where('user_id', $user->id)
                        ->orderByRaw("CASE officer_status WHEN 'ACTIVE' THEN 0 ELSE 1 END")
                        ->orderByDesc('id')
                        ->first(['position_title']);

                    if (! $officer || trim((string) $officer->position_title) === '') {
                        $reason = 'ORG_OFFICER without organization_officers position_title';
                    } else {
                        $title = mb_strtolower((string) $officer->position_title);

                        if (str_contains($title, 'president')) {
                            $suggestedRole = 'rso_president';
                        } else {
                            $reason = "ORG_OFFICER position_title '{$officer->position_title}' is not clearly mappable";
                        }
                    }
                } elseif ($user->role_type === 'ADMIN') {
                    $email = mb_strtolower((string) $user->email);
                    if (in_array($email, $adminEmails, true)) {
                        $suggestedRole = 'admin';
                    } else {
                        $reason = 'ADMIN user email not listed in config/sdao.php admin_accounts';
                    }
                } elseif ($user->role_type === 'APPROVER') {
                    $officeNames = DB::table('approval_workflows as aw')
                        ->join('offices as o', 'o.id', '=', 'aw.office_id')
                        ->where('aw.user_id', $user->id)
                        ->pluck('o.office_name')
                        ->filter(fn ($name) => trim((string) $name) !== '')
                        ->values();

                    $candidates = $officeNames
                        ->map(function ($officeName): ?string {
                            $name = mb_strtolower((string) $officeName);

                            return match (true) {
                                str_contains($name, 'executive director') => 'executive_director',
                                str_contains($name, 'academic director') => 'academic_director',
                                str_contains($name, 'program chair'), str_contains($name, 'programme chair') => 'program_chair',
                                str_contains($name, 'dean') && ! str_contains($name, 'director') => 'dean',
                                str_contains($name, 'adviser'), str_contains($name, 'advisor') => 'adviser',
                                str_contains($name, 'assistant director'),
                                str_contains($name, 'sdao'),
                                str_contains($name, 'student development'),
                                str_contains($name, 'student affairs') => 'sdao_staff',
                                default => null,
                            };
                        })
                        ->filter()
                        ->unique()
                        ->values();

                    if ($candidates->count() === 1) {
                        $suggestedRole = $candidates->first();
                    } elseif ($candidates->count() > 1) {
                        $reason = 'APPROVER matched multiple role candidates: '.$candidates->implode(', ');
                    } else {
                        $reason = 'APPROVER has no clear office-based mapping from approval_workflows';
                    }
                } else {
                    $reason = 'Unknown role_type value: '.(string) $user->role_type;
                }

                if ($suggestedRole === null) {
                    $stats['skipped_unclear']++;
                    $stats['manual_review']++;
                    $manualReviewRows[] = [
                        'id' => (string) $user->id,
                        'email' => (string) $user->email,
                        'role_type' => (string) $user->role_type,
                        'current_role_id' => (string) ($user->role_id ?? 'null'),
                        'suggested_role' => '-',
                        'reason' => (string) $reason,
                    ];
                    continue;
                }

                $targetRoleId = $roleIdsByName[$suggestedRole] ?? null;
                if (! $targetRoleId) {
                    $stats['manual_review']++;
                    $manualReviewRows[] = [
                        'id' => (string) $user->id,
                        'email' => (string) $user->email,
                        'role_type' => (string) $user->role_type,
                        'current_role_id' => (string) ($user->role_id ?? 'null'),
                        'suggested_role' => $suggestedRole,
                        'reason' => 'Suggested role is missing in roles table',
                    ];
                    continue;
                }

                if ((int) ($user->role_id ?? 0) === (int) $targetRoleId) {
                    $stats['mapped']++;
                    $stats['already_mapped']++;
                    continue;
                }

                if ($user->role_id !== null && (int) $user->role_id !== (int) $targetRoleId) {
                    $stats['manual_review']++;
                    $manualReviewRows[] = [
                        'id' => (string) $user->id,
                        'email' => (string) $user->email,
                        'role_type' => (string) $user->role_type,
                        'current_role_id' => (string) $user->role_id,
                        'suggested_role' => $suggestedRole,
                        'reason' => 'Existing role_id differs from inferred mapping; skipped to avoid overwrite',
                    ];
                    continue;
                }

                if (! $dryRun) {
                    User::query()
                        ->whereKey($user->id)
                        ->whereNull('role_id')
                        ->update(['role_id' => $targetRoleId]);
                }

                $stats['mapped']++;
                $stats['updated']++;
            }
        });

    $this->newLine();
    $this->info('Backfill summary');
    $this->line(' - Total users processed: '.$stats['processed']);
    $this->line(' - Users successfully mapped: '.$stats['mapped']);
    $this->line(' - Users skipped (unclear mapping): '.$stats['skipped_unclear']);
    $this->line(' - Users needing manual review: '.$stats['manual_review']);
    $this->line(' - Users updated this run: '.$stats['updated'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Users already mapped: '.$stats['already_mapped']);

    if ($manualReviewRows !== []) {
        $this->newLine();
        $this->warn('Manual review required for the following users:');
        $this->table(
            ['id', 'email', 'role_type', 'current_role_id', 'suggested_role', 'reason'],
            $manualReviewRows
        );
    }

    return self::SUCCESS;
})->purpose('One-time backfill for users.role_id using current role data with manual-review safeguards');

Artisan::command('submissions:migrate-legacy {--dry-run : Preview migration without writing new records}', function () {
    $dryRun = (bool) $this->option('dry-run');

    if (! Schema::hasTable('organization_submissions') || ! Schema::hasTable('submission_requirements')) {
        $this->error('New submission tables are missing. Run migrations first.');

        return self::FAILURE;
    }

    if (! Schema::hasTable('organization_registrations') || ! Schema::hasTable('organization_renewals')) {
        $this->error('Legacy tables are missing. Cannot run migration.');

        return self::FAILURE;
    }

    $this->line($dryRun
        ? 'Running in dry-run mode (no data will be written).'
        : 'Migrating legacy registrations/renewals into organization_submissions.'
    );

    $requirementLabels = [
        'req_letter_of_intent' => 'Letter of Intent',
        'req_application_form' => 'Application Form',
        'req_by_laws' => 'By Laws',
        'req_officers_list' => 'Updated List of Officers and Founders',
        'req_dean_endorsement' => 'Dean Endorsement (Faculty Adviser)',
        'req_proposed_projects' => 'Proposed Projects with Budgetary Requirements',
        'req_past_projects' => 'List of Past Projects',
        'req_financial_statement' => 'Financial Statement of the Previous Academic Year',
        'req_evaluation_summary' => 'Summary Evaluation of Past Projects',
        'req_others' => 'Others',
    ];

    $registrationRequirementKeys = [
        'req_letter_of_intent',
        'req_application_form',
        'req_by_laws',
        'req_officers_list',
        'req_dean_endorsement',
        'req_proposed_projects',
        'req_others',
    ];

    $renewalRequirementKeys = [
        'req_letter_of_intent',
        'req_application_form',
        'req_by_laws',
        'req_officers_list',
        'req_dean_endorsement',
        'req_proposed_projects',
        'req_past_projects',
        'req_financial_statement',
        'req_evaluation_summary',
        'req_others',
    ];

    $stats = [
        'registrations_processed' => 0,
        'renewals_processed' => 0,
        'submissions_created' => 0,
        'submissions_reused' => 0,
        'requirements_upserted' => 0,
        'skipped' => 0,
        'manual_review' => 0,
    ];

    $issues = [];

    $mapStatus = static function (?string $legacyStatus): string {
        return match (strtoupper((string) $legacyStatus)) {
            'PENDING' => 'pending',
            'UNDER_REVIEW' => 'under_review',
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            'REVISION', 'REVISION_REQUIRED' => 'revision',
            'DRAFT' => 'draft',
            default => 'draft',
        };
    };

    $mapApprovalDecision = static function (?string $decision): ?string {
        return match (strtoupper((string) $decision)) {
            'APPROVED' => 'approved',
            'PROBATION' => 'probation',
            default => null,
        };
    };

    $stepForStatus = static function (string $status): int {
        return match ($status) {
            'draft' => 0,
            'pending' => 1,
            'under_review' => 2,
            'approved', 'rejected', 'revision' => 3,
            default => 0,
        };
    };

    $resolveAcademicTermId = function (?string $academicYear, ?string $submissionDate, string $source, int $legacyId) use (&$issues, &$stats, $dryRun): ?int {
        $rawYear = trim((string) $academicYear);
        if ($rawYear === '') {
            $issues[] = [
                'source' => $source,
                'legacy_id' => (string) $legacyId,
                'reason' => 'Missing academic_year; cannot resolve academic_term_id',
            ];
            $stats['skipped']++;
            $stats['manual_review']++;

            return null;
        }

        if (preg_match('/^(\d{4})-(\d{4})$/', $rawYear, $m) !== 1) {
            $issues[] = [
                'source' => $source,
                'legacy_id' => (string) $legacyId,
                'reason' => "Invalid academic_year format '{$rawYear}'",
            ];
            $stats['skipped']++;
            $stats['manual_review']++;

            return null;
        }

        $startYear = (int) $m[1];
        $endYear = (int) $m[2];
        if ($endYear !== $startYear + 1) {
            $issues[] = [
                'source' => $source,
                'legacy_id' => (string) $legacyId,
                'reason' => "Academic year '{$rawYear}' is not a contiguous YYYY-YYYY range",
            ];
            $stats['skipped']++;
            $stats['manual_review']++;

            return null;
        }

        $submissionDate = trim((string) $submissionDate) !== '' ? (string) $submissionDate : null;

        $termQuery = DB::table('academic_terms')->where('academic_year', $rawYear);
        if ($submissionDate !== null) {
            $termByDate = (clone $termQuery)
                ->whereDate('starts_at', '<=', $submissionDate)
                ->whereDate('ends_at', '>=', $submissionDate)
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first(['id']);
            if ($termByDate) {
                return (int) $termByDate->id;
            }
        }

        $existingTerms = (clone $termQuery)->orderByDesc('is_active')->orderBy('id')->get(['id', 'semester']);
        if ($existingTerms->count() === 1) {
            return (int) $existingTerms->first()->id;
        }

        if ($existingTerms->count() > 1) {
            $issues[] = [
                'source' => $source,
                'legacy_id' => (string) $legacyId,
                'reason' => "Multiple academic_terms exist for '{$rawYear}' and no unique date match was found",
            ];
            $stats['skipped']++;
            $stats['manual_review']++;

            return null;
        }

        $fallbackStart = sprintf('%d-06-01', $startYear);
        $fallbackEnd = sprintf('%d-10-31', $startYear);

        if ($dryRun) {
            $issues[] = [
                'source' => $source,
                'legacy_id' => (string) $legacyId,
                'reason' => "Would create fallback academic_term '{$rawYear}' (semester: first); rerun without --dry-run to create",
            ];

            return 0;
        }

        $termId = DB::table('academic_terms')->insertGetId([
            'academic_year' => $rawYear,
            'semester' => 'first',
            'starts_at' => $fallbackStart,
            'ends_at' => $fallbackEnd,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $issues[] = [
            'source' => $source,
            'legacy_id' => (string) $legacyId,
            'reason' => "Created fallback academic_term '{$rawYear}' (semester: first) due to missing exact term mapping",
        ];
        $stats['manual_review']++;

        return (int) $termId;
    };

    $migrateLegacyRows = function (
        string $table,
        string $type,
        string $statusColumn,
        string $notesColumn,
        array $requirementKeys
    ) use (
        $dryRun,
        $requirementLabels,
        &$stats,
        &$issues,
        $mapStatus,
        $mapApprovalDecision,
        $stepForStatus,
        $resolveAcademicTermId
    ): void {
        DB::table($table)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (
                $table,
                $type,
                $statusColumn,
                $notesColumn,
                $requirementKeys,
                $dryRun,
                $requirementLabels,
                &$stats,
                &$issues,
                $mapStatus,
                $mapApprovalDecision,
                $stepForStatus,
                $resolveAcademicTermId
            ): void {
                foreach ($rows as $row) {
                    $stats[$type === 'registration' ? 'registrations_processed' : 'renewals_processed']++;

                    $legacyId = (int) $row->id;
                    $source = $table.'#'.$legacyId;

                    $academicTermId = $resolveAcademicTermId(
                        (string) ($row->academic_year ?? ''),
                        (string) ($row->submission_date ?? ''),
                        $table,
                        $legacyId
                    );

                    if ($academicTermId === null || $academicTermId === 0) {
                        continue;
                    }

                    $mappedStatus = $mapStatus((string) ($row->{$statusColumn} ?? ''));
                    $submissionPayload = [
                        'organization_id' => (int) $row->organization_id,
                        'submitted_by' => (int) $row->user_id,
                        'academic_term_id' => $academicTermId,
                        'type' => $type,
                        'contact_person' => $row->contact_person,
                        'contact_no' => $row->contact_no,
                        'contact_email' => $row->contact_email,
                        'submission_date' => $row->submission_date,
                        'notes' => $row->{$notesColumn},
                        'status' => $mappedStatus,
                        'current_approval_step' => $stepForStatus($mappedStatus),
                        'additional_remarks' => $row->additional_remarks,
                        'approval_decision' => $mapApprovalDecision((string) ($row->approval_decision ?? '')),
                    ];

                    $matchQuery = DB::table('organization_submissions')
                        ->where('organization_id', $submissionPayload['organization_id'])
                        ->where('submitted_by', $submissionPayload['submitted_by'])
                        ->where('academic_term_id', $submissionPayload['academic_term_id'])
                        ->where('type', $submissionPayload['type'])
                        ->whereDate('submission_date', (string) $submissionPayload['submission_date'])
                        ->where('notes', $submissionPayload['notes'])
                        ->where('status', $submissionPayload['status']);

                    $existingCount = (clone $matchQuery)->count();
                    if ($existingCount > 1) {
                        $issues[] = [
                            'source' => $source,
                            'legacy_id' => (string) $legacyId,
                            'reason' => 'Multiple candidate organization_submissions rows found; skipped to avoid incorrect linkage',
                        ];
                        $stats['skipped']++;
                        $stats['manual_review']++;
                        continue;
                    }

                    $submissionId = null;

                    if ($existingCount === 1) {
                        $existing = $matchQuery->first(['id']);
                        $submissionId = (int) $existing->id;
                        $stats['submissions_reused']++;
                    } elseif (! $dryRun) {
                        $submissionId = (int) DB::table('organization_submissions')->insertGetId(array_merge(
                            $submissionPayload,
                            [
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]
                        ));
                        $stats['submissions_created']++;
                    } else {
                        $stats['submissions_created']++;
                        continue;
                    }

                    foreach ($requirementKeys as $key) {
                        $label = $requirementLabels[$key] ?? $key;
                        $isSubmitted = (bool) ($row->{$key} ?? false);

                        if ($key === 'req_others' && ! empty($row->req_others_specify)) {
                            $label .= ' ('.$row->req_others_specify.')';
                        }

                        if (! $dryRun) {
                            DB::table('submission_requirements')->updateOrInsert(
                                [
                                    'submission_id' => $submissionId,
                                    'requirement_key' => $key,
                                ],
                                [
                                    'label' => $label,
                                    'is_submitted' => $isSubmitted,
                                    'updated_at' => now(),
                                    'created_at' => now(),
                                ]
                            );
                        }

                        $stats['requirements_upserted']++;
                    }
                }
            });
    };

    $migrateLegacyRows(
        'organization_registrations',
        'registration',
        'registration_status',
        'registration_notes',
        $registrationRequirementKeys
    );

    $migrateLegacyRows(
        'organization_renewals',
        'renewal',
        'renewal_status',
        'renewal_notes',
        $renewalRequirementKeys
    );

    $this->newLine();
    $this->info('Legacy submissions migration summary');
    $this->line(' - Registrations processed: '.$stats['registrations_processed']);
    $this->line(' - Renewals processed: '.$stats['renewals_processed']);
    $this->line(' - Organization submissions created: '.$stats['submissions_created'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Organization submissions reused: '.$stats['submissions_reused']);
    $this->line(' - Submission requirements upserted: '.$stats['requirements_upserted'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Records skipped: '.$stats['skipped']);
    $this->line(' - Records needing manual review: '.$stats['manual_review']);

    if ($issues !== []) {
        $this->newLine();
        $this->warn('Manual review / migration notes:');
        $this->table(
            ['source', 'legacy_id', 'reason'],
            array_slice($issues, 0, 200)
        );

        if (count($issues) > 200) {
            $this->line('Showing first 200 issues of '.count($issues).' total.');
        }
    }

    return self::SUCCESS;
})->purpose('One-time migration of legacy organization_registrations/renewals into organization_submissions');

Artisan::command('academic-terms:migrate-legacy {--dry-run : Preview term mapping/backfill without writing} {--create-missing : Create missing academic_terms when safely resolvable}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $createMissing = (bool) $this->option('create-missing');

    if (! Schema::hasTable('academic_terms')) {
        $this->error('academic_terms table is missing. Run migrations first.');

        return self::FAILURE;
    }

    $sources = [
        ['table' => 'organization_registrations', 'year_col' => 'academic_year', 'semester_col' => null],
        ['table' => 'organization_renewals', 'year_col' => 'academic_year', 'semester_col' => null],
        ['table' => 'activity_calendars', 'year_col' => 'academic_year', 'semester_col' => 'semester'],
        ['table' => 'activity_proposals', 'year_col' => 'academic_year', 'semester_col' => null],
    ];

    $this->line($dryRun
        ? 'Running in dry-run mode (no writes).'
        : 'Running academic-term migration support mapping.'
    );
    $this->line($createMissing
        ? 'Missing academic_terms may be created when mapping is unambiguous.'
        : 'Missing academic_terms will be reported only (creation disabled).'
    );

    $normalizeAcademicYear = static function (?string $value): ?string {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        $v = preg_replace('/\s+/', '', $v) ?? $v;
        $v = str_replace('/', '-', $v);

        if (preg_match('/^(\d{4})-(\d{4})$/', $v, $m) !== 1) {
            return null;
        }

        $start = (int) $m[1];
        $end = (int) $m[2];

        if ($end !== $start + 1) {
            return null;
        }

        return sprintf('%04d-%04d', $start, $end);
    };

    $normalizeSemester = static function (?string $value): ?string {
        $v = mb_strtolower(trim((string) $value));
        if ($v === '') {
            return null;
        }

        return match (true) {
            in_array($v, ['first', '1st', 'first_sem', 'first_semester', 'term_1', '1'], true) => 'first',
            in_array($v, ['second', '2nd', 'second_sem', 'second_semester', 'term_2', '2'], true) => 'second',
            in_array($v, ['midyear', 'mid-year', 'summer', 'term_3', 'third', '3rd', '3'], true) => 'midyear',
            default => null,
        };
    };

    $stats = [
        'records_scanned' => 0,
        'matched_terms' => 0,
        'created_terms' => 0,
        'backfilled_rows' => 0,
        'unresolved_records' => 0,
    ];

    $issues = [];
    $distinctRows = [];
    $termCache = [];

    $resolveTermId = function (
        ?string $rawYear,
        ?string $rawSemester,
        ?string $submissionDate,
        string $source,
        int $rowId
    ) use (
        $normalizeAcademicYear,
        $normalizeSemester,
        $dryRun,
        $createMissing,
        &$stats,
        &$issues,
        &$termCache
    ): ?int {
        $year = $normalizeAcademicYear($rawYear);
        if ($year === null) {
            $stats['unresolved_records']++;
            $issues[] = [
                'source' => $source,
                'row_id' => (string) $rowId,
                'reason' => "Invalid/empty academic_year '{$rawYear}'",
            ];

            return null;
        }

        $semester = $normalizeSemester($rawSemester);
        $cacheKey = $year.'|'.($semester ?? '-');
        if (array_key_exists($cacheKey, $termCache)) {
            return $termCache[$cacheKey];
        }

        $query = DB::table('academic_terms')->where('academic_year', $year);

        if ($semester !== null) {
            $term = (clone $query)->where('semester', $semester)->first(['id']);
            if ($term) {
                return $termCache[$cacheKey] = (int) $term->id;
            }
        }

        if ($submissionDate !== null && trim($submissionDate) !== '') {
            $termByDate = (clone $query)
                ->whereDate('starts_at', '<=', $submissionDate)
                ->whereDate('ends_at', '>=', $submissionDate)
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first(['id', 'semester']);
            if ($termByDate) {
                return $termCache[$cacheKey] = (int) $termByDate->id;
            }
        }

        $terms = (clone $query)->orderByDesc('is_active')->orderBy('id')->get(['id', 'semester']);
        if ($terms->count() === 1 && $semester === null) {
            return $termCache[$cacheKey] = (int) $terms->first()->id;
        }

        if (! $createMissing) {
            $stats['unresolved_records']++;
            $issues[] = [
                'source' => $source,
                'row_id' => (string) $rowId,
                'reason' => $semester === null
                    ? "No unique academic_terms match for year {$year}"
                    : "No academic_terms match for {$year} / {$semester}",
            ];

            return null;
        }

        $targetSemester = $semester ?? 'first';
        $startYear = (int) substr($year, 0, 4);
        [$startsAt, $endsAt] = match ($targetSemester) {
            'first' => [sprintf('%d-06-01', $startYear), sprintf('%d-10-31', $startYear)],
            'second' => [sprintf('%d-11-01', $startYear), sprintf('%d-03-31', $startYear + 1)],
            'midyear' => [sprintf('%d-04-01', $startYear + 1), sprintf('%d-05-31', $startYear + 1)],
            default => [sprintf('%d-06-01', $startYear), sprintf('%d-10-31', $startYear)],
        };

        if ($dryRun) {
            $stats['created_terms']++;
            $issues[] = [
                'source' => $source,
                'row_id' => (string) $rowId,
                'reason' => "Would create academic_term {$year} / {$targetSemester}",
            ];

            return null;
        }

        $termId = (int) DB::table('academic_terms')->insertGetId([
            'academic_year' => $year,
            'semester' => $targetSemester,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stats['created_terms']++;

        $issues[] = [
            'source' => $source,
            'row_id' => (string) $rowId,
            'reason' => "Created academic_term {$year} / {$targetSemester}",
        ];

        return $termCache[$cacheKey] = $termId;
    };

    foreach ($sources as $source) {
        $table = $source['table'];
        $yearCol = $source['year_col'];
        $semesterCol = $source['semester_col'];

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $yearCol)) {
            continue;
        }

        $distinctQuery = DB::table($table)->select($yearCol);
        if ($semesterCol !== null && Schema::hasColumn($table, $semesterCol)) {
            $distinctQuery->addSelect($semesterCol);
        }
        $distinct = $distinctQuery->distinct()->orderBy($yearCol)->limit(200)->get();
        foreach ($distinct as $d) {
            $distinctRows[] = [
                'table' => $table,
                'academic_year' => (string) ($d->{$yearCol} ?? ''),
                'semester' => $semesterCol ? (string) ($d->{$semesterCol} ?? '') : '-',
            ];
        }

        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use (
            $table,
            $yearCol,
            $semesterCol,
            $resolveTermId,
            $dryRun,
            &$stats
        ): void {
            foreach ($rows as $row) {
                $stats['records_scanned']++;

                $submissionDate = property_exists($row, 'submission_date') ? (string) $row->submission_date : null;
                $termId = $resolveTermId(
                    (string) ($row->{$yearCol} ?? ''),
                    $semesterCol ? (string) ($row->{$semesterCol} ?? '') : null,
                    $submissionDate,
                    $table,
                    (int) $row->id
                );

                if ($termId !== null) {
                    $stats['matched_terms']++;
                }

                if ($termId === null || ! Schema::hasColumn($table, 'academic_term_id')) {
                    continue;
                }

                if (! $dryRun) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->where(function ($q): void {
                            $q->whereNull('academic_term_id')->orWhere('academic_term_id', 0);
                        })
                        ->update(['academic_term_id' => $termId]);
                }

                $stats['backfilled_rows']++;
            }
        });
    }

    $this->newLine();
    $this->info('Distinct legacy academic year/semester values (sample up to 200)');
    if ($distinctRows === []) {
        $this->line('No legacy rows found in scanned source tables.');
    } else {
        $this->table(['table', 'academic_year', 'semester'], array_slice($distinctRows, 0, 200));
        if (count($distinctRows) > 200) {
            $this->line('Showing first 200 distinct values of '.count($distinctRows).' total.');
        }
    }

    $this->newLine();
    $this->info('Academic term migration summary');
    $this->line(' - Records scanned: '.$stats['records_scanned']);
    $this->line(' - Matched terms: '.$stats['matched_terms']);
    $this->line(' - Newly created terms: '.$stats['created_terms'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Backfilled academic_term_id rows: '.$stats['backfilled_rows'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Unresolved records: '.$stats['unresolved_records']);

    if ($issues !== []) {
        $this->newLine();
        $this->warn('Unresolved/created mapping notes:');
        $this->table(['source', 'row_id', 'reason'], array_slice($issues, 0, 300));
        if (count($issues) > 300) {
            $this->line('Showing first 300 notes of '.count($issues).' total.');
        }
    }

    return self::SUCCESS;
})->purpose('One-time mapping of legacy academic_year/semester values into academic_terms and backfill support');

Artisan::command('proposals:migrate-budget-items {--dry-run : Preview migration without writing rows}', function () {
    $dryRun = (bool) $this->option('dry-run');

    if (! Schema::hasTable('activity_proposals') || ! Schema::hasTable('proposal_budget_items')) {
        $this->error('Required tables are missing (activity_proposals/proposal_budget_items). Run migrations first.');

        return self::FAILURE;
    }

    $stats = [
        'proposals_scanned' => 0,
        'proposals_with_json' => 0,
        'proposals_migrated' => 0,
        'proposals_skipped_existing' => 0,
        'items_inserted' => 0,
        'malformed_rows' => 0,
    ];

    $issues = [];

    ActivityProposal::query()
        ->select(['id', 'budget_breakdown_items'])
        ->orderBy('id')
        ->chunkById(200, function ($proposals) use ($dryRun, &$stats, &$issues): void {
            foreach ($proposals as $proposal) {
                $stats['proposals_scanned']++;

                $raw = $proposal->budget_breakdown_items;
                if ($raw === null || $raw === [] || $raw === '') {
                    continue;
                }

                $stats['proposals_with_json']++;

                $alreadyMigrated = DB::table('proposal_budget_items')
                    ->where('activity_proposal_id', $proposal->id)
                    ->exists();
                if ($alreadyMigrated) {
                    $stats['proposals_skipped_existing']++;
                    continue;
                }

                $rows = is_array($raw) ? $raw : json_decode((string) $raw, true);
                if (! is_array($rows)) {
                    $stats['malformed_rows']++;
                    $issues[] = [
                        'proposal_id' => (string) $proposal->id,
                        'row' => '-',
                        'reason' => 'budget_breakdown_items is not valid JSON array',
                    ];
                    continue;
                }

                $insertRows = [];
                foreach ($rows as $idx => $row) {
                    if (! is_array($row)) {
                        $stats['malformed_rows']++;
                        $issues[] = [
                            'proposal_id' => (string) $proposal->id,
                            'row' => (string) ($idx + 1),
                            'reason' => 'Row is not an object',
                        ];
                        continue;
                    }

                    $category = trim((string) ($row['category'] ?? 'general'));
                    $description = trim((string) ($row['item_description'] ?? $row['material'] ?? ''));
                    $quantityRaw = $row['quantity'] ?? null;
                    $unitCostRaw = $row['unit_cost'] ?? $row['unit_price'] ?? null;
                    $totalRaw = $row['total_cost'] ?? $row['price'] ?? null;

                    $quantity = is_numeric($quantityRaw) ? round((float) $quantityRaw, 2) : null;
                    $unitCost = is_numeric($unitCostRaw) ? round((float) $unitCostRaw, 2) : null;
                    $totalCost = is_numeric($totalRaw) ? round((float) $totalRaw, 2) : null;

                    if ($category === '') {
                        $category = 'general';
                    }

                    if ($description === '' || $unitCost === null || $totalCost === null) {
                        $stats['malformed_rows']++;
                        $issues[] = [
                            'proposal_id' => (string) $proposal->id,
                            'row' => (string) ($idx + 1),
                            'reason' => 'Missing required item_description/material or numeric unit_cost/total_cost',
                        ];
                        continue;
                    }

                    $insertRows[] = [
                        'activity_proposal_id' => $proposal->id,
                        'category' => mb_substr($category, 0, 100),
                        'item_description' => mb_substr($description, 0, 255),
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($insertRows === []) {
                    continue;
                }

                if (! $dryRun) {
                    DB::table('proposal_budget_items')->insert($insertRows);
                }

                $stats['proposals_migrated']++;
                $stats['items_inserted'] += count($insertRows);
            }
        });

    $this->newLine();
    $this->info('Proposal budget items migration summary');
    $this->line(' - Proposals scanned: '.$stats['proposals_scanned']);
    $this->line(' - Proposals with JSON budget items: '.$stats['proposals_with_json']);
    $this->line(' - Proposals migrated: '.$stats['proposals_migrated'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Proposals skipped (already migrated): '.$stats['proposals_skipped_existing']);
    $this->line(' - Child budget rows inserted: '.$stats['items_inserted'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Malformed/incomplete rows logged: '.$stats['malformed_rows']);

    if ($issues !== []) {
        $this->newLine();
        $this->warn('Rows needing review:');
        $this->table(
            ['proposal_id', 'row', 'reason'],
            array_slice($issues, 0, 300)
        );
        if (count($issues) > 300) {
            $this->line('Showing first 300 issues of '.count($issues).' total.');
        }
    }

    return self::SUCCESS;
})->purpose('One-time migration of activity_proposals.budget_breakdown_items JSON into proposal_budget_items');

Artisan::command('attachments:migrate-legacy-files {--dry-run : Preview migration without writing rows}', function () {
    $dryRun = (bool) $this->option('dry-run');

    if (! Schema::hasTable('attachments')) {
        $this->error('attachments table is missing. Run migrations first.');

        return self::FAILURE;
    }

    $sources = [
        [
            'table' => 'organization_registrations',
            'attachable_type' => \App\Models\OrganizationRegistration::class,
            'id_col' => 'id',
            'uploaded_by_cols' => ['submitted_by', 'user_id'],
            'scalar_paths' => [
                'registration_document' => 'registration_document',
            ],
            'json_paths' => [
                'requirement_files' => 'registration_requirement_file',
            ],
        ],
        [
            'table' => 'organization_renewals',
            'attachable_type' => \App\Models\OrganizationRenewal::class,
            'id_col' => 'id',
            'uploaded_by_cols' => ['submitted_by', 'user_id'],
            'scalar_paths' => [
                'renewal_document' => 'renewal_document',
            ],
            'json_paths' => [
                'requirement_files' => 'renewal_requirement_file',
            ],
        ],
        [
            'table' => 'activity_calendars',
            'attachable_type' => \App\Models\ActivityCalendar::class,
            'id_col' => 'id',
            'uploaded_by_cols' => ['submitted_by', 'user_id'],
            'scalar_paths' => [
                'calendar_file' => 'calendar_file',
            ],
            'json_paths' => [],
        ],
        [
            'table' => 'activity_request_forms',
            'attachable_type' => \App\Models\ActivityRequestForm::class,
            'id_col' => 'id',
            'uploaded_by_cols' => ['submitted_by', 'user_id'],
            'scalar_paths' => [
                'request_letter_path' => 'request_letter',
                'speaker_resume_path' => 'speaker_resume',
                'post_survey_form_path' => 'post_survey_form',
            ],
            'json_paths' => [],
        ],
        [
            'table' => 'activity_proposals',
            'attachable_type' => \App\Models\ActivityProposal::class,
            'id_col' => 'id',
            'uploaded_by_cols' => ['submitted_by', 'user_id'],
            'scalar_paths' => [
                'organization_logo_path' => 'organization_logo',
                'resume_resource_persons_path' => 'resume_resource_persons',
                'external_funding_support_path' => 'external_funding_support',
            ],
            'json_paths' => [],
        ],
        [
            'table' => 'activity_reports',
            'attachable_type' => \App\Models\ActivityReport::class,
            'id_col' => 'id',
            'uploaded_by_cols' => ['submitted_by', 'user_id'],
            'scalar_paths' => [
                'report_file' => 'report_file',
                'poster_image_path' => 'poster_image',
                'certificate_sample_path' => 'certificate_sample',
                'evaluation_form_sample_path' => 'evaluation_form_sample',
                'attendance_sheet_path' => 'attendance_sheet',
            ],
            'json_paths' => [
                'supporting_photo_paths' => 'supporting_photo',
            ],
        ],
    ];

    $isValidPath = static function (?string $path): bool {
        $p = trim((string) $path);
        if ($p === '') {
            return false;
        }

        if (str_starts_with($p, '{') || str_starts_with($p, '[')) {
            return false;
        }

        if (mb_strlen($p) > 500) {
            return false;
        }

        return true;
    };

    $deriveOriginalName = static function (string $storedPath): string {
        $pathPart = (string) (parse_url($storedPath, PHP_URL_PATH) ?? $storedPath);
        $name = basename(str_replace('\\', '/', $pathPart));

        return $name !== '' ? $name : 'file';
    };

    $stats = [
        'records_scanned' => 0,
        'valid_paths_found' => 0,
        'attachments_created' => 0,
        'attachments_skipped_existing' => 0,
        'paths_skipped_invalid_or_null' => 0,
        'uncertain_mappings' => 0,
    ];

    $issues = [];

    foreach ($sources as $source) {
        if (! Schema::hasTable($source['table'])) {
            continue;
        }

        $columns = [$source['id_col'], ...$source['uploaded_by_cols'], ...array_keys($source['scalar_paths']), ...array_keys($source['json_paths'])];
        $availableColumns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($source['table'], $c)));
        if (! in_array($source['id_col'], $availableColumns, true)) {
            continue;
        }

        DB::table($source['table'])
            ->select($availableColumns)
            ->orderBy($source['id_col'])
            ->chunkById(200, function ($rows) use ($source, $dryRun, $isValidPath, $deriveOriginalName, &$stats, &$issues): void {
                foreach ($rows as $row) {
                    $stats['records_scanned']++;

                    $attachableId = (int) $row->{$source['id_col']};
                    $uploadedBy = null;
                    foreach ($source['uploaded_by_cols'] as $uCol) {
                        if (property_exists($row, $uCol) && ! empty($row->{$uCol})) {
                            $uploadedBy = (int) $row->{$uCol};
                            break;
                        }
                    }

                    if ($uploadedBy === null) {
                        $stats['uncertain_mappings']++;
                        $issues[] = [
                            'source' => $source['table'].'#'.$attachableId,
                            'column' => 'uploaded_by',
                            'reason' => 'Could not determine uploader (submitted_by/user_id missing)',
                        ];
                    }

                    $persistAttachment = function (string $fileType, string $storedPath) use (
                        $source,
                        $attachableId,
                        $uploadedBy,
                        $dryRun,
                        $deriveOriginalName,
                        &$stats,
                        &$issues
                    ): void {
                        if ($uploadedBy === null) {
                            $stats['uncertain_mappings']++;
                            $issues[] = [
                                'source' => $source['table'].'#'.$attachableId,
                                'column' => $fileType,
                                'reason' => 'Skipped path because uploaded_by is unknown',
                            ];

                            return;
                        }

                        $exists = DB::table('attachments')
                            ->where('attachable_type', $source['attachable_type'])
                            ->where('attachable_id', $attachableId)
                            ->where('file_type', $fileType)
                            ->where('stored_path', $storedPath)
                            ->exists();

                        if ($exists) {
                            $stats['attachments_skipped_existing']++;

                            return;
                        }

                        if (! $dryRun) {
                            DB::table('attachments')->insert([
                                'attachable_type' => $source['attachable_type'],
                                'attachable_id' => $attachableId,
                                'uploaded_by' => $uploadedBy,
                                'file_type' => $fileType,
                                'original_name' => $deriveOriginalName($storedPath),
                                'stored_path' => $storedPath,
                                'mime_type' => null,
                                'file_size_kb' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $stats['attachments_created']++;
                    };

                    foreach ($source['scalar_paths'] as $column => $fileType) {
                        if (! property_exists($row, $column)) {
                            continue;
                        }

                        $rawPath = (string) ($row->{$column} ?? '');
                        if (! $isValidPath($rawPath)) {
                            $stats['paths_skipped_invalid_or_null']++;
                            continue;
                        }

                        $stats['valid_paths_found']++;
                        $persistAttachment($fileType, trim($rawPath));
                    }

                    foreach ($source['json_paths'] as $column => $fileType) {
                        if (! property_exists($row, $column)) {
                            continue;
                        }

                        $raw = $row->{$column};
                        if ($raw === null || $raw === '') {
                            continue;
                        }

                        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
                        if (! is_array($decoded)) {
                            $stats['uncertain_mappings']++;
                            $issues[] = [
                                'source' => $source['table'].'#'.$attachableId,
                                'column' => $column,
                                'reason' => 'JSON column is not a valid array',
                            ];
                            continue;
                        }

                        $paths = [];
                        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($decoded));
                        foreach ($iterator as $value) {
                            if (is_string($value)) {
                                $paths[] = $value;
                            }
                        }

                        foreach ($paths as $path) {
                            if (! $isValidPath($path)) {
                                $stats['paths_skipped_invalid_or_null']++;
                                continue;
                            }

                            $stats['valid_paths_found']++;
                            $persistAttachment($fileType, trim($path));
                        }
                    }
                }
            }, $source['id_col']);
    }

    $this->newLine();
    $this->info('Legacy file paths -> attachments migration summary');
    $this->line(' - Records scanned: '.$stats['records_scanned']);
    $this->line(' - Valid file paths found: '.$stats['valid_paths_found']);
    $this->line(' - Attachments created: '.$stats['attachments_created'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Attachments skipped (already migrated): '.$stats['attachments_skipped_existing']);
    $this->line(' - Paths skipped (null/invalid): '.$stats['paths_skipped_invalid_or_null']);
    $this->line(' - Uncertain mappings logged: '.$stats['uncertain_mappings']);

    if ($issues !== []) {
        $this->newLine();
        $this->warn('Uncertain mappings / issues for review:');
        $this->table(['source', 'column', 'reason'], array_slice($issues, 0, 300));
        if (count($issues) > 300) {
            $this->line('Showing first 300 issues of '.count($issues).' total.');
        }
    }

    return self::SUCCESS;
})->purpose('One-time migration of legacy file path columns into polymorphic attachments table');

Artisan::command('approval:backfill-universal-workflows {--dry-run : Preview backfill without writing rows}', function () {
    $dryRun = (bool) $this->option('dry-run');

    if (! Schema::hasTable('approval_workflow_steps') || ! Schema::hasTable('approval_logs')) {
        $this->error('Universal approval tables are missing. Run migrations first.');

        return self::FAILURE;
    }

    $roleIds = DB::table('roles')->pluck('id', 'name')->all();
    $sdaoRoleId = isset($roleIds['sdao_staff']) ? (int) $roleIds['sdao_staff'] : null;

    if ($sdaoRoleId === null) {
        $this->error("Required role 'sdao_staff' is missing in roles table.");
        $this->line('Run roles seeder first: php artisan db:seed --class=RolesTableSeeder');

        return self::FAILURE;
    }

    $stats = [
        'records_scanned' => 0,
        'records_skipped_existing' => 0,
        'steps_created' => 0,
        'logs_created' => 0,
        'ambiguous_records' => 0,
    ];
    $issues = [];

    $mapGeneralStatus = static function (?string $status): string {
        return match (strtoupper((string) $status)) {
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            'REVISION', 'REVISION_REQUIRED' => 'revision_required',
            'SKIPPED' => 'skipped',
            default => 'pending',
        };
    };

    $actionForStepStatus = static function (string $stepStatus): ?string {
        return match ($stepStatus) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'revision_required' => 'revision_requested',
            default => null,
        };
    };

    $hasExistingUniversalRows = static function (string $approvableType, int $approvableId): bool {
        return DB::table('approval_workflow_steps')
            ->where('approvable_type', $approvableType)
            ->where('approvable_id', $approvableId)
            ->exists()
            || DB::table('approval_logs')
                ->where('approvable_type', $approvableType)
                ->where('approvable_id', $approvableId)
                ->exists();
    };

    $insertStep = function (array $payload) use ($dryRun, &$stats): int {
        if ($dryRun) {
            $stats['steps_created']++;

            return 0;
        }

        $id = (int) DB::table('approval_workflow_steps')->insertGetId(array_merge($payload, [
            'created_at' => $payload['created_at'] ?? now(),
            'updated_at' => $payload['updated_at'] ?? now(),
        ]));
        $stats['steps_created']++;

        return $id;
    };

    $insertLog = function (array $payload) use ($dryRun, &$stats): void {
        if ($dryRun) {
            $stats['logs_created']++;

            return;
        }

        DB::table('approval_logs')->insert($payload);
        $stats['logs_created']++;
    };

    // 1) Registrations
    if (Schema::hasTable('organization_registrations')) {
        DB::table('organization_registrations')->orderBy('id')->chunkById(200, function ($rows) use (
            $hasExistingUniversalRows,
            $mapGeneralStatus,
            $actionForStepStatus,
            $insertStep,
            $insertLog,
            $sdaoRoleId,
            $dryRun,
            &$stats,
            &$issues
        ): void {
            foreach ($rows as $row) {
                $stats['records_scanned']++;
                $approvableType = \App\Models\OrganizationRegistration::class;
                $approvableId = (int) $row->id;

                if ($hasExistingUniversalRows($approvableType, $approvableId)) {
                    $stats['records_skipped_existing']++;
                    continue;
                }

                $stepStatus = $mapGeneralStatus((string) ($row->registration_status ?? null));
                $actedAt = $stepStatus !== 'pending'
                    ? ($row->approval_date ? (string) $row->approval_date.' 12:00:00' : null)
                    : null;

                $stepId = $insertStep([
                    'approvable_type' => $approvableType,
                    'approvable_id' => $approvableId,
                    'step_order' => 1,
                    'role_id' => $sdaoRoleId,
                    'assigned_to' => null,
                    'status' => $stepStatus,
                    'is_current_step' => $stepStatus === 'pending',
                    'review_comments' => $row->additional_remarks ?? null,
                    'acted_at' => $actedAt,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);

                $submitActor = ! empty($row->user_id) ? (int) $row->user_id : null;
                if ($submitActor !== null) {
                    $insertLog([
                        'approvable_type' => $approvableType,
                        'approvable_id' => $approvableId,
                        'workflow_step_id' => $dryRun ? null : $stepId,
                        'actor_id' => $submitActor,
                        'action' => 'submitted',
                        'from_status' => null,
                        'to_status' => 'pending',
                        'comments' => null,
                        'created_at' => $row->submission_date ? (string) $row->submission_date.' 12:00:00' : ($row->created_at ?? now()),
                    ]);
                } else {
                    $stats['ambiguous_records']++;
                    $issues[] = [
                        'record' => 'organization_registrations#'.$approvableId,
                        'reason' => 'Missing user_id; submitted log skipped',
                    ];
                }

                $terminalAction = $actionForStepStatus($stepStatus);
                if ($terminalAction !== null) {
                    $stats['ambiguous_records']++;
                    $issues[] = [
                        'record' => 'organization_registrations#'.$approvableId,
                        'reason' => 'Terminal decision inferred but approver actor unknown; terminal log skipped',
                    ];
                }
            }
        });
    }

    // 2) Renewals
    if (Schema::hasTable('organization_renewals')) {
        DB::table('organization_renewals')->orderBy('id')->chunkById(200, function ($rows) use (
            $hasExistingUniversalRows,
            $mapGeneralStatus,
            $actionForStepStatus,
            $insertStep,
            $insertLog,
            $sdaoRoleId,
            $dryRun,
            &$stats,
            &$issues
        ): void {
            foreach ($rows as $row) {
                $stats['records_scanned']++;
                $approvableType = \App\Models\OrganizationRenewal::class;
                $approvableId = (int) $row->id;

                if ($hasExistingUniversalRows($approvableType, $approvableId)) {
                    $stats['records_skipped_existing']++;
                    continue;
                }

                $stepStatus = $mapGeneralStatus((string) ($row->renewal_status ?? null));
                $actedAt = $stepStatus !== 'pending'
                    ? ($row->approval_date ? (string) $row->approval_date.' 12:00:00' : null)
                    : null;

                $stepId = $insertStep([
                    'approvable_type' => $approvableType,
                    'approvable_id' => $approvableId,
                    'step_order' => 1,
                    'role_id' => $sdaoRoleId,
                    'assigned_to' => null,
                    'status' => $stepStatus,
                    'is_current_step' => $stepStatus === 'pending',
                    'review_comments' => $row->additional_remarks ?? null,
                    'acted_at' => $actedAt,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);

                $submitActor = ! empty($row->user_id) ? (int) $row->user_id : null;
                if ($submitActor !== null) {
                    $insertLog([
                        'approvable_type' => $approvableType,
                        'approvable_id' => $approvableId,
                        'workflow_step_id' => $dryRun ? null : $stepId,
                        'actor_id' => $submitActor,
                        'action' => 'submitted',
                        'from_status' => null,
                        'to_status' => 'pending',
                        'comments' => null,
                        'created_at' => $row->submission_date ? (string) $row->submission_date.' 12:00:00' : ($row->created_at ?? now()),
                    ]);
                } else {
                    $stats['ambiguous_records']++;
                    $issues[] = [
                        'record' => 'organization_renewals#'.$approvableId,
                        'reason' => 'Missing user_id; submitted log skipped',
                    ];
                }

                $terminalAction = $actionForStepStatus($stepStatus);
                if ($terminalAction !== null) {
                    $stats['ambiguous_records']++;
                    $issues[] = [
                        'record' => 'organization_renewals#'.$approvableId,
                        'reason' => 'Terminal decision inferred but approver actor unknown; terminal log skipped',
                    ];
                }
            }
        });
    }

    // 3) Proposals (use old approval_workflows for best fidelity)
    if (Schema::hasTable('activity_proposals') && Schema::hasTable('approval_workflows') && Schema::hasTable('offices')) {
        $roleFromOffice = function (?string $officeName) use ($roleIds): ?int {
            $t = mb_strtolower(trim((string) $officeName));
            if ($t === '') {
                return null;
            }

            $roleName = match (true) {
                str_contains($t, 'executive director') => 'executive_director',
                str_contains($t, 'academic director') => 'academic_director',
                str_contains($t, 'assistant director'),
                str_contains($t, 'sdao'),
                str_contains($t, 'student development'),
                str_contains($t, 'student affairs') => 'sdao_staff',
                str_contains($t, 'program chair'), str_contains($t, 'programme chair') => 'program_chair',
                str_contains($t, 'dean') && ! str_contains($t, 'director') => 'dean',
                str_contains($t, 'adviser'), str_contains($t, 'advisor') => 'adviser',
                default => null,
            };

            return $roleName !== null && isset($roleIds[$roleName]) ? (int) $roleIds[$roleName] : null;
        };

        DB::table('activity_proposals')->orderBy('id')->chunkById(200, function ($rows) use (
            $hasExistingUniversalRows,
            $insertStep,
            $insertLog,
            $roleFromOffice,
            $dryRun,
            &$stats,
            &$issues
        ): void {
            foreach ($rows as $proposal) {
                $stats['records_scanned']++;
                $approvableType = \App\Models\ActivityProposal::class;
                $approvableId = (int) $proposal->id;

                if ($hasExistingUniversalRows($approvableType, $approvableId)) {
                    $stats['records_skipped_existing']++;
                    continue;
                }

                $workflows = DB::table('approval_workflows as aw')
                    ->leftJoin('offices as o', 'o.id', '=', 'aw.office_id')
                    ->where('aw.proposal_id', $approvableId)
                    ->orderBy('aw.approval_level')
                    ->get([
                        'aw.id',
                        'aw.user_id',
                        'aw.approval_level',
                        'aw.current_step',
                        'aw.decision_status',
                        'aw.review_comments',
                        'aw.acted_at',
                        'aw.review_date',
                        'aw.created_at',
                        'aw.updated_at',
                        'o.office_name',
                    ]);

                if ($workflows->isEmpty()) {
                    $stats['ambiguous_records']++;
                    $issues[] = [
                        'record' => 'activity_proposals#'.$approvableId,
                        'reason' => 'No approval_workflows rows found; workflow history not backfilled to avoid guessing',
                    ];
                    continue;
                }

                $submittedBy = ! empty($proposal->submitted_by) ? (int) $proposal->submitted_by : (! empty($proposal->user_id) ? (int) $proposal->user_id : null);
                if ($submittedBy !== null && strtoupper((string) $proposal->proposal_status) !== 'DRAFT') {
                    $insertLog([
                        'approvable_type' => $approvableType,
                        'approvable_id' => $approvableId,
                        'workflow_step_id' => null,
                        'actor_id' => $submittedBy,
                        'action' => 'submitted',
                        'from_status' => null,
                        'to_status' => 'pending',
                        'comments' => null,
                        'created_at' => $proposal->submission_date ? (string) $proposal->submission_date.' 12:00:00' : ($proposal->created_at ?? now()),
                    ]);
                }

                foreach ($workflows as $wf) {
                    $roleId = $roleFromOffice($wf->office_name);
                    if ($roleId === null) {
                        $stats['ambiguous_records']++;
                        $issues[] = [
                            'record' => 'activity_proposals#'.$approvableId,
                            'reason' => "Could not map office '{$wf->office_name}' to redesigned role; skipped step level {$wf->approval_level}",
                        ];
                        continue;
                    }

                    $status = match (strtoupper((string) $wf->decision_status)) {
                        'APPROVED' => 'approved',
                        'REJECTED' => 'rejected',
                        'REVISION_REQUIRED' => 'revision_required',
                        default => 'pending',
                    };

                    $stepId = $insertStep([
                        'approvable_type' => $approvableType,
                        'approvable_id' => $approvableId,
                        'step_order' => (int) $wf->approval_level,
                        'role_id' => $roleId,
                        'assigned_to' => $wf->user_id ? (int) $wf->user_id : null,
                        'status' => $status,
                        'is_current_step' => (bool) $wf->current_step,
                        'review_comments' => $wf->review_comments,
                        'acted_at' => $wf->acted_at ?? ($wf->review_date ? (string) $wf->review_date.' 12:00:00' : null),
                        'created_at' => $wf->created_at ?? now(),
                        'updated_at' => $wf->updated_at ?? now(),
                    ]);

                    $action = match ($status) {
                        'approved' => 'approved',
                        'rejected' => 'rejected',
                        'revision_required' => 'revision_requested',
                        default => null,
                    };

                    if ($action !== null && ! empty($wf->user_id)) {
                        $insertLog([
                            'approvable_type' => $approvableType,
                            'approvable_id' => $approvableId,
                            'workflow_step_id' => $dryRun ? null : $stepId,
                            'actor_id' => (int) $wf->user_id,
                            'action' => $action,
                            'from_status' => null,
                            'to_status' => $status,
                            'comments' => $wf->review_comments,
                            'created_at' => $wf->acted_at ?? ($wf->updated_at ?? now()),
                        ]);
                    } elseif ($action !== null) {
                        $stats['ambiguous_records']++;
                        $issues[] = [
                            'record' => 'activity_proposals#'.$approvableId,
                            'reason' => "Step {$wf->approval_level} has terminal status but no actor user_id; terminal log skipped",
                        ];
                    }
                }
            }
        });
    }

    // 4) Calendars + reports: one-step conservative scaffolding only
    $simpleSources = [
        ['table' => 'activity_calendars', 'type' => \App\Models\ActivityCalendar::class, 'status_col' => 'calendar_status', 'actor_cols' => ['submitted_by', 'user_id'], 'submitted_date_col' => 'submission_date'],
        ['table' => 'activity_reports', 'type' => \App\Models\ActivityReport::class, 'status_col' => 'report_status', 'actor_cols' => ['submitted_by', 'user_id'], 'submitted_date_col' => 'report_submission_date'],
    ];

    foreach ($simpleSources as $src) {
        if (! Schema::hasTable($src['table']) || ! Schema::hasColumn($src['table'], $src['status_col'])) {
            continue;
        }

        DB::table($src['table'])->orderBy('id')->chunkById(200, function ($rows) use (
            $src,
            $hasExistingUniversalRows,
            $mapGeneralStatus,
            $insertStep,
            $insertLog,
            $sdaoRoleId,
            $dryRun,
            &$stats,
            &$issues
        ): void {
            foreach ($rows as $row) {
                $stats['records_scanned']++;
                $approvableType = $src['type'];
                $approvableId = (int) $row->id;

                if ($hasExistingUniversalRows($approvableType, $approvableId)) {
                    $stats['records_skipped_existing']++;
                    continue;
                }

                $status = $mapGeneralStatus((string) ($row->{$src['status_col']} ?? null));
                $stepId = $insertStep([
                    'approvable_type' => $approvableType,
                    'approvable_id' => $approvableId,
                    'step_order' => 1,
                    'role_id' => $sdaoRoleId,
                    'assigned_to' => null,
                    'status' => $status,
                    'is_current_step' => $status === 'pending',
                    'review_comments' => null,
                    'acted_at' => null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);

                $actorId = null;
                foreach ($src['actor_cols'] as $col) {
                    if (property_exists($row, $col) && ! empty($row->{$col})) {
                        $actorId = (int) $row->{$col};
                        break;
                    }
                }

                if ($actorId !== null) {
                    $submittedAt = (property_exists($row, $src['submitted_date_col']) && ! empty($row->{$src['submitted_date_col']}))
                        ? (string) $row->{$src['submitted_date_col']}.' 12:00:00'
                        : ($row->created_at ?? now());

                    $insertLog([
                        'approvable_type' => $approvableType,
                        'approvable_id' => $approvableId,
                        'workflow_step_id' => $dryRun ? null : $stepId,
                        'actor_id' => $actorId,
                        'action' => 'submitted',
                        'from_status' => null,
                        'to_status' => 'pending',
                        'comments' => null,
                        'created_at' => $submittedAt,
                    ]);
                } else {
                    $stats['ambiguous_records']++;
                    $issues[] = [
                        'record' => $src['table'].'#'.$approvableId,
                        'reason' => 'Unable to determine submitter actor; submitted log skipped',
                    ];
                }
            }
        });
    }

    $this->newLine();
    $this->info('Universal approval backfill summary');
    $this->line(' - Records scanned: '.$stats['records_scanned']);
    $this->line(' - Records skipped (already backfilled): '.$stats['records_skipped_existing']);
    $this->line(' - Workflow steps created: '.$stats['steps_created'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Approval logs created: '.$stats['logs_created'].($dryRun ? ' (dry-run preview)' : ''));
    $this->line(' - Ambiguous cases logged: '.$stats['ambiguous_records']);

    if ($issues !== []) {
        $this->newLine();
        $this->warn('Ambiguous / partial migration notes (no fake history generated):');
        $this->table(['record', 'reason'], array_slice($issues, 0, 300));
        if (count($issues) > 300) {
            $this->line('Showing first 300 issues of '.count($issues).' total.');
        }
    }

    return self::SUCCESS;
})->purpose('One-time conservative backfill of universal approval_workflow_steps and approval_logs from legacy data');
