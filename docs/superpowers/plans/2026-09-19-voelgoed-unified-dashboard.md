# Voelgoed Unified Customer Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an authenticated `/my-voelgoed/` dashboard that presents PMPro membership access, Woo Subscriptions billing, WooCommerce commerce, Tickera tickets, Masteriyo courses, profile actions, and the conditional member-perks hub in one Afrikaans interface.

**Architecture:** Bricks and Automatic.css own the dashboard shell, layout, responsive behaviour, and visible copy. A small `voelgoed-platform-core` plugin owns only the normalized service adapters, authentication guard, conditional navigation data, Bricks dynamic data, and cache safety that native WordPress and plugin UI cannot express. PMPro, WooCommerce/Woo Subscriptions, Tickera, Masteriyo, and `voelgoed-member-perks` remain the authorities for their own data and actions.

**Tech Stack:** WordPress, Bricks Builder, Automatic.css, PMPro, WooCommerce, Woo Subscriptions, Tickera, Masteriyo, Voelgoed Member Perks, PHP 8.5, plain PHP tests, Novamira/vgnova MCP.

---

## File map

Create the following integration plugin files. The parent `vgnova` repository is the implementation workspace for the new plugin; do not place dashboard logic in `functions.php`, the theme, FluentSnippets, or `voelgoed-member-perks`.

```text
voelgoed-platform-core/
├── voelgoed-platform-core.php
├── bin/check.sh
├── includes/
│   ├── Plugin.php
│   ├── Contracts/DashboardServiceInterface.php
│   ├── Contracts/DashboardCard.php
│   ├── Contracts/DashboardAction.php
│   ├── Config/AfrikaansLabels.php
│   ├── Dashboard/DashboardContext.php
│   ├── Dashboard/DashboardRegistry.php
│   ├── Dashboard/Navigation.php
│   ├── Dashboard/AccessGuard.php
│   ├── Services/MembershipService.php
│   ├── Services/SubscriptionService.php
│   ├── Services/CommerceService.php
│   ├── Services/TicketService.php
│   ├── Services/LearningService.php
│   ├── Services/MemberAreaService.php
│   ├── Bricks/DashboardDynamicData.php
│   ├── Bricks/DashboardQueryProvider.php
│   └── Performance/CachePolicy.php
└── tests/
    ├── bootstrap.php
    ├── run-dashboard-tests.php
    ├── contracts/DashboardCardTest.php
    ├── dashboard/NavigationTest.php
    ├── dashboard/AccessGuardTest.php
    ├── services/MembershipServiceTest.php
    ├── services/SubscriptionServiceTest.php
    ├── services/CommerceServiceTest.php
    ├── services/TicketServiceTest.php
    ├── services/LearningServiceTest.php
    ├── services/MemberAreaServiceTest.php
    └── performance/CachePolicyTest.php
```

Staging presentation artifacts are managed through vgnova MCP rather than committed as local Bricks JSON:

```text
WordPress page: My Voelgoed, slug my-voelgoed
Child pages: membership, subscriptions, orders, tickets, learning, profile
Bricks template: Unified Customer Dashboard Shell
Bricks template parts: Dashboard Tabs, Dashboard Overview Cards, Dashboard Alert
```

There is no `library` page or `Biblioteek` tab in this release. WooCommerce downloads, Masteriyo course resources, and Tickera ticket files stay with their owning views. Editorial member content remains in `/vriendin/`; member benefits use the conditional `Lede-area` link.

---

### Task 1: Capture the staging integration contract

**Files:**
- Create: `docs/development/unified-dashboard-staging-audit.md`
- Read: `AGENTS.md`, `docs/development/membership-billing-architecture.md`, `voelgoed-member-perks/AGENTS.md`

- [ ] **Step 1: Verify the selected staging profile and health.**

Run:

```bash
novamira --version
novamira sites list --json
novamira --site vgnova doctor --json
novamira --site vgnova discover --json
```

