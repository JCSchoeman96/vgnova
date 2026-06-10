# FEATURE PLANNING PACK: VS-004 — Runtime Status Automation

Version: **v1.2.0**  
Previous version: **v1.1.0**  
Supersedes: `VS-004_runtime-status-automation_v1.1.0.md`  
Plugin target version after this slice: **0.4.0**  
Patch pass: **Programme and Level Flexibility Pass**

---

## 0. v1.2.0 Patch Summary — Programme Flexibility Alignment

This version applies the controlled programme/level flexibility doctrine from `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md` and the programme flexibility patchlist.

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

VS-004 remains intentionally light because runtime status is mostly independent from membership structure. The important correction is this:

```text
Adding a new programme, level, billing term, or PMPro mapping must not require runtime-status code changes.
```

Changes in this version:

```text
1. Clarifies that runtime status is independent of configured programme/level count.
2. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes where examples are needed, not runtime-status dependencies.
3. Adds `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md` as a reference dependency.
4. Requires configuration-health checks, where available, to use PerkConfigRepository / validation summaries rather than hardcoded programme lists.
5. Adds `invalid_audience_configuration`, `unknown_programme_reference`, `unknown_level_reference`, and `programme_mapping_unavailable` as safe misconfiguration reasons.
6. Adds tests proving future programmes/levels do not affect lifecycle status logic.
7. Adds acceptance criteria that runtime status code does not hardcode PMPro IDs, Woo product IDs, or fixed level names.
```

---

## 1. Objective

Build the runtime lifecycle automation layer for the **Voelgoed Member Perks** plugin.

This vertical slice must make each `vg_perk` post resolve into one deterministic runtime status based on its schedule, manual override, WordPress post state, and configuration health.

Runtime statuses:

```text
upcoming
active
expired
paused
archived
misconfigured
```

The resolved status must be stored as a term in:

```text
vg_perk_runtime_status
```

This slice directly supports the business requirement:

```text
Management can create perks for this week, next week, or several weeks ahead.
Upcoming perks can be prepared safely.
Active perks can show/apply later.
Expired perks stop showing/applying automatically.
Long-running perks remain active if no end date is configured.
Broken or incomplete perks fail closed as misconfigured.
```

This slice must **not** build frontend hub queries, Bricks/Etch integration, WooCommerce discounts, claims, Masteriyo access, Tickera access, emails, reports, or notification logic.

---

## 2. Core Architectural Clarification

Runtime status answers this question:

```text
Is this perk currently lifecycle-active, upcoming, expired, paused, archived, or misconfigured?
```

Runtime status does **not** answer this question:

```text
Is this user eligible for this perk?
```

User eligibility belongs to:

```text
VS-003 — PMPro Membership Profile Resolver
VS-005 — Hub display/query eligibility
VS-006 — Target index
VS-007 — Claims and Redemption Ledger
VS-008 — WooCommerce Product Discounts
VS-009 — Masteriyo Course Perks
VS-010 — Tickera Ticket Perks
```

This distinction is non-negotiable.

A perk can be:

```text
runtime status = active
```

while only being eligible to:

```text
Vriendinneklub Goud
Voelgoednetwerk Silwer
or both
```

Runtime status is global to the perk. Eligibility is user/programme-specific.

---

## 3. Existing Assumptions From Earlier Slices

This slice assumes the following earlier slices exist or are being built in order:

```text
VS-000 v1.1.0 — Current Site Discovery and Conflict Audit
VS-001 v1.2.0 — Plugin Foundation, CPT and Taxonomies
VS-002 v1.3.0 — ACF Fields and Options Page
VS-003 v1.3.0 — PMPro Membership Profile Resolver
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0
```

Required existing pieces:

```text
CPT:
- vg_perk

Taxonomy:
- vg_perk_runtime_status

ACF fields from VS-002:
- perk_start_datetime
- perk_end_datetime
- perk_timezone
- perk_manual_status
```

Expected manual status values:

```text
auto
paused
archived
```

