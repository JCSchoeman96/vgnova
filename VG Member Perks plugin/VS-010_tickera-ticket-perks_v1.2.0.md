# FEATURE PLANNING PACK: VS-010 — Tickera Ticket Perks

Version: 1.2.0  
Status: Flexibility-hardened planning pack  
Previous source: VS-010_tickera-ticket-perks.md  
Primary correction: programme-aware ticket perks using the corrected VS-007 ledger and VS-008 Woo discount order.

---

## 0. v1.2.0 Programme and Level Flexibility Patch Summary

This version applies the Programme and Level Flexibility Pass.

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a closed forever-list.
2. Requires Tickera ticket perks to consume configured programme/level mappings through the MembershipProfile, PerkAudienceEvaluator, ClaimPolicy, and ProgrammeMappingRepository.
3. Bans hardcoded PMPro level IDs, PMPro group IDs, Woo membership product IDs, Woo variation IDs, current billing terms, and fixed Goud/Silwer/Brons assumptions in ticket execution paths.
4. Requires cart intents, order item metadata, and redemption rows to store canonical configured programme_key and level_key values, not aliases.
5. Adds programme_mapping_hash to ticket claim intent, cart metadata, order metadata, and redemption context where available.
6. Adds fail-closed behaviour for unmapped PMPro levels, unknown/disabled programme references, unknown level references, and unavailable programme mapping/config.
7. Adds future-level, future-programme, and alias-normalization tests.
```

This slice remains conservative about Tickera internals: inspect staging first, use verified Tickera/Woo Bridge APIs and hooks only, and do not bypass normal Tickera ticket generation.


## 1. Objective

Build the Tickera event/ticket-perk execution layer for the **Voelgoed Member Perks** plugin.

This vertical slice must allow eligible members to claim or receive discounted/free Tickera ticket perks while preserving the normal WooCommerce/Tickera ticket generation flow.

The core goal:

```text
Eligible member
↓
Active ticket/event perk
↓
MembershipProfile resolved from PMPro
↓
Perk audience and claim-scope validation
↓
Ticket/event target validated through vgmp target index
↓
WooCommerce cart/checkout used for ticket product
↓
Woo discount engine applies free/discounted ticket price
↓
User completes Woo checkout
↓
Tickera generates ticket normally
↓
Redemption ledger records the order/ticket/perk relationship
```

This slice must be conservative. It must **not** bypass Tickera’s normal ticket/order generation unless the actual staging integration proves a safe API exists and the implementation is explicitly approved.

---

## 2. Current Baseline Assumptions

This slice assumes the previous slices exist or have been implemented in the corrected order:

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
```

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected CPT:

```text
vg_perk
```

Expected target/audience table:

```text
{$wpdb->prefix}vgmp_perk_targets
```

Expected redemption table:

```text
{$wpdb->prefix}vgmp_perk_redemptions
```

Expected supported target types from VS-006:

```text
tickera_event
tickera_ticket
woo_product
```

Expected Woo discount system from VS-008:

```php
Voelgoed\MemberPerks\Woo\WooDiscountEngine
Voelgoed\MemberPerks\Woo\WooApplicablePerkResolver
Voelgoed\MemberPerks\Woo\WooOrderItemMeta
Voelgoed\MemberPerks\Woo\WooRedemptionRecorder
```

Expected claim ledger from VS-007:

```php
Voelgoed\MemberPerks\Claims\ClaimService
Voelgoed\MemberPerks\Claims\ClaimValidator
Voelgoed\MemberPerks\Claims\ReservationService
Voelgoed\MemberPerks\Claims\RedemptionRepository
Voelgoed\MemberPerks\Claims\ClaimPeriodResolver
```

Expected membership resolver from VS-003:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
Voelgoed\MemberPerks\Membership\MembershipProfile
Voelgoed\MemberPerks\Membership\EligibilityChecker
```

If any previous slice is missing or incomplete, stop and report exactly what is missing before implementing VS-010.

---

## 3. Critical Instruction to Novamira Agent

Before implementing this slice:

```text
Inspect the actual staging site.
Inspect the installed Tickera version.
Inspect whether Tickera WooCommerce Bridge is active.
Inspect whether tickets are represented as WooCommerce products.
Inspect event post types, ticket post types, product mappings, metadata, order item metadata, ticket generation hooks, download links, attendee fields, and check-in flow.
Inspect how current Tickera tickets are added to cart and generated.
Inspect whether Woo checkout is classic shortcode checkout or Woo Blocks checkout.
Do not guess Tickera internals.
Do not hardcode unverified Tickera table names, post types, or meta keys.
```

If no safe integration path is verified, stop and report the available options instead of forcing a fragile implementation.

---

## 4. Why This Slice Exists

Ticket perks are more dangerous than normal product discounts because a “free ticket” must still result in a valid ticket record that can be emailed, downloaded, scanned, and supported.

The system must answer:

```text
Is the user eligible for this ticket perk?
Which programme and level qualified the user?
Is the targeted ticket valid?
Does the event target have one or many ticket types?
Has the member already claimed this perk under the relevant claim scope?
Was a WooCommerce order created?
Did Tickera generate the actual ticket?
Which order/order item/ticket is linked to the redemption?
Can support see the claim trail?
Can scanning/check-in still work?
```

Do not solve ticket perks by merely showing a hidden link, bypassing checkout, or directly creating ticket records unless a verified safe Tickera API exists.

---

## 5. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Tickera detection
Tickera WooCommerce Bridge detection
Ticket/event/product target validation
Ticket perk claim-to-cart flow
Free/discounted ticket price handling through the existing Woo discount engine
Ticket redemption recording after successful Woo order creation
Ticket/order/ticket-reference relationship recording where safely detectable
Hub card ticket claim state
Admin warnings for invalid ticket perks
Programme-aware order/redemption context
Dual-programme no-stacking safety
```

