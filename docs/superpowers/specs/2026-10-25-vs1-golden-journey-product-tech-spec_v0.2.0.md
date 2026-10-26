# VG Nova VS1 — Golden Journey Product + Technical Specification

**Version:** 0.2.0<br>
**Status:** LOCKED BASELINE — PRODUCT + TECHNICAL SCOPE<br>
**Date:** 2026-10-25<br>
**Project:** Voelgoed Nova (`vgnova`)<br>
**Primary focus:** Vertical Slice 1 — Golden Journey / Journey Delivery<br>
**Supersedes:** VS1 product + technical specification v0.1.0 where this document is more specific<br>
**Implementation status:** NOT YET AUTHORIZED — preflight and implementation planning still required

---

## 0. Executive Summary

VS1 exists to prove that the rebuilt Voelgoed platform works as **one connected customer system** rather than as a collection of individually correct WordPress plugins, custom post types, Bricks templates, relationships, checkout flows and account screens.

The production-blocking Golden Journey is:

```text
Home
→ Nuwe Jy / canonical Topic
→ Article
→ Person
→ Course
→ Vriendinne benefit
→ Membership signup
→ Membership activation
→ Automatic return to original Course
→ Tier-specific member price
→ Course purchase
→ Masteriyo access
→ Thank-you
→ Start Course / My Voelgoed
→ One useful transactional email
```

The **Course journey is the only full production-blocking Golden Journey in VS1**.

Event and normal Product journeys remain mandatory regression paths, but they are **fix-blockers-only** unless they expose a shared platform defect or a regression introduced by VS1.

The primary design principle is:

> **Prove the user flow and the authority transitions first. Do not turn VS1 into a full-site redesign programme.**

Frontend design must be production-quality enough to use and test, but exact layout, card counts, carousel decisions and visual polish are secondary to proving that each relationship, state transition, commercial decision and entitlement handoff works correctly.

---

# 1. Why VS1 Exists

The rebuild already contains or plans specialist systems for:

- WordPress editorial content;
- shared Topics;
- canonical People;
- WooCommerce products and orders;
- WooCommerce Subscriptions;
- PMPro memberships;
- Voelgoed Member Perks;
- Masteriyo learning;
- Tickera events and tickets;
- Product Contribution;
- Bricks presentation;
- My Voelgoed;
- FluentCRM and email/lifecycle automation;
- analytics and conversion tracking.

The risk is that each system can be correct in isolation while the real customer journey is still fragmented.

VS1 therefore tests the platform the way a real customer experiences it:

```text
discover
→ understand
→ trust
→ choose
→ join
→ return
→ purchase
→ receive access
→ continue
```

The system must feel like **one Voelgoed platform** even when multiple systems participate underneath.

---

# 2. Canonical Architecture Principles

VS1 inherits and must not contradict the locked Voelgoed canonical architecture.

## 2.1 Native systems retain native authority

| Concern | Canonical authority |
|---|---|
| Editorial posts/pages | WordPress |
| Shared semantic Topics | `content_topic` |
| Person entity | canonical `person` CPT |
| Course content/progress/access | Masteriyo |
| Product/price/cart/checkout/order | WooCommerce |
| Recurring membership billing | WooCommerce Subscriptions |
| Membership/access state | PMPro |
| Member benefits/discounts/claims | Voelgoed Member Perks |
| Events/tickets | Tickera |
| Product↔Person canonical contribution relationships | Product Contribution architecture |
| Presentation | Bricks |
| Voelgoed-specific metadata | ACF where appropriate |
| Unified account projection | My Voelgoed |

## 2.2 One source of truth

Presentation code must never become a second authority.

Bricks must not hard-code:

- membership IDs;
- PMPro level IDs;
- tier pricing;
- perk percentages;
- Course prices;
- entitlement state;
- Product relationships;
- Course relationships;
- return destinations as trusted raw URLs.

## 2.3 `content_topic` is the semantic spine

Nuwe Jy, Geloof, Self, Werk + Geld, Ouerskap, Huwelik and Mensstories belong to the shared canonical Topic hierarchy.

Legacy classification may still exist, but VS1 must not silently make it permanent authority merely because older code consumes it.

## 2.4 Relationship ownership

The object that creates a relationship should own that relationship.

Reverse pages derive their lists.

Examples:

```text
Article → Person
Event → Person
Course → Person
Product Contribution → Person/Product
```

The Person page should query these canonical relationships rather than maintaining copied lists of Articles, Courses, Events and Products.

---

# 3. VS1 Scope Strategy

Three broad strategies were considered.

## 3.1 All commercial journeys as full Golden Journeys

Rejected.

This would make Event, Course, Product, membership, ticketing and learning independent production programmes inside one slice.

## 3.2 Course-only with no surrounding regression coverage

Rejected.

This risks building Course-specific behaviour that breaks ordinary Product or Event commerce.

## 3.3 One full Golden Journey + bounded regression journeys

**CHOSEN AND LOCKED.**

