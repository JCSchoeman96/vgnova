# FluentSnippets Bootstrap Output Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Version:** 0.1.1
**Date:** 2026-10-30

**Goal:** Remove the proven one-byte LF emitted during WordPress bootstrap by FluentSnippet `10-vg-bricks-medewerker-queries.php` through FluentSnippets' authoritative persistence path, while preserving all legacy behavior and keeping PE-008 strict.

**Architecture:** The remediation is intentionally split by an authority gate. Stage A is read-only: merge the immutable v1.3.0 evidence, discover the exact FluentSnippets record/storage ownership model and supported update/regeneration path on the installed Local runtime, capture rollback material, and prove the current RED condition. The agent must then STOP for independent review. Only a separately authorized Stage B may mutate the authoritative snippet under PE-007, verify generated-storage parity, require PE-008 health to turn GREEN, and prove protected state unchanged except for the authorized snippet bytes.

**Tech Stack:** WordPress 7.1.x, FluentSnippets runtime storage, PHP/WP-CLI, MariaDB, PE-007 mutation coordinator, PE-008 health helper/deployer tooling, Git/GitHub evidence workflow.

**Spec:** `docs/superpowers/specs/2026-10-30-fluentsnippets-bootstrap-output-remediation-design_v0.1.0.md`

## Global Constraints

- PR #69 is the immutable blocked v1.3.0 evidence record and must merge before remediation work begins.
- Exact approved PR #69 head: `e883386d1d5ac565814f05894660b90efdfe8d4b`.
- No runtime mutation until the authoritative FluentSnippets ownership/update path is proven and independently reviewed.
- Do not directly edit `wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php` as the primary fix.
- Do not use direct SQL as the primary mutation mechanism.
- Do not disable, retire, recreate, rename, or re-scope FluentSnippet 10.
- Preserve the five active helper modes and all current consumers.
- PE-008 remains strict; do not tolerate or strip bootstrap output in PE-008.
- Runtime writes must occur only under PE-007 after Stage B authorization.
- Historical v1.3.0 evidence remains append-only and unchanged.
- No ACF sync, Person schema/index setup, rebuild, reconcile, Bricks mutation, commerce/membership mutation, placeholder QA, or Golden Journey data.
- If ownership or supported persistence/regeneration cannot be proven, STOP with `FLUENTSNIPPETS_OWNERSHIP_PROVEN=FAIL`.
- If a post-mutation verification later fails, rollback must use the same proven FluentSnippets ownership path and exact captured pre-state.

## Review Focus

1. **Generated-file/source divergence:** prove which FluentSnippets record is authoritative and how the storage file is derived before allowing any write.
2. **Wrong snippet identity:** match record ID, title/name, filename mapping, active state, scope/load mode, and source hash—not filename alone.
3. **Semantic drift:** the fix may remove only the LF-producing PHP close/reopen boundary; five helper modes and execution semantics must remain identical.
4. **Regeneration side effects:** prove FluentSnippets updates only the intended generated file/record and no sibling snippets.
5. **False GREEN:** PE-008 health must pass with `no_unexpected_output=true` because bootstrap emits zero bytes, not because validation was relaxed.

---

## File Structure

### Repository files to create

```text
docs/superpowers/specs/2026-10-30-fluentsnippets-bootstrap-output-remediation-design_v0.1.0.md
docs/superpowers/plans/2026-10-30-fluentsnippets-bootstrap-output-remediation_v0.1.0.md
docs/architecture/FLUENTSNIPPETS_BOOTSTRAP_OUTPUT_REMEDIATION_v1.0.0.md
```

Optional structured companion after runtime work:

```text
docs/architecture/FLUENTSNIPPETS_BOOTSTRAP_OUTPUT_REMEDIATION_v1.0.0_DETAILS.json
```

### Runtime surface under investigation

```text
wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php
```

The runtime file is not automatically the authority. Stage A must prove the installed FluentSnippets ownership path first.

---

# Stage A — Read-only authority discovery and RED proof

### Task 1: Merge and pin the immutable v1.3.0 evidence

**Files:**
- Read only: PR #69 exact diff and checks
- No Local mutation

**Interfaces:**
- Consumes: exact PR #69 head `e883386d1d5ac565814f05894660b90efdfe8d4b`
- Produces: `REMEDIATION_BASE_SHA=<merged-main-sha>`

