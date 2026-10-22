# Person consumer decommission readiness audit

Status: in progress. This is a read-only runtime audit. No migration, consumer cutover, or cleanup is authorized by this audit.

## Execution baseline and ruling

| Item | Value |
|---|---|
| Repository | `JCSchoeman96/vgnova` |
| Authorized execution baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64` |
| Verification | `git fetch origin refs/heads/main:refs/remotes/origin/main`, then `git rev-parse origin/main` returned the authorized SHA |
| Plan-authoring baseline | `c61b02954950e4e97aaa47255af41514b5989828` |
| Approved plan head | `b74793691af8f3f3d3f3b2beeb79bb830d3df082` |
| PR #32 merge parents | `c61b02954950e4e97aaa47255af41514b5989828` and `b74793691af8f3f3d3f3b2beeb79bb830d3df082` |
| Audit branch | `docs/task-7c-person-consumer-readiness-audit` |
| Worktree baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64` |

Ruling: The plan's c61b029... SHA is the planning baseline before PR #32 merged. The authorized Task 7C execution baseline is abde5c4689ed2b15f3b97bc6b1b3e04d65618f64. Substitute abde5c... wherever the plan uses c61b029... as the execution worktree base, origin/main gate, or final Git diff base. No architectural scope changes.

## Scope and fixed provenance rulings

The audit records current consumers and dependencies for the `medewerker` implementation, compares repository source with the authorized 10039 runtime, and recommends one next unit. It does not create canonical Person data, migrate relationships, or change runtime configuration.

| Protected record | Ruling |
|---|---|
| Profile 753 | `DEFER` |
| Profile 754 | `DEFER` |
| Profile 755 | `DEFER` |
| Profile 756 | `DEFER` |
| Profile 757 | `DEFER` |
| Event 763 → profile 753 | `EXCLUDE` |
| Event 763 → profile 757 | `EXCLUDE` |
| Product 767 → profile 753 | `EXCLUDE` |
| Product 768 → profile 753 | `EXCLUDE` |
| Product 768 → profile 757 | `EXCLUDE` |

These rulings remain fixed during Task 7C. New evidence may be reported, but a ruling change needs separate review. Architecture dependency does not grant migration authority.

## Evidence and zero-write method

External sanitized evidence is stored outside the repository at `/tmp/task-7c-audit-abde5c-20261022/`. The directory contains text or data files only. `baseline.txt` records the execution ruling. `source-hashes.txt` records the repository source baseline, tracked paths, and pre-runtime SHA-256 values. Runtime identity, pre-audit fingerprints, runtime evidence, post-audit fingerprints, and access limits will be recorded in separate files there. No credentials or unnecessary personal field values will be stored.

Runtime inspection will use read-only Novamira operations where available. Any database evidence will use SELECT-only reads and value hashes for options. Runtime files will be read or hashed without writes. No editor screen or control that can save or auto-sync will be used. The same method will produce pre- and post-audit fingerprints. Missing or inaccessible evidence will be reported as an access limitation, not as proof of zero callers.

The only runtime in scope is `http://localhost:10039/`, expected at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`. No substantive runtime query may run until `ABSPATH`, `home_url`, actual request/runtime port, and database name plus server identity positively identify that installation.

## Repository source evidence recorded before runtime access

The repository source baseline is `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64`. Source paths and SHA-256 values for Event Participation and Product People implementations and tests are recorded in `/tmp/task-7c-audit-abde5c-20261022/source-hashes.txt`. Runtime deployed hashes will be collected separately after the identity gate passes. Source hashes do not establish runtime parity.

## Runtime identity and pre-audit fingerprint

Identity-only preflight passed at 2026-10-22 10:55 UTC. The selected Novamira profile is `localhost`, whose origin is `http://localhost:10039/`.

