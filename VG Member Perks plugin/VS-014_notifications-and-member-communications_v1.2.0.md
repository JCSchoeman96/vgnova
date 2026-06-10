# FEATURE PLANNING PACK: VS-014 — Notifications and Member Communications v1.2.0

## 0. Patch Summary

This document supersedes the older `VS-014_notifications-and-member-communications.md`.

The old version was directionally good, especially around conservative sending, queues, logs, dry-run mode, and avoiding uncontrolled bulk email. This hardened version patches the architecture to align with the corrected Voelgoed Member Perks slice order and the dual-programme membership model.

Key changes retained from v1.1.0:

```text
1. Corrected dependencies:
   VS-007 = Claims and Redemption Ledger
   VS-008 = WooCommerce Product Discounts

2. Renamed the notification table:
   wp_vg_perk_notifications
   → wp_vgmp_perk_notifications

3. Replaced single `membership_group` notification logic with programme-aware recipient and audit fields:
   - programme_key
   - level_key
   - qualified_path
   - audience_scope
   - eligibility_hash
   - membership_profile_hash

4. Added dual-programme digest behaviour:
   one email per user per period by default,
   grouped internally by Vriendinneklub / Voelgoednetwerk / Shared.

5. Added explicit no-duplicate rule for dual members:
   a member who belongs to both programmes must not receive duplicate emails for the same perk.

6. Updated claim-confirmation triggering to listen to VS-007 redemption ledger events, not Woo-specific events.

7. Added staging/dry-run hard gates for bulk member messaging.

8. Added cache/privacy rules aligned with VS-013:
   notification previews, current-user digests, and member-specific REST/admin data must not be publicly cached.

9. Added legacy-data warnings for old table names and old `membership_group`-only records.

10. Kept this slice strictly non-execution:
    no pricing changes,
    no claim-rule changes,
    no course/ticket access changes,
    no CRM replacement.
```

### v1.2.0 Programme and Level Flexibility Pass Summary

This version applies the programme/level flexibility pass.

```text
11. Reframes Vriendinneklub and Voelgoednetwerk as launch/default programmes, not a closed forever-list.
12. Aligns notification audience and digest logic with PROGRAMME_AND_LEVEL_MAPPING_MODEL.md.
13. Requires recipient resolution to use configured programme/level mappings, not hardcoded PMPro IDs, Woo subscription products, or fixed Goud/Silwer/Brons assumptions.
14. Adds programme_mapping_hash to notification queue/audit context and idempotency strategy.
15. Requires canonical configured programme_key and level_key values to be stored in notification records; aliases are normalized before queueing and never stored as canonical truth.
16. Adds fail-closed behaviour for unmapped PMPro levels, unknown/disabled programmes, unknown levels, and unavailable programme mapping.
17. Adds future-level, future-programme, alias-normalization, and mapping-drift tests.
```

---

## 1. Objective

Build the optional notifications and member communications layer for the **Voelgoed Member Perks** plugin.

This vertical slice must allow the system to notify members and administrators about perk-related events in a controlled, auditable, non-spammy, and programme-aware way.

The core goal:

```text
New perks can be communicated.
Expiring perks can be highlighted.
Claim confirmations can be queued after successful redemptions.
Admins can be warned about misconfigured or expiring perks.
Members can receive relevant perk summaries based on their MembershipProfile.
Dual-programme members can receive one combined digest grouped by programme.
No mass messaging happens without explicit configuration, preview, dry-run safety, and queue safeguards.
```

This slice must not replace:

```text
Brevo
FluentCRM
Mailchimp
MailPoet
main newsletter tooling
WooCommerce transactional emails
PMPro membership emails
broader CRM strategy
```

This slice is a plugin-owned notification layer for **perk-related operational communications only**.

---

## 2. Current Baseline Assumptions

This slice assumes the previous slices exist or have been implemented in the corrected order:

```text
VS-000 — Current Site Discovery and Conflict Audit
VS-001 — Plugin Foundation, CPT and Taxonomies
VS-002 — ACF Fields and Options Page
VS-003 — PMPro Membership Profile Resolver
VS-004 — Runtime Status Automation
VS-005 — Bricks/Etch Programme Hub Display
VS-006 — Target and Audience Indexing Table
VS-007 — Claims and Redemption Ledger
VS-008 — WooCommerce Product Discounts
VS-009 — Masteriyo Course Perks
VS-010 — Tickera Ticket Perks
VS-011 — Reports and Dashboards
VS-012 — Admin UX Polish and Bulk Management
VS-013 — Performance and Cache Hardening
```

This slice must be tolerant if VS-009 or VS-010 are not implemented yet.

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected key systems:

```text
vg_perk CPT
ProgrammeRegistry
ProgrammeMappingRepository
ProgrammeDefinition
ProgrammeLevelDefinition
MembershipProfile resolver
CurrentUserPerksQuery
runtime status automation
target and audience index
redemption ledger
reports
performance/cache layer
admin tools
programme_mapping_hash / mapping version context
```

Expected programme model:

```text
Vriendinneklub and Voelgoednetwerk are the launch/default programmes.
Future programmes and levels must be read from configured programme/level mappings.
Notification code must not treat the launch programmes as the only possible programmes forever.
```

Expected custom tables:

```text
{$wpdb->prefix}vgmp_perk_targets
{$wpdb->prefix}vgmp_perk_redemptions
```

New table introduced by this slice:

```text
{$wpdb->prefix}vgmp_perk_notifications
```

If core slices VS-001 to VS-008 are missing, stop and report what is missing before implementing this slice.

---

