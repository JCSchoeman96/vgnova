# FEATURE PLANNING PACK: VS-006 — Target and Audience Indexing Table

Version: **1.3.0**  
Status: **Flexibility-hardened planning pack**  
Previous slice: **VS-005 — Bricks/Etch Programme Hub Display**  
Next slice: **VS-007 — Claims and Redemption Ledger**

---

## 0. v1.2.0 ACF Runtime Boundary Patch Summary

This version applies the ACF runtime-boundary patch.

```text
1. Replaces "ACF wins during reindex" with "canonical editable configuration wins during reindex".
2. Requires PerkTargetIndexer to read normalized config through PerkConfigRepository.
3. Keeps wp_vgmp_perk_targets as a rebuildable runtime projection, not the editable source of truth.
4. Adds provider-unavailable behaviour to avoid corrupting the index if ACF/config is unavailable.
5. Adds optional last-indexed config-hash/debug metadata.
6. Adds tests for provider-boundary and ACF inactive behaviour.
```

## 0A. v1.3.0 Programme Flexibility Patch Summary

This version applies the programme and level flexibility pass from `VGMP_PROGRAMME_FLEXIBILITY_PATCHLIST_v1.0.0` and must be read alongside `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as default launch programmes, not the only possible programmes forever.
2. Requires audience indexing to validate against enabled configured programme definitions.
3. Requires programme and level aliases to normalize before indexing.
4. Requires index rows to store canonical configured programme_key and level_key values only.
5. Requires unknown, disabled, or unmapped programme/level references to be skipped and reported.
6. Adds ProgrammeMappingRepository as the validation source for audience target extraction.
7. Adds mapping-hash/debug metadata expectations so stale audience indexes can be detected after mapping changes.
8. Adds reindex/invalidation requirements when programme/level mappings change.
9. Adds tests for aliases, future configured programmes, future levels, stale mapping rows, and unknown/legacy index rows.
10. Keeps the table names stable: `wp_vgmp_perk_targets` remains the canonical target/audience index table.
```

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```


## ACF Runtime Boundary Doctrine

This document follows the project-wide ACF runtime boundary:

```text
1. ACF is the current admin editing interface and field-definition provider.
2. ACF is not the runtime authority for pricing, claims, eligibility, tickets, courses, notifications, or cache identity.
3. Runtime and execution classes must not call get_field(), get_fields(), acf_get_value(), or ACF option functions directly.
4. All runtime reads must go through plugin-owned configuration repositories.
5. The current implementation of those repositories may read from ACF/post meta, but this is an internal provider detail.
6. The repository layer must normalize, validate, sanitize, and fail closed.
7. Hot paths must consume typed/value-style configuration objects, not raw ACF arrays.
8. If ACF is inactive, admin editing is unavailable, but the plugin must not fatal.
9. If required configuration cannot be loaded, execution must fail closed.
10. This boundary preserves a future migration path from ACF to fully custom admin fields without rewriting Woo, claims, Masteriyo, Tickera, reports, cache, or notifications.
```

## 1. Objective

Build the indexing layer for the **Voelgoed Member Perks** plugin.

This vertical slice must create and maintain a dedicated lookup table that maps each `vg_perk` to:

```text
1. Benefit targets:
   - WooCommerce products
   - WooCommerce product categories
   - Masteriyo courses
   - Tickera events
   - Tickera tickets/products
   - WordPress pages
   - WordPress posts
   - external URLs

2. Audience targets:
   - programme
   - programme level
   - membership audience aliases where explicitly configured
```

This prevents later slices from relying on slow, fragile reverse lookups against serialized ACF Relationship fields.

This slice is a **backend data/indexing slice only**.

It must not:

```text
apply WooCommerce discounts
change cart prices
create claims
grant Masteriyo course access
issue Tickera tickets
send emails
change frontend hub design
```

The purpose is to create a reliable runtime lookup index that future slices can use.

---

## 2. Current Baseline Assumptions

This slice assumes the following previous slices exist or have been implemented:

```text
VS-000 — Current Site Discovery and Conflict Audit
VS-001 — Plugin Foundation, CPT and Taxonomies
VS-002 — ACF Fields and Options Page
VS-003 — PMPro Membership Profile Resolver
VS-004 — Runtime Status Automation
VS-005 — Bricks/Etch Programme Hub Display
```

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected namespace:

```php
Voelgoed\MemberPerks
```

Expected CPT:

```text
vg_perk
```

Expected runtime status taxonomy:

```text
vg_perk_runtime_status
```

Expected default launch programme keys from VS-001/VS-002:

```text
vriendinneklub
voelgoednetwerk
```

These are launch defaults for the current Voelgoed setup, not a closed forever-list. Runtime validation for audience indexing must use the enabled configured programme definitions exposed through `ProgrammeRegistry` / `SettingsRepository` / `ProgrammeMappingRepository`.

Future programmes may be indexed if they are explicitly configured, enabled, mapped, and pass QA. Unknown programme keys must fail closed and be reported.

Expected membership model from VS-003:

```text
MembershipProfile
```

Expected hub/display model from VS-005:

```text
Launch main dashboard:
- /my-voordele/