- Course = full production-blocking Golden Journey.
- Event = mandatory regression.
- Ordinary Product = mandatory regression.
- Regression failures expand VS1 only when they expose shared infrastructure problems, VS1 regressions or blockers on existing commercial paths.

---

# 4. Primary Production-Blocking Golden Journey

```text
Home
→ Nuwe Jy / Topic
→ Article
→ Person
→ relevant Course
→ Course sales surface
→ Vriendinne savings proposition
→ quick membership drawer OR full Vriendinne landing page
→ choose Brons / Silwer / Goud
→ choose monthly / yearly
→ Woo Subscription membership checkout
→ payment succeeds
→ PMPro membership becomes active
→ Member Perks resolves active tier benefit
→ automatic validated return to original Course
→ “Welcome — your member discount is now active”
→ exact tier-specific Course price
→ Course purchase
→ Woo paid order
→ Masteriyo enrolment/access
→ Course Thank-you state
→ Start Course
→ My Voelgoed
→ one useful Course purchase/access email
```

This path must use real canonical content and data.

QA fixtures or legacy records are not automatically acceptable merely because they are convenient.

---

# 5. Mandatory Alternate Golden-Journey Branches

## 5.1 Existing Vriendinne member

```text
Article / Person / Topic
→ Course
→ active membership recognised immediately
→ correct tier-specific benefit
→ no join-membership upsell
→ Course purchase
→ access
```

## 5.2 Non-member who declines membership

```text
Course
→ Vriendinne offer
→ No thanks
→ membership intent dismissed
→ normal-price purchase remains available where business rules allow
```

Membership must not become an accidental requirement for a publicly purchasable Course.

## 5.3 Interrupted journey

```text
Course
→ membership intent
→ user leaves browser/site
→ returns later
→ original intent recovered
→ continue OR explicitly dismiss
```

## 5.4 Failed/cancelled membership purchase

Must not:

- claim membership activation;
- grant member pricing;
- mark journey complete.

It should preserve the original commercial intent where appropriate and allow safe recovery.

## 5.5 Member tier change during journey

If the customer changes from one intended tier to another before payment, the **current authoritative membership purchase** wins.

The original Course intent remains, but stale tier or price data must not.

---

# 6. Mandatory Event Regression Paths

Required regressions:

```text
Home → Event → Buy
Article → Event → Buy
Person → Event → Buy
```

Minimum proof:

```text
discovery
→ Event Single
→ canonical Person participation where configured
→ ticket/product selection
→ Woo checkout/order
→ Tickera attendee/ticket outcome through existing architecture
```

Not part of VS1:

- FastCheckin redesign;
- scanner/offline synchronisation;
- complete attendee reconciliation;
- check-in lifecycle hardening;
- event-specific automation programme.

If Event regression exposes a shared checkout, membership, relationship or pricing defect, that defect may be fixed in VS1.

---

# 7. Mandatory Ordinary Product Regression Paths

Required regressions:

```text
Article → Product → Buy
Person → Product → Buy
Topic → Product → Buy
```

Minimum proof:

```text
discovery
→ Product
→ correct public/member offer where applicable
→ cart
→ checkout
→ order
```

Product↔Person behaviour must respect the specialist Product Contribution architecture.

Journey Delivery must not recreate Product Contribution responsibilities.

---

# 8. Universal Transition Contract

Every meaningful arrow in the Golden Journey must prove more than “the link works”.

For each transition:

```text
SOURCE
→ relationship / decision
→ DESTINATION
```

VS1 must prove:

1. **Canonical source**
   - source exists;
   - source type is correct;
   - source is valid for the current user.

2. **Canonical relationship**
   - relationship comes from its approved authority;
   - no copied/legacy field is silently used as new truth.

3. **Destination validity**
   - destination exists;
   - destination is published/public or otherwise legitimately accessible;
   - destination URL is canonical.

4. **User-state correctness**
   - logged-out/logged-in/member state is respected;
   - stale cache/session state does not override authoritative state.

5. **Failure behaviour**
   - missing/unpublished relationship fails gracefully;
   - no dead link;
   - no broken empty component.

6. **Observability**
   - important commercial transitions can be measured.

7. **No duplicate authority**
   - the frontend does not recalculate or reinterpret provider truth.

This contract is more important to VS1 than exact card layout.

---

# 9. Discovery Half of the Golden Journey

The discovery half is:

```text
Home
→ Topic
→ Article
→ Person
→ Course
```

Its purpose is to prove the relationship graph, not merely page navigation.

## 9.1 Home → Topic

Home should make canonical Topics easy to discover.

## 9.2 Topic → Article

A Topic page should surface useful relevant editorial content using canonical Topic relationships.

## 9.3 Article → Person

Article→Person must use the approved canonical relationship implementation.

Expected simple relationship roles include:

```text
primary_author
additional_authors
expert_contributors
reviewed_by
```

A rich participation entity must not be invented unless requirements prove the simple model insufficient.

## 9.4 Person → Course

This is a hard architecture gate.

The Course relationship authority must be proven before implementation.

Do **not** assume Product Contribution is the Person↔Course authority merely because a Course can also have a Woo Product.

Possible existing authorities may include:

