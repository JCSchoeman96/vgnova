# FluentSnippets Bootstrap Output Remediation Design v0.1.0

**Date:** 2026-10-30  
**Status:** APPROVED  
**Scope:** Minimal remediation of the proven one-byte WordPress bootstrap output emitted by FluentSnippet `10-vg-bricks-medewerker-queries.php`  
**Runtime:** VGNova Local target `vgnova-local-10039`

## 1. Intent

Remove the single proven LF byte emitted during WordPress bootstrap by the active FluentSnippet:

`wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php`

without weakening PE-008, disabling or retiring the snippet, changing its five active helper modes, or altering unrelated WordPress/runtime state.

The remediation must operate through the authoritative FluentSnippets ownership path rather than treating the generated storage file as independently authoritative.

## 2. Proven problem

The v1.3.0 activation attempt remained blocked because PE-008 health reported:

`no_unexpected_output=false`

Subsequent read-only diagnostics proved:

- two independent PE-007 sessions captured exactly one byte;
- captured byte: LF / `0x0A`;
- SHA-256: `01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b`;
- PHP tokenization found one exact matching `T_INLINE_HTML` source;
- source: `wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php:26`;
- the preceding `ABSPATH` guard does not return during a normal successful WordPress bootstrap;
- the execution path therefore reaches and emits the inline LF;
- no competing matching inline-output source was found.

`CAUSALITY=PROVEN`.

PE-008 is therefore behaving correctly and must remain strict.

## 3. Goals

1. Remove only the proven bootstrap-output defect.
2. Preserve all existing behavior of FluentSnippet 10 except the unintended output byte.
3. Preserve the snippet's active state, execution scope, helper modes, identifiers, and runtime consumers.
4. Keep FluentSnippets as the ownership authority for the snippet.
5. Verify that the generated/runtime storage representation matches the authoritative FluentSnippets record after the change.
6. Re-run the exact PE-008 session and health preflight under PE-007 and require `no_unexpected_output=true`.
7. Prove protected runtime state remains unchanged except for the intended snippet representation.
8. Preserve all v1.3.0 evidence as immutable historical evidence.
9. Start any later Person relationship runtime activation as a fresh v1.4.0 attempt.

## 4. Non-goals

This remediation does **not**:

- retire `10-vg-bricks-medewerker-queries.php`;
- refactor its five active helper modes;
- migrate Template 772 or Product Single 124;
- alter PE-008 output validation;
- whitelist whitespace or tolerate bootstrap output;
- modify Platform Core source;
- sync ACF groups;
- create Person relationship schema/indexes;
- rebuild or reconcile relationships;
- perform placeholder QA;
- create real Golden Journey data;
- change Bricks templates/components;
- perform unrelated FluentSnippet cleanup.

## 5. Anti-goals

The remediation is considered a failure even if PE-008 passes if any of these occur:

- the generated storage file is edited directly while FluentSnippets' authoritative record remains different;
- the snippet is disabled, retired, recreated under a new identity, or otherwise semantically changed;
- helper-mode behavior changes;
- PE-008 is relaxed to tolerate output;
- unrelated FluentSnippets or WordPress data changes;
- the v1.3.0 evidence record is rewritten to imply the original activation succeeded;
- the runtime change occurs outside PE-007 mutation coordination.

## 6. Approaches considered

### Approach A — Authoritative FluentSnippets mutation **(approved)**

Inspect how FluentSnippets owns snippet 10, identify the authoritative record and supported update path, mutate only the source representation responsible for the inline LF, allow FluentSnippets to regenerate/update its storage file normally, then verify parity and PE-008 health.

**Advantages**
- preserves ownership boundaries;
- avoids generated-file drift;
- minimizes future regeneration risk;
- produces a defensible audit trail.

**Tradeoff**
- requires a read-only ownership inspection before mutation because the exact FluentSnippets persistence/update mechanism must be proven rather than assumed.

### Approach B — Directly patch the generated storage file

Rejected. It risks divergence from the FluentSnippets authoritative record and later overwrite/regeneration.

### Approach C — Relax PE-008 output rules

Rejected. PE-008 correctly detected a real bootstrap defect. Allowing whitespace would weaken an important deployment invariant and could hide future defects.

## 7. Authority and sequencing

### 7.1 PR #69 first

PR #69 is the immutable blocked v1.3.0 evidence record.

