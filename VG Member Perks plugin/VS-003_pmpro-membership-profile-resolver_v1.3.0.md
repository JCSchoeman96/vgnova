# FEATURE PLANNING PACK: VS-003 — PMPro Membership Profile Resolver

```text
Document version: 1.3.0
Status: Programme-flexibility hardened planning pack
Previous version: VS-003 v1.1.0
Plugin: Voelgoed Member Perks
Depends on:
- VS-000 v1.1.0 — Current Site Discovery and Conflict Audit
- VS-001 v1.2.0 — Plugin Foundation, CPT and Taxonomies
- VS-002 v1.3.0 — ACF Fields and Options Page
- PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0
Next slice: VS-004 — Runtime Status Automation
Plugin target version after this slice: 0.3.0
```

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

---

## 0. v1.3.0 Programme Flexibility Patch Summary

This version applies the **Programme and Level Flexibility Pass**.

The previous hardened VS-003 v1.1.0 correctly moved away from a flat `level_group` model and introduced a structured `MembershipProfile`. The remaining weakness was that several sections still treated the current two launch programmes and example levels as if they were the fixed permanent universe.

This version locks the flexible doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

Major changes in this version:

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as default launch programmes, not the only possible programmes forever.
2. Requires the resolver to use ProgrammeMappingRepository / SettingsRepository, not hardcoded programme lists or PMPro level IDs.
3. Adds programme aliases and level aliases as explicit migration/compatibility mappings.
4. Adds normalized pmpro_level_ids as the canonical mapping field.
5. Treats monthly/yearly/quarterly/future billing terms as Woo billing concerns, not separate perk levels.
6. Adds unmapped PMPro level fail-closed behaviour.
7. Adds duplicate PMPro mapping fail-closed behaviour.
8. Adds dynamic programme ordering from configured sort order.
9. Adds mapping_hash / mapping_version to MembershipProfile output.
10. Adds future-level, future-billing-term, and future-programme tests.
11. Aligns conflict resolution with Programme and Level Mapping Model: lower level_rank = stronger/higher level.
12. Preserves the ACF runtime-boundary: runtime resolver code must not call raw ACF directly.
```

The core architectural correction:

```text
The resolver must never ask, “Is this user Goud/Silwer/Brons in these two hardcoded programmes?”

It must ask:
“What enabled configured programme/level mappings does this user's active PMPro state match?”
```

---

## 1. Objective

Build the membership eligibility foundation for the **Voelgoed Member Perks** plugin.

This vertical slice must allow the plugin to determine a logged-in user's active **MembershipProfile** across the configured Voelgoed membership programmes.

For the launch setup, the default programmes are:

```text
1. Voelgoed Vriendinneklub
2. Voelgoednetwerk
```

But the resolver must not be hardcoded so tightly that future programmes or levels require execution-code changes.

This slice answers this core question:

```text
Given a WordPress user ID, what active configured Voelgoed membership profile does this user have right now?
```

The answer must support these real user states:

```text
1. No active mapped programme membership.
2. One active mapped level in one configured programme.
3. One active mapped level in more than one configured programme.
4. Same-programme conflicts, detected and resolved safely.
5. Active PMPro levels that are unmapped, ignored for perks and reported for admin review.
```

Launch examples:

```text
PMPro Level 17 = Voelgoed Vriendinneklub Goud

Resolves through mapping to:
programme_key = vriendinneklub
level_key     = goud
```

```text
PMPro Level 17 = Voelgoed Vriendinneklub Goud
PMPro Level 19 = Voelgoednetwerk Silwer

Resolves through mapping to:
vriendinneklub:goud
voelgoednetwerk:silwer
```

Future example:

```text
PMPro Level 40 = Voelgoed Business Club Founder

If mapped:
programme_key = business_club
level_key     = founder

If unmapped:
ignored for perks, reported as unmapped_pmpro_level
```

This slice must not build frontend hub queries, WooCommerce discounts, claims, Masteriyo course access, Tickera ticket access, notifications, reports, runtime status automation, or persistent cache infrastructure.

The successful output of this slice is a reliable internal answer to:

```text
Which configured programme memberships and levels does this user currently have?
```

---

## 2. Why This Slice Was Hardened Again

The earlier VS-003 v1.1.0 model correctly replaced flat internal groups such as:

```text
vriendinneklub_goud
voelgoednetwerk_goud
all_paid_members
```

with a structured `MembershipProfile`.

That was the right move.

However, the resolver still needs one more hardening pass because future business changes must not break the plugin.

The site may later add:

```text
new Woo billing terms, such as quarterly or six-monthly
new PMPro access levels, such as Platinum or Founder
new display labels, such as Goud instead of Goud
new internal aliases for legacy metadata
possibly future programmes beyond the two launch programmes
```

Therefore, VS-003 must be fully mapping-driven.

Bad model:

```text
PMPro Level 17 means Vriendinneklub Goud because the PHP code knows it.
```

Correct model:

```text
PMPro Level 17 means whatever enabled ProgrammeLevelDefinition maps it to.
```

Bad model:

```text
Only these two programme keys exist forever:
- vriendinneklub
- voelgoednetwerk
```

Correct model:

```text
The plugin ships with two default launch programmes.
Runtime reads enabled configured programmes from the registry/settings layer.
Unknown or unmapped programme values fail closed.
```

---

## 3. Existing Foundation

This slice assumes the following previous slices and model docs exist:

```text
VS-000 — Current Site Discovery and Conflict Audit
VS-001 — Plugin Foundation, CPT and Taxonomies
VS-002 — ACF Fields and Options Page
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

The plugin should already exist at:

```text
wp-content/plugins/voelgoed-member-perks/
```

Plugin namespace:

```php
Voelgoed\MemberPerks
```

Primary CPT:

```text
vg_perk
```

Default launch programme keys from VS-001 / the mapping model:

```text
vriendinneklub
voelgoednetwerk
```

Important:

```text
These launch keys are defaults and seed values.
They are not permission to hardcode the resolver to only two programmes forever.
```

Do not rely on `vg_perk_level_group` as runtime authority.

If a legacy `vg_perk_level_group` taxonomy exists from an older planning pass, this slice must treat it as legacy/admin-only and must not use it as the membership authority.

---

## 4. Expected Settings from VS-002 v1.3.0

VS-002 v1.3.0 should provide settings for:

