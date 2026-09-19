# Voelgoed unified customer dashboard design

**Status:** Approved for implementation

**Date:** 2026-09-19

## Goal

Create one authenticated customer dashboard at `/my-voelgoed/`. It gives users one place to see and manage their Voelgoed account across Paid Memberships Pro (PMPro), WooCommerce, Woo Subscriptions, Tickera, Masteriyo, and the existing Voelgoed Member Perks plugin.

The dashboard is a self-service account application. It is separate from `/vriendin/`, which remains the content and discovery hub for members.

## Decisions

- Use `/my-voelgoed/` as the canonical dashboard root.
- Use a top-tab navigation pattern on desktop and a responsive, horizontally scrollable tab pattern on small screens.
- Keep every visible dashboard label, button, empty state, status, and alert in Afrikaans.
- Keep technical route slugs and plugin identifiers stable where they protect native WordPress and WooCommerce routing. The UI language does not require English labels.
- Use the authenticated WordPress user ID as the identity key across all services.
- PMPro is the authority for membership access and membership status.
- Woo Subscriptions is the authority for payment and billing. A subscription may be linked to a PMPro membership product or may be independent of PMPro.
- Masteriyo is the authority for paid course ownership, enrolment, progress, and certificates. The dashboard must show every course the user owns or can access.
- WooCommerce is the authority for orders, downloads, addresses, and saved payment methods.
- Tickera is the authority for ticket ownership, ticket status, event details, QR codes, and ticket downloads.
- The dashboard reads each system as its source of truth. It does not copy membership, subscription, order, ticket, or course state into a new master ACF table.
- Keep member-perks configuration and eligibility inside the `voelgoed-member-perks` plugin. The dashboard may consume its public access resolver, but must not recreate its programme rules.
- Reuse native provider actions for payment, subscription, order, ticket, and course operations. The dashboard supplies a consistent shell and deep links rather than replacing secure provider workflows.

## Entitlement model

| Customer concept | Practical meaning | Source of truth | Dashboard label |
| --- | --- | --- | --- |
| PMPro membership | Access entitlement for membership-gated content and member benefits | PMPro membership level and status | `Lidmaatskap` |
| Woo Subscription | Billing agreement for a recurring product or service. It may or may not grant a PMPro membership. | Woo Subscriptions | `Intekeninge` |
| Paid Masteriyo course | A course the user owns or is entitled to access | Masteriyo enrolment and course access | `Kursusse` |

The overview must keep these concepts separate. A Woo subscription card must not imply that every subscription is a PMPro membership. A PMPro card must not display billing controls that belong to Woo Subscriptions.

## Navigation and routes

The following labels are the approved first-pass Afrikaans UI copy. The route suffixes remain implementation keys and may be mapped to native WooCommerce endpoints where appropriate.

| Route | Afrikaans label | Primary responsibility |
| --- | --- | --- |
| `/my-voelgoed/` | `Oorsig` | Personal summary, alerts, and next actions |
| `/my-voelgoed/membership/` | `Lidmaatskap` | PMPro levels, access state, expiry, invoices, and membership actions |
| `/my-voelgoed/subscriptions/` | `Intekeninge` | Woo Subscriptions status, next payment, payment state, renew, and cancel actions |
| `/my-voelgoed/orders/` | `Bestellings` | WooCommerce order history, receipts, and downloads |
| `/my-voelgoed/tickets/` | `Kaartjies` | Tickera tickets, upcoming events, QR codes, and downloads |
| `/my-voelgoed/learning/` | `Kursusse` | All owned or accessible Masteriyo courses, progress, and certificates |
| `/my-voelgoed/library/` | `Biblioteek` | Member resources, saved items, and private downloads |
| `/my-voelgoed/profile/` | `Profiel` | Personal details, password, addresses, preferences, and communication settings |
| `/my-voelgoed/payment-methods/` | `Betaalmetodes` | WooCommerce saved payment methods. Expose this as a profile action or nested view unless testing shows it needs a top-level tab. |

The overview may refer to `Aflaaie` inside `Bestellings` and `Biblioteek`, but it must not create a second download authority.

## Conditional member tab

Add one dynamic top-level tab labelled `Lede-area`.

The tab is rendered only when the authenticated user has an active Vriendinneklub or Voelgoednetwerk programme according to the existing member-perks access resolver. It links to the configured custom-plugin hub. It does not appear for ordinary WooCommerce customers, learners, ticket holders, or users with no active programme.

The destination is resolved through the existing member-perks configuration and `HubAccess` flow. Do not hardcode PMPro level IDs or hub page IDs in the dashboard. If the user has more than one eligible programme, the configured main hub remains the single entry point. Programme-specific hub links can be provided inside that hub when needed.

`Lede-area` is intentionally distinct from `Lidmaatskap`. `Lidmaatskap` manages the PMPro access entitlement; `Lede-area` opens the member content and benefits destination.

The implementation may use `My voordele` as an alternative internal page title, but the navigation label remains `Lede-area` unless the product owner changes the copy.

## Overview content

