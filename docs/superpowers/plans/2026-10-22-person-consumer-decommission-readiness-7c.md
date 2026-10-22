# Task 7C Person Consumer and Legacy Decommission Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

Do not run this read-only audit until Task 7C execution is separately authorized.

**Goal:** Perform a fresh, read-only audit of remaining `medewerker` consumers and produce one evidence-backed next implementation recommendation.

**Architecture:** Gather source and runtime evidence independently. Treat fresh runtime state as authoritative for current callers and dependencies. Do not migrate data or mutate the runtime.

**Tech stack:** WordPress, Bricks, ACF, FluentSnippets, PHP/Platform Core, repository Markdown/CSV evidence.

**Spec:** `docs/superpowers/specs/2026-10-22-person-consumer-decommission-readiness-design.md`

---

## Global Constraints

- The only runtime target is the Voelgoed WordPress install at `localhost:10039`.
- Every runtime evidence payload must record `ABSPATH`, `home_url`, the actual HTTP/runtime port, and the database name plus server identity sufficient to identify the installation. Do not record credentials.
- Stop before substantive runtime inspection if the identity preflight does not resolve to the expected 10039 installation or any required identity value is missing.
- `10049` / Danbricks is outside scope. Do not inspect or touch it.
- Task 7C is read-only. Do not write to WordPress, Bricks, ACF, FluentSnippets, the database, options, posts, postmeta, terms, relationships, routes, rewrites, or runtime files.
- Preserve profiles 753–757 as `DEFER`.
- Preserve Event 763 → profile 753 and Event 763 → profile 757 as `EXCLUDE`.
- Preserve Product 767 → profile 753 and Product 768 → profiles 753 and 757 as `EXCLUDE`.
- Do not change a migration ruling without separately reviewed evidence.
- Architecture dependency does not equal data migration authority.
- This plan does not authorize or execute the next implementation task.
- If any protected runtime state differs after the audit, stop and report the difference. Do not clean up or restore runtime state.
- Use read-only Novamira operations where available. A runtime interface that can save, auto-sync, or otherwise write during inspection is outside scope. Use a read-only database or filesystem method only when the Novamira interface cannot provide the required evidence, and record the access limitation.

## File map

Task 7C execution may change only these tracked evidence documents:

- Create `docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md` for evidence, callers, classifications, dependency order, route gates, and the single recommendation.
- Create `docs/architecture/POST_TASK_7C_CURRENT_STATE.md` for the post-audit snapshot, runtime identity, fixed rulings, and zero-write closeout.
- Modify `docs/architecture/PERSON_CONSUMER_INVENTORY.csv` to reconcile applicable rows with fresh evidence.
- Modify `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md` only if fresh evidence changes a current ruling. Preserve historical statements in their existing historical documents.

Keep any temporary local evidence outside the repository. Do not create executable audit scripts, PHP files, traces, or extra tracked evidence documents.

## Execution protocol

Run tasks in order. Record source and runtime evidence separately, include capture time and runtime identity with each runtime payload, and distinguish inaccessible evidence from evidence of zero callers. Each task has a documentation commit point on the isolated audit branch. Do not create product-code tests; this task changes no product code. Validate the evidence documents and CSV in Task 10.

### Task 1: Lock the baseline and evidence protocol

**Consumes:**

- `docs/superpowers/specs/2026-10-22-person-consumer-decommission-readiness-design.md`
- `docs/architecture/POST_TASK_7B_CURRENT_STATE.md`
- `docs/architecture/PERSON_CONSUMER_INVENTORY.csv`
- `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`
- `docs/architecture/PERSON_PROFILE_PROVENANCE_AUDIT.md`
- `docs/architecture/PERSON_PROFILE_PROVENANCE_EVIDENCE.csv`
- `docs/architecture/PERSON_RELATIONSHIP_PROVENANCE.csv`
- `docs/architecture/PRODUCT_SINGLE_124_7B_IMPLEMENTATION.md`
- `docs/architecture/PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json`
- `site-code/voelgoed-platform-core/README.md` and relevant tracked Platform Core source

**Produces:**

