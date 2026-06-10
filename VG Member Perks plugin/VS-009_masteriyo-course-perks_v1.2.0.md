# FEATURE PLANNING PACK: VS-009 — Masteriyo Course Perks

Version: 1.2.0  
Status: Flexibility-hardened planning pack  
Depends on: VS-000 v1.1.0, VS-001 v1.2.0, VS-002 v1.3.0, VS-003 v1.3.0, VS-004 v1.2.0, VS-005 v1.2.0, VS-006 v1.3.0, VS-007 v1.3.0, VS-008 v1.3.0, and PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0  
Next slice: VS-010 — Tickera Ticket Perks

---

## 0. v1.2.0 Programme and Level Flexibility Patch Summary

This version applies the controlled **Programme Flexibility Pass**.

Core doctrine:

```text
Hardcode invariants.
Configure business structure.
Map external IDs.
Fail closed when unmapped.
```

Patch changes:

```text
1. Treats Vriendinneklub and Voelgoednetwerk as launch/default programmes, not the only possible programmes forever.
2. Requires course-perk eligibility to consume MembershipProfile, PerkAudienceEvaluator, ClaimPolicy, and ProgrammeMappingRepository output.
3. Prohibits Masteriyo integration code from hardcoding PMPro level IDs, PMPro group IDs, Woo product IDs, Woo variation IDs, fixed billing terms, or fixed Goud/Silwer/Brons assumptions.
4. Requires course claims to store canonical configured programme_key and level_key values, not aliases.
5. Adds programme_mapping_hash to course-claim/redemption context where available.
6. Adds fail-closed behaviour for unmapped PMPro levels, unknown programmes, disabled programmes, unknown levels, and unavailable mapping/config.
7. Adds future programme and future mapped-level testing.
8. Preserves the existing hard stop: do not guess Masteriyo internals.
```

Reference model:

```text
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md
```

---

## 1. Objective

Build the Masteriyo course-perk execution layer for the **Voelgoed Member Perks** plugin.

This vertical slice must allow eligible members to claim or receive Masteriyo course access through the existing perk system, while using the programme-aware claim and redemption ledger created in **VS-007**.

This slice must integrate carefully with Masteriyo without breaking:

```text
normal paid course purchases
WooCommerce course-product purchases
Woo Subscriptions
existing Masteriyo enrolments
manually granted course access
admin/support workflows
```

The core goal:

```text
Eligible member
↓
Active course perk
↓
MembershipProfile eligibility check
↓
Validated claim through ClaimService
↓
Programme-aware redemption row reserved/claimed
↓
Masteriyo course access/enrolment granted
↓
Redemption row completed or failed
↓
Hub shows claimed/enrolled state
```

This slice must not guess Masteriyo internals. It must first inspect the installed staging implementation and then use only verified APIs, tables, hooks, or adapter points.

It must also not guess membership structure. Course eligibility must come from configured programme/level mappings and the resolved MembershipProfile, not from fixed assumptions about today's PMPro levels or Woo subscription products.

---

## 2. Current Baseline Assumptions

This slice assumes the previous slices exist or have been implemented in the hardened order:

```text
VS-000 v1.1.0 — Current Site Discovery and Conflict Audit
VS-001 v1.2.0 — Plugin Foundation, CPT and Taxonomies
VS-002 v1.3.0 — ACF Fields and Options Page
VS-003 v1.3.0 — PMPro Membership Profile Resolver
VS-004 v1.2.0 — Runtime Status Automation
VS-005 v1.2.0 — Bricks/Etch Programme Hub Display
VS-006 v1.3.0 — Target and Audience Indexing Table
VS-007 v1.3.0 — Claims and Redemption Ledger
VS-008 v1.3.0 — WooCommerce Product Discounts
PROGRAMME_AND_LEVEL_MAPPING_MODEL.md v1.0.0 — Programme and Level Mapping Model
```

Expected plugin path:

```text
wp-content/plugins/voelgoed-member-perks/
```

Expected CPT:

```text
vg_perk
```

Expected target/audience table:

```text
{$wpdb->prefix}vgmp_perk_targets
```

Expected redemption table:

```text
{$wpdb->prefix}vgmp_perk_redemptions
```

Expected target type from VS-006:

```text
masteriyo_course
```

Default launch programme keys:

```text
vriendinneklub
voelgoednetwerk
```

These are the current Voelgoed launch/default programmes only. Runtime code must read enabled programme definitions and level definitions through the configured programme/level mapping layer. Future programmes and levels must be supported through configuration and QA, not PHP conditionals.

Expected ClaimService from VS-007:

```php
Voelgoed\MemberPerks\Claims\ClaimService
```

Expected ClaimValidator from VS-007:

```php
Voelgoed\MemberPerks\Claims\ClaimValidator
```

Expected ReservationService from VS-007, if implemented:

```php
Voelgoed\MemberPerks\Claims\ReservationService
```

Expected RedemptionRepository from VS-007:

```php
Voelgoed\MemberPerks\Claims\RedemptionRepository
```

Expected MembershipProfile resolver from VS-003:

```php
Voelgoed\MemberPerks\Membership\MembershipProfileResolver
```

Expected programme mapping services from VS-003 / the mapping model:

```php
Voelgoed\MemberPerks\Membership\ProgrammeMappingRepository
Voelgoed\MemberPerks\Membership\ProgrammeDefinition
Voelgoed\MemberPerks\Membership\ProgrammeLevelDefinition
```

If class names differ, preserve the responsibilities and document the equivalent names in the implementation report.

If any previous slice is missing or incomplete, stop and report exactly what is missing before implementing VS-009.

---

## 2A. Programme and Level Flexibility Boundary

This slice must not make course claims rigid around the current site setup.

Allowed launch examples:

```text
vriendinneklub:goud or vriendinneklub:goud
voelgoednetwerk:silwer or voelgoednetwerk:silwer
```

These examples are illustrative. The actual runtime values must come from configured programme and level mappings.

Course-perk execution must not hardcode:

```text
PMPro level IDs
PMPro group IDs
Woo membership product IDs
Woo variation IDs
current billing terms
current subscription prices
fixed Goud/Silwer/Brons assumptions
fixed Brons/Silwer/Goud assumptions
a closed list of exactly two programmes forever
```

Correct model:

```text
ProgrammeMappingRepository / SettingsRepository
↓
MembershipProfileResolver
↓
PerkAudienceEvaluator
↓
ClaimValidator / ClaimService
↓
CoursePerkService / Masteriyo adapter
```

Rules:

```text
1. A PMPro level grants course perks only when it maps to an enabled programme level.
2. Unmapped PMPro levels do not grant course perks.
3. Unknown programme references do not grant course perks.
4. Disabled programme references do not grant course perks.
5. Unknown level references do not grant course perks.
6. Programme aliases may be accepted as input only through explicit mapping normalization.
7. Course claims must store canonical configured programme_key and level_key values, not aliases.
8. Runtime code must never guess a programme or level from a display label.
9. Adding a future billing term for an existing PMPro level must not require course-perk code changes.
10. Adding a future mapped level should work after mapping/reindexing/QA without Masteriyo integration code changes.
```

If mapping/configuration cannot be resolved safely:

```text
Deny course claim.
Return dependency_missing, invalid_mapping, or not_eligible as appropriate.
Do not grant Masteriyo access.
Do not create a completed redemption row.
```

---

## 3. Critical Instruction to Novamira Agent

Before implementing this slice:

```text
Inspect the actual staging site.
Inspect the installed Masteriyo version.
Inspect Masteriyo post types, database tables, functions, classes, hooks, and enrolment APIs.
Inspect whether courses are sold directly by Masteriyo, through WooCommerce products, or both.
Inspect whether existing course access is represented as enrolments, orders, user-course records, or another structure.
Do not guess Masteriyo internals.
Do not hardcode unverified Masteriyo table names or function names.
```

The implementation must adapt to the real installed Masteriyo structure.

If no safe Masteriyo enrolment/access API is available:

```text
Do not force a fragile implementation.
Stop and report the safest available integration options.
Keep course perks display-only/manual-admin until a safe access strategy is approved.
```

This is a hard stop condition.

---

## 4. Why This Slice Exists

A course perk is not the same as a visible content card.

The system must answer:

```text
Is this member eligible for this course perk?
Which programme qualified the member?
Which level qualified the member?
Did Vriendinneklub qualify the claim, Voelgoednetwerk qualify it, or both?
Has this member already claimed this course perk?
Should the member keep course access after claiming?
Was the course access granted successfully?
Was the access granted by perk, purchase, subscription, or manual admin action?
Can support see why the user has access?
Can duplicate enrolments be prevented?
Can the same course perk appear once, not twice, for dual-programme members?
```

This cannot be solved safely with Bricks/Etch dynamic visibility or by hiding/showing course cards only.

Correct model:

```text
ACF perk configuration
↓
Runtime active status
↓
Target index: masteriyo_course
↓
MembershipProfile eligibility
↓
ClaimService / RedemptionRepository
↓
Masteriyo access adapter
↓
Display-safe course state
```

---

## 5. Non-Negotiable Scope Boundaries

This slice may implement:

```text
Masteriyo detection
Masteriyo course CPT/API discovery
Course perk target validation
Course claim validation
Course claim REST endpoint/action
Programme-aware course claim flow
Masteriyo enrolment/access grant through verified API only
Course access status helpers
Redemption ledger integration
Hub card state for course perks
Admin warnings for course-perk misconfiguration
Optional orderless course claim flow
```

This slice must not implement:

```text
Tickera ticket issuing
Ticket claim-to-cart flow
Event ticket discounts
WooCommerce product discount rules beyond VS-008
New Woo checkout flows unless explicitly approved
R0 Woo course checkout automation
Email notifications
Advanced reports
Full course-progress reporting
Full access revocation automation
Full recurring subscription renewal policy
Destructive course access removal
Theme edits
functions.php edits
WPCode snippet edits
```

Do not alter existing paid-course purchase flows.

---

## 6. Required Deliverables

Add the following plugin files:

```text
includes/
├── Masteriyo/
│   ├── MasteriyoIntegration.php
│   ├── MasteriyoDetector.php
│   ├── MasteriyoCapabilities.php
│   ├── CoursePerkService.php
│   ├── CourseAccessService.php
│   ├── CourseEnrollmentRepository.php
│   ├── CourseClaimController.php
│   ├── CourseAccessPolicy.php
│   └── CourseClaimResult.php
│
├── Admin/
│   └── PerkCourseWarnings.php
│
└── Display/
    └── CoursePerkDisplayData.php
```

Update existing files:

```text
includes/Plugin.php
includes/Claims/ClaimValidator.php
includes/Claims/ClaimService.php
includes/Display/PerkCardData.php
includes/Query/RestQueryController.php
README.md
```

If existing file names/classes differ, preserve the same responsibilities and document the deviation.

---

## 7. Supported Course Perk Types

This slice supports course-related perks only.

Supported `vg_perk_type` terms:

```text
course_access
free_course
```

Supported target type:

```text
masteriyo_course
```

Supported benefit types:

```text
free
member_only_access
display_only
```

Interpretation:

### `free`

```text
Eligible member can claim the course at no additional charge.
Usually results in Masteriyo enrolment/access being granted.
```

### `member_only_access`

```text
Eligible active member may access the course according to the selected access policy.
Can be claim-based or entitlement-based depending on course access mode.
```

### `display_only`

```text
Show the course perk in the hub.
Do not enrol automatically.
Used for announcements, manual admin workflows, or non-automated perks.
```

Do not use Woo discount pricing logic for these course perks unless the course is explicitly sold through WooCommerce and a future slice defines that path.

---

## 8. Programme-Aware Course Eligibility

Course access must use the **MembershipProfile** from VS-003.

The launch setup must support users in these states:

```text
no active programme
Vriendinneklub only
Voelgoednetwerk only
both Vriendinneklub and Voelgoednetwerk
same-programme conflict state
```

The architecture must also support future configured programmes and levels if they are enabled, mapped, indexed, and tested according to `PROGRAMME_AND_LEVEL_MAPPING_MODEL.md`.

Rules:

```text
1. A course perk may target one programme, one level, multiple levels, both launch programmes, or a future configured programme.
2. A dual-programme member must see a shared course perk once, not twice.
3. If multiple configured programmes qualify the same member for the same course perk, choose one qualified_path deterministically unless claim_scope allows more.
4. Do not stack duplicate course access.
5. Do not create duplicate Masteriyo enrolments.
6. Do not create duplicate completed redemptions for the same claim scope.
7. Do not use hardcoded programme or level names as execution authority.
8. Do not infer eligibility from Woo billing products or variations.
9. Do not infer eligibility from PMPro level labels.
10. Eligibility must come from MembershipProfile + PerkAudienceEvaluator + ClaimValidator.
```

Deterministic qualified path selection:

```text
1. Prefer the programme/level path with the best configured perk priority if available.
2. If equal, prefer explicit programme_level match over programme-wide match.
3. If equal, sort programme_key lexicographically.
4. If equal, use the configured level-rank convention from ProgrammeMappingRepository; lower rank is stronger/higher unless the mapping model explicitly says otherwise.
5. If still equal, use lowest perk_id as final tie-break.
```

The chosen path must be stored in redemption context:

```text
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
```

---

## 9. Course Perk Access Modes

Add or use ACF field from VS-002:

```text
perk_course_access_mode
```

Supported values:

```text
claim_and_enrol
direct_membership_access
display_only
manual_admin
```

### `claim_and_enrol`

Recommended default.

Behaviour:

```text
User clicks claim.
ClaimService validates eligibility and limits.
System records/reserves redemption.
System grants Masteriyo course enrolment/access.
Redemption status becomes completed if successful.
```

### `direct_membership_access`

Behaviour:

```text
Eligible members may access the course while the perk and membership remain active.
May not require an explicit claim.
```

Caution:

```text
This is complex to revoke and audit.
Only enable if Masteriyo supports clean dynamic access checks or a safe access filter.
```

For VS-009, this mode may be prepared but should remain disabled unless the real Masteriyo integration supports it safely.

### `display_only`

Behaviour:

```text
Course card appears in hub.
No enrolment/access execution.
```

### `manual_admin`

Behaviour:

```text
No frontend execution.
Admin/support handles course access manually.
```

For VS-009, implement at least:

```text
claim_and_enrol
display_only
manual_admin
```

---

## 10. Course Access Retention Policy

This is a business-critical decision.

Add or use ACF field from VS-002:

```text
perk_course_access_retention
```

Supported values:

```text
keep_after_claim
while_membership_active
until_perk_expires
manual_review
```

### Recommended default

```text
keep_after_claim
```

Reason:

```text
If a member legitimately claimed a course during an active perk, support/admin complexity is lower if they keep access.
This behaves more like a purchased/free course grant than a temporary visibility rule.
```

### `while_membership_active`

```text
Access should be removed or blocked when membership ends.
Only implement if Masteriyo has safe revocation/access-check APIs.
```

### `until_perk_expires`

```text
Access ends when the perk expires.
This can create support confusion and must not be enabled casually.
```

### `manual_review`

```text
The system records the claim but does not automatically revoke access.
Admins handle exceptions manually.
```

For VS-009:

```text
Implement keep_after_claim.
Store retention metadata for other policies.
Warn if another policy is selected but automated revocation is not implemented.
Do not implement destructive revocation unless explicitly verified and approved.
```

