# Person consumer decommission readiness audit

Status: in progress. This is a read-only runtime audit. No migration, consumer cutover, or cleanup is authorized by this audit.

## Execution baseline and ruling

| Item | Value |
|---|---|
| Repository | `JCSchoeman96/vgnova` |
| Authorized execution baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64` |
| Verification | `git fetch origin refs/heads/main:refs/remotes/origin/main`, then `git rev-parse origin/main` returned the authorized SHA |
| Plan-authoring baseline | `c61b02954950e4e97aaa47255af41514b5989828` |
| Approved plan head | `b74793691af8f3f3d3f3b2beeb79bb830d3df082` |
| PR #32 merge parents | `c61b02954950e4e97aaa47255af41514b5989828` and `b74793691af8f3f3d3f3b2beeb79bb830d3df082` |
| Audit branch | `docs/task-7c-person-consumer-readiness-audit` |
| Worktree baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64` |

Ruling: The plan's c61b029... SHA is the planning baseline before PR #32 merged. The authorized Task 7C execution baseline is abde5c4689ed2b15f3b97bc6b1b3e04d65618f64. Substitute abde5c... wherever the plan uses c61b029... as the execution worktree base, origin/main gate, or final Git diff base. No architectural scope changes.

## Scope and fixed provenance rulings

The audit records current consumers and dependencies for the `medewerker` implementation, compares repository source with the authorized 10039 runtime, and recommends one next unit. It does not create canonical Person data, migrate relationships, or change runtime configuration.

| Protected record | Ruling |
|---|---|
| Profile 753 | `DEFER` |
| Profile 754 | `DEFER` |
| Profile 755 | `DEFER` |
| Profile 756 | `DEFER` |
| Profile 757 | `DEFER` |
| Event 763 → profile 753 | `EXCLUDE` |
| Event 763 → profile 757 | `EXCLUDE` |
| Product 767 → profile 753 | `EXCLUDE` |
| Product 768 → profile 753 | `EXCLUDE` |
| Product 768 → profile 757 | `EXCLUDE` |

These rulings remain fixed during Task 7C. New evidence may be reported, but a ruling change needs separate review. Architecture dependency does not grant migration authority.

## Evidence and zero-write method

External sanitized evidence is stored outside the repository at `/tmp/task-7c-audit-abde5c-20261022/`. The directory contains text or data files only. `baseline.txt` records the execution ruling. `source-hashes.txt` records the repository source baseline, tracked paths, and pre-runtime SHA-256 values. Runtime identity, pre-audit fingerprints, runtime evidence, post-audit fingerprints, and access limits will be recorded in separate files there. No credentials or unnecessary personal field values will be stored.

Runtime inspection will use read-only Novamira operations where available. Any database evidence will use SELECT-only reads and value hashes for options. Runtime files will be read or hashed without writes. No editor screen or control that can save or auto-sync will be used. The same method will produce pre- and post-audit fingerprints. Missing or inaccessible evidence will be reported as an access limitation, not as proof of zero callers.

The only runtime in scope is `http://localhost:10039/`, expected at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`. No substantive runtime query may run until `ABSPATH`, `home_url`, actual request/runtime port, and database name plus server identity positively identify that installation.

## Repository source evidence recorded before runtime access

The repository source baseline is `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64`. Source paths and SHA-256 values for Event Participation and Product People implementations and tests are recorded in `/tmp/task-7c-audit-abde5c-20261022/source-hashes.txt`. Runtime deployed hashes will be collected separately after the identity gate passes. Source hashes do not establish runtime parity.

## Runtime identity and pre-audit fingerprint

Identity-only preflight passed at 2026-10-22 10:55 UTC. The selected Novamira profile is `localhost`, whose origin is `http://localhost:10039/`.

