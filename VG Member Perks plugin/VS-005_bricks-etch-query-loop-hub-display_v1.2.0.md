# FEATURE PLANNING PACK: VS-005 — Bricks/Etch Programme Hub Display

Version: `1.2.0`  
Previous version: `1.1.0`  
Previous source: `VS-005_bricks-etch-query-loop-hub-display_v1.1.0.md`  
Target plugin version after implementation: `0.5.0`

---

## 0. Patch Summary

This v1.1.0 hardening replaced the older flat `level_group` display model with the programme-aware model established in VS-001 to VS-003.

The old VS-005 assumed that current-user display could be filtered by:

```text
vg_perk_level_group IN current user's eligible level groups
```

That is no longer the canonical model.

The hardened model is:

```text
PMPro
↓
MembershipProfile
↓
Programme-aware audience rules
↓
Display-safe current-user eligible active perks query
↓
Main dashboard and programme hub displays
```

This slice remains display-only. It must not execute business logic.

### v1.2.0 Programme and Level Flexibility Pass

This version applies the controlled programme/level flexibility doctrine from `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

Key changes in this pass:

```text
1. Treat `vriendinneklub` and `voelgoednetwerk` as launch/default programme hubs, not the only possible programmes forever.
2. Require hub/query logic to read enabled configured programmes from ProgrammeRegistry / SettingsRepository.
3. Allow future programme hubs through configured programme records, hub page IDs, and QA.
4. Replace fixed programme validation with: all OR enabled configured programme_key.
5. Require aliases to normalize before display/query evaluation.
6. Ensure unknown or disabled programmes fail closed and show safe empty/admin-warning states.
7. Add mapping_hash / programme_mapping_hash to request-cache and display-context rules.
8. Confirm that adding a new level or billing term must not require hub-display code changes.
9. Keep this slice display-only: no claims, discounts, tickets, courses, orders, or notifications.
```

The launch UX still uses:

```text
/my-voordele/
/my-vriendinneklub/
/my-voelgoed-netwerk/
```

But the architecture must support future configured programme hubs without duplicating query engines or hardcoding new programme branches.

---

## 1. Objective

Build the first visible member hub display layer for the **Voelgoed Member Perks** plugin.

This slice must allow Bricks Builder and/or Etch to display **only the current logged-in user's eligible active perks**, grouped by hub section and optionally filtered by an enabled configured programme.

The system must support these member states:

```text
1. No active membership
2. Voelgoed Vriendinneklub only
3. Voelgoednetwerk only
4. Both Voelgoed Vriendinneklub and Voelgoednetwerk
```

This slice must support the launch main dashboard plus the two launch programme-specific hub pages, while remaining compatible with future configured programme hubs:

```text
/my-voordele/              Main member benefits dashboard
/my-vriendinneklub/        Vriendinneklub programme hub
/my-voelgoed-netwerk/      Voelgoednetwerk programme hub
```

Future programme hubs must be added through programme configuration, not by creating new hardcoded query engines.

This slice is display-only.

It must not:

```text
apply discounts
grant course access
issue tickets
create claims
create WooCommerce orders
modify pricing
modify subscriptions
send emails
create notification records
```

---

## 2. Current Baseline Assumptions

This slice assumes the previous slices exist or have been implemented in their hardened form:

```text
VS-000 v1.1.0 — Current Site Discovery and Conflict Audit
VS-001 v1.2.0 — Plugin Foundation, CPT and Taxonomies
VS-002 v1.3.0 — ACF Fields and Options Page
VS-003 v1.3.0 — PMPro Membership Profile Resolver
VS-004 v1.2.0 — Runtime Status Automation
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0 — Programme and Level Mapping Model
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

Expected taxonomies from VS-001:

```text
vg_perk_programme
vg_perk_type
vg_perk_section
vg_perk_runtime_status
vg_perk_campaign
```

Deprecated / legacy taxonomy:

```text
vg_perk_level_group
```

The legacy `vg_perk_level_group` taxonomy may exist on a staging site from older experiments, but it must not be the canonical runtime authority for VS-005.

Expected runtime statuses from VS-004:

```text
active
upcoming
expired
paused
archived
misconfigured
```

Only `active` perks are shown in normal member hub sections.

