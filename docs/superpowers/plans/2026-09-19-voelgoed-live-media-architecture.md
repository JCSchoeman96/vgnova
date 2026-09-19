# Voelgoed Live media architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing `uitsending` CPT into a reusable Voelgoed Live system with `broadcast_series`, `broadcast_type`, ACF episode metadata, a master channel hub, a branded `20-twintig` hub, reusable cards, live/replay embeds, and relationship-driven content.

**Architecture:** Keep `uitsending` as the internal episode CPT. ACF owns the two new taxonomies and the episode field group. Bricks owns the channel archive, series archive, branded page, single template, and approved component instances. Existing `uitsending_type` and `uitsending_series` data stay in place until the new output is verified.

**Tech Stack:** Novamira CLI, WordPress 7.1.1, ACF Pro 6.8.1, Bricks 2.3.5, Automatic.css, FluentSnippets only if a native Bricks relationship query cannot express the ACF relationship.

---

## Remote object map

This work changes remote WordPress objects through Novamira. It does not add site PHP to the repository.

- Existing archive template: `Voelgoed Live Archive`, ID `614`.
- Existing approved components: `Archive Hero` `22f8c9`, `Media Card` `b1be1f`, `Event Card` `f60f14`, `Article Card` `ccqfhz`, `Featured Content Block` `b56af6`, and `Medewerker Card` `6b463f`.
- New reusable Bricks variants: `Broadcast Card`, `Broadcast Live Card`, and `Broadcast Related Content`, each derived from an approved component.
- New ACF taxonomies: `broadcast_series` and `broadcast_type` on `uitsending`.
- New ACF field group: `Broadcast: Episode Details` on `uitsending`.
- New Bricks template: `Voelgoed Live Single`, condition `postType = uitsending`.
- New generic taxonomy archive template: `Voelgoed Live Series Archive`, condition `archiveType = term` and `archiveTerms = broadcast_series::all`.
- New or updated page with slug `20-twintig`.

## Task 1: Capture the staging baseline

**Files:**
- Read-only remote objects only. No repository file changes.

- [ ] **Step 1: Confirm site access and the clean Bricks/ACF baseline.**

Run:

```bash
novamira --version
novamira sites list --json
novamira --site vgnova doctor --json
novamira --site vgnova run novamira/bricks-check-setup --input '{}' --json
novamira --site vgnova run novamira/acf-check-setup --input '{}' --json
```

Expected: the `vgnova` profile is selected, the doctor status has no authentication or compatibility failure, Bricks reports version `2.3.5`, a valid license, and an empty `issues` array, and ACF Pro reports active.

- [ ] **Step 2: Read the current Voelgoed Live template and approved component definitions.**

Run:

```bash
novamira --site vgnova run novamira/bricks-get-content --input '{"post_id":614,"area":"content"}' --json
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Media","verbose":true}' --json
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Archive","verbose":true}' --json
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Event","verbose":true}' --json
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Featured","verbose":true}' --json
```

Expected: template `614` still contains `uar_hero`, `uar_sec_vid`, and `uar_sec_aud`; the component responses include the approved IDs listed in the remote object map.

- [ ] **Step 3: Record the current episodes and page slug state before any mutation.**

Run these read-only WP-CLI commands through Novamira:

```bash
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["post","list","--post_type=uitsending","--post_status=any","--fields=ID,post_title,post_status,post_name","--format=json"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["post","list","--post_type=page","--name=20-twintig","--fields=ID,post_title,post_status,post_name","--format=json"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","list","broadcast_series","--format=json"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","list","broadcast_type","--format=json"]}' --json
```

Expected: five published `uitsending` posts are present, the page query either returns no `20-twintig` page or identifies the existing page to update, and the two new taxonomies either do not exist yet or show an idempotent existing definition.

Save the five IDs from the first command in a local shell variable named `episode_ids`, and save the first ID in `first_episode_id`. Save any existing page ID in `twintig_page_id`; leave that variable empty when the page query returns no row.

## Task 2: Create the ACF taxonomies

**Files:**
- Remote ACF taxonomy `broadcast_series`.
- Remote ACF taxonomy `broadcast_type`.

- [ ] **Step 1: Create `broadcast_series` with the existing series route.**

Run:

```bash
novamira --site vgnova run novamira/acf-create-taxonomy --input '{"taxonomy":"broadcast_series","title":"Broadcast Series","labels":{"singular_name":"Broadcast Series","name":"Broadcast Series","menu_name":"Broadcast Series"},"object_type":["uitsending"],"hierarchical":false,"public":true,"show_in_rest":true,"show_admin_column":true,"rewrite":{"slug":"voelgoed-live/reeks","with_front":false},"active":true}' --json
```

Expected: the response returns `slug: broadcast_series`, `object_type: ["uitsending"]`, and no collision error. If the taxonomy already exists, the idempotent response must match these values before continuing.

- [ ] **Step 2: Create `broadcast_type` for format, not live state.**

Run:

```bash
novamira --site vgnova run novamira/acf-create-taxonomy --input '{"taxonomy":"broadcast_type","title":"Broadcast Type","labels":{"singular_name":"Broadcast Type","name":"Broadcast Types","menu_name":"Broadcast Types"},"object_type":["uitsending"],"hierarchical":false,"public":true,"show_in_rest":true,"show_admin_column":true,"rewrite":{"slug":"broadcast-type","with_front":false},"active":true}' --json
```

Expected: the response returns `slug: broadcast_type`, `object_type: ["uitsending"]`, and no collision error.

- [ ] **Step 3: Verify both definitions before creating terms.**

Run:

```bash
novamira --site vgnova run novamira/acf-get-taxonomy --input '{"key":"broadcast_series"}' --json
novamira --site vgnova run novamira/acf-get-taxonomy --input '{"key":"broadcast_type"}' --json
```

Expected: both are active ACF-managed taxonomies with `show_in_rest: true` and `uitsending` in `object_type`.

## Task 3: Seed terms and migrate the five existing episodes

**Files:**
- Remote terms in `broadcast_series` and `broadcast_type`.
- New taxonomy relationships on the five existing `uitsending` posts.

- [ ] **Step 1: Create the first series and format terms only when they are absent.**

Use the term list output from Task 1. For any absent term, run the matching command:

```bash
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","create","broadcast_series","20-Twintig","--slug=20-twintig","--porcelain"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","create","broadcast_type","Video","--slug=video","--porcelain"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","create","broadcast_type","Audio","--slug=audio","--porcelain"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","create","broadcast_type","Special","--slug=special","--porcelain"]}' --json
```

Expected: term IDs are returned, and repeated checks show exactly one term for each slug. Do not delete `uitsending_type` or `uitsending_series` terms.

- [ ] **Step 2: Read the five episode IDs and their legacy type terms.**

For every ID in the `episode_ids` shell variable, run this loop:

```bash
for episode_id in $episode_ids; do
  novamira --site vgnova run novamira/run-wp-cli --yes \
    --input "$(jq -nc --arg id "$episode_id" '{args:["post","term","get",$id,"uitsending_type","--format=json"]}')" --json
done
```

Map `recording` to `video`, `podcast` to `audio`, and any other legacy value to `special`. Do not invent a type from the title.

- [ ] **Step 3: Assign all five episodes to the first series and their mapped type.**

For each episode, set the local `mapped_type` variable from the mapping in Step 2, then run:

```bash
novamira --site vgnova run novamira/run-wp-cli --yes \
  --input "$(jq -nc --arg id "$episode_id" '{args:["post","term","set",$id,"broadcast_series","20-twintig"]}')" --json
novamira --site vgnova run novamira/run-wp-cli --yes \
  --input "$(jq -nc --arg id "$episode_id" --arg type "$mapped_type" '{args:["post","term","set",$id,"broadcast_type",$type]}')" --json
```

Expected: every published episode has `20-twintig` and exactly one new format term. The legacy taxonomy assignments remain unchanged.

- [ ] **Step 4: Verify the new relationships and public term links.**

Run:

```bash
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["post","list","--post_type=uitsending","--post_status=publish","--fields=ID,post_title","--format=json"]}' --json
novamira --site vgnova run novamira/run-wp-cli --yes --input '{"args":["term","get","broadcast_series","20-twintig","--format=json"]}' --json
```

Expected: five published posts are listed and the term response contains a public link under the `voelgoed-live/reeks` route.

## Task 4: Create and verify the ACF episode field group

**Files:**
- Remote ACF field group `group_vg_broadcast_episode`.

- [ ] **Step 1: Create the field group with conditional editor fields.**

