# VGNova Runtime Mutation Coordination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent concurrent repository-owned administrative mutations against one VGNova runtime by requiring a validated filesystem lock and MariaDB advisory lock before mutation begins.

**Architecture:** Current pre-merge design is PE-007A v1.1.0: the coordinator owns a host `flock()` and starts two independent MariaDB lock keepers on separate connections. A Linux PHP FFI subreaper supervises the target command and descendants while either surviving keeper preserves cross-host exclusion after one coordination-process failure. Repository-owned mutating runtime harnesses independently validate both lock proofs before their first write. Source CI inventories and tests the policy without connecting to the Local WordPress runtime.

**Tech Stack:** PHP 8.5, MariaDB advisory locks, WP-CLI, Bash, GitHub Actions.

**Current spec:** `docs/architecture/VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.1.0.md` · **Historical decision:** `docs/architecture/VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.0.0.md` (superseded before merge)

## Guardrails

- Do not run live WordPress mutation, migration, deployment, or runtime QA during PE-007.
- Keep Platform Core application/domain writers outside administrative session locking.
- Use strict red, green, refactor cycles for each behavior. Run every new test before its implementation and confirm the expected failure.
- Keep secrets out of argv, metadata, logs, status output, and the exported session environment.
- Do not modify branch rulesets or create a force, steal, unlock, or bypass path.
- Review subprocess lifetime and child descriptor inheritance before adding the final process implementation.

## PE-007A architecture amendment — 2026-10-26

The independent review of exact PR #57 head `b489fa60b468761ed1a4fae296e056367c03f40c` found two blockers: one keeper owned the only MariaDB lock, so its death removed cross-host exclusion; the FCM Python runner required internal FD4 even though the supervisor correctly withholds coordinator descriptors. v1.0.0 remains unchanged as historical design evidence apart from its explicit superseded status. Its original RED/GREEN record describes the single-keeper implementation and is not evidence for the amended guarantee.

- [x] Replace the single MariaDB lock with canonical A/B lock names derived from the same target identity; acquire in A then B order and release B then A.
- [x] Run two independently killable keeper processes on separate MariaDB connections. Verify target identity and both lock owners only after both keepers acquire; abort both keepers if partial startup or verification fails.
- [x] Update active metadata, status, child environment, and Platform Core session guard to carry and independently verify both lock names and connection IDs. Remove singular aliases.
- [x] Document the v1.1 guarantee: any one coordinator, supervisor, keeper A, or keeper B may die without allowing an overlapping cross-host mutation while a child could still write. Do not claim tolerance of simultaneous loss of every independent lock holder.
- [x] Replace FCM's FD3/FD4 requirement with FD3 plus session environment and `pass_fds=(3,)`; retain the supervisor's descriptor boundary.
- [x] Add RED-before-fix process tests for A/B keeper death with an independent-host lock context, coordinator/supervisor death, partial A-acquired/B-failed cleanup, dual session-proof failures, and a real source-safe FCM child through `runChildCommand()`.
- [x] Keep the FCM runner protected in the full mutating-entrypoint inventory; do not change ordinary application-domain writer exemptions without separate justification.
- [x] Audit credentials in argv, metadata, logs/status, and session environment; each count must remain zero.
- [x] Run all PE-007 core, session, static-policy, inventory, Product Contribution, Platform Core reproducibility, dashboard, PHP/Python/shell/tool lint, and diff checks requested by PE-007A.
- [x] Update the existing PR #57 branch only; inspect exact-head GitHub results and the canonical source log. Do not merge.

The PE-007A RED evidence is recorded in the new tests: the independent-host contender acquired the old single-lock boundary after keeper death, and the actual coordinator child failed in FCM when it attempted to use absent FD4. These failures were observed before the corresponding production changes.

## Tasks

- [x] Write source-safe process lifetime spikes. Show the naive descriptor-closed case permits a contender to acquire the host lock while the child continues; show that the lifetime pipe keeps the database keeper alive after coordinator death even if the child closes descriptor 3; and show host-lock exclusion if the keeper exits while the coordinator remains alive.
- [x] Add core tests first for target loading, target validation, lock naming, state paths, DB host parsing, command argument boundaries, stable exit codes, nesting, metadata redaction, and fail-fast locking. Confirm red, then implement `runtime-mutation-core.php` and the sanitized target registry.
- [x] Add fake-database core tests first for `GET_LOCK`, contention/error results, reverse release order, identity checks, status probes, and release on child failure. Confirm red, then implement dedicated `mysqli` connection and target identity checks without booting WordPress.
- [x] Add separate-process session-guard tests first for metadata validation, lock path validation, actual filesystem ownership, and matching database lock owner. Confirm red, then implement the public guard/report API.
- [x] Add parent-death and concurrency tests first. Prove fail-fast mutual exclusion, inherited host-lock and database-keeper lifetime after coordinator death, detached-descendant lock retention, lock availability after all descendants exit, abnormal child exit cleanup, and safe signal handling. Confirm red, then implement the CLI, database-lock keeper, argv-array process launch, child descriptor inheritance, metadata lifecycle, and signal forwarding.
- [x] Audit all tracked administrative runtime PHP. Create a complete inventory. Add a session assertion at each mutator boundary while preserving existing identity, artifact integrity, ownership, cleanup, and protected-state gates. Keep Product Contribution's order as runtime identity, session assertion, Platform Core integrity, final read-only preflight, mutation flag, then first write.
- [x] Add policy checker tests first for unguarded mutators, valid guards, read-only and test-only paths, exemptions, missing and duplicate paths, unknown classes, guard mismatch, `$wpdb` DML, post, taxonomy, option, and ACF writes. Confirm red, then implement token-based scanning and the inventory.
- [x] Wire the new core, session, and policy test suites and policy checker into `site-code/bin/check-source.sh`; add the short PE-007 section to `AGENTS.md`.
- [ ] Run the complete verification list from the task brief, inspect exact outputs and diff, conduct independent focused reviews, resolve high-confidence findings, then open a focused PR without merging.

## Review checkpoints

1. Review lock ordering, process lifetime, signal behavior, and inherited descriptor tests before the CLI task is complete.
2. Review session-proof validation and credential exposure before migrating runtime entrypoints.
3. Review the inventory, scanner false negatives and false positives, and guard placement before source-gate integration.
4. Run a final full-diff review before creating the PR.

## Completion evidence

The PR is ready for independent review only after all local source checks pass, exact-head GitHub checks are inspected, all repository-owned mutating administrative entrypoints are guarded, no live VGNova mutation occurred, and the PR remains unmerged.
