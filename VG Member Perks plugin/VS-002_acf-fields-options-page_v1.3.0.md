# FEATURE PLANNING PACK: VS-002 — ACF Fields and Options Page for Voelgoed Member Perks

```text
Document version: 1.3.0
Status: Hardened replacement for VS-002 v1.0.0 with ACF runtime-boundary and programme-flexibility patches
Depends on: VS-000 v1.1.0, VS-001 v1.2.0, PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0
Next slice: VS-003 — PMPro Membership Profile Resolver
Plugin target version after this slice: 0.2.0
```

---

## 0. Patch Summary from v1.0.0

This version replaces the older single-group assumptions with a programme-aware configuration model.

Major changes:

```text
1. Replaced `level_group` thinking with a first-class Programme + Level model.
2. Added support for users who can belong to:
   - no programme
   - Voelgoed Vriendinneklub only
   - Voelgoednetwerk only
   - both programmes at the same time

3. Added default launch programme keys:
   - vriendinneklub
   - voelgoednetwerk

4. Replaced single hub setting with:
   - main member dashboard page
   - Vriendinneklub hub page
   - Voelgoednetwerk hub page
   - optional upsell pages per programme

5. Added programme-aware audience fields on each perk.
6. Added `perk_claim_scope`.
7. Added no-stacking and best-benefit defaults.
8. Removed required dependency on `vg_perk_level_group`.
9. Added feature-flag settings for later execution modules.
10. Hardened ACF JSON strategy so the plugin does not hijack unrelated ACF JSON paths.
11. Added migration/legacy notes for older `vgmp_level_group_mappings` fields.
```

Old VS-002 used `vgmp_hub_page_id` and `vgmp_level_group_mappings`. Those are now deprecated and must not be used as the future runtime model.



---

### v1.2.0 ACF runtime-boundary hardening summary

This version applies `VGMP_ACF_RUNTIME_BOUNDARY_PATCHLIST_v1.0.0`.

```text
12. Added the decision to keep ACF Pro for MVP admin editing.
13. Added the ACF Runtime Boundary Doctrine.
14. Added ConfigProviderInterface / AcfConfigProvider / PerkConfigRepository / SettingsRepository concepts.
15. Clarified that runtime/execution modules must fail closed if configuration cannot be loaded.
16. Added field ownership rules so only provider/repository classes depend on ACF field names.
17. Added a future migration path from ACF to custom admin configuration without rewriting execution modules.
```

---

### v1.3.0 programme-flexibility hardening summary

This version applies `VGMP_PROGRAMME_FLEXIBILITY_PATCHLIST_v1.0.0` and aligns VS-002 with `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

```text
18. Reframed Vriendinneklub and Voelgoednetwerk as launch/default programme rows, not the only possible programmes forever.
19. Extended programme settings with aliases, hub support flags, programme hub/upsell page IDs, notification support flags, and internal notes.
20. Clarified that dedicated Vriendinneklub/Netwerk hub fields are launch convenience fields, not the long-term architecture for every future programme.
21. Extended programme level mappings with normalized PMPro level IDs, level aliases, billing notes, member-cap fields, and mapping metadata.
22. Marked monthly/yearly/extra PMPro ID fields as input conveniences that normalize into the canonical `pmpro_level_ids` field.
23. Added validation warnings for duplicate PMPro IDs, unmapped PMPro levels, alias collisions, duplicate level keys, and Woo products that grant unmapped levels.
24. Added future-change workflows for adding billing terms, access levels, and programmes without code changes where the architecture supports it.
25. Added the rule that auto-discovery may suggest mappings to admins, but only enabled mapped PMPro levels grant perks.
```
---

## 1. Objective

Extend the existing **Voelgoed Member Perks** plugin foundation created in VS-001 by adding the admin configuration layer.

This slice must add:

```text
1. ACF field groups for the `vg_perk` CPT.
2. ACF Options Page settings for global perk and hub configuration.
3. Programme-aware settings for Voelgoed Vriendinneklub and Voelgoednetwerk.
4. PMPro level mapping configuration fields.
5. Safe admin-only validation and warnings for incomplete perk setup.
6. ACF JSON sync support inside the plugin.
```

This slice must **not** build frontend display, query loop integration, PMPro runtime membership resolution, WooCommerce discounts, Masteriyo access, Tickera claims, custom database tables, redemption tracking, emails, notifications, or reports.

The goal is to make perks manageable by admins/content managers while keeping all execution logic for later vertical slices.

---

## 2. Existing Baseline from VS-001

Assume VS-001 v1.1.0 has already created the plugin:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected existing structure:

```text
voelgoed-member-perks/
├── voelgoed-member-perks.php
├── includes/
│   ├── Plugin.php
│   ├── Activator.php
│   ├── Deactivator.php
│   ├── Content/
│   │   ├── PerkPostType.php
│   │   └── PerkTaxonomies.php
│   └── Support/
│       ├── Capabilities.php
│       └── ProgrammeRegistry.php
└── README.md
```

Expected existing CPT:

```text
vg_perk
```

Expected existing taxonomies from hardened VS-001:

```text
vg_perk_programme
vg_perk_type
vg_perk_section
vg_perk_runtime_status
vg_perk_campaign
```

Important:

```text
`vg_perk_level_group` is not a required taxonomy anymore.
```

If a legacy `vg_perk_level_group` taxonomy exists from an older build, do not delete it in VS-002. Mark it legacy/deprecated and do not use it as the future authority.

Before implementing VS-002, inspect the existing plugin files and do not duplicate classes, hooks, constants, field keys, or registration logic.

---

## 3. Site Context

The staging site uses, or may use:

```text
WordPress
WooCommerce
Woo Subscriptions
Paid Memberships Pro
Masteriyo
Tickera
Tickera WooCommerce Bridge
ACF Pro
Bricks Builder / Etch
Novamira / Novamira Pro
LiteSpeed / OpenLiteSpeed
Cloudflare
```

This plugin will initially manage perks for the current Voelgoed launch programmes:

```text
Launch programme 1:
programme_key: vriendinneklub
label: Voelgoed Vriendinneklub
example launch levels: Brons / Silwer or Silwer / Goud
billing terms: currently monthly/yearly through Woo subscription variations

Launch programme 2:
programme_key: voelgoednetwerk
label: Voelgoednetwerk
example launch levels: Brons / Silwer or Silwer / Goud / Platinum
billing terms: currently monthly/yearly through Woo subscription variations
```

These are **launch defaults**, not a permanent hardcoded universe.

The flexible model is defined in:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Core rule:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

A user may have one active resolved level per enabled programme.

Examples:

```text
No programme:
- no active mapped Vriendinneklub level
- no active mapped Voelgoednetwerk level

Vriendinneklub only:
- vriendinneklub:goud

Voelgoednetwerk only:
- voelgoednetwerk:silwer

Both launch programmes:
- vriendinneklub:goud
- voelgoednetwerk:silwer

Future configured programme:
- businessclub:founding
```

This slice only creates the admin editing and settings layer. VS-003 will parse and validate the configured PMPro mapping and produce the runtime MembershipProfile.

Important:

```text
Do not automatically treat every PMPro level as a perk level.
Do not automatically treat every Woo subscription product as a programme.
Only enabled, mapped PMPro levels count for perks.
Unknown programme keys and unmapped PMPro levels fail closed.
```

## 4. Non-Negotiable Architecture Rules

Follow these rules exactly:

```text
1. ACF is the admin editing interface, not the runtime authority.
2. Do not use an ACF Options Page as the main perk storage model.
3. Each perk remains one `vg_perk` CPT record.
4. Options Page fields are only for global settings, page references, defaults, feature flags, and mapping.
5. Do not implement PMPro runtime membership checks in this slice.
6. Do not implement WooCommerce discounts in this slice.
7. Do not implement Masteriyo course access in this slice.
8. Do not implement Tickera ticket logic in this slice.
9. Do not implement frontend shortcodes in this slice.
10. Do not implement Bricks/Etch query providers in this slice.
11. Do not create custom database tables in this slice.
12. Do not create redemption/claim records in this slice.
13. Do not modify the theme.
14. Do not modify functions.php.
15. Do not edit existing custom snippets.
16. Do not make destructive database changes.
17. If ACF Pro is inactive, plugin must not fatal-error.
18. Do not assume one user can belong to only one membership programme.
19. Do not assume monthly and yearly PMPro levels are different benefits.
20. Do not allow programme benefit stacking by default.
21. Do not hardcode PMPro level IDs, PMPro group IDs, Woo product IDs, Woo variation IDs, or billing terms in execution paths.
22. Do not assume the current launch level names are the only future level names.
23. Do not assume the current two launch programmes are the only possible future programmes.
24. Only enabled mapped PMPro levels may grant perks.
25. Auto-discovery may suggest configuration to admins, but it must not become runtime authority.
```

---

## 4A. Decision: Keep ACF for MVP Admin Editing

For the first production-ready implementation, **ACF Pro remains the admin editing layer** for perk fields and global settings. The plugin will not build a full custom metabox/settings framework in this slice set.

Reason:

```text
1. ACF dramatically reduces admin UI build time.
2. Management needs editable fields quickly.
3. Custom repeaters, relationship selectors, options pages, conditional logic, and validation UI would significantly increase scope.
4. Runtime services are abstracted so ACF can be replaced later if needed.
```

This is **not** permission for execution code to call ACF directly. ACF is the MVP admin provider, not the runtime law.

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

## Shared Configuration Abstractions

The following abstractions are part of the architecture from this point forward.

```text
ConfigProviderInterface
    Low-level provider interface for reading stored configuration.
    Current provider: AcfConfigProvider.
    Future provider: custom admin table/metabox provider.