Run this payload through `novamira/acf-create-fieldgroup`:

```json
{
  "key": "group_vg_broadcast_episode",
  "title": "Broadcast: Episode Details",
  "description": "State, embeds, people, and related content for Voelgoed Live episodes.",
  "fields": [
    {
      "key": "field_vg_broadcast_state",
      "label": "Broadcast: State",
      "name": "broadcast_state",
      "type": "select",
      "instructions": "Use live only while the live provider URL is active. Use replay after the YouTube replay is available.",
      "required": 1,
      "choices": {"scheduled": "Scheduled", "live": "Live", "replay": "Replay", "archived": "Archived"},
      "default_value": "replay",
      "return_format": "value",
      "multiple": 0,
      "allow_null": 0,
      "ui": 1,
      "ajax": 0,
      "placeholder": "Select broadcast state"
    },
    {
      "key": "field_vg_broadcast_start_at",
      "label": "Broadcast: Start",
      "name": "broadcast_start_at",
      "type": "date_time_picker",
      "instructions": "Use the site timezone for scheduled and live broadcasts.",
      "required": 0,
      "display_format": "d/m/Y H:i",
      "return_format": "Y-m-d H:i:s",
      "first_day": 1
    },
    {
      "key": "field_vg_broadcast_end_at",
      "label": "Broadcast: End",
      "name": "broadcast_end_at",
      "type": "date_time_picker",
      "required": 0,
      "display_format": "d/m/Y H:i",
      "return_format": "Y-m-d H:i:s",
      "first_day": 1
    },
    {
      "key": "field_vg_broadcast_replay_url",
      "label": "Broadcast: YouTube Replay URL",
      "name": "broadcast_replay_url",
      "type": "url",
      "instructions": "Paste the YouTube replay URL. This is the primary replay source.",
      "required": 0,
      "conditional_logic": [[{"field": "field_vg_broadcast_state", "operator": "==", "value": "replay"}], [{"field": "field_vg_broadcast_state", "operator": "==", "value": "archived"}]]
    },
    {
      "key": "field_vg_broadcast_live_url",
      "label": "Broadcast: Live URL",
      "name": "broadcast_live_url",
      "type": "url",
      "instructions": "Use only for the active ReStream or YouTube Live embed.",
      "required": 0,
      "conditional_logic": [[{"field": "field_vg_broadcast_state", "operator": "==", "value": "live"}]]
    },
    {
      "key": "field_vg_broadcast_live_provider",
      "label": "Broadcast: Live Provider",
      "name": "broadcast_live_provider",
      "type": "select",
      "required": 0,
      "choices": {"restream": "ReStream", "youtube_live": "YouTube Live", "other": "Other"},
      "return_format": "value",
      "multiple": 0,
      "allow_null": 1,
      "ui": 1,
      "conditional_logic": [[{"field": "field_vg_broadcast_state", "operator": "==", "value": "live"}]]
    },
    {
      "key": "field_vg_broadcast_episode_number",
      "label": "Broadcast: Episode Number",
      "name": "broadcast_episode_number",
      "type": "number",
      "required": 0,
      "min": 1,
      "step": 1
    },
    {
      "key": "field_vg_broadcast_hosts",
      "label": "Broadcast: Hosts",
      "name": "broadcast_hosts",
      "type": "relationship",
      "instructions": "Select hosts from the medewerker profiles.",
      "required": 0,
      "post_type": ["medewerker"],
      "filters": ["search"],
      "return_format": "id",
      "min": 0,
      "max": 0,
      "bidirectional": 0
    },
    {
      "key": "field_vg_broadcast_guests",
      "label": "Broadcast: Guests",
      "name": "broadcast_guests",
      "type": "relationship",
      "instructions": "Select guests from the medewerker profiles.",
      "required": 0,
      "post_type": ["medewerker"],
      "filters": ["search"],
      "return_format": "id",
      "min": 0,
      "max": 0,
      "bidirectional": 0
    },
    {
      "key": "field_vg_broadcast_related_content",
      "label": "Broadcast: Related Content",
      "name": "broadcast_related_content",
      "type": "relationship",
      "instructions": "Select the articles, courses, products, or events to show below the episode.",
      "required": 0,
      "post_type": ["post", "mto-course", "product", "tc_events"],
      "filters": ["search"],
      "return_format": "id",
      "min": 0,
      "max": 0,
      "bidirectional": 0
    }
  ],
  "location": [[{"param": "post_type", "operator": "==", "value": "uitsending"}]],
  "position": "normal",
  "style": "default",
  "label_placement": "top",
  "instruction_placement": "label",
  "active": true,
  "menu_order": 0
}
```

