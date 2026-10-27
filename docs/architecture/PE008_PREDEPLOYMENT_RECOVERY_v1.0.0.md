# PE-008 predeployment recovery v1.0.0

**Status:** PE-008 remains blocked. The PE-008W retry stopped before reservation or rename, and the PE-008X diagnostic reproduced a WP-CLI database-bootstrap failure before the session report ran. The active plugin remains exact-old. No deployment retry is authorized.

## Baseline and artifact authority

The current source baseline is `de90ff061c20b164c127aa97e845e3f192429543`. The changes since `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` add documentation only, but the successful main workflow published a newer schema-v3 artifact. PE-008 now uses that newest exact-main publication.

| Authority | Value |
| --- | --- |
| Workflow | `40342642997` |
| Artifact ID | `12659772564` |
| Artifact name | `voelgoed-platform-core-de90ff061c20b164c127aa97e845e3f192429543` |
| Transport ZIP SHA-256 | `22128391bfb1c9ee040b929cc47385db71980d4d8dcacc0171d8b03fef413d7f` |
| Published manifest SHA-256 | `7cd38e758babddf495e484013d9345750af6f39689bc428f67d89a814949a932` |
| Embedded archive SHA-256 | `22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9` |
| Tree SHA-256 | `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a` |
| File count | `55` |

The exact downloaded artifact is preserved under `/home/jcschoeman96/.cache/vgnova/pe008-20261026/current-main-de90ff061c20b164c127aa97e845e3f192429543/`. Under CPython 3.14.4, `check-platform-core-build.py --ref de90ff061c20b164c127aa97e845e3f192429543 --verify-output` passed against the two embedded files.

The prior `50ee4bee…` artifact remains historical PE-008R evidence. Its exact manifest bytes are preserved as `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe008-50ee.manifest.json` with SHA-256 `b728e69d1488a28601982bfb5e84b4104b11cab5f6c9ae1dcd2a38192ef696a7`. The `50ee4bee…` and `de90ff0…` archives and file inventories are identical. Their published manifest JSON differs only in `source_commit` and `source_ref_requested`, so the manifest digests differ. The `de90ff0…` manifest is the active fixture and current authority.

The Product Contribution QA authority changed from the 54-file artifact at `7af938ac6f4422b1fa779b52444121e7a9293995`, through the intermediate `50ee4bee…` publication, to the current `de90ff0…` publication. The 54-file rollback/classification manifest remains unchanged.

The source gate also builds a verifier artifact with `source_ref_requested=HEAD`. Its manifest digest differs from the published release manifest because the requested ref is part of the manifest. The published `de90ff0…` manifest bytes and digest `7cd38e758babddf495e484013d9345750af6f39689bc428f67d89a814949a932` remain authoritative.

## Aborted pre-mutation QA invocation

The PE-008 attempt included `site-code/runtime-tests/product-contribution-qa.php` in a booted WordPress process. At line 1694 the file invokes `vg_product_contribution_qa_run()` whenever `ABSPATH` exists and `VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY` does not.

The run function immediately calls `vg_runtime_mutation_assert_session('vgnova-local-10039')` at line 1344. It would create the run ID and QA token at line 1345 only after that assertion. The uncoordinated invocation failed at the session guard, before preflight or the guarded mutation body.

Record this incident as `ABORTED_PRE_MUTATION_QA_INVOCATION`:

| Action or state | Result |
| --- | --- |
| Live QA entrypoint invoked | 1 |
| PE-007 session assertion | Rejected before token generation |
| QA mutation body started | No |
| Cleanup window opened | No |
| QA owner records created | No |
| Product Contribution records created | No |
| Persistent WordPress writes from the invocation | 0 |
| Successful live Product Contribution QA runs | 0 |
| Platform Core deployment | 0 |

Do not describe the invocation as a successful QA run. The entrypoint call remains part of the incident record.

## Person 1705 runtime baseline

The authorized site identity is `http://localhost:10039`, database `local@localhost`, WordPress `7.1.3`, and PHP `8.5.12`. Platform Core remains active at version `0.1.0` from the authorized plugin directory.

