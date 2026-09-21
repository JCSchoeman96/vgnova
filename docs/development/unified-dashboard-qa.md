# Unified dashboard QA handoff

## Status

The dashboard plugin tree from `850f991` is deployed byte-for-byte and active
on the `vgnova` staging site. The final PR head may add documentation after this
runtime run, but it must retain this exact plugin tree. The provider and Bricks
checks below were exercised against the deployed tree; no production deployment
is implied.

`Aandag nodig` and `Lede-area` are explicitly deferred from this release.
Neither surface is rendered until its provider contract is approved. Member
Perks `0.18.0` has no approved public capability accessor, so the dashboard
continues to fail closed rather than reading private plugin state.

## Runtime QA — 2026-09-21

### Environment

- Deployment/plugin tree: `850f991`, Voelgoed Platform Core `0.1.0`, active
- WordPress `7.1.1`; PHP-FPM `8.5.3`
- Bricks `2.3.5`; Bricks child theme `1.1`
- WooCommerce `11.1.1`; WooCommerce Subscriptions `8.8.1`
- PMPro `3.8.6`; Tickera `3.5.7.4`
- Masteriyo `3.4.2` (dashboard minimum satisfied)
- Voelgoed Member Perks `0.18.0`
- Local/Nginx runtime; LiteSpeed and Cloudflare are not installed

### Evidence status

| Status | Scenario | Evidence |
| --- | --- | --- |
| PASS | Logged-out access | `/my-voelgoed/` and children return `302` to the local login page with a relative, validated `redirect_to`. |
| PASS | User isolation | Users 12 (`vgmp_test_vriendinne`), 14 (`vgmp_test_dual`) and 15 (`vgmp_test_none`) render their own greetings and provider summaries; foreign order/subscription/ticket records are excluded. |
| PASS | Private response | Dashboard responses include `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, `Expires: 0`, and `Vary: Accept-Encoding, Cookie`; `DONOTCACHEPAGE` is defined. |
| PASS | Robots | Root and all six child routes emit `max-image-preview:large, noindex, follow`. |
| PASS | Public-token fail-closed | User-specific tokens on a public `/content/` request resolve empty; context fixtures cover text, attribute, URL, and custom-content escaping. |
| PASS | Bricks cache isolation | Query cache keys differ by user and dashboard view. |
| PASS | Bricks overview | Page 1155 renders normalized membership, subscription, order, ticket, and learning summary tokens. `Aandag nodig` is absent. |
| PASS | Bricks focused views | Pages 1156–1161 render the normalized summary on each focused view. The `subscriptions` slug collision with Woo was fixed by the dashboard route resolver; `/my-account/` remains the native Woo workflow. |
| PASS | Masteriyo missing progress | User 12 has two real enrollments. Masteriyo returns boolean `false` for missing progress; the card remains `availability=available`, contains both courses, and has empty completion/certificate fields with no certificate action. |
| PASS | Woo Subscriptions fixture | Controlled product 1186 produced active subscription 1187 and cancelled subscription 1188 for standalone user 15 (no PMPro membership). The normalized card contains both provider lifecycle statuses; user 14 sees none. |
| PASS | Tickera fixtures | User 15 owns orders 1198–1200 and ticket instances 1202–1204. Upcoming events are ascending (1189 then 1191), the past event is grouped separately, signed native download URLs are present, and foreign instance 1205 is excluded. |
| DEFERRED | `Lede-area` | No approved Member Perks public capability accessor exists in `0.18.0`; the template surface was removed and no entitlement is exposed. Reopen when the accessor is approved and shipped. |
| DEFERRED | `Aandag nodig` | No alert projection/service is approved; no alert card is rendered. |
| RELEASE-GATE | LiteSpeed | Cannot be exercised on Local/Nginx. Verify private/no-store and LiteSpeed hooks on the production-like cache stack before release. |
| RELEASE-GATE | Cloudflare | Cannot be exercised locally. Verify `/my-voelgoed*` bypass and `CF-Cache-Status` for two authenticated users before release. |
| OPTIONAL | Hostile real user value | Context-specific escaping is covered by automated/live adapter fixtures. A temporary WordPress display-name E2E check remains useful release confidence, not a merge blocker. |

### Bricks surfaces exercised

- Overview: page `1155`
- Focused pages: membership `1156`, subscriptions `1157`, orders `1158`,
  tickets `1159`, learning `1160`, profile `1161`
- Shared account section/header: template `1172`

The overview and focused pages consume the dashboard dynamic-data summaries;
provider-native pages remain authoritative for mutations and full history.

### Controlled fixture notes

The Woo and Tickera records above were created through supported staging APIs
and are labelled QA fixtures; no payment or external side effect was used.
They remain available for repeatable review. They must not be copied to
production.

## Local checks

Run from the repository root:

```bash
voelgoed-platform-core/bin/check.sh
git diff --check
```

The workflow at `.github/workflows/voelgoed-dashboard.yml` repeats both checks
on the exact pull-request head SHA.

## Final release gate

Before production deployment, repeat the LiteSpeed and Cloudflare checks above
against the exact commit that passed GitHub Actions and confirm the plugin
filesystem matches that commit byte-for-byte. Do not merge or deploy a later
plugin tree without a new exact-head CI run.