Expected configuration model reference:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md defines the flexible programme/level mapping model.
VS-004 must not hardcode configured programme keys, level keys, PMPro IDs, Woo product IDs, or billing terms.
```

Configuration-health integration, where available:

```text
PerkConfigRepository / validation summary may report that a perk references disabled or unknown programme/level mappings.
VS-004 may use that validation result to mark the perk as misconfigured.
VS-004 must not implement user membership resolution or programme eligibility logic itself.
```

Important:

```text
VS-003 may exist, but VS-004 must not call the membership resolver.
```

If any required field or taxonomy is missing, the implementation agent must report the mismatch clearly and must not silently invent a different field name.

---

## 4. Non-Negotiable Rules

```text
1. Do not implement frontend hub display in this slice.
2. Do not implement Bricks/Etch query providers in this slice.
3. Do not implement WooCommerce discounts in this slice.
4. Do not implement claim/redemption tracking in this slice.
5. Do not implement Masteriyo course access in this slice.
6. Do not implement Tickera ticket access in this slice.
7. Do not implement emails or notifications in this slice.
8. Do not implement reports dashboards in this slice.
9. Do not change existing PMPro/Woo/Masteriyo/Tickera settings.
10. Do not delete expired perks.
11. Do not modify theme files.
12. Do not modify functions.php.
13. Do not rely on page-builder dynamic visibility as runtime authority.
14. Runtime status must be deterministic and inspectable.
15. Runtime status must be updated on perk save and by scheduled batch task.
16. Invalid or incomplete date setup must fail closed as `misconfigured`, never `active`.
17. Manual `paused` and `archived` overrides always win over date rules.
18. Runtime status is perk lifecycle state, not user eligibility state.
19. The system must be safe if ACF Pro is inactive or temporarily unavailable.
20. The system must not query all perks unbounded in one request on large sites.
21. Runtime status code must not hardcode launch programme keys as an exhaustive permanent list.
22. Runtime status code must not hardcode PMPro level IDs, Woo membership product IDs, Woo variation IDs, or level names.
23. Adding a new mapped programme or level must not require runtime-status code changes.
24. If configuration validation reports unknown/disabled programme or level references, the perk may fail closed as `misconfigured`.
25. Configuration-health checks must use plugin repositories/validation summaries, not direct hardcoded programme/level lists.
```

---

## 5. Required Deliverables

Add the following files to the existing plugin:

```text
voelgoed-member-perks/
├── includes/
│   ├── Perks/
│   │   ├── PerkRuntimeStatusUpdater.php
│   │   └── PerkRuntimeStatusResult.php
│   ├── Cron/
│   │   └── ScheduledTasks.php
│   └── Support/
│       ├── Time.php
│       └── FieldValueReader.php
```

Update existing files as needed:

```text
includes/Plugin.php
includes/Activator.php
includes/Deactivator.php
includes/Content/PerkTaxonomies.php
includes/Admin/PerkMetaWarnings.php
README.md
```

Optional file if the plugin already uses a dedicated admin-column structure:

```text
includes/Admin/PerkAdminColumns.php
```

Do not create custom database tables in this slice.

---

## 6. Plugin Version Bump

Update plugin version to:

```text
0.4.0
```

If the plugin defines a version constant, update it:

```php
VGMP_VERSION = "0.4.0";
```

Do not change unrelated plugin metadata.

---

## 7. Runtime Status Terms

Ensure the following terms exist in taxonomy:

```text
vg_perk_runtime_status
```

Required terms:

| Slug | Label | Meaning |
|---|---|---|
| `active` | Active | The perk is currently lifecycle-active. |
| `upcoming` | Upcoming | The perk starts in the future. |
| `expired` | Expired | The perk end date has passed. |
| `paused` | Paused | Admin manually paused the perk. |
| `archived` | Archived | Admin manually archived the perk. |
| `misconfigured` | Misconfigured | Required data is missing, invalid, or unsafe. |

These terms should be created safely if missing.

Acceptable places to ensure terms:

```text
1. plugin activation
2. runtime status service boot
3. batch updater before assigning terms
```

Do not duplicate terms if they already exist.

Do not delete terms on deactivation.

---

## 8. Runtime Status Decision Inputs

Runtime status is determined from:

```text
perk_manual_status
perk_start_datetime
perk_end_datetime
perk_timezone
current time
WordPress post status
configuration validity
optional configuration-health validation from PerkConfigRepository
```

Runtime status is **not** determined from:

```text
PMPro membership
programme membership profile
Vriendinneklub level
Voelgoednetwerk level
WooCommerce cart
Woo Subscriptions state
Masteriyo enrolment
Tickera ticket state
claim history
notification history
number of configured programmes
number of configured levels
current billing terms such as monthly/yearly/quarterly
Woo subscription product IDs
Woo variation IDs
```

---

## 8A. Configuration Health and Programme Flexibility

Runtime status does not evaluate user eligibility, but it may fail a perk closed if the perk itself is configured unsafely.

Configuration-health examples that may result in:

```text
runtime status = misconfigured
```

include:

```text
1. Perk audience rules reference a programme key that is not enabled/configured.
2. Perk audience rules reference a level key that is not mapped inside the referenced programme.
3. Programme mapping is unavailable when the perk requires programme-specific audience rules.
4. Programme alias normalization would be required but no explicit alias exists.
5. Perk configuration cannot be loaded through the approved configuration repository.
```

Rules:

```text
1. Use PerkConfigRepository / validation summaries if available.
2. Do not parse PMPro level IDs in this slice.
3. Do not inspect Woo subscription products or variations in this slice.
4. Do not call MembershipProfileResolver.
5. Do not treat Vriendinneklub and Voelgoednetwerk as the only possible programmes forever.
6. Do not guess unknown programme keys.
7. Unknown or disabled programme/level references fail closed as misconfigured.
```

Important distinction:

```text
A future programme may be valid if it is configured and enabled in the Programme/Level Mapping Model.
An unknown programme string found in a perk record is not valid just because it exists in saved ACF data.
```

Recommended additional reason values:

```text
invalid_audience_configuration
unknown_programme_reference
unknown_level_reference
programme_mapping_unavailable
config_provider_unavailable
```

---

## 9. Runtime Status Decision Rules

### 9.1 Manual Override Rules

Manual override always wins.

```text
If perk_manual_status = paused:
    runtime status = paused
    reason = manual_paused