Expected: the `vgnova` profile points to the local staging site, `doctor` reports no blocking error, and discovery includes Bricks, ACF, WooCommerce, Woo Subscriptions, PMPro, Tickera, Masteriyo, and `voelgoed-member-perks` abilities.

- [ ] **Step 2: Inspect live ability schemas before using them.**

Use the vgnova MCP adapter to discover and describe the live schemas for:

```text
novamira/run-wp-cli
novamira/bricks-list-templates
novamira/bricks-get-content
novamira/bricks-set-content
novamira/skill-get
```

Load the `acf-integration` site skill before reading or writing ACF options. Do not infer parameters from ability names.

- [ ] **Step 3: Read current account pages and options without mutation.**

Run `novamira/run-wp-cli` through vgnova MCP with these read-only inputs:

```json
{"args":["post","list","--post_type=page","--fields=ID,post_title,post_name,post_parent","--format=table","--posts_per_page=200"]}
{"args":["option","get","woocommerce_myaccount_page_id"]}
{"args":["option","get","pmpro_account_page_id"]}
{"args":["option","get","pmpro_billing_page_id"]}
{"args":["option","get","pmpro_invoice_page_id"]}
```

Record the discovered page IDs as staging references only. The plugin must resolve settings at runtime and must not contain those IDs as constants.

- [ ] **Step 4: Confirm the member-perks destination contract.**

Read `voelgoed-member-perks/includes/Display/HubAccess.php` and `HubPageResolver.php`. Record that the conditional link must call the configured main context and use `can_view_hub` plus the returned page ID. Do not modify the submodule.

- [ ] **Step 5: Document the audit.**

Write `docs/development/unified-dashboard-staging-audit.md` with the plugin availability matrix, current native page references, verified Bricks hooks, and the explicit statement that `Biblioteek` has no current page or authority.

- [ ] **Step 6: Commit the audit.**

```bash
git add docs/development/unified-dashboard-staging-audit.md
git commit -m "Document unified dashboard staging contract"
```

---

### Task 2: Scaffold the plugin and isolated test runner

**Files:**
- Create: `voelgoed-platform-core/voelgoed-platform-core.php`
- Create: `voelgoed-platform-core/bin/check.sh`
- Create: `voelgoed-platform-core/tests/bootstrap.php`
- Create: `voelgoed-platform-core/tests/run-dashboard-tests.php`

- [ ] **Step 1: Add the plugin bootstrap.**

Create a namespaced bootstrap that exits outside WordPress, defines the plugin directory, requires each class once, and registers the service registry on `plugins_loaded`. Use this loading contract:

```php
<?php
declare(strict_types=1);

/**
 * Plugin Name: Voelgoed Platform Core
 * Description: Unified authenticated Voelgoed customer dashboard integrations.
 * Version: 0.1.0
 * Text Domain: voelgoed-platform-core
 */

namespace Voelgoed\PlatformCore;

defined('ABSPATH') || exit;

const VGPC_VERSION = '0.1.0';
const VGPC_PLUGIN_DIR = __DIR__ . '/';

require_once VGPC_PLUGIN_DIR . 'includes/Plugin.php';
require_once VGPC_PLUGIN_DIR . 'includes/Contracts/DashboardServiceInterface.php';
require_once VGPC_PLUGIN_DIR . 'includes/Contracts/DashboardCard.php';
require_once VGPC_PLUGIN_DIR . 'includes/Contracts/DashboardAction.php';
require_once VGPC_PLUGIN_DIR . 'includes/Config/AfrikaansLabels.php';

add_action('plugins_loaded', static function (): void {
    (new Plugin())->register();
}, 20);
```

Create `includes/Plugin.php` as the coordinator. Its `register()` method must instantiate the service registry, navigation, access guard, Bricks integrations, and cache policy. Keep the coordinator limited to wiring; business logic belongs in the focused classes listed in the file map.

- [ ] **Step 2: Add the check runner.**

