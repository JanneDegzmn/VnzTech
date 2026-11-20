---
type: workflow
status: active
created: 2026-11-19
updated: 2026-11-19
tags: [agents, context, ponytail]
---

# Ponytail

**Default sa vault agents:** tapusin yung request gamit ang sapat na context at pinakamaliit na useful change. Adapted mula sa Ponytail 5.3.3 ZIP na binigay mo; nasa vault `AGENTS.md` yung active rules.

## Paano ginagamit

1. Hanapin muna yung relevant filenames o headings. Piliin yung role sa [[90 System/Agent Directory]]; basahin yung isang role at affected note/module.
2. Para sa system work, check yung current source na kailangan ng task. Buksan lang yung full [[40 Knowledge/VNZ-TECH System Map|system map]], inventory, old runs, o buong history kung may tanong na kailangan nun.
3. Reuse yung existing note/helper/component. Gawin lahat ng kailangan ng request; iwas extra features, duplicate notes, at bagong dependencies kung may sapat na existing option.
4. I-check yung actual change. Same pa rin yung security, error handling, theme, accessibility, at verification requirements. Passing source/helper checks at live runtime are separate evidence.
5. Short update: ano yung natapos, relevant links, actual checks, at material limits. One shared-note writer lang kahit may parallel workers.

Lahat ng **20 named agents** gumagamit ng shared vault guidance kapag binasa ito ng runner. Hindi kailangan kopyahin yung buong Ponytail prompt sa bawat agent.

## Halimbawa

```text
Atlas, ayusin yung [request]. Relevant notes lang muna,
gamitin yung existing setup, tapusin yung checks at Obsidian update.
```

```text
Mira, check yung delivery issue. Basahin yung relevant module/source;
short findings, actual evidence, at next action lang.
```

Kung kailangan mo ng mas detalyadong explanation, sabihin lang. Hindi hard word limit yung concise default at hindi nito binabawasan yung scope ng request.

## Ano ang implemented

- Compact vault `AGENTS.md`: scope, selective reading, reuse, bounded handoffs, evidence, theme, at automatic coding memory.
- Existing role directory ang routing; preserved lahat ng agent files at dating notes. Historical copy ng dating vault guidance: [working agreements](<References/Vault Working Agreements 2026-11-14.txt>).
- [[30 Resources/Ponytail Source|Source record]], exact upstream rules, at MIT license are stored locally for reference. Hindi kailangang basahin yung raw rules bawat task.

Instruction-based integration ito para sa AI runner na may vault access. Walang Obsidian community plugin, native `/ponytail` commands, lifecycle hooks, global config changes, o token/cost dashboard na in-install. Opening this note alone does not run an agent.

Separate follow-up: may local visual plugin na para sa [[Agent Office]], enabled ayon sa owner screenshot. Updated company/task build needs a plugin reload. Office navigation and recorded task checkpoints lang yun; no AI calls. Start with [[90 System/Tasks/Current Task]] and the relevant task record; [[90 System/Company Operations]] keeps owner approvals, small-team routing, and concise handoffs part of the workflow.

## Token savings

Mas maliit yung default guidance at mas konti yung irrelevant notes na kailangang basahin. Hindi nito kino-compress o tinatanggal yung existing chat context. Actual token/cost savings depend sa runner at task; walang measured billing reduction o guaranteed percentage sa setup na ito.

[[Home]] · [[90 System/Workflows]] · [[90 System/Agent Directory]]
