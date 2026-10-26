# VS1 Discovery Services Gate

| Audit item | Value |
|---|---|
| Status | CURRENT READ-ONLY AUDIT |
| Version | 1.0.0 |
| Audit date | 2026-10-26 |
| Repository baseline | `origin/main` = `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` |
| Audit branch | `feat/vs1-preflight-authority-gates` |
| WordPress runtime | VG Nova Local, `http://localhost:10039/`, WordPress 7.1.3, PHP 8.5.12 |

## Scope and mutation boundary

This task audits existing site search, analytics, content-ranking inputs, and cache boundaries. It does not add search, tracking, ranking, cache rules, or Bricks resources. Runtime inspection used Novamira read-only PHP queries and anonymous GET requests. No order, membership, enrolment, content, setting, ACF, Bricks, or snippet mutation was performed. No Platform Core deployment occurred.

The GET requests returned response cookies. They were not saved or replayed, and no account, cart, checkout, or payment action was submitted. This is Local staging evidence, not production-like cache evidence.

## Search

### Authority and current behaviour

The relevant product requirement is in the approved VS1 specification, sections 38.3 and 38.5: Home should expose prominent search, while VS1 must not expand into a new AI or unified-search programme without evidence. Search implementation and current result scope must be audited before adding a surface.

No active search plugin was found in the runtime plugin list. The current searchable post type flags and a default `WP_Query` search check are:

| Content type | `public` | `publicly_queryable` | `exclude_from_search` | Runtime search sample |
|---|---:|---:|---:|---|
| Article (`post`) | yes | yes | no | Published post 375 matched its title token. |
| Person (`person`) | yes | yes | no | Published Person 1705 matched; the same query also returned one legacy `medewerker`. |
| Course (`mto-course`) | yes | yes | no | Published Course 532 matched its title token. |
| Event (`tc_events`) | yes | yes | no | Published Event 151 matched its title token. |
| Product (`product`) | yes | yes | no | Published Product 525 matched its title token. |

These are native WordPress query results against current Local records. The tokens came from the selected records' titles. They prove that the default query can return each type in this runtime. They do not establish editorial suitability, term relevance, ranking quality, or a production index.

The runtime search-plugin check returned no active plugin names matching search providers. Published Bricks templates and Pages contained no stored Bricks `search` element or `search_form` marker. The anonymous Home and search-result responses also had no search form markup. No result weighting, facet, autocomplete, or custom search implementation was found in the tracked Platform Core and Member Perks code.

Native search currently includes legacy `medewerker` records. Any later Bricks search surface must decide its intended result types and whether legacy profiles remain visible during Task 10B. This preflight does not change or cut over Person consumers.

```text
SEARCH_VS1=ADAPT
```

Reuse the native WordPress search query. A later bounded implementation plan must add the approved Bricks search surface and define which public post types it exposes. No separate search engine or AI-search scope is justified by current evidence.

## Analytics and funnel observability

The tracked source scan found no GA4, Google Tag Manager, `dataLayer`, Meta Pixel, ecommerce event, or custom funnel-event implementation in the Platform Core or Member Perks source trees. The active runtime plugin inventory had no Google Analytics, GTM, Pixel, or Site Kit match. Runtime HTML checks of Home, a search result, Cart, Checkout, and My Voelgoed Learning found no GTM, GA4/gtag, `dataLayer`, or `fbq` markers.

Runtime option-name inspection returned WooCommerce Analytics configuration keys only. Runtime database table names included FluentCRM campaign/funnel metrics and Fluent Forms form analytics. Those stores do not prove site page-view, content-popularity, or VS1 funnel tracking. No customer-facing event naming convention was found in the tracked code or tested HTML.

| VS1 milestone from spec section 41 | Current tracking evidence |
|---|---|
| Featured-offer exposure and click | Not proven |
| Membership savings CTA engagement | Not proven |
| Drawer versus landing entry | Not proven |
| Membership checkout start and purchase | Not proven |
| Successful return to the original intent | Not proven |
| Course purchase and Start Course click | Not proven |
| My Voelgoed click | Not proven |

