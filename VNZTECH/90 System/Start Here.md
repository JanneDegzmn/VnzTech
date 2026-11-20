---
type: guide
status: active
created: 2026-11-14
updated: 2026-11-19
tags: [second-brain]
---

# Start Here

Ito ang second brain mo: isang lugar para sa ideas, learnings, projects, at next actions. Magsimula sa isang capture.

## Existing vault mo

Ang vault na nakita sa workspace ay **`C:\xampp\htdocs\VNZ-TECH\VNZTECH`**. Dito ang `.obsidian` settings at notes. Hindi kailangang i-upload o i-import ulit.

Kung ito na ang bukas mong vault sa Obsidian, buksan lang ang [[Home]]. Kung parent folder na `VNZ-TECH` ang bukas bilang ibang vault, piliin ang **Open another vault → Open folder as vault** at gamitin ang exact path sa itaas para magamit ang settings na inihanda rito.

Obsidian ang notes interface mo. Dito sa chat, ang agents ay puwedeng magbasa at mag-edit ng parehong local files. Hindi nito kinokontrol ang mismong Obsidian window.

## Unang gamit

1. Buksan ang [[Home]] sa Obsidian; puwede ring `Ctrl+O`, saka hanapin ang `Home`.
2. Mag-save ng isang idea sa [[01 Inbox/Inbox|Inbox]] o gamitin ang Capture prompt sa ibaba.
3. `Ctrl+P` → **Daily notes: Open today's daily note**.
4. Isulat ang isang mahalagang next action.

May initial daily note sa [[02 Daily/2026-11-14]] at setup checklist sa [[10 Projects/Second Brain Setup]].

## Agent team

**Dito sa main chat ka na mag-uutos.** Si Atlas ang mag-a-assign ng relevant roles ayon sa tanong/command at scope. Ikaw ang final approver bago implementation. [[90 System/Company Operations|Company workflow]] · [[Task Center]] · [[90 System/Tasks/Current Task|Current Task]] · [[Approval Inbox]].

Para sa office visual: [[Agent Office]]. Naka-enable na yung **VNZ Agent Office** ayon sa owner screenshot; i-off/on para ma-load yung updated company/task build, saka **Ctrl+P → Open Agent Office**. Clickable directory at recorded checkpoint view ito; hiwalay pa rin yung AI runner.

Default na yung [[90 System/Ponytail|Ponytail workflow]] sa shared vault instructions: relevant notes lang ang babasahin, reuse muna ng existing setup, at concise updates. Gamitin mo pa rin yung usual agent names; walang bagong Obsidian plugin o slash commands na kailangan.

| Pangalan | Role | Trabaho |
| --- | --- | --- |
| [[90 System/Agents/Atlas|Atlas]] | Delivery Director | Pumili at mag-coordinate ng roles |
| [[90 System/Agents/Tala|Tala]] | Business Intake Coordinator | I-save ang ideas at raw input |
| [[90 System/Agents/Lex|Lex]] | Knowledge & Documentation Lead | Organize, connect, at distill notes |
| [[90 System/Agents/Nova|Nova]] | Research Lead | Research na may sources |
| [[90 System/Agents/Kai|Kai]] | Lead Systems Analyst | Outcomes, projects, at next actions |
| [[90 System/Agents/Sage|Sage]] | Governance & Release Reviewer | Progress, blockers, at reviews |

### VNZ-TECH specialists

**20 agents lahat:** ang 6 na core roles sa itaas at 14 specialists sa [[90 System/Agent Directory]]. Nakatalaga ang specialists sa accounts, social login, chat, calls/media, submissions/offers, transactions/delivery, trades, buying rates, reports, email, database, UI, backend architecture, at QA.

Para sa mga nagawa na sa system, buksan ang [[40 Knowledge/VNZ-TECH System Map]]. Nasa [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]] ang current evidence: 5 passing checks, 2 failures, at 1 test na hindi pinatakbo.

Puwedeng pangalan o role ang gamitin sa request, gaya ng `Tala:` o `Capture:`. Ang role files ay reusable instructions. Patakbuhin sila sa AI runner na may access sa vault, gaya ng chat na ito. Ang pagbukas ng role note sa Obsidian ay pagbasa sa instructions; walang naka-install na Obsidian AI plugin o background scheduler sa setup na ito.

## Unang agent prompt

Para sa kasalukuyang workspace na `C:\xampp\htdocs\VNZ-TECH`:

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Tala.md.
Gamitin si Tala. Sa loob lang ng VNZTECH mag-edit.
Tala: [ilagay ang idea, task, link, o rough note].
I-save sa inbox at ibigay ang link ng note.
```

May repository-root `AGENTS.md` na nagtuturo sa assistant na basahin ang vault conventions at isama ang Obsidian updates sa coding tasks. Hindi na kailangang ulitin ang “update Obsidian” kada coding request. Explicit prompts sa guide ay puwede pa rin para pumili ng role. Alisin ang `VNZTECH/` prefix sa prompts kapag vault mismo ang working directory. Project guidance uses [AGENTS.md discovery](https://learn.chatgpt.com/docs/agent-configuration/agents-md).

## Pagkatapos ng coding task

Relevant living module notes at [[90 System/Coding Updates]] ang ia-update bilang bahagi ng coding task: actual changes, source paths, checks/results, at pending work. Historical snapshots remain intact. Sakop nito ang assistant coding tasks dito; direct manual edits sa VS Code are not monitored. Nasa [[90 System/Coding Updates#Scope|scope guide]] ang detalye.

Nasa [[90 System/Workflows]] ang prompts para sa bawat role at daily/weekly habits.

## Templates at daily notes

Naka-configure ang built-in **Templates** at **Daily notes**. Kung bukas na ang app habang ginawa ang setup, muling buksan ang vault kung hindi lumitaw ang settings.

| Setting | Value |
| --- | --- |
| Templates → Template folder location | `90 System/Templates` |
| Daily notes → Date format | `YYYY-MM-DD` |
| Daily notes → New file location | `02 Daily` |
| Daily notes → Template file location | `90 System/Templates/Daily` |
| Files and links → Default location for new notes | `01 Inbox` |
| Files and links → Attachment folder | `90 System/Attachments` |

Para sa ibang notes: gumawa muna ng note, saka `Ctrl+P` → **Templates: Insert template**. Piliin ang Capture, Project, Area, Knowledge, Research, o Weekly Review. Isang beses lang i-insert para walang duplicate sections. [Obsidian Templates](https://obsidian.md/help/plugins/templates), [Daily notes](https://obsidian.md/help/plugins/daily-notes).

Ang template variables ay napapalitan kapag ginamit ang Obsidian command. Kapag agent ang direktang nagsusulat ng file, siya ang maglalagay ng aktuwal na title at dates.

## Local storage

Nasa Apache document root ang vault, kaya may vault-level `.htaccess` na humaharang sa direct HTTP access. File access ng Obsidian at agents ang ginagamit ng workflow.

[[Home]] · [[90 System/Workflows]]
