# FEATURE PLANNING PACK: VS-012 — Admin UX Polish and Bulk Management v1.2.0

## 0. Patch Status

This is the hardened replacement for:

```text
VS-012_admin-ux-polish-and-bulk-management.md
```

This version patches the old document for the corrected architecture:

```text
VS-007 = Claims and Redemption Ledger
VS-008 = WooCommerce Product Discounts
```

It also updates the admin layer for the dual-programme membership model:

```text
Launch/default programme 1: vriendinneklub
Launch/default programme 2: voelgoednetwerk
A user may belong to none, one, or both launch programmes.
A user should normally have one active level per configured programme.
```

### v1.2.0 Programme and Level Flexibility Pass Summary

This version applies the programme/level flexibility pass defined by `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a closed forever-list.
2. Requires admin columns, badges, filters, and tools to read configured programmes from ProgrammeRegistry / SettingsRepository.
3. Requires admin level labels and filters to read configured level definitions from ProgrammeMappingRepository.
4. Adds stronger ProgrammeMappingInspector requirements for aliases, unmapped PMPro levels, duplicate mappings, Woo billing products, and mapping drift.
5. Adds a safe admin action to refresh the programme mapping cache/version without rewriting business data.
6. Requires unknown or disabled programme/level values to display as Unknown / Legacy instead of being silently remapped.
7. Adds future-level, future-programme, alias, and mapping-cache tests.
8. Preserves this slice as admin workflow only: no frontend eligibility, pricing, claim, course, ticket, notification, or subscription-renewal behaviour may change.
```

This slice remains an admin workflow slice only.

It must not change frontend eligibility, pricing, claims, course access, ticket generation, notification behaviour, or subscription renewal behaviour.

---

## 1. Objective

Build the admin workflow polish and bulk management layer for the **Voelgoed Member Perks** plugin.

This vertical slice must make the plugin easier and safer for management to use weekly/monthly without requiring a developer to edit perks one-by-one.

The core goal:

```text
Management can create, duplicate, pause, archive, schedule, review, validate, and manage perks efficiently
without changing frontend business logic, discount rules, claim rules, course access, ticket generation, notifications, or reports semantics.
```

This slice improves admin safety and operational speed around the existing perk system.

It is not an execution slice.

---

## 2. Current Baseline Assumptions

This slice assumes the previous slices exist or have been implemented:

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
VS-011 — Reports and Dashboards
```

This slice must still work if VS-009 or VS-010 are not implemented yet.

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected CPT:

```text
vg_perk
```

Expected admin capability:

```text
manage_vg_perks
```

Expected runtime statuses from VS-004:

```text
active
upcoming
expired
paused
archived
misconfigured
```

Expected tables:

```text
{$wpdb->prefix}vgmp_perk_targets
{$wpdb->prefix}vgmp_perk_redemptions
```

Expected default launch programme keys:

```text
vriendinneklub
voelgoednetwerk
```

These are launch defaults only. Admin UX must read enabled configured programmes and levels from:

```text
ProgrammeRegistry
SettingsRepository
ProgrammeMappingRepository
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Future configured programmes and levels must be visible in admin tools without new hardcoded PHP branches. Unknown/unmapped values must fail closed in execution paths and display as Unknown / Legacy in admin/reporting views.

Expected resolver:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
```

Expected index/repository responsibilities:

```text
Target and audience index from VS-006
Redemption ledger from VS-007
Woo discounts from VS-008
Reports from VS-011
```

If core slices VS-001 to VS-008 are missing, stop and report what is missing before implementing this slice.

This slice must also align with:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

---

## 3. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Admin list improvements
Programme-aware admin columns
Programme-aware filters
Quick actions
Bulk pause/archive/resume
Duplicate perk
Campaign management helpers
Admin validation summaries
Safer status editing
Calendar-style schedule overview if lightweight
Admin notices
Tools page improvements
Target/audience reindex helpers
Runtime status repair helpers
Orphaned data inspectors
Legacy table inspectors
Role/capability review tools
Programme mapping visibility tools
```

This slice must not implement:

```text
New pricing rules
New claim rules
New course enrolment logic
New ticket generation logic
Email notification campaigns
Frontend hub redesign
Analytics/BI beyond existing VS-011 reports
Automatic destructive cleanup
Deleting historical redemptions
Changing existing perk semantics silently
Changing PMPro memberships
Changing Woo Subscriptions renewals
Changing Tickera ticket generation
Changing Masteriyo enrolment behaviour
```

This is an admin workflow slice only.

Reports and tools may inspect state, repair indexes/statuses, and warn about risks.

They must not change business rules.

---

## 4. Required Deliverables

Add the following plugin files:

```text
includes/
├── Admin/
│   ├── AdminUx.php
│   ├── PerkQuickActions.php
│   ├── PerkBulkActions.php
│   ├── PerkDuplicator.php
│   ├── PerkAdminColumns.php
│   ├── PerkAdminFilters.php
│   ├── PerkEditNotices.php
│   ├── PerkValidationSummary.php
│   ├── CampaignAdminHelper.php
│   ├── ProgrammeAdminHelper.php
│   ├── AdminToolsPage.php
│   ├── AdminAssets.php
│   └── CapabilityInspector.php
│
├── Tools/
│   ├── TargetAudienceReindexTool.php
│   ├── RuntimeStatusRepairTool.php
│   ├── OrphanedDataInspector.php
│   ├── LegacyDataInspector.php
│   ├── ProgrammeMappingInspector.php
│   └── PerkHealthCheckTool.php
│
└── Support/
    └── AdminUrl.php
```

Update existing files:

```text
includes/Plugin.php
README.md
```

Optional assets:

```text
assets/css/admin-ux.css
assets/js/admin-ux.js
```

Keep JavaScript minimal and progressive. Admin tools must remain usable without heavy JS.

If old classes from earlier attempts exist, do not duplicate them. Consolidate the responsibility and document the final class name in the agent report.

---

## 5. Admin UX Principles

Follow these principles:

```text
1. Make the safe action easy.
2. Make dangerous actions obvious.
3. Do not delete historical data casually.
4. Prefer pause/archive over delete.
5. Keep expired perks for reporting.
6. Let management duplicate and schedule instead of rebuilding from scratch.
7. Validate and warn before a perk goes live.
8. Never hide a serious misconfiguration behind a pretty interface.
9. Show programme/audience context clearly.
10. Do not let dual-programme complexity become invisible.
11. Do not silently stack or duplicate benefits.
12. Do not pretend a repair action is safe if it changes business behaviour.
```

---

## 6. Programme-Aware Admin Model

All admin views must understand that a perk may target configured programmes, including but not limited to the launch programmes:

```text
Vriendinneklub only
Voelgoednetwerk only
both launch programmes
one or more future configured programmes
all active mapped members
specific configured programme levels
manual/custom audience rules
```

All user/profile tools must understand that a member may be:

```text
no active mapped programme
one configured programme only
more than one configured programme
both launch programmes
same-programme conflict
unmapped PMPro level
unknown/legacy programme or level data
```

Do not use the old single-column concept:

```text
membership_group
```

Use programme-aware labels and stored context:

```text
programme_key
level_key
qualified_path
claim_scope
eligibility_hash
programme_mapping_hash
```

For display, convert these to friendly labels through ProgrammeRegistry / ProgrammeMappingRepository:

```text
Voelgoed Vriendinneklub — Goud
Voelgoednetwerk — Silwer
Shared benefit
All mapped paid members
Unknown / legacy: {raw stored value}
```

Rules:

```text
1. Admin labels must be generated from configured programme/level labels where possible.
2. Launch labels may be seeded as defaults, but must not be hardcoded as the only valid display values.
3. Unknown programme_key or level_key values must show as Unknown / Legacy with the stored raw value.
4. Aliases may normalize only when explicitly configured.
5. Admin UX must not silently rewrite legacy values without an approved migration plan.
6. Admin UX must not make PMPro membership changes automatically.
```

## 6A. Programme Mapping Admin Boundary

Admin UX may display, inspect, validate, and refresh programme mapping state.

It must not become the runtime membership authority.

Correct model:

```text
SettingsRepository
↓
ProgrammeRegistry
↓
ProgrammeMappingRepository
↓
ProgrammeDefinition / ProgrammeLevelDefinition
↓
Admin columns, filters, badges, inspectors, warnings
```

Incorrect model:

```text
Hardcode Goud/Silwer/Brons in admin columns
Hardcode Brons/Silwer/Goud in admin filters
Hardcode PMPro level IDs in quick actions
Hardcode Woo subscription product IDs as membership authority
Auto-discover every PMPro level as a valid perk level
Auto-repair PMPro memberships from admin display tools
```

Admin UX must support:

```text
1. Launch default programmes.
2. Future enabled configured programmes.
3. Future levels inside any configured programme.
4. Programme aliases and level aliases for compatibility.
5. Mapping drift warnings.
6. Unknown / Legacy buckets for historical rows.
7. Safe mapping-cache refresh without destructive rewrites.
```

---

## 7. Admin List Columns

Create or update:

```php
Voelgoed\MemberPerks\Admin\PerkAdminColumns
```

Improve the `vg_perk` list table.

Required columns:

```text
Title
Runtime Status
Programme / Audience
Programme Levels
Type
Section
Campaign
Start Date
End Date
Targets
Claim Mode
Claim Scope
Claims
Priority
Health
```

Column behaviour:

### Runtime Status

Show badges for:

```text
active
upcoming
expired
paused
archived
misconfigured
```

`misconfigured` must be visually distinct from `paused`.

Reason:

```text
Paused = intentional admin override.
Misconfigured = broken/incomplete data that should not become active.
```

### Programme / Audience

Show a compact summary:

```text
Vriendinneklub
Voelgoednetwerk
Shared
All mapped members
Custom audience
None configured
```

Do not rely on `vg_perk_level_group`.

Use ACF audience rules and the VS-006 audience index.

### Programme Levels

Examples:

```text
Vriendinneklub: Goud
Vriendinneklub: Goud, Silwer
Voelgoednetwerk: Silwer
Shared: Vriendinne Goud + Netwerk Silwer
All levels
```

