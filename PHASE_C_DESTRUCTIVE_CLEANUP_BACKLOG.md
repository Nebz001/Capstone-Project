# PHASE_C_DESTRUCTIVE_CLEANUP_BACKLOG

Source of truth: `SDAO_DB_Redesign.pdf`  
Scope: **Destructive Phase C only** (new cleanup migrations, conservative execution).

---

## 1) communication_threads

- **Migration ticket title**: `phase_c_cleanup_communication_threads_drop_legacy_columns`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `proposal_id`
  - `thread_type`
  - `thread_status`
- **Redesign replacement**:
  - `subject_type`, `subject_id`
  - `status`
- **Preconditions (must all be true)**:
  - All thread creation uses `subject_type` + `subject_id`.
  - No model/controller/view/command references `proposal_id`, `thread_type`, `thread_status`.
  - Existing rows have non-null, valid `subject_type`/`subject_id`.
  - `status` is fully backfilled from `thread_status`.
- **Exact up() target**:
  - Drop FK/index on `proposal_id` (if present).
  - Drop columns `proposal_id`, `thread_type`, `thread_status`.
- **Exact down() rollback target**:
  - Re-add `proposal_id` (nullable FK to `activity_proposals`), `thread_type` enum, `thread_status` enum default `OPEN`.
  - No full data restoration guarantee for dropped values unless pre-drop snapshot is retained.
- **Verification examples**:
  - SQL:
    - `SELECT COUNT(*) FROM communication_threads WHERE subject_type IS NULL OR subject_id IS NULL;`
    - `SELECT COUNT(*) FROM communication_threads WHERE status IS NULL;`
  - Laravel:
    - `DB::table('communication_threads')->whereNull('subject_type')->orWhereNull('subject_id')->count();`
- **Risk level**: Medium

---

## 2) communication_messages

- **Migration ticket title**: `phase_c_cleanup_communication_messages_drop_user_id`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `user_id`
- **Redesign replacement**:
  - `sent_by`
- **Preconditions**:
  - `sent_by` exists and is fully backfilled from `user_id`.
  - No code references `communication_messages.user_id`.
  - FK integrity for `sent_by -> users.id` verified.
- **Exact up() target**:
  - Drop FK/index on `user_id` (if present).
  - Drop `user_id`.
- **Exact down() rollback target**:
  - Re-add `user_id` FK to `users` (nullable initially for rollback safety).
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM communication_messages WHERE sent_by IS NULL;`
  - Laravel: `DB::table('communication_messages')->whereNull('sent_by')->count();`
- **Risk level**: Low

---

## 3) organization_officers

- **Migration ticket title**: `phase_c_cleanup_organization_officers_drop_officer_status`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `officer_status`
- **Redesign replacement**:
  - `status`
- **Preconditions**:
  - `status` exists and is fully mapped from `officer_status`.
  - All "active officer" logic uses `status`.
  - No code references `officer_status`.
- **Exact up() target**:
  - Drop `officer_status`.
- **Exact down() rollback target**:
  - Re-add `officer_status` enum(`ACTIVE`,`INACTIVE`) default `ACTIVE`.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM organization_officers WHERE status IS NULL;`
  - Laravel: `DB::table('organization_officers')->whereNull('status')->count();`
- **Risk level**: Medium

---

## 4) offices

- **Migration ticket title**: `phase_c_cleanup_offices_drop_legacy_head_and_status`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `office_head`
  - `office_status`
- **Redesign replacement**:
  - `head_user_id`
  - `status`
- **Preconditions**:
  - `head_user_id` and `status` exist and are backfilled.
  - Office admin UI/API uses new columns only.
  - No code references `office_head` / `office_status`.
- **Exact up() target**:
  - Drop `office_head`, `office_status`.