AcfConfigProvider
    The only runtime-adjacent class allowed to call ACF functions directly.
    Reads raw ACF/post-meta/options data and reports provider availability.

PerkConfigRepository
    Approved runtime access layer for perk configuration.
    Loads, normalizes, validates, and returns safe PerkConfig / ClaimPolicy / AudienceRules data.

SettingsRepository
    Approved runtime access layer for global plugin settings.
    Hides whether settings come from ACF Options Page, wp_options, or a future custom screen.

PerkConfig
    Normalized runtime-safe representation of one perk.

ClaimPolicy
    Normalized runtime-safe representation of claim mode, claim scope, limits, reset period, visibility behaviour, and target requirements.
```

Suggested files:

```text
includes/
└── Config/
    ├── ConfigProviderInterface.php
    ├── AcfConfigProvider.php
    ├── PerkConfigRepository.php
    ├── SettingsRepository.php
    ├── PerkConfig.php
    ├── ClaimPolicy.php
    └── AudienceRules.php
```

Implementation note:

```text
These may begin as lightweight interfaces/value objects/stubs, but the execution slices must depend on these contracts rather than raw ACF calls.
```

## 5. Required New Files

Add only the files needed for this slice.

Updated structure should become:

```text
voelgoed-member-perks/
├── voelgoed-member-perks.php
├── includes/
│   ├── Plugin.php
│   ├── Activator.php
│   ├── Deactivator.php
│   ├── Admin/
│   │   ├── SettingsPage.php
│   │   └── PerkMetaWarnings.php
│   ├── Content/
│   │   ├── PerkPostType.php
│   │   ├── PerkTaxonomies.php
│   │   └── AcfFields.php
│   ├── Support/
│   │   ├── Capabilities.php
│   │   └── ProgrammeRegistry.php
│   └── Config/
│       ├── ConfigProviderInterface.php
│       ├── AcfConfigProvider.php
│       ├── PerkConfigRepository.php
│       ├── SettingsRepository.php
│       ├── PerkConfig.php
│       ├── ClaimPolicy.php
│       └── AudienceRules.php
├── acf-json/
│   ├── group_vgmp_perk_details.json
│   └── group_vgmp_options.json
└── README.md
```

If VS-001 uses a slightly different structure, adapt carefully while keeping the same responsibilities.

Do not put long ACF field arrays in `Plugin.php`.

---

## 6. Version Bump

Update plugin version from:

```text
0.1.0
```

to:

```text
0.2.0
```

Update any plugin constant if VS-001 created one, for example:

```php
VGMP_VERSION = "0.2.0";
```

Do not confuse plugin version with document version.

---

## 7. ACF Dependency Handling

This plugin must support ACF Pro, but it must not fatal-error when ACF Pro is inactive.

Use safe checks:

```php
function_exists( "acf_add_local_field_group" )
function_exists( "acf_add_options_page" )
```

If ACF is inactive:

```text
1. CPT and taxonomies must still load.
2. Admin should show a clear warning to administrators.
3. No PHP fatal errors may occur.
4. No frontend breakage may occur.
5. Plugin settings and field editing may be unavailable until ACF Pro is active.
```

Suggested admin notice:

```text
Voelgoed Member Perks requires ACF Pro to manage perk fields and settings. The perk CPT will still load, but field editing is disabled until ACF Pro is active.
```

Only show this notice to users who can:

```text
manage_options
or
manage_vg_perks
```

Do not show ACF dependency notices to normal frontend users.



Additional runtime-boundary behaviour:

```text
If ACF is inactive:
- Admin editing fields/settings are unavailable.
- AcfConfigProvider reports unavailable.
- PerkConfigRepository returns null or invalid configuration.
- Execution modules must fail closed.
- Existing custom tables, ledgers, reports, and admin diagnostics should still load where possible.
```
---

## 8. ACF JSON Support

Create an `acf-json` directory inside the plugin:

```text
wp-content/plugins/voelgoed-member-perks/acf-json/
```

Register ACF JSON load/save paths so this plugin can own its field definitions.

Requirements:

```text
1. Add the plugin `acf-json` folder to ACF load paths.
2. Do not remove existing theme or site-level ACF JSON paths.
3. Do not hijack unrelated ACF field group saves.
4. Do not save all site field groups into the plugin folder.
5. Only plugin-owned field groups with keys beginning `group_vgmp_` belong in this folder.
6. If production deployment overwrites plugin files, do not rely on production admin edits as the source of truth.
```

Recommended policy:

```text
Development/staging:
- Allow saving plugin-owned ACF JSON into the plugin `acf-json` directory.

