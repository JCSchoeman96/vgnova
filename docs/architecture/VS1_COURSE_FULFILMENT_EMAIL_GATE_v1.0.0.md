# VS1 Course fulfilment and email gate v1.0.0

**Audit date:** 2026-10-26
**Repository baseline:** `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`
**Local runtime:** `http://localhost:10039/`; ABSPATH `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`; database `local` on `localhost`
**Scope:** Read-only review of Woo Course purchase fulfilment, Masteriyo access, My Voelgoed Learning, the Woo Thank You surface, and customer-facing email emitters.

## Gate result

```text
COURSE_PURCHASE_TO_ENROLMENT=GAP
COURSE_FULFILMENT_GATE=BLOCKED
EMAIL_GATE=GAP
RUNTIME_PROOF_REQUIRES_MUTATION=YES
TASK5_DASHBOARD_SOURCE_RUNTIME_CONFLICT=NO
```

Masteriyo has a source-supported Woo order integration. The current runtime has no Course to Woo Product links, so no current Course order can reach that integration. The code activates access only when the order reaches `completed`. A real paid order, access grant, authenticated course view, and email delivery cannot be proven without creating or changing an order and enrolment. The preflight made no such changes.

The approved VS1 spec §27 to 31 requires one useful customer transaction email. Current Woo and Masteriyo settings enable overlapping customer messages. Their actual combined delivery has not been tested. No email setting, template, snippet, or automation was changed.

## Authorities and evidence status

Evidence labels in this report mean `SOURCE PROVEN` for inspected implementation, `RUNTIME PROVEN` for a current Local value, `BOTH` when the implementation and current runtime agree, and `NOT PROVEN` when the available evidence cannot establish a claim. The Local site is staging, not a production-like checkout environment. Dashboard QA dated 2026-09-21 used named QA fixtures and remains historical fixture evidence. The VS1 spec is owner-approved; this preflight does not authorize any new purchaser, Course mapping, order, membership, enrolment, or email configuration.

The delegated tracked-source scan found no sender implementation in Platform Core. It did not inspect runtime plugins or current settings. The root runtime inventory below adds those sources and settings; the delegated scan is not used as proof of current delivery behaviour.

The approved dashboard design at `docs/superpowers/specs/2026-09-19-voelgoed-unified-dashboard-design.md` §§20 to 27 and 31 to 35 assigns Woo Subscriptions as billing authority, PMPro as membership access authority, and Masteriyo as course ownership, enrolment, progress, and certificate authority. It says the dashboard projects provider state and must not copy it into another table. This is the approved design baseline. The staging audit and QA report at `docs/development/unified-dashboard-staging-audit.md` and `docs/development/unified-dashboard-qa.md` are historical runtime records, not current proof.

For the Learning dashboard slice, tracked files and active runtime copies have matching SHA-256 values: `LearningService.php` is `b7156f25406f15e8caf8a250961742a2f6514ce68762d609092b3d06d20f0338`, `AccessGuard.php` is `77b55994203e269643ec4101e95ba06c42fc5aa286310813127312d43e1f64ab`, and `CachePolicy.php` is `48de79acd06ce3a9b28596ec5324c1ea7d5217c0b90d10b7f3260b8154826057`. This establishes source/runtime parity for these files only. The broader Platform Core module has a separate source/runtime drift finding recorded in `VS1_CURRENT_REALITY_AUDIT_v1.0.0.md`. Masteriyo and Woo plugin source is installed runtime code, not tracked in this repository.

Runtime values below were re-read on 2026-10-26 through the VGNova Novamira connection or a read-only HTTP request. No checkout, order, membership, enrolment, email, option, or template was written. Historical QA dated 2026-09-21 is labelled as such wherever used.

## Woo Course purchase to Masteriyo access

### Current Course and Product links

