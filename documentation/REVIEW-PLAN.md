# Review plan: designated reviewer and independence

**Status: the owner has designated a human reviewer and accepted the engagement arrangement. No review has been performed. All 17 manual acceptance records are PENDING. Independence is NOT VERIFIED.** This plan assigns responsibilities, sets out what independence requires, and flags every conflict and missing fact instead of assuming it away. It records no signature, finding, date, approval or acceptance, and it changes no acceptance status. The frozen plans are unchanged.

## 1. The designation and the roles

| Role | Held by | What it means |
|------|---------|---------------|
| **Human reviewer** | **Sultan Zakariya**, designated by the owner | Performs the reviewer-run records, forms the review judgement and is the person the frozen plan means by "reviewer" |
| **Review accountability** | Sultan Zakariya | Answerable for every finding, record and recommendation made in the review, whoever or whatever helped to prepare it |
| **Human sign-off** | Sultan Zakariya, personally | Every signature, decision and independence or competence declaration the frozen plan requires is his own. Nobody and nothing signs or declares for him |
| **Technical inspection support** | **Z (Z.ai)**, used under Sultan's direction | A service Sultan uses to inspect, analyse and prepare supporting evidence (for example re-verifying CI results, checksums and contrast figures). It is **not** a separate or second reviewer, is not independent of Sultan's direction, may not impersonate him, and may not sign or declare anything on his behalf |
| Owner | The owner | Keeps the owner-only responsibilities (section 3) and every acceptance decision |

| Item | Value |
|------|-------|
| Designated by | The owner |
| Instruction recorded | 2026-11-13, from the owner's instruction to the delegated development session (the date the instruction was received, not a review date) |
| Engagement | The owner accepted the arrangement on 2026-11-13: Sultan is the human reviewer and uses Z (Z.ai) for technical inspection. This resolved an earlier ambiguity about whether Z.ai was a separate reviewer. It is an engagement decision only: it verifies no independence check, performs no record and accepts no criterion |
| Supporting correspondence | A letter from Z (Z.ai) accepting the engagement is held privately by the owner as supporting correspondence, read with the owner's clarification above. It is not published here |
| Scope | The outstanding manual acceptance and review records (17), within the limits in section 3 |
| Machine-readable form | [review-designation.yml](review-designation.yml) |

By the owner's instruction the human reviewer is named in this file and in review-designation.yml only. Every other public file, the 17 records and the generated register use the role label "Designated human reviewer". The designation does not record contact details, qualifications, declarations, a signature or any decision; those are given privately to the owner. An earlier commit in git history contains a name from before this arrangement, and no history rewrite is done without separate approval.

**Rules for the inspection support.** Its findings are inputs that Sultan reads, checks and adopts or rejects; he remains accountable for them. Where a check exceeds what it can verify, it says so plainly. Its output never becomes a record, a signature or an acceptance, and a record it prepares is not complete until Sultan has personally performed or verified it and signed it.

## 2. What the frozen plans require

From [CI-ACCEPTANCE-TEST-PLAN.md](CI-ACCEPTANCE-TEST-PLAN.md) and [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md):

1. Manual tests are executed by the **reviewer** or the **owner** as the plan assigns each one, and the evidence is **E7, a signed manual or owner record**.
2. A criterion is **accepted only when** all required automated and manual evidence is present **and a reviewer has recorded acceptance**. The four status fields (planned, implemented, executed, accepted) are independent and none is derived from another.
3. Tests and CI **never** supply acceptance. Nothing the implementer or the generator does can accept a criterion.
4. For C26-006 the attestation must be approved by someone **other than the author of the manifest**, before the run starts, with a verifiable approval record.
5. **Three records belong to the owner**: C17-M01 (naming scan and screenshot review), C31-M01 and C34-M01. The plan marks them as owner-run.

## 3. Assignment of responsibilities

