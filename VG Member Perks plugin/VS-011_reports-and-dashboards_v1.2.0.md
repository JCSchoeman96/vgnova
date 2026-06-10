# FEATURE PLANNING PACK: VS-011 — Reports and Dashboards v1.2.0

## 0. Version Notes

This document supersedes the earlier `VS-011_reports-and-dashboards.md` planning pack.

This hardened version updates the reporting slice for the corrected architecture:

```text
VS-007 = Claims and Redemption Ledger
VS-008 = WooCommerce Product Discounts
```

It also updates all reporting language and data expectations for the launch dual-programme membership model:

```text
Launch programme 1: Voelgoed Vriendinneklub
Launch programme 2: Voelgoednetwerk
A user may have none, one, or both launch programmes.
A user should have at most one active resolved level per programme.
```

## 0A. v1.2.0 Programme and Level Flexibility Patch Summary

This version applies the Programme and Level Flexibility Pass.

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a closed forever-list.
2. Requires report filters to load enabled configured programmes from ProgrammeRegistry / SettingsRepository.
3. Requires level filters to load configured ProgrammeLevelDefinition rows from ProgrammeMappingRepository.
4. Adds Unknown / Legacy buckets for historical rows with unmapped or disabled programme/level values.
5. Adds Mapping Drift reporting for PMPro/Woo/mapping mismatches.
6. Adds programme_mapping_hash awareness for redemption/profile/report interpretation.
7. Adds tests proving future levels, billing variations, and configured programmes can appear in reports without report-code changes.
8. Keeps all reports read-only by default.
```

This slice follows:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
Report unknown/legacy data honestly.
```

This slice remains read-only by default. It must expose operational truth, not change business behaviour.

---

## 1. Objective

Build the first management reporting layer for the **Voelgoed Member Perks** plugin.

This vertical slice must give administrators and authorised managers a clear view of:

```text
Active perks
Upcoming perks
Expired perks
Paused perks
Archived perks
Misconfigured perks
Claim/redemption usage
WooCommerce perk usage if VS-008 exists
Masteriyo course perk usage if VS-009 exists
Tickera ticket perk usage if VS-010 exists
Perks with no claims
Perks close to expiry
Risky or incomplete perk configuration
Membership programme coverage
Dual-programme member usage
Programme-level performance
Resolver/configuration conflicts
```

This slice is a reporting and admin visibility slice.

It must not change:

```text
pricing
eligibility
claim limits
course access
ticket issuing
runtime status automation
frontend hub behaviour
notifications
membership state
```

Reports must help management see what is happening and what needs attention. They must not become a hidden execution layer.

---

## 2. Current Baseline Assumptions

This slice assumes the previous slices exist or have been implemented in the corrected order:

```text
VS-000 — Current Site Discovery and Conflict Audit
VS-001 — Plugin Foundation, CPT and Taxonomies
VS-002 — ACF Fields and Options Page
VS-003 — PMPro Membership Profile Resolver
VS-004 — Runtime Status Automation
VS-005 — Bricks/Etch Programme Hub Display
VS-006 — Target and Audience Indexing Table
VS-007 — Claims and Redemption Ledger
VS-008 — WooCommerce Product Discounts
VS-009 — Masteriyo Course Perks
VS-010 — Tickera Ticket Perks
```

VS-011 must be tolerant if VS-009 or VS-010 has not been completed yet.

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected CPT:

```text
vg_perk
```

Expected custom tables:

```text
{$wpdb->prefix}vgmp_perk_targets
{$wpdb->prefix}vgmp_perk_redemptions
```

Do **not** use the legacy table names for new work:

```text
{$wpdb->prefix}vg_perk_targets
{$wpdb->prefix}vg_perk_redemptions
```

If legacy tables are found, reports may show an admin warning, but new report queries must use the `vgmp_*` table names.

Expected runtime status taxonomy:

```text
vg_perk_runtime_status
```

Expected runtime status terms:

```text
active
upcoming
expired
paused
archived
misconfigured
```

Expected membership profile concepts from VS-003:

```text
MembershipProfile
programme_key
level_key
programme_label
level_label
qualified_path
eligibility_hash
has_any_membership
has_multiple_programmes
conflicts
```

Default launch programme keys:

```text
vriendinneklub
voelgoednetwerk
```

These are launch/default programme examples, not the only possible programmes forever.

Runtime/report code must load enabled configured programmes through:

```text
ProgrammeRegistry
SettingsRepository
ProgrammeMappingRepository
```

Related model document:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Reports must support future configured programmes and levels without requiring report-code changes.

Expected redemption ledger columns from VS-007 include programme-aware context:

```text
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
perk_config_hash
claim_scope
claim_period_key
source
status
benefit_type
discount_amount
final_member_price
```

