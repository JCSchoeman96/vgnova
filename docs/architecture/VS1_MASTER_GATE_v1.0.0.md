# VS1 Master Gate

| Audit item | Value |
|---|---|
| Status | BLOCKED |
| Version | 1.0.0 |
| Audit date | 2026-10-26 |
| Repository baseline | `origin/main` = `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` |
| Branch | `feat/vs1-preflight-authority-gates` |
| Runtime | VG Nova Local, `http://localhost:10039/`, WordPress 7.1.3, PHP 8.5.12 |

## Gate summary

```text
CURRENT_REALITY_GATE=BLOCKED
SOURCE_RUNTIME_PARITY=DRIFT
SOURCE_RUNTIME_CONFLICT=YES
FCM_RULING=ADAPT
DISCOVERY_GATE=BLOCKED
PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED
MEMBERSHIP_COMMERCE_GATE=BLOCKED
COURSE_FULFILMENT_GATE=BLOCKED
EMAIL_GATE=GAP
DISCOVERY_SERVICES_GATE=GAP
REPRESENTATIVE_DATA_GATE=BLOCKED
PERSON_PROGRAMME_DEPENDENCY=YES
VS1_IMPLEMENTATION_PLANNING_AUTHORIZED=NO
PRODUCTION_IMPLEMENTATION_AUTHORIZED=NO
VS1_MASTER_GATE=BLOCKED
```

The authorities and current system boundaries are identified, but the Golden Journey cannot be planned without inventing a Person-to-Course relationship and representative data. The Course member-price authority is also undecided. No production implementation plan is authorized by this gate. This result does not say that the journey is implemented.

## Evidence baseline

The committed evidence set is:

| Task | Evidence document | Main outcome |
|---|---|---|
| 1 | `VS1_CURRENT_REALITY_AUDIT_v1.0.0.md` | Fresh baseline and Local runtime verified; Platform Core source/runtime parity is `DRIFT`; current canonical relationship data is sparse. |
| 2 | `VS1_FCM_PREFLIGHT_v1.0.0.md` | `FCM_RULING=ADAPT`; reuse existing FCM and adapt its legacy selector to canonical Topics in a later scoped plan. |
| 3 | `VS1_DISCOVERY_AUTHORITY_GATE_v1.0.0.md` | Discovery gate blocked; Person → Course has no proven authority. |
| 4 | `VS1_MEMBERSHIP_COMMERCE_GATE_v1.0.0.md` | Woo Subscriptions owns billing, PMPro owns access, Member Perks owns benefits; Course price/benefit authority is undecided. |
| 5 | `VS1_COURSE_FULFILMENT_EMAIL_GATE_v1.0.0.md` | Current Course purchase-to-enrolment and one-email outcomes are not proven. |
| 6 | `VS1_DISCOVERY_SERVICES_GATE_v1.0.0.md` | Native search is reusable with an adapted Bricks surface; analytics and cache-state evidence have gaps; no ranking input exists. |
| 7 | `VS1_REPRESENTATIVE_DATA_CONTRACT_v1.0.0.md` | No eligible representative Journey dataset exists; data gate is blocked. |

The Local runtime is staging evidence. It is not treated as production-like. Task 1 compared the current Platform Core assembly and active runtime and found one missing and five differing files. Task 5 verified hash equality only for `LearningService.php`, `AccessGuard.php`, and `CachePolicy.php`. No gate assumes that the full active runtime matches source.

## Production Journey gate matrix

