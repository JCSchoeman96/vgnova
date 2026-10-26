# VG Nova VS1 Preflight & Authority Gates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce fresh, evidence-backed authority and runtime findings for the VS1 Golden Journey so later implementation plans can be exact and do not duplicate or contradict current VG Nova architecture.

**Architecture:** This plan is deliberately read-only with respect to WordPress business/runtime state. It verifies the current Git baseline, current assembled Platform Core/runtime, canonical relationship authorities, FCM, Vriendinne billing/access/perks, Course commerce/enrolment, My Voelgoed, email emitters, analytics/search/ranking, and representative real data. It ends in a Master Gate that either authorizes bounded implementation planning or stops on explicit architecture decisions.

**Tech Stack:** WordPress, PHP, Bricks Builder, ACF, WooCommerce, WooCommerce Subscriptions, Paid Memberships Pro, Voelgoed Member Perks, Masteriyo, Tickera, FluentCRM, GA4/GTM, Novamira MCP, Bricks MCP + Bricks Skills, Git/GitHub.

**Spec:** `docs/superpowers/specs/2026-10-25-vs1-golden-journey-product-tech-spec_v0.2.0.md`

If that spec has not yet been committed, first add the approved v0.2.0 document to that exact path unchanged except for repository-local link normalization.

## Current observed repository checkpoint

At prompt-writing time, PR #58 was merged. The observed merge baseline was:

```text
origin/main = 50ee4bee1ae1405522df7b67634b8935d4e9d9ed
```

This SHA is a historical checkpoint, not an execution base. Execution MUST re-fetch and verify current `origin/main` before branching.

At the PR #58 checkpoint, canonical Person count was at least one. Lynette Beer (ID `1705`, slug `lynette-beer`, status `publish`) exists, but PR #58 itself added no canonical Topic, Event Participation, Product Contribution, or Course relationships for her.

Current source evidence already shows that Product Contribution progressed beyond the earlier Task-5 handoff and that `PersonProductsBricks` exists in current Platform Core source. The executor must therefore use fresh current-state documents and source, not the stale earlier handoff status.

## Global Constraints

- ALWAYS re-read current `origin/main` before beginning; never branch blindly from the historical SHA above.
- Verify the exact VG Nova Local URL, ABSPATH and database before runtime inspection.
- Preflight is READ-ONLY with respect to WordPress business data unless a later explicitly approved task says otherwise.
- Do not create or edit ACF groups, Bricks templates, posts, users, orders, subscriptions, memberships, Course enrolments, FCM options, or plugin settings during this plan.
- Do not run mutating setup/migration/seeding commands during this plan.
- Do not manually copy Platform Core source into the runtime plugin directory.
- Platform Core runtime is assembled from the tracked root plugin + `site-code/voelgoed-platform-core` overlay according to the current assembly contract.
- `content_topic` remains the canonical semantic spine.
- Native providers remain authoritative for their domains.
- Product Contribution must be CONSUMED, not reimplemented.
- Member Perks must be CONSUMED for benefit/discount decisions, not duplicated in Bricks or Journey code.
- Do not infer Person→Course authority from Woo Product→Person.
- No arbitrary `return_to` URL is ever accepted as future redirect authority.
- No price, discount, tier, entitlement, or access state may be trusted from client-side state.
- One active commercial Journey Intent is the VS1 design, but no Journey Intent code is authorized by this preflight plan.
- No behavioural FluentCRM automation is in VS1.
- Exact page layout is secondary to proving flow, relationship and authority contracts.
- Use Novamira for WordPress/runtime/data/ACF inspection and Bricks MCP + Bricks Skills for Bricks/template/component inspection.
- Record every important finding with both source evidence and runtime evidence where both exist.
- When evidence conflicts, stop and classify the conflict; do not silently choose one source.

## Review Focus

