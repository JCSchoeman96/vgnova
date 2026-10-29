# VGNova ACF runtime source cutover v1.0.0

## Result

```text
PR_63_MERGED=YES
OLD_ACF_SYMLINK_TARGET=/home/jcschoeman96/projects/current/vgnova/site-code/acf-json
NEW_ACF_SYMLINK_TARGET=/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
ACF_RUNTIME_SOURCE_SHA=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
ACF_RUNTIME_SOURCE_STATUS=CLEAN
ACF_RUNTIME_SOURCE_DETACHED_HEAD=YES
ACF_RUNTIME_JSON_PARITY=PASS
RUNTIME_SYMLINK=PASS
PE007_SESSION_PROOF=PASS
PE007_CUTOVER=PASS
ACF_STATUS_READ_ONLY=PASS
TARGETED_ACF_STATUS_READ_ONLY=PASS
ACF_SYNCS_PERFORMED=0
WORDPRESS_DATA_MUTATIONS=0
BRICKS_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
PERSON_RELATIONSHIP_MUTATIONS=0
PERSON_RUNTIME_ACTIVATION_RESUMED=NO
```

The only Local runtime object changed was the Bricks Child `acf-json` symlink. It was replaced atomically under PE-007. No rollback was required. No ACF sync, Platform Core deployment, database write, Bricks edit, Person schema operation, or Person relationship assignment occurred.

## PR #63 prerequisite and source pin

Before merging PR #63, `origin/main` and the PR head were freshly fetched. The PR head was unchanged at `b9349ceed8289d8f9009687e6c2c2ce37225e710`, its diff contained exactly one added evidence file (`docs/architecture/SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.0.0.md`), and `git diff --check` passed. The report records `RUNTIME_ACTIVATION=BLOCKED` at the dirty normal-checkout ACF source gate and states that no Local runtime mutation occurred.

Exact-head workflow results were:

| Check | Result |
|---|---|
| Dashboard checks | success |
| Canonical source checks | success |
| VG Nova Platform Gate | success |
| Publish Platform Core artifact | skipped |

PR #63 was merged as merge commit `5a3bc4fc89fa61ae8916aa460989f1e8912b473d`, with parents `bfd6c98f7375a47307311ed534f273024b949e30` and `b9349ceed8289d8f9009687e6c2c2ce37225e710`. A fresh fetch after merge returned that exact `origin/main`; it is the `ACF_RUNTIME_SOURCE_SHA` used below.

## Dedicated runtime worktree

The requested destination did not exist before creation. The worktree was created detached at the post-PR-#63 merge SHA:

```bash
git worktree add --detach \
  /home/jcschoeman96/projects/runtime/vgnova-acf-source \
  5a3bc4fc89fa61ae8916aa460989f1e8912b473d
```

Before and after the symlink cutover, read-only Git checks returned:

```text
HEAD=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
DETACHED_HEAD=YES
STATUS=CLEAN
ACF_JSON_FILES=14
```

The inventory compared each worktree file byte-for-byte with `git cat-file blob <sha>:<path>` at the exact pinned commit. The normal checkout's `site-code/acf-json` status was empty, and its 14 files also matched these exact bytes before cutover. The normal checkout itself remained dirty (`M AGENTS.md`, `?? .agents/`) and was not edited, cleaned, reset, or stashed.