- An external, non-executable evidence directory named `task-7c-audit-c61b029-YYYYMMDD` under the environment's approved temporary evidence location. Use only sanitized text or data files such as `baseline.txt`, `source-hashes.txt`, `preflight-identity.txt`, `pre-state-fingerprint.txt`, `runtime-evidence.txt`, `post-state-fingerprint.txt`, and `access-limitations.txt`. Never store credentials or unnecessary personal field values.
- The initial evidence-report skeleton at `docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md`, containing the baseline, scope, fixed provenance rulings, evidence method, and a section for runtime evidence after the identity gate passes.

- [ ] Fetch and verify the exact execution baseline:

```bash
git fetch origin refs/heads/main:refs/remotes/origin/main
git rev-parse origin/main
```

Expected output is `c61b02954950e4e97aaa47255af41514b5989828`. Stop without starting the audit if the SHA differs. Record this repository baseline separately from runtime identity.

- [ ] Confirm the checkout used to create the audit worktree is clean and the target branch/worktree does not already exist. Create the isolated audit worktree from the verified commit:

```bash
git worktree add -b docs/task-7c-person-consumer-readiness-audit ../vgnova-task-7c-person-consumer-readiness-audit c61b02954950e4e97aaa47255af41514b5989828
```

Run all audit documentation edits in that worktree. Do not use the plan-authoring branch as the audit branch.

- [ ] Read the approved spec and the listed current and historical architecture evidence. Treat `POST_TASK_7B_CURRENT_STATE.md` as the current repository snapshot for the Product Single 124 cutover. Use older reports only as historical evidence.

- [ ] Before any runtime inspection, record `git rev-parse HEAD`, the tracked source file paths found by searching Platform Core for the audited contracts, and SHA-256 hashes for those files. At minimum, include tracked implementations and tests for `EventParticipationRepository::events_for_person()`, the Event Participation Bricks integration, `ProductPeopleResolver`, `ProductPeopleBricks`, and `ProductSourceEventBricks`. Record runtime deployed hashes separately later; do not infer parity from source hashes.

- [ ] Write the evidence directory path, file naming scheme, retention location, and sanitization rule into the report. The directory must be outside the repository and must not contain executable files.

- [ ] Before any runtime call, document the zero-write method: read-only Novamira inspection where available; select-only database access for database fingerprints; ordinary read-only filesystem metadata or hashing for runtime files; no editor screens or controls that save or auto-sync; and matching pre/post fingerprints. Record how access limitations will be reported.

**Completion check:** The verified baseline is exact, the audit worktree is isolated at that commit, required evidence has been read, source hashes are recorded before runtime access, and the report defines the evidence and zero-write protocol. No runtime query has occurred yet.

**Commit point:** Commit the report skeleton and protocol on the audit branch:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: start Task 7C readiness audit evidence"
```

### Task 2: Verify runtime identity and capture the pre-audit fingerprint

**Consumes:** Task 1 baseline and zero-write protocol; the expected runtime root from `AGENTS.md`.

**Produces:** Runtime identity evidence and the pre-audit protected-state fingerprint in the report and external evidence directory.

- [ ] Make an identity-only, read-only preflight against `http://localhost:10039/` before any substantive runtime query. Obtain and record:
  - resolved `ABSPATH`;
  - runtime `home_url`;
  - actual request/runtime port;
  - connected database name and server identity, without credentials;
  - WordPress site identity where available; and
  - the repository `main` baseline as a separate value.

- [ ] Compare `ABSPATH` with the expected Voelgoed installation path in `AGENTS.md`, verify the HTTP/runtime port is `10039`, and verify the database identity distinguishes this installation. If any value is missing, points to 10049/Danbricks, or identifies another installation, stop all runtime work and record the mismatch. Do not try another local site.

- [ ] Once identity passes, capture a pre-audit fingerprint using read-only, consistent database reads and read-only filesystem hashes. Record counts and canonical row digests, or an equivalent non-secret state fingerprint, for `posts`, `postmeta`, `term_relationships`, relevant `terms` and `term_taxonomy`, and `options`. Hash option values rather than copying secrets. Include whole-table counts/digests when supported; otherwise identify every targeted option hash and why the method is sufficient.