`programme_mapping_hash` is important for support and historical reporting. If a claim was made before a mapping change, reports should preserve the recorded context and indicate when the current mapping no longer recognises that stored value.

If VS-007 is not present, claims/redemption reports cannot be built. In that case, stop and report what is missing before implementing usage reporting.

---

## 3. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Admin report pages
Dashboard summary cards
Active/upcoming/expired/paused/archived/misconfigured perk tables
Redemption/claim usage tables
Programme-aware usage reports
Dual-member cohort summaries
Membership resolver conflict reports
Basic filters
CSV export for reports
Problem/misconfiguration report
Read-only support views
Admin-only debug summaries
```

This slice must not implement:

```text
New discount logic
New claim rules
New course access rules
New ticket generation
New email notification campaigns
Automated member messaging
Automatic perk activation changes beyond VS-004 runtime logic
Destructive cleanup of old perks
Data deletion tools
Frontend member hub redesign
Automated repair actions
Bulk archive/pause/delete actions
```

Reports must be read-only by default.

If a future report suggests a repair action, that repair action belongs to VS-012 or another dedicated tools slice.

---

## 4. Reporting Philosophy

The report layer must answer operational questions without becoming an authority.

Correct model:

```text
Existing source systems
↓
Read-only report queries
↓
Admin interpretation
↓
Future deliberate admin action if needed
```

Incorrect model:

```text
Report page notices a problem
↓
Report silently fixes pricing, claims, eligibility, or access
```

The reports must make risk visible. They must not hide complexity by mutating data behind the scenes.

## 4A. Programme and Level Reporting Boundary

Reports must not hardcode the launch programme or level set as if it is permanent.

Correct model:

```text
ProgrammeRegistry / SettingsRepository
↓
ProgrammeMappingRepository
↓
Configured programme and level definitions
↓
Report filters, labels, buckets, and exports
```

Rules:

```text
1. Programme filters must be generated from enabled configured programmes.
2. Level filters must be generated from configured levels for the selected programme.
3. Vriendinneklub and Voelgoednetwerk are launch/default programme examples.
4. Future programmes must appear in reports if configured, enabled, and present in data.
5. Unknown, disabled, unmapped, or historical values must appear as Unknown / Legacy.
6. Reports must not silently remap old values unless an explicit alias rule exists.
7. Reports must not treat every PMPro level as valid merely because it exists.
8. Only mapped PMPro levels count for current MembershipProfile reporting.
9. Historical redemption rows remain historical proof; do not rewrite their programme_key/level_key values.
10. Mapping drift must be visible to admins.
```

Forbidden in report logic:

```text
hardcoded PMPro level IDs
hardcoded PMPro group IDs
hardcoded Woo membership product IDs
hardcoded Woo variation IDs
fixed Goud/Silwer/Brons assumptions
fixed Brons/Silwer/Goud assumptions
assuming only two programmes can ever exist
```

---

## 5. Required Deliverables

Add the following plugin files:

```text
includes/
├── Reports/
│   ├── ReportsMenu.php
│   ├── DashboardReport.php
│   ├── PerkStatusReport.php
│   ├── RedemptionReport.php
│   ├── PerkPerformanceReport.php
│   ├── ProgrammeReport.php
│   ├── ProgrammeMappingDriftReport.php
│   ├── MembershipProfileReport.php
│   ├── MisconfigurationReport.php
│   ├── ReportFilters.php
│   ├── ReportExporter.php
│   ├── ReportRepository.php
│   └── ReportFormatter.php
│
├── Admin/
│   ├── ReportsPage.php
│   └── ReportAssets.php
│
└── Support/
    └── CsvExporter.php