- **Exact down() rollback target**:
  - Re-add `office_head` varchar(150) nullable, `office_status` enum(`ACTIVE`,`INACTIVE`) default `ACTIVE`.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM offices WHERE status IS NULL;`
  - SQL: `SELECT COUNT(*) FROM offices WHERE head_user_id IS NOT NULL AND head_user_id NOT IN (SELECT id FROM users);`
- **Risk level**: Medium

---

## 5) activity_calendar_entries

- **Migration ticket title**: `phase_c_cleanup_activity_calendar_entries_drop_legacy_target_columns`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `sdg`
  - `participant_program`
  - `budget` (varchar)
- **Redesign replacement**:
  - `target_sdg`
  - `target_participants`
  - `target_program`
  - `estimated_budget` (decimal)
- **Preconditions**:
  - New columns exist and are fully populated where needed.
  - Calendar forms/views/exports use new columns only.
  - No code references old columns.
- **Exact up() target**:
  - Drop `sdg`, `participant_program`, `budget`.
- **Exact down() rollback target**:
  - Re-add dropped columns (`sdg`, `participant_program`, `budget` varchar).
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM activity_calendar_entries WHERE target_sdg IS NULL;`
  - SQL: `SELECT COUNT(*) FROM activity_calendar_entries WHERE estimated_budget IS NULL AND budget IS NOT NULL;`
- **Risk level**: Medium

---

## 6) activity_calendars

- **Migration ticket title**: `phase_c_cleanup_activity_calendars_drop_legacy_term_file_status_columns`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `academic_year`
  - `semester`
  - `calendar_file`
  - `calendar_status`
  - `submitted_organization_name` (if retained only for legacy display)
- **Redesign replacement**:
  - `academic_term_id`
  - `submitted_by`
  - `status`
  - `attachments` (`file_type` for calendar artifacts)
- **Preconditions**:
  - `academic_term_id`, `submitted_by`, `status` fully used.
  - Calendar file access uses `attachments`.
  - No code references old term/status/file columns.
- **Exact up() target**:
  - Drop listed legacy columns.
- **Exact down() rollback target**:
  - Re-add dropped columns with legacy types.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM activity_calendars WHERE academic_term_id IS NULL;`
  - SQL: `SELECT COUNT(*) FROM attachments WHERE attachable_type='App\\Models\\ActivityCalendar';`
- **Risk level**: Medium

---

## 7) activity_request_forms

- **Migration ticket title**: `phase_c_cleanup_activity_request_forms_drop_legacy_submitter_files_and_promotion_marker`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `user_id`
  - `rso_name`
  - `request_letter_has_rationale`
  - `request_letter_has_objectives`
  - `request_letter_has_program`
  - `request_letter_path`
  - `speaker_resume_path`
  - `post_survey_form_path`
  - `used_for_proposal_at`
- **Redesign replacement**:
  - `submitted_by`
  - organization relation (name from `organizations`)
  - attachments
  - `promoted_to_proposal_id`, `promoted_at`
- **Preconditions**:
  - Promotion logic uses `promoted_to_proposal_id`/`promoted_at` only.
  - File retrieval uses `attachments` only.
  - No code references old columns.
- **Exact up() target**:
  - Drop listed legacy columns.
- **Exact down() rollback target**:
  - Re-add dropped columns with legacy types.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM activity_request_forms WHERE submitted_by IS NULL;`
  - SQL: `SELECT COUNT(*) FROM activity_request_forms WHERE promoted_to_proposal_id IS NULL AND promoted_at IS NOT NULL;`
- **Risk level**: High

---

## 8) activity_reports

- **Migration ticket title**: `phase_c_cleanup_activity_reports_drop_legacy_fk_status_title_and_file_columns`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `proposal_id`
  - `user_id`
  - `report_file`
  - `report_status`
  - `activity_event_title`
  - `event_name`
  - `poster_image_path`
  - `supporting_photo_paths`
  - `certificate_sample_path`
  - `evaluation_form_sample_path`
  - `attendance_sheet_path`