| SHA-256 | Git path |
|---|---|
| `3f1f0b119c9619c7df3584f780e76685c1ea463d35c2a1ece48d617792fd4e33` | `site-code/acf-json/group_vg_article_people.json` |
| `ae9353b6f916dec5f17ad662e71ca602590038b9aaca37f7656d573757bb386a` | `site-code/acf-json/group_vg_course_people.json` |
| `ec32c95f75b9b1c6599b09312fa0b71a9806044277c0cd7865519df37ace7030` | `site-code/acf-json/group_vg_editorial_classification.json` |
| `09232c12e7d7cce7ee7c66c9fef4c699410297315112b883ca82b01d842d71c7` | `site-code/acf-json/group_vg_event_participation_details.json` |
| `d37ec3c5d908dd6497fd953319375b641a1534574e83b600eff0bfc702be8e95` | `site-code/acf-json/group_vg_person_details.json` |
| `3ed2d1a00e3c224adcb7f51e33fa82ed7d5694c5f33a9503da446faaa00f1c03` | `site-code/acf-json/group_vg_product_contribution_details.json` |
| `434025a42c7c1be6ee20949bf45b0442f3226ff6ce91c99e6cfad4a8ab15816a` | `site-code/acf-json/post_type_event_participation.json` |
| `222dc1839d2a0ffe891aadd4c84fc68a41cbb6cb72667c57ad14cdac4abe583a` | `site-code/acf-json/post_type_person.json` |
| `d4c7d7ba3ef416e68f21e66ec366dee28e83307739a429c5895b382c15ebc506` | `site-code/acf-json/post_type_product_contribution.json` |
| `a6bb5c6a4d3b8ba3639e6288405c3b913e683c917e041b0d0c2b9735f34e41b4` | `site-code/acf-json/taxonomy_editorial_collection.json` |
| `90ad05e7cba39ea9f0f5e57f36e23990cc5074c25a83e2ad1f2c7273b8d22234` | `site-code/acf-json/taxonomy_editorial_format.json` |
| `e9794d6b6f6be6ff563f5e13c0328d91fa29988434b0d3ed3c0d35834330a895` | `site-code/acf-json/taxonomy_event_role.json` |
| `d32dba47956594771ce12e5b0eb6c4b61a866d7a90dcce679a777ffc57d9b8c2` | `site-code/acf-json/taxonomy_person_type.json` |
| `d371c6a1ac769b7491b810d04080991ba638258781bce320e0f4f7b842cb144e` | `site-code/acf-json/taxonomy_product_role.json` |

The two Person relationship group definitions are present in the inventory. No JSON file was edited during this slice.

## Target and old-link baseline

The registered target `vgnova-local-10039` has:

```text
home=http://localhost:10039
siteurl=http://localhost:10039
database=local
database host=localhost
```

The `localhost` Novamira MCP connector reported `home` and `siteurl` as `http://localhost:10049`, so it was not used as target evidence. The target was inspected with read-only WP-CLI using Local's socket paired with its port-10039 Nginx configuration. The target reported ACF Pro 6.8.1. `.devcore/project.conf` and `.devcore/render-map.tsv` are absent in the repository.

Before the cutover:

```text
readlink=/home/jcschoeman96/projects/current/vgnova/site-code/acf-json
readlink -f=/home/jcschoeman96/projects/current/vgnova/site-code/acf-json
stat=symbolic link|777|1000|1000|66306|110537011
```

The ACF runtime settings returned before the change were:

```json
{
  "load_json": [
    "/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json",
    "/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-member-perks/acf-json"
  ],
  "save_json": "/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json"
}
```

The read-only status command before cutover was:

```bash
php -d mysqli.default_socket='/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock' \
  /usr/local/bin/wp \
  --path='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/' \
  acf json status --type=field-group --detailed
```

Its output was:

```text
4 item(s) pending sync. Run `wp acf json sync` to apply changes.
Key                              Title                 Type         Action
group_vg_article_people          Article People        field-group  Create
group_vg_course_people           Course People         field-group  Create
group_vgmp_perk_details          Member Perk Details   field-group  Create
group_vgmp_options               Voelgoed Perk Settings field-group  Create
```

These are pending JSON definitions only. No sync was run.

## PE-007 cutover

Immediately before the mutation, PE-007 read-only status reported target `vgnova-local-10039`, filesystem lock free, and both database locks free. The Local database socket was passed as the coordinator's transport override; target identity still matched the registered home, site, database, and host.

The status command exited `0`; its lock state was:

```json
{
  "target": "vgnova-local-10039",
  "filesystem_lock_held": false,
  "database_locks": {
    "a": {"state": "free", "held": false},
    "b": {"state": "free", "held": false}
  }
}
```

It was run as:

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/' \
  --db-socket='/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock' \
  --status
