---
type: checklist
status: active
created: 2026-11-19
updated: 2026-11-19
tags: [tasks, verification, approvals]
---

# Completion Checklist

Use the applicable items for each task; irrelevant checks are `not applicable` with a short reason. Do not run a broad suite for a tiny change without cause.

- [ ] Goal, acceptance criteria, owner-approved scope/version, and exclusions are clear.
- [ ] Actual files/Git state match the task; unrelated work is preserved.
- [ ] Required behavior is implemented; no speculative additions or unapproved dependencies/actions.
- [ ] Relevant validation, error handling, security, accessibility, light/dark, and small-screen checks completed where applicable.
- [ ] Record exact checks, results, failures, limits, and applied-versus-unapplied configuration. Fixture evidence is not live runtime evidence.
- [ ] Attributable changed paths, recovery/rollback guidance when needed, and unresolved [[90 System/Tasks/Known Issues|known issues]] recorded.
- [ ] Living notes/coding ledger/run updated when required. Task record, Current Task, [[Task Center]], and [[Approval Inbox]] agree.
- [ ] Handoff has writer, last saved checkpoint, next safe action, blockers, and approval evidence. No secrets or user records.
- [ ] Ready-for-review report given to owner. Owner acceptance recorded only after an actual owner response; uncovered publication/deployment remains gated.

[[90 System/Company Operations]] · [[Task Center]]
