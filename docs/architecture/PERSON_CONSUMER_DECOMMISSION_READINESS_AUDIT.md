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

The active Product People resolver reads `product_people_mode`, then either follows `produk_bron_event` through Event Participation or reads `product_people_override`; it filters to published `person` records. It never reads `produk_medewerkers`, `event_medewerkers`, or `medewerker` and does not write. `ProductSourceEventBricks` separately reads and validates `produk_bron_event` for its marked query. Both Platform Core adapters are registered in the deployed plugin and represented by the source-controlled `integration.patch`. Template 124 stores their semantic query markers, but its condition list is empty. The Task 6 public Product route check returned the WooCommerce coming-soon response without either marker, so it did not verify Template 124 execution. Source/deployed hashes match for the Event Participation repository/service/Bricks adapter and Product People fields/resolver/Bricks/Source Event classes.

The complete legacy copy chain is `produk_bron_event` → Event `event_medewerkers` → snippet 9 on Product save → Product `produk_medewerkers` → helper mode `medewerker_products` → Template 772 `vgms33`. Template 772 is the only current active reader of the copied Product field found in Bricks. Task 5 confirmed a supported reverse Product flow that needs separate canonical architecture approval. No part of the copy chain can be changed until that review and a later consumer cutover pass. The source Event field has separate Product People and source Event readers and remains outside that proposed break.

### Task 5: Canonical reverse reads and Product requirement

Fresh Product-value capture: 2026-10-22 11:21 UTC, from the verified 10039 identity above. Current Person and Event Participation counts remain zero from the pre-audit snapshot. Source and deployed runtime copies of the Event Participation repository, Bricks adapter, and service match by SHA-256; paths and hashes, plus the raw-ID-only Product reads, are recorded in `/tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt` and `source-hashes.txt`. Tests were inspected as implementation evidence and were not run.

#### `Person → Event Participation → Event`

`EventParticipationRepository::events_for_person($person_id)` reads through published `event_participation` records linked by `event_participation_person`, rechecks both endpoint types, rejects trashed endpoints, and returns unique `tc_events` IDs ordered by `event_participation_display_order` ascending and then participation ID ascending. Repository tests cover ordered results, published participation status, and malformed or wrong-type endpoints.

Endpoint validation accepts any existing status except `trash`; the repository does not require the Person or Event endpoint to be `publish`. A future public Bricks query must therefore require the current Person to be publicly available and constrain returned Events to `publish`. The existing `EventParticipationBricks` adapter handles the Event context only: it uses `participations_for_event()` for Template 774's `vg-event-participation-query`, returns published participation IDs in repository order, and fails closed when no Event context exists. No existing Bricks component or adapter calls `events_for_person()`.

The repository contract is available in source and deployed runtime, so a later Person-to-Event consumer can be designed without copying profiles 753–757 or creating runtime records. A read-only Bricks adapter/query bridge is still needed to bind Person context to the reverse repository method. With zero Person and zero Event Participation records, this audit found no live canonical Person event result to render. The bridge and data migration remain separate authorization units. Event 763's two legacy edges remain `EXCLUDE` and unchanged.

#### Reverse Product requirement

**Current requirement outcome 1 selected: a genuine supported reverse Product requirement that needs separate canonical architecture approval.** Published Template 772 actively queries `produk_medewerkers` in reverse at `vgms33`; the active Product save snippet can populate the field from the linked Event; the legacy ACF fields remain active; and the documented editor flow instructs editors to set Product people. This is current configured behavior. Keeping it through Person replacement needs a separately approved canonical reverse-query contract.

The canonical Product People implementation only resolves Product → Person from `product_people_mode`, `produk_bron_event`/Event Participation, or `product_people_override`. It has no reverse Product API and deliberately ignores `produk_medewerkers`; the current code does not establish that reverse Products can be derived safely from canonical configuration. The active Template 772 consumer and editor path mean the audit cannot classify this presentation as obsolete. Product Single 124 stores the canonical forward Person card and separate source Event query, but has no template conditions. The Task 6 Product route returned WooCommerce's coming-soon response without either query marker, so Template 124's public execution remains unverified.

