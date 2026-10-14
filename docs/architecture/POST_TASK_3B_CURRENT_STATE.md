# Voelgoed current state after Task 3B review

- Inspection date: 2026-10-14
- Site: `vgnova` at `http://localhost:10039/`
- Review branch: `feat/review-local-topic-seeds`
- Source baseline: `205169b7cebf8eeb4bb9ee09826feb263d15d1fd`
- Review scope: local objects 514, 516, 518, 519, 521, 523, and 641
- Data outcome: proposals documented; no WordPress classification was written

Task 3B inspected the actual content and current relationships for exactly seven Task 2 seed objects. Two proposals received HIGH confidence scores, but both would replace a Direct Topic recorded by Task 2. The repository does not document retiring Task 2 rollback after such a replacement, so no seed relationship was changed. The remaining objects are MEDIUM or LOW confidence and also remain unchanged.

## Live taxonomy snapshot

The read-only Novamira snapshot before documentation writes reported:

| Taxonomy | Terms | Roots | Direct relationships | Objects with relationships |
|---|---:|---:|---:|---:|
| `content_topic` | 55 | 7 | 7 | 7 |
| `content_category` | 13 | n/a | 8 | 7 |

The seven `content_topic` root assignments still match the seven Task 2 record rows. The `content_category` counts remain unchanged. The Task 2 option `vgpc_content_topic_migration_local_v1` has source fingerprint `80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847` and serialized-record SHA-256 `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95`.

## Current seven-object role state

| ID | Type | Title | Direct Topics | Primary | Secondary Topics API result | Effective Topics | Classification state | Legacy `content_category` |
|---:|---|---|---|---|---|---|---|---|
| 514 | `post` | Jou oggend-routine vir meer rustigheid | `nuwe-jy` | None | `nuwe-jy`* | `nuwe-jy` | `MIGRATION_SEED` | `nuwe-jy` |
| 516 | `post` | Geloof wat jou werkweek dra | `geloof` | None | `geloof`* | `geloof` | `MIGRATION_SEED` | `geloof` |
| 518 | `post` | Ouerskap sonder perfeksie | `ouerskap` | None | `ouerskap`* | `ouerskap` | `MIGRATION_SEED` | `ouerskap` |
| 519 | `post` | Geldwenke vir enkelma's | `werk-geld` | None | `werk-geld`* | `werk-geld` | `MIGRATION_SEED` | `werk-geld` |
| 521 | `post` | Herstel na verlies: 'n mensstory | `mensstories` | None | `mensstories`* | `mensstories` | `MIGRATION_SEED` | `mensstories` |
| 523 | `product` | Voelgoed Welstand Pakket | `nuwe-jy` | None | `nuwe-jy`* | `nuwe-jy` | `MIGRATION_SEED` | `nuwe-jy`, `natuurlik-gesond` |
| 641 | `post` | QA Self Featured Content Test | `self` | None | `self`* | `self` | `MIGRATION_SEED` | `self` |

* With no Primary selected, the role API returns all Direct Topics from its Secondary Topics getter. This is a mechanical result, not an editorially reviewed Secondary assignment. All seven current seeds are roots, so their current Effective Topics equal their Direct Topics.

## Proposed classifications not applied

| ID | Proposed Primary | Proposed Secondary Topics | Proposed Effective Topics | Confidence | Decision |
|---:|---|---|---|---|---|
| 514 | None selected; `stres-balans` remains uncertain | None | Not proposed | LOW | Needs human review |
| 516 | Unselected possibility: `geloofslewe` | None | If accepted: `geloofslewe`, `geloof` | MEDIUM | Needs human review |
| 518 | Unselected possibility: `ouerskap` | None | If accepted: `ouerskap` | MEDIUM | Needs human review |
| 519 | `begroting-geldbestuur` | None | `begroting-geldbestuur`, `geld`, `werk-geld` | HIGH | Architecture issue: rollback lifecycle decision required |
| 521 | Unselected possibility: `mensstories` | None | If accepted: `mensstories` | MEDIUM | Needs human review |
| 523 | `oefening-fiksheid` | None | `oefening-fiksheid`, `nuwe-jy` | HIGH | Architecture issue: rollback lifecycle decision required |
| 641 | None | None | Not proposed | LOW | Needs human decision about QA fixture status |

See [CONTENT_TOPIC_SEED_EDITORIAL_REVIEW.md](CONTENT_TOPIC_SEED_EDITORIAL_REVIEW.md) for the content evidence, rationale, and term-parent checks for each object.

## Primary, Secondary, and effective ancestors

- Objects with `REVIEWED` classification state: none.
- Persisted Primary assignments: none.
- Objects still `MIGRATION_SEED`: all seven.
- Editorial Secondary Topics: none have been confirmed or written.
- Current API Secondary result: each object's Direct Topic, because each Primary is empty.
- All seven current Direct Topic terms exist in `content_topic`.
- Current Effective Topics: each assigned root term only.
- Proposed effective ancestors are not stored as Direct Topics.
- No redundant parent/child Direct Topic relationship was created.

## Task 2 rollback lifecycle

**Status: blocked pending architecture decision before any replacement of a Task 2 seed.** The Task 2 rollback remains operational while all seven recorded relationships and the migration record remain intact. If a seed relationship is replaced, rollback will detect drift before deletion, return `vgpc_topic_migration_rollback_drift`, and leave the record and remaining data untouched. The repository does not document retiring that rollback or establish a new classification snapshot path. This review did not silently retire it.

## Legacy and product separation

- `content_category` remains live and unchanged: 13 terms and 8 relationships across 7 objects.
- Product 523 still has legacy `content_category` terms `nuwe-jy` and `natuurlik-gesond`.
- Product 523 still has only `nuwe-jy` in `content_topic`.
- `natuurlik-gesond` did not enter `content_topic` and is not Primary, Secondary, or Effective semantic topic data.
- No taxonomy terms, relationships, attachments, templates, URLs, or commercial mappings were changed.

## Editor and verification status

- Browser editor UX verification: **PENDING**. The browser session redirected the product editor URL to the site's login form. Novamira API inspection remained available; no credentials were requested or sent.
- Content-topic suite: `php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php` printed `50 content topic tests passed.`
- Platform Core checks: `bash voelgoed-platform-core/bin/check.sh` printed `127 dashboard tests passed.` and `All dashboard checks passed.`
- PHP lint: `find site-code/voelgoed-platform-core voelgoed-platform-core -type f -name '*.php' -print0 | xargs -0 -n1 php -l` checked 41 files; each printed `No syntax errors detected`.
- Staged diff check: `git diff --cached --check` exited 0 with no output.
- Production corpus migration: not performed.
- Task 4: not started.
