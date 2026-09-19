# Unified dashboard staging audit

**Date:** 2026-09-19  
**Scope:** Staging integration contract for the authenticated `/my-voelgoed/` dashboard.

## Audit method

This audit combines the verified staging facts supplied for this task with a read-only inspection of the local repository and the `voelgoed-member-perks` submodule. It records integration contracts, not a second source of plugin state.

The MCP and network checks from the implementation plan were not run in this pass because they stalled in earlier attempts. This document does not claim fresh `doctor`, ability-discovery, or live WordPress CLI output.

## Staging profile

The supplied staging snapshot reports the following installed stack:

| Component | Version or profile fact | Contract role |
| --- | --- | --- |
| WordPress | 7.1.1 | WordPress page, user, URL, and authentication primitives |
| PHP | 8.5.3 | Runtime for WordPress and the integration plugin |
| Theme | Bricks child theme | Site presentation and Bricks templates |
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
| Paid Memberships Pro | Account, billing, confirmation, checkout, cancel, invoice, levels, and profile pages, IDs `60` through `68` | Use PMPro settings and supported URL helpers for membership actions |
| Masteriyo | Account, page `433` | Link to the native Masteriyo account and course delivery flow |
| Voelgoed Member Perks | My Voordele, page `990` | Current main member-benefits hub reference |
| Voelgoed Member Perks | My Vriendinneklub, page `991` | Current programme hub reference |
| Voelgoed Member Perks | My Voelgoednetwerk, page `992` | Current programme hub reference |
| Generic library | No page found in the supplied snapshot | No route or dashboard tab may assume a generic `Biblioteek` authority |

The member-perks page titles and IDs are useful for staging verification only. The dashboard must not embed `990`, `991`, or `992`. The current design keeps WooCommerce downloads under `Bestellings`, Masteriyo resources under `Kursusse`, and Tickera files under `Kaartjies`. Editorial member content remains in the separate `/vriendin/` experience.

## Provider boundaries

The first dashboard release uses these boundaries:

| Dashboard concern | Authority | Required integration behavior |
| --- | --- | --- |
| `Lidmaatskap` | PMPro | Read membership level, access status, and supported membership actions from PMPro. Do not put billing controls in this view. |
| `Subskripsies` | Woo Subscriptions | Read all relevant subscriptions, including subscriptions that are not linked to PMPro. Keep payment, renewal, and cancellation actions in Woo. |
| `Bestellings` | WooCommerce | Read orders, downloads, addresses, and saved payment methods through WooCommerce account APIs or native endpoints. |
| `Kursusse` | Masteriyo | Read owned or accessible courses, enrolment, progress, and certificates from Masteriyo. |
| `Kaartjies` | Tickera | Read the current user's tickets and use Tickera's native status, QR, and download actions. |
| `Lede-area` | Voelgoed Member Perks | Render only for an active configured member programme and use the configured main hub destination. Do not recreate programme eligibility in dashboard code. |
| `Biblioteek` | None | Do not expose a generic library tab until its content source, access rules, and route are defined. |

The conditional member tab is intentionally separate from `Lidmaatskap`. PMPro answers whether the user has membership access. Member Perks answers whether the user can open the configured benefits hub.

## Member-perks hub contract

The local member-perks source was read without modifying the submodule:

- `voelgoed-member-perks/includes/Display/HubAccess.php`
- `voelgoed-member-perks/includes/Hub/HubPageResolver.php`
- `voelgoed-member-perks/includes/Config/SettingsRepository.php`

`HubAccess::resolve(HubAccess::CONTEXT_MAIN)` is the required contract for `Lede-area`. The dashboard should use the returned `can_view_hub` flag and, when it is true, build the link from the returned `hub_page_id`. It must not hardcode a page ID.

The local implementation returns these main-context states:

| State | `can_view_hub` | Destination behavior |
| --- | --- | --- |
| `logged_out` | `false` | No hub link |
| `dependency_missing` | `false` | No hub link; fail closed |
| `no_membership` | `false` | No member hub link; the resolver may return the configured main page as `upsell_page_id` |
| `can_view` | `true` | Use the configured `hub_page_id` |

For the main context, `HubAccess` reads `main_dashboard_page_id` through `SettingsRepository::hub_settings()`. That setting is the destination source. The resolver checks the current user, mapping availability, and the resolved membership profile before returning `can_view`.

`HubPageResolver` separately handles programme context. `auto` uses the current queried page when no page ID is passed, resolves only configured hub pages, and fails closed for a missing page, an unmapped page, or a hub that does not support the requested context. This keeps programme selection in member-perks configuration rather than dashboard constants.

The dashboard integration therefore follows this sequence:

1. Resolve the member-perks service and call `resolve(HubAccess::CONTEXT_MAIN)`.
2. Hide `Lede-area` unless `can_view_hub` is true.
3. Build the destination URL from the returned `hub_page_id` with a WordPress URL helper.
4. Leave programme-specific choices inside the configured member-perks hub.

## Verified Bricks integration hooks

The following hooks were verified in the local member-perks source. A dashboard Bricks adapter may follow these established patterns.

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

## Implementation constraints

- Treat all page IDs in this file as staging references. Resolve them at runtime.
- Keep PMPro as the access authority and Woo Subscriptions as the billing authority.
- Use Masteriyo for course access and Tickera for tickets. Do not infer either from orders or PMPro state.
- Delegate `Lede-area` visibility and its destination to `HubAccess::resolve(HubAccess::CONTEXT_MAIN)`.
- Do not hardcode member-perks hub IDs, PMPro level IDs, Woo product IDs, or billing terms.
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