```text
1. Programme definitions.
2. Programme aliases.
3. Programme hub/upsell settings.
4. Programme level definitions.
5. Level aliases.
6. PMPro level ID mappings.
7. Enabled/disabled flags.
8. Level rank.
9. Optional display metadata such as member cap/cap mode.
```

Expected programme settings source:

```text
vgmp_programme_settings
```

Expected programme level mapping source:

```text
vgmp_programme_level_mappings
```

Important normalized field:

```text
pmpro_level_ids
```

Legacy/input convenience fields may still exist:

```text
pmpro_monthly_level_ids
pmpro_yearly_level_ids
pmpro_extra_level_ids
```

Rules:

```text
1. pmpro_level_ids is the normalized canonical mapping field.
2. monthly/yearly/extra fields are input conveniences only.
3. All PMPro IDs from convenience fields must normalize into pmpro_level_ids.
4. Billing terms do not create separate VGMP levels if they grant the same PMPro access level.
5. Future billing terms such as quarterly should not require code changes if the access level is unchanged.
```

Expected programme mapping row shape:

```text
programme_key
programme_label
programme_aliases
enabled
sort_order
supports_hub
hub_page_id
upsell_page_id
supports_notifications
```

Expected level mapping row shape:

```text
programme_key
level_key
level_label
level_aliases
level_rank
pmpro_level_ids
pmpro_monthly_level_ids
pmpro_yearly_level_ids
pmpro_extra_level_ids
is_paid_level
member_cap
cap_mode
mapping_enabled
billing_notes
```

Launch example rows:

```text
programme_key       level_key   level_rank   pmpro_level_ids
vriendinneklub      goud        10           17
vriendinneklub      silwer      20           16
vriendinneklub      brons       30           15
voelgoednetwerk     platinum    5            21
voelgoednetwerk     goud        10           20
voelgoednetwerk     silwer      20           19
voelgoednetwerk     brons       30           18
```

The exact current level names must come from staging and settings. The examples above are illustrative.

Do not hard-code raw PMPro level IDs, Woo product IDs, Woo variation IDs, programme keys, level keys, labels, prices, or billing terms in resolver logic.

---

## 5. Non-Negotiable Architecture Rules

Follow these rules exactly:

```text
1. PMPro is the membership/access authority.
2. WooCommerce Subscriptions is the recurring billing authority.
3. Woo membership products and variations are billing surfaces, not perk authority.
4. Voelgoed Member Perks maps active PMPro levels to configured programme/level definitions.
5. The plugin must not duplicate PMPro membership state.
6. Only enabled mapped PMPro levels count for perks.
7. Unmapped PMPro levels fail closed and grant no perks.
8. Unknown programme keys fail closed and grant no perks.
9. Programme aliases are explicit migration mappings, not fuzzy matching.
10. Level aliases are explicit migration/import compatibility mappings, not runtime identity.
11. Runtime identity is programme_key + level_key.
12. One resolved active level per programme is the default business rule.
13. Same-programme conflicts must be detected and must not stack benefits.
14. A user may belong to more than one configured programme.
15. The resolver must fail closed if PMPro is unavailable.
16. The resolver must fail closed if mapping/configuration cannot be loaded.
17. Resolve a user's profile once per request and reuse the result.
18. Do not implement frontend query loops in this slice.
19. Do not implement WooCommerce pricing in this slice.
20. Do not implement claims or redemptions in this slice.
21. Do not implement Masteriyo course grants in this slice.
22. Do not implement Tickera ticket logic in this slice.
23. Do not create custom database tables in this slice.
24. Do not modify theme files.
25. Do not modify functions.php.
26. Do not edit existing WPCode/FluentSnippets snippets.
```

### ACF Runtime Boundary Rule

Runtime resolver classes must not call raw ACF functions directly.

Forbidden in resolver/hot-path classes:

```text
get_field()
get_fields()
acf_get_value()
ACF option reads directly scattered across Membership classes
```

Allowed:

```text
SettingsRepository / AcfConfigProvider may read ACF internally.
ProgrammeMappingRepository consumes SettingsRepository output.
MembershipProfileResolver consumes ProgrammeMappingRepository output.
```

This keeps the resolver compatible with a future custom admin/config provider.

---

## 6. Required Deliverables

Add or update the following files if they do not already exist:

```text
voelgoed-member-perks/
├── includes/
│   ├── Membership/
│   │   ├── ProgrammeMappingRepository.php
│   │   ├── ProgrammeDefinition.php
│   │   ├── ProgrammeLevelDefinition.php
│   │   ├── MembershipProfile.php
│   │   ├── MembershipProfileResolver.php
│   │   ├── EligibilityChecker.php
│   │   └── LegacyGroupAdapter.php
│   └── Admin/
│       └── MembershipDebugPanel.php
```

Update:

```text
includes/Plugin.php
README.md
```

Optional transitional compatibility:

```text
includes/Membership/ProgrammeLevelMapper.php
```

If a previous implementation already created `ProgrammeLevelMapper`, do not break it abruptly. Either:

```text
1. Rename/refactor it into ProgrammeMappingRepository, or
2. Keep it as a thin compatibility wrapper around ProgrammeMappingRepository.
```

Do not create database tables in this slice.

---

## 7. Version Bump

Update plugin version from:

```text
0.2.0
```

to:

```text
0.3.0
```

Update any plugin constant if it exists, for example:

```php
VGMP_VERSION = "0.3.0";
```

If the plugin version has already moved beyond this, increment according to the existing project versioning convention and document the final value in the implementation report.

---

## 8. Default Launch Programme Keys

The plugin ships with default launch programme keys:

```text
vriendinneklub
voelgoednetwerk
```

Labels:

```text
vriendinneklub    = Voelgoed Vriendinneklub
voelgoednetwerk   = Voelgoednetwerk
```

These are stable default keys for the current Voelgoed launch.

They are not PMPro level IDs.
They are not Woo product IDs.
They are not Woo variation IDs.
They are not display labels.

Do not invent alternative keys in execution code such as:

```text
vriendinne
voelgoed_vriendinne
network
netwerk
voelgoed_network
```

Instead, use only configured canonical keys. If an external import later needs aliases, they must be explicit and normalize before runtime storage.

Rules:

```text
1. Aliases are explicit, configured, and reviewable.
2. Aliases normalize to canonical configured keys before runtime use.
3. Alias and canonical key must never become two active programmes.
4. Unknown aliases fail closed.
5. Future programmes must be added through programme settings and QA, not hardcoded conditionals.
```