Read-only ACF checks of all nine published Products found no canonical Product People mode or override values. Eight also have no nonempty legacy Product People values. The only populated published legacy edge is Product 767 → profile 753, which remains `EXCLUDE`. The active flow establishes the requirement boundary; it does not authorize migrating that edge or any other edge. No reverse index, storage, scan contract, or synchronization design is proposed here.

### Task 6: Routes, dynamic tags, taxonomies, and fields

The fresh read-only runtime capture was taken at 11:47 UTC on 2026-10-22 from the verified VGNova connection. The route requests used GET against localhost:10039. Each reported remote port 10039. Sanitized captures are in /tmp/task-7c-audit-abde5c-20261022/runtime-evidence.txt.

#### ACF definitions and values

group_medewerker_besonderhede is active and database-backed on medewerker. It defines medewerker_titel, medewerker_kort_bio, and medewerker_profiel_status. All five protected profiles have status public and a non-empty short bio. Only profile 753 has a non-empty title. No field text was copied into the evidence.

The active medewerker_rol and medewerker_groep taxonomies are database-backed, public, and queryable. Their rewrite slugs are medewerker-rol and medewerker-groep. The current registrations contain nine role terms and five group terms. Profiles 753–757 all have role mentor and group voelgoed-mentor. Template 772 displays these terms at vgms09 and vgms10.

group_vg_broadcast_episode is active on uitsending. Its broadcast_hosts and broadcast_guests fields are multiple relationships to medewerker and return IDs. Both fields are empty on all five published episodes. Template 1127 still has active loops for both fields, so the empty values do not establish that the consumer is obsolete. The host loop is sghlop with component instance e9a70e; the guest loop is sgglop with instance d05bf6.

The canonical Person group group_vg_person_details defines person_professional_title, person_credentials, person_company, person_website, and person_type. It has no short-bio field. A later Person profile design must decide whether the current short bio maps to post excerpt/content or needs a separately approved field.

The active event_medewerkers and produk_medewerkers fields have no direct Bricks dynamic-token use. Their current reads and writes occur through the Task 4 helper and copy chain. The broadcast_related_content field targets posts, courses, products, and events, not medewerker.

ACF list results report source json for post_type_person and group_vg_person_details; the corresponding get results report source db. The source metadata conflict remains unresolved. No definitions or values were edited.

#### Dynamic-data registry and actual uses

The Bricks ACF registry includes acf_medewerker_titel, acf_medewerker_kort_bio, acf_medewerker_profiel_status, acf_event_medewerkers, acf_produk_medewerkers, acf_broadcast_hosts, and acf_broadcast_guests. The WordPress registry includes post_terms_medewerker_rol and post_terms_medewerker_groep.

| Tag or field | Current active use | Finding |
|---|---|---|
| acf_medewerker_titel | Template 772 vgms07; component 6b463f in Templates 773 and 1127 | Reads the current medewerker loop item. |
| acf_medewerker_kort_bio | Template 772 vgms08; component 6b463f in Templates 773 and 1127 | Reads the current medewerker loop item. |
| post_terms_medewerker_rol, post_terms_medewerker_groep | Template 772 vgms09 and vgms10 | Reads profile role and group terms. |
| acf_medewerker_profiel_status | No active Bricks use found | Registered field and token; observed routes did not expose a status-based condition. |
| acf_event_medewerkers, acf_produk_medewerkers | No direct Bricks token use found | Active field reads and writes are documented under Task 4. |
| acf_broadcast_hosts, acf_broadcast_guests | Template 1127 loops sghlop and sgglop | Active relationship queries to medewerker; all five current published episode values are empty. |

Template 772's profile elements use the two profile ACF fields and the role/group terms. Its Event and Product loops use helper modes, not direct field tokens. Template 773 uses vgma04 and component instance vgma05. Template 1127 also uses 6b463f for hosts and guests. The registry entries alone do not prove a consumer; this table separates registrations from saved uses.