## 3. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Admin notification settings
Claim confirmation emails
New perks digest emails
Expiring perk digest/reminder emails
Admin alert emails
Simple email templates
Notification queue table
Notification logs
Rate limiting
Opt-out / user preference handling
Dry-run previews
Manual send approval
WP-Cron/server-cron scheduling hooks
Optional future adapter placeholders
```

This slice must not implement:

```text
Uncontrolled mass mail blasting
Replacing Brevo / FluentCRM / Mailchimp / MailPoet
Changing WooCommerce transactional email templates globally
Changing PMPro membership emails globally
Sending to users who should not receive communication
Sending member-specific perk data through public cache
SMS
WhatsApp
Push notifications
Advanced marketing automation journeys
Full CRM segmentation engine
New perk eligibility logic
New pricing logic
New claim logic
New course enrolment logic
New ticket generation logic
```

This slice must be conservative.

Programme and level definitions must remain configuration-driven. Notification execution must not hardcode:

```text
PMPro level IDs
PMPro group IDs
Woo membership product IDs
Woo variation IDs
current billing terms
fixed Goud/Silwer/Brons assumptions
fixed Brons/Silwer/Goud assumptions
a closed two-programme-only model
```

---

## 4. Communication Principles

Follow these rules exactly:

```text
1. Default all bulk/member notifications to disabled until configured.
2. Do not send mass emails automatically on plugin activation.
3. Do not send a member notification just because a perk is saved unless the admin explicitly opted into a specific campaign/send.
4. Prefer scheduled digests over one email per perk.
5. Claim confirmations may be enabled because they are transactional-style, but they must still be queued and idempotent.
6. Expiry reminders must respect current eligibility and claim state.
7. Admin alerts are separate from member alerts.
8. Use queue/log records so sends are traceable.
9. Never expose other members’ perks, claims, orders, courses, tickets, or membership details.
10. Do not bypass unsubscribe/privacy obligations.
11. Never send duplicate emails to dual-programme users for the same notification period/perk.
12. Do not treat a single `membership_group` as the recipient authority.
13. Resolve recipients through the MembershipProfile model and programme mappings.
14. Only enabled configured programme/level mappings may qualify a member for member notifications.
15. Unknown, disabled, or unmapped programme/level references must fail closed for member sends.
16. Aliases may be accepted for admin/config compatibility, but queued notification records must store canonical configured keys only.
17. Future programmes or levels must be added through ProgrammeDefinition / ProgrammeLevelDefinition configuration, not notification code edits.
```

---

## 5. Required Deliverables

Add the following plugin files:

```text
includes/
├── Notifications/
│   ├── NotificationServiceProvider.php
│   ├── NotificationSettings.php
│   ├── NotificationQueue.php
│   ├── NotificationRepository.php
│   ├── NotificationDispatcher.php
│   ├── NotificationScheduler.php
│   ├── NotificationLogger.php
│   ├── RecipientResolver.php
│   ├── RecipientBatch.php
│   ├── NotificationAudience.php
│   ├── NotificationMappingContext.php
│   ├── NotificationAudienceNormalizer.php
│   ├── EmailRenderer.php
│   ├── EmailTemplateRegistry.php
│   ├── EmailTemplate.php
│   ├── EmailTransport.php
│   ├── WpMailTransport.php
│   ├── DigestBuilder.php
│   ├── DigestProgrammeGrouper.php
│   ├── ExpiryReminderBuilder.php
│   ├── ClaimConfirmationBuilder.php
│   └── AdminAlertBuilder.php
│
├── Database/
│   └── NotificationTable.php
│
├── Admin/
│   ├── NotificationSettingsPage.php
│   ├── NotificationPreviewPage.php
│   ├── NotificationLogsPage.php
│   └── NotificationAdminActions.php
│
└── Tools/
    ├── NotificationQueueTool.php
    └── NotificationDryRunTool.php
