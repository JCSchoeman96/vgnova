# FEATURE PLANNING PACK: VS-007 — Claims and Redemption Ledger

Version: **1.3.0**  
Previous source: **VS-008 — Claims and Redemption Ledger**  
Patch purpose: **renumbered, ACF-boundary hardened, and programme-flexibility hardened before WooCommerce discount execution**

---

## 0. Patch Summary

This document replaces the earlier `VS-008 — Claims and Redemption Ledger` plan.

The corrected build order is now:

```text
VS-006 — Target and Audience Indexing Table
VS-007 — Claims and Redemption Ledger
VS-008 — WooCommerce Product Discounts
VS-009 — Masteriyo Course Perks
VS-010 — Tickera Ticket Perks
```

The old plan incorrectly placed the claims ledger **after** WooCommerce discounts. That is unsafe because claim limits, once-off benefits, free-product limits, and dual-programme claim scope must exist before any cart/checkout execution layer can enforce them.

Key hardening changes:

```text
1. Renumbered this slice from VS-008 to VS-007.
2. Removed hard dependency on WooCommerce discount order-item meta.
3. Made this slice the claim/limit authority for all later execution slices.
4. Renamed the redemption table from wp_vg_perk_redemptions to wp_vgmp_perk_redemptions.
5. Added programme-aware redemption fields.
6. Added claim_scope support: per_user, per_programme, per_programme_level, per_order, unlimited.
7. Added reservation support for future checkout-safe execution.
8. Added dual-programme semantics for Vriendinneklub + Voelgoednetwerk members.
9. Added same-programme conflict safety.
10. Added idempotency and duplicate-prevention rules.
11. Added event/action contracts for VS-008 WooCommerce, VS-009 Masteriyo, VS-010 Tickera, and VS-014 Notifications.
```



### v1.2.0 ACF runtime-boundary hardening summary

This version applies the ACF runtime-boundary patch.

```text
12. ClaimValidator and ClaimService must consume ClaimPolicy / PerkConfig from PerkConfigRepository.
13. Claims must fail closed when configuration cannot be loaded.
14. Added optional perk_config_hash to the redemption ledger and action payloads.
15. Clarified ACF/config-provider inactive behaviour.
16. Preserved the ledger as claim authority before Woo, Masteriyo, Tickera, and notifications.
```
---


### v1.3.0 Programme and level flexibility hardening summary

This version applies the **Programme and Level Flexibility Pass** and aligns this slice with `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

```text
17. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a closed forever-list.
18. Requires ClaimValidator and ClaimService to consume configured programme/level context from MembershipProfile and ProgrammeMappingRepository output.
19. Replaces fixed programme/level examples with configured programme_key and level_key semantics.
20. Adds programme_mapping_hash to reservation/claim context and ledger payloads.
21. Requires all claim records to store canonical configured programme_key and level_key values, not aliases.
22. Adds legacy_unknown handling for old or disabled programme/level values in historical rows.
23. Adds future-level and future-programme claim tests.
24. Keeps unmapped PMPro levels fail-closed: unmapped levels must not create claim eligibility.
```

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```
---

## 1. Objective

Build the claims and redemption tracking layer for the **Voelgoed Member Perks** plugin.

This vertical slice must introduce a reliable ledger that records when a member claims, reserves, completes, redeems, receives, cancels, refunds, or voids a perk.

This slice must support:

```text
Per-user claim limits
Per-programme claim limits
Per-programme-level claim limits
Total claim limits
Already-claimed checks
Claim reset periods
Dual-programme claim scope
Claim reservation lifecycle
Generic claim-button records
Display-safe claim status helpers
Admin visibility into claims and claim conflicts
Stable ledger APIs for WooCommerce, Masteriyo, Tickera, and notifications
```

This slice must **not** apply WooCommerce discounts, grant Masteriyo course access, issue Tickera tickets, create WooCommerce orders, or send emails.

The main purpose is to answer:

```text
Is this user still allowed to claim/use this perk?
If yes, record the proof safely and idempotently.
```

---

## 2. Current Baseline Assumptions

This slice assumes the following previous slices exist or have been implemented:

```text
VS-000 v1.1.0 — Current Site Discovery and Conflict Audit
VS-001 v1.2.0 — Plugin Foundation, CPT and Taxonomies
VS-002 v1.3.0 — ACF Fields and Options Page
VS-003 v1.3.0 — PMPro Membership Profile Resolver
VS-004 v1.2.0 — Runtime Status Automation
VS-005 v1.2.0 — Bricks/Etch Programme Hub Display
VS-006 v1.3.0 — Target and Audience Indexing Table
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0
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

Expected target/audience index table from VS-006:

```text
{$wpdb->prefix}vgmp_perk_targets
```

Expected schema option:

```text
vgmp_schema_version
```

Expected runtime status taxonomy from VS-004:

```text
vg_perk_runtime_status
```

Expected active runtime status term:

```text
active
```

Expected MembershipProfile resolver from VS-003:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
```

Expected programme mapping authority from VS-003:

```php
Voelgoed\MemberPerks\Membership\ProgrammeMappingRepository
```

Rules:

```text
1. Claim validation consumes MembershipProfile output.
2. Claim validation must not hardcode PMPro level IDs, Woo product IDs, fixed level names, or a closed programme list.
3. Programme aliases must already be normalized to canonical configured programme_key values before claim context is stored.
4. Unmapped PMPro levels must not produce claim eligibility.
```

Expected audience evaluator from VS-005:

```php
Voelgoed\MemberPerks\Query\PerkAudienceEvaluator
```

Expected ACF fields from VS-002 include:


Runtime code must not read these fields directly. They must be consumed through `PerkConfigRepository` / `ClaimPolicy`.

```text
perk_claim_mode
perk_claim_scope
perk_max_claims_per_user
perk_max_total_claims
perk_claim_reset_period
perk_keep_visible_after_claim
perk_benefit_type
perk_target_type
perk_manual_status
perk_start_datetime
perk_end_datetime
```

