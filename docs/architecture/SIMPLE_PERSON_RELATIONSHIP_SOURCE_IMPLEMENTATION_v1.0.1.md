# Simple Person relationship source implementation

**Version:** 1.0.1
**Status:** Complete in source; runtime activation has not started
**Historical source verification commit:** `5c879918e403ec0d8fbc6b70e92668c789d5d526`
**Evidence commit:** Recorded in Git history. This document does not embed its own commit SHA.
**Publication-validity authority:** [Architecture amendment v0.1.1](../superpowers/specs/2026-10-27-simple-person-relationship-architecture-amendment_v0.1.1.md)

## State

```text
SOURCE_IMPLEMENTATION=COMPLETE
RUNTIME_ACTIVATION=NOT_STARTED
RUNTIME_TABLE_CREATED=NO
ACF_RUNTIME_SYNC=NO
INDEX_REBUILD_RUN=NO
WORDPRESS_BUSINESS_MUTATIONS=0
BRICKS_MUTATIONS=0
REPRESENTATIVE_DATA_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
ARTICLE_TO_PERSON_ARCHITECTURE=RESOLVED_IN_SOURCE
PERSON_TO_COURSE_ARCHITECTURE=RESOLVED_IN_SOURCE
VS1_MASTER_GATE=STILL_BLOCKED
```

## Source contracts

Article source type `post` owns `primary_author` (optional, single), `additional_authors`, `expert_contributors`, and `reviewed_by`. Course source type `mto-course` owns `lead_instructor` (optional, single), `additional_instructors`, and `experts`. All fields reference canonical `person` posts. One Person may hold only one approved role per source object. Source relationships remain authoritative; the Person relationship index is disposable derived state.

The index table is `{$wpdb->prefix}vg_person_relationship_index`. It stores source post ID and type, Person ID, role key, and role position. Its uniqueness and lookup indexes follow the approved architecture design. Reverse reads use Platform Core's repository/service boundary, with public eligibility checks on both Person and source. No serialized ACF-meta `LIKE` path supplies production reverse results, and no copied Article or Course lists are written onto Person.

Ordinary editor-save projection remains an application writer with a narrow, reviewed mutation-inventory classification. Setup, full rebuild, and repair are administrative mutations protected by the PE-007 runtime-mutation session guard. The source mutation inventory and tests verify these classifications.

## Publication validity

Architecture amendment v0.1.1 supersedes only the publication-validity wording in design v0.1.0. Platform Core guarantees pre-publication Person relationship validation on supported Voelgoed publication paths: normal editor saves, supported REST/API updates, Platform Core-governed scheduled publication, and repository-owned Platform Core publication operations. Invalid Article/Course Person relationships must block publication on those paths.

Repository-owned production code must not call `wp_publish_post()` directly for governed Articles or Courses unless an approved pre-publication validation boundary has run. Unsupported low-level direct publication bypasses cannot be claimed to have a pre-write veto. If Platform Core observes an invalid governed Article/Course transition into `publish`, it logs a sanitized high-severity diagnostic, marks the source dirty, and makes reverse consumers fail safely. It does not undo the write or rewrite authoritative ACF relationships. No global SQL interception is used.

## Task D source verification

Task D aligns the source evidence with architecture amendment v0.1.1. Focused tests exercise the supported editor pre-save validator and the scheduled publication gate. The low-level bypass fixture begins with `post_status = publish`; its detector test confirms the status stays published, reports the integrity violation, persists the dirty marker, and leaves authoritative ACF relationships unchanged. A source test scans Platform Core production includes and rejects registration of the global WordPress `query` filter. The separate publication source policy test rejects `wp_publish_post()` references in repository-owned production PHP, including dynamic-dispatch forms, while excluding tests and vendor code.

The aggregate `php site-code/voelgoed-platform-core/tests/run-person-relationship-tests.php` run passed 84 test records. `php site-code/bin/tests/test-person-publication-source-policy.php` passed, and `php site-code/bin/check-person-publication-source.php` scanned 80 repository-owned production PHP files successfully. These are source-level checks only. They do not establish live WordPress runtime behavior or universal prevention of unsupported low-level publication.

## Verification evidence

The full `./site-code/bin/check-source.sh` passed before and after this evidence document was added. The checker ran all existing source suites, PHP syntax checks, JSON and Python parsing, shell syntax and ShellCheck checks, the Platform Core integration patch check, runtime mutation inventory checks, and the exact-HEAD build verifier.

Reported suite totals included 67 Content Topic tests, 69 Event Participation tests, 12 Event Participation Bricks tests, 13 Event Role Seeder tests, 142 Product Contribution tests, 101 Product People tests, 12 Person foundation tests, 14 Person migration tests, 3 Event Participation schema contract tests, 11 editorial dimension seeder tests, 73 runtime mutation core tests, 19 runtime mutation session tests, and 31 runtime mutation policy tests. Product Contribution QA suites passed at 12/12, 3/3, 1/1, 5/5, 9/9, and 24/24. The six Person relationship test runners all passed, including the mutation-inventory classification check.

The historical exact-commit artifact was built and independently verified for `5c879918e403ec0d8fbc6b70e92668c789d5d526`. That artifact contains 66 files. Tree SHA-256: `0af6450b79ab252a2da848235b7f660796b7043cab6911cbe1b28717be2e0a39`. Archive SHA-256: `a83cdb55f0bff77cc8bc1048686c4e93a7b40d4d5325eaab08829d0fb3e1c972`. Manifest SHA-256: `5711f205b9e9430e556418b4963ab321175d52d2ce5117e67bce73bba26a1988`. The verifier reported two exact-commit builds, independent archive verification, negative cases, and cleanup passed.

Targeted production searches found no `meta_value LIKE` or `LIKE ... meta_value` path in Platform Core includes or the plugin entrypoint. Person overlay searches found no serialized-meta reverse query. The only `update_post_meta` calls in the Person overlay set the internal dirty-generation and dirty-state markers on source objects; no Person reverse relationship list is written. The forward searches found `articles_for_person()` and `courses_for_person()` service methods.

PR-range whitespace validation uses `BASE=$(git merge-base origin/main HEAD)` and `git diff --check "$BASE...HEAD"`. The exact result and final commit SHA are recorded in the Task 10 report and SDD ledger after the evidence commit. No push or PR was performed by this task.
