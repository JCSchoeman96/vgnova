# PE-002 deterministic source CI and VG Nova Platform Gate implementation plan

> **For agentic workers:** Use the Superpowers subagent-driven development workflow for the independent workflow and source-check tasks. Each implementation task gets a spec review followed by a code-quality review.

**Goal:** Run deterministic checks for the dashboard plugin and canonical `site-code` source on every pull request and `main` push, then publish one stable aggregate check named `VG Nova Platform Gate`.

**Architecture:** One workflow starts for every `pull_request` and `push` to `main`. Its `dashboard` and `source` jobs run independently on PHP 8.5. A third job always runs after both jobs and succeeds only when both results are `success`.

**Tech stack:** GitHub Actions, Bash, PHP 8.5, Python 3 standard library, ShellCheck, Git.

---

## Baseline and workspace

- `origin/main` is `0216d86a60c02cc767833bbb5cdec8b57c54d335`, the expected post-PR-44 commit.
- The root checkout's local `main` is `a9587abf0a64ebedf202562571ce55225377228f`, nine commits behind `origin/main`. The nine intervening commits were reviewed. They include the PHP 8.5 policy and PE-001 source suites; no evidence changes PE-002's scope.
- The root checkout has a local `AGENTS.md` modification. Leave it untouched.
- No open pull requests were listed at baseline.
- Work in `.worktrees/chore-pe-002-platform-gate` on branch `chore/pe-002-platform-gate`, based on the exact `origin/main` SHA above.

## Files and responsibilities

- `.github/workflows/voelgoed-dashboard.yml`: trigger on all pull requests and pushes to `main`; preserve independent dashboard checks; add canonical source checks and the aggregate job; compare the correct event base and head with `git diff --check`.
- `site-code/bin/check-source.sh`: run the twelve canonical source runners, lint and parse all applicable tracked source files, and check the integration patch without applying it.
- `docs/superpowers/plans/2026-10-24-pe-002-platform-gate.md`: record this focused implementation plan.

## Task 1: Update the single GitHub Actions workflow

**File:** `.github/workflows/voelgoed-dashboard.yml`

- [ ] Remove all workflow `paths` filters. Keep `pull_request:` without path conditions and `push.branches: [main]`; remove `feat/**`.
- [ ] Keep the `dashboard` job independent and named `Dashboard checks`. Check out the event head, provision PHP 8.5, install ripgrep, and run `voelgoed-platform-core/bin/check.sh`.
- [ ] Add independent job `source`, visibly named `Canonical source checks`, on `ubuntu-latest`. Check out the event head with `fetch-depth: 0`, provision PHP 8.5, install ShellCheck, and run `site-code/bin/check-source.sh`.
- [ ] Keep one `git diff --check` owner in the dashboard job. For pull requests compare `pull_request.base.sha...pull_request.head.sha`; for pushes compare `event.before...github.sha`. Run only the matching event comparison.
- [ ] Add `platform_gate` with the exact visible name `VG Nova Platform Gate`, direct `needs: [dashboard, source]`, and `if: ${{ always() }}`. Log both results and exit successfully only if both are exactly `success`.
- [ ] Run `actionlint .github/workflows/voelgoed-dashboard.yml` and inspect the trigger and dependency expressions.

## Task 2: Add the canonical source-check entrypoint

**File:** `site-code/bin/check-source.sh`

- [ ] Resolve the repository root from the script location, enable `set -euo pipefail`, and use `git ls-files` for tracked-file discovery. This avoids scanning uninitialized submodules and ignores untracked files.
- [ ] Run these twelve commands in order, without adding the dashboard suite or live WordPress QA entrypoints:

  1. `php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php`
  2. `php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php`
  3. `php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php`
  4. `php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php`
  5. `php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`
  6. `php site-code/voelgoed-platform-core/tests/run-product-people-tests.php`
  7. `php site-code/person-foundation/tests/run-person-foundation-tests.php`
  8. `php site-code/event-participation/tests/run-event-participation-foundation-tests.php`
  9. `php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php`
  10. `php site-code/runtime-tests/tests/run-event-consumer-6c-qa-tests.php`
  11. `php site-code/runtime-tests/tests/run-event-participation-qa-cleanup-tests.php`
  12. `php site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php`

- [ ] Discover tracked `.php`, `.json`, `.py`, and shell files dynamically. Run `php -l` for each PHP file; parse each JSON file with Python's JSON parser; parse Python source with `ast.parse`; run `bash -n` and ShellCheck on tracked shell scripts, including this script.
- [ ] Run `git apply --check --directory=voelgoed-platform-core site-code/voelgoed-platform-core/integration.patch` from the repository root. Do not apply the patch or access the WordPress runtime.
- [ ] Ensure failures propagate under strict Bash and syntax checks create no persistent generated files.

## Task 3: Verify, audit, commit, and open the review PR

- [ ] Run `./voelgoed-platform-core/bin/check.sh` with PHP 8.5 and confirm the existing 127 dashboard tests pass.
- [ ] Run `./site-code/bin/check-source.sh` and record the output of all twelve suites and static checks.
- [ ] Run `actionlint .github/workflows/voelgoed-dashboard.yml`, `shellcheck` on tracked shell files, and `git diff --check` for both PR-style and push-style ranges.
- [ ] Review `git status --short`, `git diff --stat`, the full diff, and `git diff --check`. Confirm no runtime, domain, schema, deployment, branch-protection, or unrelated action changes.
- [ ] Commit the focused implementation and plan, push `chore/pe-002-platform-gate`, and open a PR titled `ci: add VG Nova platform source gate`.
- [ ] Inspect GitHub Actions on the exact PR head: both independent jobs use PHP 8.5, every requested source check passes, and `VG Nova Platform Gate` reports both dependencies as `success` and concludes `success`.
- [ ] Leave the PR unmerged and do not change branch protection, rulesets, or required checks.

## Acceptance criteria

- Every pull request gets the workflow regardless of changed paths; pushes to `main` also run it.
- Dashboard and canonical source checks stay independently visible and use PHP 8.5.
- All twelve source suites and the tracked-file static checks run; `integration.patch` is checked without modifying files.
- The aggregate gate runs after failed, cancelled, or skipped dependencies and fails unless both dependency results equal `success`.
- GitHub Actions succeeds on the exact PR head. No branch protection or required check is enabled.