Expected: the response returns `key: group_vg_broadcast_episode`, a field-group ID, and an editable DB source.

- [ ] **Step 2: Verify the saved schema and dynamic tags.**

Run:

```bash
novamira --site vgnova run novamira/acf-get-fieldgroup-schema --input '{"key":"group_vg_broadcast_episode"}' --json
novamira --site vgnova run novamira/bricks-list-dynamic-data --input '{"search":"broadcast_","verbose":true}' --json
```

Expected: all ten field names exist with the declared types, and Bricks lists ACF tokens for `broadcast_state`, `broadcast_start_at`, `broadcast_end_at`, `broadcast_replay_url`, `broadcast_live_url`, `broadcast_live_provider`, `broadcast_episode_number`, `broadcast_hosts`, `broadcast_guests`, and `broadcast_related_content`.

- [ ] **Step 3: Resolve the URL and state tokens against the first episode before saving them into Bricks.**

Use the `first_episode_id` shell variable saved in Task 1:

```bash
novamira --site vgnova run novamira/bricks-resolve-dynamic-data \
  --input "$(jq -nc --argjson id "$first_episode_id" '{tag:"{acf_broadcast_state}",post_id:$id}')" --json
novamira --site vgnova run novamira/bricks-resolve-dynamic-data \
  --input "$(jq -nc --argjson id "$first_episode_id" '{tag:"{acf_broadcast_replay_url}",post_id:$id,context:"link"}')" --json
```

Expected: the state token is registered, and an empty replay URL returns `empty: true` instead of a literal token. Do not save unresolved tags into templates.

## Task 5: Seed only verified metadata on existing episodes

**Files:**
- New ACF values on the five existing `uitsending` posts.

- [ ] **Step 1: Read each episode's native content and legacy metadata.**

For every ID in `episode_ids`, inspect its title, content, excerpt, legacy terms, and existing post meta. Use `acf-read-values` after the field group exists, and read the post content with WP-CLI. The loop below shows the first episode; repeat it for each ID:

```bash
novamira --site vgnova run novamira/acf-read-values \
  --input "$(jq -nc --argjson id "$first_episode_id" '{target:{type:"post",id:$id},only_with_values:true,format_values:false}')" --json
novamira --site vgnova run novamira/run-wp-cli --yes \
  --input "$(jq -nc --arg id "$first_episode_id" '{args:["post","get",$id,"--field=post_content"]}')" --json
```

Expected: any existing YouTube URL is visible in the source content or metadata. Do not invent a URL or infer one from a title.

- [ ] **Step 2: Write only source-backed state and replay URLs.**

For each episode with a verified YouTube URL, save that URL in a local variable named `verified_replay_url`, then call `novamira/acf-write-values` with this generated payload:

```bash
novamira --site vgnova run novamira/acf-write-values \
  --input "$(jq -nc --argjson id "$episode_id" --arg url "$verified_replay_url" \
    '{target:{type:"post",id:$id},values:{broadcast_state:"replay",broadcast_replay_url:$url}}')" --json
```

For an episode with no replay URL, call the same ability with `values:{broadcast_state:"archived"}` so the template uses its image fallback. Do not write a live URL to an archived episode.

- [ ] **Step 3: Verify the new values and preserve the old data.**

Run `acf-read-values` for all five posts with `format_values: false`, then rerun the legacy `wp post term get` commands. Expected: new fields match the source-backed values and legacy taxonomy assignments are unchanged.

## Task 6: Create the reusable Broadcast Card from the approved Media Card

**Files:**
- Remote Bricks component `Broadcast Card`, category `Voelgoed`.

- [ ] **Step 1: Fetch the full approved Media Card tree immediately before cloning it.**

Run:

```bash
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Media Card","verbose":true}' --json
```

Expected: component `b1be1f` is returned with its complete 12-element tree.

- [ ] **Step 2: Create the Broadcast Card as a component copy, then apply only broadcast-specific binding changes.**

Call `novamira/bricks-create-component` with `label: "Broadcast Card"`, `category: "Voelgoed"`, and the exact `elements` array returned for `b1be1f`. In the copied tree, make these exact substitutions before sending the create request:

```text
root data-card-type: media -> broadcast
root data-vg-card: media -> broadcast
category text: {post_terms_uitsending_type} -> {post_terms_broadcast_type}
title text: keep {post_title:12}
excerpt text: keep {post_excerpt:16}
date text: replace {post_date} with {acf_broadcast_start_at:format:d/m/Y}
root link: keep {post_url}
```

Add one `text-basic` child to the body with `tag: "span"`, text `{acf_broadcast_state}`, and the existing approved badge class from the Media Card. Use the existing ACSS variables and classes. Do not change the existing Media Card component, because other media loops use it.

Expected: the response returns a new component ID and `element_count` greater than zero.

- [ ] **Step 3: Verify the component definition and dynamic bindings.**

Run:

```bash
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Broadcast Card","verbose":true}' --json
```

Expected: the new component contains the broadcast taxonomy token, the broadcast state token, and no unresolved old `uitsending_type` token.

- [ ] **Step 4: Create the live-card and related-content variants from approved components.**

Create `Broadcast Live Card` by copying the approved `Event Card` element tree. Keep its existing ACSS classes, image, title, excerpt, and link. Replace only the date binding with `{acf_broadcast_start_at:format:d/m/Y H:i}` and add a state badge using `{acf_broadcast_state}`.

Create `Broadcast Related Content` by copying the approved `Featured Content Block` element tree. Keep its media, title, excerpt, and CTA markup. Change its loop query to `post__in: "{acf_broadcast_related_content}"` with post types `post`, `mto-course`, `product`, and `tc_events`, and set `posts_per_page` to `6`.

Save both returned component IDs as `broadcast_live_card_id` and `broadcast_related_component_id`, then verify them with:

```bash
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Broadcast","verbose":true}' --json
```

Expected: all three variants are present and the two new variants still use the approved component class structure.

## Task 7: Rebuild the existing master channel archive with targeted Bricks edits

**Files:**
- Remote Bricks archive template ID `614`.

- [ ] **Step 1: Remove only the obsolete video and audio sections.**

Run:

```bash
novamira --site vgnova run novamira/bricks-remove-content --input '{"post_id":614,"area":"content","element_ids":["uar_sec_vid","uar_sec_aud"]}' --json
```

Expected: `uar_hero` remains and the response reports the two requested section roots as removed.

- [ ] **Step 2: Insert the editorial-first section shells using existing ACSS classes.**

Insert this root structure after `uar_hero` with `novamira/bricks-insert-content`:

```json
[
  {"id":"bvl_latest_sec","name":"section","parent":0,"settings":{"_cssGlobalClasses":["zqcacy"]}},
  {"id":"bvl_latest_ct","name":"container","parent":"bvl_latest_sec","settings":{"_cssGlobalClasses":["hmlqvn"]}},
  {"id":"bvl_latest_hd","name":"heading","parent":"bvl_latest_ct","settings":{"text":"Nuutste herhaling","tag":"h2","_cssGlobalClasses":["kdpmqi"]}},
  {"id":"bvl_latest_loop","name":"div","parent":"bvl_latest_ct","settings":{"hasLoop":true,"query":{"objectType":"post","post_type":["uitsending"],"post_status":"publish","posts_per_page":1,"orderby":"date","order":"DESC","meta_query":[{"key":"broadcast_state","value":["replay","archived"],"compare":"IN"},{"key":"broadcast_replay_url","compare":"EXISTS"}]},"_display":"grid","_gridTemplateColumns":"minmax(0, 1fr)","_gridGap":"var(--grid-gap)"}},
  {"id":"bvl_live_sec","name":"section","parent":0,"settings":{"_cssGlobalClasses":["zqcacy"]}},
  {"id":"bvl_live_ct","name":"container","parent":"bvl_live_sec","settings":{"_cssGlobalClasses":["hmlqvn"]}},
  {"id":"bvl_live_hd","name":"heading","parent":"bvl_live_ct","settings":{"text":"Volgende uitsending","tag":"h2","_cssGlobalClasses":["kdpmqi"]}},
  {"id":"bvl_live_loop","name":"div","parent":"bvl_live_ct","settings":{"hasLoop":true,"query":{"objectType":"post","post_type":["uitsending"],"post_status":"publish","posts_per_page":1,"meta_key":"broadcast_start_at","orderby":"meta_value","order":"ASC","meta_query":[{"key":"broadcast_state","value":["live","scheduled"],"compare":"IN"}]},"_display":"grid","_gridTemplateColumns":"minmax(0, 1fr)","_gridGap":"var(--grid-gap)"}},
  {"id":"bvl_series_sec","name":"section","parent":0,"settings":{"_cssGlobalClasses":["zqcacy"]}},
  {"id":"bvl_series_ct","name":"container","parent":"bvl_series_sec","settings":{"_cssGlobalClasses":["hmlqvn"]}},
  {"id":"bvl_series_hd","name":"heading","parent":"bvl_series_ct","settings":{"text":"Reekse","tag":"h2","_cssGlobalClasses":["kdpmqi"]}},
  {"id":"bvl_series_loop","name":"div","parent":"bvl_series_ct","settings":{"hasLoop":true,"query":{"objectType":"term","taxonomy":"broadcast_series","hide_empty":true},"_display":"grid","_gridTemplateColumns":"repeat(auto-fit, minmax(240px, 1fr))","_gridGap":"var(--grid-gap)"}},
  {"id":"bvl_related_sec","name":"section","parent":0,"settings":{"_cssGlobalClasses":["zqcacy"]}},
  {"id":"bvl_related_ct","name":"container","parent":"bvl_related_sec","settings":{"_cssGlobalClasses":["hmlqvn"]}},
  {"id":"bvl_related_hd","name":"heading","parent":"bvl_related_ct","settings":{"text":"Verwante inhoud","tag":"h2","_cssGlobalClasses":["kdpmqi"]}},
  {"id":"bvl_related_loop","name":"div","parent":"bvl_related_ct","settings":{"hasLoop":true,"query":{"objectType":"post","post_type":["post","mto-course","product","tc_events"],"posts_per_page":6,"post__in":"{acf_broadcast_related_content}"},"_display":"grid","_gridTemplateColumns":"repeat(auto-fit, minmax(240px, 1fr))","_gridGap":"var(--grid-gap)"}}
]
```

Set the `position` object to `{"after":"uar_hero"}`. If Bricks rejects `objectType: term` or the ACF `post__in` token, keep the section shell and record the exact warning before continuing to Task 10. The next task handles native query verification before adding code.

- [ ] **Step 3: Place the approved component instances inside each loop.**

Run `bricks-apply-component` once for each loop child. The first command uses the `broadcast_card_id` value returned by Task 6:

```bash
novamira --site vgnova run novamira/bricks-apply-component \
  --input "$(jq -nc --arg cid "$broadcast_card_id" '{post_id:614,area:"content",component_id:$cid,position:{append_to:"bvl_latest_loop"}}')" --json
novamira --site vgnova run novamira/bricks-apply-component \
  --input "$(jq -nc --arg cid "$broadcast_live_card_id" '{post_id:614,area:"content",component_id:$cid,position:{append_to:"bvl_live_loop"}}')" --json
novamira --site vgnova run novamira/bricks-apply-component \
  --input "$(jq -nc --arg cid "$broadcast_related_component_id" '{post_id:614,area:"content",component_id:$cid,position:{append_to:"bvl_related_loop"}}')" --json
```

For the series loop, use a small term-card element tree based on the approved `Article Card` structure with term name, term description, and term link dynamic tags. Do not create a second series CPT or use post title data in a term loop.

- [ ] **Step 4: Verify the archive tree and condition.**

Use the `broadcast_card_id` value returned by Task 6 in the first command, then run:

```bash
novamira --site vgnova run novamira/bricks-get-content --input '{"post_id":614,"area":"content"}' --json
novamira --site vgnova run novamira/bricks-list-template-conditions --input '{"template_id":614}' --json
```

Expected: the archive contains the four new sections, uses the approved component IDs, and still has a `postType` condition for `uitsending`.

## Task 8: Create the generic series archive and branded 20-Twintig page

**Files:**
- Remote Bricks template `Voelgoed Live Series Archive`.
- Remote WordPress page with slug `20-twintig`.

- [ ] **Step 1: Create the generic taxonomy archive template.**

Pass the following payload to `novamira/bricks-create-template` and save the returned ID in the local shell variable `series_archive_template_id`:

```json
{"title":"Voelgoed Live Series Archive","type":"archive","status":"publish","template_settings":{"templateConditions":[{"main":"archiveType","archiveType":["term"],"archiveTerms":["broadcast_series::all"]}]}}
```

Pass that payload to `novamira/bricks-create-template`, then populate the returned template ID with the Bricks content tree from the master hub, replacing the fixed heading with `{term_name}` and the episode query with a taxonomy query using the current `broadcast_series` term.

Expected: the new template is published and its condition uses `archiveType: ["term"]` plus `archiveTerms: ["broadcast_series::all"]`.

- [ ] **Step 2: Create or update the page with slug `20-twintig`.**

If Task 1 found no page, pass the following payload to `novamira/create-post` and save its returned ID in `twintig_page_id`:

```json
{"title":"20-Twintig","slug":"20-twintig","status":"publish","post_type":"page","content":""}
```

Pass it to `novamira/create-post`. If Task 1 found an existing page, use its ID with `novamira/update-post` and change only the title or status if needed. Never create a duplicate slug.

- [ ] **Step 3: Populate the page with the editorial-first series layout.**

Use `bricks-set-content` on the page ID with the same approved component structure as the master hub. The episode query must include:

```json
{"taxonomy":"broadcast_series","field":"slug","terms":["20-twintig"]}
```

Keep the `Archive Hero` instance and set its heading to `20-Twintig`. Use the Broadcast Card, `broadcast_live_card_id`, and `broadcast_related_component_id` instances from the master hub.

- [ ] **Step 4: Verify the page and generic route.**

Run:

```bash
novamira --site vgnova run novamira/bricks-get-content --input "$(jq -nc --argjson id "$twintig_page_id" '{post_id:$id,area:"content"}')" --json
novamira --site vgnova run novamira/bricks-list-template-conditions --input "$(jq -nc --argjson id "$series_archive_template_id" '{template_id:$id}')" --json
```

Expected: the page is in Bricks mode, the loop contains the `20-twintig` taxonomy filter, and the generic series template is limited to `broadcast_series` archives.

## Task 9: Create the episode single template

**Files:**
- Remote Bricks content template `Voelgoed Live Single`.

- [ ] **Step 1: Create and condition the content template.**

Pass the following payload to `novamira/bricks-create-template` and save its returned ID in the local shell variable `single_template_id`:

```json
{"title":"Voelgoed Live Single","type":"content","status":"publish","template_settings":{}}
```

Pass it to `novamira/bricks-create-template`, then call:

```json
{"template_id":single_template_id,"conditions":[{"main":"postType","postType":["uitsending"]}]}
```

Expected: the template is published and applies to every `uitsending` post.

- [ ] **Step 2: Add the conditional media stage with the native Bricks Video element.**

Use a replay video element configured as:

```json
{"videoType":"meta","useDynamicData":"{acf_broadcast_replay_url}","iframeTitle":"{post_title}","aspectRatio":"16/9","youtubeControls":true,"youtubeDoNotTrack":true}
```

Use a second live video element with the same configuration but `useDynamicData: "{acf_broadcast_live_url}"`. Apply native Bricks display conditions so the live element renders only when `broadcast_state = live` and the replay element renders only when `broadcast_state` is `replay` or `archived`. Add the approved featured-image fallback when neither source URL is present.

- [ ] **Step 3: Add metadata, people, and related content.**

Use Bricks dynamic tags for:

```text
{post_title}
{post_excerpt}
{post_terms_broadcast_series}
{acf_broadcast_state}
{acf_broadcast_start_at:format:d/m/Y H:i}
{acf_broadcast_episode_number}
```

Add query loops for `broadcast_hosts` and `broadcast_guests` and place the approved `Medewerker Card` inside each. Add the `broadcast_related_component_id` variant to the related content loop. Add a related episode loop filtered to the current `broadcast_series` term and excluding the current post ID.

- [ ] **Step 4: Verify the saved single template tree and all dynamic tokens.**

Run:

```bash
novamira --site vgnova run novamira/bricks-get-content --input "$(jq -nc --argjson id "$single_template_id" '{post_id:$id,area:"content"}')" --json
novamira --site vgnova run novamira/bricks-resolve-dynamic-data --input "$(jq -nc --argjson id "$first_episode_id" '{tag:"{acf_broadcast_live_url}",post_id:$id,context:"link"}')" --json
novamira --site vgnova run novamira/bricks-resolve-dynamic-data --input "$(jq -nc --argjson id "$first_episode_id" '{tag:"{acf_broadcast_replay_url}",post_id:$id,context:"link"}')" --json
```