- [ ] **Step 1: Verify PR #69 exact head and exact-head CI**

Require:

```text
HEAD=e883386d1d5ac565814f05894660b90efdfe8d4b
Dashboard checks=SUCCESS
Canonical source checks=SUCCESS
VG Nova Platform Gate=SUCCESS
Publish Platform Core artifact=SKIPPED
```

Do not accept a different PR head without re-review.

- [ ] **Step 2: Require PR #69 to already be merged by the human operator**

The execution agent must not merge PR #69. If PR #69 is still open, STOP and return `PR69_MERGE_REQUIRED=YES`.

- [ ] **Step 3: Fetch and verify merge ancestry**

```bash
git fetch origin --prune
REMEDIATION_BASE_SHA="$(git rev-parse origin/main)"
git show --no-patch --pretty='%H%n%P' "$REMEDIATION_BASE_SHA"
```

Require merge parent 2 to equal:

```text
e883386d1d5ac565814f05894660b90efdfe8d4b
```

If not, STOP.

- [ ] **Step 4: Create an isolated worktree**

Use `superpowers:using-git-worktrees`.

Suggested branch:

```text
ops/fluentsnippets-bootstrap-output-remediation
```

Suggested worktree:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/fluentsnippets-bootstrap-output-remediation
```

Require exact `REMEDIATION_BASE_SHA` and clean status.

---

### Task 2: Install the approved design and plan in the isolated branch

**Files:**
- Create: `docs/superpowers/specs/2026-10-30-fluentsnippets-bootstrap-output-remediation-design_v0.1.0.md`
- Create: `docs/superpowers/plans/2026-10-30-fluentsnippets-bootstrap-output-remediation_v0.1.0.md`

**Interfaces:**
- Consumes: approved design + this plan
- Produces: repository-local binding authority for later evidence

- [ ] **Step 1: Copy the approved design verbatim into the spec path**

Require status `APPROVED`.

- [ ] **Step 2: Copy this implementation plan verbatim into the plan path**

Do not silently revise scope.

- [ ] **Step 3: Verify exact file hashes against handoff copies**

Record SHA-256 in the remediation evidence.

- [ ] **Step 4: Commit documentation scaffolding**

Suggested commit:

```bash
git add docs/superpowers/specs/2026-10-30-fluentsnippets-bootstrap-output-remediation-design_v0.1.0.md \
        docs/superpowers/plans/2026-10-30-fluentsnippets-bootstrap-output-remediation_v0.1.0.md
git commit -m "docs: add FluentSnippets bootstrap remediation design and plan"
```

---

### Task 3: Re-prove the frozen runtime baseline

**Files:**
- No repository source changes
- Create later evidence only

**Interfaces:**
- Produces: exact pre-remediation runtime/protected-state baseline

- [ ] **Step 1: Keep Local interactive freeze active**

No browser, wp-admin, editor, Bricks, or unrelated Local operations.

- [ ] **Step 2: Rediscover the current Local DB socket**

Do not reuse the prior path without revalidation.

Require:
- absolute path;
- active AF_UNIX socket;
- exact target still `vgnova-local-10039`.

- [ ] **Step 3: Verify target identity**

Prove:
- home/site `http://localhost:10039/`;
- DB `local`;
- DB host `localhost`;
- prefix `wp_`.

- [ ] **Step 4: Verify PE-008 state**

Require:

```text
in_progress=false
recovery_options=[]
deployment state directory absent
```

- [ ] **Step 5: Verify Platform Core runtime fingerprint**

Require the still-undeployed baseline from v1.3.0:

```text
file_count=54
content_sha256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
pe008_tree_sha256=856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56
```

Any drift = STOP.

- [ ] **Step 6: Re-run the exact protected snapshot collector**

Use the same collector/canonicalization/order used in v1.3.0.

Require exact equality with the final v1.3.0 protected snapshot unless an independently explained external change occurred. Any unexplained change = STOP.

- [ ] **Step 7: Recheck ACF pending inventory read-only**

Require the same four pending `Create` groups:

```text
group_vg_article_people
group_vg_course_people
group_vgmp_perk_details
group_vgmp_options
```

No sync.

---

### Task 4: Discover FluentSnippets authority and persistence path

**Files:**
- Read only: installed FluentSnippets plugin source/runtime tables/configuration
- Read only: `wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php`

