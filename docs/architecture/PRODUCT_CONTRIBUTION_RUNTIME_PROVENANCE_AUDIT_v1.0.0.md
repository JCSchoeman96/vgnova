# Product Contribution runtime provenance audit v1.0.0

**Audit date:** 2026-10-24
**Audit branch:** `docs/product-contribution-runtime-provenance`
**Execution baseline:** `0216d86a60c02cc767833bbb5cdec8b57c54d335`
**Final `origin/main` observed during audit:** `2669f0a08c81340625c469491311587fff1d7df2`
**Runtime verdict:** `PROVENANCE_PARTIAL`
**Runtime baseline candidate:** `NO`
**Ongoing automatic writer:** `UNKNOWN`
**Task 8:** remains blocked. Task 9 was not started.

## Executive finding

The runtime contains source files from the merged Task 5 snapshot, PR #39 at `a9587abf0a64ebedf202562571ce55225377228f`. All 79 non-bootstrap runtime files match a tracked source file at that merge. Seventy-seven also match current `main`; two test files remain at the pre-Task 7 version. The runtime entrypoint is a different, duplicated result. Reapplying the PR #39 bootstrap patch to the already-integrated preflight entrypoint reproduces its exact hash. The active `includes/Plugin.php` is an older Task 5 generated target. The Task 6 `PersonProductsBricks.php` file is missing.

The 16:42:36 SAST file creation batch on October 23 matches the PR #39 source tree. The repository root's `main` branch had advanced to the PR #39 merge 15 seconds before the batch began. No available log identifies the copy command or its actor for this batch, so the timestamp and source evidence support the post-merge deployment hypothesis without proving its mechanism.

A separate Codex/T3 Code command at 09:42:56 SAST on October 24 is directly recorded. It copied 18 shared Platform Core files and installed `includes/Plugin.php` into the active runtime. Their creation/change timestamps match that command. The command ran from the repository root checkout at the older PR #39 merge, after PR #42 had already advanced `origin/main`. This is a proven pre-discovery Task 8 runtime write. Task 8A itself performed zero runtime writes and zero WordPress writes.

No Product Contribution ACF definitions are present in the database. The three definitions remain loaded from Local JSON, Product Role has zero terms, and no Product Contribution, Person, or Event Participation records exist. The protected post, metadata, relationship, Bricks, snippet, and Event Participation schema fingerprints match the approved preflight values. The PHP-local Product People group changed from two fields to the one-field `product_people_mode` definition in the reviewed Task 5 source; that is runtime PHP code, not a database ACF sync.

The audit cannot identify who or what copied the October 23 batch. Current hooks, repository automation, scheduled jobs, and filesystem watches provide no evidence of an active writer for this target. Because those checks cannot rule out a past manual/editor action or an external mechanism, the ongoing-writer result remains `UNKNOWN`.

## Repository baseline and movement

The required starting checks were run in a fresh worktree created from current `origin/main`:

```text
git fetch origin
git status --short
git rev-parse origin/main
git log -5 --oneline --decorate origin/main
```

At audit start, `origin/main` was `0216d86a60c02cc767833bbb5cdec8b57c54d335`, the PR #44 merge. PR #44 changed only `site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php`; the comparison from PR #43's merge `454ae0e643c96da791945c53451c7f409bbbfa02` contains no Platform Core or ACF changes. PR #44 was merged before this audit and was not modified.

At audit start, PR #45, `ci: add VG Nova platform source gate`, was open against `main` at head `2787fb06c6994f754537a7c8999b871ebbb6aba9`. It merged during the audit at `2026-10-24T09:05:54Z` as `2669f0a08c81340625c469491311587fff1d7df2` (11:05:54 SAST). The merge changes only `.github/workflows/voelgoed-dashboard.yml`, `docs/superpowers/plans/2026-10-24-pe-002-platform-gate.md`, and `site-code/bin/check-source.sh`. The workflow and script add PHP 8.5 source checks and an aggregate CI gate; the PR states that runtime, schema, deployment, and WordPress state are unchanged. The commit does not change Platform Core source or the runtime assumptions in this audit. The execution baseline remains the SHA observed when the audit worktree was created; the branch is rebased onto the final `origin/main` before opening its PR.

PR #46 was closed unmerged at `2026-10-24T09:08:41Z` after completing its temporary PE-003 negative-control purpose. It did not enter `main` and was not modified by Task 8A.

The Task 8 worktree from the earlier blocked attempt began at `be713383b03c730bfdf4a17a80cfcbeadc8e44fc`. This audit used a new worktree at the current `main`; it did not reuse the Task 8 branch.

PR #43 merged at `454ae0e643c96da791945c53451c7f409bbbfa02` and established the repository PHP 8.5 contract. Its body explicitly says it did not update the runtime PHP version. The VGNova runtime currently runs PHP 8.5.3. Its active Platform Core header still says `Requires PHP: 8.0`; current tracked source says `Requires PHP: 8.5`.

The root checkout used by the October 24 Task 8 command remained on local `main` at PR #39 merge `a9587abf0a64ebedf202562571ce55225377228f`. Its reflog records the fast-forward at `2026-10-23T16:42:21+02:00` and no later root `main` advance. By the October 24 command, `origin/main` had advanced through PR #42 to `be713383b03c730bfdf4a17a80cfcbeadc8e44fc`. The command therefore read from a stale repository checkout, not the current Task 8 worktree or then-current `origin/main`.

## Pull request runtime claims

PR descriptions were read for #36 through #45. The table records what each body says about runtime work. A missing runtime statement is not evidence that no runtime action occurred.

