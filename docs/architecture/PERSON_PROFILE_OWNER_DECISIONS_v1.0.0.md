# Person profile owner decisions

Status: CURRENT
Version: 1.0.0
Decision date: 2026-10-25
Repository baseline: `ab7e7d853772fb920237e47d8fa83c72ed389fba`

This record adds the site owner's later authority for legacy profiles 753–757. It does not revise the historical Task 5C evidence. Task 5C correctly marked all five profiles `DEFER` because the owner had not yet confirmed their origin.

## Authority boundaries

Person identity, editorial content, and relationships have separate authority.

- **Person identity authority** decides whether a legacy profile represents a real person and whether to create a canonical Person.
- **Content authority** decides which photos, biographies, titles, excerpts, and other editorial values may move. Identity approval does not approve those values.
- **Relationship authority** decides whether an Event or Product edge represents a real relationship. Person identity approval does not approve any edge.

## Legacy profile 753: Lynette Beer

The site owner confirms that Lynette Beer is a genuine Voelgoed person. The owner approves one canonical Person identity for legacy profile 753 and confirms that both `Mentor` and `Kenner` are durable Person classifications for her.

Task 10A may create one published `person` record titled `Lynette Beer`, with slug `lynette-beer`, and assign the canonical `Mentor` and `Kenner` terms.

The owner does not approve the present staging profile's editorial values for migration. These values remain placeholders and must stay off the canonical Person:

- featured image 558, a Pexels image;
- `medewerker_kort_bio`;
- legacy excerpt and post content;
- `medewerker_titel = Spreker`;
- `medewerker_profiel_status`;
- `medewerker_rol` and `medewerker_groep` assignments;
- legacy topic and category assignments;
- all other inferred fields or relationships.

The Event and Product edges remain `EXCLUDE`:

- Event 763 to profiles 753 and 757;
- Product 767 to profile 753;
- Product 768 to profiles 753 and 757.

Task 10A must not create Event Participation or Product Contribution records for these edges, and it must not change the legacy values.

## Legacy profiles 754–757

The owner confirms that these records are fake testing or placeholder profiles:

| Legacy ID | Name | Decision |
|---:|---|---|
| 754 | Annelize van der Merwe | `DO_NOT_MIGRATE` |
| 755 | Thandi Mokoena | `DO_NOT_MIGRATE` |
| 756 | Elna Pretorius | `DO_NOT_MIGRATE` |
| 757 | Retha Coetzee | `DO_NOT_MIGRATE` |

Create no canonical Persons for these profiles. Do not delete or change their legacy records in Task 10A. Their removal remains subject to the existing consumer and decommission gates.

## Scope

This authority approves identity and the two durable classifications for profile 753. It does not approve editorial content or any Event, Product, programme, or other relationship. It excludes canonical Person creation for profiles 754–757 and does not authorize legacy deletion, template changes, redirects, or relationship migration.
