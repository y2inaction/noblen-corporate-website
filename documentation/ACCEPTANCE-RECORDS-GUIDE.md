# Manual acceptance records: guide

**Status: the 17 manual records are PENDING.** They are prepared in [acceptance-records/](acceptance-records/README.md) for the designated reviewer and the owner. None has been performed, signed or accepted, and none may be written by the implementer or inferred from a passing test. The plan, the independence assessment and the flagged conflicts are in [REVIEW-PLAN.md](REVIEW-PLAN.md).

## Who does what

| Who | Records |
|-----|---------|
| **Nominated independent reviewer** (designated by the owner; independence **NOT VERIFIED**) | The 14 reviewer-run records: C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, C19-M01, C20-M01, C20-M02, C25-M01, C27-M01 |
| **The owner** (the frozen plan assigns these to the owner) | C17-M01, C31-M01, C34-M01. The reviewer may add an independent verification of the owner's evidence; it does not replace the owner's record. |
| **The owner only** | C21-021, C21-022 and C21-M01, decided in [owner-acceptance-decisions.yml](owner-acceptance-decisions.yml). All three are pending. |

Corrections made when these records were prepared:

- The earlier version of this guide listed 18 rows under "the 17 items". **`C17-S01` is not a record.** It is a status item that CI reports, saying only whether the owner's naming-scan evidence is outstanding or supplied. The 17 records are the 17 `-M` items in the table above.
- **`C27-M01` is not defined in the frozen CI acceptance plan** (provenance and options: [C27-M01-PROVENANCE.md](C27-M01-PROVENANCE.md)) (criterion 27 is automated only there). It comes from the implementation's register and the earlier pre-push review. Its record says so.
- `C31-M01` is an owner-run record in the frozen plan. The earlier version of this guide wrongly gave it to the reviewer.

## Rules for every record

- It is performed by the person the record names, from their own observation, on a **staging copy built from the release packages**, never on production.
- It names the **commit SHA** checked, the date and time (UTC), the environment and the performer's name and role.
- It records PASS or FAIL for each step, and keeps the evidence **outside the public repository** (it may show private data). Only a reference to the evidence goes in the record. No secret, key, private term list or personal data goes in it.
- A failed step is reported as a failure. Nothing is smoothed over.
- Declarations of independence and competence, and qualification information, are given privately to the owner and are never written in the public record. The record carries only an identifier that lets the owner find them.
- A decision other than PENDING needs every field filled in. `tests-cli/acceptance-records-test.py` rejects a half-completed record, and CI runs it.
- The implementer never fills in a record, a result, a date or a signature for someone else.

## The 17 records

Each file contains the criterion, the source in the frozen plan, prerequisites, a numbered procedure with expected results, evidence requirements, the reviewer and performer fields, the declarations, findings and the decision field.

| Record | Criterion | Performed by |
|--------|-----------|--------------|
| [C01-M01](acceptance-records/C01-M01.md) | 1: Theme installs and activates (dashboard walk-through) | Reviewer |
| [C02-M01](acceptance-records/C02-M01.md) | 2: Plugin installs and activates (dashboard walk-through) | Reviewer |
| [C03-M01](acceptance-records/C03-M01.md) | 3: Both work together | Reviewer |
| [C06-M01](acceptance-records/C06-M01.md) | 6: Content can be created and edited (workflow walk-through) | Reviewer |
| [C10-M01](acceptance-records/C10-M01.md) | 10: Incomplete clearance blocks publication (interface paths) | Reviewer |
| [C12-M01](acceptance-records/C12-M01.md) | 12: No unfinished placeholders appear publicly | Reviewer |
| [C13-M01](acceptance-records/C13-M01.md) | 13: Layout at 360, 768 and 1280 px on real devices | Reviewer |
| [C14-M01](acceptance-records/C14-M01.md) | 14: Keyboard navigation (the acceptance test) | Reviewer |
| [C15-M01](acceptance-records/C15-M01.md) | 15: WCAG AA contrast, zoom, motion, screen reader | Reviewer |
| [C17-M01](acceptance-records/C17-M01.md) | 17: Naming scan and screenshot review | **Owner** |
| [C19-M01](acceptance-records/C19-M01.md) | 19: Both ZIPs install on a clean WordPress | Reviewer |
| [C20-M01](acceptance-records/C20-M01.md) | 20: Review of the operator documents | Reviewer |
| [C20-M02](acceptance-records/C20-M02.md) | 20: Rehearsal of the recovery steps | Reviewer |
| [C25-M01](acceptance-records/C25-M01.md) | 25: Backup-confirmation workflow | Reviewer |
| [C27-M01](acceptance-records/C27-M01.md) | 27: Review of the audit trail (not in the frozen plan) | Reviewer |
| [C31-M01](acceptance-records/C31-M01.md) | 31: Purge-list workflow | **Owner** |
| [C34-M01](acceptance-records/C34-M01.md) | 34: Audit-key custody walk-through | **Owner** |

## Operator commands used by the records

Reading the audit trail, exporting it and confirming a backup before an upgrade are done with WP-CLI, run with `--user=<login>` so the same permissions apply as on the website:

```bash
wp noblen audit status
```

```bash
wp noblen audit list --user=<approver login> --limit=20
```

```bash
wp noblen audit export --user=<approver login> --file=<new file>
```

```bash
wp noblen migrate status
```

```bash
wp noblen migrate confirm-backup --user=<administrator login>
```

Only the Publication Approver role may read the audit trail. Key rotation and recovery after loss are not available as commands or screens; see [REVIEW-PLAN.md](REVIEW-PLAN.md) section 5.

## The owner naming scan

The scan is run by the owner, never by CI: `NOBLEN_BLOCKLIST=/path/to/your/private/terms.txt scripts/naming-scan.sh`. The term list stays private and is never committed. The record shows PASS or FAIL only.

## What a completed record is, and is not

A signed record is evidence of class E7. It is **not by itself an acceptance** of its criterion: the frozen plan also needs all automated evidence and a reviewer's recorded acceptance, and the register's `accepted` field is generated and never typed. See [REVIEW-PLAN.md](REVIEW-PLAN.md) section 7 for the decision the owner still has to make.