Production:
- Prefer loading plugin-owned ACF JSON from the plugin.
- Avoid uncontrolled production edits to plugin-owned field groups.
- If production edits are made, export/review them and commit them properly.
```

Implementation notes:

```text
1. Use `acf/settings/load_json` to append the plugin JSON path.
2. Use `acf/settings/save_json` carefully.
3. If possible, filter the save path only for plugin-owned field groups.
4. Preserve existing ACF JSON paths.
```

---

## 8A. Field Ownership and Runtime Access Rules

ACF field definitions are owned by the plugin for MVP, but runtime code must not depend on ACF field names directly except inside:

```text
AcfConfigProvider
PerkConfigRepository
SettingsRepository
admin-only field registration/warning classes
```

Execution modules must consume normalized objects such as:

```text
PerkConfig
ClaimPolicy
AudienceRules
PerkRule
```

Future migration away from ACF must follow this path:

```text
1. Build custom admin screens/metaboxes.
2. Implement a new ConfigProvider.
3. Keep PerkConfigRepository and SettingsRepository contracts stable.
4. Migrate stored field values.
5. Leave Woo, Claims, Masteriyo, Tickera, Reports, Cache, and Notifications execution code unchanged.
```

## 9. Class: `AcfFields`

Create:

```text
includes/Content/AcfFields.php
```

Class:

```php
Voelgoed\MemberPerks\Content\AcfFields
```

Responsibilities:

```text
1. Register ACF field groups for `vg_perk`.
2. Register ACF Options Page field groups.
3. Register ACF JSON load/save paths.
4. Fail gracefully if ACF Pro is inactive.
5. Keep field definitions namespaced and stable.
```

Do not include runtime eligibility checks in this class.

---

## 10. Class: `SettingsPage`

Create:

```text
includes/Admin/SettingsPage.php
```

Class:

```php
Voelgoed\MemberPerks\Admin\SettingsPage
```

Responsibilities:

```text
1. Register ACF Options Page if ACF Pro supports it.
2. Use the capability `manage_vg_perks`.
3. Provide a parent options page called "Perk Settings".
4. Add subpages if practical.
5. Keep settings admin-only.
```

Recommended options page:

```php
acf_add_options_page([
    "page_title" => "Voelgoed Perk Settings",
    "menu_title" => "Perk Settings",
    "menu_slug"  => "vgmp-settings",
    "capability" => "manage_vg_perks",
    "redirect"   => true,
]);
```

Recommended subpages:

```text
General Settings
Programme Mapping
Hub Settings
Display Defaults
Rules and Feature Flags
```

If ACF subpages are not available or not appropriate, use one Options Page with tabs inside the field group.

---

## 11. Class: `PerkMetaWarnings`

Create:

```text
includes/Admin/PerkMetaWarnings.php
```

Class:

```php
Voelgoed\MemberPerks\Admin\PerkMetaWarnings
```

Responsibilities for VS-002:

```text
1. Show admin warnings on the `vg_perk` edit screen if required fields are missing.
2. Do not block saving yet unless the value is obviously dangerous.
3. Do not execute perk rules yet.
4. Keep warnings informational.
5. Use programme-aware warnings.
```

Example warning cases:

```text
Start date is missing.
Benefit type is missing.
Target type is missing.
Perk audience mode is missing.
Audience mode requires programme rules but no programme rule exists.
Programme key is invalid.
Programme level key is empty where required.
Discount value is missing when benefit type requires it.
Percentage discount is greater than 100.
Start date is after end date.
Perk has no runtime status taxonomy assigned.
Perk has no section taxonomy assigned.
```

Do not warn about missing `vg_perk_level_group`. That taxonomy is deprecated.

---

## 12. ACF Field Group: `group_vgmp_perk_details`

Create a field group for the `vg_perk` post type.

Field group title:

```text
Member Perk Details
```

Field group key:

```text
group_vgmp_perk_details
```

Location rule:

```text
Post Type is equal to vg_perk
```

---

# 12.1 Tab: Schedule

## Field: `perk_start_datetime`

```text
Label: Start Date and Time
Name: perk_start_datetime
Key: field_vgmp_perk_start_datetime
Type: Date Time Picker
Required: true
Display Format: Y-m-d H:i
Return Format: Y-m-d H:i:s
```

## Field: `perk_end_datetime`

```text
Label: End Date and Time
Name: perk_end_datetime
Key: field_vgmp_perk_end_datetime
Type: Date Time Picker
Required: false
Display Format: Y-m-d H:i
Return Format: Y-m-d H:i:s
Instructions: Leave empty only for long-running perks.
```

## Field: `perk_timezone`

```text
Label: Timezone
Name: perk_timezone
Key: field_vgmp_perk_timezone
Type: Select
Required: true
Default: Africa/Johannesburg
Choices:
- Africa/Johannesburg : Africa/Johannesburg
```

## Field: `perk_manual_status`

```text
Label: Manual Status Override
Name: perk_manual_status
Key: field_vgmp_perk_manual_status
Type: Select
Required: true
Default: auto
Choices:
- auto : Auto
- paused : Paused
- archived : Archived
Instructions: Auto allows the plugin to calculate active/upcoming/expired status in VS-004.
```

---

# 12.2 Tab: Audience

This tab defines who a perk is intended for. It stores admin configuration only. VS-003 will validate PMPro mappings and produce the runtime membership profile. VS-006 will index targets. Later slices will enforce eligibility.

## Field: `perk_audience_mode`

```text
Label: Audience Mode
Name: perk_audience_mode
Key: field_vgmp_perk_audience_mode
Type: Select
Required: true
Default: programme_rules
Choices:
- programme_rules : Specific Programme / Levels
- all_active_members : Any Active Mapped Member
- display_public : Public Display Only
- manual_only : Manual / Admin Only
Instructions: Use "Specific Programme / Levels" for normal member perks. "Public Display Only" must not grant discounts, access, tickets, or claims.
```

## Field: `perk_audience_rules`

```text
Label: Programme Audience Rules
Name: perk_audience_rules
Key: field_vgmp_perk_audience_rules
Type: Repeater
Required: false
Button Label: Add Programme Rule
Conditional Logic: Show when `perk_audience_mode` is programme_rules.
Instructions: Add one row per programme rule. A perk may target Vriendinneklub, Voelgoednetwerk, or both.
```

Subfields:

```text
programme_key
Label: Programme
Name: programme_key
Key: field_vgmp_audience_programme_key
Type: Select
Required: true
Choices:
- vriendinneklub : Voelgoed Vriendinneklub
- voelgoednetwerk : Voelgoednetwerk

level_selection_mode
Label: Level Selection Mode
Name: level_selection_mode
Key: field_vgmp_audience_level_selection_mode
Type: Select
Required: true
Default: all_levels
Choices:
- all_levels : All Levels in Programme
- explicit_levels : Selected Levels Only
- minimum_rank : Minimum Level Rank

level_keys
Label: Level Keys
Name: level_keys
Key: field_vgmp_audience_level_keys
Type: Text
Required: false
Instructions: Comma-separated stable level keys. Examples: goud,silwer or brons. VS-003 will validate these against Programme Level Mapping.

minimum_level_key
Label: Minimum Level Key
Name: minimum_level_key
Key: field_vgmp_audience_minimum_level_key
Type: Text
Required: false
Instructions: Stable level key used when Level Selection Mode is Minimum Level Rank. Example: silwer.

