# FEATURE PLANNING PACK: VS-008 — WooCommerce Product Discounts

Version: **1.3.0**

> Formerly drafted as `VS-007 — WooCommerce Product Discounts`.
>
> This document has been renumbered and hardened because the architecture now requires:
>
> ```text
> VS-007 — Claims and Redemption Ledger
> VS-008 — WooCommerce Product Discounts
> ```
>
> Do **not** implement WooCommerce product discounts before the claims/redemption ledger exists.

---

## 0. v1.2.0 ACF Runtime Boundary Patch Summary

This version applies the ACF runtime-boundary patch.

```text
1. Adds a hard ban on direct ACF calls inside Woo cart/checkout hot paths.
2. Reframes PerkRuleRepository as a runtime rule compiler, not an ACF reader.
3. Adds _vgmp_perk_config_hash and _vgmp_config_provider metadata for troubleshooting.
4. Requires stale cart metadata cleanup on every calculate_totals run.
5. Adds fail-closed behaviour when the configuration provider is unavailable.
6. Preserves future migration from ACF to custom admin configuration without rewriting Woo execution classes.
```

---

## 0A. v1.3.0 Programme Flexibility Patch Summary

This version applies the Programme and Level Flexibility Pass.

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as default launch programmes, not a closed forever-list.
2. Requires Woo discount execution to consume MembershipProfile, PerkRule, ClaimPolicy, and configured programme/level context only.
3. Bans Woo execution code from treating Woo subscription products, Woo variations, PMPro level IDs, PMPro group IDs, or current billing terms as membership authority.
4. Adds ProgrammeMappingRepository / SettingsRepository dependency expectations for configured programme and level definitions.
5. Requires Woo discount metadata to store canonical configured programme_key and level_key values, never aliases.
6. Adds programme_mapping_hash to cart, order-item, ledger, and debug metadata where applicable.
7. Adds future billing-term, future mapped-level, and future configured-programme tests.
8. Adds fail-closed behaviour for unmapped PMPro levels, unknown programmes, disabled programmes, unknown levels, and unavailable programme mapping.
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

Build the WooCommerce product discount execution layer for the **Voelgoed Member Perks** plugin.

This vertical slice must allow eligible logged-in members to receive active member-perk discounts on targeted WooCommerce products or WooCommerce product categories.

This slice must use the systems built in earlier slices:

```text
VS-003 — PMPro Membership Profile Resolver
VS-004 — Runtime Status Automation
VS-006 — Target and Audience Indexing Table
VS-007 — Claims and Redemption Ledger
```

This is an execution slice, but it is **not allowed to become the claim authority**.

The core goal:

```text
When an eligible member adds a targeted WooCommerce product to cart,
the plugin can safely apply the correct member perk price,
record the applied perk context,
respect claim limits through the ledger,
and avoid accidental stacking, renewal pricing changes, or duplicate benefit use.
```

---

## 2. Critical Reorder Decision

The original draft made WooCommerce discounts the first execution slice and placed the claims ledger after it.

That order is unsafe.

Old order:

```text
VS-007 — WooCommerce Product Discounts
VS-008 — Claims and Redemption Ledger
```

Correct order:

```text
VS-007 — Claims and Redemption Ledger
VS-008 — WooCommerce Product Discounts
```

Reason:

```text
Discounts, free products, once-off benefits, per-programme limits,
and limited-quantity perks need a ledger before execution.
```

Without a ledger first, this can happen:

```text
1. User opens two tabs.
2. User adds the same free/limited product twice.
3. Cart pricing checks eligibility twice.
4. No durable claim/reservation exists.
5. User receives more than intended.
```

VS-008 must therefore depend on VS-007.

If VS-007 is missing, incomplete, or does not expose safe claim/reservation methods, VS-008 must stop and report the blocker.

---

## 3. Current Baseline Assumptions

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
```

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected CPT:

```text
vg_perk
```

Expected target/audience index table:

```text
{$wpdb->prefix}vgmp_perk_targets
```

Example:

```text
wp_vgmp_perk_targets
```

Expected target repository:

```php
Voelgoed\MemberPerks\Perks\PerkTargetRepository
```

Expected membership profile resolver:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
```

Expected audience evaluator:

```php
Voelgoed\MemberPerks\Perks\PerkAudienceEvaluator
```

or equivalent from VS-005 / VS-006.

Expected ledger services from VS-007:

```php
Voelgoed\MemberPerks\Claims\ClaimService
Voelgoed\MemberPerks\Claims\ClaimValidator
Voelgoed\MemberPerks\Claims\ReservationService
Voelgoed\MemberPerks\Claims\RedemptionRepository
```

If existing implementation names differ, Woo execution must still use the same responsibilities: validate, reserve, complete, cancel/release, and read claim availability through the ledger authority.

If exact class names differ, preserve the responsibilities and document the deviation.

Expected runtime status taxonomy:

```text
vg_perk_runtime_status = active
```

Default launch programme examples:

```text
vriendinneklub
voelgoednetwerk
```

These are the current Voelgoed launch programmes only. Woo discount execution must support any enabled configured programme returned by ProgrammeRegistry / SettingsRepository / ProgrammeMappingRepository.

Future configured programmes must not require Woo discount engine code changes if their products, audiences, claim rules, and mappings are configured correctly.

Expected target types from VS-006:

```text
woo_product
woo_product_category
masteriyo_course
tickera_event
tickera_ticket
wp_page
wp_post
external_url
programme
programme_level
audience_alias
```

This slice only executes WooCommerce discounts for:

```text
woo_product
woo_product_category
```

---

## 4. Non-Negotiable Scope Boundaries

This slice may implement:

```text
WooCommerce product discount lookup
WooCommerce cart price adjustment
WooCommerce cart/checkout display meta
WooCommerce cart/checkout notices
WooCommerce order item perk metadata
WooCommerce order lifecycle integration with the VS-007 ledger
Conservative frontend product price hints if safe
Admin warnings for invalid product discount perks
```

This slice must not implement:

```text
New claim/redemption database tables
New claim ledger schema
Masteriyo enrolment
Masteriyo course access grants
Tickera ticket issuing
Tickera ticket claim flow
Automatic free product issuing
Automatic add-to-cart
Email notifications
Reports dashboards
Full analytics
Persistent object cache layer
Cloudflare or LiteSpeed cache rules
Theme edits
functions.php edits
WPCode snippet edits
```

