# Task 3B: Local Seed Editorial Review

- Review date: 2026-10-14
- Site: `http://localhost:10039/`
- Source baseline: `205169b7cebf8eeb4bb9ee09826feb263d15d1fd`
- Scope: the seven local Task 2 migration-seed objects listed below
- Result: read-only editorial review; no WordPress classification was applied

This is a controlled review of seven local proof objects. It is not a production corpus migration. The content evidence below comes from the live post or product body, excerpt, title, available ACF values, and current taxonomy relationships. Placeholder copy is identified as such and is not treated as supporting evidence.

## Read-only proposal manifest

| ID | Type | Title | Current seed | Proposed Primary | Proposed Secondary Topics | Confidence | Evidence summary | Apply? | Human/editorial review required? | Reason |
|---:|---|---|---|---|---|---|---|---|---|---|
| 514 | `post` | Jou oggend-routine vir meer rustigheid | `nuwe-jy` | None; `stres-balans` remains only a possibility | None | LOW | The body and excerpt say practical self-care steps for a calm start, but do not distinguish stress balance from broader well-being. | No | Yes | The available copy is too short to choose the most specific useful topic without guessing. |
| 516 | `post` | Geloof wat jou werkweek dra | `geloof` | Unselected possibility: `geloofslewe` | None | MEDIUM | The title frames faith in a workweek; the body says to keep values alive between meetings and emails. The short copy does not establish a narrower faith subject. | No | Yes | `geloofslewe` is a possible topic for an editor to consider, not a selected classification. |
| 518 | `post` | Ouerskap sonder perfeksie | `ouerskap` | Unselected possibility: `ouerskap` | None | MEDIUM | The title names parenting; the body says to show yourself grace when the day does not go to plan, without mentioning a child or parenting situation. | No | Yes | The title points to parenting, but the body does not confirm the topic in enough detail. |
| 519 | `post` | Geldwenke vir enkelma's | `werk-geld` | `begroting-geldbestuur` | None | HIGH | The body explicitly offers realistic steps for a budget; the title gives the single-mother context. | No | No editorial review; lifecycle decision required | The specific topic is supported. Replacing the migration-owned seed would make Task 2 rollback refuse before changing anything. |
| 521 | `post` | Herstel na verlies: 'n mensstory | `mensstories` | Unselected possibility: `mensstories` | None | MEDIUM | The body says it is one woman's path to hope and community. Remaining body copy is placeholder text. The ACF `article_lede` is an instruction about how the field displays, not article evidence. | No | Yes | `mensstories` is a possible topic for an editor to consider, not a selected classification; the copy does not support `ware-lewensverhale`. |
| 523 | `product` | Voelgoed Welstand Pakket | `nuwe-jy` | `oefening-fiksheid` | None | HIGH | The full product description centers on a 5 km group run or walk. Family and community references frame the event and do not establish a second topic. | No | No editorial review; lifecycle decision required | The specific topic is supported. Replacing the migration-owned seed would make Task 2 rollback refuse before changing anything. |
| 641 | `post` | QA Self Featured Content Test | `self` | None | None | LOW | The body identifies itself as a temporary QA article, followed by placeholder text. The excerpt also describes a QA test. | No | Yes | An editor must decide whether this test object belongs in the reviewed editorial corpus before it receives a semantic classification. |

The complete manifest was prepared before any proposed classification write. No WordPress object was changed.

## Object review records

### 514 — post: Jou oggend-routine vir meer rustigheid

- **Content evidence:** The body reads, “Praktiese selfsorg-stappe om jou dag kalm te begin.” The excerpt repeats that sentence. The remaining body text is example copy. No ACF values or additional relationships were found.
- **Previous migration seed:** `nuwe-jy` (`content_topic`); legacy `content_category`: `nuwe-jy`.
- **Proposed classification:** Primary: none selected. `stres-balans` was considered but not established by the copy. Secondary: none. Effective Topics: not proposed.
- **Confidence:** LOW.
- **Decision:** NOT APPLIED — NEEDS HUMAN REVIEW.
- **Rationale:** Calmness and self-care are present, but the copy does not distinguish stress balance from broader emotional well-being. The seed alone does not resolve that choice.