If any previous slice is missing or incomplete, the implementation agent must stop and report exactly what is missing before implementing VS-007.

---

## 3. Why This Slice Exists

Without a redemption ledger, the system cannot reliably answer:

```text
Has this member already claimed this perk?
Was the claim made through Vriendinneklub or Voelgoednetwerk?
If the user belongs to both programmes, did the claim count once or once per programme?
How many successful claims exist for this perk in the current period?
Was the claim only reserved, or fully completed?
Was the claim cancelled/refunded/voided later?
Did a user try to claim after the limit was reached?
Which execution surface recorded the claim: generic button, WooCommerce, Masteriyo, Tickera, or admin?
Which claims are safe to count against limits?
```

This slice creates that operational proof.

The ledger becomes the **source of truth for usage history**, while the canonical editable configuration remains the source of perk configuration. For MVP that editable configuration is provided through ACF and normalized by repository classes.

---

## 4. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Redemption database table
Claim period resolver
Claim scope resolver
Claim validator
Claim service
Reservation service
Redemption repository
Generic claim REST endpoint
Claim status helper
Claim-aware display state
Claim-aware hub filtering
Admin redemption list page
Admin claim columns
Admin claim debug tools
Event/action hooks for later slices
```

This slice must **not** implement:

```text
WooCommerce cart pricing
WooCommerce cart item price changes
WooCommerce order item meta created by cart discounts
Masteriyo enrolment
Masteriyo course access grants
Tickera ticket issuing
Ticket claim-to-cart flow
R0 ticket order automation
Email notifications
Advanced analytics dashboard
CSV export
Full recurring subscription renewal benefit policy
Automatic free-product issuing
```

Important:

```text
VS-007 creates the ledger and claim authority.
VS-008 uses this ledger for WooCommerce discount safety.
VS-009 uses this ledger for Masteriyo course claims.
VS-010 uses this ledger for Tickera ticket claims.
VS-014 listens to ledger events for optional notifications.
```

---

## 5. Core Architecture Rule

Execution surfaces must not invent their own claim rules.

Correct model:

```text
MembershipProfile resolver
↓
Perk audience evaluator
↓
ClaimValidator
↓
ClaimService / ReservationService
↓
vgmp_perk_redemptions ledger
↓
later execution slice completes fulfilment
```

Incorrect model:

```text
WooCommerce checks its own claim rules
Masteriyo checks its own claim rules
Tickera checks its own claim rules
Bricks hides already-claimed cards only with visibility rules
Each system writes unrelated usage records
```

There must be **one claim authority**.

---

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

## 5A. Configuration and ClaimPolicy Boundary

Claim execution must not read raw ACF fields directly.

Correct model:

```text
ConfigProviderInterface / AcfConfigProvider
↓
PerkConfigRepository
↓
ClaimPolicy
↓
ClaimValidator
↓
ClaimService / ReservationService
↓
vgmp_perk_redemptions
```

`ClaimPolicy` must provide normalized values such as:

```text
claim_mode
claim_scope
max_claims_per_user
max_total_claims
claim_reset_period
keep_visible_after_claim
target_required
allowed_target_types
config_hash
```

Fail-closed rule:

```text
If PerkConfigRepository cannot load a valid ClaimPolicy:
- deny the claim
- use code = dependency_missing or invalid_perk_config
- do not create redemption rows
- log/admin-warn where appropriate
```

Only provider/repository classes may know the ACF field names.


## 5B. Programme Mapping Boundary

Claim execution must consume configured programme/level state, not a hardcoded launch-only enum.

Correct model:

```text
SettingsRepository
↓
ProgrammeMappingRepository
↓
MembershipProfileResolver
↓
MembershipProfile + mapping_hash
↓
PerkAudienceEvaluator
↓
ClaimValidator / ClaimScopeResolver
↓
ClaimContext
↓
vgmp_perk_redemptions
```

Rules:

```text
1. Vriendinneklub and Voelgoednetwerk are launch/default programmes.
2. Future programmes and levels may be added through configuration and QA.
3. ClaimValidator must accept any enabled configured programme_key where the user is eligible.
4. ClaimValidator must reject unknown, disabled, or unmapped programme/level references.
5. Claim rows must store canonical configured keys, not aliases.
6. Claims must include programme_mapping_hash when available so historical rows remain interpretable after mappings change.
7. Woo product IDs, Woo variation IDs, PMPro level IDs, and billing terms are not claim authority.
```

## 6. Dual-Programme Membership Rule

A user can have:

```text
No active programme
Vriendinneklub only
Voelgoednetwerk only
Both Vriendinneklub and Voelgoednetwerk
```

Example:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

This is valid and must be supported.

The claims system must therefore store which programme and level qualified the user for a claim.

Required stored context:

```text
programme_key           = configured programme_key | none | mixed | legacy_unknown
level_key               = configured level_key for the resolved programme | empty | legacy_unknown
qualified_path          = programme-level path that caused approval
eligibility_hash        = deterministic profile/audience hash at claim time
programme_mapping_hash  = deterministic programme/level mapping hash at claim time
perk_config_hash        = normalized perk/claim configuration hash at claim time
claim_scope             = how limits are counted
```

Launch examples:

```text
vriendinneklub:goud
vriendinneklub:goud
voelgoednetwerk:silwer
voelgoednetwerk:platinum
```

These examples are illustrative only. The ledger must not assume a fixed list of level names such as Goud/Silwer/Brons or Brons/Silwer/Goud.

Rules:

```text
1. Claims must store canonical configured programme_key and level_key values.
2. Programme aliases are input-normalization helpers only; they must not be stored as separate live programmes.
3. If a historical row contains an old/disabled/unknown programme or level, admin/reporting slices should display it as Unknown / Legacy instead of silently remapping it.
4. If current mapping cannot resolve a claim request, the claim must fail closed before any redemption row is created.
```

---

## 7. Claim Scope Rules

Expected ACF field from VS-002:

```text
perk_claim_scope
```

Allowed values:

```text
per_user
per_programme
per_programme_level
per_order
unlimited
```

### 7.1 `per_user`

The user may claim the perk once per configured period, regardless of how many programmes qualify them.

Example:

```text
A dual member qualifies through Vriendinneklub Goud and Netwerk Silwer.
Claim scope = per_user.
Result: one claim total.
```

Default for most claimable perks:

```text
per_user
```

### 7.2 `per_programme`

The user may claim once per programme per configured period.

Example:

```text
User has Vriendinneklub Goud and Netwerk Silwer.
Claim scope = per_programme.
Result: user may claim once through Vriendinneklub and once through Netwerk.
```

Use carefully. This must be explicitly configured, not assumed.

### 7.3 `per_programme_level`

The user may claim once per exact programme-level combination per configured period.

Example:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

This is stricter and more specific than `per_programme`.

### 7.4 `per_order`

Used by WooCommerce when the same benefit is allowed once per order or once per order item, depending on VS-008 policy.

This slice defines the scope, but Woo execution is implemented in VS-008.

### 7.5 `unlimited`

No per-user claim limit is enforced by the claim ledger.

Use this only for perks where repeated usage is intentionally allowed.

---

## 8. Same-Programme Conflict Rule

A user should normally have only one active level per programme.

Invalid/conflicting example:

```text
vriendinneklub:goud
vriendinneklub:silwer
```

If the MembershipProfile resolver reports a same-programme conflict:

```text
1. Claim validation must not stack benefits.
2. The highest configured rank may be used for frontend eligibility if VS-003 resolved it that way.
3. The conflict must be recorded in claim validation/debug context where practical.
4. Admin reporting should surface the conflict for repair.
```

Do not treat same-programme conflicts as a reason to give the user extra claims.

---

## 9. Required Deliverables

Add the following plugin files:

```text
includes/
├── Database/
│   └── RedemptionTable.php
│
├── Claims/
│   ├── ClaimContext.php
│   ├── ClaimResult.php
│   ├── ClaimService.php
│   ├── ClaimValidator.php
│   ├── ClaimPeriodResolver.php
│   ├── ClaimScopeResolver.php
│   ├── ClaimStatus.php
│   ├── RedemptionRepository.php
│   ├── ReservationService.php
│   └── RestClaimController.php
│
├── Query/
│   └── ClaimAwarePerkFilter.php
│
├── Display/
│   └── PerkClaimDisplayData.php
│
└── Admin/
    ├── RedemptionAdminPage.php
    ├── PerkClaimColumns.php
    └── RedemptionDebugTool.php