---

## 9. Level Keys

Level keys are configured through VS-002 settings and loaded through `ProgrammeMappingRepository`.

Possible launch examples:

```text
Vriendinneklub:
- brons
- silwer
- goud
```

or:

```text
Vriendinneklub:
- brons
- silwer
- goud
```

Possible Netwerk examples:

```text
Voelgoednetwerk:
- brons
- silwer
- goud
- platinum
```

or:

```text
Voelgoednetwerk:
- level_1
- level_2
- level_3
- level_4
```

The actual values must come from configuration.

Rules:

```text
1. Level keys must be lowercase stable slugs.
2. Level keys must be unique inside one programme.
3. Level keys do not need to be globally unique across all programmes.
4. Therefore, "goud" or "goud" may exist in more than one programme.
5. Runtime identity must always use programme_key + level_key together.
6. Level aliases may normalize admin/import/legacy values, but runtime output uses level_key.
7. Unmapped level values fail closed.
```

Correct runtime identity:

```text
vriendinneklub:goud
voelgoednetwerk:goud
```

Incorrect runtime identity:

```text
goud
```

---

## 10. Derived Audience Aliases

The old VS-003 used:

```text
all_paid_members
```

This should no longer be treated as a real membership group.

Instead, implement it as a **derived audience alias** calculated from the `MembershipProfile`.

Required derived aliases:

```text
any_active_programme
all_paid_members
dual_programme_member
```

Programme-specific derived aliases may be generated dynamically:

```text
{programme_key}_member
```

Launch examples:

```text
vriendinneklub_member
voelgoednetwerk_member
```

Rules:

```text
1. Derived aliases are not PMPro levels.
2. Derived aliases are not programme levels.
3. Derived aliases are calculated from MembershipProfile.
4. Derived aliases may support backwards compatibility and later audience rules.
5. Do not store derived aliases as the main profile state.
6. Future programme aliases should generate from configured canonical programme keys, not from aliases.
```

Example:

```text
User has:
vriendinneklub:goud
voelgoednetwerk:silwer

Derived aliases:
any_active_programme
all_paid_members
dual_programme_member
vriendinneklub_member
voelgoednetwerk_member
```

---

## 11. MembershipProfile Shape

Create a structured profile.

The exact implementation may be an array or a lightweight class, depending on existing plugin style.

Recommended public array shape:

```php
[
    "user_id" => 123,
    "has_any_active_programme" => true,
    "has_multiple_programmes" => true,
    "mapping_hash" => "deterministic_mapping_hash_here",
    "profile_hash" => "deterministic_profile_hash_here",
    "programmes" => [
        "vriendinneklub" => [
            "programme_key" => "vriendinneklub",
            "programme_label" => "Voelgoed Vriendinneklub",
            "level_key" => "goud",
            "level_label" => "Goud",
            "level_rank" => 10,
            "pmpro_level_id" => 17,
            "billing_variant" => null,
            "is_paid_level" => true,
            "status" => "active",
            "source" => "pmpro",
        ],
        "voelgoednetwerk" => [
            "programme_key" => "voelgoednetwerk",
            "programme_label" => "Voelgoednetwerk",
            "level_key" => "silwer",
            "level_label" => "Silwer",
            "level_rank" => 20,
            "pmpro_level_id" => 19,
            "billing_variant" => null,
            "is_paid_level" => true,
            "status" => "active",
            "source" => "pmpro",
        ],
    ],
    "derived_aliases" => [
        "any_active_programme",
        "all_paid_members",
        "dual_programme_member",
        "vriendinneklub_member",
        "voelgoednetwerk_member",
    ],
    "unmapped_pmpro_levels" => [],
    "conflicts" => [],
    "warnings" => [],
]
```

For a logged-out user or invalid user ID:

```php
[
    "user_id" => 0,
    "has_any_active_programme" => false,
    "has_multiple_programmes" => false,
    "mapping_hash" => "none",
    "profile_hash" => "guest",
    "programmes" => [],
    "derived_aliases" => [],
    "unmapped_pmpro_levels" => [],
    "conflicts" => [],
    "warnings" => [],
]
```

Unmapped active PMPro level example:

```php
"unmapped_pmpro_levels" => [
    [
        "pmpro_level_id" => 99,
        "pmpro_level_name" => "Legacy Test Level",
        "reason" => "active_pmpro_level_not_mapped",
    ],
]
```

Unmapped PMPro levels are diagnostic only. They must not grant perks.

---

## 12. Profile Hash and Mapping Hash

The resolver must provide a deterministic `profile_hash`.

It must also include the current `mapping_hash` or mapping version from `ProgrammeMappingRepository`.

Purpose:

```text
1. Future cache variation.
2. Future hub query caching.
3. Future notification grouping.
4. Future diagnostics.
5. Correct invalidation when programme/level mappings change.
```

Rules:

```text
1. The profile hash must not include raw personal information.
2. The profile hash must be deterministic for the same resolved programme/level state under the same mapping version.
3. The profile hash should change if the user's active mapped programme/level state changes.
4. The profile hash should change if mapping changes alter the interpreted profile.
5. The mapping hash must come from normalized enabled mapping configuration.
6. Neither hash is a security authority.
```

Recommended profile hash input:

```php
[
    "mapping_hash" => "...",
    "programmes" => [
        "vriendinneklub" => "goud",
        "voelgoednetwerk" => "silwer",
    ],
]
```

Recommended algorithm:

```text
sha256(json_encode(normalized hash input))
```

For guests:

```text
guest
```

---

## 13. Class: ProgrammeMappingRepository

Create:

```text
includes/Membership/ProgrammeMappingRepository.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\ProgrammeMappingRepository
```

Purpose:

```text
Load, normalize, validate, and expose configured programme/level mappings.
```

This class replaces the old idea that the resolver directly reads fixed programme/level settings or scattered ACF fields.

Responsibilities:

```text
1. Read programme settings through SettingsRepository.
2. Read programme level mappings through SettingsRepository.
3. Normalize programme keys.
4. Normalize explicit programme aliases.
5. Normalize level keys.
6. Normalize explicit level aliases.
7. Normalize PMPro level IDs from pmpro_level_ids and convenience fields.
8. Expose enabled ProgrammeDefinition rows.
9. Expose enabled ProgrammeLevelDefinition rows.
10. Detect duplicate PMPro level IDs across enabled mappings.
11. Detect duplicate level keys inside one programme.
12. Detect alias collisions.
13. Detect disabled mappings.
14. Produce deterministic mapping_hash / mapping_version.
15. Fail closed for unmapped levels.
```