```

The bounded child command was run from the dedicated worktree through:

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/" \
  --db-socket="/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock" \
  --purpose="Cut over ACF Local JSON to the dedicated exact-commit runtime source" \
  -- \
  python3 /tmp/vgnova-acf-runtime-symlink-cutover.py \
    --link '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json' \
    --old-lexical '/home/jcschoeman96/projects/current/vgnova/site-code/acf-json' \
    --old-resolved '/home/jcschoeman96/projects/current/vgnova/site-code/acf-json' \
    --old-device 66306 \
    --old-inode 110537011 \
    --new-target '/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json'
```

The temporary script was outside both runtime source trees. Its pre-mutation dry-run passed with `mutation_performed=false`. The executed script SHA-256 was `f283d82e3ea14ec40fcf787dd8f3bfd38f9a972c2f799386510fe54a474bd953`; its source was:

```python
#!/usr/bin/env python3
import argparse
import json
import os
import stat
import sys
import uuid
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--link', required=True)
parser.add_argument('--old-lexical', required=True)
parser.add_argument('--old-resolved', required=True)
parser.add_argument('--old-device', type=int, required=True)
parser.add_argument('--old-inode', type=int, required=True)
parser.add_argument('--new-target', required=True)
parser.add_argument('--dry-run', action='store_true')
args = parser.parse_args()

link = Path(args.link)
new = Path(args.new_target)

def link_metadata(path: Path) -> dict[str, int]:
    info = path.lstat()
    return {
        'mode': stat.S_IMODE(info.st_mode),
        'uid': info.st_uid,
        'gid': info.st_gid,
        'device': info.st_dev,
        'inode': info.st_ino,
    }

def require(condition: bool, message: str) -> None:
    if not condition:
        raise RuntimeError(message)

try:
    require(link.is_symlink(), 'runtime ACF path is no longer a symlink')
    old_lexical = os.readlink(link)
    old_resolved = str(link.resolve(strict=True))
    require(old_lexical == args.old_lexical, 'runtime symlink lexical target changed')
    require(old_resolved == args.old_resolved, 'runtime symlink resolved target changed')
    require(new.is_dir() and not new.is_symlink(), 'new ACF target must be an existing non-symlink directory')
    new_resolved = str(new.resolve(strict=True))
    require(new_resolved == args.new_target, 'new ACF target resolves somewhere unexpected')
    require(link.parent.is_dir(), 'runtime symlink parent is unavailable')
    before = link_metadata(link)
    require(before['device'] == args.old_device and before['inode'] == args.old_inode, 'runtime symlink inode changed since preflight')
    if args.dry_run:
        print(json.dumps({
            'result': 'DRY_RUN_PASS',
            'old_lexical_target': old_lexical,
            'old_resolved_target': old_resolved,
            'new_target': str(new),
            'new_resolved_target': new_resolved,
            'link_metadata_before': before,
            'mutation_performed': False,
        }, sort_keys=True))
        raise SystemExit(0)

    temporary = link.parent / f'.{link.name}.cutover.{os.getpid()}.{uuid.uuid4().hex}'
    try:
        os.symlink(str(new), temporary)
        require(temporary.is_symlink(), 'temporary path is not a symlink')
        require(os.readlink(temporary) == args.new_target, 'temporary symlink lexical target mismatch')
        require(str(temporary.resolve(strict=True)) == args.new_target, 'temporary symlink resolved target mismatch')
        require(link.is_symlink(), 'runtime path changed type during staging')
        require(os.readlink(link) == args.old_lexical, 'runtime symlink lexical target changed during staging')
        require(str(link.resolve(strict=True)) == args.old_resolved, 'runtime symlink resolved target changed during staging')
        current = link_metadata(link)
        require(current['device'] == before['device'] and current['inode'] == before['inode'], 'runtime symlink inode changed during staging')
        os.replace(temporary, link)
        require(link.is_symlink(), 'final runtime path is not a symlink')
        new_lexical = os.readlink(link)
        final_resolved = str(link.resolve(strict=True))
        require(new_lexical == args.new_target, 'final symlink lexical target mismatch')
        require(final_resolved == args.new_target, 'final symlink resolved target mismatch')
        print(json.dumps({
            'result': 'CUTOVER_PASS',
            'old_lexical_target': old_lexical,
            'old_resolved_target': old_resolved,
            'new_lexical_target': new_lexical,
            'new_resolved_target': final_resolved,
            'link_metadata_before': before,
            'link_metadata_after': link_metadata(link),
            'temporary_link_name': temporary.name,
            'atomic_method': 'os.replace_same_directory',
            'mutation_performed': True,
        }, sort_keys=True))
    finally:
        if os.path.lexists(temporary):
            require(temporary.is_symlink(), 'temporary cutover path changed type; refusing cleanup')
            temporary.unlink()
except SystemExit:
    raise
except Exception as error:
    print(json.dumps({'result': 'CUTOVER_FAIL', 'error': str(error)}, sort_keys=True), file=sys.stderr)
    sys.exit(1)
```

