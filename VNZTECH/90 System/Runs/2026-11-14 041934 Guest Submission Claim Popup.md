---
type: agent-run
status: completed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, coding-update, guest-claims]
---

# Guest Submission Claim Popup

Recorded **2026-11-14 04:19 Asia/Manila**.

## Request and roles

The user confirmed guest-to-account linking already works after email verification, then requested a dashboard popup inviting a new seller to claim the last guest submission and verify email. Atlas coordinated the identity/submission/UI/QA concerns with an independent claim-flow design review. One writer consolidated the application and vault changes.

## Implemented behavior

- Successful guest submission stores its server-assigned ID with registration prefill. Matching-email normal/social signup binds a separate onboarding marker to that seller and email before clearing prefill. This marker controls presentation only.
- The first dashboard visit displays **Claim your guest submission**, after any existing 2FA/reminder dialog closes. **Yes, claim it** guides verification; **Maybe later** leaves a banner for reopening. English/Filipino copy, theme variables, native dialog focus, resend errors, and cooldown are included.
- Opening the popup does not send mail. A recent signup email is reused; other sends go through the existing authenticated/CSRF-protected verification endpoint and server rate limit. Already-verified accounts refresh status without falsely claiming another email was sent.
- Verification retains the existing transactional guest linker. Signed-in onboarding sellers return to the dashboard; existing normal verification redirects are preserved. Focus/visibility checks and visible-dialog polling detect verification elsewhere.
- A private authenticated status endpoint returns only phase and presentation flags. Dismiss/acknowledge require CSRF. Only verified email plus actual ownership of the exact last server-recorded submission produces **linked**. Legacy markers without an ID remain neutral **verified**, even if an old unrelated claim audit exists.
- The existing guest linker and its eligibility/conversation checks were not changed. Its SQLite test fixture gained the missing `offers.sender_type` column used by the existing production query.

## Attributable files

- Integration: `backend/submit_submission.php`, `backend/register.php`, `backend/social_signup_complete.php`, `backend/request_email_verification.php`, `backend/verify_email.php`, `account/dashboard.php`.
- New implementation: `backend/guest_submission_onboarding.php`, `backend/guest_submission_onboarding_status.php`, `includes/guest-submission-claim-prompt.php`, `assets/js/guest-submission-claim.js`, `assets/css/guest_submission_claim.css`.
- Checks: `tests/guest_submission_onboarding_test.php`, `tests/guest_submission_onboarding_browser_test.py`, and the fixture correction in `tests/guest_submission_link_test.php`.

Pre-existing sell-page CSS, transaction-delivery work, migration, and other unrelated working-tree files were preserved and are not attributed to this task. No application database rows or migrations were changed.

## Actual verification

| Check | Result and scope |
| --- | --- |
| `C:/xampp/php/php.exe tests/guest_submission_onboarding_test.php` | PASS; SQLite memory fixture checks session binding, wrong/inactive seller, email match, cooldown marker, dismissal, verification gating, exact ownership, and legacy audit ambiguity. |
| `C:/xampp/php/php.exe tests/guest_submission_link_test.php` | PASS; all eight existing cases, including history preservation, sign-in linking, unverified/other-owner denial, conversation ownership, idempotence, and ambiguous email denial. |
| `C:/xampp/php/php.exe tests/offer_response_authorization_test.php` | PASS; existing authorization helper regression. |
| PHP lint on 11 changed/new PHP files | PASS. |
| `node --check assets/js/guest-submission-claim.js` | PASS. |
| `python tests/guest_submission_onboarding_browser_test.py` | PASS; isolated headless Chrome, real PHP component/CSS/JS, mocked email/status APIs. Checks reminder ordering, focus, Maybe later/reopen, mail failure/retry/cooldown, verified/linked states, and uncaught script errors. Desktop success screenshot inspected. |
| `git diff --check` | PASS. |

PHP session-related checks used the OS temporary session path through a quoted `-d session.save_path` CLI setting. Expected negative guest-link cases emitted skip/ambiguous-identity log lines. The first sandboxed Chrome attempt failed on process access; an early external attempt could not reuse sandbox-owned temporary artifacts. The final browser script creates its own fresh profile/artifacts and passed outside the sandbox. No real mail/account APIs were called by that browser test.

## Limits and remaining evidence

- Actual SMTP delivery, a fresh live guest/signup/token-confirmation flow, full portal navigation, mobile layout, other browsers, and screen-reader behavior were not exercised.
- Popup state is session-based and requires a matching guest/signup email. A lost session or different browser has no onboarding marker; existing verified-account linking behavior remains available independently.
- Email verification alone does not establish eligibility or successful ownership. Unlinked verified users get neutral wording plus My Submissions and guest-tracking guidance.
- The unrelated social-login setup placeholder assertion from the baseline remains unresolved. Scaledrone database-bootstrap test remains unrun; this task did not rerun the full baseline suite.

## Notes updated

[[40 Knowledge/System Modules/Identity and Accounts]] · [[40 Knowledge/System Modules/Submissions and Offers]] · [[40 Knowledge/System Modules/Interface and Portal UX]] · [[40 Knowledge/System Modules/Email and Notifications]] · [[40 Knowledge/System Modules/Verification and QA]] · [[40 Knowledge/VNZ-TECH System Map]] · [[Home]] · [[90 System/Coding Updates]] · [[90 System/Runs/Agent Runs]]

The dated [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14|implementation snapshot]] and source inventory were preserved as historical evidence.
