# Membership Flow Test Results - 2026-06-09

Staging flow tests were run through Nova MCP against the Local staging site.

## Scope

These tests verify the current PMPro + Woo Subscriptions membership architecture:

- PMPro is the access and restriction engine.
- WooCommerce + WooCommerce Subscriptions are the billing engine.
- The PMPro WooCommerce add-on grants and removes PMPro levels from Woo subscription/order state.
- Canonical plugin-facing keys are `vriendinneklub` and `voelgoednetwerk`.
- Canonical level keys are `brons`, `silwer`, `goud`, and `platinum`.

## Test Run

Run ID: `20260609_j8IsMf`

Temporary test users, orders, and subscriptions were created during the QA run and removed during the cleanup pass.

One earlier setup attempt created a temporary user and order before Woo Subscriptions rejected the active-status transition for a draft product. That attempt did not complete the subscription test run. Those records were also removed during cleanup.

## Important Caveat

The Woo membership products are currently draft products. Woo Subscriptions blocks programmatic activation of subscriptions that contain unavailable draft products outside a normal admin/checkout context.

For this controlled staging test only, temporary in-memory filters were added inside the single Nova execution to allow subscription status transitions while keeping the products in draft:

- `woocommerce_can_subscription_be_updated_to_active`
- `woocommerce_can_subscription_be_updated_to_cancelled`

No persistent code, FluentSnippets, theme files, or plugin files were changed.

## Results

| Test | Expected | Result |
|---|---|---|
| Monthly Woo subscription purchase grants PMPro level | `vriendinneklub:brons` | Pass |
| Yearly Woo subscription purchase grants PMPro level | `voelgoednetwerk:silwer` | Pass |
| Cancelled subscription removes PMPro access | No active Voelgoed membership | Pass |
| User can hold one level per programme | `vriendinneklub:goud` and `voelgoednetwerk:silwer` | Pass |
| Same-programme upgrade replaces old level | `vriendinneklub:goud` only | Pass |
| Direct PMPro checkout remains disabled | Levels hidden from public PMPro checkout | Pass |

## Verification Details

Monthly purchase:

- Product: `905`
- Variation: `906`
- Active PMPro level: `15`
- Resolved profile: `vriendinneklub:brons`

Yearly purchase and cancellation:

- Product: `917`
- Variation: `919`
- Granted PMPro level before cancellation: `19`
- Subscription status after cancellation: `cancelled`
- Active PMPro levels after cancellation: none

Dual-programme user:

- Active PMPro levels: `17`, `19`
- Resolved profile: `vriendinneklub:goud`, `voelgoednetwerk:silwer`

Same-programme replacement:

- First profile: `vriendinneklub:brons`
- Final active PMPro level: `17`
- Final profile: `vriendinneklub:goud`

Direct PMPro checkout:

- `pmpro_getAllLevels(true, true, true)` includes levels `15` through `21`.
- `pmpro_getAllLevels(false, true, true)` returns no public Voelgoed levels.
- All Voelgoed PMPro access levels have `allow_signups = 0`.

## Final Staging State Confirmed

PMPro groups:

| Group ID | Group | Assigned levels |
|---:|---|---|
| 4 | Voelgoedvriendinne | `15`, `16`, `17` |
| 5 | Voelgoednetwerk | `18`, `19`, `20`, `21` |

Canonical PMPro metadata:

| Level ID | Code | Programme key | Level key |
|---:|---|---|---|
| 15 | `vriendinneklub_brons` | `vriendinneklub` | `brons` |
| 16 | `vriendinneklub_silwer` | `vriendinneklub` | `silwer` |
| 17 | `vriendinneklub_goud` | `vriendinneklub` | `goud` |
| 18 | `voelgoednetwerk_brons` | `voelgoednetwerk` | `brons` |
| 19 | `voelgoednetwerk_silwer` | `voelgoednetwerk` | `silwer` |
| 20 | `voelgoednetwerk_goud` | `voelgoednetwerk` | `goud` |
| 21 | `voelgoednetwerk_platinum` | `voelgoednetwerk` | `platinum` |

Woo parent products carry matching `_vg_*` metadata and remain linked to the PMPro WooCommerce add-on mapping.