| Journey edge | Authority and current implementation | Runtime evidence and class | Gap | Implementation owner | Gate |
|---|---|---|---|---|---|
| Home → Topic | `content_topic` is the canonical semantic taxonomy. `ContentTopicTaxonomy` does not define Home navigation. Existing FCM is an Article offer surface, not a Home Topic route. | Current Home is posts (`show_on_front=posts`, no static front page); runtime Header 77 has custom navigation with no canonical Topic links. `RUNTIME PROVEN`; current Local. | No approved Home → Topic consumer. | Information architecture, Bricks, Platform Core | `GAP`, shared blocker |
| Topic → Article | `content_topic` is the canonical Article classification authority. The runtime FCM resolver still selects by legacy `content_category`. | Nuwe Jy root 349 and child terms have zero assigned-object counts; the only published post, 375, has no Topic assignment. `BOTH`; current source and Local runtime. | No qualifying Article is reachable from a canonical Topic. | Editorial owner, Platform Core, Bricks | `GAP`, shared blocker |
| Article → Person | No canonical Article-to-Person ACF field, service, or relationship row is proven. WordPress `post_author` is a user ID, not a Person. | Article 375 has author user 1 and no canonical Person edge. Runtime Article ACF groups expose no Person relationship. `BOTH`; current source/schema and Local. | Approved Article → Person role/cardinality and editorial data are absent. | Editorial/data architecture; coordinate with Person programme | `GAP`, shared blocker |
| Person → Course | No canonical Person ↔ Course field, service, participation model, or consumer exists in inspected source/runtime. | Person 1705 and published Courses 532–540 have no such relationship. Masteriyo author is a WordPress user. `BOTH`; current source and Local. | No authority may be inferred from author, Topic, Product Contribution, FCM target, or Woo mapping. | Platform/data architecture with Person programme | `ARCHITECTURE_DECISION_REQUIRED`, hard stop |
| Course → membership benefit | Woo Subscriptions owns recurring billing, PMPro owns active membership state, and Member Perks owns benefit eligibility. No Course-specific authoritative Woo price/benefit path is established. | All five published Courses have no linked Woo Product. `woo_discounts`, `course_claims`, and Woo Blocks compatibility are off; no Course benefit was evaluated for a member. `BOTH`; current source/configuration and Local. | Decide how a Course maps to a selling Product and a tier-specific benefit/price. | Member Perks, WooCommerce, PMPro, Masteriyo, product architecture | `ARCHITECTURE_DECISION_REQUIRED` |
| Membership CTA → membership selection | No global FCM or approved membership CTA/selection implementation is present. Direct PMPro signup is disabled. | Vriendinne subscription parents 905, 908, and 911 are drafts; a current public comparison/quick-entry path was not proven. `RUNTIME PROVEN`; current Local configuration. | Quick entry and full comparison flow have no approved Journey surface. | Membership product and UX owners | `GAP` |
| Membership purchase → PMPro active | PMPro Woo integration maps Vriendinne products to PMPro levels and grants on Woo `completed`; Woo Subscriptions owns recurring billing. | Mapping values agree in current configuration, but parents are drafts, no matching order lineage exists, and active PMPro rows do not prove a purchase. `BOTH` for mapping; end-to-end `NOT PROVEN`. | Controlled paid order and subscription transition have not been tested. | Woo Subscriptions, PMPro Woo integration, PMPro | `GAP`; proof requires mutation |
| PMPro active → Member Perks benefit | Member Perks resolves active PMPro levels through programme mapping and audience rules. | Brons/Silwer/Goud map to PMPro 15/16/17. The resolver/eligibility mapping is source/runtime proven, but no user was evaluated in this gate and no Course price benefit is active. | Eligibility mapping passes only; this does not prove a Course benefit or tier price. | Member Perks and PMPro | `PARTIAL`; eligibility mapping `PASS`, Journey edge `GAP` |
| Membership success → original Course | The approved spec requires a single persisted intent and validated return. No Journey intent repository/service or return implementation is present. | No purchase/return transition was run. FCM does not provide intent storage. `SOURCE PROVEN` for inspected source; runtime transition `NOT PROVEN`. | Intent lifecycle, validation, cancellation, and return behavior remain undesigned and untested. | Platform Core and commerce/security architecture | `GAP` |
| Course → Woo purchase | Masteriyo supports Course/Woo mappings, but the Course offer needs a supported Woo item and authoritative price. | `_wc_product_id=0` for every published Course and no Woo Product points to a Course. No Course order exists. `BOTH`; current runtime and integration source. | No paid Course checkout surface. | Masteriyo and WooCommerce product owner | `GAP`, blocked by Course price decision |
| Woo paid → Masteriyo access | Installed Masteriyo integration reacts to Woo order events and activates access on `completed`; Woo Product metadata must identify a Course. | No Course Product link or order exists. UserCourse access after a real purchase was not tested. `BOTH` for supported source path and missing links; result `NOT PROVEN`. | Paid status, order-to-enrolment lineage, idempotency, and access need a controlled proof. | WooCommerce and Masteriyo | `GAP`; proof requires mutation |
| Access → Thank-you Start Course | Published Thank You template 128 exists; Masteriyo can redirect after its order path. | Template 128 has no Course or Start Course CTA marker and no My Voelgoed CTA. No purchase-created enrolment was available to exercise it. `RUNTIME PROVEN`; current Local template, path `NOT PROVEN`. | Thank-you action contract and access-aware destination are missing. | WooCommerce, Masteriyo, Bricks | `GAP` |
| Access → My Voelgoed Learning | Platform Core `LearningService`, access guard, and dashboard summary project Masteriyo state. Current dashboard scope is recorded as complete. | Page 1160 exists at `/my-voelgoed/learning/`; anonymous request redirects to login with private/no-store headers. Historical QA exercised controlled enrollments. New purchase-to-dashboard state is not proven. `BOTH` for dashboard slice; historical QA for authenticated state. | Dashboard availability passes within its approved scope; purchase-created access and cache transition remain unproven. | Platform Core and Masteriyo | `PARTIAL`; journey transition `GAP` |
| Transaction → one useful email | Woo, Woo Subscriptions, PMPro, PMPro/Woo, Masteriyo, Member Perks, FluentCRM, custom snippets, and relevant gateway were inventoried in Task 5. | Woo Customer Processing/Completed messages and Masteriyo student completed-order email are enabled. PMPro lifecycle templates are enabled. Member Perks `notifications=false`; FluentCRM has 0 automations/campaigns. No delivery or deduplication test ran. `RUNTIME PROVEN` for settings; delivery `NOT PROVEN`. | Overlapping messages may occur. No single owner/content contract is approved; behavioural follow-up remains out of scope. | WooCommerce, Masteriyo, PMPro, email operations | `GAP`; proof requires mutation |