```

Update existing files:

```text
includes/Plugin.php
includes/Activator.php
includes/Database/Schema.php
includes/Performance/HubCachePolicy.php
README.md
```

Optional assets:

```text
assets/css/admin-notifications.css
assets/js/admin-notifications.js
```

Keep admin assets lightweight and only load on notification-related admin pages.

---

## 6. Database Table

Create a custom notification queue/log table:

```text
{$wpdb->prefix}vgmp_perk_notifications
```

Example:

```text
wp_vgmp_perk_notifications
```

Use `dbDelta()` through the existing schema manager.

Legacy warning:

```text
If {$wpdb->prefix}vg_perk_notifications exists from an older prototype, do not silently merge or delete it.
Report it in the legacy-data/admin tools area.
A future migration may be written if real data exists.
```

---

## 7. Required Table Schema

Recommended SQL shape:

```sql
CREATE TABLE {$wpdb->prefix}vgmp_perk_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_type VARCHAR(80) NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'email',

    recipient_user_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL DEFAULT '',
    recipient_name VARCHAR(190) NOT NULL DEFAULT '',

    subject VARCHAR(255) NOT NULL DEFAULT '',
    body_hash VARCHAR(64) NOT NULL DEFAULT '',

    status VARCHAR(40) NOT NULL DEFAULT 'queued',

    related_perk_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    related_redemption_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    related_order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

    programme_key VARCHAR(80) NOT NULL DEFAULT '',
    level_key VARCHAR(80) NOT NULL DEFAULT '',
    qualified_path VARCHAR(120) NOT NULL DEFAULT '',
    audience_scope VARCHAR(80) NOT NULL DEFAULT '',
    eligibility_hash VARCHAR(64) NOT NULL DEFAULT '',
    membership_profile_hash VARCHAR(64) NOT NULL DEFAULT '',
    programme_mapping_hash VARCHAR(64) NOT NULL DEFAULT '',

    idempotency_key VARCHAR(191) NOT NULL DEFAULT '',

    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    failed_at DATETIME NULL,

    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY  (id),
    UNIQUE KEY unique_idempotency (idempotency_key),
    KEY status_scheduled (status, scheduled_at),
    KEY recipient_user (recipient_user_id),
    KEY recipient_email (recipient_email),
    KEY notification_type (notification_type),
    KEY related_perk (related_perk_id),
    KEY related_redemption (related_redemption_id),
    KEY programme_level (programme_key, level_key),
    KEY audience_scope (audience_scope),
    KEY profile_hash (membership_profile_hash),
    KEY programme_mapping_hash (programme_mapping_hash),
    KEY created_at (created_at)
) DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate};
```

Important notes:

```text
1. Do not store full email bodies in this table by default.
2. Store `body_hash` for audit/debug comparison.
3. Store enough metadata to explain why a notification was queued.
4. Store `programme_mapping_hash` so admins can see which configured programme/level mapping snapshot was used.
5. Use empty string / 0 defaults for indexed identity/context fields rather than nullable values where practical.
6. `idempotency_key` must be non-empty for queue records that may be duplicated by scheduler/retry flows.
```

Reason for not storing full bodies by default:

```text
Email bodies may contain personal details.
Logs should remain operational and lightweight.
Full-body storage is a privacy decision and must be explicitly enabled later if ever needed.
```

---

## 8. Schema Versioning

Update the schema manager.

If VS-013 schema version is currently:

```text
2 or higher
```

increment to the next integer.

Rules:

```text
1. Create vgmp_perk_notifications if missing.
2. Do not delete target/redemption data.
3. Do not drop tables on deactivation.
4. Safe upgrades only.
5. If an old vg_perk_notifications table exists, report it and do not destructively migrate without explicit approval.
```

---

## 9. Notification Types

Supported notification types in VS-014:

```text
claim_confirmation
new_perks_digest
expiring_perks_digest
admin_expiring_perks_alert
admin_misconfiguration_alert
admin_failed_notification_alert
```

Optional future types:

```text
course_claim_confirmation
ticket_claim_confirmation
weekly_member_summary
monthly_member_summary
partner_deal_announcement
programme_specific_digest
```

Do not implement all future types unless needed.

Course and ticket claim confirmations can be represented by the generic `claim_confirmation` type with `source`/related context in the redemption record.

---

## 10. Notification Statuses

Allowed statuses:

```text
draft
queued
scheduled
sending
sent
failed
cancelled
skipped
```

Meaning:

```text
draft     = created but not queued for sending
queued    = ready to send
scheduled = queued for a future time
sending   = currently being processed
sent      = successfully sent
failed    = send attempt failed
cancelled = admin/system cancelled before sending
skipped   = intentionally not sent due to eligibility, opt-out, duplicate prevention, or dry-run
```

Do not delete notification records by default.

---

## 10A. Programme and Level Notification Boundary

Notification eligibility must consume normalized programme/level data from the same mapping model used by VS-003, VS-005, VS-006, VS-007, VS-008, VS-009, VS-010, VS-011, VS-012, and VS-013.

Reference model:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

Correct notification authority path:

```text
ProgrammeMappingRepository
↓
MembershipProfile resolver
↓
CurrentUserPerksQuery / PerkAudienceEvaluator
↓
RecipientResolver
↓
NotificationAudienceNormalizer
↓
NotificationQueue
```

Incorrect notification authority path:

```text
Hardcoded PMPro level IDs
Hardcoded Woo subscription product IDs
Hardcoded Vriendinne/Netwerk-only recipient branches
Hardcoded Goud/Silwer/Brons checks
Flat membership_group recipient filters
```

Rules:

```text
1. Notification code may ship launch defaults for Vriendinneklub and Voelgoednetwerk labels, but must not assume no future programme can exist.
2. Recipient resolution must use enabled configured programme and level mappings.
3. Alias values may be normalized before queueing, but queue rows must store canonical configured programme_key and level_key values.
4. Unmapped PMPro levels must not receive member benefit notifications.
5. Unknown or disabled programme/level references must not produce member notifications.
6. If programme mapping is unavailable, member bulk/digest notification building must fail closed.
7. Admin alerts may report mapping failures, but must not silently send to guessed audiences.
8. Notification idempotency must include the mapping/profile context when recipient content depends on membership mapping.
```

Required derived/audit context:

```text
programme_key
level_key
qualified_path
audience_scope
eligibility_hash
membership_profile_hash
programme_mapping_hash
```

Historical/legacy rows with old programme/level values must be displayed as:

```text
Unknown / Legacy
```

Do not silently remap historical notification rows after mappings change.


## 11. Notification Settings

Create:

```php
Voelgoed\MemberPerks\Notifications\NotificationSettings
```

Admin page:

```text
Member Perks > Settings > Notifications
```

Settings:

```text
Enable claim confirmation emails
Enable new perks digest
New perks digest frequency
New perks digest day/time
Enable expiring perks digest
Expiring reminder window days
Enable admin alerts
Admin alert recipient emails
From name
From email
Reply-to email
Email footer text
Enable dry-run mode
Notification environment mode
Max notifications per batch
Max attempts per notification
Respect WordPress user opt-out/meta
Respect plugin opt-out meta
Default digest grouping mode
```

Defaults:

```text
Claim confirmations: disabled until configured
New perks digest: disabled
Expiring perks digest: disabled
Admin alerts: enabled only if admin recipients are safely configured, otherwise disabled
Dry-run mode: enabled on staging/development
Max notifications per batch: 50
Max attempts: 3
Expiring reminder window: 7 days
Default digest grouping mode: one_email_grouped_by_programme
```

Do not send member bulk emails until the admin explicitly enables them.

---

## 12. Environment Safety

The staging site must not accidentally email real members.

Add a staging safety mode.

Recommended setting:

```text
notification_environment_mode
```

Choices:

```text
production
staging
development
```

Default detection may use:

```text
WP_ENVIRONMENT_TYPE
site URL containing staging/dev indicators
manual admin setting
```

Behaviour in staging/development:

```text
Do not send to real member emails by default.
Route to admin test email or dry-run log.
Show clear admin warning.
```

If the site environment cannot be detected:

```text
Default to conservative dry-run mode until configured.
```

Hard gate:

```text
Bulk member notifications must not send to real recipients while dry-run mode is active.
```

---

## 13. Email Transport

Create interfaces/classes:

```php
Voelgoed\MemberPerks\Notifications\EmailTransport
Voelgoed\MemberPerks\Notifications\WpMailTransport
```

Default transport:

```text
wp_mail()
```

Do not hard-depend on:

```text
Brevo
FluentSMTP
Mailchimp
MailPoet
FluentCRM
```

If FluentSMTP or SMTP plugins are installed, `wp_mail()` will usually route through them.

This slice may include placeholders for future adapters, but do not build full third-party integrations unless explicitly approved.

---

## 14. Recipient Resolver

Create:

```php
Voelgoed\MemberPerks\Notifications\RecipientResolver
```

Responsibilities:

```text
1. Resolve eligible users for a perk or digest.
2. Resolve users by programme and programme level.
3. Resolve dual-programme users once, not once per programme.
4. Resolve future configured programmes and levels from ProgrammeMappingRepository.
5. Normalize configured programme and level aliases before evaluating recipients.
6. Exclude users who are not active/eligible at send time.
7. Exclude users who opted out where applicable.
8. Exclude users whose PMPro level is active but unmapped.
9. Avoid duplicates.
10. Batch large recipient lists.
11. Return recipient context with MembershipProfile hash and programme_mapping_hash.
```

Important:

```text
Do not query all users blindly on every page load.
Do not send to inactive/cancelled members.
Do not send to users without usable email addresses.
Do not treat flat membership_group as the authority.
Do not send one Vriendinne email plus one Netwerk email by default to a dual member.
Do not hardcode PMPro level IDs or Woo subscription product IDs.
Do not guess an audience when programme mapping is unavailable.
```

For VS-014, if PMPro active-member querying is not clear:

```text
Inspect PMPro tables/functions on staging.
Use safest available active-level query.
Document the actual method.
```

Do not guess PMPro internals.

---

## 15. Notification Audience Model

Create:

```php
Voelgoed\MemberPerks\Notifications\NotificationAudience
```

Purpose:

```text
Represent the audience context used to build or queue a notification.
```

Expected structure:

```php
[
    "recipient_user_id"        => 123,
    "recipient_email"          => "member@example.com",
    "membership_profile_hash"  => "abc123...",
    "programme_mapping_hash"  => "map123...",
    "programmes"              => [
        "vriendinneklub" => [
            "level_key" => "goud",
            "qualified_path" => "vriendinneklub:goud",
        ],
        "voelgoednetwerk" => [
            "level_key" => "silwer",
            "qualified_path" => "voelgoednetwerk:silwer",
        ],
    ],
    "audience_scope"           => "dual_programme",
]
```

Allowed launch `audience_scope` values:

```text
no_membership
vriendinneklub_only
voelgoednetwerk_only
dual_programme
programme_specific
admin
system
unknown_legacy
```

Future configured programmes may use normalized scopes generated from ProgrammeDefinition records, for example:

```text
programme:{programme_key}
programme_level:{programme_key}:{level_key}
multi_programme
```

Member-facing copy must use configured labels, not raw internal keys.

Notifications to members must use the actual recipient’s current MembershipProfile at send/build time.

---

## 16. Eligibility for Member Notifications

Member notifications must only go to users who are eligible for the relevant perk at send time.

For each perk recipient:

```text
1. User must still exist.
2. User must have an active eligible MembershipProfile.
3. If the message is programme-specific, user must currently qualify for that programme/level.
4. Perk must still be active if sending active/new perk notice.
5. Perk must not be archived, paused, or misconfigured.
6. User must not have opted out if opt-out is supported.
7. User must not already have a duplicate queued/sent notification for the same idempotency scope.
8. User's active PMPro level must resolve through an enabled ProgrammeLevelDefinition.
9. Notification builder must store the current programme_mapping_hash.
10. If mapping is stale/unavailable, member sending must stop or skip with a safe reason.
```

For expiring perk reminders:

```text
Only send to eligible users.
Prefer not to send if user has already claimed and keep-after-claim means no action is needed.
```

---

## 17. Digest Builder

Create:

```php
Voelgoed\MemberPerks\Notifications\DigestBuilder
Voelgoed\MemberPerks\Notifications\DigestProgrammeGrouper
```

Purpose:

```text
Build new-perks digest emails.
```

Digest types:

```text
weekly
monthly
manual
```

Digest content should be grouped:

```text
Voelgoed Vriendinneklub perks
Voelgoednetwerk perks
Shared / general member perks
Courses
Products
Events
Replays / downloads
Partner deals
Expiring soon
```

Each recipient should only see perks they are eligible for.

Default MVP approach:

```text
Build one per-user digest from CurrentUserPerksQuery using the recipient's current MembershipProfile and programme_mapping_hash.
Group the result by configured programme labels for display.
Deduplicate shared perks.
Queue one email per user per period.
Store canonical programme_key / level_key / programme_mapping_hash in the queue record.
```

Do not do this by default:

```text
One digest per programme per user.
```

Why:

```text
Dual-programme users would receive duplicate emails and may see duplicate shared perks.
```

Performance note:

```text
Per-user digest is safest but may be slower.
Use batching and queue.
Do not build all user bodies during admin page load.
```

---

## 18. Digest Grouping Rules for Dual Members

A user with:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
```

