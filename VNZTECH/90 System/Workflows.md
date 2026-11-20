---
type: guide
status: active
created: 2026-11-14
updated: 2026-11-19
tags: [second-brain]
---

# Workflows

Dashboard: [[Home]]. Unang gamit at app settings: [[90 System/Start Here]].

## One front door and shared task memory

Give the question/command to the main assistant; it uses Atlas to assign only the useful roles. First read [[90 System/Tasks/Current Task]] and its canonical record, reconcile actual files/Git/approval/ownership, then relevant source. [[Task Center]] keeps all substantive tasks; [[90 System/Tasks/Quick Requests]] keeps compact answer-only requests. [[90 System/Company Operations]] defines proposal/approval, checkpoints, handoffs, and result acceptance. [[Approval Inbox]] and [[Decision Log]] keep owner decisions visible.

New implementation needs a concrete owner-approved plan. Approved edits, checks, and docs proceed as one scope; agents cannot approve for the owner. Simple read-only answers/research and routine task records can proceed. Manual edits are not watched.

After each task, relevant agents reuse/update [[40 Knowledge/Integration Catalog]] (API/tool choice, purpose, source/version, data flow, limits and actual checks) and [[40 Knowledge/Engineering Lessons]] (do/don't, evidence and corrections). This owner-requested learning stays concise and linked to modules/tasks; it is shared retrievable memory for future runs.

Copilot in the same workspace is pointed to these files by `.github/copilot-instructions.md` at the repository root. Repository instructions require accessible/synchronized files; another clone/cloud runner does not automatically see local notes. [GitHub repository instructions](https://docs.github.com/en/copilot/how-tos/copilot-in-your-ide/customize-copilot/configure-custom-instructions/add-repository-instructions-in-your-ide?tool=vscode).

## Mula idea hanggang action

1. **Capture:** i-save sa [[01 Inbox/Inbox|Inbox]] o daily note ang raw input, source, at context.
2. **Organize:** iugnay sa existing note o pumili ng tamang folder sa table sa ibaba.
3. **Distill:** ipaliwanag ang useful learning sa sarili mong salita; panatilihin ang link sa source.
4. **Act:** kung may gagawin, pumili ng next action sa project o daily note.
5. **Review:** balikan ang recorded progress, open loops, at priorities.

| Uri | Lugar |
| --- | --- |
| May outcome at finish line | [[10 Projects/Projects|Projects]] |
| Ongoing responsibility | [[20 Areas/Areas|Areas]] |
| Source o reference material | [[30 Resources/Resources|Resources]] |
| Reusable insight o explanation | [[40 Knowledge/Knowledge|Knowledge]] |
| Progress reflection | [[50 Reviews/Reviews|Reviews]] |
| Inactive na gusto mong itabi | [[99 Archive/Archive|Archive]] |

Hindi lahat ng capture kailangan ng task. Panatilihin ang references at ideas kahit wala pang immediate action.

## Copy-paste agent prompts

[[90 System/Ponytail|Ponytail]] is the shared default: search first, read the relevant role/module, reuse existing work, and give concise results with actual evidence. Full histories and inventories are for questions that need them. The prompts below still work; no special activation command is needed.

Para ito sa parent workspace na `C:\xampp\htdocs\VNZ-TECH`. Kung vault mismo ang working directory, alisin ang `VNZTECH/` prefix. Palitan ang bracketed text ng input mo. Puwedeng tawagin ang agents sa pangalan o role: Atlas/Orchestrator, Tala/Capture, Lex/Librarian, Nova/Researcher, Kai/Planner, Sage/Reviewer.

### Atlas — Orchestrator

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Atlas.md.
Gamitin si Atlas. Sa loob lang ng VNZTECH mag-edit.
Request: [gusto mong matapos].
Basahin ang relevant role instructions, gawin ang kailangan,
at ibigay ang links ng notes na ginawa o binago.
```

### Tala — Capture

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Tala.md.
Gamitin si Tala. Sa loob lang ng VNZTECH mag-edit.
Tala: [idea, task, link, o rough note].
I-save ang original meaning at context sa inbox; ibigay ang note link.
```

### Lex — Librarian

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Lex.md.
Gamitin si Lex. Sa loob lang ng VNZTECH mag-edit.
I-process ang unprocessed captures sa VNZTECH/01 Inbox.
Iugnay sa existing notes, gumawa lang ng bagong note kung kailangan,
at panatilihin ang originals na may links sa organized notes.
Iwan sa inbox ang ambiguous items at ibigay ang changed-note links.
```

### Nova — Researcher

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Nova.md.
Gamitin si Nova. Sa loob lang ng VNZTECH mag-edit.
Research question: [specific question].
Magsimula sa relevant vault notes; gumamit ng sources na kaya mong
aktuwal na basahin. I-save ang findings sa VNZTECH/30 Resources.
I-cite ang sources at sabihin ang uncertainty o access limitations.
```

### Kai — Planner

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Kai.md.
Gamitin si Kai. Sa loob lang ng VNZTECH mag-edit.
Desired outcome: [outcome].
Gumawa o mag-update ng project na may success criteria,
next actions, blockers, at actual deadlines kung meron.
Huwag mag-imbento ng commitments. Ibigay ang project link.
```

### Sage — Reviewer

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Sage.md.
Gamitin si Sage. Sa loob lang ng VNZTECH mag-edit.
Review ng seven days ending [YYYY-MM-DD].
Gamitin ang recorded daily notes, project progress, at inbox items.
I-save sa VNZTECH/50 Reviews ang wins, open loops, gaps in records,
at hanggang tatlong suggested priorities. Ibigay ang review link.
```

Ang runner ang nagsasagawa ng instructions kapag nag-request ka. Delegation at external access ay nakadepende sa available tools; puwedeng sunod-sunod gawin ang roles. Ang pagdagdag ng role file ay hindi awtomatikong nag-i-install ng AI plugin o scheduler.

## VNZ-TECH system workflow

### Automatic updates after coding tasks

Naka-save sa repository-root `AGENTS.md` ang default: pagkatapos ng assistant coding task, i-update ang affected living module notes at [[90 System/Coding Updates]] bago ang final response. Hindi na kailangang humingi ng separate Obsidian update. Actual task changes at verification lang ang ire-record; partial work stays partial, at historical snapshots remain intact.

Direct manual VS Code edits are not monitored. Para ipa-document ang manual edits, sabihin: **“Sync mo ang latest changes ko sa Obsidian.”**

### Source-review requests

1. Hanapin yung matching owner/module sa [[90 System/Agent Directory]]. Basahin yung relevant role at living module; buksan yung [[40 Knowledge/VNZ-TECH System Map]] kung kailangan ng overview o cross-module context.
2. I-review ng owner ang current source paths at related tests; ang module notes ay dated memory.
3. Ihiwalay ang implemented code, actual check results, applied configuration/migrations, at runtime unknowns.
4. I-save ang lasting findings sa relevant module note at dated review. Panatilihin ang known gaps hanggang may bagong evidence. Sa implementation tasks, kasama rin ang coding-memory update sa taas.

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Mira.md.
Gamitin si Mira para i-review ang delivery workflow sa current source.
Basahin din ang relevant module memory. Source review lang;
i-update ang vault findings at sabihin kung ano ang may test evidence.
```

Para sa several modules: “Atlas, i-coordinate sina Mira, Vega, at Quinn para sa delivery review.” Implementation sa application ay dapat malinaw na hinihiling sa task; ang second-brain review ay read-only sa application source.

| Agent | Example request |
| --- | --- |
| [[90 System/Agents/Aegis|Aegis]] | Aegis, i-review ang identity and accounts at i-update ang source-backed notes. |
| [[90 System/Agents/Cipher|Cipher]] | Cipher, i-review ang social login at i-update ang source-backed notes. |
| [[90 System/Agents/Echo|Echo]] | Echo, i-review ang messaging and moderation at i-update ang source-backed notes. |
| [[90 System/Agents/Lyra|Lyra]] | Lyra, i-review ang calls and media at i-update ang source-backed notes. |
| [[90 System/Agents/Orion|Orion]] | Orion, i-review ang submissions and offers at i-update ang source-backed notes. |
| [[90 System/Agents/Mira|Mira]] | Mira, i-review ang transactions and delivery at i-update ang source-backed notes. |
| [[90 System/Agents/Flint|Flint]] | Flint, i-review ang trades and catalog at i-update ang source-backed notes. |
| [[90 System/Agents/Peso|Peso]] | Peso, i-review ang buying rates at i-update ang source-backed notes. |
| [[90 System/Agents/Rhea|Rhea]] | Rhea, i-review ang admin operations and reports at i-update ang source-backed notes. |
| [[90 System/Agents/Beacon|Beacon]] | Beacon, i-review ang email and notifications at i-update ang source-backed notes. |
| [[90 System/Agents/Vega|Vega]] | Vega, i-review ang data and migrations at i-update ang source-backed notes. |
| [[90 System/Agents/Iris|Iris]] | Iris, i-review ang interface and portal ux at i-update ang source-backed notes. |
| [[90 System/Agents/Forge|Forge]] | Forge, i-review ang backend architecture at i-update ang source-backed notes. |
| [[90 System/Agents/Quinn|Quinn]] | Quinn, i-review ang verification and qa at i-update ang source-backed notes. |

## Limang minuto araw-araw

| Oras | Gagawin |
| --- | --- |
| 1 minuto | Capture ng loose ideas at reminders |
| 2 minuto | Process ng ilang inbox items at useful links |
| 1 minuto | Piliin ang isang mahalagang next action |
| 1 minuto | Itala ang progress o isang learning |

Buksan ang daily note sa `Ctrl+P` → **Daily notes: Open today's daily note**.

## Dalawampung minuto bawat linggo

| Oras | Gagawin |
| --- | --- |
| 5 minuto | Process ng inbox at ambiguous items |
| 5 minuto | Suriin ang projects: progress, blockers, next actions |
| 5 minuto | Balikan ang areas at learnings; ayusin ang useful links |
| 5 minuto | Isulat ang review at suggested priorities |

Gumawa ng note sa `50 Reviews` at gamitin ang **Templates: Insert template → Weekly Review**, o gamitin ang Reviewer prompt. Substantive multi-step runs ay may tala sa [[90 System/Runs/Agent Runs]].

[[Home]]