Before any remediation mutation:

1. verify PR #69 exact head;
2. verify exact-head CI is complete and green;
3. independently re-review the final exact diff;
4. merge PR #69;
5. verify `main` advanced and merge parent 2 equals the exact approved PR head.

No runtime remediation begins before this gate passes.

### 7.2 FluentSnippets ownership discovery

After PR #69 is merged, perform read-only inspection to establish:

- the exact snippet record/ID corresponding to `10-vg-bricks-medewerker-queries.php`;
- active/published state;
- execution scope/load mode;
- authoritative stored source;
- how FluentSnippets maps that record to the generated storage file;
- the supported mutation path;
- pre-change hashes/metadata for both authoritative record and generated file.

Do not infer these details from filename alone.

## 8. Intended source change

The change must be the smallest source-level modification that removes the PHP close/reopen boundary or equivalent inline-HTML region responsible for the LF.

The exact textual edit is deliberately **not pre-prescribed** until the authoritative FluentSnippets source is read.

Acceptable result:

- no `T_INLINE_HTML` LF is emitted at that location;
- all PHP logic remains semantically identical;
- no helper mode is added, removed, renamed, reordered, or behaviorally altered;
- no unrelated formatting cleanup is bundled.

Preferred implementation principle:

> keep the file inside PHP continuously rather than relying on a close/reopen tag boundary that can emit whitespace.

If the authoritative stored source differs from the generated file in a way that changes this conclusion, stop and re-evaluate before mutation.

## 9. Mutation boundary

All runtime mutation must execute under PE-007 using the registered Local target and existing mutation coordination contract.

The mutation step must:

1. acquire the PE-007 host lock;
2. acquire both independent MariaDB advisory locks;
3. verify target identity;
4. verify the exact authoritative snippet identity;
5. verify the expected pre-change snippet hash/state;
6. apply one authoritative FluentSnippets source change;
7. permit/trigger only the normal FluentSnippets persistence/regeneration needed for that snippet;
8. release locks cleanly.

No direct filesystem-only edit is allowed as the primary mutation mechanism.

## 10. Lifecycle model

### States

1. `EVIDENCE_OPEN`
2. `EVIDENCE_APPROVED`
3. `EVIDENCE_MERGED`
4. `OWNERSHIP_DISCOVERED`
5. `PRE_MUTATION_VERIFIED`
6. `MUTATION_SESSION_ACTIVE`
7. `SNIPPET_MUTATED`
8. `RUNTIME_REPRESENTATION_VERIFIED`
9. `PE008_HEALTH_VERIFIED`
10. `POST_STATE_VERIFIED`
11. `REMEDIATION_ACCEPTED`
12. `BLOCKED`
13. `ROLLED_BACK`

### Transitions and guards

#### `EVIDENCE_OPEN -> EVIDENCE_APPROVED`
Guard:
- exact PR #69 head reviewed;
- no unresolved findings;
- exact-head CI complete and green.

Side effects:
- none.

#### `EVIDENCE_APPROVED -> EVIDENCE_MERGED`
Guard:
- merge uses exact approved head.

Side effects:
- documentation/evidence only.

#### `EVIDENCE_MERGED -> OWNERSHIP_DISCOVERED`
Guard:
- read-only inspection proves FluentSnippets record ownership and storage mapping.

Side effects:
- none.

#### `OWNERSHIP_DISCOVERED -> PRE_MUTATION_VERIFIED`
Guards:
- runtime target identity matches;
- snippet identity/state/hash match expected values;
- protected baseline captured;
- PE-008 status clean;
- no unrelated pending mutation.

Side effects:
- none.

#### `PRE_MUTATION_VERIFIED -> MUTATION_SESSION_ACTIVE`
Guards:
- PE-007 host lock acquired;
- both DB locks acquired;
- target/session proof valid.

Side effects:
- mutation session only.

#### `MUTATION_SESSION_ACTIVE -> SNIPPET_MUTATED`
Guards:
- authoritative FluentSnippets update path verified;
- intended diff limited to proven LF-producing boundary.

Side effects:
- one snippet source mutation;
- expected FluentSnippets-managed generated-file update only.

#### `SNIPPET_MUTATED -> RUNTIME_REPRESENTATION_VERIFIED`
Guards:
- authoritative record and generated file represent the same intended source;
- active/load state unchanged;
- five helper modes unchanged.

