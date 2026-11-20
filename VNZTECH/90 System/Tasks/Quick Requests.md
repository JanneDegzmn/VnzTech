---
type: task-log
status: active
created: 2026-11-19
updated: 2026-11-20
tags: [tasks, questions]
---

# Quick Requests

One compact row per small read-only question/explanation. If a request grows into implementation or multi-step research, promote it to one canonical task record and link it here. Answer-only requests require no implementation approval. Save concise outcomes and useful references, not full chat transcripts.

| ID / local date | Request | Outcome / evidence | Runner | Status / next |
| --- | --- | --- | --- | --- |
| Q-20261120-003 / 09:17 Asia/Manila | Owner directs Nova to inspect UI/UX with Iris | Nova/Iris source review of seller UI/topbar: visually coherent structure, existing seller light/dark tokens, 44px icon targets, sticky topbar, fixed downward panels, 600px mobile wrapping, and reduced-motion support are positive. Concerns: no live/browser screenshot was shared; mobile topbar wraps to a full-width action row and should be tested with real name, unread badge, open chat, notification popover, and keyboard focus; some selectors rely on existing floating-chat markup and can be obscured if placement/calculations change. Desktop/mobile/light/dark and real-session preview not independently verified here. | Copilot SDK in VS Code | Read-only review answered; Nova/Iris statuses set in Current Task |
| Q-20261120-002 / 08:33 Asia/Manila | Owner asks what I am doing and why many approvals appear, noting 1600+ changes | Read-only Git reconciliation reports 70 tracked files changed with 2541 insertions and 611 deletions, plus many untracked files and broad Obsidian/task-memory changes. The checkout is shared and already dirty from prior Codex/Copilot work; this turn changed only vault handoff records, not 1600 application lines. Approval entries are evidence of the user's explicit owner approvals recorded in task files, not approvals issued by the assistant; implementation/verification/owner acceptance remain separate. I also should not ask for repeated approvals within an already explicit scope, but material scope changes or uncovered changes require a new owner decision. | Copilot SDK in VS Code | Answered; no implementation or file deletion performed |
| Q-20261120-001 / 00:36 Asia/Manila | Owner reports HTTP 500 on some modules; suspects missing migrations and asks what to run in phpMyAdmin | Read-only Apache log shows 2026-11-20 00:32-00:36 `SQLSTATE[42S22]` Unknown column `s.bulk_format` at `admin/pages/submissions.php:335`. The exact additive bulk migration `database/migrations/20261119_add_bulk_submission_format.sql` was intentionally left unapplied under TASK-20261119-006; it adds `submissions.bulk_format` and `submission_items.quantity_is_estimated`, default 0. No SQL executed. Recommend check each column first, run only missing ALTER statement(s) on the correct VNZ-TECH DB after backup, then verify. Do not run unrelated identity migrations to address this error. | Copilot SDK in VS Code | Answered; owner executes DB changes in phpMyAdmin |
| Q-20261119-003 / 22:16 Asia/Manila | Owner asks whether selfie flips after capture | Source confirms horizontal mirror on live video only: assets/css/submission_face_capture.css:32 scaleX(-1); JS:168 draws original video pixels without canvas transform. Captured preview/upload uses normal orientation. This explains the visible flip; consistent mirrored previews can be a separate approved UX change while retaining normal stored evidence. No photo contents copied or source edited | Codex | Answered; current v6 acceptance unchanged |
| Q-20261119-002 / 21:20 Asia/Manila | Owner asks what to tell Copilot to continue, then greets | Read-only reconciliation found newer Copilot-owned v6 staged checkpoint, updated20:43. Handoff prompt points to Current Task and canonical TASK004, source-only remaining checks; no activation or return to superseded v4. Current pointer/writer preserved | Codex | Answered; Copilot reconciles latest files/approval and resumes pending source/browser checks |
| Q-20261119-001 | Main assistant assigns agents by role/scope/type of request | Confirmed as part of owner's explicit setup approval; routed operating model in [[90 System/Company Operations]] | Codex | Answered; setup tracked by [[90 System/Tasks/Records/TASK-20261119-001]] |

[[Task Center]] · [[90 System/Tasks/Current Task]]

Correction recorded21:28: Q002 referenced a newer Copilot V6 checkpoint seen earlier; current checkout instead has old V4 task/source and zero-byte V6 artifacts. Do not treat Q002 as implementation or authorization evidence. Current reconciliation: [[90 System/Runs/2026-11-19 212807 Face-Only Submission Revision]].

## Q-20261120-004 CSS unit rules

2026-11-20 15:17 Asia/Manila - Codex. Owner: "Apply these rules in obsidian: rem = text; % = layouts; vw = full screen sections; px = borders." Completed vault/instruction-only maintenance: canonical Interface and Portal UX guidance, repository/vault AGENTS instructions, Iris reference and Engineering Lessons. Checked exact mappings and new note links. No application CSS changes or runtime checks; no coding-ledger entry required. Copilot-owned [[90 System/Tasks/Records/TASK-20261120-015]] and Current Task pointer preserved. Next: use the convention in future approved UI changes.


## Q-20261120-005 Git non-fast-forward

2026-11-20 22:06:02 Asia/Manila - Owner asks how to push after rejection on VnzTech. Read-only current checkout: clean app source after exact removal of seven stale own additions; local658a2e2 tracks newrepo/VnzTech with28ahead/1behind. Remote-tracking ba4f3da contains README.md only; merge-base exits1 (unrelated roots). Correct generic rebase advice to explicit merge using `git pull newrepo VnzTech --no-rebase --allow-unrelated-histories` after committing pending notes; resolve any conflict before normal push. No fetch/merge/rebase/commit/push/reset executed by Codex. Current Task/TASK021 ownership preserved; base documentation reconciliation [[90 System/Runs/2026-11-20 220602 Site Updates Base Delivery Reconciliation]].