| Responsibility | Assigned to | Notes |
|----------------|-------------|-------|
| Perform and sign (personally) the 14 reviewer-run records: C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, C19-M01, C20-M01, C20-M02, C25-M01, C27-M01 | Human reviewer (Sultan), who may use technical inspection support | Subject to the independence checks in section 4 |
| Perform C17-M01, C31-M01 and C34-M01 | **The owner** | The frozen plan assigns them to the owner. The designation cannot replace the owner on these. |
| Independently verify the owner's evidence for those three | Designated reviewer (additional, optional) | A separate section of each record. It does not replace the owner's own record. |
| Approve the C26-006 pre-run attestation, if the owner wishes the reviewer to do so | Open decision | Needs the reviewer's own GitHub identity (not the account that authored the pull request) and an agreed approval route. Not assumed. |
| Run the owner naming scan and supply its pass or fail result (C17-001, reported by C17-S01) | The owner | Never run by CI. |
| Owner decisions C21-021, C21-022, C21-M01 | The owner only | In [owner-acceptance-decisions.yml](owner-acceptance-decisions.yml). Not part of this designation. |
| Independent dynamic security scan | Recommended, not assigned | Not a frozen-plan requirement. |

Order of work that keeps the dependencies simple: C19-M01, C01-M01, C02-M01, C03-M01, then C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, then C20-M01, C20-M02, C25-M01, C27-M01.

## 4. Independence assessment

Each requirement is marked with what the repository can and cannot show. **No check is marked verified unless a fact supports it.**

| # | Requirement | Source | State | What is known and what is missing |
|---|-------------|--------|-------|-----------------------------------|
| I1 | Did not write, commit or approve the work reviewed | Frozen plan (reviewer, attestation approver) | **Evidenced by git history only** | Every commit in the repository history, checked when this plan was prepared, is authored by the implementer account. None is authored by the designated reviewer. This does not show who operates the GitHub account that pushed the work. |
| I2 | Is not the operator of the account that authored pull request 2 | Needed for any protected repository review (GitHub does not let an author approve their own pull request) | **Unknown** | The reviewer's GitHub login has not been supplied. If the reviewer operates the account that opened the pull request, a repository review would not be independent. |
| I3 | Holds no Noblen operational role on the site reviewed, and is not the owner's technical representative on the decisions reviewed | Separation of duties in the plugin; independence of review | **Unknown** | To be declared. Review accounts must be separate from any operational account. If the reviewer also approves publications on that site, C06-M01 and C10-M01 are not independent. |
| I4 | Owner-run records are performed by the owner | Frozen plan | **Conflict flagged** | See section 3. Not assumed away. If the reviewer and the owner are the same person, that person is not independent of the owner's decisions; if they are different, the owner still performs these three. |
| I5 | No conflicting interest (employment, financial or personal tie to the implementer, a supplier or a person whose work is reviewed) | Independence | **Missing** | No declaration exists. |
| I6 | Competence for the review | Records need WordPress administration, keyboard and screen-reader testing, device testing, backup and restore, reading an audit trail | **Missing** | No qualification statement exists. It is supplied by the reviewer and judged by the owner; it is not assumed from the name. |
| I7 | An agreed route for the signed record (E7) | Frozen plan | **Missing** | Not decided. Options for the owner to choose: a signed document held outside the repository with a reference recorded in the record; a signed message; or, for repository work, a review by the reviewer's own GitHub account. |
| I8 | Access to a staging site, test accounts, the release packages and real devices | Records' prerequisites | **Missing** | Not provisioned. |

**Conclusion:** the designation is the owner's decision and is recorded. It **cannot be called independent yet**. The repository shows no authorship conflict, but identity, role, interest, competence, signature route and access are all unconfirmed. Until the reviewer gives the declarations privately to the owner (each record carries only a private reference, section 7) and the owner has judged the qualification, any record the reviewer completes should be treated as **an unverified review**, and no record should be used to accept a criterion.

## 5. Conflicts and gaps flagged

