# Voelgoed Live media architecture design

**Status:** Approved for implementation

**Date:** 2026-09-19

## Goal

Build a reusable Voelgoed Live media system around the existing `uitsending` CPT. The system will support a channel hub, branded series hubs, episode pages, live and replay embeds, and relationship-driven content without creating a CPT for each series.

## Decisions

- Keep `uitsending` as the internal CPT key. Do not create a second broadcast CPT.
- Add two ACF-managed taxonomies to `uitsending`:
  - `broadcast_series`
  - `broadcast_type`
- Create the first `broadcast_series` term as `20-twintig`.
- Assign all five existing `uitsending` episodes to `20-twintig`.
- Keep old `uitsending_type` and `uitsending_series` data during this pass. The new templates will read the new taxonomies. Do not delete legacy taxonomies until the new output has been verified.
- Use `video`, `audio`, and `special` as the initial `broadcast_type` terms. Map the current `recording` term to `video` and `podcast` to `audio` when migrating existing episodes.
- Store broadcast state in ACF, not in `broadcast_type`. An episode can therefore move from scheduled to live to replay without changing its format.
- Use the existing Bricks and Automatic.css design system. Do not introduce a parallel style layer.

## Content model

### CPT

The existing `uitsending` CPT remains the source for episodes. Its public rewrite is `/voelgoed-live/{slug}/`.

### Taxonomies

`broadcast_series` is a non-hierarchical ACF taxonomy attached to `uitsending`. Its series archive route is `/voelgoed-live/reeks/{series}/`. The branded page `/20-twintig/` is a custom Bricks page that uses the same series filter.

`broadcast_type` is a non-hierarchical ACF taxonomy attached to `uitsending`. Initial terms are `video`, `audio`, and `special`.

### ACF field group

Create one active field group named `Broadcast: Episode Details`, located on post type `uitsending`.

| Field name | Type | Purpose |
| --- | --- | --- |
| `broadcast_state` | Select | `scheduled`, `live`, `replay`, or `archived` |
| `broadcast_start_at` | Date time picker | Scheduled or live start time |
| `broadcast_end_at` | Date time picker | Optional end time |
| `broadcast_replay_url` | URL | Primary YouTube replay URL |
| `broadcast_live_url` | URL | ReStream or YouTube Live URL used only for live state |
| `broadcast_live_provider` | Select | `restream`, `youtube_live`, or `other` |
| `broadcast_episode_number` | Number | Optional episode number shown in cards and episode pages |
| `broadcast_hosts` | Relationship | One or more `medewerker` posts |
| `broadcast_guests` | Relationship | One or more `medewerker` posts |
| `broadcast_related_content` | Relationship | Posts, `mto-course`, `product`, and `tc_events` posts |

Native post title, excerpt, featured image, and content remain the source for the episode title, summary, artwork, and long-form copy. The new field group does not duplicate those values.

### Embed rules

The single template evaluates the state in this order:

1. If `broadcast_state` is `live` and `broadcast_live_url` is present, render the live embed.
2. If `broadcast_state` is `replay` or `archived` and `broadcast_replay_url` is present, render the YouTube replay embed.
3. If no matching URL exists, render the featured image, episode metadata, and a clear no-embed fallback.

The channel and series latest-replay modules query episodes with a replay URL. They link to the episode page and never use the live provider URL as a replay source.

## Bricks realization

### Approved components reused

- `Archive Hero` (`22f8c9`) for the channel and series introductions.
- `Media Card` (`b1be1f`) as the visual base for the reusable broadcast card.
- `Event Card` (`f60f14`) as the visual base for the next-live module.
- `Article Card` (`ccqfhz`) for editorial related content where appropriate.
- `Featured Content Block` (`b56af6`) for relationship-driven related content.
- `Medewerker Card` (`6b463f`) for hosts and guests.

The `Broadcast Card` is a reusable component derived from the approved `Media Card`. It keeps the existing media layout, ACSS classes, and responsive behavior. Only the data bindings change to use `broadcast_type`, `broadcast_state`, episode fields, and the episode URL. The approved components remain available for their existing use cases.

### Master channel hub

Update the existing `Voelgoed Live Archive` template (ID 614) with this order:

1. Archive Hero.
2. Latest replay module, one result, ordered by date descending, using Broadcast Card.
3. Next-live module, showing a current live episode first, otherwise the next scheduled episode ordered by `broadcast_start_at` ascending, using the Event Card structure.
4. Series library, driven by `broadcast_series` terms and linking to each series route.
5. Relationship-driven related content, using Featured Content Block and the episode relationship data.

The current video and audio loops that query `uitsending_type` will be replaced with the new broadcast queries.

### Branded series hub

Create or update the page with slug `20-twintig` and render it with Bricks. Use the same approved components and section order as the channel hub. Every episode query includes the taxonomy filter `broadcast_series = 20-twintig`.

The generic series archive route `/voelgoed-live/reeks/{series}/` uses the same series library pattern, so adding a future series term does not require rebuilding the channel hub.

### Episode single template

Create a Bricks content template named `Voelgoed Live Single` with a condition for post type `uitsending`.

The element structure is:

1. Conditional media stage for live, replay, or fallback image.
2. Episode title, series, state, date, and episode number.
3. Native episode content and excerpt.
4. Hosts and guests query loops using Medewerker Card.
5. Relationship-driven related content using Featured Content Block.
6. Related episodes query filtered to the current series and excluding the current post.

## Query and relationship behavior

- All episode loops use post type `uitsending`.
- Series filters use `broadcast_series`, never a second series CPT.
- Format filters use `broadcast_type`.
- Live and scheduled modules use `broadcast_state` plus `broadcast_start_at`.
- Related content reads the ACF relationship field and preserves the editor-selected order.
- If Bricks cannot consume the ACF relationship IDs directly in a `post__in` query, add one narrowly scoped FluentSnippets query filter. No custom query code is added unless the native Bricks query cannot express this relationship.
- Query loops have explicit limits and mobile grid settings. No unbounded episode queries are used.

## Migration and safety

1. Create the new taxonomies and field group without deleting existing data.
2. Create the `20-twintig` term.
3. Assign the five existing episodes to `20-twintig`.
4. Map existing recording and podcast values to the new type terms without removing the legacy terms.
5. Reuse and patch template content rather than rebuilding unrelated site templates.
6. Create the branded page only after checking for an existing `20-twintig` slug.
7. Verify the archive, series page, episode singles, embed fallbacks, taxonomy links, and relationship blocks after each mutation.

## Acceptance criteria

- The five existing episodes appear on `/20-twintig/`.
- `/voelgoed-live/` shows the latest replay, next live or scheduled episode, series library, and related content.
- A future `broadcast_series` term can be added without changing the channel hub query.
- A live episode uses only `broadcast_live_url`.
- A replay or archived episode uses only `broadcast_replay_url`.
- The episode single template applies to every `uitsending` post.
- Hosts, guests, and related content come from ACF relationships.
- The approved Bricks components remain the visual base for the new modules.
- No legacy taxonomy or unrelated template is deleted.
