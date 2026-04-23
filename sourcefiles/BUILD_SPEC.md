# Filter Time Tracker — Build Specification

**Audience:** Claude Code (implementation)
**Product owner:** Paul Halfpenny (Filter Agency)
**Status:** Ready to build
**Last updated:** 22 April 2026 (v4 — JDW monthly export spec added as §15, Phase 7 in the delivery plan; task seed list and projects table updated to support it)

---

## 0. How to use this document

This is the authoritative spec. If something is unclear or contradicts itself, stop and ask Paul — do not guess. Decisions already made are in **Decisions** sections; do not relitigate them without explicit sign-off.

Work through the phases in order. Each phase has acceptance criteria. Do not start the next phase until the current one passes its criteria locally and in staging.

Commit message convention: `[phase-N] <imperative summary>`. Branch per phase: `phase-1-foundation`, `phase-2-data-model`, etc. PR into `main`, squash-merge.

---

## 1. Product summary

Filter Agency (60 staff, WordPress-focused digital agency, UK-based) is replacing Harvest with an in-house time tracker. Harvest costs ~$700/mo and they use only a small fraction of it. The replacement must handle daily time entry, five report views, and a CSV export that matches Harvest's "Detailed time" export exactly so existing Excel client-reporting templates keep working.

**Not in scope** (confirmed): invoicing, estimates, expenses, contractor-specific reports, profitability reports, activity log, approvals workflow (phase 2), Forecast-style scheduled hours (phase 2).

---

## 2. Stack — decisions

| Layer | Choice | Version |
|---|---|---|
| Language | PHP | 8.3 |
| Framework | Laravel | 11.x (LTS) |
| UI | Livewire | 3.x |
| Frontend helper | Alpine.js | 3.x (bundled with Livewire) |
| CSS | Tailwind CSS | 3.x |
| Database | MySQL | 8.0 |
| Auth | Laravel Socialite + Google | latest |
| Excel/CSV | maatwebsite/excel | 3.x |
| Queue | Laravel Queue, `database` driver | — |
| Testing | Pest | 3.x |
| Static analysis | Larastan | level 8 |
| Formatter | Laravel Pint | default preset |
| Error reporting | Sentry | free tier |
| Hosting | Digital Ocean droplet | 2 vCPU / 4GB, Ubuntu 24.04 LTS |
| Deploy | Laravel Forge | — |

**Do not substitute these without asking Paul.** In particular: do not swap Livewire for Inertia, do not swap MySQL for Postgres, do not add Redis before it is measurably needed.

---

## 3. Repository layout

Standard Laravel 11 structure. Additions:

```
/docs
  ADR/                    # Architecture decision records, one per decision
  RUNBOOK.md              # Deploy, rollback, restore-from-backup
  CSV_EXPORT_CONTRACT.md  # Frozen column spec — change only with Paul's sign-off
app/
  Domain/
    TimeTracking/         # Time entry domain logic
    Reporting/            # Unified reporting query engine
    Billing/              # Rate resolution, billable amount calculation
    HarvestImport/        # One-shot importer
  Livewire/
    Timesheet/
    Reports/
    Admin/
  Http/
    Controllers/Auth/     # Google SSO callback only
  Policies/
  Models/
tests/
  Feature/
  Unit/
  Fixtures/
    harvest-csv/          # Sample Harvest exports for import tests
    csv-export-snapshot/  # Frozen CSV header snapshot
```

---

## 4. Data model — authoritative schema

Write the migrations exactly as specified. Every field listed is required unless marked optional. Indexes listed are non-negotiable.

### 4.1 `users`
```
id                          bigint unsigned PK
google_sub                  string, unique, nullable      # Google subject claim
email                       string, unique
name                        string
role                        enum('user','manager','admin') default 'user'   # permission level
role_title                  string nullable                # job title for CSV export "Roles" column, e.g. "Senior Developer"
is_contractor               boolean default false
default_hourly_rate         decimal(8,2) nullable          # null = non-billable by default
weekly_capacity_hours       decimal(5,2) default 37.50
is_active                   boolean default true
last_login_at               timestamp nullable
created_at, updated_at      timestamps
```
Index: `email`, `google_sub`, `(is_active, role)`.

### 4.2 `clients`
```
id                          bigint unsigned PK
name                        string
code                        string, unique, nullable       # e.g. AAB, ABC
is_archived                 boolean default false
created_at, updated_at
```