1. **Stale architecture evidence:** a historical handoff says a subsystem is incomplete but current `main` has advanced; expected behaviour is to use current source/runtime and explicitly supersede stale status.
2. **False relationship authority:** a Person appears linked to a Course only because its Woo Product has Product Contribution; expected behaviour is to prove a Course-specific canonical relationship or raise `ARCHITECTURE_DECISION_REQUIRED`.
3. **Runtime/source drift:** tracked Platform Core and active runtime differ; expected behaviour is to report exact drift and stop before relying on runtime behaviour.
4. **Duplicate transactional email authority:** Woo/PMPro/Masteriyo/FluentCRM all emit around one business event; expected behaviour is to inventory each emitter before any suppression decision.
5. **Synthetic/QA data mistaken for production data:** a convenient record satisfies the flow but has test/fixture provenance; expected behaviour is to reject it from the representative-data contract unless explicitly approved.

---

## File Structure

This preflight creates evidence documents only.

**Create:**

```text
docs/architecture/VS1_CURRENT_REALITY_AUDIT_v1.0.0.md
docs/architecture/VS1_FCM_PREFLIGHT_v1.0.0.md
docs/architecture/VS1_DISCOVERY_AUTHORITY_GATE_v1.0.0.md
docs/architecture/VS1_MEMBERSHIP_COMMERCE_GATE_v1.0.0.md
docs/architecture/VS1_COURSE_FULFILMENT_EMAIL_GATE_v1.0.0.md
docs/architecture/VS1_DISCOVERY_SERVICES_GATE_v1.0.0.md
docs/architecture/VS1_REPRESENTATIVE_DATA_CONTRACT_v1.0.0.md
docs/architecture/VS1_MASTER_GATE_v1.0.0.md
```

**Do not create production PHP/JS/CSS or mutate Bricks/ACF/runtime state in this plan.**

If repeatable read-only probes are required, place them under `site-code/runtime-tests/`, but only when an existing script/tool cannot produce deterministic evidence. Any new probe must be demonstrably read-only and receive its own review.

---

### Task 1: Rebaseline Git, Platform Core Assembly, and Runtime Identity

**Files:**
- Create: `docs/architecture/VS1_CURRENT_REALITY_AUDIT_v1.0.0.md`
- Read: current Platform Core assembly contract
- Read: `site-code/voelgoed-platform-core/README.md`
- Read: `voelgoed-platform-core/includes/Plugin.php`
- Read: active runtime plugin bootstrap and `includes/Plugin.php` through read-only runtime access

**Interfaces:**
- Consumes: current repository, current Local WordPress runtime.
- Produces: frozen VS1 preflight baseline and source/runtime parity verdict used by every later task.

- [ ] **Step 1: Verify the current Git baseline**

Run in the canonical checkout:

```bash
git fetch origin
git status --short
git rev-parse HEAD
git rev-parse origin/main
git merge-base HEAD origin/main
```

Record current branch, `HEAD`, `origin/main`, checkout cleanliness, and any intentionally preserved local changes.

- [ ] **Step 2: Record the latest merged subsystem state**

Read current Product Contribution, Event Participation, Platform Core assembly/runtime-mutation, and My Voelgoed current-state docs. Record which earlier handoffs are superseded.

Required:

```text
PRODUCT_CONTRIBUTION_CURRENT_STATE=COMPLETE|PARTIAL|CONFLICT
EVENT_PARTICIPATION_CURRENT_STATE=COMPLETE|PARTIAL|CONFLICT
MY_VOELGOED_CURRENT_STATE=COMPLETE|PARTIAL|CONFLICT
```

- [ ] **Step 3: Verify runtime identity**

Through Novamira/read-only runtime inspection record:
- site URL;
- ABSPATH;
- database name;
- active theme;
- Bricks version;
- ACF version;
- WooCommerce version;
- Woo Subscriptions version;
- PMPro version;
- Masteriyo version;
- Tickera version;
- FluentCRM version;
- Voelgoed Member Perks version;
- active Platform Core version/build identity if exposed.

- [ ] **Step 4: Verify Platform Core source/runtime parity**

Use the current assembly contract and build verifier where applicable.

```bash
python3 site-code/bin/build-platform-core.py --ref "$(git rev-parse HEAD)" --output dist
python3 site-code/bin/check-platform-core-build.py --ref "$(git rev-parse HEAD)" --verify-output dist
```

