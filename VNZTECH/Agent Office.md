---
type: dashboard
status: updated-awaiting-reload
created: 2026-11-19
updated: 2026-11-19
tags: [agents, office, dashboard]
---

# Agent Office

Company office para sa 20 agents mo: may anim na departments, roles/strategies, CEO Approval Desk, at recorded Current Task/checkpoint. Ikaw ang CEO at final approver; dito sa main chat ka mag-uutos at si Atlas ang mag-a-assign ng relevant team.

**[Buksan ang interactive Agent Office](obsidian://vnz-office?vault=VNZTECH)** — gumagana kapag enabled na yung **VNZ Agent Office** plugin.

## First open

**Update:** Naka-enable na ang plugin ayon sa screenshot mo. Sa **Settings → Community plugins → VNZ Agent Office**, i-off tapos i-on para ma-load ang company/task update, saka **Ctrl+P → Open Agent Office**. Existing app layout/settings were preserved.

1. Buksan **Settings → Community plugins**. Kung may **Turn on community plugins**, pindutin muna yun.
2. Sa **Installed plugins**, i-enable ang **VNZ Agent Office**. Nasa vault na yung files; hindi na kailangan mag-Browse o mag-download.
3. Kung wala pa sa list, gamitin yung refresh icon sa Community plugins o i-close at buksan ulit yung vault, tapos i-enable yung plugin.
4. Mag-o-open yung office sa first enable. Sa susunod: **Ctrl+P → VNZ Agent Office: Open Agent Office**, o yung link sa taas.

Disabled ang app CLI sa earlier check, kaya ikaw ang magre-reload ng plugin sa app. Hindi binago ng update na ito yung CLI, restricted-mode setting, ribbon preference, o current tabs.

## Sa office

- **Office map:** pixel characters, team rooms, and recorded agent activity. `working`, `reviewing`, `blocked`, `done`, and `idle` statuses produce distinct movement/visual states. May Pause animation at automatic reduced-motion support.
- **Agent list:** mas madaling gamitin sa maliit na pane o screen; may search para sa name, role, o team.
- **Agent details:** company position, department, strategy, agent note, at module note para sa specialists. Names at legacy role aliases remain valid.
- **CEO Approval Desk:** links sa [[Task Center]], [[Approval Inbox]], at [[Decision Log]]. Ikaw lang ang final approver.
- **Current Task:** recorded title, status, approval, writer, last checkpoint, next step, at timestamp. Buksan yung canonical record para sa full scope/evidence. May explicit unavailable state kung missing/invalid ang note; may Refresh checkpoint at automatic refresh kapag nagbago ang Current Task file.
- **Theme:** sumusunod sa Obsidian light/dark colors.

Office navigation at recorded task memory ito, walang AI/API calls at walang automated execution o approval buttons. Agent movement is driven only by the `agent_status` mapping in `90 System/Tasks/Current Task.md`; it is not evidence that an AI process is running. Approval metadata displays a record; it does not authorize tools or override owner instructions. Shared runner/Copilot workflow: [[90 System/Company Operations]].

## Preview

Actual plugin interface rendered in a test browser, with stubbed Obsidian navigation and a clearly labelled sample task. The image is a preview, not live task activity:

![[90 System/Attachments/Agent Office Preview.png]]

## Source and maintenance

Plugin: `.obsidian/plugins/vnz-agent-office/`. Distributable files: `manifest.json`, `main.js`, `styles.css`.

Editable sources: `office-view.js`, `office-scene.js`, `styles.css`, and company roles in `90 System/Company Roster.json`. After a JavaScript/roster change, rebuild with `node "VNZTECH/90 System/Tools/build-agent-office.cjs"` from the parent repository; regression: `node "VNZTECH/90 System/Tools/agent-office-test.cjs"`. Browser check needs local Chrome. Re-enable/reload the plugin after a rebuild. The office only reads the short Current Task note and opens existing records.

Implementation follows the official [custom view guide](https://github.com/obsidianmd/obsidian-developer-docs/blob/main/en/Plugins/User%20interface/Views.md), [plugin API](https://github.com/obsidianmd/obsidian-api/blob/master/obsidian.d.ts), and [community-plugin activation guide](https://obsidian.md/help/community-plugins).

[[Home]] · [[90 System/Agent Directory]] · [[90 System/Ponytail]]