Launch programme hubs:
- /my-vriendinneklub/
- /my-voelgoed-netwerk/
```

These hub paths are launch defaults. Future programme hub behaviour must come from programme configuration and SettingsRepository output, not hardcoded index logic.

If any previous slice used different names, the agent must stop and report the mismatch before implementing this slice.

---

## 3. Why This Slice Exists

ACF fields are good for admin editing. They are not good as the runtime lookup authority for questions like:

```text
Which active perks target this WooCommerce product?
Which active perks target this Masteriyo course?
Which active perks target this Tickera event?
Which active perks apply to this page?
Which active perks have audience rules for Vriendinneklub?
Which active perks have audience rules for Voelgoednetwerk Silwer?
```

ACF Relationship fields are commonly stored in post meta as arrays/serialized values. Reverse-querying them with `LIKE` meta queries is fragile and slow.

Correct model:

```text
Canonical editable configuration = admin-editable input source
For MVP, ACF provides that configuration through AcfConfigProvider
PerkConfigRepository normalizes and validates it
Target/audience index table = runtime lookup projection
Plugin services = execution authority in later slices
```

If the table and editable configuration disagree:

```text
The canonical editable configuration wins during reindex.
For MVP this configuration is provided by ACF through AcfConfigProvider and normalized by PerkConfigRepository.
Future custom admin storage may replace the provider without changing this table.
Target/audience index table wins for runtime lookup until reindex runs.
```

This is the same principle as search indexes: source data is editable; the index is rebuildable.

---

## 3A. Configuration Provider Boundary

The target/audience indexer must not scatter direct `get_field()` calls throughout indexing code.

Correct indexing flow:

```text
ConfigProviderInterface / AcfConfigProvider
↓
PerkConfigRepository
↓
PerkConfig target_config + audience_rules
↓
PerkTargetIndexer
↓
wp_vgmp_perk_targets
```

Rules:

```text
1. Only AcfConfigProvider may call ACF functions directly.
2. PerkTargetIndexer must read normalized target and audience config from PerkConfigRepository.
3. The table remains rebuildable from canonical editable configuration.
4. The table is not the editable source of truth.
5. Runtime lookup uses the table until the next reindex.
```

## 3B. Programme and Level Mapping Boundary

Audience indexing must use configured programme and level definitions.

Correct audience indexing flow:

```text
SettingsRepository
↓
ProgrammeMappingRepository
↓
ProgrammeDefinition / ProgrammeLevelDefinition
↓
PerkConfigRepository audience_rules
↓
PerkAudienceIndexExtractor
↓
wp_vgmp_perk_targets audience_target rows
```

Rules:

```text
1. Audience rows must store canonical configured programme_key and level_key values.
2. Programme aliases are input compatibility only and must normalize before indexing.
3. Level aliases are input/import compatibility only and must normalize before indexing.
4. Unknown programme keys must not be indexed as valid audience rows.
5. Disabled programmes must not be indexed as valid audience rows.
6. Unknown level keys must not be indexed as valid programme_level rows.
7. Disabled level mappings must not be indexed as valid programme_level rows.
8. If a legacy or unknown value already exists in the table, admin/reporting tools must show it as unknown/legacy.
9. Do not silently remap old rows unless an explicit alias or migration rule exists.
10. Do not call PMPro from this slice. PMPro remains membership authority, but this table indexes configured perk audience rules only.
```

The table is allowed to contain launch examples such as:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

but examples are not exhaustive. Actual valid level keys come from `ProgrammeMappingRepository`.

---

## 4. Non-Negotiable Scope Boundaries

Do not implement:

```text
WooCommerce discount execution
WooCommerce cart price changes
WooCommerce order meta
claim/redemption table
claim buttons that create records
Masteriyo enrolment/access changes
Tickera ticket issuing
R0 checkout
reports dashboards
email notifications
frontend redesign
theme edits
functions.php edits
WPCode snippet edits
```

This slice may expose repository/helper methods that later slices will use, but it must not execute benefits yet.

---

## 5. Required Deliverables

Add the following plugin files:

```text
includes/
├── Database/
│   ├── Schema.php
│   └── PerkTargetTable.php
│
├── Config/
│   ├── ConfigProviderInterface.php
│   ├── AcfConfigProvider.php
│   ├── PerkConfigRepository.php
│   ├── SettingsRepository.php
│   ├── ProgrammeMappingRepository.php
│   ├── ProgrammeDefinition.php
│   ├── ProgrammeLevelDefinition.php
│   ├── PerkConfig.php
│   ├── ClaimPolicy.php
│   └── AudienceRules.php
│
├── Perks/
│   ├── PerkTargetIndexer.php
│   ├── PerkTargetRepository.php
│   ├── PerkTargetNormalizer.php
│   └── PerkAudienceIndexExtractor.php
│
├── Admin/
│   ├── PerkTargetAdmin.php
│   └── ReindexTargetsTool.php
│
└── Support/
    └── Database.php
```

Update existing files:

```text
includes/Plugin.php
includes/Activator.php
includes/Deactivator.php
README.md
```

Optional:

```text
includes/Cli/ReindexTargetsCommand.php
```

If existing project naming differs, preserve the responsibilities and document the deviation.

---

## 6. Database Table Name

Create a custom table:

```text
{$wpdb->prefix}vgmp_perk_targets
```

Example:

```text
wp_vgmp_perk_targets
```

Do **not** use:

```text
wp_vg_perk_targets
```

Reason:

```text
The plugin prefix is vgmp.
All custom tables should use the same plugin prefix to avoid naming drift.
```

---

## 7. Table Purpose

One row represents one indexed relationship.

Examples:

```text
Perk 123 targets Woo product 456.
Perk 123 targets Woo product 789.
Perk 200 targets Masteriyo course 901.
Perk 300 targets Tickera event 1200.
Perk 400 targets programme vriendinneklub.
Perk 400 targets programme_level vriendinneklub:goud.
Perk 500 targets programme voelgoednetwerk.
Perk 500 targets programme_level voelgoednetwerk:silwer.
```

The table indexes both benefit targets and audience targets, but it must not become the source of business truth.

---

## 8. Index Scopes

The table must include an `index_scope` column.

Allowed values:

```text
benefit_target
audience_target
```

Meaning:

```text
benefit_target
    The thing the perk applies to or points at.
    Examples: product, course, ticket, page, external URL.