The child rechecked the old symlink target and inode, created a temporary symlink in the same parent directory, verified that temporary target, and atomically replaced the link with `os.replace`. The PE-007 command exited `0` and its child returned:

```json
{
  "result": "CUTOVER_PASS",
  "atomic_method": "os.replace_same_directory",
  "old_lexical_target": "/home/jcschoeman96/projects/current/vgnova/site-code/acf-json",
  "old_resolved_target": "/home/jcschoeman96/projects/current/vgnova/site-code/acf-json",
  "new_lexical_target": "/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json",
  "new_resolved_target": "/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json",
  "link_metadata_before": {"device": 66306, "gid": 1000, "inode": 110537011, "mode": 511, "uid": 1000},
  "link_metadata_after": {"device": 66306, "gid": 1000, "inode": 110547009, "mode": 511, "uid": 1000},
  "temporary_link_name": ".acf-json.cutover.1055804.b568f444acbb46a78843c1c065631684",
  "mutation_performed": true
}
```

The temporary link was consumed by the atomic rename. Immediate independent checks returned:

```text
readlink=/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
readlink -f=/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
stat=symbolic link|777|1000|1000|66306|110547009
```

The new symlink resolves exactly to the required target. No rollback was needed.

## Post-cutover ACF status and source verification

The only ACF CLI command run after cutover was:

```bash
php -d mysqli.default_socket='/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock' \
  /usr/local/bin/wp \
  --path='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/' \
  acf json status --type=field-group --detailed
```

It exited `0` and returned the same four-item pending inventory as before:

```text
4 item(s) pending sync. Run `wp acf json sync` to apply changes.
Key                              Title                 Type         Action
group_vg_article_people          Article People        field-group  Create
group_vg_course_people           Course People         field-group  Create
group_vgmp_perk_details          Member Perk Details   field-group  Create
group_vgmp_options               Voelgoed Perk Settings field-group  Create
```

Before and after status inventories are identical. No `acf json sync` command was run.

After ACF status, the dedicated runtime worktree still reported the exact SHA, detached HEAD, clean status, and 14-file Git-object parity. The two Person group files remain present and unchanged. The active runtime link resolves to the dedicated worktree, so the normal checkout is no longer an ACF runtime dependency.

## Scope and verification

This slice did not resume Simple Person Relationship Runtime Activation. It did not change WordPress database data, Bricks content, Platform Core, Person schema/index state, commerce, membership state, or any ACF JSON definition. The normal checkout's unrelated dirty files were left as found.

The approved design and execution plan are included in this evidence branch at their canonical `docs/superpowers/` paths. The plan's existing Markdown hard break after the version line was represented as `<br>` to satisfy `git diff --check`; no plan wording or steps changed. `./site-code/bin/check-source.sh` exited `0` for source commit `5a3bc4fc89fa61ae8916aa460989f1e8912b473d` using CPython 3.14.4. It ended with `PASS: two exact-commit builds, independent archive verification, negative cases, and cleanup`.

| Reproducible source-gate build | Files | Tree SHA-256 | Archive SHA-256 | Manifest SHA-256 |
|---|---:|---|---|---|
| A | 66 | `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373` | `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9` | `b904405fda301101b01e26910da5559b107b6e80b2a638f60f29587354b7a8cf` |
| B | 66 | `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373` | `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9` | `b904405fda301101b01e26910da5559b107b6e80b2a638f60f29587354b7a8cf` |

These reproducibility builds were temporary source-check outputs. They were not deployed. The final evidence tree passed `git diff --check`.

```text
./site-code/bin/check-source.sh=PASS
git diff --check=PASS
```