Read-only inspection found exactly one Person: ID `1705`, type `person`, status `publish`, title `Lynette Beer`, slug `lynette-beer`, created `2026-10-26 09:05:12` UTC. Post content and excerpt are empty. The only postmeta row is `_masteriyo_has_content=0`. The only taxonomy relationships are `Mentor` and `Kenner`; there are no `content_topic` relationships. `get_fields(1705, false)` returned `false`, and the postmeta query showed no ACF field values. No additional Person record was found.

The current Person row, postmeta row, and nine-column taxonomy rows match the preserved Task 10A capture. The raw capture is stored at [`person-1705-task10a-capture.raw.json`](../../site-code/runtime-tests/fixtures/person-1705-task10a-capture.raw.json), SHA-256 `acba754272b709d5088474acde4bd9733f91744988a16b812bacfdea5dc68fbd`. The normalized deterministic fixture is [`person-1705-fingerprint-capture.json`](../../site-code/runtime-tests/fixtures/person-1705-fingerprint-capture.json), SHA-256 `8bb5f5a701d2554c235e55a7759e942dbada36e6c4a708fbc5b7e53e7310c316`.

The fixture pins both query contracts and the saved `ARRAY_A` rows. PHP 8.5.4 source tests confirm all retrieved database scalar values are strings. Lists retain integer indexes, associative rows retain the SQL projection order, and canonicalization is `hash('sha256', serialize($value))` with `serialize_precision=-1`.

## Fingerprint provenance

The old expected post and relationship hashes came from the Task 10A standalone capture. The Product Contribution guard compared them with output from a different query shape. The same captured values reproduce both sets of hashes offline.

| Surface | Task 10A SQL and input | Rows | Task 10A bytes and hash | Product Contribution SQL and input | Rows | Product Contribution bytes and hash |
| --- | --- | ---: | --- | --- | --- |
| Post | `SELECT * FROM {posts} WHERE post_type = 'person' ORDER BY ID ASC`; hash the associative row | 1 | 779 bytes; `792654d2d6a47aec75ef635e4bc51e3a67d35f621dfef021f1dcd4f97e6e080f` | `SELECT * FROM {posts} WHERE ID IN (1705) ORDER BY ID ASC`; hash the one-row list | 1 | 789 bytes; `f2a980eb64045700dc3a4ca07a7950ec3cb1daea14e4a400ac48e6837b2e9b5b` |
| Postmeta | `SELECT * FROM {postmeta} WHERE post_id = 1705 ORDER BY meta_id ASC`; hash the one-row list | 1 | 137 bytes; `0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5` | `SELECT * FROM {postmeta} WHERE post_id IN (1705) ORDER BY post_id ASC, meta_key ASC, meta_id ASC`; hash the same one-row list | 1 | 137 bytes; `0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5` |
| Taxonomy relationships | Select `object_id,term_taxonomy_id,term_order,taxonomy,term_id,slug,name,parent,count`, ordered by `term_taxonomy_id,term_order,taxonomy,term_id,slug` ascending; hash the nine-column row list | 2 | 494 bytes; `220c90c6c8838738030fe3ae3c3011446cd9d6b6b0162cba8c6597d438ab13b6` | Select `object_id,term_taxonomy_id,term_order,taxonomy,term_id,slug`, ordered by `object_id,term_taxonomy_id,term_order,taxonomy,term_id,slug` ascending; hash the six-column row list | 2 | 364 bytes; `1d347313f990ba8063fe34926ef537e85d2dc4bdc575e5369338be45b9bdf7fb` |

Postmeta is the control. Both query paths return the same sole row, build the same list, and serialize the same bytes. Their ordering clauses cannot change that one-row list. Posts differ because Task 10A hashes one associative row while Product Contribution hashes a list containing that row. Relationships differ because the queries project nine columns and six columns respectively. The captured values and their output order reproduce the hashes deterministically, so this is a `HASH_PROVENANCE_DEFECT`, not live data drift or hash nondeterminism.

