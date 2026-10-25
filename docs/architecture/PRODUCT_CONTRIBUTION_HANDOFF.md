# Product Contribution implementation handoff

**Status:** PARKED AFTER TASK 5 — TASK 6 IS NEXT

**Checkpoint date:** 2026-10-23

**Canonical `main` at checkpoint:** `a9587abf0a64ebedf202562571ce55225377228f`

This file is the fast resume point for the Product Contribution / reverse Product implementation. Read this first, then use the approved architecture spec and implementation plan as the source of truth.

## Source-of-truth documents

- Architecture spec: `docs/superpowers/specs/2026-10-22-product-contribution-reverse-product-design.md`
- Implementation plan: `docs/superpowers/plans/2026-10-23-product-contribution-reverse-product.md`
- Task 1 preflight evidence: `docs/architecture/PRODUCT_CONTRIBUTION_PREFLIGHT.md`
- This checkpoint: `docs/architecture/PRODUCT_CONTRIBUTION_HANDOFF.md`

The plan remains authoritative for Tasks 6–9. This handoff records the execution state and review refinements that were established while Tasks 1–5 were implemented through separate PR gates.

## Current execution state

| Unit | Status | Evidence / outcome |
|---|---|---|
| Architecture spec | COMPLETE + MERGED | PR #34 |
| Implementation plan | COMPLETE + MERGED | PR #35 |
| Task 1 — fresh baseline/runtime/override gate | COMPLETE | `PRODUCT_CONTRIBUTION_OVERRIDE_GATE=PASS` in `PRODUCT_CONTRIBUTION_PREFLIGHT.md` |
| Task 2 — ACF Product Contribution schema + Product Role vocabulary | COMPLETE + MERGED | PR #36 |
| Task 3 — repository, reverse reads, rich associations, pair lock | COMPLETE + MERGED | PR #37 |
| Task 4 — canonical writes + admin/ACF validation | COMPLETE + MERGED | PR #38 |
| Task 5 — manual Product People authority cutover | COMPLETE + MERGED | PR #39 |
| Task 6 — Person context / optional reverse Product Bricks adapter | **NEXT — NOT STARTED** | Must run PASS/DEFER gate below |
| Task 7 — final Platform Core wiring + full source regression matrix | BLOCKED | Wait for Task 6 review + merge |
| Task 8 — controlled runtime deployment + runtime QA | BLOCKED | Wait for Task 7 review + merge |
| Task 9 — final implementation/current-state docs + final verification | BLOCKED | Wait for Task 8 review + merge |

## Verified Task 5 merge provenance

PR #39 was approved at exact head:

```text
9b3ba8bbb60330b566db0921749cb237fb25e0c8
```

It merged as:

```text
a9587abf0a64ebedf202562571ce55225377228f
```

The merge parents were exactly:

```text
1a00e118749d0bfe827aa81ce5b93a35fcead02d  # prior main
9b3ba8bbb60330b566db0921749cb237fb25e0c8  # approved Task 5 head
```

No Task 6 implementation work had started at this checkpoint.

## What is canonical now

The following architecture is already implemented in source and merged:

- `product_contribution` is the canonical Product↔Person relationship entity.
- `product_role` is the separate Product-context role taxonomy with the approved four-role vocabulary.
- `ProductContributionRepository` owns canonical forward/reverse reads and rich association views.
- `ProductContributionPairLock` owns race-safe Product×Person identity locking.
- `ProductContributionService` owns canonical programmatic writes and publication validation.
- `ProductContributionAdmin` owns admin/ACF lifecycle enforcement, including unconditional trash retirement and title resync.
- Manual Product People authority now comes from Product Contributions.
- Event Product People membership remains live-owned by Event Participation.
- `ProductPeopleResolver::people_for_product(int $product_id): array` remains the compatibility API and delegates to `ProductContributionRepository`.
- `product_people_override` source field definition/constants are retired.
- Historical `product_people_override` database meta is **not** deleted automatically and is ignored by canonical resolution.
- `ProductPeopleBricks.php` remains behaviorally unchanged.
- Task 5 pulled forward the minimal resolver bootstrap dependencies (`ProductRoleVocabulary` and `ProductContributionRepository`) so merged `main` remains deployable between task PRs.

## What is deliberately not complete yet

Do not infer completion from the existence of the repository/service classes.

Still outstanding:

- Task 6 has not decided whether `PersonProductsBricks` is safe to implement.
- Full Product Contribution bootstrap/lifecycle wiring is not complete; Task 7 still owns the remaining pair lock/service/admin/seeder wiring and optional Person adapter wiring.
- Controlled runtime deployment, targeted ACF sync, Product Role seeding, runtime QA, and the runtime manifest are Task 8.
- Final implementation/current-state documentation and decommission evidence updates are Task 9.
- No Bricks template mutation is authorized by Task 6.
- No legacy Product/Medewerker/Event relationship decommissioning is authorized by this unit.

---

# NEXT: Task 6 — Person context gate

Task 6 is a **gate**, not an automatic feature build.

The approved plan requires an evidence-backed decision:

```text
PASS  → implement PersonProductsBricks with TDD
DEFER → no adapter code; record why a safe Person context cannot be proven
```

## Resume baseline rule

The checkpoint baseline is:

```text
a9587abf0a64ebedf202562571ce55225377228f
```

When resuming, do **not** blindly branch from this historical SHA if `origin/main` has moved.

First verify:

```text
current origin/main SHA
checkpoint SHA above
merge-base / intervening commits
```

If `main` contains unrelated changes, explicitly rebaseline and inspect them before Task 6. Do not silently adapt.

Task 6 must run in a fresh isolated branch/worktree from the freshly verified current `origin/main`.

Suggested branch:

```text
feat/person-products-bricks-gate
```

## Task 6 read-only context inspection

Inspect the existing Bricks/WordPress context mechanisms before writing production code:

1. `\Bricks\Database::$page_data['preview_or_post_id']`
2. singular queried object
3. `get_the_ID()`
4. global `$post`
5. Bricks builder detection

The existing Product adapter is precedent for context priority, but the Person adapter must be stricter about type safety.

## Task 6 PASS condition

PASS only when **every accepted context proves `post_type=person` before calling the repository**.

Execution refinement established during review:

> If a higher-priority context explicitly exists and resolves to a non-Person post, **fail closed immediately**. Do not fall through to a stale lower-priority Person context.

Required decision shape:

```text
Bricks preview ID exists
  person     → accept
  non-person → fail closed

No preview context
  queried singular object exists
    person     → accept
    non-person → fail closed

No queried context
  get_the_ID() yields an active candidate
    person     → accept
    non-person → fail closed

No usable current ID
  global $post
    person     → accept
    otherwise  → fail closed
```

## If Task 6 PASSes

Strict RED → GREEN TDD.

Authorized files:

```text
Create: site-code/voelgoed-platform-core/includes/Product/PersonProductsBricks.php
Create: site-code/voelgoed-platform-core/tests/PersonProductsBricksTest.php
Modify: site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

Required markers:

```text
vg-person-products-query
vg-person-products-section
```

The adapter must:

- use only `ProductContributionRepository::products_for_person()` as relationship authority;
- require a current canonical `person` context;
- constrain queries to `post_type=product` and `post_status=publish`;
- use repository Product order via `post__in` + `orderby=post__in`;
- use `post__in => [0]` when empty;
- hide the marked section on the frontend when empty;
- keep the marked section visible in builder contexts;
- match CSS marker tokens exactly;
- leave unmarked Bricks queries/elements untouched;
- never read Product Contribution meta/taxonomy directly;
- never read Event Participation directly;
- never read `produk_medewerkers`, `_event_name`, or Person-side copied Product IDs;
- never mutate a Bricks template in Task 6.

Minimum Task 6 test coverage:

- Bricks preview Person;
- queried Person;
- `get_the_ID()` Person fallback;
- global `$post` Person fallback where applicable;
- higher-priority non-Person context fails closed;
- other non-Person contexts fail closed;
- exact CSS token matching;
- deterministic Product `post__in` ordering;
- empty `[0]`;
- frontend hide-on-empty;
- builder visibility;
- unmarked consumers unchanged.

Run at minimum:

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
php -l <every changed PHP file>
git diff --check
```

Open a Task 6 PR into `main`, leave it unmerged, and stop for independent review.

## If Task 6 must DEFER

Do not force an adapter into existence.

Create an evidence-only Task 6 review unit containing:

```text
docs/architecture/PERSON_PRODUCTS_BRICKS_GATE.md
```

Record:

```text
execution baseline SHA
context mechanisms inspected
PASS/FAIL result for each context path
specific unsafe ambiguity
PERSON_PRODUCTS_BRICKS=DEFER
confirmation that no adapter/template code was created
```

Open the evidence-only PR into `main`, leave it unmerged, and stop for independent review.

---

# Task 7 checkpoint notes

After Task 6 is approved, merged, and its merge provenance independently verified, Task 7 owns final Platform Core source wiring and the complete source regression matrix.

