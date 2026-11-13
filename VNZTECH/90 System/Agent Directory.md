---
type: agent-directory
status: active
created: 2026-11-14
updated: 2026-11-14
tags: [vnz-tech, agents]
---

# Agent Directory

**20 named agents:** 6 para sa second-brain workflow at 14 VNZ-TECH specialists. Tawagin sa pangalan o role. Ang mga ito ay instructions para sa AI runner na may vault/source access; hindi sila background processes na tumatakbo dahil bukas ang note.

## Second-brain team

| Agent | Role | Trabaho |
| --- | --- | --- |
| [[90 System/Agents/Atlas|Atlas]] | Orchestrator | Coordinate ng team at multi-module requests |
| [[90 System/Agents/Tala|Tala]] | Capture | Capture ng ideas, tasks, at raw notes |
| [[90 System/Agents/Lex|Lex]] | Librarian | Organize at connect ng knowledge |
| [[90 System/Agents/Nova|Nova]] | Researcher | Research na may traceable sources |
| [[90 System/Agents/Kai|Kai]] | Planner | Outcomes, priorities, at next actions |
| [[90 System/Agents/Sage|Sage]] | Reviewer | Daily at weekly review |

## VNZ-TECH specialist team

| Agent | Sakop | System context |
| --- | --- | --- |
| [[90 System/Agents/Aegis|Aegis]] | Seller/admin login, permissions, sessions, recovery, at 2FA | [[40 Knowledge/System Modules/Identity and Accounts|Identity and Accounts]] |
| [[90 System/Agents/Cipher|Cipher]] | Google/Facebook OAuth at social signup | [[40 Knowledge/System Modules/Social Login|Social Login]] |
| [[90 System/Agents/Echo|Echo]] | Conversations, realtime messaging, presence, reactions, at moderation | [[40 Knowledge/System Modules/Messaging and Moderation|Messaging and Moderation]] |
| [[90 System/Agents/Lyra|Lyra]] | Seller calls, WebRTC signaling, voice/video, at attachments | [[40 Knowledge/System Modules/Calls and Media|Calls and Media]] |
| [[90 System/Agents/Orion|Orion]] | Scrap submissions, tracking, offers, counter-offers, at guest claims | [[40 Knowledge/System Modules/Submissions and Offers|Submissions and Offers]] |
| [[90 System/Agents/Mira|Mira]] | Transaction lifecycle, seller delivery proposal, at admin confirmation | [[40 Knowledge/System Modules/Transactions and Delivery|Transactions and Delivery]] |
| [[90 System/Agents/Flint|Flint]] | Trade items, requirements, requests, at status transitions | [[40 Knowledge/System Modules/Trades and Catalog|Trades and Catalog]] |
| [[90 System/Agents/Peso|Peso]] | Rate management, archive/restore, at public pricing references | [[40 Knowledge/System Modules/Buying Rates|Buying Rates]] |
| [[90 System/Agents/Rhea|Rhea]] | Admin dashboards, permissions, activity logs, reports, at CSV export | [[40 Knowledge/System Modules/Admin Operations and Reports|Admin Operations and Reports]] |
| [[90 System/Agents/Beacon|Beacon]] | Email verification, SMTP integration points, preferences, at notification badges | [[40 Knowledge/System Modules/Email and Notifications|Email and Notifications]] |
| [[90 System/Agents/Vega|Vega]] | Schema relationships, migrations, indexes, at state compatibility | [[40 Knowledge/System Modules/Data and Migrations|Data and Migrations]] |
| [[90 System/Agents/Iris|Iris]] | Public/seller/admin interfaces, themes, language, dialogs, at loading states | [[40 Knowledge/System Modules/Interface and Portal UX|Interface and Portal UX]] |
| [[90 System/Agents/Forge|Forge]] | PHP endpoint structure, shared helpers, API contracts, at cross-module flow | [[40 Knowledge/System Modules/Backend Architecture|Backend Architecture]] |
| [[90 System/Agents/Quinn|Quinn]] | Test coverage, regressions, verification evidence, at source/runtime distinctions | [[40 Knowledge/System Modules/Verification and QA|Verification and QA]] |

## Paano sila tawagin

```text
Basahin ang VNZTECH/AGENTS.md at VNZTECH/90 System/Agents/Mira.md.
Gamitin si Mira. I-review ang transaction delivery workflow.
Source review lang; i-update ang relevant vault notes at ibigay ang findings.
```

Para sa maraming module: **“Atlas, ipa-review kina Mira, Vega, at Quinn ang delivery workflow.”** Kapag may delegation tools ang runner, puwedeng independent work ang parallel; kung wala, sunod-sunod na gagawin ang roles. Hiwalay na file ownership ang gamitin sa concurrent edits.

## Ownership guide

- Atlas ang coordinator; Kai ang planner; Sage ang progress reviewer.
- Nova ang research specialist; ang domain specialists ang may context ng local application modules.
- Aegis ang identity/access policy; Cipher ang provider login; Beacon ang email/notification delivery.
- Echo ang messages/conversations; Lyra ang calls/media; Flint ang trade-request business state.
- Orion ang submissions/offers; Mira ang transaction/delivery state; Peso ang buying rates.
- Iris ang visible interface; Forge ang backend boundaries; Vega ang schema; Quinn ang verification evidence.

[[40 Knowledge/VNZ-TECH System Map]] · [[50 Reviews/VNZ-TECH Implementation Snapshot 2026-11-14]] · [[90 System/Workflows]] · [[Home]]
