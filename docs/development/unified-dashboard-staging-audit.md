# Unified dashboard staging audit

**Date:** 2026-09-19
**Scope:** Staging integration contract for the authenticated `/my-voelgoed/` dashboard.

## Audit method

This audit combines the verified staging facts supplied for this task with a read-only inspection of the local repository and the `voelgoed-member-perks` submodule. It records integration contracts, not a second source of plugin state.

The MCP and network checks from the implementation plan were not run in this pass because they stalled in earlier attempts. This document does not claim fresh `doctor`, ability-discovery, or live WordPress CLI output.

Page ID evidence below comes from the supplied staging snapshot. The PMPro page title and shortcode mapping comes from the supplied external `backup-pre-cleanup-20260528.sql` staging backup. That backup is not present in this checkout, so the mapping is not independently reproducible here. These references were not rechecked against live WordPress.

## Staging profile

The supplied staging snapshot reports the following installed stack. Because live MCP and network checks were unavailable, these versions are recorded as reported staging facts and remain unverified in this pass.

| Component | Reported version or profile fact | Contract role |
| --- | --- | --- |
| WordPress | 7.1.1 | WordPress page, user, URL, and authentication primitives |
| PHP | 8.5.3 | Runtime for WordPress and the integration plugin |
| Bricks child theme | Active child theme | Site-level theme integration |
| Bricks Builder | Installed, version not supplied | Site presentation and Bricks templates |
| Automatic.css | Installed, version not supplied | ACSS utility classes, variables, and responsive tokens |
| Advanced Custom Fields Pro | 6.8.1 | Existing field and configuration interface where a provider uses ACF |
| Masteriyo | 3.4.0 | Course ownership and course access |
| Paid Memberships Pro | 3.8.5 | Membership access and membership status |
| WooCommerce | 11.1.0 | Orders, account actions, downloads, addresses, and payment methods |
| WooCommerce Subscriptions | 8.8.1 | Recurring billing and subscription actions |
| Tickera | 3.5.7.4 | Tickets, event ticket status, QR codes, and ticket downloads |
| Voelgoed Member Perks | 0.18.0 | Member-benefit configuration, eligibility, and hub destination resolution |

The dashboard must keep these authorities separate. PMPro remains the membership access authority. Woo Subscriptions remains the billing authority. Masteriyo owns course access. Tickera owns ticket data and ticket actions. The dashboard presents normalized summaries and links to native provider actions; it must not copy provider state into a new master table.

## Native staging page references

These IDs identify the current staging pages only. They are migration and integration references, not portable constants. Runtime code and Bricks templates must resolve the relevant page through the owning plugin setting, URL helper, or member-perks repository.

| Provider or area | Current page reference | Use in the dashboard contract |
| --- | ---: | --- |
| WooCommerce | My Account, page `74` | Link to the native WooCommerce account surface and its endpoints |
| Paid Memberships Pro | Page `60`, Membership Account, `[pmpro_account]` | Membership account and access view |
| Paid Memberships Pro | Page `61`, Membership Billing, `[pmpro_billing]` | PMPro billing details view. Billing authority remains Woo Subscriptions. |
| Paid Memberships Pro | Page `62`, Membership Cancel, `[pmpro_cancel]` | PMPro membership cancellation view |
| Paid Memberships Pro | Page `63`, Membership Checkout, `[pmpro_checkout]` | PMPro checkout route. Direct Voelgoed level sign-up remains disabled by the billing architecture. |
| Paid Memberships Pro | Page `64`, Membership Confirmation, `[pmpro_confirmation]` | PMPro confirmation view |
| Paid Memberships Pro | Page `65`, Membership Orders, `[pmpro_invoice]` | PMPro invoice and order view |
| Paid Memberships Pro | Page `66`, Membership Levels, `[pmpro_levels]` | PMPro levels view |
| Paid Memberships Pro | Page `67`, Log In, `[pmpro_login]` | PMPro login route used by unauthenticated account visitors |
| Paid Memberships Pro | Page `68`, Your Profile, `[pmpro_member_profile_edit]` | PMPro profile-edit view |
| Masteriyo | Account, page `433` | Link to the native Masteriyo account and course delivery flow |
| Voelgoed Member Perks | My Voordele, page `990` | Current main member-benefits hub reference |
| Voelgoed Member Perks | My Vriendinneklub, page `991` | Current programme hub reference |
| Voelgoed Member Perks | My Voelgoednetwerk, page `992` | Current programme hub reference |
| Generic library | No page found in the supplied snapshot | No route or dashboard tab may assume a generic `Biblioteek` authority |

The member-perks page titles and IDs are useful for staging verification only. The dashboard must not embed `990`, `991`, or `992`. The PMPro titles and shortcode functions above are also staging references. Resolve PMPro settings at runtime rather than treating any page ID as a constant. The current design keeps WooCommerce downloads under `Bestellings`, Masteriyo resources under `Kursusse`, and Tickera files under `Kaartjies`. Editorial member content remains in the separate `/vriendin/` experience.

## Provider boundaries