Important rebaseline note: the original plan predates the per-task merge workflow. Task 5 already added the minimum resolver dependencies to `integration.patch`. Task 7 must inspect the **current** patch and wire only what is still missing rather than reapplying the original planned diff mechanically.

Expected Product Contribution load/registration contract remains:

| Class | Load | Lifecycle registration |
|---|---:|---:|
| `ProductRoleVocabulary` | Yes | No |
| `ProductContributionRepository` | Yes | No |
| `ProductContributionPairLock` | Yes | No |
| `ProductContributionService` | Yes | No |
| `ProductContributionAdmin` | Yes | Yes |
| `ProductRoleSeeder` | Yes | Yes |
| `PersonProductsBricks` | Only if Task 6 PASS | Only if Task 6 PASS |

Task 7 also updates the README and runs the complete relevant Platform Core/Event/Person regression matrix described in the approved plan.

Task 7 must end in its own open, unmerged PR and independent gate.

---

# Task 8 checkpoint notes

Task 8 is the controlled runtime deployment + runtime QA phase.

Before **any write**, re-prove runtime identity. Expected target from the approved plan/preflight:

```text
WordPress root: /home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/
home:           http://localhost:10039
database:       local
```

Do not operate on the separate localhost:10049 installation.

Task 8 must:

- deploy reviewed Platform Core source using the established controlled process;
- target-sync only the three Product Contribution ACF keys;
- seed/verify exactly the four approved Product Role terms;
- run guarded owned runtime QA;
- prove manual + event membership semantics, reverse reads, stale annotation behavior, role integrity, title/admin behavior, public filtering, and cleanup;
- preserve protected legacy state;
- create `site-code/runtime-tests/product-contribution-qa.php`;
- create `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json` from captured evidence;
- open its own PR/review unit as required by the current governance.

---

# Task 9 checkpoint notes

Task 9 closes the implementation with evidence-backed documentation and final verification.

Expected documentation work includes:

```text
docs/architecture/PRODUCT_CONTRIBUTION_IMPLEMENTATION.md
docs/architecture/POST_PRODUCT_CONTRIBUTION_CURRENT_STATE.md
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md          # only if evidence changes it
docs/architecture/PERSON_CONSUMER_INVENTORY.csv           # only if evidence changes it
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Task 9 must not falsely mark unrelated legacy surfaces as decommissioned.

It must run the final full verification matrix from a clean worktree and preserve exact evidence/pass counts.

---

# Locked review workflow

The execution governance used for Tasks 2–5 remains binding for Tasks 6–9:

```text
1. Fresh verified current origin/main baseline
2. Fresh isolated branch/worktree
3. TDD RED before production behavior changes
4. Implement only the authorized task
5. Run focused + required regression verification
6. Commit and push
7. Open PR into main
8. Leave PR open/unmerged
9. Independent exact-head review
10. Revise until approved
11. Merge only after approval
12. Independently verify merge commit parents / main lineage
13. Only then authorize the next task
```

A coding-agent summary is never sufficient evidence for approval. Exact PR head, diff, tests/CI evidence, architecture contract, and merge provenance must be checked independently.

No later task may begin merely because the previous task was pushed or locally green.

---

# Resume instruction for a future agent

If returning after other work, start with:

```text
Read:
1. docs/architecture/PRODUCT_CONTRIBUTION_HANDOFF.md
2. docs/superpowers/specs/2026-10-22-product-contribution-reverse-product-design.md
3. docs/superpowers/plans/2026-10-23-product-contribution-reverse-product.md
4. docs/architecture/PRODUCT_CONTRIBUTION_PREFLIGHT.md

Then:
- fetch/verify current origin/main;
- compare it to checkpoint main a9587abf0a64ebedf202562571ce55225377228f;
- inspect intervening commits if any;
- confirm Tasks 1–5 remain merged and no Task 6 PR already exists;
- continue with Task 6 PASS/DEFER gate only;
- do not start Task 7 until Task 6 is independently approved, merged, and lineage-verified.
```

## Hard stop reminders

- Do not invent a migration for historical override meta.
- Do not restore `product_people_override` as relationship authority.
- Do not let Product Contribution create event membership.
- Do not map Event roles to Product roles.
- Do not add reverse stored relationship state or persistent relationship caches.
- Do not read legacy Product/Medewerker fields as canonical fallbacks.
- Do not mutate Bricks templates in Task 6.
- Do not run unrestricted ACF JSON sync in Task 8.
- Do not touch protected legacy state outside the approved plan.
