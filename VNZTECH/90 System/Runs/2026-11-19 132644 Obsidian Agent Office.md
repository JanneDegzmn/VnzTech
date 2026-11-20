---
type: agent-run
status: installed-awaiting-activation
created: 2026-11-19
updated: 2026-11-19
tags: [agents, obsidian-plugin, interface, verification]
---

# Obsidian Agent Office

User correction: the current Graph view has no office visual like the supplied reference. Implemented an actual local custom office view on **2026-11-19 13:26 Asia/Manila**. Plugin activation in the running app still requires the user's one-time enable action.

## Implementation

- Native Obsidian plugin **VNZ Agent Office**, view `vnz-agent-office`, command **Open Agent Office**, ribbon icon, and `obsidian://vnz-office?vault=VNZTECH` link. The first successful enable opens the office once; later invocations reuse its existing tab.
- Original SVG isometric office with 20 named pixel characters/desks, plants, partitions, books, and six teams. Clicking a character selects its role card; explicit buttons open the actual existing agent/module notes in a new tab.
- Search by name/role/team, list alternative, missing-note handling, animation pause, and reduced-motion CSS. Obsidian theme variables support light/dark; container queries support a narrow pane inside a wide app window.
- No AI calls, external assets/dependencies, online/busy metrics, session import, or fabricated task activity. Animation is decorative and the interface says no live work status is connected.
- Kept Ponytail's instruction setup separate; preserved its prior edits and all 20 role files. No PHP application changes or user-owned app/graph/workspace/ribbon settings were made by this task.

## Attributable files

- `.obsidian/plugins/vnz-agent-office/manifest.json`, `office-view.js`, `office-scene.js`, `styles.css`, generated `main.js`.
- `90 System/Tools/build-agent-office.cjs`, `90 System/Tools/agent-office-test.cjs`.
- [[Agent Office]], [[Home]], [[90 System/Start Here]], [[90 System/Agent Directory]], [[90 System/Ponytail]], and `90 System/Attachments/Agent Office Preview.png`.
- [[40 Knowledge/System Modules/Interface and Portal UX]], [[40 Knowledge/System Modules/Verification and QA]], [[90 System/Coding Updates]], and [[90 System/Runs/Agent Runs]].

## Roles

Main runner: Atlas coordination/Iris implementation and documentation. Independent Quinn worker: regression fixtures and preview capture. A separate design worker supplied an SVG draft without local write tools; the main runner adapted, saved, built, and inspected it.

## Actual checks

- `node "VNZTECH/90 System/Tools/build-agent-office.cjs"` — PASS; validates bundle syntax before writing and produces a self-contained `main.js` that only requires `obsidian`.
- JavaScript syntax checks — PASS for scene/view/build/test/bundle. Manifest JSON, expected 20-agent note paths, local source files, note metadata/links, and diff whitespace checked.
- `node "VNZTECH/90 System/Tools/agent-office-test.cjs" --capture-guide` — **5 Chrome cases PASS**: light/dark at 1200px and true 375px iframe widths, plus an 850px view container inside a 1200px viewport. Container case confirms detail stacking independently of app width.
- All 20 scene graphics and map controls render; all 20 exact agent-note targets plus a specialist module target open through the stub workspace. Registration, first-layout welcome/save, tab reuse, search/no-results/reset, map/list, 44px mobile-list targets, pause, missing-note disabled/notice, cleanup/reopen, no-network behavior, theme text, and page bounds pass.
- Inspected desktop maps and 375px lists in both themes. Captured a clean 1440×1050 dark fixture preview directly from Chrome into the vault attachment; it contains actual rendered plugin UI and no test report or real user data.
- Local app CLI attempt: sandboxed CLI could not locate the app; elevated read-only retry reports **Command line interface is not enabled**. No app activation, global CLI change, or restricted-mode bypass was performed.

## Remaining

The user must enable **VNZ Agent Office** under **Settings → Community plugins → Installed plugins**; refresh the plugin list/reopen the vault if it was open during installation. The guide provides the exact steps. After enable, use **Ctrl+P → Open Agent Office**. Live Obsidian loading/navigation, physical devices, and screen readers remain unverified; fixture checks do not establish live plugin activation or task-runner integration. No full application regression was needed for this vault-contained plugin.

Official implementation references: [custom views](https://github.com/obsidianmd/obsidian-developer-docs/blob/main/en/Plugins/User%20interface/Views.md), [plugin API](https://github.com/obsidianmd/obsidian-api/blob/master/obsidian.d.ts), [activation](https://obsidian.md/help/community-plugins), [CLI](https://obsidian.md/help/cli).

[[Agent Office]] · [[90 System/Runs/Agent Runs]] · [[Home]]