This slice must not implement:

```text
Masteriyo course logic
New course access logic
Email notification campaigns
Advanced reports
Custom ticket PDF generation
Custom scanner/check-in logic
Replacing Tickera's normal ticket generation
Manual ticket creation unless Tickera exposes a verified safe API
Recurring subscription discount logic
Subscription renewal pricing changes
Seat selection logic
Ticket stock/inventory reservation at cart stage
Theme edits
functions.php edits
Existing WPCode snippet edits
```

---

## 6. Required Deliverables

Add the following plugin files:

```text
includes/
├── Tickera/
│   ├── TickeraIntegration.php
│   ├── TickeraDetector.php
│   ├── TicketPerkService.php
│   ├── TicketClaimController.php
│   ├── TicketTargetResolver.php
│   ├── TicketCartService.php
│   ├── TicketOrderRecorder.php
│   ├── TicketLookupRepository.php
│   ├── TicketAccessPolicy.php
│   ├── TicketClaimIntent.php
│   └── TicketClaimIntentRepository.php
│
├── Admin/
│   └── PerkTicketWarnings.php
│
└── Display/
    └── TicketPerkDisplayData.php
```

Update existing files:

```text
includes/Plugin.php
includes/Claims/ClaimValidator.php
includes/Claims/ClaimService.php
includes/Woo/WooApplicablePerkResolver.php
includes/Woo/WooDiscountEngine.php
includes/Woo/WooOrderItemMeta.php
includes/Woo/WooRedemptionRecorder.php
includes/Display/PerkCardData.php
includes/Query/RestQueryController.php
README.md
```

If existing file names/classes differ, preserve responsibilities and document the difference.

---

## 7. Supported Perk Types in VS-010

Supported `vg_perk_type` terms:

```text
event_discount
free_ticket
```

Supported target types:

```text
tickera_event
tickera_ticket
woo_product
```

Supported benefit types:

```text
free
percentage_discount
fixed_discount
fixed_member_price
display_only
```

Interpretation:

### `free`

```text
Eligible member may receive a R0 ticket through the normal Woo/Tickera flow.
```

### `percentage_discount`

```text
Eligible member receives a percentage discount on the targeted ticket product.
```

### `fixed_discount`

```text
Eligible member receives a fixed discount on the targeted ticket product.
```

### `fixed_member_price`

```text
Eligible member pays a specific member price for the targeted ticket product.
```

### `display_only`

```text
Show the ticket/event perk in the hub, but no claim/cart action executes.
```

---

## 8. Preferred Execution Model

Preferred default model:

```text
Claim button
↓
Validate user, MembershipProfile, perk, target, claim limits, and ticket quantity
↓
Add the correct ticket product to WooCommerce cart with server-side perk intent metadata
↓
Apply the perk price through VS-008 Woo discount engine
↓
User completes Woo checkout
↓
Tickera generates ticket normally
↓
VS-007 redemption ledger records the final order/order-item usage
↓
VS-010 optionally links generated Tickera ticket IDs if safely detectable
```

Why:

```text
WooCommerce keeps the order/payment trail.
Tickera keeps the ticket generation trail.
The redemption ledger keeps the perk/claim trail.
Support can trace all three.
```

Avoid:

```text
Directly inserting Tickera tickets into DB
Bypassing Woo checkout
Bypassing Tickera ticket generation
Giving ticket download links without orders
Bypassing attendee fields
Bypassing stock/seat validation
```

---

## 9. Programme and Dual-Membership Rules

VS-010 must use the **MembershipProfile** from VS-003.

Supported user states:

```text
no active mapped programme
one configured programme only
multiple configured programmes, including the launch default dual-programme case
same-programme conflict detected
unmapped/unknown PMPro level present but ignored for perks
```

Rules:

```text
1. A user may qualify through Vriendinneklub, Voelgoednetwerk, or both.
2. If both programmes qualify for the same ticket perk, the ticket benefit must not duplicate automatically.
3. Default: one best eligible benefit wins.
4. Do not stack Vriendinneklub and Netwerk discounts by default.
5. If stacking is ever enabled later, it must be explicit and tested separately.
6. Store the winning programme/level context on cart item meta, order item meta, and redemption rows.
7. If the same programme has conflicting levels, use VS-003 conflict handling and do not stack benefits.
```

Required derived context:

```text
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
```

Example:

```text
programme_key = vriendinneklub
level_key = goud
qualified_path = programme_level:vriendinneklub:goud
eligibility_hash = deterministic MembershipProfile hash
programme_mapping_hash = deterministic ProgrammeMappingRepository hash
claim_scope = per_user
```

## 9A. Programme and Level Flexibility Boundary

Ticket execution must be driven by resolved configuration, not hardcoded launch assumptions.

Correct model:

```text
ProgrammeMappingRepository
↓
MembershipProfileResolver
↓
PerkAudienceEvaluator
↓
ClaimPolicy / ClaimValidator
↓
TicketPerkService / Woo discount engine
↓
cart intent + order item metadata + redemption ledger
```

Hard bans in ticket execution paths:

```text
Do not hardcode PMPro level IDs.
Do not hardcode PMPro group IDs.
Do not use Woo membership product IDs as membership authority.
Do not use Woo variation IDs as membership authority.
Do not hardcode current billing terms such as monthly/yearly.
Do not assume only Goud/Silwer/Brons or Brons/Silwer/Goud exist.
Do not assume only Vriendinneklub and Voelgoednetwerk can ever exist.
```

Allowed launch-specific references:

```text
1. default seed configuration
2. README examples
3. admin labels
4. test fixtures
5. migration/alias helpers
```

Execution decisions must use:

```text
configured programme_key
configured level_key
MembershipProfile
ClaimPolicy
PerkAudienceEvaluator match context
ProgrammeMappingRepository mapping_hash
```

Fail-closed behaviour:

```text
If a PMPro level is active but unmapped:
    do not grant ticket perks from it.

If a programme reference cannot normalize to an enabled configured programme:
    do not allow the ticket claim.

If a level reference cannot normalize to a configured level inside that programme:
    do not allow the ticket claim.

If programme mapping/config cannot be loaded:
    do not add discounted/free ticket to cart.
    do not create final redemption.
    return dependency_missing or invalid_mapping.
    show/admin-log a safe diagnostic.
```

Storage rule:

```text
Ticket cart intents, order item meta, and redemption rows must store canonical configured programme_key and level_key values.
Aliases are input/migration conveniences only.
Aliases must not be stored as canonical runtime state.
```

---

## 10. Claim Scope Rules for Ticket Perks

Use `perk_claim_scope` from VS-002/VS-007.

Supported values:

```text
per_user
per_programme
per_programme_level
per_order
unlimited
```

Default for ticket perks:

```text
per_user
```

Recommended defaults:

```text
free_ticket          → per_user, max_claims_per_user = 1
percentage_discount  → per_user or per_order depending campaign rule
fixed_discount       → per_user or per_order depending campaign rule
fixed_member_price   → per_user or per_order depending campaign rule
display_only         → no claim needed unless configured
```

Important:

```text
A dual-programme user does not automatically receive two free tickets.
```

If the business wants one ticket per programme, set:

```text
claim_scope = per_programme
```

If the business wants one ticket per programme level, set:

```text
claim_scope = per_programme_level
```

---

## 11. Tickera Detection

Create:

```php
Voelgoed\MemberPerks\Tickera\TickeraDetector
```

Responsibilities:

```text
1. Detect whether Tickera is active.
2. Detect whether Tickera WooCommerce Bridge is active.
3. Detect Tickera event post type.
4. Detect Tickera ticket post type or ticket product model.
5. Detect whether tickets are WooCommerce products.
6. Detect how ticket products relate to events.
7. Detect how tickets are generated from Woo orders.
8. Detect safe hooks/meta for linking order items to generated tickets.
9. Detect attendee field requirements if exposed in a safe way.
10. Report missing/unsafe integration state.
```

If Tickera is inactive:

```text
Plugin still loads.
Ticket claim endpoint returns dependency_missing.
Admin warnings appear for ticket perks.
```

If WooCommerce Bridge is required but inactive:

```text
Ticket claim-to-cart flow must not run.
Admin warning must explain missing bridge.
```

Do not assume Tickera can operate through WooCommerce unless staging confirms the bridge setup.

---

## 12. Checkout Mode Detection

Because WooCommerce checkout can be classic shortcode checkout or Woo Blocks checkout, VS-010 must detect the actual cart/checkout mode.

Default MVP support:

```text
Classic shortcode cart/checkout supported.
Woo Blocks checkout detection-only unless a dedicated Blocks compatibility path exists.
```

If Woo Blocks checkout is detected and the Woo/Tickera cart/order hooks cannot be verified:

```text
Disable ticket claim-to-cart execution.
Show admin warning.
Return safe dependency/unsupported_checkout response to claim endpoint.
Do not silently run partial ticket perks.
```

---

