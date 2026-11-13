# Reviewer onboarding and evidence handover

Public, role-based. The reviewer's name appears only in [review-designation.yml](review-designation.yml) and [REVIEW-PLAN.md](REVIEW-PLAN.md). Declarations, qualifications, contact details and engagement correspondence are held privately by the owner and are never written here.

## 1. Roles (unchanged)

* **Designated human reviewer:** performs the reviewer-run records, is accountable for the review and signs personally.
* **Technical inspection support (Z.ai):** used under the reviewer's direction; not a separate reviewer; may not sign or declare for the reviewer.
* **Owner:** keeps the owner-only criteria and every acceptance decision.

## 2. Distinct states (one is never evidence of another)

| State | Status at this commit | Evidence |
|-------|-----------------------|----------|
| Owner accepted the review arrangement | **Yes** (2026-11-13) | Owner instruction recorded in the designation file |
| Reviewer personally accepted the review | **Not confirmed** | none |
| Reviewer's independence declaration | **Not made**; independence **NOT VERIFIED** (8 open checks) | none |
| Reviewer pack received by the reviewer | **Not confirmed** (delivered twice to the owner's conversation; receipt by the reviewer unknown) | none |
| Review commenced | **No evidence** | none |
| Completed review findings | **None** | none |
| Signed manual acceptance records | **None**: 17 PENDING | `acceptance-records/` |
| `accepted_count` | **0** | register |

## 3. The reviewer pack

A private pack was prepared from commit `7228471` (51 files, archive SHA-256 `5733c9944b99ec2d096dc93548cd80111b720655197fe884fe1f71697800ee57`). Its source baseline and integrity reference are **preserved and not changed**; it is private and is not part of the repository. Newer evidence (this commit and later hosted runs) is recorded in the release report and, if the owner wishes, can be issued as a separate supplement without altering the original pack.

## 4. Action register (what is needed before the review can be called started or independent)

| # | Action | Owner of the action | Status | What unblocks it | Related check |
|---|--------|---------------------|--------|------------------|---------------|
| 1 | Reviewer's personal confirmation of willingness, experience and availability | Reviewer, privately to the owner | Open | A message from the reviewer to the owner, kept privately | I6 |
| 2 | Independence and conflict-of-interest declaration | Reviewer, privately to the owner | Open | The reviewer's own declaration; the owner records only a private reference in each record | I3, I5 |
| 3 | GitHub login confirmation | Reviewer | Open | The reviewer's own login (not the account that opened pull request 2); read-only use is enough | I2 |
| 4 | Signature route for the signed records | Owner decides | Open | Choose: a signed document kept privately with a reference, a signed message, or the reviewer's own GitHub review for repository work | I7 |
| 5 | GitHub Actions artifact access | Owner decides | Open | The reviewer uses their own signed-in account (artifacts need sign-in; the repository is public), or the owner exports the artifacts. No privileged or long-lived token is issued | I8 |
| 6 | Staging site and test accounts | Owner or host provides | Open | A staging copy built from the release packages with separate low-privilege accounts (editor, clearance officer, approver, administrator); never production | I8 |
| 7 | Private evidence storage | Owner provides | Open | A private folder outside the repository for screenshots, recordings and declarations | I8 |

No credential has been issued and none is prepared here. Nothing in this table has been confirmed by the people concerned.

## 5. How the review proceeds once the items above exist

1. The reviewer reads [REVIEW-PLAN.md](REVIEW-PLAN.md), the frozen plans and [ACCEPTANCE-TRACEABILITY-REPORT.md](ACCEPTANCE-TRACEABILITY-REPORT.md).
2. The reviewer (with inspection support if wished) re-verifies automated evidence independently: the hosted runs listed in [RELEASE-READINESS-REPORT.md](RELEASE-READINESS-REPORT.md), checksums and contrast figures.
3. The reviewer personally performs the reviewer-run records in the order in REVIEW-PLAN section 3, on staging, filling every field from their own observation, keeping evidence privately and writing only a reference.
4. The reviewer may add an independent verification to the three owner-run records; it never replaces the owner's record.
5. The reviewer signs each record personally by the agreed route. `tests-cli/acceptance-records-test.py` rejects a half-completed record.
6. A completed record is evidence, not acceptance. The owner decides how records feed acceptance (REVIEW-PLAN section 7).

## 6. Where the evidence is

* Automated and hosted: [RELEASE-READINESS-REPORT.md](RELEASE-READINESS-REPORT.md), [FINAL-QA-REPORT.md](FINAL-QA-REPORT.md).
* Security: [SECURITY-OPERATIONAL-ASSURANCE.md](SECURITY-OPERATIONAL-ASSURANCE.md), [RESIDUAL-RISK-REGISTER.md](RESIDUAL-RISK-REGISTER.md).
* Criteria and records: [ACCEPTANCE-TRACEABILITY-REPORT.md](ACCEPTANCE-TRACEABILITY-REPORT.md), [acceptance-records/](acceptance-records/README.md).
* Open owner decisions: [OWNER-DECISION-REGISTER.md](OWNER-DECISION-REGISTER.md).
