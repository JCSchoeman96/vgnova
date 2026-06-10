# FEATURE PLANNING PACK: VS-013 — Performance and Cache Hardening v1.3.0

## 0. v1.2.0 ACF Runtime Boundary Patch Summary

This version applies the ACF runtime-boundary patch.

```text
1. Adds vgmp_perk_config as a cache group.
2. Adds vgmp_cache_version_perk_config.
3. Requires cache keys to account for config provider, provider version, and perk_config_hash.
4. Adds config-change invalidation rules.
5. Adds diagnostics for current config provider and ACF availability.
6. Adds tests that changing ACF/provider configuration invalidates hub/cart/report caches.
```

## 0A. v1.3.0 Programme and Level Flexibility Patch Summary

This version applies the programme and level flexibility pass.

```text
1. Treats Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a hardcoded forever-list.
2. Requires all eligibility-dependent cache keys to include programme_mapping_hash or mapping version context.
3. Adds explicit cache group/version handling for configured programme and level mappings.
4. Requires cache invalidation when programme definitions, level mappings, PMPro level mappings, aliases, hub settings, or enabled flags change.
5. Requires MembershipProfile, hub, cart, claim, report, and notification-adjacent caches to vary by the mapping context that produced the result.
6. Adds alias-normalisation cache safety so legacy/current aliases do not create duplicate cache branches.
7. Adds future-level, future-programme, future-billing-term, and mapping-drift tests.
8. Preserves fail-closed behaviour for unknown, disabled, unmapped, or unavailable programme/level mapping data.
```

## Programme and Level Flexibility Doctrine

This document follows the central programme and level mapping model:

```text
1. Hardcode invariants.
2. Configure business structure.
3. Map external IDs.
4. Fail closed when unmapped.
5. Programme and level definitions are configuration records.
6. PMPro level IDs, PMPro group IDs, Woo product IDs, Woo variation IDs, prices, and billing terms are external data, not cache-authority constants.
7. The launch/default programmes are:
   - vriendinneklub
   - voelgoednetwerk
8. Future programmes and levels may be added through configured ProgrammeDefinition and ProgrammeLevelDefinition records.
9. Cache keys must use canonical configured programme_key and level_key values, not aliases.
10. Unknown programme keys, disabled programmes, unknown levels, disabled levels, unmapped PMPro levels, and unavailable mapping repositories must fail closed and invalidate or bypass unsafe caches.
```

Reference model:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
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

Build the performance, caching, invalidation, and diagnostic hardening layer for the **Voelgoed Member Perks** plugin.

This vertical slice must make the member perks system safer and faster under real logged-in member traffic, while preventing the most dangerous mistake:

```text
Caching one member’s perks, prices, claim state, membership profile, or hub output and showing it to another member.
```

The core goal:

```text
Fast member hub queries
Fast programme hub queries
Fast cart perk lookups
Fast claim-state display
Fast target and audience lookup
Fast admin reports where practical
Safe cache invalidation
Logged-in page cache protection
LiteSpeed-aware behaviour
Cloudflare-aware guidance
Query profiling tools
No stale member-specific perk leakage
```

This slice must improve performance without changing perk eligibility, pricing, claims, course enrolment, ticket generation, notification behaviour, or admin workflow semantics.

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
VS-012 — Admin UX Polish and Bulk Management
```

This slice must be tolerant if VS-009 or VS-010 are not implemented yet.

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected key components:

```text
ProgrammeRegistry
ProgrammeMappingRepository
ProgrammeDefinition
ProgrammeLevelDefinition
MembershipProfileResolver
MembershipProfile
CurrentUserPerksQuery
PerkAudienceEvaluator
PerkTargetRepository
PerkRuleRepository
RedemptionRepository
RuntimeStatusUpdater
Reports
Admin tools
```

Programme mapping expectation:

```text
ProgrammeRegistry may seed the launch/default programmes, but runtime cache identity must use enabled configured programmes and levels from SettingsRepository / ProgrammeMappingRepository.

The system must not assume that only Vriendinneklub and Voelgoednetwerk can ever exist.
```

Expected plugin-owned tables:

```text
{$wpdb->prefix}vgmp_perk_targets
{$wpdb->prefix}vgmp_perk_redemptions
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

Expected environment:

```text
WordPress
WooCommerce
Woo Subscriptions
Paid Memberships Pro
ACF Pro
Bricks / Etch
LiteSpeed Cache / OpenLiteSpeed likely
Cloudflare likely
Redis/Object Cache likely
```

If core slices VS-001 to VS-008 are missing, stop and report what is missing before implementing this slice.

---

## 3. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Request-level caching
Object-cache abstraction
Cache key factory
Cache invalidation
Query profiling
Performance diagnostics admin page
LiteSpeed/Cloudflare safety guidance
Logged-in hub cache policy
Private/no-store headers for member-specific responses
Cache-busting when perks/memberships/claims change
Admin tools to clear plugin caches
Performance-focused health checks
Safe plugin-owned table index review
```

This slice must not implement:

```text
New perk eligibility rules
New discount rules
New claim rules
New course enrolment logic
New ticket generation logic
New notification behaviour
New admin workflows unrelated to performance
Frontend redesign
Cloudflare API automation unless explicitly approved
LiteSpeed server config edits unless explicitly approved
Redis installation/configuration
Database changes to non-plugin tables
Destructive cleanup tools
```

This is a performance and safety slice, not a feature-behaviour slice.

---

## 4. Critical Caching Rule

Member-specific content must never be publicly cached.

The following are member-specific and must be treated as private:

```text
Member hub results
Programme hub results
Eligible perk lists
Claim state
Already-claimed status
Claims remaining
Member-specific product discounts
Member-specific ticket/course claim states
MembershipProfile data
Programme/level resolution
Programme mapping snapshots where used for eligibility
Programme aliases and level aliases where used for normalization
Cart perk prices
Checkout perk prices
Current-user REST responses
Claim/course/ticket action responses
```

Therefore:

```text
Do not full-page cache logged-in member hub pages.
Do not serve cached hub HTML across users.
Do not cache current-user REST responses publicly.
Do not cache claim/action responses publicly.
Do not store user-specific data in shared cache keys without user/profile context.
Do not use programme-only keys for data that includes claim state.
Do not use launch-only programme assumptions for caches that must support future configured programmes.
Do not build cache keys from aliases; normalize to canonical configured keys first.
Do not reuse eligibility-dependent caches after programme mappings, level mappings, aliases, enabled flags, or PMPro ID mappings change.
```

---

## 5. Required Deliverables

Add or consolidate the following plugin files:

```text
includes/
├── Performance/
│   ├── PerformanceServiceProvider.php
│   ├── RequestCache.php
│   ├── ObjectCache.php
│   ├── CacheKeyFactory.php
│   ├── CacheInvalidator.php
│   ├── CacheGroups.php
│   ├── QueryProfiler.php
│   ├── HubCachePolicy.php
│   ├── LiteSpeedIntegration.php
│   ├── CloudflareGuidance.php
│   ├── PerformanceDiagnostics.php
│   └── PerformanceSettings.php
│
├── Admin/
│   ├── PerformanceAdminPage.php
│   └── PerformanceAdminTools.php
│
└── Tools/
    ├── CacheClearTool.php
    ├── QueryDiagnosticsTool.php
    └── CacheHealthCheckTool.php
