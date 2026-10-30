# Platform Core legacy permission normalization evidence v1.0.0

## Scope and source

This record covers only the one-time normalization of permission metadata for the active Platform Core runtime tree. It does not restart Person Runtime Activation.

- Source SHA: 3e226294e8f72a859e50e5b9a8deb3023ec3ed6a
- PR #65 approved head: ad3dc708789494dfebf53e98ef7fec13277ac019
- origin/main was freshly fetched and matched the required source SHA before the live operation.
- Root cause classification remains LIKELY_LEGACY_TASK8B_STAGING. Historical evidence does not prove the classification.
- Runtime target: vgnova-local-10039
- WordPress root: /home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/
- Plugin root: /home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core
- The approved design and plan were copied to their canonical paths. Five header line breaks use explicit <br> tags instead of trailing spaces so the staged whitespace check stays clean; the rendered line breaks are unchanged.

## Preflight and accepted content

The Local run directory was freshly searched for MySQL sockets. The socket /home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock identified vgnova-local-10039. The other discovered socket, /home/jcschoeman96/.config/Local/run/KHpXkBuV8/mysql/mysqld.sock, returned TARGET_IDENTITY_MISMATCH from PE-007 status.

Before mutation, PE-007 showed the host lock free and both database locks free. PE-008 read-only status returned in_progress=false and no recovery options. A fresh preflight reviewer passed.

The committed Task 8B manifest at docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_MANIFEST_v1.0.0.json has SHA-256 e596c498428098a06fc4dd7bf6562e8345e456e1a4c2cc39239a8f9fe01c1365. Its 54 file records reproduced accepted content-only tree digest a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760.

The full pre-state snapshot is recorded in the accompanying JSON manifest. It captured all 71 paths with path, object type, mode, uid, gid, device, inode, hard-link count, size, mtime, ctime, SHA-256, and ACL entries. All 54 files matched accepted size and hash. The tree had 16 descendant directories, uid/gid 1000:1000 throughout, one device, no symlinks or unsupported objects, single-link regular files, no extended access ACLs, and no default ACLs. The pre-state snapshot digest is b728462a3950828856e6fd815902272ec36496705cd2597fead34f13ff726dc4.

## Normalizer probes and review

The one-time normalizer lived outside the repository at /tmp/vgnova-platform-core-permission-normalizer-20261029.py, SHA-256 a951c906f64ee3a97252549815b2c7085c8bdbfc0cbc1a3b6f71ea1bb286b21b. A fresh independent normalizer reviewer passed its scope, gates, and rollback logic.

- Dry-run probe: PASS. It planned 70 mode changes and changed no modes.
- Success probe: PASS. It changed 54 files and 16 directories on a throwaway tree. The root remained 0755.
- Failure probe: injected failure after 60 mode operations and exited with the expected failure code 23. Rollback restored the exact captured per-path mode map. Result: INJECTED_FAILURE_ROLLBACK_PASS.
- Probe output hashes and full results are in the JSON manifest.

## Live operation and verification

Interactive Local admin use was paused for the runtime window. Immediately before the change, the source SHA, normalizer SHA, accepted manifest SHA, socket identity, PE-007 lock state, PE-008 status, and full pre-state were checked again. Every live precondition passed.

The bounded normalizer ran as a child of site-code/bin/vgnova-runtime-mutate.php under PE-007. The coordinator released normally with child exit code 0. The normalizer changed exactly 53 regular files from 0664 to 0644, bin/check.sh from 0775 to 0755, and 16 descendant directories from 0775 to 0755. The plugin root stayed 0755. Live rollback was not required.

The full post-state matched these invariants:

- The path set stayed unchanged.
- All file bytes, sizes, and the content-only tree digest stayed unchanged.
- Uid/gid, object types, device IDs, inodes, hard-link counts, and mtime stayed unchanged.
- ACL state had no extended access ACL or default ACL. Access entries matched the normalized modes.
- The immediate post-state snapshot digest is 8047ca4d57608e4fa80f827ce0b2c07b9d80811ecb9db89402ad67630bee61c5.

PE-008 fingerprint_tree() passed on the runtime plugin root. Its canonical mode-aware tree SHA-256 is 856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56. PE-008 status after normalization showed no in-progress or recovery state.

One preliminary WP-CLI launch used PHP's default socket and stopped before the read-only eval expression with Error establishing a database connection. The completed bootstrap used mysqli.default_socket set to the freshly verified Local socket. It returned WordPress 7.1.4, the expected home and site URLs, and confirmed Platform Core is active. No write command ran.

One GET to http://localhost:10039/ returned HTTP 200. The immediate post-bootstrap full snapshot matched the post-normalization entry map exactly, and the post-bootstrap PE-008 fingerprint equaled the pre-bootstrap fingerprint. The post-bootstrap snapshot digest is 060160b5ba44d996996419952423963e8b463b8389a029a4d1f070b6a7077f03.

## Required result block

~~~text
SOURCE_SHA=3e226294e8f72a859e50e5b9a8deb3023ec3ed6a
PERMISSION_ROOT_CAUSE=LIKELY_LEGACY_TASK8B_STAGING

PERMISSION_NORMALIZATION=PASS
PE007_COORDINATION=PASS

PRE_CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
POST_CONTENT_TREE_SHA256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
CONTENT_TREE_SHA256_UNCHANGED=YES

PRE_MODE_PATTERN=53x0664,1x0775,16dirsx0775,root0755
POST_MODE_PATTERN=53x0644,1x0755,16dirsx0755,root0755

OWNER_GROUP_UNCHANGED=YES
PATH_SET_UNCHANGED=YES
OBJECT_TYPES_UNCHANGED=YES

PE008_RUNTIME_FINGERPRINT=PASS
PE008_RUNTIME_TREE_SHA256=856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56
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
~~~

The live scope counters are all zero. The probe rollback proof passed. No deployment, ACF sync, Person relationship change, Bricks change, commerce change, or membership change was run.

The accompanying PLATFORM_CORE_LEGACY_PERMISSION_NORMALIZATION_MANIFEST_v1.0.0.json contains the full captured pre-state, post-state, post-bootstrap state, per-path modes, probe results, PE-007 and PE-008 results, and fingerprint outputs.

## Repository checks

- ./site-code/bin/check-source.sh: PASS.
- git diff --check: PASS.
- git diff --cached --check: PASS.
