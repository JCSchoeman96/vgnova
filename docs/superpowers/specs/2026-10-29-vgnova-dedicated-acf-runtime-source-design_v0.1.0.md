# VGNova Dedicated ACF Runtime Source Design

**Version:** 0.1.0
**Status:** APPROVED
**Date:** 2026-10-29
**Repository:** `JCSchoeman96/vgnova`
**Target runtime:** `vgnova-local-10039`

## 1. Intent

Decouple the Local WordPress runtime's ACF Local JSON source from the everyday VGNova development checkout.

The runtime currently loads `site-code/acf-json` through the Bricks Child `acf-json` symlink. Historically that symlink was returned to the normal "stable main checkout" after feature work. That makes runtime safety depend on the normal checkout remaining globally clean, so unrelated development files can block ACF/runtime activation.

The new design keeps the repository as the sole ACF source authority while giving Local WordPress a dedicated, clean, auditable checkout used only for runtime ACF JSON loading.

## 2. Selected approach

Create a dedicated Git worktree pinned to one exact approved `main` commit and permanently point the Local Bricks Child `acf-json` symlink at that worktree's `site-code/acf-json` directory.

Proposed path:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-source
```

Runtime source:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
```

WordPress symlink:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json
    ->
/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
```

The worktree should normally be in detached-HEAD state at an exact approved merged `main` SHA. It is not a development workspace.

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

## 5. Runtime-source contract

The dedicated checkout is valid only when all are true:

```text
ACF_RUNTIME_SOURCE_EXISTS=YES
ACF_RUNTIME_SOURCE_IS_GIT_WORKTREE=YES
ACF_RUNTIME_SOURCE_HEAD=<approved exact SHA>
ACF_RUNTIME_SOURCE_STATUS=CLEAN
ACF_RUNTIME_SOURCE_JSON_PATH=<expected path>
ACF_RUNTIME_SYMLINK_TARGET=<expected path>
ACF_RUNTIME_JSON_PARITY=PASS
```

`git status --porcelain` must return no output.

The checked-out SHA must equal the explicitly approved runtime-source SHA. Merely being on a branch named `main` is insufficient.

The runtime symlink must resolve to the dedicated checkout and not to:

- the normal development checkout;
- an activation/evidence worktree;
- an arbitrary feature worktree;
- a missing path.

## 6. Checkout lifecycle

### Initial creation

Start from a fresh fetch:

```bash
git fetch origin --prune
```

Select an explicitly approved merged `main` SHA.

Create the worktree as a detached exact-commit checkout, for example:

```bash
git worktree add --detach   /home/jcschoeman96/projects/runtime/vgnova-acf-source   <approved-sha>
```

Do not create a long-lived runtime branch unless a later design explicitly requires one.

### Normal advancement

Advancing the runtime source is a controlled operation:

```text
new source PR merged
    ↓
fresh fetch origin
    ↓
record exact new main SHA
    ↓
prove new SHA is approved for runtime source
    ↓
verify dedicated checkout currently clean
    ↓
checkout/reset dedicated worktree to exact new SHA
    ↓
verify clean status
    ↓
verify expected ACF JSON inventory
    ↓
verify Local symlink still resolves correctly
    ↓
run targeted ACF status/dry-run
```

The runtime source must never be advanced merely because `origin/main` changed.

## 7. Symlink transition

The first transition from the old normal-checkout target to the dedicated runtime checkout is an infrastructure filesystem change, not an ACF database mutation.

Before changing the symlink:

1. verify the old target and record it;
2. verify the new dedicated worktree is clean and on the approved SHA;
3. verify required JSON files match the same approved commit;
4. verify the new target directory exists;
5. record the existing symlink inode/target if useful for evidence.

Then replace only the symlink.

After changing it:

1. `readlink` and `readlink -f` must resolve to the dedicated path;
2. ACF `load_json` / `save_json` runtime configuration must still resolve to the expected directory;
3. `wp acf json status --type=field-group --detailed` must work read-only;
4. no ACF sync is performed as part of this infrastructure transition;
5. no unrelated WordPress data changes are allowed.

If the symlink transition fails, restore the original symlink target and verify it.

## 8. Source parity

The dedicated runtime checkout must not become a second source authority.

Authority remains:

```text
Git repository merged source
    ↓
approved exact commit
    ↓
dedicated runtime worktree
    ↓
Local ACF JSON symlink
    ↓
targeted ACF synchronization
```

No edits should originate in the dedicated worktree.

If ACF or another process modifies JSON files there, the worktree becomes dirty and all governed ACF runtime mutation must stop until the provenance issue is understood.

Do not auto-reset a dirty runtime-source checkout because that could destroy evidence of an unexpected writer.

## 9. Safety rules

### Fail closed

Stop before ACF mutation when any of these occur:

- dedicated worktree missing;
- dirty worktree;
- unexpected HEAD;
- symlink resolves elsewhere;
- expected JSON files missing;
- source/runtime JSON provenance cannot be established;
- runtime source contains uncommitted files;
- an unexpected writer appears to have changed the checkout.

### No collateral cleanup

Do not clean, reset, stash or delete files in the normal development checkout to satisfy runtime preflight.

Do not clean/reset a dirty dedicated runtime checkout until its changes have been inspected and recorded.

### No disposable dependency

The runtime source path must not point at `.worktrees/<feature-name>` after this transition.

## 10. WordPress write behavior

The dedicated checkout remains writable at the filesystem level unless a later hardening design explicitly changes permissions.

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

Also record the exact JSON inventory/hashes relevant to the transition.

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
verify cutover
    ↓
resume Person Runtime Activation from Stage 1
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

The infrastructure slice passes only when:

```text
DEDICATED_ACF_RUNTIME_SOURCE=PASS
NORMAL_CHECKOUT_DEPENDENCY=REMOVED
RUNTIME_SOURCE_CLEAN=PASS
RUNTIME_SOURCE_EXACT_SHA=PASS
RUNTIME_SYMLINK=PASS
TARGETED_ACF_STATUS_READ_ONLY=PASS
ACF_SYNCS_PERFORMED=0
WORDPRESS_DATA_MUTATIONS=0
```

Only after this gate passes may the Simple Person Relationship Runtime Activation phase restart.