audience_target
    The audience rule indexed from ACF.
    Examples: programme, programme level, audience alias.
```

Important:

```text
Audience rows help with lookup/filtering.
Audience rows do not replace the MembershipProfile resolver.
Audience rows do not prove that a specific user is eligible.
```

User-specific eligibility remains the job of:

```text
VS-003 — Membership Profile Resolver
VS-005 — PerkAudienceEvaluator
later execution slices
```

---

## 9. Required Table Schema

Use `dbDelta()` through the existing schema manager.

Recommended SQL shape:

```sql
CREATE TABLE {$wpdb->prefix}vgmp_perk_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    perk_id BIGINT UNSIGNED NOT NULL,
    index_scope VARCHAR(40) NOT NULL DEFAULT 'benefit_target',
    target_type VARCHAR(60) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    target_value VARCHAR(255) NOT NULL DEFAULT '',
    source_field VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY unique_target (perk_id, index_scope, target_type, target_id, target_value),
    KEY perk_id (perk_id),
    KEY scope_type_lookup (index_scope, target_type),
    KEY target_lookup (index_scope, target_type, target_id),
    KEY target_value_lookup (index_scope, target_type, target_value),
    KEY perk_target_lookup (perk_id, index_scope, target_type, target_id),
    KEY updated_at (updated_at)
) DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate};
```

### 9.1 Why `target_id` and `target_value` must be NOT NULL

Do not use nullable columns in the unique identity key.

Bad:

```sql
target_id BIGINT UNSIGNED NULL,
target_value VARCHAR(255) NULL,
UNIQUE KEY unique_target (perk_id, target_type, target_id, target_value)
```

Reason:

```text
MySQL/MariaDB unique indexes can allow duplicate rows when indexed columns contain NULL.
```

Correct:

```sql
target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
target_value VARCHAR(255) NOT NULL DEFAULT ''
```

Rules:

```text
Use target_id for WordPress object IDs.
Use target_id = 0 when there is no object ID.
Use target_value for non-object values.
Use target_value = "" when there is no non-object value.
```

---

## 10. Supported Benefit Target Types

The following `benefit_target` rows are supported in VS-006:

```text
woo_product
woo_product_category
masteriyo_course
tickera_event
tickera_ticket
wp_page
wp_post
external_url
```

Do not fatal if WooCommerce, Masteriyo, or Tickera are inactive.

This table stores IDs/values. It does not execute the integration.

---

## 11. Supported Audience Target Types

The following `audience_target` rows are supported in VS-006:

```text
programme
programme_level
audience_alias
```

### 11.1 `programme`

Launch examples:

```text
target_type  = programme
target_value = vriendinneklub

target_type  = programme
target_value = voelgoednetwerk
```

Rules:

```text
1. These are examples from the launch setup.
2. The actual valid programme values are enabled configured programme keys.
3. Aliases such as `old_programme_key` may be accepted only if explicitly configured and must normalize to the canonical configured key before writing index rows.
4. Unknown or disabled programmes must be skipped and reported.
```

### 11.2 `programme_level`

Format:

```text
{programme_key}:{level_key}
```

Launch examples only:

```text
vriendinneklub:goud
vriendinneklub:silwer
vriendinneklub:brons
vriendinneklub:goud
voelgoednetwerk:silwer
voelgoednetwerk:platinum
```

The actual `level_key` values must come from enabled `ProgrammeLevelDefinition` rows through `ProgrammeMappingRepository`.

Do not invent levels in this slice. Do not assume Goud/Silwer/Brons, Brons/Silwer/Goud, or any fixed level ladder.

### 11.3 `audience_alias`

Examples:

```text
all_paid_members
any_active_programme
```

Rules:

```text
Use audience aliases only if VS-002 exposes them explicitly.
Do not treat aliases as real membership levels.
Do not replace MembershipProfile logic with aliases.
```

---

## 11A. Programme Mapping Repository Dependency

Create or confirm the planning abstraction:

```php
Voelgoed\MemberPerks\Config\ProgrammeMappingRepository
```

Responsibilities for this slice:

```text
1. Load enabled programme definitions from SettingsRepository.
2. Load enabled programme level definitions from SettingsRepository.
3. Normalize programme aliases to canonical configured programme_key values.
4. Normalize level aliases to canonical configured level_key values within a programme.
5. Validate whether a programme is enabled.
6. Validate whether a programme level is enabled.
7. Detect duplicate PMPro level IDs across mappings for diagnostics, but do not call PMPro from this slice.
8. Expose a deterministic programme mapping hash/version.
9. Return validation warnings for admin/reindex reports.
```

Suggested methods:

```php
public function normalize_programme_key( string $programme_or_alias ): ?string;

public function normalize_level_key(
    string $programme_key,
    string $level_or_alias
): ?string;

public function is_enabled_programme( string $programme_key ): bool;

public function is_enabled_programme_level(
    string $programme_key,
    string $level_key
): bool;

public function get_mapping_hash(): string;

public function get_warnings(): array;
```

The `PerkAudienceIndexExtractor` must use this repository. It must not validate audience values against a hardcoded array of launch programme keys.

---

## 12. Schema Class

Create or update:

```php
Voelgoed\MemberPerks\Database\Schema
```

Responsibilities:

```text
1. Create/update custom tables on activation.
2. Store schema version in wp_options.
3. Run safe upgrades when schema version changes.
4. Never drop tables on deactivation.
5. Never delete data on normal plugin update.
```

Option key:

```text
vgmp_schema_version
```

Initial schema version for this custom table set:

```text
1
```

If a previous hardened slice already introduced a schema version, increment to the next integer instead of resetting.

Rules:

```text
Do not assume the schema option is absent.
Do not downgrade schema versions.
Do not run destructive migrations.
Do not rename old tables automatically unless a migration plan exists.
```

If an older table named `wp_vg_perk_targets` already exists on staging:

```text
1. Do not silently use it as the canonical table.
2. Report it as legacy.
3. Create/use `wp_vgmp_perk_targets` as the canonical table.
4. Provide a migration recommendation, but do not move data unless explicitly approved.
```

---

## 13. PerkTargetTable Class

Create:

```php
Voelgoed\MemberPerks\Database\PerkTargetTable
```

Responsibilities:

```text
1. Return the canonical table name.
2. Provide create table SQL.
3. Provide safe insert/delete helpers.
4. Keep SQL centralised.
5. Prevent duplicate rows through the database unique key.
```

Minimum methods:

```php
public function table_name(): string;

