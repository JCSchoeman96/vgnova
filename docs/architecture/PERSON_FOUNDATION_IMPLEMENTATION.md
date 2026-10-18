# Canonical Person Foundation Implementation

**Task:** Voelgoed Task 5B

**Date:** 2026-10-18

**Status:** Implemented and runtime-verified

**Source baseline:** `c1d2af80a71b477e0eb859d3f74ea3741eab9b71`

## Ownership

ACF Pro owns the canonical Person schema:

- `person` post type;
- `person_type` taxonomy;
- `group_vg_person_details` field group.

Their source-controlled ACF Local JSON lives in `site-code/acf-json/`. The Bricks Child theme's active `acf-json` path points to that shared directory. The three editorial definitions moved into it without content changes; their baseline, source, and active-path SHA-256 values match.

Platform Core owns `content_topic` and the Primary, Secondary, Effective Topic, and classification-state policy. Its supported object types now include `person`; `medewerker` remains supported. No topic terms, roots, or legacy assignments were changed.

## Person post type

ACF registers `person` with public queries, admin UI, REST support, and these routes:

```text
archive: /mense/
single:  /mense/{person-slug}/
```

The archive and rewrite base are both `mense`; `with_front` is false. The post type supports `title`, `editor`, `thumbnail`, `excerpt`, and `revisions`.

Native WordPress storage is authoritative for profile basics:

| WordPress storage | Person data |
|---|---|
| `post_title` | Person name |
| Featured image | Profile photo |
| `post_excerpt` | Short bio |
| `post_content` | Full bio |

No duplicate ACF fields exist for those values.

## Person details and type

The optional `group_vg_person_details` group applies only to `post_type == person` and contains:

| Field | ACF type | Storage |
|---|---|---|
| `person_professional_title` | Text | Post meta |
| `person_credentials` | Textarea | Post meta |
| `person_company` | Text | Post meta |
| `person_website` | URL | Post meta |
| `person_type` | Taxonomy control | WordPress `person_type` relationship |

`person_type` is flat, visible in the admin and REST API, and has no public taxonomy queries or rewrite route. Its field control allows multiple future approved classifications, saves and loads WordPress term relationships, and does not allow inline term creation. It does not use ACF post meta as a separate classification source. Person is not attached to legacy `content_category`.

The source-controlled `seed-person-type-terms.php` seeder approves only `Mentor` (`mentor`). It creates the term when absent, leaves an exact existing term unchanged, stops safely on conflicts or unapproved terms, and creates no relationships. The runtime contains exactly one `person_type` term and zero Person assignments.

Person publication uses WordPress post status. Indexing belongs to the SEO system; protected access belongs to the access or membership system. There is no custom Person status field.

## Topic policy

Person can have direct `content_topic` relationships for expertise and relevance. Effective Topics include the direct terms and their ancestors. Person has no Primary or Secondary Topic role:

```text
direct topics                  supported
effective topics and ancestors supported
Primary Topic                  unsupported
classification state           NOT_APPLICABLE
```

The Primary field group has no `person` location. `set_primary_topic()` rejects Person, and the QA run confirmed that it stored no Primary value. The existing policy for `medewerker` is unchanged.

## Runtime setup and QA

The ACF definitions were checked with ACF JSON status and per-key dry runs. Each dry run showed only the expected `Create` action. The implementation then synced only:

```text
post_type_person
taxonomy_person_type
group_vg_person_details
```

The unrelated pending Member Perk field groups were not synced. A single explicit `wp rewrite flush` refreshed routes after setup; no per-request rewrite flush was added.

The reproducible runtime check is `site-code/runtime-tests/person-foundation-qa.php`. It used a unique `[QA][AUTOMATED]` marker and ownership token, published the temporary Person for route checks, and removed it permanently in cleanup. The run verified native excerpt/content, all four optional ACF fields, the Mentor relationship, direct `gesondheid`, effective `nuwe-jy`, `NOT_APPLICABLE`, and rejected Primary assignment. `/mense/` and the temporary single both returned HTTP 200.

After cleanup, all Person post statuses total zero; the temporary post has no post meta or taxonomy edges; Mentor has zero relationships; and Person has zero `person_type` and `content_topic` relationships. The shared topic terms and counts returned to their pre-QA state.

The QA script snapshots profiles 753–757, event 763, and products 767/768 before and after its run. It verified the profile status, slug, modified timestamp, role and group terms, `content_topic`, and `content_category`; it also verified the event/product statuses, slugs, timestamps, and relationship IDs. All snapshots matched. The five profile migration decisions remain `DEFER`.

## Regression checks

The following standalone runners pass:

```text
php site-code/person-foundation/tests/run-person-foundation-tests.php
10 Person foundation tests passed

php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
67 content topic tests passed

php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
11 editorial dimension seeder tests passed
```

Runtime state remains `content_topic`: 55 terms, 7 roots, and 0 relationships after QA. Editorial state remains `editorial_format`: 3 terms and 0 relationships; `editorial_collection`: 0 terms and 0 relationships. The active Core copies of `ContentTopicTaxonomy.php` and `ContentTopicRoles.php` match their source-controlled module files.

## Legacy and implementation boundaries

Task 5B creates no Person from `medewerker` and changes no legacy profile, event, product, team, broadcast, podcast, course, editorial, Bricks, or FluentSnippets data or consumer. `/medewerkers/` remains active and has no redirect. `team_member` and `team_department` remain outside scope. No Person expertise topic is assigned persistently.

Task 5B does not resolve legacy provenance, create migration mappings, migrate Mentor assignments, implement `event_participation`, change `event_medewerkers`, migrate product people, modify `9-acf-product-copy.php` or `10-vg-bricks-medewerker-queries.php`, add Person relationships to Uitsending, podcasts, courses, or editorial posts, or decommission `medewerker`, `team_member`, or any other legacy model. It does not migrate Bricks templates, create old-URL redirects, or change FluentSnippets. Social links, phone, email, address, booking/contact fields, and social repeaters remain deferred until a demonstrated profile requirement exists. No real person's expertise topic was assigned.

Task 5C must independently decide `MIGRATE`, `DO_NOT_MIGRATE`, or `DEFER` for profiles 753–757. Only a future approved `MIGRATE` decision authorizes creating a canonical Person from a legacy profile.
