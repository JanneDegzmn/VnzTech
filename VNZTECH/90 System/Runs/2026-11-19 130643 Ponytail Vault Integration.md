---
type: agent-run
status: completed
created: 2026-11-19
updated: 2026-11-19
tags: [ponytail, agents, context]
---

# Ponytail Vault Integration

Request: implement the user-supplied `ponytail-main.zip` in Obsidian to reduce unnecessary token use. Completed **2026-11-19 13:06 Asia/Manila**.

## Outcome

- Adapted Ponytail's smallest-complete-change approach into the vault's active `AGENTS.md`; reuse, selective reading, concise handoffs, and appropriate verification are the default.
- Reduced default vault guidance from **8,136 to 3,501 UTF-8 bytes (57.0% smaller)**. This measures file size, not model tokens, billing, total chat context, or a runtime benchmark.
- Preserved all 20 named role files. Atlas routes through the relevant directory row; the 14 specialists now load the historical snapshot only when needed. No full upstream prompt is duplicated across agents.
- Preserved scope, privacy, frontmatter, original captures, theme support, evidence distinctions, and automatic coding-memory requirements. Exact previous vault guidance is retained in `90 System/References/Vault Working Agreements 2026-11-14.txt`.
- Stored exact upstream rules and MIT license as reference text, with archive version/hash and boundaries in [[30 Resources/Ponytail Source]]. No bundled scripts were executed.

## Roles and changed notes

Atlas: scope/integration; Lex: source placement and links. A delegated design review supplied general guidance without local tool access; source and filesystem checks were performed by the main runner.

- Active instructions: `AGENTS.md`, [[90 System/Agents/Atlas]], and context paragraphs in the 14 specialist role notes.
- Guide/routing: [[90 System/Ponytail]], [[90 System/Agent Directory]], [[90 System/Start Here]], [[90 System/Workflows]], [[Home]].
- Source/reference: [[30 Resources/Ponytail Source]], [[30 Resources/Resources]], raw rules/license, and historical guidance backup.
- Index: [[90 System/Runs/Agent Runs]]. No coding-ledger or application-module update is needed for this vault-only instruction task.

## Checks and limits

- PASS: exact backup matches the previous committed vault `AGENTS.md`; selected upstream files match the ZIP byte-for-byte.
- PASS: all 20 expected agent files remain and reference the shared `AGENTS.md`; core scope/evidence/theme/privacy/completion rules remain present.
- PASS: changed note links/local reference targets and new-note metadata checked; `git diff --check` reports no whitespace errors. Git emits existing LF/CRLF normalization notices.
- Changes stay inside the vault. The pre-existing `.obsidian/graph.json` edit was left alone; application source and global AI settings were not changed.
- No Obsidian GUI/AI-plugin execution or live token/cost benchmark was run. The runner must read the vault guidance for it to affect a task. This is instruction integration, without native slash commands, hooks, session-log imports, or token compression. Remote archive authenticity/latest version was not checked.

[[90 System/Ponytail]] · [[90 System/Runs/Agent Runs]] · [[Home]]
