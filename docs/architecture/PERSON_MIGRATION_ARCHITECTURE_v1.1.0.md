# Canonical Person Migration Architecture

**Status:** CURRENT
**Version:** 1.1.0
**Decision date:** 2026-10-26
**Execution baseline:** `ab7e7d853772fb920237e47d8fa83c72ed389fba`

This version succeeds the historical [Person Migration Architecture](PERSON_MIGRATION_ARCHITECTURE.md). The predecessor remains an accurate record of the information available during Tasks 5A–5C. This version records later owner authority and the completed Task 10A / Person 5D identity migration; it does not rewrite that earlier evidence.

## Owner authority amendment

The owner resolved provenance for legacy profiles 753–757 on 2026-10-25. Task 5C correctly recorded `DEFER` for all five profiles because that authority was not available then.

| Legacy profile | Current owner decision | Canonical outcome |
|---|---|---|
| 753 — Lynette Beer | `MIGRATE` | One canonical Person, ID 1705, slug `lynette-beer` |
| 754 — Annelize van der Merwe | `DO_NOT_MIGRATE` | No canonical Person; legacy post retained |
| 755 — Thandi Mokoena | `DO_NOT_MIGRATE` | No canonical Person; legacy post retained |
| 756 — Elna Pretorius | `DO_NOT_MIGRATE` | No canonical Person; legacy post retained |
| 757 — Retha Coetzee | `DO_NOT_MIGRATE` | No canonical Person; legacy post retained |

The versioned [owner decision record](PERSON_PROFILE_OWNER_DECISIONS_v1.0.0.md) separates identity authority from content authority and relationship authority. The source-controlled [migration authority CSV](PERSON_PROFILE_MIGRATION_AUTHORITY_v1.0.0.csv) is the executable allowlist and field policy.

## Canonical Person and durable types

The existing target remains one `person` record per approved real person, under `/mense/` and `/mense/{slug}/`. The approved `person_type` vocabulary is now exactly:

```text
Mentor / mentor
Kenner / kenner
```

Lynette receives both types by native WordPress taxonomy relationships. This is a durable person classification, not a programme-membership model. The existing ACF field supports multiple selections; no schema redesign, second taxonomy, primary type, public taxonomy route, or archive was introduced.

No other legacy role or group is promoted by this amendment. `Spreker`, `Gas`, `Paneellid`, and `Fasiliteerder` remain contextual; `Skrywer` remains editorial-relationship driven. `Voelgoed Mentor` and `Voelgoed Kenner` remain legacy group semantics and are not mapped to `person_type`.

## Task 10A migration contract and result

Task 10A migrated identity and approved durable classification only. Canonical Person 1705 is published with title `Lynette Beer` and slug `lynette-beer`. Its content and excerpt are empty, it has no featured image, and the optional fields `person_professional_title`, `person_credentials`, `person_company`, and `person_website` remain empty.

The staging photo, short bio, excerpt, post content, `medewerker_titel = Spreker`, profile status, legacy role/group assignments, topic/category assignments, and all other inferred values remain on or associated with the legacy source only. None was copied to the Person.

The following QA edges remain `EXCLUDE` and unchanged:

- Event 763 → profiles 753 and 757;
- Product 767 → profile 753;
- Product 768 → profiles 753 and 757.

No Event Participation or Product Contribution was created. No legacy relationship value was cleared. Task 10A did not delete any of profiles 753–757.

The living [migration manifest](PERSON_PROFILE_MIGRATION_MANIFEST.csv) uses `NOT_STARTED`, `MIGRATED`, and `EXCLUDED` states. `EXCLUDED` means no canonical target is authorized; it does not mean the legacy source may be deleted.

## Dual-run and decommission gates

The legacy `medewerker` records remain active alongside the canonical Person. `/mense/`, `/mense/lynette-beer/`, and `/medewerkers/lynette-beer/` resolve successfully; no redirect or canonical/robots change was introduced. Profiles 754–757 remain present as owner-confirmed fake testing/placeholding records while legacy consumers still use them.

Template 772 and Template 773 remain legacy. Task 10A did not change Templates 772, 773, 774, 124, or 1127; Medewerker Card, Product Person Card, Event participant components, FluentSnippets, legacy query helpers, or Platform Core. No Person consumer cutover occurred. Dual-run continues, and all existing legacy removal gates remain in force. `medewerker` is not `READY_TO_REMOVE`, and the Person legacy model is not decommissioned.

The next safe unit is a separately reviewed Person consumer cutover design and implementation. Task 10B and all template, route, relationship, content, and deletion work remain outside this amendment.

## Evidence

- [Task 10A implementation report](PERSON_5D_IMPLEMENTATION_v1.0.0.md)
- [Post-Task 10A current state](POST_TASK_10A_CURRENT_STATE_v1.0.0.md)
- [Task 10A runtime manifest](PERSON_5D_RUNTIME_MANIFEST_v1.0.0.json)
- [Owner decisions](PERSON_PROFILE_OWNER_DECISIONS_v1.0.0.md)
- [Legacy decommission register](LEGACY_DECOMMISSION_REGISTER.md)
