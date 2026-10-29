# VGNova Dedicated ACF Runtime Source Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Version:** 0.1.1<br>**Amends:** v0.1.0; this version supersedes its runtime advancement procedure.<br>
**Date:** 2026-10-29

**Goal:** Replace the Local VGNova runtime's dependency on the normal development checkout for ACF Local JSON with a dedicated clean detached Git worktree pinned to one exact approved `main` SHA, without synchronizing ACF or mutating WordPress business data.

**Architecture:** Git remains the sole ACF source authority. The active generation 1 worktree at `/home/jcschoeman96/projects/runtime/vgnova-acf-source` stays pinned to `5a3bc4fc89fa61ae8916aa460989f1e8912b473d`; future generations use new immutable detached worktrees under `/home/jcschoeman96/projects/runtime/vgnova-acf-sources/<full-sha>`. ACF subtree parity, not whole-repository HEAD equality, determines whether the runtime source advances. Any future link switch is PE-007 guarded and also requires a verified request-consistency gate; this amendment makes no Local runtime change.

**Tech Stack:** Git worktrees, Linux symlinks/atomic rename, WordPress Local, ACF Pro 6.8.x CLI JSON workflow, PE-007 runtime mutation coordinator, PHP/WP-CLI.

**Spec:** `docs/superpowers/specs/2026-10-29-vgnova-dedicated-acf-runtime-source-design_v0.1.1.md`

**Execution status:** The initial cutover operations below are historical and are recorded in `docs/architecture/ACF_RUNTIME_SOURCE_CUTOVER_v1.0.0.md`. The evidence PR remains open for independent review. The v0.1.1 future-generation procedure below is prospective and was not executed by this amendment.

## Global Constraints

- Target only `vgnova-local-10039`.
- WordPress root: `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`.
- Dedicated runtime source path: `/home/jcschoeman96/projects/runtime/vgnova-acf-source`.
- Dedicated ACF JSON path: `/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json`.
- Runtime symlink path: `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json`.
- PR #63 must be independently approved and merged before the cutover.
- Freshly fetch `origin/main` after PR #63 merge; that exact SHA becomes `ACF_RUNTIME_SOURCE_SHA`.
- The dedicated checkout must use detached HEAD at exactly `ACF_RUNTIME_SOURCE_SHA`.
- The normal development checkout may remain dirty and must not be cleaned, reset, stashed, or modified for this task.
- The dedicated runtime checkout must be clean before and after the cutover.
- Do not edit files from the dedicated runtime checkout.
- Do not point runtime at a feature/evidence worktree.
- Do not synchronize any ACF group in this slice.
- Do not deploy Platform Core.
- Do not create or mutate Person relationships, schema, index, Bricks, commerce, memberships, or editorial/business data.
- Do not auto-reset a dirty dedicated runtime checkout; preserve evidence and stop.
- Treat generation 1 at SHA `5a3bc4fc89fa61ae8916aa460989f1e8912b473d` and its current path as immutable; do not move or update it in place.
- Future source SHAs use new, absent paths under `/home/jcschoeman96/projects/runtime/vgnova-acf-sources/<full-sha>`. Never reuse a generation path.
- During future activation, prove the complete `site-code/acf-json` subtree matches `ACTIVATION_SOURCE_SHA`; whole-repository SHA equality is not required.
- A future runtime link switch requires a request-quiescence window or independently verified request-pinned generation reads; PE-007 does not stop PHP readers.
- The symlink replacement itself must execute inside PE-007.
- If verification after symlink replacement fails, restore the exact previous symlink target under PE-007 and prove restoration.

## Review Focus

1. **Wrong repository revision:** the dedicated checkout must equal the explicitly recorded post-PR-#63 merged `main` SHA, not merely a branch named `main`.
2. **Dirty runtime source:** any tracked or untracked change in the dedicated worktree must fail closed and must not be auto-cleaned.
3. **Symlink/path confusion:** preflight and postflight must use both `readlink` and `readlink -f`; the resolved target must be exactly the dedicated `site-code/acf-json`.
4. **Partial symlink cutover:** create/verify a temporary symlink and atomically rename it over the runtime link; on postflight failure restore the recorded previous link target.
5. **Collateral ACF mutation:** only `acf json status` is allowed after cutover; `acf json sync` must remain zero.
6. **Mixed-generation reads:** a future symlink switch is blocked unless all relevant PHP requests are drained or a request-pinned immutable path is independently proven.