Expected resolver from VS-003:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
```

Expected output concept, with launch programme examples only:

```php
[
    "user_id" => 123,
    "programmes" => [
        "vriendinneklub" => [
            "level_key" => "goud",
            "level_label" => "Goud",
            "rank" => 30,
            "status" => "active",
        ],
        "voelgoednetwerk" => [
            "level_key" => "silwer",
            "level_label" => "Silwer",
            "rank" => 20,
            "status" => "active",
        ],
    ],
    "has_any_membership" => true,
    "has_multiple_programmes" => true,
    "derived_audience_keys" => [
        "programme:vriendinneklub",
        "programme_level:vriendinneklub:goud",
        "programme:voelgoednetwerk",
        "programme_level:voelgoednetwerk:silwer",
        "all_paid_members",
    ],
    "profile_hash" => "...",
    "mapping_hash" => "...",
]
```

If the installed VS-003 still exposes only the older flat group resolver, or does not expose mapping-driven programme/level resolution, this slice must stop and report that VS-003 needs to be upgraded before VS-005 is implemented.

Expected programme/mapping layer from VS-001 to VS-003:

```text
ProgrammeRegistry / SettingsRepository
ProgrammeMappingRepository
MembershipProfileResolver
EligibilityChecker
```

Rules:

```text
1. ProgrammeRegistry provides enabled configured programmes and aliases.
2. ProgrammeMappingRepository validates level mappings and exposes mapping_hash.
3. MembershipProfileResolver returns the current user's normalized profile.
4. VS-005 consumes those services; it does not inspect PMPro or Woo billing IDs directly.
```

---

## 3. Non-Negotiable Scope Boundaries

This slice must only handle **display querying and display-safe card data**.

Do not implement:

```text
WooCommerce discounts
Cart price changes
Coupon rules
Claim buttons that create real claims
Redemption ledger
Masteriyo enrolment
Tickera ticket issuing
Ticket download links generated by this plugin
Checkout flow changes
Email notifications
Reports dashboards
Analytics
Theme edits
functions.php edits
WPCode snippet edits
PMPro level ID checks
Woo subscription product/variation checks as eligibility authority
```

This slice may create display helper methods and placeholder CTA states.

Placeholder CTAs must not execute business logic.

---

## 4. Core Architecture Rules

### 4.1 Page builder is not eligibility authority

Bricks and Etch must not decide who qualifies.

Correct model:

```text
MembershipProfileResolver
↓
Programme-aware EligibilityChecker
↓
CurrentUserPerksQuery
↓
Bricks/Etch query loop or REST fallback
↓
Styled card/component
```

Incorrect model:

```text
Show all perks
Hide wrong perks with page-builder visibility rules
Duplicate one loop per membership level
Hardcode PMPro level IDs into page sections
Use dynamic visibility as business logic
```

Dynamic visibility may be used only for presentation differences after the plugin has already resolved eligible display data.

### 4.2 One plugin, configured programme hubs

Do not create separate engines for Vriendinneklub, Voelgoednetwerk, or any future programme.

Correct model:

```text
one CPT
one resolver
one query service
one card formatter
configured programme-filtered hub surfaces
```

Incorrect model:

```text
one Vriendinne plugin flow
one Netwerk plugin flow
one future-programme plugin flow
duplicate ACF groups
duplicate queries with hardcoded level IDs or programme keys
```

### 4.3 Display does not equal entitlement execution

A displayed perk is an invitation or information card.

It is not proof that:

```text
a discount has already been applied
a course has been granted
a ticket has been issued
a claim has been reserved
```

Execution belongs to later slices.

### 4.4 Configured programme model

Programme-specific display must use configured programme definitions.

Rules:

```text
1. `vriendinneklub` and `voelgoednetwerk` are launch/default programmes.
2. Future programmes may be added through configuration and QA.
3. Runtime display code must not hardcode a closed programme list.
4. QueryContext must accept `all` or an enabled configured programme_key.
5. Programme aliases must normalize to canonical configured keys before query evaluation.
6. Unknown programme keys must fail closed and return safe empty/admin-warning states.
7. A disabled programme must not show member perks, even if old perks reference that key.
8. Display code must not inspect PMPro level IDs, Woo product IDs, or Woo subscription variation IDs as membership authority.
```

The source of truth for programme availability is the configured registry/mapping layer introduced by the earlier slices, not page-builder visibility rules.


---

## 5. Required Deliverables

Add or update the following plugin files.

```text
includes/
├── Query/
│   ├── QueryContext.php
│   ├── CurrentUserPerksQuery.php
│   ├── CandidatePerkQuery.php
│   ├── BricksQueryProvider.php
│   ├── EtchQueryProvider.php
│   └── RestQueryController.php
│
├── Display/
│   ├── PerkCardData.php
│   ├── PerkDisplayFormatter.php
│   ├── HubAccess.php
│   └── HubEmptyState.php
│
├── Perks/
│   └── PerkAudienceEvaluator.php
│
└── Support/
    └── RequestCache.php
```

Update:

```text
includes/Plugin.php
README.md
```

Optional if needed:

```text
assets/css/member-hub.css
```

Do not enqueue heavy frontend JavaScript in this slice.

If any class name conflicts with existing implementation, preserve the responsibility and document the equivalent class name in the final report.

---

## 6. Programme Hub Model

### 6.1 Launch hub surfaces and future configured hubs

VS-005 must document and support these launch display surfaces:

```text
Main dashboard:
- /my-voordele/

