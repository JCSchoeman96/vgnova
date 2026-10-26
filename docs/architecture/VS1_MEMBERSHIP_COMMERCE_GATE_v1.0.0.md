# VS1 Membership Commerce Gate v1.0.0

**Audit date:** 2026-10-26
**Repository baseline:** `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`
**Local runtime:** `http://localhost:10039/`; ABSPATH `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`; database `local` on `localhost`
**Scope:** Read-only audit of Vriendinne billing, PMPro membership state, Member Perks, Course commerce, and state refresh behavior.

## Gate result

```text
BILLING_AUTHORITY=WOO_SUBSCRIPTIONS
MEMBERSHIP_AUTHORITY=PMPRO
COURSE_MEMBER_PRICE_AUTHORITY=WOOCOMMERCE_BASE_PRICE_PLUS_MEMBER_PERKS_BENEFIT
COURSE_MEMBER_PRICE_GATE=CONFIGURATION_REQUIRED
MEMBERSHIP_COMMERCE_GATE=BLOCKED
MEMBERSHIP_SOURCE_RUNTIME_CONFLICT=NO
RUNTIME_PROOF_REQUIRES_MUTATION=YES
```

The approved product and technical spec v0.2.0 §11 assigns Course/access to Masteriyo, Woo Product/base price/cart/checkout/order to WooCommerce, active membership state to PMPro, and member benefits/discounts to Voelgoed Member Perks. Masteriyo has a native Course↔Woo Product mapping. The pinned Member Perks source resolves active PMPro tiers, evaluates matching Woo Product perks, selects one deterministically, and calculates the cart price from Woo's base price. This establishes the Course member-price authority chain. Current Courses are not linked to Woo Products, no approved tier-specific Course benefit is configured, and `woo_discounts` is false. No test order, membership, enrolment, product, perk, option, or flag was created or changed.

## Evidence authority and freshness

The source architecture reference is `docs/development/membership-billing-architecture.md`, last updated 2026-06-09. Treat its implementation details as historical until checked against the current Local runtime. It assigns Woo Subscriptions as billing authority and PMPro as access authority, describes the Vriendinne product parents and variations, and records disabled direct PMPro signups.

The Member Perks code authority is the `voelgoed-member-perks/` submodule, pinned at `972a279be7a753594888e974132562e1cc099e24`. Repository `AGENTS.md` identifies that submodule as the plugin code authority. The current runtime reports Voelgoed Member Perks `0.18.0`, WooCommerce Subscriptions `8.8.1`, PMPro `3.8.6`, and the PMPro WooCommerce add-on `1.10.1` active. The add-on implementation is runtime-installed plugin source, not source-controlled in this repository.

Runtime values below were re-read through the selected VGNova Novamira connection on 2026-10-26. The PMPro member rows are Local staging rows. Their originating checkout, order, and owner authorization are unknown; they are not purchase-chain proof or representative production data.

## Current Vriendinne billing products

Current Woo currency is `ZAR`. The PMPro WooCommerce add-on is active. For each Vriendinne parent, the runtime has `_membership_product_level` post meta and a matching entry in the add-on option `_pmprowoo_product_levels`; `_membership_product_autocomplete` is `0`. The June architecture document describes the option map alongside the parent product fields. The installed add-on source confirms that `_pmprowoo_product_levels` is a global WordPress option, while `_membership_product_level` is product meta. Their values match for all three Vriendinne parents, so the storage description is clarified without a mapping-value conflict.

| Tier | Woo parent | Parent status | PMPro level | Monthly variation | Yearly variation | Current recurring prices |
|---|---:|---|---:|---:|---:|---|
| Brons | 905 | draft | 15 | 906, published | 907, published | ZAR 39 regular / 24 sale monthly; 390 / 240 yearly |
| Silwer | 908 | draft | 16 | 909, published | 910, published | ZAR 59 regular / 39 sale monthly; 590 / 390 yearly |
| Goud | 911 | draft | 17 | 912, published | 913, published | ZAR 99 regular / 79 sale monthly; 990 / 790 yearly |

Each monthly variation uses `_subscription_period=month`, interval `1`; each yearly variation uses `_subscription_period=year`, interval `1`. All six have subscription length, sign-up fee, and trial length `0`. Variation statuses are published, but each variable-subscription parent remains draft. No matching Woo Subscription order items were returned for these three parent products and six variations. This is not proof that a public purchase path works.

The configured Vriendinne mapping is Brons → PMPro 15, Silwer → 16, and Goud → 17. The add-on's global option values and each product meta value agree. Its code loads `_pmprowoo_product_levels` with `get_option()`, maintains `_membership_product_level` as product meta, and adds the PMPro level on `woocommerce_order_status_completed`; subscription activation also has a `woocommerce_subscription_status_on-hold_to_active` handler. Failed, refunded, and cancelled order handlers remove the associated level subject to the add-on's active-subscription check. The product metadata does not set the add-on's autocomplete checkbox, so this audit does not infer completion from checkout.