If perk_manual_status = archived:
    runtime status = archived
    reason = manual_archived
```

Manual `paused` and `archived` are intentional admin choices. They should not be mixed with invalid setup.

### 9.2 Auto Mode Rules

If:

```text
perk_manual_status = auto
```

then use date and configuration rules.

If `perk_manual_status` is empty or unknown:

```text
runtime status = misconfigured
reason = invalid_manual_status
```

### 9.3 Date Rules

Use the current time in the selected perk timezone.

Timezone fallback order:

```text
1. perk_timezone if valid
2. WordPress site timezone
3. Africa/Johannesburg
```

Rules:

```text
If start date is missing:
    runtime status = misconfigured
    reason = missing_start_date

If start date cannot be parsed:
    runtime status = misconfigured
    reason = invalid_start_date

If end date exists but cannot be parsed:
    runtime status = misconfigured
    reason = invalid_end_date

If start date exists and end date exists and end date is before start date:
    runtime status = misconfigured
    reason = invalid_date_range

If start date is in the future:
    runtime status = upcoming
    reason = date_window_upcoming

If start date is now/past and end date is empty:
    runtime status = active
    reason = date_window_active_open_ended

If start date is now/past and end date is now/future:
    runtime status = active
    reason = date_window_active_bounded

If end date is in the past:
    runtime status = expired
    reason = date_window_expired