**Interfaces:**
- Produces:
  - `SNIPPET_RECORD_ID`
  - `SNIPPET_AUTHORITY_KIND`
  - `SNIPPET_UPDATE_INTERFACE`
  - `SNIPPET_REGENERATION_INTERFACE`
  - exact pre-state and rollback material

- [ ] **Step 1: Identify the installed FluentSnippets plugin/version**

Record exact plugin slug/path/version and whether the storage directory is managed by that exact installation.

- [ ] **Step 2: Read installed FluentSnippets source to identify its data model**

Prove, from installed plugin code:
- persistence table/option/model used for snippets;
- record fields containing source/code;
- status/published field;
- run condition / execution scope fields;
- filename/storage mapping;
- code path that writes/regenerates `wp-content/fluent-snippet-storage/*.php`.

Do not infer names from internet documentation.

- [ ] **Step 3: Identify the exact authoritative record for runtime file 10**

Match on multiple independent attributes:
- exact record ID;
- exact title/name;
- exact generated filename or deterministic mapping;
- exact active/published state;
- exact execution scope/load mode;
- source/code bytes consistent with the runtime file;
- known helper-mode function/branch signatures.

If more than one record can plausibly map to file 10, STOP.

- [ ] **Step 4: Capture complete pre-change rollback material**

Capture:
- authoritative record fields needed to restore exact state;
- authoritative source/code bytes;
- authoritative source SHA-256;
- generated file bytes;
- generated file SHA-256;
- file owner/group/mode;
- snippet active/published state;
- execution scope/load mode;
- generated filename/path;
- five helper-mode inventory;
- hashes/state of every sibling FluentSnippet record and generated file, or at minimum a deterministic inventory sufficient to prove no sibling changed.

Secrets, if any unrelated fields exist, must not be committed.

- [ ] **Step 5: Prove the supported update path**

Identify the exact installed-plugin callable/service/model/action that:
1. updates the authoritative snippet code;
2. preserves the existing record identity;
3. preserves active/scope metadata unless explicitly supplied unchanged;
4. triggers or performs the normal generated-file persistence/regeneration.

Record the exact PHP class/function/method/action and its signature or invocation contract.

**Hard gate:**
- direct file edit alone is not accepted;
- direct SQL alone is not accepted;
- if no supported FluentSnippets-owned update/regeneration path is provable from installed code, STOP.

- [ ] **Step 6: Have a fresh reviewer inspect the ownership proof**

Reviewer must independently verify:
- exact record identity;
- authority kind;
- update API/callable;
- regeneration path;
- rollback feasibility;
- no need to alter PE-008.

Require:

```text
FLUENTSNIPPETS_OWNERSHIP_PROVEN=PASS
MUTATION_INTERFACE_PROVEN=PASS
ROLLBACK_INTERFACE_PROVEN=PASS
```

---

### Task 5: Re-prove RED without mutation

**Files:**
- No source/runtime mutation

**Interfaces:**
- Consumes: fresh socket + proven runtime state
- Produces: current RED evidence immediately before proposed fix

- [ ] **Step 1: Run PE-008 session helper through PE-007**

Expected:

```text
returncode=0
session_valid=true
```

- [ ] **Step 2: Run PE-008 health helper through PE-007**

Expected current RED:

```text
returncode=1
no_unexpected_output=false
no_php_diagnostics=true
all other health checks=true
stderr=""
```

If the health helper already passes, STOP; the defect changed externally.

- [ ] **Step 3: Run the exact output-buffer capture**

Require:

```text
length=1
hex=0a
base64=Cg==
sha256=01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b
```

If not exact, STOP.

- [ ] **Step 4: Reconfirm the exact source token**

Tokenize authoritative source and generated file.

Require one causal `T_INLINE_HTML` LF at the proven close/reopen boundary and no competing output source.

---

### Task 6: Stage A evidence and mandatory STOP

**Files:**
- Create: `docs/architecture/FLUENTSNIPPETS_BOOTSTRAP_OUTPUT_REMEDIATION_v1.0.0.md`
- Optional create: `docs/architecture/FLUENTSNIPPETS_BOOTSTRAP_OUTPUT_REMEDIATION_v1.0.0_DETAILS.json`

**Interfaces:**
- Produces a reviewable mutation authorization packet

- [ ] **Step 1: Write Stage A evidence**

