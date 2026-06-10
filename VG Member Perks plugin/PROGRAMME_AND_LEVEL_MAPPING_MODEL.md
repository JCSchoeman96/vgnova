# PROGRAMME_AND_LEVEL_MAPPING_MODEL.md

```text
Document version: 1.0.0
Status: New central model document for the Voelgoed Member Perks planning pack
Created: 2026-06-09
Applies to: VS-000 through VS-015
Related pass: VGMP Programme and Level Flexibility Patchlist v1.0.0
```

---

## 1. Purpose

This document defines the flexible programme, level, PMPro mapping, and Woo billing model for the **Voelgoed Member Perks** plugin.

It exists to prevent the plugin from becoming brittle when Voelgoed later adds:

```text
new subscription billing terms
new Woo subscription products
new Woo subscription variations
new PMPro access levels
new membership levels
new programme labels
new programmes
legacy or migration aliases
special campaign levels
founding-member levels
trial or invite-only access levels
```

The plugin must be strict about safety, but flexible about business structure.

The core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

---

## 2. Decision

The plugin must **not** hardcode the current PMPro level IDs, PMPro group IDs, Woo product IDs, Woo variation IDs, level names, subscription prices, or billing terms.

The plugin may seed defaults for the current Voelgoed launch, but it must treat programmes and levels as **configuration records**, not permanent PHP conditionals.

Correct:

```text
The plugin ships with default launch programmes.
The site config maps PMPro levels into those programmes.
Runtime code reads the configured mapping.
Unmapped levels fail closed.
```

Incorrect:

```text
If PMPro level ID is 17, user is always Goud.
If programme key is unknown, guess the closest launch programme.
If level name contains Goud, grant Goud perks.
Every PMPro level automatically grants perks.
Every Woo subscription product automatically grants perks.
```

---

## 3. Authority Model

The plugin must preserve these authority boundaries.

```text
PMPro
= membership/access authority

WooCommerce + WooCommerce Subscriptions
= billing, payment, subscription, renewal, order authority

Paid Memberships Pro WooCommerce add-on
= bridge that grants the linked PMPro level from Woo purchase flow

Voelgoed Member Perks
= perk eligibility, campaign rules, claim scopes, redemptions, target/audience lookup, hub display support, reports, and notifications

ACF Pro
= MVP admin editing/configuration provider only

Plugin repositories/services
= runtime authority for normalized config

Custom plugin tables
= runtime projections, ledgers, queues, reports, and audit proof
```

The plugin must not treat Woo product ownership as the direct membership authority. A user qualifies for perks because the active PMPro membership profile resolves to a configured programme level, not because the user once bought a Woo product.

---

## 4. Launch Defaults vs Future Programmes

The current Voelgoed launch defaults are:

```text
vriendinneklub
voelgoednetwerk
```

These are default launch programme keys, not the only possible programme keys forever.

A future programme may be added if all of the following are true:

```text
1. programme_key is unique and stable.
2. programme_label is configured.
3. programme is enabled.
4. at least one level is configured.
5. at least one enabled PMPro level ID is mapped.
6. hub/display behaviour is configured or intentionally disabled.
7. claim, cache, report, and notification behaviour is reviewed.
8. QA confirms there is no cross-user cache leakage.
9. QA confirms unmapped/unknown levels fail closed.
10. the programme is added through configuration, not hardcoded PHP branches.
```

The default two-programme model should remain supported:

```text
No membership
Vriendinneklub only
Voelgoednetwerk only
Both Vriendinneklub and Voelgoednetwerk
```

But the architecture should not break if a future enabled programme is added through the registry.

---

## 5. Current Known Launch Context

The current staging setup uses two PMPro groups:

```text
Voelgoedvriendinne
Voelgoednetwerk
```

Current PMPro access-only levels are intended to be granted by Woo variable subscription products through the PMPro WooCommerce add-on.

The current staging PMPro metadata must use these canonical plugin-facing keys:

```text
vriendinneklub
voelgoednetwerk
```