- **Redesign replacement**:
  - `activity_proposal_id`
  - `submitted_by`
  - `status`
  - `event_title`
  - attachments (`poster`, `supporting_photo`, `certificate`, `evaluation_form`, `attendance_sheet`)
- **Preconditions**:
  - New FK/status/title columns complete and in use.
  - All report file reads via attachments.
  - No code references legacy report columns.
- **Exact up() target**:
  - Drop listed legacy columns.
- **Exact down() rollback target**:
  - Re-add dropped columns with legacy schema types.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM activity_reports WHERE activity_proposal_id IS NULL AND proposal_id IS NOT NULL;`
  - SQL: `SELECT COUNT(*) FROM attachments WHERE attachable_type='App\\Models\\ActivityReport';`
- **Risk level**: High

---

## 9) activity_proposals

- **Migration ticket title**: `phase_c_cleanup_activity_proposals_drop_legacy_metadata_time_budget_and_file_columns`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `calendar_id`
  - `user_id`
  - `proposal_status`
  - `form_organization_name`
  - `organization_logo_path`
  - `school_code`
  - `department_program`
  - `academic_year`
  - `proposed_time`
  - `budget_materials_supplies`
  - `budget_food_beverage`
  - `budget_other_expenses`
  - `budget_breakdown_items`
  - `resume_resource_persons_path`
  - `external_funding_support_path`
- **Redesign replacement**:
  - `activity_calendar_id`
  - `submitted_by`
  - `status`
  - org profile via `organizations`
  - `academic_term_id`
  - `proposed_start_time`, `proposed_end_time`
  - `proposal_budget_items`
  - attachments
- **Preconditions**:
  - Proposal forms/details/reports no longer use legacy proposal columns.
  - `proposal_budget_items` complete for all proposals.
  - Attachments complete for proposal file artifacts.
  - No code references legacy columns.
- **Exact up() target**:
  - Drop listed legacy columns.
- **Exact down() rollback target**:
  - Re-add dropped columns with legacy schema types.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM activity_proposals ap LEFT JOIN proposal_budget_items pbi ON pbi.activity_proposal_id=ap.id WHERE ap.estimated_budget > 0 GROUP BY ap.id HAVING COUNT(pbi.id)=0;`
  - SQL: `SELECT COUNT(*) FROM activity_proposals WHERE academic_term_id IS NULL AND academic_year IS NOT NULL;`
- **Risk level**: High

---

## 10) users

- **Migration ticket title**: `phase_c_cleanup_users_drop_role_type_and_account_field_reviews`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `role_type`
  - `account_field_reviews`
- **Redesign replacement**:
  - `role_id` FK to `roles`
  - normalized review table/log approach (redesign notes this should be normalized)
- **Preconditions**:
  - All authentication/authorization paths use `role_id` and role relations only.
  - No code references `role_type` or `account_field_reviews`.
  - All users have valid `role_id`.
- **Exact up() target**:
  - Drop `role_type`, `account_field_reviews`.
- **Exact down() rollback target**:
  - Re-add `role_type` enum and `account_field_reviews` json nullable.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM users WHERE role_id IS NULL;`
  - Laravel: `User::query()->whereNull('role_id')->count();`
- **Risk level**: **High** (shared auth table)

---

## 11) organizations

- **Migration ticket title**: `phase_c_cleanup_organizations_drop_legacy_adviser_revision_and_old_status_column`
- **Cleanup type**: Column-drop only
- **Legacy columns to remove**:
  - `adviser_name`
  - `profile_information_revision_requested`
  - `profile_revision_notes`
  - `organization_status` (after status rename cutover)
- **Redesign replacement**:
  - `organization_advisers`
  - `organization_profile_revisions`
  - `status`
- **Preconditions**:
  - Adviser logic fully uses `organization_advisers`.
  - Profile revision flows use `organization_profile_revisions`.
  - All UI/business logic uses `status`, not `organization_status`.
  - No code references legacy fields.
- **Exact up() target**:
  - Drop listed legacy columns.
- **Exact down() rollback target**:
  - Re-add dropped columns with legacy types and defaults.
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM organization_advisers WHERE relieved_at IS NULL;`
  - SQL: `SELECT COUNT(*) FROM organizations WHERE status IS NULL;`