```

Update existing files where needed:

```text
includes/Plugin.php
includes/Membership/MembershipProfileResolver.php
includes/Membership/ProgrammeMappingRepository.php
includes/Query/CurrentUserPerksQuery.php
includes/Query/PerkAudienceEvaluator.php
includes/Perks/PerkTargetRepository.php
includes/Perks/PerkRuleRepository.php
includes/Claims/RedemptionRepository.php
includes/Woo/WooApplicablePerkResolver.php
includes/Woo/WooDiscountEngine.php
includes/Reports/ReportRepository.php
README.md
```

If file names differ in the actual implementation, preserve responsibilities and document the deviation.

If `RequestCache` already exists from VS-005, consolidate it into the `Performance` namespace or provide a backward-compatible adapter. Do not maintain two unrelated request-cache implementations.

---

## 6. Cache Layers

Use three levels of caching.

### 6.1 Request Cache

Scope:

```text
Current PHP request only
```

Good for:

```text
Current user MembershipProfile
Loaded PerkRule objects
Target lookup results
Audience lookup results
Claim counts already checked during one request
Visible perk IDs for one hub section
Woo product/category lookup results during cart calculation
```

This is always the safest cache layer because it dies at the end of the request.

---

### 6.2 WordPress Object Cache

Scope:

```text
Persistent if Redis/Object Cache is active
Non-persistent fallback if no external object cache exists
```

Good for:

```text
Non-sensitive global perk metadata
Programme registry/mapping snapshots
Programme aliases and level alias normalization maps
Active perk IDs by safe context
Target lookups
Audience index lookups
Perk rule configuration
Admin summary counts with short TTL
Integration detector results with short TTL
```

Use object cache carefully for user-specific values.

Default stance:

```text
Use object cache freely for global/perk-level data.
Use object cache sparingly for user-specific data.
Prefer request cache for user-specific hot paths unless correctness has been proven.
```

---

### 6.3 No Full-Page Cache for Logged-In Hub

Scope:

```text
LiteSpeed/Cloudflare/page cache layer
```

Rule:

```text
Do not page-cache logged-in hub pages, cart, checkout, account pages, or member-specific REST responses.
```

This slice should add detection, headers, admin warnings, and documentation. It must not blindly rewrite server/cache configuration.

---

## 7. RequestCache Class

Create or consolidate:

```php
Voelgoed\MemberPerks\Performance\RequestCache
```

Responsibilities:

```text
1. Store values for current request only.
2. Prevent repeated expensive calls in loops.
3. Provide simple get/set/has/forget/remember methods.
4. Support safe key prefixes for hot-path debugging.
```

Minimum methods:

```php
public function get( string $key, $default = null );

public function set( string $key, $value ): void;

public function has( string $key ): bool;

public function forget( string $key ): void;

public function remember( string $key, callable $callback );
```

Do not persist request-cache data.

---

## 8. ObjectCache Class

Create:

```php
Voelgoed\MemberPerks\Performance\ObjectCache
```

Responsibilities:

```text
1. Wrap wp_cache_get(), wp_cache_set(), and wp_cache_delete().
2. Apply plugin cache groups.
3. Respect global enable/disable setting.
4. Provide fallback behaviour.
5. Avoid fatal if object-cache drop-in is absent.
6. Support versioned group invalidation.
```

Minimum methods:

```php
public function get( string $key, string $group, $default = null );

public function set( string $key, $value, string $group, int $ttl = 300 ): bool;

public function delete( string $key, string $group ): bool;

public function remember( string $key, string $group, int $ttl, callable $callback );

public function get_group_version( string $group ): int;

public function bump_group_version( string $group ): int;

