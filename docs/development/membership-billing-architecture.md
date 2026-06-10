# Voelgoed Membership + Billing Architecture

Last updated: 2026-06-09

This document records the current staging setup for Voelgoed memberships after the PMPro/Woo Subscriptions configuration pass.

Flow test results are recorded in [`membership-flow-test-results-2026-06-09.md`](membership-flow-test-results-2026-06-09.md).

## Architecture

Paid Memberships Pro is the membership and access engine only. PMPro levels identify what a member can access and are used for content restriction, perks, and membership checks.

WooCommerce and WooCommerce Subscriptions are the billing engine. Membership products are WooCommerce variable subscription products with monthly and yearly billing variations. Purchases grant the linked PMPro level through the Paid Memberships Pro WooCommerce add-on.

Do not create separate monthly/yearly PMPro levels. Billing variants belong in Woo subscription variations.

## Current Staging State

PMPro groups:

| Group ID | Group | Multiple selections | Display order |
|---:|---|---:|---:|
| 4 | Voelgoedvriendinne | 0 | 10 |
| 5 | Voelgoednetwerk | 0 | 20 |

`Main Group` remains present and untouched.

PMPro access-only levels:

| Level ID | PMPro level | Internal code | Group | Direct PMPro signup |
|---:|---|---|---|---|
| 15 | Voelgoedvriendinne Brons | `vriendinneklub_brons` | Voelgoedvriendinne | Disabled |
| 16 | Voelgoedvriendinne Silwer | `vriendinneklub_silwer` | Voelgoedvriendinne | Disabled |
| 17 | Voelgoedvriendinne Goud | `vriendinneklub_goud` | Voelgoedvriendinne | Disabled |
| 18 | Voelgoednetwerk Brons | `voelgoednetwerk_brons` | Voelgoednetwerk | Disabled |
| 19 | Voelgoednetwerk Silwer | `voelgoednetwerk_silwer` | Voelgoednetwerk | Disabled |
| 20 | Voelgoednetwerk Goud | `voelgoednetwerk_goud` | Voelgoednetwerk | Disabled |
| 21 | Voelgoednetwerk Platinum | `voelgoednetwerk_platinum` | Voelgoednetwerk | Disabled |

All Voelgoed PMPro levels are access-only:

- `allow_signups = 0`
- `initial_payment = 0`
- `billing_amount = 0`
- no PMPro trial, billing limit, or expiration configured

## PMPro Level Metadata

Each PMPro level has these `vg_*` metadata keys:

| Meta key | Purpose |
|---|---|
| `vg_membership_code` | Stable code for the tier |
| `vg_programme_key` | `vriendinneklub` or `voelgoednetwerk` |
| `vg_programme_label` | Human programme label |
| `vg_level_key` | Tier key |
| `vg_level_label` | Tier label |
| `vg_member_cap` | Display/reference cap for Netwerk tiers |
| `vg_cap_mode` | `display_only` for Netwerk tiers |
| `vg_billing_engine` | `woocommerce_subscriptions` |
| `vg_pmpro_role` | `access_only` |

Netwerk caps are informational only and are not checkout enforcement.

Canonical plugin-facing keys:

| Programme | `vg_programme_key` | Level keys |
|---|---|---|
| Voelgoed Vriendinneklub | `vriendinneklub` | `brons`, `silwer`, `goud` |
| Voelgoednetwerk | `voelgoednetwerk` | `brons`, `silwer`, `goud`, `platinum` |

## Woo Subscription Products

Woo subscription products are the public billing products. They are currently drafts for admin review.

| Product ID | Product | Linked PMPro level | Type | Status |
|---:|---|---:|---|---|
| 905 | Voelgoedvriendinne Brons | 15 | variable-subscription | draft |
| 908 | Voelgoedvriendinne Silwer | 16 | variable-subscription | draft |
| 911 | Voelgoedvriendinne Goud | 17 | variable-subscription | draft |
| 914 | Voelgoednetwerk Brons | 18 | variable-subscription | draft |
| 917 | Voelgoednetwerk Silwer | 19 | variable-subscription | draft |
| 920 | Voelgoednetwerk Goud | 20 | variable-subscription | draft |
| 923 | Voelgoednetwerk Platinum | 21 | variable-subscription | draft |

The PMPro WooCommerce add-on link is stored on each parent product:

- `_membership_product_level = <PMPro level ID>`
- `_membership_product_autocomplete = 0`
- `_pmprowoo_product_levels[product_id] = pmpro_level_id`

## Billing Variations

Each product has a local variation attribute:

| Attribute | Values |
|---|---|
| `Betalingstermyn` | `Maandeliks`, `Jaarliks` |

Regular price stores the full price. Sale price is the active checkout price.

| Product | Maandeliks full | Maandeliks sale | Jaarliks full | Jaarliks sale |
|---|---:|---:|---:|---:|
| Voelgoedvriendinne Brons | R39 | R24 | R390 | R240 |
| Voelgoedvriendinne Silwer | R59 | R39 | R590 | R390 |
| Voelgoedvriendinne Goud | R99 | R79 | R990 | R790 |
| Voelgoednetwerk Brons | R94 | R74 | R940 | R740 |
| Voelgoednetwerk Silwer | R194 | R144 | R1940 | R1440 |
| Voelgoednetwerk Goud | R299 | R224 | R2990 | R2240 |
| Voelgoednetwerk Platinum | R444 | R344 | R4440 | R3440 |

Monthly variations use:

- `_subscription_period = month`
- `_subscription_period_interval = 1`
- `_subscription_length = 0`
- `_subscription_sign_up_fee = 0`
- `_subscription_trial_length = 0`

Yearly variations use:

- `_subscription_period = year`
- `_subscription_period_interval = 1`
- `_subscription_length = 0`
- `_subscription_sign_up_fee = 0`
- `_subscription_trial_length = 0`

## Rules For Future Changes

- Keep PMPro as access and restriction only.
- Keep WooCommerce Subscriptions as billing only.
- Do not create monthly/yearly PMPro duplicates.
- Use `vriendinneklub` and `voelgoednetwerk` as canonical programme keys.
- Use `brons`, `silwer`, `goud`, and `platinum` as canonical level keys.
- Do not enable direct PMPro signup for these levels unless the billing architecture changes.
- Link membership products through the PMPro WooCommerce add-on fields instead of custom code.
- Do not add FluentSnippets for this workflow unless the installed plugins cannot support a required behaviour.
