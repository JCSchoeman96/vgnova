# VGNova Dedicated ACF Runtime Source Design

**Version:** 0.1.1
**Amends:** 0.1.0; this version supersedes its runtime advancement procedure.
**Status:** APPROVED
**Date:** 2026-10-29
**Repository:** `JCSchoeman96/vgnova`
**Target runtime:** `vgnova-local-10039`

## 1. Intent

Decouple the Local WordPress runtime's ACF Local JSON source from the everyday VGNova development checkout.

The runtime currently loads `site-code/acf-json` through the Bricks Child `acf-json` symlink. Historically that symlink was returned to the normal "stable main checkout" after feature work. That makes runtime safety depend on the normal checkout remaining globally clean, so unrelated development files can block ACF/runtime activation.

The new design keeps the repository as the sole ACF source authority while giving Local WordPress a dedicated, clean, auditable checkout used only for runtime ACF JSON loading.

## 2. Selected approach

The Local runtime reads ACF Local JSON from one immutable runtime-source generation. The repository remains the only source authority. A runtime generation is a detached, clean worktree pinned to one exact approved commit; never advance a generation in place.

Generation 1 was created by this slice at:

```text
ACF_RUNTIME_SOURCE_SHA=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
/home/jcschoeman96/projects/runtime/vgnova-acf-source
```

Leave generation 1 at that path. Do not move it or run `git checkout`, `git reset`, `git pull`, or equivalent updates in it. Its ACF JSON directory remains:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
```

The runtime symlink points to the active generation's `site-code/acf-json`. Future changed generations use a new, never-reused path:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-sources/<full-approved-sha>/site-code/acf-json
```

Future generation changes are built and verified in a new detached worktree before any runtime link change. ACF subtree parity, not whole-repository HEAD equality, determines whether runtime ACF JSON must advance.

## 3. Goals

1. The normal VGNova checkout may be dirty without affecting runtime ACF provenance.
2. Runtime ACF JSON remains sourced only from committed repository files.
3. The runtime source is pinned to an exact reviewed/merged commit.
4. Advancing runtime ACF source is a deliberate, auditable operation.
5. Existing targeted ACF status/dry-run/sync workflow remains unchanged.
6. Unknown or dirty runtime-source state fails closed before ACF synchronization.
7. Feature worktrees remain disposable and are never permanent runtime dependencies.

## 4. Non-goals

This change does not:

- synchronize any ACF group;
- deploy Platform Core;
- create the Person relationship table;
- rebuild or reconcile Person relationships;
- create placeholder data;
- modify Bricks content;
- change ACF field definitions;
- change WordPress business/editorial data;
- authorize runtime ACF editing through wp-admin;
- start Phase 2 Person relationship QA.

It only establishes a safer source path for ACF Local JSON.

## 5. Runtime-source and activation-parity contract

For the active generation, require:

```text
ACF_RUNTIME_SOURCE_SHA=<generation's exact approved SHA>
ACF_RUNTIME_SOURCE_IS_GIT_WORKTREE=YES
ACF_RUNTIME_SOURCE_STATUS=CLEAN
ACF_RUNTIME_SOURCE_DETACHED_HEAD=YES
ACF_RUNTIME_SOURCE_JSON_PATH=<generation path>/site-code/acf-json
ACF_RUNTIME_SYMLINK_TARGET=<active generation path>/site-code/acf-json
ACF_RUNTIME_JSON_PARITY=PASS
```

The runtime generation HEAD must equal `ACF_RUNTIME_SOURCE_SHA`. The symlink must resolve to that generation's ACF JSON directory. The worktree must have no tracked or untracked changes.

For a later WordPress activation, record its fresh exact merged-main SHA separately:

```text
ACTIVATION_SOURCE_SHA=<fresh exact origin/main SHA>
ACF_RUNTIME_SOURCE_SHA=<currently active immutable generation SHA>
ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS
```

The two SHAs may differ. Prove parity by comparing the complete relative path inventory and bytes in `site-code/acf-json` at each Git commit. The Git tree object IDs for those subtrees may be compared first; verify the candidate worktree files against Git objects as well. Do not infer parity from names, timestamps, a clean worktree alone, or matching repository HEADs.

If the ACF JSON tree is unchanged, keep the existing runtime generation and symlink. A documentation-only or other non-ACF commit to `main` does not advance `ACF_RUNTIME_SOURCE_SHA`. If the ACF JSON tree changed, prepare a new generation and follow the guarded generation-switch procedure below.