audience_rule_label
Label: Internal Rule Label
Name: audience_rule_label
Key: field_vgmp_audience_rule_label
Type: Text
Required: false
Instructions: Admin-only note, for example "Goud launch benefit" or "Network Silwer and above".
```

Hard rules:

```text
1. A user may qualify through both programmes.
2. The same perk must not display twice for the same user.
3. The same discount must not be stacked just because the user qualifies through both programmes.
4. Later slices must resolve duplicate eligibility deterministically.
5. This slice stores rules only; it does not evaluate them.
```

---

# 12.3 Tab: Benefit

## Field: `perk_benefit_type`

```text
Label: Benefit Type
Name: perk_benefit_type
Key: field_vgmp_perk_benefit_type
Type: Select
Required: true
Choices:
- free : Free
- percentage_discount : Percentage Discount
- fixed_discount : Fixed Discount
- fixed_member_price : Fixed Member Price
- member_only_access : Member-Only Access
- display_only : Display Only
```

## Field: `perk_discount_value`

```text
Label: Discount / Price Value
Name: perk_discount_value
Key: field_vgmp_perk_discount_value
Type: Number
Required: false
Min: 0
Step: 0.01
Conditional Logic: Show when benefit type is percentage_discount, fixed_discount, or fixed_member_price.
Instructions: For percentage discounts, enter 20 for 20%. For fixed values, enter the rand amount.
```

## Field: `perk_discount_basis`

```text
Label: Discount Basis
Name: perk_discount_basis
Key: field_vgmp_perk_discount_basis
Type: Select
Required: false
Default: sale_price_if_present
Choices:
- regular_price : Regular Price
- sale_price_if_present : Sale Price If Present
- current_cart_price : Current Cart Price
Conditional Logic: Show when benefit type is percentage_discount or fixed_discount.
```

## Field: `perk_stackable`

```text
Label: Allow Stacking With Other Perks
Name: perk_stackable
Key: field_vgmp_perk_stackable
Type: True / False
Default: false
UI: true
Instructions: Default must remain off. If a dual-programme member qualifies more than once, later slices should choose the best single benefit unless this is explicitly enabled and safely supported.
```

## Field: `perk_coupon_compatible`

```text
Label: Allow Coupons With This Perk
Name: perk_coupon_compatible
Key: field_vgmp_perk_coupon_compatible
Type: True / False
Default: false
UI: true
Instructions: Default must remain off. Later WooCommerce slices will enforce this.
```

## Field: `perk_priority`

```text
Label: Perk Priority
Name: perk_priority
Key: field_vgmp_perk_priority
Type: Number
Required: false
Default: 100
Min: 1
Step: 1
Instructions: Lower numbers should win when multiple perks apply. Later slices must still use deterministic tie-break rules if priorities match.
```

## Field: `perk_dual_programme_resolution`

```text
Label: Dual-Programme Resolution
Name: perk_dual_programme_resolution
Key: field_vgmp_perk_dual_programme_resolution
Type: Select
Required: true
Default: best_single_benefit
Choices:
- best_single_benefit : Best Single Benefit
- priority_first : Priority First
- programme_scope : Use Claim Scope
Instructions: This stores admin intent only. Later slices enforce the actual calculation.
```

Recommended default:

```text
best_single_benefit
```

---

# 12.4 Tab: Target

## Field: `perk_target_type`

```text
Label: Target Type
Name: perk_target_type
Key: field_vgmp_perk_target_type
Type: Select
Required: true
Choices:
- woo_product : WooCommerce Product
- woo_product_category : WooCommerce Product Category
- masteriyo_course : Masteriyo Course
- tickera_event : Tickera Event
- tickera_ticket : Tickera Ticket
- wp_page : WordPress Page
- wp_post : WordPress Post
- external_url : External URL
- none_display_only : None / Display Only
```

## Field: `perk_target_products`

```text
Label: Target Products
Name: perk_target_products
Key: field_vgmp_perk_target_products
Type: Relationship
Post Type: product
Return Format: ID
Conditional Logic: Show when target type is woo_product.
```

If WooCommerce is inactive, this field group must not fatal-error. If product post type is unavailable, the admin should still load safely.

## Field: `perk_target_product_categories`

```text
Label: Target Product Categories
Name: perk_target_product_categories
Key: field_vgmp_perk_target_product_categories
Type: Taxonomy
Taxonomy: product_cat
Return Format: ID
Conditional Logic: Show when target type is woo_product_category.
```

## Field: `perk_target_courses`

```text
Label: Target Courses
Name: perk_target_courses
Key: field_vgmp_perk_target_courses
Type: Relationship
Post Type: detected Masteriyo course post type
Return Format: ID
Conditional Logic: Show when target type is masteriyo_course.
```

Important:

```text
If the Masteriyo course post type differs on this site, inspect it using Novamira/WP-CLI before locking the field configuration. Do not guess if the post type can be detected.
```

## Field: `perk_target_events`

```text
Label: Target Events
Name: perk_target_events
Key: field_vgmp_perk_target_events
Type: Relationship
Post Type: detected Tickera event post type
Return Format: ID
Conditional Logic: Show when target type is tickera_event.
```

Important:

```text
If the Tickera event post type differs on this site, inspect it using Novamira/WP-CLI before locking the field configuration. Do not guess if the post type can be detected.
```

## Field: `perk_target_tickets`

```text
Label: Target Tickets
Name: perk_target_tickets
Key: field_vgmp_perk_target_tickets
Type: Relationship
Post Type: product or detected Tickera ticket post type
Return Format: ID
Conditional Logic: Show when target type is tickera_ticket.
```

Important:

```text
In many Tickera + Woo Bridge setups, tickets may be WooCommerce products. Inspect staging before finalising this field.
```

## Field: `perk_target_pages`

```text
Label: Target Pages
Name: perk_target_pages
Key: field_vgmp_perk_target_pages
Type: Relationship
Post Type: page
Return Format: ID
Conditional Logic: Show when target type is wp_page.
```

## Field: `perk_target_posts`

```text
Label: Target Posts
Name: perk_target_posts
Key: field_vgmp_perk_target_posts
Type: Relationship
Post Type: post
Return Format: ID
Conditional Logic: Show when target type is wp_post.
```

## Field: `perk_external_url`

```text
Label: External URL
Name: perk_external_url
Key: field_vgmp_perk_external_url
Type: URL
Conditional Logic: Show when target type is external_url.
```

---

# 12.5 Tab: Claim Behaviour

## Field: `perk_claim_mode`

```text
Label: Claim Mode
Name: perk_claim_mode
Key: field_vgmp_perk_claim_mode
Type: Select
Required: true
Default: auto_apply
Choices:
- auto_apply : Auto-Apply
- claim_button : Claim Button
- manual_admin : Manual Admin
- display_only : Display Only
```

## Field: `perk_claim_scope`

```text
Label: Claim Scope
Name: perk_claim_scope
Key: field_vgmp_perk_claim_scope
Type: Select
Required: true
Default: per_user
Choices:
- per_user : Once per User
- per_programme : Once per User per Programme
- per_programme_level : Once per User per Programme Level
- per_order : Once per Order
- unlimited : Unlimited
Instructions: Default must be Once per User. Use per-programme only when a dual member should legitimately receive the benefit separately for Vriendinneklub and Voelgoednetwerk.
```

## Field: `perk_max_claims_per_user`

```text
Label: Max Claims Per User
Name: perk_max_claims_per_user
Key: field_vgmp_perk_max_claims_per_user
Type: Number
Required: false
Default: 1
Min: 0
Step: 1
Instructions: Leave empty or 0 for unlimited only if intentionally allowed and compatible with Claim Scope.
```

## Field: `perk_max_total_claims`

```text
Label: Max Total Claims
Name: perk_max_total_claims
Key: field_vgmp_perk_max_total_claims
Type: Number
Required: false
Min: 0
Step: 1
Instructions: Optional campaign-wide limit. Later ledger slice must enforce this atomically.
```

## Field: `perk_claim_reset_period`

```text
Label: Claim Reset Period
Name: perk_claim_reset_period
Key: field_vgmp_perk_claim_reset_period
Type: Select
Required: true
Default: never
Choices:
- never : Never
- daily : Daily
- weekly : Weekly
- monthly : Monthly
- yearly : Yearly
```

## Field: `perk_keep_visible_after_claim`

```text
Label: Keep Visible After Claim
Name: perk_keep_visible_after_claim
Key: field_vgmp_perk_keep_visible_after_claim
Type: True / False
Default: true
UI: true
```

---

# 12.6 Tab: Hub Card Display

## Field: `perk_card_title_override`

```text
Label: Card Title Override
Name: perk_card_title_override
Key: field_vgmp_perk_card_title_override
Type: Text
Required: false
```

## Field: `perk_badge_text`

```text
Label: Badge Text
Name: perk_badge_text
Key: field_vgmp_perk_badge_text
Type: Text
Required: false
Examples: New this week, Goud only, Ends Friday
```

## Field: `perk_card_excerpt`

```text
Label: Card Excerpt
Name: perk_card_excerpt
Key: field_vgmp_perk_card_excerpt
Type: Textarea
Required: false
Rows: 3
```

## Field: `perk_cta_label`

```text
Label: CTA Label
Name: perk_cta_label
Key: field_vgmp_perk_cta_label
Type: Text
Required: false
Default: View benefit
```

## Field: `perk_cta_behaviour`

```text
Label: CTA Behaviour
Name: perk_cta_behaviour
Key: field_vgmp_perk_cta_behaviour
Type: Select
Required: true
Default: view_target
Choices:
- view_target : View Target
- add_to_cart : Add to Cart
- claim_now : Claim Now
- open_content : Open Content
- external_link : External Link
```

## Field: `perk_card_image_override`

```text
Label: Card Image Override
Name: perk_card_image_override
Key: field_vgmp_perk_card_image_override
Type: Image
Return Format: ID
Required: false
```

## Field: `perk_programme_display_grouping`

```text
Label: Programme Display Grouping
Name: perk_programme_display_grouping
Key: field_vgmp_perk_programme_display_grouping
Type: Select
Required: true
Default: auto
Choices:
- auto : Auto
- show_under_each_eligible_programme : Show Under Each Eligible Programme Section
- show_once_main_dashboard_only : Show Once on Main Dashboard Only
Instructions: Display preference only. Later hub slice must still deduplicate cards for the same user.
```

---

# 12.7 Tab: Admin Notes

## Field: `perk_internal_notes`

```text
Label: Internal Notes
Name: perk_internal_notes
Key: field_vgmp_perk_internal_notes
Type: Textarea
Required: false
Rows: 4
Instructions: Admin-only. Must never be included in member emails, hub cards, or public output.
```

## Field: `perk_reporting_tag`

```text
Label: Reporting Tag
Name: perk_reporting_tag
Key: field_vgmp_perk_reporting_tag
Type: Text
Required: false
```

---

## 13. Options Field Group: `group_vgmp_options`

Create field group:

```text
Title: Voelgoed Perk Settings
Key: group_vgmp_options
```

Location:

```text
Options Page is equal to vgmp-settings
```

If subpages are used, fields may be split into separate field groups per subpage. Keep naming consistent.

---

# 13.1 Tab: General Settings

## Field: `vgmp_default_timezone`

```text
Label: Default Timezone
Name: vgmp_default_timezone
Key: field_vgmp_default_timezone
Type: Select
Default: Africa/Johannesburg
Choices:
- Africa/Johannesburg : Africa/Johannesburg
```

## Field: `vgmp_enable_logging`

```text
Label: Enable Plugin Logging
Name: vgmp_enable_logging
Key: field_vgmp_enable_logging
Type: True / False
Default: true
UI: true
```

## Field: `vgmp_enable_admin_debug_badges`

```text
Label: Enable Admin Debug Badges
Name: vgmp_enable_admin_debug_badges
Key: field_vgmp_enable_admin_debug_badges
Type: True / False
Default: false
UI: true
Instructions: Later slices may use this to show debug information to administrators only.
```

## Field: `vgmp_environment_mode`

```text
Label: Environment Mode
Name: vgmp_environment_mode
Key: field_vgmp_environment_mode
Type: Select
Required: true
Default: auto
Choices:
- auto : Auto Detect
- production : Production
- staging : Staging
- development : Development
Instructions: Later slices, especially notifications and execution modules, must use this for safety.
```

---

# 13.2 Tab: Programme Settings

These fields define enabled programme records.

The launch defaults are:

```text
vriendinneklub
voelgoednetwerk
```

However, future programmes may be added if they are configured, mapped, and tested according to `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

