# FEATURE PLANNING PACK: VS-015 — Final Integration QA and Release Hardening v1.3.0

## 0. Patch Status

This document supersedes the earlier `VS-015_final-integration-qa-and-release-hardening.md`.

### v1.1.0 hardening summary

This version updates VS-015 for the hardened architecture established across VS-000 to VS-014:

```text
1. Corrected slice order:
   VS-007 = Claims and Redemption Ledger
   VS-008 = WooCommerce Product Discounts

2. Updated plugin-owned table names:
   wp_vg_perk_targets        → wp_vgmp_perk_targets
   wp_vg_perk_redemptions    → wp_vgmp_perk_redemptions
   wp_vg_perk_notifications  → wp_vgmp_perk_notifications

3. Replaced flat `membership_group` and `vg_perk_level_group` assumptions with the MembershipProfile model.

4. Added first-class dual-programme QA:
   - no membership
   - Vriendinneklub only
   - Voelgoednetwerk only
   - both programmes
   - same-programme conflict

5. Added programme-aware QA for:
   - hub display
   - target/audience index
   - Woo discounts
   - claim scopes
   - Masteriyo course claims
   - Tickera ticket claims
   - reports
   - notifications
   - cache safety

6. Added `misconfigured` as a runtime status.

7. Added explicit legacy-drift checks for old tables, old fields, old taxonomies, and old metadata.

8. Hardened final readiness rules:
   Do not declare production-ready unless evidence exists.
```



### v1.2.0 ACF runtime-boundary hardening summary

This version applies `VGMP_ACF_RUNTIME_BOUNDARY_PATCHLIST_v1.0.0`.

```text
9. Added ACF Runtime Boundary QA.
10. Added direct ACF call whitelist/prohibited-location checks.
11. Added grep/static check examples.
12. Added ACF inactive degradation test.
13. Added future custom-admin readiness criterion.
14. Added release blocker rule for direct ACF calls in hot execution paths.
```

### v1.3.0 Programme and Level Flexibility hardening summary

This version applies the Programme and Level Flexibility Pass and aligns final QA with:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0
```

Key changes in v1.3.0:

```text
15. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a closed forever-list.
16. Requires final QA to prove that programmes, levels, aliases, and PMPro level IDs are configuration-driven.
17. Adds release-blocker checks for hardcoded PMPro level IDs, Woo membership product IDs, Woo variation IDs, fixed billing terms, and fixed Goud/Silwer/Brons assumptions in execution paths.
18. Adds ProgrammeDefinition and ProgrammeLevelDefinition QA expectations.
19. Adds programme_mapping_hash / mapping-version QA across profiles, indexes, claims, Woo, Masteriyo, Tickera, reports, cache, and notifications.
20. Adds future mapped level, future programme, alias-normalisation, and future billing-term non-effect tests.
21. Adds mapping drift checks for unmapped PMPro levels, duplicate PMPro mappings, Woo products/variations granting unmapped levels, stale mapping hashes, and unknown/legacy rows.
22. Keeps fail-closed behaviour: unmapped PMPro levels and unknown/disabled programmes or levels must not grant benefits.
```
---

## 1. Objective

Perform final end-to-end QA, release hardening, staging validation, and production-readiness review for the **Voelgoed Member Perks** plugin.

This vertical slice must verify that all previous slices work together safely:

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
VS-013 — Performance and Cache Hardening
VS-014 — Notifications and Member Communications
```

It must also verify that the whole slice set follows the central programme/level mapping doctrine:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md

Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

This slice must not introduce major new features.

The goal is to prove, with evidence:

```text
The plugin is safe enough to move from staging to production.
```

If evidence is incomplete, the final status must say so.

---

## 2. Current Baseline Assumptions

This slice assumes the hardened slice set has been implemented or is mostly implemented.

If any slice is intentionally not implemented yet, the agent must:

```text
1. Identify it clearly.
2. Mark related tests as Not Applicable or Blocked.
3. Confirm the plugin degrades safely without that slice.
4. Document production risk.
5. Never pretend incomplete slices are complete.
```

Core slices that must exist before production readiness can be considered:

```text
VS-000
VS-001
VS-002
VS-003
VS-004
VS-005
VS-006
VS-007
VS-008
VS-011
VS-012
VS-013
```

Optional/integration slices may be marked Not Applicable if the business is not enabling that feature yet:

```text
VS-009 — Masteriyo Course Perks
VS-010 — Tickera Ticket Perks
VS-014 — Notifications and Member Communications
```

But if the site intends to launch those features in production, they must be tested before launch.

---

## 3. Critical Instruction to Novamira Agent

Use Novamira / Novamira Pro to inspect the real staging site.

Before claiming readiness:

```text
Inspect plugin files.
Inspect active plugins.
Inspect relevant database tables.
Inspect ACF field groups and options.
Inspect PMPro levels, groups, subscriptions, and active/cancelled membership states.
Inspect WooCommerce products, carts, checkout mode, orders, and order item meta.
Inspect Woo Subscriptions renewal behaviour and confirm it is not silently modified.
Inspect Masteriyo course structure if VS-009 exists.
Inspect Tickera event/ticket/Woo Bridge flow if VS-010 exists.
Inspect Bricks/Etch hub page configuration.
Inspect LiteSpeed/Cloudflare/cache behaviour where visible.
Run staging-only test flows with test users.
```

Do not rely only on theoretical code review.

This slice must produce an evidence-based final QA report.

If a test cannot be safely performed, document:

```text
Skipped
Reason skipped
Risk level
Alternative evidence used
Recommended follow-up
```

---