Record:
- `REMEDIATION_BASE_SHA`;
- PR #69 merge ancestry;
- runtime/protected-state baseline;
- plugin/version;
- exact snippet record ID;
- authority kind;
- exact update interface;
- exact regeneration interface;
- exact rollback interface;
- authoritative/source/generated hashes;
- metadata/state;
- helper modes;
- sibling inventory;
- RED PE-008 result;
- exact one-byte capture.

- [ ] **Step 2: Include mandatory Stage A result block**

```text
PR69_EVIDENCE_MERGED=PASS
FLUENTSNIPPETS_OWNERSHIP_PROVEN=PASS|FAIL
AUTHORITATIVE_SNIPPET_IDENTITY=PASS|FAIL
MUTATION_INTERFACE_PROVEN=PASS|FAIL
REGENERATION_INTERFACE_PROVEN=PASS|FAIL
ROLLBACK_INTERFACE_PROVEN=PASS|FAIL
PRE_MUTATION_PROTECTED_STATE=PASS|FAIL
PE008_SESSION_RED_BASELINE=PASS|FAIL
PE008_HEALTH_RED_BASELINE=PASS|FAIL
BOOTSTRAP_CAPTURE_LENGTH=1|OTHER
BOOTSTRAP_CAPTURE_SHA256=01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b|OTHER
RUNTIME_MUTATIONS=0
ACF_SYNCS=0
PERSON_SCHEMA_MUTATIONS=0
BRICKS_MUTATIONS=0
MUTATION_AUTHORIZATION=REQUIRED
```

- [ ] **Step 3: Run repository checks**

```bash
./site-code/bin/check-source.sh
git diff --check
git diff --cached --check
```

- [ ] **Step 4: Commit/push Stage A evidence to a remediation PR**

Suggested title:

```text
ops: prepare FluentSnippets bootstrap output remediation
```

- [ ] **Step 5: STOP**

Do not mutate FluentSnippets.

Return the exact PR/head and ownership/update/rollback evidence for independent review.

**Stage B is not authorized by this execution run.**

---

# Stage B — Mutation and GREEN verification
#
# This section is the approved implementation plan after the Stage A authority gate.
# A new execution prompt must explicitly authorize it after independent review.

### Task 7: Independently authorize the exact mutation

**Files:**
- Read Stage A evidence
- No mutation until approval

**Interfaces:**
- Consumes Stage A proof
- Produces an exact authorized mutation contract

- [ ] **Step 1: Pin exact remediation PR head containing Stage A evidence**

- [ ] **Step 2: Independently verify the installed-plugin update/regeneration/rollback path**

- [ ] **Step 3: Define the exact source transformation**

The transformation may only remove the causal PHP close/reopen boundary or equivalent inline-HTML LF while keeping PHP execution continuous.

Require:
- helper modes identical;
- control flow identical;
- no formatting cleanup outside the causal boundary;
- no metadata change.

- [ ] **Step 4: Record the exact pre-hash -> expected post-hash transformation before mutation**

If the exact bytes cannot be determined from Stage A evidence, STOP.

---

### Task 8: Apply the authoritative FluentSnippets mutation under PE-007

**Files:**
- Runtime authoritative FluentSnippets record only
- FluentSnippets-managed generated file as a derived side effect

**Interfaces:**
- Consumes exact approved update callable and byte transformation
- Produces updated authoritative record + generated runtime file

- [ ] **Step 1: Re-prove all pre-mutation guards**

Require:
- main/base unchanged as authorized;
- Local target exact;
- socket fresh/active;
- PE-008 state clean;
- Platform Core fingerprint unchanged;
- protected snapshot unchanged;
- snippet pre-hashes and metadata exact;
- sibling inventory exact.

Any mismatch = STOP.

- [ ] **Step 2: Enter PE-007 mutation session**

Use the standard coordinator with exact target/wp-root/socket.

- [ ] **Step 3: Call only the proven FluentSnippets update interface**

Update only the authoritative code/source field for the existing snippet record.

Supply unchanged identity/status/scope values if the API requires them.

- [ ] **Step 4: Allow only the proven normal FluentSnippets regeneration path**

Do not manually patch the generated file after the authoritative update.

- [ ] **Step 5: Exit PE-007 cleanly**

---

### Task 9: Verify authoritative/generated parity and semantic invariants

- [ ] **Step 1: Re-read the authoritative record**