public function flush_group( string $group ): void;
```

Important:

```text
WordPress does not reliably support flushing a single group in all environments.
If group flush is unavailable, use versioned cache key namespaces.
```

Preferred strategy:

```text
Use versioned cache keys per group.
Increment group version to invalidate.
```

Do not flush the entire WordPress object cache by default.

---

## 9. Cache Groups

Create:

```php
Voelgoed\MemberPerks\Performance\CacheGroups
```

Recommended groups:

```text
vgmp_membership_profile
vgmp_programme_mapping
vgmp_perk_config
vgmp_perk_rules
vgmp_perk_targets
vgmp_perk_audience
vgmp_active_perks
vgmp_claims
vgmp_reports
vgmp_integrations
```

Do not use one giant shared group for everything.

Use TTLs by data type.

### `vgmp_programme_mapping`

Purpose:

```text
Cache normalized programme and level mapping snapshots produced by ProgrammeMappingRepository.
```

Must include:

```text
configured programme_key values
configured programme labels
programme aliases
enabled flags
hub settings
configured level_key values
level labels
level aliases
level_rank
mapped PMPro level IDs
mapping_hash or mapping_version
validation warnings
```

Rules:

```text
1. Cache canonical configured keys only.
2. Aliases are input-normalization helpers, not independent cache identities.
3. Unknown or disabled programmes must not create active cache branches.
4. Duplicate PMPro mappings or alias collisions must fail closed for eligibility-dependent caches.
5. Mapping hash must change when any enabled programme, level, alias, PMPro mapping, rank, hub setting, or enabled flag changes.
```

Invalidate when:

```text
Programme settings changed
Programme aliases changed
Level aliases changed
Programme enabled/disabled flag changed
Level enabled/disabled flag changed
PMPro level mappings changed
Level rank changed
Hub/upsell page settings changed
Manual programme mapping cache refresh is triggered
```



### `vgmp_perk_config`

Purpose:

```text
Cache normalized configuration produced by PerkConfigRepository.
```

Distinction:

```text
vgmp_perk_config = normalized config from provider
vgmp_perk_rules  = compiled execution rules derived from config
```

Invalidate when:

```text
ACF field saved
Perk post saved
Plugin-owned ACF JSON changed/synced
Settings/options changed
Config provider changed
Programme mapping changed
Future custom admin config changed
```
---

## 10. CacheKeyFactory

Create:

```php
Voelgoed\MemberPerks\Performance\CacheKeyFactory
```

Responsibilities:

```text
1. Build deterministic cache keys.
2. Include plugin cache group version.
3. Include user context only when required.
4. Include MembershipProfile context when required.
5. Prevent key collisions.
6. Keep keys short enough for object cache systems.
7. Hash long filter sets deterministically.
```

Example keys:

```text
membership_profile:user:{user_id}:map:{programme_mapping_hash}:v:{group_version}

programme_mapping:hash:{programme_mapping_hash}:v:{group_version}

perk_config:{perk_id}:provider:{provider}:hash:{config_hash}:v:{group_version}

perk_rule:{perk_id}:config:{config_hash}:v:{group_version}

targets_for_perk:{perk_id}:v:{group_version}

target_perks:{target_type}:{target_id}:v:{group_version}

audience_perks:{programme_key}:{level_key}:v:{group_version}

active_perks:user:{user_id}:profile:{membership_profile_hash}:map:{programme_mapping_hash}:section:{section}:type:{type}:programme:{programme_key}:campaign:{campaign}:claims:{claims_version}:v:{group_version}

claim_count:user:{user_id}:perk:{perk_id}:scope:{claim_scope}:period:{period}:v:{group_version}

report_summary:{period}:{filters_hash}:map:{programme_mapping_hash}:v:{group_version}

programme_alias_lookup:{alias_hash}:map:{programme_mapping_hash}:v:{group_version}