Important:

```text
A “free product” perk in this slice means:
If the eligible user adds the targeted product to cart,
the item can be priced at R0 if the VS-007 ledger confirms this is allowed.

It does not mean:
Automatically add the product to cart.
Automatically create an order.
Automatically issue a ticket.
Automatically grant a course.
```

---

## 5. Core Business Rule

The plugin must decide pricing. The page builder must not.

Correct model:

```text
Current Woo cart item
↓
Target index lookup
↓
Active perk lookup
↓
MembershipProfile resolution from configured programme/level mappings
↓
Audience eligibility evaluation against configured ProgrammeDefinition / ProgrammeLevelDefinition rows
↓
Claim ledger availability / claim-scope check
↓
Deterministic best perk selection
↓
Safe cart price adjustment
↓
Order item metadata with canonical programme/level context and mapping hash
↓
Ledger reservation/completion via order lifecycle
```

Incorrect model:

```text
Hide/show product cards only
Use coupons manually as the main system
Use Bricks/Etch dynamic visibility as price authority
Hardcode member prices into templates
Apply multiple discounts by accident
Ignore claim limits until after checkout
Call PMPro directly inside every cart item
Use Woo subscription product IDs or variation IDs as membership authority
Hardcode Goud/Silwer/Brons or Vriendinne/Netwerk-only eligibility branches
```

---

## 5A. Woo Hot-Path Configuration Boundary

Woo cart/checkout execution classes must not call ACF directly.

Forbidden direct calls in Woo execution classes:

```text
get_field()
get_fields()
acf_get_value()
acf option helpers
raw ACF option reads
```

Forbidden locations:

```text
WooDiscountEngine
WooApplicablePerkResolver
WooPriceCalculator
WooBestPerkSelector
WooOrderLedgerBridge
WooCartItemMeta
WooOrderItemMeta
WooCouponPolicy
WooSubscriptionPolicy
```

Allowed path:

```text
AcfConfigProvider / ConfigProviderInterface
↓
PerkConfigRepository
↓
PerkRuleRepository
↓
PerkRule
↓
Woo execution classes
```

Woo classes must continue working if ACF is later replaced by a custom admin provider, provided `PerkRuleRepository` returns the same `PerkRule` contract.


## 5B. Programme and Billing Flexibility Boundary

Woo discount execution must be strict about authority boundaries and flexible about configured business structure.

Rigid rules:

```text
1. PMPro remains the membership/access authority.
2. WooCommerce/Woo Subscriptions remain billing/payment surfaces.
3. VGMP remains perk eligibility, pricing-rule, claim-scope, and redemption authority.
4. Only mapped active PMPro levels count.
5. Unknown/unmapped PMPro levels fail closed.
6. Woo subscription product IDs and variation IDs never decide perk eligibility.
7. Same-programme conflicts must not stack benefits.
8. Dual-programme qualification must not stack discounts by default.
```

Flexible rules:

```text
1. Programme keys come from enabled configured ProgrammeDefinition rows.
2. Level keys come from enabled configured ProgrammeLevelDefinition rows.
3. Vriendinneklub and Voelgoednetwerk are launch/default examples, not a closed forever-list.
4. New billing terms such as quarterly, six-monthly, founding-member, or annual-special billing must not require Woo discount code changes if they grant an already mapped PMPro access level.
5. New mapped levels must work through ProgrammeMappingRepository, MembershipProfile, PerkRuleRepository, and ClaimPolicy without Woo execution code changes.
6. New configured programmes should work in Woo discount resolution if their programme/level mappings, perk audience rules, target index rows, claim policies, and QA are complete.
```

Hard ban:

```text
Woo execution code must not hardcode:
- PMPro level IDs
- PMPro group IDs
- Woo membership product IDs
- Woo variation IDs
- current billing terms
- Goud/Silwer/Brons-only assumptions
- Brons/Silwer/Goud-only assumptions
- Vriendinneklub/Netwerk-only eligibility branches
```

Allowed launch examples may appear in README, admin labels, default seed data, and tests, but not as eligibility decisions inside Woo execution paths.

## 6. Required Deliverables

Add the following plugin files:

```text
includes/
├── Woo/
│   ├── WooIntegration.php
│   ├── WooContextGuard.php
│   ├── WooDiscountEngine.php
│   ├── WooApplicablePerkResolver.php
│   ├── WooPriceCalculator.php
│   ├── WooBestPerkSelector.php
│   ├── WooCartItemMeta.php
│   ├── WooCartNotices.php
│   ├── WooOrderItemMeta.php
│   ├── WooOrderLedgerBridge.php
│   ├── WooCouponPolicy.php
│   ├── WooSubscriptionPolicy.php
│   └── WooPriceDisplay.php
│
├── Perks/
│   ├── PerkRule.php
│   └── PerkRuleRepository.php
│
└── Admin/
    └── PerkWooWarnings.php
```

Update:

```text
includes/Plugin.php
README.md
```

Optional if already established:

```text
includes/Support/Logger.php
```

Do not create claim/redemption tables in this slice.

---

## 7. Version Target

If previous slices used semantic plugin versions, target:

```text
0.8.0
```

Reason:

```text
VS-008 is the eighth planned vertical slice after VS-000 discovery.
```

If the project already uses a different version sequence, keep the existing scheme and document the actual version used.

---

## 8. WooIntegration Class

Create:

```php
Voelgoed\MemberPerks\Woo\WooIntegration
```

Responsibilities:

```text
1. Register WooCommerce-related hooks only if WooCommerce is active.
2. Instantiate Woo discount services.
3. Avoid fatal errors if WooCommerce is inactive.
4. Check that VS-007 ledger services exist before enabling execution.
5. Respect feature flags from VS-002.
6. Confirm ProgrammeMappingRepository / SettingsRepository are available before enabling programme-aware execution.
7. Fail closed if programme mapping is unavailable or invalid.
```

Safe WooCommerce detection:

```php
class_exists( "WooCommerce" )
```

or:

```php
function_exists( "WC" )
```

If WooCommerce is inactive:

```text
Do nothing safely.
```

If the VS-007 ledger is unavailable:

```text
Do not apply limited/free/claim-scoped discounts.
Show/log an admin warning.
Do not fatal.
```

Recommended feature flag:

```text
vgmp_enable_woo_discounts
```

Default:

```text
false until explicitly enabled and tested on staging
```

