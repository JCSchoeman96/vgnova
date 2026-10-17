# Task 4E fixture removal report

## Scope and baseline

- Task: controlled removal of the Task 4D `READY_TO_REMOVE` set.
- Baseline: `origin/main` at `d77c4515ec42ba6af294e7372906084630e1e52a` (PR #11).
- Branch: `chore/remove-approved-local-fixtures`.
- Worktree: `.worktrees/chore-remove-approved-local-fixtures`.
- Runtime: `http://localhost:10039`, WordPress 7.1.3, selected Novamira profile `vgnova`.
- Pre-removal runtime writes: 0.

The only authorized permanent deletions are posts 513, 514, 516, 518, and 519. Protected objects are 375, 521, 523, and 641. No taxonomy, term, ACF definition, Bricks template, migration option, or media attachment is in scope.

## Preflight before the first deletion

**Result: PASS.** All required Task 4D preconditions matched. The complete set passed before any deletion began.

| ID | Type / status | Title | Slug | Modified | `content_category` | `content_topic` | Primary | HappyFiles | Featured media |
|---:|---|---|---|---|---|---|---|---|---:|
| 513 | post / trash | Jou oggend-routine vir meer rustigheid | `oggend-routine-rustigheid__trashed` | 2026-05-27 08:06:35 | — | — | — | — | 569 |
| 514 | post / publish | Jou oggend-routine vir meer rustigheid | `oggend-routine-rustigheid-2` | 2026-05-27 08:05:51 | `nuwe-jy` | `nuwe-jy` | — | `nuwe-jy` | 515 |
| 516 | post / publish | Geloof wat jou werkweek dra | `geloof-werkweek` | 2026-05-27 08:05:51 | `geloof` | `geloof` | — | `geloof` | 517 |
| 518 | post / publish | Ouerskap sonder perfeksie | `ouerskap-sonder-perfeksie` | 2026-05-27 08:05:52 | `ouerskap` | `ouerskap` | — | `ouerskap` | 567 |
| 519 | post / publish | Geldwenke vir enkelma's | `geldwenke-enkelma` | 2026-05-27 08:05:53 | `werk-geld` | `begroting-geldbestuur` | `begroting-geldbestuur` | `werk-geld` | 520 |
| 375 | post / publish | Integer maximus sapien in bibendum. | `integer-maximus-sapien-in-bibendum` | 2026-05-20 15:15:58 | — | — | — | — | 154 |
| 521 | post / publish | Herstel na verlies: 'n mensstory | `herstel-na-verlies` | 2026-05-27 08:05:54 | `mensstories` | `mensstories` | — | `mensstories` | 522 |
| 523 | product / publish | Voelgoed Welstand Pakket | `welstand-pakket` | 2026-05-29 10:24:12 | `natuurlik-gesond`, `nuwe-jy` | `oefening-fiksheid` | `oefening-fiksheid` | — | 524 |
| 641 | post / publish | QA Self Featured Content Test | `qa-self-featured-content-test` | 2026-05-29 12:40:52 | `self` | `self` | — | `self` | 153 |

The protected objects' other taxonomy state was also recorded: 375 has no assigned taxonomy; 521 has only `content_category=mensstories`, `hf_cat_post=mensstories`, and `content_topic=mensstories`; 523 also has `product_type=simple` and `product_cat=uncategorized`; 641 has only `content_category=self`, `hf_cat_post=self`, and `content_topic=self`.

### Counts and ownership before removal

| Measure | Preflight result |
|---|---:|
| Standard posts, published | 7 |
| Standard posts, trash | 1 |
| Products, all statuses | 21 (10 published, 7 draft, 1 private, 3 trash) |
| `content_category` | 13 terms, 8 relationships, 7 objects |
| `content_topic` | 55 terms, 7 roots, 7 relationships, 7 objects |
| `editorial_format` | 3 terms (`article`, `video`, `audio`), 0 relationships |
| `editorial_collection` | 0 terms, 0 relationships |

`content_category` ownership was exactly 514 → `nuwe-jy`; 516 → `geloof`; 518 → `ouerskap`; 519 → `werk-geld`; 521 → `mensstories`; 523 → `natuurlik-gesond` and `nuwe-jy`; 641 → `self`.

`content_topic` ownership was exactly 514 → `nuwe-jy`; 516 → `geloof`; 518 → `ouerskap`; 519 → `begroting-geldbestuur`; 521 → `mensstories`; 523 → `oefening-fiksheid`; 641 → `self`. The only Primary Topics were 519 → `begroting-geldbestuur` (term 383) and 523 → `oefening-fiksheid` (term 357).

### Inbound references

- No Bricks template preview points to 513, 514, 516, 518, or 519. The known deferred previews remain template 372 → 521 and template 690 → 523.
- No ACF `relationship` or `post_object` field on a post or options page points to the removal set. The Featured Content Manager's correct storage ID is `featured-content-manager`; values are 542 for Nuwe Jy, 534 for Geloof, and 531 for Self. The other five FCM fields are empty. The Member Perks dashboard page field points to 990, outside this task.
- No content or excerpt URL reference to a removal-set slug or post query URL was found.
- No active theme or FluentSnippets code reference to the removal set was found. Exact custom-code ID matches are confined to the retired Platform Core migration manifest and its synthetic tests. The retired migration is historical provenance, not a live object consumer.
- The historical migration option's references to 514, 516, 518, and 519 are allowed provenance.

### Migration record and retirement marker before removal

| Value | Preflight result |
|---|---|
| Migration ID | `content-topic-local-v1` |
| Historical fingerprint | `80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847` |
| Serialized-record SHA-256 | `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95` |
| Record keys | `migration`, `source_fingerprint`, `created_relationships`, `started_at`, `completed_at`, `source_snapshot` |
| Lifecycle | `retired` |
| Retired timestamp | `2026-10-15T04:53:54+00:00` |
| Retirement reason | `editorial classification supersedes local migration seeds` |
| Serialized-marker SHA-256 | `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3` |

### Media before removal

| Attachment | Expected owner before | File exists | Metadata exists | Metadata SHA-256 |
|---:|---:|---|---|---|
| 569 | 513 | Yes | Yes | `119359ce071ad57735d003f0948ef4396c7f46a61719b2489f5193bd2d33f7b4` |
| 515 | 514 | Yes | Yes | `1a0d37085ff8e7cbd40511b3198cd18fb774b42f15c4550d039cc3f77a57d61d` |
| 517 | 516 | Yes | Yes | `623647302fa8022ba89f7452283bc6a8853d9dbbb16f8caa8d86e34287cb134f` |
| 567 | 518 | Yes | Yes | `6159bbbb03ea3bad5f3cb0eef93cb990b42a3a38104ab1b120047937309babcd` |
| 520 | 519 | Yes | Yes | `851ffe7b394e52af375aa30674a84612e87d7ac7b0d4138decd9944de876b9db` |

### Route and FCM baseline

The four published removal-set routes returned HTTP 200 without redirects before deletion. Post 641 returned HTTP 200 and rendered the Self Featured Content selection “Slaapmasker Deluxe” (product 531). No redirect was configured for the removal routes.

## Removal execution

After the full five-object preflight passed, the following single WP-CLI command ran through Novamira profile `vgnova` against the verified `http://localhost:10039` site:

```text
wp post delete 513 514 516 518 519 --force
```

WordPress reported success for each of the five IDs. No direct SQL, taxonomy-row cleanup, manual post-meta deletion, or pre-detachment of media was used. No other runtime mutation command was run.

## Post-removal verification

**Result: PASS.** Read-only queries after the command confirmed posts 513, 514, 516, 518, and 519 are permanently absent. None is merely trashed. Protected objects 375, 521, 523, and 641 remain with the same type, status, slug, modified timestamp, taxonomy relationships, and Primary Topic values recorded above.

### Counts and remaining ownership

| Measure | Before | After |
|---|---:|---:|
| Standard posts, published | 7 | 3 |
| Standard posts, trash | 1 | 0 |
| Products, all statuses | 21 | 21 |
| `content_category` terms / relationships / objects | 13 / 8 / 7 | 13 / 4 / 3 |
| `content_topic` terms / roots / relationships / objects | 55 / 7 / 7 / 7 | 55 / 7 / 3 / 3 |
| `editorial_format` terms / relationships | 3 / 0 | 3 / 0 |
| `editorial_collection` terms / relationships | 0 / 0 | 0 / 0 |

The remaining published standard posts are exactly 375, 521, and 641. Product statuses remain 10 published, 7 draft, 1 private, and 3 trash.

Remaining `content_category` owners are 521 → `mensstories`; 523 → `natuurlik-gesond` and `nuwe-jy`; and 641 → `self`. Remaining `content_topic` owners are 521 → `mensstories`; 523 → `oefening-fiksheid`; and 641 → `self`. The only remaining Primary Topic is 523 → `oefening-fiksheid` (term 357). Post 519's Primary Topic relationship disappeared with post 519; the term itself remains. No other Primary state changed.

`content_category` and `content_topic` term inventories and registrations were not changed. No `editorial_format` or `editorial_collection` relationships were created. HappyFiles (`hf_cat_post`) retains its 15 terms and only the two relevant surviving relationships: 521 → `mensstories` and 641 → `self`; no relationship to a deleted object remains. No HappyFiles term was removed.

### Protected objects, templates, and Featured Content Manager

The protected objects re-query matched the preflight values:

| ID | Type / status | Slug | Modified | `content_category` | `content_topic` | Primary |
|---:|---|---|---|---|---|---|
| 375 | post / publish | `integer-maximus-sapien-in-bibendum` | 2026-05-20 15:15:58 | — | — | — |
| 521 | post / publish | `herstel-na-verlies` | 2026-05-27 08:05:54 | `mensstories` | `mensstories` | — |
| 523 | product / publish | `welstand-pakket` | 2026-05-29 10:24:12 | `natuurlik-gesond`, `nuwe-jy` | `oefening-fiksheid` | `oefening-fiksheid` |
| 641 | post / publish | `qa-self-featured-content-test` | 2026-05-29 12:40:52 | `self` | `self` | — |

Bricks template 372 still has preview post 521; template 690 still has preview product 523. Featured Content Manager options are unchanged (`featured_nuwe_jy=542`, `featured_geloof=534`, `featured_self=531`; the other five fields remain null). Post 641 still returns HTTP 200 and renders the current Self featured item, “Slaapmasker Deluxe” (product 531).

### Migration history

The historical option `vgpc_content_topic_migration_local_v1` remains byte-identical by serialized-record SHA-256: `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95`. Its saved fingerprint remains `80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847`. The separate retirement marker remains byte-identical by serialized-marker SHA-256: `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3`; lifecycle remains `retired`, timestamp `2026-10-15T04:53:54+00:00`, reason `editorial classification supersedes local migration seeds`.

The default read-only command `wp vg-content-topic-migrate --format=json` was run after removal. It reports `lifecycle=retired`, `baseline_matches=false`, and a current computed fingerprint of `6cd0e2d4e8f81002db89603e92c502989644c3a498825fdebc4630f917ab6199`. The reported blockers describe the expected mismatch: 4 legacy relationships across 3 objects remain, the saved baseline count no longer matches, and the saved record does not match current source data. The output confirms the retired lifecycle keeps apply and rollback disabled. This is the expected result of deleting the proof objects and is not migration-record corruption. No `--apply`, `--rollback`, or `--retire` command was run.

### Media, routes, and scope

All five protected media attachments remain present, with files and attachment metadata present. Metadata hashes match the pre-removal values above. WordPress detached each attachment from its deleted parent as an expected side effect:

| Attachment | Exists | File exists | Metadata exists | `post_parent` after | Metadata SHA-256 unchanged |
|---:|---|---|---|---:|---|
| 569 | Yes | Yes | Yes | 0 | Yes |
| 515 | Yes | Yes | Yes | 0 | Yes |
| 517 | Yes | Yes | Yes | 0 | Yes |
| 567 | Yes | Yes | Yes | 0 | Yes |
| 520 | Yes | Yes | Yes | 0 | Yes |

After removal, `/content/oggend-routine-rustigheid-2/`, `/content/geloof-werkweek/`, `/content/ouerskap-sonder-perfeksie/`, and `/content/geldwenke-enkelma/` each return HTTP 404 without a redirect. The `/?p=513` check also returns 404 without a redirect. No redirects were created.

Runtime impact: 5 posts permanently deleted; 0 media deleted; 0 taxonomy terms deleted; 0 migration records or markers changed; 0 protected objects changed; 0 product changes; 0 Platform Core source changes. Expected attachment `post_parent → 0` changes are the only attachment-side effect. No unrelated writes were made.

The Task 4D READY-set simulation matched exactly: 3 published standard posts (375, 521, 641), 0 trashed standard posts, 21 products, `content_category` at 13 / 4 / 3, `content_topic` at 55 / 7 roots / 3 / 3, `editorial_format` at 3 / 0, and `editorial_collection` at 0 / 0.