---

## File / Evidence Map

Create in a new evidence branch after PR #63 is merged:

- `docs/superpowers/specs/2026-10-29-vgnova-dedicated-acf-runtime-source-design_v0.1.1.md`
  - approved design record.
- `docs/superpowers/plans/2026-10-29-vgnova-dedicated-acf-runtime-source_v0.1.1.md`
  - this plan.
- `docs/architecture/ACF_RUNTIME_SOURCE_CUTOVER_v1.0.0.md`
  - durable cutover evidence.

No application/plugin source code is expected to change.

---

### Task 1: Close the blocked activation record before infrastructure cutover

**Files:**
- Existing PR #63 only.

**Interfaces:**
- Consumes: PR #63 head `b9349ceed8289d8f9009687e6c2c2ce37225e710`.
- Produces: merged blocked-attempt evidence on `main`.

- [ ] **Step 1: Refresh PR #63 state and exact-head checks**

Verify:
- PR #63 still contains exactly one evidence file;
- exact head is still the reviewed head unless a new reviewed evidence-only correction was made;
- Dashboard checks = success;
- Canonical source checks = success;
- VG Nova Platform Gate = success;
- artifact publication = skipped as expected;
- `main` has not advanced in a way that makes the evidence misleading.

- [ ] **Step 2: Fresh evidence-only review**

Review `docs/architecture/SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.0.0.md`.

Confirm:
- `RUNTIME_ACTIVATION=BLOCKED`;
- dirty normal checkout is the recorded hard stop;
- no runtime activation/deployment/ACF sync/schema/rebuild/reconcile is falsely claimed;
- zero mutation counters describe this attempt, not historical runtime state;
- all unrun checks are clearly marked `NOT_RUN`, `NOT_CHECKED`, or `BLOCKED`.

- [ ] **Step 3: STOP for merge approval if any evidence changed**

If the head changed after the last independent review, do not merge until exact-head review/checks are repeated.

If unchanged and clean, merge PR #63.

- [ ] **Step 4: Verify merge**

Freshly fetch:

```bash
git fetch origin --prune
git rev-parse origin/main
```

Record the new merge SHA.

This new `origin/main` becomes the only candidate for the ACF runtime-source pin.

---

### Task 2: Create the dedicated exact-commit runtime worktree

**Files:**
- Filesystem/Git metadata only.
- No Local WordPress mutation yet.

**Interfaces:**
- Consumes: exact merged `origin/main`.
- Produces: clean detached worktree at `/home/jcschoeman96/projects/runtime/vgnova-acf-source`.

- [ ] **Step 1: Record the exact approved SHA**

```bash
git fetch origin --prune
ACF_RUNTIME_SOURCE_SHA="$(git rev-parse origin/main)"
```

Record it.

Do not use an abbreviated SHA.

- [ ] **Step 2: Verify target path is safe**

Require:

```bash
test ! -e /home/jcschoeman96/projects/runtime/vgnova-acf-source
test ! -L /home/jcschoeman96/projects/runtime/vgnova-acf-source
```

If the path already exists, STOP and inspect provenance. Do not delete or reuse unknown contents.

Create only the parent directory if needed:

```bash
mkdir -p /home/jcschoeman96/projects/runtime
```

- [ ] **Step 3: Create detached worktree**

From the VGNova Git repository:

```bash
git worktree add --detach \
  /home/jcschoeman96/projects/runtime/vgnova-acf-source \
  "$ACF_RUNTIME_SOURCE_SHA"
```

Expected: exact detached checkout created.

- [ ] **Step 4: Prove worktree identity**

Run:

```bash
git -C /home/jcschoeman96/projects/runtime/vgnova-acf-source rev-parse HEAD
git -C /home/jcschoeman96/projects/runtime/vgnova-acf-source symbolic-ref -q HEAD || true
git -C /home/jcschoeman96/projects/runtime/vgnova-acf-source status --porcelain=v1 --untracked-files=all
```