- [ ] Include scoped full-state fingerprints for Bricks Templates 772 and 773; component `6b463f`; relevant ACF groups, field definitions, and field values; and relevant FluentSnippets records, status, and deployed files, including `9-acf-product-copy.php` and `10-vg-bricks-medewerker-queries.php` where present.

- [ ] Include post, postmeta, and term-relationship state for profiles 753–757, Event 763, and Products 765, 767, and 768. Include counts by status and state fingerprints for canonical `person` records and `event_participation` records. Record the exact five `DEFER` profiles and five `EXCLUDE` edges from the spec as protected rulings.

- [ ] Record the pre-audit state of relevant runtime files and whether any `.crashed` markers or temporary PHP/trace artifacts already exist in the audit and runtime locations that will be checked at closeout. Do not create any such artifact.

**Completion check:** Runtime identity passes all four required identity fields and identifies only 10039. The protected-state baseline is recorded before substantive inspection. If identity fails, stop here and make no caller, route, or content query.

**Commit point:** Add the identity and pre-audit fingerprint summary to the report, then commit:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: record Task 7C runtime preflight"
```

### Task 3: Audit Bricks consumers

**Consumes:** Verified runtime identity and pre-audit fingerprint; fresh read-only Bricks evidence; current Post-Task-7B state as a historical lead only.

**Produces:** A current caller map and independent Template 772 concern findings in the audit report.

- [ ] Inspect Template 772, Template 773, component `6b463f`, and every current Bricks template/component that calls `6b463f`. Inspect Bricks data through a read-only runtime interface or read-only database access. Do not open a control that can save, auto-sync, or modify builder data.

- [ ] For Template 772, record its conditions, current structure, active status, all native and marked queries, exact element/query IDs, helper mode, fields and dynamic tags, relationship direction, and rendering context. Record separate findings for:
  1. Person/profile presentation, including the fields and native Person elements used and whether a replacement can be designed without copying profiles 753–757;
  2. reverse Events;
  3. reverse Products;
  4. role/group and other classification presentation;
  5. `/medewerkers/` route identity and canonical dependency; and
  6. each additional dependency discovered.

- [ ] For Template 773, record conditions, archive query and ordering, `medewerker` dependency, loop and card element IDs, fields, card instance, route behavior, and active status.

- [ ] For component `6b463f`, record current structure and dynamic data. Enumerate every active caller across templates, nested components, and other supported runtime surfaces. For each caller, record the owning surface, component instance ID, current status/condition, and the evidence used to count it as active or inactive.

- [ ] Verify from current runtime data whether Product Single 124 still uses `6b463f` or the post-Task-7B Product Person Card `dc8611`. Do not use the historical caller list as a current count.

- [ ] Search all active runtime Bricks definitions for further `medewerker` references, including additional templates, components, queries, and dynamic fields. Record inactive registered definitions separately from active callers.

**Completion check:** The report contains exact current element/query IDs, modes, fields/tags, relationship directions, caller lists, and active status. Template 772 concerns are separate rows/findings, not one template-level conclusion.

**Commit point:** Commit the Bricks evidence in the report:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: record Task 7C Bricks consumer audit"
```

### Task 4: Trace relationship helpers and write paths

**Consumes:** Tracked Platform Core hashes from Task 1; fresh Bricks caller map from Task 3; current runtime definitions and values.

**Produces:** Source/runtime relationship and write-path evidence, including the complete Product copy chain, in the audit report.

- [ ] Search tracked repository code and inspect deployed runtime state separately for `medewerker_events`, `medewerker_products`, `event_medewerkers`, `produk_medewerkers`, and `produk_bron_event`. Locate the source and deployed definitions of `10-vg-bricks-medewerker-queries.php` and `9-acf-product-copy.php`. If either definition exists only in runtime storage, record source parity as unavailable/runtime-only rather than inventing a source counterpart.

- [ ] Inspect every branch of `10-vg-bricks-medewerker-queries.php`, including all six known modes: `medewerker_events`, `medewerker_products`, `event_medewerkers`, `event_products`, `product_medewerkers`, and `product_source_event`. Record any additional branches found. For each mode, record active state, source and deployed hashes/identifiers, hook/conditions if applicable, active callers, read direction, data read, and access limitations.