The check runner must fail on missing `php` or `rg`, lint every tracked PHP file, run the dashboard test runner, and scan production PHP for hardcoded PMPro level IDs, Woo product IDs, direct ACF reads, and raw user-specific output outside the approved service boundary.

Run:

```bash
chmod +x voelgoed-platform-core/bin/check.sh
voelgoed-platform-core/bin/check.sh
```

Expected before implementation: the runner exits successfully after the empty test runner prints `0 dashboard tests passed.`

- [ ] **Step 3: Add plain PHP test bootstrapping.**

Create `tests/bootstrap.php` with deterministic assertion helpers and only the WordPress function stubs required by each unit test. Do not load the production WordPress installation for unit tests. Use the existing member-perks test style as the local convention.

- [ ] **Step 4: Commit the scaffold.**

```bash
git add voelgoed-platform-core
git commit -m "Add unified dashboard plugin scaffold"
```

---

### Task 3: Define normalized dashboard contracts and Afrikaans labels

**Files:**
- Create: `voelgoed-platform-core/includes/Contracts/DashboardServiceInterface.php`
- Create: `voelgoed-platform-core/includes/Contracts/DashboardCard.php`
- Create: `voelgoed-platform-core/includes/Contracts/DashboardAction.php`
- Create: `voelgoed-platform-core/includes/Config/AfrikaansLabels.php`
- Create: `voelgoed-platform-core/tests/contracts/DashboardCardTest.php`

- [ ] **Step 1: Write the failing contract tests.**

The tests must assert that a card always contains these keys and that actions contain a stable key, an Afrikaans label, and a safe URL:

```php
$card = DashboardCard::make(
    'subscriptions',
    AfrikaansLabels::SUBSCRIPTIONS,
    'active',
    '1 aktiewe subskripsie',
    [DashboardAction::link('manage', 'Bestuur subskripsies', '/my-voelgoed/subscriptions/')]
);

assert_same('subscriptions', $card['key']);
assert_same('Subskripsies', $card['label']);
assert_same('active', $card['state']);
assert_true(str_starts_with($card['actions'][0]['url'], '/'));
```

Add an assertion that `Biblioteek` is absent from `AfrikaansLabels::sections()` and that `Lede-area` is present only as a conditional navigation item, not as a provider service.

- [ ] **Step 2: Run the contract test to verify failure.**

```bash
php voelgoed-platform-core/tests/run-dashboard-tests.php
```

Expected: FAIL because the contract classes and label map do not yet exist.

- [ ] **Step 3: Implement the normalized card and action value objects.**

Use this stable card shape:

```php
[
    'key'      => 'subscriptions',
    'label'    => 'Subskripsies',
    'visible'  => true,
    'state'    => 'active',
    'summary'  => '1 aktiewe subskripsie',
    'items'    => [],
    'actions'  => [
        [
            'key'   => 'manage',
            'label' => 'Bestuur subskripsies',
            'url'   => '/my-voelgoed/subscriptions/',
        ],
    ],
]
```

`DashboardCard` must sanitize display values at construction time and reject an empty key, label, or action URL. `DashboardAction` must accept only same-site paths or URLs produced by an approved provider URL helper.

- [ ] **Step 4: Implement the central label map.**

Use these labels exactly in the first release:

```php
public const OVERVIEW = 'Oorsig';
public const MEMBERSHIP = 'Lidmaatskap';
public const SUBSCRIPTIONS = 'Subskripsies';
public const ORDERS = 'Bestellings';
public const TICKETS = 'Kaartjies';
public const LEARNING = 'Kursusse';
public const PROFILE = 'Profiel';
public const PAYMENT_METHODS = 'Betaalmetodes';
public const MEMBER_AREA = 'Lede-area';
public const ATTENTION = 'Aandag nodig';
```

Do not add a generic `Biblioteek` label or route in this release.

- [ ] **Step 5: Run the contract test to verify success.**

```bash
php voelgoed-platform-core/tests/run-dashboard-tests.php
```

Expected: all contract tests pass.

