# Unified dashboard QA handoff

## Status

Release approval is blocked until the hardened plugin revision is deployed to
the `vgnova` staging site. The local worktree contains the implementation and
unit checks, but it has not been activated on staging. This file records the
required runtime evidence instead of claiming it exists.

The `Aandag nodig` projection is deferred from this release. The dashboard
must not render an alert card until its provider rules and service boundary are
approved.

## Runtime QA attempt — 2026-09-21

The exact reviewed plugin revision was copied to the staging plugin directory,
verified byte-for-byte, activated, and then exercised through the live
`vgnova` site:

- Deployment commit: `15f7f3fc61dbe763fefbaebefa95e0f2fd22fd8f`
- Plugin: Voelgoed Platform Core `0.1.0`, active
- WordPress: `7.1.1`
- Bricks: `2.3.5`; Bricks child theme `1.1`
- PHP-FPM response: `8.5.3`
- WooCommerce: `11.1.1`; WooCommerce Subscriptions: `8.8.1`
- PMPro: `3.8.6`; Tickera: `3.5.7.4`
- Masteriyo: `3.4.2` (meets the dashboard minimum)
- Voelgoed Member Perks: `0.18.0`

### Passed with live evidence

- Logged-out requests to `/my-voelgoed/` and child routes return `302` to the
  local login page with a relative, validated `redirect_to` path.
- Authenticated browser sessions for `vgmp_test_vriendinne` (user 12) and
  `vgmp_test_dual` (user 14) render their own greeting and do not contain the
  other user's greeting or order identifiers.
- Dashboard root and child responses return
  `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`,
  `Pragma: no-cache`, `Expires: 0`, and `Vary: Accept-Encoding, Cookie`.
- Dashboard root and all six child routes return a `noindex` robots meta tag.
- The live request path defines `DONOTCACHEPAGE`; the local server is Nginx
  and has no LiteSpeed or Cloudflare runtime installed.
- Request-local Bricks cache keys differ for the same view under user IDs 12
  and 13. Dynamic tokens also resolve to distinct live values (`1 aktiewe
  lidmaatskap` / `9 bestellings` versus `2 aktiewe lidmaatskap` /
  `3 bestellings`).
- User-specific dynamic tokens fail closed on a public `/content/` request,
  and a context fixture confirms text, attribute, URL, and custom-content
  escaping.
- Member Perks resolves user 12/13/14 as eligible and user 15 as
  `no_membership`; the dashboard correctly remains fail-closed because the
  installed Member Perks `0.18.0` has no approved public feature-flag accessor.

### Still blocked or not exercised

- The Bricks pages `1155`–`1161` and section shell `1172` are reachable, but
  their live content is still a static shell. They do not render the
  normalized membership, order, subscription, ticket, or course projections;
  therefore the Bricks data-rendering gate is not passed.
- User 12 has two real Masteriyo enrolments. The adapter returns
  `availability=unavailable` because the installed Masteriyo progress helper
  returns boolean `false` for missing progress and the adapter treats that
  shape as invalid. A no-course user returns the expected available/empty
  card. The learner gate remains blocked until this is corrected and retested.
- No staging Woo Subscription records or Tickera ticket orders/instances are
  present, so standalone subscription, lifecycle, signed-ticket, and event
  ordering checks cannot be claimed.
- No hostile display name has been installed on a real staging user; escaping
  was verified with a live adapter fixture only.
- LiteSpeed and Cloudflare are not present on this Local/Nginx runtime, so
  LiteSpeed behavior and `CF-Cache-Status`/edge bypass require a real
  cache-enabled environment.

Release approval remains blocked. This section deliberately records partial
evidence and does not claim the missing provider or Bricks scenarios passed.

## Local checks

Run from the repository root:

```bash
voelgoed-platform-core/bin/check.sh
git diff --check
```

The workflow at `.github/workflows/voelgoed-dashboard.yml` repeats both checks
on the exact pull-request head SHA.

## Staging deployment gate

- [ ] Deploy the exact reviewed commit to the staging plugin directory.
- [ ] Confirm `MASTERIYO_VERSION` is `3.4.2` or newer before testing course data.
- [ ] Confirm the dashboard pages resolve under `/my-voelgoed/` and children.
- [ ] Confirm WordPress reports the dashboard routes as `noindex`.

## User scenarios

Run each scenario with a separate browser session. Record the account fixture,
the visible cards, and the native provider links.

- [ ] Logged-out visitor is redirected to login with a validated return URL.
- [ ] Ordinary customer sees only data owned by that account.
- [ ] A second ordinary customer cannot see the first customer's HTML, cards,
      orders, subscriptions, tickets, courses, or dynamic values.
- [ ] PMPro member sees membership status and native membership actions.
- [ ] A standalone Woo Subscription customer sees the subscription even when it
      does not grant a PMPro level.
- [ ] A customer with cancelled or expired subscriptions sees provider item
      statuses without an `active` dashboard projection.
- [ ] Masteriyo learner sees every owned or accessible course and progress.
- [ ] Tickera ticket holder sees upcoming tickets in ascending event order and
      past tickets in descending event order.
- [ ] A configured Member Perks user sees one `Lede-area` link to the resolved
      main hub.
- [ ] A user without an eligible Member Perks programme does not see
      `Lede-area`.

## Cache and output isolation

Capture the raw response headers and rendered HTML for two authenticated users.

- [ ] `Cache-Control` includes `private, no-store, no-cache`.
- [ ] LiteSpeed reports the request as private and not cacheable when present.
- [ ] Cloudflare returns a bypass/private result for `/my-voelgoed*`; record
      `CF-Cache-Status` for each user.
- [ ] Bricks query cache keys differ by user and dashboard view.
- [ ] A hostile display name containing quotes, ampersands, and HTML-looking
      text is escaped correctly in text, attributes, ARIA values, and URLs.
- [ ] A public cached page containing a dashboard token does not resolve the
      token or expose any authenticated value.

## Evidence to record

- Staging URL and deployment commit.
- WordPress, Bricks, WooCommerce, Woo Subscriptions, PMPro, Tickera, and
  Masteriyo versions.
- The two test-user fixture descriptions, without storing passwords or tokens.
- Response headers, cache statuses, and the relevant HTML excerpts.
- Any provider integration that was unavailable and the resulting card state.
