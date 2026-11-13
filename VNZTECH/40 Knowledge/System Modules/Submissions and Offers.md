---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Orion]]"
---

# Submissions and Offers

Owner: [[90 System/Agents/Orion|Orion]]. Review date: **2026-11-14**.

## Implemented in source

- Scrap-board/whole-phone submission paths, uploaded-file handling, text cleanup, and reference generation.
- Guest/account tracking uses normalized reference/mobile data; guest chat recovery has throttling helpers.
- Offer send/respond/counter-offer handlers use status checks, row locking or conditional updates, and duplicate-transaction guards.
- Seller responses bind to the owning account or a submission-specific guest session grant; admin sessions are excluded from the seller action.
- Acceptance paths create transaction records and update submission/offer state.
- Guest submission success records the latest submission ID in server-side registration prefill. Matching-email signup keeps a separate onboarding marker for the dashboard claim prompt.
- Claim prompt leads to email verification and checks real submission ownership before reporting success. It preserves the existing linker's eligibility and conversation-integrity checks; the new status endpoint does not perform claims.
- Verification now reopens a confirmation even after the original prompt was dismissed, and status changes refresh the current portal fragment so submission counters/history are current. Email verification remains distinct from actual submission ownership.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `backend/submit_submission.php`
- `backend/track_submission.php`
- `backend/send_offer.php`
- `backend/respond_offer.php`
- `backend/counter_offer.php`
- `backend/admin_respond_offer.php`
- `backend/admin_respond_submission.php`
- `backend/offer_response_authorization.php`
- `account/pages/submission.php`
- `admin/pages/submission_review.php`
- `seller/tracking.php`
- `backend/guest_submission_onboarding.php`
- `backend/guest_submission_onboarding_status.php`
- `includes/guest-submission-claim-prompt.php`
- `backend/email_verification_feedback.php`

## Verification limits and open items

- The offer-response authorization helper test passes; full submission/upload/offer HTTP flows were not exercised.
- `admin_respond_submission.php` documents a direct-accept `offer_id = NULL` compatibility concern with its expected schema. The actual MySQL schema was not inspected.
- Guest-claim regression now passes all eight cases after adding `offers.sender_type` to its SQLite fixture. Production linking rules were not changed. Onboarding helper and mocked-browser checks also pass; live signup/email/token completion remains untested for this task.
- A later isolated HTTP test now exercises actual token-confirmation and guest-link routes against fake SQLite records, including chat transfer and token consumption. All ten cases pass; this is separate from live SMTP or a fresh user signup.

## Related tests

- `tests/offer_response_authorization_test.php`
- `tests/guest_submission_link_test.php`
- `tests/guest_submission_onboarding_test.php`
- `tests/guest_submission_onboarding_browser_test.py`
- `tests/email_verification_http_test.py`

Latest implementation and checks: [[90 System/Runs/2026-11-14 044636 Email Verification Feedback]]. Initial popup: [[90 System/Runs/2026-11-14 041934 Guest Submission Claim Popup]].

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Mira|Mira]] · [[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Quinn|Quinn]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