- Masteriyo instructor/author state;
- Voelgoed Course relationship metadata;
- an already-approved Course participation/contribution model;
- another canonical mapping.

If none exists:

```text
ARCHITECTURE DECISION REQUIRED
```

Do not solve it with a hidden Bricks query.

---

# 10. Course vs Woo Product Boundary

A Masteriyo Course and its Woo commercial Product are related but not automatically the same domain entity.

Locked rule:

- Masteriyo owns Course content/access/progress.
- Woo owns purchase/order/payment.
- Existing supported Course↔Woo integration must be audited.
- Successful purchase must result in correct Masteriyo entitlement.

Do not collapse the Course and Product models for convenience.

---

# 11. Voelgoed-Vriendinneklub as a Cross-Cutting Layer

Vriendinneklub is becoming a core business model.

Membership must not be treated as a bolt-on banner.

It can influence:

- Course pricing;
- Product pricing;
- Event/ticket perks;
- content access;
- navigation/CTA state;
- My Voelgoed;
- commercial conversion;
- contextual upsells.

The architecture remains:

```text
Woo Subscription purchase
→ PMPro membership/access
→ Member Perks eligibility/benefits
→ frontend presentation
```

PMPro remains membership/access authority.

Woo Subscriptions remains billing authority.

Member Perks remains perk/discount authority.

---

# 12. Tier-Aware Member Benefits

Vriendinne benefits can differ by:

```text
Brons
Silwer
Goud
```

Therefore the frontend may never treat “Vriendinne discount” as one universal number.

For a non-member:

> **Vriendinne save up to R___**

The maximum must be derived from currently valid configured tiers.

For an existing member:

> **Your Silwer Vriendinne price: R___**<br>
> **You save R___.**

The actual tier-specific values are resolved live.

No tier price or discount is trusted from a URL, cookie or Bricks field.

---

# 13. Membership Entry Modes

Two entry modes are locked.

## 13.1 Quick Membership Drawer

For high-intent customers.

It should contain only the information necessary to make a quick membership choice:

- Brons / Silwer / Goud;
- relevant current saving/benefit;
- monthly/yearly choice;
- concise tier distinction;
- choose CTA;
- link to full comparison.

## 13.2 Full Vriendinne Landing Page

For customers who need more information.

It may contain:

- full value proposition;
- tier comparison;
- monthly/yearly options;
- benefit detail;
- FAQs;
- billing/cancellation clarity;
- trust/social proof.

Both entry modes use the same underlying authorities.

They must not maintain separate pricing or membership definitions.

---

# 14. Checkout Escape and Comparison

A customer who enters through the quick drawer must be able to move to the full membership landing/comparison experience before payment.

Example:

> **Not sure which membership is right for you? Compare all Vriendinne options.**

Moving between:

```text
Course
→ drawer
→ landing page
→ checkout
```

must preserve the original Course intent.

Changing tier/billing must update membership intent without destroying the original Course intent.

---

# 15. Contextual Membership Landing Experience

The Vriendinne landing page remains one universal landing page.

When entered from a Course/Product/Event, it may show one optional context panel:

```text
YOU WERE LOOKING AT

[Course]
Become a Vriendin and save on this Course.

Brons  ...
Silwer ...
Goud   ...
```

The normal general membership page continues below.

This is **bounded contextual personalisation**, not a generic recommendation engine.

---

# 16. Persistent Journey Intent

VS1 introduces a deliberately narrow Journey Intent capability.

Its job is:

> Remember the commercial object the customer was trying to reach when membership interrupted the original journey.

It is not:

- browsing history;
- multi-item recommendation memory;
- AI personalisation;
- marketing surveillance.

---

# 17. Intent Creation

Do not create persistent commercial intent merely because someone viewed a Course/Product/Event.

Create or refresh it after a meaningful commercial action, for example:

- customer clicks Vriendinne savings CTA;
- opens quick membership flow;
- chooses Learn More about Vriendinne from a commercial object;
- begins the membership conversion specifically from that object.

---

# 18. One Active Intent

Locked:

```text
ONE active commercial intent per customer in VS1
```

A newer explicit commercial intent supersedes the older one.

No multi-intent history is part of VS1.

---

# 19. Intent Persistence

## 19.1 Anonymous visitor

Use minimal first-party persistent storage.

Preferred direction:

- first-party cookie;
- secure in production;
- `SameSite=Lax`;
- minimal payload;
- tamper-resistant/signed representation where appropriate;
- `HttpOnly` where compatible with the chosen UX.

## 19.2 Authenticated customer

Promote valid anonymous intent to authenticated user-level persistence.

This allows recovery after:

- browser restart;
- logout/login;
- checkout interruption;
- later visit;
- potentially another device after authentication.

The authenticated representation becomes durable context.

---

# 20. Intent Data

Persist only minimal context such as:

```text
target_type
target_id
source_type
source_id
created_at
expires_at
status
```

Do not persist as authority:

- price;
- discount;
- saving;
- tier entitlement;
- access;
- membership eligibility;
- arbitrary redirect URL.

