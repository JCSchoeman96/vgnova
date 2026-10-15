# Editorial Collection Candidate Review

## Scope

This review asks whether named editorial collections are proven by recurring editorial installments in the current local rebuild. A collection must have a deliberate identity, at least two identifiable installments, a boundary distinct from topics and other entities, and an editor-understandable assignment rule.

**This review covers the current local corpus only. It does not establish the full historical or future production collection set.**

The seven published standard posts were checked by title, slug, content, excerpt, legacy category, and topic labels. The corpus was also scanned for recurring patterns beyond the seven documented candidates; no additional named series or repeated installment pattern emerged. No documented candidate matched a named series across two local installments. Repository examples were treated as leads, not assignments. No historical export or tracked inventory was found in the repository search used for this audit.

The documented candidate sources include [Editorial Dimensions Architecture](EDITORIAL_DIMENSIONS_ARCHITECTURE.md), [Legacy Editorial Classification Mapping](LEGACY_EDITORIAL_CLASSIFICATION_MAPPING.md), [Canonical Topic Taxonomy Migration](../../repo/VOELGOED_CANONICAL_TOPIC_TAXONOMY_MIGRATION_v0.1.md), and [Taxonomy Discovery UX](../../repo/VOELGOED_TAXONOMY_DISCOVERY_UX_v0.1.md). These documents provide research leads; they do not prove current local membership.

## Candidate decisions

All seven documented candidates are `DEFER`, confidence `LOW`, with `review_required = YES`. Each has zero matching local posts, so no recurring identity is proven. No candidate term should be created from this evidence.

### GeloofsVRAAG

- **Evidence:** Documented as a candidate in editorial architecture materials; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** The name may describe a recurring series, but the local `geloof` topic and the word “Geloof” do not prove collection membership.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** Can two distinct historical installments and a stable editor-facing assignment rule be verified?

### #StilwordSondag

- **Evidence:** Documented candidate; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** It has a series-like name, but current evidence cannot distinguish an editorial series from a devotional theme or campaign.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** Are there at least two actual installments with a consistent identity and boundary?

### Wat ek geleer het...

- **Evidence:** Documented candidate; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** The phrase could be a recurring package or a one-off headline pattern. No local evidence resolves that distinction.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** Do actual installments share a deliberate package identity beyond similar wording?

### Hoopstories

- **Evidence:** Documented candidate; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** The name overlaps semantically with the `Mensstories` topic. Shared subject matter does not prove a collection.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** Is this a named recurring series with membership rules separate from the topic pillar?

### Só oorkom ek...

- **Evidence:** Documented candidate; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** It may describe a recurring story package or a theme. No local installments establish either interpretation.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** Can two distinct installments and a stable package boundary be verified?

### Gelukkig getroud

- **Evidence:** Documented candidate; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** The phrase may describe a topic area or commercial vertical rather than a durable editorial package.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** Does it have recurring editorial installments with an assignment rule distinct from topic or commercial classification?

### Mentorklas

- **Evidence:** Documented candidate; no matching local installment was found.
- **Matching posts:** None.
- **Collection assessment:** Its identity may be a programme, course, access offer, or person-led activity. Those boundaries must be resolved before treating it as an editorial collection.
- **Decision:** `DEFER` — LOW confidence.
- **Remaining question:** What entity did this name identify, and are there two editorial installments that belong to it?

## False friends and boundaries

- `Geloof` and `Mensstories` are topic labels. Topic overlap is not collection evidence.
- A campaign or devotional label such as `#StilwordSondag` needs evidence of a durable editorial package, not just repeated promotion.
- A recurring headline shape such as “Wat ek geleer het...” or “Só oorkom ek...” does not prove a collection without identifiable installments and an assignment rule.
- `Gelukkig getroud` may be a subject or commercial vertical.
- Commercial classifications such as `Leefstyl`, `Natuurlik Gesond`, and `Finansies` remain separate dimensions and do not establish editorial collections.
- `Mentorklas` may be a programme, course, access offer, or person-related label.
- Podcast series and Voelgoed Live programmes are separate entities and must not be represented as `editorial_collection` terms.

## Recommendation

No collection term or relationship is supported by the current local evidence. Keep `editorial_collection` empty. Revisit a candidate only after two actual installments and its entity/topic/commercial boundaries are verified from current runtime content or an approved historical source.