## 4. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Bug fixes
Small hardening patches
Missing guards
Missing admin warnings
Missing README/release notes
Final test scripts/checklists
Safe schema verification
Safe cache exclusion checks
Release checklist
Rollback plan
Production deployment notes
```

This slice must not implement:

```text
Major new business features
New discount types
New claim models
New course access models
New ticket generation strategies
Large admin redesign
New notification campaigns
CRM integrations
Cloudflare API automation
LiteSpeed server changes without explicit approval
Database deletion/cleanup without explicit approval
```

If a major issue is found:

```text
Stop and report it as a blocker.
Do not hide it behind a quick patch.
```

---

## 5. Required Deliverables

Add or update the following documentation files:

```text
docs/
├── RELEASE_CHECKLIST.md
├── STAGING_QA_REPORT.md
├── PRODUCTION_DEPLOYMENT_PLAN.md
├── ROLLBACK_PLAN.md
├── TEST_MATRIX.md
├── KNOWN_LIMITATIONS.md
└── OPERATIONAL_RUNBOOK.md
```

Optional folder structure:

```text
docs/qa/
docs/release/
docs/operations/
```

Possible small code additions if useful:

```text
includes/QA/
├── ReadinessChecker.php
├── DependencyChecker.php
├── SchemaChecker.php
├── IntegrationChecker.php
└── SmokeTestReporter.php
```

Optional admin page:

```text
Member Perks > Tools > Readiness Check
```

Update:

```text
README.md
```

Do not overbuild a full automated test framework unless the project already has one.

---

## 6. Final System Contract

The final system must honour this authority model:

```text
1. PMPro is the membership authority.
2. WooCommerce is the order/payment authority.
3. Woo Subscriptions is the recurring payment authority.
4. Masteriyo is the course delivery authority.
5. Tickera is the ticket delivery/check-in authority.
6. Voelgoed Member Perks is the perk eligibility, campaign, claim-scope, and redemption authority.
7. ACF is the admin editing interface/current configuration provider, not the runtime lookup authority.
8. Bricks/Etch query loops are display surfaces, not business logic authority.
9. Target/audience index table is the runtime lookup layer.
10. Redemption ledger is the usage/claim proof layer.
11. Caching must never leak member-specific data.
12. Notifications must be configured, traceable, rate-limited, and safe by default.
13. Runtime configuration access must be abstracted enough that a future custom admin provider can replace ACF without rewriting execution modules.
14. Programme and level structure is configuration, not hardcoded PHP.
15. PMPro level IDs, PMPro group IDs, Woo membership product IDs, Woo variation IDs, and billing terms are external identifiers and must be mapped.
16. Only enabled, mapped PMPro levels may grant programme benefits.
17. Future configured programmes and levels must be possible without rewriting Woo, Claims, Masteriyo, Tickera, Reports, Cache, or Notifications.
18. Unmapped or unknown programme/level values must fail closed and be reported, not guessed.
```

Any code or configuration that violates this model is a blocker or high-risk warning.

---

## 7. Programme and Membership Contract

The plugin must support the real Voelgoed membership model:

```text
A user may belong to:
- no programme
- Vriendinneklub only
- Voelgoednetwerk only
- both Vriendinneklub and Voelgoednetwerk
```

The user must normally have one active level per programme:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

If the user has multiple active levels inside the same programme:

```text
vriendinneklub:goud
vriendinneklub:silwer
```

Then the system must:

```text
1. Resolve using the configured rank rule.
2. Do not stack same-programme benefits.
3. Report the conflict for admin repair.
4. Preserve trace/debug information.
```

Launch/default programme keys for the current Voelgoed release:

```text
vriendinneklub
voelgoednetwerk
```

These are the default configured programmes for launch. They must be stable for current data, URLs, reporting, and QA evidence, but they are **not** a closed forever-list. Future programmes may be added only through the configured Programme Registry and Programme Level Mapping model.

Configured programme and level records must include, where relevant:

```text
ProgrammeDefinition
ProgrammeLevelDefinition
programme_key
programme_label
programme_aliases
level_key
level_label
level_aliases
level_rank
enabled
pmpro_level_ids
hub/upsell/display configuration
programme_mapping_hash or equivalent mapping version
```

Programme-aware values that should appear consistently where relevant:

```text
programme_key
level_key
qualified_path
eligibility_hash
membership_profile_hash
claim_scope
claim_period_key
```

Legacy flat values may be supported for migration/debug only, but must not be the new authority:

```text
membership_group
vriendinneklub_goud
voelgoednetwerk_goud
vg_perk_level_group
old_programme_key if used as an old programme alias
programme/level display text used as logic
```

Final QA must prove:

```text
1. Business structure is read from configuration.
2. External IDs are mapped.
3. Aliases normalize only through explicit configuration.
4. Unknown aliases do not grant benefits.
5. Runtime code never guesses from display text.
```

---

## 8. Dependency Inventory

Create dependency inventory in:

```text
docs/STAGING_QA_REPORT.md
```

Record:

```text
WordPress version
PHP version
Database engine/version if visible
Theme name/version
Active plugins relevant to this system
WooCommerce version
Woo Subscriptions version
Paid Memberships Pro version
ACF Pro version
Masteriyo version
Tickera version
Tickera WooCommerce Bridge version if active
Bricks version
Etch/EtchWP version
LiteSpeed Cache version if active
Redis/Object Cache status
Cloudflare usage notes if visible
Novamira/Novamira Pro context
Checkout mode: classic shortcode vs Woo Blocks
Cron strategy: WP-Cron only vs server cron
Programme mapping model version / document reference
Configured programme definitions
Configured programme level definitions
Programme aliases
Level aliases
Programme mapping hash/current mapping version
Mapped PMPro level IDs per programme level
Unmapped active PMPro levels found during audit
Woo subscription products/variations that grant PMPro levels
Billing terms currently configured: monthly/yearly/quarterly/etc.
```

If exact version cannot be detected:

```text
Write "not detected" or "not verified".
```

Do not guess.

---

## 9. Schema Verification

Verify plugin-owned tables:

```text
wp_vgmp_perk_targets
wp_vgmp_perk_redemptions
wp_vgmp_perk_notifications
```

For each table, check:

```text
Exists
Expected columns exist
Expected indexes exist
Schema version option exists
Charset/collation is sane
No obvious dbDelta mismatch
No nullable unique-key issue where uniqueness must be enforced
```

Expected options may include:

```text
vgmp_schema_version
vgmp_cache_version_membership_profiles
vgmp_cache_version_programme_mapping
vgmp_cache_version_perk_rules
vgmp_cache_version_perk_targets
vgmp_cache_version_active_perks
vgmp_cache_version_claims
vgmp_cache_version_reports
vgmp_cache_version_notifications
vgmp_cache_version_perk_config
vgmp_cache_version_programme_mapping
vgmp_programme_mapping_hash or equivalent stored mapping hash/version
```

If notification slice is not implemented:

```text
wp_vgmp_perk_notifications may be Not Applicable.
```

### Legacy schema drift checks

Inspect and report whether any old/legacy tables exist:

```text
wp_vg_perk_targets
wp_vg_perk_redemptions
wp_vg_perk_notifications
```

If old tables exist:

```text
Do not delete them automatically.
Report them as legacy drift.
Confirm whether they contain data.
Recommend a migration/cleanup plan if needed.
```

---

## 10. CPT and Taxonomy Verification

Verify:

```text
CPT vg_perk exists.
CPT is admin-visible.
CPT is not publicly exposed as a normal public archive/single unless intentionally configured.
CPT supports required fields.
Taxonomies exist and are attached to vg_perk.
Taxonomies show in admin where useful.
Runtime status terms exist or are creatable.
```

Expected taxonomies from the hardened model:

```text
vg_perk_programme
vg_perk_type
vg_perk_section
vg_perk_runtime_status
vg_perk_campaign
```

The old taxonomy may exist for backwards compatibility only:

```text
vg_perk_level_group
```

If `vg_perk_level_group` exists:

```text
Report whether it is used.
Confirm it is not the new runtime eligibility authority.
Do not rely on it for final QA unless legacy compatibility is intentionally active.
```

Required runtime status terms:

```text
active
upcoming
expired
paused
archived
misconfigured
```

---

## 11. ACF Verification

Verify ACF field groups and Options Page.

Check:

```text
Member Perk field group attached to vg_perk.
Schedule fields exist.
Benefit fields exist.
Target fields exist.
Audience/programme fields exist.
Claim fields exist.
Display/card fields exist.
Admin notes fields exist.
Options Page exists.
Programme registry settings exist.
Programme level mapping settings exist.
Programme aliases exist if configured.
Level aliases exist if configured.
Enabled/disabled flags exist for configured programmes and levels.
PMPro level ID mappings exist and are normalized.
Programme mapping hash/version can be recalculated or inspected.
Hub settings exist.
Feature flags exist.
Performance settings exist if VS-013 implemented.
Notification settings exist if VS-014 implemented.
```

Verify field names match code.

Important:

```text
If code expects perk_target_products but ACF uses a different field name, this is a blocker.
```

Run at least one save test:

```text
Create/edit a perk.
Save ACF fields.
Confirm target index/status/audience index logic still works.
```

ACF Local JSON checks:

```text
Plugin loads its own ACF JSON.
Plugin does not hijack unrelated site/theme ACF JSON.
Production does not accidentally overwrite plugin-controlled field definitions without review.
```



## 11A. ACF Runtime Boundary QA

The final QA must prove that ACF is boxed into the approved provider/admin layer.

Required checks:

```text
1. Search plugin code for direct ACF calls.
2. Confirm direct ACF calls are limited to approved provider/admin classes.
3. Confirm Woo execution classes do not call ACF directly.
4. Confirm Claims execution classes do not call ACF directly.
5. Confirm Masteriyo/Tickera execution classes do not call ACF directly.
6. Confirm Reports do not parse raw ACF fields for every row where a repository/index exists.
7. Confirm Hub queries do not use raw ACF fields as eligibility authority.
8. Confirm PerkConfigRepository / SettingsRepository are the only runtime config access points.
```

Allowed direct ACF call locations:

```text
includes/Config/AcfConfigProvider.php
includes/Content/AcfFields.php
includes/Admin/SettingsPage.php
includes/Admin/PerkMetaWarnings.php
admin-only preview/debug tools where explicitly safe
```

Prohibited direct ACF call locations:

```text
includes/Woo/*
includes/Claims/*
includes/Masteriyo/*
includes/Tickera/*
includes/Query/CurrentUserPerksQuery.php
includes/Query/PerkAudienceEvaluator.php
includes/Reports/ReportRepository.php
includes/Notifications/RecipientResolver.php
```

If a prohibited folder needs configuration, it must use:

```text
PerkConfigRepository
SettingsRepository
PerkRuleRepository
ClaimPolicy
PerkConfig
```

Static check example:

```bash
grep -R "get_field\|get_fields\|acf_" wp-content/plugins/voelgoed-member-perks/includes
```

Expected result:

```text
Only approved provider/admin classes contain direct ACF calls.
```

ACF inactive degradation test:

```text
Temporarily deactivate ACF on staging if safe.
Expected:
- plugin does not fatal
- admin shows clear warning
- execution modules fail closed
- existing custom tables remain readable
- no Woo discounts/free products apply from missing config
- claim endpoints deny with safe error
- reports still load where possible
```

If ACF cannot be deactivated safely:

```text
Inspect defensively and document skipped test, risk level, alternative evidence, and recommended follow-up.
```

Release blocker:

```text
If direct ACF calls exist inside Woo, Claims, Masteriyo, Tickera, or cache-sensitive hub/query paths, final status cannot be READY FOR PRODUCTION.
```

---

## 11B. Programme and Level Mapping Model QA

Final QA must verify that the implemented system follows:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Required checks:

```text
1. ProgrammeRegistry exposes launch/default programmes from configuration or a documented default registry.
2. ProgrammeMappingRepository or equivalent returns enabled ProgrammeDefinition and ProgrammeLevelDefinition records.
3. Vriendinneklub and Voelgoednetwerk are treated as launch/default programmes, not the only possible programmes forever.
4. PMPro level IDs are mapped external IDs, not hardcoded execution constants.
5. Woo subscription products and variations are billing surfaces, not perk-level authority.
6. Billing terms such as monthly, yearly, quarterly, annual, once-off, or campaign pricing do not create new perk levels unless intentionally mapped.
7. Programme aliases and level aliases normalize only through explicit configuration.
8. Alias normalization stores canonical programme_key and level_key values in runtime/audit records.
9. Unknown aliases do not grant benefits.
10. Duplicate PMPro mappings are detected and reported.
11. Unmapped active PMPro levels do not grant benefits.
12. Disabled programmes/levels do not grant benefits.
13. programme_mapping_hash or equivalent mapping version changes when programme definitions, level definitions, aliases, enabled flags, or PMPro level mappings change.
14. MembershipProfile, target/audience index, claims, Woo cart/order meta, course/ticket redemptions, reports, cache keys, and notification records preserve mapping context where relevant.
```

Required future-flexibility tests:

```text
Test A — Future billing term
Add a quarterly/term variation or test Woo billing product that grants an already-mapped PMPro level.
Expected: same programme/level resolves correctly without code changes.
Expected: no new perk level is created just because the billing term is new.

Test B — Future mapped level
Add a test PMPro level such as TEST_VRIENDINNE_PLATINUM and map it through settings.
Expected: resolver, hub, target/audience index, claims, reports, cache, and notifications understand it through configuration.
Expected: no PHP execution code needs a new hardcoded branch.

Test C — Unmapped PMPro level
Add or identify an active PMPro level not mapped to any enabled programme level.
Expected: user with only that level receives no member perks.
Expected: reports/admin tools show unmapped-level warning.

Test D — Future configured programme
If safe, add a disabled or staging-only test programme, for example TEST_PROGRAMME.
Expected: disabled programme grants nothing.
Expected: enabled + mapped programme can be queried/reported without rewriting execution modules.
Expected: unknown programme references fail closed.

Test E — Alias normalization
Configure a safe alias such as old_programme_key → vriendinneklub if needed.
Expected: alias normalizes to canonical key for stored runtime records.
Expected: member-facing labels remain friendly.
Expected: unconfigured aliases do not grant access.
```

Release blockers:

```text
1. Woo/Claims/Masteriyo/Tickera execution code checks specific PMPro level IDs directly.
2. Woo/Claims/Masteriyo/Tickera execution code checks specific Woo membership product or variation IDs as programme authority.
3. Runtime code assumes only two programmes can ever exist.
4. Runtime code assumes only Goud/Silwer/Brons or Brons/Silwer/Goud can ever exist.
5. Unmapped PMPro levels grant benefits.
6. Alias strings grant benefits without explicit mapping.
7. Cache keys for eligibility-dependent data omit programme_mapping_hash or equivalent mapping version.
8. Historical/redemption/report data silently remaps unknown legacy values instead of showing Unknown / Legacy.
```

---

## 12. PMPro MembershipProfile QA

Create or identify test users:

```text
No membership user
Vriendinneklub Goud user
Vriendinneklub Silwer user
Vriendinneklub Brons user
Voelgoednetwerk user
Dual-programme user
Same-programme conflict user if possible
Expired/cancelled member
Logged-out/public user
```

Verify:

```text
Goud monthly and Goud yearly map to Vriendinneklub Goud if intended.
Silwer monthly/yearly map correctly.
Brons monthly/yearly map correctly.
Voelgoednetwerk levels map correctly.
Dual-programme user resolves to both programmes.
Expired/cancelled member does not receive active paid benefits.
Logged-out user receives no member-only perks.
Same-programme conflict is detected and reported.
Mapped future test level resolves without code changes if configured.
Unmapped PMPro level fails closed and is reported.
New billing variation/term granting an existing PMPro level does not break profile resolution.
Programme aliases normalize only when explicitly configured.
programme_mapping_hash changes when PMPro mappings or aliases change.
```

Expected MembershipProfile shape conceptually:

```php
[
    "user_id" => 123,
    "programmes" => [
        "vriendinneklub" => [
            "level_key" => "goud",
            "status" => "active",
        ],
        "voelgoednetwerk" => [
            "level_key" => "silwer",
            "status" => "active",
        ],
    ],
    "has_any_membership" => true,
    "has_multiple_programmes" => true,
    "membership_profile_hash" => "..."
]
```

Do not expose real member PII in the final report.

Use test user labels:

```text
TEST_GOLD
TEST_BRONZE
TEST_DUAL
TEST_EXPIRED
```

---

## 13. Runtime Status QA

Create test perks:

```text
Future perk
Currently active perk
Expired perk
Paused perk
Archived perk
Misconfigured perk
No end date long-running perk
```

Verify:

```text
Future perk becomes upcoming.
Active perk becomes active.
Expired perk becomes expired.
Paused perk stays paused.
Archived perk stays archived.
Misconfigured perk is not active.
Long-running perk stays active if rules allow.
Cron/manual repair updates statuses.
Admin list status matches query behaviour.
```

Run runtime status repair tool if available.

Confirm:

```text
Invalid/missing required dates do not become active.
manual paused ≠ misconfigured.
```

---

## 14. Target and Audience Index QA

Create perks with these targets where available:

```text
Woo product
Woo product category
Masteriyo course
Tickera event
Tickera ticket
WordPress page
WordPress post
External URL
Programme audience
Programme-level audience
Shared/both-programme audience
```

Verify:

```text
Saving a perk writes index rows.
Changing targets updates index rows.
Removing targets removes index rows.
Deleting/trashing a perk removes target rows.
Reindex tool rebuilds rows.
Repository lookup returns correct perk IDs.
Invalid targets are skipped safely.
Audience index rows reflect programme and level targeting.
Audience index rows store canonical configured programme_key and level_key values, not aliases.
Unknown programme/level references are skipped or marked misconfigured, not silently accepted.
Stale _vgmp_last_indexed_programme_mapping_hash or equivalent mapping version is detected after mapping changes.
Dual-programme/shared perks index once without duplicate display behaviour.
Future mapped levels and future configured programmes can be indexed through configuration.
```

Document row counts before/after at least one test.

---

## 15. Hub Query Loop QA

Inspect configured hub pages:

```text
/my-voordele/
/my-vriendinneklub/
/my-voelgoed-netwerk/
```

or actual configured pages.

Verify:

```text
Main dashboard exists.
Vriendinneklub hub exists if enabled.
Voelgoednetwerk hub exists if enabled.
Hub uses Bricks/Etch query loops or documented safe fallback.
Query loops use current-user eligible active perks.
No-member user sees login/upsell/empty state.
Vriendinneklub-only user sees Vriendinneklub perks.
Netwerk-only user sees Netwerk perks.
Dual-programme user sees both.
Duplicate shared perks display once.
Goud-only perk is hidden from Brons user.
Future mapped level perks display through configuration, not hardcoded level branches.
Disabled or unknown programme hub requests fail closed or show safe empty/upsell state.
Programme aliases in URLs/settings normalize only when configured.
Expired/upcoming/paused/archived/misconfigured perks do not show in active sections.
Sections filter correctly.
```

Sections to verify where applicable:

```text
New this week
Courses
Products
Events
Replays/downloads
Partner deals
Expiring soon
Already claimed
```

If Bricks/Etch integration uses fallback query instructions, document exact configuration.

---

## 16. Claims and Redemption Ledger QA

This QA now belongs before Woo discount final validation because the ledger is VS-007.

Create test perks with claim rules:

```text
Once per user
Per programme
Per programme level
Per order
Unlimited
Total limit
Monthly reset
Already-claimed hidden
Already-claimed visible
```

Verify:

```text
Claim creates redemption row.
Duplicate claim does not create duplicate row.
Per-user limit blocks second claim.
Per-programme claim scope works for dual-programme user.
Per-programme-level claim scope works.
Total limit blocks after configured maximum.
Cancelled/refunded/failed statuses behave according to documented rules.
Already-claimed display state works.
Claim endpoints require login/nonce.
Users cannot claim for another user.
```

Verify redemption table records programme-aware context:

```text
user_id
perk_id
target_type
target_id
target_value
order_id if applicable
order_item_id if applicable
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash or equivalent mapping version
benefit_type
status
claim_scope
claim_period_key
source
idempotency_key
claimed_at
updated_at
```

Document sample redemptions using test users only.

---

## 17. WooCommerce Product Discount QA

Create at least these test cases:

```text
Percentage discount
Fixed discount
Fixed member price
Free product/R0 product
Product category discount
Variation product discount
Multiple perks on same product
Coupon compatibility
Expired perk no discount
Ineligible user no discount
Logged-out user no discount
Dual-programme user with two qualifying discounts
Claim-limited discount
```

Verify:

```text
Discount applies only to eligible users.
Discount does not apply to ineligible users.
Discount does not apply to logged-out users.
Discount does not compound on cart refresh.
Fixed member price does not increase customer price.
Free product becomes R0 only for eligible member.
Multiple perks choose deterministic winner.
Dual-programme discounts do not stack by default.
Coupon compatibility follows documented rule.
Claim limits are checked before applying.
Order item meta records applied perk and programme context.
Redemption/order record is created after checkout, not abandoned cart.
```

Also verify:

```text
Admin order editing does not accidentally modify product prices.
Product prices are not changed globally.
Woo Subscriptions renewal pricing is not silently changed.
New billing terms/variations do not break mapped eligibility.
Execution code does not hardcode PMPro level IDs, Woo membership product IDs, Woo variation IDs, billing terms, or fixed level labels.
Cart/order meta records canonical programme_key and level_key plus programme_mapping_hash where implemented.
Normal frontend cart/checkout only is affected.
```

Checkout mode must be documented:

```text
Classic shortcode checkout: tested/supported.
Woo Blocks checkout: tested/supported or not supported with warning.
```

---

## 18. Masteriyo Course Perk QA

Only perform if VS-009 is implemented and Masteriyo is active.

Verify:

```text
Masteriyo detected safely.
Course post type verified.
Enrolment/access API verified.
Eligible user can claim course.
Ineligible user cannot claim course.
Expired/misconfigured perk cannot be claimed.
Already-enrolled user handled safely.
Claim creates/updates redemption row.
Course access is actually granted.
Hub card shows correct course state.
Normal paid course purchase still works if applicable.
Dual-programme user does not get duplicate course grants unless claim scope permits.
Future mapped levels and configured programmes work through MembershipProfile/ClaimPolicy rather than Masteriyo-specific hardcoding.
Course redemption records canonical programme/level context and mapping hash where implemented.
No destructive revocation occurs unless explicitly implemented and approved.
```

If Masteriyo is inactive or no safe API exists:

```text
Document as Not Available / Not Implemented.
Confirm plugin degrades safely.
```

Do not fake course integration readiness.

---

## 19. Tickera Ticket Perk QA

Only perform if VS-010 is implemented and Tickera is active.

Verify:

```text
Tickera detected safely.
Tickera WooCommerce Bridge status verified.
Ticket/event/product mapping verified.
Eligible user can claim ticket into cart.
Ineligible user cannot claim.
Expired/misconfigured perk cannot claim.
Ticket quantity limit works.
Free ticket becomes R0 through Woo cart.
Discounted ticket applies correct price.
Checkout creates Woo order.
Tickera ticket is generated normally.
Redemption row records order/order item.
Existing non-perk ticket sales still work.
Scanning/check-in flow is not broken.
Dual-programme user does not receive duplicate ticket benefit unless claim scope permits.
Future mapped levels and configured programmes work through MembershipProfile/ClaimPolicy rather than Tickera-specific hardcoding.
Ticket cart/order/redemption records canonical programme/level context and mapping hash where implemented.
```

If generated ticket ID linkage is implemented, verify it.

If not, document limitation.

Do not claim ticket readiness unless a real staging order generated a valid Tickera ticket.

---

## 20. Reports QA

Verify report pages:

```text
Dashboard
Perk Status
Redemptions
Performance
Issues/Misconfiguration
Programme/MembershipProfile report if implemented
CSV export
```

Check:

```text
Reports are admin-only.
Counts match test data.
Programme filters work.
Level filters work.
Dual-member reporting works.
Conflict reporting works.
Filters work.
CSV export respects filters.
Reports paginate.
Reports do not fatal if optional plugins inactive.
Issues report detects misconfigured perks.
Legacy table/field warnings appear if legacy drift exists.
Programme filters are generated from configured ProgrammeDefinition rows.
Level filters are generated from configured ProgrammeLevelDefinition rows.
Unknown/legacy programme and level values are shown as Unknown / Legacy, not silently remapped.
Mapping drift report detects unmapped PMPro levels, duplicate mappings, stale mapping hashes, and Woo products/variations granting unmapped levels.
```

Confirm reports are operational, not accounting/audited financial reports.

---

## 21. Admin UX QA

Verify:

```text
Admin columns display.
Admin filters work.
Programme badges display correctly.
Claim-scope information is visible where useful.
Duplicate perk creates draft copy.
Duplicate does not copy redemptions.
Pause action works.
Resume action works.
Archive action works.
Bulk actions work.
Reindex tool works.
Runtime repair tool works.
Health check works.
Programme mapping inspector works if implemented.
Legacy data inspector works if implemented.
Capability inspector works.
Programme mapping inspector shows configured programmes, levels, aliases, PMPro mappings, stale mapping hashes, and unmapped-level warnings.
Programme mapping cache clear/rebuild tool works if implemented.
Future level/programme rows appear dynamically in admin filters and columns.
Non-admin users cannot access tools.
```

Confirm:

```text
Pause/archive preserves history.
No destructive cleanup runs automatically.
```

---

## 22. Performance and Cache QA

Verify:

```text
Request cache reduces repeated resolver calls.
Object cache wrapper works or falls back safely.
Main dashboard is not publicly cached for logged-in users.
Vriendinneklub hub is not publicly cached for logged-in users.
Voelgoednetwerk hub is not publicly cached for logged-in users.
Current-user REST endpoints send private/no-store headers.
Cart/checkout pages are not publicly cached.
Claim endpoints are not publicly cached.
Cache invalidates after perk save.
Cache invalidates after claim.
Cache invalidates after runtime status change.
Cache invalidates after target/audience reindex.
Goud user cache does not leak to Brons user.
Vriendinne-only cache does not leak to Netwerk-only user.
Dual-programme hub cache does not leak to single-programme users.
Eligibility-dependent cache keys include programme_mapping_hash or equivalent mapping version.
Changing programme aliases, level aliases, enabled flags, or PMPro mappings invalidates relevant profile/hub/claims/report/notification caches.
Future configured programme hub output uses its own safe cache context and does not collide with launch programme hubs.
```

If LiteSpeed Cache is active, document:

```text
Hub page cache exclusion status
Cart/checkout/account exclusion status
REST endpoint exclusion guidance/status
Any uncertainty
```

If Cloudflare is active, document:

```text
Whether rules were inspected
Recommended bypass/cache rules
Any uncertainty
```

Do not claim Cloudflare or LiteSpeed rules were changed unless actually changed and tested.

---

## 23. Notification QA

Only perform if VS-014 is implemented.

Verify:

```text
Notifications disabled by default or staging dry-run active.
Test email works or fails safely.
Claim confirmation queues after redemption if enabled.
Queue processes in batches.
Idempotency prevents duplicate notifications.
Digest preview shows only eligible perks.
Dual-programme digest sends one email per user per period, grouped by programme.
Shared perk does not appear twice in a digest.
Expiring perk preview works.
Admin alerts can be generated.
Opt-out is respected for marketing-style digests.
Notification logs are admin-only.
No mass email sends happen without explicit approval.
Future mapped levels appear in digests through configuration if enabled.
Future configured programmes appear in grouped digests through configuration if enabled.
Notification idempotency keys include programme_mapping_hash or equivalent mapping version where membership profile context affects content.
Queued notifications are rechecked or skipped if programme mapping changes materially before send.
```

On staging:

```text
Confirm real members are not emailed accidentally.
```

Do not enable production bulk sending during QA unless explicitly instructed.

---

## 24. Security QA

Verify:

```text
Admin pages require manage_vg_perks.
REST claim endpoints require login.
REST claim endpoints require nonce where applicable.
Users cannot submit user_id to claim for someone else.
Users cannot submit discount amount.
Users cannot force arbitrary perk IDs onto cart items.
Users cannot force programme_key/level_key/eligibility_hash from frontend.
All admin actions use nonces.
All output is escaped.
SQL uses prepared statements.
Plugin does not expose admin notes to frontend.
Reports are not public.
Notification logs are not public.
Direct ACF calls are absent from prohibited runtime/hot-path classes.
Direct PMPro level ID checks are absent from Woo/Claims/Masteriyo/Tickera execution paths except inside approved resolver/mapping classes.
Direct Woo membership product/variation ID checks are absent from eligibility authority paths except inside approved mapping/audit classes.
Frontend requests cannot submit programme_key, level_key, qualified_path, eligibility_hash, membership_profile_hash, programme_mapping_hash, or discount amount as trusted authority.
Unmapped PMPro levels, unknown aliases, and disabled configured levels fail closed.
```

Check edge cases:

```text
Invalid perk ID
Invalid target ID
Invalid nonce
Logged-out request
Expired membership
Cancelled membership
Expired perk
Archived perk
Misconfigured perk
Deleted target
Legacy table exists
Old membership_group metadata exists
```

---

## 25. Dependency Failure QA

Temporarily disable optional plugins on staging only if safe.

Check plugin behaviour with:

```text
WooCommerce inactive
Woo Subscriptions inactive
PMPro inactive
ACF inactive
Masteriyo inactive
Tickera inactive
Tickera WooCommerce Bridge inactive
Bricks/Etch inactive
LiteSpeed Cache inactive
Object cache inactive
```

Expected:

```text
No fatal errors.
Affected features disable safely.
Admin warnings explain missing dependencies.
Core admin plugin remains accessible.
```

Do not run these tests on production.

If disabling a plugin is unsafe, inspect defensively and document why the test was skipped.

---

## 26. Data Integrity QA

Verify:

```text
Deactivating plugin does not delete data.
Reactivating plugin preserves data.
Plugin-owned tables are not dropped on deactivation.
Existing perks remain intact.
Existing target/audience index rows remain intact unless explicitly reindexed.
Existing redemptions remain intact.
Existing notifications remain intact.
Existing Woo orders remain intact.
Existing Tickera tickets remain intact.
Existing Masteriyo course access remains intact.
Trashing a perk cleans target/audience index rows if implemented.
Redemptions preserve historical references.
Order item meta remains on completed orders.
```

Run a backup before destructive staging tests.

Do not permanently delete production-like data during QA.

---

## 27. User Journey Test Matrix

Create:

```text
docs/TEST_MATRIX.md
```

Minimum journeys:

### Journey A — Vriendinneklub Goud sees hub perks

```text
Login as TEST_VRIENDINNE_GOLD.
Visit /my-voordele/ and /my-vriendinneklub/.
Confirm eligible perks appear.
Confirm ineligible perks do not appear.
```

### Journey B — Brons does not see Goud-only perk

```text
Login as TEST_VRIENDINNE_BRONZE.
Confirm Goud-only perk hidden.
```

### Journey C — Voelgoednetwerk member sees Netwerk hub

```text
Login as TEST_NETWERK.
Visit /my-voelgoed-netwerk/.
Confirm Netwerk perks appear.
Confirm Vriendinne-only perks do not appear unless shared.
```

### Journey D — Dual-programme member sees both

```text
Login as TEST_DUAL.
Visit /my-voordele/.
Confirm both programme sections/perks appear.
Confirm shared perks display once.
```

### Journey E — Product discount

```text
Eligible user adds targeted product.
Discount applies.
Checkout creates order.
Redemption/order meta recorded.
```

### Journey F — Free product

```text
Eligible user adds free-perk product.
Price becomes R0.
Checkout creates order.
Claim limit respected after purchase.
```

### Journey G — Course claim

```text
Eligible user claims course.
Course access granted.
Redemption recorded.
Hub updates.
```

### Journey H — Ticket claim

```text
Eligible user claims ticket.
Ticket added to cart.
Checkout creates Woo order.
Tickera ticket generated.
Redemption recorded.
```

### Journey I — Expired/misconfigured perk

```text
Expired/misconfigured perk does not show.
Expired/misconfigured perk cannot apply/claim.
```

### Journey J — Admin weekly workflow

```text
Admin duplicates a perk.
Changes dates/targets/audience.
Publishes/schedules.
Old perk expires automatically.
Reports show both.
```

### Journey K — Cache safety

```text
Goud, Brons, Netwerk, and Dual users alternate hub/cart tests.
No cross-user leakage.
```

### Journey L — Notification dry run

```text
Admin previews digest.
Dual-programme digest groups correctly.
No real member email sent on staging.
```

### Journey M — Future mapped level

```text
Add or simulate TEST_VRIENDINNE_PLATINUM as a mapped level.
Confirm resolver, hub, claim eligibility, reports, and cache identity work through configuration.
Confirm no execution code requires hardcoded Platinum logic.
```

### Journey N — Unmapped PMPro level fails closed

```text
Add or simulate TEST_UNMAPPED_PMPro level.
Confirm user receives no perks from that level.
Confirm admin/report tools show unmapped-level warning.
```

### Journey O — Future billing term does not change perks

```text
Add or simulate quarterly/term billing variation for an existing mapped PMPro level.
Confirm billing term does not create a new perk level.
Confirm member still resolves to the mapped programme/level.
```

### Journey P — Future configured programme safety

```text
Add or simulate disabled TEST_PROGRAMME.
Confirm disabled programme grants no benefits.
If enabled and mapped on staging, confirm hub/report/cache/notification paths handle it through configuration.
```

Mark each journey:

```text
Pass
Fail
Blocked
Not Applicable
```

---

## 28. Production Deployment Plan

Create:

```text
docs/PRODUCTION_DEPLOYMENT_PLAN.md
```

Include:

```text
Pre-deployment file backup
Pre-deployment database backup
Plugin version to deploy
Git tag/commit or package name
Files to deploy
Database migration expectations
Plugin-owned table schema expectations
Required active plugins
Required ACF fields/options
Required PMPro programme mappings
Required ProgrammeDefinition and ProgrammeLevelDefinition records
Required programme aliases / level aliases if used
Current programme_mapping_hash or mapping version
Required hub pages
Required cron jobs
Required cache exclusions
Required Cloudflare/LiteSpeed checks
Post-deployment smoke tests
Rollback checkpoint
Sign-off owner
```

Deployment must include:

```text
Full files backup
Database backup
Staging-tested version tag/commit
Maintenance window if needed
Rollback plan ready
Cache clear plan
```

Do not deploy without backup.

---

## 29. Rollback Plan

Create:

```text
docs/ROLLBACK_PLAN.md
```

Include:

```text
How to deactivate plugin safely
How to restore previous plugin version
How to restore database backup if schema/data issue occurs
What data is preserved on deactivation
What features stop working if plugin is disabled
How Woo orders/redemptions are handled after rollback
How course/ticket access is handled after rollback
How to clear plugin/object/LiteSpeed/Cloudflare caches after rollback
How to communicate rollback internally
```

Important:

```text
Deactivating plugin should stop new perk execution but should not delete perks, targets, redemptions, notifications, orders, tickets, or courses.
```

If rollback requires database restore, state that clearly.

---

## 30. Operational Runbook

Create:

```text
docs/OPERATIONAL_RUNBOOK.md
```

Include weekly/monthly admin tasks:

```text
Create new perk
Duplicate existing perk
Set programme/audience rules
Set claim scope
Pause perk
Archive perk
Schedule next week’s perks
Run target/audience reindex
Run runtime repair
Check active/upcoming/expired/misconfigured reports
Check redemptions report
Check issue report
Check failed notifications
Clear plugin caches
Preview notification digest
Verify hub as test member
```

Support tasks:

```text
Check if user is eligible
Check MembershipProfile
Check if user claimed perk
Check why discount did not apply
Check related Woo order
Check course access
Check ticket/order relation
Check whether cache is stale
Check whether notification was queued/sent/skipped
```

---

## 31. Known Limitations

Create:

```text
docs/KNOWN_LIMITATIONS.md
```

Document all limitations honestly.

Possible examples:

```text
Subscription renewal discounts are not automatically modified.
Ticket inventory reservation at cart stage is not implemented.
Course access revocation is not automated unless explicitly implemented.
Cloudflare rules are guidance unless manually configured.
Notification bulk sending is disabled/dry-run until approved.
Reports are operational, not audited financial reports.
Direct CRM integration is not implemented.
Woo Blocks checkout may require separate compatibility work if not tested.
Old legacy tables/metadata may exist until migration/cleanup is approved.
ACF remains the MVP admin editing provider; replacing it with fully custom admin screens is a future slice, not part of this release.
Programme and level structure is configurable, but changing launch programme keys after production requires a migration/reindex plan.
Unmapped PMPro levels intentionally receive no perks until mapped.
Future programmes require registry/mapping/hub/report/cache/notification QA before production use.
```

Do not hide limitations.

---

## 32. Readiness Checker Optional Tool

Optional but useful.

Create:

```php
Voelgoed\MemberPerks\QA\ReadinessChecker
```

Admin location:

```text
Member Perks > Tools > Readiness Check
```

Checks:

```text
Required tables exist.
Required CPT/taxonomies exist.
Required runtime terms exist.
ACF active/fields available.
PMPro active/programme mappings configured.
ProgrammeDefinition and ProgrammeLevelDefinition rows configured.
Programme aliases / level aliases valid.
No duplicate PMPro mappings.
No unexpected unmapped active PMPro levels that should grant benefits.
programme_mapping_hash available/current.
Hub pages configured.
Feature flags reviewed.
Cache safety warnings.
Runtime cron configured or warning.
Reports accessible.
Notification dry-run status.
Legacy drift detected.
Known dependency issues.
```

Output:

```text
Ready
Ready with warnings
Not ready
```

This is not a substitute for manual QA.

---

## 33. Release Versioning

Set a clear plugin version before production release.

Recommended if first production-ready version:

```text
1.0.0
```

If still beta:

```text
0.9.0-beta
```

Do not call it 1.0.0 unless:

```text
Core flows tested.
Rollback plan exists.
Staging QA passed.
Management accepts known limitations.
No material blockers remain.
```

Update plugin header and README version notes.

---

## 34. Logging Review

Verify logs are useful and not noisy.

Check logs for:

```text
Failed claims
Failed reservations
Failed redemptions
Target/audience index errors
Runtime status updates
Woo discount application errors
Course claim failures
Ticket claim failures
Notification send failures
Cache invalidation issues
Legacy drift warnings
```

Ensure:

```text
No sensitive customer data is unnecessarily logged.
Logs are not public.
Logs do not grow without limit if file-based.
```

If log rotation is not implemented, document limitation.

---

## 35. Cron Review

Verify scheduled tasks:

```text
Runtime status updates
Notification queue processing if enabled
Expiring perk checks if enabled
Any cleanup/repair jobs if implemented
```

Confirm:

```text
WP-Cron strategy is documented.
Server cron recommended.
No task runs excessively.
No task sends mass emails unintentionally.
```

Recommended note:

```text
Use real server cron to call wp-cron.php reliably, especially because perks and notifications are time-sensitive.
```

---

## 36. Cache Exclusion Checklist

Document and verify where possible:

```text
/my-voordele/ excluded from public cache for logged-in users.
/my-vriendinneklub/ excluded from public cache for logged-in users.
/my-voelgoed-netwerk/ excluded from public cache for logged-in users.
/cart/ excluded.
/checkout/ excluded.
/my-account/ excluded.
/wp-json/vg-member-perks/* not publicly cached.
Claim endpoints not publicly cached.
Notification admin pages not cached.
Reports admin pages not cached.
```

If using Cloudflare:

```text
Ensure no Cache Everything rule catches member hub/cart/checkout/account.
```

If using LiteSpeed Cache:

```text
Ensure logged-in/private behaviour is safe.
```

Do not overstate what was verified.

---

## 37. Final Acceptance Criteria

VS-015 is complete only when:

```text
1. Dependency inventory is documented.
2. Schema verification is complete.
3. Legacy schema drift check is complete.
4. CPT/taxonomy verification is complete.
5. ACF/options verification is complete.
6. PMPro MembershipProfile tests pass or blockers are documented.
6A. Programme and Level Mapping Model QA passes or blockers are documented.
6B. Future billing term, future mapped level, unmapped PMPro level, alias normalization, and future programme tests pass or are documented as skipped with risk.
7. Runtime status tests pass.
8. Target/audience index tests pass.
9. Hub query-loop tests pass.
10. Claims/redemption tests pass.
11. Woo discount tests pass.
12. Masteriyo course tests pass or are marked Not Applicable with safe degradation.
13. Tickera ticket tests pass or are marked Not Applicable with safe degradation.
14. Reports/admin UX tests pass.
15. Cache safety tests pass.
16. Notification tests pass or are disabled/dry-run with documentation.
17. Security checks pass.
18. Dependency failure behaviour is verified or documented.
19. Production deployment plan exists.
20. Rollback plan exists.
21. Operational runbook exists.
22. Known limitations are documented.
23. README is updated.
24. Final readiness status is declared honestly.
```

Readiness statuses:

```text
READY FOR PRODUCTION
READY WITH WARNINGS
NOT READY — BLOCKERS FOUND
```

Do not declare ready if material blockers remain.

---

## 38. README Update Requirements

Update README with:

```text
Final architecture overview
Installed slice list
Minimum dependency requirements
Programme model summary
Programme and Level Mapping Model summary
Configured launch/default programme keys
Future programme/level workflow and fail-closed rules
MembershipProfile summary
Admin workflow summary
Member hub summary
Woo discount summary
Claims/redemption summary
Masteriyo integration status
Tickera integration status
Reports summary
Performance/cache policy
Notification policy
Production deployment link
Rollback link
Known limitations link
Version/release notes
Support/debug notes
```

Include warning:

```text
The member hub, cart, checkout, current-user REST endpoints, and claim endpoints contain member-specific data and must not be served from public/shared full-page cache.
```

Include operational rule:

```text
Expired perks should be archived or kept for reporting, not deleted casually.
```

---

## 39. Explicit Out-of-Scope List

Do not implement these in VS-015:

```text
New perk types
New discount models
Subscription renewal discount policy
Ticket inventory reservation at cart stage
Course access revocation automation
CRM/newsletter integrations
Cloudflare API automation
LiteSpeed server config automation
Major frontend redesign
Formal accounting reports
Custom scanner/check-in logic
Custom ticket PDF generation
Legacy data deletion/migration unless separately approved
Renaming production programme keys without a planned migration/reindex process
Automatic mapping of every PMPro level to perks
```

These require separate future slices.

---

## 40. Required Final Agent Report

After QA and hardening, provide an evidence-based final report with:

```text
Final readiness status
Files created
Files modified
Docs created
Dependency inventory
Schema verification results
Programme/level mapping verification results
programme_mapping_hash / mapping version evidence
Future flexibility tests performed
Legacy drift findings
Main user journeys tested
Admin journeys tested
Woo tests performed
Masteriyo tests performed or skipped reason
Tickera tests performed or skipped reason
Cache safety results
Notification safety results
Security findings
Blockers
Warnings
Known limitations
Production deployment recommendation
Rollback summary
```

Use one of these final status formats:

```text
Final Status: READY FOR PRODUCTION
```

or:

```text
Final Status: READY WITH WARNINGS
```

or:

```text
Final Status: NOT READY — BLOCKERS FOUND
```

Do not use optimistic wording if the evidence does not support it.

---

## 41. Future Slice Candidates

After VS-015, possible future slices include:

```text
VS-016 — Subscription Renewal Benefit Policy
VS-017 — Ticket Inventory Reservation
VS-018 — Course Access Revocation Policy
VS-019 — CRM/Brevo/FluentCRM Integration
VS-020 — Advanced Analytics and Looker Studio Export
VS-021 — Member Preference Centre
VS-022 — ESI/Fragment Cache Advanced Optimisation
VS-023 — Automated Campaign Calendar
VS-024 — Partner Deal Portal
VS-025 — Legacy Data Migration and Cleanup
VS-026 — Woo Blocks Checkout Compatibility
VS-027 — Fully Custom Admin Configuration Provider replacing ACF
VS-028 — Programme Key Rename / Migration Toolkit
VS-029 — Advanced Programme Lifecycle and Member Migration Tools
```

Do not start these during VS-015.