No Vriendinne legacy programme key is configured for the current staging solution. Runtime storage, PMPro metadata, index rows, and active programme records must use `vriendinneklub`.

Do not allow alternate Vriendinne keys and `vriendinneklub` to exist as separate active programme records unless the business explicitly creates two different programmes.

---

## 6. Rigid Invariants

These rules must remain rigid.

```text
1. Only mapped PMPro levels count for perks.
2. Unknown PMPro levels grant no perks.
3. Unknown programme keys grant no perks.
4. Disabled programme mappings grant no perks.
5. Disabled level mappings grant no perks.
6. Default rule: one resolved active level per programme.
7. Same-programme conflicts must not stack benefits.
8. Dual-programme users must not receive duplicate copies of the same benefit unless claim_scope explicitly allows it.
9. No programme/level/benefit stacking by default.
10. Claim limits are enforced by the ledger.
11. Woo renewal pricing is not silently modified by perk rules.
12. ACF is not runtime authority.
13. Runtime code must not guess missing mappings.
14. Execution modules must fail closed if mapping/config cannot be resolved.
```

---

## 7. Flexible Configuration Areas

These must be configurable, not hardcoded into execution logic.

```text
PMPro group IDs
PMPro level IDs
Woo product IDs
Woo variation IDs
level labels
level keys
programme labels
programme keys beyond seeded defaults
subscription prices
billing terms
number of levels per programme
number of programmes
hub pages
upsell pages
report filters
notification digest group labels
programme sort order
legacy aliases
```

---

## 8. ProgrammeDefinition

A programme is a configured business container for one family of membership levels.

Suggested value object:

```php
ProgrammeDefinition
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

### 8.1 `programme_key`

A stable lowercase identifier used by the plugin.

Examples:

```text
vriendinneklub
voelgoednetwerk
```

Rules:

```text
1. Must be unique.
2. Must be stable after launch.
3. Must not be derived from mutable labels.
4. Must not include spaces.
5. Must be normalized before runtime use.
6. Must not be guessed from PMPro group names.
```

### 8.2 `programme_label`

Human-readable display label.

Examples:

```text
Voelgoed Vriendinneklub
Voelgoednetwerk
```

Labels may be renamed without changing the stable key.

### 8.3 `programme_aliases`

Explicit legacy or migration keys that normalize to the canonical programme key.

Rules:

```text
1. Aliases must be explicit.
2. No fuzzy matching.
3. No automatic guessing from labels.
4. An alias may map to only one canonical programme.
5. A canonical programme key may have multiple aliases.
6. Alias collisions are configuration errors.
```

### 8.4 `enabled`

Only enabled programmes participate in runtime eligibility.

Disabled programmes may remain in settings for historical/reference purposes, but they must not grant perks.

---

## 9. ProgrammeLevelDefinition

A programme level is a configured tier within one programme.

Suggested value object:

```php
ProgrammeLevelDefinition
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
notes
```

### 9.1 `level_key`

A stable lowercase identifier for the level inside a programme.

Examples:

```text
brons
silwer
goud
platinum
```

or:

```text
brons
silwer
goud
platinum
```

The project should choose one internal convention and use it consistently.

Display labels may be Afrikaans or English. Internal keys should be boring and stable.

### 9.2 Recommended current-level convention

For the current Afrikaans Voelgoed setup, a clean internal convention would be:

```text
vriendinneklub:brons
vriendinneklub:silwer
vriendinneklub:goud
voelgoednetwerk:brons
voelgoednetwerk:silwer
voelgoednetwerk:goud
voelgoednetwerk:platinum
```

If existing metadata already uses mixed keys such as `silwer` or `goud`, do not break existing data blindly. Either normalize the metadata before launch or add explicit aliases.

### 9.3 `level_rank`

Rank controls hierarchy inside a programme.

Recommended convention:

```text
Lower number = stronger/higher level.
```

Example:

```text
goud = 10
silwer = 20
brons = 30
```

For a future higher level:

```text
platinum = 5
```

Rules:

```text
1. Rank is per programme.
2. Same rank in one programme should be avoided.
3. Rank is used for minimum-level audience rules.
4. Rank is used to resolve same-programme conflicts.
5. Rank must not be inferred from label text.
```

### 9.4 `pmpro_level_ids`

The PMPro level IDs that grant this programme level.

Rules:

```text
1. PMPro level IDs are external IDs.
2. They must be mapped explicitly.
3. One PMPro level ID must not be mapped to multiple programme levels unless an intentional migration rule exists.
4. Monthly/yearly/quarterly billing options should not require separate PMPro levels if they grant the same access.
5. Direct PMPro signup should remain disabled for Woo-billed access levels unless the billing architecture changes.
```

---

## 10. PMPro Mapping Rules

PMPro is the access authority. The plugin must use PMPro state only through the MembershipProfile resolver and configured mapping repositories.

### 10.1 Only mapped levels count

A PMPro level grants perks only when:

```text
1. PMPro level is active for the user.
2. PMPro level ID is mapped to an enabled programme level.
3. The programme is enabled.
4. The level is enabled.
5. Mapping is valid and not conflicting.
```

If any part is missing:

```text
Fail closed.
Grant no perks.
```

### 10.2 Unmapped PMPro levels

Unmapped PMPro levels must be treated as:

```text
unknown
not eligible for perks
visible in admin/debug reports
not fatal
not silently guessed
```

This prevents old test levels, free levels, migration levels, and accidental levels from receiving perks.

### 10.3 Duplicate PMPro mapping

A single PMPro level ID should normally map to exactly one configured programme level.

If one PMPro level ID appears in more than one mapping:

```text
1. Mark configuration as warning or critical depending on impact.
2. Do not duplicate benefits.
3. Resolver should fail closed for that level or resolve according to an explicit migration rule.
4. Admin report must identify the duplicate mapping.
```

### 10.4 Same-programme user conflict

A user should normally have one active level per programme.

Conflict example:

```text
vriendinneklub:goud
vriendinneklub:silwer
```

Runtime behaviour:

```text
1. Resolve highest configured rank for display if safe.
2. Do not stack same-programme benefits.
3. Record conflict in MembershipProfile debug context.
4. Surface the conflict in reports/admin tools.
5. Recommend upstream PMPro/group repair.
```

---

## 11. Woo Billing Product and Variation Rules

WooCommerce and WooCommerce Subscriptions are billing surfaces, not perk authority.

### 11.1 Membership products

Woo subscription products may grant PMPro levels through the PMPro WooCommerce add-on.

The plugin may inspect these for QA/reporting, but runtime perk eligibility should still resolve through active PMPro membership state.

Correct:

```text
Woo purchase grants PMPro level.
PMPro active level maps to programme level.
Programme level grants eligibility for perks.
```

Incorrect:

```text
User bought Product 905, therefore user is always Vriendinne Brons.
```

### 11.2 Billing terms

Billing terms such as monthly, yearly, quarterly, or promotional annual payment terms are billing choices.

They must not create duplicate perk levels when they grant the same access.

Example:

```text
Voelgoedvriendinne Goud — Maandeliks
Voelgoedvriendinne Goud — Jaarliks
Voelgoedvriendinne Goud — Kwartaalliks
```

If all grant the same PMPro level, the perks plugin sees only:

```text
vriendinneklub:goud
```

### 11.3 Adding a new billing term

To add a new billing term that grants the same access:

```text
1. Add Woo subscription variation or product.
2. Link it to the existing PMPro access level.
3. Confirm subscription/renewal behaviour.
4. Confirm the MembershipProfile still resolves to the same programme level.
5. No perk plugin code change should be required.
```

### 11.4 Adding a new Woo product for an existing level

If a new Woo product grants an existing PMPro level:

```text
1. Link the Woo product to the existing PMPro level.
2. Confirm checkout grants the correct PMPro level.
3. Confirm subscription cancellation/failure affects PMPro access as intended.
4. No perk plugin code change should be required.
```

---

## 12. ProgrammeMappingRepository

The plugin should use a repository/service to load and validate programme and level mappings.

Suggested class:

```php
Voelgoed\MemberPerks\Membership\ProgrammeMappingRepository
```

Suggested responsibilities:

```text
1. Read configured programme definitions through SettingsRepository.
2. Read configured programme-level mappings.
3. Normalize programme keys and aliases.
4. Normalize level keys and aliases.
5. Validate PMPro level IDs.
6. Detect duplicate PMPro level IDs across mappings.
7. Detect duplicate level keys inside one programme.
8. Detect disabled mappings.
9. Detect alias collisions.
10. Produce a deterministic mapping hash/version.
11. Expose only enabled, valid mappings to runtime resolvers.
12. Fail closed for unknown or unmapped values.
```

Suggested methods:

```php
public function all_programmes(): array;