All commercial facts are recalculated from live authorities.

---

# 21. Intent Redirect Safety

Never blindly trust a raw `return_to` browser URL.

The server must:

1. load the stored target;
2. confirm supported object type;
3. confirm publication/access state;
4. regenerate its canonical URL;
5. redirect only to that validated destination.

---

# 22. Intent Lifecycle

Locked lifecycle:

```text
ACTIVE
SUPERSEDED
DISMISSED
COMPLETED
INVALID
EXPIRED
```

## ACTIVE
Valid unresolved intent.

## SUPERSEDED
A newer explicit intent replaced it.

## DISMISSED
Customer explicitly chose **No thanks** / **Don't remind me about this**.

## COMPLETED
The intended commercial outcome was successfully consumed/completed.

## INVALID
Target no longer exists or is no longer a valid destination.

## EXPIRED
Intent exceeded its configured lifetime.

Initial default:

```text
30 days
```

This must be configurable, not hard-coded into presentation.

---

# 23. No Thanks vs Close

These are different actions.

## Close (`X`)

- hides/suppresses the prompt temporarily;
- intent can remain ACTIVE.

## No Thanks

- explicitly marks the intent DISMISSED;
- the system must not repeatedly resurrect that commercial reminder.

This is mandatory to keep persistence from becoming irritating.

---

# 24. Membership Activation Return

After successful membership purchase:

```text
Woo Subscription order
→ linked membership integration
→ active PMPro Vriendinne state
→ Member Perks resolves current benefit
→ validated return to original object
```

The return experience should show:

> **Welcome — your member discount is now active.**

Where useful:

> Your Goud price is now R___ and you save R___.

Values are resolved live.

---

# 25. Membership Activation Guard

Never show a success state merely because:

- checkout returned;
- a query parameter says success;
- a cart emptied;
- the browser says it selected Goud.

The success state must be based on authoritative membership/benefit resolution.

If payment succeeded but membership activation is temporarily unresolved, the system must fail safely and truthfully.

---

# 26. Contextual Vriendinne Savings Nudge

VS1 includes a small, non-obstructive membership nudge on appropriate commercial surfaces.

## Show when

- visitor is not an eligible active Vriendinne member;
- current object has a real member benefit;
- benefit can be resolved authoritatively;
- page is appropriate.

## Suppress when

- user is already eligible;
- user owns/has access to target;
- membership checkout is active;
- current page is membership signup;
- no valid benefit exists;
- user explicitly dismissed relevant intent;
- prompt would obstruct critical UI.

## Desktop
Small bottom-right style prompt.

## Mobile
Bottom sheet/card positioned safely.

It must never cover:

- Add to Cart;
- checkout actions;
- forms;
- navigation;
- consent controls;
- important content.

---

# 27. Transactional Communication Principle

Locked:

> **One transaction = one useful customer email.**

Do not send separate emails simply because WooCommerce, PMPro, Masteriyo and FluentCRM each participated.

---

# 28. Membership Transaction Email

Membership purchase should produce one useful customer email.

Primary purpose:

```text
welcome
→ benefits active
→ continue original journey
```

Suggested hierarchy:

```text
Welcome to Voelgoed-Vriendinneklub

Your [tier] benefits are active.

You were looking at:
[Course]

[CONTINUE TO COURSE]

[MY VOELGOED]

Membership/order details
```

It must still satisfy required transactional/receipt information.

---

# 29. Course Purchase Email

Course purchase should result in one useful Course-ready email.

Hierarchy:

```text
Your Course is ready

[START COURSE]   ← primary

[MY VOELGOED]    ← secondary

Order/payment details
```

Avoid redundant:

- generic Woo order confirmation;
- separate Masteriyo enrolment email;
- immediate duplicate FluentCRM welcome email;

when one well-designed message can cover the transaction.

---

# 30. Behavioural Email Is Deferred

Not VS1:

- not-started reminders;
- progress nudges;
- completion campaigns;
- re-engagement;
- recommendations;
- next-best-offer automation.

Those belong to a later lifecycle-marketing slice.

---

# 31. Email Emitter Audit

Before suppressing or changing anything, audit all possible emitters:

- WooCommerce;
- WooCommerce Subscriptions;
- PMPro;
- PMPro/Woo integration;
- Masteriyo;
- Member Perks;
- FluentCRM;
- FluentSnippets/custom code;
- relevant payment integration.

Required output:

```text
business event
→ current emitters
→ desired single customer message
→ which emitters remain/suppress
```

No sender is disabled until its responsibility is understood.

---

# 32. Course Thank-You Experience

After successful Course purchase and confirmed access:

```text
Purchase successful

Your Course is ready.

[START COURSE]

View all your courses, orders and benefits:
[MY VOELGOED]

Order summary
```

Locked hierarchy:

1. Start Course = primary.
2. My Voelgoed = secondary.
3. Order details = supporting.

Do not force the customer through My Voelgoed merely to access the Course just purchased.

---

# 33. Course Access Failure Safety

Do not show:

> Your Course is ready

unless access is actually confirmed.