Launch programme hub:
- /my-vriendinneklub/

Launch programme hub:
- /my-voelgoed-netwerk/
```

The launch page IDs may be configured by VS-002 Options Page fields:

```text
vgmp_main_dashboard_page_id
vgmp_vriendinneklub_hub_page_id
vgmp_voelgoednetwerk_hub_page_id
vgmp_vriendinneklub_upsell_page_id
vgmp_voelgoednetwerk_upsell_page_id
```

However, these dedicated fields are launch convenience fields, not the long-term architecture boundary.

Future programme hubs should be read from configured programme records, for example:

```text
vgmp_programme_settings[].programme_hub_page_id
vgmp_programme_settings[].programme_upsell_page_id
vgmp_programme_settings[].programme_supports_hub
```

SettingsRepository / ProgrammeRegistry should normalize both launch convenience fields and future programme-row hub settings into one safe runtime view.

If the expected VS-002 settings do not exist yet, this slice must not invent new field names silently. Stop and report that VS-002 needs to be upgraded.

### 6.2 Hub access behaviour

| User membership profile | Main dashboard | Vriendinneklub hub | Voelgoednetwerk hub |
|---|---|---|---|
| Logged out | Login-required state | Login-required state | Login-required state |
| No active programme | Empty/upsell state | Upsell/no-access state | Upsell/no-access state |
| Vriendinneklub only | Show Vriendinne perks | Show Vriendinne perks | Upsell/no-access state |
| Voelgoednetwerk only | Show Netwerk perks | Upsell/no-access state | Show Netwerk perks |
| Both programmes | Show both grouped by programme | Show Vriendinne perks | Show Netwerk perks |

Important:

```text
Access to the hub page and visibility of perk cards are related but separate.
A user may access a hub page and still have zero active perks in a section.
A future programme hub follows the same pattern: active mapped programme member sees hub; non-member sees safe no-access/upsell state; logged-out user sees login-required state.
```

---

## 7. Main Query Capability

Create:

```php
Voelgoed\MemberPerks\Query\CurrentUserPerksQuery
```

Primary responsibility:

```text
Return display-safe eligible active perks for the current user and query context.
```

The query must support:

```text
programme
section
type
campaign
limit
include_claimed
order
```

For VS-005, `include_claimed` may be accepted but does not need real ledger behaviour yet. It should default to showing all eligible active perks because the claims ledger is not available yet.

### 7.1 Configured programme values

Allowed `programme` values:

```text
all
any enabled configured programme_key from ProgrammeRegistry / SettingsRepository
```

Launch examples:

```text
vriendinneklub
voelgoednetwerk
```

Default:

```text
all
```

Rules:

```text
1. Programme aliases must normalize before evaluation.
2. Unknown programme values must fail closed.
3. Disabled programmes must behave as no-access/empty state.
4. Runtime display code must not maintain a hardcoded closed list.
```

### 7.2 Query return shape

The query may return either:

```php
WP_Query
```

or a deterministic array of `WP_Post` IDs, depending on what Bricks/Etch integration requires.

Preferred internal API:

```php
public function get_perk_ids_for_current_user( QueryContext $context ): array;

public function get_wp_query_args_for_current_user( QueryContext $context ): array;