#### Routes and navigation

| GET route | Result | Canonical and indexing signals | Current evidence |
|---|---|---|---|
| /medewerkers/ | 200, no redirect; body class post-type-archive-medewerker | No canonical link; robots meta contains only max-image-preview:large; no X-Robots-Tag. | Template 773 is configured for this archive. The rendered response had no profile-path anchors, despite the saved card's post_url setting. |
| /medewerkers/{profile-753}/ | 200, no redirect | Self-canonical; robots meta contains only max-image-preview:large; no X-Robots-Tag. | Representative protected profile route. The response had one /medewerkers/ link and no /mense/ link. |
| /mense/ | 200; body class post-type-archive-person; no h1 | No canonical link; robots meta contains only max-image-preview:large; no X-Robots-Tag. | Person archive route exists, but there are zero persistent Person records and no archive heading. |
| /mense/{profile-753}/ | 404; body class error404 | No canonical link; robots meta contains only max-image-preview:large; no X-Robots-Tag. | No verified canonical single destination for the protected profile. |

The tested responses contained no /medewerkers/ or /mense/ links in header or nav markup. Template 77's published header definition has no display condition and remains inactive; its only Medewerkers link is #. No route, canonical, menu, redirect, indexing, or rewrite setting changed.

The Task 6 public check of Product 767's URL returned 200 but rendered WooCommerce's coming-soon response. It had a self-canonical and the same max-image-preview:large robots meta. It contained no Product People or source Event marker, no main content, and no main heading. Template 124's stored canonical markers therefore remain unverified in public rendering; its published Bricks template also has no display conditions.

A future single-route change still needs an approved identity and provenance ruling, a persistent target Person ID, a verified /mense/{slug}/ destination, and an exact unambiguous legacy-route mapping. Archive work needs a verified canonical archive and separate review of canonical, menu, and indexing behavior. The registered /mense/ route alone does not meet those gates.

#### Task 6 access limitations

During resumed execution, the novamira_localhost adapter was called before its identity had been checked. Its read-only ACF response did not match the Voelgoed baseline. I excluded those results and stopped using that adapter. The VGNova adapter then returned Voelgoed, http://localhost:10039, and WordPress 7.1.3; the existing preflight file supplies the expected root and database/server identity. All subsequent runtime calls used that verified connection or public GETs to port 10039. The incident and ACF source metadata conflict are recorded in /tmp/task-7c-audit-abde5c-20261022/access-limitations.txt.

## Classifications and dependency order

Each row receives one approved current-state classification. These are readiness decisions. None authorizes migration, retirement, deletion, or runtime mutation.