- **Risk level**: **High** (shared core domain table)

---

## 12) organization_registrations

- **Migration ticket title**: `phase_c_cleanup_drop_organization_registrations_table`
- **Cleanup type**: **Full table drop**
- **Legacy table to remove**:
  - `organization_registrations` (entire table)
- **Redesign replacement**:
  - `organization_submissions` (`type='registration'`)
  - `submission_requirements`
  - `attachments`
  - `approval_workflow_steps`, `approval_logs`
- **Preconditions**:
  - No runtime reads/writes to `organization_registrations`.
  - Full verified migration of records to redesign tables.
  - Admin/officer registration pages fully bound to `organization_submissions`.
  - Commands/jobs/backfills no longer require this table.
- **Exact up() target**:
  - Drop table `organization_registrations`.
- **Exact down() rollback target**:
  - Recreate table schema only (data restore requires backup).
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM organization_registrations;` (expect archival decision before drop)
  - SQL: `SELECT COUNT(*) FROM organization_submissions WHERE type='registration';` (compare expected parity)
- **Risk level**: **High**

---

## 13) organization_renewals

- **Migration ticket title**: `phase_c_cleanup_drop_organization_renewals_table`
- **Cleanup type**: **Full table drop**
- **Legacy table to remove**:
  - `organization_renewals` (entire table)
- **Redesign replacement**:
  - `organization_submissions` (`type='renewal'`)
  - `submission_requirements`
  - `attachments`
  - `approval_workflow_steps`, `approval_logs`
- **Preconditions**:
  - No runtime reads/writes to `organization_renewals`.
  - Full verified migration of records to redesign tables.
  - Admin/officer renewal pages fully bound to `organization_submissions`.
  - Commands/jobs/backfills no longer require this table.
- **Exact up() target**:
  - Drop table `organization_renewals`.
- **Exact down() rollback target**:
  - Recreate table schema only (data restore requires backup).
- **Verification examples**:
  - SQL: `SELECT COUNT(*) FROM organization_renewals;`
  - SQL: `SELECT COUNT(*) FROM organization_submissions WHERE type='renewal';`
- **Risk level**: **High**

---

## Master Destructive Cleanup Checklist

- [ ] Full backup/snapshot taken and restore tested.
- [ ] Staging environment dry-run completed with production-like dataset.
- [ ] Zero code references to each legacy column/table (controllers/models/views/routes/commands/jobs/tests).
- [ ] Backfills and reconciliation reports completed and signed off.
- [ ] Attachments completeness validated for all migrated file-bearing modules.
- [ ] Approval workflow/log completeness validated where replacing legacy approval fields.
- [ ] Data parity checks passed for registration/renewal table consolidation.
- [ ] Rollback strategy documented for each migration batch.
- [ ] Maintenance window and monitoring/alerting prepared.

---

## Recommended Execution Order

1. `communication_threads`
2. `communication_messages`
3. `organization_officers`
4. `offices`
5. `activity_calendar_entries`
6. `activity_calendars`
7. `activity_request_forms`
8. `activity_reports`
9. `activity_proposals`
10. `users`
11. `organizations`
12. `organization_registrations` (table drop)
13. `organization_renewals` (table drop)

---

## Rollback Caution Notes

- Column-drop rollback can restore schema, but not guaranteed original values unless a pre-drop data backup exists.
- Table-drop rollback recreates structure only; historical data restoration requires backup import.
- High-risk shared tables (`users`, `organizations`) should be executed in isolated migration batches with immediate smoke tests.
- Prefer one destructive migration per table for clear rollback boundaries and incident isolation.
