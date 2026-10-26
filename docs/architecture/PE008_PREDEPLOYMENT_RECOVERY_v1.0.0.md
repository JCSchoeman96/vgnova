# PE-008 predeployment recovery v1.0.0

**Status:** Source-only remediation prepared for draft-PR review. PE-008 runtime deployment remains blocked. No Platform Core deployment has occurred.

## Baseline and artifact authority

The exact source baseline is `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`. The current-main artifact came from successful workflow `40311497761`, artifact `12645287640`, named `voelgoed-platform-core-50ee4bee1ae1405522df7b67634b8935d4e9d9ed`.

| Authority | Value |
| --- | --- |
| Transport ZIP SHA-256 | `e90073d5a7a3e2dff0a871090c7ae036d7a8922f60a589d11ca85870965a23ed` |
| Published manifest SHA-256 | `b728e69d1488a28601982bfb5e84b4104b11cab5f6c9ae1dcd2a38192ef696a7` |
| Embedded archive SHA-256 | `22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9` |
| Tree SHA-256 | `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a` |
| File count | `55` |

The published ZIP, archive, and manifest are preserved under `/home/jcschoeman96/.cache/vgnova/pe008-20261026/current-main-50ee4bee/`. The independent exact-output verifier passed for source commit `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` and reported the same 55-file tree, archive, and published-manifest hashes. The published manifest bytes are authoritative.

The Product Contribution QA authority changed from the `origin/main` 54-file authority at `7af938ac6f4422b1fa779b52444121e7a9293995` to the actual current-main 55-file publication at `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`. The old manifest bytes remain in the rollback/classification fixture; the new fixture contains the exact downloaded published manifest bytes.

The source gate also builds a verifier artifact with `source_ref_requested=HEAD`. Its manifest digest was `b44d99008f62bba3695a4ec13a81eb8808d2e8fec105bf52bf168e71fad77ced`; its tree and archive hashes matched. That verifier manifest is not the release manifest. The exact published current-main manifest bytes and digest `b728e69d1488a28601982bfb5e84b4104b11cab5f6c9ae1dcd2a38192ef696a7` remain authoritative because the manifest records the requested source ref.

## Aborted pre-mutation QA invocation

The PE-008 attempt included `site-code/runtime-tests/product-contribution-qa.php` in a booted WordPress process. At line 1694 the file invokes `vg_product_contribution_qa_run()` whenever `ABSPATH` exists and `VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY` does not.

The run function immediately calls `vg_runtime_mutation_assert_session('vgnova-local-10039')` at line 1344. It would create the run ID and QA token at line 1345 only after that assertion. The uncoordinated invocation failed at the session guard, before preflight or the guarded mutation body.

Record this incident as `ABORTED_PRE_MUTATION_QA_INVOCATION`:

| Action or state | Result |
| --- | --- |
| Live QA entrypoint invoked | 1 |
| PE-007 session assertion | Rejected before token generation |
| QA mutation body started | No |
| Cleanup window opened | No |
| QA owner records created | No |
| Product Contribution records created | No |
| Persistent WordPress writes from the invocation | 0 |
| Successful live Product Contribution QA runs | 0 |
| Platform Core deployment | 0 |

Do not describe the invocation as a successful QA run. The entrypoint call remains part of the incident record.

## Person 1705 runtime baseline

The authorized site identity is `http://localhost:10039`, database `local@localhost`, WordPress `7.1.3`, and PHP `8.5.12`. Platform Core remains active at version `0.1.0` from the authorized plugin directory.

Read-only inspection found exactly one Person: ID `1705`, type `person`, status `publish`, title `Lynette Beer`, slug `lynette-beer`, created `2026-10-26 09:05:12` UTC. Post content and excerpt are empty. The only postmeta row is `_masteriyo_has_content=0`. The only taxonomy relationships are `Mentor` and `Kenner`; there are no `content_topic` relationships. `get_fields(1705, false)` returned `false`, and the postmeta query showed no ACF field values. No additional Person record was found.

The current Person row, postmeta row, and nine-column taxonomy rows match the preserved Task 10A capture. The raw capture is stored at [`person-1705-task10a-capture.raw.json`](../../site-code/runtime-tests/fixtures/person-1705-task10a-capture.raw.json), SHA-256 `acba754272b709d5088474acde4bd9733f91744988a16b812bacfdea5dc68fbd`. The normalized deterministic fixture is [`person-1705-fingerprint-capture.json`](../../site-code/runtime-tests/fixtures/person-1705-fingerprint-capture.json), SHA-256 `8bb5f5a701d2554c235e55a7759e942dbada36e6c4a708fbc5b7e53e7310c316`.

The fixture pins both query contracts and the saved `ARRAY_A` rows. PHP 8.5.4 source tests confirm all retrieved database scalar values are strings. Lists retain integer indexes, associative rows retain the SQL projection order, and canonicalization is `hash('sha256', serialize($value))` with `serialize_precision=-1`.

## Fingerprint provenance

The old expected post and relationship hashes came from the Task 10A standalone capture. The Product Contribution guard compared them with output from a different query shape. The same captured values reproduce both sets of hashes offline.