```

Update existing files:

```text
includes/Plugin.php
README.md
```

Optional if already established:

```text
assets/css/admin-reports.css
assets/js/admin-reports.js
```

Keep admin assets light and load them only on plugin report pages.

---

## 6. Admin Menu Structure

Create a main reports/admin area under the plugin menu.

Recommended menu structure:

```text
Member Perks
├── All Member Perks
├── Add New
├── Reports
│   ├── Dashboard
│   ├── Perk Status
│   ├── Redemptions
│   ├── Performance
│   ├── Programmes
│   ├── Members
│   └── Issues
├── Tools
└── Settings
```

If WordPress menu constraints make this difficult, implement:

```text
Member Perks > Reports
```

with tabs:

```text
Dashboard
Perk Status
Redemptions
Performance
Programmes
Members
Issues
```

Required capability:

```text
manage_vg_perks
```

No report should be visible to normal members.

---

## 7. ReportsMenu Class

Create:

```php
Voelgoed\MemberPerks\Reports\ReportsMenu
```

Responsibilities:

```text
1. Register report admin pages/tabs.
2. Check capabilities.
3. Route report views.
4. Enqueue report assets only on report pages.
5. Do not load report data unless a report page is actually requested.
```

Do not load reporting queries on every admin page.

---

## 8. Dashboard Report

Create:

```php
Voelgoed\MemberPerks\Reports\DashboardReport
```

Purpose:

```text
Show a fast high-level management overview.
```

Required summary cards:

```text
Active perks
Upcoming perks
Expired perks this month
Paused perks
Archived perks
Misconfigured perks
Total redemptions
Redemptions this month
Top claimed perk
Perks with zero claims
Perks expiring in next 7 days
Estimated member saving this month
```

Programme-aware summary cards:

```text
Active perks by configured programme
Redemptions this month by configured programme
Shared/multi-programme active perks
Dual/multi-programme member redemptions this month
Unattributed redemptions needing review
Unknown / legacy programme rows
Mapping drift warnings
```

Launch examples may display as:

```text
Vriendinneklub-targeted active perks
Voelgoednetwerk-targeted active perks
Vriendinneklub redemptions this month
Voelgoednetwerk redemptions this month
```

But report code must generate these from configured programme definitions, not hardcoded two-programme conditionals.

If VS-009 exists, optionally include:

```text
Course claims
Course claims completed
Course claims failed
```

If VS-010 exists, optionally include:

```text
Ticket claims
Ticket claims completed
Ticket claims failed/refunded/cancelled
```

Keep dashboard queries efficient.

Do not load all redemption rows into PHP to count them.

---

## 9. Perk Status Report

Create:

```php
Voelgoed\MemberPerks\Reports\PerkStatusReport
```

Purpose:

```text
Show operational status of perks.
```

Columns:

```text
Perk title
Runtime status
Programme audience
Programme levels
Type
Section
Campaign
Start date
End date
Targets
Claim mode
Claim scope
Claim count
Last claim date
Issue count
Admin edit link
```

Filters:

```text
Runtime status
Programme
Programme level
Type
Section
Campaign
Date range
Claim scope
Has claims / no claims
Expiring soon
Has issues
Shared across programmes
```

Required views:

```text
Active
Upcoming
Expired
Paused
Archived
Misconfigured
Expiring soon
No claims
Shared/multi-programme perks
Perks by each enabled configured programme
Unknown / legacy programme perks
```

Launch-specific quick views may include:

```text
Vriendinneklub perks
Voelgoednetwerk perks
```

but they must be generated from configured launch programme definitions.

Do not delete or auto-archive expired perks from this report.

---

## 10. Redemption Report

Create:

```php
Voelgoed\MemberPerks\Reports\RedemptionReport
```

Purpose:

```text
List and filter claim/redemption records.
```

Columns:

```text
Date
User
Email
Perk
Target type
Target
Status
Source
Programme
Level
Qualified path
Claim scope
Claim period
Benefit type
Discount amount
Final member price
Order
Order item
Eligibility hash
```

Use display labels from configured programme/level definitions where possible:

```text
Vriendinneklub Goud
Voelgoednetwerk Silwer
Configured Programme Label + Configured Level Label
Multiple programmes
Unknown / legacy
Unknown / not recorded
```

If the stored `programme_key` or `level_key` no longer exists in current mapping, show the raw stored value in an admin-safe way under an Unknown / Legacy label. Do not silently rewrite historical rows.

Filters:

```text
Date range
Perk
Status
Source
Programme
Programme level
Qualified path
Claim scope
Target type
Benefit type
Order ID
User search
Has order
Has discount amount
```

Sources may include:

```text
manual
hub
generic_claim
woo_order
course_claim
ticket_claim
admin
```

Status filters:

```text
reserved
claimed
completed
cancelled
refunded
failed
expired
voided
```

If email or PII display is sensitive, limit to users with:

```text
manage_vg_perks
```

Do not expose this report publicly.

---

## 11. Perk Performance Report

Create:

```php
Voelgoed\MemberPerks\Reports\PerkPerformanceReport
```

Purpose:

```text
Help management understand which perks are being used.
```

Minimum metrics per perk:

```text
Total successful redemptions
Unique users
Total recorded discount amount
Average recorded discount amount
First claim date
Last claim date
Claims by programme
Claims by programme level
Claims by source
Claims by status
Claims by claim scope
```

Useful views:

```text
Most claimed perks
Highest recorded member-saving perks
Zero-claim active perks
Expired perks with zero claims
Top perks by configured programme
Top perks by configured programme level
Top shared/multi-programme perks
Top unknown/legacy programme rows
Top course claims
Top ticket claims
Top product discounts
```

Launch examples such as Top Vriendinneklub perks and Top Voelgoednetwerk perks may be rendered as configured programme tabs/cards.

Important:

```text
This is operational reporting, not formal accounting.
Do not present discount totals as audited financial figures.
```

Use clear labels:

```text
Estimated member saving
Recorded discount amount
```

Avoid saying:

```text
Revenue loss
```

unless the business has explicitly defined that interpretation.

---

## 12. Programme Report

Create:

```php
Voelgoed\MemberPerks\Reports\ProgrammeReport
```

Purpose:

```text
Show how perks and claims are distributed across configured programmes and levels, including the launch programmes Vriendinneklub and Voelgoednetwerk.
```

Required sections:

```text
Perks by configured programme
Perks by configured programme level
Active perks by configured programme
Expiring perks by configured programme
Redemptions by configured programme
Redemptions by configured programme level
Recorded discount amount by configured programme
Shared/multi-programme perks claimed by multi-programme users
Unattributed redemptions
Unknown / legacy programme rows
```

Required programme buckets:

```text
one bucket per enabled configured programme_key
shared
mixed
legacy_unknown
unknown
```

Launch example buckets:

```text
vriendinneklub
voelgoednetwerk
shared
unknown
```

Definitions:

```text
configured programme bucket = perk/claim resolved through a currently configured programme.
shared = perk targets or qualified through more than one configured programme.
mixed = historical/ledger context explicitly recorded more than one programme.
legacy_unknown = stored programme_key or level_key is no longer recognised by current mapping.
unknown = incomplete data without programme context.
```

Do not infer programme from display text if programme metadata exists.

If old records lack programme data, show them as:

```text
Unknown / legacy
```

If old records use an alias or previous key such as `old_programme_key`:

```text
1. If an explicit alias maps it to a configured programme, reports may show the configured label with an alias note.
2. If no alias exists, reports must show Unknown / Legacy.
3. Reports must not silently invent programme mappings.
```

## 12A. Programme Mapping Drift Report

Create:

```php
Voelgoed\MemberPerks\Reports\ProgrammeMappingDriftReport
```

Purpose:

```text
Show configuration drift between PMPro, Woo billing products, programme mappings, audience indexes, and historical ledger rows.
```

Required checks:

```text
Active PMPro levels not mapped to any enabled programme level
Mapped PMPro level IDs that no longer exist
Duplicate PMPro level IDs mapped to more than one enabled programme level
Woo subscription products/variations that grant unmapped PMPro levels
Mapped PMPro levels with no known active billing product/variation
Redemption rows with unknown programme_key
Redemption rows with unknown level_key
Redemption rows whose programme_mapping_hash differs from current mapping hash
Audience index rows with disabled/unknown programme_key
Audience index rows with disabled/unknown level_key
Programme aliases that collide with another programme key or alias
Level aliases that collide inside the same programme
```

Columns:

```text
Area
Severity
External system
Stored value
Current mapping status
Affected programme
Affected level
Affected PMPro level ID
Affected Woo product/variation ID
Suggested fix
Inspection link
```

Important:

```text
This report is read-only.
It must not repair mappings automatically.
It must not rewrite historical redemption rows.
It must not treat unmapped PMPro levels as eligible.
```

---

## 13. Membership Profile Report

Create:

```php
Voelgoed\MemberPerks\Reports\MembershipProfileReport
```

Purpose:

```text
Help administrators understand member programme coverage and resolver problems.
```

This report may use the VS-003 MembershipProfile resolver.

Required views:

```text
No active programme
One configured programme only
Multiple configured programmes
Launch Vriendinneklub only
Launch Voelgoednetwerk only
Both launch programmes
Resolver conflicts
Unmapped PMPro levels
Users with multiple active levels inside the same programme
```

Minimum columns:

```text
User
Email
Resolved programmes
Programme levels
Launch Vriendinneklub level if present
Launch Voelgoednetwerk level if present
Has multiple programmes
Conflict count
Mapping hash
Resolved profile hash
Last checked at
```

Performance warning:

```text
Do not scan all users on every page load without pagination.
```

Minimum acceptable implementation:

```text
User search / inspect specific user
Small paginated resolver audit
Conflict-only view
```

A full all-user membership audit can be deferred if it is too heavy.

---

## 14. Misconfiguration Report

Create:

```php
Voelgoed\MemberPerks\Reports\MisconfigurationReport
```

Purpose:

```text
Surface perks, mappings, and runtime state that need admin attention.
```

Detect issues such as:

```text
ACF inactive
PMPro inactive or resolver unavailable
Target index table missing
Redemption table missing
Legacy vg_perk_targets table found
Legacy vg_perk_redemptions table found
Active perk with no audience rule
Active perk with no programme target
Active perk with no benefit target
Active perk with unsupported benefit type
Perk with end date before start date
Perk with missing start date
Perk runtime status = misconfigured
Free product/ticket/course perk with no claim limit
Claimable perk with claim_scope missing
Ticket event target with multiple tickets and no default
Course perk with Masteriyo inactive
Ticket perk with Tickera/Woo Bridge inactive
Woo discount perk targeting missing product
Target index stale or empty for a perk with targets
Audience index stale or empty for a perk with audience rules
Runtime status mismatch
Archived perk still active in taxonomy
Expired perk still marked active
Redemption row with missing programme_key where it should exist
Redemption row with unknown qualified_path
Same-programme membership conflict detected
PMPro level mapping duplicate across levels in same programme
PMPro level active but unmapped
Woo subscription product grants unmapped PMPro level
Programme alias collision
Level alias collision inside same programme
Redemption row with stale programme_mapping_hash
Audience index row with disabled/unknown programme_key
Audience index row with disabled/unknown level_key
```

Each issue should have:

```text
Severity
Area
Perk or user if applicable
Issue code
Human-readable message
Suggested fix
Admin edit link or inspection link
```

Severity levels:

```text
critical
warning
notice
```

Do not automatically fix issues in VS-011.

---

## 15. ReportRepository

Create:

```php
Voelgoed\MemberPerks\Reports\ReportRepository
```

Purpose:

```text
Centralise report queries.
```

Responsibilities:

```text
1. Count perks by runtime status.
2. Count redemptions by date/status/source.
3. Count redemptions by programme and level.
4. Get top claimed perks.
5. Get zero-claim perks.
6. Get expiring perks.
7. Query redemption rows with filters.
8. Query performance aggregates.
9. Query programme-level aggregates.
10. Query issue/misconfiguration data where SQL is appropriate.
11. Query configured programme and level definitions for labels/filters.
12. Query mapping drift data.
13. Query unknown/legacy programme and level values from redemptions/index tables.
```

Use direct SQL where appropriate for:

```text
redemptions
target index
audience index
aggregates
```

Use `WP_Query` where appropriate for:

```text
vg_perk CPT status/taxonomy queries
```

Use prepared SQL.

Do not query the database inside table row rendering if avoidable.

---

## 16. ReportFilters

Create:

```php
Voelgoed\MemberPerks\Reports\ReportFilters
```

Purpose:

```text
Normalise and sanitise report filter inputs from admin requests.
```

Supported filters:

```text
date_from
date_to
perk_id
user_id
status
source
programme_key
level_key
qualified_path
claim_scope
target_type
benefit_type
runtime_status
type
section
campaign
user_search
order_id
has_order
has_discount
has_issues
page
per_page
```

Deprecated legacy filters may be accepted only as aliases:

```text
program      → programme_key
level_group  → level_key or legacy display alias
membership_group → qualified_path / programme-level display alias
```

Rules:

```text
per_page default = 50
per_page maximum = 200
page minimum = 1
dates use site timezone
programme_key must normalize to one of:
  - all
  - shared
  - unknown
  - legacy_unknown
  - an enabled configured programme_key from ProgrammeRegistry
