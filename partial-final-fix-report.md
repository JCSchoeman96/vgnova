# Partial final fix report

**Scope:** Whole-branch review findings at `7ec09048d47114d2c5e41ed3f2654457ec9ddbb2`.

## Fixes

- `PersonRelationshipAdmin::submitted_relationship_fields()` now accepts the ACF payload when `post_ID` is absent or zero. ACF's post form accepts both cases and passes the actual post ID to `acf/save_post`, which is the ID used for persistence. A positive explicit ID must still match, so nested callbacks for another post ignore that payload.
- `PersonRelationshipSourceLock` now retries a thrown `RELEASE_LOCK()` query once inside the active release call. It retains the lock and failed-release state if both attempts throw. A `0` or `NULL` result remains unconfirmed and is not retried against a possibly different owner.
- The Person test harness models ACF's post-save callback with the actual callback ID and priority-10 field persistence. It covers new and existing sources with missing and zero `post_ID`, an unsafe published partial state, invalid draft values, and a nested callback with a mismatched explicit ID. A shutdown regression test models the `WP_Hook` priority behavior that skips a newly added earlier-priority callback after iteration reaches `PHP_INT_MAX`.

## Verification

Read-only source inspection confirmed these behaviors in the installed ACF `ACF_Form_Post::allow_save_post()`, `ACF_Form_Post::save_post()`, and `acf_save_post()` implementations, plus WordPress `WP_Hook::resort_active_iterations()`.

Passed:

- `php site-code/voelgoed-platform-core/tests/run-person-relationship-tests.php`
- `php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php` (142 tests)
- `php site-code/runtime-tests/tests/run-runtime-mutation-policy-tests.php` (31 tests)
- `php site-code/bin/tests/test-person-publication-source-policy.php`
- `php site-code/bin/check-runtime-mutation-policy.php`
- `php site-code/bin/check-person-publication-source.php`
- PHP syntax checks for all five changed PHP files
- `git diff --check`

No WordPress runtime or database mutations were performed. The branch is ready for fresh whole-branch review. This report does not claim that review has passed.