public function create_table(): void;

public function delete_targets_for_perk( int $perk_id ): void;

public function insert_target(
    int $perk_id,
    string $index_scope,
    string $target_type,
    int $target_id,
    string $target_value,
    string $source_field
): bool;
```

Implementation requirements:

```text
Use $wpdb->insert(), $wpdb->delete(), and $wpdb->prepare().
Sanitize target_type and index_scope before writes.
Normalize target_id to 0 when absent.
Normalize target_value to "" when absent.
Never concatenate untrusted values into SQL.
```

---

## 14. Support Database Helper

Create:

```php
Voelgoed\MemberPerks\Support\Database
```

Responsibilities:

```text
1. Provide safe current datetime helper for database writes.
2. Provide charset/collate helper if not centralised elsewhere.
3. Provide small wrappers for detecting table existence where needed.
```

Do not create a large ORM abstraction.

This is WordPress plugin code. Keep it boring and explicit.

---

## 15. PerkTargetNormalizer Class

Create:

```php
Voelgoed\MemberPerks\Perks\PerkTargetNormalizer
```

Responsibilities:

```text
1. Normalize relationship field values into positive integer IDs.
2. Normalize term field values into positive integer term IDs.
3. Normalize external URLs.
4. Normalize target values for unique-key stability.
```

Relationship fields may return:

```text
IDs
WP_Post objects
arrays of IDs
arrays of WP_Post objects
null
empty array
```

Taxonomy fields may return:

```text
term IDs
WP_Term objects
arrays of term IDs
arrays of WP_Term objects
null
empty array
```

The normalizer must remove:

```text
0
negative values
non-numeric values
missing posts/terms where practical
duplicates
```

Sort IDs ascending for deterministic indexing.

---

## 16. PerkAudienceIndexExtractor Class

Create:

```php
Voelgoed\MemberPerks\Perks\PerkAudienceIndexExtractor
```

Purpose:

```text
Read normalized programme-aware audience rules from PerkConfigRepository and produce canonical audience index rows.
```

This class must inspect the actual locked field/config structure from VS-002 / PerkConfigRepository.

Expected logical data from VS-002:

```text
programme_key
level_selection_mode
explicit_level_keys
minimum_level_key
include_users_with_any_active_programme
```

Do not hardcode an exact repeater shape unless VS-002 already locked it.

Programme and level validation must be delegated to `ProgrammeMappingRepository`. Do not validate against only the two launch programme keys.

Required output rows:

```php
[
    [
        "index_scope"  => "audience_target",
        "target_type"  => "programme",
        "target_id"    => 0,
        "target_value" => "vriendinneklub",
        "source_field" => "perk_audience_rules",
    ],
    [
        "index_scope"  => "audience_target",
        "target_type"  => "programme_level",
        "target_id"    => 0,
        "target_value" => "vriendinneklub:goud",
        "source_field" => "perk_audience_rules",
    ],
]
```

If the perk is configured for both programmes:

```text
Index both.
```

If the perk is configured for all active programmes:

```text
Index:
audience_alias = any_active_programme
```

If the audience rules are missing or invalid:

```text
Do not guess.
Index zero audience rows for the invalid part.
Report warnings for admin/reindex output.
Let VS-004/VS-012/VS-015 report misconfiguration where appropriate.
```

Alias handling:

```text
1. Explicit programme aliases normalize to canonical configured programme_key before indexing.
2. Explicit level aliases normalize to canonical configured level_key before indexing.
3. The table stores canonical keys only, never aliases.
4. If an alias collides or cannot be resolved, skip the row and report it.
```

This extractor must not call PMPro directly.

---

## 17. PerkTargetIndexer Class

Create:

```php
Voelgoed\MemberPerks\Perks\PerkTargetIndexer
```

Purpose:

```text
Read normalized target and audience configuration from PerkConfigRepository and sync it into wp_vgmp_perk_targets.
```

The indexer must not call ACF directly. Only AcfConfigProvider / PerkConfigRepository may read raw ACF/post-meta data.

Hook into:

```php
save_post_vg_perk
trashed_post
before_delete_post
```

Only reindex when the post type is:

```text
vg_perk
```

Do not index autosaves or revisions.

Guard rules:

```text
Check wp_is_post_revision().
Check wp_is_post_autosave().
Check current post type.
Check permissions for admin-triggered save flows where appropriate.
Avoid infinite loops.
```

---

## 18. Indexing Strategy

On save:

```text
1. Confirm post type is vg_perk.
2. Read benefit target ACF fields.
3. Read programme-aware audience ACF fields.
4. Normalize all values.
5. Delete existing index rows for this perk.
6. Insert current benefit_target rows.
7. Insert current audience_target rows.
8. Update lightweight debug meta if useful.
```

Use a **replace all rows for this perk** strategy.

Reason:

```text
It is simpler, deterministic, and safe because each perk usually has a small number of targets.
```

Do not attempt complex diff logic in VS-006.

---

## 19. Benefit Target Field Extraction

The indexer must support the ACF fields defined in VS-002.

Expected fields may include:

```text
perk_target_type
perk_target_products
perk_target_product_categories
perk_target_courses
perk_target_events
perk_target_tickets
perk_target_pages
perk_target_posts
perk_external_url
```

Use `get_field()` if ACF is active.

If ACF is inactive:

```text
Do not fatal.
Log/admin-warn that target indexing could not read ACF fields.
Return safely.
```

If field names differ from VS-002:

```text
Inspect ACF JSON.
Use the actual locked field names.
Document the deviation in the final agent report.
```

---

## 19A. Provider-Unavailable Behaviour

If the configuration provider is unavailable during save/reindex:

```text
1. Do not fatal.
2. Do not guess target rows.
3. Do not create empty/incorrect audience rows.
4. Manual reindex must fail safely and report provider_unavailable.
5. Normal page loads must not delete existing index rows simply because the provider is unavailable.
6. Admin diagnostics should explain that canonical editable configuration could not be loaded.
```

Optional debug meta after successful indexing:

```text
_vgmp_last_indexed_config_hash
_vgmp_last_indexed_programme_mapping_hash
_vgmp_last_indexed_at
_vgmp_last_indexed_by_provider
```

If the stored `_vgmp_last_indexed_programme_mapping_hash` differs from the current ProgrammeMappingRepository hash, the perk's audience index may be stale and should be reindexed or flagged for admin review.

## 20. Benefit Target Normalisation Rules

For each target field:

### 20.1 `perk_target_products`

```text
index_scope  = benefit_target
target_type  = woo_product
target_id    = product ID
target_value = ""
source_field = perk_target_products
```

### 20.2 `perk_target_product_categories`

```text
index_scope  = benefit_target
target_type  = woo_product_category
target_id    = term ID
target_value = ""
source_field = perk_target_product_categories
```

### 20.3 `perk_target_courses`

```text
index_scope  = benefit_target
target_type  = masteriyo_course
target_id    = course ID
target_value = ""
source_field = perk_target_courses
```

### 20.4 `perk_target_events`

```text
index_scope  = benefit_target
target_type  = tickera_event
target_id    = event ID
target_value = ""
source_field = perk_target_events
```

### 20.5 `perk_target_tickets`

```text
index_scope  = benefit_target
target_type  = tickera_ticket
target_id    = ticket/product ID
target_value = ""
source_field = perk_target_tickets
```

### 20.6 `perk_target_pages`

```text
index_scope  = benefit_target
target_type  = wp_page
target_id    = page ID
target_value = ""
source_field = perk_target_pages
```

### 20.7 `perk_target_posts`

```text
index_scope  = benefit_target
target_type  = wp_post
target_id    = post ID
target_value = ""
source_field = perk_target_posts
```

### 20.8 `perk_external_url`

```text
index_scope  = benefit_target
target_type  = external_url
target_id    = 0
target_value = sanitized URL
source_field = perk_external_url
```

---

## 21. Target Type Validation

The indexer must only insert allowed rows.

Allowed scopes:

```php
[
    "benefit_target",
    "audience_target",
]
```

Allowed benefit target types:

```php
[
    "woo_product",
    "woo_product_category",
    "masteriyo_course",
    "tickera_event",
    "tickera_ticket",
    "wp_page",
    "wp_post",
    "external_url",
]
```

Allowed audience target types:

```php
[
    "programme",
    "programme_level",
    "audience_alias",
]
```

Do not store arbitrary target types from request data.

Do not accept frontend writes.

Only admin save/reindex processes may write index rows.

---

## 22. Target Existence Checks

For `target_id` rows:

```text
Confirm target ID is positive.
Prefer confirming the post/term exists where practical.
Do not fatal if the related plugin is inactive.
```

Recommended:

```text
Post targets:
- get_post( $target_id )
- skip if missing