public function get_card_data_for_current_user( QueryContext $context ): array;
```

All three must rely on the same underlying eligibility logic.

---

## 8. Candidate Query Rules

Because VS-006 target indexing is not implemented yet, VS-005 may not have a fully indexed audience lookup table.

Therefore use a safe candidate-query approach:

```text
1. Query active published vg_perk posts using cheap taxonomy filters.
2. Prefer narrowing by programme taxonomy if a configured programme context is supplied and the taxonomy term exists.
3. Narrow by section/type/campaign taxonomy if supplied.
4. Do not use date meta queries for active/upcoming/expired.
5. Evaluate programme-aware audience rules in PHP against MembershipProfile.
6. Return only eligible perks.
7. Store/use canonical configured programme keys only; aliases are input-normalization helpers.
8. Deduplicate results.
8. Apply final limit after eligibility filtering.
```

This is acceptable for VS-005 because it is display-only.

Programme taxonomy caveat:

```text
`vg_perk_programme` may be used as a cheap candidate-narrowing hint only.
It is not eligibility authority.
Eligibility still comes from MembershipProfile + normalized perk audience rules.
If taxonomy terms drift from configured programme keys, the evaluator must fail closed or report a configuration warning.
```

However, do not load an unlimited number of posts.

### 8.1 Candidate limits

Default display limit:

```text
6
```

Maximum display limit:

```text
50
```

Candidate query cap:

```text
min( max( display_limit * 5, 30 ), 150 )
```

Example:

```text
display limit 6 => candidate cap 30
display limit 20 => candidate cap 100
display limit 50 => candidate cap 150
```

If there are more candidate posts than the cap and the output may be incomplete, the service may log an admin-only debug warning.

VS-006 will later improve lookup performance through indexing.

### 8.2 Candidate query must use runtime status taxonomy

Default candidate query must only include:

```text
post_type = vg_perk
post_status = publish
vg_perk_runtime_status = active
```

Normal member hub queries must not show:

```text
upcoming
expired
paused
archived
misconfigured
```

unless an admin/debug context explicitly asks for them.

---

## 9. Programme-Aware Audience Evaluation

Create:

```php
Voelgoed\MemberPerks\Perks\PerkAudienceEvaluator
```

Responsibilities:

```text
1. Read normalized audience configuration through PerkConfigRepository / SettingsRepository where available.
2. Compare perk audience rules against MembershipProfile and configured programme/level mappings.
3. Determine whether a user may see the perk.
4. Return matched canonical programme/level context for card metadata.
5. Deduplicate perks that match more than one programme.
```

### 9.1 Audience fields

The evaluator must use the hardened VS-002 audience fields via the approved configuration repository boundary.

Expected conceptual fields:

```text
perk_audience_scope
perk_programme_rules
perk_claim_scope
perk_allow_discount_stacking
perk_priority
```

The actual implementation must inspect the current VS-002 field names before coding, but raw ACF field reads must remain inside the approved config provider/repository layer.

Do not silently fall back to `vg_perk_level_group` as authority.

### 9.2 Required audience scopes

Support at least:

```text
specific_programmes
specific_programme_levels
all_active_members
display_only_public
admin_only_preview
```

For VS-005 normal member display:

```text
specific_programmes       = eligible if profile has that enabled configured programme
specific_programme_levels = eligible if profile has that enabled configured programme and mapped level rule
all_active_members        = eligible if profile has at least one active programme
display_only_public       = not shown in member-only query unless explicitly enabled
admin_only_preview        = never shown to normal members
```

### 9.3 Dual-programme deduplication

If a perk applies to both programmes and the user belongs to both, show it once.

The card may include:

```text
Applies to: Vriendinneklub + Voelgoednetwerk
```

But do not duplicate the card.

### 9.4 Programme-filtered hub behaviour

If `programme = vriendinneklub`:

```text
show only perks that match the user's Vriendinneklub profile
do not show Netwerk-only perks
show shared perks if the perk includes Vriendinneklub
```

If `programme = voelgoednetwerk`:

```text
show only perks that match the user's Voelgoednetwerk profile
do not show Vriendinne-only perks
show shared perks if the perk includes Voelgoednetwerk
```

If `programme = all`:

```text
show all perks that match any active programme in the user profile
deduplicate shared perks
```

If `programme = {future_enabled_programme_key}`:

```text
show only perks that match the user's profile for that configured programme
show safe no-access/empty state if the user does not have the programme
do not fatal if the programme has no dedicated hub page
```

Unknown or disabled programme keys must not show member perks.

---

## 10. QueryContext Class

Create:

```php
Voelgoed\MemberPerks\Query\QueryContext
```

Purpose:

```text
Normalise query inputs from Bricks, Etch, REST, or internal PHP.
```

Supported context properties:

```php
[
    "programme"       => "all", // or enabled configured programme_key
    "section"         => "",
    "type"            => "",
    "campaign"        => "",
    "limit"           => 6,
    "include_claimed" => false,
    "order"           => "default",
]
```

Sanitisation requirements:

```text
programme must be `all` or normalize to an enabled configured programme_key
section/type/campaign must be sanitised slugs
limit must be an integer between 1 and 50
include_claimed must be boolean
order must be one of allowed values
```

Allowed order values:

```text
default
newest
oldest
menu_order
priority
expiring_soon
```

For VS-005:

```text
priority and expiring_soon may be implemented best-effort.
If not implemented safely, fall back to default and document the limitation.
```

---

## 11. RequestCache Class

Create:

```php
Voelgoed\MemberPerks\Support\RequestCache
```

Purpose:

```text
Avoid repeatedly resolving the same membership profile and perk card data during one request.
```

Minimum methods:

```php
public function get( string $key );
public function set( string $key, $value ): void;
public function has( string $key ): bool;
public function forget( string $key ): void;
public function flush_prefix( string $prefix ): void;
```

This is a simple in-memory request cache only.

Do not implement Redis/object cache in this slice.

### 11.1 Cache keys

Request cache keys must include:

```text
user_id
membership_profile_hash
programme_mapping_hash
programme
section
type
campaign
limit
order
```

Do not cache one user's display data and reuse it for another user. Do not create separate cache branches for aliases; cache keys must use canonical configured programme keys.

---

## 12. Bricks Query Provider

Create:

```php
Voelgoed\MemberPerks\Query\BricksQueryProvider
```

Purpose:

```text
Expose "Voelgoed Current User Perks" as a usable Bricks query-loop source if the installed Bricks version supports the necessary hooks.
```

Important instruction for implementation agent:

```text
Inspect the actual installed Bricks version and available hooks/APIs on staging before implementing.
Do not guess blindly.
Do not fatal if Bricks is inactive.
```

Required behaviour:

```text
If Bricks is inactive:
- Class does nothing safely.