Expected:
- HEAD = `ACF_RUNTIME_SOURCE_SHA`;
- symbolic-ref emits nothing / detached HEAD;
- status emits nothing.

- [ ] **Step 5: Verify ACF JSON inventory**

Require the canonical source directory and all currently tracked ACF JSON files.

At minimum require the Person relationship groups:

```text
group_vg_article_people.json
group_vg_course_people.json
```

Generate a deterministic inventory of:
- relative path;
- SHA-256.

Compare every file in `site-code/acf-json` against the same exact Git commit using Git object content, not the dirty normal checkout filesystem.

Expected: exact parity.

- [ ] **Step 6: Review gate**

A fresh reviewer verifies:
- worktree is exact SHA;
- detached;
- clean;
- target directory not shared with normal checkout;
- JSON inventory matches Git;
- no Local runtime path has changed yet.

---

### Task 3: Capture the current ACF runtime-link baseline

**Files:**
- Read-only Local filesystem/runtime inspection.

**Interfaces:**
- Consumes: existing Local symlink.
- Produces: rollback target and pre-cutover evidence.

- [ ] **Step 1: Record old link**

Set:

```text
ACF_LINK=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json
```

Run:

```bash
test -L "$ACF_LINK"
readlink "$ACF_LINK"
readlink -f "$ACF_LINK"
stat -c '%F|%a|%u|%g|%d|%i' "$ACF_LINK"
```

Record:
- lexical target;
- resolved target;
- link metadata.

Expected old resolved target for the blocked attempt:

```text
/home/jcschoeman96/projects/current/vgnova/site-code/acf-json
```

If it has changed, STOP and classify provenance before cutover.

- [ ] **Step 2: Verify new target before mutation**

```bash
NEW_ACF_TARGET=/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
test -d "$NEW_ACF_TARGET"
test ! -L "$NEW_ACF_TARGET"
```

Verify:
- dedicated worktree still clean;
- HEAD still exact;
- JSON inventory unchanged from Task 2.

- [ ] **Step 3: Capture read-only ACF configuration**

Using the known-working Local database socket/runtime method, inspect:
- ACF `load_json`;
- ACF `save_json`;
- current `wp acf json status --type=field-group --detailed`.

This is baseline evidence only.

Do not run `acf json sync`.

Record the complete pending inventory so post-cutover status can be compared.

---

### Task 4: Atomically cut over the runtime ACF symlink under PE-007

**Files:**
- Modify only the runtime symlink path.
- No WordPress DB write.

**Interfaces:**
- Consumes: `OLD_ACF_TARGET`, `NEW_ACF_TARGET`, valid `vgnova-local-10039` PE-007 session.
- Produces: runtime symlink resolving to dedicated source path.

- [ ] **Step 1: Prepare a bounded cutover script**

Use a temporary script outside the runtime source trees, or an inline shell with equivalent safeguards, that:

1. asserts `$ACF_LINK` is still a symlink;
2. asserts `readlink -f "$ACF_LINK"` is still exactly the recorded old resolved target;
3. asserts `$NEW_ACF_TARGET` exists and resolves exactly;
4. creates a temporary symlink in the same parent directory as `$ACF_LINK`;
5. verifies the temporary link resolves to `$NEW_ACF_TARGET`;
6. atomically renames the temporary symlink over `$ACF_LINK` with same-filesystem rename semantics;
7. verifies the final link resolves exactly to `$NEW_ACF_TARGET`;
8. cleans any unused temporary link on failure.

Do not use an unverified `ln -sfn` as the entire cutover.

- [ ] **Step 2: Execute the cutover under PE-007**

Run the bounded cutover through:

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/" \
  --purpose="Cut over ACF Local JSON to dedicated exact-commit runtime source" \
  -- \
  <bounded-cutover-command>
```

Expected:
- target identity/session gates pass;
- exactly one runtime filesystem object changes: the `acf-json` symlink;
- command exits 0.

- [ ] **Step 3: Immediate post-cutover path proof**

Run read-only:

```bash
readlink "$ACF_LINK"
readlink -f "$ACF_LINK"
```

Expected resolved target exactly:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
```