Do not deploy. Compare active runtime inventory/hashes against current controlled deployment evidence or manifest.

Required:

```text
SOURCE_RUNTIME_PARITY=PASS|DRIFT|UNKNOWN
```

- [ ] **Step 5: Record current canonical entity counts**

Read-only counts for:
- published WordPress Posts;
- canonical `person`;
- Masteriyo Courses;
- Woo Products;
- Tickera Events;
- Product Contributions;
- Event Participations;
- root `content_topic` terms and relationships.

Separate real records from QA/system-test/legacy records.

- [ ] **Step 6: Finish the current-reality audit**

Required summary:

```text
REPO_BASELINE=
RUNTIME_IDENTITY=
SOURCE_RUNTIME_PARITY=
PRODUCT_CONTRIBUTION_CURRENT_STATE=
EVENT_PARTICIPATION_CURRENT_STATE=
MY_VOELGOED_CURRENT_STATE=
CANONICAL_PERSON_COUNT=
COURSE_COUNT=
PRODUCT_COUNT=
EVENT_COUNT=
BLOCKERS=
```

- [ ] **Step 7: Commit the evidence document**

```bash
git add docs/architecture/VS1_CURRENT_REALITY_AUDIT_v1.0.0.md
git commit -m "docs: record VS1 current reality audit"
```

---

### Task 2: Audit Featured Content Manager and Global Featured-Offer Authority

**Files:**
- Create: `docs/architecture/VS1_FCM_PREFLIGHT_v1.0.0.md`
- Read: `site-code/runtime-tests/fcm-self-featured-integration.py`
- Read: `site-code/runtime-tests/fcm-self-featured-runtime.php`
- Read: current FCM-related source, snippets, ACF JSON/options, Bricks consumers, and current-state docs

**Interfaces:**
- Consumes: Task 1 baseline.
- Produces: `KEEP`, `ADAPT`, `MIGRATE`, or `RETIRE` ruling and exact FCM authority/consumer map.

- [ ] **Step 1: Inventory FCM configuration authority**

Record every current featured option/field and its source: ACF field/group/key, options page/storage, supported target types, validation/filtering, current runtime value.

- [ ] **Step 2: Inventory FCM resolution logic**

Find the exact source/snippet/service that maps content classification to a featured option, resolves the target, validates publish/type state, and builds Bricks-facing data.

Explicitly identify whether the driver is `content_category`, `content_topic`, both, or another mechanism.

- [ ] **Step 3: Inventory FCM presentation consumers**

Through Bricks MCP + source/runtime inspection record every Bricks template/component/element that consumes FCM, including marker classes, conditions, dynamic data, template IDs, and empty/fallback behaviour.

- [ ] **Step 4: Verify existing automated/runtime coverage**

Read existing FCM integration tests and determine whether they can be safely executed under the read-only preflight rule. If they mutate temporary records, document the cleanup contract and defer execution unless the user explicitly authorizes mutation-safe QA.

- [ ] **Step 5: Resolve the canonical Topic conflict**

Answer:

```text
Does FCM currently depend on legacy content_category?
Can it consume canonical content_topic without a second featured-content system?
Does adaptation preserve existing valid consumers?
```

- [ ] **Step 6: Produce the FCM ruling**

Exactly one primary ruling:

```text
FCM_RULING=KEEP|ADAPT|MIGRATE|RETIRE
HOME_AGNOSTIC_FEATURE_SLOT_SUPPORTED_BY_FCM=YES|NO|UNKNOWN
TOPIC_START_HERE_SUPPORTED_BY_FCM=YES|NO|UNKNOWN
NEW_COMPETING_FEATURE_SYSTEM_AUTHORIZED=NO
```

- [ ] **Step 7: Commit**

```bash
git add docs/architecture/VS1_FCM_PREFLIGHT_v1.0.0.md
git commit -m "docs: complete VS1 featured content preflight"
```

---

### Task 3: Prove Discovery Relationship Authorities — Topic → Article → Person → Course