If Bricks hook/API is available:
- Register a custom query type/source called "Voelgoed Current User Perks".
- Allow selecting/filtering by:
  - programme
  - section
  - type
  - campaign
  - limit
  - order
```

Fallback:

```text
If Bricks does not expose a stable custom query provider API on this site,
provide README instructions for Bricks custom PHP query usage, but still route through CurrentUserPerksQuery.
```

Do not duplicate query logic in Bricks-specific code.

---

## 13. Etch Query Provider

Create:

```php
Voelgoed\MemberPerks\Query\EtchQueryProvider
```

Purpose:

```text
Expose the same current-user eligible active perks query to Etch if Etch supports a query/provider integration.
```

Important instruction for implementation agent:

```text
Inspect the actual installed Etch/EtchWP version and available hooks/APIs on staging before implementing.
Do not guess blindly.
Do not fatal if Etch is inactive.
```

If Etch has no stable documented query provider hook on the site:

```text
Provide README instructions for using the REST fallback or WP_Query helper instead.
```

Do not make Etch mandatory.

---

## 14. REST Query Fallback

Create:

```php
Voelgoed\MemberPerks\Query\RestQueryController
```

Purpose:

```text
Expose read-only display-safe perk card data for the current logged-in user.
```

Endpoint:

```text
GET /wp-json/vg-member-perks/v1/current-user-perks
```

Supported query parameters:

```text
programme
section
type
campaign
limit
include_claimed
order
```

Security:

```text
Logged-in users only.
Use current user context only.
Do not allow querying another user's perks.
Do not expose private admin notes.
Do not expose raw ACF fields wholesale.
Return only display-safe fields.
```

Logged-out behaviour:

```text
Return 401 with a simple authenticated-required response.
```

Do not return public member perk payloads to anonymous users in this slice.

---

## 15. Perk Card Data

Create:

```php
Voelgoed\MemberPerks\Display\PerkCardData
```

Purpose:

```text
Convert a vg_perk post into display-safe card data.
```

Return fields:

```php
[
    "id"                   => 123,
    "title"                => "Free course: Example",
    "excerpt"              => "...",
    "image_url"            => "...",
    "badge"                => "Goud only",
    "cta_label"            => "View benefit",
    "cta_url"              => "...",
    "cta_behaviour"        => "view_target",
    "section"              => "courses",
    "type"                 => "course_access",
    "programmes"           => [ "vriendinneklub" ], // canonical configured programme keys
    "matched_programmes"   => [ "vriendinneklub" ],
    "matched_level_keys"   => [ "vriendinneklub" => "goud" ],
    "start_datetime"       => "...",
    "end_datetime"         => "...",
    "runtime_status"       => "active",
    "mapping_hash"         => "...",
]
```

Important:

```text
Do not expose:
- internal admin notes
- raw PMPro level IDs
- raw target data if not needed
- private debugging data
- full user MembershipProfile
- other members' claim or eligibility state
```

---

## 16. Display Formatter

Create:

```php
Voelgoed\MemberPerks\Display\PerkDisplayFormatter
```

Purpose:

```text
Format dates, labels, badge text, CTA labels, and fallback values for display.
```

Required formatter methods:

```php
public function format_expiry_label( int $perk_id ): string;
public function get_badge_text( int $perk_id, array $match_context = [] ): string;
public function get_card_excerpt( int $perk_id ): string;
public function get_cta_label( int $perk_id ): string;
public function get_cta_url( int $perk_id ): string;
public function get_programme_label( string $programme_key ): string;
```

Programme labels must be resolved from configured programme definitions, not from a hardcoded switch statement.

For VS-005:

```text
CTA URLs may point to the selected target if safely available.
If target resolution is not implemented yet, CTA may return empty or a safe placeholder.
Do not build claim/checkout CTAs yet.
```

---

## 17. HubAccess

Create:

```php
Voelgoed\MemberPerks\Display\HubAccess
```

Purpose:

```text
Determine whether the current user may view a main or programme-specific hub surface.
```

Supported hub contexts:

```text
main
any enabled configured programme_key
```

Launch examples:

```text
vriendinneklub
voelgoednetwerk
```

Behaviour:

```text
main:
    logged-in active mapped member sees dashboard
    logged-in non-member sees empty/upsell state
    logged-out user sees login-required state

