---
type: source
status: reviewed
created: 2026-11-19
updated: 2026-11-19
tags: [ponytail, agents, source]
---

# Ponytail Source

User-supplied archive: `C:\Users\Administrator\Downloads\ponytail-main.zip`, reviewed **2026-11-19**. The archive's `package.json` declares **5.3.3**, author Dietrich Gebert, license MIT, repository `https://github.com/DietrichGebert/ponytail`. These are local archive declarations; remote authenticity/latest version was not checked.

ZIP SHA-256: `fb1e4285aaa30fa1667d7c644f8a28142392d27ec719bf11564627c6fdb63d83`.

## Selected local sources

- [Original upstream rules](<Ponytail Source/Rules.txt>) — exact archive `ponytail-main/AGENTS.md`, kept as reference text, not an active nested instruction file.
- [MIT license](<Ponytail Source/LICENSE.txt>) — exact archive `ponytail-main/LICENSE` with copyright notice.
- Inspected in the ZIP: `README.md`, `INSTALL.md`, `skills/ponytail/SKILL.md`, `package.json`, and the file listing. No bundled scripts were run.

The core idea is the smallest **complete** change: inspect the affected flow, reuse existing code, avoid speculative additions, and preserve validation, security, accessibility, and error handling. This supports the user's request for less unnecessary token use.

## Adaptation boundary

[[90 System/Ponytail]] applies those ideas to existing vault instructions and selective reading. The archive's AGENTS/skill text is source material; it does not replace the user's request, existing agreements, or higher-priority runner instructions. The upstream reply-ending format, automatic global/session activation, and plugin commands were not adopted as additional mandatory behavior.

No Obsidian plugin entry files (`manifest.json`, `main.js`, `styles.css`) were found in the archive. Installation here is vault-local instruction integration. No plugin-manager registration, external services, global agent settings, hooks, session-log import, or token telemetry was added. Upstream benchmark percentages were not validated locally and are not savings claims for this vault.

[[30 Resources/Resources]] · [[90 System/Ponytail]]
