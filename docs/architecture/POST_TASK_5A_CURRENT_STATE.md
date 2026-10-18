# Post-Task 5A Current-State Snapshot

**Date:** 2026-10-17
**Baseline SHA:** `afb91b71d8011f8423e719e85e096f9c59aff057`
**Branch:** `docs/person-migration-discovery`
**Runtime:** Local staging at `http://localhost:10039/`

## Audited state

| Measure | Current state |
|---|---|
| `medewerker` registration | ACF Pro database-managed CPT; public, queryable, REST-visible |
| `medewerker` archive / single | `/medewerkers/` / `/medewerkers/{slug}/` |
| Profile records | 5 published; 0 draft; 0 private; 0 trash |
| `medewerker_rol` | 9 terms; 5 total assignments, all Mentor |
| `medewerker_groep` | 5 terms; 5 total assignments, all Voelgoed Mentor |
| Profile expertise | 0 `content_topic` assignments; 0 `content_category` assignments |
| Events | 17 published; 1 event with people; 2 event-person edges |
| Products | 20 total; 2 with people (including 1 trash); 3 product-person edges; 3 source-event links |
| Product copy helper | Published FluentSnippets runtime snippet active on all pages |
| Consumer inventory | 31 inventory rows; 5 Person-consuming Bricks templates, shared card component, one header placeholder, one uninstantiated hero component, and 2 active runtime snippets |
| Uitsending | 5 published episodes; host/guest fields exist but are empty |
| Podcasts | No usable person links on podcast-like Uitsending records |
| Courses | 5 published, 1 draft; no Person/medewerker relation found |
| Editorial | No profile relationship fields found; WP author remains `wp_users` |
| `team_member` | Active ACF database-managed CPT; zero records in all statuses; no Bricks consumer found |
| `/mense/` collision | None; route is available and currently returns 404 |
| Initial `person_type` candidate | Mentor only |

## Readiness and migration authority

| Decision | Current result |
|---|---|
| Person architecture | READY FOR IMPLEMENTATION |
| Current five-profile corpus | NOT YET APPROVED FOR MIGRATION |
| Per-profile migration decisions | 753 DEFER; 754 DEFER; 755 DEFER; 756 DEFER; 757 DEFER |
| Event 763 person edges | 2 edges; PENDING_PROVENANCE; neither approved for automatic migration |
| Product 767/768 person edges | 3 edges; PENDING_PROVENANCE; none approved for automatic migration |
| `person_type` vocabulary concept | Mentor approved as the initial concept; current five Mentor assignments are not approved for migration |

The shared creation timestamp, placeholder-style imagery, duplicated short bios, repeated classifications, `Test Event`, and QA/trash product links raise provenance questions but do not prove that the profiles are fixtures. No repository/import/source evidence establishes the five records as genuine source content. A profile's name, publication status, or similarity to a canonical IA example is not sufficient authority to migrate it. Task 5C must review identity/source, demo/QA status, photo and biography provenance, classification validity, and relationship provenance, then assign `MIGRATE`, `DO_NOT_MIGRATE`, or `DEFER` per profile.

Per-profile data, all 14 legacy terms, and the full consumer inventory are available in the [profile manifest](PERSON_PROFILE_MIGRATION_MANIFEST.csv), [classification mapping](PERSON_LEGACY_CLASSIFICATION_MAPPING.csv), and [consumer inventory](PERSON_CONSUMER_INVENTORY.csv). See the [current-state audit](PERSON_CURRENT_STATE_AUDIT.md), [migration architecture](PERSON_MIGRATION_ARCHITECTURE.md), and [decommission register](LEGACY_DECOMMISSION_REGISTER.md) for evidence and phase gates.

## Recommended sequence

1. Task 5B: implement the canonical Person foundation without copying or requiring any current `medewerker` profile; verify with temporary or reproducible test data.
2. Task 5C: resolve provenance and eligibility for every current profile; create Person records only for `MIGRATE` decisions, with deterministic legacy-ID mappings.
3. Task 6A+: implement `event_participation`; migrate only edges whose event and Person are both approved. Event 763's two edges remain `PENDING_PROVENANCE`.
4. Then migrate only approved product Person links and replace copy automation/query code atomically. Product 767/768's three current edges remain `PENDING_PROVENANCE`.
5. Later review any Uitsending/podcast/course relationships and design editorial links.
6. Final decommission task: account for every profile as migrated or explicitly excluded, verify zero legacy consumers, freeze legacy writes, validate routes/SEO, redirect old URLs, and remove legacy structures only after the gate passes.

The product phase follows event participation because the active product save helper reads `event_medewerkers`; changing product targets first could reintroduce legacy IDs. This sequence describes architecture dependencies and does not approve current event or product data for migration.

## Runtime impact

| Mutation class | Count |
|---|---:|
| WordPress persistent writes | 0 |
| Posts changed | 0 |
| Relationships changed | 0 |
| Taxonomy assignments changed | 0 |
| Routes changed | 0 |
| FluentSnippets changed | 0 |
| Migration provenance written | 0 |

No Person CPT, taxonomy, field, relationship, redirect, or migration was implemented. This snapshot records discovery and design only.
