# Unified dashboard QA handoff

## Status

Release approval is blocked until the hardened plugin revision is deployed to
the `vgnova` staging site. The local worktree contains the implementation and
unit checks, but it has not been activated on staging. This file records the
required runtime evidence instead of claiming it exists.

The `Aandag nodig` projection is deferred from this release. The dashboard
must not render an alert card until its provider rules and service boundary are
approved.

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