Expected: no literal `{acf_...}` strings are present in saved text settings, and empty URL fields resolve as empty rather than as literal tokens.

## Task 10: Verify native relationship queries before adding any snippet

**Files:**
- No file unless native Bricks cannot consume the relationship field.

- [ ] **Step 1: Test the relationship field with an editor-selected post.**

Use `acf-write-values` on one existing episode only if the user has selected a real article, course, product, or event for that relationship. Read it back with `acf-read-values` using `format_values: false`, then inspect the rendered query loop with the Bricks builder preview.

Expected: the related content loop preserves the ACF-selected order and shows the approved Featured Content Block.

- [ ] **Step 2: Stop if the native query works.**

Do not add PHP or JavaScript when the Bricks query loop accepts the relationship IDs. Record the successful query settings in the final report.

- [ ] **Step 3: Add a scoped FluentSnippets query only if the native query fails.**

If the native query cannot read the relationship IDs, create one FluentSnippets snippet named `[Broadcast] Related content query`, scoped to `uitsending` single templates. The snippet may only convert `broadcast_related_content` IDs into the `post__in` query for the related-content loop. It must not register a CPT, taxonomy, field, or global query. Test it on one episode, then verify that the selected IDs and order are unchanged.

## Task 11: Run the staging smoke test and final verification

**Files:**
- Remote WordPress objects only. Update the design record only if the final implementation differs from the approved design.

- [ ] **Step 1: Re-run the structural checks.**

Run:

```bash
novamira --site vgnova run novamira/bricks-check-setup --input '{}' --json
novamira --site vgnova run novamira/acf-get-taxonomy --input '{"key":"broadcast_series"}' --json
novamira --site vgnova run novamira/acf-get-taxonomy --input '{"key":"broadcast_type"}' --json
novamira --site vgnova run novamira/acf-get-fieldgroup-schema --input '{"key":"group_vg_broadcast_episode"}' --json
novamira --site vgnova run novamira/bricks-list-templates --input '{"status":"any"}' --json
novamira --site vgnova run novamira/bricks-list-components --input '{"search":"Broadcast Card","verbose":true}' --json
```

Expected: Bricks has no issues, both taxonomies and the field group are active, the new single and series templates are published, and the Broadcast Card is present.

- [ ] **Step 2: Smoke test the public routes in the collaborative browser.**

Use the preview browser to navigate to:

```text
http://localhost:10039/voelgoed-live/
http://localhost:10039/20-twintig/
http://localhost:10039/voelgoed-live/reeks/20-twintig/
```

Verify:

- `/voelgoed-live/` renders the hero, latest replay section, next-live section, series library, and related-content section.
- `/20-twintig/` shows all five assigned episodes.
- The generic series route resolves to the same episode set.
- An episode permalink uses the single template.
- A replay never loads the live provider URL.
- A live-state test record loads only the live provider URL.
- Empty relationship fields do not leave empty headings or broken cards.

- [ ] **Step 3: Confirm the legacy data and unrelated templates are intact.**

Re-run the legacy term checks for all five episodes and compare the template list to the baseline. Expected: `uitsending_type` and `uitsending_series` still exist, no unrelated template changed, and no episode or template was deleted.

- [ ] **Step 4: Report exact remote changes.**

Report the new taxonomy keys, term slugs, field group key, template IDs, page ID, component ID, existing episode IDs assigned to `20-twintig`, and any native-query limitation that required a scoped FluentSnippets snippet.

## Self-review

- The approved spec's CPT decision is covered by Tasks 2 and 3.
- The two requested taxonomies and the `20-twintig` term are covered by Tasks 2 and 3.
- ACF metadata and relationships are covered by Tasks 4 and 5.
- YouTube replay and live-provider embed separation is covered by Tasks 9 and 11.
- The master hub, branded page, generic future-series route, and single template are covered by Tasks 7, 8, and 9.
- Approved components are reused in Tasks 6 through 9.
- Relationship-driven content and the no-code-first rule are covered by Task 10.
- No task deletes legacy taxonomies or unrelated templates.

No placeholders, TODO markers, or unapproved CPTs appear in this plan.
