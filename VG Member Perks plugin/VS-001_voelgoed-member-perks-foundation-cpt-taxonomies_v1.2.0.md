# FEATURE PLANNING PACK: VS-001 — Voelgoed Member Perks Plugin Foundation, CPT and Taxonomies

## Version

**Document version:** `1.2.0`  
**Previous version:** `1.1.0`  
**Status:** Programme-flexibility hardened foundation  
**Supersedes:** `VS-001_voelgoed-member-perks-foundation-cpt-taxonomies_v1.1.0.md`

**Related model:** `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`

---

## 1. Slice Identity

**Slice ID:** VS-001  
**Slice name:** Plugin Foundation, CPT and Taxonomies  
**Plugin name:** Voelgoed Member Perks  
**Plugin slug:** `voelgoed-member-perks`  
**Namespace:** `Voelgoed\MemberPerks`  
**Initial plugin version:** `0.1.0`  
**Primary CPT:** `vg_perk`  
**Primary admin label:** Member Perks

This slice creates the safe foundation only. It must not implement membership checks, discounts, claims, course access, ticket access, notifications, frontend hub rendering, or custom database tables.

---

## 2. Changelog from v1.1.0

Patch reason:

```text
VS-001 already introduced the dual-programme foundation, but it still reads too much like the two launch programmes are the only programmes the plugin can ever understand.

This v1.2.0 patch keeps Vriendinneklub and Voelgoednetwerk as launch/default programmes, while making the foundation future-safe for new configured programmes, new levels, and new billing options.
```

Main changes:

```text
1. Reframes `vriendinneklub` and `voelgoednetwerk` as launch/default programme keys, not an exhaustive forever-list.
2. Adds a hard doctrine: hardcode invariants, configure business structure, map external IDs, fail closed when unmapped.
3. Aligns the foundation with `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.
4. Clarifies that ProgrammeRegistry in VS-001 is a foundation/default registry, not the final runtime mapping authority.
5. Adds safe key-format and alias-normalisation expectations for future slices.
6. Prevents future agents from hardcoding "only two programmes" into CPTs, taxonomies, capabilities, or execution code.
7. Clarifies that new subscription billing terms must not require new PMPro levels or code changes.
8. Clarifies that new business levels must be added through configuration/mapping, not foundation code.
```

Important design decision:

```text
VS-001 may define launch defaults, but later runtime slices must read enabled programmes and levels from the configured mapping layer.

The foundation must not block future programmes merely because they are not one of the two launch defaults.
```

---

## 2. Changelog from v1.0.0

Patch reasons:

```text
The site must support users who can belong to both membership programmes at the same time:
- Voelgoed Vriendinneklub
- Voelgoednetwerk

A user may have one active business level per programme, for example:
- Vriendinneklub: Goud
- Voelgoednetwerk: Silwer
```

Changes in this version:

```text
1. Adds canonical programme keys at foundation level.
2. Adds a small ProgrammeRegistry class/stub.
3. Clarifies that one plugin and one CPT must serve both programmes.
4. Removes the old `vg_perk_level_group` taxonomy from the required foundation taxonomy set.
5. Replaces broad level-group taxonomy thinking with programme-aware future mapping handled in VS-002 and VS-003.
6. Adds the `vg_perk_programme` taxonomy for admin filtering only.
7. Clarifies that taxonomies are not runtime eligibility authority.
8. Adds future-safe capability and naming rules.
9. Strengthens agent instructions to avoid hardcoding PMPro level IDs in the foundation.
10. Adds acceptance criteria for dual-programme readiness.
```

Important design decision:

```text
VS-001 must not encode PMPro level IDs, monthly/yearly variants, or benefit stacking rules.
Those belong in later slices:
- VS-002: ACF fields/options and PMPro level mapping UI
- VS-003: PMPro MembershipProfile resolver
- VS-006: Target index
- VS-007: Claims and redemption ledger
- VS-008: WooCommerce discounts
```

---

## 2A. Programme Flexibility Doctrine

This slice follows the shared model in `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

Meaning for VS-001:

```text
1. The plugin may ship with launch/default programme keys.
2. The plugin must not assume those are the only possible programmes forever.
3. PMPro level IDs, Woo product IDs, Woo variation IDs, prices, hub page IDs, and future billing terms are not foundation constants.
4. Future programmes and levels must be added through configuration/mapping in later slices.
5. Unknown or unmapped programmes/levels must not grant benefits.
6. Runtime code must consume a normalized MembershipProfile, not raw PMPro names, product IDs, or taxonomy terms.
```

VS-001 creates the stable plugin shell. It does not create the complete business registry.

---

## 3. Objective

Build the foundation for a custom WordPress plugin called **Voelgoed Member Perks**.

This slice must create:

```text
1. Base plugin structure.
2. The primary `vg_perk` custom post type.
3. Core non-public taxonomies for admin filtering, query-loop support, and future reporting.
4. Base capabilities for managing perks.
5. Launch/default programme identifiers for the current Voelgoed membership programmes.
```

The plugin must be designed from the start for this launch model:

```text
One user may have:
- no active programme membership
- Vriendinneklub only
- Voelgoednetwerk only
- both Vriendinneklub and Voelgoednetwerk
```

The plugin must also remain compatible with future configured programmes if later slices add them through the Programme and Level Mapping model.

This slice must **not** resolve which user has which membership. It only prevents the foundation from assuming a single membership group.

---

## 4. Site Context

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

The launch purpose of this plugin is to manage membership perks for:

```text
Voelgoed Vriendinneklub:
- current configured levels
- billing variants handled by WooCommerce Subscriptions

Voelgoednetwerk:
- current configured levels
- billing variants handled by WooCommerce Subscriptions
```

Future programmes, levels, and billing terms must be possible through configuration and mapping. They must not require new CPTs, new plugins, new taxonomies, or hardcoded PHP conditionals.

Members will eventually see active perks in member hub pages. Perks may later give discounts, free products, free courses, event benefits, ticket benefits, content access, downloads, replays, or partner deals.

This first slice only creates the safe foundation.

---

## 5. Core Dual-Programme Rule

The plugin must be built around this rule:

```text
The user does not have one universal membership group.
The user has a MembershipProfile.
For launch, the MembershipProfile can contain zero, one, or two active programme memberships.
Future configured programmes may extend this profile shape without changing the foundation.
```

Example future profile:

```php
[
    "user_id" => 123,
    "programmes" => [
        "vriendinneklub" => [
            "level_key" => "goud",
            "status"    => "active",
        ],
        "voelgoednetwerk" => [
            "level_key" => "silwer",
            "status"    => "active",
        ],
    ],
    "has_any_membership"       => true,
    "has_multiple_programmes"  => true,
]
```

VS-001 must not implement this resolver yet. It must merely provide launch defaults and safe foundations that later slices use.

---

## 6. Launch Programme Defaults and Registry Boundary

VS-001 may define launch/default programme keys for the current Voelgoed implementation.

Launch default keys:

```text
vriendinneklub
voelgoednetwerk
```

These keys are important because the current launch model needs a stable vocabulary for Vriendinneklub and Voelgoednetwerk.

However:

```text
These are launch defaults, not an exhaustive list of all possible future programmes.
```

Future programmes must be configured through the mapping layer introduced in later slices, not added by scattering new hardcoded constants across execution code.

Recommended class:

```text
includes/Programmes/ProgrammeRegistry.php
```

Class:

```php
Voelgoed\MemberPerks\Programmes\ProgrammeRegistry
```

Required launch constants:

```php
public const PROGRAMME_VRIENDINNEKLUB   = "vriendinneklub";
public const PROGRAMME_VOELGOEDNETWERK = "voelgoednetwerk";
```

Recommended registry method for VS-001 launch defaults:

```php
public static function launch_defaults(): array
{
    return [
        self::PROGRAMME_VRIENDINNEKLUB => [
            "key"   => self::PROGRAMME_VRIENDINNEKLUB,
            "label" => "Voelgoed Vriendinneklub",
        ],
        self::PROGRAMME_VOELGOEDNETWERK => [
            "key"   => self::PROGRAMME_VOELGOEDNETWERK,
            "label" => "Voelgoednetwerk",
        ],
    ];
}
```

