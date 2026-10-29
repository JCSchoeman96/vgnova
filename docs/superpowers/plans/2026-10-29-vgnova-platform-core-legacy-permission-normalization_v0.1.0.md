# VGNova Legacy Platform Core Permission Normalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Version:** 0.1.0<br>
**Date:** 2026-10-29

**Goal:** Normalize the one known legacy Platform Core runtime tree from `0664/0775` to PE-008 canonical `0644/0755` modes, changing only permission metadata and proving content/ownership/path identity before and after.

**Architecture:** This is a one-time runtime remediation, not a deployment. A bounded ephemeral normalizer revalidates the accepted Task 8B path/content manifest and exact legacy permission pattern immediately before mutation, runs as a child of PE-007, records every original mode, rolls back changed entries on failure, and proves PE-008 can fingerprint the resulting tree. Person Runtime Activation, ACF synchronization, and application-source deployment remain out of scope.

**Tech Stack:** Linux filesystem metadata, Python 3, SHA-256, WordPress Local, PE-007 runtime mutation coordinator, PE-008 read-only fingerprint/status logic, Git evidence workflow.

**Spec:** `docs/superpowers/specs/2026-10-29-vgnova-platform-core-legacy-permission-normalization-design_v0.1.0.md`

## Accepted baseline

PR #65 has been merged.

Last independently verified:

```text
EXPECTED_SOURCE_SHA=3e226294e8f72a859e50e5b9a8deb3023ec3ed6a
PR65_APPROVED_HEAD=ad3dc708789494dfebf53e98ef7fec13277ac019
PR65_MERGE=3e226294e8f72a859e50e5b9a8deb3023ec3ed6a

TARGET=vgnova-local-10039
WP_ROOT=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/
PLUGIN_ROOT=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core

ACCEPTED_TASK8B_FILES=54
ACCEPTED_TASK8B_CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760

EXPECTED_PRE_MODE_PATTERN=53x0664,1x0775,16dirsx0775,root0755
EXPECTED_POST_MODE_PATTERN=53x0644,1x0755,16dirsx0755,root0755
EXPECTED_OWNER_UID=1000
EXPECTED_GROUP_GID=1000
```

## Global Constraints

- Freshly fetch `origin/main` before work.
- If fresh `origin/main` is not exactly `3e226294e8f72a859e50e5b9a8deb3023ec3ed6a`, STOP before Local mutation and report the new SHA.
- Use an isolated evidence worktree from the exact source SHA.
- Do not clean/reset/stash/edit the normal development checkout.
- Do not modify the active ACF runtime generation or candidate ACF worktrees.
- Do not deploy Platform Core.
- Do not synchronize ACF.
- Do not create/setup/rebuild/reconcile Person relationships.
- Do not edit Bricks, commerce, memberships, editorial/business data, plugin code, or WordPress configuration.
- Do not change ownership, ACLs, paths, file bytes, inode type, or plugin activation state.
- The plugin root itself is already `0755`; it must not require a chmod.
- Every live chmod must execute as a child of PE-007.
- Before the live run, prove the normalizer on a throwaway copy, including rollback on an injected failure.
- If any live precondition differs from the accepted legacy state, STOP. Do not “adapt” the normalizer to what is found.
- If rollback fails, STOP all runtime work and preserve evidence.
- Do not resume Person Runtime Activation in this slice.

## Review Focus

1. **Wrong runtime tree:** exact target/root, accepted 54-path manifest, bytes, ownership, and object types must all match before chmod.
2. **Silent collateral mutation:** path set, bytes, uid/gid, file types, ACL state, and plugin activation must remain unchanged.
3. **Partial chmod failure:** rollback must have been demonstrated on a non-runtime copy before live execution.
4. **PE-007 bypass:** no direct live chmod outside the guarded runtime mutation session.
5. **Overclaiming causality:** evidence must retain `LIKELY_LEGACY_TASK8B_STAGING`, not promote it to `PROVEN`.

---

## File / Evidence Map

Expected repository changes are documentation/evidence only:

- `docs/superpowers/specs/2026-10-29-vgnova-platform-core-legacy-permission-normalization-design_v0.1.0.md`
- `docs/superpowers/plans/2026-10-29-vgnova-platform-core-legacy-permission-normalization_v0.1.0.md`
- `docs/architecture/PLATFORM_CORE_LEGACY_PERMISSION_NORMALIZATION_v1.0.0.md`
- optional machine-readable companion:
  `docs/architecture/PLATFORM_CORE_LEGACY_PERMISSION_NORMALIZATION_MANIFEST_v1.0.0.json`

