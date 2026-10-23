# Product Contribution preflight

**Checked:** 2026-10-23
**Scope:** Task 1 of `docs/superpowers/plans/2026-10-23-product-contribution-reverse-product.md`
**Execution baseline:** `5ea881fc98bfe04c37ba9465ed6d5ae75aec3602`
**Implementation branch:** `feat/product-contribution-reverse-product`

This record contains fresh, read-only runtime evidence for the `product_people_override` gate. It does not authorize Product Contribution implementation until an independent reviewer accepts this exact preflight commit.

## Repository identity

Task 1 began with a clean implementation worktree. `HEAD`, `origin/main`, and the merge base are all `5ea881fc98bfe04c37ba9465ed6d5ae75aec3602`. The baseline commit has parents `b0206640a676ac4b0233c274581abdc4ac8ea477` and `7371e81aa3b47559015ecf02d05223cf45c5065f`, matching the merged architecture/spec PR and approved plan PR.

## Runtime identity

| Item | Observed value |
|---|---|
| WordPress root | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/` |
| Home/site URL | `http://localhost:10039/` |
| Effective port | `10039` |
| Database name | `local` |
| Database host | `localhost` |
| Local site data directory | `/home/jcschoeman96/Local Sites/voelgoed-novamira` |
| Local site UUID | Not exposed through the connected WordPress runtime or Novamira connection |
| WordPress | `7.1.3` |
| ACF Pro | `6.8.1`, loaded; `get_field()` is available |
| Platform Core | `0.1.0`, active at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core` |
| Platform Core entrypoint SHA-256 | `2a0645e9d0812cf13e27b2b4f3f1ff25c07650c3bd93deba672f21d96f6a71b6` |
| Platform Core tree SHA-256 | `aa2011228417894a84c15c28aa6c4b10fc7f441f94b8ced30a1efe95735b6bcc` |
| Platform Core files fingerprinted | `47` |

The tree fingerprint sorts every runtime file by path relative to the Platform Core directory, joins each path and file SHA-256 with a NUL byte and newline, then hashes that complete string with SHA-256. Runtime identity matches the planned Local site, port, and database.

## Override values and current mode configuration

The read-only inventory covered all `product` posts except auto-drafts, in every other status. It found 20 Products:

| Status | Product IDs |
|---|---|
| `publish` | 525, 527, 529, 531, 767, 842, 844, 845, 846 |
| `draft` | 905, 908, 911, 914, 917, 920, 923 |
| `private` | 1186 |
| `trash` | 642, 765, 768 |

`product_people_override` inventory:

| State | Count |
|---|---:|
| Missing on Product | 20 |
| Empty string/value | 0 |
| Serialized empty array | 0 |
| Non-empty | 0 |
| Duplicate override meta rows | 0 |
| Override rows attached to non-Products | 0 |

The companion `_product_people_override` reference metadata also has no rows. The normalized override configuration digest is SHA-256 `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3`, the serialized empty collection digest.

`product_people_mode` inventory:

| Mode state | Product count |
|---|---:|
| `event` | 0 |
| `manual` | 0 |
| Blank or absent | 20 |
| Invalid non-empty | 0 |

No Product has a manual mode, so there is no live manual Product with an empty override to exercise. The existing resolver returns an empty list for absent, empty, and invalid modes; no mode or override values are being reinterpreted here.

## Caller and consumer search

The exact key `product_people_override` appears in tracked Platform Core documentation, `ProductPeopleFields`, `ProductPeopleResolver`, and the associated tests. The production class references are the known source definitions and resolver being replaced by the approved plan.

Fresh runtime search results:

| Surface | Result |
|---|---|
| Active Platform Core runtime code | Two files only: `includes/Product/ProductPeopleFields.php` and `includes/Product/ProductPeopleResolver.php` |
| Active child theme | No exact-key match |
| FluentSnippets storage | No exact-key match |
| WordPress post content/excerpts | No exact-key match |
| WordPress postmeta, options, termmeta, usermeta, and commentmeta | No exact-key match |
| FluentSnippets custom database tables | No such tables found |
| Bricks metadata/options | No exact-key match in the searched postmeta/options; protected Bricks component/template fingerprints are recorded below |

The runtime ACF group `Product People` (`group_vg_product_people`) is PHP-local and contains the two expected fields. The canonical resolver reads the override only in manual mode. `ProductPeopleBricks` constructs that resolver without an old repository dependency. No required external/runtime consumer of `product_people_override` was found. The active legacy snippet files still have other documented legacy Product/Medewerker duties; this gate does not authorize changing those snippets or retiring those legacy fields.

## Protected runtime fingerprints

For each post group, the post digest covers all columns of the requested `wp_posts` rows sorted by ID. The postmeta digest covers all rows sorted by post ID, meta key, and meta ID. The term relationship digest covers object ID, term-taxonomy ID, order, taxonomy, term ID, and slug. Digests were computed in WordPress without returning stored content.

| Protected group | Posts found | Postmeta rows | Term relationships | Posts SHA-256 | Postmeta SHA-256 | Term relationships SHA-256 |
|---|---:|---:|---:|---|---|---|
| Profiles 753–757 | 5 | 34 | 10 | `6cb107bcb3a70ed61526caff114e5f7771d838905e1ebf4de2bf207e894ba420` | `3a5bfb948796c6ba11ad98c50d4de8d1eabcdd2db73d6fc94abfe15203f24e5c` | `37ef040584ca77a139e611763910d7700ec9e9b05a50d63241f871817c489ae3` |
| Event 763 | 1 | 15 | 0 | `be163f4a063ede077b98c9c1b24cc3b5cc519fdbf559e40a47ff270013e1ea6b` | `f7ca21d464725e1d20573954880fcc13084207274b1618eb011e420e2e8a9a99` | `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` |
| Products 765, 767, 768 | 3 | 20 | 0 | `97a956b5881cd58aeef2c9765e4080692a968bc3b25e1266acfd53cc5740630a` | `7e95f2f96d0d4a2357a199acab176a01b91209240acb8fb16f1b4ab45c6e8934` | `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` |
| Bricks templates 773, 1127 | 2 | 11 | 0 | `7f2c9eb0c0918d5ff4cbdae818840c7b0b8f2bb8e8bbe33ce421e6399911dc81` | `6b3cd25ddde6b06f1748900a65612ae530f2c10163834a6ea2844df52f110e03` | `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` |

Additional protected configuration fingerprints:

| Surface | Runtime evidence |
|---|---|
| Product People ACF group | `group_vg_product_people`, PHP-local, 2 fields; schema SHA-256 `eb2ca243eb89871febe0587b57c768e3291cb489e51ad0dc18f85d416bbceae4` |
| Event Participation ACF group | `group_vg_event_participation_details`, database/JSON group ID `1263`, 4 fields; schema SHA-256 `d89dec703bb5702fed069dfa9a4c6cca4d8dd3d837b45fa2504cbc762c4f5680` |
| Product People configuration | 20 Product records inspected, 0 relevant meta rows; digest `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` |
| Event Participation state | 0 posts, 0 postmeta rows, 0 term relationships; each empty collection digest is `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` |
| Bricks component `6b463f` | `wp_options.bricks_components`, option ID `13809`; row SHA-256 `55beb9ae2508c235362bc850d6f11954f0634d1bace3d8a59e3869c750b7d004` |
| FluentSnippets file 9 | `wp-content/fluent-snippet-storage/9-acf-product-copy.php`, 1,263 bytes; SHA-256 `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70`; runtime callback registered on `acf/save_post` at priority 20 |
| FluentSnippets file 10 | `wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php`, 5,998 bytes; SHA-256 `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`; runtime callback registered on `bricks/posts/query_vars` at priority 10 |
| Person records | 0 |
| Product Contribution records | 0 |

FluentSnippets 9 and 10 are file-managed, so no WordPress posts with IDs 9 or 10 were present. Their runtime files and registered callbacks were fingerprinted directly. The protected template post and metadata fingerprints include templates 773 and 1127.

## Method and gate

Runtime inspection used the project-specific VGNova Novamira connection. WordPress APIs, read-only SQL queries, and runtime file reads were used. The runtime code scan covered PHP, JSON, JavaScript, CSS, HTML, and text files up to 2 MB in the active Platform Core, active child theme, and FluentSnippets storage. The database key search covered post content and excerpts, postmeta, options, termmeta, usermeta, and commentmeta. No WordPress data, files, or options were changed. No automated tests were run because Task 1 makes no code change. The Local site UUID was not available from the connected runtime; the root directory, home URL, port, database name, and host identify the expected target.

No non-empty override value and no required external/runtime consumer was found. The key exists only in the planned source field/resolver and tests/documentation. Runtime Product mode values are absent on all 20 Products. This passes the binding Task 1 override gate.

```text
PRODUCT_CONTRIBUTION_OVERRIDE_GATE=PASS
```

This result opens the implementation gate only after an independent reviewer audits this exact commit. Do not begin Task 2 before that review.