```

Update existing files:

```text
includes/Plugin.php
includes/Activator.php
includes/Database/Schema.php
includes/Display/PerkCardData.php
includes/Query/RestQueryController.php
README.md
```

Do **not** add WooCommerce execution files in this slice. Woo files belong to VS-008.

If implementation names differ, preserve responsibilities and document the deviation.

---

## 10. Database Table

Create a custom table:

```text
{$wpdb->prefix}vgmp_perk_redemptions
```

Example:

```text
wp_vgmp_perk_redemptions
```

Do not use the old table name:

```text
wp_vg_perk_redemptions
```

If a legacy table already exists, do not delete it automatically. Report it and add a safe migration plan.

---

## 11. Required Table Schema

Use `dbDelta()` through the existing schema manager.

Recommended SQL shape:

```sql
CREATE TABLE {$wpdb->prefix}vgmp_perk_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    perk_id BIGINT UNSIGNED NOT NULL,
    target_type VARCHAR(60) NOT NULL DEFAULT '',
    target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    target_value VARCHAR(255) NOT NULL DEFAULT '',
    order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    order_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    programme_key VARCHAR(80) NOT NULL DEFAULT '',
    level_key VARCHAR(80) NOT NULL DEFAULT '',
    qualified_path VARCHAR(160) NOT NULL DEFAULT '',
    claim_scope VARCHAR(60) NOT NULL DEFAULT 'per_user',
    eligibility_hash VARCHAR(64) NOT NULL DEFAULT '',
    programme_mapping_hash VARCHAR(64) NOT NULL DEFAULT '',
    perk_config_hash VARCHAR(64) NOT NULL DEFAULT '',
    benefit_type VARCHAR(80) NOT NULL DEFAULT '',
    discount_amount DECIMAL(18,6) NOT NULL DEFAULT 0,
    final_member_price DECIMAL(18,6) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'claimed',
    claim_period_key VARCHAR(120) NOT NULL DEFAULT 'lifetime',
    source VARCHAR(80) NOT NULL DEFAULT 'manual',
    idempotency_key VARCHAR(191) NOT NULL DEFAULT '',
    reserved_until DATETIME NULL,
    claimed_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    failure_code VARCHAR(120) NOT NULL DEFAULT '',
    notes TEXT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY unique_idempotency (idempotency_key),
    KEY user_perk (user_id, perk_id),
    KEY user_perk_scope_period (user_id, perk_id, claim_scope, claim_period_key),
    KEY user_perk_programme_period (user_id, perk_id, programme_key, claim_period_key),
    KEY user_perk_level_period (user_id, perk_id, programme_key, level_key, claim_period_key),
    KEY perk_status (perk_id, status),
    KEY perk_period_status (perk_id, claim_period_key, status),
    KEY order_lookup (order_id),
    KEY order_item_lookup (order_item_id),
    KEY target_lookup (target_type, target_id),
    KEY programme_lookup (programme_key, level_key),
    KEY programme_mapping_hash_lookup (programme_mapping_hash),
    KEY perk_config_hash_lookup (perk_config_hash),
    KEY claimed_at (claimed_at),
    KEY reserved_until (reserved_until)
) DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate};
```

### Schema hardening rules

```text
1. Use NOT NULL DEFAULT 0 or '' for identity columns used in indexes.
2. Do not rely on nullable columns inside unique identity logic.
3. Use explicit status values.
4. Store programme context at claim time.
5. Store eligibility_hash at claim time for support/debugging.
6. Store programme_mapping_hash at claim/reservation time so support can see which configured programme/level mapping was in force.
7. Store perk_config_hash at claim/reservation time so support can see which normalized configuration allowed the claim.
8. Do not store sensitive personal details in the ledger.
9. User, order, and perk IDs are enough for operational joins.
```

---

## 12. Schema Versioning

Update the schema manager.

If VS-006 used schema version:

```text
1
```

Increment to:

```text
2
```

Option key:

```text
vgmp_schema_version
```

Rules:

```text
1. Create vgmp_perk_redemptions if missing.
2. Do not drop vgmp_perk_targets.
3. Do not delete existing target or redemption data.
4. Do not drop tables on deactivation.
5. Safe upgrades only.
6. Report legacy wp_vg_perk_redemptions if found.
```

---

## 13. Redemption Statuses

Allowed statuses:

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

Meaning:

```text
reserved  = claim capacity temporarily held, not fulfilled yet
claimed   = claim was accepted and recorded
completed = claim was fulfilled successfully
cancelled = claim or related order was cancelled
refunded  = related payment/order was refunded
failed    = execution failed after validation or reservation
expired   = reserved claim expired before completion
voided    = admin/system invalidated the redemption
```

Successful-count statuses:

```text
claimed
completed
```

Temporary blocking status:

```text
reserved
```

Non-counting statuses:

```text
cancelled
refunded
failed
expired
voided
```

Important:

```text
Reserved records may temporarily block capacity so race conditions do not oversubscribe limited perks.
Expired reservations must be cleaned/released by scheduled task or repository method.
```

---

## 14. Claim Modes

Expected ACF field:

```text
perk_claim_mode
```

Supported choices:

```text
auto_apply
claim_button
manual_admin
display_only
```

### 14.1 `auto_apply`

Used by later execution layers.

Examples:

```text
VS-008 WooCommerce discount applied during cart/checkout
VS-009 course access granted after claim
VS-010 ticket benefit added to cart or order
```

In VS-007, this mode is only validated and recorded when a caller uses the ClaimService API. No Woo execution happens yet.

### 14.2 `claim_button`

The REST endpoint may create a generic claim record.

This is useful for:

```text
manual perks
download/replay unlock markers
partner deal claim markers
future course/ticket claim preparation
```

No course enrolment or ticket generation happens in this slice.

### 14.3 `manual_admin`

No frontend claim is allowed.

Admin manual creation may be deferred. If implemented, it must use capability checks, nonce verification, and ClaimValidator.

### 14.4 `display_only`

No claim record is required by default.

Display-only perks can still show in hubs, but they are not counted as claimed unless future configuration says otherwise.

---

## 15. Claim Periods

Expected ACF field:

```text
perk_claim_reset_period
```

Supported values:

```text
never
daily
weekly
monthly
yearly
```

Create:

```php
Voelgoed\MemberPerks\Claims\ClaimPeriodResolver
```

Purpose:

```text
Convert a perk and current time into a stable claim_period_key.
```

Examples:

```text
never   → lifetime
daily   → 2026-06-09
weekly  → 2026-W24
monthly → 2026-06
yearly  → 2026
```

Use the site timezone, defaulting to:

```text
Africa/Johannesburg
```

Do not use server UTC blindly for human campaign windows.

---

## 16. Claim Limits

Expected ACF fields:

```text
perk_max_claims_per_user
perk_max_total_claims
perk_claim_reset_period
perk_claim_scope
```

Rules:

```text
1. Empty max_claims_per_user means use the configured default from VS-002.
2. Recommended default for claim_button/free perks is 1.
3. Empty max_total_claims means unlimited.
4. Limits are evaluated per claim_period_key.
5. Successful-count statuses are claimed and completed.
6. Reserved records may temporarily count to prevent race abuse.
7. Cancelled, refunded, failed, expired, and voided records do not count as successful claims.
8. Claim scope determines which successful rows are counted for the current user.
```

---

## 17. ClaimContext Class

Create:

```php
Voelgoed\MemberPerks\Claims\ClaimContext
```

Purpose:

```text
Hold the server-derived context for a claim request.
```

Suggested properties:

```php
user_id
perk_id
target_type
target_id
target_value
claim_mode
claim_scope
claim_period_key
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
perk_config_hash
source
order_id
order_item_id
benefit_type
discount_amount
final_member_price
idempotency_key
```

Rules:

```text
1. Do not trust user-submitted programme_key, level_key, programme_mapping_hash, eligibility_hash, discount_amount, order_id, or status.
2. These must be derived server-side.
3. REST requests may submit perk_id and target fields only.
4. Woo/Masteriyo/Tickera callers in later slices may pass execution-specific IDs, but the service must still validate them.
```

---

## 18. ClaimResult Class

Create:

```php
Voelgoed\MemberPerks\Claims\ClaimResult
```

Purpose:

```text
Return structured outcomes from validation, reservation, and claim creation.
```

Shape:

```php
[
    "allowed"       => true,
    "success"       => true,
    "code"          => "claimed",
    "message"       => "Perk claimed.",
    "redemption_id" => 123,
    "status"        => "claimed",
]
```

Denial shape:

```php
[
    "allowed" => false,
    "success" => false,
    "code"    => "user_limit_reached",
    "message" => "You have already claimed this perk.",
]
```

Do not expose raw database errors to normal users.

---

## 19. ClaimScopeResolver Class

Create:

```php
Voelgoed\MemberPerks\Claims\ClaimScopeResolver
```

Responsibilities:

```text
1. Read perk_claim_scope.
2. Validate allowed claim scope.
3. Default safely to per_user.
4. Determine the claim counting key for the current ClaimContext.
5. Handle dual-programme users deterministically.
```

If multiple eligible paths exist for a dual-programme member and the claim scope is `per_user`:

```text
Choose the best/primary path deterministically for metadata.
Do not grant multiple claims.
```

If claim scope is `per_programme`:

```text
The caller must provide or the service must select a specific programme path.
If ambiguous, deny with ambiguous_programme unless a deterministic business rule exists.
```

Recommended MVP for ambiguity:

```text
For frontend generic claims, if both programmes qualify and claim_scope = per_programme, require the request to specify a programme_key.
Validate that the user actually qualifies through that programme.
```

Programme flexibility rules:

```text
1. Accepted programme_key values must come from enabled configured programmes, after alias normalization.
2. Accepted level_key values must come from the resolved MembershipProfile / ProgrammeLevelDefinition context.
3. The resolver may support future programmes and future levels without claim-service code changes.
4. Unknown or disabled programme/level values deny with invalid_mapping or not_eligible.
5. Stored claim rows keep the canonical values used at the time of the claim.
```


---

## 20. ClaimValidator Class

Create:

```php
Voelgoed\MemberPerks\Claims\ClaimValidator
```

Responsibilities:

```text
1. Confirm user is logged in.
2. Confirm perk exists and is published.
3. Confirm perk runtime status is active.
4. Confirm MembershipProfile exists and user is eligible for the perk through configured programme/level mapping.
5. Confirm MembershipProfile mapping_hash / programme_mapping_hash is available where the resolver provides it.
6. Confirm same-programme conflicts do not result in stacking.
6. Confirm claim mode allows the requested claim.
7. Resolve claim scope.
8. Resolve claim period.
9. Confirm per-user/per-scope claim limit is not exceeded.
10. Confirm total claim limit is not exceeded.
11. Confirm target is valid if target is required.
12. Return structured allow/deny result.
```

Do not call PMPro directly. Use VS-003 MembershipProfile resolver and VS-005 audience evaluator.

Recommended denial codes:

```text
not_logged_in
perk_not_found
perk_not_published
perk_not_active
not_eligible
same_programme_conflict
ambiguous_programme
claim_mode_not_allowed
user_limit_reached
total_limit_reached
invalid_target
invalid_mapping
dependency_missing
invalid_claim_scope
invalid_claim_period
reservation_unavailable
```

---

## 21. ClaimService Class

Create:

```php
Voelgoed\MemberPerks\Claims\ClaimService
```

Responsibilities:

```text
1. Validate claim request.
2. Build ClaimContext.
3. Generate idempotency key.
4. Create redemption row.
5. Prevent duplicate claims.
6. Return structured ClaimResult.
7. Fire safe internal actions after claim/reservation/completion.
```

Minimum methods:

```php
public function claim_perk_for_user(
    int $perk_id,
    int $user_id,
    array $args = []
): ClaimResult;

