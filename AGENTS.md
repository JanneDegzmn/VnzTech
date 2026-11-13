# VNZ-TECH working agreements

This is the PHP/PDO VNZ-TECH application. Its Obsidian second brain is the `VNZTECH/` subdirectory.

## Theme consistency for every UI change

The user explicitly requires all new or modified UI to follow the active light/dark theme. This includes modals, popups, login errors, alerts, confirmations, toasts, forms, and dynamically created content across public, seller, and admin pages.

- Reuse the active area's theme attributes and CSS tokens; avoid fixed surface/text colors that work in only one mode. Shared dialogs must support `data-public-theme`, `data-seller-theme`, and `data-admin-theme` with the active area's theme taking precedence.
- Check readable text, semantic status colors, buttons, focus states, backdrops, and small-screen layout in both light and dark modes. Report actual preview coverage and any limits.
- Record lasting UI decisions and this requirement in the relevant Obsidian notes. Treat theme support as part of completing the requested UI change, without another reminder.

## Automatic Obsidian updates after coding tasks

The user wants Obsidian kept current without repeating an update request. Apply this default to assistant coding tasks in this repository unless the user overrides it for a particular task.

1. Before editing, inspect the current working tree so you can distinguish the task's changes from pre-existing user work. Read `VNZTECH/AGENTS.md`; consult the agent directory and only the relevant module notes/instructions.
2. Complete the requested implementation and appropriate verification. Preserve unrelated working-tree changes. Do not claim ownership of or document pre-existing changes as work completed by this task.
3. Before the final response, update the affected living notes under `VNZTECH/40 Knowledge/System Modules/` when behavior, decisions, source paths, or verification status changed. Preserve user writing, evidence, and unresolved findings. Update the system map only if its overview, ownership, or status is affected.
4. Add one concise entry for the logical coding task to `VNZTECH/90 System/Coding Updates.md`, using the user's local date/time (Asia/Manila). Include what changed and why, attributable source paths, actual checks and their results/limits, remaining items, and links to relevant module notes. If application edits remain but work is incomplete, label the entry `partial` rather than completed.
5. For substantive multi-step work, also follow the vault run-record convention and link that record from the coding entry. Consolidate subagent outputs through one writer; do not create duplicate entries per worker or per file save.
6. Check the changed note links and briefly report the Obsidian update with the coding outcome. This documentation is part of completing the task; do not wait for a separate request or ask whether to update it.

This applies when the assistant changes application source, assets, migrations, tests, configuration, or documentation describing application behavior. Keep updates proportional: a small coding task needs a short entry and relevant note adjustments, not a new full-system audit or broad test rerun.

## Evidence and scope

- Keep source implementation, passing checks, applied migrations/configuration, and runtime verification distinct. Record failures or unrun checks honestly. Do not mark an existing issue resolved without new evidence.
- Preserve dated reviews, inventories, and old run records as historical snapshots; append new evidence instead of rewriting history.
- Never copy secrets, tokens, credentials, or user records into the vault.
- Read-only questions, vault-only maintenance, and changes only to agent instructions do not need a coding-ledger entry. This prevents documentation updates from recursively logging themselves.
- This is an assistant workflow rule, not a filesystem watcher, Git hook, or background service. Direct manual edits in VS Code are not monitored. If asked to sync manual edits, review the specified changes and document them with their actual evidence.

The named agent roster and detailed vault conventions live in `VNZTECH/AGENTS.md` and `VNZTECH/90 System/Agent Directory.md`.