## Field: `vgmp_programme_settings`

```text
Label: Programme Settings
Name: vgmp_programme_settings
Key: field_vgmp_programme_settings
Type: Repeater
Button Label: Add Programme
Required: true
Instructions: The default rows should be Vriendinneklub and Voelgoednetwerk. Future programme rows may be added through configuration. Do not rename programme keys after launch; add aliases instead.
```

Subfields:

```text
programme_key
Label: Programme Key
Name: programme_key
Key: field_vgmp_programme_settings_programme_key
Type: Text
Required: true
Instructions: Stable lowercase key. Examples: vriendinneklub, voelgoednetwerk, businessclub. Do not use spaces. Do not change after launch unless a migration plan exists.

programme_label
Label: Programme Label
Name: programme_label
Key: field_vgmp_programme_settings_programme_label
Type: Text
Required: true
Examples:
- Voelgoed Vriendinneklub
- Voelgoednetwerk
- Voelgoed Business Club

programme_aliases
Label: Programme Aliases
Name: programme_aliases
Key: field_vgmp_programme_settings_programme_aliases
Type: Text
Required: false
Instructions: Optional comma-separated legacy/external keys that should normalize to this programme. Example: old_programme_key,vriendinne. Aliases must not create separate programmes.

programme_enabled
Label: Programme Enabled
Name: programme_enabled
Key: field_vgmp_programme_settings_programme_enabled
Type: True / False
Default: true
UI: true

programme_sort_order
Label: Programme Sort Order
Name: programme_sort_order
Key: field_vgmp_programme_settings_programme_sort_order
Type: Number
Default: 100
Min: 1
Step: 1

programme_supports_hub
Label: Enable Programme Hub
Name: programme_supports_hub
Key: field_vgmp_programme_settings_programme_supports_hub
Type: True / False
Default: true
UI: true
Instructions: If disabled, the programme may still exist for eligibility/reporting, but a dedicated hub should not be exposed.

programme_hub_page_id
Label: Programme Hub Page
Name: programme_hub_page_id
Key: field_vgmp_programme_settings_programme_hub_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false
Instructions: Preferred future-safe hub page reference for this programme. Launch shortcut fields may also exist for Vriendinneklub and Voelgoednetwerk.

programme_upsell_page_id
Label: Programme Upsell Page
Name: programme_upsell_page_id
Key: field_vgmp_programme_settings_programme_upsell_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false

programme_supports_notifications
Label: Enable Programme Notifications
Name: programme_supports_notifications
Key: field_vgmp_programme_settings_programme_supports_notifications
Type: True / False
Default: false
UI: true
Instructions: VS-014 must also be enabled before notifications are sent.

programme_notes
Label: Internal Programme Notes
Name: programme_notes
Key: field_vgmp_programme_settings_programme_notes
Type: Textarea
Required: false
Rows: 3
Instructions: Admin-only notes. Do not render publicly.
```

Default launch rows:

```text
programme_key: vriendinneklub
programme_label: Voelgoed Vriendinneklub
programme_aliases: old_programme_key,vriendinne
programme_enabled: true
programme_sort_order: 10
programme_supports_hub: true

programme_key: voelgoednetwerk
programme_label: Voelgoednetwerk
programme_aliases: netwerk
programme_enabled: true
programme_sort_order: 20
programme_supports_hub: true
```

Validation rules:

```text
1. programme_key must be unique.
2. programme_key must be lowercase and stable.
3. programme_aliases must not collide with another programme_key or alias.
4. Disabled programmes must not grant new perks.
5. Unknown programme keys must fail closed in later runtime slices.
6. Aliases are input normalization only; stored runtime/index values must use canonical configured programme_key.
```

# 13.3 Tab: Programme Level Mapping

These fields map raw PMPro levels to stable configured programme level keys.

Important:

```text
Do not implement PMPro checks yet.
Only store the mapping fields in this slice.
VS-003 will parse, validate, and resolve the active MembershipProfile.
```

## Field: `vgmp_programme_level_mappings`

```text
Label: Programme Level Mappings
Name: vgmp_programme_level_mappings
Key: field_vgmp_programme_level_mappings
Type: Repeater
Button Label: Add Programme Level Mapping
Required: false
Instructions: Each row maps one stable programme level key to one or more PMPro level IDs that grant the same access level.
```

Subfields:

```text
programme_key
Label: Programme
Name: programme_key
Key: field_vgmp_mapping_programme_key
Type: Select or dynamically populated Select
Required: true
Choices:
- Load from enabled `vgmp_programme_settings` rows
Instructions: For launch, this normally includes Vriendinneklub and Voelgoednetwerk. Future programmes must appear automatically from Programme Settings, not from hardcoded field definitions.

level_key
Label: Level Key
Name: level_key
Key: field_vgmp_mapping_level_key
Type: Text
Required: true
Instructions: Stable lowercase key. Examples: brons, silwer, goud, platinum, founding, corporate. Do not use spaces. Do not rename after launch unless a migration plan exists.

level_label
Label: Level Label
Name: level_label
Key: field_vgmp_mapping_level_label
Type: Text
Required: true
Examples: Brons, Silwer, Silwer, Goud, Platinum, Founding Member

level_aliases
Label: Level Aliases
Name: level_aliases
Key: field_vgmp_mapping_level_aliases
Type: Text
Required: false
Instructions: Optional comma-separated aliases/display/import values. Example: goud,goud or silwer,silwer. Aliases must be unique inside the same programme.

level_rank
Label: Level Rank
Name: level_rank
Key: field_vgmp_mapping_level_rank
Type: Number
Required: true
Default: 100
Min: 1
Step: 1
Instructions: Lower number = stronger/higher level. Example: Goud 10, Silwer/Silwer 20, Brons 30.

pmpro_level_ids
Label: PMPro Level IDs
Name: pmpro_level_ids
Key: field_vgmp_mapping_pmpro_level_ids
Type: Text
Required: false
Instructions: Canonical comma-separated PMPro level IDs that grant this access level. Example: 17,27. This is the normalized field later resolvers should use.

pmpro_monthly_level_ids
Label: PMPro Monthly Level IDs
Name: pmpro_monthly_level_ids
Key: field_vgmp_mapping_pmpro_monthly_level_ids
Type: Text
Required: false
Instructions: Optional input convenience only. These IDs should normalize into `pmpro_level_ids`. Example: 17.

pmpro_yearly_level_ids
Label: PMPro Yearly Level IDs
Name: pmpro_yearly_level_ids
Key: field_vgmp_mapping_pmpro_yearly_level_ids
Type: Text
Required: false
Instructions: Optional input convenience only. These IDs should normalize into `pmpro_level_ids`. Example: 27.

pmpro_extra_level_ids
Label: PMPro Extra Level IDs
Name: pmpro_extra_level_ids
Key: field_vgmp_mapping_pmpro_extra_level_ids
Type: Text
Required: false
Instructions: Optional comma-separated IDs for migration/legacy/manual cases. These IDs should also normalize into `pmpro_level_ids`.

is_paid_level
Label: Paid Membership Level
Name: is_paid_level
Key: field_vgmp_mapping_is_paid_level
Type: True / False
Default: true
UI: true

mapping_enabled
Label: Mapping Enabled
Name: mapping_enabled
Key: field_vgmp_mapping_enabled
Type: True / False
Default: true
UI: true

member_cap
Label: Member Cap
Name: member_cap
Key: field_vgmp_mapping_member_cap
Type: Number
Required: false
Min: 0
Step: 1
Instructions: Optional display/reference cap. Do not enforce checkout caps from this field unless a later slice explicitly implements that policy.

cap_mode
Label: Cap Mode
Name: cap_mode
Key: field_vgmp_mapping_cap_mode
Type: Select
Required: false
Default: none
Choices:
- none : None
- display_only : Display Only
- enforced_future : Reserved for Future Enforcement
Instructions: For the current setup, Netwerk caps are display/reference only unless a future slice implements enforcement.

billing_notes
Label: Billing Notes
Name: billing_notes
Key: field_vgmp_mapping_billing_notes
Type: Textarea
Required: false
Rows: 3
Instructions: Optional notes about Woo products/variations that sell this level. Runtime eligibility must still use active PMPro membership, not Woo product IDs.

mapping_notes
Label: Internal Mapping Notes
Name: mapping_notes
Key: field_vgmp_mapping_notes
Type: Textarea
Required: false
Rows: 3
```