| Surface | Task 10A SQL and input | Rows | Task 10A bytes and hash | Product Contribution SQL and input | Rows | Product Contribution bytes and hash |
| --- | --- | ---: | --- | --- | --- |
| Post | `SELECT * FROM {posts} WHERE post_type = 'person' ORDER BY ID ASC`; hash the associative row | 1 | 779 bytes; `792654d2d6a47aec75ef635e4bc51e3a67d35f621dfef021f1dcd4f97e6e080f` | `SELECT * FROM {posts} WHERE ID IN (1705) ORDER BY ID ASC`; hash the one-row list | 1 | 789 bytes; `f2a980eb64045700dc3a4ca07a7950ec3cb1daea14e4a400ac48e6837b2e9b5b` |
| Postmeta | `SELECT * FROM {postmeta} WHERE post_id = 1705 ORDER BY meta_id ASC`; hash the one-row list | 1 | 137 bytes; `0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5` | `SELECT * FROM {postmeta} WHERE post_id IN (1705) ORDER BY post_id ASC, meta_key ASC, meta_id ASC`; hash the same one-row list | 1 | 137 bytes; `0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5` |
| Taxonomy relationships | Select `object_id,term_taxonomy_id,term_order,taxonomy,term_id,slug,name,parent,count`, ordered by `term_taxonomy_id,term_order,taxonomy,term_id,slug` ascending; hash the nine-column row list | 2 | 494 bytes; `220c90c6c8838738030fe3ae3c3011446cd9d6b6b0162cba8c6597d438ab13b6` | Select `object_id,term_taxonomy_id,term_order,taxonomy,term_id,slug`, ordered by `object_id,term_taxonomy_id,term_order,taxonomy,term_id,slug` ascending; hash the six-column row list | 2 | 364 bytes; `1d347313f990ba8063fe34926ef537e85d2dc4bdc575e5369338be45b9bdf7fb` |

Postmeta is the control. Both query paths return the same sole row, build the same list, and serialize the same bytes. Their ordering clauses cannot change that one-row list. Posts differ because Task 10A hashes one associative row while Product Contribution hashes a list containing that row. Relationships differ because the queries project nine columns and six columns respectively. The captured values and their output order reproduce the hashes deterministically, so this is a `HASH_PROVENANCE_DEFECT`, not live data drift or hash nondeterminism.

The mismatched constants first appeared in deployment-tool commit `445a656448d3222befd721117fe7cef5aebbba8a`. The initial RED regression computed the group hashes from the captured rows and failed because the current Person guard still expected the Task 10A hashes: 26/27 integrity checks passed. After changing only the expected group hashes and moving the query/canonical serialization into `site-code/runtime-tools/pe008-protected-state.php`, the suite passed 27/27. The standalone PE-008 protected-state regression also passed. It does not load WordPress or include the Product Contribution QA file.

The Product Contribution authority now pins the actual `50ee4bee…` published manifest bytes. The old `54`-file fixture remains preserved for exact-old runtime classification. The earlier temporary `HEAD`-build manifest digest is not used.

## Runtime and coordinator state

The active plugin remains `OLD_AUTHORITY_EXACT`: 54 files, tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`, missing `0`, extra `0`, content mismatch `0`, mode mismatch `0`, symlinks `0`, unsupported objects `0`. The integrity check used the old published manifest and `platform-core-runtime-integrity.php`; it did not include or invoke the Product Contribution QA entrypoint.

The post-incident read-only queries found zero Product Contribution posts, zero Event Participation posts, and zero Product Contribution QA owner-meta rows. Person 1705 remains the only Person. The WP-CLI coordinator status returned no status error: the host filesystem lock was free, DB lock A was free, and DB lock B was free. No PE-007 mutation session was acquired during PE-008R.

The superseded stage at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-20261026T105633Z` was not used or modified. No PE-008 backup was created.

## Source verification

All source gates passed after the remediation:

- `./site-code/bin/check-source.sh` passed, including PHP lint, JSON validation, Python AST parsing, `bash -n`, ShellCheck, the Platform Core build/source gate, FCM integration, and runtime mutation policy scan.
- `./voelgoed-platform-core/bin/check.sh` passed all 127 dashboard checks.
- Product Contribution integrity regression: 27/27 passed.
- PE-008 protected-state fingerprint regression passed from the captured fixture only.
- PE-008 deployment helper regression: 23 cases passed using temporary trees only.
- Runtime mutation core: 73 tests passed; session: 19/19; policy: 31/31; static policy scanned 131 tracked PHP files and passed.
- FCM integration: 3 tests passed.
- `actionlint` passed with version 1.7.12; `git diff --cached --check` passed.

The source gate's local verifier build retained the Platform Core tree hash and archive hash. No canonical Platform Core module source file changed. No live QA entrypoint ran.

## PE-008R mutation counts

| Mutation | Count |
| --- | ---: |
| Platform Core deployment | 0 |
| ACF writes | 0 |
| Taxonomy writes | 0 |
| Post writes | 0 |
| Persistent WordPress DB writes | 0 |
| Bricks writes | 0 |
| FluentSnippet writes | 0 |
| Rewrite flushes | 0 |
| Plugin activation/deactivation writes | 0 |
| Successful live Product Contribution QA runs | 0 |
| Aborted pre-mutation QA invocations | 1 |

No credentials are stored in this report. A later approved PE-008 execution must create a new stage from artifact `12645287640`; it must not use the superseded staging directory. PE-008 remains blocked from deployment until independent review of the exact draft PR head and a separate authorization to run the deployment.