## 6. Checkout lifecycle

### Generation 1

The current runtime generation is the detached worktree at `/home/jcschoeman96/projects/runtime/vgnova-acf-source`, pinned to `5a3bc4fc89fa61ae8916aa460989f1e8912b473d`. It remains unchanged as generation 1. Its current path is retained for compatibility; no relocation is part of this amendment.

### Future candidate and generation creation

For a future exact approved source SHA:

1. Freshly fetch and record the exact merged `main` SHA.
2. Create a new detached worktree at `/home/jcschoeman96/projects/runtime/vgnova-acf-sources/<full-sha>`; the destination must be absent. If it exists, stop and establish provenance.
3. Verify exact detached HEAD, clean status, complete ACF JSON path inventory, and byte-for-byte parity against that commit's Git objects.
4. Compare the candidate ACF JSON subtree to the active generation's subtree.
5. If unchanged, keep the current generation and symlink. Record `ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS`; do not advance the live source merely to follow `main`.
6. If changed, treat the candidate as a new immutable generation and meet the request-consistency gate in Section 7 before changing the runtime symlink.
7. After a successful switch, retain the previous generation until the new generation's evidence is accepted. Never update or delete a live/previous generation as part of cutover.

A candidate worktree created for comparison is not active runtime source unless the guarded switch succeeds. Do not reuse a generation path for another SHA.

## 7. Generation switch and request consistency

PE-007 governs the filesystem symlink mutation. Its locks serialize approved mutators but do not stop WordPress or PHP requests from reading ACF JSON.

In the installed ACF Pro 6.8.1 source, `wp-content/plugins/advanced-custom-fields-pro/includes/local-json.php`, `ACF_Local_JSON::scan_files()` scans the directory and reads each discovered file (lines 381–410); `include_fields()` later reads those files again by path (lines 302–315). An atomic symlink replacement makes the pointer update indivisible; it does not guarantee that an in-flight request reading multiple files uses one resolved generation for every read.

Before a future generation switch, establish one of these controls and capture its proof:

1. **Request quiescence:** stop admitting every web, admin, cron, queue, and other PHP request that can load ACF JSON; wait for all in-flight PHP/WordPress work to drain; keep admission closed throughout the symlink replacement and read-only post-switch ACF status; then reopen requests.
2. **Request-pinned source root:** independently prove that the runtime resolves one immutable physical generation path once per request and all ACF JSON reads for that request use that same path. Any code needed to provide this behavior requires its own reviewed source change before the switch.

For the installed ACF 6.8.1 loader, request quiescence is the available documented control. If a reliable quiescence window cannot be demonstrated, do not switch generations. A PE-007 session alone is not sufficient.

Once the request-consistency gate is active:

1. record the exact current symlink target and verify the new generation's exact SHA, clean status, and Git-object parity;
2. create and verify a temporary symlink in the runtime link's parent directory;
3. atomically replace the symlink with a same-directory rename while the request gate remains closed;
4. verify lexical and resolved targets, then run only read-only `wp acf json status --type=field-group --detailed` while the gate remains closed;
5. if any check fails, restore the exact old target under PE-007 before reopening requests and stop;
6. retain the old generation until evidence for the new generation is accepted.

Do not run ACF sync as part of a generation switch. The v0.1.1 documentation amendment itself performs no runtime symlink change.

## 8. Source parity

Authority remains:

```text
merged Git source
    ↓
exact activation/source commit
    ↓
verified immutable generation
    ↓
atomic runtime symlink selection
    ↓
read-only ACF status / separately approved targeted sync
```

No edits should originate in an active generation. A dirty active generation is a hard stop; preserve evidence and do not auto-reset, checkout, or clean it. Future source work creates another generation from committed Git objects.

During an activation whose `ACTIVATION_SOURCE_SHA` is newer than the active generation, prove the complete ACF JSON subtree is byte-identical between those commits. This allows documentation or unrelated source commits to advance `main` without advancing the ACF runtime generation.

## 9. Safety rules

### Fail closed

Stop before ACF mutation when any of these occur:

- dedicated worktree missing;
- dirty worktree;
- unexpected generation SHA;
- an attempted in-place checkout/reset/update of an active generation;
- symlink resolves elsewhere;
- expected JSON files missing;
- source/runtime JSON provenance cannot be established;
- future generation switch lacks a verified request-consistency gate;
- runtime source contains uncommitted files;
- an unexpected writer appears to have changed the checkout.

### No collateral cleanup