### 4.3 `projects`
```
id                          bigint unsigned PK
client_id                   FK -> clients.id
code                        string, unique                 # e.g. AAB001
name                        string
billing_type                enum('hourly','fixed_fee','non_billable') default 'hourly'
default_hourly_rate         decimal(8,2) nullable
starts_on                   date nullable
ends_on                     date nullable
is_archived                 boolean default false

# Fields for JDW monthly export (§15). Null for non-JDW projects.
jdw_category                enum('programme','project','support_maintenance') nullable
jdw_sort_order              integer nullable               # row order within JDW report block
jdw_status                  string nullable                # e.g. "Live", "On Hold", "Live - Continuous Improvements"
jdw_estimated_launch        string nullable                # free text — real data has "Q2 2026", "TBC", etc.
jdw_description             text nullable                  # long description populating col O on the JDW sheet

created_at, updated_at
```
Index: `client_id`, `code`, `(is_archived, client_id)`, `(jdw_category, jdw_sort_order)`.

### 4.4 `tasks`
```
id                          bigint unsigned PK
name                        string, unique
is_default_billable         boolean default true
colour                      string(7) default '#3B82F6'    # hex, for stacked bars
sort_order                  integer default 0
is_archived                 boolean default false
created_at, updated_at
```

**Seed with the canonical Filter task list.** Names align with JDW report labels where they overlap (see §15 for context — do not rename these without considering downstream effects on the JDW export).

Billable tasks (`is_default_billable = true`):
- Planning
- Project Management, Meetings & Reporting
- Development
- Design
- Testing
- Release
- Research
- Systems Admin
- Maintenance
- Customer Support
- Training
- Admin

Non-billable tasks (`is_default_billable = false`):
- Holiday
- Bank Holiday
- Sick
- Other Absence
- Lunch
- Break
- Travel
- Finance
- HR
- Recruitment

Note the exact capitalisation and punctuation — "Project Management, Meetings & Reporting" (with ampersand, not comma) and "Customer Support" (capital S). These are the names JDW's report consumes, so they are the canonical names in the app.

### 4.5 `project_task` (pivot)
```
project_id                  FK -> projects.id
task_id                     FK -> tasks.id
is_billable                 boolean                        # per-project override
hourly_rate_override        decimal(8,2) nullable
PK (project_id, task_id)
```

### 4.6 `project_user` (pivot)
```
project_id                  FK -> projects.id
user_id                     FK -> users.id
hourly_rate_override        decimal(8,2) nullable
PK (project_id, user_id)
```

### 4.7 `time_entries`
```
id                          bigint unsigned PK
user_id                     FK -> users.id
project_id                  FK -> projects.id
task_id                     FK -> tasks.id
spent_on                    date
hours                       decimal(5,2)                   # 0.01–24.00
notes                       text nullable
is_running                  boolean default false
timer_started_at            timestamp nullable
is_billable                 boolean                        # DENORMALISED, see 4.8
billable_rate_snapshot      decimal(8,2) nullable          # DENORMALISED
billable_amount             decimal(10,2)                  # hours * rate, 0 if non-billable
invoiced_at                 timestamp nullable             # stub for uninvoiced total
external_reference          string nullable                # populated by Harvest import
created_at, updated_at
```
Indexes (all required):
- `(user_id, spent_on)` — team member reports
- `(project_id, spent_on)` — project reports
- `(task_id, spent_on)` — task reports
- `(spent_on)` — date range scans
- `(is_running)` partial where true — find the running timer
- unique `(user_id, is_running)` where `is_running = true` — at most one running timer per user (enforce via partial unique index or application-level check)

### 4.8 Denormalisation rule — critical

`is_billable`, `billable_rate_snapshot`, and `billable_amount` are **calculated at save time and frozen**. Rate changes, task billable-flag changes, and project setting changes do **not** propagate to existing entries. This matches Harvest's behaviour and is what keeps historical client reports stable.

Resolution order for `billable_rate_snapshot` (first match wins):
1. `project_user.hourly_rate_override` for this (project, user)
2. `project.default_hourly_rate`
3. `user.default_hourly_rate`
4. `null` → entry is non-billable

Resolution for `is_billable`:
1. If `project.billing_type = 'non_billable'` → `false`
2. Else `project_task.is_billable` for this (project, task)
3. `billable_amount = is_billable ? hours * billable_rate_snapshot : 0`

Implement this in `App\Domain\Billing\RateResolver` with full unit test coverage. It is the highest-risk piece of business logic in the system.

### 4.9 `harvest_import_log` (optional but recommended)
```
id, source_harvest_id, imported_at, entity_type, target_id, notes
```
Lets the import script be idempotent and gives a reconciliation trail.

---

## 5. Authentication

### 5.1 Google Workspace SSO (only login method)

- Package: `laravel/socialite`.
- OAuth scopes: `openid email profile`.
- Callback route: `GET /auth/google/callback`.
- In the callback handler, **reject** if:
  - `hd` claim is not `filter.agency`, OR
  - `email` does not end in `@filter.agency`, OR
  - `email_verified` is false.
- On first successful login, auto-provision a user with role `user` and `is_active = true`. Populate `google_sub`, `email`, `name`.
- On subsequent logins, update `last_login_at` and refresh `name` from Google.
- Session cookies, Laravel's default session guard. No JWTs, no API tokens at launch.
- Add a `/auth/logout` that clears the session.