---

## 11. Masteriyo Detection

Create:

```php
Voelgoed\MemberPerks\Masteriyo\MasteriyoDetector
```

Responsibilities:

```text
1. Detect whether Masteriyo is active.
2. Detect installed Masteriyo version if possible.
3. Detect course post type.
4. Detect safe enrolment/access API.
5. Detect whether user is already enrolled.
6. Detect whether access grant can record a source/context.
7. Report missing/unsafe integration state.
```

Do not fatal if Masteriyo is inactive.

If Masteriyo is inactive:

```text
Course claim endpoint must deny with dependency_missing.
Admin warnings should explain that Masteriyo is inactive.
Normal plugin loading must continue.
```

Create a small capabilities/result object:

```php
Voelgoed\MemberPerks\Masteriyo\MasteriyoCapabilities
```

Example shape:

```php
[
    "active" => true,
    "version" => "x.y.z",
    "course_post_type" => "mto-course",
    "can_detect_access" => true,
    "can_grant_access" => true,
    "can_revoke_access" => false,
    "grant_strategy" => "verified_api",
    "notes" => [],
]
```

Use the actual detected course post type and strategy from staging. Do not hardcode guessed values into the planning output as truth.

---

## 12. Course Post Type Discovery

Do not blindly assume the course post type.

The agent must inspect staging and determine the correct Masteriyo course post type.

Recommended checks:

```text
Inspect registered post types.
Search for Masteriyo course CPT.
Check Masteriyo plugin code/classes if available.
Check existing Masteriyo course URLs/admin screens.
Check ACF relationship field settings from VS-002.
Check whether Masteriyo stores courses as posts or custom tables.
```

Once discovered, expose centrally through:

```php
MasteriyoDetector::get_course_post_type()
```

If no course post type is found:

```text
Stop course execution.
Report inability to verify course CPT.
Keep course perks display-only/manual-admin.
Do not create fragile code.
```

---

## 13. CourseEnrollmentRepository

Create:

```php
Voelgoed\MemberPerks\Masteriyo\CourseEnrollmentRepository
```

Purpose:

```text
A thin adapter around Masteriyo's real enrolment/access storage.
```

Responsibilities:

```text
1. Check whether a user already has access/enrolment.
2. Grant enrolment/access if safe.
3. Avoid duplicate enrolments.
4. Optionally store source metadata if Masteriyo supports it.
5. Return structured results.
```

Required methods:

```php
public function user_has_course_access( int $user_id, int $course_id ): bool;

public function grant_course_access(
    int $user_id,
    int $course_id,
    array $context = []
): array;
```

Structured success response:

```php
[
    "success" => true,
    "code"    => "enrolled",
    "message" => "Course access granted.",
]
```

Structured already-has-access response:

```php
[
    "success" => true,
    "code"    => "already_enrolled",
    "message" => "User already has course access.",
]
```

Structured failure response:

```php
[
    "success" => false,
    "code"    => "masteriyo_api_missing",
    "message" => "No safe Masteriyo enrolment API was found.",
]
```

Do not throw fatal errors for normal failures.

Do not directly insert into Masteriyo tables unless the structure has been verified and there is no safer API. If direct table writes are proposed, stop and request explicit approval.

---

## 14. CourseAccessService

Create:

```php
Voelgoed\MemberPerks\Masteriyo\CourseAccessService
```

Responsibilities:

```text
1. Validate course target.
2. Confirm target is linked to the perk via wp_vgmp_perk_targets.
3. Confirm target_type = masteriyo_course.
4. Check whether the user already has access.
5. Grant access through CourseEnrollmentRepository.
6. Return structured result.
```

This class should not perform PMPro checks directly.

It should rely on:

```text
ClaimValidator
ClaimService
MembershipProfile context already resolved by VS-007 claim layer
```

---

## 15. CoursePerkService

Create:

```php
Voelgoed\MemberPerks\Masteriyo\CoursePerkService
```

Responsibilities:

```text
1. Coordinate the course claim flow.
2. Use ClaimService/ClaimValidator to validate the user and claim limits.
3. Use ReservationService if VS-007 provides it.
4. Use CourseAccessService to grant course access.
5. Update redemption status to completed or failed.
6. Return display-safe response.
```

Recommended flow for `claim_and_enrol`:

```text
1. User submits course claim.
2. Validate perk/user/target through ClaimValidator with target_type = masteriyo_course.
3. Resolve MembershipProfile, canonical programme/level context, qualified_path, eligibility_hash, and programme_mapping_hash.
4. Reserve redemption if ReservationService exists and claim_scope requires protection.
5. Grant Masteriyo course access.
6. If already enrolled, treat as safe success and complete/record according to policy.
7. If successful, update redemption status to completed.
8. If failed, update redemption status to failed and release/void reservation if applicable.
9. Return response.
```

If VS-007 did not implement reservation behaviour cleanly:

```text
Use idempotent claim creation, then update status after enrolment.
Document exact behaviour.
```

---

## 16. Course Claim Endpoint

Create:

```php
Voelgoed\MemberPerks\Masteriyo\CourseClaimController
```

Endpoint:

```text
POST /wp-json/vg-member-perks/v1/course-claim
```