The mismatched constants first appeared in deployment-tool commit `445a656448d3222befd721117fe7cef5aebbba8a`. The initial RED regression computed the group hashes from the captured rows and failed because the current Person guard still expected the Task 10A hashes: 26/27 integrity checks passed. After changing only the expected group hashes and moving the query/canonical serialization into `site-code/runtime-tools/pe008-protected-state.php`, the suite passed 27/27. The standalone PE-008 protected-state regression also passed. It does not load WordPress or include the Product Contribution QA file.

At the PE-008R evidence snapshot, Product Contribution pinned the actual `50ee4bee…` publication. PE-008T below supersedes that active authority with the newest `de90ff0…` manifest. The old `54`-file fixture remains preserved for exact-old runtime classification, and the intermediate `50ee4bee…` bytes remain in their own historical fixture.

## Runtime and coordinator state

The active plugin remains `OLD_AUTHORITY_EXACT`: 54 files, tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`, missing `0`, extra `0`, content mismatch `0`, mode mismatch `0`, symlinks `0`, unsupported objects `0`. The integrity check used the old published manifest and `platform-core-runtime-integrity.php`; it did not include or invoke the Product Contribution QA entrypoint.

The post-incident read-only queries found zero Product Contribution posts, zero Event Participation posts, and zero Product Contribution QA owner-meta rows. Person 1705 remains the only Person. The WP-CLI coordinator status returned no status error: the host filesystem lock was free, DB lock A was free, and DB lock B was free. No PE-007 mutation session was acquired during PE-008R.

The superseded stage at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-20261026T105633Z` was not used or modified. No PE-008 backup was created.

## Source verification

All source gates passed after the remediation:

- `./site-code/bin/check-source.sh` passed, including PHP lint, JSON validation, Python AST parsing, `bash -n`, ShellCheck, the Platform Core build/source gate, FCM integration, and runtime mutation policy scan.
- `./voelgoed-platform-core/bin/check.sh` passed all 127 dashboard checks.
- Product Contribution integrity regression: 27/27 passed.
- PE-008 protected-state fingerprint regression passed from the captured fixture only.
- PE-008 deployment helper regression: 28 cases passed using temporary trees only.
- Runtime mutation core: 73 tests passed; session: 19/19; policy: 31/31; static policy scanned 131 tracked PHP files and passed.
- FCM integration: 3 tests passed.
- `actionlint` passed with version 1.7.12; `git diff --cached --check` passed.

The source gate's local verifier build retained the Platform Core tree hash and archive hash. No canonical Platform Core module source file changed. No live QA entrypoint ran.

### Independent review finding and correction

The first independent review of draft head `47bef76468a5d54c6cacd50f33c2bf2f6abf4b7f` found that a failed `stage → active` rename followed by a failed `stage → failed` preservation rename returned before trying `backup → active`. The active plugin directory could therefore remain absent even though the old backup was intact.

A fault-injection regression failed before the correction because the helper did not attempt the fourth rename. The helper now verifies that the unchanged stage still matches the exact new authority, reports that stage path as the retained failed tree, and attempts backup restoration. If the stage cannot be verified, the helper still restores and verifies the old active tree before returning `BLOCKED_CRITICAL`. Additional fault-injection cases exposed empty failed-tree reservations on critical exits; the helper now removes the reservation when empty and leaves it intact whenever it contains a preserved failed tree. A later independent review accepted the PE-008S process and exit-code changes at head `27b8900883f5a71e99ac16f2d367a9311add3b1b`; exact-head run `40357725833` passed. PE-008T below refreshes only the artifact provenance. Its exact-head review and checks remain pending.

## PE-008R mutation counts

| Mutation | Count |
| --- | ---: |
| Platform Core deployment | 0 |
| ACF writes | 0 |
| Taxonomy writes | 0 |
| Post writes | 0 |
| Persistent WordPress DB writes | 0 |
| Bricks writes | 0 |
| FluentSnippet writes | 0 |
| Rewrite flushes | 0 |
| Plugin activation/deactivation writes | 0 |
| Successful live Product Contribution QA runs | 0 |
| Aborted pre-mutation QA invocations | 1 |