The funnel stages above are an audit map only. No event was created or sent. A later plan must select the event owner and naming convention, preserve Woo and plugin transaction authorities, and keep personal or entitlement data out of analytics.

```text
ANALYTICS_GATE=GAP
```

## Content ranking inputs

The approved specification prefers rolling popularity with recency and optional editorial priority, and directs the implementation to audit existing counters first. The current read-only source scan found no first-party page-view or popularity counter, GA4 import, or analytics-backed content-ranking service. Runtime post-meta keys matching view, popularity, engagement, analytics, hit, or page-view patterns returned review metadata only. No page-view or popularity table was found. WooCommerce Analytics and campaign/form metrics are not evidence of Article popularity input.

```text
CONTENT_RANKING_INPUT=NONE
```

Do not add a counter or ranking store during preflight. The later product plan can decide whether recency and an approved editorial signal are sufficient for the initial `Latest & Useful` result set.

## Cache and state boundaries

### Current Local response observations

Anonymous GETs were made without a cookie jar. Response headers are current Local/Nginx observations only.

| Request | Result | Cache headers observed | Evidence limit |
|---|---|---|---|
| `/` | 200 | `Cache-Control: no-store, no-cache, must-revalidate`; `Vary: Accept-Encoding` | Does not identify the origin of the policy or prove upstream cache storage. |
| `/?s=vs1-preflight-audit` | 200 | `Cache-Control: no-store, no-cache, must-revalidate`; `Vary: Accept-Encoding` | Search query only; no result-scope or shared-cache test. |
| `/cart/` | 200 | `Cache-Control: max-age=60`; `Vary: Accept-Encoding` | Response also set a cookie. A header alone does not prove the page was stored or shared. |
| `/checkout/` | 302 | Redirected to the HTTPS checkout URL; redirect response was private/no-cache | The HTTPS target was not followed; no checkout or order was created. |
| `/my-voelgoed/learning/` | 302 to login | `private, no-store, no-cache, must-revalidate, max-age=0`; `Vary: Cookie` | Anonymous route only; authenticated response was not refreshed in this task. |

No `CF-Cache-Status` or `X-LiteSpeed-Cache` header appeared in these responses. The selected Local runtime is Nginx; Local results do not establish Cloudflare, LiteSpeed, or production cache configuration. Product, Course, Person, Event, PMPro-gated content, and authenticated member-price pages were not all individually exercised.

### Source policy and known cache scope

The tracked root Platform Core assembly contains `voelgoed-platform-core/includes/Performance/CachePolicy.php`. `CachePolicy::is_dashboard_request()` limits the no-cache policy to `/my-voelgoed` and its descendants (lines 156–170). `apply()` defines `DONOTCACHEPAGE`; `send_no_cache_headers()` emits private/no-store headers (lines 71–112 and 181–188). LiteSpeed hooks are conditional on LiteSpeed being detected (lines 191–220). This policy does not define general Cart, Checkout, Product, Course, or PMPro page-cache rules.

`DashboardQueryProvider::filter_cache_key()` adds the current user ID and dashboard view marker to the Bricks query cache key (lines 297–331). `DashboardRegistry::card_for_provider()` keeps provider cards in an in-request array keyed by card and user (lines 193–198). The current Member Perks `RequestCache` is also an in-request array (`voelgoed-member-perks/includes/Support/RequestCache.php`, lines 12–44). These are source-level boundaries. The 2026-09-21 unified-dashboard QA reported private/no-store responses and per-user Bricks cache isolation, but that is historical QA, not fresh logged-in runtime proof.