## 13. Target Resolution

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketTargetResolver
```

Responsibilities:

```text
1. Validate submitted event/ticket/product target.
2. Confirm target is indexed against the perk in wp_vgmp_perk_targets.
3. Convert event target into eligible ticket products if safely possible.
4. Resolve ticket target to WooCommerce product/cart item target if using Woo Bridge.
5. Return safe target data for cart/service classes.
```

Target handling:

### `tickera_ticket`

```text
Most direct target.
Should resolve to a ticket object or Woo ticket product.
```

### `tickera_event`

```text
Less direct target.
May require selecting available ticket types for that event.
If multiple ticket types exist, do not guess blindly.
Require explicit ticket selection or admin configuration.
```

### `woo_product`

```text
Allowed when the actual ticket is represented as a WooCommerce product.
Useful if Tickera Woo Bridge maps tickets to Woo products.
```

Important rule:

```text
If a perk targets an event with multiple ticket types, the system must not arbitrarily choose one unless the admin has configured a default ticket.
```

---

## 14. Required ACF Additions or Usage

Use existing fields if already defined; otherwise add fields carefully through the ACF slice/update process.

Recommended fields for ticket perks:

```text
perk_ticket_claim_mode
perk_default_ticket_product
perk_ticket_quantity_limit
perk_ticket_require_checkout
perk_ticket_redirect_after_claim
perk_ticket_event_selection_mode
```

Supported `perk_ticket_claim_mode` values:

```text
add_to_cart
view_event
display_only
manual_admin
```

Recommended default:

```text
add_to_cart
```

Supported `perk_ticket_event_selection_mode` values:

```text
explicit_ticket_required
default_ticket
member_selects_ticket
```

Recommended default:

```text
explicit_ticket_required
```

Reason:

```text
Events often have multiple ticket types, prices, dates, stock rules, seat rules, or attendee requirements.
Guessing a ticket type is risky.
```

---

## 15. Ticket Claim Controller

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketClaimController
```

Endpoint:

```text
POST /wp-json/vg-member-perks/v1/ticket-claim
```

Request body examples:

### Direct ticket/product claim

```json
{
  "perk_id": 123,
  "target_type": "tickera_ticket",
  "target_id": 456,
  "quantity": 1
}
```

### Woo ticket product claim

```json
{
  "perk_id": 123,
  "target_type": "woo_product",
  "target_id": 789,
  "quantity": 1
}
```

Security:

```text
Logged-in users only.
Use nonce.
Use current user only.
Sanitize all fields.
Validate target relationship server-side.
Resolve MembershipProfile server-side.
Validate audience/eligibility through ClaimValidator.
Validate claim limits through ClaimValidator/RedemptionRepository.
Validate quantity.
Do not allow user_id in request.
Do not allow user-submitted programme_key, level_key, discount amount, or perk winner.
```

Response success:

```json
{
  "success": true,
  "code": "ticket_added_to_cart",
  "message": "Your member ticket benefit has been added to your cart.",
  "cart_url": "https://example.com/cart/",
  "checkout_url": "https://example.com/checkout/"
}
```

Response failure:

```json
{
  "success": false,
  "code": "not_eligible",
  "message": "This ticket perk is not available for your membership."
}
```

Recommended failure codes:

```text
not_logged_in
not_eligible
perk_not_found
perk_not_active
invalid_target
dependency_missing
unsupported_checkout
woo_bridge_missing
ticket_mapping_missing
requires_ticket_selection
quantity_not_allowed
claim_limit_reached
cart_add_failed
```

---

## 16. Ticket Claim Intent

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketClaimIntent
Voelgoed\MemberPerks\Tickera\TicketClaimIntentRepository
```

Purpose:

```text
Represent the server-validated intent to claim a ticket perk before checkout.
```

This is not the final redemption.

Recommended intent data:

```text
intent_key
user_id
perk_id
target_type
target_id
quantity
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
claim_period_key
created_at
expires_at
```

Default storage:

```text
Woo cart item metadata only.
```

Do not create a new database table for intents in VS-010 unless a clear need exists.

Important:

```text
Cart intent does not permanently consume claim limits.
The final redemption is recorded after Woo order creation/payment lifecycle.
```

---

## 17. Ticket Cart Service

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketCartService
```

Responsibilities:

```text
1. Add the correct ticket product to WooCommerce cart.
2. Attach server-side cart item metadata identifying the perk and validated intent.
3. Ensure WooDiscountEngine can apply the correct perk price.
4. Avoid duplicate cart additions if idempotency requires it.
5. Return cart/checkout redirect data.
```

Cart item metadata to add:

```text
_vgmp_ticket_perk_id
_vgmp_ticket_target_type
_vgmp_ticket_target_id
_vgmp_ticket_claim_source = ticket_claim
_vgmp_ticket_claim_intent_key
_vgmp_programme_key
_vgmp_level_key
_vgmp_qualified_path
_vgmp_eligibility_hash
_vgmp_programme_mapping_hash
_vgmp_claim_scope
_vgmp_claim_period_key
```

Cart metadata must be generated server-side from validated configuration and MembershipProfile context. It must never be trusted from frontend input.

Important:

```text
Cart metadata is not a redemption record.
The actual redemption is recorded after checkout/order creation.
```

Do not consume final claim limits at add-to-cart stage unless reservation behaviour is explicitly implemented and tested.

For VS-010 default:

```text
Add-to-cart does not create final redemption.
Checkout/order creates redemption.
```

---

## 18. Quantity Rules

Default:

```text
quantity = 1
```

Validate quantity:

```text
Minimum: 1
Maximum: perk_ticket_quantity_limit if configured
Fallback maximum: 1
```

Reason:

```text
Most membership ticket perks should be one ticket per eligible member unless explicitly configured.
```

