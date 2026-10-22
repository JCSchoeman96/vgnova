# Post-Task 7C Current State

**Status:** Draft during Task 8; Task 10 closeout pending. This snapshot records the authorized read-only audit state. It does not authorize migration, cleanup, route changes, or runtime writes.

## Repository and runtime identity

| Item | Verified state |
|---|---|
| Execution baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64` (`origin/main` at Task 1) |
| Audit branch | `docs/task-7c-person-consumer-readiness-audit` |
| Runtime host and actual port | `http://localhost:10039/`; GET reported port `10039` |
| `ABSPATH` | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/` |
| `home_url` and site | `http://localhost:10039`; Voelgoed; WordPress 7.1.3 |
| Database identity | `local`, server `jc-kubuntu:10038`, UUID `028fe7c9-3720-11f1-8cb0-a8a159280deb`, MySQL 8.0.35 |
| Identity evidence | `/tmp/task-7c-audit-abde5c-20261022/preflight-identity.txt`; the selected verified profile was VGNova `localhost` |

The pre-audit identity gate and protected-state fingerprints were recorded before substantive inspection. The merged plan’s `c61b029...` value is its pre-merge planning baseline; Task 7C uses the verified `abde5c...` merge commit as its execution baseline.

## Current Person and relationship state

| Record or relationship | Current state and ruling |
|---|---|
| `person` records | 0 persistent records across statuses in the pre-audit fingerprint and fresh read-only audit |
| `event_participation` records | 0 persistent records across statuses |
| `medewerker` profiles | 5 published profiles; IDs 753–757 remain `DEFER` |
| Event 763 → Profiles 753 and 757 | Both source edges remain present and `EXCLUDE` |
| Product 767 → Profile 753 | Source edge remains present and `EXCLUDE` |
| Product 768 → Profiles 753 and 757 | Both source edges remain present and `EXCLUDE` |
| Product People configuration | No existing Product is configured for canonical Product People; no configuration or relationship was created |

The protected rulings are unchanged. The full pre-audit table/object hashes and protected object values are in `/tmp/task-7c-audit-abde5c-20261022/pre-state-fingerprint.txt`. Post-audit comparison and final zero-write conclusion remain pending Task 10; they will be recorded in this document and the audit report using the same fingerprint method.

## Current consumers

- **Template 772 — Medewerker Single:** remains the active legacy profile presentation. It reads title, image, content, `medewerker_titel`, `medewerker_kort_bio`, and role/group terms. Its `vgms23` reverse Event loop calls helper mode `medewerker_events`; `vgms33` reverse Product loop calls `medewerker_products`.
- **Template 773 — Medewerker Archive:** remains active for `medewerker`; card instance `vgma05` uses component `6b463f`.
- **Component `6b463f` — Medewerker Card:** has three active instances: Template 773 `vgma05` and Template 1127 `e9a70e`/`d05bf6`. It is not used in Template 124.
- **Template 1127 — Voelgoed Live Single:** still queries `broadcast_hosts` and `broadcast_guests`; both fields were empty on the five published episodes inspected.
- **Template 124 — Single Product:** its stored Product People loop now uses the canonical `ProductPeopleBricks` path and Person card `dc8611`; the separate source Event loop uses `ProductSourceEventBricks`. It has no display conditions. The tested public Product URL returned a 200 coming-soon response without the main content, Product People, or source Event markers, so public rendering of the saved loops remains unverified.
- **Template 774 — Event Single:** uses canonical Event Participation for its participant section and hides that section when no canonical results exist. It does not use the legacy Event → medewerker relationship for participants.
- **Helper `10-vg-bricks-medewerker-queries.php`:** its six modes were inspected. `medewerker_events` and `medewerker_products` are active in Template 772. `event_medewerkers`, `event_products`, `product_medewerkers`, and `product_source_event` have no active Bricks callers in the fresh caller scan.
- **Writer `9-acf-product-copy.php`:** remains active on Product `acf/save_post` at priority 20. It copies Event `event_medewerkers` to an empty Product `produk_medewerkers` field and preserves non-empty selections. The resulting Product → medewerker relationship remains in use by Template 772’s reverse Product loop.

The detailed dependency classifications, caller evidence, and dependency order are in [the Task 7C audit](PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md). Fresh caller evidence is stored in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt`.

## Source/runtime parity and route state

- Event Participation repository and Bricks adapter, Product People resolver and Bricks adapter, and Product source Event adapter matched their repository source hashes at inspection.
- The two FluentSnippets files are runtime-only in the inspected state; no source-controlled copy was found. Their deployed hashes are recorded in the external runtime evidence.
- The ACF definition list reported `json` for `post_type_person` and `group_vg_person_details`, while detail reads reported `db`. This metadata conflict is unresolved; no definition was edited.
- `/medewerkers/` returned 200 without a canonical link; the representative legacy profile single returned 200 with a self-canonical. Both responses had the `max-image-preview:large` robots meta and no `X-Robots-Tag` header. The archive response had no profile-path links; the representative single linked to `/medewerkers/` and not `/mense/`.
- `/mense/` returned 200 as an empty archive response without an H1 or canonical link. `/mense/{profile-753}/` returned 404. No `/mense/` navigation link was found in the tested header/navigation markup.
- The tested Product route returned 200 but rendered WooCommerce coming-soon content, leaving the saved Template 124 Product People and source Event markers unverified.

Route and indexing changes remain outside Task 7C. No canonical Person destination or exact legacy route mapping is verified.

## Read-only boundary and unresolved access limits

No runtime write was issued by the audit. This statement is provisional until Task 10 compares the pre- and post-audit fingerprints. The pre-audit state and final closeout artifacts are external and non-executable under `/tmp/task-7c-audit-abde5c-20261022/`; final evidence references will be completed at Task 10.

During resumed execution, the `novamira_localhost` adapter was called before its identity had been verified. Its read-only ACF response did not match the Voelgoed baseline; those results were excluded and the adapter was not used again. The verified VGNova `localhost` adapter and direct GETs to port 10039 were used for subsequent inspection. The incident is recorded in `/tmp/task-7c-audit-abde5c-20261022/access-limitations.txt`.

Novamira exposed no FluentSnippets management ability. No snippet editor or other save-capable editor screen was opened. Therefore the caller scan and runtime source reads do not authorize snippet cleanup. The public Product response limitation and ACF source metadata conflict also remain unresolved.

## Next unit selected by the audit

The audit recommends one next unit: a separately reviewed architecture/spec decision for canonical reverse Product relationships. It will define the supported Person-to-Product read and editor behavior, relationship authority and provenance expectations, and separation from Product source Event display. It selects no storage or synchronization mechanism and authorizes no implementation. The existing Product consumer, field, writer, and protected edge rulings remain unchanged pending separate approvals.

## Protected-state closeout

**Task 10 status:** Pending. Reverify runtime identity, recompute the same global and protected-object fingerprints, compare the pre/post state, verify the fixed profile and edge rulings, inspect for audit-created executable PHP/trace files or a new `.crashed` marker, and confirm repository output scope. Record the actual results here before claiming zero writes.
