# VGNova Runtime Mutation Coordination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent concurrent repository-owned administrative mutations against one VGNova runtime by requiring a validated filesystem lock and MariaDB advisory lock before mutation begins.

**Architecture:** A repository-owned mutation coordinator owns a host `flock()` and starts a dedicated MariaDB lock keeper as a separate process. The mutator receives the keeper's database proof and a lifetime-pipe descriptor, so either the host lock or database lock continues to exclude a contender if one process fails. Repository-owned mutating runtime harnesses independently validate session proof before their first write. Source CI inventories and tests the policy without connecting to the Local WordPress runtime.

**Tech Stack:** PHP 8.5, MariaDB advisory locks, WP-CLI, Bash, GitHub Actions.

**Spec:** `docs/architecture/VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.0.0.md`

## Guardrails

- Do not run live WordPress mutation, migration, deployment, or runtime QA during PE-007.
- Keep Platform Core application/domain writers outside administrative session locking.
- Use strict red, green, refactor cycles for each behavior. Run every new test before its implementation and confirm the expected failure.
- Keep secrets out of argv, metadata, logs, status output, and the exported session environment.
- Do not modify branch rulesets or create a force, steal, unlock, or bypass path.
- Review subprocess lifetime and child descriptor inheritance before adding the final process implementation.

## Tasks

- [x] Write source-safe process lifetime spikes. Show the naive descriptor-closed case permits a contender to acquire the host lock while the child continues; show that the lifetime pipe keeps the database keeper alive after coordinator death even if the child closes descriptor 3; and show host-lock exclusion if the keeper exits while the coordinator remains alive.
- [x] Add core tests first for target loading, target validation, lock naming, state paths, DB host parsing, command argument boundaries, stable exit codes, nesting, metadata redaction, and fail-fast locking. Confirm red, then implement `runtime-mutation-core.php` and the sanitized target registry.
- [x] Add fake-database core tests first for `GET_LOCK`, contention/error results, reverse release order, identity checks, status probes, and release on child failure. Confirm red, then implement dedicated `mysqli` connection and target identity checks without booting WordPress.
- [x] Add separate-process session-guard tests first for metadata validation, lock path validation, actual filesystem ownership, and matching database lock owner. Confirm red, then implement the public guard/report API.
- [x] Add parent-death and concurrency tests first. Prove fail-fast mutual exclusion, inherited host-lock and database-keeper lifetime after coordinator death, lock availability after child exit, abnormal child exit cleanup, process-group cleanup, and safe signal handling. Confirm red, then implement the CLI, database-lock keeper, argv-array process launch, child descriptor inheritance, metadata lifecycle, and signal forwarding.
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