---

## 9. Required WooCommerce Hooks

Use WooCommerce hooks carefully.

Recommended hooks:

```php
woocommerce_before_calculate_totals
woocommerce_checkout_create_order_line_item
woocommerce_get_item_data
woocommerce_cart_item_name
woocommerce_cart_totals_before_order_total
woocommerce_before_cart_totals
woocommerce_checkout_order_processed
woocommerce_payment_complete
woocommerce_order_status_processing
woocommerce_order_status_completed
woocommerce_order_status_failed
woocommerce_order_status_cancelled
```

Optional display hook/filter if safe:

```php
woocommerce_get_price_html
```

### Critical warning

`woocommerce_before_calculate_totals` may run multiple times per request.

The implementation must prevent repeated discount compounding.

Do not do this:

```text
Take already-discounted cart item price and discount it again on every hook run.
```

Correct approach:

```text
Resolve a deterministic base price.
Calculate from that base price.
Set the final price for this calculation cycle.
Store server-generated cart item metadata explaining the applied perk.
Never permanently alter the product price.
```

---

## 10. Context Guard

Create:

```php
Voelgoed\MemberPerks\Woo\WooContextGuard
```

Purpose:

```text
Centralise when the Woo pricing engine is allowed to run.
```

Allow execution only when:

```text
1. WooCommerce is active.
2. Cart context is valid.
3. Current request is a normal frontend cart/checkout calculation or valid Woo AJAX cart update.
4. User is logged in.
5. Woo discount feature flag is enabled.
6. MembershipProfile resolver is available.
7. Target table/repository is available.
8. Ledger service is available for claim-scoped perks.
```

Do not run in:

```text
wp-admin order editing
wp-admin product editing
cron jobs
REST requests without valid Woo cart context
CLI
subscription renewal order generation
membership subscription product/variation inspection as authority
payment gateway callbacks unless explicitly safe
```

Guard example:

```php
if ( is_admin() && ! wp_doing_ajax() ) {
    return false;
}
```

Also guard:

```text
No cart = no execution.
Empty cart = no execution.
Logged-out user = no execution.
Missing MembershipProfile = no execution.
```

---

## 11. Supported Benefit Types in VS-008

From ACF `perk_benefit_type`, support these for Woo products:

```text
free
percentage_discount
fixed_discount
fixed_member_price
```

Ignore or skip these in VS-008:

```text
member_only_access
display_only
course_access
free_course
free_ticket
event_discount
download
replay
partner_deal
```

If a skipped perk type targets a Woo product, it should not break anything.

Admin warnings may flag questionable configuration.

---

## 12. Supported Target Types in VS-008

Support these benefit target types:

```text
woo_product
woo_product_category
```

### `woo_product`

Applies when:

```text
cart item product ID matches target_id
or cart item variation ID matches target_id
or cart item variation parent product ID matches target_id
```

### `woo_product_category`

Applies when:

```text
cart item product belongs to the targeted product category
or variation parent product belongs to the targeted product category
```

Important:

```text
Variation products must be handled carefully.
A perk targeted to a parent variable product should apply to its variations unless explicitly configured otherwise in a future slice.
```

---

## 13. Explicit Exclusions

Do not apply member perk discounts automatically to:

```text
Logged-out users
Ineligible members
Membership subscription renewal orders
Existing subscriptions
Admin-created/editing orders
Cron-generated orders
Manual renewal orders
Subscription switch orders
Resubscribe orders
Payment gateway callback recalculations
Non-product perk targets
```

If Woo Subscriptions is active, be conservative.

Default rule:

```text
Apply campaign perk discounts only in normal frontend cart/checkout context.
```

Subscription product policy:

```text
If a subscription product itself is explicitly targeted and added to cart,
the first checkout price may receive the perk only if tested and enabled.

Recurring renewal totals must not be modified by this slice.
```

Add a clear README warning:

```text
VS-008 does not define recurring subscription renewal discount policy.
Do not use this slice alone for long-term subscription renewal discounts.
```

---

## 14. Eligibility Rules

A Woo product discount may apply only if all are true:

```text
1. User is logged in.
2. WooCommerce cart is active.
3. Woo discount feature flag is enabled.
4. Programme mapping is available and valid.
5. Product/category is targeted by one or more active perks.
6. Perk post_status = publish.
7. Perk runtime status = active.
8. Perk is not misconfigured, paused, archived, expired, or upcoming.
9. User's MembershipProfile satisfies the perk's audience rules through configured programme/level mappings.
10. Perk benefit type is supported by VS-008.
11. Claim scope / ledger availability says the user may still receive the benefit.
12. Coupon policy allows the member perk to apply.
13. Subscription policy allows the member perk to apply.
14. The discount calculation results in a valid non-negative price.
```

Do not apply discounts to logged-out users.

Do not apply discounts to users whose MembershipProfile has no matching eligible configured programme/level.

Do not apply discounts for unmapped PMPro levels, unknown programme keys, disabled programme mappings, or unknown level keys.

Do not call PMPro directly inside cart item loops.

Correct:

```php
$profile = $membership_profile_resolver->resolve( $user_id );
```

Incorrect:

```php
pmpro_hasMembershipLevel( ... ) inside every cart item loop
```

---

## 15. Dual-Programme Eligibility Rules

A user can belong to any enabled configured programme set.

For launch, expected states include:

```text
Vriendinneklub only
Voelgoednetwerk only
both Vriendinneklub and Voelgoednetwerk
neither
```

Future configured programmes must not require Woo discount code changes if they are mapped and tested.

Launch example:

```text
vriendinneklub = goud
voelgoednetwerk = silwer
```

Future example:

```text
voelgoedbusiness = founder
```

Examples are illustrative only. Actual programme_key and level_key values come from ProgrammeMappingRepository.

VS-008 must handle this cleanly.

Rules:

```text
1. A multi-programme member may qualify through one or more configured programmes.
2. If the same perk is available through both programmes, do not duplicate it.
3. If two different perks apply to the same product, use deterministic best-benefit selection.
4. Do not stack discounts from multiple configured programmes by default.
5. Programme context must be recorded for the winning/applied perk.
```

Default stacking rule:

```text
No stacking unless a future slice explicitly enables a narrow stacking policy.
```

Example:

```text
Vriendinneklub Goud perk = 10% off Product A
Voelgoednetwerk Silwer perk = 15% off Product A

Default result:
User gets the winning single benefit according to priority/best-discount rules.
```