| Required value | Evidence |
|---|---|
| `ABSPATH` | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`. `wp-config.php` defines it as `__DIR__ . '/'`; `realpath` resolves to the expected WordPress root. |
| `home_url` | `http://localhost:10039`. Novamira `core/get-site-info` returned this URL and `wpurl`; a read-only WP-CLI option read using the site-specific PHP config returned the same `home` value. Site title: `Voelgoed`. |
| Actual HTTP port | A GET to `http://localhost:10039/` returned HTTP 200 with `remote_port=10039`. No redirect was followed. |
| Database name | `local`, confirmed by a SELECT-only query against the connected database. The expected root's `wp-config.php` sets `DB_NAME=local` and `DB_HOST=localhost`. |
| Database server identity | A SELECT-only query returned hostname `jc-kubuntu`, port `10038`, server UUID `028fe7c9-3720-11f1-8cb0-a8a159280deb`, and MySQL `8.0.35`. The site-specific Local PHP and MySQL configuration use the same socket `/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock`; runtime Novamira reports MySQL `8.0.35`. |
| Repository baseline | `abde5c4689ed2b15f3b97bc6b1b3e04d65618f64`, recorded separately from runtime identity. |

The identity evidence and capture time are in `/tmp/task-7c-audit-abde5c-20261022/preflight-identity.txt`. No credentials were recorded. The system PATH has no MySQL client, so the `wp db query` wrapper could not run. The Local installation's MySQL 8.0.35 client connected through the expected site's socket and ran the read-only identity query. It is available for the pre- and post-audit SELECT-only fingerprints.

The protected pre-audit fingerprint was captured at 10:56 UTC, before caller, route, or content inspection. One SELECT statement produced canonical row hashes for the complete `wp_posts`, `wp_postmeta`, `wp_term_relationships`, `wp_terms`, `wp_term_taxonomy`, and `wp_options` tables. All six tables use InnoDB, so the statement read one consistent snapshot. SQL hashed post content, metadata values, term text, and option values before returning results. No raw option values, credentials, or unnecessary profile fields were stored.

| Table | Rows | Pre-audit SHA-256 |
|---|---:|---|
| `wp_posts` | 761 | `a7ff0eb5c9def20c100dfc7680e17ca4338203fdb7b8fead6fc3ff0dfd93bd47` |
| `wp_postmeta` | 3,928 | `c582dd125fc4b6d9aacdf720b65bfbef7e5ff3614cb504c62465b8823e1588bb` |
| `wp_term_relationships` | 160 | `676a5e974719590f8d2f70e8b484c3835507364661748f14f6dc1d24fb964736` |
| `wp_terms` | 396 | `c3960c1bce6f0565021aeda4760ba7378169bf4cca1bd4c0bbac3745a9d26a18` |
| `wp_term_taxonomy` | 396 | `0d3926719996eea230b3f253791965bd40a1078220b2de85e385370b16bccb61` |
| `wp_options` | 1,198 | `27cc25362cae7d279e45f07ade79965176f9a3885b17d5835827867f24aeb6e1` |

The protected object fingerprint covers Profiles 753–757, Event 763, Products 765, 767, and 768, Templates 772, 773, 774, 124, and 1127, including their post, postmeta, and term-relationship state. Per-object hashes and counts are in `/tmp/task-7c-audit-abde5c-20261022/pre-state-fingerprint.txt`. Fresh relationship values at baseline are Event 763 `event_medewerkers=[753,757]`, Product 767 `produk_medewerkers=[753]`, and Product 768 `produk_medewerkers=[753,757]`. Products 765, 767, and 768 each have `produk_bron_event=763`. The audit did not read `_event_name`.

The database had zero `person` posts and zero `event_participation` posts across all statuses. It had five published `medewerker` profiles and 17 published `tc_events`. The `bricks_components` option has a baseline hash of `a3fe81604eb4215980bf93f842c3c4d5c6ae9a739604b015351b33a861497105`; all `bricks%`, relevant `acf%`, and non-secret FluentSnippets option hashes are recorded in the fingerprint file. The global post and postmeta fingerprints include ACF definitions, Bricks templates, and FluentSnippets records.