### Targets

Show compact count and target types:

```text
2 products
1 course
1 ticket
1 page
```

Use:

```text
{$wpdb->prefix}vgmp_perk_targets
index_scope = benefit_target
```

Do not parse ACF relationship fields per row.

### Claims

Show total successful claims from:

```text
{$wpdb->prefix}vgmp_perk_redemptions
```

Successful statuses:

```text
claimed
completed
```

If the redemption table does not exist:

```text
—
```

### Health

Show:

```text
OK
Notice
Warning
Critical
```

Use `PerkValidationSummary`.

Do not duplicate validation logic directly in the column renderer.

---

## 8. Admin Filters

Create:

```php
Voelgoed\MemberPerks\Admin\PerkAdminFilters
```

Add filters to the `vg_perk` list:

```text
Runtime status
Programme
Programme level
Audience type
Perk type
Section
Campaign
Claim mode
Claim scope
Has targets / no targets
Has claims / no claims
Expiring soon
Health severity
```

Rules:

```text
All filter inputs must be sanitized.
Filters must not break normal post list behaviour.
Filters should use tax_query/meta_query sparingly and clearly.
Target/audience filters should use the VS-006 index where practical.
```

For performance:

```text
Prefer taxonomies for status/type/section/campaign.
Prefer vgmp_perk_targets for target/audience filters.
Avoid reverse ACF relationship LIKE queries.
Avoid scanning all perks in PHP for normal admin list filters.
```

Legacy filter aliases may be accepted for admin convenience but must be normalized:

```text
program → programme
level_group → programme_level / audience rule
```

---

## 9. Quick Actions

Create:

```php
Voelgoed\MemberPerks\Admin\PerkQuickActions
```

Add row actions for `vg_perk` list rows:

```text
Duplicate
Pause
Resume
Archive
View Report
Reindex Target/Audience Index
Repair Runtime Status
Preview Hub Card
```

Rules:

```text
Check manage_vg_perks.
Use nonce.
Use post type checks.
Redirect back with admin notice.
Do not delete data.
Do not alter redemptions.
Do not alter Woo orders.
Do not alter Masteriyo enrolments.
Do not alter Tickera tickets.
```

### Duplicate

Creates a new draft copy of the perk.

### Pause

Sets manual status to:

```text
paused
```

Then runs VS-004 runtime status update for that perk.

### Resume

Sets manual status to:

```text
auto
```

Then recalculates runtime status through VS-004.

### Archive

Sets manual status to:

```text
archived
```

Then updates runtime status through VS-004.

### View Report

Links to VS-011 report filtered by this perk.

### Reindex Target/Audience Index

Runs VS-006 indexer for this perk only.

### Repair Runtime Status

Runs VS-004 updater for this perk only.

### Preview Hub Card

Shows an admin-safe card preview if implemented; otherwise links to edit screen with a preview notice.

Preview must not execute claims, cart actions, enrolments, ticket claims, or notifications.

---

## 10. Bulk Actions

Create:

```php
Voelgoed\MemberPerks\Admin\PerkBulkActions
```

Add bulk actions:

```text
Pause selected perks
Resume selected perks
Archive selected perks
Reindex target/audience index
Repair runtime status
Duplicate selected perks as drafts
Assign campaign
Assign programme/audience draft template
```

Security:

```text
Check manage_vg_perks.
Verify bulk nonce.
Sanitize post IDs.
Confirm post type is vg_perk.
Report partial failures.
```

Rules:

```text
Bulk archive must not delete perks.
Bulk duplicate must create drafts.
Bulk assign campaign must not remove existing campaign terms unless explicitly selected.
Bulk programme/audience assignment must not execute pricing/claims.
Bulk actions must not affect non-vg_perk posts.
```

If “Assign campaign” or “Assign programme/audience template” requires an extra UI step, implement a simple confirmation screen or defer with a README note.

Do not silently overwrite complex audience rules across multiple perks without confirmation.

---

## 11. Perk Duplicator

Create:

```php
Voelgoed\MemberPerks\Admin\PerkDuplicator
```

Responsibilities:

```text
1. Duplicate post title/content/excerpt/thumbnail/menu order.
2. Duplicate taxonomies.
3. Duplicate ACF fields/meta that represent configuration.
4. Duplicate featured image reference.
5. Reset runtime status through safe draft/manual behaviour.
6. Reset claim/redemption history.
7. Reindex target/audience rows for the new perk.
8. Preserve programme/audience configuration unless the admin chooses a template/change.
9. Preserve target configuration unless the admin changes it.
10. Reset any runtime-only or ledger-only metadata.
```

New duplicated post should be:

```text
post_status = draft
```

New title:

```text
Copy of {Original Title}
```

Important:

```text
Copy configuration, not history.
```

Do not copy:

```text
redemption records
reservation records
order IDs
order item IDs
ticket IDs
course enrolment records
runtime debug timestamps
claim counts
notification queue records
generated report/cache metadata
```

May copy:

```text
schedule fields
benefit fields
target fields
audience fields
display fields
campaign/section/type taxonomies
featured image reference
```

After duplication:

```text
1. Reindex target/audience rows for the new draft.
2. Recalculate runtime status.
3. Show a warning if copied dates are already expired.
4. Show a warning if copied free/ticket/course perk has claim limits that should be reviewed.
```

---

## 12. Perk Edit Notices

Create:

```php
Voelgoed\MemberPerks\Admin\PerkEditNotices
```

Show contextual warnings on the perk edit screen.

Examples:

```text
This perk is active but has no audience rules.
This perk is active but has no target.
This perk is misconfigured and will not show/apply.
This free ticket perk has no per-user claim limit.
This free product perk has no per-user claim limit.
This perk expired on {date}.
This perk is paused and will not appear in the hub.
This perk is archived and hidden from normal operations.
Target/audience index appears stale. Reindex recommended.
This perk is shared across both programmes.
This perk allows per-programme claims. Dual members may be able to claim more than once.
This perk has stacking enabled. Confirm this is intentional.
This perk targets a subscription product. Renewal behaviour is not managed by this admin action.
```

Warnings should be clear and practical.

Do not block saving unless a value is dangerously invalid and the lower-level validation already supports blocking.

This slice itself should prefer warnings, not destructive enforcement.

---

## 13. Perk Validation Summary

Create:

```php
Voelgoed\MemberPerks\Admin\PerkValidationSummary
```

Purpose:

```text
Produce reusable health checks for one perk.
```

Return structure:

```php
[
    "severity" => "ok|notice|warning|critical",
    "issues"   => [
        [
            "code" => "missing_target",
            "severity" => "critical",
            "message" => "This active perk has no target.",
            "suggested_fix" => "Add a product, course, ticket, page, or external URL target.",
        ],
    ],
]
```

Use this in:

```text
Admin edit notices
Health column
Misconfiguration report from VS-011
Tools health check
Quick action result notices
Bulk action result notices
```

Do not duplicate issue logic in multiple places.

Required issue checks:

```text
missing_start_date
invalid_date_range
manual_paused
manual_archived
runtime_misconfigured
missing_audience_rules
missing_programme_mapping
unknown_programme_key
unknown_level_key
shared_perk_no_claim_scope
per_programme_claim_scope_on_single-programme perk
free_product_no_claim_limit
free_ticket_no_claim_limit
discount_value_invalid
percentage_over_100
target_index_stale
audience_index_stale
missing_target
missing_woo_product_target
missing_course_target
missing_ticket_target
woo_inactive_for_woo_perk
masteriyo_inactive_for_course_perk
tickera_inactive_for_ticket_perk
woo_bridge_unverified_for_ticket_perk
legacy_table_detected
subscription_target_requires_review
programme_alias_collision
level_alias_collision
duplicate_pmpro_level_mapping
unmapped_pmpro_level_detected
woo_product_grants_unmapped_pmpro_level
programme_mapping_hash_stale
unknown_legacy_programme_value
unknown_legacy_level_value
```

If VS-009 or VS-010 are absent, skip their detector-specific checks safely.

---

## 14. Campaign Admin Helper

Create:

```php
Voelgoed\MemberPerks\Admin\CampaignAdminHelper
```

Purpose:

```text
Make weekly/monthly campaign management easier.
```

Possible features:

```text
Show campaign term on perk list.
Add quick link to filter by campaign.
Add campaign summary box.
Allow duplicate perk into new campaign.
Allow duplicate selected campaign perks into a new campaign.
Allow schedule shift when duplicating campaign perks.
```

Minimum VS-012 implementation:

```text
Campaign filter and campaign-aware duplicate helper.
```

Optional but useful:

```text
Duplicate selected perks into a new campaign as drafts.
```

Example use case:

```text
Duplicate all June Week 1 perks into June Week 2 drafts.
Then management changes dates/targets/audience rules.
```

Rules:

```text
Duplicated campaign perks must be drafts.
Redemption history must not be copied.
Target/audience index must be rebuilt for new perk IDs.
Admin must confirm date shifting if implemented.
```

Do not create a full campaign calendar app in this slice.

---

## 15. Programme Admin Helper

Create:

```php
Voelgoed\MemberPerks\Admin\ProgrammeAdminHelper
```

Purpose:

```text
Make configured programme/level setup visible and less error-prone.
```

Launch examples:

```text
Vriendinneklub
Voelgoednetwerk
```

But this helper must work from configured programme definitions, not hardcoded launch-only branches.

Features:

```text
Show configured programme keys.
Show configured programme labels.
Show programme enabled/disabled state.
Show programme aliases.
Show configured programme levels.
Show configured level aliases.
Show PMPro level IDs mapped to each programme level.
Show levels with no PMPro IDs.
Show PMPro IDs mapped to more than one enabled level.
Show PMPro levels that exist but are not mapped.
Show Woo subscription products/variations that grant mapped PMPro levels.
Show Woo subscription products/variations that grant unmapped PMPro levels.
Show mapped PMPro level IDs that no longer exist.
Show stored programme_mapping_hash / mapping version.
Show users with more than one active level in the same programme if this can be queried safely.
Show counts for launch programme cohorts and future configured programme cohorts if safe and not expensive.
```