Do not allow arbitrary high quantities from frontend requests.

If quantity > 1 is allowed:

```text
Confirm claim-scope and max-claims rules are clear.
Confirm Woo/Tickera stock allows the quantity.
Confirm order item meta records the claimed quantity.
```

---

## 19. Claim Limits and Cart Behaviour

Claim limits must be checked before adding to cart.

However:

```text
Adding to cart should not permanently consume claim limits.
```

The actual successful claim is recorded after Woo checkout/order creation.

Known risk:

```text
A user could add a limited perk to cart and abandon checkout.
```

VS-010 default:

```text
Do not reserve claim inventory at cart stage.
Use checkout/order creation and order status lifecycle as the source of final redemption.
```

If strict total inventory reservation is required, that belongs to a future reservation-hardening slice.

If VS-007 ReservationService is used in VS-010:

```text
Use short-lived reservation only.
Do not leave abandoned cart reservations active indefinitely.
Expire reservations automatically.
Document exact timeout and release rules.
```

---

## 20. Woo Discount Integration

VS-010 must reuse the Woo discount engine from VS-008.

Required updates:

```text
WooApplicablePerkResolver must recognise ticket-claim cart item metadata.
WooApplicablePerkResolver must validate the intended perk server-side again.
WooDiscountEngine must apply the intended perk to the intended ticket product if validation passes.
WooDiscountEngine must not apply an arbitrary user-submitted perk ID.
WooOrderItemMeta must store ticket perk metadata on order items.
WooRedemptionRecorder / TicketOrderRecorder must record ticket redemptions with source = ticket_claim or woo_order.
```

Do not create a separate duplicate discount engine for Tickera.

---

## 21. Order and Ticket Recording

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketOrderRecorder
```

Purpose:

```text
After Woo checkout, link redemption records to order/order item and, if safely detectable, generated Tickera ticket IDs.
```

Potential hooks to inspect on staging:

```text
WooCommerce order creation hooks
WooCommerce order status hooks
Tickera ticket generation hooks
Tickera Woo Bridge hooks
Order item meta hooks
```

The Novamira agent must inspect and report actual hooks used.

Required minimum redemption row context:

```text
user_id
perk_id
target_type = tickera_ticket / tickera_event / woo_product
target_id
order_id
order_item_id
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
benefit_type
discount_amount
final_member_price
source = ticket_claim
status = claimed or completed
claim_period_key
idempotency_key
```

If generated Tickera ticket ID can be safely detected:

```text
Store it as structured metadata if VS-007 redemption schema supports metadata.
Otherwise store compact reference in notes.
Do not silently change the redemption table schema without a documented migration.
```

If ticket ID tracking needs a new column:

```text
Propose a schema patch clearly before implementation.
```

---

## 22. Ticket Lookup Repository

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketLookupRepository
```

Responsibilities:

```text
1. Find ticket products for a Tickera event if safely possible.
2. Resolve a Tickera ticket to a Woo product if Woo Bridge is active.
3. Find generated tickets for an order/order item if safely possible.
4. Avoid hardcoding unverified meta keys.
```

If mapping cannot be verified:

```text
Return structured failure and report the limitation.
```

---