Pre-audit runtime file hashes are in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt`. The deployed Event Participation repository and Bricks adapter, Product People resolver and Bricks adapter, and Product source Event adapter match their source-controlled SHA-256 values. The deployed `9-acf-product-copy.php` hash is `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70`; `10-vg-bricks-medewerker-queries.php` is `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`.

No `.crashed` marker, `task-7c` file, or trace-named file existed under the runtime root at baseline. The external evidence directory contained only non-executable text files. WP-CLI's Local PHP configuration emitted an Imagick shared-library warning, but read-only option reads succeeded. The system PATH lacked `mysql`; the Local MySQL 8.0.35 client and expected socket were used for all SQL fingerprints.

The full pre-audit counts, digests, per-object hashes, field relationship IDs, runtime file hashes, and artifact inventory are in `/tmp/task-7c-audit-abde5c-20261022/`. No runtime write has been issued. The post-audit comparison remains pending Task 10.

## Fresh consumer findings

Historical inventories and implementation reports are leads only. The following Task 3 findings come from fresh read-only Novamira Bricks content, template, component, and condition reads, plus SELECT-only searches of Bricks postmeta. Capture time: 2026-10-22 11:09 UTC. Runtime identity is the verified 10039 identity above; the sanitized caller evidence is in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt`.

### Task 3: Bricks consumers and active callers

The current Bricks inventory contains 27 templates and 58 component definitions. A current caller search looked for `medewerker` in Bricks metadata and the relevant component IDs (`6b463f`, `dc8611`, `194bba`, `4d1dd4`), then checked the owning element trees, publication status, and template conditions. Revision records with `inherit` status were excluded from active callers. No builder screen or save-capable control was opened.

| Surface | Current status and condition | Element/query evidence | Finding |
|---|---|---|---|
| Template 772, Medewerker Single | Published; `postType=medewerker`; preview post 753 | Profile nodes: `vgms04` featured image and post title alt; `vgms06` post title; `vgms07` `{acf_medewerker_titel}`; `vgms08` `{acf_medewerker_kort_bio}`; `vgms09` `{post_terms_medewerker_rol}`; `vgms10` `{post_terms_medewerker_groep}`; `vgms11` post content. Event loop `vgms23`, `vg_query_type=medewerker_events`, 12 per page, Event Card `f60f14` at `vgms24`. Product loop `vgms33`, `vg_query_type=medewerker_products`, 12 per page, Product Card `04fb31` at `vgms34`. | The profile shell is dynamic and has no hard-coded profile IDs. Preview post 753 is template preview metadata, not a relationship or a copy operation. A replacement can be designed without copying profiles 753–757; their protected `DEFER` rulings still bar migration. Event and Product relationship directions await Task 4 source/runtime tracing. |
| Template 773, Medewerker Archive | Published; archive condition for post type `medewerker` | Native archive loop `vgma04`: post type `medewerker`, 24 per page, title ascending. Component `6b463f` at instance `vgma05`. | Active archive consumer of `medewerker`; cards expose the title, `medewerker_titel`, `medewerker_kort_bio`, and current post URL. |
| Template 1127, Voelgoed Live Single | Published; `postType=uitsending` | Host loop uses `objectType=acf_broadcast_hosts`, with `6b463f` instance `e9a70e`; guest loop uses `objectType=acf_broadcast_guests`, with `6b463f` instance `d05bf6`. | Two active instances of the legacy Medewerker Card occur outside Templates 772/773. The ACF definitions and relationship targets are checked in Task 6. |
| Template 774, Event Single | Published; `postType=tc_events` | Loop `be9e2f` queries `event_participation`; component `194bba` is used at `ae53cc`. The component queries one `person` using `post__in={acf_event_participation_person}` and displays featured image, post title, and `{acf_person_professional_title}`. | Active reverse Event presentation is wired through Event Participation to canonical `person`, not through Template 772's `medewerker_events` helper. Current record counts and deployed integration readiness are assessed in Task 5. |
| Template 124, Single Product | Published; type `wc_product`; returned condition list is empty | Stored Product People loop `vgpr03` has class `vg-product-people-query`; component instance `vgpr04` is `dc8611` Product Person Card, not `6b463f`. Separate source Event loop is `vgpr13` with Event Card `f60f14`. | Current stored layout uses the post-Task-7B Product Person Card. Since the condition list is empty, published status alone does not establish that the template renders on product routes; Task 6 will check public route evidence. |
| Template 77, Header | Published; returned condition list is empty | Element `tfqwpv` is labelled “Medewerkers” and links to `#`. | Inactive registration: Bricks' condition read returns no conditions, so this template is not counted as an active route caller. The placeholder link does not establish a `/medewerkers/` navigation consumer. |
| Pages 465 and 612, component library pages | Both published pages | Instances `i_mh` and `it_mentor` call component `4d1dd4` Medewerker Hero. That component uses only the current context's `{featured_image}` and `{post_title}`. | These are active layout-demo callers, but they do not read `medewerker` fields or establish Person relationships. The component definition has no other caller found. |