The overview answers three questions: what the user has, what needs attention, and what they can do next.

- Welcome and profile summary: name, avatar, and account state.
- `Lidmaatskap`: current PMPro level, status, expiry or renewal date, and a link to manage access.
- `Intekeninge`: all active, paused, on-hold, or failed Woo subscriptions, including the next payment date and a link to manage billing.
- `Kursusse`: count of owned or accessible Masteriyo courses, the next course to continue, progress, and certificates.
- `Kaartjies`: the next upcoming event, ticket count, QR or download action, and event date.
- `Bestellings`: recent WooCommerce orders and download availability.
- `Biblioteek`: member resources and saved items available to the user.
- `Aandag nodig`: failed payment, expiring membership, upcoming event, incomplete profile, or course-progress reminders.

Cards are summaries. Full management views open through the tabs and native provider actions.

## Service boundary

Implement a small adapter boundary for the dashboard presentation layer. Each adapter returns a small, normalized view model and keeps plugin-specific data structures out of Bricks templates.

- `Membership_Service`: PMPro level, status, expiry, invoices, and access actions.
- `Subscription_Service`: Woo Subscriptions state, next payment, payment method, and manage actions. It must include standalone subscriptions as well as membership-linked subscriptions.
- `Commerce_Service`: Woo orders, downloads, addresses, and payment methods.
- `Ticket_Service`: Tickera tickets grouped by upcoming and past events, with secure QR and download actions.
- `Learning_Service`: Masteriyo owned or accessible courses, enrolment state, progress, next lesson, and certificates.
- `Member_Area_Service`: member-perks eligibility and configured hub URL. It delegates eligibility and destination decisions to the existing custom plugin.

The adapters may return empty states when a plugin is unavailable or the user has no records. An unavailable integration must not break the rest of the dashboard.

## Bricks realization

- Build the shell in Bricks using existing global classes, ACSS variables, responsive controls, and reusable template parts.
- Use dynamic data for user and plugin values. Do not hardcode account data in a Bricks template.
- Use conditions to hide tabs and cards when the user has no applicable data or entitlement.
- Use a reusable tab component with an active-state data attribute. JavaScript, if needed, targets data attributes rather than visual classes.
- Keep the overview concise. Do not embed five full vendor dashboards inside the overview.
- Link to native WooCommerce account endpoints and provider actions for operations that involve payment, cancellation, downloads, ticket validation, or course delivery.

## Access, security, and caching

- Require authentication for every dashboard route and view.
- Send unauthenticated visitors to the configured login page with a validated return URL.
- Check the current user before every service query and every account action.
- Do not expose another user’s orders, memberships, tickets, courses, downloads, or payment data through query parameters or client-side state.
- Mark the dashboard and its routes as non-indexable.
- Exclude user-specific dashboard pages and fragments from page and edge caches. Do not serve member-specific HTML from a shared cache.
- Keep payment and subscription mutations inside WooCommerce, Woo Subscriptions, and PMPro’s supported actions.

## Delivery sequence

1. Create the Bricks shell, authentication guard, responsive tabs, and Afrikaans copy system.
2. Implement `Oorsig`, `Profiel`, `Bestellings`, and `Lidmaatskap` using native account actions.
3. Add `Intekeninge`, including subscriptions that are not linked to PMPro.
4. Add `Kursusse` with the complete Masteriyo owned/access list.
5. Add `Kaartjies` and secure Tickera actions.
6. Add `Biblioteek` and the conditional `Lede-area` tab.
7. Add alerts, cache exclusions, analytics events, and responsive/accessibility hardening.

## Current staging references

The staging site already has native account pages for WooCommerce, PMPro, and Masteriyo, plus configured member-perks hubs. These are references for integration and migration only, not new authorities:

- WooCommerce account page: current staging page `74`.
- PMPro account, billing, invoice, levels, and profile pages: current staging pages `60` through `68`.
- Masteriyo account page: current staging page `433`.
- Member-perks main and programme hub pages: current staging pages `990`, `991`, and `992`.

The implementation must resolve these through plugin settings and the existing member-perks repositories rather than embedding these staging IDs in templates or code.

## Acceptance criteria

- A logged-in customer can reach all applicable account areas from `/my-voelgoed/`.
- All visible tabs, actions, statuses, empty states, and alerts are Afrikaans.
- `Lidmaatskap` reports PMPro access and does not become a billing duplicate.
- `Intekeninge` shows every relevant Woo subscription, including subscriptions unrelated to PMPro.
- `Kursusse` shows every Masteriyo course the user owns or can access.
- `Bestellings`, `Kaartjies`, and `Biblioteek` show only data belonging to the current user.
- `Lede-area` appears only for active Vriendinneklub or Voelgoednetwerk members and opens the configured custom-plugin hub.
- The member hub remains separate from the `/vriendin/` content experience, with clear links between the two.
- Native WooCommerce, PMPro, Tickera, and Masteriyo management actions remain the source of truth.
- No shared cache returns one user’s dashboard HTML to another user.
- The dashboard remains usable on mobile without replacing the approved Bricks and ACSS design system.