Required public methods:

```php
public function get_enabled_programmes(): array;
public function get_programme( string $programme_key_or_alias ): ?ProgrammeDefinition;
public function normalize_programme_key( string $programme_key_or_alias ): ?string;
public function get_levels_for_programme( string $programme_key ): array;
public function get_level( string $programme_key, string $level_key_or_alias ): ?ProgrammeLevelDefinition;
public function normalize_level_key( string $programme_key, string $level_key_or_alias ): ?string;
public function get_all_configured_pmpro_level_ids(): array;
public function get_mapping_for_pmpro_level_id( int $level_id ): ?ProgrammeLevelDefinition;
public function get_mapping_warnings(): array;
public function get_mapping_hash(): string;
public function has_blocking_mapping_errors(): bool;
```

Expected normalized output example:

```php
[
    "programmes" => [
        "vriendinneklub" => [
            "programme_key" => "vriendinneklub",
            "programme_label" => "Voelgoed Vriendinneklub",
            "programme_aliases" => [],
            "enabled" => true,
            "sort_order" => 10,
        ],
        "voelgoednetwerk" => [
            "programme_key" => "voelgoednetwerk",
            "programme_label" => "Voelgoednetwerk",
            "programme_aliases" => [ "netwerk" ],
            "enabled" => true,
            "sort_order" => 20,
        ],
    ],
    "levels" => [
        "vriendinneklub" => [
            "goud" => [
                "programme_key" => "vriendinneklub",
                "level_key" => "goud",
                "level_label" => "Goud",
                "level_aliases" => [ "goud" ],
                "level_rank" => 10,
                "is_paid_level" => true,
                "pmpro_level_ids" => [ 17 ],
            ],
        ],
    ],
    "mapping_hash" => "...",
]
```

Important:

```text
Lower level_rank means stronger/higher level.
Example: Platinum 5, Goud 10, Silwer 20, Brons 30.
```

### Transitional note: ProgrammeLevelMapper

If an implementation already has `ProgrammeLevelMapper`, it may remain as a compatibility wrapper:

```php
final class ProgrammeLevelMapper
{
    public function __construct(
        private ProgrammeMappingRepository $repository
    ) {}
}
```

But new code should depend on `ProgrammeMappingRepository`.

---

## 14. Reading Settings Safely

The Membership layer must not scatter direct ACF reads.

Correct:

```text
SettingsRepository
↓
ProgrammeMappingRepository
↓
MembershipProfileResolver
```

Incorrect:

```php
get_field( "vgmp_programme_level_mappings", "option" );
```

inside `MembershipProfileResolver`, `EligibilityChecker`, Woo classes, Tickera classes, Masteriyo classes, or hub query classes.

If the current implementation still uses ACF for MVP settings, that ACF read belongs inside:

```text
AcfConfigProvider
SettingsRepository
```

If the configuration provider is unavailable:

```text
1. Do not fatal.
2. Mapping repository returns empty mapping plus warning.
3. Resolver returns guest/empty profile.
4. Debug panel reports provider unavailable.
5. No perks are granted.
```

PMPro ID normalization must accept possible formats from settings:

```text
Array of IDs
Comma-separated string
Newline-separated string
Single integer
Empty value
```

Normalize values like:

```text
"12, 13, 14"
"12\n13\n14"
[ "12", "13", 14 ]
12
```

To:

```php
[ 12, 13, 14 ]
```

Remove:

```text
0
negative values
non-numeric values
empty strings
duplicates
```

Sort IDs ascending for deterministic output.

---

## 15. Duplicate Mapping and Alias Collision Detection

A raw PMPro level ID must not map to more than one enabled programme/level.

Invalid example:

```text
PMPro Level 17 => vriendinneklub:goud
PMPro Level 17 => voelgoednetwerk:silwer
```

If duplicate mapping is detected:

```text
1. Do not fatal.
2. Record a blocking mapping warning.
3. Exclude the ambiguous duplicate from eligibility.
4. Show the problem in the debug panel.
5. Require admin correction.
```

Preferred fail-closed behaviour:

```text
A duplicated PMPro level ID should not grant any perk eligibility until fixed.
```

Programme alias collision example:

```text
programme_key = vriendinneklub
programme_aliases = netwerk

programme_key = voelgoednetwerk
programme_aliases = netwerk
```

If alias collision is detected:

```text
1. Do not normalize that alias.
2. Record a mapping warning.
3. Fail closed for that alias.
4. Require admin correction.
```

Level alias collision inside same programme example:

```text
vriendinneklub:goud aliases = goud
vriendinneklub:silwer aliases = goud
```

If detected:

```text
1. Do not normalize that level alias.
2. Record a mapping warning.
3. Runtime still uses configured level_key if mapped directly.
4. Require admin correction.
```

Reason:

```text
Bad mapping can cause wrong discounts, course access, ticket claims, reports, and communications later.
```

---

## 16. Class: ProgrammeDefinition

Create:

```text
includes/Membership/ProgrammeDefinition.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\ProgrammeDefinition
```

Purpose:

```text
Represent a normalized configured programme.
```

Suggested fields:

```text
programme_key
programme_label
programme_aliases
enabled
sort_order
hub_page_id
upsell_page_id
supports_hub
supports_notifications
created_from_default_seed
notes
```

Rules:

```text
1. programme_key is the runtime identity.
2. programme_label is display/admin text.
3. aliases are input normalization only.
4. disabled programmes do not grant perks.
5. unknown programmes fail closed.
```

---

## 17. Class: ProgrammeLevelDefinition

Create:

```text
includes/Membership/ProgrammeLevelDefinition.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\ProgrammeLevelDefinition
```

Purpose:

```text
Represent one normalized level inside one configured programme.
```

Suggested fields:

```text
programme_key
level_key
level_label
level_aliases
level_rank
pmpro_level_ids
enabled
is_paid_level
member_cap
cap_mode
billing_notes
```

Rules:

```text
1. programme_key + level_key is the runtime identity.
2. level_label may change without changing identity.
3. level_aliases are compatibility/input helpers only.
4. pmpro_level_ids are external PMPro IDs.
5. Woo product IDs are not stored here as runtime authority.
6. disabled levels do not grant perks.
7. lower level_rank = stronger/higher level.
```

---

## 18. Class: MembershipProfile

Create:

```text
includes/Membership/MembershipProfile.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\MembershipProfile
```

Purpose:

```text
Provide a clear object or normalized array wrapper for resolved membership state.
```

If the plugin style is simple arrays, this class may be a factory/helper rather than a heavy DTO.

Recommended responsibilities:

```text
1. Build guest profile.
2. Build profile for user.
3. Normalize programme order using ProgrammeDefinition sort_order.
4. Calculate derived aliases.
5. Include unmapped PMPro diagnostics.
6. Include conflicts/warnings.
7. Include mapping_hash.
8. Calculate profile_hash.
9. Expose helper methods.
```

Recommended methods:

```php
public static function guest( string $mapping_hash = "none" ): array;
public static function from_programmes(
    int $user_id,
    array $programmes,
    string $mapping_hash,
    array $conflicts = [],
    array $unmapped_pmpro_levels = [],
    array $warnings = []
): array;
public static function calculate_profile_hash( array $programmes, string $mapping_hash ): string;
public static function get_programme_level_key( array $profile, string $programme_key ): ?string;
public static function has_programme( array $profile, string $programme_key ): bool;
public static function has_programme_level( array $profile, string $programme_key, string $level_key ): bool;
```

---

## 19. Class: MembershipProfileResolver

Create:

```text
includes/Membership/MembershipProfileResolver.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
```

Responsibilities:

```text
1. Resolve a user's active MembershipProfile.
2. Use PMPro as the membership authority.
3. Use ProgrammeMappingRepository as the mapping authority.
4. Support users with more than one configured programme.
5. Detect multiple active mapped levels inside the same programme.
6. Resolve one winning level per programme.
7. Record unmapped active PMPro levels for admin diagnostics.
8. Include mapping_hash and profile_hash.
9. Cache resolved profiles per request.
10. Fail closed if user is logged out, PMPro is unavailable, or mapping is unavailable.
```

Required public methods:

```php
public function get_user_membership_profile( int $user_id ): array;
public function user_has_programme( int $user_id, string $programme_key ): bool;
public function user_has_programme_level( int $user_id, string $programme_key, string $level_key ): bool;
public function user_has_any_active_programme( int $user_id ): bool;
public function get_user_programme_level( int $user_id, string $programme_key ): ?string;
public function get_user_derived_aliases( int $user_id ): array;
public function get_user_profile_hash( int $user_id ): string;
public function clear_request_cache( ?int $user_id = null ): void;
```

Do not return raw PMPro state as the main API.

### Required Resolver Flow

```text
1. Validate user ID.
2. Load current mapping from ProgrammeMappingRepository.
3. If mapping unavailable or has blocking errors, return fail-closed empty profile with warnings.
4. Load active PMPro level IDs for the user.
5. Match active PMPro level IDs to enabled ProgrammeLevelDefinition rows.
6. Active PMPro levels without mappings are added to unmapped_pmpro_levels diagnostics.
7. Group matched levels by programme_key.
8. For each programme, resolve one winning level using level_rank.
9. Record same-programme conflicts if more than one level matched.
10. Build MembershipProfile.
11. Include mapping_hash.
12. Calculate profile_hash.
13. Store in request cache.
```

---

## 20. PMPro Checking Rules

Use PMPro only if available.

Acceptable detection:

```php
function_exists( "pmpro_hasMembershipLevel" )
```

Preferred detection for active membership levels, if available on the site:

```php
function_exists( "pmpro_getMembershipLevelsForUser" )
```

Recommended safe strategy:

```text
1. Get configured PMPro level IDs from ProgrammeMappingRepository.
2. If pmpro_getMembershipLevelsForUser is available, fetch the user's active PMPro levels and intersect with configured IDs.
3. Also record active PMPro levels not present in configured mappings if practical.
4. If pmpro_getMembershipLevelsForUser is unavailable, loop through configured PMPro level IDs and check each with pmpro_hasMembershipLevel.
5. Use only active PMPro membership status.
6. Ignore Woo subscription/order status directly in this slice.
```

Fallback check:

```php
pmpro_hasMembershipLevel( $level_id, $user_id );
```

or, where safe and supported:

```php
pmpro_hasMembershipLevel( $level_ids, $user_id );
```

Fail closed:

```text
No PMPro function = guest/empty profile.
No user ID = guest/empty profile.
No enabled mapped levels = empty profile.
Duplicate mapping = no eligibility for the ambiguous raw PMPro level.
Unmapped active PMPro level = diagnostic only, no perks.
```

Do not assume a membership level is active based only on:

```text
user meta
WooCommerce subscription status
order history
email address
role
Woo product purchase
Woo variation purchase
```

Woo products/variations may be used in diagnostics to verify billing setup, but not as runtime perk authority.

---

## 21. One-Level-Per-Programme Rule

The business rule is:

```text
A user may belong to more than one configured programme.
A user should normally have only one active resolved level inside each programme.
```

Valid launch example:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

Invalid/conflicting example:

```text
vriendinneklub:goud
vriendinneklub:silwer
```

If a same-programme conflict occurs, do this:

```text
1. Do not fatal.
2. Resolve the strongest configured level for frontend/profile use.
3. Strongest = lowest level_rank.
4. Add a conflict record to the profile.
5. Show the conflict in the debug panel.
6. Later reporting/admin slices must surface this for repair.
7. Do not stack benefits inside the same programme.
```

Conflict record example:

```php
[
    "type" => "multiple_active_levels_in_programme",
    "programme_key" => "vriendinneklub",
    "detected_levels" => [ "silwer", "goud" ],
    "resolved_level" => "goud",
    "resolution" => "lowest_level_rank_wins",
]
```

Tie-break if two mapped levels have the same rank:

```text
1. Prefer the level that appears first in the configured VS-002 mapping order.
2. If mapping order cannot be known, sort by level_key ascending.
3. Record a conflict warning.
4. Never grant duplicate same-programme benefits.
```

---

## 22. Deterministic Output Rules

The resolver output must be deterministic.

Programme order:

```text
Configured enabled programme sort_order ascending.
Then programme_key ascending as a stable tie-break.
```