Not:

```text
10% + 15%
```

---

## 16. Claim Scope / Ledger Rules

VS-008 must use the VS-007 ledger for claim-scoped perks.

Expected claim scopes from VS-002 / VS-007:

```text
per_user
per_programme
per_programme_level
per_order
unlimited
```

Default:

```text
per_user
```

Claim validation must use canonical configured programme_key and level_key values. Aliases are input-normalization only and must not be stored as applied Woo context.

Rules:

```text
1. If claim_scope = unlimited, ledger may still record usage, but it does not block repeated use.
2. If claim_scope = per_user, the user may receive the perk once total.
3. If claim_scope = per_programme, the user may receive the perk once per qualifying programme.
4. If claim_scope = per_programme_level, the user may receive the perk once per programme-level pair.
5. If claim_scope = per_order, the user may receive the perk once per order/cart context.
```

Important:

```text
Cart price calculation should not create a completed claim.
```

Recommended lifecycle:

```text
Cart calculation:
- read ledger availability
- price item if available
- store temporary cart metadata only

Order creation:
- create ledger reservation if the perk is claim-scoped and still available

Payment/order success:
- complete the reservation

Failed/cancelled order:
- release or cancel the reservation

Refund:
- do not automatically restore claims unless VS-007 defines that policy
- record/report according to ledger rules
```

If the ledger does not support reservations:

```text
Do not enable free/limited claim-scoped Woo perks.
Only allow unlimited / display-safe discounts if explicitly approved.
Report this as a blocker.
```

---

## 17. Applicable Perk Resolver

Create:

```php
Voelgoed\MemberPerks\Woo\WooApplicablePerkResolver
```

Responsibilities:

```text
1. Determine product ID, variation ID, and parent product ID for a cart item.
2. Get active perk IDs targeting that product.
3. Get active perk IDs targeting that product's categories.
4. Filter perks by supported benefit type.
5. Evaluate audience rules against the user's MembershipProfile.
6. Check ledger availability for claim-scoped perks.
7. Return candidate PerkRule objects with canonical programme/level match context.
8. Include programme_mapping_hash in candidate context.
9. Fail closed when programme mapping is unavailable or when a perk references unknown/disabled programme or level values.
```

Do not calculate prices here.

This class resolves candidates only.

It must not contain fixed checks for launch-only programme names, fixed level names, or PMPro level IDs.

---

## 18. PerkRule Class

Create:

```php
Voelgoed\MemberPerks\Perks\PerkRule
```

Purpose:

```text
A small immutable/value-style object representing a perk pricing rule.
```

Suggested properties:

```php
perk_id
title
benefit_type
discount_value
discount_basis
stackable
coupon_compatible
priority
claim_scope
target_type
target_id
matched_programme_key
matched_level_key
eligibility_hash
programme_mapping_hash
audience_match_reason
config_provider
perk_config_hash
```

Do not include raw PMPro level IDs in the value object unless only used in admin debug contexts.

---

## 19. PerkRuleRepository Class

Create:

```php
Voelgoed\MemberPerks\Perks\PerkRuleRepository
```

Responsibilities:

```text
1. Load normalized PerkConfig through PerkConfigRepository.
2. Validate Woo-supportable fields.
3. Compile immutable PerkRule objects.
4. Cache rule data per request.
5. Fail closed for invalid or unavailable configuration.
6. Expose config_hash and config_provider for order/ledger audit.
7. Expose programme_mapping_hash for cart/order/ledger audit.
8. Reject rules whose audience configuration cannot resolve to enabled configured programme/level mappings.
```

Important:

```text
PerkRuleRepository does not mean "ACF reader".
It is the Woo runtime rule compiler.
Only the configuration provider/repository may know ACF field names.
```

Expected ACF/meta fields:

```text
perk_benefit_type
perk_discount_value
perk_discount_basis
perk_stackable
perk_coupon_compatible
perk_priority
perk_claim_scope
```

Expected `perk_discount_basis` choices:

```text
regular_price
sale_price_if_present
current_cart_price
```

Default if missing:

```text
sale_price_if_present
```

If ACF is inactive:

```text
Do not fatal.
No rules should execute.
Admin/debug warning may be logged.
```

---

## 20. Price Calculator

Create:

```php
Voelgoed\MemberPerks\Woo\WooPriceCalculator
```

Responsibilities:

```text
1. Determine base price.
2. Calculate final member price.
3. Prevent negative prices.
4. Avoid raising prices.
5. Return applied discount amount.
```

### Supported calculations

#### `free`

```text
final_price = 0
```

#### `percentage_discount`

Example:

```text
20 means 20% off
final_price = base_price - (base_price * 0.20)
```

Rules:

```text
Minimum percentage: 0
Maximum percentage: 100
```

#### `fixed_discount`

Example:

```text
R50 off
final_price = base_price - 50
```

Rules:

```text
Do not allow final price below 0.
```

#### `fixed_member_price`

Example:

```text
Member price = R99
final_price = 99
```

Rules:

```text
Do not allow negative member price.
If fixed member price is higher than base price, skip the perk by default.
Never make the customer pay more because of a perk.
```

---

## 21. Discount Basis Rules

Use `perk_discount_basis`.

### `regular_price`

Calculate discount from:

```text
$product->get_regular_price()
```

### `sale_price_if_present`

Calculate discount from:

```text
sale price if product is on sale,
otherwise regular/current product price
```

### `current_cart_price`

Calculate discount from:

```text
current Woo cart item price before member perk is applied
```

Important:

```text
current_cart_price is the riskiest basis because other plugins may have already affected price logic.
```

Default:

```text
sale_price_if_present
```

If base price cannot be determined:

```text
Skip the perk.
Log/admin-warn if appropriate.
```

---

## 22. Best Perk Selection

Create:

```php
Voelgoed\MemberPerks\Woo\WooBestPerkSelector
```

If multiple perks apply to the same cart item, choose one deterministic winner.

Default rule:

```text
Perks do not stack.
```

Selection order:

```text
1. Highest priority wins.
2. If same priority, best customer discount wins.
3. If same discount, most specific target wins.
4. If still tied, lowest perk ID wins.
```

Priority convention:

```text
Lower number = higher priority.
```

Specificity order:

```text
woo_product direct variation target
woo_product direct product target
woo_product parent product target
woo_product_category target
```

Example:

```text
Priority 10 beats priority 100.
```

If `perk_stackable = true`:

```text
Read and store the flag.
Do not implement multi-perk stacking in VS-008 unless explicitly approved.
```

---

## 23. Coupon Compatibility

Create:

```php
Voelgoed\MemberPerks\Woo\WooCouponPolicy
```

Default:

```text
Coupons do not combine with member perks.
```

Preferred VS-008 rule:

```text
If WC()->cart->get_applied_coupons() is not empty
and winning perk has perk_coupon_compatible = false,
then do not apply that member perk.
```

If `perk_coupon_compatible = true`:

```text
Allow the member perk to apply, but still calculate from the documented discount basis.
```

Document this clearly in README.

Later slices may implement more nuanced interaction between product-level coupons, cart-level coupons, and member perks.

---

## 24. Subscription Policy

Create:

```php
Voelgoed\MemberPerks\Woo\WooSubscriptionPolicy
```

Default policy:

```text
VS-008 does not discount renewal orders.
VS-008 does not modify existing subscriptions.
VS-008 does not change recurring totals.
VS-008 does not apply to switch/resubscribe/manual renewal orders.
```

If Woo Subscriptions is active, guard cautiously.

Examples of contexts to avoid unless explicitly tested:

```text
wcs renewal order generation
subscription switch cart context
resubscribe cart context
manual renewal payment
failed renewal retry
```

If a subscription product is directly targeted for a one-time initial checkout discount:

```text
The first checkout price may be discounted only if:
1. the product is explicitly targeted,
2. the site owner has enabled it,
3. testing confirms recurring totals are not unintentionally changed.
```

Recommended option:

```text
vgmp_allow_initial_subscription_product_discounts
```

Default:

```text
false
```

---

## 25. Cart Price Adjustment

Create:

```php
Voelgoed\MemberPerks\Woo\WooDiscountEngine
```

Hook:

```php
woocommerce_before_calculate_totals
```

Responsibilities:

```text
1. Guard against invalid contexts.
2. Loop through cart items.
3. Resolve applicable perks.
4. Select winning perk.
5. Calculate final price.
6. Set cart item product price.
7. Store server-generated cart item metadata about the applied perk.
8. Avoid repeated compounding.
9. Clear stale applied-perk metadata when the item no longer qualifies.
```

Guard conditions:

```php
if ( is_admin() && ! wp_doing_ajax() ) {
    return;
}

if ( ! function_exists( "WC" ) || ! WC()->cart ) {
    return;
}
```

Also guard:

```text
Do not process if cart is empty.
Do not process for logged-out users.
Do not process if MembershipProfile has no eligible matches.
Do not process if VS-006 target table is missing.
Do not process if VS-007 ledger is unavailable for claim-scoped perks.
```

---

## 26. Avoiding Discount Compounding

Each cart item should store calculated values in cart item data.

Suggested cart item keys:

```text
_vgmp_original_price
_vgmp_base_price
_vgmp_member_price
_vgmp_applied_perk_id
_vgmp_applied_perk_title
_vgmp_applied_benefit_type
_vgmp_applied_discount_amount
_vgmp_applied_programme_key
_vgmp_applied_level_key
_vgmp_applied_claim_scope
_vgmp_eligibility_hash
_vgmp_perk_config_hash
_vgmp_config_provider
_vgmp_programme_mapping_hash
_vgmp_ledger_availability_key
```

Important:

```text
Do not calculate percentage discounts from already-discounted prices.
Do not permanently alter product prices.
Do not trust cart item meta supplied by the browser.
```

Recommended VS-008 behaviour:

```text
Use fresh product price as base each calculate_totals run where practical.
Store applied perk metadata for display/order/ledger only.
```

If a product price changes after the item is already in the cart:

```text
Recalculate from current authoritative Woo product price.
```

Document the chosen behaviour.



Additional stale metadata cleanup rule:

```text
On every calculate_totals run:
- recalculate from current PerkRuleRepository output
- remove stale _vgmp_* cart metadata when the user/product no longer qualifies
- never trust old cart item metadata as proof of eligibility
- never use browser-submitted metadata to force a perk
```
---

## 27. Cart Item Display Meta

Create:

```php
Voelgoed\MemberPerks\Woo\WooCartItemMeta
```

Hook:

```php
woocommerce_get_item_data
```

Display small cart metadata for applied perk:

```text
Member perk: Goud member benefit
You saved: R50
```

Use translatable strings.

Example labels:

```text
Member perk
Member saving
```

Do not expose:

```text
internal perk IDs
raw PMPro level IDs
ledger IDs
eligibility hashes
debug data
```

---

## 28. Cart Notices

Create:

```php
Voelgoed\MemberPerks\Woo\WooCartNotices
```

Purpose:

```text
Show a clear, non-spammy message when a member perk has applied.
```

Example:

```text
Your member benefit has been applied to eligible products in your cart.
```

Avoid duplicate notices on every cart recalculation.

Use a session/request flag to prevent duplicates.

Do not show one notice per item if many items qualify.

---

## 29. Order Item Meta

Create:

```php
Voelgoed\MemberPerks\Woo\WooOrderItemMeta
```

Hook:

```php
woocommerce_checkout_create_order_line_item
```

Store hidden order item meta:

```text
_vgmp_perk_id
_vgmp_perk_title
_vgmp_benefit_type
_vgmp_discount_amount
_vgmp_final_member_price
_vgmp_programme_key
_vgmp_level_key
_vgmp_claim_scope
_vgmp_eligibility_hash
_vgmp_perk_config_hash
_vgmp_config_provider
_vgmp_programme_mapping_hash
_vgmp_redemption_id
_vgmp_reservation_id
```

Visible order item meta is optional.

Recommended:

```text
Store technical values as hidden meta.
Use cart item display for customer-facing clarity.
```

This prepares for reporting, support, and reconciliation.

---

## 30. Woo Order Ledger Bridge

Create:

```php
Voelgoed\MemberPerks\Woo\WooOrderLedgerBridge
```

Purpose:

```text
Connect discounted Woo order line items to the VS-007 claim/redemption ledger.
```

Recommended lifecycle:

### Order created

On:

```php
woocommerce_checkout_order_processed
```

For each discounted order item:

```text
1. Re-check perk eligibility through current MembershipProfile, PerkRule, ClaimPolicy, and configured programme mapping.
2. Re-check ledger availability using canonical programme/level context and programme_mapping_hash.
3. Create reservation if required.
4. Store reservation/redemption reference in order item meta.
```