should receive one digest by default:

```text
Subject: Your latest Voelgoed member perks

Sections:
1. Voelgoed Vriendinneklub
2. Voelgoednetwerk
3. Shared / available to both
4. Expiring soon
```

Rules:

```text
1. The same perk must not appear twice.
2. If a perk is eligible through both programmes, show it once under Shared / available to both unless admin configured programme-specific placement.
3. If the digest contains only one programme’s content, show only that programme section.
4. Do not include raw programme keys in member-facing copy unless intentionally styled.
5. Do not expose internal eligibility hashes.
6. Future configured programmes must be grouped from ProgrammeDefinition labels and sort_order.
7. Disabled or unknown programmes must not appear as positive benefit sections.
```

---

## 19. Expiry Reminder Builder

Create:

```php
Voelgoed\MemberPerks\Notifications\ExpiryReminderBuilder
```

Purpose:

```text
Build emails for perks expiring soon.
```

Admin setting:

```text
Expiring reminder window days
```

Default:

```text
7 days
```

Reminder rules:

```text
Perk runtime status = active
End date is within configured window
Perk is not paused/archived/misconfigured
Recipient is eligible at build/send time
Respect claim state where practical
Use one email per user per period where possible
Group expiring perks by programme
Deduplicate shared perks
```

Do not send daily repeats for the same perk/user unless configured.

