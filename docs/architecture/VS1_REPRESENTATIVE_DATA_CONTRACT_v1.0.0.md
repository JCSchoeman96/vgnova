# VS1 Representative Data Contract

| Audit item | Value |
|---|---|
| Status | BLOCKED |
| Version | 1.0.0 |
| Audit date | 2026-10-26 |
| Repository baseline | `origin/main` = `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` |
| Runtime | VG Nova Local, `http://localhost:10039/`, WordPress 7.1.3, PHP 8.5.12 |

## Data authority and classification rules

The approved VS1 specification section 47 requires real, traceable records for the Journey and regression paths. The exact record IDs in this audit are evidence only. They must not become production constants or justify new relationships.

| Category | Meaning in this contract | Current result |
|---|---|---|
| `REAL_EDITORIAL` | Content whose origin and editorial use are established and whose needed relationships are reviewed | No Article or Course candidate is verified. |
| `OWNER_AUTHORIZED` | Identity or content explicitly approved by the site owner; authority does not extend beyond the named approval | Person 1705 is approved as a canonical identity and as Mentor/Kenner only. |
| `QA_FIXTURE` | Data created or retained for tests, demonstrations, or QA | Excluded from Golden Journey data. PR #58 explicitly excludes the prior Event/Product QA edges. |
| `LEGACY` | Existing `medewerker`, old relationship values, and old consumers awaiting the Person programme | Do not substitute these for canonical Person data or cut over their consumers here. |
| `SYNTHETIC` | Placeholder or sample data without editorial provenance | Post 375 is classified as non-editorial placeholder data; its original import/source remains unresolved. |

If a staging record has unknown provenance, it is not upgraded to `REAL_EDITORIAL`. It remains ineligible until an owner-authorized provenance decision says otherwise.

## Candidate inventory

### Canonical Topic path

The canonical Topic authority is the `content_topic` taxonomy in `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php`. Current Local runtime terms show the Nuwe Jy root and child candidates below. Counts are current assigned-object counts, not editorial approval.

| Candidate | Runtime ID | Slug | Assigned-object count | Contract status |
|---|---:|---|---:|---|
| Nuwe Jy root | 349 | `nuwe-jy` | 0 | Canonical root; no linked Journey content. |
| Gesondheid | 350 | `gesondheid` | 0 | Child candidate; no linked Journey content. |
| Oefening & fiksheid | 357 | `oefening-fiksheid` | 0 | Child candidate; no linked Journey content. |
| Resepte | 362 | `resepte` | 0 | Child candidate; no linked Journey content. |
| Skoonheid & versorging | 358 | `skoonheid-versorging` | 0 | Child candidate; no linked Journey content. |
| Voeding & gewig | 354 | `voeding-gewig` | 0 | Child candidate; no linked Journey content. |

No child is selected for the Golden Journey because no qualifying Article is assigned to any candidate. The single published standard post, ID 375, has no `content_topic` assignment. No relationship is created to make a Topic path appear complete.

### Article

The current Local runtime has one published standard post: ID `375`, slug `integer-maximus-sapien-in-bibendum`. It has no `content_topic` or `content_category` assignment and its WordPress author ID is 1, not a canonical Person relationship.

The provenance authority is `docs/architecture/POST_TASK_4D_CURRENT_STATE.md` and `POST_TASK_4H_CURRENT_STATE.md`. They classify 375 as an unresolved demo/import placeholder, state that its filler content is not editorial, and leave its origin unresolved under `NEEDS_PROVENANCE`. For this contract it is `SYNTHETIC`, not a Journey candidate. The Task 1 current reality audit and Task 3 discovery gate freshly confirmed the runtime identity and zero Topic/Person edges.

No 3–5 `REAL_EDITORIAL` Article set exists in the current evidence. The Task 2 QA posts and post 641, the FCM Self test target, are QA evidence and must not stand in for approved editorial Articles.

### Canonical Person/Mentor

The only current canonical Person is Lynette Beer, ID `1705`, slug `lynette-beer`, status `publish`. The site owner's authority record, `docs/architecture/PERSON_PROFILE_OWNER_DECISIONS_v1.0.0.md`, approves her identity and the durable `Mentor / mentor` and `Kenner / kenner` classifications. The approval is present in merged PR #58, baseline commit `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`.

The same owner record withholds the legacy profile's photo, bio, excerpt, title, status, topic assignments, and all inferred relationships. Runtime confirms that Person 1705 has no featured image, editorial body, professional details, `content_topic`, Event Participation, Product Contribution, or Person-to-Course edge. The only postmeta is the Masteriyo housekeeping marker `_masteriyo_has_content=0`.

Classification: `OWNER_AUTHORIZED` for identity and Mentor/Kenner classification only. Lynette is not approved as representative Journey data. Do not add profile content or relationships to make her eligible.

### Course and paid commercial identity

Current runtime has five published Masteriyo Courses: IDs `532`, `534`, `536`, `538`, and `540`. Draft ID `438` has no slug and is not a candidate. The five published records have no confirmed editorial authorization. Runtime inspection finds no `content_topic` assignment or canonical Person/Instructor relationship for these Courses.

All five published Courses have Masteriyo `_wc_product_id=0`; Masteriyo course-list output also reports no linked Woo Product for each. The approved authority chain is Masteriyo's native Course↔Woo mapping, WooCommerce base price, PMPro active tier, and Member Perks benefit calculation. That chain is source-proven, but no Course/Product mapping or approved tier benefit is configured, `woo_discounts` is off, and exact price presentation and cart/checkout proof are absent. `COURSE_MEMBER_PRICE_GATE=CONFIGURATION_REQUIRED`. The separate Person-to-Course gate is `ARCHITECTURE_DECISION_REQUIRED` because no canonical Person ↔ Course authority exists in the inspected source or runtime contracts.