No credentials are stored in this report. A later approved PE-008 execution must create a new stage from artifact `12659772564`; it must not use the superseded staging directory or the intermediate `50ee4bee…` artifact. PE-008 remains blocked from deployment until independent review of the exact draft PR head and a separate authorization to run the deployment.

## PE-008S process isolation and CLI results

PR #59 remains draft and unmerged. The runtime deployment remains blocked. PE-008S changes only source, tests, and this evidence.

The deployment process tree is:

```text
PE-007 coordinator
└── PE-008 deployment parent (WordPress-free)
    ├── pre-rename WP-CLI guard child
    ├── filesystem directory swap
    └── post-swap WP-CLI smoke child
```

The parent loads no `wp-load.php`, `wp-settings.php`, active plugin, or theme. It verifies exact-old and staged exact-new trees, then starts the pre-rename child. That child receives the PE-007 session environment and FD3, suppresses plugin, theme, MU-plugin, and WP-Cron loading, explicitly loads the staged `RuntimeMutationSession.php`, and calls `vg_runtime_mutation_assert_session('vgnova-local-10039')`. The parent waits for `proc_close()` before it creates a failed-tree reservation or renames the active directory. The child test uses a temporary locked file and fixture descriptors; it does not connect to Local WordPress or MariaDB.

