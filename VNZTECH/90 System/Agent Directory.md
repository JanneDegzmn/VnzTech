---
type: agent-directory
status: active
created: 2026-11-14
updated: 2026-11-19
tags: [vnz-tech, agents]
---

# Agent Directory

**20 named agents:** 6 para sa second-brain workflow at 14 VNZ-TECH specialists. Tawagin sa pangalan o role. Ang mga ito ay instructions para sa AI runner na may vault/source access; hindi sila background processes na tumatakbo dahil bukas ang note.

Shared default: [[90 System/Ponytail|Ponytail]]. Search the relevant row, then read that role and affected note/module. Full maps, inventories, and historical reviews are on-demand; do not load every role or duplicate the shared rules in each file. All 20 agents remain available.

Visual navigation: [[Agent Office]] — local Obsidian plugin with an office map, 20 characters, search/list, and recorded task activity. Assigned agent status comes from `agent_status` in Current Task: idle, working, reviewing, blocked, or done. It still does not launch AI tasks or act as a background worker.

## Company structure

Shared learning: [[40 Knowledge/Integration Catalog]] and [[40 Knowledge/Engineering Lessons]]. Every role consults the relevant entries and contributes verified decisions/lessons through the coordinator; no need to load unrelated histories. Owner requested this on 2026-11-19.

**Owner = CEO / Product Owner / sole final approver.** Atlas coordinates; agents review/recommend and execute only authorized work. [[90 System/Company Operations|Operating rules]] · [[Task Center]] · [[Approval Inbox]]

| Agent | Position | Department | Existing module/specialty |
| --- | --- | --- | --- |
| [[90 System/Agents/Atlas|Atlas]] | Delivery Director | Leadership | Orchestrator |
| [[90 System/Agents/Tala|Tala]] | Business Intake Coordinator | Leadership | Capture |
| [[90 System/Agents/Lex|Lex]] | Knowledge & Documentation Lead | Leadership | Librarian |
| [[90 System/Agents/Sage|Sage]] | Governance & Release Reviewer | Leadership | Reviewer |
| [[90 System/Agents/Kai|Kai]] | Lead Systems Analyst | Product & Research | Planner |
| [[90 System/Agents/Nova|Nova]] | Research Lead | Product & Research | Researcher |
| [[90 System/Agents/Orion|Orion]] | Seller Experience Analyst | Product & Research | [[40 Knowledge/System Modules/Submissions and Offers|Submissions and Offers]] |
| [[90 System/Agents/Mira|Mira]] | Business Process Analyst | Product & Research | [[40 Knowledge/System Modules/Transactions and Delivery|Transactions and Delivery]] |
| [[90 System/Agents/Flint|Flint]] | Trade Product Analyst | Product & Research | [[40 Knowledge/System Modules/Trades and Catalog|Trades and Catalog]] |
| [[90 System/Agents/Peso|Peso]] | Pricing & Business Intelligence Analyst | Product & Research | [[40 Knowledge/System Modules/Buying Rates|Buying Rates]] |
| [[90 System/Agents/Forge|Forge]] | Software Architect & Engineering Lead | Engineering | [[40 Knowledge/System Modules/Backend Architecture|Backend Architecture]] |
| [[90 System/Agents/Vega|Vega]] | Database Engineer | Engineering | [[40 Knowledge/System Modules/Data and Migrations|Data and Migrations]] |
| [[90 System/Agents/Echo|Echo]] | Backend & Messaging Engineer | Engineering | [[40 Knowledge/System Modules/Messaging and Moderation|Messaging and Moderation]] |
| [[90 System/Agents/Lyra|Lyra]] | Realtime & Media Engineer | Engineering | [[40 Knowledge/System Modules/Calls and Media|Calls and Media]] |
| [[90 System/Agents/Beacon|Beacon]] | Integration & Notifications Engineer | Engineering | [[40 Knowledge/System Modules/Email and Notifications|Email and Notifications]] |
| [[90 System/Agents/Aegis|Aegis]] | Security & Identity Lead | Security | [[40 Knowledge/System Modules/Identity and Accounts|Identity and Accounts]] |
| [[90 System/Agents/Cipher|Cipher]] | Authentication Engineer | Security | [[40 Knowledge/System Modules/Social Login|Social Login]] |
| [[90 System/Agents/Iris|Iris]] | Product Designer & Frontend Lead | Design & QA | [[40 Knowledge/System Modules/Interface and Portal UX|Interface and Portal UX]] |
| [[90 System/Agents/Quinn|Quinn]] | QA & Test Engineering Lead | Design & QA | [[40 Knowledge/System Modules/Verification and QA|Verification and QA]] |
| [[90 System/Agents/Rhea|Rhea]] | IT Operations & Change Manager | Operations | [[40 Knowledge/System Modules/Admin Operations and Reports|Admin Operations and Reports]] |

Names and legacy roles remain accepted. Every role note contains its strategy, deliverable, handoff, and approval boundary. Canonical roster for the office: `90 System/Company Roster.json`.

## Paano sila tawagin

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Mira.md.
Gamitin si Mira. I-review ang transaction delivery workflow.
Source review lang; i-update ang relevant vault notes at ibigay ang findings.
```

Para sa maraming module: **“Atlas, ipa-review kina Mira, Vega, at Quinn ang delivery workflow.”** Kapag may delegation tools ang runner, puwedeng independent work ang parallel; kung wala, sunod-sunod na gagawin ang roles. Hiwalay na file ownership ang gamitin sa concurrent edits.

## Ownership guide

- Atlas routes and coordinates; Kai leads systems analysis/planning; Sage reviews governance and readiness.
- Nova ang research specialist; ang domain specialists ang may context ng local application modules.
- Aegis ang identity/access policy; Cipher ang provider login; Beacon ang email/notification delivery.
- Echo ang messages/conversations; Lyra ang calls/media; Flint ang trade-request business state.
- Orion ang submissions/offers; Mira ang transaction/delivery state; Peso ang buying rates.
- Iris ang visible interface; Forge ang backend boundaries; Vega ang schema; Quinn ang verification evidence.

[[40 Knowledge/VNZ-TECH System Map]] · [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]] · [[90 System/Workflows]] · [[Home]]