| Dependency or concern | Current caller(s) | Source and runtime evidence | Read or write role | Classification | Reason | Prerequisite for changing classification | Access limitation | Provenance and data authority |
|---|---|---|---|---|---|---|---|---|
| Template 772 profile presentation | Template 772 profile nodes vgms04–vgms11 | Task 3 Bricks tree; Task 6 Person and Medewerker ACF schemas | Reads title, image, post content, title/bio fields, and role/group terms | READY_FOR_CONSUMER_REPLACEMENT | A canonical Person profile schema exists and a replacement can be designed without copying profiles 753–757. The current Person group has no short-bio field. | Decide how a Person summary uses post excerpt/content or an approved field; verify the Person single route before cutover. | Person group source metadata conflicts between ACF list and get responses. | Design readiness does not approve any profile copy. Profiles 753–757 remain DEFER. |
| Template 772 reverse Events | Template 772 loop vgms23, helper mode medewerker_events | Tasks 3–5; source and deployed EventParticipationRepository hashes match | Reads Events through event_medewerkers; canonical events_for_person reads Event Participation | READY_FOR_CONSUMER_REPLACEMENT | The canonical repository contract exists. A Person-context Bricks adapter is missing. | Specify the adapter's published Person and Event checks, then separately approve the consumer cutover. | No canonical Person or Event Participation records exist to verify a live result. | Event 763 → 753 and 757 remain EXCLUDE. |
| Template 772 reverse Products | Template 772 loop vgms33, helper mode medewerker_products; writer 9-acf-product-copy.php | Tasks 3–5; runtime Template 772, ACF values, and snippet 9 | Reads reverse Product links; snippet 9 writes produk_medewerkers on Product save | BLOCKED_BY_RELATIONSHIP_MODEL | A current supported reverse Product flow exists, but canonical Product People has no reverse Product API or safe derivation contract. | Approve a separate canonical reverse Product architecture and consumer requirement before changing any field, helper, or writer. | Product Single 124 did not render in the public route check; its stored query markers remain unverified in a coming-soon response. | The known Product edges remain EXCLUDE. The active requirement does not authorize migrating them. |
| Template 772 classification and taxonomy presentation | Template 772 elements vgms09 and vgms10 | Tasks 3 and 6; active role/group taxonomy definitions and five profile assignments | Reads global role and group terms | BLOCKED_BY_RELATIONSHIP_MODEL | Role terms mix persistent profile classifications with contextual Event, episode, and editorial roles. Group semantics also need review. | Approve the split between persistent person_type values and relationship-specific roles or programme membership. | No access limitation for current term reads. | All five role assignments and all five group assignments remain DEFER. Do not copy terms mechanically. |
| Template 772 route and canonical dependency | Template 772 condition for medewerker; /medewerkers/{slug}/ | Tasks 3 and 6 route GETs; ACF routes; zero Person records | Reads current medewerker context and post URL | BLOCKED_BY_PERSON_DATA | The equivalent /mense/{profile-753}/ route returns 404. There is no persistent target Person ID or exact approved route mapping. | Approve profile identity/provenance, create an authorized target Person in a separate task, and verify its exact route. | /mense/ returns an empty archive response without a canonical link or h1. | No protected profile may be copied or redirected in Task 7C. |
| Profiles 753–757 and their values | Templates 772 and 773; component 6b463f; current public routes | Tasks 2, 3, 5, and 6; pre/post fingerprint pending Task 10 | Current legacy profile records and fields are read-only inputs | BLOCKED_BY_PERSON_DATA | No canonical Person record exists, and the five migration rulings are DEFER. | Separate approved identity/provenance decisions and explicit per-record migration authority. | No access limitation for read-only profile values; unnecessary field text was not recorded. | Keep all five DEFER rulings unchanged. |
| medewerker_titel | Template 772 vgms07; component 6b463f in Templates 773 and 1127 | Task 6 ACF schema and Bricks reads | Read-only display field | RETIRE_PENDING_DEPENDENCY | Current legacy templates read it. Canonical Person has person_professional_title, but data migration is not authorized. | Replace all approved consumers and complete any later field mapping before field removal. | No profile text was stored in evidence. | The value on profile 753 remains under its DEFER ruling. |
| medewerker_kort_bio | Template 772 vgms08; component 6b463f in Templates 773 and 1127 | Task 6 ACF schema and Bricks reads | Read-only display field | RETIRE_PENDING_DEPENDENCY | Current legacy templates read it. The current Person group has no corresponding short-bio field. | Approve a canonical summary mapping and replace all consumers before field removal. | No profile text was stored in evidence. | The five current values are not approved for migration. |
| medewerker_profiel_status | ACF editor field; no active Bricks or code reader found | Task 6 ACF schema, values, and dynamic-tag search | Editor-managed status; no active runtime read found | KEEP_LEGACY | The current profile workflow still exposes this status field, and no canonical status mapping is approved. | Review the field in a separately approved Person editorial workflow. | No access limitation for read-only values. | All five current values are public; this does not change the DEFER rulings. |
| medewerker_rol taxonomy | Template 772 vgms09; current profile editor workflow | Task 6 ACF taxonomy and term reads | Reads role terms; editors assign terms | RETIRE_PENDING_DEPENDENCY | The current profile consumer uses the taxonomy, while its global and contextual meanings are unresolved. | Replace consumers after approved person_type and contextual-role mapping. | No access limitation for read-only taxonomy values. | Five Mentor assignments remain DEFER. |
| medewerker_groep taxonomy | Template 772 vgms10; current profile editor workflow | Task 6 ACF taxonomy and term reads | Reads group terms; editors assign terms | RETIRE_PENDING_DEPENDENCY | The current profile consumer uses the taxonomy; Voelgoed Mentor and episode/event groups need domain review. | Confirm retained programme membership and replace consumers before retiring the taxonomy. | No access limitation for read-only taxonomy values. | Five Voelgoed Mentor assignments remain DEFER. |
| Template 773 Medewerker Archive | Archive loop vgma04 and card instance vgma05 | Task 3 Bricks tree; Task 6 /medewerkers/ GET | Reads published medewerker records and card fields | KEEP_LEGACY | Current profiles and their archive remain operational pending approved migration. | A separate archive cutover must pass profile data, canonical destination, route, menu, and indexing gates. | The public archive GET had no profile-path anchors despite the saved post_url component setting. | No archive route change is approved. |
| Shared Medewerker Card 6b463f | Template 773 vgma05; Template 1127 e9a70e and d05bf6 | Task 3 component scan; Task 6 saved tree and rendered routes | Reads featured image, title, medewerker_titel, medewerker_kort_bio, and post_url | KEEP_LEGACY | Three active instances remain. Template 124 uses dc8611, not 6b463f. | Replace or remove each active caller only after its domain-specific Person migration and render check. | Product Template 124 public rendering is unverified; no 6b463f caller exists there in saved data. | Component use grants no authority over the DEFER profiles or EXCLUDE edges. |
| medewerker CPT and /medewerkers/ routes | Templates 772 and 773; public archive and single | Tasks 2, 3, and 6; ACF definition and GET responses | Stores and serves the current legacy profiles | KEEP_LEGACY | Current profiles remain public and must stay operational while migration authority is unresolved. | Map and verify every record, relation, template, URL, and consumer before any later decommission. | Archive has no canonical link; the single tested is self-canonical. | Protected profile and edge rulings stay fixed. |
| event_medewerkers ACF field | Template 772 helper; snippet 9; Event editor workflow | Tasks 3 and 4; group_event_medewerkers; runtime values | Event → medewerker; read by helper and copy writer | RETIRE_PENDING_DEPENDENCY | The field still feeds reverse Events and the Product copy chain. Template 774 now reads canonical Event Participation separately. | Replace all remaining reads and writes and resolve any approved edge migration before field removal. | Snippet management is not available through a Novamira ability; no editor was opened. | Both Event 763 edges remain EXCLUDE. |
| Helper mode medewerker_events | Template 772 vgms23 | Task 4 helper source and Task 3 current caller scan | Reads event_medewerkers in reverse | RETIRE_PENDING_DEPENDENCY | The active single-profile consumer still uses it. | Cut over the reverse Event consumer and verify other readers before retirement. | Snippet source is runtime-only; no Novamira snippet-management ability. | No edge migration is authorized. |
| Helper mode medewerker_products | Template 772 vgms33 | Task 4 helper source and Task 3 current caller scan | Reads produk_medewerkers in reverse | RETIRE_PENDING_DEPENDENCY | The active reverse Product consumer remains supported and needs separate architecture approval. | Complete the separate Product relationship decision and later consumer cutover. | Snippet source is runtime-only; public Product Single 124 execution was not verified. | Known Product edges remain EXCLUDE. |
| Helper mode event_medewerkers | No active Bricks caller found | Task 4 full branch scan and Task 3 published-template/component scan | Reads event_medewerkers to list profiles on an Event | RETIRE_PENDING_ACCESS | No current caller exists for this helper branch. | Establish safe FluentSnippets edit access, then remove only after a final caller check. | Novamira exposes no FluentSnippets management ability; audit stayed read-only. | Field values and Event rulings remain unchanged. |
| Helper mode event_products | No active Bricks caller found | Task 4 full branch scan and Task 3 published-template/component scan | Reads produk_bron_event to list Products on an Event | RETIRE_PENDING_ACCESS | No current caller exists for this helper branch. | Establish safe FluentSnippets edit access, then remove only after a final caller check. | Novamira exposes no FluentSnippets management ability; audit stayed read-only. | produk_bron_event remains in use by canonical Product People and source Event readers. |
| Helper mode product_medewerkers | No active Bricks caller found | Task 4 full branch scan; Template 124 uses ProductPeopleBricks | Reads produk_medewerkers on Product context | RETIRE_PENDING_ACCESS | Product Template 124 has cut over to canonical Product People; no active helper caller remains. | Establish safe FluentSnippets edit access, then remove only after a final caller check. | Novamira exposes no FluentSnippets management ability; audit stayed read-only. | This does not authorize changing produk_medewerkers or its protected values. |
| Helper mode product_source_event | No active Bricks caller found | Task 4 full branch scan; Template 124 uses ProductSourceEventBricks | Reads produk_bron_event on Product context | RETIRE_PENDING_ACCESS | The separate Platform Core source Event adapter owns the current Product Single use. | Establish safe FluentSnippets edit access, then remove only after a final caller check. | Novamira exposes no FluentSnippets management ability; audit stayed read-only. | The source Event field remains a current supported field. |
| produk_medewerkers ACF field | Template 772 vgms33; snippet 9; product editor workflow | Tasks 3–5; group_medewerker_produkte and current values | Product → medewerker; read and written | RETIRE_PENDING_DEPENDENCY | The active reader and writer support the confirmed reverse Product requirement. | Approve a canonical reverse Product model, replace consumers and writer, and complete separately approved data decisions. | Product Single 124 public query did not render during the observed coming-soon response. | Product 767/768 edges remain EXCLUDE. |
| 9-acf-product-copy.php | Product acf/save_post at priority 20 | Task 4 runtime file and SHA-256; no source-controlled copy found | Writes produk_medewerkers when empty; preserves non-empty selections | RETIRE_PENDING_DEPENDENCY | It remains an active writer for the current supported reverse Product flow. | After separate architecture approval, verify every reader and writer and approve any required edge migration before retirement. | Runtime-only snippet; no source parity and no Novamira snippet-management ability. | Existing values do not identify whether an editor or the snippet wrote them. |
| produk_bron_event ACF field | ProductPeopleResolver; ProductSourceEventBricks; snippet 9 | Tasks 4 and 5; active source-controlled/deployed integrations | Product → source Event; read by canonical and legacy flows | KEEP_LEGACY | It is the supported source Event input for canonical Product People and separate source Event display. | No Person-specific removal planned; keep it separate from reverse Product People. | Product Single 124 public execution remains unverified. | Not a Person edge and not authority to migrate product-person data. |
| Broadcast host and guest fields | Template 1127 loops sghlop and sgglop | Task 6 schema, saved Bricks content, and five published episode values | Uitsending → medewerker; relationship reads | KEEP_LEGACY | The current episode model still supports host and guest relations, though all five published values are empty. | Any later broadcast migration needs its own relationship and consumer review. | Empty values give no migration sample. | No profile or episode edge may be inferred or migrated from field definitions alone. |
| Person → Event Participation → Event read contract | No Person-context Bricks caller; repository method events_for_person exists | Task 5 source/deployed hashes and tests inspected; no tests run | Read-only reverse Event lookup | READY_FOR_CONSUMER_REPLACEMENT | The repository provides an ordered canonical reverse read; a Person-context Bricks adapter is still needed. | Design the adapter's public endpoint constraints and obtain separate consumer approval. | Zero Person and Event Participation records prevent live-result verification. | The contract does not approve copying profiles or Event edges. |

