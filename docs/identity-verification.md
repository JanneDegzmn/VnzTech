# ID and selfie verification v2

Historical v2 setup/review guide. Current local mode is submission selfie v6,
activated2026-11-19 and verified21:50 Asia/Manila. The ID form/module/API is retired
in that mode. Current behavior/setup/retention: [Submission selfie v6](submission-selfie-v6.md).

Owner-approved scope: account verification once, guest verification for each draft,
face-api.js comparison assisting an explicitly permitted administrator, and exact
guest-to-account carryover after matching email verification. Login upgrades are a
separate follow-up. This is not automated document authentication or certified
liveness detection.

## Local activation

Source/focused checks and **local activation verified 2026-11-19 17:07 Asia/Manila**.
Owner ran Administrator completion successfully. Independent readback confirms
live form/API enabled, private health, and Ready/enabled daily03:00 cleanup with
last result0. Next run: 2026-11-20 03:00. Personal ID/camera/device review is still
pending. Earlier normal-token permission failures are resolved. The commands
below remain the reviewed new-install/repair guide.

For new setup or verified repair, open PowerShell **as Administrator**, then run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\VNZ-TECH\tools\complete_identity_setup.ps1"
```

The reviewed script restricts private file DACLs without replacing an existing
encryption key, validates/registers the exact daily 03:00 task, runs it, verifies a
fresh successful heartbeat and private PHP/schema/encryption health, then enables
the feature and creates the local fail-closed instance marker. No user IDs are
captured during setup. Configuration/key stay in
`C:\xampp\private\VNZ-TECH\identity`, outside the entire HTTP document root.
The task normally uses SYSTEM; a permitted interactive desktop task is a
least-privilege fallback and can run only while that account is signed in.
Timezone is checked against Windows Singapore Standard Time (Asia/Manila).

After completion:

```powershell
Get-ScheduledTask -TaskName 'VNZ-TECH Identity Evidence Cleanup'
Get-ScheduledTaskInfo -TaskName 'VNZ-TECH Identity Evidence Cleanup'
C:\xampp\php\php.exe C:\xampp\htdocs\VNZ-TECH\tools\deploy_identity.php health
```

Check `sell.php` shows the verification card and the guest status API reports
enabled/not_started. Admin **ID verification** is available only to roles with
`identity_review`, initially active Super Admin. Test your own ID/camera locally;
do not put personal evidence in chat, test fixtures, Git or Obsidian. A phone
opening a LAN address needs HTTPS and browser camera permission. Responsive
Chrome checks are not a physical-phone test.

## Data and decisions

- Private originals are content-validated, stripped/re-encoded JPEGs encrypted
  with AES-256-GCM. Image delivery rechecks ownership/active actor or dedicated
  reviewer permission, sends no-store headers, and validates evidence integrity.
- Descriptors/scores are browser review aids and cannot authorize submissions.
  Approval is an authenticated administrator's decision about an immutable
  evidence version; email verification and submission/chat claims stay separate.
- Pending review expires after seven days. Abandoned orphan files are eligible
  for removal after 24 hours; decided images after seven days. Expired images
  become inaccessible immediately; physical deletion occurs at the next daily
  cleanup. Minimal status/audit remains, and durable account approval survives
  raw-file deletion until revoked. There is no persistent facial gallery.
- A stale/missing maintenance heartbeat (48 hours) blocks the enabled feature.
  The local marker makes missing configuration fail closed after activation.
  A valid explicit `enabled=false` is deliberate rollout/policy state, not an
  automatic fallback when health fails.
- Guest grants bind session capability, exact draft, email and approved attempt,
  expire in 24 hours, and are consumed atomically with submission creation. Exact
  signup reservation plus verified matching email can bind pending/approved
  attempts; expired, conflicting, revoked or rejected proof cannot be promoted.

## Setup/recovery boundaries

`tools/deploy_identity.php migrate` is an additive first-install helper and backs
up affected permission tables privately before applying the SQL. It refuses to
reapply when any identity table already exists; inspect a partial migration
instead of dropping/replacing tables. No historical user/submission data is
retroactively approved. Never delete or replace a key while encrypted evidence
exists; recover the original key. Preserve audit data when reverting code.

Private directory/file ACLs must allow the actual PHP/Apache service identity.
The local scripts allow the installing account, SYSTEM and Administrators only.
For a different deployment identity, review and explicitly grant that service
identity rather than opening the directory to everyone. Do not claim a remote
cron, certificate or production deployment was configured by this local setup.

## Reproducible focused checks

```text
php tests/identity_verification_test.php
python tests/identity_api_http_test.py
python tests/identity_signup_http_test.py
python tests/email_verification_http_test.py
php tests/identity_mysql_concurrency_test.php
node tests/identity_models_browser_test.mjs
node tests/identity_ui_browser_test.mjs
```

The MySQL test creates and removes an exact-prefix-validated disposable database;
it requires permission to do so and never queries application user records. Other
backend tests use isolated synthetic fixtures. Browser tests require local Chrome
and Node with built-in WebSocket; real models are tested on a blank canvas, while
UI camera/quality tests use synthetic boundaries. No test establishes biometric
accuracy, ID authenticity, liveness, live SMTP/provider success or real-device
compatibility.

Asset provenance: `assets/vendor/face-api/manifest.json`; upstream version 0.22.2,
MIT license retained. Re-fetch only through `tools/fetch_identity_assets.py` after
reviewing upstream changes; upgrades need compatibility/security review.

Knowledge: `VNZTECH/40 Knowledge/Integration Catalog.md`,
`VNZTECH/40 Knowledge/Engineering Lessons.md`, and task 004's canonical/run notes.