| Required value | Evidence |
|---|---|
| `ABSPATH` | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`. `wp-config.php` defines it as `__DIR__ . '/'`; `realpath` resolves to the expected WordPress root. |
| `home_url` | `http://localhost:10039`. Novamira `core/get-site-info` returned this URL and `wpurl`; a read-only WP-CLI option read using the site-specific PHP config returned the same `home` value. Site title: `Voelgoed`. |
| Actual HTTP port | A GET to `http://localhost:10039/` returned HTTP 200 with `remote_port=10039`. No redirect was followed. |
| Database name | `local`, confirmed by a SELECT-only query against the connected database. The expected root's `wp-config.php` sets `DB_NAME=local` and `DB_HOST=localhost`. |
| Database server identity | A SELECT-only query returned hostname `jc-kubuntu`, port `10038`, server UUID `028fe7c9-3720-11f1-8cb0-a8a159280deb`, and MySQL `8.0.35`. The site-specific Local PHP and MySQL configuration use the same socket `/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock`; runtime Novamira reports MySQL `8.0.35`. |
| Repository baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64`, recorded separately from runtime identity. |

The identity evidence and capture time are in `/tmp/task-7c-audit-abde5c-20261022/preflight-identity.txt`. No credentials were recorded. The system PATH has no MySQL client, so the `wp db query` wrapper could not run. The Local installation's MySQL 8.0.35 client connected through the expected site's socket and ran the read-only identity query. It is available for the pre- and post-audit SELECT-only fingerprints.

The protected pre-audit fingerprint was captured at 10:56 UTC, before caller, route, or content inspection. One SELECT statement produced canonical row hashes for the complete `wp_posts`, `wp_postmeta`, `wp_term_relationships`, `wp_terms`, `wp_term_taxonomy`, and `wp_options` tables. All six tables use InnoDB, so the statement read one consistent snapshot. SQL hashed post content, metadata values, term text, and option values before returning results. No raw option values, credentials, or unnecessary profile fields were stored.

| Table | Rows | Pre-audit SHA-256 |
|---|---:|---|
| `wp_posts` | 761 | `a7ff0eb5c9def20c100dfc7680e17ca4338203fdb7b8fead6fc3ff0dfd93bd47` |
| `wp_postmeta` | 3,928 | `c582dd125fc4b6d9aacdf720b65bfbef7e5ff3614cb504c62465b8823e1588bb` |
| `wp_term_relationships` | 160 | `676a5e974719590f8d2f70e8b484c3835507364661748f14f6dc1d24fb964736` |
| `wp_terms` | 396 | `c3960c1bce6f0565021aeda4760ba7378169bf4cca1bd4c0bbac3745a9d26a18` |
| `wp_term_taxonomy` | 396 | `0d3926719996eea230b3f253791965bd40a1078220b2de85e385370b16bccb61` |
| `wp_options` | 1,198 | `27cc25362cae7d279e45f07ade79965176f9a3885b17d5835827867f24aeb6e1` |

The protected object fingerprint covers Profiles 753–757, Event 763, Products 765, 767, and 768, Templates 772, 773, 774, 124, and 1127, including their post, postmeta, and term-relationship state. Per-object hashes and counts are in `/tmp/task-7c-audit-abde5c-20261022/pre-state-fingerprint.txt`. Fresh relationship values at baseline are Event 763 `event_medewerkers=[753,757]`, Product 767 `produk_medewerkers=[753]`, and Product 768 `produk_medewerkers=[753,757]`. Products 765, 767, and 768 each have `produk_bron_event=763`. The audit did not read `_event_name`.

The database had zero `person` posts and zero `event_participation` posts across all statuses. It had five published `medewerker` profiles and 17 published `tc_events`. The `bricks_components` option has a baseline hash of `a3fe81604eb4215980bf93f842c3c4d5c6ae9a739604b015351b33a861497105`; all `bricks%`, relevant `acf%`, and non-secret FluentSnippets option hashes are recorded in the fingerprint file. The global post and postmeta fingerprints include ACF definitions, Bricks templates, and FluentSnippets records.

Pre-audit runtime file hashes are in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt`. The deployed Event Participation repository and Bricks adapter, Product People resolver and Bricks adapter, and Product source Event adapter match their source-controlled SHA-256 values. The deployed `9-acf-product-copy.php` hash is `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70`; `10-vg-bricks-medewerker-queries.php` is `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`.

No `.crashed` marker, `task-7c` file, or trace-named file existed under the runtime root at baseline. The external evidence directory contained only non-executable text files. WP-CLI's Local PHP configuration emitted an Imagick shared-library warning, but read-only option reads succeeded. The system PATH lacked `mysql`; the Local MySQL 8.0.35 client and expected socket were used for all SQL fingerprints.

The full pre-audit counts, digests, per-object hashes, field relationship IDs, runtime file hashes, and artifact inventory are in `/tmp/task-7c-audit-abde5c-20261022/`. No runtime write has been issued. The post-audit comparison remains pending Task 10.

## Fresh consumer findings

Pending Tasks 3–6. Historical inventories and implementation reports are leads only; active caller counts will come from fresh runtime inspection.

## Classifications and dependency order

Pending Task 7. Each atomic concern will receive exactly one approved lifecycle classification. These classifications authorize no deletion, migration, retirement, or runtime mutation.

## Recommendation

Pending Task 9. The completed audit will select exactly one next implementation unit, or one evidence/access blocker where the approved plan allows it. The recommendation will not be executed.

## Zero-write closeout and validation

Pending Task 10. The closeout will repeat runtime identity and protected-state fingerprints, verify the fixed rulings and artifact inventory, validate the approved documentation outputs and CSV, and run `git diff --check`.