## Regression gate matrix

No row is marked `PASS`. Existing fixture or FCM target behavior cannot substitute for approved real data or a current purchase test.

| Regression path | Current evidence | Failure class | Owner | Gate |
|---|---|---|---|---|
| Home → Event → Buy | Home has no canonical Topic/offer route. FCM's current Event target is not a Home path or owner-approved representative Event. | Shared VS1 blocker | Bricks/IA and Tickera | `NOT PROVEN` |
| Article → Event → Buy | Article FCM can resolve a published Event through legacy `content_category`; no eligible real Article or successful Event purchase was established. | VS1 regression | Editorial, FCM, Tickera | `NOT PROVEN` |
| Person → Event → Buy | Event Participation authority exists, but rows and a canonical Person template consumer are absent; Task 10B remains separate. | VS1 regression | Event Participation owner and Person programme | `NOT PROVEN`; `PERSON_PROGRAMME_DEPENDENCY=YES` |
| Article → Product → Buy | Article FCM can resolve a Product, but there is no eligible real Article/Product pair or current purchase proof. | VS1 regression | Editorial, FCM, WooCommerce | `NOT PROVEN` |
| Person → Product → Buy | Product Contribution source and adapter exist, but there are zero rows and no published canonical Person template consumer. | VS1 regression | Product Contribution owner and Person programme | `NOT PROVEN`; `PERSON_PROGRAMME_DEPENDENCY=YES` |
| Topic → Product → Buy | No Topic → Article assignment exists; FCM still uses legacy `content_category`; no approved Product candidate or purchase proof exists. | Shared VS1 blocker | Editorial, FCM, WooCommerce | `NOT PROVEN` |

Person regressions must wait for Task 10B coordination. No legacy Person relationship or excluded QA edge may be migrated to turn a row into `PASS`.

## Cross-cutting acceptance criteria

The approved spec section 61 has 33 production-blocking criteria. The main Journey and regression tables above cover the named data, discovery, commerce, activation, fulfilment, email, and Person/Product/Event paths. The remaining criteria map as follows so no scope drops from later planning.

