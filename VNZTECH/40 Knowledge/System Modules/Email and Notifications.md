---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Beacon]]"
---

# Email and Notifications

Owner: [[90 System/Agents/Beacon|Beacon]]. Review date: **2026-11-14**.

## Implemented in source

- Email-verification request/send/confirm helpers and routes, including `sendVnzEmail` and `sendEmailVerification`.
- PHPMailer is declared in Composer for mail delivery integration.
- Authenticated, CSRF-checked persistence of submission/offer/transaction notification preferences.
- Seller/admin unseen-result/count endpoints plus mark-seen handlers.
- Account/admin interfaces consume notification state and settings.
- Guest-signup onboarding reuses the existing email-verification sender. Successful sends record the prompt cooldown; opening the popup sends nothing. A recent registration email is reused before another send is attempted.
- The resend endpoint explicitly reports an already-verified email so the prompt can refresh ownership status. After token confirmation, a signed-in onboarding seller returns to the dashboard; other sellers keep the existing settings destination.
- Opening a valid email link displays an explicit **VERIFY MY EMAIL** form; only its CSRF-protected POST verifies the address and links eligible guest submissions. Email/confirmation copy now explains this second step and using the latest email after resending.
- Successful verification queues seller-bound session feedback after commit. Invalid/used links for signed-in sellers now queue visible feedback and return to the dashboard, avoiding the old login redirect that discarded the result. An already-verified database state is presented truthfully even when the clicked link has been consumed.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/email_verification.php`
- `backend/request_email_verification.php`
- `backend/verify_email.php`
- `backend/update_seller_notification_preferences.php`
- `backend/get_unseen_results.php`
- `backend/get_unseen_admin_counts.php`
- `backend/mark_result_seen.php`
- `backend/mark_admin_notifications_seen.php`
- `composer.json`
- `backend/guest_submission_onboarding.php`
- `assets/js/guest-submission-claim.js`
- `backend/email_verification_feedback.php`
- `account/login.php`

## Verification limits and open items

- SMTP deliverability and deployment-specific verification URLs were not tested.
- Saving preferences does not prove that every sender currently enforces every preference. Trace actual consumers before claiming that behavior.

## Related tests

- `tests/guest_submission_onboarding_browser_test.py` covers mocked email failure/retry/cooldown plus verified and invalid-link presentation.
- `tests/email_verification_http_test.py` passes ten real-route HTTP cases using temporary PHP copies, fake sessions, and SQLite, including valid GET without mutation, POST confirmation, token consumption, linking, CSRF, expired/used links, and seller-bound feedback. Live SMTP was not exercised.

Latest implementation and checks: [[90 System/Runs/2026-11-14 044636 Email Verification Feedback]]. Initial popup: [[90 System/Runs/2026-11-14 041934 Guest Submission Claim Popup]].

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Cipher|Cipher]] · [[90 System/Agents/Orion|Orion]] · [[90 System/Agents/Rhea|Rhea]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