Backward-compatible methods may exist:

```php
public static function all(): array;
public static function keys(): array;
public static function exists( string $programme_key ): bool;
public static function label( string $programme_key ): string;
```

But the documentation and code comments must make this clear:

```text
In VS-001, `all()` means launch defaults only.
It does not mean "the only possible programmes forever".
```

Recommended future-safe helper methods:

```php
public static function is_safe_programme_key( string $programme_key ): bool;
public static function normalize_programme_key( string $programme_key ): string;
public static function aliases(): array;
```

Initial alias guidance:

```text
Aliases may help during migration/discovery, for example:
- old_programme_key → vriendinneklub
- vriendinne → vriendinneklub
- netwerk → voelgoednetwerk

Aliases must be explicit.
Aliases must not silently grant benefits.
Aliases must be resolved before runtime eligibility decisions.
```

Hard rule:

```text
Do not hardcode PMPro level IDs in ProgrammeRegistry.
Do not hardcode WooCommerce product IDs in ProgrammeRegistry.
Do not hardcode WooCommerce variation IDs in ProgrammeRegistry.
Do not hardcode monthly/yearly/quarterly billing variants in ProgrammeRegistry.
Do not hardcode discount percentages in ProgrammeRegistry.
Do not hardcode hub page IDs in ProgrammeRegistry.
Do not make ProgrammeRegistry the final runtime membership authority.
```

The registry in VS-001 only provides launch defaults and safe naming helpers. Runtime programme and level mapping belongs to later slices.


---

## 7. Non-Negotiable Architecture Rules

Follow these rules exactly:

```text
1. PMPro will later be the membership authority.
2. WooCommerce will later be the order/payment authority.
3. Woo Subscriptions will later be the recurring payment authority.
4. Masteriyo will later be the course delivery authority.
5. Tickera will later be the ticket delivery authority.
6. This plugin will become the perk eligibility and campaign authority.
7. Bricks/Etch query loops will be the display layer.
8. ACF will be the admin editing interface later, not the runtime authority.
9. The plugin must support Vriendinneklub-only, Netwerk-only, and dual-programme members for launch.
10. The plugin must not prevent future configured programmes from being added through the mapping layer.
11. Do not create separate CPTs for Vriendinneklub, Voelgoednetwerk, or any future programme.
12. Do not create separate plugins for the two launch programmes or future programmes.
13. Do not implement shortcodes in this slice.
14. Do not implement frontend display in this slice.
15. Do not implement discounts in this slice.
16. Do not implement ACF fields in this slice.
17. Do not implement PMPro membership checking in this slice.
18. Do not implement membership inheritance rules in this slice.
19. Do not implement claim limits in this slice.
20. Do not modify the theme.
21. Do not modify functions.php.
22. Do not edit existing custom snippets.
23. Do not make destructive database changes.
24. Do not create custom database tables in this slice.
25. Do not insert default perks in this slice.
26. Do not insert default taxonomy terms in this slice.
27. Do not assume WooCommerce, PMPro, ACF, Masteriyo, Tickera, Bricks, or Etch is active.
```

---

## 8. Required Deliverables

Create or update the plugin at:

```text
wp-content/plugins/voelgoed-member-perks/
```

Required initial file structure:

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
│   ├── Programmes/
│   │   └── ProgrammeRegistry.php
│   └── Support/
│       └── Capabilities.php
└── README.md
```

Do not create unnecessary files yet.

Allowed addition in this hardened version:

```text
ProgrammeRegistry.php
```

Reason:

```text
The plugin must have launch/default programme keys and safe key helpers before later slices add configurable programme mapping, PMPro resolving, query filters, claims, discounts, reports, and notifications.
```

---

## 9. Plugin Header

The main plugin file must include a proper WordPress plugin header.

Use this text domain:

```text
voelgoed-member-perks
```

Suggested plugin header:

```php
/**
 * Plugin Name: Voelgoed Member Perks
 * Description: Manages member perks, benefit campaigns, and future perk eligibility for Voelgoed membership programmes.
 * Version: 0.1.0
 * Author: Voelgoed
 * Text Domain: voelgoed-member-perks
 */