public function enabled_programmes(): array;

public function get_programme( string $programme_key_or_alias ): ?ProgrammeDefinition;

public function get_level(
    string $programme_key_or_alias,
    string $level_key_or_alias
): ?ProgrammeLevelDefinition;

public function find_level_by_pmpro_level_id( int $pmpro_level_id ): ?ProgrammeLevelDefinition;

public function normalize_programme_key( string $programme_key_or_alias ): ?string;

public function normalize_level_key(
    string $programme_key_or_alias,
    string $level_key_or_alias
): ?string;

public function mapping_hash(): string;

public function validation_issues(): array;
```

---

## 13. MembershipProfile Resolver Contract

The MembershipProfile resolver must use configured mappings, not hardcoded level IDs or labels.

Suggested output shape:

```php
[
    "user_id" => 123,
    "programmes" => [
        "vriendinneklub" => [
            "programme_key" => "vriendinneklub",
            "programme_label" => "Voelgoed Vriendinneklub",
            "level_key" => "goud",
            "level_label" => "Goud",
            "rank" => 10,
            "source_pmpro_level_ids" => [17],
            "status" => "active",
        ],
        "voelgoednetwerk" => [
            "programme_key" => "voelgoednetwerk",
            "programme_label" => "Voelgoednetwerk",
            "level_key" => "silwer",
            "level_label" => "Silwer",
            "rank" => 20,
            "source_pmpro_level_ids" => [19],
            "status" => "active",
        ],
    ],
    "has_any_membership" => true,
    "has_multiple_programmes" => true,
    "conflicts" => [],
    "unmapped_pmpro_level_ids" => [],
    "membership_profile_hash" => "...",
    "programme_mapping_hash" => "..."
]
```

Rules:

```text
1. The resolver reads PMPro active levels.
2. The resolver maps PMPro levels through ProgrammeMappingRepository.
3. The resolver ignores unmapped PMPro levels for eligibility.
4. The resolver reports unmapped levels for admin/debug visibility.
5. The resolver detects same-programme conflicts.
6. The resolver does not call Woo product/subscription data to determine active membership.
7. The resolver returns deterministic hashes for cache/audit.
```

---

## 14. Audience Rules

Perks should target configured programme/level definitions, not raw PMPro level IDs.

Allowed audience rule concepts:

```text
all_active_members
specific programme
all levels in programme
explicit level keys
minimum rank in programme
manual/admin only
public display only
```

### 14.1 Explicit levels

Example:

```text
programme_key = vriendinneklub
level_selection_mode = explicit_levels
level_keys = goud,silwer
```

This should match configured levels only.

### 14.2 Minimum rank

Example:

```text
programme_key = vriendinneklub
level_selection_mode = minimum_rank
minimum_level_key = silwer
```

If lower number means higher rank:

```text
goud rank 10 qualifies
silwer rank 20 qualifies
brons rank 30 does not qualify
```

### 14.3 Future levels and minimum rank

When a future higher level is added, such as:

```text
platinum rank 5
```

it should automatically qualify for “minimum silwer” perks, because its rank is stronger.

This is why rank must be configured carefully.

---

## 15. Claim Scope and Future Levels

Claim scopes are stable enums and should not change casually:

```text
per_user
per_programme
per_programme_level
per_order
unlimited
```

Future levels should work without code changes because claim scope stores normalized programme/level context.

For `per_programme_level`, the ledger should count by:

```text
user_id
perk_id
programme_key
level_key
claim_period_key
```

If a level is renamed by label only, old ledger rows remain valid because `level_key` is unchanged.

If a level key is changed, that is a migration event and must not happen casually.

---

## 16. Adding a New Level

Example:

```text
Add Voelgoedvriendinne Platinum
```

Required steps:

```text
1. Create PMPro access-only level.
2. Disable direct PMPro signup if Woo remains billing authority.
3. Create or update Woo subscription product/variation.
4. Link Woo billing product to the new PMPro level through the PMPro WooCommerce add-on.
5. Add Programme Level Mapping:
   - programme_key = vriendinneklub
   - level_key = platinum
   - level_label = Platinum
   - level_rank = 5 or chosen rank
   - pmpro_level_ids = new PMPro level ID(s)
   - enabled = true