Term targets:
- get_term( $target_id )
- skip if missing or WP_Error

External URL:
- esc_url_raw()
- skip if empty/invalid
```

Do not enforce plugin-specific post type names too aggressively in this slice.

Reason:

```text
Tickera and Masteriyo setups can vary.
VS-000 should discover exact CPT names.
VS-002 should lock the ACF field configuration.
```

For audience targets:

```text
programme must normalize to an enabled configured programme key.
programme_level must normalize to an enabled configured programme key + enabled configured level key.
audience_alias must be from an allowed alias list.
aliases must never be stored directly in the table.
```

If validation cannot confirm the value:

```text
Skip it and log/report it.
Do not guess.
Do not silently create a new programme or level from an unknown value.
```

Unknown or disabled values:

```text
Unknown programme key      => skip row, report unknown_programme_reference.
Disabled programme key     => skip row, report disabled_programme_reference.
Unknown level key          => skip row, report unknown_level_reference.
Disabled level key         => skip row, report disabled_level_reference.
Alias collision            => skip row, report alias_collision.
Mapping unavailable        => skip audience indexing safely and report programme_mapping_unavailable.
```

---

## 23. PerkTargetRepository Class

Create:

```php
Voelgoed\MemberPerks\Perks\PerkTargetRepository
```

Purpose:

```text
Provide read methods for later slices.
```

Minimum methods:

```php
public function get_targets_for_perk( int $perk_id, ?string $index_scope = null ): array;

public function get_perk_ids_for_target(
    string $target_type,
    int $target_id,
    string $index_scope = "benefit_target"
): array;

public function get_perk_ids_for_target_value(
    string $target_type,
    string $target_value,
    string $index_scope = "benefit_target"
): array;

public function get_active_perk_ids_for_target(
    string $target_type,
    int $target_id,
    string $index_scope = "benefit_target"
): array;

public function get_active_perk_ids_for_programme( string $programme_key ): array;

public function get_active_perk_ids_for_programme_level(
    string $programme_key,
    string $level_key
): array;

public function get_unknown_or_legacy_audience_rows(): array;

