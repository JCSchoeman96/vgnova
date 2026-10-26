# VS1 Current Reality Audit

| Audit item | Value |
|---|---|
| Status | CURRENT READ-ONLY AUDIT |
| Version | 1.0.0 |
| Audit date | 2026-10-26 |
| Repository baseline | `origin/main` = `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` |
| Audit branch | `feat/vs1-preflight-authority-gates` |
| Audit HEAD before this document | `c6e94ae2d50689308cb188e25f6ce63d371bc6af` |

## Scope and evidence standard

This audit follows the approved VS1 Preflight & Authority Gates plan. It is read-only with respect to WordPress business/runtime state. Runtime observations below came from the selected VG Nova Novamira profile at `http://localhost:10039/`; no local WP-CLI database access was available. Source and runtime are reported separately. Dated execution reports are historical evidence and are not promoted to current runtime facts without a fresh check.

No WordPress posts, People, Courses, Products, Events, relationships, orders, subscriptions, memberships, enrolments, settings, ACF definitions, Bricks resources, snippets, or plugin files were changed. Platform Core was not deployed.

## Git baseline

| Check | Result | Authority |
|---|---|---|
| Fresh upstream baseline | `origin/main` = `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` | `git fetch origin`, `git rev-parse origin/main`, `git log -1 --oneline origin/main` |
| Merge state | Merge commit for PR #58, “migrate first owner-approved canonical Person” | Git history; PR #58 is merged |
| Isolated branch | `feat/vs1-preflight-authority-gates`, based on the verified upstream SHA | Git worktree metadata |
| Worktree at audit start | Clean; `HEAD` was `c6e94ae2d50689308cb188e25f6ce63d371bc6af` after the two approved documentation commits | `git status --short`, `git rev-parse HEAD` |
| Original checkout changes | Unrelated `AGENTS.md` edit was preserved in the source checkout and not copied into this worktree | Source-checkout status before worktree creation |

The historical status sentence in `POST_TASK_10A_CURRENT_STATE_v1.0.0.md` that says PR #58 was open/unmerged was not edited. Git/GitHub merge state is the current authority; that file remains Task 10A execution evidence.

## Platform Core source and deployment

The source authority is `site-code/voelgoed-platform-core/`. The current assembly contract is `docs/architecture/PLATFORM_CORE_ASSEMBLY_CONTRACT_v1.3.0.md`, which is present in the verified `origin/main` history. The assembly build and verifier were run against this audit HEAD:

```text
python3 site-code/bin/build-platform-core.py --ref <audit-HEAD> --output dist
python3 site-code/bin/check-platform-core-build.py --ref <audit-HEAD> --verify-output dist
```

Both completed successfully. The deterministic source build contains 55 files; `tree_sha256=5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a`; `archive_sha256=22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9`; `manifest_sha256=705a7a0d15ecbfa782789fd17f413f2bc06cbddc6b66f1674a0e8464e0695c18`. The verifier used CPython 3.14.4. The ignored local `dist/` output was not committed or deployed.

Fresh read-only inventory and hashes of the active runtime plugin found 54 files. Relative to the current source build:

- Runtime is missing `includes/Runtime/RuntimeMutationSession.php`.
- Five files differ: `includes/Event/EventRoleSeeder.php`, `includes/Product/ProductRoleSeeder.php`, `includes/Taxonomy/ContentTopicMigration.php`, `includes/Taxonomy/ContentTopicTaxonomy.php`, and `voelgoed-platform-core.php`.
- No extra runtime files were found.
- The active runtime entrypoint, `includes/Plugin.php`, and `includes/Product/PersonProductsBricks.php` match their current source hashes.

This is a fresh source/runtime conflict, not a conclusion copied from the 2026-10-25 Task 9 report. That report recorded a 54-file match to an earlier source build and is historical. The present finding is:

```text
SOURCE_RUNTIME_PARITY=DRIFT
SOURCE_RUNTIME_CONFLICT=YES
```

No deployment or runtime correction is part of this preflight.

## Runtime identity

The selected VG Nova Local profile was reverified read-only. Its identity is:

| Item | Current runtime observation |
|---|---|
| Home/site URL | `http://localhost:10039/` |
| WordPress root | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/` |
| Database | `local` on `localhost` |
| WordPress / PHP | WordPress `7.1.3`; PHP `8.5.12` |
| Active theme | Bricks Child Theme `1.1`; parent Bricks `2.4.3` |
| ACF Pro | `6.8.1` |
| WooCommerce / Subscriptions | `11.2.1` / `8.8.1` |
| PMPro / PMPro Woo integration | `3.8.6` / `1.10.1` |
| Masteriyo | `3.4.3` |
| Tickera / Woo bridge | `3.5.7.4` / `1.7.4` |
| FluentCRM / Fluent Snippets / FluentSMTP | `3.2.0` / `10.56` / `2.4.1` |
| Voelgoed Member Perks | `0.18.0` |
| Voelgoed Platform Core | `0.1.0`; 54-file runtime inventory described above |

The local shell's WP-CLI database connection failed. Runtime identity and content observations use Novamira's selected VG Nova runtime; no database credentials were read or exposed.

## Current subsystem state

| Required status | Ruling | Evidence and limitation |
|---|---|---|
| `PRODUCT_CONTRIBUTION_CURRENT_STATE` | `PARTIAL` | Current source contains the canonical Product Contribution repository/service and `PersonProductsBricks` adapter. Runtime Product Contribution count is 0; the runtime adapter hash matches source, but no published canonical Person template consumer is proven. `PRODUCT_CONTRIBUTION_IMPLEMENTATION.md` explicitly says adapter source does not prove a production template uses it. The 2026-10-25 Task 9 report is historical. |
| `EVENT_PARTICIPATION_CURRENT_STATE` | `PARTIAL` | Canonical ACF schema and Platform Core repository/service exist, and runtime Participation count is 0. `EVENT_PARTICIPATION_IMPLEMENTATION.md` documents that legacy consumer migration had not started at its historical checkpoint. Current runtime has no Person Event edge. |
| `MY_VOELGOED_CURRENT_STATE` | `COMPLETE` for the approved dashboard scope | Source has the route resolver, access guard, Masteriyo-backed `LearningService`, and cache policy. Runtime contains the overview and child pages, including `/my-voelgoed/learning/`; prior staging QA documents the access, cache, Bricks projection and route checks. The `Lede-area` Member Perks surface and production cache-stack checks remain explicitly deferred by the dashboard QA record. |

The Product Contribution and Event Participation model implementations are source-proven, but no current relationship rows exist. Their historical test/QA records do not establish representative editorial data.

## Runtime content and relationship counts

Counts below were queried from the current Local runtime on 2026-10-26. They describe Local staging and do not establish owner authorization or production provenance.

| Entity / relationship | Runtime result | Provenance or interpretation |
|---|---:|---|
| Published WordPress Posts | 1 | Post 375, “Integer maximus sapien in bibendum.”; no `content_topic` or `content_category`. Provenance is unknown; excluded as real editorial data. |
| Canonical `person` | 1 published | Lynette Beer, ID `1705`, slug `lynette-beer`; canonical identity was owner-authorized in merged PR #58. She has exactly `Mentor / mentor` and `Kenner / kenner`. |
| Masteriyo Courses | 5 published, 1 draft | Draft ID `438` is “Untitled Course.” Five published course records have no confirmed editorial authorization. |
| Woo Products | 9 published, 7 draft, 1 private, 3 trash | Twenty records across these statuses; FCM targets and event-test products are not representative-data approvals. |
| Tickera Events | 17 published | No canonical Event Participation records. Existing events are not treated as VS1 representative data without provenance review. |
| Product Contributions | 0 in all statuses | No canonical Person → Product edge. |
| Event Participations | 0 in all statuses | No canonical Person → Event edge. |
| `content_topic` roots | 7 | `nuwe-jy`, `geloof`, `self`, `werk-geld`, `ouerskap`, `huwelik`, `mensstories`. |
| Published Article ↔ `content_topic` relationships | 0 | No Article provides a canonical Topic → Article path. |

PR #58 made Lynette a valid canonical Person identity candidate. It did not add a canonical Topic, Event Participation, Product Contribution, or Course relationship for her. Her lack of excerpt/content, featured image, professional details, and related content is intentional. No relationship is inferred from identity, FCM targets, legacy profile edges, or a Woo Product.

## Current blockers carried into later gates

- Source/runtime Platform Core parity is `DRIFT`; affected behavior cannot pass solely on assumed source deployment.
- The only published Article has no canonical Topic assignment and unknown provenance.
- No canonical Person → Course authority is established by the current model or runtime data.
- No canonical Person → Event or Person → Product relationship rows exist.
- Product Contribution implementation source exists, but production Person template consumption is unproven.
- Lynette is owner-authorized as a Person identity, not as representative Journey data.

## Required summary

```text
REPO_BASELINE=50ee4bee1ae1405522df7b67634b8935d4e9d9ed
RUNTIME_IDENTITY=VG Nova Local, http://localhost:10039/, local@localhost
SOURCE_RUNTIME_PARITY=DRIFT
PRODUCT_CONTRIBUTION_CURRENT_STATE=PARTIAL
EVENT_PARTICIPATION_CURRENT_STATE=PARTIAL
MY_VOELGOED_CURRENT_STATE=COMPLETE
CANONICAL_PERSON_COUNT=1
COURSE_COUNT=6 (5 published, 1 draft)
PRODUCT_COUNT=20 (9 published, 7 draft, 1 private, 3 trash)
EVENT_COUNT=17 published
BLOCKERS=Platform Core source/runtime drift; zero Article-topic, Event Participation, and Product Contribution edges; Person-to-Course authority unproven; no suitable real Article/Person/Course Journey set
WORDPRESS_BUSINESS_MUTATIONS=0
BRICKS_MUTATIONS=0
ACF_MUTATIONS=0
ORDERS_CREATED=0
MEMBERSHIPS_CREATED=0
ENROLMENTS_CREATED=0
```