6. Review minimum-rank perks to confirm Platinum should qualify.
7. Reindex target/audience rows if required.
8. Clear membership/profile/config cache.
9. Test a user with the new level.
10. Confirm reports show the new level dynamically.
```

Expected outcome:

```text
No Woo discount code change.
No claim code change.
No hub query code change.
No Tickera/Masteriyo execution code change.
No notification code change.
```

---

## 17. Adding a New Billing Term

Example:

```text
Add quarterly billing to Voelgoednetwerk Goud
```

Required steps:

```text
1. Add Woo subscription variation or product.
2. Set correct subscription period/interval.
3. Set price.
4. Link to the existing PMPro access level for Netwerk Goud.
5. Test checkout.
6. Test renewal.
7. Test cancellation/failure access behaviour.
8. Confirm MembershipProfile still resolves to the same programme level.
```

Expected outcome:

```text
No new programme level required.
No perk plugin code change required.
```

---

## 18. Adding a New Programme

Example:

```text
Voelgoed Akademie
```

Required steps:

```text
1. Add ProgrammeDefinition:
   - programme_key
   - programme_label
   - enabled
   - aliases if needed
   - sort_order
   - hub configuration or intentionally disabled hub

2. Add ProgrammeLevelDefinition rows:
   - level_key
   - level_label
   - level_rank
   - pmpro_level_ids
   - enabled

