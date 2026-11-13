---
type: source-inventory
status: reviewed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, source-evidence]
---

# VNZ-TECH Source Inventory

Snapshot date: **2026-11-14**. Scope: first-party `.php`, `.js`, `.css`, `.sql`, `.ps1`, `.md`, manifests and Apache/Git settings. **186 files**, **115,635 lines**.

## Coverage and exclusions

Content was scanned for file structure, functions/actions, and migration tables. Core application flows were then read selectively to build [[40 Knowledge/VNZ-TECH System Map]] and the module notes. An inventory entry does not claim that every line was semantically reviewed or that its feature was exercised.

Excluded: `node_modules`, `vendor`, uploads/user records, `.git` internals, this generated vault, local credential files, lockfile bodies, and the vendored QR implementation. Database bootstraps were inspected structurally without copying connection values. Secret values and user records are not stored in this note.

## Inventory

| Repository-relative file | Lines | Structural evidence |
| --- | ---: | --- |
| `.gitignore` | 26 | Content/structure inventory |
| `.htaccess` | 3 | Content/structure inventory |
| `account/dashboard.php` | 4232 | Functions: `saveReminder`, `closeSellerMobileMenu`, `setActiveNavigation`, `updatePageTitle`, `executeScripts` … |
| `account/forgot_password.php` | 341 | Content/structure inventory |
| `account/login.php` | 296 | Content/structure inventory |
| `account/logout.php` | 54 | Content/structure inventory |
| `account/pages/dashboard.php` | 1076 | Functions: `dashboardEscape`, `dashboardMoney`, `dashboardStatusLabel`, `dashboardStatusClass`, `dashboardDate` … |
| `account/pages/offers.php` | 442 | Functions: `sellerOfferEscape`, `sellerOfferMoney`, `sellerOfferDate` |
| `account/pages/profile.php` | 605 | Functions: `profileEscape`, `profilePicturePath` |
| `account/pages/settings.php` | 630 | Functions: `settingsEscape`, `bindForm`, `submitTwoFactorForm` |
| `account/pages/submission.php` | 2492 | Functions: `submissionEscape`, `escapeHtml`, `money`, `statusClass`, `statusLabel` … |
| `account/pages/submissions.php` | 1478 | Functions: `submissionEscape`, `submissionStatusLabel`, `submissionStatusClass`, `submissionFormatDate`, `submissionMoney` |
| `account/pages/trade_history.php` | 945 | Functions: `tradeHistoryStatusLabel`, `tradeHistoryFormatDate`, `openModal`, `closeModal` |
| `account/pages/transaction-chat-window.php` | 11 | Content/structure inventory |
| `account/pages/transaction.php` | 1246 | Functions: `transactionDetailEscape`, `transactionDetailDate`, `transactionDetailStatusLabel`, `transactionDetailStatusClass`, `transactionDetailOfferStatusClass` … |
| `account/pages/transactions.php` | 962 | Actions: `today`, `this_week`, `this_month`, `last_month`, `custom` |
| `account/register.php` | 400 | Content/structure inventory |
| `account/reset_password.php` | 179 | Content/structure inventory |
| `account/social_signup.php` | 300 | Functions: `evaluatePassword`, `updateStrength` |
| `account/submissions.php` | 1911 | Functions: `submissionEscape`, `submissionStatusLabel`, `submissionStatusClass`, `submissionFormatDate`, `submissionMoney` |
| `admin/dashboard.php` | 1692 | Functions: `updateNotificationBadge`, `refreshAdminNotificationCounts`, `markAdminNotificationsSeen`, `buildPageUrl`, `setActiveNavigation` … |
| `admin/login.php` | 790 | Content/structure inventory |
| `admin/logout.php` | 54 | Content/structure inventory |
| `admin/pages/buying_rates.php` | 1246 | Functions: `rateTypeLabel`, `rateTypeClass`, `ratePesoAmount`, `rateUpdatedLabel`, `rateArchivedLabel` … |
| `admin/pages/dashboard.php` | 450 | Content/structure inventory |
| `admin/pages/offers.php` | 1050 | Functions: `offerSubmissionStatusLabel`, `offerSubmissionStatusClass`, `offerNegotiationLabel`, `offerSenderLabel`, `offerSenderClass` … |
| `admin/pages/reports.php` | 331 | Functions: `reportEscape`, `reportDateIsValid`, `reportCsvValue` |
| `admin/pages/settings.php` | 891 | Functions: `showMessage`, `request`, `makeButton`, `ensureCategoryOption`, `renderRules` … |
| `admin/pages/submission_review.php` | 3220 | Functions: `e`, `peso`, `statusLabel`, `statusClass`, `damageLabel` … |
| `admin/pages/submissions.php` | 1115 | Functions: `submissionStatusLabel`, `submissionStatusClass`, `sellingMethodLabel`, `pesoAmount`, `submissionDate` … |
| `admin/pages/trade_items.php` | 569 | Functions: `tradeRequirementTypeLabel`, `requirementTypeLabel`, `buildRequirementRow`, `syncRequirementsJson`, `addRequirementRow` … |
| `admin/pages/trade_requests.php` | 239 | Functions: `tradeRequestStatusLabel`, `tradeRequestFormatDate` |
| `admin/pages/transaction-chat-window.php` | 14 | Content/structure inventory |
| `admin/pages/transaction_review.php` | 1199 | Actions: `completed`, `paid`, `successful`, `accepted`, `pending`, `processing`, `cancelled`, `failed`, `rejected` |
| `admin/pages/transactions.php` | 1393 | Functions: `transactionStatusLabel`, `transactionStatusClass`, `transactionPeso`, `transactionDate`, `applyTransactionFilters` … |
| `assets/css/admin_buying_rates.css` | 944 | Content/structure inventory |
| `assets/css/admin_dashboard.css` | 1799 | Content/structure inventory |
| `assets/css/admin_login.css` | 459 | Content/structure inventory |
| `assets/css/admin_operations.css` | 1042 | Content/structure inventory |
| `assets/css/admin_submission_review.css` | 1891 | Content/structure inventory |
| `assets/css/admin_submissions.css` | 794 | Content/structure inventory |
| `assets/css/admin_theme.css` | 1093 | Content/structure inventory |
| `assets/css/admin_trade_items.css` | 1387 | Content/structure inventory |
| `assets/css/admin_transactions.css` | 912 | Content/structure inventory |
| `assets/css/loader.css` | 179 | Content/structure inventory |
| `assets/css/password_visibility.css` | 61 | Content/structure inventory |
| `assets/css/public_theme.css` | 992 | Content/structure inventory |
| `assets/css/sell.css` | 6754 | Content/structure inventory |
| `assets/css/seller_dashboard.css` | 10573 | Content/structure inventory |
| `assets/css/seller_login.css` | 381 | Content/structure inventory |
| `assets/css/seller_register.css` | 424 | Content/structure inventory |
| `assets/css/seller_sell_modal.css` | 1655 | Content/structure inventory |
| `assets/css/seller_submission.css` | 3286 | Content/structure inventory |
| `assets/css/seller_theme.css` | 710 | Content/structure inventory |
| `assets/css/seller_tracking.css` | 2080 | Content/structure inventory |
| `assets/css/setup_super_admin.css` | 142 | Content/structure inventory |
| `assets/css/social_auth.css` | 479 | Content/structure inventory |
| `assets/css/terms_modal.css` | 337 | Content/structure inventory |
| `assets/css/transaction_chat.css` | 5137 | Content/structure inventory |
| `assets/css/transaction_review.css` | 626 | Content/structure inventory |
| `assets/css/vnz_dialog.css` | 136 | Content/structure inventory |
| `assets/js/admin-theme.js` | 46 | Functions: `getTheme`, `setTheme`, `syncSelector` |
| `assets/js/floating-messages.js` | 1740 | Functions: `unreadLabel`, `setUnreadBadge`, `updateUnreadBadges`, `initialsFor`, `presenceLabel` … |
| `assets/js/loader.js` | 162 | Functions: `applyVariantFromHtml`, `clearFailSafe`, `show`, `hide`, `hideAfterMinimumDelay` |
| `assets/js/password-strength.js` | 89 | Functions: `evaluatePassword`, `updateStrength` |
| `assets/js/password-visibility.js` | 70 | Functions: `enhancePasswordInput`, `setVisibility`, `enhancePasswordInputs` |
| `assets/js/public-theme.js` | 53 | Functions: `currentTheme`, `updateButton`, `setTheme`, `initialize` |
| `assets/js/seller-language.js` | 381 | Functions: `translateNode`, `translateElement`, `start` |
| `assets/js/seller-theme.js` | 52 | Functions: `currentTheme`, `updateTheme`, `syncPreference`, `syncPreferencesIn` |
| `assets/js/seller.portal.js` | 338 | Functions: `setActiveNav`, `updateUrl`, `scrollTop`, `loadSubmissions`, `loadDashboard` |
| `assets/js/terms-modal.js` | 74 | Functions: `prefersReducedMotion`, `closeDialog` |
| `assets/js/transaction-chat.js` | 5789 | Functions: `registerVnzMediaStream`, `unregisterVnzMediaStream`, `vnzOwnedMediaTrackSummary`, `ensureCallOwnerHost`, `releaseRetainedCallOwner` … |
| `assets/js/vnz-dialog.js` | 142 | Functions: `createDialog`, `finishRequest`, `showNextRequest`, `enqueue` |
| `backend/admin_activity.php` | 17 | Functions: `logAdminActivity` |
| `backend/admin_respond_offer.php` | 537 | Functions: `findEnumValue`, `generateTransactionReference` |
| `backend/admin_respond_submission.php` | 430 | Functions: `jsonResponse`, `generateTransactionReference` |
| `backend/admin_respond_transaction_delivery.php` | 196 | Functions: `adminTransactionDeliveryRespond` |
| `backend/admin_totp.php` | 113 | Functions: `generateAdminTotpSecret`, `decodeAdminTotpSecret`, `matchAdminTotpCode`, `buildAdminTotpUri`, `generateAdminTotpRecoveryCodes` … |
| `backend/auth.php` | 139 | Functions: `isAdminLoggedIn`, `requireAdminLogin`, `getCurrentAdmin`, `hasPermission`, `requirePermission` |
| `backend/chat_attachment.php` | 235 | Functions: `chatAttachmentErrorResponse` |
| `backend/chat_attachments.php` | 572 | Functions: `chatAttachmentMaxBytes`, `chatDocumentMaxBytes`, `chatVoiceMaxBytes`, `chatVoiceMaxDurationSeconds`, `chatIniSizeLimitBytes` … |
| `backend/chat_call_sessions.php` | 403 | Functions: `chatCallParticipantMatches`, `chatCallDiagnosticState`, `chatCallSummaryContent`, `chatPersistCallEvent`, `chatCallInviteCanBeRelayed` … |
| `backend/chat_call_signal.php` | 243 | Content/structure inventory |
| `backend/chat_conversations.php` | 437 | Actions: `revoke_guest_access`, `trade_requests`, `open_trade_request`, `open`, `contacts`, `list` |
| `backend/chat_messages.php` | 1641 | Actions: `history`, `send`, `edit`, `delete`, `reaction`, `typing`, `read`, `delivered` |
| `backend/chat_presence.php` | 35 | Content/structure inventory |
| `backend/chat_presence_store.php` | 164 | Functions: `chatLogAuthenticationTiming`, `chatPresenceSessionHash`, `chatRegisterPresenceSession`, `chatHeartbeatPresenceSession`, `chatEndPresenceSession` … |
| `backend/chat_scaledrone.php` | 570 | Functions: `chatScaledroneConfig`, `chatScaledroneBase64Url`, `chatScaledroneSignJwt`, `chatScaledroneRoomName`, `chatScaledroneConversationRoomName` … |
| `backend/chat_scaledrone_auth.php` | 92 | Content/structure inventory |
| `backend/chat_service.php` | 2284 | Functions: `chatParticipantDisplayNameForViewer`, `chatGetConversationPresence`, `chatJsonResponse`, `chatRequireMethod`, `chatRequireCsrf` … |
| `backend/counter_offer.php` | 618 | Functions: `normalizeReference`, `normalizeMobile`, `getSentStatus` |
| `backend/csrf.php` | 86 | Functions: `generateCsrfToken`, `verifyCsrfToken` |
| `backend/database.php` | 79 | Content/structure inventory |
| `backend/email_verification.php` | 283 | Functions: `resolveVerificationBaseUrl`, `getVerificationMailConfig`, `sendVnzEmail`, `sendEmailVerification` |
| `backend/get_public_rates.php` | 61 | Content/structure inventory |
| `backend/get_unseen_admin_counts.php` | 161 | Content/structure inventory |
| `backend/get_unseen_results.php` | 141 | Content/structure inventory |
| `backend/global_chat_moderation.php` | 396 | Actions: `save_rule`, `delete_rule`, `review_case` |
| `backend/global_chat_moderation_service.php` | 138 | Functions: `globalChatModerationAppliesToConversationType`, `globalChatModerationSchemaAvailable`, `globalChatModerationNormalize`, `globalChatModerationRuleMatches`, `globalChatModerationMatches` … |
| `backend/guest_submission_claim.php` | 10 | Content/structure inventory |
| `backend/guest_submission_link.php` | 422 | Functions: `guestSubmissionLinkLockSuffix`, `guestSubmissionLinkAuditSchemaAvailable`, `guestSubmissionResponsibleAdminId`, `guestSubmissionSellerDisplayName`, `linkVerifiedGuestSubmissions` … |
| `backend/login.php` | 534 | Functions: `isAjaxRequest`, `sendJsonResponse`, `sendLoginFailure`, `redirectToDashboard`, `showMessage` |
| `backend/mail_config.example.php` | 18 | Content/structure inventory |
| `backend/manage_buying_rate.php` | 485 | Actions: `create`, `update`, `toggle`, `archive`, `restore`, `delete_permanent` |
| `backend/manage_trade.php` | 704 | Actions: `create_item`, `update_item`, `delete_item`, `toggle_active`, `toggle_featured`, `update_sort`, `create_requirement`, `update_requirement`, `delete_requirement`, `reorder_requirements` |
| `backend/mark_admin_notifications_seen.php` | 173 | Content/structure inventory |
| `backend/mark_result_seen.php` | 137 | Content/structure inventory |
| `backend/offer_response_authorization.php` | 79 | Functions: `grantGuestOfferResponseAccess`, `offerResponseSessionIsAuthorized`, `offerResponseOfferBelongsToSubmission`, `offerResponseOfferIsAvailable` |
| `backend/pagination.php` | 118 | Functions: `vnzPaginationState`, `vnzPaginationClamp`, `vnzRenderPagination` |
| `backend/password_policy.php` | 28 | Functions: `vnzPasswordStrength` |
| `backend/propose_transaction_delivery.php` | 164 | Functions: `proposeTransactionDeliveryRespond` |
| `backend/register.php` | 224 | Functions: `redirectWithError` |
| `backend/remember_login.php` | 229 | Functions: `vnzRememberCookieOptions`, `vnzRememberCookieSelector`, `vnzClearRememberCookie`, `vnzRevokeRememberLoginCookie`, `vnzRevokeRememberLoginTokens` … |
| `backend/request_email_verification.php` | 128 | Content/structure inventory |
| `backend/respond_offer.php` | 724 | Functions: `normalizeReference`, `normalizeMobile`, `findEnumValue`, `generateTransactionReference` |
| `backend/scaledrone_config.example.php` | 15 | Content/structure inventory |
| `backend/seller_2fa.php` | 192 | Actions: `dismiss_reminder`, `set_login_requirement`, `setup`, `confirm_setup`, `disable` |
| `backend/send_offer.php` | 585 | Functions: `jsonResponse` |
| `backend/session.php` | 56 | Functions: `clearSellerSessionState`, `clearAdminSessionState` |
| `backend/setup_super_admin.php` | 392 | Content/structure inventory |
| `backend/social_auth.php` | 164 | Actions: `start` |
| `backend/social_auth_config.example.php` | 11 | Content/structure inventory |
| `backend/social_auth_service.php` | 280 | Functions: `vnzSocialAuthConfigErrors`, `vnzSocialAuthProvider`, `vnzSocialAuthBuildAuthorizationUrl`, `vnzSocialAuthHttpRequest`, `vnzSocialAuthFetchProfile` … |
| `backend/social_signup_complete.php` | 169 | Functions: `vnzSocialSignupError` |
| `backend/submit_submission.php` | 1729 | Functions: `redirectWithError`, `cleanText`, `generateReferenceNumber`, `getUploadedFiles` |
| `backend/track_submission.php` | 725 | Functions: `trackGuestChatRecoveryIsRateLimited`, `trackGuestChatRecoveryWasAttempted`, `trackRecoverGuestChat`, `normalizeReference`, `normalizeMobile` |
| `backend/trade_requests.php` | 248 | Actions: `admin_update_status` |
| `backend/transaction_delivery_workflow.php` | 51 | Functions: `vnzTransactionDeliveryMethodIsAllowed`, `vnzTransactionDeliverySellerMayPropose`, `vnzTransactionDeliveryAdminMayRespond`, `vnzTransactionDeliveryAdminDecisionStatus`, `vnzTransactionDeliveryMethodLabel` |
| `backend/update_admin_settings.php` | 356 | Actions: `profile`, `password` |
| `backend/update_password.php` | 222 | Content/structure inventory |
| `backend/update_profile.php` | 344 | Content/structure inventory |
| `backend/update_seller_language.php` | 80 | Content/structure inventory |
| `backend/update_seller_notification_preferences.php` | 106 | Content/structure inventory |
| `backend/verify_email.php` | 151 | Functions: `redirectVerificationResult` |
| `backend/verify_seller_2fa.php` | 162 | Content/structure inventory |
| `composer.json` | 5 | Content/structure inventory |
| `database/migrations/20261006_add_admin_2fa_and_activity_log.sql` | 31 | Tables: `admins`, `admin_activity_logs`, `admin_totp_recovery_codes` |
| `database/migrations/20261006_add_admin_totp_login_preference.sql` | 3 | Tables: `admins` |
| `database/migrations/20261006_add_seller_2fa.sql` | 21 | Tables: `users`, `seller_totp_login_attempts` |
| `database/migrations/20261006_add_seller_totp_login_preference.sql` | 2 | Tables: `users` |
| `database/migrations/20261006_create_admin_password_reset_attempts.sql` | 15 | Tables: `admin_password_reset_attempts` |
| `database/migrations/20261006_create_email_verification_tokens.sql` | 14 | Tables: `email_verification_tokens` |
| `database/migrations/20261006_create_seller_notification_preferences.sql` | 12 | Tables: `seller_notification_preferences` |
| `database/migrations/20261006_create_seller_password_reset_tokens.sql` | 25 | Tables: `seller_password_reset_attempts` |
| `database/migrations/20261008_add_chat_message_features.sql` | 39 | Tables: `messages`, `message_reactions` |
| `database/migrations/20261008_create_messaging_tables.sql` | 61 | Tables: `conversations`, `conversation_participants`, `messages` |
| `database/migrations/20261009_add_presence_and_delivery_receipts.sql` | 36 | Tables: `chat_presence_sessions`, `message_deliveries` |
| `database/migrations/20261010_add_chat_image_attachments.sql` | 2 | Tables: `messages` |
| `database/migrations/20261010_add_pdf_attachment_metadata.sql` | 3 | Tables: `messages` |
| `database/migrations/20261010_add_presence_logout_timestamp.sql` | 2 | Tables: `chat_presence_sessions` |
| `database/migrations/20261010_add_seller_email_to_submissions.sql` | 2 | Tables: `submissions` |
| `database/migrations/20261010_add_voice_message_type.sql` | 4 | Tables: `messages` |
| `database/migrations/20261010_create_chat_call_sessions.sql` | 43 | Tables: `chat_call_sessions` |
| `database/migrations/20261011_add_guest_submission_chat.sql` | 42 | Tables: `conversations`, `conversation_participants`, `messages`, `message_reactions`, `message_deliveries`, `chat_presence_sessions`, `guest_chat_identities` |
| `database/migrations/20261012_create_guest_submission_claims.sql` | 27 | Tables: `guest_submission_claims` |
| `database/migrations/20261012_extend_guest_submission_claim_proof.sql` | 3 | Tables: `guest_submission_claims` |
| `database/migrations/20261026_add_video_chat_message_type.sql` | 4 | Tables: `messages` |
| `database/migrations/20261026_create_remember_login_tokens.sql` | 16 | Tables: `remember_login_tokens` |
| `database/migrations/20261027_add_global_chat_fuckyou_rule.sql` | 4 | Content/structure inventory |
| `database/migrations/20261027_create_global_chat_moderation.sql` | 105 | Tables: `global_chat_moderation_rules`, `global_chat_moderation_cases` |
| `database/migrations/20261027_create_trade_request_chats.sql` | 14 | Tables: `trade_request_conversations` |
| `database/migrations/20261112_create_user_oauth_accounts.sql` | 16 | Tables: `user_oauth_accounts` |
| `database/migrations/20261114_add_transaction_delivery_choice.sql` | 16 | Tables: `transactions` |
| `database.php` | 75 | Content/structure inventory |
| `docs/social-login-development.md` | 71 | Content/structure inventory |
| `includes/floating-messages.php` | 208 | Content/structure inventory |
| `includes/loader-body.php` | 10 | Content/structure inventory |
| `includes/loader-head.php` | 16 | Content/structure inventory |
| `includes/social_auth_buttons.php` | 37 | Content/structure inventory |
| `includes/terms_modal.php` | 53 | Content/structure inventory |
| `includes/transaction-chat-window.php` | 381 | Content/structure inventory |
| `includes/transaction-chat.php` | 555 | Content/structure inventory |
| `index.php` | 4 | Content/structure inventory |
| `package.json` | 5 | Content/structure inventory |
| `scripts/configure-social-login.ps1` | 158 | Content/structure inventory |
| `scripts/validate-social-auth-config.php` | 46 | Functions: `runSocialAuthConfigValidation` |
| `sell.php` | 4651 | Functions: `renderCarousel`, `moveCarousel`, `setActiveSection`, `syncBodyScrollLock`, `showSellerModal` … |
| `seller/tracking.php` | 2333 | Functions: `escapeHtml`, `showMessage`, `hideMessage`, `money`, `statusClass` … |
| `tests/chat_attachment_message_type_test.php` | 59 | Functions: `assertChatAttachmentType` |
| `tests/chat_scaledrone_auth_test.php` | 204 | Functions: `assertChatAuthorization`, `decodeChatTokenPayload`, `chatRoomContext` |
| `tests/configure_social_login_test.ps1` | 39 | Content/structure inventory |
| `tests/global_chat_moderation_test.php` | 101 | Functions: `assertGlobalModeration` |
| `tests/guest_submission_link_test.php` | 399 | Functions: `testAssert`, `createTestDatabase`, `seedEligibleSubmission`, `runTest` |
| `tests/offer_response_authorization_test.php` | 140 | Functions: `offerAuthorizationAssert` |
| `tests/social_auth_service_test.php` | 60 | Functions: `assertSocialAuth` |
| `tests/transaction_delivery_workflow_test.php` | 86 | Functions: `transactionDeliveryAssert` |

