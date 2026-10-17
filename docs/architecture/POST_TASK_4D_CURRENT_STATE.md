# Post Task 4D Current State

| Snapshot detail | Value |
|---|---|
| Task | Voelgoed Task 4D — Local Proof/Test Fixture Lifecycle and Decommission Audit |
| Date | 2026-10-17 |
| Baseline | origin/main at 8796ca0c1467856a61e1d0b5a4c05e21e815a21a (PR #10) |
| Audit branch | docs/local-fixture-lifecycle-audit |
| Runtime inspected | WordPress at http://localhost:10039/ through the read-only Novamira CLI profile vgnova. The attached Novamira MCP endpoint targeted localhost:10059 and was not used for this runtime snapshot. |

## Object classifications and recommendations

| ID | Type / status | Fixture class | Recommendation | Confidence |
|---:|---|---|---|---|
| 375 | post / publish | Unresolved demo/import placeholder | NEEDS_PROVENANCE | Low |
| 513 | post / trash | Trashed duplicate of 514 | READY_TO_REMOVE | High |
| 514 | post / publish | Task 2 SYSTEM_TEST | READY_TO_REMOVE | High |
| 516 | post / publish | Task 2 SYSTEM_TEST | READY_TO_REMOVE | High |
| 518 | post / publish | Task 2 SYSTEM_TEST | READY_TO_REMOVE | High |
| 519 | post / publish | Task 2 SYSTEM_TEST, reviewed classification | READY_TO_REMOVE | High |
| 521 | post / publish | Task 2 SYSTEM_TEST | DEFER_REMOVAL | High |
| 523 | product / publish | Task 2 SYSTEM_TEST product, reviewed classification | DEFER_REMOVAL | High |
| 641 | post / publish | Explicit QA fixture and active Self FCM target | DEFER_REMOVAL | High |

## Removal recommendations

- Ready for a separate controlled removal task: 513, 514, 516, 518, 519.
- Deferred: 521 until Bricks template Posts preview ID 521 is replaced; 523 until Bricks Article Card preview ID 523 is replaced; 641 until a reproducible Featured Content Manager → Bricks Self test replaces the current QA scenario.
- Provenance unresolved: 375. Its content is clearly Lorem/sample-style and not editorial, but the source or import history was not recovered.

## Task 2 proof and unresolved dependencies

The original migration option and retired marker remain valid historical provenance without the live proof objects. Source tests create synthetic records and preserve taxonomy registration, Primary Topic, derived Secondary, Effective Topic ancestor, and retired lifecycle behavior. The migration's source-controlled fixed-ID manifest remains as history. After fixture removal, the read-only default dry-run is expected to report baseline mismatch and blockers with lifecycle still retired; apply and rollback remain blocked by the retired guard. The record is not rewritten.

The only current runtime object dependency among Task 2 proof records is outside the migration proof: 641 is the current Self-category article used to render the Featured Content Manager selection. Posts 521 and product 523 also remain active Bricks editor preview inputs.

## Relationship ownership and simulated counts

Current owners of all content_category relationships:

- 514: nuwe-jy
- 516: geloof
- 518: ouerskap
- 519: werk-geld
- 521: mensstories
- 523: natuurlik-gesond and nuwe-jy
- 641: self

Current owners of all content_topic relationships:

- 514: nuwe-jy
- 516: geloof
- 518: ouerskap
- 519: begroting-geldbestuur (Primary)
- 521: mensstories
- 523: oefening-fiksheid (Primary)
- 641: self

Current counts are 13 content_category terms / 8 relationships / 7 objects; 55 content_topic terms / 7 relationships / 7 objects; 3 editorial_format terms / 0 relationships / 0 objects; 0 editorial_collection terms / 0 relationships / 0 objects.

After only the READY_TO_REMOVE set:

| Measure | Simulated result |
|---|---:|
| Standard posts | 3 published, 0 trash |
| Products | 21 total |
| content_category | 13 terms, 4 relationships, 3 objects |
| content_topic | 55 terms, 3 relationships, 3 objects |
| editorial_format | 3 terms, 0 relationships, 0 objects |
| editorial_collection | 0 terms, 0 relationships, 0 objects |
| product_cat | 1 term, 17 relationships, 17 objects |

If the remaining Task 2 proof objects 521, 523, and 641 are removed after their gates pass, the category and topic relationships would reach 0 while their taxonomies remain registered with 13 and 55 terms respectively. Product count would become 20. This does not authorize taxonomy removal.

## Runtime invariants and task status

| Invariant | Result |
|---|---|
| Posts changed | NO |
| Products changed | NO |
| Taxonomy relationships changed | NO |
| ACF values changed | NO |
| Media changed | NO |
| WordPress writes | 0 |
| Fixture removed | NO |
| Historical migration record or marker changed | NO |

The original migration dry-run reported lifecycle=retired, baseline_matches=true, and no blockers before any hypothetical removal. The final read-only re-query matched the initial object statuses and modified timestamps, taxonomy counts and owners, Primary values, Featured Content Manager options, and migration record, checksum, and retired marker. Published fixture routes returned HTTP 200 with self-canonical metadata and no noindex directive on localhost; trashed post 513 returned 404. Current local URLs do not establish external internet exposure.

See [Local Fixture Lifecycle Audit](LOCAL_FIXTURE_LIFECYCLE_AUDIT.md) and [Local Fixture Decommission Manifest](LOCAL_FIXTURE_DECOMMISSION_MANIFEST.csv) for evidence and per-object gates.