Use idempotency keys.

---

## 20. Claim Confirmation Builder

Create:

```php
Voelgoed\MemberPerks\Notifications\ClaimConfirmationBuilder
```

Purpose:

```text
Build transactional-style confirmation emails after a successful claim/redemption.
```

Trigger source:

```text
VS-007 redemption ledger events
```

Listen to internal actions created by VS-007, for example:

```php
do_action( "vgmp_redemption_claimed", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_completed", $redemption_id, $redemption_data );
```

Supported sources from redemption data may include:

```text
generic_claim
woo_order
course_claim
ticket_claim
admin
```

Message should include:

```text
Perk title
Claim date
What the user received
Next step link if available
Account/order/course/event link if available
Support contact/footer
```

Do not include:

```text
raw internal IDs unless needed for support/admin-only context
other users’ data
admin notes
debug data
eligibility hashes
```

Preferred behaviour:

```text
Queue confirmation.
Dispatcher sends shortly after.
Do not send email directly during checkout/claim critical path.
```

---

## 21. Admin Alert Builder

Create:

```php
Voelgoed\MemberPerks\Notifications\AdminAlertBuilder
```

Admin alerts:

```text
Perks expiring soon
Misconfigured active perks
Failed notification batch
High number of failed claims if tracked
Target index stale if detected
Legacy table detected
Staging/dry-run mismatch warning
```

Admin alerts can be sent to:

```text
Site admin email
Configured alert recipients
```

Do not send admin alerts to members.

Admin alerts may include internal details suitable for administrators, but still escape output and avoid unnecessary PII.

---

## 22. Email Templates

Create:

```php
Voelgoed\MemberPerks\Notifications\EmailTemplateRegistry
Voelgoed\MemberPerks\Notifications\EmailTemplate
Voelgoed\MemberPerks\Notifications\EmailRenderer
```

Template keys:

```text
claim_confirmation
new_perks_digest
expiring_perks_digest
admin_expiring_perks_alert
admin_misconfiguration_alert
admin_failed_notification_alert
```

Templates should support:

```text
Subject
Preheader
HTML body
Plain text body
Footer
```

Use simple, robust email HTML.

Do not create complex branded templates in VS-014 unless required.

Allow admin to customise basic subject/body in settings later, but MVP can use safe defaults.

Template safety:

```text
Do not execute PHP in templates.
Do not allow arbitrary shortcodes unless explicitly approved and sanitized.
Do not insert raw ACF/admin notes into member emails.
```

---

## 23. Queueing Model

Do not send bulk emails directly during admin page load.

Create:

```php
Voelgoed\MemberPerks\Notifications\NotificationQueue
```

Flow:

```text
1. Builder creates queue records.
2. Queue records carry programme/profile/audience context.
3. Dispatcher processes due records in batches.
4. Scheduler triggers dispatcher through WP-Cron/server cron.
5. Logs status and failures.
```

Batch size:

```text
Default 50
Configurable
```

Retries:

```text
Max attempts 3
```

Failed handling:

```text
Mark failed.
Store last_error.
Do not retry forever.
```

---

## 24. Notification Scheduler

Create:

```php
Voelgoed\MemberPerks\Notifications\NotificationScheduler
```

Scheduled tasks:

```text
Process notification queue
Build weekly/monthly new perks digest if enabled
Build expiring perks digest if enabled
Build admin alerts if enabled
Clean old notification logs if retention policy exists
```

Use WordPress cron hooks, but document that real server cron should call `wp-cron.php`.

Do not rely on page traffic for timely sending where important.

Recommended cron hooks:

```text
vgmp_process_notification_queue
vgmp_build_new_perks_digest
vgmp_build_expiring_perks_digest
vgmp_build_admin_notification_alerts
```

---

## 25. Idempotency

Notifications must be idempotent.

Examples:

### Claim confirmation

```text
claim_confirmation:redemption:{redemption_id}:user:{user_id}:map:{programme_mapping_hash}
```

### Weekly digest

```text
new_perks_digest:user:{user_id}:period:{YYYY-WW}:profile:{membership_profile_hash}:map:{programme_mapping_hash}
```

### Monthly digest

```text
new_perks_digest:user:{user_id}:period:{YYYY-MM}:profile:{membership_profile_hash}:map:{programme_mapping_hash}
```

### Expiring perk digest

```text
expiring_perks_digest:user:{user_id}:window:{YYYY-MM-DD}:profile:{membership_profile_hash}:map:{programme_mapping_hash}
```

### Expiring single-perk reminder if ever used

```text
expiring_perk:user:{user_id}:perk:{perk_id}:window:{YYYY-MM-DD}:profile:{membership_profile_hash}:map:{programme_mapping_hash}
```

### Admin alert

```text
admin_alert:{type}:period:{YYYY-MM-DD}
```

If the same idempotency key exists:

```text
Do not create duplicate queued notifications.
```

Important:

```text
Do not create one idempotency key per programme for a normal dual-programme digest.
The default digest is per user per period, grouped by programme.
```

---

## 26. Notification Logs Page

Create:

```php
Voelgoed\MemberPerks\Admin\NotificationLogsPage
```

Location:

```text
Member Perks > Notifications > Logs
```

Columns:

```text
Date created
Scheduled date
Sent date
Type
Recipient
Status
Programme
Level
Audience scope
Programme mapping hash
Related perk
Related redemption
Attempts
Last error
```

Filters:

```text
Status
Type
Date range
Recipient search
Programme
Level
Audience scope
Programme mapping hash
Related perk
```

Capability:

```text
manage_vg_perks
```

Do not expose logs to normal members.

---

## 27. Notification Preview Page

Create:

```php
Voelgoed\MemberPerks\Admin\NotificationPreviewPage
```

Purpose:

```text
Allow admins to preview digest/claim/admin emails before enabling sending.
```

Preview modes:

```text
Preview as specific user
Preview for programme
Preview for programme level
Preview dual-programme user
Preview specific perk
Preview admin alert
```

Security:

```text
Admin-only.
No actual sending unless Send Test is clicked.
```

Send Test:

```text
Send only to current admin or configured test email.
Use dry-run/test routing in staging.
```

Do not allow “send to all” from the preview screen without a separate confirmation flow.

Preview must not expose private data to unauthorised users.

---

## 28. Manual Digest Send

Optional but useful.

Create admin action:

```text
Build manual digest now
```

Rules:

```text
Show recipient count first.
Show estimated batch count.
Require confirmation.
Respect staging/dry-run mode.
Queue emails, do not send immediately in page load.
Use idempotency unless admin chooses a unique manual campaign key.
Show warning for dual-programme handling.
```

For VS-014, it is acceptable to implement dry-run/manual preview only and defer full manual bulk send if risk is high.

---

## 29. Opt-Out and Preferences

At minimum, respect any existing WordPress/user meta preference if found or create plugin-specific meta.

Recommended user meta:

```text
vgmp_email_perks_opt_out
```

Rules:

```text
Transactional-style claim confirmations may still be allowed if configured.
Marketing-style digests must respect opt-out.
Admin alerts ignore member opt-out because they go to admins.
Opt-out checks must happen at queue-build time and preferably again at send time.
```

If the site has a newsletter/CRM unsubscribe system:

```text
Do not attempt to sync automatically unless integration is explicitly built.
Document limitation.
```

---

## 30. Data Privacy

Notifications involve personal data.

Rules:

```text
Only store what is needed.
Do not store full email body by default.
Do not expose recipient logs publicly.
Do not send other users’ perk/claim data.
Do not include internal admin notes in member emails.
Use current eligibility at send time.
Do not send to inactive members unless the notification type explicitly requires it.
Do not public-cache notification previews or current-user notification endpoints.
```

If POPIA/GDPR processes exist, align with them.

Do not invent legal claims. Preserve conservative data handling.

---

## 31. Email Content Standards

Member emails should be clear, warm, and practical.

Avoid overpromising.

Example claim confirmation subject:

```text
Your member perk has been confirmed
```

Example digest subject:

```text
Your latest Voelgoed member perks
```

Example expiring subject:

```text
Some of your member perks are ending soon
```

For dual-programme members, wording should be natural:

```text
Here are the latest benefits available through your Voelgoed memberships.
```

Do not imply that a perk is available to all members if it is level-specific.

Do not show raw terms such as:

```text
vriendinneklub:goud
voelgoednetwerk:silwer
membership_profile_hash
eligibility_hash
```

in member-facing email copy.

---

## 32. Admin Settings Safety

On the notification settings page, show:

```text
Current environment mode
Dry-run status
Bulk sending enabled/disabled
Last queue run
Number queued/sent/failed
Configured sender identity
Configured admin alert recipients
Warning if SMTP/mail transport appears unverified
Send Test Email button
Preview Digest button
```

Do not enable bulk sending without a saved configuration.

Do not allow production mode + bulk sending until admin explicitly confirms.

---

## 33. Mail Transport Testing

Add:

```text
Send test email
```

Rules:

```text
Admin-only.
Nonce-protected.
Sends only to current admin email or configured test address.
Logs result.
Does not require member recipient resolution.
Respects staging/test routing.
```

If `wp_mail()` returns false:

```text
Log failure.
Show admin-safe error.
```

Do not expose SMTP credentials or raw debug output.

---

## 34. Queue Processing

Create:

```php
Voelgoed\MemberPerks\Notifications\NotificationDispatcher
```

Process:

```text
1. Fetch queued/scheduled notifications due now.
2. Mark each as sending.
3. Recheck skip rules where practical:
   - opt-out
   - user still exists
   - dry-run/test mode
4. Send through EmailTransport or dry-run transport.
5. If success, mark sent.
6. If dry-run, mark skipped or sent_to_test depending on implementation convention.
7. If failure, increment attempts.
8. If attempts < max, requeue/schedule retry.
9. If attempts >= max, mark failed.
```

Concurrency safety:

```text
Avoid processing the same row twice where practical.
Use status transition and updated_at.
```

For high-volume future sending, this may need stronger locking. For VS-014, keep it safe and simple.

---

## 35. Rate Limiting

Settings:

```text
Max notifications per batch
Minimum seconds between queue runs if needed
```

Default:

```text
50 per batch
```

Do not send thousands of emails in one PHP request.

Recommended behaviour:

```text
If queued count exceeds batch size, process over multiple cron runs.
Show backlog in admin.
```

---

## 36. Integration Adapter Placeholders

Optional but acceptable:

```text
Brevo adapter placeholder
FluentCRM adapter placeholder
MailPoet adapter placeholder
Webhook adapter placeholder
```

But do not implement real integrations unless requested.

Default must remain:

```text
wp_mail()
```

Reason:

```text
The site may already route wp_mail through FluentSMTP or another SMTP service.
```

Do not claim CRM/newsletter integration unless actually built and tested.

---

## 37. Admin Alerts

Admin alerts should be concise.

Recommended schedule:

```text
Daily
```

Admin alert types:

```text
Perks expiring within 7 days
Active perks with critical misconfiguration
Notifications failed in last 24 hours
Legacy table/data warnings
Dry-run/staging safety warnings
Target index stale if detected
```

Do not send admin alerts every page load.

Queue them through the same notification table.

---

## 38. Claim Confirmation Triggering

Hook into VS-007 redemption ledger events.

Expected events may include:

```php
do_action( "vgmp_redemption_reserved", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_claimed", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_completed", $redemption_id, $redemption_data );
do_action( "vgmp_redemption_failed", $redemption_id, $redemption_data );
```

For claim confirmation emails:

```text
Listen to claimed/completed events.
Do not send confirmations for failed/cancelled/voided records.
Use idempotency to avoid duplicates if both claimed and completed fire.
```

Preferred:

```text
Queue confirmation.
Dispatcher sends shortly after.
```

Do not send directly during:

```text
cart calculation
checkout critical path
claim REST request if it would slow the response
course/ticket fulfilment critical path
```

---

## 39. New Perks Digest Triggering

Do not send a digest whenever a perk is saved.

Preferred:

```text
Scheduled weekly/monthly digest.
```

Digest includes active/new perks for the recipient’s current MembershipProfile.

“New” can mean:

```text
Perk start date within digest period
or campaign selected for digest
```

Recommended VS-014 default:

```text
New perks = active perks with start date within digest period.
```

Document the exact rule in README.

---

## 40. Expiring Perks Triggering

Scheduled daily job:

```text
Find active perks ending within configured window.
Resolve eligible recipients by MembershipProfile.
Queue one expiring perks digest per user per window/period.
```

Avoid one email per perk where possible.

Preferred:

```text
One expiring perks digest per user per period.
```

---

## 41. Performance Requirements

Rules:

```text
1. Do not resolve all members on normal page loads.
2. Use scheduled/batched queue building.
3. Use recipient batching.
4. Use idempotency to avoid duplicates.
5. Do not load full user table into memory.
6. Do not build per-user email bodies during admin page load for all users.
7. Use pagination/batches for recipient resolver.
8. Use plugin cache carefully but do not cache sensitive message content publicly.
9. Do not call PMPro repeatedly for every perk/user without batching/caching.
10. Do not call CurrentUserPerksQuery for thousands of users in one request.
```

If recipient count is large and safe batching is not implemented:

```text
Keep bulk digest disabled and report limitation.
```

---

## 42. Cache and Page-Cache Safety

Align with VS-013.

Member-specific notification data must not be publicly cached.

Do not public-cache:

```text
notification previews
current-user digest previews
member notification preference endpoints
claim confirmation context
REST/action responses for notifications
```

Notification emails themselves are queued/sent server-side and do not depend on public page cache.

Admin pages must be admin-only and should not be cached.

---

## 43. Security Requirements

All notification admin actions must:

```text
Require manage_vg_perks.
Use nonces.
Sanitize settings.
Validate email addresses.
Escape admin output.
Avoid arbitrary template code execution.
Avoid allowing unsafe HTML from untrusted users.
```

Email templates:

```text
Do not allow PHP execution.
Do not allow arbitrary shortcodes unless explicitly approved and sanitized.
```

REST/admin actions:

```text
Do not allow normal members to trigger bulk emails.
Do not allow normal members to view logs or previews.
Do not allow user-submitted recipient IDs for bulk sending.
```

---

## 44. Compatibility Requirements

This slice must not fatal if these are inactive:

```text
WooCommerce
Woo Subscriptions
Paid Memberships Pro
Masteriyo
Tickera
Tickera WooCommerce Bridge
ACF Pro
Bricks
Etch
FluentSMTP
Brevo
MailPoet
FluentCRM
```

If no mail transport is available:

```text
wp_mail may fail.
Log and show admin warning.
Do not fatal.
```

If PMPro/resolver is unavailable:

```text
Bulk member digests must not send.
Admin should see dependency warning.
```

If VS-009 or VS-010 are absent:

```text
Course/ticket-specific confirmation details should be omitted or fallback to generic claim confirmation.
```

---

## 45. Testing Plan

Test on staging only.

### Test 1 — Settings page

```text
Open Member Perks > Settings > Notifications.
Expected: settings visible only to admins.
Expected: bulk sending disabled by default.
```

### Test 2 — Environment safety

```text
On staging, verify dry-run mode is enabled or clearly warned.
Expected: no real member emails are sent by accident.
```

### Test 3 — Send test email

```text
Send test email to current admin/test address.
Expected: one email queued/sent or dry-run logged.
Expected: result logged.
```

### Test 4 — Claim confirmation queue

```text
Enable claim confirmation.
Create a successful claim/redemption through VS-007.
Expected: claim confirmation queued.
Expected: no duplicate on repeated hook/idempotency.
```

### Test 5 — Queue processing

```text
Run queue processor manually or via cron.
Expected: queued email marked sent, skipped, or failed according to environment/mailer result.
Expected: attempts increment on failure.
```

### Test 6 — New perks digest dry run: single programme

```text
Create active perks for Vriendinneklub Goud and Brons.
Preview digest as Goud user.
Expected: Goud sees only eligible perks.
Preview as Brons user.
Expected: Brons does not see Goud-only perks.
```

### Test 7 — New perks digest dry run: dual programme

```text
Use a user with:
- Vriendinneklub Goud
- Voelgoednetwerk Silwer

Create eligible perks for both programmes and one shared perk.
Preview digest.

Expected:
- one preview/email, not two
- Vriendinneklub section appears
- Voelgoednetwerk section appears
- shared perk appears once only
```

### Test 8 — Expiring perks digest

```text
Create active perk ending within 7 days.
Run expiring digest builder in dry-run.
Expected: eligible recipients resolved.
Expected: notification records created or previewed.
Expected: dual-programme duplicates avoided.
```

### Test 9 — Opt-out

```text
Set user meta vgmp_email_perks_opt_out = true.
Run digest builder.
Expected: marketing digest skipped for that user.
```

### Test 10 — Idempotency

```text
Run same digest builder twice for same period.
Expected: no duplicate notifications.
```

### Test 11 — Admin alert

```text
Create misconfigured active perk.
Run admin alert builder.
Expected: admin alert queued/sent to configured admin recipient only.
```

### Test 12 — Non-admin access

```text
Log in as normal member.
Try notification settings/logs URLs.
Expected: access denied.
```

### Test 13 — Missing PMPro safety

```text
If safe, disable PMPro on staging or simulate resolver unavailable.
Expected: bulk digest does not send.
Expected: admin warning.
```

### Test 14 — Large recipient safety

```text
Simulate enough users to test batch size.
Expected: queue processes only max batch size per run.
```

### Test 15 — Legacy table warning

```text
If old wp_vg_perk_notifications exists on staging, open notification/admin tools.
Expected: warning/report appears.
Expected: no destructive migration occurs without approval.
```