- [ ] Inspect the ACF definitions and relevant runtime values for `event_medewerkers`, `produk_medewerkers`, and `produk_bron_event`, plus the actual readers and writers for each. Record whether a definition has zero active callers. Keep `produk_bron_event` and source Event presentation separate from Product People and WooCommerce `_event_name`.

- [ ] Read the complete deployed save behavior of `9-acf-product-copy.php`: active status, hook and priority, save conditions, fields read, fields written, overwrite protections, and every current reader of its output. Search for other supported purposes before drawing a retirement conclusion.

- [ ] Trace and document the complete chain and all additional readers/writers:

```text
produk_bron_event
→ event_medewerkers
→ 9-acf-product-copy.php
→ produk_medewerkers
→ medewerker_products
→ Template 772
```

- [ ] For every path, record its source/definition, runtime deployed state, source/runtime parity, active callers, read direction, write direction if any, data read or written, supported purpose, access limitation, and whether a registered definition has zero actual active callers. Use fresh current evidence to locate the earliest safe break point in the chain. Do not alter or deactivate any helper, field, or snippet.

- [ ] Recheck that Event 763 → 753/757 and Product 767/768 → 753/757 remain `EXCLUDE`; this is a read-only assertion, not a ruling change.

**Completion check:** Both read and write paths are documented for each field/helper. The report explains the earliest safe break point from current callers and writers, and distinguishes current Product Single 124 behavior from stale pre-Task-7B descriptions.

**Commit point:** Commit the relationship and write-path evidence:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: trace Task 7C relationship write paths"
```

### Task 5: Assess canonical reverse-read readiness

**Consumes:** Task 3 Template 772 findings; Task 4 relationship and writer map; source and deployed Platform Core evidence.

**Produces:** Reverse Event readiness and one evidence-based classification of the current reverse Product presentation requirement.

- [ ] Inspect the source and deployed contract for `Person → Event Participation → Event`, including `EventParticipationRepository::events_for_person()`, its supporting repository methods, the Event Participation Bricks integration, and the relevant tests. Record source paths and hashes separately from deployed runtime evidence.

- [ ] Determine from the current implementation and runtime evidence its input validation, result ordering, Event/Person endpoint checks, publication/status behavior, and whether an existing Bricks consumer can call this reverse-read contract. State whether existing APIs suffice, a later read-only adapter/query bridge is needed, or the contract remains blocked. Do not infer Bricks readiness from the existence of `events_for_person()` alone.

- [ ] Decide whether a later reverse Event consumer can be designed without persistent migrated Person data. Do not create Event Participation records or migrate the two excluded Event 763 edges.

- [ ] For reverse Products, select exactly one current requirement description only if the available evidence supports it:
  1. a genuine supported reverse Product requirement that needs separate canonical architecture approval;
  2. a presentation safely derivable from canonical Product configuration without persistent reverse storage; or
  3. an obsolete legacy presentation to retire.

  If evidence or access is insufficient to support any outcome, do not force a choice. Record the evidence limitation, mark Task 5 blocked/incomplete, and carry that blocker into the final Task 7C recommendation. Do not invent an unsupported conclusion to satisfy the three-way decision.

- [ ] Base that choice on the current Template 772 presentation, Product People behavior in Product Single 124, current editor/user flows, actual active callers, and the source/runtime relationship model. If evidence is inaccessible, record the limitation and do not claim the requirement is obsolete or supported without evidence.

- [ ] Do not add or propose a reverse index, new relationship storage, database scan contract, synchronization system, or Product relationship design as part of Task 7C. Keep the known Product edges `EXCLUDE`.

**Completion check:** The report states the reverse Event read contract and its Bricks readiness from evidence. For reverse Products, it records exactly one of the three requirement descriptions when sufficient evidence supports that choice. Otherwise, it records the access/evidence limitation, marks Task 5 blocked/incomplete, and carries that blocker into the final recommendation without forcing an outcome.

**Commit point:** Commit the canonical-readiness findings:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: assess Task 7C canonical reverse reads"
```