### 516 — post: Geloof wat jou werkweek dra

- **Content evidence:** The body reads, “Hoe om jou waardes lewendig te hou tussen vergaderings en e-posse.” The excerpt repeats that sentence. The remaining body text is example copy. No ACF values or additional relationships were found.
- **Previous migration seed:** `geloof` (`content_topic`); legacy `content_category`: `geloof`.
- **Proposed classification:** No Primary selected. Unselected possibility at MEDIUM confidence: `geloofslewe`. Secondary: none. Effective Topics if an editor accepts this possibility: `geloofslewe`, `geloof`.
- **Confidence:** MEDIUM.
- **Decision:** NOT APPLIED — NEEDS HUMAN REVIEW.
- **Rationale:** The title and body suggest living out faith in daily work. The available copy does not show whether `geloofslewe` is the right specific subject or whether the broader `geloof` topic is intended.

### 518 — post: Ouerskap sonder perfeksie

- **Content evidence:** The body reads, “Genade vir jouself wanneer die dag nie volgens plan verloop nie.” The excerpt repeats that sentence. The remaining body text is example copy. No ACF values or additional relationships were found.
- **Previous migration seed:** `ouerskap` (`content_topic`); legacy `content_category`: `ouerskap`.
- **Proposed classification:** No Primary selected. Unselected possibility at MEDIUM confidence: `ouerskap`. Secondary: none. Effective Topics if an editor accepts this possibility: `ouerskap`.
- **Confidence:** MEDIUM.
- **Decision:** NOT APPLIED — NEEDS HUMAN REVIEW.
- **Rationale:** The title identifies parenting, but the actual copy discusses self-compassion without a parenting-specific example. An editor should confirm that the title reflects the full article.

### 519 — post: Geldwenke vir enkelma's

- **Content evidence:** The body reads, “Bederf jou begroting met realistiese stappe wat werk.” The excerpt repeats that sentence. The remaining body text is example copy. No ACF values or additional relationships were found.
- **Previous migration seed:** `werk-geld` (`content_topic`); legacy `content_category`: `werk-geld`.
- **Proposed classification:** Primary: `begroting-geldbestuur`. Secondary: none. Effective Topics if accepted: `begroting-geldbestuur`, `geld`, `werk-geld`.
- **Confidence:** HIGH.
- **Decision:** NOT APPLIED — ARCHITECTURE ISSUE.
- **Rationale:** The actual body explicitly concerns budgeting, so the specific child topic is supported. The existing `werk-geld` seed is a relationship recorded by Task 2. Removing it would leave the historical migration record out of sync and cause rollback to refuse. Keeping both the seed and its child as Direct Topics would violate the no-redundant-ancestor rule. No relationship was changed pending an explicit Task 2 lifecycle decision.

### 521 — post: Herstel na verlies: 'n mensstory

- **Content evidence:** The body reads, “Een vrou se pad na hoop en gemeenskap.” The rest of the post body is placeholder copy. ACF `article_lede` contains the text “Een vrou se pad na hoop en gemeenskap — hierdie lede verskyn in die held wanneer die ACF-veld gevul is,” which describes field display behavior rather than adding editorial evidence. No other ACF values were found.
- **Previous migration seed:** `mensstories` (`content_topic`); legacy `content_category`: `mensstories`.
- **Proposed classification:** No Primary selected. Unselected possibility at MEDIUM confidence: `mensstories`. Secondary: none. Effective Topics if an editor accepts this possibility: `mensstories`.
- **Confidence:** MEDIUM.
- **Decision:** NOT APPLIED — NEEDS HUMAN REVIEW.
- **Rationale:** The title and one-sentence summary support the broad story topic, but do not establish whether the account is a verified true-life story. Do not promote it to `ware-lewensverhale` without that evidence.

### 523 — product: Voelgoed Welstand Pakket

