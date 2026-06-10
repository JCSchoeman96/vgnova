# FEATURE PLANNING PACK: VS-000 — Current Site Discovery and Conflict Audit

Version: 1.1.0  
Previous version: 1.0.0  
Plugin: Voelgoed Member Perks  
Status: Planning / pre-implementation gate  
Authoritative position: This slice must run before VS-001 implementation work continues.  
Patch purpose: Programme/level flexibility hardening aligned with `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

---

## 0. Patch Status

This document supersedes `VS-000_current-site-discovery-and-conflict-audit_v1.0.0.md`.

### v1.1.0 hardening summary

This version updates VS-000 for the programme and level flexibility model.

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

Main changes:

```text
1. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not the only possible programmes forever.
2. Requires discovery of configured programme definitions, level definitions, aliases, and PMPro mappings.
3. Requires the audit to distinguish stable internal keys from display labels, PMPro IDs, Woo product IDs, and billing variations.
4. Adds future-readiness checks for new billing terms, new levels, and future programmes.
5. Adds explicit checks for key drift, for example `old_programme_key` vs `vriendinneklub`.
6. Requires unmapped PMPro levels to fail closed and be reported instead of guessed.
7. Adds readiness findings against `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.
```

This patch does **not** loosen the safety model. It only prevents the future plugin from becoming brittle when new subscription products, billing terms, levels, or programmes are added.

## 1. Objective

Create a controlled discovery and conflict-audit slice for the **Voelgoed Member Perks** plugin before new business logic is added to the live or staging site.

The purpose of this slice is to identify the current real-world WordPress setup, plugin state, membership model, payment/subscription structure, existing custom code, and possible conflicts before implementing perks, discounts, claims, course access, ticket benefits, or notifications.

This slice is deliberately conservative.

The core goal:

```text
Know what already exists.
Know what must not be broken.
Know which hooks/snippets/plugins already affect WooCommerce, PMPro, Tickera, Masteriyo, ACF, login, checkout, cart pricing, subscriptions, and member access.
Confirm the launch programme defaults, configured programme registry, level mapping, aliases, and PMPro/Woo external ID relationships without hardcoding the future business model.
Prove whether users can belong to both launch programmes and whether the model can safely support future configured programmes.
Prove whether any user has conflicting levels within the same programme.
Create a findings report before coding starts.
```

No pricing, claim, course, ticket, hub, notification, or subscription behaviour may be changed in VS-000.

---

## 2. Why This Slice Exists

The site already has a complex stack:

```text
WordPress
WooCommerce
WooCommerce Subscriptions
Paid Memberships Pro
Tickera
Tickera WooCommerce Bridge
Masteriyo
ACF Pro
Bricks Builder
Etch
Cloudflare
LiteSpeed / caching layer
Existing custom snippets and/or functions.php logic
Possible existing Voelgoed Vriendinne discount logic
```

The proposed Member Perks plugin will touch sensitive areas:

```text
cart totals
checkout validation
subscription products
member-only benefits
course enrolment
Ticket/Tickera order flows
PMPro level checks
ACF-powered admin settings
frontend hub visibility
cache variation
email/notification queues
```

That means implementation without discovery is dangerous.

The specific risk is not that the new plugin cannot be built. The risk is that it gets built on top of hidden legacy logic and creates double discounts, wrong ticket benefits, stale hub displays, incorrect course access, or accidental member emails.

---

## 3. Non-Negotiable Scope Boundaries

VS-000 may do:

```text
Read plugin/theme files.
Read WPCode/snippet configuration.
Read WordPress options.
Read WooCommerce settings.
Read WooCommerce Subscriptions settings/status.
Read PMPro levels and active user memberships.
Read Tickera and Tickera Bridge configuration.
Read Masteriyo course/product/enrolment relationships.
Read ACF field groups and options.
Read current cart/checkout page setup.
Read relevant database tables using read-only queries.
Create reports.
Create risk findings.
Create recommended patch actions for later slices.
```

VS-000 must not do:

```text
Change plugin settings.
Change PMPro levels.
Change WooCommerce products.
Change WooCommerce subscription products.
Change prices.
Change coupons.
Change user memberships.
Create or delete orders.
Create or delete tickets.
Create or delete course enrolments.
Send emails.
Create notification queues.
Modify theme files.
Modify WPCode snippets.
Modify database rows.
Add production-facing shortcodes.
Change caching rules.
Deploy plugin runtime logic.
```