### Task 6: Audit routes, dynamic tags, taxonomies, and fields

**Consumes:** Runtime identity; Bricks caller map; current ACF/Bricks/route evidence.

**Produces:** Read-only route, tag, field, and taxonomy findings in the audit report.

- [ ] Inspect `/medewerkers/` and representative `/medewerkers/{slug}/` routes with public, read-only `GET` requests to `localhost:10039`. Record status, canonical behavior, indexing signals, and relevant response evidence. Do not use a request method or admin action that changes state. Do not add redirects, modify canonicals, change menus, change indexing settings, or flush rewrites.

- [ ] Record current route/link/navigation dependencies and the evidence required for later route work. A single-route redirect requires an approved identity/provenance ruling, persistent target Person ID, verified `/mense/{slug}/` destination, and an unambiguous exact legacy-route mapping. Archive work separately requires a verified canonical archive and review of route, canonical, menu, and indexing behavior. Person count alone is not a route gate.

- [ ] Inspect the Bricks dynamic-data registry and separately locate actual uses of `{acf_medewerker_titel}`, `{acf_medewerker_kort_bio}`, `{acf_medewerker_profiel_status}`, `{acf_event_medewerkers}`, `{acf_produk_medewerkers}`, `{post_terms_medewerker_rol}`, `{post_terms_medewerker_groep}`, `{acf_broadcast_hosts}`, `{acf_broadcast_guests}`, and every additional current Medewerker-related tag. A registered tag alone is not an active consumer.

- [ ] Inspect read-only definitions, values, and actual consumers for `medewerker_rol`, `medewerker_groep`, `medewerker_titel`, `medewerker_kort_bio`, `medewerker_profiel_status`, `broadcast_hosts`, `broadcast_guests`, their related ACF groups, and any further Medewerker ACF/taxonomy fields found. Do not edit or save definitions or values.

- [ ] For each active field/tag/taxonomy use, record its owning surface, element or code identifier, relationship direction where relevant, fields/data read or written, supported status, evidence source, and access limitation. Report unused registrations separately.

**Completion check:** The report covers route responses and gates, actual tag uses, relevant definitions and values, and all additional fields/taxonomies found. Only read-only public GETs and read-only runtime/database inspection were used.

**Commit point:** Commit route and field-consumer evidence:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: record Task 7C route and field consumers"
```

### Task 7: Classify atomic dependencies and order the work

**Consumes:** Findings from Tasks 1–6 and the fixed provenance rulings.

**Produces:** A complete dependency table and evidence-backed dependency order in the audit report.

- [ ] Assign exactly one current-state classification to each audited legacy dependency and each independent Template 772 concern. Use only:
  - `READY_FOR_CONSUMER_REPLACEMENT`
  - `BLOCKED_BY_PERSON_DATA`
  - `BLOCKED_BY_RELATIONSHIP_MODEL`
  - `RETIRE_PENDING_ACCESS`
  - `RETIRE_PENDING_DEPENDENCY`
  - `KEEP_LEGACY`

- [ ] Include a structured table with at least these columns: dependency/concern; current caller(s); source/runtime evidence; read/write role; classification; reason; prerequisite for changing classification; access limitation; and relation to provenance/data authority.

- [ ] Give Template 772 separate classification rows for profile presentation, reverse Events, reverse Products, classification/taxonomy presentation, route/canonical dependency, and each additional dependency found. Do not assign one status to the whole template when its concerns differ.

- [ ] Include Template 773, `6b463f`, each helper mode, each relevant ACF field/taxonomy, each write/copy path, and route surfaces as separate dependencies where their callers or lifecycle differ. A registered definition with no actual active caller must not be classified as an active dependency.

- [ ] Draw the dependency order from recorded caller and read/write evidence. Show what blocks each later action, the prerequisites that must pass first, and the earliest safe point to break the Product copy chain. Mark unknown or inaccessible edges as blocked by evidence limits rather than guessing.

- [ ] Keep architecture readiness separate from migration authority. State beside each relevant dependency that classifications authorize no deletion, migration, retirement, or runtime mutation. Preserve all five `DEFER` profiles and all five `EXCLUDE` edges.

**Completion check:** Every atomic dependency has one allowed classification, the table contains every required column, Template 772 concerns are classified independently, and every edge in the dependency order points to evidence in the report.

**Commit point:** Commit the classification table and dependency order:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md
git commit -m "docs: classify Task 7C legacy dependencies"
```