## Current PMPro tier state

Levels 15–21 are configured with `allow_signups=0`, `initial_payment=0`, and `billing_amount=0`. For Vriendinne, Local PMPro membership rows include two `active` rows at each of levels 15, 16, and 17. The add-on query found no matching Subscription order items for products 905–913. There is no user-level order-to-membership lineage in this read-only audit. The active PMPro rows therefore prove only that staging membership state exists; they do not prove a successful Woo billing transition.

The current configuration supports one PMPro level for each tier; monthly and yearly billing use variations under the same parent/tier mapping. This agrees with the 2026-06-09 architecture document. Direct PMPro signup is disabled.

## Member Perks eligibility and execution

`SettingsRepository::feature_flags()` reads the current ACF option values through `AcfConfigProvider`. Fresh runtime values are:

| Flag | Current value |
|---|---:|
| `runtime_status_cron` | `true` |
| `hub_display` | `true` |
| `woo_discounts` | `false` |
| `allow_initial_subscription_product_discounts` | `false` |
| `course_claims` | `false` |
| `ticket_claims` | `false` |
| `woo_blocks_compat` | `false` |
| `notifications` | `false` |

The live programme mapping has enabled `vriendinneklub` and `voelgoednetwerk` programmes. Its Vriendinne level map is `brons` → 15, `silwer` → 16, and `goud` → 17. `MembershipProfileResolver::get_user_membership_profile()` reads active PMPro levels using `pmpro_getMembershipLevelsForUser()` when available and resolves them through `ProgrammeMappingRepository`. `PerkAudienceEvaluator::evaluate()` supports all-member and configured programme/level rules. `EligibilityChecker` supports `any`, `specific`, and `minimum` level selection. These are the current Member Perks eligibility inputs; Woo orders and products are not used to infer membership.

The current Local `vg_perk` inventory contains ten published Course access perks. They target the five published Masteriyo Courses (runtime IDs 532, 534, 536, 538, and 540), with repeated targets across `claim_and_enrol`, `display_only`, and `manual_admin` modes. All use `benefit_type=free`; nine have `audience_mode=all_members`, and one has a Vriendinne `any` level rule. The `hub_display` flag is on, but this audit did not evaluate any member account or confirm a specific card's display. `course_claims` is off, so these records do not prove that a claim can grant access. Their data provenance and owner authorization are unknown.

The runtime also has two published Product perks, one free claim-button benefit and one percentage discount with auto-apply, plus four draft Product percentage perks. The four draft entries contain Vriendinne Brons/Silwer and Voelgoednetwerk Platinum audience rules. The `woo_discounts` flag is off, so none is evidence of an executable current Course price. There is no current Event/Ticket-target Member Perks record in this inventory, and `ticket_claims` is off.

The source `TicketPerkService::claim_ticket()` fails closed when `ticket_claims` is off. When enabled, it validates Tickera Event/Ticket or explicitly linked Woo Product targets and adds the resolved ticket product to the Woo cart. This is a ticket claim path, not Course pricing.

### Product discounts

At the pinned Member Perks revision `972a279be7a753594888e974132562e1cc099e24`, `WooApplicablePerkResolver::resolve_for_cart_item()` matches Woo Product and product-category targets, reads the user's active PMPro profile, and compiles audience-matched rules. `PerkAudienceEvaluator::evaluate()` returns matched programme and level keys. `WooBestPerkSelector::select()` ranks by lower numeric priority, higher discount amount, lower target-specificity rank, then lower perk ID. Competing rules therefore resolve deterministically. `WooPriceCalculator::get_authoritative_base_price()` reads the Woo Product regular or sale price, then calculates free, percentage, fixed, or fixed-member-price results. `WooDiscountEngine::apply_discounts()` applies the selected result to the cart Product during `woocommerce_before_calculate_totals`. This source path can calculate a member cart price for a linked Course Woo Product. The `woo_discounts` flag is false, so `WooContextGuard` prevents execution. `allow_initial_subscription_product_discounts` is also false, and `WooSubscriptionPolicy` fails closed for subscription products unless that flag is enabled.

The active PMPro WooCommerce add-on also contains its own per-level Woo price filters and accepts global level discounts or per-product `_level_{id}_price` values. For Vriendinne levels 15–17, the current global discount options are absent, and the runtime has no `_level_15_price`, `_level_16_price`, or `_level_17_price` overrides on the current Woo product inventory. Its `pmpro_pmprowoo_discounts_on_subscriptions` option is absent, which the add-on treats as off. This is a separate source capability, but no current Vriendinne discount value was found there. A later implementation plan must preserve the approved spec's Member Perks benefit authority and avoid configuring two price owners.