public function reserve_perk_for_user(
    int $perk_id,
    int $user_id,
    array $args = []
): ClaimResult;

public function complete_redemption(
    int $redemption_id,
    array $args = []
): ClaimResult;

public function cancel_redemption(
    int $redemption_id,
    string $reason = ''
): ClaimResult;

public function record_external_redemption(
    int $perk_id,
    int $user_id,
    string $source,
    array $args = []
): ClaimResult;
```

Do not grant course access or ticket access in this class yet.

This class writes the ledger. It does not perform every downstream benefit execution.

---

## 22. ReservationService Class

Create:

```php
Voelgoed\MemberPerks\Claims\ReservationService
```

Purpose:

```text
Create and expire temporary claim reservations for future execution slices.
```

This is especially important for:

```text
limited free products
limited free tickets
limited course seats
one-per-member checkout benefits
```

Required behaviour:

```text
1. Reserve capacity only after ClaimValidator allows it.
2. Set status = reserved.
3. Set reserved_until.
4. Reserved rows temporarily count against limits.
5. Expire stale reservations.
6. Complete reservation after execution succeeds.
7. Cancel reservation if execution fails.
```

Default reservation duration:

```text
15 minutes
```

This can be adjusted later for checkout/ticket flows.

VS-008 Woo discounts will decide exactly when to reserve during checkout. VS-007 only provides the service.

---

## 23. Idempotency

Idempotency is required.

Examples:

### Generic claim button

```text
claim:user:{user_id}:perk:{perk_id}:scope:{claim_scope}:period:{claim_period_key}:target:{target_type}:{target_id}:{target_value}:programme:{programme_key}:level:{level_key}
```

### Reservation

```text
reservation:user:{user_id}:perk:{perk_id}:scope:{claim_scope}:period:{claim_period_key}:target:{target_type}:{target_id}:context:{context_hash}
```

### Future Woo order item redemption

Defined for later VS-008:

```text
woo_order_item:{order_item_id}:perk:{perk_id}
```

### Future Masteriyo course claim

Defined for later VS-009:

```text
masteriyo:user:{user_id}:perk:{perk_id}:course:{course_id}:period:{claim_period_key}
```

### Future Tickera ticket claim

Defined for later VS-010:

```text
tickera:user:{user_id}:perk:{perk_id}:ticket:{ticket_id}:period:{claim_period_key}:order:{order_id}
```

If the same idempotency key already exists:

```text
Return existing redemption instead of creating another row.
```

---

## 24. RedemptionRepository Class

Create:

```php
Voelgoed\MemberPerks\Claims\RedemptionRepository
```

Responsibilities:

```text
1. Insert redemption records.
2. Fetch redemptions by user/perk.
3. Count successful claims by claim scope and period.
4. Count total successful claims.
5. Count active reservations.
6. Find by idempotency key.
7. Update status.
8. Expire stale reservations.
9. Fetch redemptions for admin list.
```

Minimum methods:

```php
public function insert( array $data ): int;