```

Important:

```text
A missing end date means the perk can run indefinitely after its start date.
A missing start date must never become active.
Invalid date ranges must never become active.
Invalid dates must never become active.
```

---

## 10. Post Status Handling

Only perks that are not trashed should be evaluated.

Recommended behaviour:

| WordPress post status | Runtime behaviour |
|---|---|
| `publish` | Evaluate normally. |
| `draft` | Evaluate and store runtime status for admin clarity. |
| `pending` | Evaluate and store runtime status for admin clarity. |
| `private` | Evaluate and store runtime status for admin clarity. |
| `future` | Evaluate and store runtime status for admin clarity. |
| `trash` | Do not evaluate; do not assign a new runtime status. |

Do not change the WordPress post status based on runtime status.

Runtime status is separate from WordPress post status.

Future frontend slices must still filter by WordPress post status and runtime status.

Example future query logic:

```text
post_status = publish
runtime status = active
eligible for current user's MembershipProfile
```

---

## 11. Debug Meta

In addition to assigning the taxonomy term, store lightweight internal post meta for debugging:

```text
_vgmp_runtime_status
_vgmp_runtime_status_reason
_vgmp_runtime_status_checked_at
_vgmp_runtime_status_timezone
_vgmp_runtime_status_start_at
_vgmp_runtime_status_end_at
```

Example values:

```text
_vgmp_runtime_status = active
_vgmp_runtime_status_reason = date_window_active_bounded
_vgmp_runtime_status_checked_at = 2026-06-09 14:35:00
_vgmp_runtime_status_timezone = Africa/Johannesburg
_vgmp_runtime_status_start_at = 2026-06-01 08:00:00
_vgmp_runtime_status_end_at = 2026-06-30 23:59:00
```

Rules:

```text
1. The taxonomy term remains the query/filter authority.
2. Debug meta mirrors the latest evaluation.
3. Debug meta is for admins, reports, diagnostics, and future QA.
4. Do not store user eligibility data in runtime status meta.
```

---

## 12. Class: Time Helper

Create:

```text
includes/Support/Time.php
```

Class:

```php
Voelgoed\MemberPerks\Support\Time
```

Responsibilities:

```text
1. Return current DateTimeImmutable in a requested timezone.
2. Validate timezone names.
3. Provide WordPress timezone fallback.
4. Parse ACF/date meta values safely.
5. Never fatal on bad admin input.
```

Required methods:

```php
public static function get_site_timezone(): DateTimeZone;

public static function get_timezone_or_default( ?string $timezone ): DateTimeZone;

public static function now( ?string $timezone = null ): DateTimeImmutable;

public static function parse_datetime( mixed $value, ?string $timezone = null ): ?DateTimeImmutable;

public static function format_storage_datetime( DateTimeImmutable $value ): string;
```

The implementation must handle common ACF date/time formats gracefully, including:

```text
Y-m-d H:i:s
Y-m-d H:i
Ymd H:i:s
Ymd H:i
d/m/Y g:i a
d/m/Y H:i
```

If parsing fails, return `null`.

Do not throw fatal exceptions for bad admin input.

---

## 13. Class: FieldValueReader

Create:

```text
includes/Support/FieldValueReader.php
```

Class:

```php
Voelgoed\MemberPerks\Support\FieldValueReader
```

Responsibilities:

```text
1. Read ACF values safely when ACF is active.
2. Fall back to raw post meta if ACF is inactive.
3. Normalize empty values consistently.
4. Avoid fatal errors if ACF Pro is inactive.
```

Required methods:

```php
public function get_post_field( int $post_id, string $field_name, mixed $default = null ): mixed;

public function acf_available(): bool;
```

Recommended behaviour:

```text
If get_field() exists:
    return get_field( $field_name, $post_id )

Else:
    return get_post_meta( $post_id, $field_name, true )