For the launch programmes, this will normally produce:

```text
vriendinneklub
voelgoednetwerk
```

Within each programme, the resolved level is a single level object.

Derived aliases should be sorted in this order:

```text
any_active_programme
all_paid_members
dual_programme_member
{programme_key}_member sorted by programme order
```

Do not return duplicates.

Do not return unknown programme keys as eligible programmes.

Do not return raw PMPro level IDs as the primary eligibility output.

---

## 23. Request-Level Cache

The resolver must not re-check PMPro repeatedly during a single request.

Implement private request cache similar to:

```php
private array $resolved_profiles = [];
```

Cache by user ID and mapping hash:

```php
$cache_key = $user_id . ":" . $mapping_hash;
$this->resolved_profiles[ $cache_key ] = $profile;
```

If mapping changes during a request, clear cache explicitly where practical.

Do not implement persistent object cache yet.

Persistent cache belongs in:

```text
VS-013 — Performance and Cache Hardening
```

---

## 24. Class: EligibilityChecker

Create:

```text
includes/Membership/EligibilityChecker.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\EligibilityChecker
```

Responsibilities:

```text
1. Provide a clean API for checking whether a user is eligible for programme-aware audience rules.
2. Use MembershipProfileResolver internally.
3. Avoid duplicating PMPro logic.
4. Support users with multiple configured programmes.
5. Support derived aliases such as any_active_programme.
6. Normalize programme/level values through ProgrammeMappingRepository where needed.
7. Fail closed on unknown programmes, unknown levels, disabled programmes, or disabled mappings.
```

Required public methods:

```php
public function is_user_eligible_for_audience_rules( int $user_id, array $audience_rules ): bool;
public function get_matching_programmes_for_audience_rules( int $user_id, array $audience_rules ): array;
public function get_membership_profile( int $user_id ): array;
public function user_matches_derived_alias( int $user_id, string $alias ): bool;
```

Audience rule examples from VS-002:

```php
[
    "audience_scope" => "any_active_programme",
]
```

```php
[
    "audience_scope" => "programme",
    "programme_keys" => [ "vriendinneklub" ],
]
```

```php
[
    "audience_scope" => "programme_level",
    "programme_levels" => [
        [ "programme_key" => "vriendinneklub", "level_key" => "goud" ],
        [ "programme_key" => "voelgoednetwerk", "level_key" => "silwer" ],
    ],
]
```

```php
[
    "audience_scope" => "minimum_level",
    "programme_key" => "vriendinneklub",
    "minimum_level_key" => "silwer",
]
```

Rules:

```text
If audience rules are empty, return false.
If user has no active mapped programme, return false.
If a rule references an unknown programme key, return false for that rule.
If a rule references an unknown level key, return false for that rule.
For minimum_level, use configured level_rank.
For minimum_level, lower rank number means stronger/higher level.
Do not call PMPro directly inside EligibilityChecker.
```

---

## 25. Class: LegacyGroupAdapter

Create:

```text
includes/Membership/LegacyGroupAdapter.php
```

Class:

```php
Voelgoed\MemberPerks\Membership\LegacyGroupAdapter
```

Purpose:

```text
Allow old flat group-style logic to be translated safely while the planning docs and future slices move to the programme-aware model.
```

This is not the main runtime authority.

It is a compatibility adapter for cases where older code or docs still refer to examples like:

```text
vriendinneklub_goud
vriendinneklub_goud
vriendinneklub_silwer
vriendinneklub_silwer
vriendinneklub_brons
vriendinneklub_brons
voelgoednetwerk_brons
voelgoednetwerk_silwer
voelgoednetwerk_goud
all_paid_members
```

Responsibilities:

```text
1. Convert a MembershipProfile into flat legacy aliases where explicitly configured.
2. Convert flat required groups into programme-aware audience rules where possible.
3. Use ProgrammeMappingRepository aliases; do not invent fuzzy mappings.
4. Mark unknown legacy groups as invalid.
5. Prevent flat groups from becoming the canonical model again.
```

Example output for dual launch user:

```php
[
    "vriendinneklub_goud",
    "voelgoednetwerk_silwer",
    "all_paid_members",
]
```

Important:

```text
Do not use this adapter for new code unless a future slice explicitly needs backwards compatibility.
```

---

## 26. Admin Debug Panel

Create:

```text
includes/Admin/MembershipDebugPanel.php
```

Class:

```php
Voelgoed\MemberPerks\Admin\MembershipDebugPanel
```

Purpose:

Provide an admin-only diagnostic tool so we can verify:

```text
1. Programme mapping.
2. Programme aliases.
3. Level mappings.
4. Level aliases.
5. PMPro detection.
6. User MembershipProfile resolution.
7. Users with more than one configured programme.
8. Same-programme conflicts.
9. Duplicate PMPro level mapping problems.
10. Active PMPro levels not mapped to any enabled programme level.
11. mapping_hash and profile_hash.
```

Location:

```text
Tools > Voelgoed Perk Debug
```

Capability required:

```text
manage_vg_perks
```

Panel should display:

```text
1. Whether configuration provider is available.
2. Whether ACF is available, if ACF is the current provider.
3. Whether PMPro function pmpro_hasMembershipLevel is available.
4. Whether PMPro function pmpro_getMembershipLevelsForUser is available.
5. Current enabled programme definitions.
6. Programme aliases.
7. Current programme-level mapping.
8. Level aliases.
9. Mapping warnings.
10. Mapping hash.
11. Current logged-in admin user ID.
12. Current logged-in admin MembershipProfile.
13. Current logged-in admin profile hash.
14. A small form to enter a user ID and inspect that user's MembershipProfile.
15. Same-programme conflict warnings for inspected user.
16. Unmapped active PMPro level warnings for inspected user.
```

Security:

```text
1. Only users with manage_vg_perks can access.
2. Sanitize submitted user ID.
3. Use nonce verification for submitted debug form.
4. Escape all output.
5. Do not expose member email addresses unless absolutely required.
6. Do not expose payment/order details.
7. Do not expose raw personal information in hashes.
```

This panel may remain available on staging.

Before production release, VS-015 must decide whether the panel remains enabled, is hidden behind a debug flag, or is removed.

---

## 27. Plugin Integration

Update:

```text
includes/Plugin.php
```

The main Plugin class should instantiate/register:

```text
SettingsRepository
ProgrammeMappingRepository
MembershipProfileResolver
EligibilityChecker
LegacyGroupAdapter
MembershipDebugPanel
```

Preferred pattern:

```php
$settings_repository = new SettingsRepository();

$programme_mapping_repository = new ProgrammeMappingRepository(
    $settings_repository
);

$membership_profile_resolver = new MembershipProfileResolver(
    $programme_mapping_repository
);

$eligibility_checker = new EligibilityChecker(
    $membership_profile_resolver,
    $programme_mapping_repository
);

$legacy_group_adapter = new LegacyGroupAdapter(
    $programme_mapping_repository
);

$debug_panel = new MembershipDebugPanel(
    $programme_mapping_repository,
    $membership_profile_resolver
);

$debug_panel->register();
```

If the existing Plugin class has a service container or different clean pattern, follow the existing style.

Do not create unnecessary globals.

---

## 28. Hooks and Filters

This slice may add read-only filters for future extension.

Recommended filters:

```php
apply_filters( "vgmp_programme_mapping", $mapping );
apply_filters( "vgmp_programme_mapping_warnings", $warnings );
apply_filters( "vgmp_membership_profile", $profile, $user_id );
apply_filters( "vgmp_membership_profile_conflicts", $conflicts, $user_id );
```

Rules:

```text
1. Filters must not be required for core behaviour.
2. Filters must not bypass PMPro authority.
3. Filters must not allow public users to grant themselves eligibility.
4. Filters must not map arbitrary unknown PMPro levels to benefits without admin-configured mappings.
5. Document every filter in README.
```

Do not add AJAX or REST endpoints in this slice.

---

## 29. Logging

This slice may log mapping/resolution warnings if VS-002 logging settings are enabled.

Examples:

```text
Duplicate PMPro level ID mapping.
Programme alias collision.
Level alias collision.
Unknown programme key in settings.
Unknown level key in audience rule.
Multiple active mapped levels inside one programme.
Active PMPro level not mapped to any enabled programme level.
PMPro unavailable.
Configuration provider unavailable.
```

Rules:

```text
1. Do not log excessive per-page noise.
2. Do not log full personal details.
3. Do not log payment details.
4. Keep debug panel as the primary diagnostic surface for staging.
```

If plugin logging does not exist yet, do not build a full logger in this slice. Use admin debug output and README notes.

---

## 30. Acceptance Tests

The agent must test the following on staging where safe.

### Test 1 — Configuration provider / ACF inactive safety

Temporarily simulate provider unavailable only if safe, or inspect the guard logic.

Expected:

```text
No fatal error.
Mapping returns empty with warning.
Resolved user profile is guest/empty.
Debug panel reports provider unavailable.
No perks are granted.
```

Do not deactivate ACF on staging if that could disrupt other work. Code inspection is acceptable for this test.

---

### Test 2 — PMPro unavailable safety

Temporarily simulate PMPro unavailable only if safe, or inspect the guard logic.

Expected:

```text
No fatal error.
Resolved profile is guest/empty.
Debug panel reports PMPro unavailable.
No perks are granted.
```

Do not deactivate PMPro on staging if that could disrupt other work. Code inspection is acceptable for this test.

---

### Test 3 — Empty mapping

Clear or leave programme-level mapping fields empty in plugin settings.

Expected:

```text
User resolves to an empty MembershipProfile.
No fatal errors.
Debug panel shows empty mapping.
Unmapped PMPro levels do not grant perks.
```

---

### Test 4 — Launch programme mapping

Configure one valid PMPro level ID for a known test user.

Example:

```text
programme_key = vriendinneklub
level_key = goud
pmpro_level_ids = 17
```

Expected:

```php
"profile" => [
    "programmes" => [
        "vriendinneklub" => [
            "level_key" => "goud",
        ],
    ],
    "has_any_active_programme" => true,
    "has_multiple_programmes" => false,
]
```

A user without that PMPro level resolves to an empty profile.

---

### Test 5 — Billing term neutrality

Configure multiple Woo billing products/variations that grant the same PMPro level.

Expected:

```text
The resolver output is based on the active PMPro level only.
Monthly/yearly/quarterly billing differences do not create separate VGMP level identities.
No resolver code change is required.
```

---

### Test 6 — Dual-programme membership

Use a user who belongs to two configured programmes, or simulate if safe.

Example:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

Expected:

```php
[
    "has_any_active_programme" => true,
    "has_multiple_programmes" => true,
    "programmes" => [
        "vriendinneklub" => [
            "level_key" => "goud",
        ],
        "voelgoednetwerk" => [
            "level_key" => "silwer",
        ],
    ],
]
```

Derived aliases include:

```text
any_active_programme
all_paid_members
dual_programme_member
vriendinneklub_member
voelgoednetwerk_member
```

---

### Test 7 — Same-programme conflict

Use or simulate a user with two mapped active levels inside the same programme.

Example:

```text
vriendinneklub:silwer
vriendinneklub:goud
```

Expected:

```text
Lowest level_rank resolves as the active display/eligibility level.
Conflict is recorded in the profile.
Debug panel shows the conflict.
Benefits do not stack inside the same programme.
```

---

### Test 8 — Duplicate PMPro mapping configuration

Configure the same PMPro level ID in two mapping rows.

Example:

```text
PMPro Level 17 = vriendinneklub:goud
PMPro Level 17 = voelgoednetwerk:silwer
```

Expected:

```text
Mapping warning appears.
Ambiguous PMPro level does not grant eligibility.
Debug panel reports the duplicate mapping.
```

---

### Test 9 — Programme alias normalization

Configure a non-launch alias in mapping settings.

Expected:

```text
Alias normalizes to vriendinneklub.
Alias does not create a second programme.
Unknown aliases fail closed.
```

---

### Test 10 — Level alias normalization

Configure alias:

```text
vriendinneklub:goud aliases = goud
```

Expected:

```text
Admin/import logic may normalize goud to goud.
Runtime profile output uses level_key = goud.
Alias does not create a second level.
```

---

### Test 11 — Unmapped PMPro level fail-closed

Create or identify an active PMPro level that is not mapped.

Expected:

```text
User does not receive perks for that level.
Profile includes unmapped_pmpro_levels diagnostic if available.
Debug panel reports it.
No fatal errors.
```

---

### Test 12 — Future mapped level without code change

Create a temporary PMPro access-only test level on staging.