- [ ] **Step 4: Failure rollback**

If any post-cutover verification fails, STOP normal progression.

Under a fresh PE-007 mutation session, atomically restore the recorded old lexical/resolved symlink target.

Then prove:
- old resolved target restored;
- no ACF sync occurred;
- dedicated worktree remains unchanged.

Record the cutover as BLOCKED.

---

### Task 5: Prove ACF can read from the dedicated source without syncing

**Files:**
- No writes expected.

**Interfaces:**
- Consumes: new symlink.
- Produces: read-only ACF runtime-source proof.

- [ ] **Step 1: Re-prove dedicated worktree**

Run:
- exact HEAD;
- detached state;
- clean status;
- JSON inventory hash.

Expected unchanged from Task 2.

- [ ] **Step 2: Verify WordPress/ACF resolved paths**

Using the same verified Local WP-CLI/socket method, inspect ACF runtime configuration.

Expected:
- `load_json` includes/resolves the Bricks Child `acf-json` path backed by the dedicated checkout;
- `save_json` behavior is unchanged from before except for the symlink's resolved filesystem destination.

Do not alter ACF configuration.

- [ ] **Step 3: Run read-only ACF status**

```bash
wp --path="$WP_ROOT" acf json status \
  --type=field-group \
  --detailed
```

Expected:
- command succeeds;
- target Person groups are visible from Local JSON;
- complete pending inventory is logically identical to pre-cutover status;
- no sync/action was applied.

Record:

```text
ACF_STATUS_READ_ONLY=PASS
ACF_SYNCS_PERFORMED=0
```

- [ ] **Step 4: Prove no runtime-source writes occurred**

Re-run:

```bash
git -C /home/jcschoeman96/projects/runtime/vgnova-acf-source status --porcelain=v1 --untracked-files=all
```

Expected: empty.

If merely reading ACF dirties the checkout, STOP and investigate before any future ACF mutation.

---

### Task 6: Record cutover evidence and open an evidence-only PR

**Files:**
- Create: `docs/architecture/ACF_RUNTIME_SOURCE_CUTOVER_v1.0.0.md`
- Add approved design/plan to canonical repo paths if not already present.

**Interfaces:**
- Consumes: Tasks 1–5 evidence.
- Produces: auditable PASS/BLOCKED record.

- [ ] **Step 1: Record mandatory evidence**

Include:

```text
OLD_ACF_SYMLINK_TARGET=<old path>
NEW_ACF_SYMLINK_TARGET=/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
ACF_RUNTIME_SOURCE_SHA=<exact post-PR63 main sha>
ACF_RUNTIME_SOURCE_STATUS=CLEAN
ACF_RUNTIME_SOURCE_DETACHED_HEAD=YES
ACF_RUNTIME_JSON_PARITY=PASS
RUNTIME_SYMLINK=PASS
TARGETED_ACF_STATUS_READ_ONLY=PASS
ACF_SYNCS_PERFORMED=0
WORDPRESS_DATA_MUTATIONS=0
BRICKS_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
PERSON_RELATIONSHIP_MUTATIONS=0
```

Also record:
- old/new `readlink` and `readlink -f`;
- worktree creation proof;
- JSON inventory hashes;
- ACF status before/after comparison;
- PE-007 session/cutover command result;
- rollback state (`NOT_REQUIRED` or evidence of successful restoration).

- [ ] **Step 2: Run repo verification**

```bash
./site-code/bin/check-source.sh
git diff --check
```

Expected: PASS.

- [ ] **Step 3: Fresh evidence review**

Reviewer explicitly confirms:
- normal checkout was not cleaned/reset/stashed;
- runtime source is dedicated and exact-commit;
- source is detached and clean;
- symlink is correct;
- no ACF sync occurred;
- no WordPress/Bricks/Platform Core/business-data mutation occurred;
- Person Runtime Activation did not resume during this slice.

Fix/re-review any Critical or Important evidence issue.

- [ ] **Step 4: Open evidence-only PR and STOP**

Suggested branch:

```text
ops/dedicated-acf-runtime-source
```

Suggested PR title:

```text
ops: cut over ACF runtime source to dedicated worktree
```

STOP for independent ChatGPT review.

Do not restart Person Runtime Activation until this evidence PR is independently reviewed and accepted. Any later activation starts again from Stage 1 under separate authorization.

---

## v0.1.1 Amendment: Future Runtime Generation Advancement

This procedure is prospective and is not executed by the current cutover evidence PR. Generation 1 remains at `/home/jcschoeman96/projects/runtime/vgnova-acf-source`, pinned to `5a3bc4fc89fa61ae8916aa460989f1e8912b473d`; this amendment does not move it or change the Local symlink.

For each later activation/source SHA:

1. Freshly fetch and record the exact merged `ACTIVATION_SOURCE_SHA`.
2. Create a new detached candidate worktree at `/home/jcschoeman96/projects/runtime/vgnova-acf-sources/<full-sha>`. Require the path to be absent; if it exists, stop and establish provenance.
3. Prove exact HEAD, detached state, clean status, complete ACF JSON file inventory, and byte-for-byte parity against Git object content at that SHA.
4. Compare the candidate `site-code/acf-json` Git tree with the active generation's subtree. If identical, keep the active runtime source SHA and symlink. Record parity with the activation SHA; do not switch merely because `main` advanced. Retire the comparison worktree only after its evidence is accepted.
5. If the ACF subtree changed, treat the candidate as a new immutable generation. Before link change, establish and evidence request consistency. ACF Pro 6.8.1 reads multiple JSON files by path; atomic pointer replacement alone is not a per-request snapshot. PE-007 does not stop PHP readers. Stop new requests capable of loading ACF JSON, drain in-flight web/admin/cron/queue/PHP work, and keep the gate closed through replacement and read-only ACF status. Alternatively, use a separately reviewed and verified request-pinned physical generation path. If neither control is proven, stop with the current source active.
6. Under PE-007, verify the old symlink, create and validate a temporary same-directory symlink to the new generation, then atomically replace the link. While the request gate remains closed, verify both link forms, run only read-only `acf json status`, and verify the candidate remains clean and exact.
7. On any failure, restore the exact old target under PE-007 before reopening requests, then stop. On success, reopen requests and retain the old generation until the new generation evidence is accepted. Never update, reset, or delete the prior active generation as part of the switch.

Required future evidence includes:

```text
ACTIVATION_SOURCE_SHA=<exact SHA>
ACF_RUNTIME_SOURCE_SHA=<active generation SHA>
ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS|BLOCKED
CANDIDATE_GENERATION_CLEAN=PASS|BLOCKED
REQUEST_READ_WINDOW_CONSISTENCY=PASS|BLOCKED|NOT_REQUIRED
RUNTIME_SYMLINK_SWITCH=PASS|NOT_REQUIRED|BLOCKED
TARGETED_ACF_STATUS_READ_ONLY=PASS|BLOCKED
PREVIOUS_GENERATION_RETAINED=YES|NO
ACF_SYNCS_PERFORMED=0
```

No generation advancement, WordPress data mutation, ACF sync, or Person Runtime Activation is part of this documentation amendment.

---

# Acceptance Gate

The slice is PASS only when:

```text
PR_63_MERGED=YES
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
BRICKS_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
PERSON_RELATIONSHIP_MUTATIONS=0
```

After independent acceptance of the evidence PR, a separately authorized Simple Person Relationship Runtime Activation must restart from **Stage 1**, not Stage 1 Step 5.

# Explicitly Forbidden

- cleaning/resetting/stashing the dirty normal checkout;
- using the normal checkout as runtime ACF authority after cutover;
- using a disposable `.worktrees/<feature>` path as the permanent runtime source;
- synchronizing ACF;
- editing ACF groups in wp-admin;
- deploying Platform Core;
- setting up/rebuilding/reconciling Person relationship schema/index;
- creating QA or real relationship data;
- changing Bricks or business data;
- auto-resetting a dirty dedicated runtime checkout;
- checking out, resetting, pulling, or otherwise updating any active ACF runtime generation in place;
- switching generations while ACF JSON readers may span the symlink change;
- treating PE-007 locks as a PHP request quiescence mechanism.
