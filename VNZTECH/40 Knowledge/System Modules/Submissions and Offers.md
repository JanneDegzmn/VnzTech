---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-20
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Orion]]"
---

# Submissions and Offers

Current local behavior: [[90 System/Runs/2026-11-19 213300 Submission Selfie v6|submission selfie v6]], active2026-11-19. Earlier dated ID-review sections below remain history.

Owner: [[90 System/Agents/Orion|Orion]]. Review date: **2026-11-14**.

## Implemented in source

- Private selfie display inside existing admin submission details opens with **View submission selfie**; the top-layer viewer provides fit/2x zoom/reset and light/dark/mobile controls. V6.2 replaces zoom scrollbars with bounded mouse/touch drag and arrow-key pan, preserving click-vs-drag and capture cleanup. Lazy private reads, abort/revoke on close/replacement and honest unavailable states preserve existing submission-bound storage/access/retention. Submission capture/face-presence policy unchanged. [[90 System/Runs/2026-11-19 224454 Submission Selfie Drag v6.2]]; original viewer evidence: [[90 System/Runs/2026-11-19 222413 Submission Selfie Viewer v6.1]].

- Scrap-board/whole-phone submission paths, uploaded-file handling, text cleanup, and reference generation.
- Guest/account tracking uses normalized reference/mobile data; guest chat recovery has throttling helpers.
- Offer send/respond/counter-offer handlers use status checks, row locking or conditional updates, and duplicate-transaction guards.
- Seller responses bind to the owning account or a submission-specific guest session grant; admin sessions are excluded from the seller action.
- Acceptance paths create transaction records and update submission/offer state.
- Guest submission success records the latest submission ID in server-side registration prefill. Matching-email signup keeps a separate onboarding marker for the dashboard claim prompt.
- Claim prompt leads to email verification and checks real submission ownership before reporting success. It preserves the existing linker's eligibility and conversation-integrity checks; the new status endpoint does not perform claims.
- Verification now reopens a confirmation even after the original prompt was dismissed, and status changes refresh the current portal fragment so submission counters/history are current. Email verification remains distinct from actual submission ownership.
- Seller-account bulk submissions now use the explicit `bulk_format` marker and per-lot `quantity_is_estimated`; each lot selects phone or scrap-board, requires photos, records exact/estimated quantity, an item note, and one total lot price. Per-device specifications are skipped only for this marked account format; the existing selfie path remains required. Public guest bulk is rejected, and older wholesale records keep their prior per-unit semantics. Source: `backend/submit_submission.php`, seller/admin/history/tracking views, and `database/migrations/20261119_add_bulk_submission_format.sql` (unapplied). Forty-one isolated HTTP checks pass; live migration is not established. [[90 System/Runs/2026-11-20 003109 Seller Bulk Submission Flow]].

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

## ID verification v2 - 2026-11-19

Both actual creation forms (sell.php and account/dashboard.php) now carry creation CSRF and identity draft markers. Shared backend/submit_submission.php rejects invalid account intent and checks identity before permanent uploads; guest proof is rechecked/consumed under the submission transaction, preventing replay and restoring grants on rollback. Public remains guest-sourced even with a seller session. Existing claims/offers stay separate. Rollout remains disabled pending administrator cleanup setup; no real submission was created for this task.

[[90 System/Runs/2026-11-19 163005 Identity Verification v2]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]

### Activation follow-up - 2026-11-19 17:07

Earlier disabled/setup-pending state is historical. Owner Administrator setup completed; independent live form/API/private health and successful daily03:00 cleanup readback confirm local activation. No personal ID/device/human-review test or owner acceptance inferred. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Submission selfie v6 - 2026-11-19 21:53

Guest/account Submit now opens camera capture with consent. Shared backend/submit_submission.php validates uploaded selfie, actor-bound600s nonce and real local face presence before public item uploads/INSERT; private photo links atomically to the exact submission. One-use nonce, rollback/file ownership and post-commit mail failure behavior are checked. Logged-in public selling remains guest-sourced. Server errors preserve item fields through the client fetch/redirect adapter. Accepted/declined transitions latch photo retention for30 days; existing offers/claims are unchanged.

[[90 System/Runs/2026-11-19 213300 Submission Selfie v6]] - [[90 System/Tasks/Records/TASK-20261119-004]] - [[40 Knowledge/Integration Catalog]] - [[40 Knowledge/Engineering Lessons]]