If Woo payment/order succeeds but Masteriyo access fails:

- preserve truthful order state;
- avoid broken Start Course CTA;
- expose a safe fallback;
- log/report the integration failure;
- allow reconciliation.

Exact recovery is determined from current Course/Woo/Masteriyo architecture during preflight.

---

# 34. My Voelgoed Role

My Voelgoed is a projection, not an authority.

VS1 must prove that the user can reach:

- current membership state;
- learning/Course access;
- relevant order history where intended.

It must not create shadow copies of:

- Woo orders;
- PMPro membership truth;
- Masteriyo progress/access.

---

# 35. Header Membership CTA

General direction:

```text
Non-member
→ Vriendinne CTA

Active member
→ My Voelgoed / member-relevant CTA
```

The exact visual/header treatment is not a VS1 architecture blocker.

---

# 36. Featured Content Manager (FCM) Preflight

Before creating any new global CTA/featured-offer system, run:

## VS1-PREFLIGHT-FCM

Audit:

- ACF fields/options;
- current resolver;
- supported target types;
- Bricks consumers;
- frontend surfaces;
- empty behaviour;
- unpublished target behaviour;
- current runtime values;
- tests;
- analytics/tracking;
- precedence/overrides;
- legacy `content_category` vs canonical `content_topic`.

Required ruling:

```text
KEEP
ADAPT
MIGRATE
RETIRE
```

Current hypothesis may be **KEEP + ADAPT**, but that is not authorized until the audit proves it.

---

# 37. Featured Offer vs Membership Nudge

These are distinct concerns.

## FCM answers

> What object should be featured/promoted here?

## Vriendinne nudge answers

> What membership advantage applies to the object this user is considering?

Do not merge them into one overloaded field/system.

---

# 38. Homepage — Accepted UX Guardrails

The homepage design discussion produced useful guardrails, but these are **secondary to the Golden Journey flow**.

## 38.1 Core purpose

Home is a **decision page**, not a catalogue.

It should answer:

> **What do you need help with?**

rather than:

> Here is everything Voelgoed does.

## 38.2 Primary discovery

Dominant model:

```text
What do you need help with?
```

Canonical root Topics are the main discovery system.

## 38.3 Search

Search should be prominent near the top.

Before implementation, audit the current search capability.

VS1 must not accidentally expand into a full AI/unified-search programme unless a blocker is proven.

## 38.4 Secondary discovery

A smaller:

```text
What do you want to do?
```

layer can expose:

- Read;
- Learn;
- Attend;
- Shop.

Do not automatically add every content format as a homepage section.

## 38.5 Hero

The hero stays clean and search/topic-led.

It may contain **one optional agnostic featured slot** for a current important destination.

Approved target categories can include things such as:

- Event;
- Course;
- Product;
- membership campaign;
- approved landing campaign.

The slot is optional.

If no valid target is configured, the hero remains clean/full-width.

The target should provide canonical content automatically rather than requiring duplicate title/image/link data where possible.

## 38.6 Topic cards

All seven canonical root Topics should be visible in a structured desktop grid.

No carousel/swiper for core Topic navigation.

Mobile may stack/reflow.

A Frames/GetFrames Romeo-style component is an accepted starting point for implementation, but the specific visual component is not an architectural requirement.

## 38.7 Homepage anti-pattern

Do not create separate giant carousels/sections for:

- Events;
- Courses;
- Products;
- Podcasts;
- Videos;
- People;
- Voelgoed Live;
- Vriendinne;
- each Topic;
- every campaign.

A new Voelgoed capability does not automatically earn a new Home section.

---

# 39. Topic / Pillar Page — Accepted UX Guardrails

Topic page layout is not the core VS1 objective, but the following direction is accepted.

## 39.1 Child topics

Child topics should be accessible but visually secondary.

Desktop:
- compact inline/wrapped navigation.

Mobile:
- user-controlled horizontal scroll rail.

No autoplay/infinite motion for core navigation.

## 39.2 Start Here / Featured

One strong primary recommendation.

Selection model:

```text
manual editor override
→ governed topic-specific feature
→ deterministic automatic fallback
```

Invalid/unpublished manual choice must fall back safely.

## 39.3 Latest & Useful

User-facing section remains simple:

> **Latest & Useful**

Under the hood it may combine:

- rolling popularity;
- recency;
- editorial priority;
- exclusions.

Do not expose a cluttered collection of separate “Newest”, “Most Read”, “Trending”, etc. unless later evidence warrants it.

## 39.4 Ranking concept

Preferred initial direction:

```text
rolling popularity (~30 days)
+ recency
+ optional editorial boost/exclusion
```

Avoid lifetime-view dominance.

Before building a new counter/ranking store, audit what VG Nova already captures.

Bricks consumes ranked results; ranking logic does not live in the template.

## 39.5 Mentors

Top-level Topics may prominently show their **1–2 designated Mentors**.

Mentors remain canonical People.

Do not create a separate Mentor entity.

Audit the existing Mentor field/category before adding anything.

Do not pad the layout with lower-priority People merely to fill a grid.