The membership commerce audit found that `WooDiscountEngine` recalculates cart prices at `woocommerce_before_calculate_totals` and after coupon changes (`voelgoed-member-perks/includes/Woo/WooDiscountEngine.php`, lines 34–49). `WooPriceDisplay::append_member_hint()` displays only a generic “Member benefit may apply in cart” hint, not a tier price (`voelgoed-member-perks/includes/Woo/WooPriceDisplay.php`, lines 18–35). The audited `MembershipProfileResolver` has request-local cache clearing support, but no call site was found that clears it after a PMPro membership change (`voelgoed-member-perks/includes/Membership/MembershipProfileResolver.php`, lines 30–55 and 144–155; see also `VS1_MEMBERSHIP_COMMERCE_GATE_v1.0.0.md`).

The critical `non-member → active member` cache transition is not proven. No order, membership, or entitlement transition was performed. Shared-cache behaviour after activation, member-price refresh, nudge removal, and cross-user isolation on public offer pages needs a controlled later runtime task. Production-like LiteSpeed/Cloudflare behaviour also remains unproven.

```text
CACHE_STATE_TRANSITION_GATE=NOT_PROVEN
RUNTIME_PROOF_REQUIRES_MUTATION=YES
```

### Source/runtime classification

| Conclusion | Authority | Classification |
|---|---|---|
| WordPress native search can return Article, Person, Course, Event, and Product in the selected Local runtime | Runtime `get_post_type_object()` flags and default `WP_Query` results | `RUNTIME PROVEN`; current Local staging. Records are not approved representative Journey data. |
| A Bricks search UI is not present in inspected published Bricks content | Runtime published template/Page metadata and anonymous Home/search HTML | `RUNTIME PROVEN`; current Local staging; no production-like claim. |
| No GA4/GTM/DataLayer/Meta tags appeared in tested responses | Active-plugin inventory and anonymous GET HTML checks | `RUNTIME PROVEN`; current Local staging; does not prove other environments. |
| Tracked source has no custom search, funnel analytics, or popularity implementation | Source search of Platform Core and Member Perks code | `SOURCE PROVEN`; current source at the audit baseline. |
| Dashboard no-store policy applies only to `/my-voelgoed` | `CachePolicy::is_dashboard_request()`, `apply()`, and `headers()` | `SOURCE PROVEN`; the matching CachePolicy file hash was separately verified in Task 5. |
| Logged-in dashboard and Bricks cache isolation passed staging checks | `unified-dashboard-qa.md`, dated 2026-09-21 | `RUNTIME PROVEN`; historical QA fixture/staging evidence, not current re-test or production-like cache proof. |
| Course/member public-page cache behavior after membership activation | No authorized transition test or production-like cache layer | `NOT PROVEN`; requires a later controlled runtime task. |

Task 1 identified Platform Core source/runtime drift overall. Task 5 verified matching runtime hashes for the selected LearningService, AccessGuard, and CachePolicy files. This does not clear the overall Platform Core drift or prove production cache behaviour.

## Gate ruling

```text
SEARCH_VS1=ADAPT
ANALYTICS_GATE=GAP
CONTENT_RANKING_INPUT=NONE
CACHE_STATE_TRANSITION_GATE=NOT_PROVEN
SOURCE_RUNTIME_CONFLICT=YES
DISCOVERY_SERVICES_GATE=GAP
RUNTIME_PROOF_REQUIRES_MUTATION=YES
WORDPRESS_BUSINESS_MUTATIONS=0
BRICKS_MUTATIONS=0
ACF_MUTATIONS=0
ORDERS_CREATED=0
MEMBERSHIPS_CREATED=0
ENROLMENTS_CREATED=0
```

The source/runtime conflict refers to the broader Platform Core parity drift recorded in `VS1_CURRENT_REALITY_AUDIT_v1.0.0.md`. No direct conflict was found for the inspected `CachePolicy` file or Local response headers. The discovery services gate remains a gap because there is no search UI, no proven VS1 funnel telemetry, and no current or production-like proof for the member-state cache transition.