## Migration inventory

- `database/migrations/20261006_add_admin_2fa_and_activity_log.sql`
- `database/migrations/20261006_add_admin_totp_login_preference.sql`
- `database/migrations/20261006_add_seller_2fa.sql`
- `database/migrations/20261006_add_seller_totp_login_preference.sql`
- `database/migrations/20261006_create_admin_password_reset_attempts.sql`
- `database/migrations/20261006_create_email_verification_tokens.sql`
- `database/migrations/20261006_create_seller_notification_preferences.sql`
- `database/migrations/20261006_create_seller_password_reset_tokens.sql`
- `database/migrations/20261008_add_chat_message_features.sql`
- `database/migrations/20261008_create_messaging_tables.sql`
- `database/migrations/20261009_add_presence_and_delivery_receipts.sql`
- `database/migrations/20261010_add_chat_image_attachments.sql`
- `database/migrations/20261010_add_pdf_attachment_metadata.sql`
- `database/migrations/20261010_add_presence_logout_timestamp.sql`
- `database/migrations/20261010_add_seller_email_to_submissions.sql`
- `database/migrations/20261010_add_voice_message_type.sql`
- `database/migrations/20261010_create_chat_call_sessions.sql`
- `database/migrations/20261011_add_guest_submission_chat.sql`
- `database/migrations/20261012_create_guest_submission_claims.sql`
- `database/migrations/20261012_extend_guest_submission_claim_proof.sql`
- `database/migrations/20261026_add_video_chat_message_type.sql`
- `database/migrations/20261026_create_remember_login_tokens.sql`
- `database/migrations/20261027_add_global_chat_fuckyou_rule.sql`
- `database/migrations/20261027_create_global_chat_moderation.sql`
- `database/migrations/20261027_create_trade_request_chats.sql`
- `database/migrations/20261112_create_user_oauth_accounts.sql`
- `database/migrations/20261114_add_transaction_delivery_choice.sql`