Cohort labels should be dynamic:

```text
{Programme Label} only
Multiple configured programmes
Both launch programmes
Unknown / legacy
Same-programme conflict
Unmapped PMPro level
```

This helper is visibility-only.

It must not change PMPro membership assignments.

It must not auto-fix membership conflicts.

It must not treat every PMPro level as a valid perk level automatically.

If all-user scanning would be expensive:

```text
Provide a manual tool with pagination/batching.
Do not run it on every admin page.
```

---

## 15A. Programme Mapping Inspector

Create or update:

```php
Voelgoed\MemberPerks\Tools\ProgrammeMappingInspector
```

Purpose:

```text
Expose configured programme/level mapping health before management relies on perks, claims, Woo discounts, courses, tickets, reports, or notifications.
```

The inspector must show:

```text
1. Enabled programmes.
2. Disabled programmes.
3. Programme aliases.
4. Programme hub and upsell page configuration.
5. Levels per programme.
6. Level aliases.
7. Level ranks.
8. PMPro level IDs per level.
9. PMPro levels currently active but unmapped.
10. Duplicate PMPro level IDs across enabled mappings.
11. Woo subscription products/variations that grant each PMPro level where detectable.
12. Woo billing products that grant unmapped PMPro levels.
13. Audience index rows that reference unknown/disabled programme keys.
14. Redemption rows that reference unknown/legacy programme_key or level_key values.
15. Current programme_mapping_hash / mapping version.
```

Output buckets:

```text
OK
Notice
Warning
Critical
Unknown / Legacy
```

Rules:

```text
1. The inspector may suggest fixes.
2. The inspector must not automatically rewrite mappings.
3. The inspector must not change PMPro levels.
4. The inspector must not change Woo products or variations.
5. The inspector must not silently remap old stored values without explicit alias/migration rules.
```

## 15B. Programme Mapping Cache Tool

Create:

```php
Voelgoed\MemberPerks\Tools\ProgrammeMappingCacheTool
```

Purpose:

```text
Allow administrators to refresh/bump programme mapping cache/version after programme, level, alias, PMPro mapping, or hub-setting changes.
```

Safe action:

```text
Refresh programme mapping cache
```

Expected behaviour:

```text
1. Check manage_vg_perks.
2. Verify nonce.
3. Rebuild normalized programme/level mapping from SettingsRepository.
4. Validate aliases and PMPro mappings.
5. Compute a new deterministic programme_mapping_hash if inputs changed.
6. Bump mapping cache version where applicable.
7. Clear membership profile, audience, hub, report, and admin-list cache groups where implemented.
8. Do not rewrite historical redemption rows.
9. Do not rewrite target/audience index rows directly; instead flag affected perks for reindex or instruct admin to run TargetAudienceReindexTool.
```

This tool is a cache/version tool, not a migration tool.

## 16. Admin Tools Page

Create:

```php
Voelgoed\MemberPerks\Admin\AdminToolsPage
```

Location:

```text
Member Perks > Tools
```

Tools:

```text
Reindex all perk targets and audience rows
Repair runtime statuses
Run perk health check
Inspect orphaned target/audience rows
Inspect orphaned redemption rows
Inspect legacy tables
Inspect programme mappings
Refresh programme mapping cache/version
Inspect configured programme aliases and level aliases
Inspect Woo billing products that grant mapped/unmapped PMPro levels
Inspect capabilities
```

All tools must:

```text
Check manage_vg_perks.
Use nonce.
Show dry-run preview where broad or potentially destructive.
Avoid deleting data unless explicitly confirmed.
Report partial failures.
```

For VS-012, prefer:

```text
Inspect/report
Repair safe indexes/statuses
Do not delete historical rows automatically
```

---

## 17. Target and Audience Reindex Tool

If VS-006 already has a reindex tool, consolidate or improve it.

Create/update:

```php
Voelgoed\MemberPerks\Tools\TargetAudienceReindexTool
```

Modes:

```text
Single perk reindex
All perks reindex
Dry run if practical
```

Output:

```text
Perks scanned
Benefit target rows deleted
Benefit target rows inserted
Audience target rows deleted
Audience target rows inserted
Invalid targets skipped
Invalid programme/level values skipped
Errors
```

Do not run all-perk reindex automatically on every admin page.

Use VS-006 indexer services.

Do not duplicate target/audience extraction logic.

---

## 18. Runtime Status Repair Tool

Create:

```php
Voelgoed\MemberPerks\Tools\RuntimeStatusRepairTool
```

Purpose:

```text
Recalculate runtime status taxonomy for perks.
```

Modes:

```text
Single perk
All perks
Dry run
```

Output:

```text
Perks scanned
Statuses changed
Already correct
Misconfigured found
Errors
```

Use the VS-004 runtime status updater.

Do not duplicate date/status logic.

The tool must recognise:

```text
misconfigured
```

as a distinct runtime status.

---

## 19. Orphaned Data Inspector

Create:

```php
Voelgoed\MemberPerks\Tools\OrphanedDataInspector
```

Purpose:

```text
Detect data that references missing posts/users/orders/targets.
```

Check:

```text
Target rows where perk_id no longer exists.
Target rows where target_id no longer exists.
Audience rows where programme_key or level_key is no longer configured.
Audience rows where programme_mapping_hash is stale if that metadata exists.
Redemptions where perk_id no longer exists.
Redemptions where user_id no longer exists.
Redemptions where order_id no longer exists.
Redemptions where programme_key is unknown.
Redemptions where level_key is unknown for the programme.
Redemptions where programme_mapping_hash differs from the current mapping hash.
Woo billing products/variations that grant unmapped PMPro levels where detectable.
```

For VS-012:

```text
Inspect and report only.
Do not delete automatically.
```

Future cleanup actions can be a separate maintenance slice.

---

## 20. Legacy Data Inspector

Create:

```php
Voelgoed\MemberPerks\Tools\LegacyDataInspector
```

Purpose:

```text
Detect stale objects from earlier or abandoned plugin iterations.
```

Check for:

```text
{$wpdb->prefix}vg_perk_targets
{$wpdb->prefix}vg_perk_redemptions
old membership_group columns
old vg_perk_level_group taxonomy use
old ACF fields:
- vgmp_hub_page_id
- vgmp_level_group_mappings
old order item meta:
- _vgmp_membership_group
```

Rules:

```text
Do not delete legacy data.
Do not migrate automatically in VS-012 unless a migration was explicitly planned and tested.
Show safe report and recommended next action.
```

If legacy data exists, the admin notice should be calm but clear:

```text
Legacy Member Perks data was detected. Do not delete it manually until a migration/cleanup plan has been approved.
```

---

## 21. Perk Health Check Tool

Create:

```php
Voelgoed\MemberPerks\Tools\PerkHealthCheckTool
```

Purpose:

```text
Run validation summaries across all perks.
```

Output grouping:

```text
Critical
Warnings
Notices
OK count
```

Each issue should link to the affected perk edit screen.

Use `PerkValidationSummary`.

Do not run full health check automatically on every admin page.

---

## 22. Capability Inspector

Create:

```php
Voelgoed\MemberPerks\Admin\CapabilityInspector
```

Purpose:

```text
Show which roles have Voelgoed Member Perks capabilities.
```

Display:

```text
Administrator
Shop Manager
Editor
Custom roles
```

Capabilities:

```text
manage_vg_perks
edit_vg_perks
edit_others_vg_perks
publish_vg_perks
delete_vg_perks
delete_others_vg_perks
read_private_vg_perks
```

Do not automatically grant capabilities to new roles in this slice unless explicitly approved.

This tool is for visibility.

If a capability is missing from Administrator, show it as a critical issue.

If Shop Manager should be able to manage perks but is not configured, show a notice only. Do not change automatically.

---

## 23. Admin Assets

Create:

```php
Voelgoed\MemberPerks\Admin\AdminAssets
```

Load assets only on:

```text
vg_perk edit/list screens
Member Perks report/tool/settings pages
```

Do not load globally across wp-admin.

CSS may style:

```text
Status badges
Programme badges
Audience badges
Claim scope badges
Severity badges
Health summary
Tool result boxes
Admin notices
```

Keep CSS lightweight.

Do not rely only on colour. Include visible text labels.

---

## 24. Status Badge Standards

Use consistent badges:

```text
Active         = positive label
Upcoming       = scheduled/future label
Expired        = muted label
Paused         = warning label
Archived       = muted/admin-only label
Misconfigured  = critical label
Critical       = critical label
Warning        = warning label
Notice         = notice label
OK             = positive label
```

Do not rely only on colour.

Do not treat `paused` and `misconfigured` as the same thing.

---

## 25. Admin Notices

After quick/bulk/tool actions, show dismissible admin notices:

```text
3 perks paused.
1 perk duplicated as draft.
Targets and audience rows reindexed for 12 perks.
Runtime status repaired for 8 perks.
Health check found 2 critical issues.
Programme mapping inspector found 1 same-programme conflict.
```

If errors occur:

```text
Clearly list failed perk IDs/titles where practical.
```

Do not show raw SQL errors to normal admins. Log technical details separately.

---

## 26. Data Integrity Rules

Required:

```text
1. Quick/bulk actions must not affect non-vg_perk posts.
2. Duplicate perks must not duplicate redemptions.
3. Duplicate perks must not duplicate runtime-only records.
4. Archive/pause must preserve history.
5. Tools must not delete historical data automatically.
6. Reindex tools must rebuild from source ACF fields.
7. Runtime repair must use existing status logic.
8. Admin actions must be nonce-protected.
9. Capabilities must be checked.
10. Bulk actions must report partial failures.
11. Existing execution logic must not change.
12. Programme/audience warnings must not rewrite PMPro membership state.
13. Legacy data inspectors must not delete or migrate automatically.
14. Programme mapping cache refresh must not rewrite historical claims or PMPro membership assignments.
15. Admin displays must show Unknown / Legacy for unmapped stored values instead of silently guessing.
```