public function get_target_counts_for_perks( array $perk_ids ): array;
```

Important:

`get_active_perk_ids_for_target()` should only return perks that are currently:

```text
post_status = publish
vg_perk_runtime_status = active
```

It does not check user-specific membership eligibility.

Membership-specific filtering still belongs to:

```text
VS-003 MembershipProfile
VS-005 PerkAudienceEvaluator
future execution slices
```

---

## 24. Active Perk Lookup Rules

Active lookup methods must join or cross-check WordPress post/taxonomy data so they only return:

```text
post_type = vg_perk
post_status = publish
runtime status = active
```

Do not use date meta queries here.

Runtime state is already owned by:

```text
VS-004 — Runtime Status Automation
```

If runtime status taxonomy is missing:

```text
Fail closed and return empty.
Log/admin-warn if possible.
```

---

## 25. Admin Target Count Column

Create:

```php
Voelgoed\MemberPerks\Admin\PerkTargetAdmin
```

Add an admin list column for `vg_perk`:

```text
Targets
```

Display examples:

```text
0 targets
1 target
3 targets
2 products, 1 course
1 Vriendinne audience, 2 product targets
```

Performance requirement:

```text
Do not parse ACF fields in each admin row.
Use the target table.
```

Preferred implementation:

```text
Bulk-prefetch target counts for visible admin list rows.
```

Acceptable MVP:

```text
Query count per row from target table.
Document that this can be optimized later.
```

---

## 26. Reindex Tool

Create:

```php
Voelgoed\MemberPerks\Admin\ReindexTargetsTool
```

Purpose:

```text
Allow administrators to rebuild the index from existing vg_perk ACF fields.
```

Location:

```text
Member Perks > Tools
```

Or if no tools page exists yet:

```text
Member Perks > Reindex Targets
```

Capability required:

```text
manage_vg_perks
```

Action:

```text
Reindex all non-trash vg_perk posts.
```

Security:

```text
Use nonce.
Check capability.
Sanitize request values.
Process in batches.
```

Recommended batch size:

```text
50 perks
```

If full AJAX/background batching is not implemented yet:

```text
Keep the manual tool small and honest.
Report the limitation.
Do not pretend it is safe for thousands of perks in one request.
```

---

## 26A. Reindexing After Programme Mapping Changes

When programme or level mappings change, audience index rows may become stale.

Required behaviour:

```text
1. Programme mapping changes must bump or change the programme mapping hash/version.
2. Admin tools must detect perks whose `_vgmp_last_indexed_programme_mapping_hash` differs from the current mapping hash.
3. Affected perks must be reindexed or clearly flagged as needing reindex.
4. Cache groups for audience lookups, active perks, and current-user display must be invalidated by later cache slices.
5. Existing index rows that reference disabled/unknown programmes must not be silently remapped.
6. Unknown/legacy rows should remain inspectable until an explicit migration or reindex removes them.
```

This slice may implement only the detection and manual reindex surface. Persistent cache invalidation is finalised in VS-013.

---

## 27. Optional WP-CLI Command

If WP-CLI is available, add:

```bash
wp vgmp reindex-targets
```

Expected output:

```text
Found 42 perks.
Reindexed 42 perks.
Inserted 128 target rows.
Skipped 3 invalid target values.
Done.
```

Do not make WP-CLI required.

If not implemented, document it as a future improvement.

---

## 28. Logging

If a logger exists from previous slices, use it.

If not, add light internal logging only when debugging is enabled.

Log these events:

```text
Target index table created/updated
Perk targets reindexed
Audience targets reindexed
Invalid target ID skipped
Invalid programme key skipped
Disabled programme key skipped
Invalid programme level skipped
Disabled programme level skipped
Programme alias normalized
Level alias normalized
Programme mapping unavailable
Stale programme mapping hash detected
External URL skipped as invalid
ACF inactive during indexing
Reindex tool completed
Database insert failure
Legacy table detected
```

Do not expose logs to normal members.

---

## 29. Data Integrity Rules

Required integrity rules:

```text
1. A perk cannot have stale index rows after save.
2. Trashing/deleting a perk must remove its index rows.
3. Invalid target IDs must be skipped.
4. Invalid audience values must be skipped.
5. Audience index rows must store canonical configured programme_key and level_key values only.
6. Programme/level aliases must never be stored as canonical target_value rows.
7. Duplicate rows must be prevented by the unique key.
8. Empty target fields must result in zero benefit_target rows for that perk.
9. Empty audience rules must result in zero audience_target rows for that perk.
10. External URL targets must be sanitized.
11. The index must be rebuildable from canonical editable configuration.
12. ACF remains the MVP admin editing provider, not runtime authority.
```

---

## 30. Performance Requirements

This slice must improve performance, not create new bottlenecks.

Rules:

```text
1. Do not run reverse meta queries against ACF Relationship fields.
2. Do not scan all perks for each product/course/ticket lookup.
3. Use indexed target lookup queries.
4. Avoid expensive admin column queries per row where practical.
5. Replace index rows per perk on save instead of complex diff logic.
6. Keep index rows narrow and indexed.
7. Avoid loading all users or all PMPro memberships.
8. Do not call PMPro from this slice.
9. Do not hardcode PMPro level IDs, PMPro group IDs, Woo membership product IDs, Woo variation IDs, current billing terms, or fixed level names in indexing logic.
10. Use ProgrammeMappingRepository for programme/level validation.
```

This slice indexes perk configuration only.

It must not resolve users.

---

## 31. Security Requirements

All admin actions must:

```text
Check current_user_can( "manage_vg_perks" )
Verify nonce
Sanitize request values
Use prepared SQL
Avoid exposing internal data to unauthorised users
```

All database writes must use safe WordPress database methods:

```text
$wpdb->insert()
$wpdb->delete()
$wpdb->prepare()
```

Do not accept arbitrary target rows from frontend requests.

Do not add a public write REST endpoint.

---

## 32. Compatibility Requirements

This slice must not fatal if these plugins are inactive:

```text
WooCommerce
Woo Subscriptions
Paid Memberships Pro
Masteriyo
Tickera
ACF Pro
Bricks
Etch
```

If ACF is inactive:

```text
Plugin still loads.
Target indexing skips safely.
Admin should show a clear warning if appropriate.
```

If Woo/Masteriyo/Tickera are inactive:

```text
Target rows can still exist if the underlying post IDs/terms exist.
No execution logic runs.
```

If PMPro is inactive:

```text
Audience target indexing can still index configured programme rules from canonical editable configuration.
Do not resolve users.
Do not call PMPro.
Unmapped PMPro-level diagnostics may be unavailable until PMPro is active, but audience indexing must still fail closed for unknown configured programme/level values.
```

---

## 33. Testing Plan

Test on staging only.

### Test 1 — Activation creates table

```text
Activate plugin or run schema upgrade.
Expected: wp_vgmp_perk_targets exists.
Expected: vgmp_schema_version option exists.
```

### Test 2 — Nullable unique-key protection

```text
Inspect table structure.
Expected:
target_id is NOT NULL DEFAULT 0.
target_value is NOT NULL DEFAULT "".
unique_target includes index_scope.
```

### Test 3 — Product target indexing

```text
Create a vg_perk.
Select one or more Woo products in perk_target_products.
Save perk.
Expected:
Rows created with:
index_scope = benefit_target
target_type = woo_product
```

### Test 4 — Product category indexing

```text
Select one or more Woo product categories.
Save perk.
Expected:
Rows created with:
index_scope = benefit_target
target_type = woo_product_category
```

### Test 5 — Course target indexing

```text
Select one or more Masteriyo course targets if the course CPT exists.
Save perk.
Expected:
Rows created with:
index_scope = benefit_target
target_type = masteriyo_course
```

### Test 6 — Event/ticket target indexing

```text
Select Tickera event/ticket targets if available.
Save perk.
Expected:
Rows created with:
target_type = tickera_event or tickera_ticket
```

### Test 7 — Page/post target indexing

```text
Select a page and post target.
Save perk.
Expected:
wp_page and wp_post rows exist.
```

### Test 8 — External URL indexing

```text
Set perk_external_url to a valid URL.
Save perk.
Expected:
target_type = external_url
target_id = 0
target_value = sanitized URL
```

### Test 9 — Programme audience indexing

```text
Configure a perk for Vriendinneklub Goud.
Save perk.
Expected:
audience_target row:
target_type = programme
target_value = vriendinneklub