Side effects:
- none beyond verification.

#### `RUNTIME_REPRESENTATION_VERIFIED -> PE008_HEALTH_VERIFIED`
Guards:
- PE-008 session helper passes;
- PE-008 health helper passes;
- `no_unexpected_output=true`;
- no PHP diagnostics;
- strict JSON contract unchanged.

Side effects:
- none.

#### `PE008_HEALTH_VERIFIED -> POST_STATE_VERIFIED`
Guards:
- protected post-state snapshot equals pre-state for all protected categories except the explicitly authorized snippet representation;
- Platform Core fingerprint unchanged;
- PE-008 deployment state remains clean/absent as expected;
- ACF pending inventory unchanged;
- no ACF sync occurred.

Side effects:
- evidence capture only.

#### `POST_STATE_VERIFIED -> REMEDIATION_ACCEPTED`
Guard:
- all required checks pass and evidence is recorded append-only.

Side effects:
- remediation may be documented/merged;
- fresh v1.4.0 activation may be planned separately.

### Failure transitions

Any failed guard transitions immediately to `BLOCKED`.

If the authoritative snippet mutation has already occurred and a later verification fails, restore the exact pre-mutation authoritative snippet state through the same FluentSnippets ownership path, verify generated representation parity, and transition to `ROLLED_BACK`.

### Terminal states

- `REMEDIATION_ACCEPTED`
- `BLOCKED`
- `ROLLED_BACK`

No failed run may silently continue into a Person runtime activation.

## 11. Verification requirements

### Before mutation

Capture and verify:

- current `origin/main` SHA;
- PR #69 merged ancestry;
- Local target identity;
- PE-008 clean status;
- deployment state directory status;
- Platform Core fingerprint;
- authoritative FluentSnippet 10 ID/state/source hash;
- generated storage file hash;
- five active helper-mode inventory;
- protected-state snapshot;
- ACF pending inventory;
- current bootstrap captured-output hash.

### Immediately after mutation

Verify:

- same snippet ID;
- same active/published state;
- same execution scope/load mode;
- same five helper modes;
- authoritative source contains no LF-producing inline HTML boundary;
- generated file matches expected authoritative source representation;
- no unintended sibling FluentSnippet changes.

### PE-008 verification

Run through PE-007:

- `session` mode must return code 0;
- `health` mode must return code 0;
- `session_valid=true`;
- `wordpress_bootstrapped=true`;
- `no_php_diagnostics=true`;
- `no_unexpected_output=true`;
- all other existing health checks remain true;
- stderr empty;
- stdout remains deterministic canonical JSON.

A direct buffer replay may additionally confirm captured bootstrap output length is zero.

### Post-state verification

Require:

- protected state unchanged except authorized snippet source/storage bytes;
- Platform Core runtime fingerprint unchanged;
- no PE-008 deployment created;
- no ACF sync;
- four targeted ACF groups still show the same pending `Create` state unless an unrelated external actor changed them, in which case stop and investigate;
- Person schema/index/rebuild/reconcile remain not run;
- no Bricks mutations;
- no representative data mutations.

## 12. Evidence and documentation

Create a new append-only remediation evidence record. Do not edit historical v1.3.0 activation facts to imply success.

Suggested artifact:

`docs/architecture/FLUENTSNIPPETS_BOOTSTRAP_OUTPUT_REMEDIATION_v1.0.0.md`

The record should contain:

- exact source/main SHA;
- PR #69 merge ancestry;
- snippet identity and pre/post hashes;
- exact intended source diff;
- PE-007 mutation session evidence;
- PE-008 pre/post health results;
- protected-state comparison;
- rollback status;
- explicit outcome.

Recommended outcome enum:

- `FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=PASS`
- `FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=BLOCKED`
- `FLUENTSNIPPETS_BOOTSTRAP_REMEDIATION=ROLLED_BACK`

## 13. Acceptance criteria

The remediation is accepted only when all are true:

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

## 14. Next step after acceptance

Do **not** resume v1.3.0.

Start a fresh Person Relationship Runtime Activation **v1.4.0** from Stage 1 against the then-current reviewed `main`, carrying forward all previous activation gates plus an early PE-008 health confirmation proving the bootstrap-output defect remains absent.

Phase 2 placeholder relationship QA remains `NOT_STARTED` until the full Phase 1 v1.4.0 activation passes and its evidence is independently reviewed.