Recommended launch mapping rows may include:

```text
vriendinneklub:brons
vriendinneklub:silwer or vriendinneklub:silwer
vriendinneklub:goud

voelgoednetwerk:brons
voelgoednetwerk:silwer or voelgoednetwerk:silwer
voelgoednetwerk:goud
voelgoednetwerk:platinum
```

Important:

```text
The actual final level keys must match the site decision.
Do not mix `silwer` and `silwer` accidentally. Pick one canonical key and use aliases for the other.
Do not invent final Voelgoednetwerk level names if they are not confirmed. Use staging data and document the actual mapping.
```

Normalization rules:

```text
1. `pmpro_level_ids` is the canonical normalized PMPro ID field.
2. `pmpro_monthly_level_ids`, `pmpro_yearly_level_ids`, and `pmpro_extra_level_ids` are input conveniences.
3. VS-003 must merge all PMPro ID fields, remove duplicates, validate IDs, and expose one normalized list per enabled level mapping.
4. Woo subscription products/variations are billing surfaces and must not become runtime perk authority.
5. Adding a new billing term for the same access level should usually not require a new VGMP level mapping if it grants the same PMPro level.
```

Legacy field:

```text
vgmp_level_group_mappings
```

Handling:

```text
1. Treat as deprecated.
2. Do not use for new logic.
3. If existing values are found, show admin migration warning.
4. Do not delete the old option automatically in VS-002.
5. VS-003 may include a read-only migration helper if needed.
```

Do not auto-discover everything:

```text
1. Do not automatically treat every PMPro level as a perk level.
2. Do not automatically treat every Woo subscription product as a programme.
3. Auto-discovery may be used for admin suggestions and warnings only.
4. Only enabled mapped PMPro levels count for runtime perks.
5. Unmapped active PMPro levels must fail closed and appear in diagnostics.
```

# 13.4 Tab: Hub Settings

Replace the old single hub page setting with a main dashboard and programme-aware hub settings.

The current launch setup may use dedicated convenience fields for:

```text
/my-voordele/
/my-vriendinneklub/
/my-voelgoed-netwerk/
```

However, future programme hubs should be represented through the configured programme rows in `vgmp_programme_settings`:

```text
programme_supports_hub
programme_hub_page_id
programme_upsell_page_id
```

## Field: `vgmp_main_dashboard_page_id`

```text
Label: Main Member Dashboard Page
Name: vgmp_main_dashboard_page_id
Key: field_vgmp_main_dashboard_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false
Instructions: Recommended URL: /my-voordele/. This page may show all configured programmes for the current user.
```

## Field: `vgmp_vriendinneklub_hub_page_id`

```text
Label: Vriendinneklub Hub Page
Name: vgmp_vriendinneklub_hub_page_id
Key: field_vgmp_vriendinneklub_hub_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false
Instructions: Launch convenience field for /my-vriendinneklub/. Future-safe code should normalize this into the Vriendinneklub programme definition.
```

## Field: `vgmp_voelgoednetwerk_hub_page_id`

```text
Label: Voelgoednetwerk Hub Page
Name: vgmp_voelgoednetwerk_hub_page_id
Key: field_vgmp_voelgoednetwerk_hub_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false
Instructions: Launch convenience field for /my-voelgoed-netwerk/. Future-safe code should normalize this into the Voelgoednetwerk programme definition.
```

## Field: `vgmp_vriendinneklub_upsell_page_id`

```text
Label: Vriendinneklub Upsell Page
Name: vgmp_vriendinneklub_upsell_page_id
Key: field_vgmp_vriendinneklub_upsell_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false
Instructions: Launch convenience field. Prefer programme_upsell_page_id inside the programme settings row for future programmes.
```

## Field: `vgmp_voelgoednetwerk_upsell_page_id`

```text
Label: Voelgoednetwerk Upsell Page
Name: vgmp_voelgoednetwerk_upsell_page_id
Key: field_vgmp_voelgoednetwerk_upsell_page_id
Type: Page Link or Post Object
Post Type: page
Return Format: ID
Required: false
Instructions: Launch convenience field. Prefer programme_upsell_page_id inside the programme settings row for future programmes.
```

## Field: `vgmp_hub_intro_text`

```text
Label: Main Dashboard Intro Text
Name: vgmp_hub_intro_text
Key: field_vgmp_hub_intro_text
Type: WYSIWYG or Textarea
Required: false
```

## Field: `vgmp_vriendinneklub_intro_text`

```text
Label: Vriendinneklub Hub Intro Text
Name: vgmp_vriendinneklub_intro_text
Key: field_vgmp_vriendinneklub_intro_text
Type: WYSIWYG or Textarea
Required: false
Instructions: Launch convenience field. Future programmes should use programme-level content settings or template content, not new hardcoded global fields.
```

## Field: `vgmp_voelgoednetwerk_intro_text`

```text
Label: Voelgoednetwerk Hub Intro Text
Name: vgmp_voelgoednetwerk_intro_text
Key: field_vgmp_voelgoednetwerk_intro_text
Type: WYSIWYG or Textarea
Required: false
Instructions: Launch convenience field. Future programmes should use programme-level content settings or template content, not new hardcoded global fields.
```

## Field: `vgmp_empty_state_message`

```text
Label: Empty State Message
Name: vgmp_empty_state_message
Key: field_vgmp_empty_state_message
Type: Textarea
Default: There are no active benefits available for your membership right now.
```

## Field: `vgmp_no_membership_message`

```text
Label: No Membership Message
Name: vgmp_no_membership_message
Key: field_vgmp_no_membership_message
Type: Textarea
Default: Log in or join a Voelgoed membership programme to see your available benefits.
```

Legacy field:

```text
vgmp_hub_page_id
```

Handling:

```text
1. Treat as deprecated.
2. Do not use for new hub logic.
3. If set, show admin migration warning suggesting `vgmp_main_dashboard_page_id`.
4. Do not delete automatically in VS-002.
```

Future-safe hub rule:

```text
SettingsRepository should expose normalized hub configuration per enabled programme.
Display slices must not hardcode only two programme hub pages.
```

# 13.5 Tab: Display Defaults

## Field: `vgmp_default_cta_label`

```text
Label: Default CTA Label
Name: vgmp_default_cta_label
Key: field_vgmp_default_cta_label
Type: Text
Default: View benefit
```

## Field: `vgmp_default_card_image`

```text
Label: Default Card Image
Name: vgmp_default_card_image
Key: field_vgmp_default_card_image
Type: Image
Return Format: ID
Required: false
```

## Field: `vgmp_deduplicate_shared_perks`

```text
Label: Deduplicate Shared Perks
Name: vgmp_deduplicate_shared_perks
Key: field_vgmp_deduplicate_shared_perks
Type: True / False
Default: true
UI: true
Instructions: Dual-programme users should not see the same perk card duplicated.
```

---

# 13.6 Tab: Rules and Feature Flags

## Field: `vgmp_default_stackable`

```text
Label: Allow Perks to Stack by Default
Name: vgmp_default_stackable
Key: field_vgmp_default_stackable
Type: True / False
Default: false
UI: true
```

## Field: `vgmp_default_coupon_compatible`

```text
Label: Allow Coupons with Perks by Default
Name: vgmp_default_coupon_compatible
Key: field_vgmp_default_coupon_compatible
Type: True / False
Default: false
UI: true
```

