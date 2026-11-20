---
type: agent-run
status: awaiting_approval
created: 2026-11-19
updated: 2026-11-19
tags: [identity, automation, research, planning]
task_id: TASK-20261119-004
runner: Codex
---

# Automated Face Matching Proposal

Owner now requests automatic matching without admin ID review; mentions Face++ as an option. Atlas coordinated read-only Aegis/Forge review and official-source research. Live manual v2 remains unchanged; no new approval inferred from the prior v2 approval.

Concrete [[90 System/Tasks/Records/TASK-20261119-004#Concrete implementation plan v3 - automatic local matching (pending approval)|local v3 proposal]] defines a trusted Node CPU worker with existing pinned face-api assets, PHP GD tensors, versioned conservative threshold, exact evidence/lease finalization, automatic pass/retry, retired manual APIs/UI and current-policy gates. Preserves account-once/guest-per-draft, carryover, audit, encryption and cleanup; login deferred. Old captures are not processed automatically; fresh consent/enrollment required under the new policy.

Actual read-only probe: existing bundle exports tensor/disk loaders; all three models load from disk on Node25 CPU; synthetic128x128 blank tensor returns zero faces in760ms. No real-photo calibration/accuracy or full capture-path latency established. No source/config/database/dependency changes, service start or user image/ID processing. Screenshot personal data not copied.

Official [Face++ comparison](https://www.faceplusplus.com/face-comparing/), [free pricing](https://www.faceplusplus.com/v2/pricing/), [privacy](https://www.faceplusplus.com/privacy-policy/), and [SDK](https://github.com/FacePlusPlus/facepp-python-sdk/blob/master/PythonSDK/facepp.py) reviewed. Account-specific quota/region/console contract unresolved; console docs only loading shell. External processing/credentials require provider-specific approval/consent; no account, key or call made. The local option avoids that dependency.

Updated canonical/current task, approval/task/decision indexes and shared [[40 Knowledge/Integration Catalog]] / [[40 Knowledge/Engineering Lessons]]. Seven planning notes/93 links checked with no missing targets/anchors, default whitespace check passed before this run was indexed. No coding entry for planning-only work. Next: owner approves local v3 or selects Face++ for a revised provider-specific plan.

## V4 follow-up - 2026-11-19 17:34 Asia/Manila

Owner asked for both engines and the actual uploaded ID's face, then chose **"Parehong dapat pumasa; retry kapag may fail/unavailable"** and **"Wala pa"** for Face++ account/keys. Exact message timestamps unavailable; this is recording time. These are requirements, not concrete v4 implementation approval. Earlier v3 proposal/probe remains history.

Root saved [[90 System/Tasks/Records/TASK-20261119-004#Concrete implementation plan v4 - dual automatic matching (approved)|concrete v4]]: identical server-created actual-ID/selfie crops, local AND cloud passes, current-policy/evidence/lease finalization, fresh cloud consent, staged adapters/tests/private config/migration before readiness-conditional atomic rollout. Aegis/Forge read-only review highlighted same/different-person validation and exclusion of old manual approvals from new gates. No source/config/migration/provider account/call or personal evidence processing; live manual v2 remains current. After approval, source/tests/docs proceed without credentials, while activation waits for private keys, provider region/contract/quota/health and consented matching validation. Updated the same task/index/catalog/lessons; no duplicate task or coding-ledger entry.

Final readback17:34: eight changed notes/104 wikilinks with zero missing files/anchors; v4-pending/live-manual-v2 pointer consistency and default git diff --check passed (existing CRLF conversion warnings). No new application tests needed for this proposal-only update. Next safe action remains explicit owner v4 approval; keys are a later activation dependency, never requested in chat.