configured programme hub:
    user with active mapped level for that configured programme sees hub
    dual or multi-programme member sees the hub if their profile contains that programme
    user without that programme sees upsell/no-access state
    logged-out user sees login-required state
    disabled/unknown programme returns safe no-access/dependency state
```

Launch examples:

```text
vriendinneklub hub:
    user with active Vriendinneklub level sees hub
    dual member sees hub
    Netwerk-only member sees upsell/no-access state

voelgoednetwerk hub:
    user with active Voelgoednetwerk level sees hub
    dual member sees hub
    Vriendinne-only member sees upsell/no-access state
```

Do not rely on Bricks dynamic visibility as access authority.

---

## 18. HubEmptyState

Create:

```php
Voelgoed\MemberPerks\Display\HubEmptyState
```

Purpose:

```text
Return consistent, configurable empty/login/upsell text for the hub surfaces.
```

Required states:

```text
logged_out
no_membership
programme_not_active
no_active_perks
section_empty
dependency_missing
```

Use Options Page values from VS-002 where available.

Fallback text must be safe and generic.

Example fallback messages:

```text
logged_out:
Please log in to view your member benefits.

no_membership:
You do not currently have an active membership linked to this account.

programme_not_active:
This hub is available to members of this programme.

no_active_perks:
There are no active benefits available for your membership right now.
```

---

## 19. Recommended Hub Page Layout

### 19.1 Main dashboard: `/my-voordele/`

Sections:

```text
Welcome / membership summary
Vriendinneklub benefits
Voelgoednetwerk benefits
Future configured programme sections where enabled
New this week
Courses
Products
Events
Replays and downloads
Partner deals
Expiring soon
```

Recommended query contexts:

```text
Vriendinneklub benefits:
programme = vriendinneklub
limit = 6

Voelgoednetwerk benefits:
programme = voelgoednetwerk
limit = 6

Courses:
section = courses
programme = all
limit = 6