```

Version for this slice:

```text
0.1.0
```

---

## 10. Coding Standards

Use clean PHP with:

```text
Strict namespaces
Guard clauses
No anonymous global functions except plugin bootstrap if unavoidable
No direct file access
Clear comments
Clear class names
No business logic in the main plugin file
No fatal errors if dependencies like WooCommerce, PMPro, ACF, Masteriyo, or Tickera are inactive
```

Every PHP file must begin with:

```php
<?php
```

Every PHP file must include direct access protection:

```php
defined( "ABSPATH" ) || exit;
```

Use double quotes for translatable strings and labels where practical.

Reason:

```text
Afrikaans text may later include contractions like 'n, and double quotes reduce avoidable parsing mistakes in future edits.
```

Do not use Composer yet unless the current site already requires it and it is clearly safe. Keep this slice simple.

---

## 11. Plugin Bootstrap Requirements

The main plugin file should:

```text
1. Define plugin constants:
   - VGMP_VERSION
   - VGMP_PLUGIN_FILE
   - VGMP_PLUGIN_DIR
   - VGMP_PLUGIN_URL
   - VGMP_TEXT_DOMAIN

2. Register activation hook.
3. Register deactivation hook.
4. Load required class files.
5. Start the plugin on plugins_loaded or init via the main Plugin class.
```

Do not perform dependency checks that block activation. This foundation must activate even if WooCommerce, PMPro, ACF, Masteriyo, Tickera, Bricks, or Etch are inactive.

---

## 12. Main Plugin Class

Create:

```text
includes/Plugin.php
```

Class:

```php
Voelgoed\MemberPerks\Plugin
```

Responsibilities:

```text
1. Instantiate/register PerkPostType.
2. Instantiate/register PerkTaxonomies.
3. Ensure ProgrammeRegistry launch defaults and key helpers are available for later slices.
4. Register hooks.
5. Keep orchestration only.
```

Do not put CPT/taxonomy registration details directly inside the main Plugin class.

---

## 13. Programme Registry Class

Create:

```text
includes/Programmes/ProgrammeRegistry.php
```

Class:

```php
Voelgoed\MemberPerks\Programmes\ProgrammeRegistry
```

Responsibilities:

```text
1. Define launch/default programme keys.
2. Return launch/default programme labels.
3. Provide a safe helper to check whether a key is one of the launch defaults.
4. Provide a safe key-format validator for future configured programme keys.
5. Provide explicit alias normalization helpers for known legacy/staging names.
6. Avoid any PMPro, WooCommerce, ACF, or database dependency.
```

Recommended methods:

```php
public static function launch_defaults(): array;
public static function all(): array;
public static function keys(): array;
public static function exists( string $programme_key ): bool;
public static function label( string $programme_key ): string;
public static function is_safe_programme_key( string $programme_key ): bool;
public static function normalize_programme_key( string $programme_key ): string;
public static function aliases(): array;
```

Launch default keys:

```text
vriendinneklub
voelgoednetwerk
```

Important interpretation:

```text
`exists()` in VS-001 means "exists in launch defaults".
Later runtime slices must use configured programme mapping/repositories before deciding final eligibility.
```

Alias guidance:

```text
Aliases are for migration and key drift detection only.
They must not become a second business model.
They must not silently grant benefits.
```

Potential aliases to document, not blindly enforce:

```text
old_programme_key → vriendinneklub
vriendinne → vriendinneklub
netwerk → voelgoednetwerk
```

Do not add PMPro level IDs here.

Do not add hub page IDs here.

Do not add subscription product IDs here.

Do not add Woo variation IDs here.

Those are configuration concerns for later slices.


---

## 14. Activation Behaviour

Create:

```text
includes/Activator.php
```

Class:

```php
Voelgoed\MemberPerks\Activator
```

On activation:

```text
1. Register CPT and taxonomies before flushing rewrite rules.
2. Add required plugin capabilities to Administrator.
3. Flush rewrite rules once.
```

Do not create custom database tables in this slice.

Do not insert default perks in this slice.

Do not insert default taxonomy terms in this slice.

---

## 15. Deactivation Behaviour

Create:

```text
includes/Deactivator.php
```

Class:

```php
Voelgoed\MemberPerks\Deactivator
```

On deactivation:

```text
1. Flush rewrite rules.
2. Do not delete data.
3. Do not remove capabilities yet.
4. Do not unregister data manually.
```

Data must remain intact after deactivation.

---

## 16. Capabilities

Create:

```text
includes/Support/Capabilities.php
```

Class:

```php
Voelgoed\MemberPerks\Support\Capabilities
```

Add custom capabilities to the Administrator role.

Required CPT capabilities:

```text
edit_vg_perk
read_vg_perk
delete_vg_perk