Component `6b463f` has seven elements: `vgmcc2` uses `{featured_image}`, `vgmcc3` uses `{post_title}`, `vgmcc4` uses `{acf_medewerker_titel}`, `vgmcc5` uses `{acf_medewerker_kort_bio}`, and `vgmcc6` links to `{post_url}`. Its three active instances are `vgma05` in Template 773 and `e9a70e`/`d05bf6` in Template 1127. No nested component definition calls `6b463f`. The fresh component scan found 58 definitions; the relevant registrations are `4d1dd4`, `6b463f`, `194bba`, and `dc8611`. The Bricks metadata search found no additional active `medewerker` field/tag or component-ID callers beyond those listed. Full sanitized evidence and identity are recorded externally at `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt`.

The `medewerker` route remains a direct dependency of the single and archive template conditions. The actual route response, canonical and indexing signals, current ACF field/taxonomy definitions, and any route consumers are reserved for Task 6. No route or record was changed.

### Task 4: Relationship helpers and write paths

Fresh runtime capture: 2026-10-22 11:16 UTC, from the verified 10039 identity above. Novamira ACF setup, field-group, schema, and raw-value abilities were inspected read-only. Runtime helper and writer code was read from the authorized install. Repository source searches did not find source-controlled PHP definitions for either FluentSnippets file, so their source parity is unavailable. The runtime storage index and snippet headers identify both as published, run globally with conditions disabled. Deployed SHA-256: `10-vg-bricks-medewerker-queries.php` `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`; `9-acf-product-copy.php` `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70`. Runtime hashes and field-group/value evidence are in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt`.

#### ACF definitions and current IDs

| Field | Current definition | Data direction and editor/write path |
|---|---|---|
| `event_medewerkers` | Active DB field group `group_event_medewerkers` (ID 748); multiple ACF relationship to `medewerker`, ID return format, on `tc_events` | Event → legacy profile. Editors set the Event relationship. Snippet 9 reads it during Product saves; helper 10 also reads it in the `event_medewerkers` and `medewerker_events` branches. |
| `produk_bron_event` | Active DB field group `group_medewerker_produkte` (ID 750); single ACF post object to `tc_events`, ID return format, on `product` | Product → source Event. Editors set the source Event. Snippet 9 and helper 10 read it. Platform Core also reads it for canonical Event-derived Product People and separate source Event presentation. |
| `produk_medewerkers` | Active DB field group `group_medewerker_produkte` (ID 750); multiple ACF relationship to `medewerker`, ID return format, on `product` | Product → legacy profiles. Editors may select it. Snippet 9 fills it when empty. Template 772's `medewerker_products` query reads it in reverse. The canonical Product People resolver ignores it. |

The current ACF raw ID reads were: Event 763 `event_medewerkers=[753,757]`; Product 765 `produk_bron_event=763`, `produk_medewerkers` empty; Product 767 `produk_bron_event=763`, `produk_medewerkers=[753]`; Product 768 `produk_bron_event=763`, `produk_medewerkers=[753,757]`. These match the protected state recorded before substantive inspection. The `EXCLUDE` edge rulings remain fixed. No ACF value or relationship was written.

The separate active PHP ACF group `group_vg_product_people` belongs to `ProductPeopleFields`; it owns `product_people_mode` and `product_people_override` targeting `person`. It does not define the three legacy fields above. Its runtime source is Platform Core, with source/deployed class hashes matching. Product People mode and override are a separate data path. The WooCommerce `_event_name` query in Template 774 is also separate from these ACF fields.

#### FluentSnippets and all six helper modes

`10-vg-bricks-medewerker-queries.php` is runtime-only and published. The snippet registers `bricks/posts/query_vars` at priority 10 with four arguments. It resolves the current post context and has exactly these six branches:

| Mode | Purpose; read direction and data | Current active caller |
|---|---|---|
| `event_medewerkers` | List Event profiles; Event context → `medewerker` IDs from ACF `event_medewerkers`, ordered by the relationship list | None found |
| `event_products` | List Products sourced from an Event; Event context → Products whose `produk_bron_event` equals the current Event ID | None found |
| `medewerker_events` | Show Events for a profile; `medewerker` context → published `tc_events` whose `event_medewerkers` metadata contains the profile ID | Template 772, loop `vgms23` |
| `medewerker_products` | Show Products for a profile; `medewerker` context → published Products whose `produk_medewerkers` metadata contains the profile ID | Template 772, loop `vgms33` |
| `product_medewerkers` | Show legacy profiles on a Product; Product context → `medewerker` IDs from ACF `produk_medewerkers`, ordered by the selected IDs | None found |
| `product_source_event` | Show the Product source Event; Product context → Event ID from ACF `produk_bron_event` | None found |

The fresh exact-string scan of published Bricks metadata and component definitions found only the two Template 772 helper modes listed above. The other four registered branches have no current Bricks helper-mode caller. All six branches only change Bricks query vars and perform reads. The reverse-query helper checks published target posts and matches relationship metadata with a substring `LIKE` search for the current numeric ID. No additional branch was present. The separate `ProductPeopleBricks` and `ProductSourceEventBricks` integrations use Template 124's semantic CSS markers and Platform Core; they are not calls to modes in snippet 10.

`9-acf-product-copy.php` is runtime-only and published, with global run condition and snippet-manager priority 10. Its `acf/save_post` callback runs at WordPress priority 20. It skips nonnumeric IDs and non-Product posts. For Products with an empty `produk_medewerkers`, it reads `produk_bron_event`, reads that Event's `event_medewerkers`, normalizes the returned IDs, and writes them to `produk_medewerkers` with `update_field`. A nonempty Product selection is preserved. The snippet stores no provenance marker, so an existing value does not disclose whether it came from an editor or an earlier copy. The current stored IDs alone do not establish which actor wrote them.

The active Product People resolver reads `product_people_mode`, then either follows `produk_bron_event` through Event Participation or reads `product_people_override`; it filters to published `person` records. It never reads `produk_medewerkers`, `event_medewerkers`, or `medewerker` and does not write. `ProductSourceEventBricks` separately reads and validates `produk_bron_event` for its marked query. Both Platform Core adapters are registered in the deployed plugin and represented by the source-controlled `integration.patch`. Template 124 stores their semantic query markers; its condition list is empty, so front-end execution remains unverified pending Task 6. Source/deployed hashes match for the Event Participation repository/service/Bricks adapter and Product People fields/resolver/Bricks/Source Event classes.

The complete legacy copy chain is `produk_bron_event` → Event `event_medewerkers` → snippet 9 on Product save → Product `produk_medewerkers` → helper mode `medewerker_products` → Template 772 `vgms33`. Template 772 is the only current active reader of the copied Product field found in Bricks. The next safe break point remains unresolved: Task 5 must settle the reverse Product presentation requirement, and Task 6 must verify editor use and other consumers before any proposal to change the writer or field. The source Event field has separate Product People and source Event readers and remains outside that proposed break.

### Task 5: Canonical reverse reads and Product requirement

Fresh Product-value capture: 2026-10-22 11:21 UTC, from the verified 10039 identity above. Current Person and Event Participation counts remain zero from the pre-audit snapshot. Source and deployed runtime copies of the Event Participation repository, Bricks adapter, and service match by SHA-256; paths and hashes, plus the raw-ID-only Product reads, are recorded in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt` and `source-hashes.txt`. Tests were inspected as implementation evidence and were not run.