level_alias_lookup:{programme_key}:{alias_hash}:map:{programme_mapping_hash}:v:{group_version}
```

Rules:

```text
Never cache user-specific data without user_id or a safe user/profile context.
Never cache claim state without user_id, claim scope, and claim period where applicable.
Never cache cart prices in persistent object cache.
Never use programme-only keys for output that includes already-claimed or remaining-claims state.
Never allow raw unbounded query-string values in keys; sanitize and hash filter arrays.
Include config_provider, config_provider_version, and perk_config_hash where output depends on normalized perk configuration.
Include programme_mapping_hash where output depends on programme/level definitions, aliases, PMPro mappings, MembershipProfile resolution, audience rules, hub sections, claims display state, Woo discount eligibility, course/ticket eligibility, or reports.
Normalize programme and level aliases to canonical configured keys before creating cache keys.
```

---

## 10A. ProgrammeMappingCache and MappingHashFactory

Create or clarify:

```php
Voelgoed\MemberPerks\Performance\ProgrammeMappingCache
Voelgoed\MemberPerks\Performance\MappingHashFactory
```

Purpose:

```text
Cache and identify the normalized configured programme/level model used by MembershipProfile resolution, audience evaluation, hub queries, Woo discount eligibility, claims, reports, and notification grouping.
```

`MappingHashFactory` must generate a deterministic hash from normalized mapping data, including:

```text
enabled programme keys
programme labels where display cache depends on labels
programme aliases
programme supports_hub flag
programme hub/upsell page IDs where hub policy depends on them
enabled level keys
level labels where display cache depends on labels
level aliases
level ranks
mapped PMPro level IDs
is_paid_level flags
member caps / cap mode if used for eligibility or display
```

Rules:

```text
1. Sort programme keys deterministically.
2. Sort level keys deterministically inside each programme.
3. Sort PMPro level IDs numerically.
4. Sort aliases deterministically after normalization.
5. Exclude volatile timestamps unless they affect runtime behaviour.
6. Unknown/unmapped PMPro levels may appear in diagnostics, but must not create positive eligibility cache.
7. Any hash change must invalidate dependent caches through CacheInvalidator.
```

This class must not decide eligibility. It only identifies the mapping context used by other services.

## 11. CacheInvalidator

Create:

```php
Voelgoed\MemberPerks\Performance\CacheInvalidator
```

Responsibilities:

```text
Invalidate the correct cache groups when data changes.
```

Invalidation events:

```text
Perk saved
Perk trashed/deleted
Perk taxonomies changed
ACF perk fields saved
Runtime status updated
Target index updated
Audience index updated
Programme mapping options changed
Hub page/settings changed
PMPro user membership changed
Claim/redemption created
Claim/redemption status changed
Woo order with perk changes status
Course claim completed/failed
Ticket claim completed/failed
Admin clears cache manually
```

Preferred invalidation strategy:

```text
Increment group version options.
```

Example option keys:

```text
vgmp_cache_version_membership_profile
vgmp_cache_version_programme_mapping
vgmp_cache_version_perk_config
vgmp_cache_version_perk_rules
vgmp_cache_version_perk_targets
vgmp_cache_version_perk_audience
vgmp_cache_version_active_perks
vgmp_cache_version_claims
vgmp_cache_version_reports
vgmp_cache_version_integrations
```

Do not require Redis-specific flush support.

---

## 12. Invalidation Map

Implement this map clearly.

### Perk saved/updated

Invalidate:

```text
vgmp_perk_config
vgmp_perk_rules
vgmp_perk_audience
vgmp_active_perks
vgmp_reports
```

If target fields changed:

```text
vgmp_perk_targets
```

If audience rules changed:

```text
vgmp_perk_audience
vgmp_active_perks
```

### Perk trashed/deleted

Invalidate:

```text
vgmp_perk_rules
vgmp_perk_targets
vgmp_perk_audience
vgmp_active_perks
vgmp_claims
vgmp_reports
```

### Runtime status changed

Invalidate:

```text
vgmp_active_perks
vgmp_reports
```

### Target index changed

Invalidate:

```text
vgmp_perk_targets
vgmp_active_perks
vgmp_reports
```

### Audience index changed

Invalidate:

```text
vgmp_perk_audience
vgmp_active_perks
vgmp_reports
```

### Programme mapping options changed

This includes changes to:

```text
ProgrammeDefinition rows
ProgrammeLevelDefinition rows
programme aliases
level aliases
enabled flags
level ranks
PMPro level ID mappings
hub/upsell page settings
mapping validation state
```

Invalidate:

```text
vgmp_programme_mapping
vgmp_membership_profile
vgmp_perk_audience
vgmp_perk_config
vgmp_perk_rules
vgmp_active_perks
vgmp_claims
vgmp_reports
vgmp_integrations if integration warnings depend on mapping
```

Rules:

```text
1. Bump programme mapping version/hash before rebuilding dependent caches.
2. Do not reuse MembershipProfile cache across mapping changes.
3. Do not reuse hub/cart/report eligibility caches across mapping changes.
4. A newly added mapped level must become visible after cache invalidation without code changes.
5. An unmapped or disabled level must not keep stale benefits through old cache keys.
```

### Hub page/settings changed

Includes launch convenience hub fields and future programme-specific hub settings.

Invalidate:

```text
vgmp_programme_mapping if hub settings live inside programme definitions
vgmp_active_perks
vgmp_reports
```

Rules:

```text
Future configured programme hubs must invalidate the same cache paths as the launch hubs.
Do not hardcode only /my-vriendinneklub/ and /my-voelgoed-netwerk/ in cache invalidation logic.
```

### User membership changed

Invalidate:

```text
vgmp_membership_profile for affected user if supported
vgmp_active_perks if user-specific cache exists
vgmp_claims if claim eligibility depends on current membership
```

If per-user invalidation is hard:

```text
Use short TTL for MembershipProfile object-cache entries.
Provide manual clear tool.
Document limitation.
```

### Redemption created/updated

Invalidate:

```text
vgmp_claims
vgmp_active_perks
vgmp_reports
```

### Woo order status changed

Invalidate:

```text
vgmp_claims
vgmp_active_perks
vgmp_reports
```

### Course/ticket execution status changed

Invalidate:

```text
vgmp_claims
vgmp_active_perks
vgmp_reports
```

---

## 13. MembershipProfile Resolver Caching

Update:

```text
includes/Membership/MembershipProfileResolver.php
```

Add caching:

```text
Request-cache current user MembershipProfile.
Optional object-cache MembershipProfile with short TTL.
```

Recommended object-cache TTL:

```text
300 seconds
```

Reason:

```text
Membership changes are important and should not stay stale for long.
```

Cache key must include:

```text
user_id
programme_mapping_hash or programme mapping version
membership-profile cache group version
```

Rules:

```text
1. The profile hash must be derived from canonical configured programme_key and level_key values.
2. Aliases such as legacy programme keys may be accepted only before normalization.
3. Alias values must not create separate active cache branches.
4. Unmapped PMPro levels may be reported in debug/admin context but must not grant cached eligibility.
5. Same-programme conflicts must be represented in the profile and profile hash so unsafe cached output is not reused.
```

Output cache must preserve:

```text
programme_key
level_key
rank
status
has_any_membership
has_multiple_programmes
conflicts
membership_profile_hash
programme_mapping_hash
unmapped_pmpro_levels where useful for admin/debug only
```

PMPro hook handling:

```text
Inspect actual PMPro hooks/functions on staging before registering invalidation hooks.
Do not guess hook names blindly.
```

Possible events to inspect:

```text
Membership level changed
Membership checkout completed
Membership cancelled
Membership expired
Subscription/payment status changed
```

If hooks cannot be verified:

```text
Use short TTL and admin cache clear tool.
Document limitation.
```

---

## 14. Current User Perks Query Caching

Update:

```text
includes/Query/CurrentUserPerksQuery.php
```

Cache the generated list of eligible perk IDs for a user/programme/section/filter context.

Safe cache key must include:

```text
user_id
membership_profile_hash
programme_mapping_hash
programme_key or all
section
type
campaign
include_claimed flag
runtime status cache version
audience cache version
claim cache version if claim-aware
```

Important distinction:

```text
If output includes claim state or hidden already-claimed filtering:
Use user_id.