### Order paid / accepted

On:

```php
woocommerce_payment_complete
woocommerce_order_status_processing
woocommerce_order_status_completed
```

For each reserved discounted order item:

```text
Complete ledger reservation.
Record order ID and item ID.
```

### Order failed / cancelled

On:

```php
woocommerce_order_status_failed
woocommerce_order_status_cancelled
```

For each reserved but incomplete ledger entry:

```text
Release/cancel reservation according to VS-007 ledger rules.
```

### Refunds

Refund behaviour must follow VS-007 policy.

Default VS-008 rule:

```text
Do not automatically restore consumed claim limits on refund unless VS-007 explicitly defines that policy.
Log/report refund-linked redemptions for admin review.
```

---

## 31. Price Display Hints

Create:

```php
Voelgoed\MemberPerks\Woo\WooPriceDisplay
```

Optional in VS-008.

Purpose:

```text
Show logged-in eligible members a conservative hint on product pages or loops that a member perk may apply.
```

Example:

```text
Member benefit may apply in cart.
```

Important:

```text
Do not override product price HTML in a way that lies about final cart pricing.
Do not run expensive perk lookups for every product in large loops without request caching.
Do not show member-only price hints to logged-out users unless a future marketing teaser policy exists.
```

Recommendation:

```text
Implement conservative hints only, or defer display price changes until cart discount logic is proven.
```

Acceptance does not require aggressive price display modification.

---

## 32. Admin Warnings

Create:

```php
Voelgoed\MemberPerks\Admin\PerkWooWarnings
```

Purpose:

```text
Warn admins when Woo product discount perks are misconfigured.
```

Warnings:

```text
Woo discount perk has no Woo product/category target.
Woo discount perk has unsupported benefit type.
Percentage discount is below 0 or above 100.
Fixed discount is negative.
Fixed member price is negative.
Free product perk is claim-scoped but ledger is unavailable.
Perk allows stacking, but VS-008 does not implement stacking.
Perk targets subscription product while subscription discount option is disabled.
Perk has no valid audience rules.
Perk has ambiguous dual-programme audience rules.
Perk references an unknown/disabled configured programme.
Perk references an unknown configured level.
Programme mapping is unavailable while Woo discounts are enabled.
Woo billing product or variation appears to grant an unmapped PMPro level.
```

Warnings should appear on the perk edit screen for administrators.

Do not block saving unless values are dangerously invalid.

---

## 33. Product Categories

For category-targeted perks:

```text
Target type: woo_product_category
Target ID: product_cat term ID
```

A product qualifies if:

```text
Product or parent product has that product_cat term.
```

Use Woo/WordPress taxonomy functions safely:

```php
has_term( $term_id, "product_cat", $product_id )
```

For variations:

```text
Check parent product ID as well.
```

---

## 34. Variation Products

Required behaviour:

```text
If perk targets parent variable product, all variations qualify.
If perk targets variation product directly and variation ID is indexed, that variation qualifies.
If perk targets product category assigned to parent, variation qualifies.
```

Cart item IDs to consider:

```text
$product_id
$variation_id
parent_id
```

Do not skip variations.

---

## 35. Checkout Blocks Compatibility

WooCommerce cart pricing usually still passes through server-side cart calculation, but cart/checkout display behaviour may differ between classic shortcode checkout and Woo Blocks checkout.

VS-008 must inspect and document:

```text
1. Is /cart classic shortcode or Woo Blocks?
2. Is /checkout classic shortcode or Woo Blocks?
3. Do cart item meta and notices display correctly?
4. Are price adjustments reflected correctly?
```

Default MVP support:

```text
Classic shortcode cart/checkout required for full display guarantees.
Woo Blocks may be supported only if tested.
```

If Blocks are detected and display is incomplete:

```text
Discount calculations may still work,
but README must document limitations.
```

Do not pretend Woo Blocks integration is complete unless verified.

---

## 36. Tax and Currency Handling

Use WooCommerce product/cart price values.

Rules:

```text
Do not manually format currency in calculations.
Use wc_price() only for display.
Use decimal-safe numeric handling where practical.
Do not create negative prices.
Do not modify tax settings.
Let WooCommerce calculate taxes from final item prices.
```

If prices include/exclude tax depends on WooCommerce settings.

Do not override tax behaviour in this slice.

---

## 37. Rounding

Use WooCommerce decimal helpers where practical.

Recommended:

```php
wc_format_decimal()
wc_get_price_decimals()
```

Final price must be numeric and non-negative.

Avoid floating point surprises as much as practical.

---

## 38. Caching and Performance

This slice must be careful on cart/checkout.

Rules:

```text
1. Resolve current user's MembershipProfile once per request.
2. Cache PerkRule objects per request keyed by perk_config_hash and programme_mapping_hash.
3. Cache target lookup results per product/category per request.
4. Cache ledger availability checks per cart calculation cycle where safe.
5. Do not query all perks for every cart item.
6. Use wp_vgmp_perk_targets for target lookups.
7. Use runtime status taxonomy to restrict to active perks.
8. Avoid expensive frontend price lookups in product archive loops.
9. Include programme_mapping_hash in eligibility-dependent request cache keys.
10. Invalidate request-level eligibility/rule assumptions when mapping changes during the request, if such an admin action is triggered.
```

Do not implement persistent Redis/object cache yet unless the project already has a standard cache abstraction.

Request-level caching is enough for VS-008.

---

## 39. Security Requirements

All values must be sanitized/validated.

Cart item meta must not trust frontend input.

Perk IDs used in pricing must come from server-side lookup only.
Programme keys and level keys used in pricing must come from server-side MembershipProfile / PerkRule match context only.

Do not allow users to submit a perk ID, programme key, level key, PMPro level ID, Woo variation ID, or billing product ID and force a discount.

Admin warnings must check capability:

```text
manage_vg_perks
```

Order item meta must be generated server-side.

Ledger reservations/completions must be server-side only.

Do not expose:

```text
raw PMPro level IDs
ledger internals
eligibility hashes
admin notes
```

to normal frontend users.

---