- [ ] **Step 6: Commit the contracts.**

```bash
git add voelgoed-platform-core/includes/Contracts voelgoed-platform-core/includes/Config voelgoed-platform-core/tests/contracts
git commit -m "Define dashboard view contracts and Afrikaans labels"
```

---

### Task 4: Implement PMPro, Woo Subscriptions, and WooCommerce services

**Files:**
- Create: `voelgoed-platform-core/includes/Services/MembershipService.php`
- Create: `voelgoed-platform-core/includes/Services/SubscriptionService.php`
- Create: `voelgoed-platform-core/includes/Services/CommerceService.php`
- Create: `voelgoed-platform-core/tests/services/MembershipServiceTest.php`
- Create: `voelgoed-platform-core/tests/services/SubscriptionServiceTest.php`
- Create: `voelgoed-platform-core/tests/services/CommerceServiceTest.php`

- [ ] **Step 1: Add service interface tests.**

Each test must cover a populated user, an empty user, and a missing dependency. The service must return a valid empty card instead of throwing when the provider is inactive.

- [ ] **Step 2: Implement `MembershipService`.**

Read the current user’s PMPro membership through PMPro’s supported runtime functions. Return level name, status, expiry or renewal date, invoice link, account link, and cancellation/manage links. Use PMPro URL helpers or configured page settings. Never use a PMPro level ID constant and never read membership metadata directly as the runtime authority.

The service must expose the label `Lidmaatskap` and must not expose Woo payment controls.

- [ ] **Step 3: Implement `SubscriptionService`.**

Use Woo Subscriptions’ supported user subscription API, filtering by the authenticated WordPress user ID. Include active, pending, on-hold, failed, paused, and cancelled records when the provider exposes them. Return product name, status, next payment date, payment method label, and native manage, renew, and cancel URLs.

Do not assume that a subscription grants PMPro access. Do not filter out standalone subscriptions.

- [ ] **Step 4: Implement `CommerceService`.**

Use `wc_get_orders()` with the current user’s customer identity and WooCommerce’s endpoint helpers for order details, downloads, addresses, and payment methods. Return only records owned by the current user. Keep downloadable files under the WooCommerce order/download view instead of inventing a library.

- [ ] **Step 5: Run the service tests.**

```bash
php voelgoed-platform-core/tests/run-dashboard-tests.php
```

Expected: membership, subscription, and commerce tests pass, including empty and missing-provider fixtures.

- [ ] **Step 6: Commit the commerce services.**

```bash
git add voelgoed-platform-core/includes/Services/MembershipService.php voelgoed-platform-core/includes/Services/SubscriptionService.php voelgoed-platform-core/includes/Services/CommerceService.php voelgoed-platform-core/tests/services
git commit -m "Add membership subscription and commerce adapters"
```

---

### Task 5: Implement Masteriyo, Tickera, and the member-perks hub service

**Files:**
- Create: `voelgoed-platform-core/includes/Services/LearningService.php`
- Create: `voelgoed-platform-core/includes/Services/TicketService.php`
- Create: `voelgoed-platform-core/includes/Services/MemberAreaService.php`
- Create: `voelgoed-platform-core/tests/services/LearningServiceTest.php`
- Create: `voelgoed-platform-core/tests/services/TicketServiceTest.php`
- Create: `voelgoed-platform-core/tests/services/MemberAreaServiceTest.php`

- [ ] **Step 1: Verify the provider APIs before writing adapters.**

Use vgnova MCP read-only ability discovery and the installed plugin code to identify the supported Masteriyo enrolment/progress API and Tickera ticket lookup/display API. Record the selected calls in `docs/development/unified-dashboard-staging-audit.md`. Do not query plugin tables directly when a supported API exists.

- [ ] **Step 2: Implement `LearningService`.**

Return every Masteriyo course that the user owns or can access, including course title, single-course URL, enrolment state, completion percentage, next lesson when available, and certificate link when available. Use `masteriyo_is_user_enrolled_in_course()` or the verified Masteriyo service/model APIs for access checks. A missing progress field must render as an explicit empty value, not as a false 100% completion state.