Events:
section = events
programme = all
limit = 6
```

### 19.2 Launch Vriendinneklub hub: `/my-vriendinneklub/`

Recommended query context:

```text
programme = vriendinneklub
```

### 19.3 Launch Voelgoednetwerk hub: `/my-voelgoed-netwerk/`

Recommended query context:

```text
programme = voelgoednetwerk
```

Do not create separate loops for Goud/Silwer/Brons or any future fixed level list unless required for design.

Eligibility must come from the plugin query.

### 19.4 Future configured programme hub

If a future programme is configured:

```text
programme = {configured_programme_key}
```

The same query/display classes must handle it. A dedicated page/template may be built in Bricks/Etch, but the plugin must not require a new query engine or hardcoded branch.

---

## 20. CSS / Styling Scope

This slice may include minimal CSS only if required for clean default output.

Recommended class naming:

```text
vgmp-hub
vgmp-hub__section
vgmp-hub__empty
vgmp-hub__programme
vgmp-perk-card
vgmp-perk-card__image
vgmp-perk-card__badge
vgmp-perk-card__title
vgmp-perk-card__excerpt
vgmp-perk-card__meta
vgmp-perk-card__programme
vgmp-perk-card__cta
```

Do not over-style in the plugin if Bricks/Etch will handle design.

Plugin CSS should be minimal, defensive, and easy to override.

---

## 21. Admin Debug Helper

Add an admin-only debug helper if useful.

Allowed:

```text
For administrators only, show current MembershipProfile and resolved query context.
Only when debug setting is enabled or WP_DEBUG is true.
```

Not allowed:

```text
Show debug data to normal members.
Expose PMPro raw level IDs on frontend.
Expose internal admin notes.
Expose another user's eligibility through REST.
```

Capability:

```text
manage_vg_perks
```

---

## 22. Performance Requirements

This slice must be performance-conscious.

Rules:

```text
1. Resolve MembershipProfile once per request.
2. Do not call PMPro checks inside each card.
3. Do not perform date meta queries for active/upcoming/expired.
4. Use vg_perk_runtime_status taxonomy for active filtering.
5. Use cheap taxonomy filters for programme/section/type/campaign where possible.
6. Do not load all perks unbounded.
7. Use candidate cap before PHP eligibility evaluation.
8. Limit query results by default.
9. Keep REST responses small.
10. Do not persistently cache user-specific output in this slice.
11. Include programme_mapping_hash in request cache keys when available.
12. Do not hardcode fixed programme/level lists in query or display logic.
```

Default display limit:

```text
6 per section
```

Maximum display limit:

```text
50
```

Candidate cap:

```text
150 maximum per query context
```

---

## 23. Security Requirements

All inputs must be sanitised.

REST endpoint must:

```text
Check logged-in status.
Use current user only.
Sanitise all query parameters.
Return display-safe data only.
Avoid leaking private perk settings.
```

Admin-only debug must:

```text
Check current_user_can( "manage_vg_perks" ).
```

Do not trust page-builder query arguments without sanitisation. Unknown programme query values must fail closed, not broaden the query.

Do not expose:

```text
raw PMPro IDs
internal notes
private ACF field payloads
claim state
order state
subscription state
other users' profile data
```

---

## 24. Compatibility Requirements

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

If the VS-003 resolver or programme mapping layer is unavailable:

```text
Return no eligible perks.
Show admin/debug warning where appropriate.
Do not fatal.
```

If PMPro is unavailable:

```text
Resolver fails closed.
No eligible member perks are shown.
```

If Bricks or Etch is unavailable:

```text
Do nothing safely.
REST fallback may still work for logged-in users if core plugin dependencies exist.
```

If ACF/config provider is unavailable:

```text
Audience evaluation cannot be trusted.
Return no member perks unless a safe legacy compatibility mode is explicitly enabled.
Show admin warning.
```

---

## 25. Testing Plan

Test on staging only.

### Test 1 — Logged-out user

```text
Visit /my-voordele/ while logged out.
Expected: no member perks are shown.
Expected: login-required empty state appears if the page uses the helper.
```

### Test 2 — No active membership

```text
Log in as a user with no active programme.
Expected: no member perks are shown.
Expected: main dashboard shows no-membership/upsell state.
```

### Test 3 — Vriendinneklub-only member

```text
Create active Vriendinneklub Goud perk.
Create active Netwerk Silwer perk.
Log in as Vriendinneklub Goud user.
Expected: main dashboard shows Vriendinneklub perk only.
Expected: Vriendinneklub hub shows Vriendinneklub perk.
Expected: Netwerk hub shows no-access/upsell state.
```

### Test 4 — Netwerk-only member

```text
Create active Vriendinneklub Goud perk.
Create active Netwerk Silwer perk.
Log in as Netwerk Silwer user.
Expected: main dashboard shows Netwerk perk only.
Expected: Netwerk hub shows Netwerk perk.
Expected: Vriendinneklub hub shows no-access/upsell state.
```

### Test 5 — Dual-programme member

```text
User has Vriendinneklub Goud and Voelgoednetwerk Silwer.
Create one perk for each programme.
Expected: main dashboard shows both perks.
Expected: Vriendinneklub hub shows Vriendinneklub perk.
Expected: Netwerk hub shows Netwerk perk.
```

### Test 6 — Shared perk deduplication

```text
Create one active perk that applies to both programmes.
Log in as dual-programme member.
Expected: main dashboard shows the shared perk once.
Expected: Vriendinneklub hub may show it once.
Expected: Netwerk hub may show it once.
```

### Test 7 — Expired/upcoming/misconfigured perks

```text
Create active, expired, upcoming, paused, archived, and misconfigured perks.
Expected: normal member hub shows only active perks.
```

### Test 8 — Section filtering

```text
Create active eligible perk in section courses.
Create active eligible perk in section products.
Query section = courses.
Expected: only course section perk appears.
```

### Test 9 — Bricks/Etch disabled

```text
Temporarily disable Bricks/Etch only if safe on staging.
Expected: plugin does not fatal.
```

### Test 10 — REST endpoint

```text
Call REST endpoint as logged-in eligible user.
Expected: display-safe JSON data returned.