level_key must be one of configured level keys for the selected programme when possible
programme aliases may be accepted as admin filter input only if ProgrammeMappingRepository normalizes them explicitly
level aliases may be accepted as admin filter input only if they normalize inside the selected programme
all slugs sanitized
IDs cast to positive integers
booleans normalised
unknown statuses rejected
```

Report filters must not hardcode the launch programme list. They may expose launch shortcuts, but actual validation comes from ProgrammeRegistry / ProgrammeMappingRepository.

---

## 17. ReportFormatter

Create:

```php
Voelgoed\MemberPerks\Reports\ReportFormatter
```

Purpose:

```text
Format report values consistently.
```

Responsibilities:

```text
Format dates
Format currency
Format runtime status labels
Format redemption status labels
Format source labels
Format programme labels
Format level labels
Format claim scope labels
Format empty/unknown values
Format user links
Format order links
Format perk edit links
Format target links
```

Use WooCommerce currency formatting if WooCommerce is active:

```php
wc_price()
```

Fallback to plain numeric formatting if WooCommerce is inactive.

For unknown programme context, use a clear label:

```text
Unknown / legacy
```

For disabled or previously valid programme/level values, use:

```text
Unknown / legacy: {stored_value}
```

For alias-normalised values, optionally show an admin-safe note:

```text
Vriendinneklub (from alias: old_programme_key)
```

Do not silently hide missing programme data.
Do not silently rewrite historical values in reports or CSV exports.

---

## 18. CSV Export

Create:

```php
Voelgoed\MemberPerks\Support\CsvExporter
```

and:

```php
Voelgoed\MemberPerks\Reports\ReportExporter
```

Supported exports:

```text
Redemptions CSV
Perk Status CSV
Performance CSV
Programme CSV
Issues CSV
```

Security:

```text
Capability check: manage_vg_perks
Nonce verification
Sanitise filters
Stream CSV with correct headers
Do not allow arbitrary file paths
Do not write CSV exports to public uploads unless explicitly needed
```

CSV should include a generated timestamp and applied filters in the filename.

Example:

```text
vg-member-perks-redemptions-2026-06-08.csv
vg-member-perks-programmes-2026-06-08.csv
vg-member-perks-issues-2026-06-08.csv
```

CSV exports may include admin-visible operational PII only when capability checks pass.

Do not include raw internal notes unless explicitly required.

---

## 19. Admin Assets

If CSS is added:

```text
Load only on plugin report pages.
```

Recommended classes:

```text
vgmp-report-page
vgmp-report-tabs
vgmp-report-card-grid
vgmp-report-card
vgmp-report-table
vgmp-report-filter-bar
vgmp-report-severity-critical
vgmp-report-severity-warning
vgmp-report-severity-notice
vgmp-report-programme
vgmp-report-programme--configured
vgmp-report-programme--vriendinneklub
vgmp-report-programme--voelgoednetwerk
vgmp-report-programme--shared
vgmp-report-programme--mixed
vgmp-report-programme--legacy-unknown
vgmp-report-programme--unknown
```

Launch-specific classes are allowed for styling convenience, but report rendering must also support future configured programme keys.

Do not load admin JS globally.

Use progressive enhancement only.

Reports must be usable without JavaScript.

Do not add heavy charting libraries in VS-011.

---

## 20. Data Privacy and PII Rules

Reports may show:

```text
User display name
User email
Order ID
Claim timestamps
Programme
Level
Qualified path
Claim source
```

Because this is admin-only operational data.

Rules:

```text
Only users with manage_vg_perks can view reports.
Do not expose reports through public REST endpoints.
Do not include reports in frontend pages.
Do not export CSV without nonce and capability check.
Do not store generated exports in public uploads by default.
Do not expose raw internal notes unless intentionally required for admin troubleshooting.
```

If future roles need limited report access, add a dedicated capability later.

Do not invent legal claims. Preserve conservative data handling.

---

## 21. Performance Requirements

Reports can become heavy.

Rules:

```text
1. Paginate redemption reports.
2. Default per_page = 50.
3. Maximum per_page = 200.
4. Use SQL aggregates for counts.
5. Do not load all redemptions into PHP.
6. Do not call get_user_by for hundreds of users one-by-one if avoidable.
7. Use cached/bulk user lookups where practical.
8. Use indexes already created on redemptions table.
9. Avoid report queries on normal frontend requests.
10. Avoid report queries on unrelated admin screens.
11. Do not scan all users for membership profile reports on every page load.
12. Use date filters by default for large usage reports.
```

If a query may be heavy, note it in README and keep the default filtered/date-limited.

---

## 22. Date Handling

Use site timezone.

Default dashboard period:

```text
Current month
```

Useful presets:

```text
Today
Last 7 days
This month
Last month
This year
Custom range
```

Use WordPress timezone functions where possible.

Do not mix server UTC and site-local dates in a way that confuses admins.

---

## 23. Empty States

Reports must handle no data gracefully.

Examples:

```text
No redemptions found for the selected filters.
No active perks found.
No misconfigured perks detected.
No zero-claim perks found.
No programme data found for this filter.
No dual-programme usage found for this period.
No resolver conflicts found.
```

Do not show PHP warnings or raw SQL messages.

---

## 24. Admin Table Implementation

Use a simple custom table or `WP_List_Table`.

If using `WP_List_Table`, document that it is a WordPress internal class and load it safely.

Minimum requirements:

```text
Pagination
Sorting where practical
Filters
Clear links to edit perk/order/user
CSV export
```

Do not overbuild with charts in VS-011.

Charts can come later.

---

## 25. Issue Detection Rules

The MisconfigurationReport should use existing services where practical:

```text
Target repository
Audience index repository
Runtime status updater/status taxonomy
ACF field loaders
Programme registry
ProgrammeMappingRepository
SettingsRepository
MembershipProfile resolver
BillingAccessMapper or Woo membership-product audit helper if it exists
Masteriyo detector if exists
Tickera detector if exists
Woo detector/integration if exists
```

Do not duplicate complicated integration detection logic if VS-009/VS-010 already created detector classes.

If those slices are absent:

```text
Skip their issue checks safely.
Show an informational notice that course/ticket-specific checks are unavailable.
```

---

## 26. Report Links

Where possible:

```text
Perk title links to edit perk screen.
User links to user edit screen.
Order links to WooCommerce order edit screen if WooCommerce is active.
Target links to edit product/course/event/page/post if target exists.
Issue rows link to the relevant perk/settings/debug page.
Programme mapping issues link to Perk Settings > Programme Mapping.
```

Only show links if the current admin has permission to access them.

---

## 27. Security Requirements

All report pages/actions must:

```text
Check current_user_can( "manage_vg_perks" )
Use nonces for CSV export and actions
Sanitise all filter inputs
Use prepared SQL
Escape all output
Avoid raw HTML from database values
Avoid exposing internal notes unless intentionally displayed to admins
```

Do not trust query string parameters.

Do not expose report data through unauthenticated REST endpoints.

---

## 28. Compatibility Requirements

This slice must not fatal if these plugins are inactive:

```text
WooCommerce
Woo Subscriptions
Paid Memberships Pro
Masteriyo
Tickera
Tickera WooCommerce Bridge
ACF Pro
Bricks
Etch
```

If WooCommerce is inactive:

```text
Order links and wc_price formatting fall back safely.
Woo-specific dashboard cards are hidden or marked unavailable.
```

If PMPro is inactive or VS-003 resolver is unavailable:

```text
MembershipProfile report is hidden or shows dependency warning.
Programme usage from existing redemption rows may still display if data exists.
```

If Masteriyo is inactive or VS-009 absent:

```text
Course-specific report widgets are hidden or marked unavailable.
```

If Tickera is inactive or VS-010 absent:

```text
Ticket-specific report widgets are hidden or marked unavailable.
```

If ACF is inactive:

```text
Misconfiguration report shows ACF inactive warning.
Reports still load where possible.
```

---

## 29. Testing Plan

Test on staging only.

### Test 1 — Reports menu access

```text
Log in as Administrator.
Expected: Member Perks > Reports visible.