First, do not map it.

Expected:

```text
No perks.
Unmapped diagnostic only.
```

Then map it:

```text
programme_key = vriendinneklub
level_key = test_platinum
pmpro_level_ids = TEST_LEVEL_ID
level_rank = 5
```

Expected:

```text
MembershipProfile includes vriendinneklub:test_platinum.
No resolver code change is required.
```

---

### Test 13 — Future programme dry-run

Configure a disabled test programme.

Expected:

```text
No perks.
No profile eligibility.
No fatal errors.
```

Enable the programme with one mapped PMPro test level.

Expected:

```text
MembershipProfile can include the new programme.
Programme appears through configured programme definitions.
No resolver code change is required.
```

Do not mark future-programme production-ready unless later hub/cache/claims/reports/notifications slices are also tested.

---

### Test 14 — Deterministic order

Expected programme order:

```text
Configured sort_order ascending.
programme_key ascending as tie-break.
```

Expected derived alias order:

```text
any_active_programme
all_paid_members
dual_programme_member
{programme_key}_member sorted by programme order
```

Output must not change randomly between requests.

---

### Test 15 — Profile hash and mapping hash stability

For the same resolved profile and same mapping:

```text
profile_hash remains the same between requests.
```

When membership changes:

```text
vriendinneklub:goud -> vriendinneklub:silwer
```

Expected:

```text
profile_hash changes.
```

When mapping changes in a way that changes interpretation:

```text
mapping_hash changes.
profile_hash changes or is invalidated.
```

---

### Test 16 — EligibilityChecker audience rules

Test:

```text
any_active_programme
programme = configured launch programme
programme = future configured programme
programme_level = configured programme:configured level
minimum_level = configured programme:configured level
unknown programme
unknown level
```

Expected:

```text
Correct true/false results based on MembershipProfile.
No direct PMPro calls inside EligibilityChecker.
Unknown values fail closed.
```

---

## 31. Manual Verification Checklist

After implementation, verify:

```text
[ ] Plugin still activates cleanly.
[ ] Plugin version reflects this slice.
[ ] No fatal errors in wp-admin.
[ ] No fatal errors on frontend.
[ ] Tools > Voelgoed Perk Debug exists for admins.
[ ] Debug panel is blocked for users without manage_vg_perks.
[ ] Debug panel shows configuration provider availability.
[ ] Debug panel shows ACF availability if ACF is current provider.
[ ] Debug panel shows PMPro availability.
[ ] Debug panel shows enabled programme definitions.
[ ] Debug panel shows programme aliases.
[ ] Debug panel shows programme-level mapping.
[ ] Debug panel shows level aliases.
[ ] Debug panel shows mapping warnings.
[ ] Debug panel shows mapping_hash.
[ ] Debug panel can inspect a specific user ID.
[ ] Known Vriendinne-only user resolves correctly.
[ ] Known Netwerk-only user resolves correctly.
[ ] Known dual-programme user resolves correctly.
[ ] Same-programme conflict is detected if present.
[ ] Unmapped PMPro levels are reported and do not grant perks.
[ ] Logged-out frontend visitors are not affected.
[ ] WooCommerce cart/checkout is not modified.
[ ] PMPro membership behaviour is not modified.
[ ] Masteriyo is not modified.
[ ] Tickera is not modified.
[ ] No existing snippets or theme files were changed.
```

---

## 32. Explicit Non-Goals

Do not build any of the following in VS-003:

```text
Frontend member hub
Bricks query provider
Etch query provider
WooCommerce cart discounts
WooCommerce price display changes
WooCommerce coupons
Masteriyo course enrolment
Tickera ticket claims
Claim/redemption database table
Runtime status automation
Email notifications
Reports dashboard
Persistent object cache
REST API endpoints
Shortcodes
Frontend login prompts
CRM/newsletter logic
Billing/subscription lifecycle management
```

These belong to later slices.

---

## 33. README Update Requirements

Update README with:

```text
VS-003 implemented features
Programme and Level Mapping Model reference
Default launch programmes
Config-driven programme definitions
Programme aliases
Level mapping model
Level aliases
PMPro level ID mapping rules
MembershipProfile shape
mapping_hash and profile_hash
Dual-programme support
One-level-per-programme expectation
Conflict handling
Unmapped PMPro level fail-closed behaviour
Derived aliases
Debug panel location
Configuration-provider/ACF fail-closed behaviour
PMPro fail-closed behaviour
Known limitations
Next slice: VS-004 — Runtime Status Automation
```

Include this warning:

```text
This slice resolves membership profile only. It does not grant products, discounts, tickets, course access, or frontend hub visibility by itself.
```

---

## 34. Expected Agent Report

After completing the slice, provide a short report with:

```text
1. Files created.
2. Files modified.
3. Plugin version confirmed.
4. Whether plugin activation still works.
5. Whether configuration provider was detected.
6. Whether ACF was detected if ACF is current provider.
7. Whether PMPro was detected.
8. Example configured programme map.
9. Example configured programme-level map.
10. Example mapping_hash.
11. Example resolved MembershipProfile for at least one test user.
12. Whether dual-programme resolution was tested.
13. Whether same-programme conflict detection was tested.
14. Whether unmapped PMPro fail-closed behaviour was tested.
15. Whether future-level mapping was tested.
16. Any mapping warnings found.
17. Any assumptions made.
18. Any risks or follow-up issues.
19. Confirmation that no pricing, frontend hub, Masteriyo, Tickera, notifications, billing lifecycle, or claim logic was added.
```

Do not claim staging tests were performed unless they were actually performed.

---

## 35. Success Definition

VS-003 is complete only when the plugin can safely and deterministically resolve a **MembershipProfile** for a user based on active PMPro membership levels configured in the plugin settings.

A successful profile can represent:

```text
No mapped membership
One configured programme only
More than one configured programme
Same-programme conflict detected and resolved safely
Unmapped PMPro level detected and ignored for perks
Future mapped level without code changes
```

The successful output of this slice is not a visible member hub yet.

The successful output is a reliable internal answer to:

```text
What configured Voelgoed membership profile does this user have right now?
```

That answer will be used by later slices for:

```text
hub display
WooCommerce discounts
claims and redemptions
Masteriyo access
Tickera perks
reports
notifications
cache variation
```

Keep the resolver boring, explicit, deterministic, mapping-driven, and fail-closed.