### Test 16 — Future mapped level digest

```text
Add a new configured level under an existing launch programme.
Map it to a PMPro level ID.
Create an eligible perk for that level.
Preview digest for a user on the new level.
Expected: user receives the correct eligible perk without notification code changes.
Expected: queue/dry-run record stores canonical programme_key, level_key, and programme_mapping_hash.
```

### Test 17 — Future configured programme digest

```text
Add a new configured programme through ProgrammeDefinition / ProgrammeLevelDefinition.
Map one PMPro level to it.
Create a perk for the new programme.
Preview digest for a user on that programme.
Expected: digest can be built if the programme is enabled and hub/display behaviour is configured.
Expected: no hardcoded Vriendinneklub/Netwerk-only branch blocks the new programme.
```

### Test 18 — Unmapped PMPro level fail-closed

```text
Give a test user an active PMPro level that is not mapped to any enabled ProgrammeLevelDefinition.
Run member digest dry-run.
Expected: user is skipped for member benefit notifications.
Expected: admin/log reason indicates unmapped PMPro level or mapping unavailable.
```

### Test 19 — Programme alias normalization

```text
Configure a programme alias such as old_programme_key → vriendinneklub.
Build/preview a notification from config that references the alias.
Expected: alias normalizes before queueing.
Expected: notification row stores canonical programme_key = vriendinneklub, not the alias.
```

### Test 20 — Programme mapping changed after queue build

```text
Queue a digest in dry-run.
Change programme/level mapping.
Process or rebuild the queue.
Expected: old queued rows show their original programme_mapping_hash.
Expected: new rows use the new programme_mapping_hash.
Expected: stale/unsafe queued member notifications are skipped, rebuilt, or clearly reported according to documented policy.
```

---

## 46. Acceptance Criteria

VS-014 is complete only when:

```text
1. Notification settings page exists and is admin-only.
2. Bulk/member notifications are disabled by default.
3. Staging/dry-run safety exists.
4. wp_vgmp_perk_notifications queue/log table is created safely.
5. Notification dispatcher processes queued emails in batches.
6. wp_mail transport works or fails safely.
7. Test email action exists.
8. Claim confirmation can be queued after successful VS-007 redemption events if enabled.
9. New perks digest can be previewed/dry-run by specific user, programme, and programme level.
10. Dual-programme digest preview sends/builds one combined grouped digest by default.
11. Expiring perks digest can be previewed/dry-run.
12. Admin alerts can be queued/sent for expiring or misconfigured perks.
13. Idempotency prevents duplicate notification records.
14. Opt-out/preference rule exists for marketing-style digests.
15. Notification logs page exists.
16. Notification logs include programme/audience context.
17. Notification logs include programme_mapping_hash where recipient content depends on mapping.
18. Future configured levels can be included through mapping/settings without notification code changes.
19. Future configured programmes can be included through mapping/settings once enabled and QA-tested.
20. Unmapped PMPro levels, unknown programmes, disabled programmes, unknown levels, and unavailable mappings fail closed for member sends.
21. Notification queue records store canonical configured programme_key and level_key values, not aliases.
22. No uncontrolled mass email sends occur.
18. No frontend perk behaviour, pricing, claims, course access, or ticket generation is changed.
19. Plugin does not fatal if optional dependencies are inactive.
20. README documents notification settings, queue, dry-run, safety, dual-programme digest behaviour, and limitations.
```

---

## 47. README Update Requirements

Update README with:

```text
VS-014 implemented features
Notification types
Default disabled behaviour
Staging/dry-run safety
Notification table
Queue processing
Cron requirements
Email transport
Test email instructions
Claim confirmation behaviour
New perks digest behaviour
Dual-programme digest grouping
Programme/level mapping behaviour
Programme aliases and canonical queue values
Programme mapping hash/audit context
Expiring perks digest behaviour
Admin alerts
Opt-out handling
Notification logs
Known limitations
Next slice: VS-015 — Final Integration QA and Release Hardening
```

Include this warning prominently:

```text
Do not enable bulk member notifications on staging or production until recipient rules, dry-run previews, unsubscribe/opt-out expectations, and mail transport have been verified.
```

Include this note:

```text
VS-014 uses wp_mail by default. If the site routes wp_mail through FluentSMTP or another SMTP provider, that provider remains responsible for actual sending/deliverability.
```

Include this note:

```text
Dual-programme members receive one grouped digest by default, not one email per programme.
```

---

## 48. Explicit Out-of-Scope List

Do not implement these in VS-014:

```text
SMS
WhatsApp
Push notifications
Full CRM integration
Brevo API integration
Mailchimp API integration
FluentCRM journey automation
Advanced segmentation engine
Email template drag-and-drop builder
WooCommerce email template replacement
PMPro email template replacement
High-volume marketing automation
Frontend notification preference centre beyond basic opt-out meta
New eligibility logic
New discount logic
New claim limit logic
Course enrolment changes
Ticket generation changes
```

These belong to future slices if needed.

---

## 49. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Database table created
Schema version changes
Settings added
Notification types implemented
Queue/dispatcher behaviour
Cron hooks added
Email transport used
Dry-run/staging safety implemented
Claim confirmation trigger implemented
Digest preview/dry-run implemented
Dual-programme digest behaviour implemented
Programme/level mapping behaviour implemented
programme_mapping_hash storage/usage implemented
Future level/programme dry-run tests performed
Admin alerts implemented
Opt-out handling implemented
Notification logs implemented
Manual tests performed
Known limitations
Any assumptions made
Any dependency issues
```

If bulk sending is intentionally kept in dry-run/manual-preview mode only, state that clearly.

Do not claim CRM/newsletter integration unless explicitly implemented and tested.

Do not claim deliverability guarantees.

---

## 50. Next Slice

After VS-014 is complete and tested, proceed to:

```text
VS-015 — Final Integration QA and Release Hardening
```

VS-015 will perform end-to-end QA across all slices, verify dependency safety, test real user journeys, check staging-to-production readiness, and lock the release checklist.

Do not start VS-015 during this slice.