| Spec criteria | Evidence and gap | Later boundary | Current disposition |
|---|---|---|---|
| 1: approve representative data | Task 7 has no suitable Article/Person/Course set; Event/Product representatives and isolated QA personas are not certified. | Plan A and Plan E, after data provenance decisions | `BLOCKED`; no data creation authorized |
| 2–5: Home, Topic, Article, Person, Course discovery chain | See first four rows of the production matrix. Person → Course requires a separate authority decision. | Plan A — Discovery Chain | `BLOCKED` |
| 6–10: Course offer, membership presentation/entry/comparison, persistent original intent | No paid Course offer, accurate tier price, membership CTA selection route, or intent implementation is proven. | Plans B and C | `GAP`; plans not authorized |
| 11–12: one active intent and No Thanks dismissal | No intent lifecycle or dismissal behavior is implemented or runtime-proven. | Plan B — Vriendinne Context + Persistent Journey Intent | `NOT PROVEN` |
| 13–16: membership purchase, PMPro activation, Member Perks tier, validated return | Current mapping is known; end-to-end purchase and return are not proven, and Course benefit authority is undecided. | Plans B and C | `GAP` / `ARCHITECTURE_DECISION_REQUIRED` |
| 17–18: truthful success message and exact tier-specific Course price | No Course tier price exists; current Course benefit authority is not proven. | Plan C, then Plan D email work | `ARCHITECTURE_DECISION_REQUIRED` |
| 19–23: non-member purchase, checkout, Masteriyo access, Thank You actions, dashboard state | No Course Product link/order; Thank You lacks Course actions; dashboard exists but new purchase state is unproven. | Plan D — Course Fulfilment + Transactional Communication | `GAP`; dashboard surface only is `PARTIAL` |
| 24: useful deduplicated transaction email | Multiple enabled customer messages may overlap; delivery, order-status timing, and suppression are not tested. | Plan D | `GAP`; no email changes authorized |
| 25: analytics can observe the key funnel | No GA4/GTM/dataLayer or VS1 event convention is proven. | Plan E — Cross-Cutting Hardening | `GAP` |
| 26–28: SEO, desktop/mobile, accessibility | No complete Journey surfaces exist to validate against the approved criteria. | Plan E | `NOT PROVEN` |
| 29: cache/session transitions | `/my-voelgoed` has a specific no-store policy; Local anonymous headers are known. Member activation and production-like cache behavior are unproven. | Plan E, with controlled runtime proof | `NOT PROVEN`; proof requires mutation and production-like cache access |
| 30: security requirements | Return validation, object/tier/price authority, intent storage, and output behavior have not been implemented or reviewed on a Journey surface. | Plans B, C, and E | `NOT PROVEN` |
| 31–32: Event and Product regressions | Six regression paths above lack approved representative data and current full-path proofs. | Plan E; coordinate specialist Event/Product work | `NOT PROVEN` |
| 33: do not promote legacy authority | Canonical Topic remains `content_topic`; FCM currently selects by legacy `content_category`; `Leefstyl` remains commercial classification. | Bounded FCM adaptation in Plan A/E | `GAP`; `FCM_RULING=ADAPT` |

## FCM and taxonomy boundary

The current FCM uses the runtime ACF option store `featured-content-manager`, validates published Product/Course/Event targets, and renders Article cards through existing Bricks consumers. Its active selector reads the first `content_category` term. The canonical semantic taxonomy is `content_topic`; `content_category` stays legacy, and `Leefstyl` remains a commercial classification outside the canonical Topic pillars.

```text
FCM_RULING=ADAPT
HOME_AGNOSTIC_FEATURE_SLOT_SUPPORTED_BY_FCM=NO
TOPIC_START_HERE_SUPPORTED_BY_FCM=NO
NEW_COMPETING_FEATURE_SYSTEM_AUTHORIZED=NO
```

Reuse the existing FCM option/target validation and Article component if later work needs them. A future scoped plan may adapt the semantic selector to `content_topic`. This gate does not authorize that change or a second feature/CTA system.

## Explicit architecture decisions

Every current `ARCHITECTURE_DECISION_REQUIRED` item is listed here:

1. `PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED`: define a canonical Person ↔ Course authority, relationship owner, cardinality/roles, forward and reverse consumers, and validation. Do not infer it from Masteriyo authors, Topics, Product Contribution, FCM, or a Woo Product.
2. `COURSE_MEMBER_PRICE_GATE=ARCHITECTURE_DECISION_REQUIRED`: define the authoritative Course selling item and the authoritative tier-specific Course benefit/price path across WooCommerce, PMPro, Member Perks, and Masteriyo. Do not enable flags or copy prices to make a test pass.

