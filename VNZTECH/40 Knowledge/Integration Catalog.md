---
type: integration-catalog
status: active
created: 2026-11-19
updated: 2026-11-20
tags: [engineering, agents, apis, third-party]
---

# Integration Catalog

Shared reference nina Nova, Forge, Aegis, Cipher, Iris, Lyra, Beacon at Quinn; other agents read only relevant entries. Owner requested on 2026-11-19 that API/tool choices and limits become reusable agent knowledge. Actual source/task evidence takes precedence over stale summaries. This note records decisions; it does not install anything.

## ID/selfie face comparison — face-api.js 0.22.2

- Decision: approved ID-verification v2 in [[90 System/Tasks/Records/TASK-20261119-004]]. Self-hosted browser library/models; no paid API account or per-check service. Cost of app hosting/storage remains separate.
- Source/license: [tagged package](https://github.com/justadudewhohacks/face-api.js/blob/0.22.2/package.json), [MIT license](https://github.com/justadudewhohacks/face-api.js/blob/0.22.2/LICENSE). Version embeds TensorFlow.js core 1.7.0: pinned legacy dependency, compatibility/security maintenance must be reassessed before upgrades or production claims.
- Purpose: detect faces, landmarks and descriptor distance for a known ID/selfie pair. Use only tiny-face detector, 68-point landmarks and recognition models. Detection of exactly one face improves capture feedback; a distance is not an ID-authenticity result, calibrated probability or certified liveness proof.
- Data flow: model inference inside the page; submitted ID/selfie goes only to the app's private encrypted evidence storage for authorized human review. No descriptors in Obsidian, telemetry, localStorage or permanent face gallery. Browser scores cannot approve; server records the authorized admin's exact evidence-version decision.
- Files/provenance: `assets/vendor/face-api/manifest.json` records official tagged URLs, byte sizes and SHA-256 for bundle/license/models; `tools/fetch_identity_assets.py` retrieves only those pinned files without executing them. Nine assets downloaded (7,688,239 bytes). `node tests/identity_models_browser_test.mjs` passed integrity checks and loaded all three real models on Chrome/CPU; a blank canvas produced zero faces. This validates loading/inference availability, not biometric quality. `tests/identity_ui_browser_test.mjs` passes 12 themed viewport cases plus mocked quality/retry and synthetic camera lifecycle checks.
- Alternatives/boundary: a paid KYC/document-authenticity service or trusted server matcher would be a separate proposal. Login upgrade is deferred in [[90 System/Tasks/Records/TASK-20261119-005]]; do not reuse browser similarity alone as login proof.

## Camera capture — browser MediaDevices

[getUserMedia documentation](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia), reviewed 2026-11-19. Requires user permission and a secure context; localhost on the computer is different from a phone opening an HTTP LAN IP. Use HTTPS for real-phone access, stop all camera tracks after capture/cancel/leave, offer permission/model/quality retry feedback. Responsive viewport checks are separate from actual device/camera tests. Owners: Lyra/Iris/Aegis; current task 004.

## Private encryption — PHP OpenSSL AES-GCM

PHP 8.2.12/OpenSSL observed locally; sodium unavailable. V2 selects authenticated AES-GCM encryption, random filenames and key outside webroot, in addition to ownership/reviewer authorization and retention. Encryption does not replace access control, protected backups or key management. Store configuration/key outside this vault/repository. Owners: Aegis/Forge/Vega; actual activation/cleanup evidence belongs in task 004.

## How to add an entry

Record feature/purpose, owner roles, approved-vs-candidate status, official sources/access date, exact version/license, data destination/retention, auth/key handling without values, limits/recovery, alternatives and actual checks. Mark unknowns explicitly. Future runners should verify changed external facts instead of assuming this dated catalog remains current.

[[40 Knowledge/Engineering Lessons]] · [[40 Knowledge/Knowledge]] · [[90 System/Agent Directory]]

Implementation evidence and local deployment limits: [[90 System/Runs/2026-11-19 163005 Identity Verification v2]].

## Local activation evidence - 2026-11-19 17:07

Owner Administrator setup resolved local scheduling. Independent readback confirms enabled form/API, private health and daily03 cleanup lastresult0. Library/model tests remain as recorded; no personal ID/device accuracy claim. [[90 System/Runs/2026-11-19 163005 Identity Verification v2]]

## Automatic matching candidates - 2026-11-19

Owner requests automation without admin review; v3 not yet approved. Existing face-api.js bundle can load all three models and infer on a blank Tensor3D in Node25 CPU (read-only probe760ms), avoiding native canvas installation for PHP GD tensor input. No real-photo accuracy/latency calibration yet. Trusted server result, versioned threshold/evidence binding and no manual/client bypass are required. Proposed source: [[90 System/Tasks/Records/TASK-20261119-004]].

Face++ Compare is an alternative cloud service returning confidence/thresholds. Its [global free plan](https://www.faceplusplus.com/v2/pricing/) is advertised without credit card; exact regional/account quota and contract unverified (console docs loading shell). [Compare](https://www.faceplusplus.com/face-comparing/), [official key/secret SDK](https://github.com/FacePlusPlus/facepp-python-sdk/blob/master/PythonSDK/facepp.py), [privacy/data processing](https://www.faceplusplus.com/privacy-policy/). Requires provider credentials and external image processing; not selected, configured, invoked or authorized to receive existing photos. No paid fallback or credential values in notes. Similarity is distinct from ID authenticity/liveness.

### Dual-engine requirement selected - 2026-11-19 17:34

Owner subsequently selected local AND Face++ on the actual ID portrait/camera selfie, retry/block on either fail/unavailable, and confirmed no Face++ account/keys. This supersedes the earlier candidate-only selection status; concrete [[90 System/Tasks/Records/TASK-20261119-004#Concrete implementation plan v4 - dual automatic matching (approved)|v4 implementation]] still awaits approval. No adapter/config/account/API call exists yet; live manual v2 is unchanged.

Proposed data flow: server validates originals -> deterministic ID-portrait/selfie crop pair -> local matcher and server-only Face++ Compare on identical crop bytes -> both independent passes tied to original/crop hashes and policy. Cropping limits external document-text exposure but remains biometric processing needing fresh informed consent. No profile photo, raw response/token storage, old-photo backfill or paid fallback. Local distance and provider confidence are different scales; never average or present as accuracy percentages. Keep keys outside webroot, TLS verification on, region endpoint allowlisted and errors fail closed. Account/region/quota/strict threshold contract and real matching validation remain activation blockers. Staged mock integration is separate from live provider readiness. Owners: Forge/Aegis/Nova/Quinn.

### Face-only replacement requested - 2026-11-19 21:28

Owner now requests a face step with photo inside existing submissions and removal of ID/admin module. [[90 System/Tasks/Records/TASK-20261119-004#Concrete implementation plan v6 - selfie inside submissions (approved)|V6 proposal]] reuses pinned face-api.js only for local single-image presence detection; no Face++/ID/reference, external images, new dependency or identity-match claim. Private submission-linked encryption/admin authorization/retention remain required. Existing two-image worker needs a real single-image action. Current V6 files are empty; proposal/source/readiness are distinct. Owners: Forge/Aegis/Iris/Quinn; no new API call or implementation performed.

### Active local single-image face presence - 2026-11-19 21:53

Approved and locally active v6 replaces the earlier dual-ID proposal for new submissions. Reuses pinned MIT face-api.js0.22.2/TensorFlow CPU bundle and tiny-face detector in a bounded Node single-image action, with PHP GD JPEG/tensor validation. Model/license/SHA provenance remains assets/vendor/face-api/manifest.json. Purpose is one visible face, not identity/reference matching, OCR or liveness. No Face++ key/quota/consent flags or external image request is needed or used. Private JPEG is encrypted and exact-submission-bound; only active authorized admin/role/view_submissions may read before expiry. No descriptor/gallery or personal image in knowledge notes.

Actual evidence:33 worker checks including real single-image blank refusal,63 helper, isolated route/storage/authorization tests, themed Chrome and11 live anonymous probes. Positive route inference mocked; actual camera/physical-device acceptance remains unverified. New migration/trigger and local mode switch applied after encrypted private backup/health. Roles Forge/Aegis/Iris/Quinn reuse this entry; previous v2/v4 sections are dated history. [[90 System/Runs/2026-11-19 213300 Submission Selfie v6]]

### On-demand private selfie viewer v6.1 - 2026-11-19

Uses built-in browser HTMLDialogElement/showModal, Fetch, AbortController and Blob URL/decode with the existing PHP submission_face_photo endpoint; no new library, paid API, external image destination or dependency license. Image bytes are requested only after View submission selfie, with current same-origin session/no-store/no-referrer; decoded URL is memory-only and revoked on exit/replacement. Native top layer avoids clipping under the existing transformed review modal. Actual-template synthetic Chrome/actual CSS and protected-route checks are recorded in [[90 System/Runs/2026-11-19 222413 Submission Selfie Viewer v6.1]]; physical phone/other-browser/live authenticated acceptance remain separate. Iris/Quinn/Aegis reuse this entry for private-photo UI decisions.

### Bounded photo drag v6.2 - 2026-11-19

Adds built-in Pointer Events/setPointerCapture and keyboard pan to the existing viewer; no SDK/library/service or external data destination. Preserve stage geometry using bounded internal offsets behind overflow:hidden. Capture on the image button, distinguish6px drag from click, disable native image drag and set touch-action:none on both zoomed stage/button. Iris/Quinn reuse [[90 System/Runs/2026-11-19 224454 Submission Selfie Drag v6.2]] for actual-input results/limits and interruption lessons. Trusted mouse/emulated touch drag is distinct from physical-phone/native-tap acceptance; a plain-button control reproduced missing CDP compatibility clicks, so this fixture explicitly labels simulated click completion instead of changing product behavior to fit the test.

## Seller topbar internal reader / existing chat - 2026-11-20

Purpose: persistent seller notification/profile reader plus placement adapter for the existing chat. Internal PHP/PDO GET backend/seller_topbar_api.php; no new external API, package, version/license or provider account. Browser sends existing same-origin session cookie; response contains own profile display name/avatar and bounded submission references/status/times. No ID/selfie data or message bodies added. Existing Scaledrone/chat/call integrations remain unchanged; the topbar does not send new third-party data. Alternative rejected for v1: another notification database/provider; existing latest-offer and result-seen state suffices. Limits:30s attention-feed refresh, latest20 rows, pending offers are outstanding not unread, current chat window cap remains. Actual helper/route/browser evidence and live-provider limits: [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]].

## Seller topbar internal reader / existing chat - 2026-11-20

Persistent notification/profile reader: internal PHP/PDO GET backend/seller_topbar_api.php. No new external API/package/license/provider account. Existing same-origin session cookie authorizes own display name/avatar and bounded submission references/status/times; no ID/selfie or message-body data added. Existing chat/Scaledrone/call integrations remain unchanged. Alternative deferred: separate notification database/provider; latest-offer/result-seen state suffices. Limits:30s attention feed, latest20 rows, pending offers are outstanding not unread, existing chat window cap.8 helper/4 isolated route/79 browser assertions pass; no live provider evidence. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v3 layout amendment - 2026-11-20

No new API/package/provider/browser permission: fullscreen is viewport CSS within the application, without the browser Fullscreen API. Existing transaction chat endpoints, session authorization, message/call provider and data sent remain unchanged. Seller-only geometry/state adjustment; actual PHP widget rendered with synthetic data in isolated fixtures, not an authenticated production route or provider test. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v4 internal UI/state - 2026-11-20

No new API/package/provider/license or data destination. Native DOM/Pointer Events/keyboard handling render bounded open-window overflow; CSS and actual-row measurement bound the contact list. Existing chat endpoints/cookies, message/call transport and attachment APIs unchanged. Maximum10 mounted seller widgets is a UI resource bound, not a server conversation/account limit. Alternative avoided: destroying prior windows to satisfy display capacity; keep nodes/drafts and select2/1 visible slots instead. Open-window state is current-page memory, not new cross-reload persistence. Actual621 full/187 focused fixture assertions; no live provider claim. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]

### Chat v5 local avatar rail / layout - 2026-11-20

No new package/API/provider/account/license or external data destination. Native DOM/CSS responsive layout and rail reuse current authorized widget's local peer-picture value; only safe existing uploads paths are rendered, with initials fallback for remote/unsafe/missing URLs and image errors. Existing local image GET is the only extra asset read; no profile-fetch endpoint or biometric data. Existing chat transport/unread/call APIs unchanged. Display slots1..4 differ from10 mounted cap. Screenshot is an owner-provided visual reference only, no Facebook SDK/connection.755 synthetic assertions include one local SVG fixture asset, unsafe URL refusal and available-width layout; no real avatar/provider verification claimed. [[90 System/Tasks/Records/TASK-20261120-010]] - [[90 System/Runs/2026-11-20 092635 Seller Topbar v1]]


## Git credential cleanup v1 - 2026-11-20T11:24:01+08:00

No new package/API/provider/data destination. Existing Google/Facebook OAuth setup uses native PowerShell prompts and ignored private PHP configuration. Placeholder keys restored; installed Git/Python used locally for targeted known-value cleanup. No external upload/provider test; rotation and live local configuration unverified. [[90 System/Tasks/Records/TASK-20261120-011]].


## Mobile Messages v1 - 2026-11-20T11:49:24+08:00

No new API/provider/package/license/data destination: native DOM/CSS, History API and VisualViewport API; existing chat_conversations.php list, contacts/private and trade_requests GET responses reused. Existing open/widget transport reused on selection. Search is client-side loaded authorized data only; no global full-history search. Local uploads path allowlist/initials fallback, no remote avatar provider. No raw user data stored in notes. 216 mobile/tablet assertions PASS, plus 223 existing topbar/desktop regression assertions PASS. Actual production PHP topbar/widget markup and JS/CSS under synthetic API responses, no DB/session/providers. Mobile/tablet 320x667,375x812,768x1024,1024x768,812x375 in light/dark; additional812x260 short-height and1440 desktop resize. Verified direct inbox, authorized people/local avatar/presence, search/unread/New message, full-screen real-widget content/composer, outside-click persistence, Back/query/scroll/filter/draft restore, browser Back, explicit Close/Escape/focus wrap, delayed-open cancellation, loading/error/retry/empty, call-owner node retention and desktop resize/dimensions. Three final previews inspected: light/dark375 inbox and light375 conversation. No live sends/calls, actual device keyboard, physical phone/tablet, screen-reader or full authenticated admin matrix verification. [[90 System/Tasks/Records/TASK-20261120-012]].


### Alignment + Chat Space v2 - 2026-11-20T13:52:53+08:00

Native HTML details/summary and existing CSS/DOM only; no new package, SDK, provider, API, license dependency or data destination. Original server-authorized info remains in existing widget markup; disclosure is presentation, not authorization. Existing call/message transport unaffected. PASS:77 new actual-component browser assertions across1200x800,375x812,768x1024,1024x768 light/dark (native disclosure/click/Enter, five short replies, draft and message/composer preservation, centered arrow/SVG, left burger/close);216 mobile/tablet regression assertions including812x260;223 existing topbar/desktop regressions. PHP syntax, two JS syntax checks and admin PHP widget unchanged-info guard PASS. Two inspected previews: light desktop and dark phone collapsed-info conversation. No live seller messages/calls/provider, physical devices/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-012]].


## Admin UX + Queue v1 - 2026-11-20T14:43:32+08:00

No new cloud/third-party/API package/provider/license or data destination. Native DOM/CSS/details/History/VisualViewport and existing PHP/PDO. New same-origin GET admin_topbar_updates.php returns only permission-scoped aggregate update categories (active current admin); admin_submission_queue_updates.php returns aggregate advisory counts after validating session/active permission/nonnegative baselines. Both no-store/read-only; topbar releases PHP session after auth/permission snapshot; queue reader closes session before counting. Pollers30s, visible-only/nonoverlapping; queue cleanup on navigation/repeated script. Existing chat APIs/transports untouched. Client mobile search remains loaded authorized data only. Alternative deferred: durable arrival events/atomic request claiming (needs separate backend design, not automatic assignment now). PASS:185 actual admin-component/endpoint/browser assertions on320/375/768/1024/1440 light/dark;235 SQLite query/pagination/synthetic-burst assertions;41 permission endpoint/controller assertions;16 pure seller next-step assertions; seller regressions77 chat-space +216 mobile/tablet +223 topbar/desktop assertions. PHP lint11 application files and JS syntax5 checks; existing admin decision beginTransaction/FOR UPDATE/status-validation ordering preserved by source check. Four final previews inspected: light375 queue/header, dark1440 multi-chat, light/dark375 inbox. Synthetic data/services only; no live account/provider/message/call writes, real MySQL concurrency/load test, physical phone/keyboard or screen-reader acceptance. [[90 System/Tasks/Records/TASK-20261120-013]].


## Header Alignment v1 - 2026-11-20T14:55:00+08:00

No API/provider/package/data destination change. Native CSS shared header-height/border tokens and existing measured-header popup positioning. Two CSS files only for runtime; three existing browser suites verify geometry/theme. [[90 System/Tasks/Records/TASK-20261120-014]].


## Site Updates internal PHP/PDO integration - TASK016 base

No new API provider/SDK/package, license obligation or external announcement/biometric data destination. Existing PHP/PDO with same-origin escaped announcements and bounded visible-only status polling; two privileged internal admin actions and public published-only reader. Native dialog/DOM plus private local file/DB state; local recovery is CLI-only. Local migration/setup actually applied and maintenance stays OFF. Current health +16 MySQL temporary-table checks PASS; later service scopes/palettes follow their own records. File/DB changes compensate ordinary failures but are not crash-atomic. [[90 System/Runs/2026-11-20 220602 Site Updates Base Delivery Reconciliation]]