Classification: published Courses are unverified staging content, not `REAL_EDITORIAL` or `OWNER_AUTHORIZED` candidates. No Course is selected as the Golden Journey Course.

### Event and Product regression records

The runtime has 17 published Tickera Events and 9 published Woo Products, but this inventory does not establish owner-authorized representative records or a working purchase path. No canonical Event Participation or Product Contribution rows exist in any status.

The owner decision record and merged PR #58 explicitly exclude these previous QA edges from migration:

| Existing legacy edge | Required classification | Use in VS1 |
|---|---|---|
| Event 763 → profiles 753 and 757 | `QA_FIXTURE` and `LEGACY` | Prohibited; do not create canonical Event Participation. |
| Product 767 → profile 753 | `QA_FIXTURE` and `LEGACY` | Prohibited; do not create canonical Product Contribution. |
| Product 768 → profiles 753 and 757 | `QA_FIXTURE` and `LEGACY` | Prohibited; do not create canonical Product Contribution. |

No Event or ordinary Product regression object is selected. A later selection must independently prove record provenance and its purchase path. If a Person edge is exercised, it requires the canonical Event Participation or Product Contribution relationship, not a legacy edge.

### Vriendinne QA personas

The future matrix needs a non-member plus Brons, Silwer, and Goud personas. This preflight did not identify or use customer accounts, create accounts, change memberships, or test entitlements. No safe isolated test accounts are certified by this contract. A separately authorized controlled task must provision or designate isolated QA accounts, record the tier authority, and verify account isolation before running an end-to-end transition.

## Relationship contract and blockers

| Journey edge | Current authority/data | Data contract result |
|---|---|---|
| Home → Topic | `content_topic` is canonical, but no current Home Topic destination is proven by the discovery gate. | `GAP` |
| Topic → Article | Canonical taxonomy authority exists; all candidate Nuwe Jy terms have zero assignments, and post 375 is ineligible. | `GAP` |
| Article → Person | No canonical Article-to-Person field/service or row is proven. WordPress `post_author` is not a Person relationship. Spec §60 requires a separate architecture decision when no approved authority can represent this edge. | `ARCHITECTURE_DECISION_REQUIRED` |
| Person → Course | No canonical authority is proven. Product Contribution, shared Topic, Masteriyo author user, and Woo Product must not be used as substitutes. | `ARCHITECTURE_DECISION_REQUIRED` |
| Course → paid Woo purchase | All five published Courses have no linked Woo Product and no paid order. The Course member-price authority is defined, but Product/benefit configuration and runtime proof are absent. | `GAP` / `CONFIGURATION_REQUIRED` |
| Person → Event | Event Participation authority exists in source, but there are zero canonical rows. The PR #58 QA edges are prohibited. | `GAP` |
| Person → Product | Product Contribution authority exists in source, but there are zero canonical rows. The PR #58 QA edges are prohibited. | `GAP` |

The Task 3 Discovery Authority Gate is the authority for the edge-by-edge source/runtime findings. Current Local PHP reads on 2026-10-26 independently confirmed: root 349 and its five child term counts are zero; Article 375 has no Topic assignment; Person 1705 has exactly `mentor` and `kenner` and no Topic assignment; published Courses 532–540 have no Topic assignment and `_wc_product_id=0`; Event Participation and Product Contribution counts are zero in all statuses. This is current Local staging evidence, not owner approval or production-like evidence.

## Person programme boundary

PR #58 completed Task 10A identity migration only. The legacy profiles 753–757 remain, templates 772 and 773 remain legacy, and Task 10B consumer cutover has not started. VS1 must not change those templates, migrate old relationships, delete legacy profiles, add redirects, or take over the Person migration programme.

```text
PERSON_PROGRAMME_DEPENDENCY=YES
EXACT_DEPENDENCY=Task 10B must separately design and execute the canonical Person consumer cutover. Any VS1 Person template or consumer change overlapping templates 772/773 must wait for coordination with that programme unit.
```

## Data decision

The minimum dataset in spec section 47 cannot be assembled from current authorized records. There are no verified real editorial Articles or Course, no complete Topic-to-Article or Article-to-Person path, no approved Article-to-Person or Person-to-Course authority, no Course-to-Woo mapping or configured Course member benefit, no canonical Event/Product person edges, and no certified QA personas. Creating or editing data to fill these gaps is outside this read-only preflight and requires a later explicit authorization.

```text
REPRESENTATIVE_DATA_GATE=BLOCKED
ARTICLE_TO_PERSON_AUTHORITY=NONE_PROVEN
ARTICLE_TO_PERSON_GATE=ARCHITECTURE_DECISION_REQUIRED
PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED
COURSE_MEMBER_PRICE_AUTHORITY=WOOCOMMERCE_BASE_PRICE_PLUS_MEMBER_PERKS_BENEFIT
COURSE_MEMBER_PRICE_GATE=CONFIGURATION_REQUIRED
REPRESENTATIVE_DATA_CREATION_REQUIRES_AUTHORIZATION=YES
RUNTIME_PROOF_REQUIRES_MUTATION=YES
WORDPRESS_BUSINESS_MUTATIONS=0
BRICKS_MUTATIONS=0
ACF_MUTATIONS=0
ORDERS_CREATED=0
MEMBERSHIPS_CREATED=0
ENROLMENTS_CREATED=0
```

The gate is blocked because the current records do not meet the approved provenance and relationship contract. This document does not authorize synthetic relationship creation, owner-content migration, a Person consumer cutover, or production implementation.