WP-CLI loads the source-controlled command library through `WP_CLI_REQUIRE`, which is equivalent to its normal `--require` option and makes the `WP_CLI` command API available before the WordPress load hook. A direct probe against the installed WP-CLI confirmed that a command registered this way runs at `before_wp_load`. The child process sets `WP_CLI_CONFIG_PATH` and `WP_CLI_SYSTEM_SETTINGS_PATH` to `/dev/null` and runs from `/` to avoid unrelated user, system, or repository-local WP-CLI configuration. The helper carries a supplied Local DB socket through `mysqli.default_socket`, `pdo_mysql.default_socket`, and `VGNOVA_DB_SOCKET`. It does not place DB credentials in argv or the child environment. With no socket override, it uses the current PHP socket defaults and removes any stale socket override from the child environment. The official [WP-CLI config reference](https://make.wordpress.org/cli/handbook/references/config/) documents `WP_CLI_REQUIRE` as equivalent to `--require` and documents `WP_CLI_SYSTEM_SETTINGS_PATH`.

The post-swap smoke runs in another new WP-CLI process. It checks PHP 8.5.x, WordPress bootstrap, active Platform Core at version `0.1.0`, and the deployed session guard while PE-007 coordination is active. The rollback smoke uses a separate process after the old tree is restored.

The production CLI result mapping is:

| Result | Exit |
| --- | ---: |
| `SWAPPED` | 0 |
| `SKIPPED_ALREADY_EXACT` | 0 |
| `ROLLED_BACK` | 3 |
| `BLOCKED` | 1 |
| `BLOCKED_CRITICAL` | 2 |

The PE-008S TDD regression first failed while normal `--require` remained in argv and while the system WP-CLI settings path was not disabled. A direct WP-CLI probe then showed `WP_CLI_EARLY_REQUIRE` loads before the `WP_CLI` class exists, so that environment hook cannot register the guard command. The regression failed again while the helper used that early hook. The helper now uses `WP_CLI_REQUIRE`; the installed WP-CLI probe registered and ran a `before_wp_load` command, and all 33 PE-008 cases passed. The source-safe subprocess case proves FD3 reaches the child, fixture descriptors 4 through 8 do not, the PE-007 session fields and socket override arrive without credentials, and the parent starts its first rename only after the child exits. Separate subprocess tests check the JSON status and process exit for every terminal state.

PE-008S has not accessed Local WordPress, performed a deployment, started a PE-007 mutation session, or run live Product Contribution QA. Runtime mutation counts remain unchanged from PE-008R: Platform Core deployments 0, ACF writes 0, taxonomy writes 0, post writes 0, persistent WordPress DB writes 0, Bricks writes 0, FluentSnippet writes 0, rewrite flushes 0, plugin activation/deactivation writes 0, successful live QA runs 0, and aborted pre-mutation QA invocations 1.

## PE-008T provenance refresh

`origin/main` advanced to `de90ff061c20b164c127aa97e845e3f192429543`. The intervening source diff adds only documentation. The successful main workflow `40342642997` nevertheless published artifact `12659772564`, so PE-008 now uses that publication as its source authority.

The transport ZIP SHA-256 is `22128391bfb1c9ee040b929cc47385db71980d4d8dcacc0171d8b03fef413d7f`. The published manifest SHA-256 is `7cd38e758babddf495e484013d9345750af6f39689bc428f67d89a814949a932`. The archive SHA-256 remains `22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9`, the tree SHA-256 remains `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a`, and the file count remains 55.

The downloaded `50ee4bee…` and `de90ff0…` artifacts have byte-identical archives and identical file inventories. Their published manifest JSON differs only in `source_commit` and `source_ref_requested`, which identify the exact release commit. The `50ee4bee…` manifest remains preserved at `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe008-50ee.manifest.json`; the active fixture now contains the exact downloaded `de90ff0…` manifest bytes. A regression pins both byte digests and the expected field-level comparison.

The PE-008S deployment helper mechanics were not changed by this refresh. They still use a WordPress-free deployment parent, a separate pre-rename WP-CLI guard child, FD3 session proof, the distinct post-swap smoke child, and the exit contract `SWAPPED=0`, `SKIPPED_ALREADY_EXACT=0`, `ROLLED_BACK=3`, `BLOCKED=1`, `BLOCKED_CRITICAL=2`.

The source authority commit is `cb6d76d78952fb568edde36e95930e93595b56d7`; the artifact source commit is `de90ff061c20b164c127aa97e845e3f192429543`. The PE-008T diff changes only the active manifest authority, its regression, and the historical manifest fixture. In the deployment helper, only the expected new manifest authority changed.

The authority regression ran RED before source constants or manifest bytes changed. It passed 25/27 checks and failed because it still read the `50ee4bee…` manifest while expecting the current publication. After preserving the historical bytes and installing the exact downloaded `de90ff0…` manifest, it passed 28/28. The comparison regression confirms that the two release manifests differ only in `source_commit` and `source_ref_requested`.

The PE-008T source gates passed: `check-source.sh`, `voelgoed-platform-core/bin/check.sh`, Product Contribution integrity 28/28, PE-008 protected-state, PE-008 bootstrap 33/33, mutation core 74, mutation session 19/19, mutation policy 32, static policy across 133 tracked PHP files, and FCM 3/3. The dashboard suite passed 127/127. PHP lint, JSON validation, Python AST, Bash syntax, ShellCheck, `actionlint` 1.7.12 through mise, the canonical artifact checks, and final `git diff --check` passed.

PE-008T performed no Local WordPress inspection, deployment, mutation-session acquisition, live QA invocation, or persistent write. Its runtime mutation counts are zero. The earlier aborted pre-mutation QA invocation remains recorded as one. The exact PE-008T GitHub check result is reported with the final draft PR head; deployment remains blocked.

## PE-008U blocked pre-guard deployment attempt

The authorized PE-008U execution used reviewed tooling head `9d6d3f123217f31edf449c4a80288de59b5c8170`, current main `de90ff061c20b164c127aa97e845e3f192429543`, and artifact `12659772564`. The PE-007 coordinator launched the helper and acquired a mutation session for `vgnova-local-10039`. The production guard callback ran after it rechecked the active and staged trees.

The callback referenced `$base_environment` for the Local socket override and subprocess environment, but its `use (...)` list omitted that variable. PHP reported an undefined variable, then raised a `TypeError` because `vg_pe008_run_subprocess()` received `null` where it requires an array. The callback returned no successful guard result, and the WP-CLI guard child never started. The helper returned `BLOCKED`; the coordinator recorded child exit code `1` and released the session.

| PE-008U action or state | Result |
| --- | --- |
| Coordinator launched | Yes |
| PE-007 session acquired | Yes; session `f306cb4da79628f1be1aeb21f9b263a5` |
| Deployment helper launched | Yes |
| Production guard callback entered | Yes |
| WP-CLI guard subprocess started | No |
| Failed-tree reservation created | No |
| Active-to-backup rename | No |
| Stage-to-active rename | No |
| Platform Core deployment | 0 |
| Persistent WordPress writes | 0 |
| Successful live Product Contribution QA runs | 0 |

The read-only post-failure check classified the active plugin as `OLD_AUTHORITY_EXACT`: 54 files, tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`, with zero missing files, extra files, content mismatches, mode mismatches, symlinks, or unsupported objects. The fresh stage at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-20261026T194240Z` exactly matched the authorized 55-file artifact, tree `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a`. The backup destination remained absent. After the helper exited, the host lock and both database locks were free.

This was a `BLOCKED_PRE_GUARD_PRODUCTION_WIRING` deployment attempt. It did not invoke Product Contribution QA. The separate historical `ABORTED_PRE_MUTATION_QA_INVOCATION` count remains one.

## PE-008V production closure capture fix

PE-008V uses the existing isolated worktree and branch. A fresh fetch confirmed `origin/main` remains `de90ff061c20b164c127aa97e845e3f192429543`. PE-008V performs no Local runtime access, deployment, mutation-session acquisition, live QA invocation, or persistent WordPress write. The runtime and stage facts above remain the last observed PE-008U state.

The new regression reads the actual closure declarations inside `vg_pe008_production()` and checks their free variables against their lexical `use (...)` captures. Before the production change, the test failed with `production guard does not capture base_environment`; the other 33 PE-008 bootstrap cases passed. The production fix adds `$base_environment` to the guard closure's existing capture list. The smoke closure already captures the same environment snapshot and required no change. The real subprocess tests remain in place.

The sibling callback audit found no other uncaptured production variable:

| Production callback | Outer-scope variable audit |
| --- | --- |
| `guard` | `$active`, `$stage`, `$old`, `$old_path`, `$new`, `$new_path`, `$wordpress_root`, `$wp_cli`, `$command_library`, and `$base_environment` are captured; `$expected` is a parameter; report, socket, and command values are local. |
| `verify` | `$old`, `$new`, `$old_path`, and `$new_path` are captured; `$path` and `$which` are parameters; authority, manifest, and report values are local. |
| `smoke` | `$target`, `$wp_cli`, `$base_environment`, and `$smoke_library` are captured; `$root` and `$require_guard` are parameters; plugin, socket, and command values are local. |
| `rename` | The inline static arrow callback uses only its `$a` and `$b` parameters and has no outer-scope variable dependency. |

Local PE-008V verification passed: `check-source.sh`; the Platform Core dashboard suite at 127/127; Product Contribution integrity at 28/28; protected-state regression; PE-008 bootstrap at 34/34; mutation core at 74; session at 19/19; mutation policy at 32/32; static policy across 133 tracked PHP files and 34 files with mutation APIs; and FCM integration at 3/3. The source gate also passed PHP lint, JSON parsing, Python AST parsing, `bash -n`, and ShellCheck. `actionlint` 1.7.12 and `git diff --check` passed. The canonical Platform Core build remains at 55 files with tree `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a` and archive `22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9`.

The exact draft PR head and GitHub checks are reported in the handoff. PE-008 deployment remains blocked. A new deployment attempt requires independent review of the updated head and renewed authorization.

## PE-008W authorized retry stopped before rename

The retry ran at `2026-10-27 04:55 UTC` from the independently reviewed tooling head `0094319e96a8d4610adf4229cba75d1e7d4abcfa`, with `origin/main` still at `de90ff061c20b164c127aa97e845e3f192429543`. The authorized publication remained workflow `40342642997`, artifact `12659772564`, with the exact `de90ff0…` published manifest, archive, and tree authorities listed above.

Immediately before the retry, the reviewed worktree was clean at the expected tooling head. A fresh integrity check classified the active plugin as `OLD_AUTHORITY_EXACT`: 54 files, tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`, with zero missing, extra, content-mismatched, mode-mismatched, symlink, or unsupported entries. The preserved stage at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-20261026T194240Z` matched the published 55-file authority exactly, had no symlinks, unsupported objects, or world-writable entries, and shared device `66306` with the active directory and backup parent. Its files retained the original maximum change time `2026-10-26T19:43:26.868965189Z`. The new backup path `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-backup-20261027T045227Z` was absent.

The runtime identity was WordPress `7.1.3`, runtime PHP `8.5.12`, WP-CLI PHP `8.5.4`, ACF Pro `6.8.1`, WooCommerce `11.2.1`, and active Platform Core `0.1.0`. The fresh protected-state capture at `/home/jcschoeman96/.cache/vgnova/pe008-20261027/runtime-predeployment-20261027T045227Z.json` had SHA-256 `4d3c4cbd1470e163cf45061699cb0f1aea11321a699e2f15988fd4552ef8c6f1`. Its complete summary matched the prior accepted protected snapshot. Person 1705 remained the sole published `person` record at slug `lynette-beer`, with Mentor and Kenner, no `content_topic` relationships, `_masteriyo_has_content=0`, and fingerprints `f2a980eb64045700dc3a4ca07a7950ec3cb1daea14e4a400ac48e6837b2e9b5b`, `0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5`, and `1d347313f990ba8063fe34926ef537e85d2dc4bdc575e5369338be45b9bdf7fb` for post, postmeta, and taxonomy relationships. The Product Contribution and Event Participation counts remained zero; all 20 approved products, the protected ACF definitions, Product Role terms and relationships, profiles, event, templates, Bricks state, and FluentSnippets fingerprints matched.

The PE-007 coordinator acquired session `fd5999ac793624d9b5cc726d91606d1a` for target `vgnova-local-10039` and launched the reviewed helper from that session. The production guard callback ran, but the helper returned `{"status":"BLOCKED","reason":"mutation_session_invalid"}` with child exit code `1`. The helper's result does not distinguish WP-CLI child startup failure from a started child returning an unclean/nonzero result; no guard-child stdout, stderr, or exit code was retained. Therefore the guard child is recorded as **not confirmed started or successfully exited**, and the session assertion is not recorded as successful. This is the point where PE-008W stopped.

The state machine checks the guard result before creating the failed-tree reservation or performing either rename. The post-attempt filesystem check found no new failed-tree reservation. Active-to-backup and stage-to-active renames did not occur: the active directory still matched exact-old, the stage remained exact-new, and the new backup path remained absent. No post-swap WordPress smoke or deployed-guard handshake ran.

The read-only post-attempt snapshot at `/home/jcschoeman96/.cache/vgnova/pe008-20261027/runtime-postblocked-20261027T045227Z.json` had SHA-256 `d50dff3bbebc1e5b0c0ee8328a6395e4314ab4040297fec44f508a6d41d70798`. Its protected summary equaled the pre-attempt summary, including Person 1705, ACF database-definition fingerprints, protected groups, products, Bricks, FluentSnippets, and route-option fingerprints. The active runtime remained `OLD_AUTHORITY_EXACT`; the stage remained exact-new. Coordinator status after release reported the host lock and DB locks A and B free. The sanitized DB lock names were `vgnova.mutation.a.f9d63fbcd072e54005ff68365e36e040fbf11b93` and `vgnova.mutation.b.f9d63fbcd072e54005ff68365e36e040fbf11b93`.

| PE-008W action or state | Result |
| --- | --- |
| Coordinator launched / PE-007 session acquired | Yes; session `fd5999ac793624d9b5cc726d91606d1a` |
| Helper launched at reviewed tooling head | Yes; `0094319e96a8d4610adf4229cba75d1e7d4abcfa` |
| Production guard callback entered | Yes; returned false to the state machine |
| WP-CLI guard child started and exited successfully | Not confirmed; helper output did not retain child startup/result details |
| Deployment result / coordinator child exit | `BLOCKED` / `1`; reason `mutation_session_invalid` |
| Failed-tree reservation created | No |
| Active-to-backup rename | No |
| Stage-to-active rename | No |
| Active post-attempt state | Exact-old: 54 files; tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56` |
| Stage post-attempt state | Exact-new: 55 files; tree `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a` |
| New backup destination | Absent |
| Persistent WordPress state delta observed | None; pre/post protected summaries are equal |
| Successful live Product Contribution QA runs | 0 |

The PE-008W blocked retry is separate from `BLOCKED_PRE_GUARD_PRODUCTION_WIRING` in PE-008U and the historical `ABORTED_PRE_MUTATION_QA_INVOCATION`. The QA invocation count remains one; PE-008W did not include or invoke the QA harness. Aggregate successful Platform Core deployments remain zero and persistent WordPress writes remain zero. A new deployment attempt requires a separate decision after the session-guard failure is reviewed.

## PE-008X nested session diagnostic

The diagnostic ran at `2026-10-27 06:29 UTC` from tooling head `2363b02b0640307e1768c283adef4ff2d60e639a`. The source checks on that exact head passed in GitHub Actions run `40460036669`: Dashboard checks, Canonical source checks, and `VG Nova Platform Gate` succeeded; publication was skipped. `origin/main` remained `de90ff061c20b164c127aa97e845e3f192429543`. PR #59 remained draft, open, and unmerged.

Immediately before the diagnostic, the active plugin matched the old 54-file manifest exactly: zero missing, extra, content-mismatched, mode-mismatched, symlink, or unsupported entries; tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`. The existing stage matched the new 55-file manifest exactly with the same zero deltas and tree `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a`. The active plugin and stage shared device `66306`. PE-007 status showed the host lock and both database locks free.

One PE-007 session ran the read-only diagnostic parent. The parent started and reaped its WP-CLI child. The child exited `1`; stdout was empty, stderr contained 49 bytes, and the parent could not parse a diagnostic JSON object. The sanitized stderr was `Error: Error establishing a database connection.` WordPress did not finish bootstrap, so the `pe008 session-diagnostic` command and `vg_runtime_mutation_session_report()` did not execute. `session_valid`, the report error list, filesystem-lock validity, and DB-owner matches are therefore unavailable.

Classify this probe as `WP_CLI_BOOTSTRAP_OR_COMMAND`, specifically a database connection failure during WordPress bootstrap. This proves that the current nested WP-CLI path cannot reach the diagnostic command with the supplied Local DB transport. PE-008W did not preserve its child output, so this probe cannot prove that the earlier child emitted the same message. It does not establish whether the PE-007 session report would have passed after WordPress connected. No deployment retry is authorized.

Raw subprocess captures remain outside the repository with mode `0600` under `/home/jcschoeman96/.cache/vgnova/pe008-session-diagnostic-20261027T071500Z/`. Stdout is empty. The 49-byte stderr contains only the database-bootstrap error above. The committed evidence records the sanitized classification, not the raw capture files.

After the probe, PE-007 status reported the host lock and DB locks A and B free. The active plugin remained exact-old at 54 files and the stage remained exact-new at 55 files. No backup directory was created. No failed-tree reservation or directory rename occurred. The diagnostic did not load or invoke Product Contribution QA. No persistent WordPress writes occurred. The historical QA invocation count remains one; the PE-008U and PE-008W blocked incidents remain separate.

| PE-008X action or state | Result |
| --- | --- |
| Probe started | Yes; WP-CLI child process started |
| Child exit / stderr empty | `1` / No |
| Diagnostic JSON parsed / session report executed | No / No |
| Primary failure layer | `WP_CLI_BOOTSTRAP_OR_COMMAND` |
| Sanitized child error | WordPress could not establish a database connection |
| Session validity and lock-owner report | Not available; diagnostic command did not execute |
| Host lock / DB lock A / DB lock B after release | Free / Free / Free |
| Active plugin after probe | Exact-old, 54 files; tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56` |
| Stage after probe | Exact-new, 55 files; tree `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a` |
| Backup / failed reservation / directory renames | Absent / none / zero |
| Platform Core deployments / persistent WordPress writes / successful live QA runs | `0` / `0` / `0` |
| Historical aborted pre-mutation QA invocation | `1` |
| Blocked pre-guard wiring attempts / blocked pre-rename guard attempts | `1` / `1` |
| PE-008X diagnostic probes | `1` |