- [ ] **Step 3: Implement `TicketService`.**

Return only tickets owned by the authenticated user. Group them into upcoming and past events. Use Tickera’s supported attendee/ticket APIs and secure ticket URLs for QR and downloads. If the current Tickera configuration keys a ticket by purchaser email rather than WordPress user ID, document the account-linking requirement and fail closed instead of showing another user’s ticket.

- [ ] **Step 4: Implement `MemberAreaService`.**

Resolve the conditional link through the existing member-perks public boundary:

```php
$resolution = $hub_access->resolve(\Voelgoed\MemberPerks\Display\HubAccess::CONTEXT_MAIN);
$visible = (bool) ($resolution['can_view_hub'] ?? false);
$page_id = (int) ($resolution['hub_page_id'] ?? 0);
$url = $visible && $page_id > 0 ? get_permalink($page_id) : '';
```

If the class or dependency is unavailable, return `visible = false`. Do not read the member-perks ACF options directly and do not hardcode page IDs or PMPro levels.

- [ ] **Step 5: Run the service tests.**

```bash
php voelgoed-platform-core/tests/run-dashboard-tests.php
```

Expected: learning, ticket, and conditional hub tests pass for active, inactive, missing-dependency, and dual-programme fixtures.

- [ ] **Step 6: Commit the learning, ticket, and hub services.**

```bash
git add voelgoed-platform-core/includes/Services/LearningService.php voelgoed-platform-core/includes/Services/TicketService.php voelgoed-platform-core/includes/Services/MemberAreaService.php voelgoed-platform-core/tests/services
git commit -m "Add learning ticket and member area adapters"
```

---

### Task 6: Build the dashboard registry, navigation, and authentication guard

**Files:**
- Create: `voelgoed-platform-core/includes/Dashboard/DashboardContext.php`
- Create: `voelgoed-platform-core/includes/Dashboard/DashboardRegistry.php`
- Create: `voelgoed-platform-core/includes/Dashboard/Navigation.php`
- Create: `voelgoed-platform-core/includes/Dashboard/AccessGuard.php`
- Create: `voelgoed-platform-core/tests/dashboard/NavigationTest.php`
- Create: `voelgoed-platform-core/tests/dashboard/AccessGuardTest.php`

- [ ] **Step 1: Define the view keys.**

Use these stable keys:

```php
overview
membership
subscriptions
orders
tickets
learning
profile
payment-methods
```

Do not add `library` to the registry.

- [ ] **Step 2: Implement the registry.**

`DashboardRegistry` must register the six provider services, collect their summary cards, and expose a view by key. It must preserve provider failure isolation: a missing Tickera installation cannot remove `Bestellings` or `Profiel`.

- [ ] **Step 3: Implement the navigation model.**

Return this order for the tab component:

```text
Oorsig → Lidmaatskap → Subskripsies → Bestellings → Kaartjies → Kursusse → Profiel
```

Append `Lede-area` only when `MemberAreaService` returns `visible = true`. Keep `Betaalmetodes` as a profile action unless the responsive usability test proves it needs its own tab.

Every navigation item must include `label`, `url`, `active`, `visible`, and `aria_current`. Labels come only from `AfrikaansLabels`.

- [ ] **Step 4: Implement the authentication guard.**

For every dashboard page:

```php
if (! is_user_logged_in()) {
    $request_path = wp_unslash($_SERVER['REQUEST_URI'] ?? '/my-voelgoed/');
    $return_url = wp_validate_redirect($request_path, home_url('/my-voelgoed/'));
    wp_safe_redirect(wp_login_url($return_url));
    exit;
}
```

Use the WordPress login helper and validate the return path. Never accept an arbitrary external `redirect_to` value.

- [ ] **Step 5: Run navigation and access tests.**

```bash
php voelgoed-platform-core/tests/run-dashboard-tests.php
```