Masteriyo runtime reports version `3.4.3`; WooCommerce reports version `11.2.1`. The Masteriyo Woo integration option `masteriyo_wc_integration_setting` is absent. Its installed `Setting` class supplies defaults when that option is absent, including `auto_redirect.enable=false`, `add_to_cart.enable=false`, and unenrolment statuses `cancelled`, `failed`, and `refunded` (`wp-content/plugins/learning-management-system/addons/wc-integration/Setting.php`, lines 21 to 46).

| Course ID | Runtime status | Woo product link | Current Course URL |
|---:|---|---:|---|
| 532 | publish | none (`_wc_product_id=0`) | `/leer/selfsorg-besige-mas/` |
| 534 | publish | none (`_wc_product_id=0`) | `/leer/geloof-identiteit/` |
| 536 | publish | none (`_wc_product_id=0`) | `/leer/finansiele-vryheid/` |
| 538 | publish | none (`_wc_product_id=0`) | `/leer/huwelik-kommunikasie/` |
| 540 | publish | none (`_wc_product_id=0`) | `/leer/mindfulness-vroue/` |
| 438 | draft | none (`_wc_product_id=0`) | no public Course URL |

A reverse query for Woo products with `_masteriyo_course_id` greater than zero returned no rows. These values came from current WordPress posts and metadata. They are runtime-proven, current Local staging state. The six Course records are not evidence of a purchasable Course path.

### Installed integration behaviour

The active Masteriyo source file is `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/learning-management-system/addons/wc-integration/WcIntegrationAddon.php`.

| Source evidence | Behaviour |
|---|---|
| `init_hooks()` lines 114 to 117 and 128 to 130 | Hooks `create_user_course()` to `woocommerce_new_order` and `woocommerce_update_order`, and `change_order_status()` to `woocommerce_order_status_changed`. It also hooks a thank-you redirect at priority 1. |
| `create_user_course()` lines 1265 to 1275 | Requires a Woo order with a nonzero customer ID. Guest orders return without creating access. |
| `create_user_course()` lines 1356 to 1407 | Reads `_masteriyo_course_id` from the Woo product, stores that course ID on the order item, finds an existing UserCourse by Course and user or creates one, and saves the Woo order ID as `_wc_order_id`. A `completed` order sets the UserCourse active. Other order states do not grant active access here. |
| `change_order_status()` lines 734 to 743 and 803 to 834 | Requires an order object and customer ID. A transition to `completed` activates the existing UserCourse. A configured cancellation, failure, refund, processing, or checkout-draft state can set access inactive. The function sends the enrolment email only after saving active access for a completed order. |
| `link_wc_product()` lines 2779 to 2823 | The integration stores `_wc_product_id` on the Course and `_masteriyo_course_id` on the Woo Product. Its source code supports the mapping, but no current mapping exists. |

The integration reuses the first UserCourse returned for a Course and user. Its lookup is not keyed by Woo order ID and the inspected code has no separate order idempotency lock. On a repeated callback or a later purchase by the same user, it may reuse that record and replace its `_wc_order_id` with the current order. This is source-proven behaviour, not a current duplicate-enrolment finding. Reconciliation would need to compare Woo order items with Masteriyo UserCourse records and their `_wc_order_id` metadata. No automated repair or reconciliation job was identified in this integration path.

The supported code path is therefore present, but runtime configuration and data do not connect any published Course to a Woo Product. No paid order or active purchase-created enrolment exists as evidence for the VS1 path. The Task 4 audit separately found the Member Perks `course_claims` flag off and no established Course Woo price authority. A Course perk claim is not a Woo purchase fulfilment path.

```text
COURSE_PURCHASE_TO_ENROLMENT=GAP
```

## Direct Course destination

`LearningService::item()` in `voelgoed-platform-core/includes/Services/LearningService.php` resolves the UserCourse or Course permalink, validates it with `safe_url()`, and projects it as the Course action. Runtime WordPress `get_permalink()` returned the URLs listed above. These are the current direct Course URL shapes. No user has an order-linked Course in this evidence set, so access to those URLs as a newly purchasing learner is not runtime-proven.