Request body:

```json
{
  "perk_id": 123,
  "course_id": 456
}
```

Security:

```text
Logged-in users only.
Use nonce.
Use current user only.
Sanitize perk_id and course_id.
Validate course is a target of the perk.
Validate eligibility through ClaimValidator.
Do not allow user_id in request.
Do not allow programme_key/level_key in request.
Do not trust frontend claim state.
```

Response success:

```json
{
  "success": true,
  "code": "course_access_granted",
  "message": "Course access granted.",
  "redemption_id": 789,
  "course_id": 456,
  "status": "completed"
}
```

Response already enrolled:

```json
{
  "success": true,
  "code": "already_enrolled",
  "message": "You already have access to this course.",
  "redemption_id": 789,
  "course_id": 456,
  "status": "completed"
}
```

Response failure:

```json
{
  "success": false,
  "code": "not_eligible",
  "message": "This course perk is not available for your membership."
}
```

Messages should be translatable and user-safe.

---

## 17. Claim Idempotency for Course Claims

Course claims must be idempotent.

Recommended idempotency key:

```text
course_claim:user:{user_id}:perk:{perk_id}:course:{course_id}:scope:{claim_scope}:path:{qualified_path}:period:{claim_period_key}
```

Simpler allowed form if `qualified_path` is not available:

```text
course_claim:user:{user_id}:perk:{perk_id}:course:{course_id}:period:{claim_period_key}
```

If the user clicks the claim button twice:

```text
Do not create duplicate redemptions.
Do not create duplicate Masteriyo enrolments.
Return existing successful state if applicable.
```

If user already has Masteriyo access before claiming:

```text
Do not duplicate enrolment.
Return code = already_enrolled.
Record a completed redemption if policy says this is useful for audit and claim limits.
```

---

## 18. Target Validation

A course claim may only execute if all are true:

```text
1. Perk exists.
2. Perk post_status = publish.
3. Perk runtime status = active.
4. Perk has target type masteriyo_course.
5. Submitted course_id is indexed as a benefit target of the perk in wp_vgmp_perk_targets.
6. User is eligible through MembershipProfile and audience rules.
7. Claim limits allow the claim.
8. Course exists in Masteriyo.
9. Course access mode allows frontend claim.
10. Masteriyo access adapter confirms safe grant capability.
```

Do not trust the submitted `course_id`.

Do not trust frontend display data.

---

## 19. Updating ClaimValidator

Update VS-007 `ClaimValidator` only generically.

Additional validation context:

```php
[
    "target_type" => "masteriyo_course",
    "target_id"   => 456,
    "source"      => "course_claim",
]
```

The validator may call a generic target relationship service/repository, but it must not contain Masteriyo internals.

Correct:

```text
ClaimValidator confirms generic claim eligibility and target relationship.
CourseAccessService confirms Masteriyo-specific course/access safety.
```

Incorrect:

```text
ClaimValidator writes to Masteriyo tables.
ClaimValidator knows Masteriyo enrolment schema.
```

---

## 20. Updating Perk Card Data

Update:

```text
includes/Display/PerkCardData.php
includes/Display/CoursePerkDisplayData.php
```

Add course-specific card state:

```php
[
    "course_id"              => 456,
    "course_url"             => "...",
    "course_access_status"   => "not_enrolled",
    "course_claim_available" => true,
    "course_access_mode"     => "claim_and_enrol",
    "course_retention"       => "keep_after_claim",
    "cta_label"              => "Claim course",
    "cta_behaviour"          => "course_claim",
]
```

Possible course access statuses:

```text
not_enrolled
already_enrolled
claim_available
already_claimed
claim_limit_reached
not_eligible
dependency_missing
display_only
manual_admin
```

Do not expose:

```text
raw Masteriyo internal data
raw PMPro level IDs
admin notes
redemption notes
other users' claim state
```

---

## 21. Hub Button Behaviour

For course perks:

### If user can claim

```text
CTA: Claim course
Behaviour: POST to course claim endpoint
```

### If already enrolled

```text
CTA: Go to course
Behaviour: link to course page
```

### If already claimed but access state is unclear

```text
CTA: View details or Contact support
Behaviour: safe message; no duplicate grant attempt unless idempotent recheck is supported
```

### If display only

```text
CTA: View course
Behaviour: link to course page if available
```

### If manual admin

```text
CTA: Request access or Contact support if configured
Behaviour: no automatic enrolment
```

### If dependency missing

```text
CTA: unavailable
Normal members see friendly unavailable state.
Admins see diagnostic warning if debug badges are enabled.
```

Do not create a Woo order in this slice unless the course claim mode explicitly requires Woo checkout and that path is separately verified and approved.

Default should be direct claim-and-enrol.

---

## 22. Admin Warnings

Create:

```php
Voelgoed\MemberPerks\Admin\PerkCourseWarnings
```

Show warnings on the `vg_perk` edit screen.

Warnings:

```text
Course perk has no Masteriyo course target.
Course perk uses unsupported benefit type.
Course perk has claim_and_enrol mode but Masteriyo is inactive.
Course target ID does not appear to be a valid Masteriyo course.
Course retention policy requires revocation but revocation is not implemented.
Course claim mode is direct_membership_access but dynamic access check is not verified.
Course perk has multiple course targets but claim copy/CTA assumes one target.
Course perk is shared across multiple programmes but claim_scope is per_programme and may grant duplicate audit records.
Course audience references an unknown or disabled programme.
Course audience references an unknown level.
Course audience uses an alias that normalizes correctly but should be re-saved/reindexed to canonical values.
Course target/audience index is stale compared with the current programme_mapping_hash.
User has an unmapped PMPro level that does not grant this course perk.
```

Do not block saving unless values are dangerously invalid.

---

## 23. Existing Paid Course Purchases

Do not break existing course purchase flows.

Rules:

```text
1. Do not remove or alter WooCommerce product links for Masteriyo courses.
2. Do not change Masteriyo course pricing.
3. Do not replace existing course checkout.
4. Do not remove existing enrolments.
5. Do not revoke existing access.
6. Do not assume perk access overrides purchased access.
7. Do not convert paid courses into free courses globally.
```

If a user already bought/enrolled in a course:

```text
The perk claim should return already_enrolled.
Optionally record a completed redemption for audit if allowed by claim policy.
```

---

## 24. WooCommerce Course Products

Some Masteriyo setups may sell courses via WooCommerce products.

VS-009 default:

```text
Do not implement Woo checkout for course claims.
```

Reason:

```text
VS-008 already handles Woo product discounts.
VS-009 should focus on Masteriyo access.
```

If the site relies entirely on Woo checkout to create Masteriyo enrolments:

```text
Stop and report this.
Recommend either:
1. Use VS-008 Woo product discount/free product path for Masteriyo course products, or
2. Add a future slice for R0 Woo course checkout claims.
```

Do not secretly mix both flows without approval.

---

## 25. Access Revocation Policy

Do not implement destructive course access revocation in VS-009 unless Masteriyo provides a safe, verified API and the business rule is explicitly approved.

Default:

```text
keep_after_claim
```

If policy is set to `while_membership_active` or `until_perk_expires`:

```text
Record policy metadata.
Show admin warning that automated revocation is not active unless implemented.
Do not remove access automatically.
```

Revocation can anger members if done incorrectly. Keep it explicit.

---

## 26. Redemption Ledger Integration

Course claims must create/update rows in:

```text
wp_vgmp_perk_redemptions
```

Required values:

```text
user_id
perk_id
target_type = masteriyo_course
target_id = course_id
target_value = ""
programme_key
level_key
qualified_path
eligibility_hash
programme_mapping_hash
claim_scope
benefit_type
status
claim_period_key
source = course_claim
idempotency_key
claimed_at
updated_at
completed_at if available
```

Recommended statuses:

```text
reserved = claim accepted but access not granted yet
completed = course access granted or already existed
failed = Masteriyo access grant failed
claimed = claim recorded but access not automatically granted
voided = admin/system invalidated the claim
```

For `display_only`:

```text
No redemption required unless user explicitly claims.
```

For `manual_admin`:

```text
No frontend redemption should be created.
Admin/manual claim flow may be added later.
```

---

## 27. Claim Scope Rules for Course Perks

Course perks must respect the claim scope from VS-002 / VS-007.

Supported claim scopes:

```text
per_user
per_programme
per_programme_level
per_order
unlimited
```

Recommended default for courses:

```text
per_user
```

This default is independent of the number of current or future programmes. A user should normally receive one access grant to the same course unless the perk deliberately uses a broader claim scope.

Reason:

```text
A user should normally not receive duplicate access to the same Masteriyo course just because they belong to both Vriendinneklub and Voelgoednetwerk.
```

If `per_programme` is selected:

```text
The ledger may allow separate programme audit records.
The Masteriyo access grant must still happen once.
Do not duplicate enrolment.
```

If `per_programme_level` is selected:

```text
The ledger may allow separate level-scoped claim records.
The Masteriyo access grant must still happen once.
```

`per_order` is usually not appropriate for direct course claims and should trigger an admin warning unless a Woo checkout course path exists.

---

## 28. Logging

Log:

```text
Course claim requested
Course claim denied
Course access granted
User already enrolled
Masteriyo inactive/missing
Course target invalid
Course enrolment failed
Duplicate idempotent claim returned
Redemption status updated
Unsupported course access policy selected
Programme mapping unavailable
Unknown or disabled programme reference blocked
Unknown level reference blocked
Unmapped PMPro level ignored for course eligibility
Stale programme_mapping_hash detected for course perk index rows
```

Do not log sensitive personal details beyond operational IDs.

---

## 29. Performance Requirements

Rules:

```text
1. Do not call Masteriyo enrolment checks repeatedly for every card if avoidable.
2. Prefetch course access state for visible course perk cards where practical.
3. Use wp_vgmp_perk_targets to validate course targets.
4. Use RedemptionRepository for claim state.
5. Do not query all perks and filter in PHP.
6. Do not call PMPro directly per card.
7. Use MembershipProfile request cache from VS-003.
8. Keep REST responses small.
```

For MVP, if the number of course perks per hub section is small, simple per-card checks are acceptable but must be documented.

---

## 30. Security Requirements

All course claim actions must:

```text
Require logged-in user.
Use nonce.
Use current user ID only.
Sanitize perk_id and course_id.
Validate target relationship server-side.
Validate eligibility server-side.
Use ClaimService/ClaimValidator.
Never trust frontend claim state.
Never allow user-submitted user_id.
Never allow user-submitted programme_key, level_key, qualified_path, eligibility_hash, or programme_mapping_hash.
Never allow user-submitted redemption status.
```

Do not expose Masteriyo internal errors to normal members.

Return user-safe messages.

Admin/debug output must require:

```text
manage_vg_perks
```

---

## 31. Compatibility Requirements

This slice must not fatal if these are inactive:

```text
WooCommerce
Woo Subscriptions
Paid Memberships Pro
Masteriyo
Tickera
ACF Pro
Bricks
Etch
```

If Masteriyo is inactive:

```text
Plugin still loads.
Course claim endpoint returns dependency_missing.
Admin warnings appear for course perks.
Hub cards may show unavailable or display-only state.
```

If PMPro/resolver is unavailable:

```text
Claim validation denies safely.
```

If ProgrammeMappingRepository / mapping configuration is unavailable:

```text
Course claims deny safely with dependency_missing, invalid_mapping, or not_eligible.
No Masteriyo access is granted.
No completed redemption row is created.
```

If ACF is inactive:

```text
Course perk configuration may not load.
Claims fail safely where config is required.
```

If `wp_vgmp_perk_targets` or `wp_vgmp_perk_redemptions` is missing:

```text
Course claims must fail safely with dependency_missing.
Admin warning must explain the missing table/slice.
```

---

## 32. Testing Plan

Test on staging only.

### Test 1 — Masteriyo detection

```text
Confirm plugin detects Masteriyo active/inactive state.
Confirm detected Masteriyo version if available.
Confirm detected course post type.
Expected: no fatal if inactive.
```

### Test 2 — Course target validation

```text
Create active course perk targeting Course A.
Try claim with Course A.
Expected: validation passes if user eligible.

Try claim with Course B not linked to perk.
Expected: invalid_target.
```

### Test 3 — Eligible Vriendinne course claim

```text
Create active perk:
type = free_course or course_access
target = Course A
audience = vriendinneklub:goud
access mode = claim_and_enrol
claim_scope = per_user

Log in as Vriendinneklub Goud member.
Claim course.
Expected: redemption row created.
Expected: programme_key = vriendinneklub.
Expected: level_key = goud.
Expected: Masteriyo access/enrolment granted.
Expected: status completed.
```

### Test 4 — Eligible Voelgoednetwerk course claim

```text
Create active perk:
target = Course A
audience = voelgoednetwerk:silwer
access mode = claim_and_enrol

Log in as Netwerk Silwer member.
Claim course.
Expected: redemption row created with programme_key = voelgoednetwerk.
Expected: course access granted.
```

### Test 5 — Dual-programme member shared course perk

```text
User has Vriendinneklub Goud and Voelgoednetwerk Silwer.
Create a course perk eligible to both programmes.
Claim course.
Expected: card appears once.
Expected: one Masteriyo enrolment/access grant.
Expected: redemption qualified_path is deterministic.
Expected: no duplicate enrolment.
```

### Test 6 — Ineligible user blocked

```text
Log in as Brons member for Goud-only course.
Try claim.
Expected: not_eligible.
Expected: no course access granted.
Expected: no completed redemption.
```

### Test 7 — Expired perk blocked

```text
Set runtime status = expired.
Try claim.
Expected: perk_not_active.
```

### Test 8 — Claim limit

```text
Set max_claims_per_user = 1.
Claim once.
Try claim again.
Expected: no duplicate enrolment.
Expected: returns already claimed/already enrolled state.
```

### Test 9 — Already enrolled user

```text
User already has Masteriyo course access before claiming.
Claim perk.
Expected: no duplicate enrolment.
Expected: safe already_enrolled response.
Expected: redemption record behaviour documented.
```

### Test 10 — Double-click/idempotency

```text
Submit same claim twice quickly.
Expected: one redemption record according to claim scope.
Expected: one enrolment/access grant.
```

### Test 11 — Display state

```text
After claim, view member hub.
Expected: card shows Go to course / already enrolled state.
```

### Test 12 — Display-only mode

```text
Create course perk with display_only.
Expected: card shows course info.
Expected: no claim/enrolment occurs.
```

### Test 13 — Manual-admin mode

```text
Create course perk with manual_admin.
Expected: no frontend claim endpoint execution.
Expected: user sees safe manual/support CTA if configured.
```

### Test 14 — Masteriyo inactive safety

```text
If safe, deactivate Masteriyo on staging.
Expected: plugin does not fatal.
Expected: course claim returns dependency_missing.
```

### Test 15 — Existing paid course flow

```text
Test normal Masteriyo/Woo paid course purchase if applicable.
Expected: existing flow still works.
Expected: plugin does not interfere.
```

### Test 16 — Retention policy warning

```text
Set retention policy to while_membership_active or until_perk_expires.
Expected: admin warning if automated revocation is not implemented.
Expected: no destructive revocation occurs.
```

### Test 17 — Unmapped PMPro level fails closed