## 23. Ticket Perk Service

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketPerkService
```

Responsibilities:

```text
1. Coordinate the ticket claim request.
2. Resolve MembershipProfile.
3. Validate eligibility and target through ClaimValidator and TicketTargetResolver.
4. Check claim limits.
5. Resolve ticket/product mapping.
6. Add ticket product to cart through TicketCartService.
7. Return safe result to frontend.
```

This service should not generate tickets directly.

---

## 24. Ticket Access Policy

Create:

```php
Voelgoed\MemberPerks\Tickera\TicketAccessPolicy
```

Purpose:

```text
Centralise ticket-specific rules.
```

Rules:

```text
1. Free/discounted tickets must go through Woo/Tickera flow unless a verified safe API exists.
2. Event target with multiple tickets requires explicit ticket selection/default.
3. Quantity defaults to 1.
4. Claim limit checked before add-to-cart.
5. Final redemption recorded after checkout/order creation.
6. Cancelled/refunded/failed orders should update redemption status where possible.
7. No subscription renewal pricing changes.
8. No direct ticket DB insertion.
9. No scanner/check-in changes.
```

---

## 25. Hub/Card Display Updates

Create:

```php
Voelgoed\MemberPerks\Display\TicketPerkDisplayData
```

Add ticket-specific card state:

```php
[
    "ticket_target_type"     => "tickera_ticket",
    "ticket_target_id"       => 456,
    "ticket_claim_available" => true,
    "ticket_claim_status"    => "available",
    "ticket_quantity_limit"  => 1,
    "cta_label"              => "Claim ticket",
    "cta_behaviour"          => "ticket_claim",
]
```

Possible statuses:

```text
available
already_claimed
limit_reached
not_eligible
dependency_missing
unsupported_checkout
requires_ticket_selection
display_only
```

CTA behaviour:

### Available direct ticket claim

```text
CTA: Claim ticket
POST to ticket-claim endpoint
Then redirect to cart or checkout.
```

### Requires ticket selection

```text
CTA: Choose ticket
Link to event/ticket selection page.
```

### Already claimed

```text
CTA: View order / Already claimed
If order link is available, show account order link.
```

### Display only

```text
CTA: View event
```

---

## 26. Admin Warnings

Create:

```php
Voelgoed\MemberPerks\Admin\PerkTicketWarnings
```

Warnings:

```text
Ticket perk has no ticket/event/product target.
Ticket perk targets event with multiple tickets but no default ticket configured.
Tickera inactive.
Tickera WooCommerce Bridge inactive or not verified.
WooCommerce inactive.
Checkout mode unsupported or unverified.
Target ticket cannot be mapped to Woo product.
Free ticket perk has no claim limit configured.
Ticket quantity limit is higher than recommended.
Ticket claim mode is add_to_cart but WooCommerce is inactive.
Ticket perk uses unsupported benefit type.
Same ticket perk may qualify through both programmes; default no-stacking will choose one.
```

Do not block saving unless configuration is dangerously invalid.

---

## 27. Existing Tickera Flow Protection

Do not break existing event/ticket sales.

Rules:

```text
1. Do not modify Tickera core files.
2. Do not modify WooCommerce ticket product prices globally.
3. Do not remove existing ticket meta.
4. Do not change scanner/check-in behaviour.
5. Do not alter ticket PDFs.
6. Do not bypass existing required attendee fields.
7. Do not bypass seat/stock restrictions.
8. Do not bypass existing Tickera validation.
9. Do not bypass WooCommerce order status/payment lifecycle.
```

If Tickera requires attendee info at checkout:

```text
Do not skip it.
The member perk should still pass through the normal form/checkout requirements.
```

---

## 28. Ticket Stock and Seat Rules

Default:

```text
Respect WooCommerce/Tickera stock and availability.
```

Do not force-add unavailable tickets to cart.

If stock or seating APIs are not clear:

```text
Let Woo/Tickera reject invalid cart additions.
Return a friendly failure message.
```

Do not build seat selection logic in VS-010.

---

## 29. Redemption Ledger Integration

Ticket claims must use:

```text
wp_vgmp_perk_redemptions
```

Required values:

```text
user_id
perk_id
target_type = tickera_ticket / tickera_event / woo_product
target_id
order_id
order_item_id
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
benefit_type
discount_amount
final_member_price
status
claim_period_key
source = ticket_claim
idempotency_key
claimed_at
updated_at
```

Recommended idempotency key after order item exists:

```text
ticket_order_item:{order_item_id}:perk:{perk_id}
```

For cart intent:

```text
ticket_cart_intent:user:{user_id}:perk:{perk_id}:target:{target_type}:{target_id}:period:{claim_period_key}:scope:{claim_scope}
```

Do not rely only on cart intent for final claim tracking.

---

## 30. Order Status Changes

Update redemptions on Woo order status changes where possible.

Recommended:

```text
Order cancelled → redemption cancelled
Order refunded → redemption refunded
Order failed → redemption failed
```

Do not delete redemption rows.

If partial refunds are complex:

```text
Document limitation.
```

Cancelled/refunded/failed records should not count as successful redemptions unless a future business rule explicitly says otherwise.

---

## 31. Coupon Compatibility

Ticket perks must follow the existing Woo discount rules from VS-008.

Default:

```text
Coupons do not combine with member ticket perks unless perk_coupon_compatible = true.
```

Do not create special undocumented coupon behaviour for tickets.

---

## 32. Subscription Products

Ticket perks should generally not apply to subscription products unless explicitly targeted and verified.

Default:

```text
Ticket perks apply to normal frontend cart/checkout ticket products only.
Do not apply to subscription renewals.
Do not change existing subscription recurring totals.
```

Do not change Woo Subscriptions renewal pricing in this slice.

---

## 33. Logging

Log:

```text
Ticket claim requested
Ticket claim denied
Ticket target resolved
Ticket added to cart
Ticket add-to-cart failed
Tickera inactive/missing
Woo Bridge inactive/missing
Unsupported checkout mode detected
Ticket order redemption recorded
Generated ticket reference detected if available
Order status changed redemption
Duplicate idempotent request ignored
Dual-programme winner selected
Same-programme conflict encountered
```

Do not log sensitive personal data beyond operational IDs.

---

## 34. Performance Requirements

Rules:

```text
1. Use target index table for target validation.
2. Do not scan all perks for each ticket.
3. Do not call PMPro repeatedly per card.
4. Do not call heavy Tickera lookups in large loops without caching.
5. Keep endpoint responses small.
6. Avoid expensive ticket mapping unless claim action is performed.
7. Cache ticket mapping per request where safe.
```

Display card state may be conservative if resolving ticket mapping is expensive.

---

## 35. Security Requirements

All ticket claim actions must:

```text
Require logged-in user.
Use nonce.
Use current user ID only.
Sanitize perk_id, target_type, target_id, and quantity.
Validate target relationship server-side.
Validate MembershipProfile server-side.
Validate eligibility server-side.
Validate claim limits server-side.
Never trust frontend price/discount.
Never trust frontend user_id.
Never trust frontend membership group/programme/level.
Never allow direct arbitrary product add with perk discount.
```

Users must not be able to submit their own:

```text
user_id
discount amount
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
perk winner
redemption status
```

---

## 36. Compatibility Requirements

This slice must not fatal if these are inactive:

```text
WooCommerce
Woo Subscriptions
Paid Memberships Pro
Masteriyo
Tickera
Tickera WooCommerce Bridge
ACF Pro
Bricks
Etch
```

If Tickera is inactive:

```text
Plugin still loads.
Ticket claim endpoint returns dependency_missing.
Admin warnings appear.
```

If WooCommerce is inactive:

```text
Ticket claim-to-cart is unavailable.
No fatal.
```

If Woo Bridge is inactive and required:

```text
Ticket claim-to-cart is unavailable.
Admin warning explains issue.
```

If PMPro/resolver is unavailable:

```text
Claim validation denies safely.
```

---

## 37. Testing Plan

Test on staging only.

### Test 1 — Tickera detection

```text
Confirm Tickera active/inactive detection.
Confirm Woo Bridge detection if used.
Expected: no fatal if inactive.
```

### Test 2 — Checkout mode detection

```text
Inspect cart/checkout pages.
Expected: classic shortcode or Woo Blocks mode documented.
Expected: if Woo Blocks are used and unverified, execution is disabled safely.
```

### Test 3 — Target mapping

```text
Create active ticket perk targeting a known ticket.
Expected: TicketTargetResolver validates target.
Expected: mapped Woo product detected if Woo Bridge is active.
```

### Test 4 — Event with multiple ticket types

```text
Create perk targeting event with multiple tickets and no default.
Expected: claim does not guess.
Expected: returns requires_ticket_selection or admin warning.
```

### Test 5 — Eligible ticket claim

```text
Create active perk:
type = free_ticket
target = ticket product
benefit = free
eligible programme/level = vriendinneklub:goud
claim mode = add_to_cart
claim_scope = per_user