The first dashboard release uses these boundaries:

| Dashboard concern | Authority | Required integration behavior |
| --- | --- | --- |
| `Lidmaatskap` | PMPro | Read membership level, access status, and supported membership actions from PMPro. Do not put billing controls in this view. |
| `Subskripsies` | Woo Subscriptions | Read all relevant subscriptions, including subscriptions that are not linked to PMPro. Keep payment, renewal, and cancellation actions in Woo. |
| `Bestellings` | WooCommerce | Read orders, downloads, addresses, and saved payment methods through WooCommerce account APIs or native endpoints. |
| `Kursusse` | Masteriyo | Read owned or accessible courses, enrolment, progress, and certificates from Masteriyo. |
| `Kaartjies` | Tickera | Read the current user's tickets and use Tickera's native status, QR, and download actions. |
| `Lede-area` | Voelgoed Member Perks | `vgmp_enable_hub_display` is a staging prerequisite. At the inspected submodule commit, the current public member-perks surface does not expose a feature-flag accessor. `MemberAreaService` must require an approved public capability or configuration API before release. If that API is unavailable, return invisible and do not call `HubAccess`. If it reports the flag as false, hide the tab. Do not direct-read ACF or hardcode the setting in dashboard runtime. |
| `Biblioteek` | None | Do not expose a generic library tab until its content source, access rules, and route are defined. |

The conditional member tab is intentionally separate from `Lidmaatskap`. PMPro answers whether the user has membership access. Member Perks answers whether the user can open the configured benefits hub.

## Member-perks hub contract

The local member-perks source was read without modifying the submodule. The inspected submodule commit was `972a279be7a753594888e974132562e1cc099e24`.

- `voelgoed-member-perks/includes/Display/HubAccess.php`
- `voelgoed-member-perks/includes/Hub/HubPageResolver.php`
- `voelgoed-member-perks/includes/Config/SettingsRepository.php`

At the inspected submodule commit, the current public member-perks surface does not expose a feature-flag accessor for `vgmp_enable_hub_display`. This API is a staging prerequisite for the dashboard. `MemberAreaService` must require the approved public capability or configuration API before calling `HubAccess`; if it is unavailable, the service must return invisible.

`HubAccess::resolve(HubAccess::CONTEXT_MAIN)` is the required contract for `Lede-area`. The dashboard should use the returned `can_view_hub` flag and, when it is true, apply the destination guard below before building a link from the returned `hub_page_id`. It must not hardcode a page ID.

The local implementation returns these main-context states:

| State | `can_view_hub` | Destination behavior |
| --- | --- | --- |
| `logged_out` | `false` | No hub link |
| `dependency_missing` | `false` | No hub link; fail closed |
| `no_membership` | `false` | No member hub link; the resolver may return the configured main page as `upsell_page_id` |
| `can_view` | `true` | Use the configured `hub_page_id` |

For the main context, `HubAccess` reads `main_dashboard_page_id` through `SettingsRepository::hub_settings()`. That setting is the destination source. `HubAccess::resolve()` checks the current user, mapping availability, and the resolved membership profile before returning `can_view`. `HubPageResolver` handles page and programme resolution separately. It does not replace the `HubAccess` user and membership check.

`HubPageResolver` separately handles programme context. `auto` uses the current queried page when no page ID is passed, resolves only configured hub pages, and fails closed for a missing page, an unmapped page, or a hub that does not support the requested context. Explicit `all` mode has separate semantics. It returns the valid `all` programme context without resolving a page. This keeps programme selection in member-perks configuration rather than dashboard constants.

The dashboard integration therefore follows this sequence:

1. Resolve the public member-perks service and require its approved capability/configuration API. At the inspected commit, no public flag accessor exists, so the service must return invisible and not call `HubAccess` until staging supplies this API.
2. Once the approved API exists, read `vgmp_enable_hub_display` through it. If the flag is unavailable or false, return invisible and do not call `HubAccess`.
3. Call `resolve(HubAccess::CONTEXT_MAIN)` only after the service and display flag checks pass. A `dependency_missing` result from `HubAccess` is also a hidden-tab failure, but it is not the only failure mode.
4. Hide `Lede-area` unless `can_view_hub` is true.
5. Even when `can_view_hub` is true, require `hub_page_id > 0` and call `get_permalink($hub_page_id)`.
6. Require that permalink to be non-empty and a same-site URL. If either check fails, fail closed and hide the link.
7. Leave programme-specific choices inside the configured member-perks hub.

## Bricks hooks verified in local source

The following hooks were verified in the local member-perks source, not through live staging. A dashboard Bricks adapter may follow these established patterns.

### Dynamic data

`voelgoed-member-perks/includes/Query/BricksHubDynamicData.php` registers its hooks on `init` at priority `20`, after checking that `\Bricks\Query` exists:

| Hook | Local callback behavior |
| --- | --- |
| `bricks/dynamic_tags_list` | Adds named dynamic tags for member-perks loop action fields |
| `bricks/dynamic_data/render_content` | Resolves tokens in text and custom-attribute content. The source gives this filter priority `11` and accepts three arguments. |
| `bricks/dynamic_data/render_tag` | Resolves an individual dynamic tag. The source gives this filter priority `20` and accepts three arguments. |

