# Submission selfie v6

Locally active and verified on 2026-11-19, 21:50 Asia/Manila. Owner approved
"Apply v6 at activate locally". Result acceptance and real camera/device testing
remain with the owner.

Guest and account sellers fill the item form, press Submit, consent and take a
fresh camera selfie. The server checks one clear detected face before creating
the submission. The photo appears inside the existing admin submission review's
Seller Information section. No ID upload, identity-approval module, reference
comparison, face gallery, Face++ request or login upgrade is used in this mode.
Face presence does not establish identity or certified liveness.

### Admin photo viewer v6.2

In Seller Information, select **View submission selfie** to open the private photo.
Click the photo or **Zoom in** for 2x zoom, then drag with the mouse or one finger
to look around. The zoomed photo has no scrollbars; movement stays within its
bounds. When the photo is focused, arrow keys pan (Shift moves farther).
A simple click/tap or **Reset zoom** fits it again; dragging does not reset it.
Close, Escape or the backdrop returns to the
same submission review. The viewer follows the active admin light/dark theme.
The photo loads on demand and its memory URL is revoked when the viewer closes
or the review fragment is replaced. Existing authorization and retention apply
on each request. Missing/expired photos show an explanatory state.

The viewer and drag amendments were explicitly approved with **"I-apply ang v6.1"**
and **"I-apply ang v6.2"**. They add no
admin module, provider, schema/configuration change or camera-orientation change.

The camera dialog follows the active public/seller theme, supports retake/cancel,
and preserves item fields after a server rejection. A CSRF-protected nonce refresh
allows long item drafts without reloading. Each 10-minute nonce belongs to the
session/context/account and can be committed once. The actual server validates
the multipart image and ignores browser approval flags.

Photos are re-encoded, encrypted using the existing private AES-GCM key and stored
outside the webroot. Submission and evidence link commit together; rollback
removes only newly created files. The image endpoint rechecks current active
admin, active role and `view_submissions`, verifies the evidence hash, and returns
an uncached JPEG. No raw photo, token or credential belongs in Git/Obsidian/logs.

Photo retention ends 30 days after the submission is first accepted or declined.
The database trigger latches the UTC transition time; later notification/seen
updates cannot extend it. Access is denied at expiry and physical deletion runs
at the next existing daily03:00 cleanup. Unlinked crash leftovers are removed
after24 hours; the shared orphan sweep preserves files referenced by the new
submission table. Older submissions without a v6 photo have no fabricated photo.

Local mode is `face_submission_v6`; `backend/submission_face_required.flag`
prevents a silent fallback to old ID approval. Historical ID records retain their
existing retention; the old admin page and ID APIs return410 in v6. The scheduled
cleanup task was already Ready/enabled with last result0 and next run
2026-11-20 03:00 Asia/Manila; no new service/task was installed.

## Setup and recovery

`tools/activate_submission_face_v6.php` is CLI-only. Its `--inspect`, `--migrate`,
`--check`, `--activate` actions print only health/status metadata. Migration backs
up configuration/schema as encrypted private evidence, creates the additive
`submission_security_faces` table using the existing submission ID's exact type
(local BIGINT UNSIGNED), and installs the terminal-time trigger. Existing user,
submission and ID evidence rows are not rewritten. Activation verifies schema,
trigger body, pinned assets, private storage/key, cleanup heartbeat and a real
single-image blank-model refusal before the atomic configuration replacement.
Re-running health requires private filesystem access; no credentials go in CLI
arguments or chat. A policy rollback requires an owner decision and reconciliation
with the fail-closed marker; never disable the gate to bypass a setup failure.

Use HTTPS for a phone opening the site through a network address. Localhost camera
support on the computer does not prove physical-phone/browser compatibility.

## Verification

V6.1 viewer checks:14 actual-template Chrome groups passed across admin light/dark
desktop1200/mobile375, including on-demand loading, zoom/reset/keyboard, nested
close/focus/scroll, error states, loading/ready AJAX cleanup and unchanged item
photo viewing. The focused private-photo HTTP24 rerun passed; PHP lint and changed
JS syntax passed. Synthetic previews were reviewed; live authenticated admin and
physical-device acceptance remain with the owner.

63 helper,36 isolated submit/API HTTP,24 isolated admin-photo HTTP,33 worker and
10 actual disposable InnoDB trigger/schema checks passed. Capture UI24 and admin
inline UI7 Chrome checks passed for the original v6 delivery, including light/dark desktop/375px; earlier-mode
regressions passed. Fourteen affected PHP lints and changed JS syntax passed.
Eleven live anonymous probes confirmed the new form/token flow, retired ID APIs,
protected photo denial and CLI-only setup. Full evidence is in the task/run notes.

Positive HTTP face inference is mocked only in copied disposable fixtures. Real
CPU blank-image refusal passed; no personal photo, actual camera acceptance,
authenticated live admin/seller, physical phone, live SMTP or biometric accuracy
claim is made from these checks.