Log in as non-admin/member.
Expected: reports not visible.
```

### Test 2 — Dashboard cards

```text
Create active, upcoming, expired, paused, archived, and misconfigured perks.
Expected: counts display correctly.
```

### Test 3 — Perk status report

```text
Filter by active.
Expected: only active perks shown.

Filter by programme = vriendinneklub.
Expected: Vriendinneklub-targeted perks shown.

Filter by programme = voelgoednetwerk.
Expected: Voelgoednetwerk-targeted perks shown.

Filter by campaign/section/type.
Expected: filters apply correctly.
```

### Test 4 — Redemption report

```text
Create/record several redemptions.
Expected: rows show user, perk, status, source, programme, level, claim scope, and order if available.
```

### Test 5 — Redemption filters

```text
Filter by status, source, date range, programme, level, claim scope.
Expected: correct results.
```

### Test 6 — Programme report

```text
Create/record claims for Vriendinneklub, Voelgoednetwerk, and shared/both-programme perks.
Expected: launch programme buckets display correctly.

Create or simulate a configured future programme row if safe.
Expected: report can show that programme from ProgrammeRegistry without report-code changes.

Create or simulate a historical row with an unmapped programme_key.
Expected: Unknown / Legacy bucket displays it clearly.
```

### Test 7 — Membership profile report

```text
Inspect users in these states:
- no membership
- Vriendinneklub only
- Voelgoednetwerk only
- both programmes
- same-programme conflict if safe to simulate
Expected: report labels states clearly.
```

### Test 8 — Performance report

```text
Create claims for multiple perks.
Expected: top claimed and zero-claim reports are correct.
Expected: programme-level aggregates are correct where programme data exists.
```

### Test 9 — Issues report

```text
Create misconfigured perk:
- active but no target
- active but no audience rule
- end date before start date
- free perk with no claim limit
Expected: issues appear with severity and suggested fix.
```

### Test 10 — CSV export

```text
Export redemptions CSV.
Expected: file downloads.
Expected: filters respected.
Expected: no unauthorised access.