---

## 27. Performance Requirements

Admin UX must not slow down admin lists.

Rules:

```text
1. Do not run full health checks on every row if too expensive.
2. Cache or simplify health status for admin list if needed.
3. Do not run all-perk reindex automatically.
4. Do not query redemptions table once per row if avoidable.
5. Bulk-prefetch counts for visible rows where practical.
6. Load admin assets only on plugin screens.
7. Do not scan all users for membership profile summaries on every admin page.
8. Programme mapping/user conflict scans must be manual or batched.
9. Do not perform heavy Tickera/Masteriyo detection per row.
```

MVP acceptable:

```text
If row count is small, simple queries are acceptable but document hardening path.
```

---

## 28. Security Requirements

All admin actions must:

```text
Require manage_vg_perks.
Use nonces.
Sanitize request values.
Validate post type.
Escape all output.
Use prepared SQL where SQL is needed.
Avoid exposing private notes outside admin.
```

Do not allow quick actions through GET without nonce.

Do not trust post IDs from request.

Do not expose full redemption history or user email data outside admin pages protected by `manage_vg_perks`.

---

## 29. Compatibility Requirements

This slice must not fatal if these are inactive:

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

If a detector/integration class from VS-009 or VS-010 is unavailable:

```text
Skip that health check safely.
Show "not available" rather than fatal.
```

If ACF is inactive:

```text
Show warning.
Do not fatal.
Do not attempt to read undefined fields in a fatal way.
```

If PMPro is inactive:

```text
Programme mapping inspector shows dependency missing.
No membership changes are attempted.
```

---

## 30. Testing Plan

Test on staging only.

### Test 1 — Admin columns

```text
Open Member Perks list.
Expected: new columns display without fatal.
Expected: runtime status, programme/audience, targets, claims, claim scope, and health show correctly.
```

### Test 2 — Admin filters

```text
Filter by active status.
Filter by campaign.
Filter by programme.
Filter by programme level.
Filter by claim scope.
Expected: correct perks displayed.
```

### Test 3 — Duplicate perk

```text
Duplicate an existing perk.
Expected: new draft created.
Expected: taxonomies and ACF configuration copied.
Expected: redemption history not copied.
Expected: target/audience index rebuilt for duplicate.
Expected: old runtime-only metadata not copied.
```

### Test 4 — Pause quick action

```text
Click Pause on active perk.
Expected: manual status paused.
Expected: runtime status paused.
Expected: perk no longer appears in active hub query.
Expected: redemption history preserved.
```

### Test 5 — Resume quick action

```text
Click Resume on paused perk.
Expected: manual status auto.
Expected: runtime status recalculated.
Expected: if invalid dates exist, status becomes misconfigured, not active.
```

### Test 6 — Archive quick action

```text
Click Archive.
Expected: manual status archived.
Expected: runtime status archived.
Expected: history preserved.
```

### Test 7 — Bulk pause/archive

```text
Select multiple perks.
Run bulk pause.
Expected: selected vg_perk posts paused.
Expected: no other post types affected.
Expected: partial failures reported if any.
```

### Test 8 — Reindex target/audience tool

```text
Run single-perk reindex.
Run all-perk reindex.
Expected: target/audience table rebuilt.
Expected: benefit_target and audience_target row counts reported.
Expected: invalid programme/level values reported.
```

### Test 9 — Runtime repair tool

```text
Manually create status mismatch if safe.
Run repair.
Expected: status corrected.
Expected: invalid setup becomes misconfigured.
```

### Test 10 — Health check

```text
Create misconfigured perk:
- active with no target
- active with no audience rules
- free ticket with no claim limit
Expected: health check reports issues.
```

### Test 11 — Programme mapping inspector

```text
Configure Vriendinneklub and Netwerk mappings.
Expected: mappings display.
If same PMPro level ID is mapped twice in one programme, expected warning/critical issue.
```

### Test 12 — Dual-programme visibility in admin

```text
Create perk available to both programmes.
Expected: admin list shows Shared/both-programme audience clearly.
Expected: no duplicate perk rows appear.
```

### Test 13 — Capability inspector

```text
Open capabilities tool.
Expected: roles/capabilities shown.
Expected: no automatic unwanted role changes.
```

### Test 14 — Non-admin access

```text
Log in as non-admin/member.
Attempt admin tool URL.
Expected: access denied.
```

### Test 15 — Dependency safety

```text
If safe, test with ACF, Woo, PMPro, Masteriyo, or Tickera inactive.
Expected: admin pages do not fatal.
Expected: warnings appear where relevant.
```

### Test 16 — Asset loading

```text
Open unrelated admin page.
Expected: plugin admin CSS/JS not loaded.
```

### Test 17 — Future configured level in admin UX

```text
Add a temporary configured level under an existing programme mapping.
Expected: admin filters, badges, validation summaries, and ProgrammeMappingInspector show the new level without code changes.
Expected: no hardcoded Goud/Silwer/Brons or Brons/Silwer/Goud branch is required.
```

### Test 18 — Future configured programme in admin UX