**Files:**
- Create: `docs/architecture/VS1_DISCOVERY_AUTHORITY_GATE_v1.0.0.md`
- Read: canonical IA/topic docs
- Read: current Article↔Person ACF/source/consumers
- Read: current Person CPT/template/Bricks consumers
- Read: current Masteriyo Course relationship fields/source
- Read: Product Contribution only as Product↔Person authority

**Interfaces:**
- Consumes: Task 1 baseline, Task 2 FCM ruling.
- Produces: PASS/GAP/`ARCHITECTURE_DECISION_REQUIRED` for each discovery edge.

- [ ] **Step 1: Verify Topic → Article authority**

For Nuwe Jy and selected child terms, record term IDs/slugs, published Article assignments, provenance, and frontend consumers.

Required:

```text
TOPIC_TO_ARTICLE_AUTHORITY=content_topic
TOPIC_TO_ARTICLE_RUNTIME=PASS|GAP
```

- [ ] **Step 2: Verify Article → Person authority**

Inventory exact Article Person relationship fields and roles. For each record ACF field key/name, cardinality, accepted post type, current consumers, reverse-query support.

Compare against:

```text
primary_author
additional_authors
expert_contributors
reviewed_by
```

Do not invent a participation entity during this audit.

- [ ] **Step 3: Verify canonical Person runtime and Mentors**

Record current canonical Person count, public Person template behaviour, existing `person_type`/Mentor classification, and current Topic/expertise field/category.

Required:

```text
MENTOR_CLASSIFICATION_REUSE=PASS|ADAPT|ARCHITECTURE_DECISION_REQUIRED
```

- [ ] **Step 4: Verify Person → Product is consumable**

Verify from current Product Contribution docs/source:
- `ProductContributionRepository::products_for_person()`;
- `PersonProductsBricks`;
- runtime deployment status;
- whether a production Person template consumes the adapter.

Do not treat this as Course authority.

- [ ] **Step 5: Prove Person → Course authority**

Inspect Masteriyo instructor/author model, Course post meta/ACF, existing Course template queries, Voelgoed Course relationship metadata, and architecture docs.

Output either:

```text
PERSON_TO_COURSE_AUTHORITY=<exact system/field/service>
PERSON_TO_COURSE_GATE=PASS|GAP
```

or:

```text
PERSON_TO_COURSE_AUTHORITY=NONE_PROVEN
PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED
```

A Woo Product→Person relationship is explicitly insufficient proof.

- [ ] **Step 6: Verify Course → Woo commercial mapping**

Record the supported mapping between Masteriyo Course and Woo Product/checkout mechanism.

- [ ] **Step 7: Produce edge-by-edge gate table**

| Edge | Authority | Source proof | Runtime proof | Gate |
|---|---|---|---|---|
| Home → Topic | canonical navigation/content_topic | ... | ... | PASS/GAP |
| Topic → Article | content_topic | ... | ... | PASS/GAP |
| Article → Person | ... | ... | ... | PASS/GAP/ADR |
| Person → Course | ... | ... | ... | PASS/GAP/ADR |
| Person → Product | Product Contribution | ... | ... | PASS/GAP |
| Person → Event | Event Participation | ... | ... | PASS/GAP |

- [ ] **Step 8: Commit**

```bash
git add docs/architecture/VS1_DISCOVERY_AUTHORITY_GATE_v1.0.0.md
git commit -m "docs: complete VS1 discovery authority gate"
```

---

### Task 4: Verify Vriendinne Billing, Membership, Tier, and Member Perks Commerce Authority

**Files:**
- Create: `docs/architecture/VS1_MEMBERSHIP_COMMERCE_GATE_v1.0.0.md`
- Read: current `docs/development/membership-billing-architecture.md`
- Read: current Voelgoed Member Perks source/README/config
- Read: current Woo/PMPro integration configuration

**Interfaces:**
- Consumes: Task 1 baseline.
- Produces: authoritative membership chain and Course benefit contract.

- [ ] **Step 1: Verify current membership product model**

Record Vriendinne Woo products/variations, monthly/yearly billing, statuses, and mapping to PMPro levels. IDs are evidence only, never future hard-coded constants.

- [ ] **Step 2: Verify PMPro tier model**

