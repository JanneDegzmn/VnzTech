---
type: agent-run
status: completed
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, coding-update, identity, theme]
---

# Remember Login and Theme Consistency

Recorded **2026-11-14 05:08 Asia/Manila**.

## Request and root cause

The user reported login failure only when Remember me was checked, an error popup that ignored the light theme, and explicitly required all future UI/modals to follow active light/dark mode and be recorded in Obsidian.

Read-only schema metadata confirmed the local `remember_login_tokens` table was absent. Checked login attempted an INSERT, which reached the general login failure handler and cleared an otherwise valid session. The shared `VNZDialog` CSS also used fixed dark surfaces/text instead of the active area's palette.

## Completed changes

- Applied the existing `database/migrations/20261026_create_remember_login_tokens.sql` only after confirming the table was missing; verified required columns. The migration source was unchanged.
- `vnzIssueRememberLoginToken` now returns a boolean. Database failures clear the remember cookie and return false while preserving a successful credential/2FA session. Persistent cookies are still issued only after token storage succeeds; validators remain hashed and actor input remains validated.
- Remembered restoration updates `last_used_at` before establishing authenticated session fields. A database failure leaves restoration signed out and clears the cookie, addressing a partial-session failure path.
- Shared dialogs now map public/seller/admin theme flags and palettes into dialog tokens. Admin/seller flags take precedence over lower-priority flags. Surface, text, secondary/danger actions, backdrop, focus, 44px buttons, and constrained scrolling adapt to both modes; dialog JavaScript/queue logic was preserved.
- Saved the user's all-UI theme requirement in the root and vault `AGENTS.md`, Iris instructions, and the living UI module note. Every new/changed modal, popup, error, confirmation, form, and other UI must follow the active mode and be checked in both themes.

## Attributable files and roles

- Application: `backend/remember_login.php`, `assets/css/vnz_dialog.css`.
- Regression: new `tests/remember_login_http_test.py`.
- Instructions: `AGENTS.md`, `VNZTECH/AGENTS.md`, `VNZTECH/90 System/Agents/Iris.md`.
- Database action: existing `20261026_create_remember_login_tokens.sql` applied to the local application database; no account/submission records changed.

Atlas coordinated the identity/schema/UI/QA work. Independent workers diagnosed the missing schema, implemented the isolated HTTP regression, and repaired/previewed shared-dialog CSS. One writer consolidated all Obsidian notes. Earlier task changes and unrelated working-tree edits were preserved.

## Actual checks

| Check | Result and limits |
| --- | --- |
| Local schema metadata and existing migration | Missing table confirmed; migration applied; required columns present. This establishes only the local remember-token schema. |
| Rolled-back local MySQL issuance check | PASS: helper inserted a fake-actor token, cookie format/hash/expiry matched, rollback completed, and the test selector was absent afterward. No test token persisted and no real user was authenticated. |
| `python tests/remember_login_http_test.py` | PASS, 14 isolated HTTP cases using real copied login/session/remember helpers, SQLite/fake identities, and stubbed presence. Covers persistent protected cookie/hash/fresh-session restore, unchecked login, missing-schema fallback including stale cookie, malformed/unknown/expired/tampered tokens, inactive/invalid actors, failed token-use update, wrong credentials, and no token before 2FA confirmation. |
| `C:/xampp/php/php.exe tests/social_auth_service_test.php` | PASS, existing related regression. |
| PHP lint | PASS on remember helper, seller login, seller 2FA confirmation route, and admin login. |
| Python syntax and `git diff --check` | PASS; only pre-existing Git line-ending notices. |
| Temporary Chrome shared-dialog fixtures | PASS, 12 cases: public/seller/admin × light/dark × 1200px/375px iframe widths. Checks live mode switching, conflicting lower-priority flags, surface/text/danger-action contrast, native focus, 44px actions, bounds, short-frame/long-content scrolling, confirmation/cancel, queue order, and danger reset. Light login-error screenshot inspected. |

The visual fixture used actual shared dialog JavaScript and CSS, with fake content. Public theme transitions were completed in the fixture before computed-color sampling to avoid virtual-clock timing errors; application transition code was unchanged. No persistent CSS-only test was added.

## Remaining evidence

No real user's password login, complete 2FA code submission, live presence transport, physical-device, or screen-reader pass was performed. The HTTP fixture stubs presence and uses SQLite; the separate rollback check establishes local MySQL token storage. Other migrations, environments, SMTP, and unrelated baseline failures were not reverified. The future theme rule is a user requirement, not a claim that every historical UI screen has been audited.

## Notes updated

[[40 Knowledge/System Modules/Identity and Accounts]] · [[40 Knowledge/System Modules/Data and Migrations]] · [[40 Knowledge/System Modules/Interface and Portal UX]] · [[40 Knowledge/System Modules/Verification and QA]] · [[40 Knowledge/VNZ-TECH System Map]] · [[90 System/Coding Updates]] · [[90 System/Runs/Agent Runs]] · [[90 System/Agents/Iris]]

Historical snapshots and source inventories were preserved.