#### `Person → Event Participation → Event`

`EventParticipationRepository::events_for_person($person_id)` reads through published `event_participation` records linked by `event_participation_person`, rechecks both endpoint types, rejects trashed endpoints, and returns unique `tc_events` IDs ordered by `event_participation_display_order` ascending and then participation ID ascending. Repository tests cover ordered results, published participation status, and malformed or wrong-type endpoints.

Endpoint validation accepts any existing status except `trash`; the repository does not require the Person or Event endpoint to be `publish`. A future public Bricks query must therefore require the current Person to be publicly available and constrain returned Events to `publish`. The existing `EventParticipationBricks` adapter handles the Event context only: it uses `participations_for_event()` for Template 774's `vg-event-participation-query`, returns published participation IDs in repository order, and fails closed when no Event context exists. No existing Bricks component or adapter calls `events_for_person()`.

The repository contract is available in source and deployed runtime, so a later Person-to-Event consumer can be designed without copying profiles 753–757 or creating runtime records. A read-only Bricks adapter/query bridge is still needed to bind Person context to the reverse repository method. With zero Person and zero Event Participation records, this audit found no live canonical Person event result to render. The bridge and data migration remain separate authorization units. Event 763's two legacy edges remain `EXCLUDE` and unchanged.

#### Reverse Product requirement