Record Brons/Silwer/Goud levels, active state, whether billing frequencies share the same tier level, and direct PMPro signup state.

Required:

```text
BILLING_AUTHORITY=WOO_SUBSCRIPTIONS
MEMBERSHIP_AUTHORITY=PMPRO
```

or exact divergence.

- [ ] **Step 3: Verify Member Perks runtime capability**

For Course/Product/Event benefit types record feature flags, enabled state, eligibility inputs, tier differentiation, benefit resolution APIs/services, claim/enrol modes, and existing frontend helpers.

Do not enable anything.

- [ ] **Step 4: Verify tier-specific Course pricing path**

Determine how the Course Woo Product receives a member benefit: price filter, cart adjustment, claim/coupon, or another supported mechanism.

Required:

```text
COURSE_MEMBER_PRICE_AUTHORITY=<exact Member Perks/Woo path>
```

- [ ] **Step 5: Verify state-transition invalidation requirements**

Inspect cache/session behaviour around logged-out, logged-in, PMPro activation, Woo cart/checkout and My Voelgoed. Record what must refresh on `non-member → member`.

- [ ] **Step 6: Verify presentation helpers**

Search Bricks/dynamic data/source for active member state, tier label, member price, saving and benefit eligibility. Classify each as reusable, needs adapter, or must not be used.

- [ ] **Step 7: Finish the membership gate**

Required chain:

```text
Woo Subscription
→ successful billing state
→ PMPro active tier
→ Member Perks eligibility
→ authoritative Course Woo price/benefit
```

Each arrow = PASS, GAP, or `ARCHITECTURE_DECISION_REQUIRED`.

- [ ] **Step 8: Commit**

```bash
git add docs/architecture/VS1_MEMBERSHIP_COMMERCE_GATE_v1.0.0.md
git commit -m "docs: complete VS1 membership commerce gate"
```

---

### Task 5: Verify Course Fulfilment, My Voelgoed, Thank-You, and Email Emitters

**Files:**
- Create: `docs/architecture/VS1_COURSE_FULFILMENT_EMAIL_GATE_v1.0.0.md`
- Read: unified-dashboard/My Voelgoed spec, plan, source and staging audit
- Read: Masteriyo/Woo integration configuration/source
- Read: Woo/PMPro/Masteriyo/FluentCRM email configuration and custom snippets

**Interfaces:**
- Consumes: Task 4 membership commerce findings.
- Produces: exact post-payment entitlement and communication contract.

- [ ] **Step 1: Verify Woo Course purchase → Masteriyo access**

Record Product/Course mapping, qualifying Woo status, integration owner/hook, enrolment result, idempotency/retry behaviour.

Required:

```text
COURSE_PURCHASE_TO_ENROLMENT=PASS|GAP|ARCHITECTURE_DECISION_REQUIRED
```

- [ ] **Step 2: Verify direct Course access URL**

Determine the canonical post-purchase destination for an enrolled Course.

- [ ] **Step 3: Verify My Voelgoed Learning projection**

Record `/my-voelgoed/learning/` route, provider used to list Courses, access guard, cache policy, rendering consumer, and enrolled/non-enrolled behaviour. Confirm it projects Masteriyo state rather than copying it.

- [ ] **Step 4: Verify current Woo Thank-you surface**

Record current template/hook ownership, Course-specific CTA support, My Voelgoed CTA support, and the correct bounded extension point.

- [ ] **Step 5: Build the transactional email emitter matrix**

Events:

```text
membership order created
membership payment succeeded
PMPro membership activated
Course order created
Course payment succeeded
Masteriyo enrolment created
```

Emitters:

```text
WooCommerce
Woo Subscriptions
PMPro
PMPro/Woo integration
Masteriyo
Voelgoed Member Perks
FluentCRM
custom code/snippets
payment gateway where relevant
```

For each record trigger, recipient, enabled state, purpose and duplication.

- [ ] **Step 6: Propose one-transaction/one-useful-email ownership**

Without changing settings, identify the intended owner of membership communication and Course purchase/access communication, plus later suppress/adapt actions.

- [ ] **Step 7: Record fulfilment failure modes**