## Field: `vgmp_default_claim_scope`

```text
Label: Default Claim Scope
Name: vgmp_default_claim_scope
Key: field_vgmp_default_claim_scope
Type: Select
Default: per_user
Choices:
- per_user : Once per User
- per_programme : Once per User per Programme
- per_programme_level : Once per User per Programme Level
- per_order : Once per Order
- unlimited : Unlimited
```

## Field: `vgmp_default_claims_per_user`

```text
Label: Default Claims Per User
Name: vgmp_default_claims_per_user
Key: field_vgmp_default_claims_per_user
Type: Number
Default: 1
Min: 0
Step: 1
```

## Field: `vgmp_enable_hub_display`

```text
Label: Enable Hub Display Module
Name: vgmp_enable_hub_display
Key: field_vgmp_enable_hub_display
Type: True / False
Default: false
UI: true
Instructions: Later slice will implement this. Default false.
```

## Field: `vgmp_enable_woo_discounts`

```text
Label: Enable WooCommerce Discount Module
Name: vgmp_enable_woo_discounts
Key: field_vgmp_enable_woo_discounts
Type: True / False
Default: false
UI: true
Instructions: Later Woo slice will implement this. Default false.
```

## Field: `vgmp_enable_course_claims`

```text
Label: Enable Masteriyo Course Claims
Name: vgmp_enable_course_claims
Key: field_vgmp_enable_course_claims
Type: True / False
Default: false
UI: true
Instructions: Later Masteriyo slice will implement this. Default false.
```

## Field: `vgmp_enable_ticket_claims`

```text
Label: Enable Tickera Ticket Claims
Name: vgmp_enable_ticket_claims
Key: field_vgmp_enable_ticket_claims
Type: True / False
Default: false
UI: true
Instructions: Later Tickera slice will implement this. Default false.
```

## Field: `vgmp_enable_notifications`

```text
Label: Enable Notifications Module
Name: vgmp_enable_notifications
Key: field_vgmp_enable_notifications
Type: True / False
Default: false
UI: true
Instructions: VS-014 will implement notifications. Default false.
```

---

## 14. Validation and Sanitisation Requirements

For VS-002, do not block saves unless there is an obvious dangerous value.

However, add admin warnings for:

```text
1. Missing start date.
2. Missing benefit type.
3. Missing target type.
4. Missing audience mode.
5. Audience mode = programme_rules but no programme audience rules exist.
6. Programme key cannot normalize to an enabled configured programme.
7. Level keys are malformed.
8. Audience rule references a disabled/unknown programme.
9. Audience rule references a disabled/unknown level.
10. Discount value missing when benefit type requires it.
11. Percentage discount greater than 100.
12. Fixed discount or fixed member price below 0.
13. Start date after end date.
14. Claim scope is unlimited while max claims fields suggest a limit.
15. No perk section taxonomy assigned.
16. No runtime status taxonomy assigned.
17. Deprecated `vgmp_hub_page_id` option still has a value.
18. Deprecated `vgmp_level_group_mappings` option still has a value.
```

Programme and mapping warnings:

```text
1. Programme has no enabled levels.
2. Programme key is duplicated.
3. Programme alias collides with another programme key or alias.
4. Programme alias would create a separate accidental programme if not normalized.
5. Level mapping has no PMPro level IDs.
6. Same PMPro level ID appears in more than one enabled mapping.
7. Same level_key appears twice inside one programme.
8. Level alias collides inside the same programme.
9. PMPro level metadata contains a programme key that does not match any configured programme key or alias.
10. Woo membership product grants an unmapped PMPro level.
11. Mapped PMPro level ID no longer exists.
12. Billing notes reference a Woo product/variation that was not found during audit.
13. Launch shortcut hub fields disagree with programme-row hub fields.
```

Warnings should appear only in admin screens and should not break saving.

Later slices may convert some warnings into hard validation.

Sanitisation expectations:

```text
1. Programme keys must be lowercase safe keys.
2. Programme aliases must be lowercase safe keys and must normalize explicitly.
3. Level keys must be lowercase safe keys.
4. Level aliases must be lowercase safe keys and must be unique inside their programme.
5. PMPro level ID lists must be parsed as positive integers by VS-003.
6. VS-003 will parse PMPro level IDs into integers and validate duplicates/conflicts.
7. URLs must be validated as URLs.
8. Text fields must be sanitized/escaped properly in admin output.
9. WYSIWYG content must not be rendered publicly in VS-002.
```

Fail-closed configuration rule:

```text
If a programme, level, PMPro ID, alias, or audience rule cannot be normalized safely, later runtime slices must not grant perks from it.
```

## 15. Admin UI Expectations

After this slice:

```text
1. Admin can create a Member Perk post.
2. Admin sees structured tabs for schedule, audience, benefit, target, claim behaviour, hub display, and notes.
3. Admin can fill out perk details without touching code.
4. Admin can configure global plugin settings via Perk Settings.
5. Admin can configure enabled programme records.
6. Admin can configure launch programmes Vriendinneklub and Voelgoednetwerk.
7. Admin can add future programme rows without code changes, subject to QA.
8. Admin can map PMPro level IDs to stable programme/level keys.
9. Admin can configure aliases for programme/key drift.
10. Admin can configure main dashboard and launch programme hub pages.
11. Admin can configure per-programme hub/upsell page references for future programmes.
12. Admin sees helpful warnings if a perk or mapping is incomplete.
13. Admin sees warnings for deprecated old settings if they exist.
14. No frontend display is required yet.
```

The admin UI must make the launch programme model obvious:

```text
Voelgoed Vriendinneklub
Voelgoednetwerk
```

But it must not imply that future programmes require code changes.

Do not hide the distinction behind a generic "membership group" label.

Do not create an overly magical auto-discovery model:

```text
Auto-discovery may help suggest mappings.
Only enabled mapped PMPro levels count.
```

## 16. Important Detection Tasks for Novamira Agent

Before finalising fields that point to plugin-specific post types, inspect the staging site.

Check:

```text
1. Is WooCommerce active?
2. Is Woo Subscriptions active?
3. Is Paid Memberships Pro active?
4. What are the current PMPro level IDs and names?
5. What PMPro groups exist?
6. Which PMPro levels belong to the Vriendinneklub launch programme?
7. Which PMPro levels belong to the Voelgoednetwerk launch programme?
8. Which PMPro metadata keys exist for programme/level mapping?
9. Is there key drift, for example `old_programme_key` vs `vriendinneklub`?
10. Which Woo subscription products/variations grant which PMPro levels?
11. Which Woo products grant unmapped PMPro levels?
12. Which mapped PMPro levels have no billing product?
13. Are there duplicate PMPro level IDs that could map to more than one configured level?
14. Is ACF Pro active?
15. Is Masteriyo active?
16. What is the exact Masteriyo course post type?
17. Is Tickera active?
18. Is Tickera WooCommerce Bridge active?
19. What is the exact Tickera event post type?
20. Are Tickera tickets represented as WooCommerce products or a separate CPT?
21. Does the site already use ACF JSON in the theme or another plugin?
22. Does the site already define ACF Options Pages?
23. Does a legacy `vg_perk_level_group` taxonomy exist?
24. Do legacy `vgmp_hub_page_id` or `vgmp_level_group_mappings` options exist?
```

Do not guess if the real post type, level IDs, product IDs, or metadata keys can be inspected.

If uncertain, implement safe fallback fields and document the uncertainty in the final report.

Detection output must distinguish:

```text
current detected PMPro/Woo setup
configured canonical programme/level model
aliases/legacy keys
unmapped external IDs
code-change risks
```

## 17. Testing Checklist

Perform these tests on staging.

### Plugin health

```text
1. Plugin activates without fatal errors.
2. Plugin deactivates without deleting data.
3. Plugin loads when ACF Pro is active.
4. Plugin loads safely when ACF Pro is inactive.
5. No frontend fatal errors.
6. No admin fatal errors.
```

### ACF field tests

```text
1. Create a new Member Perk.
2. Confirm all tabs appear.
3. Fill schedule fields.
4. Fill audience fields for Vriendinneklub only.
5. Fill audience fields for Voelgoednetwerk only.
6. Fill audience fields for both launch programmes.
7. Fill audience fields using a configured future/test programme if one exists.
8. Fill benefit fields.
9. Fill target fields.
10. Fill claim behaviour fields.
11. Fill hub display fields.
12. Save the perk.
13. Reload and confirm values persist.
14. Confirm ACF JSON files are created or loaded correctly.
```