**Current requirement outcome 1 selected: a genuine supported reverse Product requirement that needs separate canonical architecture approval.** Published Template 772 actively queries `produk_medewerkers` in reverse at `vgms33`; the active Product save snippet can populate the field from the linked Event; the legacy ACF fields remain active; and the documented editor flow instructs editors to set Product people. This is current configured behavior. Keeping it through Person replacement needs a separately approved canonical reverse-query contract.

The canonical Product People implementation only resolves Product → Person from `product_people_mode`, `produk_bron_event`/Event Participation, or `product_people_override`. It has no reverse Product API and deliberately ignores `produk_medewerkers`; the current code does not establish that reverse Products can be derived safely from canonical configuration. The active Template 772 consumer and editor path mean the audit cannot classify this presentation as obsolete. Product Single 124 stores the canonical forward Person card and separate source Event query, but has no template conditions, so its public rendering remains unverified pending Task 6.

Read-only ACF checks of all nine published Products found no canonical Product People mode or override values. Eight also have no nonempty legacy Product People values. The only populated published legacy edge is Product 767 → profile 753, which remains `EXCLUDE`. The active flow establishes the requirement boundary; it does not authorize migrating that edge or any other edge. No reverse index, storage, scan contract, or synchronization design is proposed here.

## Classifications and dependency order

Pending Task 7. Each atomic concern will receive exactly one approved lifecycle classification. These classifications authorize no deletion, migration, retirement, or runtime mutation.

## Recommendation

Pending Task 9. The completed audit will select exactly one next implementation unit, or one evidence/access blocker where the approved plan allows it. The recommendation will not be executed.

## Zero-write closeout and validation

Pending Task 10. The closeout will repeat runtime identity and protected-state fingerprints, verify the fixed rulings and artifact inventory, validate the approved documentation outputs and CSV, and run `git diff --check`.