Log in as Goud Vriendinneklub member.
Click claim.
Expected: ticket product added to cart.
Expected: perk metadata added to cart item.
Expected: final price becomes R0 through Woo discount engine.
Expected: programme_key and level_key metadata are server-derived.
```

### Test 6 — Dual-programme member

```text
User has:
- Vriendinneklub Goud
- Voelgoednetwerk Silwer

Create one ticket perk that both programmes qualify for.
Expected: ticket benefit is not duplicated.
Expected: deterministic winning programme/level is stored.
Expected: no automatic two-ticket benefit unless claim_scope/config explicitly allows it.
```

### Test 7 — Discounted ticket

```text
Create active perk:
benefit = percentage_discount
value = 20

Expected: ticket product receives 20% discount in cart for eligible member.
```

### Test 8 — Ineligible user

```text
Log in as Brons member for Goud-only ticket perk.
Try claim.
Expected: not_eligible.
Expected: ticket not added to cart with perk metadata.
```

### Test 9 — Expired perk

```text
Set runtime status = expired.
Try claim.
Expected: perk_not_active.
Expected: no cart perk metadata.
```

### Test 10 — Quantity limit

```text
Set quantity limit = 1.
Submit quantity = 2.
Expected: blocked or reduced according to documented rule.
```

### Test 11 — Checkout and ticket generation

```text
Complete checkout for free/discounted ticket.
Expected: Woo order created.
Expected: Tickera ticket generated normally.
Expected: redemption row created with order_id/order_item_id.
Expected: generated ticket reference stored if safely detectable.
```

### Test 12 — Claim limit after purchase

```text
Set max_claims_per_user = 1.
User completes first ticket order.
User tries to claim same perk again.
Expected: limit_reached or no discount/add-to-cart.
```

### Test 13 — Cart abandonment

```text
Add perk ticket to cart but do not checkout.
Expected: no final redemption row unless short-lived reservation is explicitly implemented.
Expected: claim is not permanently consumed.
```

### Test 14 — Coupon compatibility

```text
Apply coupon with non-compatible ticket perk.
Expected: member perk follows VS-008 coupon compatibility rules.
```

### Test 15 — Order cancellation/refund

```text
Cancel/refund order if safe.
Expected: redemption status updates to cancelled/refunded if implemented.
```

### Test 16 — Existing normal ticket sales

```text
Buy a normal ticket without member perk.
Expected: standard Tickera flow still works.
```

### Test 17 — Admin warnings

```text
Create misconfigured ticket perk.
Expected: clear admin warnings.
```

### Test 18 — Unmapped PMPro level fail-closed

```text
Create or use a PMPro level that is active for the user but not mapped in ProgrammeMappingRepository.
Create a ticket perk that would otherwise look related by name/label.
Expected: no ticket perk is granted.
Expected: no discounted/free cart item is added.
Expected: admin/debug output reports unmapped_pmpro_level or invalid_mapping.
```

### Test 19 — Future mapped level

```text
Add a temporary configured level under an enabled programme.
Map it to a PMPro access-only level.
Create a ticket perk for that configured level.
Expected: eligible user can claim the ticket perk without ticket execution code changes.
```

### Test 20 — Future configured programme

```text
Add a temporary enabled programme with one mapped level.
Create a display-safe ticket perk for that programme.
Expected: ticket flow uses configured programme_key and level_key.
Expected: no hardcoded Vriendinneklub/Netwerk-only branch is required.
```

### Test 21 — Programme alias normalization

```text
Add an explicit alias such as old_programme_key for the configured launch programme vriendinneklub.
Create or migrate a ticket perk audience reference using the alias.
Expected: alias normalizes to canonical configured programme_key before cart intent/order/redemption storage.
Expected: alias itself is not stored as canonical runtime state.
```

### Test 22 — Programme mapping unavailable

```text
Simulate ProgrammeMappingRepository unavailable or invalid.
Try a ticket claim.
Expected: claim fails closed with dependency_missing or invalid_mapping.
Expected: no discounted/free ticket is added to cart.
Expected: no final redemption row is created.
```

---

## 38. Acceptance Criteria

VS-010 is complete only when:

```text
1. Tickera and Woo Bridge detection is safe and documented.
2. Checkout mode is detected and unsupported modes fail closed.
3. Ticket/event/product targets are validated through target index.
4. Eligible users can claim direct ticket/product perks into Woo cart.
5. Free/discounted ticket pricing uses existing Woo discount engine.
6. MembershipProfile eligibility is used; no direct PMPro checks are repeated per card/cart item.
7. Dual-programme users do not receive duplicate benefits by accident.
8. Programme/level/eligibility context is stored server-side.
9. Ineligible, logged-out, expired, and invalid-target claims are blocked.
10. Event target with multiple tickets is not guessed blindly.
11. Quantity limits are enforced.
12. Checkout creates normal Woo order.
13. Tickera ticket generation remains intact.
14. Redemption ledger records ticket perk order usage.
15. Claim limits are respected after successful checkout.
16. Cart abandonment does not permanently consume claims by default.
17. Admin warnings flag unsafe ticket perk configuration.
18. Existing non-perk Tickera sales still work.
19. No Masteriyo logic is touched.
20. Plugin does not fatal if dependencies are inactive.
21. README documents integration strategy, detected hooks/APIs, limitations, and tests.
22. Ticket execution does not hardcode PMPro level IDs, PMPro group IDs, Woo membership product IDs, Woo variation IDs, current billing terms, or fixed level names.
23. Ticket execution stores canonical configured programme_key and level_key values, not aliases.
24. Programme mapping hash is carried into cart/order/redemption context where available.
25. Future mapped levels can qualify for ticket perks without code changes.
26. Unknown/unmapped programme or level references fail closed.
```

---

## 39. README Update Requirements

Update README with:

```text
VS-010 implemented features
Detected Tickera version/details
Detected Woo Bridge status
Detected checkout mode
Detected event/ticket/product mapping strategy
Supported ticket perk types
Supported target types
Programme/dual-membership behaviour
Programme mapping and future-level behaviour
Ticket claim-to-cart flow
How Woo discount engine applies ticket perk pricing
How redemptions are recorded
Quantity rules
Event-with-multiple-tickets rule
Coupon compatibility rule
Cart abandonment rule
Known limitations
Testing notes
Next slice: VS-011 — Reports and Dashboards
```

Include warnings:

```text
VS-010 does not bypass Tickera ticket generation.
VS-010 does not implement custom ticket PDFs.
VS-010 does not implement scanner/check-in logic.
VS-010 does not reserve claim inventory at cart stage by default.
VS-010 does not modify subscription renewal pricing.
VS-010 does not automatically give a dual-programme member two ticket benefits unless claim_scope/config explicitly says so.
```

---

## 40. Explicit Out-of-Scope List

Do not implement these in VS-010:

```text
Masteriyo course enrolment
Course access revocation
Custom Tickera ticket PDF generation
Scanner/check-in changes
Seat selection logic
Ticket inventory reservation at cart stage by default
Email notification campaigns
Advanced analytics dashboard
CSV exports
Recurring subscription renewal benefit policy
Hardcoded future programme-specific branches
Hardcoded PMPro level/Product/variation eligibility rules
Persistent Redis/object cache layer
Cloudflare/LiteSpeed cache rules
```

These belong to later slices.

---

## 41. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Tickera version detected
Woo Bridge status detected
Checkout mode detected
Event/ticket/product post types detected
Ticket mapping strategy used
Hooks/APIs used
Ticket claim endpoint created
Woo discount integration details
Redemption integration details
Programme/dual-membership behaviour implemented
ProgrammeMappingRepository / mapping_hash behaviour implemented
Admin warnings added
Manual tests performed
Known limitations
Any assumptions made
Any dependency issues
```

If a safe Tickera/Woo Bridge mapping cannot be verified, state that clearly and do not fake the integration.

Do not claim ticket generation support unless an actual test order generated a valid Tickera ticket on staging.

Do not claim scanner/check-in changes; they are out of scope.

---

## 42. Next Slice

After VS-010 is complete and tested, proceed to:

```text
VS-011 — Reports and Dashboards
```

VS-011 will build management reporting over perks, claims, usage, active/upcoming/expired/misconfigured status, programme performance, dual-membership usage, and benefit performance.

Do not start VS-011 during this slice.