public function find_by_idempotency_key( string $key ): ?array;

public function count_user_claims(
    int $user_id,
    int $perk_id,
    string $claim_scope,
    string $claim_period_key,
    array $scope_context = []
): int;

public function count_total_claims(
    int $perk_id,
    string $claim_period_key
): int;

public function count_active_reservations(
    int $perk_id,
    string $claim_period_key,
    array $scope_context = []
): int;

public function get_user_redemptions_for_perk(
    int $user_id,
    int $perk_id
): array;

public function update_status(
    int $redemption_id,
    string $status,
    array $extra = []
): bool;

public function expire_stale_reservations(): int;
```

Use prepared SQL. Do not expose raw DB errors to normal users.

---

## 25. Successful Claim Counting Rules

Claim limits must count these statuses:

```text
claimed
completed
```

Claim limits may temporarily count:

```text
reserved
```

Claim limits must not count:

```text
cancelled
refunded
failed
expired
voided
```

For `per_user`:

```text
Count rows for user_id + perk_id + claim_period_key.
```

For `per_programme`:

```text
Count rows for user_id + perk_id + programme_key + claim_period_key.
```

For `per_programme_level`:

```text
Count rows for user_id + perk_id + programme_key + level_key + claim_period_key.
```

For `per_order`:

```text
Count rules are defined by the execution caller.
VS-008 WooCommerce must specify whether per_order means order-level or order-item-level.
```

For `unlimited`:

```text
Do not block based on per-user successful claim count.
Still respect max_total_claims if configured, unless explicitly bypassed.
```

---

## 26. Generic Claim REST Endpoint

Create:

```php
Voelgoed\MemberPerks\Claims\RestClaimController
```

Endpoint:

```text
POST /wp-json/vg-member-perks/v1/claim
```

Request body:

```json
{
  "perk_id": 123,
  "target_type": "wp_page",
  "target_id": 456,
  "programme_key": "vriendinneklub"
}
```

`programme_key` is optional and only used where claim scope or dual eligibility makes the qualified path ambiguous.

Security:

```text
Logged-in users only.
Use nonce.
Use current WordPress user only.
Do not allow claiming for another user.
Sanitize all values.
Validate through ClaimValidator.
```

Users must not be able to submit:

```text
user_id
level_key
qualified_path
eligibility_hash
programme_mapping_hash
discount_amount
order_id
order_item_id
status
source
```

These are server-derived only.

Response success:

```json
{
  "success": true,
  "code": "claimed",
  "message": "Perk claimed.",
  "redemption_id": 789,
  "status": "claimed"
}
```

Response denial:

```json
{
  "success": false,
  "code": "user_limit_reached",
  "message": "You have already claimed this perk."
}
```

This endpoint records generic claims only.

It must not:

```text
enrol courses
issue tickets
create WooCommerce orders
send email
```

---

## 27. Display Claim State

Update:

```text
includes/Display/PerkCardData.php
```

Add display-safe claim fields:

```php
[
    "claim_mode"               => "claim_button",
    "claim_scope"              => "per_user",
    "claim_status"             => "available",
    "claim_status_label"       => "Available",
    "already_claimed"          => false,
    "claims_remaining"         => 1,
    "qualified_programmes"     => ["vriendinneklub"],
    "keep_visible_after_claim" => true,
]
```

Recommended display statuses:

```text
available
already_claimed
limit_reached
not_eligible
not_claimable
ambiguous_programme
reservation_pending
```

Do not expose:

```text
internal DB notes
raw eligibility hash unless admin/debug mode
all historical redemptions
other members' claim state
```

---

## 28. Query Behaviour for Claimed Perks

Update the query/display layer to respect:

```text
perk_keep_visible_after_claim
```

Default:

```text
true
```

Behaviour:

```text
If already claimed and keep_visible_after_claim = true:
- Show card with “Already claimed” state.