Expected: logged-out requests redirect safely, logged-in requests continue, the tab order is stable, and `Lede-area` is absent for ordinary users.

- [ ] **Step 6: Commit the dashboard registry.**

```bash
git add voelgoed-platform-core/includes/Dashboard voelgoed-platform-core/tests/dashboard
git commit -m "Add dashboard navigation and access guard"
```

---

### Task 7: Add Bricks dynamic data and user-scoped cache protection

**Files:**
- Create: `voelgoed-platform-core/includes/Bricks/DashboardDynamicData.php`
- Create: `voelgoed-platform-core/includes/Bricks/DashboardQueryProvider.php`
- Create: `voelgoed-platform-core/includes/Performance/CachePolicy.php`
- Create: `voelgoed-platform-core/tests/performance/CachePolicyTest.php`

- [ ] **Step 1: Register Bricks dynamic tags using the existing site pattern.**

Follow the established `bricks/dynamic_tags_list`, `bricks/dynamic_data/render_content`, and `bricks/dynamic_data/render_tag` hooks used by `voelgoed-member-perks`. Register only dashboard fields needed by Bricks cards:

```text
vg_dashboard_user_name
vg_dashboard_membership_summary
vg_dashboard_subscription_summary
vg_dashboard_order_summary
vg_dashboard_ticket_summary
vg_dashboard_learning_summary
vg_dashboard_member_area_url
vg_dashboard_member_area_visible
```

All field labels in the Bricks editor must be Afrikaans or clearly prefixed with `Voelgoed Dashboard`.

- [ ] **Step 2: Register the dashboard query provider.**

Use a dedicated query marker such as `vg_dashboard_card` and attach the current user ID plus view key to the query cache key. A query loop with no current user must return an empty query, not public content.

- [ ] **Step 3: Implement cache policy.**

On dashboard requests, set `DONOTCACHEPAGE` when supported, send no-cache headers, and add the equivalent exclusion hooks for the installed page-cache layer after confirming its live hook names through read-only inspection. Do not use a shared persistent cache for normalized user cards. Any Bricks query cache key must include the user ID and dashboard view key.

- [ ] **Step 4: Add cache tests.**

Assert that two users produce different cache keys, logged-out queries produce empty results, and a missing provider produces an empty card instead of cached data from another provider.

- [ ] **Step 5: Run tests and syntax checks.**

```bash
php voelgoed-platform-core/tests/run-dashboard-tests.php
voelgoed-platform-core/bin/check.sh
```

Expected: all unit tests and PHP syntax checks pass.

- [ ] **Step 6: Commit Bricks and cache integration.**

```bash
git add voelgoed-platform-core/includes/Bricks voelgoed-platform-core/includes/Performance voelgoed-platform-core/tests/performance
git commit -m "Add Bricks dashboard data and cache isolation"
```

---

### Task 8: Create the protected pages and Bricks shell through vgnova MCP

**Files:**
- Modify through vgnova MCP: WordPress pages and Bricks templates only
- Verify through vgnova MCP: page hierarchy, template conditions, and rendered content

- [ ] **Step 1: Inspect existing Bricks global classes and templates.**

Use `novamira/bricks-list-templates`, `novamira/bricks-list-components`, and `novamira/bricks-get-content` through vgnova MCP. Reuse existing header, footer, card, spacing, typography, and ACSS variables. Do not create a parallel CSS system.

- [ ] **Step 2: Create the parent page.**

Create a protected WordPress page titled `My Voelgoed` with slug `my-voelgoed` through the live ability schema. Keep its content empty until the Bricks shell is ready. Verify the created slug and page ID with a read-only page query.

- [ ] **Step 3: Create the child pages.**

Create child pages under `My Voelgoed` with these slugs and Afrikaans titles:

```text
membership       Lidmaatskap
subscriptions    Subskripsies
orders           Bestellings
tickets          Kaartjies
learning         Kursusse
profile          Profiel
```