Call as logged-out user.
Expected: 401 or authenticated-required response.
```

### Test 11 — Query performance

```text
Enable query monitoring if available.
Expected: no repeated PMPro calls per card.
Expected: no date meta query for runtime status.
Expected: candidate query is capped.
Expected: no unbounded loading of all perks.
```

### Test 12 — ACF unavailable guard

```text
Simulate ACF unavailable by code inspection or safe staging method.
Expected: no fatal.
Expected: member perk display fails closed or reports dependency missing.
```

### Test 13 — Future configured programme

```text
Configure a temporary enabled programme with one mapped level and hub disabled or no dedicated page.
Expected: main dashboard does not fatal.
Expected: unknown/no-hub states are safe.
Expected: no query engine code change is required.
```

### Test 14 — Unknown programme query value

```text
Call Bricks/Etch/REST query context with an unknown programme value.
Expected: no member perks are shown.
Expected: admin/debug warning may be recorded.
Expected: query does not broaden to all perks.
```

### Test 15 — Programme alias normalization

```text
Configure an alias such as `old_programme_key` for `vriendinneklub`.
Query using the alias only in an approved/admin test path.
Expected: alias normalizes to canonical `vriendinneklub`.
Expected: no duplicate cache/query branch is created.
```

### Test 16 — Future billing-term non-effect

```text
Add a future Woo billing variation that grants an already mapped PMPro access level.
Expected: MembershipProfile shape remains the same.
Expected: hub display works without VS-005 code changes.
```


---

## 26. Acceptance Criteria

VS-005 is complete only when:

```text
1. A current-user eligible active perks query exists.
2. Query uses MembershipProfile, not old flat level groups as authority.
3. Query supports main dashboard, launch programme hubs, and future configured programme contexts.
4. Query uses runtime status taxonomy, not date meta queries.
5. Logged-out users do not see member perks by default.
6. No-membership users do not see member-only perks by default.
7. Launch Vriendinne-only users see Vriendinne perks, not Netwerk-only perks.
8. Launch Netwerk-only users see Netwerk perks, not Vriendinne-only perks.
9. Dual-programme users see both sets without duplicates.
10. Bricks integration works or has a documented safe fallback.
11. Etch integration works or has a documented safe fallback.
12. REST fallback returns display-safe current-user perk data.
13. Perk card data helper exists.
14. Hub empty/access state helpers exist.
15. No discounts, claims, enrolments, or tickets are implemented.
16. No fatal errors occur if Woo, PMPro, Masteriyo, Tickera, ACF, Bricks, or Etch are inactive.
17. README explains how to build the main dashboard, launch programme hubs, and future configured programme hubs.
18. QueryContext validates programme against enabled configured programme keys, not a fixed hardcoded list.
19. Unknown/disabled programme keys fail closed.
20. Request-cache keys include programme_mapping_hash when available.
21. No PMPro level IDs, Woo membership product IDs, Woo variation IDs, or fixed level names are used as display eligibility authority.
22. Future configured programme hub behaviour is documented and does not require a new query engine.
```

---

## 27. README Update Requirements

Update README with:

```text
VS-005 implemented features
How current-user perks are resolved
How MembershipProfile affects display
How to create the main dashboard
How to create the launch Vriendinneklub hub
How to create the launch Voelgoednetwerk hub
How to add a future configured programme hub safely
How shared perks are deduplicated
How to use Bricks query loop integration if available
How to use Etch query loop integration if available
How to use REST fallback
Known limitations
Next slice: VS-006 — Target Indexing Table
```

Include example hub sections:

```text
New this week: section = new_this_week
Courses: section = courses
Products: section = products
Events: section = events
Replays: section = replays
Downloads: section = downloads
Partner deals: section = partner_deals
Expiring soon: section = expiring_soon
```

Include example query contexts:

```text
Main dashboard:
programme = all

Launch Vriendinneklub hub:
programme = vriendinneklub

Launch Voelgoednetwerk hub:
programme = voelgoednetwerk

Future configured programme hub:
programme = {enabled_configured_programme_key}
```

---

## 28. Explicit Out-of-Scope List

Do not implement these in VS-005:

```text
WooCommerce discounts
WooCommerce cart notices
WooCommerce order meta
Free product checkout
Claim buttons that create records
Redemption table
Target indexing table
Masteriyo course enrolment
Tickera ticket claim flow
Email notifications
Analytics
Reports
Full-page cache rules
Cloudflare rules
Persistent object cache
```

These belong to later slices.

---

## 29. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
MembershipProfile resolver used
Programme hub contexts implemented
Programme validation source used
Programme mapping hash/cache handling
Hooks used for Bricks
Hooks used for Etch
REST endpoint created
How to test the main dashboard
How to test the Vriendinneklub hub
How to test the Voelgoednetwerk hub
Known limitations
Any assumptions made
Any missing dependencies
Screenshots or admin notes if useful
```

If Bricks or Etch query provider integration cannot be completed because no stable hook/API is available on staging, report that clearly and provide safe fallback instructions.

Do not pretend an integration exists if it was not verified.

---

## 30. Next Slice

After VS-005 is complete and tested, proceed to:

```text
VS-006 — Target Indexing Table
```

VS-006 will create the lookup table that maps perks to Woo products, Masteriyo courses, Tickera events/tickets, pages, posts, external targets, and programme audience targets where indexing becomes necessary for performance.

Do not start VS-006 during this slice.

---

## 31. Definition of Done

This slice is done only when the plugin can safely answer and display:

```text
What active perks may the current logged-in user see on the main dashboard?
What active perks may the current logged-in user see on the launch Vriendinneklub hub?
What active perks may the current logged-in user see on the launch Voelgoednetwerk hub?
What active perks may the current logged-in user see on any future configured programme hub?
```

Without:

```text
hardcoded PMPro level checks in the page builder
hardcoded fixed programme/level lists in execution/display logic
flat level-group authority
duplicate cards for dual members
public leakage of member perks
claim/discount/ticket/course execution
```

The correct output of VS-005 is not yet a claimable benefit system.

The correct output is a safe, programme-aware, display-only member hub foundation.