### Task 8: Produce the Task 7C evidence documents

**Consumes:** Completed findings and classifications from Tasks 1–7; current inventory and decommission register.

**Produces:** The four core evidence outputs listed below, with the register conditional on a changed current ruling.

- [ ] Complete `docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md` with the evidence, active callers, read/write map, source/runtime differences, classifications, route gates, dependency order, access limitations, fixed provenance rulings, and a recommendation section reserved for Task 9.

- [ ] Create `docs/architecture/POST_TASK_7C_CURRENT_STATE.md` with the verified repository baseline, runtime identity, current Person and Event Participation counts, current consumers, changed/unchanged source/runtime parity, protected state/ruling status, zero-write evidence reference, and unresolved access limits.

- [ ] Reconcile every applicable row in `docs/architecture/PERSON_CONSUMER_INVENTORY.csv` against fresh evidence. Correct stale pre-Task-7B Product Single 124 and `6b463f` caller descriptions, update the actual current callers/modes/read-write roles, and keep evidence-source and migration-authority distinctions clear. Preserve the existing CSV columns unless a schema change is essential to represent a required fact; if considered, document why in the audit report.

- [ ] Update `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md` only if fresh evidence changes a current ruling. If no ruling changes, do not modify the register. Do not revise historical implementation reports or silently rewrite statements that remain historically correct.

- [ ] Keep local fingerprint artifacts outside Git and do not add any fifth tracked output. Do not write or execute the recommended implementation.

**Completion check:** The audit report and current-state snapshot exist, the inventory reconciles current callers and the post-7B Product state, and the register changes only when evidence changes a current ruling. No historical evidence was silently rewritten.

**Commit point:** Commit only the approved evidence documents:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md docs/architecture/POST_TASK_7C_CURRENT_STATE.md docs/architecture/PERSON_CONSUMER_INVENTORY.csv
```

If the register ruling changed, also stage `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`. Then commit:

```bash
git commit -m "docs: record Task 7C consumer readiness audit"
```

### Task 9: Select one next implementation recommendation

**Consumes:** Completed evidence table, dependency order, route gates, and current-state snapshot.

**Produces:** Exactly one next-task recommendation in the audit report.

- [ ] Select exactly one next unit. It may be a Person Single 772 consumer foundation/cutover, Person Archive 773/card cutover, reverse Event Bricks adapter/query prerequisite, separate Product-to-Person reverse-relationship architecture design, retirement of an obsolete legacy consumer, or a finding that work remains blocked by Person data/provenance authority.

- [ ] State the exact next unit; why it is the next dependency; which legacy dependency it removes or unlocks; why it carries less risk than the alternatives; what remains blocked afterward; and which new design/spec/review gate must pass before implementation.

- [ ] Tie each part of the recommendation to evidence and classifications already recorded. If access limits prevent a defensible choice, select one blocked recommendation and name the evidence/access gate that must be resolved.

- [ ] Do not list a ranked shortlist, co-equal alternatives, implementation steps, or product-code changes. Do not execute the recommendation.

**Completion check:** The final audit report contains one recommendation and answers all six required decision questions. No second next task is proposed.

**Commit point:** Commit the completed recommendation and any resulting documentation corrections:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md docs/architecture/POST_TASK_7C_CURRENT_STATE.md
git commit -m "docs: recommend next Person consumer task"
```

### Task 10: Verify zero writes and validate the evidence documents

**Consumes:** Pre-audit identity and fingerprints from Task 2; all final Task 7C evidence documents.

**Produces:** Final zero-write and documentation validation results in `POST_TASK_7C_CURRENT_STATE.md` and the audit report.

- [ ] Repeat the read-only identity check against `localhost:10039`. Compare `ABSPATH`, `home_url`, actual port, and database name/server identity with Task 2. If identity differs, stop and report it.