edit_vg_perks
edit_others_vg_perks
publish_vg_perks
read_private_vg_perks

delete_vg_perks
delete_private_vg_perks
delete_published_vg_perks
delete_others_vg_perks

edit_private_vg_perks
edit_published_vg_perks
```

Also add a general future management capability:

```text
manage_vg_perks
```

This will be used later for settings/reporting pages.

Programme-specific capabilities are **not** required in VS-001.

Do not add capabilities like these yet:

```text
manage_vriendinneklub_perks
manage_voelgoednetwerk_perks
```

Reason:

```text
The MVP admin model is one trusted administrator/editorial team managing both programmes through one plugin. If role separation is needed later, add it in an admin/security slice after the workflows are known.
```

For VS-001, only Administrator needs these capabilities.

---

## 17. CPT Registration

Create:

```text
includes/Content/PerkPostType.php
```

Class:

```php
Voelgoed\MemberPerks\Content\PerkPostType
```

Register CPT key:

```text
vg_perk
```

Register on:

```php
init
```

CPT settings:

```php
[
    "labels"              => [...],
    "public"              => false,
    "show_ui"             => true,
    "show_in_menu"        => true,
    "show_in_rest"        => true,
    "has_archive"         => false,
    "rewrite"             => false,
    "query_var"           => false,
    "exclude_from_search" => true,
    "supports"            => [
        "title",
        "editor",
        "excerpt",
        "thumbnail",
        "revisions",
        "page-attributes",
    ],
    "menu_icon"           => "dashicons-awards",
    "capability_type"     => [ "vg_perk", "vg_perks" ],
    "map_meta_cap"        => true,
]
```

Suggested labels:

```text
Name: Member Perks
Singular Name: Member Perk
Add New: Add New Perk
Add New Item: Add New Member Perk
Edit Item: Edit Member Perk
New Item: New Member Perk
View Item: View Member Perk
Search Items: Search Member Perks
Not Found: No member perks found
Not Found in Trash: No member perks found in Trash
All Items: All Member Perks
Menu Name: Member Perks
```

Important:

```text
The CPT should be manageable in admin, but not publicly accessible as standalone posts.
```

Do not create separate CPTs such as:

```text
vg_vriendinne_perk
vg_netwerk_perk
```

There must be one authoritative perk CPT for both programmes.

---

## 18. Taxonomy Registration

Create:

```text
includes/Content/PerkTaxonomies.php
```

Class:

```php
Voelgoed\MemberPerks\Content\PerkTaxonomies
```

Register all taxonomies on:

```php
init
```

Attach all taxonomies to:

```text
vg_perk
```

All taxonomies should:

```text
1. Be visible in admin.
2. Be hierarchical where appropriate.
3. Show in REST.
4. Use clear labels.
5. Avoid public frontend archives.
6. Be treated as admin/query grouping only, not eligibility authority.
```

Taxonomies must not become the source of truth for runtime access, discounts, claims, course grants, or ticket grants.

---

## 19. Required Taxonomies

### 19.1 `vg_perk_programme`

Purpose:

```text
Admin filtering and broad programme grouping.
```

Hierarchical:

```text
true
```

Launch/future terms may include:

```text
vriendinneklub
voelgoednetwerk
shared
all_members
future configured programme keys
```

Rules:

```text
1. Do not insert terms in VS-001.
2. Do not use taxonomy terms as PMPro authority.
3. Do not assume that programme taxonomy equals user eligibility.
4. Later slices may sync or validate terms against configured programme mapping if needed.
5. A manually created programme taxonomy term does not create a valid programme.
```

This taxonomy is an editorial/admin grouping tool only.

---

### 19.2 Removed from required foundation: `vg_perk_level_group`

The original VS-001 proposed:

```text
vg_perk_level_group
```

This is no longer required in the hardened foundation.

Reason:

```text
The site has two programmes, each with its own level ladder and monthly/yearly PMPro variants.
A generic level-group taxonomy risks becoming a second, stale membership mapping system.
```

Level eligibility belongs in later slices:

```text
VS-002 — ACF Fields and Options Page
VS-003 — PMPro MembershipProfile Resolver
VS-006 — Target Indexing Table
VS-007 — Claims and Redemption Ledger
VS-008 — WooCommerce Product Discounts
```

Do not register `vg_perk_level_group` in VS-001 unless a future patch explicitly reintroduces it as a non-authoritative admin-only label.

---

### 19.3 `vg_perk_type`

Purpose:

```text
Kind of perk or benefit.
```

Hierarchical:

```text
true
```

Future terms:

```text
product_discount
free_product
course_access
free_course
event_discount
free_ticket
member_content
download
replay
partner_deal
```

This taxonomy is useful for admin filtering, hub sections, reports, and future query loops.

---

### 19.4 `vg_perk_section`

Purpose:

```text
Where the perk appears in the member hub.
```

Hierarchical:

```text
true
```

Future terms:

```text
new_this_week
this_month
courses
products
events
replays
downloads
partner_deals
expiring_soon
already_claimed
```

This is a display grouping tool only.

It must not decide whether a user is eligible.

---

### 19.5 `vg_perk_runtime_status`

Purpose:

```text
Fast active/upcoming/expired filtering.
```

Hierarchical:

```text
true
```

Future terms:

```text
active
upcoming
expired
paused
archived
misconfigured
```

This taxonomy is important for performance. Later slices will update this automatically.

For VS-001, only register the taxonomy. Do not build status automation yet.

---

### 19.6 `vg_perk_campaign`

Purpose:

```text
Admin campaign grouping and reporting.
```

Hierarchical:

```text
true
```

Future example terms:

```text
june_2026
july_2026
womens_month_2026
black_friday_2026
```

---

## 20. Taxonomy Settings

Each taxonomy should use settings similar to:

```php
[
    "public"            => false,
    "show_ui"           => true,
    "show_admin_column" => true,
    "show_in_rest"      => true,
    "hierarchical"      => true,
    "rewrite"           => false,
    "query_var"         => false,
]
```

Use specific labels for each taxonomy.

Do not expose public taxonomy archives.

---

## 21. Admin UX Requirements for VS-001

In WordPress admin:

```text
1. “Member Perks” must appear as a top-level admin menu item.
2. Admin must be able to add/edit/delete Member Perk posts.
3. Admin must be able to assign all required taxonomies.
4. CPT must support featured image, excerpt, editor, revisions, and page attributes.
5. No public single perk pages should be accessible.
6. No frontend rendering is required.
7. No programme hub page is created in this slice.
8. No PMPro level mapping UI is created in this slice.
```

If `vg_perk_programme` terms are manually created in admin, that is allowed, but VS-001 must not insert terms automatically.

---

## 22. README Requirements

Create or update `README.md` with:

```text
Plugin name
Current version
Purpose
Current slice: VS-001
What is included
What is intentionally excluded
Dual-programme design note
Launch programme defaults
Programme flexibility doctrine
Reference to PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
Required CPT
Required taxonomies
Next planned slice: VS-002 — ACF Fields and Options Page
Testing checklist
Rollback notes
```

README must clearly state:

```text
This plugin is currently foundation-only and does not yet apply discounts, resolve memberships, display hubs, enrol courses, issue tickets, create claims, or send notifications.
```

README must also state:

```text
The plugin is designed to support members who may belong to Vriendinneklub, Voelgoednetwerk, or both at launch, while allowing future configured programmes through the mapping model.
```

---

## 23. Testing Checklist

After implementation, test the following on staging.

### Plugin activation

```text
1. Plugin activates without fatal errors.
2. Plugin deactivates without fatal errors.
3. No dependency plugin is required for activation.
4. Rewrite rules flush on activation/deactivation.
```

### Foundation constants / programme registry

```text
1. ProgrammeRegistry exists.
2. ProgrammeRegistry exposes launch default `vriendinneklub`.
3. ProgrammeRegistry exposes launch default `voelgoednetwerk`.
4. ProgrammeRegistry does not claim launch defaults are the only possible future programmes.
5. ProgrammeRegistry validates safe programme key format.
6. ProgrammeRegistry documents any aliases explicitly.
7. ProgrammeRegistry does not contain PMPro level IDs.
8. ProgrammeRegistry does not contain discount rules.
9. ProgrammeRegistry does not contain hub page IDs.
10. ProgrammeRegistry does not contain Woo product or variation IDs.
```

### Admin visibility

```text
1. Member Perks admin menu appears.
2. Administrator can create a new Member Perk.
3. Administrator can edit a Member Perk.
4. Administrator can delete/trash a Member Perk.
5. Administrator can assign required taxonomy terms.
```

### CPT behaviour

```text
1. `vg_perk` exists in WordPress.
2. `vg_perk` does not create a public archive.
3. `vg_perk` does not create public single pages.
4. `vg_perk` supports title, editor, excerpt, thumbnail, revisions, and page attributes.
5. `vg_perk` is available in REST.
```

### Taxonomy behaviour

```text
1. `vg_perk_programme` exists and is attached to `vg_perk`.
2. `vg_perk_type` exists and is attached to `vg_perk`.
3. `vg_perk_section` exists and is attached to `vg_perk`.
4. `vg_perk_runtime_status` exists and is attached to `vg_perk`.
5. `vg_perk_campaign` exists and is attached to `vg_perk`.
6. All required taxonomies show in REST.
7. All required taxonomies are manageable in admin.
8. No public taxonomy archives are created.
```

### Deprecated taxonomy check

```text
1. `vg_perk_level_group` is not required by this slice.
2. If `vg_perk_level_group` already exists from an earlier implementation, do not delete data during this slice.
3. Report its existence as a legacy item for VS-002/VS-003 review.
```

### Capability behaviour

```text
1. Administrator has all required `vg_perk` capabilities.
2. Administrator has `manage_vg_perks`.
3. No other roles need to be changed in this slice.
```

---

## 24. Novamira / Agent Instructions

Before editing:

```text
1. Inspect the staging site plugin directory.
2. Confirm whether `voelgoed-member-perks` already exists.
3. If it exists, inspect its current files before overwriting anything.
4. Check whether an older `vg_perk_level_group` taxonomy already exists.
5. Do not delete legacy data without approval.
6. Do not modify theme files.
7. Do not modify functions.php.
8. Do not modify existing WPCode snippets.
9. Do not touch WooCommerce, PMPro, Masteriyo, Tickera, or ACF settings in this slice.
```

During implementation:

```text
1. Create only the files required for VS-001.
2. Keep classes small and focused.
3. Use namespaces consistently.
4. Use double quotes for translatable strings where practical.
5. Avoid hard dependency checks because this slice must work even if companion plugins are disabled.
6. Do not hardcode PMPro level IDs.
7. Do not hardcode WooCommerce product IDs.
8. Do not hardcode hub page IDs.
9. Do not hardcode launch programmes as an exhaustive forever-list.
10. Do not create separate foundation logic per programme.
11. Do not make future subscription billing terms a foundation concern.
```

After implementation:

```text
1. Activate the plugin on staging.
2. Run the testing checklist.
3. Report all changed files.
4. Report whether activation succeeded.
5. Report whether the CPT and taxonomies are visible.
6. Report whether ProgrammeRegistry returns both canonical programme keys.
7. Report any warnings, PHP notices, or fatal errors.
8. Report whether any legacy taxonomy or prior plugin file was found.
9. Do not proceed to VS-002 without approval.
```

---

## 25. Explicit Out-of-Scope Items

Do not implement any of the following in VS-001:

```text
ACF field groups
ACF Options Page
PMPro level mapping
PMPro membership checks
MembershipProfile resolver
Hub page selection
WooCommerce discount hooks
WooCommerce price display changes
WooCommerce cart notices
Woo Subscriptions renewal logic
Masteriyo course access
Tickera ticket claims
Custom database tables
Claim/redemption ledger
Frontend hub page
Bricks query provider
Etch query provider
REST query endpoint
Shortcodes
Email notifications
Reporting screens
Cron jobs
Runtime status automation
Cache layer
Cloudflare or LiteSpeed rules
```

---

## 26. Acceptance Criteria

VS-001 is complete only when:

```text
1. The plugin exists at `wp-content/plugins/voelgoed-member-perks/`.
2. The plugin activates without fatal errors.
3. The plugin deactivates without deleting data.
4. The `vg_perk` CPT is registered.
5. The CPT is visible in admin as “Member Perks”.
6. The CPT is not publicly queryable as standalone frontend content.
7. All required v1.1.0 taxonomies are registered and attached to `vg_perk`.
8. Administrator has the required capabilities.
9. ProgrammeRegistry exists.
10. ProgrammeRegistry exposes launch defaults `vriendinneklub` and `voelgoednetwerk`.
11. ProgrammeRegistry does not treat those launch defaults as the only possible future programmes.
12. ProgrammeRegistry contains no PMPro level IDs, product IDs, variation IDs, hub IDs, billing terms, or pricing rules.
13. No existing theme or snippet files were modified.
14. No ACF, PMPro, WooCommerce, Woo Subscriptions, Masteriyo, or Tickera logic was added.
15. No custom database tables were created.
16. No default perks or taxonomy terms were inserted.
17. README.md documents the current slice, exclusions, dual-programme foundation, and programme flexibility doctrine.
18. The agent provides a clear implementation and test report.
```

---

## 27. Expected Agent Final Report Format

The implementing agent must report back in this format:

```text
VS-001 IMPLEMENTATION REPORT