- **Content evidence:** The full description invites a group to run or walk 5 km, then describes time together and a community initiative involving families and health. The short description is “Voorbeeldproduk — beeld via Pexels.” The product is a simple product. No ACF values, source-event relationship, or related mentor were found; the `related_mentors` product metadata is empty. The WooCommerce category is `uncategorized`.
- **Previous migration seed:** `nuwe-jy` (`content_topic`); legacy `content_category`: `nuwe-jy`, `natuurlik-gesond`.
- **Proposed classification:** Primary: `oefening-fiksheid`. Secondary: none. Effective Topics if accepted: `oefening-fiksheid`, `nuwe-jy`.
- **Confidence:** HIGH.
- **Decision:** NOT APPLIED — ARCHITECTURE ISSUE.
- **Rationale:** The full description clearly centers on a 5 km walk or run. Family, health, and community are framing details, not a second subject. `oefening-fiksheid` is the existing specific child of `nuwe-jy`, so retaining both as Direct Topics would be redundant. Removing the seed is blocked by the Task 2 rollback lifecycle decision. `natuurlik-gesond` remains only in `content_category` and is not proposed as a semantic topic.

### 641 — post: QA Self Featured Content Test

- **Content evidence:** The first paragraph calls this a temporary QA article for testing Self featured-content automation. The excerpt says the same. The remaining body is placeholder and Lorem Ipsum text. No ACF values or additional relationships were found.
- **Previous migration seed:** `self` (`content_topic`); legacy `content_category`: `self`.
- **Proposed classification:** Primary: none. Secondary: none. Effective Topics: not proposed.
- **Confidence:** LOW.
- **Decision:** NOT APPLIED — NEEDS HUMAN REVIEW.
- **Rationale:** The content identifies itself as a test fixture. An editor must decide whether it should be omitted from editorial classification or replaced with real content before a topic is chosen.

## Topic and hierarchy checks

Every proposed term already exists in `content_topic`:

| Proposed term | Existing term | Canonical parent | Check |
|---|---|---|---|
| `geloofslewe` | Yes | `geloof` | Semantic faith-life topic; no ancestor proposed as a Direct Topic. |
| `ouerskap` | Yes | Root | Matches the possible subject; no child is evidenced. |
| `begroting-geldbestuur` | Yes | `geld` → `werk-geld` | Most-specific supported budgeting topic; ancestors appear only in proposed Effective Topics. |
| `mensstories` | Yes | Root | Broad story topic; no evidence for `ware-lewensverhale`. |
| `oefening-fiksheid` | Yes | `nuwe-jy` | Most-specific supported activity topic; `nuwe-jy` appears only as an Effective ancestor. |

No new term is required. No proposed topic is `Vrouegesondheid`, `Leefstyl`, or a commercial term. No proposal converts `content_category` membership into semantic membership. No classification was applied, so no Primary/Direct invariant or final hierarchy pair was changed.

## Task 2 rollback lifecycle

The live option `vgpc_content_topic_migration_local_v1` was not edited. Its record fingerprint is `80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847`; the SHA-256 of the PHP-serialized option value at review time is `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95`.

`ContentTopicMigration::rollback()` checks the recorded source fingerprint and each term relationship created by Task 2 before removing anything. If an editorial replacement removes one of the seven recorded seeds, rollback returns `vgpc_topic_migration_rollback_drift` during preflight. It leaves the remaining relationships, any replacement relationship, and the option record in place. It is a safe no-op in that state, but it can no longer complete the Task 2 rollback unless the missing recorded relationship is restored.

The source architecture describes rollback drift behavior but does not say that Task 2 rollback is retired after editorial review begins, nor define a replacement snapshot/rollback path. Therefore the lifecycle status for relationship replacement is **blocked pending architecture decision**. Task 2 rollback is still operational with the seven recorded seed relationships intact; this report does not retire it. No seed was removed, and no seed plus child pair was created.

## Write and legacy-state record

- WordPress classification writes: none.
- Direct `content_topic` relationships: unchanged.
- Primary assignments: none; all seven remain empty.
- Task 2 migration option: unchanged; checksum recorded above.
- `content_category` terms and relationships: unchanged.
- Product 523 `natuurlik-gesond`: remains a legacy `content_category` term only.
- Browser editor UX: PENDING. The Novamira browser reached the site's login form when opening product 523's editor, so the authenticated editor controls could not be inspected. No credentials were requested or transmitted.
- Production corpus migration: not performed.