If an agent needs to test behaviour, it must use staging and must clearly separate observation from modification.

---

## 4. Required Outcome

VS-000 is complete only when it produces a written audit report with:

```text
1. Current plugin and theme inventory.
2. Existing custom-code inventory.
3. PMPro programme and level mapping.
4. Vriendinneklub membership structure.
5. Voelgoednetwerk membership structure.
6. Dual-membership user examples.
7. Same-programme conflict detection.
8. WooCommerce product and subscription risk map.
9. Woo Subscriptions renewal/switch/resubscribe policy recommendations.
10. Tickera Bridge mapping findings.
11. Masteriyo course/product/enrolment findings.
12. ACF field group and options-page findings.
13. Cart/checkout mode finding: classic shortcode vs Woo Blocks.
14. Existing cart/checkout/pricing hooks and snippets.
15. Cache and Cloudflare risk notes.
16. Email/SMTP/notification risk notes.
17. Programme/level flexibility findings against `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.
18. Specific conflicts that must be resolved before VS-007/VS-008 execution slices.
19. Go / blocked recommendation for the next slice.
```

---

## 5. Required Deliverables

Create the following documentation file:

```text
docs/audits/VS-000_current-site-discovery-and-conflict-audit-report.md
```

Optional supporting files:

```text
docs/audits/VS-000_plugin-inventory.csv
docs/audits/VS-000_custom-code-inventory.csv
docs/audits/VS-000_pmpro-level-map.csv
docs/audits/VS-000_woocommerce-risk-map.csv
docs/audits/VS-000_tickera-masteriyo-map.csv
docs/audits/VS-000_findings.json
```

If generated files are not practical, the Markdown report must contain all required findings in structured tables.

---

## 6. Site Context to Confirm

The audit must confirm the site context instead of assuming it.

Expected but not guaranteed:

```text
Primary site: voelgoed.co.za
Related sites: voelgoedwinkel.co.za, voelgoed-leer.co.za, passievol.com
Staging site: confirm exact URL
Hosting stack: OpenLiteSpeed / CyberPanel / Cloudflare / LiteSpeed Cache
Primary builder: Bricks Builder and/or Etch
Commerce engine: WooCommerce
Recurring billing: WooCommerce Subscriptions
Membership authority: Paid Memberships Pro
Course engine: Masteriyo
Ticket engine: Tickera + Tickera WooCommerce Bridge
Admin data layer: ACF Pro
```

The report must state what was confirmed, what was not found, and what still needs a human decision.

---

## 7. Programme Model Discovery

The plugin must support the current launch membership programmes while staying configurable for future growth.

Launch/default programmes expected on the current Voelgoed site:

```text
vriendinneklub      = Voelgoed Vriendinneklub
voelgoednetwerk     = Voelgoednetwerk
```

These are the expected launch defaults, not a permanent limit on the architecture.

The audit must confirm the real configured state and identify any drift, including alternate or legacy keys such as:

```text
old_programme_key
vriendinne
vriendinneklub
voelgoednetwerk
netwerk
```

The audit must not silently choose between conflicting keys. It must report the conflict and recommend either:

```text
1. one canonical key,
2. a configured alias mapping, or
3. a migration/cleanup task before implementation.
```

A user may have:

```text
no active configured programme
Vriendinneklub only
Voelgoednetwerk only
both launch programmes
future configured programme membership, if the registry supports it later
```

A user should normally have one active level per programme.

Example valid state:

```text
Vriendinneklub: Goud
Voelgoednetwerk: Silwer
```

Example conflict state:

```text
Vriendinneklub: Goud
Vriendinneklub: Silwer
```

The audit must confirm whether PMPro is configured in a way that allows the launch dual-programme model and whether existing users already have both programmes.

### Programme flexibility rule

VS-000 must audit whether the current site can support this rule:

```text
New Woo billing option = billing configuration change.
New PMPro level = mapping/settings change.
New perk audience = admin configuration change.
New programme = registry + mapping + hub/report/cache QA.
```

The plugin must not treat every PMPro level as automatically eligible.

Correct:

```text
Only enabled, mapped PMPro levels produce MembershipProfile entries.
```

Incorrect:

```text
Every PMPro level automatically grants perks.
```

Unknown or unmapped PMPro levels must fail closed.

## 8. Required Programme Registry Draft

The report must propose a configurable programme registry for later slices.

This registry must include the launch/default programmes and must leave room for future configured programmes.

Minimum shape:

```php
$programmes = [
    "vriendinneklub" => [
        "label" => "Voelgoed Vriendinneklub",
        "slug" => "vriendinneklub",
        "hub_page_slug" => "my-vriendinneklub",
        "ranked_levels" => true,
        "enabled" => true,
        "sort_order" => 10,
        "aliases" => [
            "old_programme_key",
            "vriendinne",
        ],
    ],
    "voelgoednetwerk" => [
        "label" => "Voelgoednetwerk",
        "slug" => "voelgoednetwerk",
        "hub_page_slug" => "my-voelgoed-netwerk",
        "ranked_levels" => true,
        "enabled" => true,
        "sort_order" => 20,
        "aliases" => [
            "netwerk",
        ],
    ],
];
```

The exact labels, slugs, aliases, hub pages, and enabled state must be confirmed against the real site.

### Required registry findings

The audit report must state:

```text
1. Which programme keys are used by PMPro level metadata today.
2. Which programme keys the plugin should treat as canonical launch keys.
3. Whether aliases are needed to bridge existing data safely.
4. Whether any PMPro levels use unknown programme keys.
5. Whether future programme additions can be made through configuration rather than code.
```

### Future programme rule

Future programmes may be added only if:

```text
programme_key is unique
programme label is configured
programme is enabled
at least one level mapping exists
hub/display behaviour is configured or intentionally disabled
cache/profile/report implications are reviewed
QA confirms no eligibility, claim, notification, or cache leakage
```

If a future programme lacks these requirements, it must be ignored by runtime eligibility and reported as unmapped/misconfigured.

## 9. Required PMPro Level Mapping

The audit must map every relevant PMPro level to the configurable programme/level model.

The mapping must distinguish between:

```text
programme_key          internal stable programme key
level_key              internal stable level key within a programme
level_label            admin/display label
level_rank             configured ordering for same-programme conflict resolution
pmpro_level_id         external PMPro level ID
pmpro_level_name       external PMPro label
woo_product_id         external Woo subscription product ID, if applicable
woo_variation_ids      external Woo billing variation IDs, if applicable
billing_terms          monthly/yearly/quarterly/etc. billing options
mapping_enabled        whether this mapping counts for runtime eligibility
is_paid_level          whether this is a paid/access mapping
aliases                legacy or alternate keys, if any
```

Example target output:

| PMPro Level ID | PMPro Name | Programme Key | Level Key | Rank | Woo Product | Billing Terms | Status | Notes |
|---:|---|---|---|---:|---:|---|---|---|
| TBD | Voelgoedvriendinne Goud | vriendinneklub | goud | 10 | TBD | monthly/yearly | mapped | Confirm key/alias |
| TBD | Voelgoednetwerk Silwer | voelgoednetwerk | silwer | 20 | TBD | monthly/yearly | mapped | Confirm label language |
| TBD | Legacy Test Level | unknown | unknown | — | — | — | unmapped | Must fail closed |

Rules:

```text
1. Monthly/yearly/quarterly variants of the same benefit level must resolve to the same level_key.
2. Billing terms do not create separate perk levels unless the business explicitly wants different access.
3. Rank determines highest-level conflict resolution inside the same programme.
4. Programme membership must not be inferred from display names alone if IDs/options/meta are available.
5. PMPro level IDs are external IDs and must not be hardcoded into later execution logic.
6. Woo product IDs and variation IDs are external billing IDs and must not become perk authority.
7. The final mapping must be explicit, reviewable, and editable through later settings.
8. Only enabled mappings count for runtime MembershipProfile resolution.
9. Unmapped PMPro levels do not grant perks.
```

### Billing option flexibility

If a new billing term is added later, for example:

```text
quarterly
six-monthly
founding-member annual
corporate invoice term
```

then the audit should expect that this can be handled as a Woo billing/product/variation configuration change if it grants the same access level.

Do not create a new PMPro level merely because billing cadence changes.

### New level flexibility

If a new level is added later, for example:

```text
vriendinneklub:platinum
voelgoednetwerk:diamond
```

then the expected change should be:

```text
1. create PMPro access-only level,
2. create/link Woo billing product or variation,
3. add Programme Level Mapping row,
4. verify rank,
5. reindex/audit perks if needed,
6. test MembershipProfile resolution.
```

No Woo discount, claim, hub, course, ticket, cache, or notification execution code should need hardcoded changes for the new level.

## 10. Dual-Membership Audit

The audit must identify counts for:

```text
users with no relevant active programme
users with Vriendinneklub only
users with Voelgoednetwerk only
users with both programmes
users with conflicting levels inside Vriendinneklub
users with conflicting levels inside Voelgoednetwerk
users with inactive/cancelled/expired levels that could confuse reports
```

The report must include anonymised examples.

Do not expose personal data unless the report is explicitly intended for internal admin use and stored securely.

Recommended format:

| User Example | Vriendinneklub | Voelgoednetwerk | Status | Risk |
|---|---|---|---|---|
| User A | Goud | None | Valid | None |
| User B | Goud | Silwer | Valid dual member | Must test both hubs |
| User C | Goud + Brons | None | Conflict | Admin repair needed |

---

## 10A. Programme Mapping Model Readiness

The audit must compare the current site against `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

