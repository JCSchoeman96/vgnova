# Post Task 4E Current State

| Snapshot detail | Value |
|---|---|
| Task | Voelgoed Task 4E — Controlled Removal of Approved Local Proof/Test Fixtures |
| Date | 2026-10-17 |
| Git baseline | Fresh `origin/main` at `d77c4515ec42ba6af294e7372906084630e1e52a` (PR #11) |
| Branch / worktree | `chore/remove-approved-local-fixtures` / `.worktrees/chore-remove-approved-local-fixtures` |
| Runtime | WordPress at `http://localhost:10039/`, WordPress 7.1.3, Novamira CLI profile `vgnova` |
| Mutation | `wp post delete 513 514 516 518 519 --force` through Novamira `run-wp-cli`; all five reported success |

## Removal and protected objects

The exact Task 4D `READY_TO_REMOVE` set was permanently deleted: 513, 514, 516, 518, and 519. Each ID is absent as a post after removal. No object outside that set was included in the deletion command.

Protected and unchanged:

| ID | Task 4D status | Post-removal state | Unchanged identity / classification |
|---:|---|---|---|
| 375 | `NEEDS_PROVENANCE` | post / publish | `integer-maximus-sapien-in-bibendum`; modified `2026-05-20 15:15:58`; no taxonomy or Primary Topic |
| 521 | `DEFER_REMOVAL` | post / publish | `herstel-na-verlies`; modified `2026-05-27 08:05:54`; `content_category=mensstories`, `content_topic=mensstories`; no Primary Topic |
| 523 | `DEFER_REMOVAL` | product / publish | `welstand-pakket`; modified `2026-05-29 10:24:12`; `content_category=natuurlik-gesond, nuwe-jy`, `content_topic=oefening-fiksheid`, Primary `oefening-fiksheid` |
| 641 | `DEFER_REMOVAL` | post / publish | `qa-self-featured-content-test`; modified `2026-05-29 12:40:52`; `content_category=self`, `content_topic=self`; no Primary Topic |

No type, status, slug, modified timestamp, taxonomy relationship, or Primary Topic value changed on these four objects.

## Counts and remaining relationship owners

| Measure | Before | After |
|---|---:|---:|
| Standard posts, published | 7 | 3 |
| Standard posts, trash | 1 | 0 |
| Remaining published standard post IDs | — | 375, 521, 641 |
| Products, all statuses | 21 | 21 |
| `content_category` terms / relationships / objects | 13 / 8 / 7 | 13 / 4 / 3 |
| `content_topic` terms / roots / relationships / objects | 55 / 7 / 7 / 7 | 55 / 7 / 3 / 3 |
| `editorial_format` terms / relationships | 3 / 0 | 3 / 0 |
| `editorial_collection` terms / relationships | 0 / 0 | 0 / 0 |

Remaining `content_category` owners:

- 521 → `mensstories`
- 523 → `natuurlik-gesond`, `nuwe-jy`
- 641 → `self`

Remaining `content_topic` owners:

- 521 → `mensstories`
- 523 → `oefening-fiksheid`
- 641 → `self`

The only remaining Primary Topic is 523 → `oefening-fiksheid` (term 357). Post 519's Primary Topic relationship disappeared with the deleted post. No `content_topic` term, root, registration, or seed logic changed. `content_category` remains live with 13 terms and its existing consumers. `editorial_format` remains `article`, `audio`, and `video` with no assignments; `editorial_collection` remains empty. No default classification or collection term was created.

Products remain 21 total (10 published, 7 draft, 1 private, 3 trash). No product was modified.

## Migration provenance

| Value | Before / after |
|---|---|
| Historical option | `vgpc_content_topic_migration_local_v1` |
| Migration ID | `content-topic-local-v1` |
| Saved fingerprint | `80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847` |
| Serialized record SHA-256 | `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95` |
| Lifecycle | `retired` |
| Retired timestamp | `2026-10-15T04:53:54+00:00` |
| Retirement reason | `editorial classification supersedes local migration seeds` |
| Serialized marker SHA-256 | `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3` |
| Record / marker changed | No / No |

The default read-only `wp vg-content-topic-migrate --format=json` command reports lifecycle `retired`, `baseline_matches=false`, and current computed fingerprint `6cd0e2d4e8f81002db89603e92c502989644c3a498825fdebc4630f917ab6199`. Its blockers report the expected current baseline difference: 4 legacy relationships across 3 objects, a count mismatch from the saved 13-term / 8-relationship baseline, and a saved-record mismatch against current source data. Apply and rollback remain blocked by the retired lifecycle. This is expected after fixture removal, not migration corruption. No `--apply`, `--rollback`, or `--retire` was run.

## Media and remaining consumers

All five attachments survive with file and metadata present and unchanged metadata hashes. WordPress set `post_parent` to 0 when deleting each parent, which is the expected permitted side effect.

| Attachment | Parent before | Parent after | File / metadata |
|---:|---:|---:|---|
| 569 | 513 | 0 | Present / present |
| 515 | 514 | 0 | Present / present |
| 517 | 516 | 0 | Present / present |
| 567 | 518 | 0 | Present / present |
| 520 | 519 | 0 | Present / present |

No attachment was deleted. The relevant HappyFiles state now has 15 terms and 2 relationships across 2 objects: 521 → `mensstories` and 641 → `self`. There are no HappyFiles relationships to deleted objects; no term was removed.

Bricks Posts template 372 still previews 521. Bricks Article Card template 690 still previews 523. Featured Content Manager selections are unchanged (`featured_nuwe_jy=542`, `featured_geloof=534`, `featured_self=531`; other fields null). Post 641 still returns HTTP 200 and renders the Self featured product “Slaapmasker Deluxe” (531).

Deleted routes `/content/oggend-routine-rustigheid-2/`, `/content/geloof-werkweek/`, `/content/ouerskap-sonder-perfeksie/`, and `/content/geldwenke-enkelma/` return HTTP 404 without redirects. The removed trashed duplicate query `/?p=513` also returns 404 without redirect. No redirects were created.

## Reconciliation and scope

Task 4D's READY-set simulation matched the runtime exactly: 3 published standard posts (375, 521, 641), 0 trashed standard posts, 21 products, `content_category` 13 terms / 4 relationships / 3 objects, `content_topic` 55 terms / 7 roots / 3 relationships / 3 objects, `editorial_format` 3 terms / 0 relationships, and `editorial_collection` 0 terms / 0 relationships.

Runtime impact: 5 posts permanently deleted; 0 media deleted; 0 taxonomy terms deleted; 0 migration records or markers changed; 0 protected objects changed; 0 product changes; 0 Platform Core source changes; 0 unrelated writes. The only expected secondary changes were the five attachment parent pointers to 0 and normal WordPress cleanup of relationships owned by deleted posts.

The original Task 4D recommendations remain preserved in `LOCAL_FIXTURE_DECOMMISSION_MANIFEST.csv`. Current outcomes are tracked separately in `LOCAL_FIXTURE_REMOVAL_RESULT.csv`. Task 4F has not started.

See [Task 4E fixture removal report](TASK_4E_FIXTURE_REMOVAL_REPORT.md) for the full preflight and removal evidence.