Status:
- Complete / Partial / Blocked

Files created:
- ...

Files modified:
- ...

Plugin activation:
- Passed / Failed

Programme registry verification:
- Passed / Failed
- Returned launch default programme keys:
  - vriendinneklub
  - voelgoednetwerk
- Safe future key validation documented: Yes / No
- Aliases documented: Yes / No / Not applicable

CPT verification:
- Passed / Failed

Taxonomy verification:
- Passed / Failed

Capability verification:
- Passed / Failed

Legacy findings:
- Existing plugin folder found: Yes / No
- Existing `vg_perk_level_group` taxonomy/data found: Yes / No / Not checked
- Existing old taxonomy terms found: ...

Issues found:
- ...

Items intentionally not implemented:
- ACF fields
- Options Page
- PMPro resolver
- MembershipProfile resolver
- Woo discounts
- Woo Subscriptions logic
- Hub query loops
- Claims/redemptions
- Masteriyo
- Tickera
- Notifications

Recommended next slice:
- VS-002 — ACF Fields and Options Page
```

---

## 28. Next Slice

After VS-001 is implemented and approved, proceed to:

```text
VS-002 — ACF Fields and Options Page
```

VS-002 must define the editable configuration layer for:

```text
Configurable programme registry options
PMPro level mapping
Hub page IDs
Feature flags
Perk audience rules
Claim scope
Discount stacking defaults
Mapping-model alignment with PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Do not start VS-002 during VS-001.