Required checks:

```text
1. Programmes are represented as configuration/mapping records, not hardcoded PHP conditions.
2. PMPro levels are access-only where Woo Subscriptions handles billing.
3. Woo products/variations are billing surfaces, not perk-level authority.
4. Programme keys are stable and unique.
5. Level keys are stable within each programme.
6. Display labels may change without breaking runtime identity.
7. PMPro IDs may change only through mapping updates, not code edits.
8. Unmapped levels fail closed.
9. Future billing terms can map to existing level keys.
10. Future levels can be added through mapping/settings.
11. Future programmes require registry + mapping + hub/cache/report QA.
```

Required output table:

| Check | Status | Evidence | Risk | Required Action |
|---|---|---|---|---|
| PMPro levels access-only | Pass/Fail | Details | LOW/HIGH | Action |
| Programme keys stable | Pass/Fail | Details | MEDIUM | Action |
| Future level via mapping | Pass/Fail | Details | MEDIUM | Action |
| Unmapped levels fail closed | Pass/Fail | Details | HIGH | Action |

## 11. PMPro Discovery Checklist

The audit must inspect:

```text
PMPro plugin version
PMPro multiple-membership support state
All membership levels
All membership groups if used
Whether users can hold more than one level
Whether current levels are monthly/yearly variants
Whether membership expiry dates are used
Whether WooCommerce Subscriptions drives membership lifecycle
Whether PMPro checkout is still used for memberships
Whether PMPro discount codes are used
Whether PMPro content restrictions are used on relevant pages
Any custom PMPro hooks in theme/plugin/snippets
```