The current content_category taxonomy has zero profile assignments. The shared 4d1dd4 component's two current demo-page callers use only the current context image and title. Template 77 has no active display condition. None of these is a current Person relationship dependency. Template 124 uses canonical Product People and source Event adapters; Template 774 uses canonical Event Participation. Neither is counted as an active legacy caller of 6b463f or snippet 10.

### Dependency order

| Order | Gate or dependency | Evidence and blocker | Earliest change it permits |
|---:|---|---|---|
| 1 | Preserve provenance and data rulings | Profiles 753–757 remain DEFER; Event 763 and Product 767/768 edges remain EXCLUDE. Person and Event Participation counts are zero. | No record creation, edge migration, route redirect, or legacy cleanup is permitted by Task 7C. |
| 2 | Decide the supported reverse Product contract | Template 772 reads produk_medewerkers in reverse; snippet 9 writes that field; canonical Product People has no reverse API. Task 5 selected outcome 1. | A separately approved Product-to-Person reverse architecture can determine whether and how the current flow is replaced. |
| 3 | Complete the reverse Product consumer and writer review | Current Product field, Template 772 helper, save hook, and editor flow form one active chain. Known edges have no migration authority. | Only after the architecture and a separate edge-level data decision can any link in the copy chain be considered for removal. |
| 4 | Design the Person profile and archive consumers | Person fields exist, but there is no persistent Person, no verified single destination, no archive canonical link, and no short-bio field. | Design can proceed without copying protected profiles. Cutover and redirects still require approved target records and route gates. |
| 5 | Replace the reverse Event consumer | events_for_person exists and is source/runtime matched; no Bricks Person-context adapter calls it. | A reviewed adapter can replace Template 772's legacy Event loop after canonical public endpoint checks and an approved consumer cutover. |
| 6 | Resolve profile classification semantics | The current role/group terms mix persistent, contextual, and programme meanings. | Only a reviewed mapping can replace the role/group presentation or migrate any assignment. |
| 7 | Retire remaining helper branches and fields | Four helper modes have no active Bricks caller but safe FluentSnippets edit access was not established. Active fields and branches still have documented readers/writers. | Retirement requires safe edit access, a fresh caller scan, and verification that no remaining read or write path depends on each item. |