The Masteriyo `maybe_redirect_to_enrolled_course()` handler in `WcIntegrationAddon.php`, lines 1924 to 1949, redirects only for a logged-in customer with a completed order and linked Course. If enabled, its destination is `masteriyo_get_account_url() . '#/courses'`, not the direct Course URL. The `Setting` class default is `auto_redirect.enable=false`, and the runtime option is absent. The automatic redirect is therefore off. VS1 should use the direct Course permalink as its primary Course action and `/my-voelgoed/learning/` as its secondary account route, subject to a later authenticated access check.

## My Voelgoed Learning projection

The approved dashboard design assigns course ownership and access to Masteriyo and requires the dashboard to read provider state rather than store another copy. Current source and runtime evidence for the dashboard slice agrees:

| Concern | Authority and evidence |
|---|---|
| Route | Runtime page 1160, "Kursusse" is published at `http://localhost:10039/my-voelgoed/learning/`. `DashboardRoutes::filter_request()` resolves `/my-voelgoed/<view>/` to its child WordPress page after checking its parent (`includes/Dashboard/DashboardRoutes.php`, lines 28 to 47). |
| Provider | `LearningService` queries Masteriyo `UserCourseQuery` with the authenticated `user_id` and status `any` (`includes/Services/LearningService.php`, lines 100 to 140). It projects each UserCourse and does not copy it to an ACF field or separate dashboard table. |
| Ownership and access | `LearningService::item()` checks the record user ID against the current user ID, calls `masteriyo_is_user_enrolled_in_course()`, and omits records without access (`includes/Services/LearningService.php`, lines 184 to 212 and 352 to 378). |
| Direct action | The service resolves the UserCourse permalink, then falls back to the Course permalink (`includes/Services/LearningService.php`, lines 227 to 238). |
| Rendering | `DashboardDynamicData` maps `vg_dashboard_learning_summary` to `DashboardRegistry::LEARNING` (`includes/Bricks/DashboardDynamicData.php`, lines 19 to 40). A read-only runtime scan confirmed that Bricks page 1160 contains this token in `_bricks_page_content_2`. |
| Authentication | `AccessGuard::register()` attaches `guard_dashboard()` to `template_redirect` at priority 1 (`includes/Dashboard/AccessGuard.php`, lines 43 to 65). `enforce()`, `login_url()`, and `return_url()` redirect unauthenticated requests to login with a validated relative return path (`includes/Dashboard/AccessGuard.php`, lines 113 to 148 and 151 to 165). |
| Cache | `CachePolicy` targets `/my-voelgoed` and child routes, and emits `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, `Expires: 0`, and `Vary: Cookie` (`includes/Performance/CachePolicy.php`, lines 13, 156 to 188). |

A current unauthenticated GET to `/my-voelgoed/learning/` returned HTTP 302 to `/login/?redirect_to=%2Fmy-voelgoed%2Flearning%2F`, with the private/no-store headers above. This is current runtime proof of the route guard and anonymous response headers. It does not prove an authenticated user sees a newly granted Course.

The historical unified dashboard QA report dated 2026-09-21 recorded two Masteriyo enrollments for QA user 12, user isolation, the dashboard's course summary, and a successful missing-progress case. It also labels controlled Woo and Tickera records as QA fixtures. Those results remain historical fixture evidence. They do not establish current Course purchase mapping, current learner access, or representative VS1 data.

## Woo checkout and Thank You surface

Runtime Bricks template 80 is the published `wc_form_checkout` template named "Checkout." Template 128 is the published `wc_thankyou` template named "Thank You Page." A read-only scan of template 128 found no "Start Course" text, no `/my-voelgoed/learning/` URL, and no Course or Course-specific CTA token. The template is Bricks-owned. Masteriyo owns its order-status and enrolment hooks. Neither has a current Course-specific post-purchase CTA.

The bounded future extension point is the existing Bricks Woo Thank You template 128, with a Course-specific CTA shown only after the order and Masteriyo access checks pass. Any such change must be a later, separately authorized implementation plan. The current Masteriyo thank-you redirect is disabled and points to the general Masteriyo account if enabled; it is not a substitute for a verified direct Course destination.

## Transactional email emitters

The runtime inventory was read without sending test messages. It used `WC()->mailer()->get_emails()` and each class's `is_enabled()` value, Woo Subscriptions settings, PMPro email template settings, Masteriyo email settings, Member Perks flags, FluentCRM `list-automations` and `list-campaigns` with one-offs included, active Fluent Snippets metadata and snippet contents, and gateway availability. No email address or credential was read into this report. "Enabled" records current configuration, not proof that an external email was delivered.

### WooCommerce

Current registered Woo mailer classes and their `is_enabled()` values:

The class identifiers and enabled values came from the current `WC()->mailer()->get_emails()` result. Woo's transaction dispatcher is `WC_Emails::send_transactional_email()` in `woocommerce/includes/class-wc-emails.php`, line 205. Core order email classes are in `woocommerce/includes/emails/`, including `class-wc-email-customer-processing-order.php`, `class-wc-email-customer-completed-order.php`, and `class-wc-email-new-order.php`. Subscription renewal classes are in `woocommerce-subscriptions/includes/core/emails/`. Gift classes are in `woocommerce-subscriptions/includes/gifting/emails/`. The customer verification class is `woocommerce/src/Internal/CustomerEmailVerification/Emails/CustomerVerifyEmail.php`.

| Email class | Recipient | Current state | Relevance |
|---|---|---:|---|
| `WC_Email_New_Order` | Admin | enabled | New order notice. |
| `WC_Email_Cancelled_Order` | Admin | enabled | Cancelled order notice. |
| `WC_Email_Failed_Order` | Admin | enabled | Failed order notice. |
| `WC_Email_Customer_Failed_Order` | Customer | enabled | Failed purchase notice. |
| `WC_Email_Customer_On_Hold_Order` | Customer | enabled | Pending/on-hold purchase notice. |
| `WC_Email_Customer_Processing_Order` | Customer | enabled | Processing purchase notice, which may precede the `completed` access transition. |
| `WC_Email_Customer_Completed_Order` | Customer | enabled | Completed purchase notice. |
| `WC_Email_Customer_Refunded_Order` | Customer | enabled | Refund notice. |
| `WC_Email_Customer_Cancelled_Order` | Customer | disabled | Cancelled purchase notice. |
| `WC_Email_Customer_Invoice` | Customer | disabled | Invoice notice. |
| `WC_Email_Customer_Note` | Customer | enabled | Customer note. |
| `WC_Email_Customer_Reset_Password` | Customer | enabled | Password reset. |
| `WC_Email_Customer_New_Account` | Customer | enabled | New account. |
| `WC_Email_Customer_Verify_Email` | Customer | enabled | Customer email verification. |
| `WC_Email_Admin_Payment_Gateway_Enabled` | Admin | enabled | Gateway administration notice. |
| `WC_Email_Customer_POS_Completed_Order` | Customer | disabled | POS completed order. |
| `WC_Email_Customer_POS_Refunded_Order` | Customer | disabled | POS refund. |

The class inventory is current runtime evidence. WooCommerce core owns the classes under `woocommerce/includes/emails/`. The active checkout and order templates are Bricks templates 80 and 128. For the VS1 order path, the enabled Processing and Completed customer emails overlap the possible Masteriyo completed-order email.

### Woo Subscriptions

| Email class or notification | Recipient | Current state | Purpose |
|---|---|---:|---|
| `WCS_Email_New_Renewal_Order` | Admin | enabled | New renewal order notice. |
| `WCS_Email_New_Switch_Order` | Admin | enabled | New switch order notice. |
| `WCS_Email_Processing_Renewal_Order` | Customer | enabled | Processing renewal notice. |
| `WCS_Email_Completed_Renewal_Order` | Customer | enabled | Completed renewal notice. |
| `WCS_Email_Customer_On_Hold_Renewal_Order` | Customer | enabled | On-hold renewal notice. |
| `WCS_Email_Completed_Switch_Order` | Customer | enabled | Completed switch notice. |
| `WCS_Email_Customer_Renewal_Invoice` | Customer | enabled | Renewal invoice. |
| `WCS_Email_Cancelled_Subscription` | Admin | disabled | Cancelled subscription notice. |
| `WCS_Email_Expired_Subscription` | Admin | disabled | Expired subscription notice. |
| `WCS_Email_On_Hold_Subscription` | Admin | disabled | On-hold subscription notice. |
| `WCS_Email_Reactivated_Subscription` | Admin | disabled | Reactivated subscription notice. |
| Manual trial expiration notification | Customer | enabled | Trial ending reminder for manual renewals. |
| Automatic trial expiration notification | Customer | enabled | Trial ending reminder for automatic renewals. |
| Manual renewal notification | Customer | enabled | Upcoming manual renewal reminder. |
| Automatic renewal notification | Customer | enabled | Upcoming automatic renewal reminder. |
| Subscription expiration notification | Customer | enabled | Upcoming subscription expiration reminder. |
| `WCSG_Email_Customer_New_Account` | Customer | enabled | Gifted membership account notice. |
| `WCSG_Email_Completed_Renewal_Order` | Customer | enabled | Gifted subscription completed renewal notice. |
| `WCSG_Email_Processing_Renewal_Order` | Customer | enabled | Gifted subscription processing renewal notice. |
| `WCSG_Email_Recipient_New_Initial_Order` | Gift recipient | enabled | New gifted subscription notice. |

These are lifecycle messages and may be relevant to a subscription purchase or renewal. Current Task 4 evidence found no Woo Subscription order items for the three Vriendinne membership parent products or their six variations. No current Course is linked to a subscription product.

The four `WCSG_*` classes are registered and enabled in the current Woo mailer inventory. The current Vriendinne membership parent products are drafts, and their `_gift_membership_email_option` values are blank with no gift code. No current gift message is proven for those products. Keep the gift-recipient path in scope if a later journey permits gifted membership purchases.

### PMPro and PMPro/Woo integration

Current PMPro runtime reports these 24 email templates. The `_disabled` option is absent for every slug. `PMProEmail::sendEmail()` blocks a send only when the value is non-empty and is not the string `false` (`paid-memberships-pro/classes/class.pmproemail.php`, lines 94 to 99). Therefore all 24 templates are currently enabled by the installed code. `pmpro_email_member_notification` is null; admin checkout, membership-change, cancellation, and billing notification options are `1`. `PMPro_Email_Template::get_all_email_templates()` returns this catalog (`classes/email-templates/class-pmpro-email-template.php`, lines 10 to 18).

| Recipient | Current enabled template slugs | Purpose |
|---|---|---|
| Member | `cancel`, `admin_change`, `refund`, `payment_action`, `invoice`, `membership_recurring`, `membership_expiring`, `membership_expired`, `checkout_check`, `checkout_free`, `checkout_paid`, `billing`, `billing_failure`, `cancel_on_next_payment_date` | Membership cancellation or change, refund, payment action, invoice, recurring billing, expiry, checkout, and billing messages. |
| Admin | `cancel_admin`, `admin_change_admin`, `refund_admin`, `payment_action_admin`, `checkout_check_admin`, `checkout_free_admin`, `checkout_paid_admin`, `billing_admin`, `billing_failure_admin`, `cancel_on_next_payment_date_admin` | Admin copies for membership changes, checkout, cancellation, refunds, payment actions, and billing events. |

PMPro core's native checkout handler calls `sendCheckoutEmail()` and `sendCheckoutAdminEmail()` when `pmpro_send_checkout_emails` permits it (`paid-memberships-pro/includes/checkout.php`, lines 329 to 342). The PMPro/Woo handler `pmprowoo_add_membership_from_order()` runs on `woocommerce_order_status_completed` and calls `pmpro_changeMembershipLevel()` (`pmpro-woocommerce/pmpro-woocommerce.php`, lines 203 to 321). That callback contains no direct `sendCheckoutEmail()` call. A native PMPro checkout email must not be inferred from a Woo order that grants a PMPro level. Separate PMPro billing and lifecycle emails remain enabled and outside the one-time purchase welcome path.

The add-on's completed-order membership grant callback currently runs before Woo's transactional mail callback at the same priority. Source and the current runtime hook inventory show this order. A future Woo Completed email can therefore read an active PMPro level if that order reaches `completed`. No test order was used to prove the full sequence.

### Masteriyo

`emails.student.completed_order.enable` is currently `true`. `WcIntegrationAddon::change_order_status()` saves an active UserCourse for a completed order, then calls `send_wc_enrollment_email()` (`WcIntegrationAddon.php`, lines 823 to 834). `WcEnrollmentEmailToStudent::trigger()` sends to the Masteriyo student's email and supplies Course, customer, and Woo order context, including order ID and total (`Emails/WcEnrollmentEmailToStudent.php`, lines 45 to 95 and 143 to 178). The message uses Masteriyo's student completed-order template (`get_content()`, lines 125 to 135).

The email guard is keyed by `masteriyo_wc_enrollment_email_sent_<course_id>` per user, not by order. The class writes that marker after calling `send()` without checking its result (`WcEnrollmentEmailToStudent.php`, lines 50 to 55 and 86 to 95). A failed transport may therefore leave the guard set and prevent another send until a relevant status path clears it. No current Course link or purchase-created enrolment exists to show how this behaves on this site.

### Voelgoed Member Perks

The current `notifications` feature flag is `false` (Task 4 runtime evidence). A read-only source search of `voelgoed-member-perks/includes/` and `voelgoed-platform-core/includes/` for `wp_mail`, `WC_Email`, PMPro send-email, and generic `send_email` patterns found no direct mail sender in those areas. The flag state and source scan do not prove that every indirect WordPress hook is silent, but no Member Perks transactional emitter is currently identified. Do not enable the flag to test it.

### FluentCRM and FluentSMTP

Current read-only FluentCRM queries returned zero automations and zero campaigns, including one-off campaigns. This is current runtime state. It does not rule out a later automation created outside this checkout. FluentSMTP `2.4.1` is active as the mail transport. It is not itself a purchase or access message owner. No email was sent during this audit.

### Custom snippets and gateway

The active Fluent Snippets runtime has nine published snippets: `1-vg-product-card.php`, `10-vg-bricks-medewerker-queries.php`, `2-vg-article-dynamic-tags.php`, `3-shortcode-voelgoed-vennoot-block.php`, `4-taxonomy-posts-hide.php`, `6-taxonomy-posts-happyfiles.php`, `7-maintenance-action-scheduler.php`, `9-acf-product-copy.php`, and `9-bricks-tickera-event-date-time.php`. A read-only scan of these current snippet files found no direct mail sender API. The README's earlier snippet list is historical documentation and is not used as current state authority.

Woo BACS, cheque, and cash-on-delivery gateways are registered but disabled and unavailable. PMPro Paystack is configured for `sandbox`. Local settings and source do not prove whether Paystack or another external payment provider sends its own receipt or payment notice. That external message remains `NOT PROVEN`; do not suppress local mail based on an assumed gateway message.

If a Woo order contains a Tickera ticket as well as a Course or membership, the active Tickera Woo bridge also hooks order completion to send attendee and admin notifications. This mixed-cart case is not part of the current Course mapping, but a later one-email plan must account for it or exclude mixed carts explicitly.

### Emitter evidence summary

| Emitter | Trigger and recipient evidence | Current state | Evidence status |
|---|---|---|---|
| WooCommerce | Core `WC_Email_*` classes above; customer order statuses and admin new/failed/cancelled notices. | Per-class values recorded above. | Runtime-proven enabled state; code owner is installed WooCommerce source. No VS1 delivery test. |
| Woo Subscriptions and gifting | `WCS_Email_*` renewal, switch, and subscription lifecycle classes; `WCSG_Email_*` gifting classes. | Per-class values recorded above. | Runtime-proven enabled state. No matching current Vriendinne subscription order items or configured gift path. |
| PMPro | Native checkout, billing, membership change, cancellation, failure, expiry, and admin templates. | 24 templates enabled by current absent/null `_disabled` values; admin notification options listed above. | Runtime configuration and installed source; Woo integration does not directly invoke native checkout sender. |
| PMPro/Woo integration | `woocommerce_order_status_completed` grants the linked PMPro level. | Active plugin; no direct checkout sender in the grant callback. | Runtime source-proven; no test order. |
| Masteriyo | Completed linked Course order activates access, then student completed-order email sends. | Student email enabled. | Runtime source and setting proven; no current Course map or delivery proof. |
| Member Perks | No direct sender found in inspected code; current notification flag off. | `notifications=false`. | Current flag and source scan; indirect hooks not exhaustively excluded. |
| FluentCRM | No current automation or campaign returned. | 0 automations; 0 campaigns, including one-offs. | Current runtime inventory. |
| Custom snippets | Nine current published snippets scanned for direct sender APIs. | No direct sender found. | Current runtime snippet metadata and files; indirect senders are not proven absent. |
| Payment gateway | PMPro Paystack uses sandbox; Woo BACS, cheque, and COD are disabled. | External provider notices unknown. | Local configuration proven; external delivery not proven. |
| FluentSMTP | Mail transport. | Active `2.4.1`. | Current runtime plugin inventory; not a business email trigger. |

### Transaction event crosswalk

| Event | Current possible messages or state changes | Finding |
|---|---|---|
| Membership order created | Woo admin `WC_Email_New_Order` is enabled. Customer Processing and On-Hold emails are enabled when those statuses occur. A gifted membership may also reach the enabled WCSG recipient email. | No current membership order was created for this audit. The Vriendinne parent products remain drafts. |
| Membership payment succeeded | Woo customer Processing or Completed messages are enabled. Woo Subscriptions renewal messages are enabled for recurring orders. The PMPro/Woo add-on activates the linked PMPro level on `woocommerce_order_status_completed`. | A payment event alone does not prove PMPro active state. The installed integration uses completed order status as its grant hook. |
| PMPro membership activated | The PMPro/Woo add-on calls `pmpro_changeMembershipLevel()` from its completed-order callback. The same callback does not call PMPro's native checkout email sender. Woo's Completed email is enabled and runs after that grant callback at the same priority. | Active PMPro state can be available to the Woo Completed message when the order reaches `completed`; no current order verified this sequence. PMPro lifecycle templates remain enabled. |
| Course order created | Woo admin New Order is enabled. Customer Processing and On-Hold emails are enabled when those statuses occur. Masteriyo's `create_user_course()` may create an inactive UserCourse when a linked product is present. | No Course product is linked. Creating the inactive record does not grant Course access or send the Masteriyo completed-order email. |
| Course payment succeeded | Woo customer Processing and Completed emails are enabled. Masteriyo access is activated only on the `completed` status path in the inspected integration. | If an order stays at `processing`, the Masteriyo path does not grant active access or send its completion email. The real paid-status mapping is unproven. |
| Masteriyo enrolment created | The Woo-created UserCourse begins inactive unless the order is already completed. At completion, Masteriyo saves active status and sends the student completed-order email. Woo's Completed email is also enabled. | Two customer messages can result from one completed Course order. A Course-ready message must follow active access. No delivery was tested. |

## Proposed one-email ownership for a later plan

This is a proposed contract only. The preflight did not modify settings or emails.

| Transaction | Proposed message owner | Message contents | Later suppression or adaptation to consider |
|---|---|---|---|
| Woo membership purchase | WooCommerce `Customer Completed Order`, after the PMPro/Woo grant has set the active level. | Receipt and order details, active membership state and benefits, and a link to continue the original journey. | Adapt the completed-order message for mapped membership products. Suppress the generic Processing message only after status timing and payment semantics are verified. Do not suppress separate PMPro billing or failure notices. |
| Woo Course purchase | Masteriyo `student.completed_order`, after the Course access record is active. | Course-ready confirmation, direct Course URL as the main action, `/my-voelgoed/learning/` as the secondary action, and order/payment details. | Adapt Masteriyo's completed-order template. Suppress Woo customer Processing and Completed messages for linked Course products only after proving which paid status grants access and that the Masteriyo message is reliable. |

The Woo completed-order path is the stronger membership candidate because the current PMPro/Woo grant callback precedes Woo's completed-order mail callback. Masteriyo is the stronger Course candidate because it sends only after saving active access. These are source-based proposals. A controlled runtime test must check actual order status changes, hooks, duplicate messages, delivery results, and member/course access before any suppression plan is authorized. FluentCRM behavioural follow-up stays out of scope under the approved spec.

## Fulfilment failure modes and later evidence

| Failure mode | Current evidence and observability | Remaining proof |
|---|---|---|
| Paid order but no enrolment | No current Course/Product mapping exists. Masteriyo order and UserCourse state can be compared by order item, user, Course ID, and `_wc_order_id`. No automated reconciliation job was found in the inspected integration path. | A controlled paid-order test must prove the chosen paid status activates access and that support staff can find and reconcile a missing UserCourse. |
| Enrolment exists but Course URL is invalid | Runtime Course permalinks are present and `LearningService` validates the provider URL, then falls back to the Course permalink. No authenticated purchase-created UserCourse was tested. | Open the direct URL as the owning learner after a controlled enrolment and confirm access. |
| My Voelgoed shows stale access | The service queries Masteriyo on request. Current anonymous HTTP response carries private/no-store headers. | Verify authenticated response freshness immediately after a controlled access change and check any full-page or edge cache on the production-like path. |
| Duplicate enrolment | Source reuses an existing UserCourse by Course and user. It does not use order ID as the query key or expose an order-level idempotency lock in this path. No current duplicate was found. | Replay a controlled callback safely and verify one active record, stable lineage, and support-visible reconciliation. |
| Email sends before access exists | Woo Customer Processing Order is enabled. Masteriyo activates the Course only on `completed`, then sends its email. | Check the actual checkout statuses. Confirm the selected customer email does not promise access before the Course is active. |
| Email transport fails after guard is set | Masteriyo's email class stores the per-user/Course sent marker after `send()` without checking success. | Test failure/retry in a controlled task and define support-visible delivery reconciliation before relying on this message as the only customer confirmation. |
| One purchase emits multiple customer messages | Woo Processing and Completed emails, Masteriyo's student completed-order email, and applicable Woo Subscription messages are enabled. | Record delivered messages for each intended membership and Course status path before suppressing any emitter. |

```text
COURSE_FULFILMENT_GATE=BLOCKED
EMAIL_GATE=GAP
RUNTIME_PROOF_REQUIRES_MUTATION=YES
```

The deferred proof requires an explicitly authorized controlled task with a dedicated test purchaser, a reviewed Course to Product mapping, a paid order, an active Masteriyo access record, an authenticated direct Course visit, a My Voelgoed Learning check, and captured mail delivery results. Do not create any of those records during this preflight.