```

This does not make raw post meta the preferred admin interface. It only prevents fatal errors and preserves safe evaluation where data exists.

Runtime-boundary note:

```text
If PerkConfigRepository exists from VS-002/VS-003 implementation, prefer reading normalized lifecycle/config-health values through that repository.
FieldValueReader may remain a low-level fallback for lifecycle date fields, but runtime status code must not scatter direct ACF calls.
```

---

## 14. Class: PerkRuntimeStatusResult

Create:

```text
includes/Perks/PerkRuntimeStatusResult.php
```

Class:

```php
Voelgoed\MemberPerks\Perks\PerkRuntimeStatusResult
```

Purpose:

Provide a small structured result object/value shape so status decisions are explicit.

Minimum data:

```php
[
    "status"      => "active",
    "reason"      => "date_window_active_bounded",
    "should_store" => true,
    "timezone"    => "Africa/Johannesburg",
    "start_at"    => "2026-06-01 08:00:00",
    "end_at"      => "2026-06-30 23:59:00",
]
```

If a perk should not be evaluated, for example if it is trashed:

```php
[
    "status"       => null,
    "reason"       => "post_is_trashed",
    "should_store" => false,
]
```

A simple array return is acceptable if the plugin does not use value objects yet, but the structure must remain explicit.

---

## 15. Class: PerkRuntimeStatusUpdater

Create:

```text
includes/Perks/PerkRuntimeStatusUpdater.php
```

Class:

```php
Voelgoed\MemberPerks\Perks\PerkRuntimeStatusUpdater
```

Responsibilities:

```text
1. Ensure runtime status terms exist.
2. Evaluate a single perk.
3. Assign the correct runtime status term.
4. Store debug meta.
5. Batch-update non-trash perks.
6. Hook into save_post_vg_perk.
7. Fire lightweight internal actions for later cache/report hooks.
```

Required methods:

```php
public function register_hooks(): void;

public function ensure_terms_exist(): void;

public function update_perk_status( int $perk_id ): ?string;

public function update_all_perks( int $batch_size = 100 ): array;

public function evaluate_perk_status( int $perk_id ): array;
```

`evaluate_perk_status()` should return structured data:

```php
[
    "status"       => "active",
    "reason"       => "date_window_active_bounded",
    "should_store" => true,
]
```

Allowed stored status values:

```text
active
upcoming
expired
paused
archived
misconfigured
```

Allowed reason values:

```text
manual_paused
manual_archived
invalid_manual_status
missing_start_date
invalid_start_date
invalid_end_date
invalid_date_range
date_window_upcoming
date_window_active_open_ended
date_window_active_bounded
date_window_expired
post_not_found
post_is_trashed
acf_unavailable_fallback_used
term_assignment_failed
invalid_audience_configuration
unknown_programme_reference
unknown_level_reference
programme_mapping_unavailable
config_provider_unavailable
```

Rules:

```text
1. Do not assign unknown status terms.
2. Do not create arbitrary runtime status slugs.
3. Do not update trashed posts.
4. Do not change post status.
5. Do not call membership resolver.
6. Do not call WooCommerce, Masteriyo, Tickera, or notification services.
```

---

## 16. Internal Hook for Later Slices

When a status changes, fire an internal action:

```php
do_action(
    "vgmp_perk_runtime_status_changed",
    $perk_id,
    $old_status,
    $new_status,
    $reason
);
```

Rules:

```text
1. This slice only emits the action.
2. This slice must not build listeners for cache, notifications, reports, Woo, Masteriyo, or Tickera.
3. Future slices may listen to this action.
```

This gives later slices a clean integration point without adding their logic here.

---

## 17. Save Hook

Register a save hook for:

```php
save_post_vg_perk
```

Requirements:

```text
1. Ignore autosaves.
2. Ignore revisions.
3. Check current user can edit the perk.
4. Update only the saved perk.
5. Avoid infinite loops.
6. Do not update runtime status for trashed posts.
7. Do not fatal if ACF values are temporarily unavailable during save.
```

Pseudo-behaviour:

```text
When a perk is saved:
    evaluate the perk
    assign runtime status term
    update debug meta
    fire status-changed action if the stored status changed
