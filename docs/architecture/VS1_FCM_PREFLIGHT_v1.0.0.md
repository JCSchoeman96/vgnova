# VS1 Featured Content Manager Preflight

| Audit item | Value |
|---|---|
| Status | CURRENT READ-ONLY PREFLIGHT |
| Version | 1.0.0 |
| Audit date | 2026-10-26 |
| Repository baseline | `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` |
| Runtime | VG Nova Local, `http://localhost:10039/` |

## Decision

```text
FCM_RULING=ADAPT
HOME_AGNOSTIC_FEATURE_SLOT_SUPPORTED_BY_FCM=NO
TOPIC_START_HERE_SUPPORTED_BY_FCM=NO
NEW_COMPETING_FEATURE_SYSTEM_AUTHORIZED=NO
```

Keep the existing Featured Content Manager option store, target validation, article card component, and Bricks query integration. A later bounded implementation plan may adapt the article classification driver to canonical `content_topic`. The present FCM resolver selects from `content_category`, which is a live legacy taxonomy. VS1 must use `content_topic` for canonical semantic pillars and keep commercial classification, including `Leefstyl`, outside that topic model.

This ruling does not authorize a second global feature/CTA system, code changes, option changes, or Bricks changes. The current FCM is an Article feature offer surface; it is not evidence of a Home → Topic route or a global home-page offer slot.

## Authority and current configuration

The active configuration is runtime ACF state in options store `featured-content-manager`, group `Featured Content Manager` (`group_vg_featured_content_manager`). Eight fields were read from ACF in the selected VGNova runtime. Each is a single Post Object whose allowed target post types are `product`, `mto-course`, and `tc_events`, with ID return format.

| Field | Current Local runtime value | Resolved published target |
|---|---:|---|
| `featured_nuwe_jy` | `542` | Tickera Event “Vroue-oggend: Rustig & Herlaai” |
| `featured_geloof` | `534` | Masteriyo Course “Geloof & Identiteit” |
| `featured_self` | `531` | Woo Product “Slaapmasker Deluxe” |
| `featured_werk_geld` | empty | none |
| `featured_ouerskap` | empty | none |
| `featured_huwelik` | empty | none |
| `featured_mensstories` | empty | none |
| `featured_leefstyl` | empty | none |

**Evidence class:** current `RUNTIME PROVEN`, Local staging configuration, not owner-authorized representative Journey data. The selected targets may not be used to infer Person relationships or representative-data suitability.

The field group and values are not tracked as an ACF JSON source definition in this repository. The checked-in FCM integration tests define only a test contract: they expect eight option keys and read the option group through ACF. They do not define the production resolver. See `site-code/runtime-tests/fcm-self-featured-integration.py` and `site-code/runtime-tests/fcm-self-featured-runtime.php`. The older README and setup guide describe seven fields; those documents are superseded for field count by the current runtime schema and later eight-field test/state evidence.

## Resolver and content classification boundary

Fresh `novamira/read-file` inspection of the published runtime snippet:

```text
ABSPATH/wp-content/fluent-snippet-storage/2-vg-article-dynamic-tags.php
```

found these active functions and behaviors:

- `vg_article_get_first_content_category_term()` reads the first `content_category` term, ordered by term order.
- `vg_article_get_featured_content_field_for_slug()` maps the seven canonical-looking slugs (`nuwe-jy`, `geloof`, `self`, `werk-geld`, `ouerskap`, `huwelik`, `mensstories`) to their corresponding `featured_*` option fields.
- The helper does not map `leefstyl` to `featured_leefstyl`.
- `vg_article_get_featured_content_post_id()` reads from `featured-content-manager`, then returns `0` unless the target exists, is published, and is one of `product`, `mto-course`, or `tc_events`.
- The helper exposes Bricks tags including `{vg_featured_content_id}`, `{vg_has_featured_content}`, `{vg_featured_content_type}`, `{vg_featured_object_type_label}`, and `{vg_featured_object_cta_label}`. `vg_category_promo_*` tags are aliases into this FCM resolver.
- The marked Bricks query is constrained to the resolved ID and post types `product`, `tc_events`, or `mto-course`. Empty/invalid targets produce no result; the article featured slots are hidden when no current target resolves.

The runtime file is current executable behavior. The checked-in integration test covers one `Self` case where the Article has `content_category=self` and no `content_topic`; this is a test fixture contract, not proof of a complete tracked production implementation. The broader current resolver finding above is runtime-proven from the active FluentSnippets file.