## 40. Compatibility Requirements

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
WooIntegration registers no hooks.
Plugin still loads.
```

If PMPro/resolver is unavailable:

```text
No discounts apply.
Admin/debug warning may be logged.
```

If ProgrammeMappingRepository / SettingsRepository is unavailable or invalid:

```text
No discounts apply.
Admin/debug warning should state programme mapping is unavailable or invalid.
Do not guess from PMPro level names, Woo product IDs, or legacy metadata.
```

If ACF is inactive:

```text
Perk rules may not load.
No discounts apply.
Plugin still loads.
```

If VS-006 table is missing:

```text
No discounts apply.
Admin warning should state target index table is missing.
Do not fatal.
```

If VS-007 ledger is missing:

```text
Do not apply claim-scoped or limited/free discounts.
Admin warning should state claim ledger is missing.
Do not fatal.
```

---

## 41. Testing Plan

Test on staging only.



### Test 0 — Configuration provider fail-closed behaviour

```text
Simulate ACF/config provider unavailable.
Expected: no member discounts apply.
Expected: no fatal errors.
Expected: no fake R0/free product pricing.
Expected: admin/debug warning records provider unavailable.
Expected: stale cart metadata is cleared on recalculation.
```

### Test 0A — Programme mapping unavailable fail-closed behaviour

```text
Simulate ProgrammeMappingRepository unavailable or invalid.
Expected: no member discounts apply.
Expected: no fatal errors.
Expected: stale _vgmp_* cart metadata is cleared.
Expected: admin/debug warning records programme_mapping_unavailable.
Expected: no discount is guessed from PMPro level names, Woo product IDs, or Woo variation IDs.
```

### Test 0B — Unmapped PMPro level fail-closed behaviour

```text
Give a test user an active PMPro level that is not mapped to any enabled ProgrammeLevelDefinition.
Add a targeted product that would otherwise have a member perk.
Expected: no member discount applies.
Expected: debug/admin context reports unmapped_pmpro_level where available.
Expected: no ledger reservation is created.
```

### Test 0C — Future billing term for existing access level

```text
Add a temporary Woo subscription variation or billing product that grants an already mapped PMPro access level.
Purchase it on staging if safe.
Expected: MembershipProfile resolves to the same programme_key + level_key as the existing monthly/yearly option.
Expected: existing Woo product perks still work without Woo discount code changes.
Expected: no new VGMP level mapping is required if access level is unchanged.
```

### Test 0D — Future mapped level

```text
Create a temporary PMPro access-only test level.
Map it to a new enabled ProgrammeLevelDefinition row.
Create a Woo product perk targeted to that programme/level.
Expected: eligible user receives the correct discount without Woo execution code changes.
Expected: order item meta stores the new canonical programme_key and level_key.
```

### Test 0E — Future configured programme

```text
Create a temporary configured programme with one mapped test level.
Create a Woo product perk targeted to that programme/level.
Expected: eligible user receives the correct discount without adding a programme-specific Woo code branch.
Expected: if the programme is disabled, no discount applies.
```

### Test 0F — Alias normalization

```text
Configure a programme alias or level alias for migration compatibility.
Create a perk/audience rule using an alias only if the admin/provider layer allows alias input.
Expected: Woo execution stores canonical configured programme_key and level_key.
Expected: no duplicate programme branch is created.
```

### Test 1 — Woo inactive safety

```text
If safe, deactivate WooCommerce on staging.
Expected: plugin does not fatal.
Woo discount hooks do not run.
```

### Test 2 — Feature flag disabled

```text
Set vgmp_enable_woo_discounts = false.
Add targeted product as eligible user.
Expected: no discount applies.
Expected: no ledger reservation is created.
```

### Test 3 — Logged-out user

```text
Create active product perk.
Visit product/cart logged out.
Expected: no member discount applies.
```

### Test 4 — No-membership user

```text
Log in as a user with no active Vriendinneklub or Netwerk membership.
Add targeted product.
Expected: normal price.
```

### Test 5 — Ineligible member

```text
Create perk for Vriendinneklub Goud.
Log in as Vriendinneklub Brons member.
Add targeted product to cart.
Expected: normal price.
```

### Test 6 — Eligible Vriendinne member percentage discount

```text
Create active perk:
target = Product A
benefit = percentage_discount
value = 20
audience = Vriendinneklub Goud

Log in as Vriendinneklub Goud member.
Add Product A to cart.
Expected: item price reduced by 20%.
```

### Test 7 — Eligible Netwerk member percentage discount

```text
Create active perk:
target = Product A
benefit = percentage_discount
value = 15
audience = Voelgoednetwerk Silwer

Log in as Netwerk Silwer member.
Add Product A to cart.
Expected: item price reduced by 15%.
```

### Test 8 — Multi-programme best benefit

```text
User has launch example:
Vriendinneklub Goud
Voelgoednetwerk Silwer

Or future equivalent:
Programme A Level X
Programme B Level Y

Create two active perks for Product A:
Vriendinneklub Goud = 10%
Voelgoednetwerk Silwer = 15%

Expected:
One discount applies.
No stacking across programmes by default.
Winning rule follows priority/best-benefit rules.
No fixed launch-only programme branch is required.
```

### Test 9 — Fixed discount

```text
Create active perk:
target = Product A
benefit = fixed_discount
value = 50

Expected: price reduced by R50, not below R0.
```

### Test 10 — Fixed member price

```text
Create active perk:
target = Product A
benefit = fixed_member_price
value = 99

Expected: eligible user pays R99 if base price is above R99.
Expected: if base price is below R99, customer price is not increased.
```

### Test 11 — Free product with ledger

```text
Create active perk:
target = Product A
benefit = free
claim_scope = per_user

Eligible user adds Product A.
Expected: cart item price = R0 if ledger says available.
Expected: checkout creates reservation.
Expected: successful order completes redemption.
Expected: second attempt is blocked or normal-priced according to ledger rules.
```

### Test 12 — Free product without ledger

```text
Disable or simulate unavailable ledger service.
Create free per_user product perk.
Expected: discount does not apply.
Expected: admin/debug warning says ledger is required.
```

### Test 13 — Expired perk

```text
Set perk runtime status = expired.
Add targeted product.
Expected: no discount.
```

### Test 14 — Misconfigured perk

```text
Set perk runtime status = misconfigured.
Add targeted product.
Expected: no discount.
```

### Test 15 — Category target

```text
Create active perk targeting product category.
Add product in that category.
Expected: discount applies.
Add product outside category.
Expected: no discount.
```

### Test 16 — Variation product

```text
Create perk targeting parent variable product.
Add variation to cart.
Expected: discount applies.
```

### Test 17 — Multiple perks same priority

```text
Create two active perks for same product with same priority.