Migration files describe intended changes. This review did not apply them or confirm a live schema.

## Git history observed

Commit subjects are historical context, not acceptance-test evidence.

| Commit | Date | Recorded subject |
| --- | --- | --- |
| `e5728d2` | 2026-11-13 | Update project and social signup UI |
| `130d9f5` | 2026-10-27 | core changes, settings, permissions, control, and overal UI |
| `030d21f` | 2026-10-26 | permissions and layout |
| `42e7035` | 2026-10-13 | seller to seller call and video call, ui enhancement |
| `f133b84` | 2026-10-12 | Remove temporary calling regression test |
| `ae0595e` | 2026-10-12 | Integrate guest claim and messaging updates |
| `60c6ff3` | 2026-10-12 | transaction/communication after accepted offer between guest seller and admin |
| `21aaed5` | 2026-10-10 | Call new features and changes |
| `d463163` | 2026-10-10 | File attachment, and voice records attachment in message module |
| `0f269e8` | 2026-10-09 | new features in message module |
| `2ece6a0` | 2026-10-08 | New features in messaging module |
| `3a0bc08` | 2026-10-08 | Chat global, admin, and private |
| `db64e5b` | 2026-10-06 | 2FA implemented, forgot password both on seller and admin portal, and settings modifications |
| `bed6c94` | 2026-10-06 | trade, and email verification and also seller settings |
| `8ba1cf6` | 2026-10-05 | modifying trade features and modules |
| `5c4fe4e` | 2026-10-05 | trade scrap board or broken phones to something useful |
| `6c17a0f` | 2026-10-05 | admin modules done, and light adjustment on tag |
| `2bb534d` | 2026-09-28 | loading overlay, and dot navigation |
| `6eeae6b` | 2026-09-28 | fixed landing page layout |
| `bf6a19e` | 2026-09-24 | landing page fixing layout |
| `85e9bc9` | 2026-09-22 | Buying Rates page with delete and archive |
| `b5e5dc5` | 2026-09-21 | Fix Submission Page, Badge Notification for ADMIN/BUYER and Pop-up Notification for Seller |
| `60d1b10` | 2026-09-17 | Fix naming Item Type if WHOLE PHONE or SCRAP BOARD |
| `4c9041c` | 2026-09-16 | Initial backup - VNZ TECH |

[[40 Knowledge/VNZ-TECH System Map]] · [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]] · [[Home]]