Do not create `library` or `biblioteek` pages in this release.

- [ ] **Step 4: Create the Bricks shell template.**

Create a Bricks template named `Unified Customer Dashboard Shell` with conditions for the parent and child dashboard pages. Build:

```text
Dashboard wrapper
├── Page title and user greeting
├── Dashboard Tabs template part
├── Current view content area
└── Optional alert region
```

Use `aria-current="page"`, visible keyboard focus, and horizontal scrolling on small screens. Use data attributes for active state and analytics hooks.

- [ ] **Step 5: Add the overview cards.**

Create cards for `Lidmaatskap`, `Subskripsies`, `Bestellings`, `Kaartjies`, `Kursusse`, and `Aandag nodig`. Hide a card only when its service returns no applicable content. The overview contains summary actions, not duplicate provider dashboards.

- [ ] **Step 6: Add native provider sections.**

Use the native PMPro, WooCommerce, Tickera, and Masteriyo actions or shortcodes inside the focused child views. The plugin’s normalized data supplies summaries and conditions; provider workflows handle mutations.

- [ ] **Step 7: Verify the Bricks page tree.**

Read back page titles, slugs, parent IDs, template conditions, dynamic tags, and query settings through vgnova MCP. Expected: every visible label is Afrikaans and no `Biblioteek` tab/page exists.

- [ ] **Step 8: Record the remote page IDs without exporting Bricks JSON.**

Append the verified parent and child page IDs, template ID, and condition summary to `docs/development/unified-dashboard-staging-audit.md`. The WordPress page and Bricks mutations remain in staging and are verified remotely; do not fabricate local JSON exports.

---

### Task 9: Wire the conditional `Lede-area` tab and provider links

**Files:**
- Modify through vgnova MCP: Dashboard Tabs template part and focused Bricks views
- Modify: `voelgoed-platform-core/includes/Dashboard/Navigation.php`
- Test: `voelgoed-platform-core/tests/dashboard/NavigationTest.php`

- [ ] **Step 1: Add the conditional navigation item.**

Render `Lede-area` only when `MemberAreaService` reports `visible = true`. Build the URL from the configured page returned by `HubAccess`; never use `990`, `991`, or `992` as constants.

- [ ] **Step 2: Verify ordinary users do not see the tab.**

Run the navigation unit test with an inactive profile fixture and verify that the tab is absent from both the navigation array and rendered HTML.

- [ ] **Step 3: Verify active programme users see one direct tab.**

Run fixtures for Vriendinneklub, Voelgoednetwerk, and a dual-programme user. Expected: one `Lede-area` tab points to the configured main hub. The dashboard must not show a programme chooser unless the configured hub itself provides one.

- [ ] **Step 4: Add clear links between experiences.**

In the member area card, use Afrikaans copy that distinguishes `Lidmaatskap` management from the member-perks hub. Keep `/vriendin/` as a separate content/discovery destination and link to it only from an approved member-content CTA, not from a fabricated `Biblioteek` route.

- [ ] **Step 5: Commit the dynamic tab.**

```bash
git add voelgoed-platform-core/includes/Dashboard/Navigation.php voelgoed-platform-core/tests/dashboard/NavigationTest.php
git commit -m "Add conditional Afrikaans member area tab"
```

---

### Task 10: Add analytics, accessibility, and failure-state copy

**Files:**
- Modify through vgnova MCP: Bricks Dashboard Tabs and card components
- Modify: `voelgoed-platform-core/includes/Config/AfrikaansLabels.php`
- Modify: `voelgoed-platform-core/includes/Contracts/DashboardCard.php`

- [ ] **Step 1: Add the approved empty and failure labels.**

Use explicit Afrikaans states such as:

```text
Geen aktiewe lidmaatskap nie.
Geen subskripsies om te wys nie.
Geen bestellings om te wys nie.
Geen komende kaartjies nie.
Geen kursusse met toegang nie.
Hierdie inligting is tans nie beskikbaar nie.
```