audience_target row:
target_type = programme_level
target_value = vriendinneklub:goud
```

### Test 10 — Dual-programme audience indexing

```text
Configure a perk for:
- Vriendinneklub Goud
- Voelgoednetwerk Silwer

Save perk.

Expected:
Audience index rows exist for both programmes/levels.
No duplicates.
```

### Test 11 — Removing targets

```text
Remove all selected benefit targets from a perk.
Save perk.
Expected:
All benefit_target rows for that perk are removed.
Audience rows remain only if audience rules still exist.
```

### Test 12 — Removing audience rules

```text
Remove all audience rules from a perk.
Save perk.
Expected:
All audience_target rows for that perk are removed.
Benefit rows remain only if benefit targets still exist.
```

### Test 13 — Updating targets

```text
Change Product A to Product B.
Save perk.
Expected:
Product A row removed.
Product B row inserted.
```

### Test 14 — Trash/delete cleanup

```text
Trash or delete a perk.
Expected:
All index rows for that perk are removed.
```

### Test 15 — Reindex tool

```text
Create several perks with targets and audiences.
Clear target table manually on staging if safe.
Run admin reindex.
Expected:
Rows rebuilt from ACF fields.
```

### Test 16 — Inactive ACF

```text
If safe, temporarily disable ACF on staging.
Expected:
Plugin does not fatal.
Indexing skips safely.
Admin warning/log is created.
```

### Test 17 — Repository lookup by product

```text
Call get_perk_ids_for_target( "woo_product", PRODUCT_ID ).
Expected:
Returns IDs of perks targeting that product.
```

### Test 18 — Repository lookup by programme

```text
Call get_active_perk_ids_for_programme( "vriendinneklub" ).
Expected:
Returns active published perks indexed for Vriendinneklub.
```

### Test 19 — Active lookup

```text
Create one active and one expired perk targeting the same product.
Call get_active_perk_ids_for_target().
Expected:
Only active published perk ID returned.
```

---

### Test 20 — Configuration provider unavailable

```text
Disable ACF on staging if safe, or simulate AcfConfigProvider unavailable.
Expected: plugin does not fatal.
Expected: manual reindex reports provider_unavailable.
Expected: index table is not corrupted with empty guessed rows.
Expected: existing index rows are not deleted during normal page load.
```

### Test 21 — Programme alias normalization

```text
Configure a programme alias, for example:
old_programme_key => vriendinneklub

Create a perk audience rule using the alias if the admin/import path allows it.
Run index.

Expected:
- audience row stores target_value = vriendinneklub
- no row stores target_value = old_programme_key
- reindex report notes alias normalization if diagnostics are enabled
```

### Test 22 — Unknown programme fails closed

```text
Create or simulate a perk audience rule referencing an unknown programme key.
Run index.

Expected:
- no valid programme row is inserted for the unknown key
- warning is reported as unknown_programme_reference
- plugin does not fatal
```

### Test 23 — Future configured programme indexing

```text
Add a temporary enabled programme through Programme Settings.
Add at least one enabled level mapping.
Create a display-only test perk targeting that programme.
Run index.