```

---

## 18. Scheduled Task

Create:

```text
includes/Cron/ScheduledTasks.php
```

Class:

```php
Voelgoed\MemberPerks\Cron\ScheduledTasks
```

Responsibilities:

```text
1. Register custom cron schedule if needed.
2. Schedule recurring runtime status update.
3. Unschedule on plugin deactivation.
4. Hook scheduled action to PerkRuntimeStatusUpdater::update_all_perks().
5. Prevent overlapping batch runs.
```

Cron action name:

```text
vgmp_update_perk_runtime_statuses
```

Recommended interval:

```text
15 minutes
```

Custom schedule name:

```text
vgmp_every_fifteen_minutes
```

Recommended feature flag from VS-002:

```text
vgmp_enable_runtime_status_cron
```

Default:

```text
enabled
```

Implementation requirements:

```text
1. Schedule event only if not already scheduled.
2. Do not create duplicate events.
3. On deactivation, unschedule the event cleanly.
4. Batch update non-trash vg_perk posts.
5. Use a short lock/transient to avoid overlapping runs.
6. Log/admin-report failures without fataling the frontend.
```

Note:

WordPress WP-Cron is acceptable for this slice. The README must recommend that production/staging environments with LiteSpeed/Cloudflare use a real server cron calling `wp-cron.php` for reliability.

---

## 19. Batch Update Behaviour

Do **not** use an unbounded `posts_per_page => -1` query as the final production design.

Recommended query pattern:

```php
[
    "post_type"      => "vg_perk",
    "post_status"    => [ "publish", "draft", "pending", "private", "future" ],
    "posts_per_page" => $batch_size,
    "fields"         => "ids",
    "no_found_rows"  => true,
    "orderby"        => "ID",
    "order"          => "ASC",
    "paged"          => $page,
]
```

Default batch size:

```text
100
```

For each found perk:

```text
evaluate
assign runtime status term
update debug meta
```

Return a summary array:

```php
[
    "total_seen" => 10,
    "updated" => 10,
    "skipped" => 0,
    "active" => 4,
    "upcoming" => 2,
    "expired" => 2,
    "paused" => 1,
    "archived" => 0,
    "misconfigured" => 1,
    "errors" => [],
]
```

If the site has very few perks, a single request may process all. The code should still be designed with batching so it remains safe as the number of perks grows.

---

## 20. Admin List Columns

Add or update admin columns for `vg_perk`:

```text
Runtime Status
Start Date
End Date
Status Reason
```

If this logic was already started in VS-002, extend it cleanly instead of duplicating.

Visual admin requirements:

| Status | Visual treatment |
|---|---|
| `active` | Clear positive label. |
| `upcoming` | Neutral/future label. |
| `expired` | Muted label. |
| `paused` | Warning/admin-paused label. |
| `archived` | Muted/admin-only label. |
| `misconfigured` | Error/warning label. |

Do not overbuild styling. Basic admin clarity is enough.

---

## 21. Admin Notices / Warnings

On the edit screen for a perk, show non-blocking warnings for invalid configurations:

```text
Missing start date
Invalid start date
Invalid end date
End date before start date
Manual status paused
Manual status archived
Runtime status is misconfigured
Unknown or disabled programme reference in audience configuration
Unknown or disabled level reference in audience configuration
Programme mapping unavailable for a programme-specific perk
```

Warnings must only show to users who can edit the perk.

Do not prevent saving in this slice.

Future slices may convert specific warnings into hard validation before execution.

---

## 22. Admin Manual Recheck Action

Add a simple admin-only action if practical:

```text
Recheck runtime status
```

Location options:

```text
1. row action on vg_perk list
2. button/metabox on vg_perk edit screen
3. admin tools page action
```

Rules:

```text
1. Must require manage_vg_perks or edit_post capability.
2. Must use nonces.
3. Must only recheck runtime status.
4. Must not apply discounts, claims, notifications, reports, or access rules.
```

This is useful for admin confidence but should not delay the core slice if the plugin structure is not ready.

---

## 23. README Update

Update `README.md` with:

```text
## VS-004 Runtime Status Automation
```

Include:

```text
1. What runtime statuses mean.
2. Difference between runtime status and user eligibility.
3. How start/end dates work.
4. How manual paused/archived overrides work.
5. What misconfigured means.
6. How often statuses update.
7. How cron scheduling works.
8. How to manually force status update if helper/admin action exists.
9. Important note that expired perks are not deleted.
10. Recommendation to use real server cron in production/staging.
11. Warning that this slice does not display perks or apply benefits.
12. Note that adding new programmes, levels, or billing terms should not require runtime-status code changes.
13. Note that invalid programme/level references may cause a perk to be marked `misconfigured`, but user eligibility is still handled elsewhere.
```

---

## 24. Optional WP-CLI Command

If simple and safe, add a WP-CLI command:

```bash
wp vgmp perks update-statuses
```

This is optional for VS-004.

If implemented, it must:

```text
1. Run the batch updater.
2. Print summary counts.
3. Not require Woo/PMPro/ACF/Masteriyo/Tickera to be active.
4. Not change frontend display, pricing, claims, or access.
```

Do not delay the slice for WP-CLI if it adds too much complexity.

---

## 25. Acceptance Tests

The agent must test the following on staging.

### Test 1 — Required terms exist

Confirm these terms exist in `vg_perk_runtime_status`:

```text
active
upcoming
expired
paused
archived
misconfigured
```

### Test 2 — Missing start date fails safe

Create or edit a perk:

```text
manual status = auto
start date = empty
end date = empty
```

Expected:

```text
runtime status = misconfigured
reason = missing_start_date
```

### Test 3 — Invalid start date fails safe

Force an invalid start date value if safe.

Expected:

```text
runtime status = misconfigured
reason = invalid_start_date
```

### Test 4 — Future perk

Create/edit a perk:

```text
manual status = auto
start date = tomorrow
end date = empty or later date
```

Expected:

```text
runtime status = upcoming
reason = date_window_upcoming
```

### Test 5 — Active open-ended perk

Create/edit a perk:

```text
manual status = auto
start date = yesterday
end date = empty
```

Expected:

```text
runtime status = active
reason = date_window_active_open_ended
```

### Test 6 — Active bounded perk

Create/edit a perk:

```text
manual status = auto
start date = yesterday
end date = tomorrow
```

Expected:

```text
runtime status = active
reason = date_window_active_bounded
```

### Test 7 — Expired perk

Create/edit a perk:

```text
manual status = auto
start date = last week
end date = yesterday
```

Expected:

```text
runtime status = expired
reason = date_window_expired
```

### Test 8 — Invalid date range

Create/edit a perk:

```text
manual status = auto
start date = tomorrow
end date = yesterday
```

Expected:

```text
runtime status = misconfigured
reason = invalid_date_range
```

### Test 9 — Manual paused override

Create/edit a perk:

```text
manual status = paused
start date = yesterday
end date = tomorrow
```

Expected:

```text
runtime status = paused
reason = manual_paused
```

### Test 10 — Manual archived override

Create/edit a perk:

```text
manual status = archived
start date = yesterday
end date = tomorrow
```

Expected:

```text
runtime status = archived
reason = manual_archived
```

### Test 11 — Cron event exists once

Confirm the cron event is scheduled once:

```text
vgmp_update_perk_runtime_statuses
```

Expected:

```text
Exactly one recurring event exists.
No duplicate scheduled events.
```

### Test 12 — Batch update

Run the scheduled action manually or use the optional WP-CLI command.

Expected:

```text
All non-trash perks receive correct runtime statuses.
Summary counts are accurate.
No fatal errors.
```

### Test 13 — Deactivation cleanup

Deactivate plugin.

Expected:

```text
Scheduled cron event is unscheduled.
Data is not deleted.
Perks are not deleted.
Taxonomy terms are not deleted.
```

### Test 14 — ACF inactive safety

Inspect code or simulate safely.

Expected:

```text
No fatal error if get_field() is unavailable.
FieldValueReader falls back safely.
Perks with unreadable/missing data become misconfigured, not active.
```

### Test 15 — Dual-programme non-effect

Use a user who belongs to both programmes and a user who belongs to none.

Expected:

```text
Runtime status for the same perk is identical regardless of user membership.
MembershipProfile is not called by runtime status code.
```

---

### Test 16 — Future programme non-effect

Configure or simulate a future enabled programme/level in mapping settings if safe.

Expected:

```text
Runtime status logic does not require code changes.
The same perk date/manual-status rules still resolve to active/upcoming/expired/paused/archived/misconfigured as before.
Runtime status does not call MembershipProfileResolver.
```

### Test 17 — Unknown audience configuration fails closed where validation is available

Create or inspect a perk whose audience configuration references an unknown or disabled programme/level.

Expected:

```text
If PerkConfigRepository validation is available, runtime status may become misconfigured.
Reason should be one of:
- invalid_audience_configuration
- unknown_programme_reference
- unknown_level_reference
- programme_mapping_unavailable