Read-only areas to inspect:

```text
pmpro_membership_levels
pmpro_memberships_users
wp_options related to PMPro
active PMPro add-ons
custom code using pmpro_hasMembershipLevel()
custom code using pmpro_changeMembershipLevel()
custom code using pmpro_after_change_membership_level
custom code hardcoding PMPro level IDs
custom code hardcoding programme keys or legacy keys
custom code reading vg_programme_key / vg_level_key metadata
```

The report must clearly state the final membership authority:

```text
PMPro is the membership-state authority.
Woo Subscriptions is the recurring billing authority where applicable.
The Member Perks plugin must not become the membership authority.
```

---

## 12. Existing Custom-Code Inventory

The audit must search for existing custom logic in:

```text
active theme functions.php
child theme functions.php
mu-plugins
custom plugins
WPCode / Code Snippets / WPCode Pro snippets
WooCommerce template overrides
ACF local JSON folders
Bricks custom code areas if used
Etch custom code areas if used
```

Search for these keywords:

```text
woocommerce_before_calculate_totals
woocommerce_cart_calculate_fees
woocommerce_coupon_is_valid
woocommerce_available_payment_gateways
woocommerce_checkout_create_order_line_item
woocommerce_checkout_process
woocommerce_before_checkout_process
woocommerce_after_checkout_validation
woocommerce_thankyou
woocommerce_order_status_
pmpro_hasMembershipLevel
pmpro_changeMembershipLevel
pmpro_after_change_membership_level
tc_
tickera
masteriyo
acf/
get_field
update_field
vip
vriendinne
voelgoed
membership
member
subscription
renewal
switch
resubscribe
```

The report must identify:

```text
file/snippet name
hook/filter used
priority
condition logic
business purpose
whether it can conflict with Member Perks
recommendation: keep, migrate, disable later, or inspect manually
```

Recommended table:

| Source | Hook/Filter | Priority | Purpose | Conflict Risk | Recommendation |
|---|---|---:|---|---|---|
| functions.php | woocommerce_before_calculate_totals | 20 | VIP discount | High | Must coordinate with VS-008 |

---

## 13. WooCommerce Discovery Checklist

Inspect:

```text
WooCommerce version
HPOS enabled/disabled
Cart page type
Checkout page type
My Account page structure
Payment gateways
Coupons
Tax settings
Shipping settings if physical products matter
Product types in use
Products that are subscription products
Products that are Tickera ticket products
Products created/linked by Masteriyo
Products currently discounted by snippets/coupons
Order item meta already used by Tickera/Masteriyo/custom logic
```

Cart/checkout mode must be explicitly recorded:

```text
classic shortcode cart
classic shortcode checkout
Woo Blocks cart
Woo Blocks checkout
mixed/unknown
```

MVP policy:

```text
Classic shortcode cart/checkout is supported first.
Woo Blocks cart/checkout must be detection-only unless a dedicated compatibility slice is created.
```

If Woo Blocks checkout is active, VS-008 pricing/execution work must be marked blocked or separately scoped.

---

## 14. Woo Subscriptions Discovery Checklist

Inspect:

```text
WooCommerce Subscriptions version
subscription product IDs
subscription statuses
manual renewal settings
payment gateways used for renewals
initial orders vs renewal orders
switch orders
resubscribe orders
failed payment retry behaviour
Payfast or other gateway token/renewal support if relevant
membership products connected to PMPro
whether subscription cancellation/expiry changes PMPro levels
whether billing terms are implemented as Woo variations/products rather than duplicate PMPro levels
whether adding a new billing term would require code changes or only Woo/mapping configuration
```

The report must propose the default policy for later slices:

```text
Member perk discounts do not apply to subscription renewals by default.
Member perk discounts do not modify existing subscriptions by default.
Member perk discounts do not apply to switch/resubscribe/renewal orders by default.
Member perk discounts do not discount membership subscription products unless explicitly enabled in a future slice.
```

This policy must be confirmed before VS-008 is implemented.

---

## 15. Tickera Discovery Checklist

Inspect:

```text
Tickera plugin version
Tickera WooCommerce Bridge version
whether tickets are sold as WooCommerce products
how event IDs map to Woo product IDs
how ticket type IDs map to Woo product IDs
attendee fields currently used
PDF ticket generation flow
QR/check-in flow
order status required before ticket generation
existing custom ticket/email code
existing Tickera template overrides
```

The report must identify:

```text
Which Woo products are Tickera ticket products.
Which events/ticket types may receive member perks.
Which existing code already modifies ticket product pricing or attendee data.
Which order item meta is required by Tickera and must not be overwritten.
```

Policy for later slices:

```text
Tickera remains the ticket authority.
The Member Perks plugin may influence eligibility/discount/claim state.
The Member Perks plugin must not bypass Tickera ticket generation or check-in authority.
```

---

## 16. Masteriyo Discovery Checklist

Inspect:

```text
Masteriyo version
course CPT names/statuses
course product relationship
WooCommerce product relationship if used
course enrolment table/model
existing access restrictions
existing course bundles/free courses
existing custom course grant logic
whether Masteriyo creates Woo products automatically
whether courses should be accessed through course pages or product pages
```

The report must identify:

```text
Which courses can be offered as perks.
Which Woo products correspond to courses.
Whether free course perks should grant enrolment or create zero-value Woo orders.
Whether duplicate enrolments can happen.
```

Policy for later slices:

```text
Masteriyo remains the course/access authority.
The Member Perks plugin must not create duplicate enrolments.
The Member Perks plugin must use the claim ledger before granting access.
```

---

## 17. ACF Discovery Checklist

Inspect:

```text
ACF Pro version
existing field groups related to events/products/courses/memberships/perks
ACF Local JSON paths
ACF options pages
field key/name collisions
fields already used for VIP/Vriendinne pricing
fields already used for Tickera events
fields already used for Masteriyo/course displays
fields already used in Bricks/Etch templates
```

The report must recommend:

```text
Plugin-owned ACF JSON load path.
Development/staging-only ACF JSON save path.
Production field edit policy.
Field naming prefix: vgmp_*
Whether existing fields should be reused or migrated.
```

Default policy:

```text
ACF is an admin editing layer.
ACF fields are not the runtime authority for claims, redemptions, or financial state.
Programme/level mappings may be edited through ACF for MVP, but runtime slices must consume normalized repository output.
```