- [ ] Recompute the protected-state fingerprints with the same safe method and compare them with the pre-audit values. At minimum compare counts/state for `posts`, `postmeta`, term relationships and terms; relevant options or option hashes; Templates 772 and 773; component `6b463f`; relevant FluentSnippets files/state; relevant ACF definitions/values; profiles 753–757; Event 763; Products 765, 767, and 768; Person count/state; and Event Participation count/state.

- [ ] Verify the five profile rulings and five edge rulings are unchanged. Confirm Task 7C created no Person records, Event Participation records, or Product People configuration and changed no relationship edge.

- [ ] Verify that no temporary executable sandbox PHP or trace artifacts were introduced and no new `.crashed` marker was created in the checked audit/runtime locations. Compare against the pre-audit artifact inventory. Do not delete, repair, or clean up any unexpected artifact; stop and report it.

- [ ] Confirm the audit branch contains only the four approved evidence documents, with the register present only if a current ruling changed. Check with `git status --short` and `git diff --name-only c61b02954950e4e97aaa47255af41514b5989828...HEAD`.

- [ ] Parse the refreshed CSV with Python's standard CSV parser and confirm every row has the header's field count:

```bash
python3 -c 'import csv, pathlib; p=pathlib.Path("docs/architecture/PERSON_CONSUMER_INVENTORY.csv"); rows=list(csv.reader(p.open(newline="", encoding="utf-8"))); assert rows and all(len(row)==len(rows[0]) for row in rows), "CSV row width mismatch"; print(f"CSV OK: {len(rows)-1} data rows, {len(rows[0])} columns")'
```

- [ ] Validate Markdown tables and local links with the repository's available Markdown validator or a non-writing local check. Record the command and result. Run:

```bash
git diff --check
```

- [ ] If any protected runtime state differs, stop immediately, identify the exact difference, and do not clean up. Do not claim zero writes unless identity and protected-state comparisons pass.

- [ ] Keep the final Task 7C audit PR open and unmerged for independent review. Do not merge it or start the recommended implementation.

**Completion check:** Runtime identity still matches 10039; all protected fingerprints and rulings match; no audit-created runtime or temporary executable artifacts exist; only approved documentation files changed; CSV, Markdown checks, and `git diff --check` pass; the audit PR remains unmerged.

**Commit point:** Record the closeout and validation results in the current-state document and audit report, then commit the final documentation update:

```bash
git add docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md docs/architecture/POST_TASK_7C_CURRENT_STATE.md
git commit -m "docs: verify Task 7C audit closeout"
```

Open a docs-only PR against `main` after Task 10. Leave it open for independent review.

## Review Focus

| Failure mode | Owning task | Concrete verification |
|---|---|---|
| The audit reaches the wrong local WordPress installation, such as 10049 instead of 10039. | Task 2 and Task 10 | Record `ABSPATH`, `home_url`, actual port, and database name/server identity before and after. Stop before substantive queries unless they identify the expected 10039 Voelgoed install. Never inspect 10049/Danbricks. |
| A registered but unused legacy definition is mistaken for an active caller. | Tasks 3, 4, 6, and 7 | Enumerate current Bricks templates/components and actual field/tag/helper uses. Record registered definitions separately and classify zero callers only after the active caller search completes. |
| The read path is audited while a write/copy path is missed. | Task 4 | Inspect every helper mode, the full save conditions and writes in `9-acf-product-copy.php`, and all readers of `produk_medewerkers`; record read and write direction for each path and trace the full copy chain. |
| The legacy reverse Product presentation is recreated without proof that it remains a supported requirement. | Task 5 and Task 9 | Compare Template 772 presentation, Product Single 124 Product People behavior, current editor/user flows, active callers, and the relationship model. Select exactly one of the three requirement descriptions before recommending any next unit. |
| Existing `DEFER` or `EXCLUDE` evidence is treated as canonical migration authority. | Tasks 1, 2, 5, 7, and 10 | Lock the five `DEFER` profiles and five `EXCLUDE` edges from the approved spec, carry them into the pre/post fingerprint, state they remain unchanged, and require separate review for any proposed ruling change. |