No user-specific eligibility checks run.
No discounts, claims, notifications, course grants, or ticket logic runs.
```

### Test 18 — No hardcoded programme or level list in runtime-status code

Inspect runtime-status classes.

Expected:

```text
No hardcoded PMPro level IDs.
No hardcoded Woo product IDs.
No hardcoded Woo variation IDs.
No fixed Goud/Silwer/Brons eligibility assumptions.
Any launch programme key references are examples/admin text only, not exhaustive runtime eligibility logic.
```

---

## 26. Safety Checks Before Editing

Before editing, the agent must inspect the current plugin structure and report:

```text
Existing plugin files
Existing CPT registration
Existing taxonomy registration
Existing ACF field keys/names relevant to runtime status
Whether classes from previous slices exist
Whether any naming differs from this pack
Whether vg_perk_runtime_status already has terms
Whether any old runtime status code/snippets exist
Whether Programme/Level Mapping Model and PerkConfigRepository validation are available
Whether current audience fields contain unknown/disabled programme or level references
Whether cron hooks with similar names already exist
```

If previous slices used different names, stop and report before renaming anything.

---

## 27. Final Report Required From Agent

After implementation, provide a short report with:

```text
Files created
Files modified
Plugin version confirmed
Hooks added
Cron action name
Runtime status terms confirmed
Admin columns added
Admin warnings added
Test perks created/used
Acceptance test results
Known limitations
Confirmation that no membership, pricing, claim, course, ticket, frontend, report, or notification logic was added
Programme flexibility checks performed
Whether runtime status code avoids hardcoded programme/level/PMPro/Woo ID assumptions
Next recommended slice
```

Next recommended slice:

```text
VS-005 — Bricks/Etch Query Loop Hub Display
```

---

## 28. Explicit Non-Goals

Do not build any of the following in VS-004:

```text
PMPro membership resolver changes
Programme eligibility logic
Frontend member hub
Bricks query provider
Etch query provider
WooCommerce cart discounts
WooCommerce price display changes
WooCommerce coupons
Masteriyo course enrolment
Tickera ticket claims
Claim/redemption database table
Emails or notifications
Reports dashboard
Persistent object cache
REST API endpoints
Frontend shortcodes
```

---

## 29. Definition of Done

This slice is complete only when:

```text
1. Runtime status terms exist, including `misconfigured`.
2. Each non-trash perk can resolve to active/upcoming/expired/paused/archived/misconfigured.
3. Runtime status is stored as a taxonomy term.
4. Runtime status debug meta is stored.
5. Status updates on saving a perk.
6. Status updates in batch via scheduled task.
7. Invalid date setups fail safe as misconfigured and never become active.
8. Expired perks are not deleted.
9. Admin can clearly see current runtime status and reason.
10. Cron event is scheduled once and unscheduled on deactivation.
11. Batch updates are bounded/paginated.
12. ACF inactive/missing data does not cause fatal errors.
13. Runtime status does not call MembershipProfile or user eligibility logic.
14. No frontend display is built.
15. No pricing/access/claim/report/notification logic is built.
16. Adding a future configured programme/level does not require runtime-status code changes.
17. Unknown/disabled programme or level references fail closed as misconfigured where configuration-health validation is available.
18. Runtime-status code does not hardcode PMPro level IDs, Woo product IDs, Woo variation IDs, or fixed level names.
19. README is updated.
20. Acceptance tests pass on staging.
```

---

## 30. Developer Notes

This slice should remain boring and deterministic.

The correct long-term relationship remains:

```text
Runtime status = perk lifecycle state
MembershipProfile = current user's programme/level state
Target index = what the perk points to
Claim ledger = what has been reserved/redeemed
Woo/Masteriyo/Tickera slices = execution surfaces
Hub display = frontend presentation
```

Do not collapse these responsibilities into one class.

Programme flexibility reminder:

```text
Runtime status remains deliberately boring.
It does not become a membership engine.
It should not need edits when a new level, billing term, or future programme is added through configuration.
```