The architecture boundary is explicit:

- `content_topic` is the canonical semantic topic taxonomy owned by Platform Core.
- `content_category` remains live legacy classification until its own migration and consumer gates pass.
- `Leefstyl` and its descendants are commercial classifications, not canonical `content_topic` pillars.
- The existence of `featured_leefstyl` in runtime ACF does not make it a canonical topic or put it in the current resolver map.

Therefore the current `content_category` driver must not be presented as canonical `Topic` authority. A later implementation plan must define `content_topic` as the FCM semantic selector and explicitly preserve the commercial/legacy boundary.

## Bricks consumers and route evidence

Current Bricks runtime inspection found:

- Published Article template `372` (“Posts”) renders the `Featured Content Block` component `b56af6` in the mobile and sidebar placements.
- The component's query uses `{vg_featured_content_id}`, a one-item query over Product, Course, or Event, and type/CTA dynamic tags from the runtime snippet.
- The current Home setting is `show_on_front=posts`, `page_on_front=0`; there is no static Home page configured. The Bricks Header template is `77` and has a custom navigation. These facts do not prove Home → Topic navigation.
- No canonical Person Bricks template consumer was found. Legacy Person templates `772` and `773` remain; their existence does not make FCM a Person consumer.

This is current `RUNTIME PROVEN` Bricks state. The integration test in `site-code/runtime-tests/fcm-self-featured-integration.py` checks a marked template/component and card output but is not a current deployment manifest or complete consumer inventory. The FCM option targets do not create Person → Event, Person → Product, or Person → Course relationships.

## Reuse decision and limits

| Concern | Current authority | VS1 ruling |
|---|---|---|
| Featured target options | Runtime ACF option store `featured-content-manager` | Reuse the existing store; do not add another options system. |
| Semantic selector | Runtime snippet uses first `content_category` term | Adapt to canonical `content_topic` in a later scoped plan. |
| Leefstyl | Runtime ACF includes `featured_leefstyl`; resolver omits it | Keep it outside canonical topic selection; no automatic conversion. |
| Target eligibility | Runtime resolver requires published Product, Course, or Event | Preserve native target authority; do not infer Person relationships from a featured target. |
| Article rendering | Bricks template `372`, component `b56af6` | Reuse the existing card/query surface if later design requires an Article offer. |
| Home navigation/offer | Home is posts; no topic route proven | Separate Home → Topic authority remains unresolved in the Discovery Authority Gate. |
| Global CTA | No global FCM consumer proved | Do not create a second system or claim current FCM owns all journey CTAs. |

## Verification classification

| Conclusion | Authority and exact evidence | Classification |
|---|---|---|
| Eight current FCM fields and current values | ACF runtime group `group_vg_featured_content_manager`, option store `featured-content-manager`, selected VGNova runtime | `RUNTIME PROVEN`; current Local staging; not owner-authorized journey data |
| Resolver uses `content_category` and validates published allowed post types | Runtime FluentSnippets file `2-vg-article-dynamic-tags.php`; functions named above | `RUNTIME PROVEN`; current Local staging |
| `content_topic` is the canonical topic authority; `content_category` remains legacy | `AGENTS.md`, Platform Core `ContentTopicTaxonomy`, `docs/architecture/POST_TASK_1_CURRENT_STATE.md` | `SOURCE PROVEN`; current tracked architecture, with existing runtime assignments absent for this Journey |
| Bricks Article FCM consumer | Template `372`, component `b56af6`, read-only Bricks runtime inspection | `RUNTIME PROVEN`; current Local staging |
| Test contract includes eight fields and a single `Self` path | `site-code/runtime-tests/fcm-self-featured-integration.py`; `site-code/runtime-tests/fcm-self-featured-runtime.php` | `SOURCE PROVEN`; fixture/QA test code only |
| Full FCM schema as source-controlled ACF JSON | No tracked definition found; runtime group is database-managed | `NOT PROVEN` in source |

## Required ruling

```text
FCM_RULING=ADAPT
HOME_AGNOSTIC_FEATURE_SLOT_SUPPORTED_BY_FCM=NO
TOPIC_START_HERE_SUPPORTED_BY_FCM=NO
NEW_COMPETING_FEATURE_SYSTEM_AUTHORIZED=NO
```

The existing FCM has useful runtime configuration and Article/Bricks consumption, while its active semantic driver is the legacy taxonomy. The next implementation-planning unit may scope a canonical `content_topic` adaptation after the Master Gate; this preflight itself makes no runtime change.