### Course claims are not Course pricing

`CoursePerkService::claim_course()` implements a separate claim-and-enrol path. It fails closed when `course_claims` is false, validates a `masteriyo_course` target, and delegates access grant to the Course access service. Source supports `claim_and_enrol`, `display_only`, and `manual_admin` modes. This path grants or displays Course access; it does not set a Woo cart price. All five published Masteriyo Courses currently have `wc_integration.linked_product_id=0` and `product_create=false`. Masteriyo's native Course↔Woo mapping is supported in source but absent from current runtime data.

Member Perks' Woo discount resolver handles `woo_product` and `woo_product_category` targets. When Masteriyo's native mapping links a Course to its Woo Product, the Woo cart item provides the target used by this resolver. Woo retains the base-price authority; Member Perks applies the eligible tier benefit. The approved spec therefore does not need a new price owner or a second pricing implementation.

```text
COURSE_MEMBER_PRICE_AUTHORITY=WOOCOMMERCE_BASE_PRICE_PLUS_MEMBER_PERKS_BENEFIT
COURSE_MEMBER_PRICE_GATE=CONFIGURATION_REQUIRED
COURSE_MEMBER_PRICE_PRESENTATION=NOT_PROVEN
```

The authority chain is source-proven, not configured or runtime-proven for a Course. The current Local Courses have no linked Woo Product; no owner-approved Brons/Silwer/Goud Course benefits, Product targets, audiences, or priorities are proven; `woo_discounts` is false; and no member Course price was evaluated. Do not infer a current Course price from the existence of the supported chain or from Course access perks.

Future authorized work must choose the Woo Product for the representative Course, decide Brons/Silwer/Goud benefit values and perk targets/audiences/priorities, authorize controlled enabling of `woo_discounts`, define the exact non-member and member price presentation, and verify the configured result through cart and checkout. These are configuration, product, presentation, and runtime proof tasks, not an unresolved price-authority decision.

## Presentation and state refresh

Existing code paths:

- Member Perks resolves active membership profiles and programme/level eligibility server-side.
- Member Perks adjusts eligible Woo cart items at cart-total calculation time when its discount gate and configuration allow it.
- `WooPriceDisplay::append_member_hint()` can append the generic text "Member benefit may apply in cart." It does not resolve or display an exact tier price, savings amount, or Course price. The current feature flag is off, so exact non-member/member presentation remains unproven.
- Woo Blocks cart data has `member_price` and `discount_amount` fields, but the Woo Blocks compatibility flag is off.
- Member Perks' Bricks integration supplies perk-hub query/action data; no Course-specific tier-price or savings dynamic tag was found.

`MembershipProfileResolver` caches a resolved profile in a request-local array keyed by user ID and programme mapping hash. `clear_request_cache()` exists, but the plugin source contains no call site that clears it after PMPro membership changes. A later request resolves current PMPro state again. The Woo discount engine recalculates totals through `woocommerce_before_calculate_totals` and recalculates after coupons are applied or removed, but no actual checkout transition was exercised. My Voelgoed's authenticated route/cache behavior is recorded in `VS1_CURRENT_REALITY_AUDIT_v1.0.0.md`; shared cache behavior for public Course and Product pages and membership-state invalidation there remain unproven.

Later implementation planning must define when membership status is re-read after Woo payment, PMPro activation, login/logout, tier change, cart recalculation, and return to My Voelgoed. It must also prevent a shared/public cache from serving a member-specific price to another visitor. No cache setting was changed.

## Chain adjudication

| Required transition | Authority and exact evidence | Evidence class | Gate |
|---|---|---|---|
| Woo Subscription → successful billing state | Woo Subscriptions parent products 905/908/911 are draft; six published variations define monthly/yearly recurring amounts. No matching subscription order items were returned. A real successful order was not created. | Runtime proven for product configuration; order path not proven | `GAP` |
| Successful billing state → PMPro active tier | Active PMPro Woo add-on maps each parent to the corresponding level, grants on completed order, and has a subscription activation hook. PMPro rows exist, but no Woo order lineage was proven. | Source and runtime configuration proven; end-to-end transition not proven | `GAP` |
| PMPro active tier → Member Perks eligibility | Current PMPro level IDs map to enabled programme/level keys; the resolver reads PMPro active levels; the audience evaluator applies programme/level rules. Ten published Course access perks and two published Product perks exist in Local staging, but no member account was evaluated. | Source plus runtime configuration proven | `PASS` for eligibility mapping only |
| Member Perks eligibility → Course Woo member price | Masteriyo's native Course↔Woo mapping, Woo's base-price authority, PMPro active-tier resolution, Member Perks audience matching, deterministic best-perk selection, and Woo cart-price calculation form a supported chain. | Source chain proven at pinned Member Perks commit `972a279be7a753594888e974132562e1cc099e24` and current Masteriyo integration source. Current Local has no Course Product link, no approved Course perk values/configuration, and `woo_discounts=false`; exact page-price presentation and end-to-end cart/checkout are not proven. | `CONFIGURATION_REQUIRED`; runtime proof not complete |