At minimum:
- paid order but no enrolment;
- enrolment exists but Course URL invalid;
- My Voelgoed stale;
- duplicate enrolment;
- email sent before access exists.

For each record observability/reconciliation or GAP.

- [ ] **Step 8: Commit**

```bash
git add docs/architecture/VS1_COURSE_FULFILMENT_EMAIL_GATE_v1.0.0.md
git commit -m "docs: complete VS1 course fulfilment and email gate"
```

---

### Task 6: Audit Search, Analytics, Ranking Inputs, and Cache Boundaries

**Files:**
- Create: `docs/architecture/VS1_DISCOVERY_SERVICES_GATE_v1.0.0.md`
- Read: GA4/GTM/dataLayer implementation evidence
- Read: WordPress search implementation
- Read: existing view-count/engagement storage
- Read: Platform Core cache policy and caching integration

**Interfaces:**
- Consumes: prior gates.
- Produces: bounded reuse decisions; prevents new duplicate infrastructure.

- [ ] **Step 1: Audit site search**

Record native/search-plugin behaviour, indexed post types, inclusion of Course/Event/Product/Person, and Bricks search UI.

Ruling:

```text
SEARCH_VS1=REUSE|ADAPT|DEFER
```

- [ ] **Step 2: Audit analytics**

Record GA4, GTM, dataLayer, Woo ecommerce tracking and custom event conventions. Map the VS1 funnel to existing conventions without implementing new events yet.

- [ ] **Step 3: Audit content popularity data**

Find any page-view counters, GA4 imports, analytics tables or first-party engagement counters.

Ruling:

```text
CONTENT_RANKING_INPUT=REUSE|ADAPT|NONE
```

- [ ] **Step 4: Audit cache boundaries**

Inspect public cache, logged-in behaviour, My Voelgoed, Woo cart/checkout, PMPro and dynamic fragment rules. Explicitly identify risk for `non-member → member`.

- [ ] **Step 5: Commit**

```bash
git add docs/architecture/VS1_DISCOVERY_SERVICES_GATE_v1.0.0.md
git commit -m "docs: complete VS1 discovery services gate"
```

---

### Task 7: Freeze the Representative Real-Data Contract

**Files:**
- Create: `docs/architecture/VS1_REPRESENTATIVE_DATA_CONTRACT_v1.0.0.md`

**Interfaces:**
- Consumes: Tasks 1–6.
- Produces: exact real records for Golden Journey/regression testing, or a precise data-creation prerequisite.

- [ ] **Step 1: Select the canonical Nuwe Jy Topic path**

Record root/child term IDs, slugs and provenance.

- [ ] **Step 2: Select 3–5 real Articles**

Each must be genuinely editorial, have reviewed Topic assignment, not be QA/fixture content, and support a coherent path toward the selected Person/Course.

- [ ] **Step 3: Select one real canonical Person/Mentor**

Require canonical `person`, acceptable profile content, approved Mentor/topic classification if used, and no legacy `medewerker` substitution for convenience.

- [ ] **Step 4: Select one real paid Course**

Require Masteriyo Course, supported Woo commercial mapping, suitable Vriendinne benefit candidate, and acceptable real provenance.

If Person→Course authority is not proven, mark the contract BLOCKED rather than inventing a link.

- [ ] **Step 5: Select one Event regression object**

Require real Tickera Event, working commerce path, and canonical Person participation if Person→Event is exercised.

- [ ] **Step 6: Select one normal Product regression object**

Require real Woo Product, valid purchase path, and canonical Product Contribution if Person→Product is exercised.

- [ ] **Step 7: Record Vriendinne QA personas**

Define future personas: non-member, Brons, Silwer, Goud. Record whether safe test accounts exist or controlled users must later be created. Do not create users now.

- [ ] **Step 8: Publish the data verdict**

```text
REPRESENTATIVE_DATA_GATE=PASS|BLOCKED
```

IDs/slugs are test evidence only and must not be hard-coded into production implementation.

- [ ] **Step 9: Commit**

```bash
git add docs/architecture/VS1_REPRESENTATIVE_DATA_CONTRACT_v1.0.0.md
git commit -m "docs: freeze VS1 representative data contract"
```