Expected:
- programme audience row stores the new configured programme_key
- programme_level row stores the configured programme_key:level_key
- no code changes are required
```

### Test 24 — Future mapped level indexing

```text
Add a new enabled level mapping under an existing programme.
Create a perk targeted to that level.
Run index.

Expected:
- programme_level row uses the new configured level_key
- no hardcoded Goud/Silwer/Brons assumptions are required
```

### Test 25 — Mapping hash stale detection

```text
Index a perk.
Change programme/level mapping.
Inspect admin reindex tool or debug meta.

Expected:
- current mapping hash differs from _vgmp_last_indexed_programme_mapping_hash
- affected perk is flagged for reindex or reindexed by the tool
```

### Test 26 — Unknown/legacy row reporting

```text
Create or simulate an index row with an old/unknown programme_key.
Run admin diagnostics or repository legacy-row check.

Expected:
- row appears as unknown/legacy
- system does not silently remap it without explicit alias/migration rule
```


## 34. Acceptance Criteria

VS-006 is complete only when:

```text
1. wp_vgmp_perk_targets table is created safely on activation/schema upgrade.
2. Schema version is stored in wp_options.
3. Table uses NOT NULL/default values for target_id and target_value.
4. Unique key prevents duplicate rows even for value-only targets.
5. Index rows sync when a vg_perk is saved.
6. Benefit target rows are removed when benefit targets are removed.
7. Audience target rows are removed when audience rules are removed.
8. All index rows are removed when a perk is trashed/deleted.
9. Product, category, course, event, ticket, page, post, and external URL targets are supported.
10. Programme and programme-level audience rows are supported.
11. Audience validation uses ProgrammeMappingRepository, not a hardcoded launch-only programme/level list.
12. Programme aliases normalize before indexing and are not stored as canonical table values.
13. Level aliases normalize before indexing and are not stored as canonical table values.
14. Unknown or disabled programme/level references are skipped and reported.
15. Invalid target data is skipped safely.
16. Invalid audience data is skipped safely.
17. Admin can see target/audience counts on the Member Perks list screen.
18. Admin can run a reindex tool.
19. Programme mapping changes can flag or trigger reindex of stale audience rows.
20. Repository methods exist for later Woo/Masteriyo/Tickera/claim slices.
21. Unknown/legacy audience rows can be reported for admin review.
22. No Woo discounts, Masteriyo access, Tickera tickets, or claims are implemented.
23. Plugin does not fatal if Woo, PMPro, Masteriyo, Tickera, ACF, Bricks, or Etch are inactive.
24. README is updated with table purpose, reindex instructions, mapping-flexibility behaviour, and testing notes.
```

---

## 35. README Update Requirements

Update README with:

```text
VS-006 implemented features
Database table name
Schema version behaviour
Supported benefit target types
Supported audience target types
How indexing works
When indexing runs
How to run the reindex tool
How to verify target rows
How to detect stale rows
How audience rows store canonical configured programme/level keys
How programme and level aliases are normalized
How mapping changes affect reindexing
How unknown/legacy rows are reported
Known limitations
Next slice: VS-007 — Claims and Redemption Ledger
```

Include this warning:

```text
ACF fields are the admin source of truth.
The vgmp_perk_targets table is the runtime lookup index.
If index rows become stale, run the reindex tool.
```

Include this warning:

```text
Audience rows are not user eligibility decisions.
They are indexed audience configuration only.
User eligibility still depends on the MembershipProfile resolver and later execution checks.
```

Include this warning:

```text
The table stores canonical configured programme_key and level_key values only.
Aliases are allowed only as input normalization rules and must not be stored as target_value rows.
Unknown or unmapped values fail closed and are reported.
```

---

## 36. Explicit Out-of-Scope List

Do not implement these in VS-006:

```text
WooCommerce discounts
cart price adjustment
Woo price display messages
coupon compatibility
order item meta
claim/redemption table
claim buttons
Masteriyo enrolment
course access changes
Tickera ticket generation
R0 checkout
reports dashboard
email notifications
frontend hub redesign
object cache/Redis cache layer
user-specific eligibility caching
```

These belong to later vertical slices.

---

## 37. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Database table created
Actual table name
Schema version stored
Hooks used
Admin tools added
Repository methods added
Benefit target types tested
Audience target types tested
Manual tests performed
Any skipped integrations
Any assumptions about ACF field names
Any legacy table detected
ProgrammeMappingRepository validation behaviour
Programme aliases tested
Level aliases tested
Mapping hash/stale-index behaviour tested
Unknown/legacy audience rows detected
Any limitations
```

If ACF field names differ from this pack, list the actual field names used.

If a target type could not be verified because the relevant plugin/CPT was absent on staging, state that clearly.

Do not pretend targets were tested if the plugin data was not available.

---

## 37A. Programme Flexibility Developer Notes

This slice must remain an indexer, not a membership resolver.

Correct long-term relationship:

```text
ProgrammeMappingRepository = knows configured programmes/levels/aliases
PerkConfigRepository = knows perk target/audience configuration
wp_vgmp_perk_targets = rebuildable lookup projection
MembershipProfileResolver = resolves user membership from PMPro
PerkAudienceEvaluator = evaluates a specific user against indexed/configured audience rules
```

Do not collapse these responsibilities.

The key safety rule:

```text
Index canonical configured values.
Never guess.
Never let unmapped PMPro levels grant perks.
Never let unknown programme keys create themselves.
```

---

## 38. Next Slice

After VS-006 is complete and tested, proceed to:

```text
VS-007 — Claims and Redemption Ledger
```

VS-007 will create the claim/redemption authority before any WooCommerce discounts, Masteriyo grants, or Tickera ticket benefits are executed.

Do not start VS-007 during this slice.