Article → Person has `ARTICLE_TO_PERSON_AUTHORITY=NONE_PROVEN` and remains a data-model gap. It needs an approved relationship design before the journey can use it. This Master Gate does not silently promote it to a current authority.

## Person programme coordination

PR #58 migrated one owner-authorized canonical Person identity only. It did not migrate Article, Course, Event, or Product relationships. Task 10B consumer cutover has not started. Templates 772 and 773 and legacy profiles 753–757 remain operational.

```text
PERSON_PROGRAMME_DEPENDENCY=YES
EXACT_DEPENDENCY=Task 10B must separately design and execute canonical Person consumer cutover. Any VS1 Person consumer/template change overlapping templates 772/773, legacy profile URLs, relationships, or their consumers must wait for coordination with that programme unit.
```

VS1 must not perform Task 10B, cut over templates 772/773, delete legacy profiles, add redirects, or migrate the legacy Event/Product relationships.

## Deferred runtime proofs requiring mutation

```text
RUNTIME_PROOF_REQUIRES_MUTATION=YES
```

Defer these proofs to later explicitly authorized controlled tasks:

- Woo Subscription purchase, paid status, subscription activation, and PMPro grant lineage.
- Course Woo purchase through the paid order state to Masteriyo active access, direct Course URL, Thank You actions, and My Voelgoed state.
- Member state transition and tier-specific Course benefit/price refresh, including non-member-to-member cart and cache isolation.
- Transactional email delivery, ordering, duplicate emission, failure/retry, and the one-useful-email behavior.
- Event/Product purchase regressions where the proof needs orders, tickets, enrolments, or new relationship data.
- Production-like LiteSpeed/Cloudflare cache checks and authenticated cross-user isolation.

No account, order, membership, enrolment, content record, relationship, ACF value, Bricks resource, feature flag, or email setting was changed during this preflight.

## Proposed later plan boundaries and authorization

These are the boundaries from the approved preflight plan, not approved work orders:

| Proposed plan | Scope | Prerequisite | Authorization now |
|---|---|---|---|
| Plan A — VS1 Discovery Chain | Home → Topic → Article → Person → Course; bounded FCM adaptation | Person → Course decision, Article → Person design, representative editorial data, and Task 10B coordination | `NOT AUTHORIZED` |
| Plan B — Vriendinne Context + Persistent Journey Intent | Course entry, one-intent lifecycle, validated return and dismissal | Approved Course/Person data, security contract, and membership selection route | `NOT AUTHORIZED` |
| Plan C — Membership Activation + Tier-Aware Course Commerce | Woo Subscription → PMPro → Member Perks → Course offer/purchase | Course price/benefit decision and controlled billing proof | `NOT AUTHORIZED` |
| Plan D — Course Fulfilment + Transactional Communication | Woo paid → Masteriyo → Thank You → My Voelgoed → deduplicated email | Course/Product mapping, controlled order/access proof, and email ownership decision | `NOT AUTHORIZED` |
| Plan E — Event/Product Regressions + Cross-Cutting Hardening | Event/Product regressions, analytics, SEO, responsive, accessibility, cache, security | Approved real records, analytics contract, production-like cache access, and specialist coordination | `NOT AUTHORIZED` |

Architecture decision and data-provenance work can be proposed as separate future tasks. This document does not authorize even those tasks. The present verdict is:

```text
VS1_IMPLEMENTATION_PLANNING_AUTHORIZED=NO
PRODUCTION_IMPLEMENTATION_AUTHORIZED=NO
AUTHORIZED_IMPLEMENTATION_PLANS=NONE
```

## Preflight mutation totals

```text
WORDPRESS_BUSINESS_MUTATIONS=0
BRICKS_MUTATIONS=0
ACF_MUTATIONS=0
FCM_OPTION_OR_FLAG_MUTATIONS=0
ORDERS_CREATED=0
MEMBERSHIPS_CREATED=0
ENROLMENTS_CREATED=0
PLATFORM_CORE_DEPLOYMENTS=0
```

The final verdict is blocked because the representative data is unsuitable, Person → Course authority is unresolved, Course member pricing has no authority, the discovery and commerce/fulfilment paths have gaps, and analytics/cache transition proof is incomplete. A later implementation planning decision requires those blockers to be resolved or explicitly bounded by a new approved architecture and data contract.

VS1_MASTER_GATE=BLOCKED