If already claimed and keep_visible_after_claim = false:
- Exclude from normal active perks query or hide via claim-aware filter.
```

Create:

```php
Voelgoed\MemberPerks\Query\ClaimAwarePerkFilter
```

Performance rule:

```text
Do not query redemption state individually for every card if avoidable.
Prefetch current user's redemptions for visible perk IDs.
```

For MVP, simple implementation is acceptable if query result limits remain small, but document the hardening path.

---

## 29. Admin Redemption Page

Create:

```php
Voelgoed\MemberPerks\Admin\RedemptionAdminPage
```

Location:

```text
Member Perks > Redemptions
```

Capability:

```text
manage_vg_perks
```

Minimum columns:

```text
Date
User
Perk
Target
Status
Programme
Level
Claim Scope
Claim Period
Order
Discount Amount
Source
```

Filters:

```text
Perk
Status
Programme
Level
Claim scope
Source
Date range
```

For VS-007, a simple paginated admin table is enough.

Do not build a full analytics dashboard yet.

---

## 30. Perk Admin Columns

Create:

```php
Voelgoed\MemberPerks\Admin\PerkClaimColumns
```

Add columns to `vg_perk` list:

```text
Claims
Claim Mode
Claim Scope
Claim Limit
```

Example display:

```text
Claims: 12
Claim Mode: claim_button
Claim Scope: per_user
Claim Limit: 1 per user / monthly
```

Use efficient counts where possible.

Do not parse entire redemption history per row if avoidable.

---

## 31. Scheduled Reservation Cleanup

Add a scheduled task if the plugin cron infrastructure exists from VS-004.

Cron action:

```text
vgmp_expire_stale_redemption_reservations
```

Recommended interval:

```text
15 minutes
```

Behaviour:

```text
Find reserved rows where reserved_until < current time.
Set status = expired.
Set failure_code = reservation_expired.
Do not delete rows.
```

Do not create duplicate scheduled events.

Unschedule on plugin deactivation.

---

## 32. Internal Actions for Later Slices

Fire internal actions after important events.

Recommended actions:

```php
do_action( "vgmp_redemption_reserved", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_claimed", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_completed", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_cancelled", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_failed", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_refunded", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_voided", $redemption_id, $redemption_data );
```

These actions are for:


Action payload requirement:

```text
All redemption actions must include enough context for later slices:
- redemption_id
- redemption_data
- programme_mapping_hash
- perk_config_hash
- claim_scope
- programme_key
- level_key
- qualified_path
- eligibility_hash
- source
```


```text
VS-008 WooCommerce Product Discounts
VS-009 Masteriyo Course Perks
VS-010 Tickera Ticket Perks
VS-011 Reports and Dashboards
VS-014 Notifications and Member Communications
```

Do not make VS-007 depend on those later slices.

---

## 33. Logging

Log important events:

```text
Redemption table created
Generic claim created
Reservation created
Reservation completed
Reservation expired
Duplicate idempotency ignored
Claim denied by validator
Invalid claim request
Database insert failure
Same-programme membership conflict encountered
Ambiguous dual-programme claim path
```

Do not log sensitive personal details beyond IDs and operational context.

---

## 34. Data Integrity Rules

Required:

```text
1. Duplicate generic claims must not be created.
2. Double-click claim buttons must not create duplicates.
3. Claim limits must use successful statuses only, with reserved as temporary capacity where configured.
4. Redemptions must not be deleted when perks expire.
5. Redemptions must not be deleted when related orders are cancelled/refunded later.
6. Expired perks cannot be newly claimed.
7. Misconfigured perks cannot be newly claimed.
8. Ineligible users cannot claim.
9. Logged-out users cannot claim.
10. Admin views must require manage_vg_perks.
11. All claim writes must be server-side validated.
12. Dual-programme users must not receive duplicate claims unless claim_scope explicitly allows it.
13. Same-programme conflicts must not stack benefits or claims.
14. Unmapped PMPro levels must not grant claim eligibility.
15. Unknown or disabled programme/level references must fail closed before writing new redemption rows.
```

---

## 35. Performance Requirements

Rules:

```text
1. Use indexed SQL queries for claim counts.
2. Avoid scanning all redemptions in PHP.
3. Use claim_period_key for period-based limits.
4. Use claim_scope + canonical programme_key + canonical level_key for dual-programme limits.
4A. Store and expose programme_mapping_hash for cache/report/debug safety.
5. Use idempotency keys for duplicate prevention.
6. Prefetch claim state for visible perk IDs if practical.
7. Keep REST responses small.
8. Do not query PMPro per card.
9. Do not recalculate eligibility repeatedly if MembershipProfile resolver already caches per request.
```

---

## 36. Security Requirements

All claim endpoints must:

```text
Require logged-in user
Use nonce
Use current user ID from WordPress, not request body
Sanitize perk_id and target fields
Validate capability for admin actions
Use prepared SQL
Return safe messages
Avoid leaking admin notes or raw DB data
```

Users must not be able to submit:

```text
user_id
level_key
qualified_path
eligibility_hash
programme_mapping_hash
discount_amount
order_id
order_item_id
status
source
```

Those values are server-derived only.

---

## 37. Compatibility Requirements

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

If WooCommerce is inactive:

```text
No Woo-related execution should exist in this slice anyway.
Generic claim service still works for non-Woo claimable perks if PMPro/ACF dependencies are available.
```

If PMPro/resolver is unavailable:

```text
Claim validation denies claims safely with dependency_missing or not_eligible.
```

If ACF is inactive:

```text
Claim configuration may not load.
Claims should fail safely where config is required.
```

---

## 38. Testing Plan

Test on staging only.



### Test 0 — Claim config provider fail-closed behaviour

```text
Simulate PerkConfigRepository returning invalid/null ClaimPolicy.
Expected: claim endpoint denies with dependency_missing or invalid_perk_config.
Expected: no redemption row is created.
Expected: no fatal error occurs.
Expected: admin/debug log records configuration unavailable.
```


### Test 0A — Programme mapping fail-closed behaviour

```text
Simulate ProgrammeMappingRepository unavailable or returning invalid mapping.
Expected: claim endpoint denies with dependency_missing or invalid_mapping.
Expected: no redemption row is created.
Expected: no fatal error occurs.
Expected: admin/debug log records programme mapping unavailable.
```

### Test 0B — Unmapped PMPro level does not claim

```text
Create or use a user with an active PMPro level that is not mapped to any enabled ProgrammeLevelDefinition.
Create an otherwise active claimable perk.
Expected: user is not eligible through that unmapped level.
Expected: claim is denied as not_eligible or invalid_mapping.
Expected: no redemption row is created.
```
### Test 1 — Table creation

```text
Activate or run schema upgrade.
Expected: wp_vgmp_perk_redemptions exists.
Expected: vgmp_schema_version updated to 2 or current next version.
```

### Test 2 — Generic claim success

```text
Create active perk with claim_mode = claim_button.
Make user eligible through one programme.
Call REST claim endpoint.
Expected: redemption row created.
Expected: programme_key and level_key are stored.
```

### Test 3 — Logged-out claim blocked

```text
Call claim endpoint logged out.
Expected: blocked.
Expected: no redemption row.
```

### Test 4 — Ineligible claim blocked

```text
User without required programme/level tries to claim.
Expected: not_eligible.
Expected: no redemption row.
```

### Test 5 — Expired/misconfigured perk blocked

```text
Set runtime status = expired or misconfigured.
Try to claim.
Expected: perk_not_active or equivalent denial.
```

### Test 6 — Per-user claim limit

```text
Set claim_scope = per_user.
Set max_claims_per_user = 1.
Claim once.
Claim again.
Expected: second claim denied as user_limit_reached.
```

### Test 7 — Dual-programme per-user claim

```text
User has Vriendinneklub Goud and Voelgoednetwerk Silwer.
Perk applies to both programmes.
claim_scope = per_user.
Claim once.
Attempt second claim through other programme.
Expected: second claim denied.
```

### Test 8 — Dual-programme per-programme claim

```text
User has Vriendinneklub Goud and Voelgoednetwerk Silwer.
Perk applies to both programmes.
claim_scope = per_programme.
Claim through Vriendinneklub.
Claim through Netwerk.
Expected: both can be recorded once if explicitly selected/validated.
Third claim through either same programme blocked.
```

### Test 9 — Monthly reset

```text
Set reset period = monthly.
Claim in current period.
Expected: second claim same month blocked.
Manually test next period key if possible.
Expected: new period allows claim.
```

### Test 10 — Total claim limit

```text
Set max_total_claims = 2.
Two different users claim.
Third user attempts.
Expected: total_limit_reached.
```

### Test 11 — Idempotency

```text
Submit the same claim twice quickly.
Expected: no duplicate successful rows.
Expected: existing redemption returned or duplicate safely ignored.
```

### Test 12 — Reservation lifecycle

```text
Create reservation for eligible user/perk.
Expected: status = reserved.
Complete reservation.
Expected: status = completed.
Create another reservation and let it expire.
Run cleanup.
Expected: status = expired.
```

### Test 13 — Claim display state

```text
Claim a perk.
View hub card.
Expected: card shows already claimed or is hidden based on perk_keep_visible_after_claim.
```

### Test 14 — Admin redemption page

```text
Visit Member Perks > Redemptions.
Expected: claims listed with user, perk, status, source, programme, level, and claim scope.
```

### Test 15 — Dependency safety

```text
If safe, test with WooCommerce inactive.
Expected: no fatal.
Generic claim logic still loads.
```


### Test 16 — Future mapped level without code change

```text
Add a temporary enabled programme-level mapping, for example vriendinneklub:platinum or a test programme level.
Map it to a PMPro test level.
Create a claimable perk targeted to that configured level.
Expected: eligible user can claim according to the claim policy.
Expected: programme_key, level_key, eligibility_hash, programme_mapping_hash, and perk_config_hash are stored.
Expected: no claim-service code change is required.
```

### Test 17 — Future configured programme dry-run

```text
Configure a temporary future programme with one mapped test level.
Create a claimable perk targeted to that programme/level.
Expected: ClaimValidator uses the configured programme/level context.
Expected: unknown/disabled values fail closed.
Expected: no hardcoded Vriendinneklub/Netwerk-only claim branch is required.
```

---

## 39. Acceptance Criteria

VS-007 is complete only when:

```text
1. wp_vgmp_perk_redemptions table is created safely.
2. Schema version is updated without deleting existing data.
3. ClaimService can create validated generic claims.
4. ClaimValidator blocks logged-out, ineligible, inactive, misconfigured, and over-limit claims.
5. Per-user claim limits work.
6. Per-programme claim limits work.
7. Per-programme-level claim limits work or are explicitly validated as future-ready.
8. Total claim limits work.
9. Claim reset periods produce stable period keys.
10. Idempotency prevents duplicate claims.
11. Reservation lifecycle exists or is explicitly implemented as a safe service for later slices.
12. Already-claimed display state is available to hub/query/card data.
13. Admin can view redemption records.
14. Programme and level context are stored for claims using canonical configured programme_key and level_key values.
14A. programme_mapping_hash and perk_config_hash are stored for claim/reservation context.
14B. Unmapped PMPro levels and unknown/disabled programme/level mappings fail closed.
15. Dual-programme users do not get duplicate claims unless claim_scope explicitly allows it.
16. No WooCommerce cart pricing is implemented.
17. No Masteriyo enrolment is implemented.
18. No Tickera ticket issuing is implemented.
19. Plugin does not fatal if major dependencies are inactive.
20. README documents claim rules, table, statuses, dual-programme behaviour, and limitations.
```

---

## 40. README Update Requirements

Update README with:

```text
VS-007 implemented features
Redemption table name and purpose
Redemption statuses
Claim modes
Claim scopes
Claim period rules
Claim limits
Dual-programme behaviour
Configured programme/level mapping behaviour
Future level/programme behaviour
Reservation lifecycle
Generic claim endpoint
Already-claimed display state
Admin redemption page instructions
Known limitations
Next slice: VS-008 — WooCommerce Product Discounts
```

Include this warning:

```text
VS-007 records and enforces claims. It does not apply WooCommerce discounts, grant Masteriyo course access, or issue Tickera tickets. Those integrations must use ClaimService in later slices.
```

Include this caution:

```text
Abandoned carts must not consume claim limits. WooCommerce reservations/completions are handled by VS-008 using the services defined here.
```

---

## 41. Explicit Out-of-Scope List

Do not implement these in VS-007:

```text
WooCommerce cart price adjustment
WooCommerce cart notices
WooCommerce order item meta from discounts
Masteriyo enrolment
Course access grants
Tickera ticket generation
Ticket claim-to-cart flow
R0 ticket order automation
Email notifications
Advanced analytics dashboards
CSV exports
Full recurring subscription renewal benefit policy
Persistent Redis/object cache layer
Cloudflare/LiteSpeed cache rules
```

These belong to later slices.

---

## 42. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Database table created
Schema version changes
REST endpoint created
Claim statuses implemented
Claim scopes implemented
Configured programme/level mapping behaviour
programme_mapping_hash / perk_config_hash storage
Claim limit rules implemented
Reservation behaviour implemented
Idempotency strategy
Admin pages/columns added
Manual tests performed
Known limitations
Any assumptions made
Any dependency issues
Confirmation that Woo pricing, Masteriyo access, Tickera tickets, and notifications were not implemented
```

Do not claim WooCommerce, Masteriyo, or Tickera integration support in this slice.

---

## 43. Next Slice

After VS-007 is complete and tested, proceed to:

```text
VS-008 — WooCommerce Product Discounts
```

VS-008 will use ClaimValidator, ClaimService, ReservationService, and RedemptionRepository to safely apply WooCommerce product/category discounts and record completed Woo redemptions.

Do not start VS-008 during this slice.
