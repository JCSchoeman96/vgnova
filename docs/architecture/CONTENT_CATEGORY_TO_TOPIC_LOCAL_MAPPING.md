# Local content category to topic mapping contract

Status: Task 2 local proof contract
Scope: the seven existing local objects and eight `content_category` relationships

## Purpose

This document defines the small local migration from `content_category` to
`content_topic`. It is not the production corpus migration. The old taxonomy
remains active, and this migration adds parallel semantic relationships only.

## Migration principles

- `content_topic` describes what an object is about.
- Entity type, contextual roles, access, editorial format, campaigns, and
  commercial context remain separate dimensions.
- Only explicit semantic pillar mappings create `content_topic` relationships.
- The migration preserves all `content_category` terms and relationships.
- It assigns only the known root topic. It does not infer child topics or
  ancestors from object titles or content.
- The migration creates no Primary Topic or Secondary Topic fields.
- The two taxonomies remain independent. No synchronization is added.

## Classification of every legacy term

| Legacy slug | Legacy term | Classification | Canonical target | Action |
|---|---|---|---|---|
| `nuwe-jy` | Nuwe Jy | Semantic pillar | `nuwe-jy` | `DIRECT_MAP` |
| `geloof` | Geloof | Semantic pillar | `geloof` | `DIRECT_MAP` |
| `self` | Self | Semantic pillar | `self` | `DIRECT_MAP` |
| `werk-geld` | Werk + Geld | Semantic pillar | `werk-geld` | `DIRECT_MAP` |
| `ouerskap` | Ouerskap | Semantic pillar | `ouerskap` | `DIRECT_MAP` |
| `huwelik` | Huwelik | Semantic pillar | `huwelik` | `DIRECT_MAP` |
| `mensstories` | Mensstories | Semantic pillar | `mensstories` | `DIRECT_MAP` |
| `leefstyl` | Leefstyl commercial container | Commercial context | None | `SKIP_COMMERCIAL_CONTEXT` |
| `aanlyn-onderwys` | Aanlyn Onderwys | Commercial vertical | None | `SKIP_COMMERCIAL_CONTEXT` |
| `doen-goed` | Doen Goed | Commercial vertical | None | `SKIP_COMMERCIAL_CONTEXT` |
| `finansies` | Finansies | Commercial vertical | None | `SKIP_COMMERCIAL_CONTEXT` |
| `natuurlik-gesond` | Natuurlik Gesond | Commercial vertical | None | `SKIP_COMMERCIAL_CONTEXT` |
| `vakansie` | Vakansie | Commercial vertical | None | `SKIP_COMMERCIAL_CONTEXT` |

If a term is assigned and has no classification, apply stops and names it in
the dry-run report. An object with commercial assignments and no semantic
assignment receives `REVIEW_REQUIRED` and no topic guess.

## Verified seven-object manifest

| ID | Type | Title | Legacy terms | Canonical topics | Skipped commercial terms | Action |
|---:|---|---|---|---|---|---|
| 514 | post | Jou oggend-routine vir meer rustigheid | `nuwe-jy` | `nuwe-jy` | None | `DIRECT_MAP` |
| 516 | post | Geloof wat jou werkweek dra | `geloof` | `geloof` | None | `DIRECT_MAP` |
| 518 | post | Ouerskap sonder perfeksie | `ouerskap` | `ouerskap` | None | `DIRECT_MAP` |
| 519 | post | Geldwenke vir enkelma's | `werk-geld` | `werk-geld` | None | `DIRECT_MAP` |
| 521 | post | Herstel na verlies: 'n mensstory | `mensstories` | `mensstories` | None | `DIRECT_MAP` |
| 523 | product | Voelgoed Welstand Pakket | `natuurlik-gesond`, `nuwe-jy` | `nuwe-jy` | `natuurlik-gesond` | `MIGRATE_WITH_COMMERCIAL_SKIP` |
| 641 | post | QA Self Featured Content Test | `self` | `self` | None | `DIRECT_MAP` |

Product 523 proves the dimension split. Its semantic topic is `nuwe-jy`.
`natuurlik-gesond` remains only in `content_category` because it represents
commercial context.

## Expected counts

| State | `content_topic` terms | Roots | Relationships | Objects | `content_category` terms | Legacy relationships |
|---|---:|---:|---:|---:|---:|---:|
| Before migration | 55 | 7 | 0 | 0 | 13 | 8 |
| After migration | 55 | 7 | 7 | 7 | 13 | 8 |
| After rollback proof | 55 | 7 | 0 | 0 | 13 | 8 |

The difference between eight legacy relationships and seven new topic
relationships is intentional. Product 523 has one semantic relationship and
one commercial relationship.

## Rollback semantics

The versioned run record stores the source fingerprint and only the canonical
relationships created by this migration. Rollback verifies the source and each
recorded relationship, then removes only those recorded relationships. It
preserves canonical relationships created separately, all canonical terms,
and every legacy term and assignment. It stops if a recorded relationship or
its term has changed.

## Non-goals

This proof does not migrate the production WordPress XML or the 1,000-plus
article corpus. It does not classify articles, add deeper topic terms, create
Primary or Secondary Topic fields, remove or detach `content_category`, change
front-end templates or URLs, redesign Leefstyl or partner data, change
Featured Content Manager, alter Vennoot mappings, or change HappyFiles sync.