Do not clean, reset, stash or delete files in the normal development checkout to satisfy runtime preflight.

Do not clean/reset a dirty dedicated runtime checkout until its changes have been inspected and recorded.

### No disposable dependency

The runtime source path must not point at `.worktrees/<feature-name>` after this transition.

## 10. WordPress write behavior

The dedicated checkout remains writable at the filesystem level unless a later hardening design explicitly changes permissions. Treat every active generation as immutable by policy.

However, repository source remains authoritative. Editing governed ACF definitions through wp-admin is not an approved source workflow.

Any unexpected runtime JSON write will dirty the dedicated worktree, which becomes a hard stop for subsequent governed ACF synchronization.

A future hardening slice may make the runtime JSON path read-only if operational testing proves ACF does not require write access for approved runtime behavior.

## 11. Evidence

Create a versioned evidence record for the transition, proposed:

```text
docs/architecture/ACF_RUNTIME_SOURCE_CUTOVER_v1.0.0.md
```

Record:

```text
OLD_ACF_SYMLINK_TARGET=<path>
NEW_ACF_SYMLINK_TARGET=<path>
ACF_RUNTIME_SOURCE_SHA=<sha>
ACF_RUNTIME_SOURCE_STATUS=CLEAN
ACF_RUNTIME_SOURCE_DETACHED_HEAD=YES
ACF_RUNTIME_JSON_PARITY=PASS
ACF_STATUS_READ_ONLY=PASS
ACF_SYNCS_PERFORMED=0
WORDPRESS_DATA_MUTATIONS=0
BRICKS_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
```

Also record the exact JSON inventory/hashes relevant to the transition. For a later activation, include:

```text
ACTIVATION_SOURCE_SHA=<fresh exact main SHA>
ACF_RUNTIME_SOURCE_SHA=<active immutable generation SHA>
ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS|BLOCKED
REQUEST_READ_WINDOW_CONSISTENCY=PASS|BLOCKED|NOT_REQUIRED
PREVIOUS_GENERATION_RETAINED=YES|NO
```

The request-consistency field is required for a future generation switch; it does not retroactively assert a quiescence proof for the initial cutover recorded by this evidence report.

## 12. Relationship to blocked Phase 1 activation

PR #63 remains the historical record that the first runtime activation attempt stopped correctly because the old source checkout was dirty.

Recommended sequence:

```text
PR #63 checks pass
    ↓
independent review / merge PR #63
    ↓
dedicated ACF runtime-source cutover
    ↓
independent evidence review / merge cutover PR
    ↓
restart Person Runtime Activation from Stage 1 under separate authorization
```

The blocked activation is not "continued from Step 5." After the source-path correction, Phase 1 must restart its read-only preflight from the beginning because runtime/repository state may have changed.

## 13. Approaches considered

### A. Clean the normal development checkout

Rejected as the permanent solution. It couples runtime safety to unrelated developer activity and encourages cleanup of unrelated work merely to satisfy runtime preflight.

### B. Point runtime at the current activation/feature worktree

Rejected as a permanent solution. It makes Local WordPress depend on a disposable worktree and recreates historical handoff risk when that worktree is removed.

### C. Dedicated clean exact-commit runtime worktree

**Selected.**

It preserves Git as the source authority, isolates runtime provenance from daily development, is easy to audit with standard Git commands, and keeps the existing ACF Local JSON workflow intact.

## 14. Acceptance gate

The initial infrastructure slice passes only when:

```text
DEDICATED_ACF_RUNTIME_SOURCE=PASS
NORMAL_CHECKOUT_DEPENDENCY=REMOVED
RUNTIME_SOURCE_CLEAN=PASS
RUNTIME_SOURCE_EXACT_SHA=PASS
RUNTIME_SOURCE_DETACHED_HEAD=YES
RUNTIME_SYMLINK=PASS
ACF_RUNTIME_JSON_PARITY=PASS
TARGETED_ACF_STATUS_READ_ONLY=PASS
ACF_SYNCS_PERFORMED=0
WORDPRESS_DATA_MUTATIONS=0
```

A future generation advancement has a separate gate: candidate ACF subtree parity, clean detached candidate generation, `REQUEST_READ_WINDOW_CONSISTENCY=PASS`, successful guarded atomic symlink replacement, read-only ACF status, and retained previous generation. Failure of any gate leaves the current source active. Do not resume Person Runtime Activation until the cutover evidence PR is independently reviewed and accepted; any later activation restarts from Stage 1 under separate authorization.