---

### Task 8: Produce the VS1 Master Gate and Split Later Implementation Plans

**Files:**
- Create: `docs/architecture/VS1_MASTER_GATE_v1.0.0.md`

**Interfaces:**
- Consumes: all prior evidence docs.
- Produces: implementation authorization status and exact follow-up plan set.

- [ ] **Step 1: Build the Golden Journey gate matrix**

Rows:

```text
Home → Topic
Topic → Article
Article → Person
Person → Course
Course → membership benefit
membership CTA → membership selection
membership purchase → PMPro active
PMPro active → Member Perks benefit
membership success → original Course
Course → Woo purchase
Woo paid → Masteriyo access
access → Thank-you Start Course
access → My Voelgoed Learning
transaction → one useful email
```

For every row record authority, current implementation, runtime evidence, gap, implementation owner and gate.

- [ ] **Step 2: Build the regression gate matrix**

Rows:

```text
Home → Event → Buy
Article → Event → Buy
Person → Event → Buy
Article → Product → Buy
Person → Product → Buy
Topic → Product → Buy
```

Classify failures as shared VS1 blocker, VS1 regression, independent specialist follow-up, or PASS.

- [ ] **Step 3: List architecture blockers**

Every unresolved authority issue must be explicitly labelled:

```text
ARCHITECTURE_DECISION_REQUIRED
```

- [ ] **Step 4: Decide whether implementation planning may proceed**

Final verdict:

```text
VS1_MASTER_GATE=PASS|BLOCKED
```

PASS requires known authorities, not necessarily completed implementation.

- [ ] **Step 5: Split implementation into bounded plans**

Recommended boundaries:

```text
Plan A — VS1 Discovery Chain
Home → Topic → Article → Person → Course

Plan B — Vriendinne Context + Persistent Journey Intent
Course → membership entry → one-intent lifecycle → validated return

Plan C — Membership Activation + Tier-Aware Course Commerce
Woo Subscription → PMPro → Member Perks → Course offer/purchase

Plan D — Course Fulfilment + Transactional Communication
Woo paid → Masteriyo → Thank-you → My Voelgoed → deduplicated email

Plan E — Event/Product Regressions + Cross-Cutting Hardening
regressions + analytics + SEO + responsive + accessibility + cache + security
```

If a gate needs its own architecture spec, write that spec before implementation planning.

- [ ] **Step 6: Self-review against VS1 v0.2.0**

Every production-blocking criterion must be satisfied, assigned to a later plan, or explicitly blocked. No criterion may silently disappear.

- [ ] **Step 7: Commit**

```bash
git add docs/architecture/VS1_MASTER_GATE_v1.0.0.md
git commit -m "docs: complete VS1 master gate"
```

---

# Final Verification for This Preflight Plan

- [ ] No WordPress business data mutated.
- [ ] No Bricks template/component changed.
- [ ] No ACF field group/option changed.
- [ ] No Member Perks flag/config changed.
- [ ] No Course enrolment/order/membership created.
- [ ] Current `origin/main` and runtime identity recorded.
- [ ] Platform Core source/runtime parity known.
- [ ] FCM has an explicit ruling.
- [ ] Article→Person authority known.
- [ ] Person→Course authority known or explicitly blocked.
- [ ] Product Contribution current state is fresh.
- [ ] Membership billing/access/perk chain known.
- [ ] Course purchase→Masteriyo path known.
- [ ] Email emitter matrix exists.
- [ ] Search/analytics/ranking/cache reuse decisions exist.
- [ ] Representative real data frozen or explicitly blocked.
- [ ] Master Gate says PASS or BLOCKED with evidence.
- [ ] Later implementation is split into bounded plans; no production code written under this plan.

# Execution Recommendation

Use **subagent-driven execution** for this preflight.

Reason: the gates are largely independent evidence domains (FCM, discovery relationships, membership commerce, Course fulfilment/email, analytics/cache), and a mistaken authority conclusion could cause later implementation to duplicate or corrupt architecture. Each evidence task should receive a fresh critical review before the Master Gate synthesizes them.
