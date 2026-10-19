# Post-Task 6C current state

**Date:** 2026-10-19
**Branch:** `feat/event-consumer-cutover-6c`
**Base:** `ba1b48c7726d9ce477a52eb32bb354a5259edd0c`
**Runtime:** Voelgoed at `http://localhost:10039/`

## Event participant presentation

Event Single 774 consumes only canonical Event Participation data through Platform Core's `EventParticipationBricks` adapter and `EventParticipationRepository::participations_for_event()`. Repository order is preserved. The marked participant section hides on the frontend when the repository returns no IDs and remains editable in Bricks builder requests.

The new Event Participant Card uses an outer `event_participation` item, resolves `event_participation_person` to `person`, and outputs the Person image, title, optional professional title, and canonical WordPress permalink. It does not render an Event Role badge. The prior local participant modal was retired.

The static introduction `3c88cc` and product loop `cxqfiw` are unchanged. Template 774 remains published with its `tc_events` condition. The full runtime and subtree hashes are in [the Task 6C manifest](EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json).

## Bricks component storage

Bricks 2.3.5 stores the new component as option `bricks_components`, entry ID `194bba`, title `Event Participant Card`. WordPress post ID and post status are not applicable. The final component record hash is `481c60b1bd2310b96cae05834aa49202777788192927021d218c5d1445cadc7f` and the final option hash is `9d11fa323621913572bbb6b03534eaec3ec721c537f9e759ea028ef5481a0293`.

## Data and legacy consumers

Persistent canonical counts are:

| Record or relationship | Count |
|---|---:|
| Person | 0 |
| Event Participation | 0 |
| Event Role terms | 4 |
| Event Role relationships | 0 |
| Person `person_type` relationships | 0 |
| Person `content_topic` relationships | 0 |
| `content_topic` terms / roots | 55 / 7 |

Event 763 retains `event_medewerkers=[753,757]`; its canonical participation count is zero and its participant section is absent. No legacy Event, Person, or product relationship was migrated.

The `event_medewerkers` ACF field stays active. Medewerker Single 772, Medewerker Archive 773, shared card `6b463f`, Product Template 124, Voelgoed Live Template 1127, product relationship fields, and `9-acf-product-copy.php` remain unchanged.

The helper `event_medewerkers` mode has zero active Bricks callers after the fresh 27-template and 57-component audit. It remains in the active helper because the available Novamira connection has no FluentSnippets code-edit ability and the UI requires a WordPress admin login. Retirement is deferred. The other five helper modes remain unchanged.

## QA and cleanup

Event 151 was the temporary QA Event. Five QA runs used two Persons and two participations at a time. All 10 temporary Persons and 10 participations were hard-deleted. The final run, after query-constraint hardening, showed Person B before Person A for display orders 10 and 20, optional professional-title behavior, canonical Person URLs with HTTP 200, no legacy modal, and no `/medewerkers/` participant links. The empty section was absent before QA and after cleanup.

Protected profiles, attachments, Event 763, Products 767/768, Templates 772/773/124/1127, shared component `6b463f`, the Event ACF field, topic and role vocabularies, the helper source, and product-copy automation matched their pre-task snapshots. Template 774 and the new component option entry are the intended persistent runtime changes. Platform Core source and runtime adapter hashes match.

Independent review found that marked-query input could retain unrelated Bricks constraints. The adapter now returns only its five canonical query arguments. A regression test covers inherited legacy filters, and another covers frontend Event context without a Bricks preview ID. The deployed adapter hash is `1adee948ecb9a53f762e7f9de62bf721e1bc46c9f8d4e3f093f9fd26a9c87c63`; the follow-up runtime QA, cleanup, and final count checks passed.

The implementation is on [PR #23](https://github.com/JCSchoeman96/vgnova/pull/23), open against `main` and unmerged for independent review. See [Task 6C implementation evidence](EVENT_CONSUMER_6C_IMPLEMENTATION.md).