The source explicitly handles `render_content` because Bricks custom attributes and text fields do not rely only on `render_tag`. The registered tags include `{vgmp_action_type}`, `{vgmp_action_label}`, `{vgmp_action_disabled}`, `{vgmp_action_message}`, `{vgmp_target_type}`, `{vgmp_target_id}`, `{vgmp_course_id}`, `{vgmp_quantity}`, `{vgmp_claim_status}`, `{vgmp_claim_status_label}`, `{vgmp_claims_remaining}`, and `{vgmp_claims_remaining_known}`.

### Query loops and cache keys

`voelgoed-member-perks/includes/Query/BricksQueryProvider.php` also registers its hooks on `init` at priority `20`, after the same Bricks availability check:

| Hook | Local callback behavior |
| --- | --- |
| `bricks/posts/query_vars` | Detects the site-standard `vg_query_type` setting. For `vgmp_current_user_perks`, it builds a user-scoped member-perks query or an empty query when the context is invalid. |
| `bricks/query/cache_key` | Adds the current user marker and a hash of the query context to the Bricks cache key for member-perks loops. |

The local query type is `vgmp_current_user_perks`. Its query settings accept `vgmp_programme`, including `auto`. The provider uses `HubPageResolver` for `auto`, stores the resolved loop context, and adds these internal markers to query arguments:

- `_vgmp_query_type`
- `_vgmp_user_id`
- `_vgmp_context_key`

The dashboard query provider must preserve the same privacy rule. User-specific query results and dynamic values must include the current user and view context in cache isolation. An unauthenticated or invalid context must not fall back to public member data.

## Cache and response contract

Normalized user output is request-local by default. Dashboard HTML and fragments must not use a shared persistent, page, or edge cache. Do not put normalized dashboard cards or user-specific dynamic values in a shared persistent cache.

At the dashboard page boundary, apply cache controls before page or edge-cache decisions:

- Set `DONOTCACHEPAGE` when the page-cache layer supports it.
- Apply the equivalent page-cache exclusions for the installed page-cache and edge-cache layers.
- Send private, no-store response handling for dashboard HTML and fragments, such as `Cache-Control: private, no-store` where the response layer permits it.

The Bricks query cache key must include the current user and dashboard view context, as the local member-perks provider does. That key is an additional isolation measure. It is not sufficient by itself to make dashboard HTML, fragments, or normalized user output safe for shared caching.

Cache verification must check both response headers and behavior. The test or QA path must confirm the private/no-store headers, the `DONOTCACHEPAGE` or equivalent exclusion signal, and that a later request cannot receive another user's dashboard HTML or fragments from a page or edge cache.

## Authentication and security contract

- Every `/my-voelgoed/` route and child view requires a logged-in user.
- Unauthenticated visitors go to the configured login page with a validated return URL. Never accept an arbitrary external return target.
- Check the current user before every membership, subscription, order, ticket, course, or profile query and account action.
- Mark dashboard pages as non-indexable.
- Exclude full-page, fragment, and edge caches for dashboard responses. Never serve one user's dashboard HTML to another user.

## Implementation constraints

- Treat all page IDs in this file as staging references. Resolve them at runtime.
- Keep PMPro as the access authority and Woo Subscriptions as the billing authority.
- Use Masteriyo for course access and Tickera for tickets. Do not infer either from orders or PMPro state.
- Delegate `Lede-area` visibility and its destination to `HubAccess::resolve(HubAccess::CONTEXT_MAIN)`.
- Do not hardcode member-perks hub IDs, PMPro level IDs, Woo product IDs, or billing terms.
- Treat the approved public `vgmp_enable_hub_display` API as a staging prerequisite. If it is unavailable, `MemberAreaService` returns invisible before `HubAccess` runs.
- Do not create a generic `Biblioteek` page or tab in this release.
- Do not modify `voelgoed-member-perks` as part of the dashboard implementation. The submodule remains the member-perks code authority.

## Local evidence used

- `AGENTS.md`
- `docs/development/membership-billing-architecture.md`
- `docs/superpowers/specs/2026-09-19-voelgoed-unified-dashboard-design.md`
- `voelgoed-member-perks/AGENTS.md`
- `voelgoed-member-perks/includes/Display/HubAccess.php`
- `voelgoed-member-perks/includes/Hub/HubPageResolver.php`
- `voelgoed-member-perks/includes/Config/SettingsRepository.php`
- `voelgoed-member-perks/includes/Query/BricksHubDynamicData.php`
- `voelgoed-member-perks/includes/Query/BricksQueryProvider.php`
- Submodule commit `972a279be7a753594888e974132562e1cc099e24`
- Supplied staging snapshot for current page IDs and installed versions
- Supplied external `backup-pre-cleanup-20260528.sql` staging backup, not present in this checkout, for PMPro page titles and shortcode mapping