### 5.2 Roles and policies

Three roles: `user`, `manager`, `admin`.

| Capability | user | manager | admin |
|---|---|---|---|
| Enter/edit own time | ✓ | ✓ | ✓ |
| View own reports | ✓ | ✓ | ✓ |
| View team reports (anyone) | — | ✓ | ✓ |
| Edit anyone's time | — | — | ✓ |
| Manage clients/projects/tasks | — | — | ✓ |
| Manage users and rates | — | — | ✓ |
| Run CSV exports | — | ✓ | ✓ |

Enforce via Laravel Policies (`TimeEntryPolicy`, `ReportPolicy`, `AdminPolicy`). Do not sprinkle role checks in Blade/Livewire views — call policy methods.

### 5.3 First admin

Seed one admin user with Paul's email address. All other users are auto-provisioned as `user` on first login; an admin promotes them from the admin screen.

---

## 6. CSV export contract (frozen)

This is a **contract**. Verified against a real Harvest "Detailed time" export supplied by Paul (April 2026, 541 rows). Changing column order or header names breaks Filter's Excel templates. Any change requires Paul's explicit sign-off.

Headers in this exact order (21 columns):

```
Date,Client,Project,Project Code,Task,Notes,Hours,Billable?,Invoiced?,Approved?,First Name,Last Name,Employee Id,Roles,Employee?,Billable Rate,Billable Amount,Cost Rate,Cost Amount,Currency,External Reference URL
```

### 6.1 Field rules