Person pages remain the richer destination for related Articles, Courses, Events and Products.

## 39.6 Explore Topic

A mixed feed is preferred over a rigid fixed Course/Event/Product slot model.

The feed may contain relevant Courses, Products, Events or other approved commercial/helpful destinations.

Editors may later be able to pin/boost/exclude, but exact ranking and visual layout are not VS1 production blockers.

---

# 40. Important Refocus Rule

The homepage/topic decisions above are **design guardrails**, not permission to keep expanding VS1 into full information-design work.

The primary objective remains:

```text
prove the user flows
prove the canonical relationships
prove the state transitions
prove the commercial handoffs
prove entitlement
prove recovery
```

Exact layout is secondary.

If a design question does not materially affect the user flow, authority boundary, accessibility, mobile usability, conversion or correctness, it should be deferred to implementation/design iteration rather than blocking architecture.

---

# 41. Analytics and Ranking

VS1 must be observable.

Before creating new tracking, audit existing:

- GA4;
- GTM;
- dataLayer;
- first-party counters;
- analytics storage;
- event naming.

At minimum, the final Golden Journey should allow measurement of:

- featured-offer exposure/click;
- membership savings CTA engagement;
- drawer vs landing entry;
- membership checkout start;
- membership purchase;
- successful return to original intent;
- Course purchase;
- Start Course click;
- My Voelgoed click.

Do not place sensitive data into analytics.

---

# 42. SEO Requirements

Production-blocking journey surfaces must be checked for:

- title;
- meta description;
- canonical;
- robots;
- OpenGraph;
- schema where appropriate;
- breadcrumbs;
- heading hierarchy;
- indexability.

Topic pages should be meaningful pillar pages, not thin archives.

SEO correctness is required, but VS1 is not a complete site-wide SEO redesign.

---

# 43. Responsive and Accessibility Requirements

Golden Journey is not complete until tested on desktop and mobile.

At minimum verify:

- Home;
- Topic;
- Article;
- Person;
- Course;
- membership nudge;
- drawer/modal;
- Vriendinne landing;
- checkout;
- return banner;
- Thank-you;
- My Voelgoed.

Check:

- keyboard navigation;
- focus visibility;
- modal/drawer focus management;
- Escape/close behaviour;
- focus restoration;
- labels;
- error messages;
- semantics;
- contrast;
- tap targets;
- overflow;
- sticky/floating collisions.

---

# 44. Cache and State Correctness

Critical state transition:

```text
non-member
→ active Vriendinne member
```

Immediately after activation:

- member benefit resolves correctly;
- stale non-member pricing disappears;
- join-membership nudge disappears;
- Course offer reflects current tier;
- cached fragments/pages do not expose stale state.

The real runtime cache/session layers must be tested.

---

# 45. Security Requirements

At minimum:

- client-supplied price is never authoritative;
- client-supplied discount is never authoritative;
- client-supplied tier is never authoritative;
- return destinations are server validated;
- object type is validated;
- unpublished/private targets fail safely;
- intent storage is minimal;
- no secrets in browser state, logs or analytics;
- existing nonce/capability/payment protections remain intact.

---

# 46. Privacy and Customer Control

Persistent intent must remain proportionate.

Locked rules:

- one active intent;
- meaningful action required to create it;
- finite expiry;
- explicit No Thanks;
- dismissal respected;
- no large browsing-history profile;
- no behavioural recommender in VS1.

---

# 47. Representative Real Data Gate

Before final implementation acceptance, select a small real dataset.

Likely minimum:

```text
1 canonical Nuwe Jy Topic path
3–5 real Articles
1 real canonical Person / Mentor
1 real paid Course
1 linked Woo commercial Product where required
1 Vriendinne benefit configuration across tiers
1 representative Event
1 ordinary Product
```

Data must have acceptable provenance.

Do not use legacy/QA objects as production truth merely because they already exist.

---

# 48. Editor/Admin Journey

VS1 should prove that authorised editors can maintain the journey through canonical systems.

At minimum they should be able to:

- edit the selected Article;
- assign canonical Topic;
- maintain Article→Person through approved authority;
- maintain Course→Person through approved authority;
- configure governed featured content through the approved FCM mechanism;
- configure Vriendinne benefit through Member Perks.

They should not have to:

- edit template PHP;
- duplicate relationship lists;
- maintain copied prices;
- manually edit reverse Person lists.

---

# 49. Product Contribution Coordination

Product Contribution remains its own specialist workstream.

Before touching Person→Product regression surfaces:

- verify current merged specialist state;
- use the latest canonical repository/service contract;
- do not duplicate unfinished Tasks;
- do not silently resurrect legacy Product/Medewerker authority.

Journey Delivery adapts around the specialist architecture.

---

# 50. Preflight Gates

No implementation plan is considered complete until these gates are addressed.

## Gate A — Fresh Runtime Reality

Verify current:

- `origin/main`;
- runtime;
- active plugins;
- ACF groups;
- Bricks templates/components;
- relevant data;
- current relationship counts.

Historical handoffs are evidence, not current runtime truth.