```text
Add a disabled temporary programme with one mapped level.
Expected: admin tools do not fatal.
Expected: disabled programme is visible in ProgrammeMappingInspector.
Expected: normal admin filters do not treat it as enabled unless configured.

Enable the programme on staging.
Expected: configured programme appears in programme filters/badges/tools according to its enabled settings.
```

### Test 19 — Programme alias and legacy value display

```text
Add an explicit programme alias such as old_programme_key → vriendinneklub.
Expected: inspector shows the alias mapping.
Expected: alias does not create a second active programme.
Expected: unknown non-aliased values display as Unknown / Legacy.
```

### Test 20 — Programme mapping cache refresh

```text
Change a programme/level mapping on staging.
Run Refresh programme mapping cache.
Expected: mapping hash/version changes if inputs changed.
Expected: membership/profile/admin display caches are invalidated where implemented.
Expected: no historical redemption rows are rewritten.
Expected: admin is told whether target/audience reindex is recommended.
```

### Test 21 — Woo billing product mapping visibility

```text
Where detectable, inspect Woo variable subscription products and variations that grant PMPro levels.
Expected: products granting mapped PMPro levels are shown as OK.
Expected: products granting unmapped PMPro levels are shown as warning/critical.
Expected: admin tool does not use Woo product IDs as perk authority.
```

---

## 31. Acceptance Criteria

VS-012 is complete only when:

```text
1. Member Perks admin list has useful programme-aware columns and filters.
2. Runtime status includes misconfigured as distinct from paused.
3. Quick actions exist for duplicate, pause, resume, archive, reindex, repair status, and report link.
4. Bulk actions exist for pause, resume, archive, target/audience reindex, and runtime repair.
5. Duplicated perks are drafts and do not copy redemption/order/course/ticket history.
6. Admin edit notices show common configuration problems.
7. PerkValidationSummary centralises health checks.
8. Tools page exists and is protected.
9. Target/audience reindex and runtime repair tools work.
10. Orphaned data inspector reports issues without deleting data.
11. Legacy data inspector reports old table/field/meta usage without deleting data.
12. Programme mapping inspector shows mapping state and conflicts.
13. Capability inspector shows role capability state.
14. Admin actions use nonces and capability checks.
15. Plugin does not fatal if optional dependencies are inactive.
16. No frontend behaviour or execution rules are changed.
17. Admin programme/level labels and filters are generated from configured ProgrammeRegistry / ProgrammeMappingRepository output where available.
18. Unknown/legacy programme and level values are shown clearly instead of silently remapped.
19. ProgrammeMappingInspector shows aliases, mapped PMPro levels, unmapped PMPro levels, duplicate mappings, and Woo billing-product mapping drift where detectable.
20. Programme mapping cache refresh exists or is explicitly documented as deferred with safe manual instructions.
21. Future configured levels and programmes can appear in admin tools without hardcoded execution-path changes.
22. README documents admin workflows, tools, safety rules, and limitations.
```

---

## 32. README Update Requirements

Update README with:

```text
VS-012 implemented features
Admin list columns
Admin filters
Quick actions
Bulk actions
Perk duplication rules
Pause/resume/archive behaviour
Programme/audience admin labels
Tools page instructions
Health check rules
Target/audience reindex tool
Runtime repair tool
Orphaned data inspector
Legacy data inspector
Programme mapping inspector
Programme mapping cache refresh tool
Future level/programme admin workflow
Unknown / Legacy display rules
Capability inspector
Known limitations
Next slice: VS-013 — Performance and Cache Hardening
```

Include this warning:

```text
VS-012 does not delete historical perks, redemptions, orders, course access, or ticket records. It focuses on safe admin workflow improvements.
```

Include this note:

```text
Pause and archive affect perk availability but preserve history for reporting.
```

Include this note:

```text
Programme mapping tools are visibility tools. They do not change PMPro membership assignments automatically.
```

---

## 33. Explicit Out-of-Scope List

Do not implement these in VS-012:

```text
New discount rules
New claim rules
New course enrolment rules
New ticket generation rules
Email notifications
Member-facing frontend changes
Advanced BI dashboards
Destructive cleanup tools
Automatic migration of legacy data
Automatic PMPro membership repair
Formal accounting exports
Persistent cache strategy
Cloudflare/LiteSpeed rules
```

These belong to later slices if needed.

---

## 34. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Admin columns added
Filters added
Quick actions added
Bulk actions added
Tools added
Health checks added
Programme/audience admin support added
Programme mapping inspector and cache refresh support added
Unknown / Legacy handling added
Legacy inspectors added
Security checks implemented
Manual tests performed
Known limitations
Any assumptions made
Any dependency issues
```

If any quick/bulk/tool action could not be safely implemented because of existing plugin structure, report that clearly and do not fake it.

---

## 35. Next Slice

After VS-012 is complete and tested, proceed to:

```text
VS-013 — Performance and Cache Hardening
```

VS-013 will define caching, invalidation, query profiling, logged-in hub cache policy, LiteSpeed exclusions, and Cloudflare considerations.

Do not start VS-013 during this slice.