3. Create/access PMPro levels.
4. Create/link Woo subscription billing products if paid.
5. Review hub display behaviour.
6. Review reports/admin filters.
7. Review notifications grouping.
8. Review cache keys and mapping hash.
9. Review claim scopes and dual/multi-programme behaviour.
10. Run QA before production.
```

A new programme is larger than a new level. It should be configuration-driven, but it still requires QA across display, claims, cache, reports, and notifications.

---

## 19. Renaming Labels vs Renaming Keys

### 19.1 Safe label rename

Changing this is normally safe:

```text
level_label: Silwer → Silwer
programme_label: Voelgoednetwerk → Voelgoednetwerk
```

Because stable keys remain the same.

### 19.2 Risky key rename

Changing these is a migration:

```text
level_key: old_level_key → silwer
programme_key: old_programme_key → vriendinneklub
```

Key changes affect:

```text
audience rules
index rows
redemption rows
order item meta
cache keys
reports
notifications
historical support/debugging
```

If key rename is required:

```text
1. Add alias first.
2. Confirm runtime normalizes correctly.
3. Plan migration for stored data only if needed.
4. Preserve historical audit meaning.
5. Do not silently change historical ledger records without approval.
```

---

## 20. Alias Rules

Aliases are for migration and compatibility, not business logic.

Allowed use:

```text
old_programme_key → vriendinneklub
silwer → silwer
```

Rules:

```text
1. Aliases must be configured explicitly.
2. Aliases normalize before eligibility evaluation.
3. Aliases must be visible in admin/debug reports.
4. Alias collision is a configuration error.
5. Aliases should not be used to create duplicate programmes or duplicate levels.
```

---

## 21. Cache and Mapping Version Rules

Programme/level mapping affects eligibility, hub display, discounts, claims, reports, and notifications.

Therefore the system must produce a deterministic mapping hash/version.

Suggested value:

```text
programme_mapping_hash
```

It should change when any of these change:

```text
programme enabled/disabled
programme key/alias configuration
programme hub config
level enabled/disabled
level key/alias configuration
level rank
PMPro level ID mapping
is_paid_level
cap mode where relevant
```

Cache invalidation should include:

```text
membership profile cache
programme mapping cache
active perks cache
audience cache
claim availability cache
reports cache
notification recipient/digest cache
```

Runtime cache keys that depend on membership must include either:

```text
programme_mapping_hash
```

or a derived:

```text
membership_profile_hash
```

---

## 22. Target and Audience Index Rules

The target/audience index table should validate against configured programmes and levels.

Do not validate only against hardcoded lists.

Correct:

```text
ProgrammeMappingRepository says programme_key is enabled and valid.
ProgrammeMappingRepository says level_key exists under programme.
Index row is allowed.
```

Incorrect:

```text
if programme_key in [vriendinneklub, voelgoednetwerk]
```

The launch defaults may still be seeded, but runtime validation must use the configured registry.

---

## 23. Reports and Admin UX Rules

Reports and admin screens must list configured programmes and levels dynamically.

Required buckets:

```text
configured programme
configured level
unknown/unmapped PMPro levels
legacy alias-normalized levels
disabled mappings
same-programme conflicts
users with no mapped membership
users with multiple programmes
```

Do not hardcode report filters to only Goud/Silwer/Brons.

The default launch levels may be shown as defaults, but admin UI should not break when a future level is added.

---

## 24. Notification Rules

Notification digests should group by configured programme labels.

For a dual-programme user:

```text
One digest per user per period by default.
Sections grouped by configured programme label.
Shared perks deduplicated.
```

If a future programme is added and notifications are enabled:

```text
1. It should appear as a configured group.
2. It must not leak to users outside the programme.
3. It must respect opt-out/dry-run rules.
4. It must respect cache/private-data rules.
```

---

## 25. BillingAccessMapper

Optional but useful QA/helper concept:

```php
Voelgoed\MemberPerks\Membership\BillingAccessMapper
```

Purpose:

```text
Inspect Woo subscription products/variations and PMPro Woo add-on links for readiness/reporting.
```

Suggested responsibilities:

```text
1. List Woo subscription products linked to PMPro levels.
2. List variations and billing terms.
3. Identify products that grant unmapped PMPro levels.
4. Identify mapped PMPro levels without billing products.
5. Identify draft/published status of membership products.
6. Support final QA and admin health checks.
```

Important:

```text
BillingAccessMapper is QA/support tooling.
It is not the runtime membership authority.
```

---

## 26. Failure Modes

The system must fail safely.

### 26.1 PMPro active level is unmapped

Runtime behaviour:

```text
No perks granted for that level.
Report unmapped level to admin/debug.
No fatal error.
```

### 26.2 Programme key is unknown

Runtime behaviour:

```text
Do not grant eligibility.
Do not create index rows.
Do not apply discounts.
Report configuration issue.
```

### 26.3 Level key is unknown

Runtime behaviour:

```text
Do not grant eligibility for that rule.
Mark perk as warning/misconfigured if audience depends on unknown level.
Fail closed in execution paths.
```

### 26.4 Mapping duplicate found

Runtime behaviour:

```text
Do not stack benefits.
Report critical mapping warning.
Resolve only if explicit rule exists; otherwise fail closed for ambiguous mapping.
```

### 26.5 Same-programme conflict on user

Runtime behaviour:

```text
Resolve highest rank for display if safe.
Do not stack.
Report conflict.
Use deterministic MembershipProfile hash.
```

---

## 27. QA Checklist for Future Changes

Use this checklist whenever adding or changing a programme, level, PMPro mapping, or Woo billing product.

### 27.1 New billing term

```text
[ ] Woo variation/product created.
[ ] Subscription period/interval correct.
[ ] Price correct.
[ ] Product/variation linked to intended PMPro level.
[ ] Checkout grants intended PMPro level.
[ ] Renewal works.
[ ] Cancellation/failure access behaviour works.
[ ] MembershipProfile output unchanged except expected billing context.
[ ] No plugin code change required.
```

### 27.2 New level

```text
[ ] PMPro access-only level created.
[ ] Direct PMPro signup disabled if Woo remains billing authority.
[ ] Woo subscription product/variation linked.
[ ] Programme Level Mapping added.
[ ] Level key stable.
[ ] Level rank correct.
[ ] Aliases configured if needed.
[ ] Minimum-rank audience rules reviewed.
[ ] User with new level resolves correctly.
[ ] Hub display tested.
[ ] Discount eligibility tested.
[ ] Claim scope tested if relevant.
[ ] Reports show new level.
[ ] Cache cleared/invalidation verified.
```

### 27.3 New programme

```text
[ ] ProgrammeDefinition added.
[ ] Unique programme_key selected.
[ ] Programme label configured.
[ ] Programme enabled.
[ ] Hub page configured or intentionally disabled.
[ ] Upsell page configured if needed.
[ ] Programme levels mapped.
[ ] PMPro levels linked.
[ ] Woo billing products linked if paid.
[ ] Audience rules can target programme.
[ ] Audience index validates programme.
[ ] MembershipProfile resolver supports programme.
[ ] Hub display tested.
[ ] Claims tested.
[ ] Woo discounts tested if applicable.
[ ] Reports tested.
[ ] Notifications tested if enabled.
[ ] Cache safety tested across users.
```

---

## 28. Static/Code Review Rules

Implementation agents must avoid brittle hardcoding.

Prohibited patterns in runtime logic:

```php
if ( $level_id === 17 ) { ... }
if ( $programme_key === 'old_programme_key' ) { ... } // unless inside explicit alias normalizer
if ( in_array( $level_name, ['Goud', 'Silwer', 'Brons'], true ) ) { ... }
if ( $product_id === 905 ) { ... } // for membership authority
```

Allowed patterns:

```php
$profile = $membership_profile_resolver->resolve( $user_id );