| PR | Merged head and merge | Runtime/database statement in PR body |
|---|---|---|
| [#36](https://github.com/JCSchoeman96/vgnova/pull/36) | head `5a2cbd8fca8cb1059f8ce9fc751d648d64aa0b36`; merge `b049f47876957c42fb40554645feab6799147221` | Says no runtime or database state changed. A separate exact-head review comment approves Task 1 + Task 2. |
| [#37](https://github.com/JCSchoeman96/vgnova/pull/37) | head `5e85699d99e712a3d66a95292bb2724bf1551079`; merge `10231ece9895deaa382a0d3af0a6d698fdff02e0` | Describes Task 3 repository and lock source only. No runtime-state statement. The body says an independent scoped review found no remaining issues; a later exact-head review comment records **APPROVE**. |
| [#38](https://github.com/JCSchoeman96/vgnova/pull/38) | head `fac48bb50dbfe1fce2c69631521308efee55e3ad`; merge `1a00e118749d0bfe827aa81ce5b93a35fcead02d` | Describes canonical writes, admin validation, locking, tests, and source lint. No runtime-state statement. A separate exact-head review comment approves the source. |
| [#39](https://github.com/JCSchoeman96/vgnova/pull/39) | head `9b3ba8bbb60330b566db0921749cb237fb25e0c8`; merge `a9587abf0a64ebedf202562571ce55225377228f` | Describes the Product People authority cutover, Local source, and runtime QA harness code. It does not claim an actual runtime deployment or QA run. The body leaves the independent gate checkbox unchecked, but a later exact-head review comment records **APPROVE FOR MERGE**. |
| [#40](https://github.com/JCSchoeman96/vgnova/pull/40) | head `d75125b530368baf65ea6e9dd3ce43d8959c3af0`; merge `12009ec54dbf5a16b46fa4d8b6484677d04746e4` | Explicitly says no Product Contribution code, schema, runtime, or Bricks template changes. |
| [#41](https://github.com/JCSchoeman96/vgnova/pull/41) | head `35b23b9d4007117c0b128121b7496d5c5b9602d2`; merge `cd6ccd25336f94867cdd82ad08da73b3f3493abd` | Says no live MCP state was used and no runtime state changed. Task 7 had not started. |
| [#42](https://github.com/JCSchoeman96/vgnova/pull/42) | head `0d42e4e71f84007998a4393fab91905fa3b8d92d`; merge `be713383b03c730bfdf4a17a80cfcbeadc8e44fc` | Explicitly says no runtime deployment, ACF sync, Product Role seeding, Bricks template mutation, data migration, or JSON sync. Task 8 had not started. |
| [#43](https://github.com/JCSchoeman96/vgnova/pull/43) | head `3814da537c1fd662c4965819f23097ff5cd13b5a`; merge `454ae0e643c96da791945c53451c7f409bbbfa02` | Explicitly says no runtime deployment or runtime PHP update. |
| [#44](https://github.com/JCSchoeman96/vgnova/pull/44) | head `e6144c28259c0fc027e7832a50e0ca8ccb9dbe10`; merge `0216d86a60c02cc767833bbb5cdec8b57c54d335` | Changes the Editorial Dimensions test allowlist and duplicate checks. No runtime action is described. |
| [#45](https://github.com/JCSchoeman96/vgnova/pull/45) | head `2787fb06c6994f754537a7c8999b871ebbb6aba9`; merge `2669f0a08c81340625c469491311587fff1d7df2` | Adds PHP 8.5 source CI and an aggregate gate. The body explicitly excludes runtime, schema, deployment, and WordPress state changes. It merged while Task 8A was in progress. |

The GitHub REST review endpoint returned an empty formal-review array for each PR #36 through #44. Exact-head independent approvals are present as PR comments for #36, #37, #38, and #39. Those comments—not the formal review array—are the review evidence used for the corresponding source snapshots. PR #45 was open at audit start and merged during the audit. The absence of formal review objects remains a limitation of the GitHub PR record for #36–#44.

## Runtime identity

The explicit VGNova Novamira connection and a read-only WordPress PHP inspection identified the target below. No request was made to `localhost:10049`.

| Item | Observed value |
|---|---|
| WordPress root / `ABSPATH` | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/` |
| Home URL | `http://localhost:10039/` |
| Site URL | `http://localhost:10039/` |
| Effective port | `10039` |
| Database name | `local` |
| Database host | `localhost` |
| PHP | `8.5.3` |
| WordPress | `7.1.3` |
| ACF Pro | `6.8.1`, active |
| WooCommerce | `11.2.1`, active |
| Platform Core | `0.1.0`, active |
| Active plugin path | `voelgoed-platform-core/voelgoed-platform-core.php` |
| Runtime Platform Core header | `Requires PHP: 8.0` |
| Current tracked Platform Core header | `Requires PHP: 8.5` |

The WP-CLI `db query` fallback could not run because the local `mysql` executable is unavailable. No SQL ran through that failed command. Read-only `$wpdb` `SELECT` queries through the VGNova WordPress connection supplied the database inventory instead. The PHP inspection used getters and `SELECT` queries only.

## Filesystem identity and ACF Local JSON path

The runtime plugin, tracked root plugin, and `site-code` module are separate directories on the same ext4 root filesystem. `readlink` reports no link for any of these plugin directories. `findmnt -T` reports `/dev/nvme1n1p2` mounted at `/` for all three paths; no bind mount is present. The runtime plugin contains 81 regular files and zero symlink files.

| Directory | `readlink -f` result | Device:inode | Mode | Owner | Type |
|---|---|---|---|---|---|
| Runtime plugin | same as `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core` | `66306:112984185` | `040755` | `1000:1000` | directory |
| Tracked root plugin | same as `/home/jcschoeman96/projects/current/vgnova/voelgoed-platform-core` | `66306:106603045` | `040775` | `1000:1000` | directory |
| `site-code` module | same as `/home/jcschoeman96/projects/current/vgnova/site-code/voelgoed-platform-core` | `66306:106603023` | `040775` | `1000:1000` | directory |
| Fresh audit worktree root plugin | same as `/home/jcschoeman96/projects/current/vgnova/.worktrees/product-contribution-runtime-provenance/voelgoed-platform-core` | `66306:29647064` | `040775` | `1000:1000` | directory |
| Fresh audit worktree module | same as `/home/jcschoeman96/projects/current/vgnova/.worktrees/product-contribution-runtime-provenance/site-code/voelgoed-platform-core` | `66306:29647005` | `040775` | `1000:1000` | directory |

`readlink` reports `(not link)` for the three plugin/module directories. All five paths are on `/dev/nvme1n1p2`, mounted at `/` as ext4 with `rw,relatime`. Key runtime files also have distinct source inodes:

| File | Runtime device:inode | Source device:inode |
|---|---|---|
| `voelgoed-platform-core.php` | `66306:113022717` | `66306:106603053` |
| `includes/Plugin.php` | `66306:113022061` | `66306:106603052` |
| `includes/Event/EventParticipationRepository.php` | `66306:116791921` | `66306:29647012` |
| `includes/Product/ProductContributionRepository.php` | `66306:8955559` | `66306:29647019` |
| `includes/Product/ProductPeopleFields.php` | `66306:8915518` | `66306:29647022` |
| `includes/Product/ProductPeopleResolver.php` | `66306:8951094` | `66306:29647023` |
| `includes/Product/PersonProductsBricks.php` | absent | `66306:29647016` |

The runtime is neither a source symlink nor a hard-linked view of the source tree.

The active child-theme `acf-json` path has `readlink` target `/home/jcschoeman96/projects/current/vgnova/site-code/acf-json` and `readlink -f` resolves to the same stable repository JSON directory, not an abandoned feature worktree. Its followed `stat` reports device `66306`, inode `27942504`, mode `040775`, owner `1000:1000`, and directory type. ACF `save_json` points there. ACF `load_json` includes that path and the Member Perks plugin JSON directory. The target Product Contribution JSON files on disk match current `origin/main` byte-for-byte:

| Local JSON key/file | SHA-256 |
|---|---|
| `post_type_product_contribution.json` | `e98b4ad7ebf1831975fa7777bcccf2baf9966e21665293533b1dbef6d88ff747` |
| `taxonomy_product_role.json` | `d371c6a1ac769b7491b810d04080991ba638258781bce320e0f4f7b842cb144e` |
| `group_vg_product_contribution_details.json` | `3ed2d1a00e3c224adcb7f51e33fa82ed7d5694c5f33a9503da446faaa00f1c03` |

## Runtime file inventory and provenance

The fresh runtime inventory reproduces the later Task 8 observation:

```text
Runtime Platform Core files: 81
Runtime tree SHA-256: fcc9839f165341c92745a426d246536d300e2a052647d4983ce8cb37b7f4560e
Historical preflight files: 47
Historical preflight tree SHA-256: aa2011228417894a84c15c28aa6c4b10fc7f441f94b8ced30a1efe95735b6bcc
Historical preflight entrypoint SHA-256: 2a0645e9d0812cf13e27b2b4f3f1ff25c07650c3bd93deba672f21d96f6a71b6
Current runtime entrypoint SHA-256: 7a1b25a840294b029aba48d24e076adfe551ea9dcefc6b983525e7a82e06736d
Current runtime includes/Plugin.php SHA-256: c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8
```

All 79 runtime files other than the two bootstrap files match a tracked file in either the root Platform Core tree or the `site-code` module at PR #39 merge `a9587abf...`. Seventy-seven also match current `main`. This comparison checks bytes, not timestamps. No ordinary runtime class or test file is unique to the runtime.

The table below gives the focused Product and Event boundary evidence. For present files, `first source` is the earliest checked source checkpoint with the exact byte sequence; `latest source` is current `main` at `0216d86a...`. The hashes for present files match both checkpoints. `PersonProductsBricks.php` is absent from runtime; its current source hash is shown for comparison.

| Runtime file | Runtime SHA-256 | Runtime mtime (SAST) | Runtime ctime / birth (SAST) | First matching source checkpoint | Latest matching source checkpoint |
|---|---|---|---|---|---|
| `includes/Event/EventParticipationRepository.php` | `4de51c154b4863546570245cddc91124b2eabb8a6ee166bddfe98c5c4728baff` | `2026-10-23 13:03:03.766` | `2026-10-23 16:42:36.503` | Task 3 head `5e85699d...` | `0216d86a...` |
| `includes/Product/ProductRoleVocabulary.php` | `fc27c8007876eb6ffea5ed2f1509bf349f88b6acd20e742cb2b507e5e07d3bd9` | `2026-10-23 12:08:42.794` | `2026-10-23 16:42:36.505` | Task 2 head `5a2cbd8f...` | `0216d86a...` |
| `includes/Product/ProductPeopleFields.php` | `373ce7592f6f79603fefc95b2c37978d94fef01d106bc8f6e28995c8146ba762` | `2026-10-23 16:42:21.287` | `2026-10-23 16:42:36.505` | Task 5 head `9b3ba8bb...` | `0216d86a...` |
| `includes/Product/ProductContributionRepository.php` | `db8d394d63a1265cfae9fef61bcfa8abe049da375199d647845f69391a5a0af6` | `2026-10-23 13:03:03.768` | `2026-10-23 16:42:36.504` | Task 3 head `5e85699d...` | `0216d86a...` |
| `includes/Product/ProductContributionPairLock.php` | `3da0944a49525f7b041c7738cd6cb789fb3a524d88e6bfb31fb2ba85b498a31f` | `2026-10-23 13:03:03.767` | `2026-10-23 16:42:36.504` | Task 3 head `5e85699d...` | `0216d86a...` |
| `includes/Product/ProductContributionService.php` | `6db9978174b2320bea3efa6ccade1e50db21cd0c6e16e4d279b3208a315d248e` | `2026-10-23 14:55:04.379` | `2026-10-23 16:42:36.504` | Task 4 head `fac48bb5...` | `0216d86a...` |
| `includes/Product/ProductContributionAdmin.php` | `435a270647db590a6f9ccfc2f57e8817dd9f55a03d44c115d847746ab6484074` | `2026-10-23 14:55:04.379` | `2026-10-23 16:42:36.504` | Task 4 head `fac48bb5...` | `0216d86a...` |
| `includes/Product/ProductRoleSeeder.php` | `2b8979dad3eade53b6f5204e06a36621eadbda320ee18efc86b84d166221d4b8` | `2026-10-23 12:08:42.794` | `2026-10-23 16:42:36.505` | Task 2 head `5a2cbd8f...` | `0216d86a...` |
| `includes/Product/ProductPeopleResolver.php` | `27181a44aca8e6a701f367d11497596f74c16ca13b2eb592cf0caf8923c9ff23` | `2026-10-23 16:42:21.287` | `2026-10-23 16:42:36.505` | Task 5 head `9b3ba8bb...` | `0216d86a...` |
| `includes/Product/ProductPeopleBricks.php` | `001967451c55ef4911427a53f0b72a3457ff925298450fc6a4517ff123d18ca7` | `2026-10-22 10:15:05.423` | `2026-10-23 16:42:36.504` | Preflight `bee6925f...` | `0216d86a...` |
| `includes/Product/ProductSourceEventBricks.php` | `9781aac4f6594d0626539f0f7fc488b863b3fe0af4a9e8b34e3604fed1467697` | `2026-10-22 10:15:05.423` | `2026-10-23 16:42:36.505` | Preflight `bee6925f...` | `0216d86a...` |
| `includes/Product/PersonProductsBricks.php` | **absent** | — | — | Task 6 head `35b23b9d...`; source SHA-256 `a7460a1e21d4318a9d3997e52ad9e0d804a155798b1b7992e786b4cb976170e5` | `0216d86a...` |

Two test files in the runtime are exact older source versions, not current-main versions:

| Runtime file | Runtime SHA-256 | Current-main SHA-256 | Latest exact source checkpoint |
|---|---|---|---|
| `tests/ProductPeopleIntegrationTest.php` | `adc34c63172e251aa090d49ef3d7798608b29931cd8a5a99ada3d3785b03aefa` | `4f50e3d053daadadc56dd85dd4b8e5e8c8c8594c96f31c5f5866476e860ece1a` | Task 6 merge `cd6ccd253...` |
| `tests/run-product-contribution-tests.php` | `4f02ac55da9ecc61e401622250f0b02bcde1e091b065b8baa8fd4c5a5d8b7dd3` | `3f7b9bc2e0e2e027fdfe49d2442b70ff07c047e334d29772e7d2974a9aafeb11` | Task 5 merge `a9587abf...` |

The mtime clues in the prompt do not mark runtime creation. `ProductContributionRepository.php` has an mtime at 13:03, 29 seconds after PR #37 merged, but its ctime and birth time are at 16:42:36. The service and admin mtimes similarly follow PR #38, while their ctime/birth time is in the same 16:42 batch. These older mtimes are consistent with a copy that preserved source timestamps. They do not prove the utility used for the October 23 copy.

## Bootstrap reconstruction

For each checkpoint, the tracked root plugin bootstrap files and that checkpoint's `integration.patch` were extracted into a temporary directory. The patch was applied there with `git apply --unidiff-zero`. Both generated PHP files linted under the available PHP 8.5.4 CLI.

| Reconstructed source stage | Entrypoint SHA-256 | `includes/Plugin.php` SHA-256 | PHP lint |
|---|---|---|---|
| Post Task 3, merge `10231ece...` | `2a0645e9d0812cf13e27b2b4f3f1ff25c07650c3bd93deba672f21d96f6a71b6` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` | both pass |
| Post Task 4, merge `1a00e118...` | `2a0645e9d0812cf13e27b2b4f3f1ff25c07650c3bd93deba672f21d96f6a71b6` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` | both pass |
| Post Task 5, merge `a9587abf...` | `6894b2a7a0564e603e8856207f164cbc604029f04b60767309184c96328e69a9` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` | both pass |
| Post Task 6, merge `cd6ccd25...` | `6894b2a7a0564e603e8856207f164cbc604029f04b60767309184c96328e69a9` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` | both pass |
| Post Task 7, merge `be713383...` | `1902e09145d0c29994a3902f19d59a62cf7c94f121e991dd83d128afc1dd68c0` | `054b373bc4512cadf7e4f9700347867dc90ca359527770b0ecf3a3a825512625` | both pass |
| Current main, merge `0216d86a...` | `3fc688ccf27ec11ee4bb9fe5e282630ded724d4a987e5489f9bc77142dca72bb` | `054b373bc4512cadf7e4f9700347867dc90ca359527770b0ecf3a3a825512625` | both pass |
| Runtime | `7a1b25a840294b029aba48d24e076adfe551ea9dcefc6b983525e7a82e06736d` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` | both pass |

The runtime `includes/Plugin.php` is the pre-Task 7 generated target. It does not import or register `ProductContributionAdmin`, `ProductRoleSeeder`, or `PersonProductsBricks`. The Task 7 target does. The runtime entrypoint has duplicated integration include lines and does not match any clean reconstructed stage. Applying the PR #39 entrypoint hunk a second time to the preflight-generated entrypoint in a temporary directory reproduces the runtime hash `7a1b25a8...` exactly and passes `php -l`. This establishes a reproducible source transformation, not who applied it or which command did so.

The active bootstrap is therefore a mixed stage. It combines an entrypoint with a repeated PR #39 patch and a Plugin class from the older Task 5 stage. It is not the Task 7 bootstrap or the current-main target.

## Runtime change timeline and mechanism findings

| Time | Evidence | Finding |
|---|---|---|
| 2026-10-23 13:02:34 SAST | PR #37 merged at `11:02:34Z`. Runtime `ProductContributionRepository.php` mtime is `13:03:03.768` SAST. | The mtime is close to the merge, but ctime and birth are at `16:42:36.504`. It was not created at 13:03. |
| 2026-10-23 14:54:18 SAST | PR #38 merged at `12:54:18Z`. Runtime contribution Service/Admin mtimes are `14:55:04.379`. | Their ctime and birth are both at `16:42:36.504`, not 14:55. |
| 2026-10-23 16:41:36 SAST | PR #39 merged at `14:41:36Z`. | Reviewed source merge. |
| 2026-10-23 16:42:21 SAST | Root checkout reflog records `pull origin main: Fast-forward` to `a9587abf...`. | The repository source checkout advanced to PR #39 merge 15 seconds before the runtime batch began. |
| 2026-10-23 16:42:36.501–16:42:36.610 SAST | 49 runtime files have matching birth/change times. Forty-eight non-bootstrap files match the PR #39 tree; the entrypoint hash is `7a1b25a8...`. | Strongly supports a post-merge filesystem deployment from the PR #39 source state. No exact command, actor, hook, or external sync event was found for this batch. |
| 2026-10-24 09:36:46 SAST | PR #42 merged at `07:36:46Z`, advancing `origin/main` to Task 7. | The root checkout used by the later Task 8 command remained at PR #39. |
| 2026-10-24 09:42:56.839 SAST | Codex/T3 Code session `01a16cd8-f498-73e0-9a0a-fdcc7b6097a8`, tool call `call_e36dea99a8a74214bbf8dffcf202c17f`. | Direct filesystem write into the active runtime, before Task 8 later discovered the anomalous state. |
| 2026-10-24 09:42:56.910–09:42:56.919 SAST | Nineteen files have matching birth/change times. | Eighteen copied shared source files plus `includes/Plugin.php` match the Task 8 command's targets and output. |
| 2026-10-24 09:52:14 SAST | PR #43 merged at `07:52:14Z`. | PHP 8.5 contract merged after the Task 8 file write. PR #43 did not update the runtime PHP version. |
| 2026-10-24 10:22:19 SAST | PR #44 merged at `08:22:19Z`. | Editorial test-only change; current audit baseline is `0216d86a...`. |
| 2026-10-24 11:05:54 SAST | PR #45 merged at `09:05:54Z` as `2669f0a08c81340625c469491311587fff1d7df2`. | CI, source-check script, and plan only; no runtime or database change. This moved `origin/main` during Task 8A. |

The recorded October 24 Task 8 command staged the root plugin entrypoint and `includes/Plugin.php` in `/tmp/vgnova-platform-core-restore.ojceQ1`, applied the root checkout's `integration.patch`, and linted the staged `Plugin.php`. It then copied `includes/Bricks`, `Config`, `Contracts`, `Dashboard`, `Performance`, and `Services` with `cp -a`, and installed the staged `includes/Plugin.php` into the runtime. The command's output confirms success. Its guards required those six runtime directories and runtime `includes/Plugin.php` to be absent before copying. It did not install the staged root entrypoint, so the runtime entrypoint stayed at `7a1b25a8...`.

The same session first listed the configured Novamira sites; both `vgnova` and `localhost` resolved to `http://localhost:10039/`. A read-only `novamira --site vgnova doctor --json` then returned `status: fail`. The subsequent write used the literal VGNova filesystem root `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`, which is the expected 10039 installation. The available command log does not show a completed pre-write identity check for `ABSPATH`, home/site URL, DB name, PHP/WordPress/plugin versions, nor a rollback snapshot. The target directories and `includes/Plugin.php` were checked absent before copying, so the 19 files were additions rather than overwrites. No request to port 10049 appears in the logged sequence. This establishes which filesystem path the command targeted, but it does not establish that the Task 8 identity gate was completed before the write.

That command explains the 19-file October 24 birth-time group. It is not the source of the 49-file October 23 group. Its source was the root checkout at PR #39, not the then-current Task 8 baseline after PR #42. The operation changed runtime files but did not call WordPress, ACF, or the database.

## Hooks, automation, shell history, and editor logs

- `core.hooksPath` is not configured. The standard `post-merge`, `post-checkout`, `post-rewrite`, and `post-commit` hook files are absent in the current repository hook directory. This does not prove that historical hook state was identical.
- Tracked repository automation contains no command that copies Platform Core into the local WordPress plugin path. Search results were limited to documentation and tests that inspect `integration.patch`.
- No matching VGNova/Platform Core service or timer appeared in systemd user units, system unit files, or cron configuration.
- Seventy-six inotify file descriptors existed during inspection. Three watches from `dolphin` covered `/home/jcschoeman96/projects`, `/home/jcschoeman96/projects/current`, and the repository root. None covered the active runtime plugin tree, the tracked root plugin tree, the `site-code` module, ACF JSON directory, or runtime parent paths. No matching active writer process name appeared in the focused process scan.
- The available `.bash_history` has no timestamps and no direct VGNova runtime-path, Product Contribution, `integration.patch`, `rsync`, or target-copy command. Generic copy commands in it cannot be tied to these changes.
- No relevant Cursor log file was available under `~/.cursor/logs`; the existing `~/.config/Cursor/logs` had no matching entries. The local Codex session logs contain the October 24 Task 8 write described above. No matching VGNova Codex deployment call was found around the October 23 16:42 batch.
- The connected VGNova interface exposes no server-side file-change or command audit log for the October 23 event.

These results do not exclude an unlogged manual copy, an IDE action outside the available logs, a historical hook that was later removed, or an external sync process. Therefore `ONGOING_AUTOMATIC_WRITER=UNKNOWN`.

## Database and ACF schema state

Fresh read-only ACF API and database reads found:

| Surface | Current result |
|---|---|
| `product_contribution` posts | `0` |
| `person` posts | `0` |
| `event_participation` posts | `0` |
| Products | 20 total: 9 published, 7 draft, 1 private, 3 trashed |
| `product_role` terms / relationships | `0 / 0` |
| `event_role` terms / relationships | `4 / 0` |
| `product_people_mode` rows | `0` |
| `product_people_override` rows | `0` |
| `_product_people_override` rows | `0` |
| Product Contribution ACF database records | `0` in `acf-field-group`, `acf-field`, `acf-post-type`, and `acf-taxonomy` |

The three target definitions are active from Local JSON and have no matching database ACF records:

- `post_type_product_contribution`: source `json`, active, public `false`.
- `taxonomy_product_role`: source `json`, active, attached to `product_contribution`.
- `group_vg_product_contribution_details`: source `json`, active, ID `0`, four fields.

The unrelated JSON-only field groups `group_vgmp_perk_details` and `group_vgmp_options` also remain pending. No global or targeted ACF sync occurred in this audit or in the identified October 24 shell command.

The PHP-local Product People group `group_vg_product_people` currently has one field, `product_people_mode`. The preflight recorded the old two-field PHP-local group, including the retired override field, with schema SHA-256 `eb2ca243eb89871febe0587b57c768e3291cb489e51ad0dc18f85d416bbceae4`. Task 5 source removed the override field and made the resolver use the canonical repository. This is a runtime PHP definition change, not a persistent ACF database schema update. No historical override meta was deleted or migrated; the current count is zero.

The Event Participation ACF group remains database/JSON ID `1263`, with four fields and schema SHA-256 `d89dec703bb5702fed069dfa9a4c6cca4d8dd3d837b45fa2504cbc762c4f5680`. No Product Role terms were seeded, and no Product Contribution records were created.

## Protected state comparison

Fresh Task 8A reads reproduce the hashes in `PRODUCT_CONTRIBUTION_PREFLIGHT.md` exactly. Post count, metadata, and term relationship hashes are shown for the same protected IDs. The empty relationship collections use the preflight empty-set digest.

| Protected surface | Preflight SHA-256 | Current SHA-256 | Result |
|---|---|---|---|
| Profiles 753–757 posts | `6cb107bcb3a70ed61526caff114e5f7771d838905e1ebf4de2bf207e894ba420` | same | unchanged |
| Profiles 753–757 postmeta | `3a5bfb948796c6ba11ad98c50d4de8d1eabcdd2db73d6fc94abfe15203f24e5c` | same | unchanged |
| Profiles 753–757 term relationships | `37ef040584ca77a139e611763910d7700ec9e9b05a50d63241f871817c489ae3` | same | unchanged |
| Event 763 post | `be163f4a063ede077b98c9c1b24cc3b5cc519fdbf559e40a47ff270013e1ea6b` | same | unchanged |
| Event 763 postmeta | `f7ca21d464725e1d20573954880fcc13084207274b1618eb011e420e2e8a9a99` | same | unchanged |
| Event 763 term relationships | `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` | same | unchanged |
| Products 765, 767, 768 posts | `97a956b5881cd58aeef2c9765e4080692a968bc3b25e1266acfd53cc5740630a` | same | unchanged |
| Products 765, 767, 768 postmeta | `7e95f2f96d0d4a2357a199acab176a01b91209240acb8fb16f1b4ab45c6e8934` | same | unchanged |
| Products 765, 767, 768 term relationships | `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` | same | unchanged |
| Bricks templates 773, 1127 posts | `7f2c9eb0c0918d5ff4cbdae818840c7b0b8f2bb8e8bbe33ce421e6399911dc81` | same | unchanged |
| Bricks templates 773, 1127 postmeta | `6b3cd25ddde6b06f1748900a65612ae530f2c10163834a6ea2844df52f110e03` | same | unchanged |
| Bricks templates 773, 1127 relationships | `35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3` | same | unchanged |
| Bricks component `6b463f` option row | `55beb9ae2508c235362bc850d6f11954f0634d1bace3d8a59e3869c750b7d004` | same | unchanged |
| Bricks component `6b463f` component record | `57370bbb83d3125758eba9a5194eb1a898b8914b546ee4d84b32f642e07aeaac` | same | unchanged |
| FluentSnippets 9 | `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70` | same | unchanged |
| FluentSnippets 10 | `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2` | same | unchanged |
| Event Participation ACF group | `d89dec703bb5702fed069dfa9a4c6cca4d8dd3d837b45fa2504cbc762c4f5680` | same | unchanged |

The audit did not hash all `wp_options`; volatile options were not used as the protected-state gate. No unexplained protected database or legacy relationship difference was found.

## Provenance classifications and baseline decision

| Runtime item | Classification | Evidence |
|---|---|---|
| 79 non-bootstrap runtime files | A | Every file exactly matches a tracked root plugin or module file in merged source: the PR #39 snapshot `a9587abf...`, with two test files retaining exact earlier merged versions. Seventy-seven also match current `main`. Exact-head approval comments exist for PRs #36–#39; GitHub's formal review arrays are empty. No unmatched ordinary runtime file was found. |
| `includes/Plugin.php` | A, historical generated stage | SHA `c50ad78a...` matches the pre-Task 7 generated bootstrap from the preflight through PR #39 source state. It is not the Task 7/current-main target. |
| `voelgoed-platform-core.php` | E | SHA `7a1b25a8...` is a duplicate-application result, not a clean historical checkpoint. The repeated patch is reproducible, but the October 23 actor/command is unknown. |
| `includes/Product/PersonProductsBricks.php` | absent | Current merged source has the file at SHA `a7460a1e...`; the runtime copy is missing. |

`PROVENANCE_PARTIAL` applies because the bytes and source stage are identified, and the October 24 writer is identified, but the writer for the October 23 batch is not. The runtime is not a safe baseline candidate. The entrypoint is not a reviewed generated target, `includes/Plugin.php` is stale relative to Task 7, `PersonProductsBricks.php` is absent, and the runtime header does not reflect the current PHP 8.5 source contract.

`RUNTIME_BASELINE_CANDIDATE=NO` is an audit finding, not an authorization to repair or redeploy. The Master Programme Gate and independent review must decide whether to adopt, restore, or investigate further. Task 8 remains blocked until that decision and a new baseline verification.

## Scope and unresolved questions

- Task 8A performed zero WordPress writes and zero runtime file writes.
- No plugin was activated/deactivated. No ACF sync, role seeding, migration, cache flush, or data mutation occurred.
- The October 24 pre-discovery Task 8 shell command wrote 19 runtime files. It did not write to WordPress or the database.
- The exact October 23 command and actor remain unknown. The source is the PR #39 merge tree, and the local main fast-forward 15 seconds before the runtime creation batch is strong timeline evidence.
- No current automatic writer was found for the target paths, but past or external mechanisms cannot be ruled out.
- PR #45 merged during Task 8A with CI/source-check/plan changes only. The audit branch is rebased onto its merge commit before the provenance PR is opened. Task 9 was not started.
- At the time of this audit, no `PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json` had been created.


## Appendix A. Full runtime file metadata

The active plugin tree contains 81 regular files and no symlink files. The table records relative path, size, SHA-256, modification time, change time, birth time, owner, mode, inode, device, file type, and symlink target. Timestamps include UTC and `Africa/Johannesburg` (+02:00). `none` in the symlink-target column means the entry is a regular file.

```text
relative_path	size	sha256	mtime_utc	mtime_sast	ctime_utc	ctime_sast	birth_utc	birth_sast	uid	gid	mode	inode	device	type	symlink_target
bin/check.sh	2816	fa75e33b86dc16a8b9ad180319703aaa70376623ac50d1751c2181fe8de8c32f	2026-10-14T12:49:26.630090+00:00	2026-10-14T14:49:26.630090+02:00	2026-10-14T12:49:26.630101+00:00	2026-10-14T14:49:26.630101+02:00	2026-10-14T12:49:26.630051+00:00	2026-10-14T14:49:26.630051+02:00	1000	1000	0o644	112985941	66306	file	none
includes/Bricks/DashboardDynamicData.php	13294	78b5e8889dfba308cbe359736c19e3e1da78718df1ff169dfc56c4512e6b3c8f	2026-10-15T06:48:50.306556+00:00	2026-10-15T08:48:50.306556+02:00	2026-10-24T07:42:56.911138+00:00	2026-10-24T09:42:56.911138+02:00	2026-10-24T07:42:56.910444+00:00	2026-10-24T09:42:56.910444+02:00	1000	1000	0o664	113021958	66306	file	none
includes/Bricks/DashboardQueryProvider.php	14434	19e488cf222605aeb78337910feac2a7d388173ae2723705946b01ebe8d817ba	2026-10-15T06:48:50.306776+00:00	2026-10-15T08:48:50.306776+02:00	2026-10-24T07:42:56.911187+00:00	2026-10-24T09:42:56.911187+02:00	2026-10-24T07:42:56.911138+00:00	2026-10-24T09:42:56.911138+02:00	1000	1000	0o664	113021966	66306	file	none
includes/Config/AfrikaansLabels.php	2316	85ff7693c406d420d8f83f44772a41e0d0c5651daa8de5d9b589351837a6fc36	2026-10-15T06:48:50.306776+00:00	2026-10-15T08:48:50.306776+02:00	2026-10-24T07:42:56.912756+00:00	2026-10-24T09:42:56.912756+02:00	2026-10-24T07:42:56.911444+00:00	2026-10-24T09:42:56.911444+02:00	1000	1000	0o664	113021978	66306	file	none
includes/Contracts/DashboardAction.php	4177	a1a6469f6aaf21a3324ccfd22870dad34ec6e9cb0e3f5203857be311fb0d2905	2026-10-15T06:48:50.306776+00:00	2026-10-15T08:48:50.306776+02:00	2026-10-24T07:42:56.914015+00:00	2026-10-24T09:42:56.914015+02:00	2026-10-24T07:42:56.912756+00:00	2026-10-24T09:42:56.912756+02:00	1000	1000	0o664	113021980	66306	file	none
includes/Contracts/DashboardCard.php	4123	bed4565c9883328129b4f2dcccb66655fbceae37a97c74af9ed8c227cef73cec	2026-10-15T06:48:50.307413+00:00	2026-10-15T08:48:50.307413+02:00	2026-10-24T07:42:56.914074+00:00	2026-10-24T09:42:56.914074+02:00	2026-10-24T07:42:56.914014+00:00	2026-10-24T09:42:56.914014+02:00	1000	1000	0o664	113021981	66306	file	none
includes/Contracts/DashboardServiceInterface.php	302	fec05df907a648931a90ec2a25d1c68061eec7a80a224afdb40805c56dc17702	2026-10-15T06:48:50.307413+00:00	2026-10-15T08:48:50.307413+02:00	2026-10-24T07:42:56.914123+00:00	2026-10-24T09:42:56.914123+02:00	2026-10-24T07:42:56.914073+00:00	2026-10-24T09:42:56.914073+02:00	1000	1000	0o664	113021983	66306	file	none
includes/Dashboard/AccessGuard.php	8246	77b55994203e269643ec4101e95ba06c42fc5aa286310813127312d43e1f64ab	2026-10-15T06:48:50.307413+00:00	2026-10-15T08:48:50.307413+02:00	2026-10-24T07:42:56.915357+00:00	2026-10-24T09:42:56.915357+02:00	2026-10-24T07:42:56.914444+00:00	2026-10-24T09:42:56.914444+02:00	1000	1000	0o664	113021992	66306	file	none
includes/Dashboard/DashboardContext.php	3796	572534a6a72aa7bb415f376d66fb0597a9d80e57369d609fef8fa0fd20ccae67	2026-10-15T06:48:50.307928+00:00	2026-10-15T08:48:50.307928+02:00	2026-10-24T07:42:56.915420+00:00	2026-10-24T09:42:56.915420+02:00	2026-10-24T07:42:56.915357+00:00	2026-10-24T09:42:56.915357+02:00	1000	1000	0o664	113021994	66306	file	none
includes/Dashboard/DashboardRegistry.php	10866	afe5a0e0d35cf02151493459742c86eae6ce435188603c0b330c4723df850e88	2026-10-15T06:48:50.307928+00:00	2026-10-15T08:48:50.307928+02:00	2026-10-24T07:42:56.915473+00:00	2026-10-24T09:42:56.915473+02:00	2026-10-24T07:42:56.915420+00:00	2026-10-24T09:42:56.915420+02:00	1000	1000	0o664	113021998	66306	file	none
includes/Dashboard/DashboardRoutes.php	2275	42bf035569292175272216a6d5f1d6d0bdf3fbd2bb8975e15835bc05c6fb8d87	2026-10-15T06:48:50.307928+00:00	2026-10-15T08:48:50.307928+02:00	2026-10-24T07:42:56.915528+00:00	2026-10-24T09:42:56.915528+02:00	2026-10-24T07:42:56.915473+00:00	2026-10-24T09:42:56.915473+02:00	1000	1000	0o664	113022002	66306	file	none
includes/Dashboard/Navigation.php	6512	a8f945933987854a30cf85a57816fc3760d1b78d15028bf95b7d249f73d97050	2026-10-15T06:48:50.307928+00:00	2026-10-15T08:48:50.307928+02:00	2026-10-24T07:42:56.915576+00:00	2026-10-24T09:42:56.915576+02:00	2026-10-24T07:42:56.915528+00:00	2026-10-24T09:42:56.915528+02:00	1000	1000	0o664	113022005	66306	file	none
includes/Event/EventParticipationAdmin.php	10816	bbdbea299e6712f84d574ea5d5a25c517f2917920d36858f6bf8bf92b5e3b9f9	2026-10-18T15:52:32.849277+00:00	2026-10-18T17:52:32.849277+02:00	2026-10-23T14:42:36.502192+00:00	2026-10-23T16:42:36.502192+02:00	2026-10-23T14:42:36.501257+00:00	2026-10-23T16:42:36.501257+02:00	1000	1000	0o664	116827816	66306	file	none
includes/Event/EventParticipationBricks.php	4865	1adee948ecb9a53f762e7f9de62bf721e1bc46c9f8d4e3f093f9fd26a9c87c63	2026-10-19T15:39:02.882495+00:00	2026-10-19T17:39:02.882495+02:00	2026-10-23T14:42:36.503013+00:00	2026-10-23T16:42:36.503013+02:00	2026-10-23T14:42:36.502257+00:00	2026-10-23T16:42:36.502257+02:00	1000	1000	0o664	116795289	66306	file	none
includes/Event/EventParticipationPairLock.php	4298	95107d2db6c3e713c6685cd0c13aa7fe4764f89b7ac37596843e436f74e9c49c	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.503309+00:00	2026-10-23T16:42:36.503309+02:00	2026-10-23T14:42:36.503013+00:00	2026-10-23T16:42:36.503013+02:00	1000	1000	0o664	116791903	66306	file	none
includes/Event/EventParticipationRepository.php	14214	4de51c154b4863546570245cddc91124b2eabb8a6ee166bddfe98c5c4728baff	2026-10-23T11:03:03.766359+00:00	2026-10-23T13:03:03.766359+02:00	2026-10-23T14:42:36.503524+00:00	2026-10-23T16:42:36.503524+02:00	2026-10-23T14:42:36.503309+00:00	2026-10-23T16:42:36.503309+02:00	1000	1000	0o664	116791921	66306	file	none
includes/Event/EventParticipationService.php	9725	4075158d9a6ab4b4079f5722bb9a2736190eb779dfd2ae1282e9c9bf74362600	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.503891+00:00	2026-10-23T16:42:36.503891+02:00	2026-10-23T14:42:36.503524+00:00	2026-10-23T16:42:36.503524+02:00	1000	1000	0o664	116824087	66306	file	none
includes/Event/EventRoleSeeder.php	8704	9b0396bf9b2e4b3515fb0b2bd5224e03e501fd964efe80e03cd3f1a76f98c4e6	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.504047+00:00	2026-10-23T16:42:36.504047+02:00	2026-10-23T14:42:36.503890+00:00	2026-10-23T16:42:36.503890+02:00	1000	1000	0o664	116824089	66306	file	none
includes/Performance/CachePolicy.php	7172	48de79acd06ce3a9b28596ec5324c1ea7d5217c0b90d10b7f3260b8154826057	2026-10-15T06:48:50.307928+00:00	2026-10-15T08:48:50.307928+02:00	2026-10-24T07:42:56.917071+00:00	2026-10-24T09:42:56.917071+02:00	2026-10-24T07:42:56.915575+00:00	2026-10-24T09:42:56.915575+02:00	1000	1000	0o664	113022027	66306	file	none
includes/Plugin.php	3715	c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8	2026-10-24T07:42:56.919445+00:00	2026-10-24T09:42:56.919445+02:00	2026-10-24T07:42:56.919445+00:00	2026-10-24T09:42:56.919445+02:00	2026-10-24T07:42:56.919444+00:00	2026-10-24T09:42:56.919444+02:00	1000	1000	0o644	113022061	66306	file	none
includes/Product/ProductContributionAdmin.php	27954	435a270647db590a6f9ccfc2f57e8817dd9f55a03d44c115d847746ab6484074	2026-10-23T12:55:04.379201+00:00	2026-10-23T14:55:04.379201+02:00	2026-10-23T14:42:36.504178+00:00	2026-10-23T16:42:36.504178+02:00	2026-10-23T14:42:36.504047+00:00	2026-10-23T16:42:36.504047+02:00	1000	1000	0o664	8955557	66306	file	none
includes/Product/ProductContributionPairLock.php	4547	3da0944a49525f7b041c7738cd6cb789fb3a524d88e6bfb31fb2ba85b498a31f	2026-10-23T11:03:03.767359+00:00	2026-10-23T13:03:03.767359+02:00	2026-10-23T14:42:36.504222+00:00	2026-10-23T16:42:36.504222+02:00	2026-10-23T14:42:36.504177+00:00	2026-10-23T16:42:36.504177+02:00	1000	1000	0o664	8955558	66306	file	none
includes/Product/ProductContributionRepository.php	25790	db8d394d63a1265cfae9fef61bcfa8abe049da375199d647845f69391a5a0af6	2026-10-23T11:03:03.768321+00:00	2026-10-23T13:03:03.768321+02:00	2026-10-23T14:42:36.504287+00:00	2026-10-23T16:42:36.504287+02:00	2026-10-23T14:42:36.504222+00:00	2026-10-23T16:42:36.504222+02:00	1000	1000	0o664	8955559	66306	file	none
includes/Product/ProductContributionService.php	17377	6db9978174b2320bea3efa6ccade1e50db21cd0c6e16e4d279b3208a315d248e	2026-10-23T12:55:04.379201+00:00	2026-10-23T14:55:04.379201+02:00	2026-10-23T14:42:36.504342+00:00	2026-10-23T16:42:36.504342+02:00	2026-10-23T14:42:36.504287+00:00	2026-10-23T16:42:36.504287+02:00	1000	1000	0o664	8955560	66306	file	none
includes/Product/ProductPeopleBricks.php	4499	001967451c55ef4911427a53f0b72a3457ff925298450fc6a4517ff123d18ca7	2026-10-22T08:15:05.422577+00:00	2026-10-22T10:15:05.422577+02:00	2026-10-23T14:42:36.504387+00:00	2026-10-23T16:42:36.504387+02:00	2026-10-23T14:42:36.504342+00:00	2026-10-23T16:42:36.504342+02:00	1000	1000	0o664	8955561	66306	file	none
includes/Product/ProductPeopleFields.php	2367	373ce7592f6f79603fefc95b2c37978d94fef01d106bc8f6e28995c8146ba762	2026-10-23T14:42:21.287133+00:00	2026-10-23T16:42:21.287133+02:00	2026-10-23T14:42:36.504867+00:00	2026-10-23T16:42:36.504867+02:00	2026-10-23T14:42:36.504386+00:00	2026-10-23T16:42:36.504386+02:00	1000	1000	0o664	8915518	66306	file	none
includes/Product/ProductPeopleResolver.php	1301	27181a44aca8e6a701f367d11497596f74c16ca13b2eb592cf0caf8923c9ff23	2026-10-23T14:42:21.287133+00:00	2026-10-23T16:42:21.287133+02:00	2026-10-23T14:42:36.505124+00:00	2026-10-23T16:42:36.505124+02:00	2026-10-23T14:42:36.504866+00:00	2026-10-23T16:42:36.504866+02:00	1000	1000	0o664	8951094	66306	file	none
includes/Product/ProductRoleSeeder.php	8588	2b8979dad3eade53b6f5204e06a36621eadbda320ee18efc86b84d166221d4b8	2026-10-23T10:08:42.793640+00:00	2026-10-23T12:08:42.793640+02:00	2026-10-23T14:42:36.505331+00:00	2026-10-23T16:42:36.505331+02:00	2026-10-23T14:42:36.505124+00:00	2026-10-23T16:42:36.505124+02:00	1000	1000	0o664	8951095	66306	file	none
includes/Product/ProductRoleVocabulary.php	1180	fc27c8007876eb6ffea5ed2f1509bf349f88b6acd20e742cb2b507e5e07d3bd9	2026-10-23T10:08:42.793924+00:00	2026-10-23T12:08:42.793924+02:00	2026-10-23T14:42:36.505372+00:00	2026-10-23T16:42:36.505372+02:00	2026-10-23T14:42:36.505330+00:00	2026-10-23T16:42:36.505330+02:00	1000	1000	0o664	8955562	66306	file	none
includes/Product/ProductSourceEventBricks.php	4205	9781aac4f6594d0626539f0f7fc488b863b3fe0af4a9e8b34e3604fed1467697	2026-10-22T08:15:05.422577+00:00	2026-10-22T10:15:05.422577+02:00	2026-10-23T14:42:36.505413+00:00	2026-10-23T16:42:36.505413+02:00	2026-10-23T14:42:36.505371+00:00	2026-10-23T16:42:36.505371+02:00	1000	1000	0o664	8955563	66306	file	none
includes/Services/CommerceService.php	13828	c3fc99ef8f3b224e5921ff708080c680342504a2bbdab5c8572da5ea5703e9e9	2026-10-15T06:48:50.307928+00:00	2026-10-15T08:48:50.307928+02:00	2026-10-24T07:42:56.918293+00:00	2026-10-24T09:42:56.918293+02:00	2026-10-24T07:42:56.917444+00:00	2026-10-24T09:42:56.917444+02:00	1000	1000	0o664	113022032	66306	file	none
includes/Services/LearningService.php	34709	b7156f25406f15e8caf8a250961742a2f6514ce68762d609092b3d06d20f0338	2026-10-15T06:48:50.309085+00:00	2026-10-15T08:48:50.309085+02:00	2026-10-24T07:42:56.918355+00:00	2026-10-24T09:42:56.918355+02:00	2026-10-24T07:42:56.918293+00:00	2026-10-24T09:42:56.918293+02:00	1000	1000	0o664	113022040	66306	file	none
includes/Services/MemberAreaService.php	9335	9a5bf000eacb1d6eda74a24c1a0dcb86bfaf044dc128e69e0be7a21e7e0e495d	2026-10-15T06:48:50.309085+00:00	2026-10-15T08:48:50.309085+02:00	2026-10-24T07:42:56.918400+00:00	2026-10-24T09:42:56.918400+02:00	2026-10-24T07:42:56.918354+00:00	2026-10-24T09:42:56.918354+02:00	1000	1000	0o664	113022041	66306	file	none
includes/Services/MembershipService.php	13154	5355ecdfa921a4b7da306f6384649c7387d1c5fbd58be6425d209820c9a04fe6	2026-10-15T06:48:50.309085+00:00	2026-10-15T08:48:50.309085+02:00	2026-10-24T07:42:56.918443+00:00	2026-10-24T09:42:56.918443+02:00	2026-10-24T07:42:56.918399+00:00	2026-10-24T09:42:56.918399+02:00	1000	1000	0o664	113022042	66306	file	none
includes/Services/SubscriptionService.php	16835	70cfa98752f37c60045ef3ca2fc10ff50992e826d0e8a792116eb96c08098f4f	2026-10-15T06:48:50.309085+00:00	2026-10-15T08:48:50.309085+02:00	2026-10-24T07:42:56.918485+00:00	2026-10-24T09:42:56.918485+02:00	2026-10-24T07:42:56.918442+00:00	2026-10-24T09:42:56.918442+02:00	1000	1000	0o664	113022057	66306	file	none
includes/Services/TicketService.php	35903	ddde203376c45859763cfe6f2aaf500cc5bf228f5606c1aaef9707cfc9f19318	2026-10-15T06:48:50.309085+00:00	2026-10-15T08:48:50.309085+02:00	2026-10-24T07:42:56.918524+00:00	2026-10-24T09:42:56.918524+02:00	2026-10-24T07:42:56.918484+00:00	2026-10-24T09:42:56.918484+02:00	1000	1000	0o664	113022060	66306	file	none
includes/Taxonomy/ContentTopicMigration.php	41413	928521996320fbce4db8154e63a217c34a34bfd0bcf397337cc7ee29f271d856	2026-10-15T06:48:50.304031+00:00	2026-10-15T08:48:50.304031+02:00	2026-10-23T14:42:36.505547+00:00	2026-10-23T16:42:36.505547+02:00	2026-10-23T14:42:36.505413+00:00	2026-10-23T16:42:36.505413+02:00	1000	1000	0o664	112984189	66306	file	none
includes/Taxonomy/ContentTopicRoles.php	26630	9241fbaef68ac5318fda72e5daf2c71a5e147d9a3ff30ca97066480de45b6512	2026-10-18T08:11:31.434260+00:00	2026-10-18T10:11:31.434260+02:00	2026-10-23T14:42:36.505741+00:00	2026-10-23T16:42:36.505741+02:00	2026-10-23T14:42:36.505546+00:00	2026-10-23T16:42:36.505546+02:00	1000	1000	0o664	112984190	66306	file	none
includes/Taxonomy/ContentTopicTaxonomy.php	15926	5668c77070bebe2dcaa3984438f5297486ed2963803864abbbb7b92b724048c9	2026-10-18T08:11:31.434260+00:00	2026-10-18T10:11:31.434260+02:00	2026-10-23T14:42:36.506044+00:00	2026-10-23T16:42:36.506044+02:00	2026-10-23T14:42:36.505741+00:00	2026-10-23T16:42:36.505741+02:00	1000	1000	0o664	112984191	66306	file	none
tests/ContentTopicMigrationTest.php	28165	38131b2c37ca2b70e1666f718f17d93e6d587b6a5e3c24d0831bdb972cbb2e92	2026-10-15T06:48:50.304421+00:00	2026-10-15T08:48:50.304421+02:00	2026-10-23T14:42:36.550854+00:00	2026-10-23T16:42:36.550854+02:00	2026-10-23T14:42:36.550258+00:00	2026-10-23T16:42:36.550258+02:00	1000	1000	0o664	112984192	66306	file	none
tests/ContentTopicRolesTest.php	36372	aeb7c9d85fc146e84354ac8aaba9ebba69a435bf285f055bf4894b92a7e8e31a	2026-10-18T08:11:31.435141+00:00	2026-10-18T10:11:31.435141+02:00	2026-10-23T14:42:36.550954+00:00	2026-10-23T16:42:36.550954+02:00	2026-10-23T14:42:36.550853+00:00	2026-10-23T16:42:36.550853+02:00	1000	1000	0o664	112984193	66306	file	none
tests/ContentTopicTaxonomyTest.php	16149	e485ed1f655cc7f2df62bbeeedf8c12f2d1e75eb9f765df050ff7d92f53a1d54	2026-10-18T08:11:31.435141+00:00	2026-10-18T10:11:31.435141+02:00	2026-10-23T14:42:36.551013+00:00	2026-10-23T16:42:36.551013+02:00	2026-10-23T14:42:36.550954+00:00	2026-10-23T16:42:36.550954+02:00	1000	1000	0o664	112984194	66306	file	none
tests/EventParticipationAdminTest.php	28993	1a16afbb77ad7aceefd1aefa863c4a125e2a21c6a3b3780e7c9300ec1fc2cb3a	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.551090+00:00	2026-10-23T16:42:36.551090+02:00	2026-10-23T14:42:36.551012+00:00	2026-10-23T16:42:36.551012+02:00	1000	1000	0o664	112985069	66306	file	none
tests/EventParticipationBricksTest.php	10924	10d6d78e5c5527709e1f3e1521f24d468f941ebabb988db1b2b49a1532868a24	2026-10-19T15:39:02.882495+00:00	2026-10-19T17:39:02.882495+02:00	2026-10-23T14:42:36.551157+00:00	2026-10-23T16:42:36.551157+02:00	2026-10-23T14:42:36.551090+00:00	2026-10-23T16:42:36.551090+02:00	1000	1000	0o664	112985383	66306	file	none
tests/EventParticipationRepositoryTest.php	13387	f1264a48e9cae824d33b116a985774c09587fabfa9e726c1e8bd0dd7f952e9d8	2026-10-23T11:03:03.768359+00:00	2026-10-23T13:03:03.768359+02:00	2026-10-23T14:42:36.551222+00:00	2026-10-23T16:42:36.551222+02:00	2026-10-23T14:42:36.551156+00:00	2026-10-23T16:42:36.551156+02:00	1000	1000	0o664	112985385	66306	file	none
tests/EventParticipationServiceTest.php	20858	61c6856dfc12e3f7bc8d7d851748125b2e72955c8488741c0553e2405360f49d	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.551299+00:00	2026-10-23T16:42:36.551299+02:00	2026-10-23T14:42:36.551221+00:00	2026-10-23T16:42:36.551221+02:00	1000	1000	0o664	112985394	66306	file	none
tests/EventRoleSeederTest.php	13320	ec0c58e128871b1e019a99b41548dd1fe009416145efe799d7089cdd91cc4bba	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.551367+00:00	2026-10-23T16:42:36.551367+02:00	2026-10-23T14:42:36.551298+00:00	2026-10-23T16:42:36.551298+02:00	1000	1000	0o664	112985397	66306	file	none
tests/ProductContributionAdminTest.php	65375	ff7a72b33ec3dfea56cb9321a64379e61bac71244c0464759867dfaeb908fa70	2026-10-23T12:55:04.380201+00:00	2026-10-23T14:55:04.380201+02:00	2026-10-23T14:42:36.551479+00:00	2026-10-23T16:42:36.551479+02:00	2026-10-23T14:42:36.551366+00:00	2026-10-23T16:42:36.551366+02:00	1000	1000	0o664	112985398	66306	file	none
tests/ProductContributionPairLockTest.php	6827	0aebd1f6969c3b3853c6721d80ec0f32d1e6df0b5361ab7c917aea35ce50f260	2026-10-23T11:03:03.769359+00:00	2026-10-23T13:03:03.769359+02:00	2026-10-23T14:42:36.551555+00:00	2026-10-23T16:42:36.551555+02:00	2026-10-23T14:42:36.551478+00:00	2026-10-23T16:42:36.551478+02:00	1000	1000	0o664	112985421	66306	file	none
tests/ProductContributionRepositoryTest.php	33012	256b1f9de389734e9417e2c7e1ff19bf16b64751950d908f2f60df0c5444f93c	2026-10-23T14:42:21.288133+00:00	2026-10-23T16:42:21.288133+02:00	2026-10-23T14:42:36.551637+00:00	2026-10-23T16:42:36.551637+02:00	2026-10-23T14:42:36.551555+00:00	2026-10-23T16:42:36.551555+02:00	1000	1000	0o664	112985430	66306	file	none
tests/ProductContributionSchemaTest.php	12346	9099383cacdddce28a4a39fdb5dec4759a02ed7d8400502a822ea8d527a46657	2026-10-23T10:08:42.794606+00:00	2026-10-23T12:08:42.794606+02:00	2026-10-23T14:42:36.551685+00:00	2026-10-23T16:42:36.551685+02:00	2026-10-23T14:42:36.551637+00:00	2026-10-23T16:42:36.551637+02:00	1000	1000	0o664	112985441	66306	file	none
tests/ProductContributionServiceTest.php	34729	18744f60ab30b4ffd4290579db3a4f85eca35cfd9e7b832ef0c6851b0cda32a6	2026-10-23T12:55:04.380201+00:00	2026-10-23T14:55:04.380201+02:00	2026-10-23T14:42:36.551751+00:00	2026-10-23T16:42:36.551751+02:00	2026-10-23T14:42:36.551685+00:00	2026-10-23T16:42:36.551685+02:00	1000	1000	0o664	112985442	66306	file	none
tests/ProductPeopleBricksTest.php	16261	48643d3988b5b5ca1a079c7855ec2171e998ddd524c2c6cdd65488942e6613dc	2026-10-23T14:42:21.288133+00:00	2026-10-23T16:42:21.288133+02:00	2026-10-23T14:42:36.551798+00:00	2026-10-23T16:42:36.551798+02:00	2026-10-23T14:42:36.551750+00:00	2026-10-23T16:42:36.551750+02:00	1000	1000	0o664	112985443	66306	file	none
tests/ProductPeopleFieldsTest.php	6700	0d6f0651277366e429006b45c7eb206a2d7a6c37ed5769e70219ff17fe84414c	2026-10-23T14:42:21.288133+00:00	2026-10-23T16:42:21.288133+02:00	2026-10-23T14:42:36.551839+00:00	2026-10-23T16:42:36.551839+02:00	2026-10-23T14:42:36.551797+00:00	2026-10-23T16:42:36.551797+02:00	1000	1000	0o664	112985444	66306	file	none
tests/ProductPeopleIntegrationTest.php	10767	adc34c63172e251aa090d49ef3d7798608b29931cd8a5a99ada3d3785b03aefa	2026-10-23T14:42:21.288133+00:00	2026-10-23T16:42:21.288133+02:00	2026-10-23T14:42:36.551891+00:00	2026-10-23T16:42:36.551891+02:00	2026-10-23T14:42:36.551839+00:00	2026-10-23T16:42:36.551839+02:00	1000	1000	0o664	112985445	66306	file	none
tests/ProductPeopleQaSideEffectsTest.php	7304	3fa5f6b1c0ea11f2f3dd5e9b10ebcbdc7a1572489b9370f476d3de02659ea0b4	2026-10-20T06:57:52.985383+00:00	2026-10-20T08:57:52.985383+02:00	2026-10-23T14:42:36.551939+00:00	2026-10-23T16:42:36.551939+02:00	2026-10-23T14:42:36.551890+00:00	2026-10-23T16:42:36.551890+02:00	1000	1000	0o664	112985456	66306	file	none
tests/ProductPeopleResolverTest.php	23866	26a9101bfe5756c50bd2f12401b8207956a86fcad2f41803f2b2947c27215129	2026-10-23T14:42:21.288133+00:00	2026-10-23T16:42:21.288133+02:00	2026-10-23T14:42:36.551999+00:00	2026-10-23T16:42:36.551999+02:00	2026-10-23T14:42:36.551939+00:00	2026-10-23T16:42:36.551939+02:00	1000	1000	0o664	112985526	66306	file	none
tests/ProductRoleSeederTest.php	13941	a904a81ef4b6dec9840302a4d0f32824d4db5069443ec658b61deb80519c7b1a	2026-10-23T10:08:42.795170+00:00	2026-10-23T12:08:42.795170+02:00	2026-10-23T14:42:36.552057+00:00	2026-10-23T16:42:36.552057+02:00	2026-10-23T14:42:36.551998+00:00	2026-10-23T16:42:36.551998+02:00	1000	1000	0o664	112985716	66306	file	none
tests/ProductRoleVocabularyTest.php	3562	f7c9f8b848ba67fc4db131dc3be646e9a26f8dcec1aa7470b006a23fdf041819	2026-10-23T10:08:42.795606+00:00	2026-10-23T12:08:42.795606+02:00	2026-10-23T14:42:36.552096+00:00	2026-10-23T16:42:36.552096+02:00	2026-10-23T14:42:36.552056+00:00	2026-10-23T16:42:36.552056+02:00	1000	1000	0o664	112985717	66306	file	none
tests/ProductSingle124QaRequestTest.php	12184	804d38d9d64055c4f63e2670e249972f992ad51fe33e65692af977803be88a9c	2026-10-22T08:15:05.422577+00:00	2026-10-22T10:15:05.422577+02:00	2026-10-23T14:42:36.552146+00:00	2026-10-23T16:42:36.552146+02:00	2026-10-23T14:42:36.552095+00:00	2026-10-23T16:42:36.552095+02:00	1000	1000	0o664	112985718	66306	file	none
tests/ProductSourceEventBricksTest.php	10668	450ba05d822bcffa1c85fad56c51ad925fd776b72fcc942be94151da0123f30e	2026-10-22T08:15:05.422577+00:00	2026-10-22T10:15:05.422577+02:00	2026-10-23T14:42:36.552190+00:00	2026-10-23T16:42:36.552190+02:00	2026-10-23T14:42:36.552146+00:00	2026-10-23T16:42:36.552146+02:00	1000	1000	0o664	112985719	66306	file	none
tests/bootstrap.php	28277	aa978dc66837f061ac6eecf3dfb26f0e49589f252574fb78a533ef38b4c11da0	2026-10-23T11:03:03.770526+00:00	2026-10-23T13:03:03.770526+02:00	2026-10-23T14:42:36.552265+00:00	2026-10-23T16:42:36.552265+02:00	2026-10-23T14:42:36.552189+00:00	2026-10-23T16:42:36.552189+02:00	1000	1000	0o664	112985724	66306	file	none
tests/contracts/DashboardCardTest.php	6992	e624d61733e763e4e8c69edac7b346f71fe4926dc0f4e6fa3fdf68cfd93d8b03	2026-10-14T12:49:26.630451+00:00	2026-10-14T14:49:26.630451+02:00	2026-10-14T12:49:26.630468+00:00	2026-10-14T14:49:26.630468+02:00	2026-10-14T12:49:26.630406+00:00	2026-10-14T14:49:26.630406+02:00	1000	1000	0o644	112986126	66306	file	none
tests/dashboard/AccessGuardTest.php	4466	69151d928c2c9deacc13881c50891283bd8371fab0d7dc228cc561580c470150	2026-10-14T12:49:26.630334+00:00	2026-10-14T14:49:26.630334+02:00	2026-10-14T12:49:26.630348+00:00	2026-10-14T14:49:26.630348+02:00	2026-10-14T12:49:26.630297+00:00	2026-10-14T14:49:26.630297+02:00	1000	1000	0o644	112986015	66306	file	none
tests/dashboard/DashboardRoutesTest.php	3093	44b32e7f36591313e41516817ed85f48d90f2d14eeef400dabe02cfa816dd0b1	2026-10-14T12:49:26.630286+00:00	2026-10-14T14:49:26.630286+02:00	2026-10-14T12:49:26.630297+00:00	2026-10-14T14:49:26.630297+02:00	2026-10-14T12:49:26.630247+00:00	2026-10-14T14:49:26.630247+02:00	1000	1000	0o644	112985957	66306	file	none
tests/dashboard/NavigationTest.php	9037	8edf44b916c8c8a217836b007dace0d8684b4c314f4a64d91cbae60beec934de	2026-10-14T12:49:26.630391+00:00	2026-10-14T14:49:26.630391+02:00	2026-10-14T12:49:26.630406+00:00	2026-10-14T14:49:26.630406+02:00	2026-10-14T12:49:26.630347+00:00	2026-10-14T14:49:26.630347+02:00	1000	1000	0o644	112986112	66306	file	none
tests/performance/CachePolicyTest.php	24739	b813031aa173cdc61b4429843eb7cedcb4b303f3a6b43510275c3990d4f8da7d	2026-10-14T12:49:26.630228+00:00	2026-10-14T14:49:26.630228+02:00	2026-10-14T12:49:26.630248+00:00	2026-10-14T14:49:26.630248+02:00	2026-10-14T12:49:26.630158+00:00	2026-10-14T14:49:26.630158+02:00	1000	1000	0o644	112985946	66306	file	none
tests/run-content-topic-tests.php	782	7ea3a937702ad5fdcdcb660297fe6419aac35a033bbd1b3141fb9178a01e30a3	2026-10-15T06:48:50.305421+00:00	2026-10-15T08:48:50.305421+02:00	2026-10-23T14:42:36.552346+00:00	2026-10-23T16:42:36.552346+02:00	2026-10-23T14:42:36.552265+00:00	2026-10-23T16:42:36.552265+02:00	1000	1000	0o664	112985883	66306	file	none
tests/run-dashboard-tests.php	1706	ebea06cb804228bc8bed7e6a6824a01ac4eb0215bc71d1a145e6eef0b7a386d4	2026-10-14T12:49:26.630932+00:00	2026-10-14T14:49:26.630932+02:00	2026-10-14T12:49:26.630942+00:00	2026-10-14T14:49:26.630942+02:00	2026-10-14T12:49:26.630898+00:00	2026-10-14T14:49:26.630898+02:00	1000	1000	0o644	112986301	66306	file	none
tests/run-event-participation-bricks-tests.php	3031	3cf0da685837e929ffba3bd5a921d6c57ec1658acc016f4f2931d9f7fd3334ea	2026-10-19T15:39:02.882495+00:00	2026-10-19T17:39:02.882495+02:00	2026-10-23T14:42:36.552388+00:00	2026-10-23T16:42:36.552388+02:00	2026-10-23T14:42:36.552345+00:00	2026-10-23T16:42:36.552345+02:00	1000	1000	0o664	112985944	66306	file	none
tests/run-event-participation-tests.php	1936	7b576655d9d151b96683bc8ffc77ff901cc8567db5f3c20099a7dbd709c84589	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.552438+00:00	2026-10-23T16:42:36.552438+02:00	2026-10-23T14:42:36.552387+00:00	2026-10-23T16:42:36.552387+02:00	1000	1000	0o664	112991065	66306	file	none
tests/run-event-role-seeder-tests.php	900	7838058dd649cc1c73397e5aaf078d1db384ae7259d498f9a8d1e49026eab3cd	2026-10-18T15:52:32.849518+00:00	2026-10-18T17:52:32.849518+02:00	2026-10-23T14:42:36.553282+00:00	2026-10-23T16:42:36.553282+02:00	2026-10-23T14:42:36.552437+00:00	2026-10-23T16:42:36.552437+02:00	1000	1000	0o664	113022697	66306	file	none
tests/run-product-contribution-tests.php	2330	4f02ac55da9ecc61e401622250f0b02bcde1e091b065b8baa8fd4c5a5d8b7dd3	2026-10-23T12:55:04.380201+00:00	2026-10-23T14:55:04.380201+02:00	2026-10-23T14:42:36.553325+00:00	2026-10-23T16:42:36.553325+02:00	2026-10-23T14:42:36.553281+00:00	2026-10-23T16:42:36.553281+02:00	1000	1000	0o664	113022699	66306	file	none
tests/run-product-people-tests.php	2598	c4ccc7a7114be1e80a30d614c712a58036acfddf7c6cbc0e55d995039173bb46	2026-10-23T14:42:21.288133+00:00	2026-10-23T16:42:21.288133+02:00	2026-10-23T14:42:36.553367+00:00	2026-10-23T16:42:36.553367+02:00	2026-10-23T14:42:36.553325+00:00	2026-10-23T16:42:36.553325+02:00	1000	1000	0o664	113022705	66306	file	none
tests/services/CommerceServiceTest.php	13823	c1f982c6f6ac420aa7bf50dd3cd37d4907aba0ef921ec6356370a6b01c7aa4a8	2026-10-14T12:49:26.630682+00:00	2026-10-14T14:49:26.630682+02:00	2026-10-14T12:49:26.630708+00:00	2026-10-14T14:49:26.630708+02:00	2026-10-14T12:49:26.630622+00:00	2026-10-14T14:49:26.630622+02:00	1000	1000	0o644	112986130	66306	file	none
tests/services/LearningServiceTest.php	17805	78a37f8778517cf0971e0d1f8a3aae4b94ac44beaf9fbb45b7b0f7bf5cc0eabb	2026-10-14T12:49:26.630524+00:00	2026-10-14T14:49:26.630524+02:00	2026-10-14T12:49:26.630541+00:00	2026-10-14T14:49:26.630541+02:00	2026-10-14T12:49:26.630468+00:00	2026-10-14T14:49:26.630468+02:00	1000	1000	0o644	112986128	66306	file	none
tests/services/MemberAreaServiceTest.php	8466	a4abd9c23fb4a379245fb328173c8b374a958e81c7f18985e4aac9fbf49e168d	2026-10-14T12:49:26.630818+00:00	2026-10-14T14:49:26.630818+02:00	2026-10-14T12:49:26.630833+00:00	2026-10-14T14:49:26.630833+02:00	2026-10-14T12:49:26.630776+00:00	2026-10-14T14:49:26.630776+02:00	1000	1000	0o644	112986298	66306	file	none
tests/services/MembershipServiceTest.php	11750	7e94ee415395ef4b67bfafd796bd32d2b07b566e41f46b3627164d8943a4e7cb	2026-10-14T12:49:26.630759+00:00	2026-10-14T14:49:26.630759+02:00	2026-10-14T12:49:26.630777+00:00	2026-10-14T14:49:26.630777+02:00	2026-10-14T12:49:26.630708+00:00	2026-10-14T14:49:26.630708+02:00	1000	1000	0o644	112986133	66306	file	none
tests/services/SubscriptionServiceTest.php	14730	4721c3ebab6aa91bebf0dd034adab729e7cdcafc0e85d39bb2c4c9ade19407ad	2026-10-14T12:49:26.630882+00:00	2026-10-14T14:49:26.630882+02:00	2026-10-14T12:49:26.630899+00:00	2026-10-14T14:49:26.630899+02:00	2026-10-14T12:49:26.630832+00:00	2026-10-14T14:49:26.630832+02:00	1000	1000	0o644	112986299	66306	file	none
tests/services/TicketServiceTest.php	22594	7cf6e81c7680574cc4a8466497191cc27c24b715d8dc8246e2c4882f91793cf4	2026-10-14T12:49:26.630605+00:00	2026-10-14T14:49:26.630605+02:00	2026-10-14T12:49:26.630623+00:00	2026-10-14T14:49:26.630623+02:00	2026-10-14T12:49:26.630540+00:00	2026-10-14T14:49:26.630540+02:00	1000	1000	0o644	112986129	66306	file	none
voelgoed-platform-core.php	3099	7a1b25a840294b029aba48d24e076adfe551ea9dcefc6b983525e7a82e06736d	2026-10-23T14:42:36.610108+00:00	2026-10-23T16:42:36.610108+02:00	2026-10-23T14:42:36.610129+00:00	2026-10-23T16:42:36.610129+02:00	2026-10-23T14:42:36.609258+00:00	2026-10-23T16:42:36.609258+02:00	1000	1000	0o644	113022717	66306	file	none
```