The live normalizer is ephemeral and should be created outside the repository, for example:

```text
/tmp/vgnova-platform-core-permission-normalizer-<unique>.py
```

Do not add permanent remediation code merely for this one repair.

---

### Task 1: Pin exact merged source and establish evidence workspace

**Interfaces:**
- Produces `SOURCE_SHA` and clean isolated evidence workspace.

- [ ] **Step 1: Fresh fetch**

```bash
git fetch origin --prune
git rev-parse origin/main
```

Expected exactly:

```text
3e226294e8f72a859e50e5b9a8deb3023ec3ed6a
```

If different, STOP before Local mutation.

- [ ] **Step 2: Verify PR #65 merge ancestry**

Prove the merge commit has:
- parent 1: `131cce920354ba90ceb7c9c5438422b2de16a6c3`
- parent 2: `ad3dc708789494dfebf53e98ef7fec13277ac019`

- [ ] **Step 3: Create isolated worktree**

Use `superpowers:using-git-worktrees`.

Suggested:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/platform-core-legacy-permission-normalization
```

Create from exact `SOURCE_SHA`.

Require clean status before adding design/plan/evidence.

---

### Task 2: Fresh read-only runtime preflight

**Interfaces:**
- Produces exact pre-normalization state and abort decision.

- [ ] **Step 1: Rediscover target transport**

Freshly identify the Local MySQL socket corresponding to the target site.

Verify:

```text
home=http://localhost:10039
siteurl=http://localhost:10039
DB_NAME=local
DB_HOST=localhost
TARGET=vgnova-local-10039
```

Any mismatch = STOP.

- [ ] **Step 2: Verify PE-008 state**

Run read-only PE-008 status.

Require:

```text
in_progress=false
recovery_options=[]
```

- [ ] **Step 3: Load accepted Task 8B manifest**

Use the committed accepted evidence:

```text
docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_v1.0.0.md
docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_MANIFEST_v1.0.0.json
```

Extract the exact accepted 54-file relative path/size/SHA-256 map.

Do not construct expected content from the current runtime.

- [ ] **Step 4: Capture complete live pre-snapshot**

For root, every descendant directory, and every file capture deterministically:

```text
relative_path
type
mode
uid
gid
device
inode
nlink
size
sha256 (regular files only)
ACL/access/default ACL state
```

Reject symlinks or unsupported objects.

- [ ] **Step 5: Hard precondition comparison**

Require all:

```text
FILES=54
CONTENT_PATH_SIZE_SHA256_MATCH_TASK8B=YES
CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760

53 ordinary files=0664
bin/check.sh=0775
16 descendant directories=0775
root=0755