Expected:
Best customer discount wins.

If same discount:
Lowest perk ID wins.
```

### Test 18 — Priority wins

```text
Create two active perks for same product:
Perk A priority 10 gives 10%.
Perk B priority 100 gives 50%.

Expected:
Priority 10 wins if lower number = higher priority.
```

### Test 19 — Coupon compatibility

```text
Create active perk with coupon_compatible = false.
Apply Woo coupon.
Expected: member perk does not apply.

Create active perk with coupon_compatible = true.
Expected: behaviour follows documented VS-008 rule.
```

### Test 20 — No compounding

```text
Refresh cart several times.
Trigger calculate_totals multiple times.
Expected: discount does not compound repeatedly.
```

### Test 21 — Order item meta

```text
Complete checkout with discounted item.
Inspect order item meta.
Expected hidden _vgmp_* meta exists.
Expected programme/level context is stored.
Expected reservation/redemption reference is stored where applicable.
```

### Test 22 — Failed/cancelled order reservation release

```text
Create an order with reserved claim-scoped discount.
Set order to failed/cancelled before completion.
Expected: reservation is released/cancelled according to VS-007 ledger rules.
```

### Test 23 — Admin context

```text
Edit order or product in wp-admin.
Expected: no unwanted price changes.
```

### Test 24 — Subscription caution

```text
If a subscription product is targeted, test first checkout price only if enabled.
Expected: no renewal policy is assumed or silently changed.
Document observed behaviour.
```

### Test 25 — Checkout mode

```text
Determine if cart/checkout are classic shortcode pages or Woo Blocks.
Expected: discounts calculate correctly.
Expected: display meta/notices are verified or limitations documented.
```

---

## 42. Acceptance Criteria

VS-008 is complete only when:

```text
1. Eligible logged-in members receive product/category discounts in Woo cart.
2. Logged-out users do not receive member discounts.
3. No-membership users do not receive member discounts.
4. Ineligible members do not receive member discounts.
5. Active runtime status is required.
6. Misconfigured/paused/archived/upcoming/expired perks do not apply.
7. Target index table wp_vgmp_perk_targets is used for product/category lookup.
8. MembershipProfile is used; PMPro is not called directly inside every cart item loop.
9. Dual-programme users are handled correctly.
10. Multiple matching perks resolve deterministically.
11. Discounts do not stack by default.
12. Discounts do not compound on repeated cart recalculation.
13. Free, percentage, fixed discount, and fixed member price benefits work.
14. Free/limited/claim-scoped benefits are gated by the VS-007 ledger.
15. Variation products are handled correctly.
16. Product category targets are handled correctly.
17. Coupon compatibility is conservative and documented.
18. Order item meta records applied perk data.
19. Order item meta records programme/level context.
20. Claim reservation/completion integration works where required.
21. No new claim/redemption table is created in this slice.
22. Masteriyo and Tickera behaviour is untouched.
23. Subscription renewals are not modified.
24. Plugin does not fatal if dependencies are inactive.
25. README documents usage, limitations, and testing.
26. Woo execution consumes configured programme/level context and does not hardcode PMPro level IDs, Woo membership product IDs, Woo variation IDs, or current billing terms.
27. ProgrammeMappingRepository / SettingsRepository unavailability fails closed.
28. Unmapped PMPro levels do not receive discounts.
29. Future billing variation for an existing mapped access level requires no Woo discount code change.
30. Future mapped level can receive discounts through configuration and QA.
31. Future configured programme can be supported through mapping and QA without a programme-specific Woo execution branch.
32. Cart, order item, and ledger metadata include programme_mapping_hash where applicable.
```

---

## 43. README Update Requirements

Update README with:

```text
VS-008 implemented features
Required dependency on VS-007 ledger
Supported Woo target types
Supported benefit types
How discounts are calculated
How best perk selection works
How dual-programme and future multi-programme users are handled
How claim scopes are enforced
How coupon compatibility works
How variation products are handled
How order item meta is stored
How ledger reservations/completions work
How programme_mapping_hash is stored for audit
How to add a new billing term without Woo code changes
How to test a future mapped level or future configured programme
Checkout Blocks/classic checkout notes
Known limitations
Subscription renewal caution
Next slice: VS-009 — Masteriyo Course Perks
```

Include this warning clearly:

```text
VS-008 depends on the VS-007 Claims and Redemption Ledger.
Do not enable free, once-off, or limited Woo perks unless ledger reservation/completion has been tested.
```

Also include:

```text
VS-008 does not define recurring subscription renewal discount policy.
Do not use this slice alone for long-term subscription renewal discounts without a dedicated renewal-pricing slice.
```

---

## 44. Explicit Out-of-Scope List

Do not implement these in VS-008:

```text
New claim/redemption table schema
New ledger authority
Automatic free product issuing
Masteriyo enrolment
Course access changes
Tickera ticket generation
Ticket claim-to-cart flow
Email notifications
Reports dashboard
Analytics
Persistent Redis/object cache layer
Cloudflare/LiteSpeed page cache rules
Theme edits
functions.php edits
WPCode edits
Treating every Woo subscription product as a programme
Treating Woo product/variation IDs as membership authority
Treating every PMPro level as a perk level automatically
```

These belong to other vertical slices.

---

## 45. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
WooCommerce hooks used
Feature flags checked
Supported benefit types implemented
Supported target types implemented
How discount compounding is prevented
How multiple perks are resolved
How dual-programme and future multi-programme users are handled
How claim scope/ledger availability is checked
How reservation/completion lifecycle works
How coupon compatibility was handled
How subscription contexts were guarded
Order item meta keys added
Programme mapping hash handling
Future billing-term test result
Future mapped-level test result
Future configured-programme dry-run result
Checkout mode tested
Manual tests performed
Any assumptions made
Any limitations
Any dependency issues
```

If subscription products were encountered during testing, report exactly what was tested and what was not.

Do not claim renewal discount support unless it was intentionally implemented and tested, which is not expected in VS-008.

---

## 46. Next Slice

After VS-008 is complete and tested, proceed to:

```text
VS-009 — Masteriyo Course Perks
```

VS-009 will use the membership profile, target index, and claim ledger to handle course-related perks safely.

Do not start VS-009 during this slice.