1. **Ownership of three records** (I4). The designation as worded covers all outstanding records; the frozen plan reserves three for the owner. Decision needed: leave them with the owner (the plan's reading and the default here), or amend the plan through the owner's approval process. Not decided here.
2. **Identity link between the pull-request account and the reviewer** (I2). Needs the reviewer's own GitHub login, which is not recorded.
3. **Name in a public repository.** The owner instructed it, but earlier project guidance prefers role labels for private individuals ([CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md](CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md)). That file's `reviewed_by` field is unchanged and still uses a role label.
4. **No qualification or conflict declaration** (I5, I6).
5. **Signature route and access undefined** (I7, I8).
6. **A completed record is not yet connected to the acceptance register.** The register generator today reads owner decisions only for criterion 21. How a signed record feeds the "accepted" field of its criterion is not implemented. A decision is needed (see section 7).
7. **Operator interface gaps found while preparing the records.** Reading the audit trail, exporting it and confirming a backup before an upgrade existed only as PHP functions. This phase adds read-only WP-CLI commands (`wp noblen audit status|list|export`, `wp noblen migrate status|confirm-backup|run`) so that C25-M01 and C27-M01 can be performed by a person. Key rotation and recovery after loss had no operator interface; this phase adds `wp noblen key status|suspend|resume|rotate|complete-rotation|abandon-rotation|recover|complete-recovery|old-key-available` and an automated rehearsal on a disposable site (`tests-cli/key-custody-rehearsal.py`). C34-M01 now asks the owner to perform the rehearsal themselves on staging with throwaway keys, as the frozen plan's "custody and recovery rehearsal" requires. There is still no administration screen for these actions.
8. **An upgrade only runs at plugin activation.** The refusal is silent at that moment, so this phase adds an administration notice (and the existing Site Health item) that tells an administrator an upgrade is waiting and names the commands. There is no earlier release, so no real upgrade is pending today.
9. **C27-M01 is not in the frozen plan.** Criterion 27 is automated only in the frozen CI plan. C27-M01 was added by the implementation's register and the earlier pre-push review. The record is prepared as the owner asked, and says so. Provenance, effect and three resolution options are in [C27-M01-PROVENANCE.md](C27-M01-PROVENANCE.md); the choice is the owner's, and the default is to keep it supplementary.
10. **A pre-existing count error was corrected.** The earlier guide listed 18 rows under "the 17 items". `C17-S01` is a CI status item, not a record. The 17 records are the 17 `-M` items listed in the index.

## 6. How a record is completed

1. The performer sets up the staging environment described in the record and works through the procedure from their own observation.
2. They fill in every field, keep the evidence **outside the public repository** (it may show private data) and write only a reference to it in the record.
3. They give their independence declaration, conflict declaration and qualification information privately to the owner, and write only an identifier for them in section 7. None of that personal information goes in the public repository.
4. They set the decision to ACCEPTED or NOT ACCEPTED and sign by the route the owner agreed.
5. The record is committed by whoever holds it, in its own commit, so the history shows who changed it.
6. `tests-cli/acceptance-records-test.py` rejects a record that is half completed, gives no private declaration reference but claims a decision, or whose fields do not match its status.

Nobody, including the implementer, fills in a record, a result, a date or a signature on someone else's behalf.

## 7. From records to acceptance (decision needed)

A signed record is **evidence (E7)**. It is not by itself an acceptance of its criterion: the frozen plan also requires all automated evidence and a reviewer's recorded acceptance, and the register's `accepted` field is generated, never typed. The proposal, for the owner to approve before anything is built, is to let `owner-acceptance-decisions.yml` reference a completed record for each criterion, with the same fail-closed rules already used for criterion 21. Until then every record stays PENDING and `accepted_count` stays 0.

## 8. What stays reserved

Merging pull request 2, production deployment, DNS and production database changes, recording owner acceptance, and paid infrastructure remain separate approvals. Preparing these records does not perform any of them.
