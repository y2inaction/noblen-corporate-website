# C27-M01: provenance, discrepancy and proposed resolution

**Status: documented. Nothing here amends a frozen plan, accepts a criterion or changes a record's status.** C27-M01 stays a PENDING supplementary record and is not a requirement of criterion 27 unless the owner decides otherwise.

## 1. The discrepancy

| Source | What it says about criterion 27 (audit trail behaviour) |
|---|---|
| `M2-IMPLEMENTATION-PLAN.md` (Rev 5.1), section 10 | Type **A** (automated only). No manual test. |
| `CI-ACCEPTANCE-TEST-PLAN.md`, test register row 27 | C27-001 to C27-010, all automated (`ci`). Executors are `ci`; no `-M` id. |
| Generated register (`acceptance-criteria-traceability.yml`) | Lists `C27-M01` as a pending manual record (marked "supplementary, NOT in the frozen plans"). |
| `documentation/acceptance-records/C27-M01.md` | A prepared PENDING record that says it is not defined in the frozen plan. |

Neither frozen plan defines C27-M01. Checked by `tests-cli/plan-register-consistency-test.py`, which also proves the other 16 records match the plans one for one (the plan's 17 manual ids, less C21-M01 which is an owner decision, plus C27-M01).

## 2. Provenance (from git history)

| Commit | Date | What happened |
|---|---|---|
| `daa712e` "RC prep: … traceability register …" | 2026-11-12 | First appearance. The implementation session's first draft of the register generator carried a hand-written table in which criterion 27 had `C27-M01` as its manual record. The commit also added it to the list of 17 records in the acceptance guide. No plan text was changed. |
| `de59ce1` "Pre-push review …" | 2026-11-12 | `PRE-PUSH-ACCEPTANCE-REVIEW.md` repeated the list ("27 Audit trail behaviour: C27-M01"). The list was copied from the register, not from the plan. |
| `3289081`, `eda7389` | 2026-11-13 | The acceptance guide and then the 17 prepared records inherited the id. The record text itself flagged the gap. |

Conclusion: C27-M01 was introduced by the implementer's draft register, not by the owner or either frozen plan. The probable reason is that the audit trail is security-relevant and a human walk-through seemed useful. That is a reasonable idea, but it was never approved as a requirement, and the earlier guide listed it among "the 17 records" without saying it was an addition.

## 3. Effect today

* It does not block criterion 27: the frozen plan accepts 27 on the automated tests (C27-001 to C27-010) plus a reviewer's recorded acceptance.
* It does not count towards `accepted_count`, which stays 0.
* Recording the 17 as "the plan's manual tests" would be wrong by one. The accurate statement is: 16 records come from the frozen plan, C21-M01 is handled as an owner decision, and C27-M01 is a supplementary record.

## 4. Proposed resolution (a proposal for the owner, not applied)

| Option | What changes | Cost and effect |
|---|---|---|
| **A. Keep it as supplementary (default, no change needed)** | Nothing. It stays prepared and PENDING, labelled supplementary. Criterion 27 is judged on its frozen definition. | A reviewer may perform it for extra assurance; its result never gates anything. Lowest risk. |
| **B. Formal plan amendment** | The owner approves a numbered amendment adding C27-M01 (executor reviewer, `MAN`, E7) to criterion 27 and changing its type to A + M. The frozen files stay untouched; the amendment is a separate approved document. | Adds a required manual step before criterion 27 can be accepted. Needs the owner's approval process and a plan revision. |
| **C. Withdraw it** | Remove the record and the register entry; the register then lists 16 plus C21-M01. | Cleaner match to the plans. Loses a prepared walk-through. Reversible from git history. |

Recommendation: **A** now, because it changes no frozen text and no gate, and **B** only if the owner wants a human review of the audit trail before launch (the key-rotation and recovery rehearsal in C34-M01 already exercises the audit trail with a person). The choice is the owner's.

Until the owner chooses, the register, the guide, the review plan and the record keep saying that C27-M01 is outside the frozen plans.