### Options page tests

```text
1. Confirm Perk Settings page exists.
2. Confirm only authorised users can access it.
3. Add programme settings for Vriendinneklub and Voelgoednetwerk.
4. Add programme aliases where needed.
5. Add programme level mappings.
6. Add canonical `pmpro_level_ids` values.
7. Add launch main dashboard page.
8. Add Vriendinneklub hub page.
9. Add Voelgoednetwerk hub page.
10. Add per-programme hub page in the programme row.
11. Add upsell pages if available.
12. Save settings.
13. Reload and confirm values persist.
```

### Warning tests

```text
1. Create an incomplete perk.
2. Confirm warnings are displayed.
3. Add missing fields.
4. Confirm warnings reduce or disappear.
5. Confirm warnings do not block saving yet.
6. Add a malformed programme key and confirm warning.
7. Add an alias collision and confirm warning.
8. Add duplicate PMPro level ID mappings and confirm warning.
9. Add deprecated old setting values if present and confirm migration warning.
```

### Dual-programme configuration tests

```text
1. Configure Vriendinneklub Brons/Silwer-or-Silwer/Goud mappings.
2. Configure Voelgoednetwerk mappings from the real staging levels.
3. Create a perk for Vriendinneklub only.
4. Create a perk for Voelgoednetwerk only.
5. Create a perk for both launch programmes.
6. Confirm all save and reload.
7. Confirm no runtime access, discount, claim, course, ticket, or frontend behaviour is added by this slice.
```

### Future billing term configuration test

```text
1. Document or create a temporary future billing term such as quarterly for an existing level.
2. Confirm it grants the same PMPro access level.
3. Confirm no new VGMP level mapping is required if access is unchanged.
4. Confirm admin notes can record the billing variation without making Woo product/variation IDs runtime authority.
```

### Future level configuration test

```text
1. Create or simulate a new access level in the mapping UI.
2. Add a new Programme Level Mapping row.
3. Map one or more PMPro level IDs.
4. Set rank and label.
5. Save and reload.
6. Confirm no plugin code change is required for the configuration to exist.
7. Runtime behaviour remains out of scope until VS-003 and later slices.
```

### Future programme configuration test

```text
1. Add a disabled test programme row.
2. Add aliases and notes.
3. Add one test level mapping.
4. Leave hub disabled or unconfigured.
5. Save and reload.
6. Confirm admin UI does not fatal and does not assume only two programmes forever.
7. Do not mark this future programme production-ready until later hub/cache/claims/reports QA is complete.
```

## 18. Acceptance Criteria

VS-002 is complete only when all of the following are true:

```text
1. Plugin version is updated to 0.2.0.
2. Document version is updated to 1.3.0.
3. ACF field group for `vg_perk` exists and loads.
4. ACF Options Page exists and loads.
5. ACF JSON support is configured safely.
6. Perk detail fields save and reload correctly.
7. Programme-aware audience fields save and reload correctly.
8. Programme settings save and reload correctly.
9. Programme settings support launch defaults and future configured programme rows.
10. Programme aliases can be stored and validated.
11. Programme level mappings save and reload correctly.
12. Programme level mappings support canonical `pmpro_level_ids`.
13. Monthly/yearly/extra PMPro ID fields are treated as input conveniences, not separate benefit levels.
14. Level aliases can be stored and validated.
15. Main dashboard and launch programme hub page settings save and reload correctly.
16. Per-programme hub/upsell page fields exist inside programme settings.
17. Feature flag settings exist and default to false.
18. Plugin does not fatal-error if ACF Pro is inactive.
19. Admin warning system exists for incomplete perk records.
20. Admin warning system reports duplicate PMPro IDs, alias collisions, unknown programmes, and deprecated settings.
21. Admin warnings no longer depend on `vg_perk_level_group`.
22. Deprecated old settings are detected but not deleted.
23. The doc clearly states that only enabled mapped PMPro levels count for perks.
24. The doc clearly states that auto-discovery may suggest mappings but cannot grant runtime perks.
25. The doc includes future workflows for adding billing terms, levels, and programmes.
26. No frontend display logic has been added.
27. No PMPro runtime membership logic has been added.
28. No WooCommerce discount logic has been added.
29. No Masteriyo access logic has been added.
30. No Tickera ticket logic has been added.
31. No custom database tables have been created.
32. No theme or functions.php edits were made.
33. README documents programme-aware settings, ACF runtime boundary, flexibility rules, and limitations.
```

## 19. Out of Scope for VS-002

Do not build:

```text
PMPro membership profile resolver
Runtime status automation
Bricks/Etch query provider
WooCommerce discount engine
Masteriyo enrolment/access engine
Tickera ticket claim engine
Custom database tables
Redemption tracking
REST endpoints
Shortcodes
Frontend hub page
Emails or notifications
Reports dashboard
Cache layer
Cron jobs
```

These belong to later vertical slices.

---

## 20. Final Report Required from Agent

After implementation, provide a clear report with:

```text
1. Files created.
2. Files modified.
3. Plugin version confirmed.
4. Document version confirmed.
5. ACF fields registered.
6. Options Page fields registered.
7. ACF JSON path behaviour confirmed.
8. Programme settings added.
9. Programme aliases added/validated.
10. Programme level mapping fields added.
11. Canonical `pmpro_level_ids` field added.
12. Monthly/yearly/extra PMPro ID convenience fields added or preserved.
13. Level alias fields added/validated.
14. Main dashboard and launch programme hub page fields added.
15. Per-programme hub/upsell fields added.
16. Feature flag fields added.
17. Deprecated setting detection added, if applicable.
18. Detected WooCommerce status.
19. Detected Woo Subscriptions status.
20. Detected PMPro status.
21. Detected PMPro levels, if inspected.
22. Detected PMPro metadata key drift, if inspected.
23. Detected Woo subscription products/variations that grant PMPro levels, if inspected.
24. Detected unmapped PMPro levels, if inspected.
25. Detected Masteriyo course post type, if available.
26. Detected Tickera event/ticket post types, if available.
27. Any uncertainty or fallback decisions.
28. Tests performed.
29. Any errors found.
30. Confirmation that no out-of-scope logic was added.
```

Do not claim success unless the staging tests were actually performed.

If tests are documentation-only or planned-only, state that clearly.

## 21. Developer Notes

This slice exists to make the future system manageable by humans.

The correct long-term model remains:

```text
Perk CPT = individual benefit/campaign record
Taxonomies = fast grouping/filtering
ACF fields = admin-editable rule configuration
Options Page = global defaults, page references, feature flags, and mappings
Programme Registry = default launch seeds + configured programme access
Programme Mapping Repository = normalized programme/level/PMPro mapping authority
SettingsRepository = normalized runtime settings access
VS-003 MembershipProfile = runtime membership interpretation
Target Index = later fast lookup support
Claim Ledger = later claim/redemption authority
Bricks/Etch = later display layer
Woo/Masteriyo/Tickera = later execution layers
```

Keep the system boring, explicit, and testable.

The most important rules:

```text
Never let "one user = one membership group" leak back into the implementation.
Never let "current launch programmes = the only programmes forever" leak into runtime execution.
Never let Woo product IDs or variation IDs become membership authority.
Never let unmapped PMPro levels grant perks.
```

Future-change recipes:

```text
Adding a new billing term:
- Add Woo subscription variation/product.
- Link it to the existing PMPro access level.
- Do not add a new VGMP level mapping if access is unchanged.
- Test purchase, renewal, cancellation.

Adding a new access level:
- Create PMPro access-only level.
- Create/link Woo billing product/variation.
- Add Programme Level Mapping row.
- Set rank.
- Add or adjust perks that target the level.
- Reindex audience targets in VS-006.
- Clear mapping/profile caches in VS-013.
- Test hub, claims, Woo discounts, reports.

Adding a new programme:
- Add Programme Settings row.
- Add programme key, label, enabled flag, aliases if needed.
- Add Programme Level Mapping rows.
- Configure hub/upsell behaviour.
- Create perks targeted to that programme.
- Reindex audience targets.
- Clear caches.
- Test hub/display/claims/reports/notifications.
```