Export programme CSV.
Expected: programme/level columns included.
```

### Test 11 — Woo inactive safety

```text
If safe, deactivate WooCommerce on staging.
Expected: reports still load.
Expected: order/currency formatting falls back.
```

### Test 12 — ACF inactive safety

```text
If safe, deactivate ACF on staging.
Expected: reports still load.
Expected: ACF inactive warning appears.
```

### Test 13 — Large-ish dataset

```text
Create or simulate enough redemption rows to test pagination.
Expected: report does not load all rows at once.
Expected: pagination works.
```

### Test 14 — Output escaping

```text
Create a perk title with special characters.
Expected: report displays escaped output safely.
```

### Test 15 — Legacy table warning

```text
If legacy vg_perk_targets or vg_perk_redemptions tables exist, confirm issues report warns without using them for new reports.
Expected: clear admin warning.
```

---

### Test 16 — Dynamic programme/level filters

```text
Add a temporary configured programme/level mapping on staging if safe.
Expected: Programme and level filters are generated from mapping configuration.
Expected: no code change is needed for the filter to appear.
Expected: unknown disabled mappings do not appear as current valid filters.
```

### Test 17 — Mapping drift report

```text
Create or simulate:
- active PMPro level not mapped
- Woo subscription product that grants an unmapped PMPro level
- redemption row with unknown programme_key
- audience index row with disabled/unknown programme_key