The earliest safe point to break the Product copy chain is after a separate canonical reverse Product decision, consumer replacement, full reader/writer review, and edge-level data authority review. The protected Product edges stay EXCLUDE in Task 7C, so that point has not been reached. Keep snippet 9 and produk_medewerkers unchanged until those later gates pass.

## Task 8 evidence documents and inventory reconciliation

The current-state snapshot records the verified execution baseline and 10039 runtime identity, current Person and Event Participation counts, current callers, source/runtime parity, fixed profile and edge rulings, route state, and unresolved access limits. Its zero-write statement remains provisional until Task 10 repeats the fingerprints. The external pre-state and runtime evidence references are retained outside Git.

The inventory now reflects the fresh post-Task-7B state: Template 124's canonical Product People and source Event adapters; the three active `6b463f` instances and absence from Template 124; all six helper modes and their actual callers; the Template 772 reverse Product copy chain; current `/medewerkers/` and `/mense/` route responses; and the active demo-page callers of `4d1dd4`. Historical Task 7A inventory state is marked as historical and points to the current Template 124 row. The existing CSV columns were preserved.

No current lifecycle ruling changed during this audit. `LEGACY_DECOMMISSION_REGISTER.md` and historical implementation reports therefore remain untouched. The five profile `DEFER` rulings and five relationship `EXCLUDE` rulings remain fixed.

## Recommendation

Pending Task 9. The completed audit will select exactly one next implementation unit, or one evidence/access blocker where the approved plan allows it. The recommendation will not be executed.

## Zero-write closeout and validation

Pending Task 10. The closeout will repeat runtime identity and protected-state fingerprints, verify the fixed rulings and artifact inventory, validate the approved documentation outputs and CSV, and run `git diff --check`.