Do not expose PHP warnings, provider class names, or raw API errors to users.

- [ ] **Step 2: Add analytics data attributes.**

Add stable event keys to tab and card actions without putting English UI strings in the interface:

```text
data-vg-dashboard-event="open_membership"
data-vg-dashboard-event="open_subscriptions"
data-vg-dashboard-event="open_orders"
data-vg-dashboard-event="open_tickets"
data-vg-dashboard-event="open_learning"
data-vg-dashboard-event="open_member_area"
```

Push these stable keys to the existing GTM data layer only after verifying the site’s current tracking hook through read-only inspection.

- [ ] **Step 3: Run an accessibility pass.**

Verify keyboard navigation, active-tab announcement, focus visibility, sufficient contrast, mobile horizontal scroll, and a meaningful heading hierarchy in the Bricks preview.

- [ ] **Step 4: Commit copy and accessibility adjustments.**

```bash
git add voelgoed-platform-core/includes/Config/AfrikaansLabels.php voelgoed-platform-core/includes/Contracts/DashboardCard.php
git commit -m "Add Afrikaans dashboard states and accessibility hooks"
```

---

### Task 11: Run staging verification and publish the handoff

**Files:**
- Create: `docs/development/unified-dashboard-qa.md`
- Read-only verification through vgnova MCP and Novamira CLI

- [ ] **Step 1: Run the complete local checks.**

```bash
voelgoed-platform-core/bin/check.sh
git diff --check
git status --short --branch
```

Expected: all PHP syntax checks and tests pass, `git diff --check` is clean, and only intended files are modified.

- [ ] **Step 2: Verify the page tree remotely.**

Read the dashboard parent and child pages through `novamira/run-wp-cli` via vgnova MCP. Confirm the parent/child hierarchy, slugs, and titles exactly match the approved route table.

- [ ] **Step 3: Verify each integration with approved staging users.**

Use existing approved staging test users. Do not create or alter accounts without explicit approval. Verify:

```text
logged out       → safe login redirect with return path
ordinary user    → no Lede-area tab
PMPro member     → Lidmaatskap summary and native manage link
standalone Woo   → Subskripsies summary without PMPro assumption
course owner     → every accessible Masteriyo course appears
ticket holder    → only owned Tickera tickets appear
dual programme   → one Lede-area tab to the configured main hub
```

- [ ] **Step 4: Verify cache isolation.**

Inspect response headers and Bricks query cache keys for two different staging users. Confirm no user-specific HTML or query result is shared.

- [ ] **Step 5: Record QA results.**

Write `docs/development/unified-dashboard-qa.md` with date, staging URL, page IDs, tested user scenarios, provider availability, cache checks, and any non-blocking plugin limitations. Include screenshots only if the project’s existing QA workflow requires them.

- [ ] **Step 6: Commit the QA handoff.**

```bash
git add docs/development/unified-dashboard-qa.md
git commit -m "Document unified dashboard staging QA"
```

---

## Self-review checklist

- [ ] Every design decision in `docs/superpowers/specs/2026-09-19-voelgoed-unified-dashboard-design.md` maps to a task above.
- [ ] `Subskripsies` is used consistently as the visible billing label.
- [ ] `Biblioteek` is explicitly deferred and has no route, tab, service, or acceptance criterion in this release.
- [ ] PMPro remains the access authority, while Woo Subscriptions remains the billing authority.
- [ ] Masteriyo displays all owned or accessible courses rather than only perk-granted courses.
- [ ] Tickera and WooCommerce data are scoped to the current WordPress user.
- [ ] The conditional member tab delegates to `HubAccess` and does not hardcode staging IDs.
- [ ] `/vriendin/` remains separate from the dashboard and is not disguised as a library.
- [ ] All visible labels, empty states, status labels, and actions are Afrikaans.
- [ ] Every task contains a concrete file path, command, expected result, and verification step where a local change is involved.
- [ ] No task mutates staging without a preceding read-only inspection and a clear verification step.
