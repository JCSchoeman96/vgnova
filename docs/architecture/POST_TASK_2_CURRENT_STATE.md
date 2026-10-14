# Voelgoed current state after Task 2

Inspection date: 2026-10-14
Site: `vgnova` at `http://localhost:10039/`
Status: Local semantic topic migration proof complete

Task 2 populated the canonical `content_topic` taxonomy for the seven approved
local objects. The legacy `content_category` taxonomy remains live and
unchanged. The two taxonomies have no synchronization mechanism.

## Canonical `content_topic`

| Property | Current state |
|---|---|
| Terms | 55 |
| Root pillars | 7 |
| Relationships | 7 across 7 objects |
| Vrouegesondheid | Absent |
| Leefstyl | Absent |
| Primary Topic field | Not implemented |
| Secondary Topic mechanism | Not implemented |

Current relationships:

| Object | Type | Topic |
|---:|---|---|
| 514 | post | `nuwe-jy` |
| 516 | post | `geloof` |
| 518 | post | `ouerskap` |
| 519 | post | `werk-geld` |
| 521 | post | `mensstories` |
| 523 | product | `nuwe-jy` |
| 641 | post | `self` |

The root-level assignments reflect the existing legacy data. Task 2 did not
infer child topics from titles or content.

## Legacy `content_category`

| Property | Current state |
|---|---|
| Terms | 13 |
| Relationships | 8 across 7 objects |
| Status | Live and unchanged |
| Front-end use | Existing templates and display logic still consume this taxonomy |
| Cleanup or detachment | None |
| Sync with `content_topic` | None |

The exact legacy relationships remain:

| Object | Type | Legacy term |
|---:|---|---|
| 514 | post | `nuwe-jy` |
| 516 | post | `geloof` |
| 518 | post | `ouerskap` |
| 519 | post | `werk-geld` |
| 521 | post | `mensstories` |
| 523 | product | `natuurlik-gesond` |
| 523 | product | `nuwe-jy` |
| 641 | post | `self` |

Both taxonomies can refer to the same seven objects. Product 523 has canonical
topic `nuwe-jy`; its legacy `natuurlik-gesond` commercial relationship remains
only in `content_category`.

## Migration controls

Platform Core provides `wp vg-content-topic-migrate`. It runs a read-only dry
run by default, requires the reviewed source fingerprint for apply, records
only relationships it creates, and supports a narrowly scoped rollback. The
final local run record is `vgpc_content_topic_migration_local_v1`.

Source code lives in `site-code/voelgoed-platform-core/`. Runtime and source
copies of both taxonomy files matched after deployment. No front-end templates,
queries, or URLs changed.

## Untouched areas and non-goals

Task 2 did not change Leefstyl/commercial infrastructure, Featured Content
Manager, Vennoot mappings, HappyFiles sync, Bricks dependencies, navigation,
public URLs, Primary or Secondary Topic fields, or the production legacy
corpus. No Task 3 work has started.
