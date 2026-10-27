# PE-008Y Database Transport Diagnostic Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Identify why PE-007 direct MySQL connectivity succeeds while the nested PE-008 WP-CLI bootstrap fails, without changing deployment behavior or WordPress data.

**Architecture:** Add a read-only parent diagnostic and a nested direct-mysqli probe. Compare the coordinator's existing PE-007 database status path, nested mysqli under the forced PHP configuration, direct WP-CLI, and `PHP_BINARY` plus WP-CLI. Report only sanitized connection results and PHP/transport facts. Lock the command shapes, secret handling, and runtime isolation with offline regression tests.

**Tech Stack:** PHP 8.5, WP-CLI, mysqli, PE-007 host and dual advisory locks, PHP process control.

**Spec:** PE-008Y — Prove Local Database Transport Divergence.

## Tasks

- [x] Add offline tests for command modes, DB_HOST/socket parsing, sanitized output, and no deployment or QA calls. Run them to capture RED.
- [x] Implement the read-only diagnostic library, parent command, and nested direct-mysqli probe.
- [x] Classify the new entrypoints as read-only in the mutation inventory and add the suite to `check-source.sh`.
- [ ] Run all required PE-007/PE-008 source suites, PHP/JSON/Python/shell checks, ShellCheck, actionlint, and `git diff --check`.
- [ ] Commit and push the source-only diagnostic to PR #59. Wait for exact-head CI before runtime use.
- [ ] Recheck main, old active tree, new stage, backup absence, and free coordinator status. Run one diagnostic-only PE-007 session, then verify all paths and locks remain unchanged.
- [ ] Record sanitized findings and runtime evidence. Do not change deployment code, retry deployment, run live QA, or merge PR #59.