## Gate B — FCM / Global CTA

Produce KEEP / ADAPT / MIGRATE / RETIRE ruling.

## Gate C — Article → Person

Prove canonical implementation and runtime behaviour.

## Gate D — Person → Course

Prove canonical relationship authority.

Hard gate.

## Gate E — Membership / Discount

Prove:

```text
Woo Subscription
→ PMPro active membership
→ Member Perks tier benefit
→ Woo commercial price
```

## Gate F — Course Purchase / Enrolment

Prove:

```text
Woo Course purchase
→ supported Masteriyo access
```

## Gate G — Email Emitters

Inventory and deduplicate transactional communication.

## Gate H — Representative Real Data

Freeze exact Topic/Article/Person/Course/Event/Product records.

## Gate I — Analytics / Ranking

Audit current page-view and engagement infrastructure before creating new ranking storage.

## Gate J — Existing Mentor Classification

Audit current Mentor field/category and map it into canonical Person architecture without duplication.

## Gate K — Search

Audit current search implementation before treating unified search as a new build.

---

# 51. Implementation Tooling Direction

The implementation environment may use both:

## Novamira

Preferred for:

- WordPress runtime inspection;
- ACF/data architecture;
- content entities;
- relationship/data verification;
- runtime/admin work where supported.

## Bricks MCP + Bricks Skills

Preferred for:

- Bricks templates;
- frontend components;
- dynamic bindings;
- responsive layout;
- visual implementation;
- Bricks-specific inspection/changes.

The choice of tool must follow the authority of the layer.

Do not force frontend work through a data tool or data architecture through a page-builder tool.

All source-controlled custom code remains subject to the repository's normal code-review and verification standards.

---

# 52. Implementation Ownership Principle

Prefer, in order:

```text
native provider capability
→ existing Platform Core/service
→ existing Member Perks capability
→ existing reusable Bricks component
→ narrow new implementation
```

Do not hide business logic inside:

- Bricks conditions;
- CSS;
- arbitrary snippets;
- query parameters;
- duplicated ACF fields.

---

# 53. Likely Narrow Technical Shape — Journey Intent

A small abstraction may be appropriate, naming to follow current conventions.

Conceptually:

```text
JourneyIntentRepository
JourneyIntentService
```

Possible responsibilities:

## Repository
- read authenticated intent;
- persist authenticated intent;
- update lifecycle;
- clear intent.

## Service
- create/replace intent;
- validate target;
- promote anonymous intent;
- dismiss;
- complete;
- expire;
- resolve safe canonical destination.

Cookie handling may be a separate boundary/helper.

Do not generalise beyond VS1 requirements.

---

# 54. Failure Behaviour

Every Golden Journey integration must fail gracefully.

Examples:

## Featured target unavailable
Hide/degrade safely.

## No member benefit
Do not invent a saving.

## Intent target invalid
Mark INVALID and fall back safely.

## Membership unresolved
Do not claim member pricing is active.

## Course access unresolved
Do not show broken Start Course CTA.

## Member Perks unavailable
Fail closed on benefit execution while preserving safe normal commerce where business rules permit.

---

# 55. Observability and Evidence Standard

For each important transition, final QA must prove both:

1. customer-facing outcome;
2. authoritative backend state.

Examples:

```text
Membership checkout appears successful
+
PMPro membership actually active
```

```text
Member price shown
+
Member Perks/Woo authoritative price agrees
```

```text
Course says ready
+
Masteriyo access exists
```

Screenshots alone are insufficient.

Database state alone is insufficient.

---

# 56. Required Final QA Personas

At minimum:

```text
Anonymous non-member → joins → buys Course
Non-member → declines membership → buys normally
Existing Brons member
Existing Silwer member
Existing Goud member
Interrupted/returning customer
Customer who dismisses intent
```

Where practical also test:

- logged-out vs logged-in;
- desktop vs mobile;
- anonymous cached state;
- authenticated state;
- builder/editor rendering where relevant.

---

# 57. Regression Expansion Rule

A regression failure may expand VS1 when:

- shared checkout is broken;
- shared membership pricing is broken;
- shared Topic/Person/discovery infrastructure is broken;
- VS1 introduced the failure;
- the failure blocks an existing core commercial path.

It does **not** automatically expand VS1 when it reveals a large independent specialist programme.

---

# 58. Explicitly Deferred

Not VS1:

- FastCheckin hardening;
- scanner/offline sync;
- complete event attendee lifecycle redesign;
- behavioural FluentCRM;
- progress reminders;
- completion marketing;
- generic recommendation engine;
- multi-intent customer history;
- full legacy `medewerker` retirement;
- complete Product visual redesign;
- complete Home redesign beyond what the flow needs;
- complete Topic-page visual system;
- complete content-topic corpus migration;
- full Voelgoed Live redesign;
- generic site-wide personalisation;
- unrelated cleanup/security work unless it blocks VS1;
- a complex AI search/recommendation system.

---

# 59. Proposed Delivery Sequence

Subject to preflight:

```text
VS1-000
Fresh targeted current-reality audit

VS1-PREFLIGHT-FCM
Featured Content Manager / global CTA ruling

VS1-001
Representative real-data contract

VS1-002
Discovery relationship chain
Home → Topic → Article → Person → Course

VS1-003
Vriendinne benefit + membership entry
Course → nudge/drawer/landing

VS1-004
Persistent Journey Intent
membership interruption + recovery + dismissal

VS1-005
Membership activation + validated return
Woo Subscription → PMPro → Member Perks → Course

VS1-006
Tier-specific Course commerce
member/non-member pricing + checkout

VS1-007
Course fulfilment
Woo → Masteriyo → Thank-you → My Voelgoed → email

VS1-008
Event + ordinary Product regression paths

VS1-009
SEO / analytics / accessibility / responsive / cache / security hardening

VS1-010
Final end-to-end evidence + milestone exit
```

The implementation plan may split these into smaller PRs.

Scope may not silently expand merely because PR boundaries change.

---

# 60. Architecture Escalation Rule

Implementation must stop and classify:

```text
ARCHITECTURE DECISION REQUIRED
```

when approved authorities cannot represent a required relationship or lifecycle.

Especially:

- Person↔Course;
- Article↔Person;
- FCM topic authority;
- Course→Woo→Masteriyo entitlement;
- membership tier→perk→price;
- journey-intent ownership.

Do not hide architectural inventions inside frontend queries or snippets.

---

# 61. VS1 Production-Blocking Acceptance Criteria

VS1 cannot be called complete until:

1. real representative data is approved;
2. Home→Topic works;
3. Topic→Article works;
4. Article→Person works canonically;
5. Person→Course authority is proven and works;
6. Course sales surface works;
7. non-member benefit presentation is accurate;
8. quick membership entry works;
9. full membership comparison route works;
10. original Course intent persists;
11. only one intent is active;
12. No Thanks dismisses it;
13. Woo membership purchase works;
14. PMPro activation is authoritative;
15. Member Perks recognises the tier;
16. automatic validated Course return works;
17. welcome/discount-active message is truthful;
18. exact tier-specific Course price is correct;
19. non-member normal purchase remains possible where intended;
20. Course checkout succeeds;
21. Masteriyo access is confirmed;
22. Thank-you exposes Start Course + My Voelgoed;
23. My Voelgoed shows the expected state;
24. transactional email is useful and deduplicated;
25. analytics can observe the key funnel;
26. SEO is valid;
27. mobile and desktop pass;
28. accessibility is acceptable;
29. cache/session state transitions are correct;
30. security requirements pass;
31. Event regressions pass or are correctly classified;
32. Product regressions pass or are correctly classified;
33. no legacy field/system was accidentally promoted into canonical authority.

---

# 62. Definition of Done

VS1 is done when a real customer can:

```text
discover useful Voelgoed content
→ understand who can help
→ discover a relevant Course
→ understand the Vriendinne value
→ join membership if desired
→ return to the original Course
→ receive the correct current tier benefit
→ buy
→ receive Course access
→ continue immediately
```

without:

- broken relationships;
- stale membership state;
- misleading prices;
- dead-end navigation;
- duplicate communication;
- unclear next actions;
- hidden authority duplication.

And a real editor can maintain the underlying content using canonical systems without developer-only intervention.

---

# 63. Final Scope Doctrine

VS1 is **not**:

> Make every page perfect and finish all of Voelgoed.

VS1 is:

> **Make one commercially meaningful Voelgoed journey excellent, prove every authority transition in that journey, and ensure the surrounding Event/Product paths still work.**

Course is the production-blocking Golden Journey.

Event and Product are bounded regressions.

Vriendinne is a cross-cutting commercial layer.

`content_topic` is the semantic spine.

Native systems remain authoritative.

Persistent intent remembers what the customer wanted while respecting explicit dismissal.

Useful communication beats more communication.

Frontend design serves the flow; it does not replace the flow.

**Evidence beats inference.**

---

# 64. Version 0.2.0 Change Summary

Compared with the accepted v0.1.0 baseline, v0.2.0 additionally locks:

- Home as a calm topic-first decision surface rather than a catalogue;
- prominent search as a desired discovery affordance, subject to search preflight;
- one optional agnostic Home featured slot;
- all canonical root Topic cards visible rather than hidden in a carousel;
- Topic child navigation as accessible secondary navigation;
- manual + automatic Topic featured-content behaviour;
- “Latest & Useful” as a hybrid ranked section concept;
- rolling popularity/recency/editorial influence as the ranking direction;
- 1–2 designated Mentors per top-level Topic without a separate Mentor entity;
- mixed Topic exploration rather than rigid fixed Event/Course/Product slots;
- Novamira + Bricks MCP/Bricks Skills as complementary implementation tooling;
- the explicit refocus that detailed page layout is not the VS1 objective;
- a universal transition contract for proving each Golden Journey relationship/state change.

This document is now the locked product + technical baseline for the next phase: **fresh preflight evidence and implementation planning**.

## Provenance

This approved specification was added on a branch based on freshly verified `origin/main` commit `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`.
