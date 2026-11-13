---
type: system-module
status: source-reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, system-module]
owner: "[[90 System/Agents/Quinn]]"
---

# Verification and QA

Owner: [[90 System/Agents/Quinn|Quinn]]. Review date: **2026-11-14**.

## Implemented in source

- The baseline review ran six PHP tests plus one PowerShell check: five passed and two failed. Historical results cover social-auth helpers, delivery states, offer authorization, moderation, and legacy attachment inference.
- The guest-link fixture failure is now resolved by adding its missing `offers.sender_type` column. All eight existing cases pass without changing production linking rules.
- The claim-popup task added a PHP/SQLite onboarding test and an offline headless-Chrome integration test. Focused onboarding, guest linking, offer authorization, PHP syntax, JavaScript syntax, and mocked-browser checks pass; see the dated run for commands and scope.
- A subsequent user report exposed missing redirect/feedback coverage in the original mock-only browser check. The new isolated HTTP test runs actual PHP verification/status/login routes with temporary fake data; all ten cases pass. Extended helper/browser checks now cover reopening after dismissal, stale feedback acknowledgement, visible invalid links, and refresh events.
- Remember-login regression now adds 14 passing isolated HTTP cases for real login/session/remember helpers, with SQLite/fake identity and stubbed chat presence. A separate rolled-back MySQL check passed issuance/hash/expiry after applying the missing local token-table migration. Shared-dialog theme fixtures pass 12 public/seller/admin light/dark desktop/mobile cases.
- The unrelated social-login setup check's expected-placeholder failure remains unresolved; Scaledrone authorization was not run because of database bootstrap. This task did not repeat the whole baseline suite.

## Evidence paths

Repo-relative paths from `C:\xampp\htdocs\VNZ-TECH`:

- `tests/chat_attachment_message_type_test.php`
- `tests/chat_scaledrone_auth_test.php`
- `tests/configure_social_login_test.ps1`
- `tests/global_chat_moderation_test.php`
- `tests/guest_submission_link_test.php`
- `tests/offer_response_authorization_test.php`
- `tests/social_auth_service_test.php`
- `tests/transaction_delivery_workflow_test.php`
- `tests/guest_submission_onboarding_test.php`
- `tests/guest_submission_onboarding_browser_test.py`
- `tests/email_verification_http_test.py`
- `tests/remember_login_http_test.py`

## Verification limits and open items

- Existing helper/source tests are narrow evidence. Passing them does not verify browser behavior, migrations, provider configuration, or external integrations.
- The historical guest fixture failure did not establish a production defect. Its corrected regression now passes. Token completion is covered by isolated real-route HTTP tests; live SMTP/fresh signup, mobile rendering, and full portal flows remain separate evidence gaps.
- Remember-login HTTP checks isolate chat presence and do not exercise full 2FA confirmation or a real user's login. Browser theme checks use actual dialog assets with fixture content. The local remember-token migration is verified independently from unrelated migration/deployment state.

## Related tests

- `tests/chat_attachment_message_type_test.php`
- `tests/chat_scaledrone_auth_test.php`
- `tests/configure_social_login_test.ps1`
- `tests/global_chat_moderation_test.php`
- `tests/guest_submission_link_test.php`
- `tests/offer_response_authorization_test.php`
- `tests/social_auth_service_test.php`
- `tests/transaction_delivery_workflow_test.php`
- `tests/guest_submission_onboarding_test.php`
- `tests/guest_submission_onboarding_browser_test.py`
- `tests/email_verification_http_test.py`
- `tests/remember_login_http_test.py`

Latest focused checks: [[90 System/Runs/2026-11-14 050800 Remember Login and Theme Consistency]]. Previous verification checks: [[90 System/Runs/2026-11-14 044636 Email Verification Feedback]].

Actual results and scope: [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]]. Source inventory: [[30 Resources/VNZ-TECH Source Inventory]].

## Handoffs

[[90 System/Agents/Aegis|Aegis]] · [[90 System/Agents/Cipher|Cipher]] · [[90 System/Agents/Echo|Echo]] · [[90 System/Agents/Vega|Vega]]

[[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Agent Directory]] · [[Home]]