If output only includes active perks by programme/level and no claim state:
membership_profile_hash may be enough, but use user_id by default for safety.
```

For VS-013, prefer:

```text
user_id-specific keys for hub results.
```

Additional flexibility rule:

```text
When a new programme or level is added through configuration, active-perk query cache keys must change through programme_mapping_hash/version even if the same user_id is used.
```

Default TTL:

```text
300 seconds
```

Invalidate when:

```text
Perks change
Runtime status changes
Audience rules change
Programme mapping changes
Membership changes
Claims change
```

---

## 15. Programme Hub Cache Safety

The launch setup may have these hub pages:

```text
/my-voordele/
/my-vriendinneklub/
/my-voelgoed-netwerk/
```

Future configured programmes may add additional hub pages through ProgrammeDefinition / SettingsRepository.

Cache policy:

```text
Main dashboard and programme hubs are logged-in, member-specific pages.
They must not be public full-page cached.
They may use private request/object cache internally.
```

A dual-programme member must not get cached output intended for a single-programme member.

A Vriendinneklub-only member must not get cached Voelgoednetwerk-only output unless the page is explicitly an upsell/display-only state.

A user in a future configured programme must not receive stale launch-only hub output just because an older cache key ignored programme_mapping_hash.

Unknown or disabled programme hub requests must fail closed into an empty, upsell, or admin-warning state; they must not reuse a valid configured programme cache branch.

---

## 16. Perk Rule Caching

Update:

```text
includes/Perks/PerkRuleRepository.php
```

Cache PerkRule objects.

Key:

```text
perk_rule:{perk_id}:config:{perk_config_hash}:map:{programme_mapping_hash}:v:{perk_rules_version}
```

If a PerkRule has no audience dependency, the implementation may omit mapping context only if that is proven safe and documented. MVP should include programme_mapping_hash by default for simplicity and safety.

TTL:

```text
3600 seconds
```

Invalidate on:

```text
Perk saved
ACF field saved
Taxonomy changed
Programme/audience rule changed
Programme mapping changed
Programme/level aliases changed
PMPro level ID mapping changed
```

Do not cache invalid/malformed rules forever.

Rules with misconfigured status may be cached briefly for admin/reporting, but execution paths must fail closed.

---

## 17. Target and Audience Lookup Caching

Update:

```text
includes/Perks/PerkTargetRepository.php
```

Cache:

```text
Targets for perk
Perk IDs for target
Active perk IDs for target
Audience rows for perk
Perk IDs for programme
Perk IDs for programme level
```

Key examples:

```text
targets_for_perk:{perk_id}
perk_ids_for_target:{target_type}:{target_id}
active_perk_ids_for_target:{target_type}:{target_id}
audience_for_perk:{perk_id}
perk_ids_for_programme:{programme_key}
perk_ids_for_programme_level:{programme_key}:{level_key}
```

TTL:

```text
1800 seconds
```

Programme/level rules:

```text
1. Audience lookup keys must use canonical configured programme_key and level_key values.
2. Alias values must normalize before lookup/cache.
3. Unknown/disabled programme or level values must not create reusable positive cache entries.
4. Mapping changes must invalidate audience caches.
```

Invalidate on:

```text
Target index updated
Audience index updated
Perk saved/deleted
Runtime status changed for active target lookups
Programme mapping changed where audience evaluation depends on mapping
```

---

## 18. Claim Count Caching

Update:

```text
includes/Claims/RedemptionRepository.php
```

Cache claim counts carefully.

Safe cache keys:

```text
user_claim_count:{user_id}:{perk_id}:{claim_scope}:{period}
programme_claim_count:{user_id}:{perk_id}:{programme_key}:{period}
programme_level_claim_count:{user_id}:{perk_id}:{programme_key}:{level_key}:{period}
total_claim_count:{perk_id}:{period}
```

TTL:

```text
120 seconds
```

Invalidate on:

```text
Redemption created
Redemption status changed
Order status changed
Course/ticket execution status changed
```

Critical rule:

```text
Use cache for display state only where safe.
Use direct DB count, transaction/reservation logic, or immediately invalidated cache for final write validation.
```

Do not let stale claim-count cache allow over-claiming or incorrectly block an eligible claim.

---

## 19. Woo Cart Performance

Update:

```text
WooApplicablePerkResolver
WooDiscountEngine
```

Rules:

```text
1. Resolve MembershipProfile once per request.
2. Cache product target lookups per product ID.
3. Cache category target lookups per product ID.
4. Cache PerkRule objects per perk ID.
5. Cache claim availability checks per user/perk/period only inside request unless direct DB check is required.
6. Do not query all perks for every cart item.
7. Do not apply discounts by scanning all perks.
8. Do not perform public page cache operations from cart hooks.
```

Cart/checkout must favour correctness over caching.

Do not persist final cart item price calculations in object cache.

Do not cache cart-specific price decisions across users or sessions.

---

## 20. Reports Performance

Update:

```text
includes/Reports/ReportRepository.php
```

Rules:

```text
1. Cache dashboard summary cards with short TTL.
2. Cache programme-level aggregate counts with short TTL and programme_mapping_hash.
3. Do not cache filtered personal/PII-heavy exports unless explicitly safe.
4. Paginate redemption rows.
5. Use SQL aggregates.
6. Avoid loading all redemptions into PHP.
7. Avoid resolving every user’s MembershipProfile during normal dashboard load.
```

Recommended dashboard TTL:

```text
300 seconds
```

Invalidate on:

```text
Perk saved/status changed
Redemption created/status changed
Target/audience index changed
Programme mapping changed
Programme aliases or level aliases changed
```

CSV exports should generally run live with filters, not serve stale cached results.

---

## 21. HubCachePolicy

Create:

```php
Voelgoed\MemberPerks\Performance\HubCachePolicy
```

Responsibilities:

```text
1. Identify configured member hub pages.
2. Identify main dashboard and programme-specific hub pages.
3. Mark member hub responses as private/no-store where appropriate.
4. Mark current-user REST and claim endpoints as private/no-store.
5. Add admin warnings if hub pages appear publicly cacheable.
6. Provide helper output for LiteSpeed/Cloudflare exclusion instructions.
```

Use configured settings from VS-002 and the programme mapping model:

```text
vgmp_main_dashboard_page_id
vgmp_vriendinneklub_hub_page_id
vgmp_voelgoednetwerk_hub_page_id
programme_hub_page_id on configured ProgrammeDefinition rows
programme_supports_hub on configured ProgrammeDefinition rows
```

The dedicated Vriendinneklub/Netwerk fields are launch convenience fields. Future programme hubs should be discovered through SettingsRepository / ProgrammeRegistry.

Recommended headers for logged-in hub pages:

```text
Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: 0
```

Apply only where safe:

```text
Logged-in users
Member hub pages
Programme hub pages
Member-specific REST endpoints
Claim endpoints
Cart/checkout/account safety where plugin can affect response
```

Do not globally disable caching across the whole site.

---

## 22. LiteSpeed Integration

Create:

```php
Voelgoed\MemberPerks\Performance\LiteSpeedIntegration
```

Purpose:

```text
Work safely with LiteSpeed Cache where present.
```

This slice may:

```text
Detect LiteSpeed Cache plugin if active.
Warn if hub/cart/checkout/account pages are not excluded for logged-in/private behaviour.
Add no-cache headers for plugin endpoints/pages.
Document recommended LiteSpeed exclusions.
```

This slice must not:

```text
Rewrite LiteSpeed configuration automatically without explicit approval.
Clear entire site cache repeatedly.
Disable LiteSpeed Cache globally.
Implement ESI.
```

Recommended LiteSpeed exclusions/documentation:

```text
/my-voordele/
/my-vriendinneklub/
/my-voelgoed-netwerk/
Any future configured programme hub URL
/cart/
/checkout/
/my-account/
/wp-json/vg-member-perks/*
```

Also recommend:

```text
Do not cache logged-in users globally unless private ESI/fragments are intentionally configured and tested.
```

If LiteSpeed ESI is available:

```text
Do not implement ESI in VS-013.
Document as future advanced optimisation only.
```

---

## 23. Cloudflare Guidance

Create:

```php
Voelgoed\MemberPerks\Performance\CloudflareGuidance
```

Purpose:

```text
Provide admin guidance for Cloudflare cache/page rules and security considerations.
```

This slice may show recommended settings.

This slice must not call the Cloudflare API unless explicitly approved.

Recommended Cloudflare guidance:

```text
Do not cache logged-in HTML.
Do not cache /my-voordele/.
Do not cache /my-vriendinneklub/.
Do not cache /my-voelgoed-netwerk/.
Do not cache /cart/, /checkout/, or /my-account/.
Do not cache /wp-json/vg-member-perks/*.
Respect WordPress/WooCommerce cookies.
Keep Turnstile/security separate from member perk cache rules.
```

If Cloudflare APO is used:

```text
Verify logged-in bypass behaviour.
Do not assume APO is safe for member-specific hub pages without testing.
```

Do not modify Cloudflare Zero Trust rules in this slice.

---

## 24. REST Endpoint Cache Safety

Member-specific REST endpoints must be private.

Endpoints likely include:

```text
/wp-json/vg-member-perks/v1/current-user-perks
/wp-json/vg-member-perks/v1/claim
/wp-json/vg-member-perks/v1/course-claim
/wp-json/vg-member-perks/v1/ticket-claim
```

Rules:

```text
Do not allow public caching.
Require authentication where appropriate.
Send no-store/private headers.
Do not expose other users' data.
Do not expose raw MembershipProfile internals unless admin-only.
Do not include cacheable public headers on claim/action responses.
```

---

## 25. Performance Settings

Create:

```php
Voelgoed\MemberPerks\Performance\PerformanceSettings
```

Admin settings under:

```text
Member Perks > Settings > Performance
```

Settings:

```text
Enable object cache integration
Enable query profiling for admins
Enable performance debug panel
MembershipProfile cache TTL
Perk rule cache TTL
Target lookup cache TTL
Claim count display cache TTL
Report summary cache TTL
Programme mapping cache TTL
Mapping diagnostics enabled
Hub no-cache headers enabled
REST no-store headers enabled
LiteSpeed guidance enabled
Cloudflare guidance enabled
```

Defaults:

```text
Object cache integration: enabled
Query profiling: disabled
Performance debug panel: disabled
MembershipProfile TTL: 300
Perk rule TTL: 3600
Target lookup TTL: 1800
Claim display TTL: 120
Report summary TTL: 300
Programme mapping TTL: 300
Mapping diagnostics: enabled for admins
Hub no-cache headers: enabled
REST no-store headers: enabled
LiteSpeed guidance: enabled
Cloudflare guidance: enabled
```

Do not expose these settings to normal members.

---

## 26. QueryProfiler

Create:

```php
Voelgoed\MemberPerks\Performance\QueryProfiler
```

Purpose:

```text
Allow admins/developers to inspect plugin query behaviour on staging.
```

Only active when:

```text
WP_DEBUG is true
or performance profiling setting is enabled
and current_user_can( "manage_vg_perks" )
```

Track:

```text
Number of MembershipProfile resolver calls
Number of audience evaluator calls
Number of target lookup queries
Number of PerkRule loads
Number of redemption count queries
Current user perks query time
Woo cart perk resolution time
Report query time
Cache hits/misses where available
Cache key context warnings where available
```

Do not show profiling to normal members.

Do not log excessive data on production unless explicitly enabled.

---

## 27. Performance Diagnostics Admin Page

Create:

```php
Voelgoed\MemberPerks\Admin\PerformanceAdminPage
```

Location:

```text
Member Perks > Tools > Performance
```

Show:

```text
Object cache status
External object cache detected yes/no
Current config provider
ACF active yes/no
Current programme_mapping_hash
Programme mapping cache version
Programme alias count
Level alias count
Unmapped PMPro level warnings
Perk config cache version
Perk rule cache version
Last config invalidation time if tracked
Cache group versions
Main dashboard page configured yes/no
Launch Vriendinneklub hub page configured yes/no
Launch Voelgoednetwerk hub page configured yes/no
Future configured programme hub pages configured/disabled status
LiteSpeed detected yes/no
Cloudflare guidance checklist
REST endpoint cache warning checklist
Recent query profiler summary if enabled
Manual clear plugin caches button
Recommended exclusions
Cross-user cache safety reminders
```

Do not expose secrets, API tokens, or sensitive config.

---

## 28. CacheClearTool

Create:

```php
Voelgoed\MemberPerks\Tools\CacheClearTool
```

Actions:

```text
Clear all plugin cache versions
Clear MembershipProfile cache version
Clear programme mapping cache version
Refresh programme mapping cache/hash
Clear perk config cache version
Clear perk rule cache version
Clear target lookup cache version
Clear audience lookup cache version
Clear active perks cache version
Clear claims cache version
Clear reports cache version
Clear integration detector cache version
```

Implementation:

```text
Increment version option(s).
```

Do not flush the entire WordPress object cache by default.

Do not purge full LiteSpeed/Cloudflare cache automatically unless explicitly approved.

---

## 29. CacheHealthCheckTool

Create:

```php
Voelgoed\MemberPerks\Tools\CacheHealthCheckTool
```

Checks:

```text
Main dashboard page is configured.
Launch Vriendinneklub hub page is configured or intentionally disabled.
Launch Voelgoednetwerk hub page is configured or intentionally disabled.
Any future enabled programme with supports_hub = true has a configured hub page or an intentional disabled/upsell state.
Hub pages are not publicly cacheable for logged-in users.
Object cache available.
Cache versions exist.
Programme mapping hash/version exists.
Alias normalization does not create duplicate positive cache branches.
REST endpoints send no-store/private headers.
Cart/checkout/account pages are not wrongly cached.
LiteSpeed detected and guidance shown.
Cloudflare guidance shown.
Old table names are not being used by cache repositories.
```

If automated HTTP checks are too heavy or unreliable:

```text
Provide admin checklist and clear warnings.
```

---

## 30. Database Index Review

This slice may inspect plugin-owned tables and confirm required indexes exist.

Tables:

```text
wp_vgmp_perk_targets
wp_vgmp_perk_redemptions
```

Do not modify non-plugin WordPress/WooCommerce/PMPro tables.

If plugin-owned table indexes are missing from earlier slices:

```text
Add safe schema upgrade.
Document it.
```

Expected important indexes:

```text
vgmp_perk_targets:
- target_lookup (target_type, target_id)
- target_value_lookup (target_type, target_value)
- perk_id
- perk_target_lookup
- scope_target_lookup if implemented
- audience_lookup if implemented

vgmp_perk_redemptions:
- user_perk
- user_perk_period
- perk_status
- perk_period_status
- order_lookup
- target_lookup
- programme_lookup if implemented
- eligibility_hash_lookup if implemented
- programme_mapping_hash_lookup if implemented
- perk_config_hash_lookup if implemented
- claimed_at
```

If the earlier schema does not include a useful programme/claim-scope index but reports or claims need it:

```text
Propose and add only plugin-owned safe schema upgrades.
Do not silently alter non-plugin tables.
```

---

## 31. Page Cache Exclusion Recommendations

README and admin guidance must recommend excluding these from public/page cache:

```text
Member hub pages:
/my-voordele/
/my-vriendinneklub/
/my-voelgoed-netwerk/
Any future configured programme hub URL

Woo pages:
/cart/
/checkout/
/my-account/

Plugin REST:
/wp-json/vg-member-perks/*

Claim/action URLs if any non-REST actions exist.
```

Important:

```text
Excluding these pages does not mean disabling cache for the whole site.
```

---

## 32. Logged-In Cache Warning

Add an admin warning if:

```text
Any hub page is configured
LiteSpeed Cache is active
and no documented/known exclusion is confirmed
```

The warning should say:

```text
The member hub contains user-specific membership perks and claim state. Ensure these pages are excluded from public page cache for logged-in users.
```

Do not claim the plugin can verify every Cloudflare/LiteSpeed setting unless it actually can.

---

## 33. Cloudflare and Turnstile Caution

The site may use Cloudflare and Turnstile.

Rules:

```text
Do not bypass Turnstile/security.
Do not cache authenticated REST claim endpoints.
Do not create Cloudflare page rules automatically.
Do not expose claim endpoints as publicly cacheable.
```

If Cloudflare blocks admin-ajax or REST:

```text
Document that REST endpoints require authenticated access and should not be challenged in a way that breaks normal logged-in use.
```

Do not modify Cloudflare Zero Trust rules in this slice.

---

## 34. Performance Testing Plan

Test on staging only.

### Test 1 — Plugin loads without object cache

```text
Disable/ignore external object cache if safe.
Expected: plugin works with request cache and non-persistent object cache fallback.
```

### Test 2 — Object cache detection

```text
Check performance admin page.
Expected: object cache status shown accurately.
```

### Test 3 — MembershipProfile resolver call count

```text
Load member hub with multiple sections/cards.
Expected: MembershipProfile resolves once per request or near-once.
```

### Test 4 — Hub query caching

```text
Load hub twice as same user.
Expected: second load has cache hits for eligible perk query where enabled.
```

### Test 5 — Cross-user safety: programme level

```text
User A is Vriendinneklub Goud.
User B is Vriendinneklub Brons.
Load hub as User A, then User B.
Expected: User B never sees User A-only perks.
```

### Test 6 — Cross-user safety: dual-programme

```text
User A has Vriendinneklub Goud and Voelgoednetwerk Silwer.
User B has only Vriendinneklub Goud.
Load all hubs as both users.
Expected: User B never receives User A's Voelgoednetwerk hub output or claim state.
```

### Test 7 — Claim state invalidation

```text
User claims a perk.
Reload hub.
Expected: already-claimed/remaining state updates correctly.
Expected: no stale available state after claim.
```

### Test 8 — Perk save invalidation

```text
Change perk eligibility/section/status.
Reload hub.
Expected: cache invalidated and hub updates.
```

### Test 9 — Runtime status invalidation

```text
Run runtime status updater.
Expected: active/upcoming/expired/misconfigured hub results update.
```

### Test 10 — Cart discount performance

```text
Add several products to cart.
Expected: target/perk lookup does not query all perks per item.
Expected: no discount behaviour changes.
```

### Test 11 — Cart price correctness

```text
Refresh cart multiple times.
Expected: no discount compounding.
Expected: correct member price still applies.
```

### Test 12 — REST no-cache headers

```text
Call current-user perks endpoint as logged-in user.
Expected: private/no-store headers present.
```

### Test 13 — Logged-out REST

```text
Call member-specific endpoint logged out.
Expected: blocked and not publicly cacheable.
```

### Test 14 — Reports summary cache

```text
Load reports dashboard.
Create redemption.
Reload after invalidation.
Expected: summary updates correctly.
```

### Test 15 — Admin cache clear tool

```text
Clear plugin caches.
Expected: cache version increments.
Expected: plugin still works.
```

### Test 16 — LiteSpeed/Cloudflare guidance

```text
Open performance page.
Expected: clear recommendations and no false certainty.
```

### Test 17 — Legacy cache/table safety

```text
Search plugin cache/repository code for old table names:
wp_vg_perk_targets
wp_vg_perk_redemptions
Expected: no active usage remains except explicit legacy inspectors/warnings.
```

---

### Test 18 — Config-change invalidation

```text
Change a perk configuration value, for example discount value, claim scope, audience rule, or target.
Reload cart/hub/report views.
Expected: old config is not used.
Expected: cache versions/hashes invalidate correctly.
Expected: new rule applies or fails closed correctly.
Expected: diagnostics show current provider/cache version context.
```

### Test 19 — Programme mapping hash invalidation

```text
Change a programme mapping row, such as level rank, PMPro level IDs, enabled flag, or programme label.
Expected: programme_mapping_hash changes.
Expected: MembershipProfile cache invalidates.
Expected: hub/cart/report eligibility caches invalidate.
Expected: stale cache keys do not continue granting old benefits.
```

### Test 20 — Future mapped level cache safety

```text
Add a temporary mapped PMPro level under an existing configured programme.
Expected: mapping hash changes.
Expected: user on that level resolves into MembershipProfile without code changes.
Expected: hub/cart/report caches reflect the new mapped level after invalidation.
```

### Test 21 — Future billing term non-effect

```text
Add a new Woo billing variation that grants an already mapped PMPro level.
Expected: programme_mapping_hash does not need to change if access level is unchanged.
Expected: MembershipProfile output remains same programme/level.
Expected: perks continue to work without Woo discount cache logic changes.
```

### Test 22 — Programme alias normalization cache safety

```text
Add an explicit programme alias, for example a legacy key that normalizes to a configured programme.
Expected: alias normalizes to canonical programme_key.
Expected: no duplicate active cache branch is created for the alias.
Expected: unknown aliases fail closed and do not receive cached positive results.
```

### Test 23 — Future configured programme hub cache safety

```text
Configure a temporary future programme with hub disabled.
Expected: no fatal errors and no public cache leakage.
Enable hub support and assign a hub page.
Expected: HubCachePolicy discovers the programme through ProgrammeRegistry / SettingsRepository.
Expected: page receives private/no-store treatment for logged-in users.
```

## 35. Acceptance Criteria

VS-013 is complete only when:

```text
1. Request cache is consolidated and used in hot paths.
2. Object cache wrapper exists with versioned group invalidation.
3. Cache key factory prevents cross-user cache leaks.
4. MembershipProfile resolver uses safe caching.
5. Current user perk query uses safe user/profile-aware caching.
6. Perk rule, target, and audience lookups are cached safely.
7. Claim count display caching does not break final validation.
8. Cache invalidation runs on perk saves, status changes, target/audience changes, programme mapping changes, membership changes, and redemptions.
9. Eligibility-dependent cache keys include programme_mapping_hash or equivalent mapping version context.
10. Programme/level aliases normalize before cache-key creation and do not create duplicate positive cache branches.
11. Future mapped levels invalidate MembershipProfile/hub/cart/report caches without code changes.
12. Future configured programme hubs are discovered through settings/mapping, not hardcoded launch-only URLs.
13. Hub pages and member REST endpoints send private/no-store headers where appropriate.
10. Performance admin page exists.
11. Plugin cache clear tool exists.
12. Query profiler exists and is admin-only.
13. LiteSpeed and Cloudflare guidance is documented.
14. Correct plugin-owned table names are used: vgmp_perk_targets and vgmp_perk_redemptions.
15. No pricing, claim, course, ticket, notification, or admin-workflow behaviour is changed.
16. No public full-page cache is used for member-specific hub output.
17. Plugin does not fatal if object cache, LiteSpeed, Cloudflare, Woo, PMPro, Masteriyo, Tickera, ACF, Bricks, or Etch are absent.
18. README documents cache layers, invalidation, exclusions, and testing.
```

---

## 36. README Update Requirements

Update README with:

```text
VS-013 implemented features
Cache layers explained
Cache groups explained
Cache key safety rules
MembershipProfile hash safety
Programme mapping hash/version safety
Programme/level alias cache safety
Future-level and future-programme cache behaviour
Programme hub cache policy
Invalidation map
Object cache behaviour
MembershipProfile cache TTL
Perk query cache TTL
Claim count cache caution
Hub page cache policy
LiteSpeed recommendations
Cloudflare recommendations
REST endpoint cache safety
Performance admin page instructions
Manual cache clear instructions
Known limitations
Next slice: VS-014 — Notifications and Member Communications
```

Include this warning prominently:

```text
The member hub, programme hubs, cart, checkout, claim endpoints, and current-user perk REST endpoints contain user-specific data. They must not be served from public/shared full-page cache.
```

Include this note:

```text
This plugin uses targeted request/object caching. It does not replace LiteSpeed Cache, Redis Object Cache, Cloudflare, or WooCommerce caching. It defines safe plugin-level cache behaviour and invalidation.
```

---

## 37. Explicit Out-of-Scope List

Do not implement these in VS-013:

```text
New discount rules
New claim rules
New course enrolment rules
New ticket generation rules
Email notifications
Frontend redesign
Cloudflare API automation
LiteSpeed server config automation
Redis installation/configuration
Full ESI implementation
Formal load testing infrastructure
Database changes to non-plugin tables
Destructive cleanup tools
```

These belong to later slices if needed.

---

## 38. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Cache groups added
Cache keys/versioning strategy
Programme mapping hash/versioning strategy
Programme alias/level alias cache handling
Invalidation hooks added
Hot paths updated
No-cache headers added
Performance settings added
Admin performance page added
Query profiler added
LiteSpeed/Cloudflare guidance added
Manual tests performed
Cross-user cache safety tests performed
Known limitations
Any assumptions made
Any dependency issues
```

If any cache invalidation hook could not be verified, state that clearly.

Do not claim Cloudflare/LiteSpeed rules were changed unless they were explicitly changed and tested.

---

## 39. Next Slice

After VS-013 is complete and tested, proceed to:

```text
VS-014 — Notifications and Member Communications
```

VS-014 will introduce optional member/admin notifications for new perks, expiring perks, claim confirmations, and weekly/monthly perk summaries.

Do not start VS-014 during this slice.