Expected:
Mapping Drift / Issues report shows clear warnings.
Expected:
No automatic repair happens.
```

### Test 18 — Programme mapping hash reporting

```text
Create or inspect redemption rows with programme_mapping_hash.
Change mapping configuration on staging if safe.
Expected:
Reports can show rows created under an older mapping hash.
Expected:
Historical rows are not rewritten.
```

### Test 19 — Future billing variation reporting

```text
Add a temporary Woo subscription variation that grants an existing mapped PMPro level.
Expected:
Reports/Billing audit treat it as an additional billing surface, not a new perk level.
Expected:
No report-code change is needed.
```

## 30. Acceptance Criteria

VS-011 is complete only when:

```text
1. Reports admin page exists and is protected by manage_vg_perks.
2. Dashboard summary cards show correct high-level counts.
3. Perk Status report lists and filters perks.
4. Redemption report lists and filters redemption rows.
5. Performance report shows useful aggregate usage metrics.
6. Programme report shows configured programme buckets, including launch Vriendinneklub, launch Voelgoednetwerk, shared/mixed, Unknown, and Legacy buckets.
7. Programme/level filters are generated from ProgrammeRegistry / ProgrammeMappingRepository, not hardcoded launch lists.
8. MembershipProfile report or resolver audit exists without unbounded all-user scanning.
9. Mapping Drift report or Issues section detects PMPro/Woo/mapping mismatches.
10. Misconfiguration report detects common setup issues.
11. Historical rows with unknown programme_key/level_key display as Unknown / Legacy instead of being silently remapped.
12. CSV export works for at least redemption, perk status, programme, and issues reports.
13. Reports are paginated where row counts can grow.
14. Reports do not load on unrelated frontend/admin pages.
15. Reports do not fatal if Woo/Masteriyo/Tickera/ACF/PMPro are inactive.
16. All output is escaped and all filters are sanitised.
17. README documents report pages, filters, CSV exports, programme reporting, mapping drift reporting, and limitations.
18. No pricing, claim, course, ticket, membership, or hub execution logic is changed.
```

---

## 31. README Update Requirements

Update README with:

```text
VS-011 implemented features
Reports menu location
Dashboard cards explained
Perk Status report explained
Redemption report explained
Performance report explained
Programme report explained
Programme Mapping Drift report explained
MembershipProfile report explained
Issues report explained
Unknown / Legacy bucket behaviour
CSV export instructions
Required capability
Known limitations
Performance notes
Next slice: VS-012 — Admin UX Polish and Bulk Management
```

Include this warning:

```text
VS-011 reports are operational/admin reports. They are not formal accounting reports and should not be treated as audited financial statements.
```

Include this note:

```text
Reports are read-only by default. Repair actions and bulk management belong to a later slice.
```

Include this note about historical/legacy data:

```text
Older rows without programme_key, level_key, qualified_path, or eligibility_hash should be reported as Unknown / legacy rather than silently assigned to a programme.
```

---

## 32. Explicit Out-of-Scope List

Do not implement these in VS-011:

```text
New perk execution logic
Discount changes
Claim rule changes
Course enrolment changes
Ticket generation changes
Email notification campaigns
Member-facing dashboards
Charts requiring heavy JS libraries
Automated repair actions
Bulk archive/delete/pause actions
Formal accounting exports
Persistent BI warehouse integration
Recurring subscription revenue analysis
Cloudflare/LiteSpeed cache rules
```

These belong to later slices if needed.

---

## 33. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Admin pages/tabs added
Report queries implemented
CSV exports implemented
Capabilities checked
Programme reporting behaviour
Dynamic programme/level filter behaviour
Programme mapping drift reporting behaviour
MembershipProfile reporting behaviour
Legacy/unknown data handling
Programme mapping hash handling
Manual tests performed
Performance notes
Known limitations
Any assumptions made
Any dependency issues
```

If any report depends on VS-009 or VS-010 and those slices are not implemented, state that clearly.

Do not claim course/ticket-specific reporting if no course/ticket redemptions were available to test.

Do not claim financial/accounting accuracy for discount totals.

---

## 34. Next Slice

After VS-011 is complete and tested, proceed to:

```text
VS-012 — Admin UX Polish and Bulk Management
```

VS-012 will improve admin workflows such as duplication, bulk pause/archive, improved warnings, campaign views, and safer management tools.

Do not start VS-012 during this slice.
