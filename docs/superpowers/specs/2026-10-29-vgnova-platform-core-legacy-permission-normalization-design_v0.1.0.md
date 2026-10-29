# VGNova Legacy Platform Core Permission Normalization Design

**Version:** 0.1.0<br>
**Status:** APPROVED<br>
**Date:** 2026-10-29<br>
**Repository:** `JCSchoeman96/vgnova`<br>
**Target runtime:** `vgnova-local-10039`

## 1. Intent

Normalize the active legacy Platform Core runtime tree from the historically reproduced Task 8B mode pattern to the canonical PE-008 mode contract, without changing file contents, ownership, plugin structure, WordPress data, ACF state, or Person relationship state.

This slice exists because Phase 1 Person Runtime Activation correctly stopped when PE-008 refused to fingerprint the active Platform Core tree. The live tree's 54 file paths and bytes match the accepted Task 8B content fingerprint, but its filesystem permission metadata is outside PE-008's accepted file modes.

The remediation must preserve PE-008's strict canonical contract. It must not weaken the deployer to tolerate legacy permissions.

## 2. Current accepted facts

The active Platform Core tree:

```text
PLUGIN_ROOT=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core

files=54
content_tree_sha256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760

53 ordinary files = 0664
bin/check.sh      = 0775
16 descendant dirs = 0775
plugin root       = 0755

owner/group = 1000:1000
symlinks = 0
```

The permission investigation classified the cause as:

```text
PERMISSION_ROOT_CAUSE=LIKELY_LEGACY_TASK8B_STAGING
CURRENT_UMASK=0002
DEFAULT_ACL_PRESENT=NO
LIVE_CONTENT_MATCHES_TASK8B=YES
TASK8B_PROBE_MODE_PATTERN=MATCH
TASK8B_EXACT_STAGING_COMMANDS_RECOVERED=YES
NORMAL_BOOTSTRAP_METADATA_DELTA=NO
ONGOING_AUTOMATIC_WRITER_EVIDENCE=NOT_PROVEN
```

The classification deliberately remains `LIKELY`, not `PROVEN`, because the historical Task 8B evidence did not record staged modes, historical umask, or historical ACL state.

## 3. Selected approach

Perform one bounded, PE-007-governed, in-place permission normalization of the already accepted 54-file runtime tree.

Canonical target modes:

```text
ordinary files = 0644
bin/check.sh    = 0755
all descendant directories = 0755
plugin root = 0755
```

No file bytes, paths, ownership, group ownership, inode type, or plugin structure may intentionally change.

The normalization is allowed only if every precondition proves the live tree is exactly the known Task 8B content and mode pattern. Any unexpected path, content hash, owner/group, symlink, object type, or permission blocks the mutation.

## 4. Why this approach

### A. In-place metadata normalization — selected

Advantages:

- smallest possible mutation;
- changes only the metadata known to be incompatible;
- preserves the already accepted 54-file content tree;
- preserves all file paths;
- no plugin code replacement;
- no source deployment;
- restores PE-008 compatibility rather than weakening it.

Risk:

- a sequence of chmod operations is not intrinsically atomic.

Mitigation:

- complete preflight;
- PE-007 coordination;
- bounded normalizer;
- mutation journal in memory/on temporary evidence path;
- deterministic order;
- rollback already changed entries if any chmod or verification step fails;
- immediate byte/ownership/mode verification.

### B. Build second tree and atomically exchange directories — rejected

This would replace every inode and recreate the plugin tree even though its bytes are already trusted. It creates more moving parts and unnecessarily resembles a deployment.

### C. Relax PE-008 to accept `0664/0775` — rejected

This would convert a historical staging artifact into permanent deployment policy. PE-008 intentionally accepts canonical Git-style `0644/0755` modes and stages new trees using those modes.

## 5. Hard preconditions

Before the first chmod, all of these must be freshly re-proven:

```text
TARGET_IDENTITY=PASS
PE008_IN_PROGRESS=NO
PE008_RECOVERY_REQUIRED=NO

LIVE_FILES=54
LIVE_CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
LIVE_CONTENT_MATCHES_ACCEPTED_TASK8B_MANIFEST=YES

FILES_0664=53
FILES_0775=1
EXECUTABLE_0775=bin/check.sh

DESCENDANT_DIRS_0775=16
ROOT_MODE=0755

OWNER_UID=1000
GROUP_GID=1000

SYMLINKS=0
UNSUPPORTED_OBJECTS=0
HARDLINK_MULTIPLICITY_UNEXPECTED=0
DEFAULT_ACL_PRESENT=NO
```

If any condition differs from the investigated state, stop and classify the new state before mutation.

The accepted Task 8B manifest and content hashes are authority for path/content identity. Do not infer identity from file count alone.

## 6. Mutation boundary

The only permitted runtime mutations are mode changes on paths already proven by preflight.

Allowed:

```text
0664 -> 0644 for the 53 ordinary files
0775 -> 0755 for bin/check.sh
0775 -> 0755 for the 16 descendant directories
```

The plugin root is already `0755`; it must not require a change.

Forbidden:

- file content changes;
- file creation/deletion;
- rename/move;
- chown/chgrp;
- ACL changes;
- plugin activation/deactivation;
- ACF sync;
- Platform Core deployment;
- Person schema/index operations;
- Bricks/data/commerce/membership mutations;
- cleanup of the normal Git checkout.

## 7. PE-007 coordination

The normalization command must execute as a child of the existing runtime mutation coordinator:

```bash
php site-code/bin/vgnova-runtime-mutate.php   --target=vgnova-local-10039   --wp-root="/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/"   --db-socket="<freshly-verified-local-socket>"   --purpose="Normalize legacy Platform Core runtime permissions to canonical PE-008 modes"   --   <bounded-permission-normalizer>
```

PE-007 serializes approved runtime mutations and verifies the target identity/session.

PE-007 does not itself prove content identity; the bounded normalizer must independently revalidate every precondition before changing the first mode.

## 8. Bounded normalizer behavior

The normalizer must:

1. Resolve the exact plugin root and refuse symlinked roots.
2. Walk the tree with `lstat`/equivalent, not follow symlinks.
3. Verify exact relative-path set against the accepted Task 8B manifest.
4. Verify every regular file's size and SHA-256.
5. Verify current modes, uid, gid, type, link count, and device.
6. Verify directory path set and current modes.
7. Produce the complete intended mode delta before mutation.
8. Refuse any delta except the exact known legacy-to-canonical mapping.
9. Change modes in deterministic order.
10. Record each successfully changed path and original mode.
11. If any chmod fails, rollback every already changed path to its recorded original mode in reverse order.
12. After all chmods, re-walk and verify:
   - path set unchanged;
   - bytes unchanged;
   - ownership unchanged;
   - object types unchanged;
   - modes canonical.
13. If post-verification fails, attempt rollback to the exact original mode map and stop.
14. Emit a machine-readable summary suitable for durable evidence.

The normalizer should be ephemeral for this one-time operation unless a separate source design decides it belongs in the repository. Do not add permanent production tooling merely to perform one legacy repair.

## 9. Rollback

Rollback means restoring only the original permission map captured immediately before mutation.

Original map is expected to be:

```text
53 files = 0664
bin/check.sh = 0775
16 descendant dirs = 0775
root = 0755
```

Rollback must not restore from assumptions; it uses the captured exact per-path mode map.

If rollback itself fails:

```text
PERMISSION_NORMALIZATION=BLOCKED
ROLLBACK=FAILED
```

Stop all further runtime mutation and preserve the state/evidence for manual investigation.

Do not continue to Person Runtime Activation after a rollback failure.

## 10. Post-normalization verification

Require:

```text
FILES_0644=53
bin/check.sh=0755
DESCENDANT_DIRS_0755=16
ROOT_MODE=0755

CONTENT_TREE_SHA256_UNCHANGED=YES
OWNER_GROUP_UNCHANGED=YES
PATH_SET_UNCHANGED=YES
SYMLINKS=0
UNSUPPORTED_OBJECTS=0
```

Then run the same PE-008 runtime fingerprint function/status path that previously failed.

PE-008 must now successfully fingerprint the current runtime tree.

Record:

```text
PE008_RUNTIME_FINGERPRINT=PASS
```

Also perform:

- one normal WordPress/WP-CLI bootstrap;
- one HTTP GET to `http://localhost:10039/`;
- immediate post-bootstrap fingerprint.

Expected:

```text
WORDPRESS_BOOT=PASS
HTTP_200=PASS
POST_BOOTSTRAP_METADATA_DELTA=NO
```

No Platform Core deployment is performed.

## 11. Evidence

Create append-only evidence:

```text
docs/architecture/PLATFORM_CORE_LEGACY_PERMISSION_NORMALIZATION_v1.0.0.md
```

and, if useful, a machine-readable companion:

```text
docs/architecture/PLATFORM_CORE_LEGACY_PERMISSION_NORMALIZATION_MANIFEST_v1.0.0.json
```

Mandatory result fields:

```text
PERMISSION_ROOT_CAUSE=LIKELY_LEGACY_TASK8B_STAGING

PERMISSION_NORMALIZATION=PASS|BLOCKED
PE007_COORDINATION=PASS|BLOCKED

PRE_CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
POST_CONTENT_TREE_SHA256=<sha>
CONTENT_TREE_SHA256_UNCHANGED=YES|NO

PRE_MODE_PATTERN=53x0664,1x0775,16dirsx0775,root0755
POST_MODE_PATTERN=53x0644,1x0755,16dirsx0755,root0755

OWNER_GROUP_UNCHANGED=YES|NO
PATH_SET_UNCHANGED=YES|NO
PE008_RUNTIME_FINGERPRINT=PASS|BLOCKED
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

Include the exact pre/post per-path mode maps and content hashes.

## 12. Relationship to PR #65 and Person Runtime Activation

PR #65 records the blocked Person activation attempt and should be merged independently once exact-head CI is green.

The permission normalization is a separate runtime remediation slice.

Sequence:

```text
merge PR #65
    ↓
legacy permission normalization
    ↓
independent evidence review
    ↓
merge permission-normalization evidence
    ↓
restart Person Runtime Activation from Stage 1
```

Do not amend the blocked Person activation evidence to make it appear successful.

Do not resume Phase 1 within the permission-normalization slice.

## 13. Acceptance gate

The slice passes only when:

```text
PERMISSION_ROOT_CAUSE=LIKELY_LEGACY_TASK8B_STAGING
PERMISSION_NORMALIZATION=PASS
PE007_COORDINATION=PASS

CONTENT_TREE_SHA256_UNCHANGED=YES
OWNER_GROUP_UNCHANGED=YES
PATH_SET_UNCHANGED=YES

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

Any failure blocks Person Runtime Activation until explicitly reviewed.