```text
Create or use a PMPro level that is active for the user but not mapped to an enabled programme level.
Create a course perk that might look similar by label.
Expected: user is not eligible.
Expected: no Masteriyo access is granted.
Expected: debug/admin tooling reports unmapped PMPro level where appropriate.
```

### Test 18 — Future mapped level works without Masteriyo code change

```text
Create a temporary PMPro access-only test level.
Map it to an enabled programme/level row.
Create or update a course perk targeted to that configured level.
Reindex audience targets if required.
Expected: eligible user can claim the course.
Expected: no Masteriyo integration code changes are required.
```

### Test 19 — Future configured programme does not fatal

```text
Configure a temporary test programme with one mapped level.
Create a display-only course perk for that programme first.
Expected: hub/card state and admin warnings do not fatal.

If claim_and_enrol is enabled and fully tested:
Expected: CoursePerkService consumes MembershipProfile/ClaimService output and does not require hardcoded programme-specific branches.
```

### Test 20 — Programme alias normalization

```text
Configure an explicit programme alias such as old_programme_key -> vriendinneklub.
Create or inspect a course perk with legacy/alias input.
Expected: alias normalizes to canonical configured programme_key before indexing/claiming.
Expected: redemption stores canonical programme_key, not the alias.
```

---

## 33. Acceptance Criteria

VS-009 is complete only when:

```text
1. Masteriyo is detected safely.
2. Course post type/access API is inspected and documented.
3. Active eligible course perks can be claimed.
4. Course target validation uses wp_vgmp_perk_targets.
5. ClaimService/RedemptionRepository are used.
6. Course access/enrolment is granted only after server-side validation.
7. Programme-aware redemption context is recorded.
8. Dual-programme members do not receive duplicate course enrolments.
9. Already-enrolled users are handled safely.
10. Claim limits and idempotency are respected.
11. Redemption ledger records course claims in wp_vgmp_perk_redemptions.
12. Hub/card data can show course claim/enrolled state.
13. Ineligible, logged-out, expired, dependency-missing, and invalid-target claims are blocked.
14. Existing paid course purchase flows are not broken.
15. No Tickera ticket logic is implemented.
16. No destructive course access revocation is implemented unless explicitly verified and approved.
17. Plugin does not fatal if dependencies are inactive.
18. README documents Masteriyo detection, course claim flow, limitations, and test results.
19. Course eligibility consumes configured programme/level mapping output; it does not hardcode PMPro IDs or fixed level names.
20. Unmapped PMPro levels fail closed and do not grant course perks.
21. Future mapped levels can qualify for course perks after configuration/reindexing/QA without Masteriyo-specific code changes.
22. Course redemption context includes programme_mapping_hash where available.
```

---

## 34. README Update Requirements

Update README with:

```text
VS-009 implemented features
Detected Masteriyo version/details
Detected Masteriyo course post type
Detected enrolment/access API or integration strategy
Supported course perk types
Supported course access modes
Course claim flow
Course retention policy
Programme-aware redemption context
Programme mapping hash / mapping-context behaviour
Dual-programme and future configured-programme behaviour
Unmapped PMPro level fail-closed behaviour
Known limitations
Testing notes
Next slice: VS-010 — Tickera Ticket Perks
```

Include this warning:

```text
VS-009 does not implement Tickera ticket perks.
VS-009 does not create WooCommerce course orders unless explicitly added and documented.
VS-009 does not automatically revoke course access after cancellation/expiry unless a safe policy has been approved and implemented.
```

Include this note:

```text
If the site relies on WooCommerce checkout to create Masteriyo enrolments, use the Woo product path from VS-008 or create a future dedicated R0 Woo course checkout slice. Do not mix direct enrolment and Woo checkout silently.
```

---

## 35. Explicit Out-of-Scope List

Do not implement these in VS-009:

```text
Tickera ticket generation
Event ticket discounts
Ticket claim-to-cart flow
R0 ticket checkout
New Woo course checkout path unless explicitly approved
Email notifications
Advanced analytics dashboards
CSV exports
Full course progress reports
Automatic destructive course access revocation without approval
Recurring subscription renewal benefit policy
Persistent Redis/object cache layer
Cloudflare/LiteSpeed cache rules
```

These belong to later slices.

---

## 36. Required Final Agent Report

After implementation, provide a concise report with:

```text
Files created
Files modified
Masteriyo version detected
Masteriyo course post type detected
Masteriyo enrolment/access API used
Course claim endpoint created
Claim/redemption integration details
Programme-aware redemption fields populated
Programme mapping hash behaviour
Unmapped PMPro level / unknown programme fail-closed behaviour
Future mapped-level test result
Course access retention behaviour
Admin warnings added
Manual tests performed
Known limitations
Any assumptions made
Any dependency issues
```

If no safe Masteriyo enrolment/access API could be verified, report that clearly and do not fake the integration.

Do not claim Woo checkout course integration unless it was intentionally implemented and tested.

Do not claim access revocation support unless it was intentionally implemented and tested.

---

## 37. Next Slice

After VS-009 is complete and tested, proceed to:

```text
VS-010 — Tickera Ticket Perks
```

VS-010 will use the target index and redemption ledger to support free/discounted event and ticket perks while preserving proper WooCommerce/Tickera ticket generation.

Do not start VS-010 during this slice.