$audience_evaluator->user_matches_perk( $profile, $perk_config );

$programme = $programme_mapping_repository->get_programme( $programme_key );

$level = $programme_mapping_repository->find_level_by_pmpro_level_id( $pmpro_level_id );
```

Allowed hardcoded values:

```text
controlled enums
plugin-owned table names
known option keys
known meta keys owned by plugin
seeded default programme keys, only in seed/bootstrap/fixtures/docs
```

---

## 29. Acceptance Criteria

This model is correctly adopted when:

```text
1. Programmes are loaded from configuration, with seeded defaults.
2. Levels are loaded from configuration, with PMPro IDs mapped explicitly.
3. Runtime code does not hardcode PMPro level IDs.
4. Runtime code does not hardcode Woo product IDs as membership authority.
5. Unmapped PMPro levels fail closed.
6. Unknown programme keys fail closed.
7. Same-programme conflicts do not stack benefits.
8. Dual-programme users are supported.
9. Future billing terms do not require new perk levels.
10. Future levels can be added through mapping and QA.
11. Future programmes can be added through registry/mapping and QA.
12. Cache keys/invalidation include mapping/profile hashes.
13. Reports/admin tools surface unmapped and conflicting states.
14. Final QA includes future-change tests.
```

---

## 30. Summary

The Voelgoed Member Perks plugin must be flexible in the right places and strict in the right places.

Strict:

```text
authority boundaries
mapped-level requirement
claim scopes
runtime statuses
ledger enforcement
cache safety
no guessing
fail closed
```

Flexible:

```text
programmes
levels
labels
PMPro IDs
Woo billing products
billing terms
hub pages
report filters
notification group labels
```

The final rule:

```text
Business structure belongs in configuration.
External IDs belong in mappings.
Runtime code consumes normalized profiles and config.
Unmapped or unknown values do not grant benefits.
```