---

## 18. Bricks / Etch / Hub Discovery Checklist

Inspect:

```text
current member account pages
current PMPro account pages
current Woo My Account customisations
current Bricks templates used for member areas
current Etch components used for member areas
existing dynamic visibility conditions
existing query loops for products/courses/events
existing shortcodes used on member pages
```

The report must propose exact pages for later slices:

```text
/my-voordele/              Main combined member perks dashboard
/my-vriendinneklub/        Vriendinneklub hub
/my-voelgoed-netwerk/      Voelgoednetwerk hub
```

If different final slugs are desired, document them.

Rules for later slices:

```text
Hub pages are display surfaces only.
The plugin resolver/index/ledger remains the authority.
Do not create separate perk engines per hub.
A dual member must see both programmes.
A shared perk must display once unless explicitly duplicated for a clear business reason.
```

---

## 19. Cache and Cloudflare Discovery Checklist

Inspect:

```text
LiteSpeed Cache settings relevant to logged-in users
object cache status
Redis/object cache usage
Cloudflare cache rules
Cloudflare page rules/cache rules for member/account pages
Turnstile/login protection if relevant
whether cart/checkout/account pages bypass cache
whether member hub pages bypass public cache
whether ESI/private cache is used
```

Policy for later slices:

```text
Do not publicly cache user-specific perk data.
Member hub output must vary by user or be rendered privately.
Cache keys must use membership_profile_hash, not a single membership group.
Cart/checkout/member account pages must not leak personalised data through Cloudflare or LiteSpeed public cache.
```

---

## 20. Email / SMTP / Notification Discovery Checklist

Inspect:

```text
FluentSMTP or SMTP plugin state
Brevo/Sendinblue use
WooCommerce transactional email overrides
PMPro email overrides
Tickera ticket email behaviour
unsubscribe/opt-out processes
existing newsletter/CRM tools
staging email safety
whether WP_ENVIRONMENT_TYPE is set
```

Policy for later VS-014:

```text
No mass member emails by default.
Staging must default to dry-run or test-email routing.
Notifications must use a queue/log model.
wp_mail() may be used as transport, but deliverability remains the SMTP provider's responsibility.
```

---

## 21. Read-Only SQL Guidance

Only use read-only queries in VS-000.

Examples may be adapted to the actual table prefix.

### PMPro levels

```sql
SELECT id, name, description, confirmation, initial_payment, billing_amount, cycle_number, cycle_period, expiration_number, expiration_period
FROM wp_pmpro_membership_levels
ORDER BY id;
```

### PMPro active membership rows

```sql
SELECT user_id, membership_id, status, startdate, enddate, modified
FROM wp_pmpro_memberships_users
WHERE status = 'active'
ORDER BY user_id, membership_id;
```

### Potential users with multiple active PMPro memberships

```sql
SELECT user_id, COUNT(*) AS active_level_count
FROM wp_pmpro_memberships_users
WHERE status = 'active'
GROUP BY user_id
HAVING COUNT(*) > 1
ORDER BY active_level_count DESC, user_id ASC;
```

### WooCommerce subscription products by product type taxonomy

```sql
SELECT p.ID, p.post_title, p.post_status, t.slug AS product_type
FROM wp_posts p
JOIN wp_term_relationships tr ON tr.object_id = p.ID
JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
JOIN wp_terms t ON t.term_id = tt.term_id
WHERE p.post_type = 'product'
  AND tt.taxonomy = 'product_type'
  AND t.slug LIKE '%subscription%'
ORDER BY p.ID;
```

### Active custom snippets in WPCode-style storage

```sql
SELECT ID, post_title, post_status, post_type
FROM wp_posts
WHERE post_type LIKE '%snippet%'
   OR post_title LIKE '%snippet%'
ORDER BY ID DESC;
```

The actual WPCode storage model may differ. If this query does not find snippets, inspect plugin-specific tables/options.

---

## 22. Required Conflict Categories

Every finding must be tagged using one of these categories:

```text
BLOCKER
HIGH
MEDIUM
LOW
INFO
```

Definitions:

```text
BLOCKER = must be resolved before implementing affected slice.
HIGH    = likely to cause wrong money/access/ticket/member behaviour if ignored.
MEDIUM  = should be resolved before production, but does not block early display-only work.
LOW     = cleanup or documentation improvement.
INFO    = useful fact with no immediate risk.
```

Required output table:

| ID | Category | Area | Finding | Affected Slices | Required Action |
|---|---|---|---|---|---|
| VS000-001 | BLOCKER | Woo Checkout | Woo Blocks checkout detected | VS-008, VS-010 | Add blocks compatibility or switch to classic checkout |

---

## 23. Required Stop Conditions

Stop and report immediately if any of these are found:

```text
1. Production environment is detected and agent was about to make changes.
2. Cart/checkout uses Woo Blocks and VS-008 assumes classic hooks only.
3. Existing code already applies Vriendinne/VIP discounts on the same products targeted by Member Perks.
4. Users can have multiple active levels inside the same programme and no resolution policy exists.
5. PMPro levels cannot be mapped cleanly to programme_key + level_key.
5A. Relevant PMPro levels use conflicting programme keys and no alias/migration decision exists.
5B. Future billing terms or levels would require hardcoded execution changes instead of mapping/settings.
6. Woo Subscriptions membership products are targeted for discounts without explicit approval.
7. Tickera ticket products cannot be distinguished from ordinary Woo products.
8. Masteriyo course access cannot be safely granted without duplicate enrolment risk.
9. Staging email safety is missing and notification testing could email real members.
10. Public cache is active on personalised member hub pages.
```

A stop condition does not mean the project fails. It means the relevant later slice must be patched before implementation.

---

## 24. Required Recommendations for Later Slices

The report must explicitly recommend whether each slice is:

```text
ready
ready with patches
blocked
not yet inspected
```

The recommendation must also state whether the central `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md` is satisfied, needs patches, or is blocked.

Required table:

| Slice | Status | Reason | Required Patch |
|---|---|---|---|
| VS-001 | Ready with patches | Needs programme constants | Add programme registry stub |
| VS-002 | Blocked | PMPro level map incomplete | Complete programme-level mapping |
| VS-008 | Blocked | Existing Woo discount snippet found | Deconflict pricing logic |

---

## 25. Minimum Acceptance Criteria

VS-000 is complete only when:

```text
1. No runtime behaviour has been changed.
2. Current plugin/theme/snippet inventory is documented.
3. PMPro level IDs are mapped through the programme/level model or explicitly marked unresolved.
4. Launch programme keys, aliases, and any key drift are documented and either accepted or marked unresolved.
5. Dual-membership support is confirmed for launch programmes or limitations are documented.
6. Same-programme conflicts are detected or the method for detecting them is documented.
7. Woo cart/checkout mode is confirmed.
8. Existing Woo pricing/cart/checkout custom code is inventoried.
9. Woo Subscriptions product and renewal risks are documented.
10. Tickera product/event/ticket relationships are documented.
11. Masteriyo course/product/enrolment relationships are documented.
12. ACF Local JSON and field-group risks are documented.
13. Cache/public-personalisation risk is documented.
14. Email/staging safety risk is documented.
15. All blocker/high findings are listed.
16. Later slice readiness table is completed.
17. Programme/level mapping model readiness is documented.
18. Future billing-term and future-level flexibility risks are documented.
19. Final recommendation is either GO TO VS-001, GO WITH PATCHES, or BLOCKED.
```

---

## 26. Manual Test / Inspection Plan

Run on staging first.

### Test 1 — Environment confirmation

```text
Confirm site URL.
Confirm WP_ENVIRONMENT_TYPE.
Confirm staging/production status.
Expected: report clearly states environment.
```

### Test 2 — Plugin inventory

```text
List active plugins and versions.
Expected: WooCommerce, Woo Subscriptions, PMPro, Tickera, Masteriyo, ACF Pro, Bricks/Etch status documented.
```

### Test 3 — PMPro level map

```text
List all PMPro levels.
Map each relevant level to programme_key and level_key.
Record level_rank, PMPro ID, Woo billing product/variation link where relevant.
Expected: no unclassified relevant level remains without a note.
Expected: unmapped/test/legacy levels are explicitly marked fail-closed.
```

### Test 3A — Programme key drift and alias audit

```text
Inspect PMPro level metadata and existing docs/settings for programme keys.
Expected: canonical launch keys, legacy keys, and aliases are listed.
Expected: conflicts such as old_programme_key vs vriendinneklub are not silently ignored.
```

### Test 3B — Future flexibility audit

```text
Check whether a new billing term or new PMPro level could be added through Woo/settings/mapping rather than PHP changes.
Expected: future billing terms map to existing level keys where access is unchanged.
Expected: future levels require a mapping row, not execution code changes.
```