| # | Column | Format / rule |
|---|---|---|
| 1 | `Date` | ISO `YYYY-MM-DD`. |
| 2 | `Client` | `clients.name`, unquoted unless it contains a comma. |
| 3 | `Project` | `projects.name`. |
| 4 | `Project Code` | `projects.code` or empty string (many projects have no code — confirmed in real data, ~78% of rows have no code). |
| 5 | `Task` | `tasks.name`. |
| 6 | `Notes` | `time_entries.notes`, may be empty. Quote if contains comma, newline, or double-quote; escape internal double-quotes by doubling. |
| 7 | `Hours` | Decimal, 1 dp minimum, e.g. `1.0`, `3.5`, `0.25`. Match Harvest's formatting: trailing zeros only where meaningful (`1.0` not `1.00`, but `0.25` not `0.2`). |
| 8 | `Billable?` | `Yes` / `No`. Literal strings. |
| 9 | `Invoiced?` | `Yes` / `No`. In phase 1 always `No` (no invoicing module). |
| 10 | `Approved?` | `Yes` / `No`. In phase 1 always `No` (no approvals module). |
| 11 | `First Name` | Split `users.name` on first space; take part before. |
| 12 | `Last Name` | Everything after the first space; empty if no space. |
| 13 | `Employee Id` | Empty string in phase 1 (not populated in the real Harvest export either — 0/541 rows). Reserved column. |
| 14 | `Roles` | `users.role_title` (new field — see §6.3). Empty string if not set. Comma-separated if multiple, wrapped in quotes. |
| 15 | `Employee?` | `Yes` if `users.is_contractor = false`, else `No`. |
| 16 | `Billable Rate` | 1 dp, e.g. `84.0`. `0.0` when not billable. |
| 17 | `Billable Amount` | 1 dp, e.g. `84.0`. `0.0` when not billable. |
| 18 | `Cost Rate` | `0.0` in phase 1 (cost rates not modelled — confirmed Paul doesn't use them). |
| 19 | `Cost Amount` | `0.0` in phase 1. |
| 20 | `Currency` | Literal string `British Pound - GBP`. Not `GBP`. |
| 21 | `External Reference URL` | `time_entries.external_reference` or empty. |

- Line endings: `\r\n`.
- Encoding: UTF-8, **no BOM** (Harvest's export has no BOM — confirmed in real file).
- Field quoting: RFC 4180 — quote only when the field contains `,`, `"`, `\r`, or `\n`.

### 6.1.1 Why the always-empty columns are retained

Five columns in the real Harvest export are effectively empty in normal operation: `Invoiced?` (0% populated), `Approved?` (0%), `Employee Id` (0%), `Cost Rate` (always `0.0`), `Cost Amount` (always `0.0`). `External Reference URL` is only 4% populated.

**Do not remove these columns as a cleanup task.** They are retained because Filter's downstream Excel templates reference CSV columns by position (via `VLOOKUP`, `INDEX/MATCH`, pivot tables, or direct cell references like `$K$2`). Removing a column shifts every column after it and silently breaks client reports. The cost of keeping empty columns is five extra bytes per row; the cost of breaking a client report is a phone call.

Confirmed with product owner (Paul, April 2026): keep all 21 columns exactly as Harvest exports them. Any future change to this column set requires his explicit sign-off and coordination with whoever maintains the Excel templates.

### 6.2 Snapshot test (required)

Capture the exact header line into `tests/Fixtures/csv-export-snapshot/headers.txt`:

```
Date,Client,Project,Project Code,Task,Notes,Hours,Billable?,Invoiced?,Approved?,First Name,Last Name,Employee Id,Roles,Employee?,Billable Rate,Billable Amount,Cost Rate,Cost Amount,Currency,External Reference URL
```

```php
it('freezes CSV export headers', function () {
    $csv = (new DetailedTimeExport(/* minimal fixture */))->toCsv();
    $headerLine = explode("\r\n", $csv)[0];
    expect($headerLine)->toBe(trim(file_get_contents(
        base_path('tests/Fixtures/csv-export-snapshot/headers.txt')
    )));
});
```

Also write a row-level golden-file test: export a fixed fixture, compare the entire CSV byte-for-byte against a frozen expected output. Run against Paul's real file as part of Phase 6 acceptance.

### 6.3 Schema additions required by the CSV contract

Add to `users` table (update §4.1):

```
role_title                  string nullable                # free-text job title, e.g. "Senior Developer", "Project Manager"
```

This is distinct from `users.role` (the permission level: user/manager/admin). Seed with the role titles found in Paul's CSV: Developer, Senior Developer, Project Manager, Senior Project Manager, Dev Ops Engineer, and whatever other titles appear. Admin-editable on the user edit screen.

---

## 7. Reporting engine — one query class, five views

All five report views aggregate `time_entries` over a date range, grouped by one dimension. Build one query class; do not duplicate logic per view.

### 7.1 `App\Domain\Reporting\TimeReportQuery`

```php
final class TimeReportQuery
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $userId = null,
        public ?int $clientId = null,
        public ?int $projectId = null,
        public ?int $taskId = null,
        public bool $billableOnly = false,
        public bool $activeProjectsOnly = false,
        public bool $includeFixedFee = false,
    ) {}

    public function totals(): TotalsDto;           // headline: total_hours, billable_hours, billable_amount, uninvoiced_amount
    public function groupBy(GroupBy $dim): Collection;   // rows by client|project|task|user
    public function entries(): LazyCollection;     // for CSV export, streamed
}
```

`GroupBy` enum: `Client`, `Project`, `Task`, `User`.

### 7.2 Report views (Livewire components)

| Report | Route | GroupBy | Matches Harvest screenshot |
|---|---|---|---|
| Time (headline) | `/reports/time` | — + `Project` and `Task` tabs | Image 2 (Team Member), Image 3 (Clients headline) |
| Clients | `/reports/clients` | `Client` | Image 3 |
| Projects | `/reports/projects` | `Project` | Image 4 |
| Tasks | `/reports/tasks` | `Task` | Image 5 |
| Team | `/reports/team` | `User` | Image 6 |
| Team member (individual) | `/reports/team/{user}` | — + sub-tabs for Project/Task | Image 2 |

All views share a single header component showing: period selector (Today/This week/This month/Last month/This year/Last year/Custom), "Save report" stub (phase 2), totals card (total hours, billable %, billable amount, uninvoiced amount, "Include Fixed Fee projects" checkbox), and an "Active projects only" filter.

### 7.3 Performance

Target: any report over ≤ 24 months of data returns in < 200ms on the production droplet. Indexes in §4.7 are sufficient; do not add caching before measuring. For "this year" / "last year" whole-company reports, cache for 5 minutes keyed on `(from, to, filters)`.

---

## 8. Time entry UI

### 8.1 Day view (`/timesheet`)

Reproduce the layout in Image 8:
- Week strip: Mon–Sun with per-day totals and a highlighted current day.
- "Track time" green `+` button opens the new-entry modal (Image 1 then Image 7 — project picker then task picker).
- Entries list for the selected day: project (bold), client (inline), task, notes (small grey), hours, Start timer button, Edit button.
- Day total and week total bottom-right.
- "Submit week for approval" button — render as disabled tooltip "Coming soon" in phase 1.

### 8.2 Entry modal

Two-step picker matching Image 1 + Image 7:
1. Project picker: searchable dropdown grouped by client.
2. Task picker: searchable dropdown grouped into "Billable" and "Non-billable" sections based on `project_task.is_billable`.

Then: hours field (accept `1.5`, `1:30`, `90m`), notes field (textarea, unlimited length, stored as plain text), date (defaults to selected day).

### 8.3 Running timer

- At most one running timer per user (DB constraint + application check).
- Starting a timer on an entry sets `is_running = true`, `timer_started_at = now()`.
- Stopping computes `hours += (now() - timer_started_at) / 3600`, rounded to 2 dp, sets `is_running = false`, clears `timer_started_at`.
- If a user starts a timer while another is running, auto-stop the previous one first.
- Timer continues across page reloads and sessions — it's server state.
- Poll every 60s via Livewire to update the displayed running total (do not poll more aggressively).

### 8.4 Week view

Toggle between Day and Week. Week view is a grid: rows = project/task pairs the user has used recently, columns = Mon–Sun, cells = hours. Editable in place. Same underlying `time_entries` rows.

---

## 9. Admin screens

Under `/admin`, admin role only.

- **Users:** list, edit (role, rate, capacity, active/inactive). No create — users provision via SSO.
- **Clients:** CRUD, archive toggle.
- **Projects:** CRUD, assign tasks (sets `project_task` rows with `is_billable` per task), assign users (optional rate overrides).
- **Tasks:** CRUD, reorder, archive.
- **Rates:** read-only report showing current effective rate per (project, user) combination — helps spot mistakes.

Keep these screens plain. Tailwind forms, no fancy components. Paul is the only person using them.

---

## 10. Harvest import

One-shot Artisan command. Idempotent (re-running on the same CSV does not duplicate rows).

```
php artisan harvest:import {path} {--dry-run} {--since=YYYY-MM-DD}
```

Inputs: Harvest "Detailed time" CSV. Matches users by email (fail loudly on unmatched emails — do not auto-create). Matches clients, projects, tasks by name (case-insensitive); creates any that don't exist with sensible defaults, logs what it created.

For each row, populate `time_entries.external_reference` with the Harvest entry ID extracted from the `External Reference URL` column (column 21). Parse the numeric ID from the URL (e.g. `https://filteragency.harvestapp.com/time_entries/12345` → `12345`) and store the ID as the dedupe key — do not store the full URL, as URL format changes would break idempotency.

After import, run `php artisan harvest:reconcile {path}` which prints a month-by-month comparison of total hours and billable amount between the CSV and the new DB. Any row with variance > 0.1 hours or > £1 is flagged.

Test fixtures in `tests/Fixtures/harvest-csv/` with a small known-good dataset and expected reconciliation output.

---

## 11. Phased delivery

**Each phase ends with a green CI build, passing acceptance criteria, and a staging deploy.** Do not carry broken tests forward.

### Phase 1 — Foundation (week 1)

**Build:**
- Fresh Laravel 11 project, Livewire 3, Tailwind, Pest, Larastan, Pint installed and wired into CI.
- GitHub Actions: lint, static analysis (level 8), test on push.
- Google SSO working end-to-end against `filter.agency` domain.
- First-login auto-provisioning.
- Admin/manager/user role enum on users + seeded admin account for Paul.
- Base layout: top nav (Time / Reports / Admin for admins), Google avatar, logout.
- Staging environment deployed on a $6 droplet with SSL.
- `RUNBOOK.md` with deploy, rollback, restore-from-backup procedures (can be stubs initially).

**Acceptance:**
- Paul logs in with his filter.agency account, lands on `/timesheet` (empty state), sees his name top-right.
- A gmail.com test account is rejected with a clear error.
- `APP_TIMEZONE=Europe/London` is set in `.env` and `.env.example`.
- CI green, Larastan level 8 clean.

### Phase 2 — Core data model (week 2)

**Build:**
- All migrations in §4.
- Eloquent models with relationships.
- `RateResolver` with unit tests covering every branch of §4.8.
- Admin CRUD for clients, projects, tasks.
- Admin user list + role/rate/capacity edit.
- Seed data: task list from §4.4, Paul as admin, a handful of real Filter clients for local dev.

**Acceptance:**
- Paul creates a client, project, and task assignment via admin UI. Rate resolution unit tests pass. Can assign users to a project with rate overrides.

### Phase 3a — Time entry UI (week 3)

**Build:**
- `/timesheet` day view matching Image 8.
- Entry modal with two-step project/task picker matching Images 1 and 7.
- Hours parser accepting `1.5` / `1:30` / `90m`.
- On save, denormalise `is_billable`, `billable_rate_snapshot`, `billable_amount` using `RateResolver`.
- Keyboard shortcuts: `N` new entry, `Esc` close modal, `Enter` save.

**Acceptance:**
- Paul can create, edit, and delete a time entry. Denormalisation is correct — changing a rate on a project after the entry exists does not alter the saved entry.
- Feature test: create an entry, mutate the project's rate, assert the entry's `billable_amount` is unchanged.

### Phase 3b — Running timer (week 3, continued)

**Build:**
- Running timer with DB-enforced "one running timer per user".
- Starting a timer while another is running auto-stops the previous one.
- Timer persists across page reloads and sessions (server state).
- Livewire poll every 60s to update displayed running total.

**Acceptance:**
- Timer start/stop behaves correctly across page reloads.
- Starting a second timer auto-stops the first; both entries have correct hours.
- At-most-one-running-timer-per-user is enforced at the DB level (partial unique index or application check with test coverage).

### Phase 4 — Reporting engine + two views (week 4)

**Build:**
- `TimeReportQuery` with full test coverage (totals, groupBy for all four dimensions, entries stream).
- `/reports/time` headline view with period selector.
- `/reports/team/{user}` individual view with Project/Task sub-tabs.
- Shared report header component.

**Acceptance:**
- Totals on `/reports/time` match a hand-calculated sum over test data to the penny.
- Period selector switches between Today/Week/Month/This year/Last year/Custom correctly at UK timezone boundaries (Europe/London, BST/GMT).

### Phase 5 — Remaining reports (week 5)

**Build:**
- `/reports/clients`, `/reports/projects`, `/reports/tasks`, `/reports/team`.
- Stacked horizontal bar chart on the Tasks view using `tasks.colour`.
- "Active projects only" filter on project/client views.
- "Include Fixed Fee projects" toggle.

**Acceptance:**
- Visual parity with Harvest screenshots (Images 3–6) at the information-architecture level — same columns, same rollups, same totals.
- All four views return in < 200ms on a dataset of 60 users × 24 months of realistic entries (generate this in a seeder).

### Phase 6 — CSV export + Harvest import (week 6)

**Build:**
- CSV export endpoint on every report view (button in top-right).
- Detailed time CSV matching §6 exactly.
- Snapshot test on headers.
- `harvest:import` and `harvest:reconcile` Artisan commands per §10.
- Test fixtures and integration tests for import.

**Acceptance:**
- CSV snapshot test passes.
- A known Harvest CSV imports and reconciles to zero variance (use an anonymised real export from Paul).

### Phase 7 — JDW monthly export (week 7)

Must be delivered before cut-over. See §15 for full specification.

**Build:**
- Admin UI on project edit screen for the four JDW fields: `jdw_category`, `jdw_sort_order`, `jdw_status`, `jdw_estimated_launch`, `jdw_description`.
- One-off migration script to populate `jdw_*` fields for existing JDW projects from the current workbook (§15.6).
- `/reports/jdw` screen: month picker, renders the three on-screen tables (Programme, Projects, S&M) with per-table "Copy" buttons producing TSV.
- `.xlsx` generation using Maatwebsite/Laravel-Excel. Download button outputs a single-sheet file matching the layout of one month tab in Olly's workbook (§15.4).
- Golden-file test: generate the export for March 2026 using real (imported) Harvest data, compare to Paul's supplied workbook values cell-by-cell. Variance tolerance: 0.01 hours.

**Acceptance:**
- Olly generates the March 2026 export and it matches his existing sheet for March 2026 to within 0.01 hours on every cell.
- Copy-paste of each block into a blank copy of his workbook lands in the correct cells and preserves his existing column-J `SUM()` formulas.
- New tasks (Bank Holiday, Sick, Recruitment, Other Absence, Customer Support) exist in the app and can be selected on time entries.

### Phase 8 — Hardening (week 8)

**Build:**
- Accessibility pass: keyboard navigation on all interactive elements, ARIA labels on icon-only buttons, color contrast AA.
- Spatie Laravel Backup configured: nightly mysqldump → DO Spaces, 30-day retention.
- Monthly restore-test cron that drops staging DB and restores from latest backup (run Saturdays).
- Sentry wired up in production only.
- Laravel Pulse enabled at `/pulse` (admin-only).
- Uptime monitoring: authenticated check only (e.g. a cron from another Filter server that logs in and hits `/timesheet`). **No public health endpoint** (per Paul, §13.2).
- Security review: CSRF on every form (Livewire handles this by default but verify), rate limiting on `/auth/google/callback`, secure session cookies, HTTPS-only in production.
- Run `php artisan route:list` and confirm no unauthenticated routes other than SSO callback and health check.

**Acceptance:**
- Backup restored into staging successfully.
- No new P1/P2 issues from manual test pass by Paul and one other tester.

### Phase 9 — Parallel run + cut-over (weeks 9–10)

**Build:**
- Production deployed, DNS pointed.
- All staff provisioned on first login across the week.
- Daily reconciliation job comparing new-system weekly totals against Harvest CSV export (admin email).
- Read-only flag on Harvest after cut-over date (documentation task, not code).

**Acceptance:**
- Two full weeks of parallel running complete with variance < £1 per project per week.
- Cut-over on a Monday after a clean reconciliation week.
- Harvest cancellation scheduled for +90 days post-cut-over.

---

## 12. Non-negotiables

These are easy to get wrong and expensive to fix later. Read twice.

1. **Denormalise billable fields on `time_entries` at write time.** Do not compute them on read. (§4.8)
2. **Freeze the CSV header order.** Snapshot test in CI. (§6)
3. **Google SSO domain check is server-side on the callback.** Do not trust the client. (§5.1)
4. **One `TimeReportQuery` class**, five thin Livewire components. Do not duplicate the aggregation logic per report. (§7)
5. **Policies for authorisation**, not role checks in controllers or views. (§5.2)
6. **Indexes listed in §4.7 are mandatory.** The performance target assumes them.
7. **Timezone is `Europe/London` everywhere.** `APP_TIMEZONE=Europe/London` in `.env`. Store timestamps as UTC in DB but render in London time.
8. **Idempotent Harvest importer.** Use `external_reference` to deduplicate. (§10)
9. **Larastan level 8 from day one.** Do not lower it to get a PR through; fix the types.
10. **Every PR has tests.** The rate resolver, CSV export, and report totals are the three areas with the highest test density.
11. **JDW monthly export is a cut-over blocker, not a nice-to-have.** Olly's first post-cut-over month must use the new process. Phase 7 must be complete before Phase 9 begins. (§15)

---

## 13. Resolved questions (from product owner)

Paul has answered the open questions. Record the decisions here so they're not revisited:

1. **Forecast integration:** Not used. Do not build scheduled-hours/delta features. Remove any Forecast references from UI mockups.
2. **Health check endpoint:** Not required to be public. Do not expose `/up` or similar unauthenticated. Monitoring (Phase 8) uses an authenticated check or skip external uptime monitoring.
3. **Initial admin users:** Paul only. Seed exactly one admin: his filter.agency email. All others are promoted from the admin UI after first SSO login.
4. **Archived items in pickers:** Hidden entirely from all selection UIs (project picker, task picker, client filters). Still visible in admin screens when "Show archived" toggle is on. Historical reports must still resolve archived entities to their name — archiving does not hide past time entries.
5. **Harvest test fixture:** Provided. One week export (20–26 April 2026), 541 rows, 21 columns. Use as the golden-file fixture for CSV export test (§6.2) and as the import reconciliation test case (§10). Store at `tests/Fixtures/harvest-csv/detailed-time-week-2026-04-20.csv` (anonymise before committing if it contains sensitive rate or staff data — confirm with Paul before check-in).

---

## 14. Cost envelope (for context, not to optimise against)

Monthly running cost target: under £50/mo all-in. Current Harvest cost: ~£550/mo. Paul has said cost is not the driver for infra choices — optimise for reliability and simplicity first.

---

## 15. JDW monthly export

### 15.1 Background

Filter's largest client, JDW, receives a monthly time report. The report is assembled by Olly in a single long-lived workbook (`JDW_Monthly_Time_Reporting_With_Breakdown.xlsx`). The workbook has three layers:

1. **Per-month sheets** (28 sheets as of April 2026, one per month Feb 2024 → Mar 2026), each containing three hours-based blocks plus manually-maintained header data and a third-party services register.
2. **Summary sheet** — a cross-tab with line items as rows and months as columns, populated entirely by formulas referencing the per-month sheets.
3. **IT Summary sheet** — top-level cost rollup with FY breakdowns for JDW finance.

The workbook is **Olly's working document**. Filter does not take ownership of it in this project. The goal is to eliminate the manual work of transcribing hours figures from Harvest reports into the three hours blocks each month.

### 15.2 Scope — what we build

Two complementary outputs, same data, different consumption modes:

1. **On-screen report** at `/reports/jdw` with a month picker and three tables matching the three hours blocks in Olly's per-month sheet. Each table has a "Copy" button that puts the cell values on the clipboard as tab-separated text, ready to paste into Excel.
2. **`.xlsx` download** that generates a single-sheet workbook matching the exact cell layout of one per-month sheet in Olly's workbook. Olly copies sheets from this file into his main workbook using Excel's "Move or Copy Sheet" command.

### 15.3 Scope — what we do NOT build

- The Summary sheet and IT Summary sheet. These are pure derivations Olly's existing formulas handle for him once the month sheet is populated.
- The header block (Dedicated Team Billing £, Fixed Team Target Hours, third-party services register). These are not time-tracking data. Olly continues to maintain them manually.
- Any vendor/contract register feature.
- Any £-cost output. The app outputs hours only.

### 15.4 On-screen report — `/reports/jdw`

Route: `/reports/jdw` (manager + admin only).

Month picker at the top (defaults to previous complete calendar month). Three tables render below, in this order:

#### Block 1 — Programme Management Hours
Single row of hours, 17 columns in this exact order:

`Planning | Project Management, Meetings & Reporting | Design | Admin | Systems Admin | Research | Training | Finance | HR | Recruitment | Travel | Break | Lunch | Holiday | Bank Holiday | Sick | Other Absence`

Source: sum of hours from `time_entries` where `project.jdw_category = 'programme'`, grouped by task, filtered to the selected month.

Empty cells render as empty (not `0.00`), matching Olly's existing sheet which leaves zero cells blank so the SUM formulas still work visually.

#### Block 2 — Projects Hours
Rows = projects where `jdw_category = 'project'`, ordered by `jdw_sort_order` then by `projects.name`.
Columns in this exact order:

`Development | Project Management, Meetings & Reporting | Testing | Planning | Systems Admin | Design | Release`

Source: sum of hours for the (project, task) combination in the selected month. Empty cell if zero.

Columns shown on screen but **not** part of the Copy output: project name, project code (for Olly's reference; his sheet has those in columns A and B already).

#### Block 3 — Support & Maintenance Hours
Rows = projects where `jdw_category = 'support_maintenance'`, ordered by `jdw_sort_order` then name.
Columns in this exact order:

`Customer Support | Project Management, Meetings & Reporting | Maintenance | Systems Admin`

### 15.5 Copy behaviour

Each of the three tables has its own "Copy" button. On click, the button puts tab-separated values on the clipboard representing only the hours cells (no headers, no project names, no totals). Row separator: `\n` (line feed). Empty cells produce empty fields, not `0`.

Olly's workflow:
1. Click **Copy** on Block 1 → click into cell C13 of his sheet → paste → 17 values populate C13:S13. His existing `=SUM()` in T13 recalculates.
2. Click **Copy** on Block 2 → click into cell C17 → paste → N rows × 7 columns populate C17:I56 (or however many project rows).
3. Click **Copy** on Block 3 → click into cell C63 → paste.

**Testing this reliably is fiddly** because browsers restrict clipboard APIs. Use `navigator.clipboard.writeText()` with a user-initiated click handler. Fallback to a modal showing the TSV selected in a `<textarea>` with "Select all + Ctrl+C" instructions if the API fails.

### 15.6 `.xlsx` download

A single-sheet workbook generated via Maatwebsite/Laravel-Excel. Sheet layout matches one per-month sheet from Olly's workbook:

- `A1` = "Month:", `B1` = date (first of selected month).
- Rows 3–6 left **empty** (header block — Dedicated Team Billing etc. — is manually maintained).
- Row 9 = "Planning, Development & Delivery " section header (cell A9 only).
- Rows 10–13 = Programme Management block: R10 tagging row (R&P / TMC), R11 headers, R13 values.
- Row 16 = Projects block headers, rows 17+ = project rows with name, code, hours, total formula.
- Row 60 = "Support and Maintenance" section header.
- Rows 62–71 = S&M block.
- Column L on project rows = `jdw_status`, column N = `jdw_estimated_launch`, column O = `jdw_description`.
- Column J on each project row = `=SUM(C{row}:I{row})` (preserve formulas rather than hardcoded totals).
- Total row at 57: `J57 = =SUM(J17:J56)`.

Olly's workflow with this file: open the download, in Excel right-click the sheet tab → "Move or Copy…" → select his main workbook as destination → tick "Create a copy". The new sheet arrives with all hours and formulas. He then fills in the header block manually.

File naming: `jdw-time-report-{YYYY-MM}.xlsx`.

### 15.7 Seeding existing JDW projects

As part of Phase 7, seed the `jdw_*` fields on existing JDW projects from the current workbook. Use the March 2026 sheet as the source of truth for the current state:

- `jdw_category` — inferred from which block the project appears in (Projects block → `project`, S&M block → `support_maintenance`). Programme Management has no project rows.
- `jdw_sort_order` — row index within the block (17 → 1, 18 → 2, etc.).
- `jdw_status` — column L value (e.g. "Live - Continuous Improvements", "On Hold", "Live").
- `jdw_estimated_launch` — column N value. Free text — real data includes "Q2 2026", "TBC", plain dates. Do not try to parse as a date.
- `jdw_description` — column O value.

Write this as a one-off seeder `JdwProjectMetadataSeeder` that reads from a checked-in copy of the workbook (or a cleaned CSV of its project metadata). Run once after Phase 7 projects are seeded, then never again — ongoing maintenance is via the admin UI.

### 15.8 Golden-file test

Acceptance rests on reproducing Paul's supplied workbook. Test:

1. Import the Harvest detailed-time CSV for March 2026 into the test database.
2. Generate the JDW export for March 2026.
3. For each of the three blocks, assert every hours value matches Olly's existing sheet within 0.01 hours.

The test fixture pair (Harvest CSV + Olly's workbook for the same month) is the reference for this. If they disagree by more than 0.01 hours on any cell, investigate before declaring Phase 7 complete — it usually indicates either a task-name mismatch or a project mis-categorisation, both of which are data issues rather than code bugs.

### 15.9 Maintenance note for Claude Code

The `jdw_estimated_launch` is deliberately a string, not a date. The real data contains "Q2 2026", "TBC", "Ongoing", "Various", and plain dates. Do not migrate this to a `date` type even though it looks tempting — it will lose information.

Olly may add or retire projects from the JDW report over time. The admin UI must make it straightforward to set `jdw_category = null` to remove a project from the report without archiving the project entirely, and to reorder projects via `jdw_sort_order`.

---