ALL_UID=1000
ALL_GID=1000
SYMLINKS=0
UNSUPPORTED_OBJECTS=0
UNEXPECTED_HARDLINKS=0
DEFAULT_ACL_PRESENT=NO
```

If any mismatch: `PERMISSION_NORMALIZATION=BLOCKED`, stop without mutation.

- [ ] **Step 6: Record pre-state digest**

Produce a machine-readable pre-snapshot and SHA-256 of that snapshot.

Fresh reviewer verifies this gate before any live chmod.

---

### Task 3: Build the ephemeral bounded normalizer

**Files:**
- Create outside repository: `/tmp/vgnova-platform-core-permission-normalizer-<unique>.py`

**Interfaces:**
- Inputs: plugin root, accepted manifest JSON, expected uid/gid and exact mode map.
- Outputs: JSON summary and exit status.
- Modes: `--dry-run`, live run; test-only failure injection allowed only on non-runtime probe trees.

- [ ] **Step 1: Write a failing probe test first**

Create a throwaway copy/probe tree that reproduces:
- 53 regular files `0664`
- `bin/check.sh` `0775`
- 16 directories `0775`
- root `0755`

Use real accepted Task 8B content if practical; otherwise the test must still separately verify the live run uses the real accepted manifest.

Test assertions:
- dry-run reports exactly 70 intended chmods (53 + 1 + 16);
- no bytes/ownership/paths change;
- canonical expected post modes are reported.

Run before normalizer implementation; expected failure because tool does not exist.

- [ ] **Step 2: Implement minimum normalizer**

It must:
- refuse symlinked root;
- walk via `lstat`/no-follow behavior;
- verify exact accepted file relative path/size/SHA-256 set;
- verify exact directory set;
- verify uid/gid/device/nlink/object type;
- verify exact legacy mode map;
- precompute entire delta before first chmod;
- never call chown/chgrp/setfacl/rename/write file contents;
- chmod in deterministic path order;
- record original mode after each successful chmod;
- rollback in reverse order on failure;
- post-verify paths/bytes/ownership/types/modes;
- emit JSON.

No permissive “normalize whatever exists” behavior.

- [ ] **Step 3: Verify dry-run on probe**

Expected:
- exact delta;
- zero filesystem mutation.

- [ ] **Step 4: Verify successful probe normalization**

Run live normalizer only against the throwaway probe.

Expected:
- all target modes canonical;
- path/content/uid/gid unchanged.

- [ ] **Step 5: Verify rollback with injected failure**

On a freshly recreated throwaway probe only, inject failure after a nonzero number of chmods.

Expected:
- command fails;
- rollback executes;
- final per-path mode map exactly equals original legacy map;
- bytes/ownership/path set unchanged.

This failure-injection option must be rejected or omitted for the actual live invocation.

- [ ] **Step 6: Fresh reviewer gate**

Reviewer checks:
- live root cannot accidentally be used in probe/failure-injection tests;
- failure injection is impossible/disabled in production invocation;
- exact-manifest verification precedes chmod;
- rollback is exact per-path restoration;
- no content-writing APIs are used.

---

### Task 4: Live PE-007 governed normalization

**Interfaces:**
- Consumes verified normalizer and fresh Task 2 preflight.
- Produces canonical runtime permission metadata.

- [ ] **Step 1: Freeze interactive Local administration**

Avoid browser/admin/editor activity until post-normalization verification completes.

- [ ] **Step 2: Re-run all hard preconditions immediately before mutation**

Do not rely on Task 2 capture alone.

Require identical path/content/ownership/mode/object state and PE-008 status.

If anything changed, STOP.

- [ ] **Step 3: Run normalizer under PE-007**

Use the freshly verified `MYSQL_SOCKET`:

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/" \
  --db-socket="$MYSQL_SOCKET" \
  --purpose="Normalize legacy Platform Core runtime permissions to canonical PE-008 modes" \
  -- \
  python3 /tmp/vgnova-platform-core-permission-normalizer-<unique>.py \
    --plugin-root="/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core" \
    --accepted-manifest="<path-to-read-only-accepted-task8b-manifest>"
```

The live command must NOT include failure injection.

- [ ] **Step 4: Require successful normalizer summary**

Expected:

```text
changed_files=54
changed_directories=16
root_changed=0
rollback_required=false
```

And exact canonical mode map.

If command fails:
- inspect returned rollback result;
- if rollback succeeded, stop;
- if rollback failed, stop all runtime operations and escalate.

---

### Task 5: Independent post-normalization proof

**Interfaces:**
- Produces proof the runtime changed only modes.

- [ ] **Step 1: Capture complete post-snapshot**

Capture the same fields as Task 2.

- [ ] **Step 2: Compare immutable invariants**

Require:

```text
PATH_SET_UNCHANGED=YES
CONTENT_PATH_SIZE_SHA256_UNCHANGED=YES
CONTENT_TREE_SHA256_UNCHANGED=YES
OWNER_GROUP_UNCHANGED=YES
OBJECT_TYPES_UNCHANGED=YES
DEVICE_UNCHANGED=YES
```

Inodes are expected to remain the same for in-place chmod; require them unchanged unless a documented OS behavior proves otherwise.

ctime will change because chmod changes inode metadata; this is intentional and must not be treated as content drift.

- [ ] **Step 3: Verify canonical modes**

Require:

```text
53 ordinary files=0644
bin/check.sh=0755
16 descendant directories=0755
root=0755
```

No `0664/0775` legacy mode may remain in the tree.

- [ ] **Step 4: PE-008 fingerprint proof**

Invoke the same PE-008 runtime fingerprint/status code path that previously rejected the live tree.

Require successful fingerprint.

Record canonical PE-008 tree SHA-256.

It may differ from the old content-only Task 8B digest because PE-008's canonical tree hash includes mode strings. Do not incorrectly require the old content-only digest to equal the PE-008 digest.

- [ ] **Step 5: WordPress bootstrap**

One verified read-only WP-CLI bootstrap.

Require success.

- [ ] **Step 6: HTTP health**

One GET to:

```text
http://localhost:10039/
```

Require HTTP 200.

- [ ] **Step 7: Post-bootstrap metadata fingerprint**

Re-run post-snapshot/fingerprint.

Expected no additional metadata or content change beyond the intentional chmod ctimes.

Record:

```text
POST_BOOTSTRAP_METADATA_DELTA=NO
```