```text
WOO_SUBSCRIPTION_TO_SUCCESSFUL_BILLING_STATE=GAP
SUCCESSFUL_BILLING_TO_PMPRO_ACTIVE_TIER=GAP
PMPRO_ACTIVE_TIER_TO_MEMBER_PERKS_ELIGIBILITY=PASS
MEMBER_PERKS_ELIGIBILITY_TO_COURSE_WOO_PRICE_BENEFIT=CONFIGURATION_REQUIRED
COURSE_MEMBER_PRICE_AUTHORITY=WOOCOMMERCE_BASE_PRICE_PLUS_MEMBER_PERKS_BENEFIT
COURSE_MEMBER_PRICE_GATE=CONFIGURATION_REQUIRED
MEMBERSHIP_COMMERCE_GATE=BLOCKED
```

The `PASS` on PMPro→Member Perks is limited to the authoritative eligibility mapping and resolver. It does not mean a current Course benefit is configured or that any person has been verified against a paid subscription. The price authority chain is defined, but the Course mapping, tier benefits, presentation, and runtime proof remain open.

## Deferred proof and mutation boundary

```text
MEMBERSHIP_E2E_RUNTIME_PROOF_REQUIRES_MUTATION=YES
RUNTIME_PROOF_REQUIRES_MUTATION=YES
```

Proving Woo purchase, paid state, Subscription activation, PMPro tier grant, Member Perks eligibility, and a resulting Course price requires a controlled test order and state transition. Defer this to a separately authorized task. Do not enable Member Perks flags to make the path pass. No production implementation or Platform Core deployment is authorized by this preflight.

## Evidence locations

- Historical architecture: `docs/development/membership-billing-architecture.md` §§ Architecture, Current Staging State, Woo Subscription Products, Billing Variations.
- Approved product authority: `docs/superpowers/specs/2026-10-25-vs1-golden-journey-product-tech-spec_v0.2.0.md` §11.
- Member Perks flags and feature behavior: `voelgoed-member-perks/includes/Config/SettingsRepository.php::feature_flags()`, `Config/AcfConfigProvider.php::get_option_value()`, and `voelgoed-member-perks/README.md` §§ Feature Flags, WooCommerce Discount Workflow, Masteriyo Course Workflow.
- Eligibility authority: `Membership/MembershipProfileResolver.php::get_user_membership_profile()`, `Membership/ProgrammeMappingRepository.php::load()`, `Membership/EligibilityChecker.php`, and `Perks/PerkAudienceEvaluator.php::evaluate()`.
- Product pricing: `Woo/WooApplicablePerkResolver.php::resolve_for_cart_item()`, `Woo/WooDiscountEngine.php::register_hooks()` / `apply_discounts()`, `Woo/WooContextGuard.php::can_apply_discounts()`, `Woo/WooSubscriptionPolicy.php::allows_cart_item()`, and `Woo/WooPriceCalculator.php`.
- Deterministic selection and price basis: `Woo/WooBestPerkSelector.php::select()` / `is_better()` and `Woo/WooPriceCalculator.php::get_authoritative_base_price()` at pinned Member Perks commit `972a279be7a753594888e974132562e1cc099e24`.
- Native Course/Woo mapping: installed Masteriyo 3.4.3 `WcIntegrationAddon::link_wc_product()` stores `_wc_product_id` on the Course and `_masteriyo_course_id` on the Woo Product; see `VS1_COURSE_FULFILMENT_EMAIL_GATE_v1.0.0.md` for the current Local source/runtime evidence.
- Course claim behavior: `Masteriyo/CoursePerkService.php::claim_course()`.
- Ticket claim behavior: `Tickera/TicketPerkService.php::claim_ticket()` and `Tickera/TicketAccessPolicy.php`.
- Current PMPro Woo integration runtime source: `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/pmpro-woocommerce/pmpro-woocommerce.php`, including `pmprowoo_init()`, `pmprowoo_add_membership_from_order()`, `pmprowoo_activated_subscription()`, `pmprowoo_get_membership_price()`, and `pmprowoo_process_product_meta()`.
- Current runtime configuration: Novamira `novamira/execute-php` read-only calls on 2026-10-26. Results recorded above are limited to plugin versions, flags, membership mappings, counts by tier/status, product/variation configuration, perk target summary, Course Woo integration fields, and PMPro Woo price options; no account identifiers or customer data were returned.

**Business/runtime mutation count:** 0. **Orders:** 0. **Memberships created:** 0. **Enrolments created:** 0. **Member Perks flags changed:** 0.