Require:
- same ID;
- same title/name;
- same active/published state;
- same scope/load mode;
- source differs only by the authorized minimal boundary edit.

- [ ] **Step 2: Re-read generated file**

Require:
- expected generated path;
- no causal `T_INLINE_HTML` LF;
- PHP syntax valid;
- exact expected post-hash.

- [ ] **Step 3: Verify sibling FluentSnippets inventory**

Require no unintended sibling record/file changes.

- [ ] **Step 4: Verify five helper modes**

Use static inventory and focused read-only invocation/behavior checks where safe.

Require exact same mode names and existing behavior contracts.

---

### Task 10: Prove GREEN through PE-008

- [ ] **Step 1: Run direct bootstrap buffer capture**

Require:

```text
BOOTSTRAP_CAPTURE_LENGTH=0
```

- [ ] **Step 2: Run PE-008 session through PE-007**

Require return code 0 and session valid.

- [ ] **Step 3: Run PE-008 health through PE-007**

Require return code 0 and every check true, especially:

```text
wordpress_bootstrapped=true
no_php_diagnostics=true
no_unexpected_output=true
session_valid=true
runtime_identity=true
plugin_entrypoint_active=true
plugin_class_loaded=true
lifecycle_registered=true
site_name_available=true
```

Require stderr empty and canonical JSON stdout.

- [ ] **Step 4: Do not deploy Platform Core**

This remediation proves health only. Person runtime activation is a later v1.4.0 run.

---

### Task 11: Protected post-state and rollback decision

- [ ] **Step 1: Re-run exact protected snapshot**

Require equality with pre-state except authorized FluentSnippets record/generated-file representation.

- [ ] **Step 2: Verify Platform Core fingerprint unchanged**

- [ ] **Step 3: Verify PE-008 deployment state unchanged/absent**

- [ ] **Step 4: Verify ACF pending inventory unchanged**

No sync.

- [ ] **Step 5: Decide terminal state**

If all checks pass:

```text
FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=PASS
```

If mutation occurred and any verification fails:
- restore exact authoritative pre-state through the same proven FluentSnippets interface;
- let the normal regeneration path restore the file;
- verify pre-hashes/state;
- record:

```text
FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=ROLLED_BACK
```

If blocked before mutation:

```text
FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=BLOCKED
```

---

### Task 12: Final evidence PR

- [ ] **Step 1: Complete append-only remediation evidence**

Required result block on success:

```text
PR69_EVIDENCE_MERGED=PASS
FLUENTSNIPPETS_OWNERSHIP_PROVEN=PASS
AUTHORITATIVE_SNIPPET_IDENTITY=PASS
PRE_MUTATION_STATE=PASS
PE007_MUTATION_COORDINATION=PASS
SNIPPET_SOURCE_MINIMAL_DIFF=PASS
SNIPPET_ACTIVE_STATE_UNCHANGED=PASS
SNIPPET_EXECUTION_SCOPE_UNCHANGED=PASS
SNIPPET_HELPER_MODES_UNCHANGED=PASS
GENERATED_STORAGE_PARITY=PASS
BOOTSTRAP_CAPTURE_LENGTH=0
PE008_SESSION=PASS
PE008_HEALTH=PASS
PE008_NO_UNEXPECTED_OUTPUT=PASS
PE008_NO_PHP_DIAGNOSTICS=PASS
PLATFORM_CORE_RUNTIME_FINGERPRINT_UNCHANGED=PASS
PROTECTED_STATE=PASS
ACF_SYNC_PERFORMED=NO
PERSON_SCHEMA_MUTATION=NO
BRICKS_MUTATIONS=0
REPRESENTATIVE_DATA_MUTATIONS=0
FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=PASS
```

- [ ] **Step 2: Run full repository checks**

```bash
./site-code/bin/check-source.sh
git diff --check
git diff --cached --check
```

- [ ] **Step 3: Fresh whole-run reviewer**

Review exact runtime evidence, mutation authority, pre/post hashes, health output, rollback status, and repository diff.

- [ ] **Step 4: Push/open final remediation evidence PR and STOP**

Do not begin Person Runtime Activation v1.4.0 in the same run.

---

# Acceptance Gate

The remediation is complete only after Stage B independently passes every acceptance criterion from the approved design.

Stage A by itself can only end in:

```text
MUTATION_AUTHORIZATION=REQUIRED
```

It must never be treated as remediation success.