---

### Task 6: Durable evidence and evidence-only PR

**Files:**
- Add approved design at canonical specs path.
- Add this plan at canonical plans path.
- Create `docs/architecture/PLATFORM_CORE_LEGACY_PERMISSION_NORMALIZATION_v1.0.0.md`
- Optional JSON manifest.

**Interfaces:**
- Produces reviewed remediation evidence.

- [ ] **Step 1: Write mandatory result block**

```text
SOURCE_SHA=3e226294e8f72a859e50e5b9a8deb3023ec3ed6a
PERMISSION_ROOT_CAUSE=LIKELY_LEGACY_TASK8B_STAGING

PERMISSION_NORMALIZATION=PASS|BLOCKED
PE007_COORDINATION=PASS|BLOCKED

PRE_CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
POST_CONTENT_TREE_SHA256=<content-only digest>
CONTENT_TREE_SHA256_UNCHANGED=YES|NO

PRE_MODE_PATTERN=53x0664,1x0775,16dirsx0775,root0755
POST_MODE_PATTERN=53x0644,1x0755,16dirsx0755,root0755

OWNER_GROUP_UNCHANGED=YES|NO
PATH_SET_UNCHANGED=YES|NO
OBJECT_TYPES_UNCHANGED=YES|NO

PE008_RUNTIME_FINGERPRINT=PASS|BLOCKED
PE008_RUNTIME_TREE_SHA256=<canonical-mode-aware hash or NOT_AVAILABLE>

WORDPRESS_BOOT=PASS|BLOCKED
HTTP_200=PASS|BLOCKED
POST_BOOTSTRAP_METADATA_DELTA=NO|YES

PLATFORM_CORE_DEPLOYMENTS=0
ACF_SYNCS_PERFORMED=0
PERSON_RELATIONSHIP_MUTATIONS=0
BRICKS_MUTATIONS=0
COMMERCE_MUTATIONS=0
MEMBERSHIP_MUTATIONS=0

ROLLBACK=NOT_REQUIRED|PASS|FAILED
```

- [ ] **Step 2: Include raw proof**

Document:
- exact accepted Task 8B manifest authority;
- exact pre/post mode maps;
- pre/post content hashes;
- pre/post ownership/path/object comparison;
- probe success;
- injected-failure rollback proof;
- PE-007 session outcome;
- PE-008 fingerprint result;
- bootstrap/HTTP result;
- zero forbidden mutations.

- [ ] **Step 3: Repository verification**

Run:

```bash
./site-code/bin/check-source.sh
git diff --check
```

Expected PASS.

- [ ] **Step 4: Fresh reviewer**

Reviewer must confirm:
- cause remains `LIKELY`, not `PROVEN`;
- no PE-008 policy change;
- no deployment occurred;
- only permission metadata changed;
- exact content/ownership/path identity preserved;
- rollback was demonstrated before live mutation;
- Person Runtime Activation was not resumed.

Fix/re-review any Critical or Important evidence issue.

- [ ] **Step 5: Open evidence-only PR and STOP**

Suggested branch:

```text
ops/platform-core-legacy-permission-normalization
```

Suggested title:

```text
ops: normalize legacy Platform Core runtime permissions
```

STOP after opening PR.

Do not restart Person Runtime Activation yet.

---

# Acceptance Gate

The remediation passes only if:

```text
SOURCE_SHA=3e226294e8f72a859e50e5b9a8deb3023ec3ed6a
PERMISSION_ROOT_CAUSE=LIKELY_LEGACY_TASK8B_STAGING

PERMISSION_NORMALIZATION=PASS
PE007_COORDINATION=PASS

CONTENT_TREE_SHA256_UNCHANGED=YES
OWNER_GROUP_UNCHANGED=YES
PATH_SET_UNCHANGED=YES
OBJECT_TYPES_UNCHANGED=YES

POST_MODE_PATTERN=53x0644,1x0755,16dirsx0755,root0755

PE008_RUNTIME_FINGERPRINT=PASS
WORDPRESS_BOOT=PASS
HTTP_200=PASS
POST_BOOTSTRAP_METADATA_DELTA=NO

PLATFORM_CORE_DEPLOYMENTS=0
ACF_SYNCS_PERFORMED=0
PERSON_RELATIONSHIP_MUTATIONS=0
BRICKS_MUTATIONS=0
COMMERCE_MUTATIONS=0
MEMBERSHIP_MUTATIONS=0

ROLLBACK=NOT_REQUIRED
```

Any failure blocks further Person Runtime Activation until reviewed.
