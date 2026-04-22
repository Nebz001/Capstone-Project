# Schema Cleanup Audit Summary

## Scope
- Audited deprecated schema usage across:
  - Models (`app/Models`)
  - Controllers (`app/Http/Controllers`)
  - Requests (`app/Http/Requests`)
  - Views (`resources/views`)
  - Routes (`routes/web.php`, `routes/console.php`)
  - Seeders (`database/seeders`)
  - Commands (`routes/console.php`)

## Audit Result
- Conservative finding: **no deprecated schema element is currently safe to drop** without breaking active code paths.
- Because cleanup requires "confirmed unused anywhere in codebase", **no destructive cleanup migration was generated** in this pass.

## Candidate Review (Verified)

### 1) `users.role_type`
- **Still used** in auth/role checks and account filtering.
- Examples:
  - `app/Models/User.php`
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/OrganizationController.php`
  - `app/Http/Controllers/AdminController.php`
  - `resources/views/admin/user-accounts/*.blade.php`
  - `routes/console.php`
- Decision: **keep**.

### 2) Legacy `organization_registrations` / `organization_renewals` tables
- **Still used** by compatibility/admin flows and migration commands.
- Examples:
  - `app/Http/Controllers/AdminController.php`
  - `app/Http/Controllers/OrganizationController.php`
  - `app/Http/Controllers/OrganizationSubmittedDocumentsController.php`
  - `app/Models/OrganizationRegistration.php`
  - `app/Models/OrganizationRenewal.php`
  - `routes/console.php`
  - `resources/views/admin/registrations/show.blade.php`
- Decision: **keep**.

### 3) Legacy `req_*` columns
- **Still used** in compatibility writes, admin registration review display, and migration tooling.
- Examples:
  - `app/Http/Controllers/OrganizationController.php`
  - `resources/views/admin/registrations/show.blade.php`
  - `app/Models/OrganizationRegistration.php`
  - `app/Models/OrganizationRenewal.php`
  - `routes/console.php`
- Decision: **keep**.

### 4) Copied proposal metadata (`form_organization_name`, `calendar_id`, `proposed_time`, legacy budget fields)
- **Still used** in forms, detail pages, dashboards, or compatibility/fallback logic.
- Examples:
  - `app/Http/Controllers/OrganizationController.php`
  - `app/Http/Controllers/OrganizationSubmittedDocumentsController.php`
  - `app/Http/Controllers/AdminController.php`
  - `resources/views/organizations/activity-proposal-submission.blade.php`
  - `app/Models/ActivityProposal.php`
- Decision: **keep**.

### 5) Scattered legacy file path columns
- **Still used** as fallback/read compatibility while attachments transition is ongoing.
- Examples:
  - `app/Http/Controllers/OrganizationController.php`
  - `app/Http/Controllers/OrganizationSubmittedDocumentsController.php`
  - `app/Http/Controllers/AdminController.php`
  - `app/Models/ActivityProposal.php`
  - `app/Models/ActivityReport.php`
  - `app/Models/OrganizationRegistration.php`
  - `app/Models/OrganizationRenewal.php`
- Decision: **keep**.

### 6) Old approval/revision string fields
- **Still used** in registration admin review and compatibility status mapping.
- Examples:
  - `app/Http/Controllers/AdminController.php`
  - `app/Http/Controllers/OrganizationSubmittedDocumentsController.php`
  - `resources/views/admin/registrations/show.blade.php`
  - `resources/views/organizations/profile.blade.php`
- Decision: **keep**.

### 7) Duplicate title columns in reports (`activity_event_title`, `event_title`, `event_name`)
- **Still used** in report creation, display, and fallback logic.
- Examples:
  - `app/Http/Controllers/OrganizationController.php`
  - `app/Http/Controllers/OrganizationSubmittedDocumentsController.php`
  - `app/Http/Controllers/AdminController.php`
  - `resources/views/organizations/after-activity-report.blade.php`
  - `app/Models/ActivityReport.php`
- Decision: **keep**.

## Cleanup Migration Output
- **Generated migrations in this pass:** none
- Reason: no candidate met the "confirmed unused anywhere in codebase" requirement.

## Recommended Next Safe Sequence
- Remove compatibility readers/writers for one module at a time (start with registrations/renewals), then re-run this audit.
- After code references are removed, generate targeted drop migrations for:
  - legacy tables (`organization_registrations`, `organization_renewals`)
  - legacy `req_*` columns
  - legacy file-path columns
  - legacy approval/revision columns