### Test 4 — Dual-member detection

```text
Inspect active PMPro membership rows.
Expected: counts for Vriendinne only, Netwerk only, both, and conflicts.
```

### Test 5 — Existing pricing logic

```text
Search custom code for Woo pricing/cart hooks.
Expected: all relevant snippets/hooks listed.
```

### Test 6 — Checkout mode

```text
Inspect cart and checkout page content/templates.
Expected: classic shortcode vs Woo Blocks recorded.
```

### Test 7 — Subscription safety

```text
Identify all subscription products and membership products.
Expected: no later discount slice targets these by accident.
```

### Test 8 — Tickera safety

```text
Map ticket products to events/ticket types.
Expected: Tickera products clearly identifiable.
```

### Test 9 — Masteriyo safety

```text
Map course products and enrolment behaviour.
Expected: course grant strategy can be planned without duplicate enrolments.
```

### Test 10 — Cache safety

```text
Inspect cache rules for account/hub/cart/checkout pages.
Expected: personalised pages are not publicly cached.
```

### Test 11 — Email safety

```text
Inspect SMTP/mail plugin and staging behaviour.
Expected: no future notification slice can email real members accidentally on staging.
```

---

## 27. Required Final Agent Report Format

After completing VS-000, the agent must provide:

```text
1. Executive verdict
2. Environment confirmed
3. Plugin/theme inventory
4. PMPro programme-level map
4A. Programme/level mapping model readiness
5. Dual-membership findings
6. Conflict findings
7. Existing custom-code inventory
8. WooCommerce and checkout findings
9. Woo Subscriptions findings
10. Tickera findings
11. Masteriyo findings
12. ACF findings
13. Hub/page-builder findings
14. Cache/Cloudflare findings
15. Email/notification findings
16. Blockers
17. High-risk items
18. Later slice readiness table
19. Recommended next step
20. Assumptions and unknowns
```

The final verdict must be one of:

```text
GO TO VS-001
GO TO VS-001 WITH PATCHES
BLOCKED UNTIL FINDINGS RESOLVED
```

Do not use vague wording like "looks good" without evidence.

---

## 28. Downstream Slice Impact

VS-000 findings must directly inform the central `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md` and:

```text
VS-001 foundation constants and capabilities
VS-002 programme registry and ACF options
VS-003 MembershipProfile resolver
VS-005 hub page behaviour
VS-006 target indexing table
VS-007 claims/redemption ledger
VS-008 WooCommerce discounts
VS-009 Masteriyo course perks
VS-010 Tickera ticket perks
VS-011 reports
VS-013 cache keys
VS-014 notification dry-run and recipient resolution
VS-015 final QA
```

If VS-000 is skipped, the later slices become assumption-driven and should not be considered production-ready.

---

## 29. Explicit Out-of-Scope List

Do not implement these in VS-000:

```text
New perk CPT registration
ACF field creation
Programme hub rendering
Discount execution
Claim creation
Course enrolment
Ticket issuing
Notification queue creation
Email sending
Admin bulk actions
Cache purge automation
Programme/level mapping changes
PMPro level edits
Woo subscription product edits
Production deployment
```

These belong to later slices.

---

## 30. Relationship to `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`

VS-000 is the discovery source that confirms whether the central mapping model matches the real site.

The audit report must explicitly answer:

```text
1. Are the launch programmes correct?
2. Are the current PMPro groups and levels compatible with one-level-per-programme?
3. Are monthly/yearly billing terms correctly represented in Woo rather than duplicate PMPro benefit levels?
4. Are programme keys and level keys stable?
5. Are aliases needed for existing metadata such as `old_programme_key`?
6. Are any PMPro levels unmapped?
7. Would a new billing term require code changes?
8. Would a new level require code changes?
9. What must be patched before VS-002/VS-003 rely on the mapping?
```

If the real site contradicts the model, do not patch the model silently. Report the contradiction and require a decision.

## 30. Next Slice

After VS-000 is completed and reviewed, proceed to:

```text
VS-001 — Plugin Foundation, CPT and Taxonomies
```

But only if the VS-000 final verdict is:

```text
GO TO VS-001
```

or:

```text
GO TO VS-001 WITH PATCHES
```

If the verdict is:

```text
BLOCKED UNTIL FINDINGS RESOLVED
```

then resolve the blockers before continuing.
