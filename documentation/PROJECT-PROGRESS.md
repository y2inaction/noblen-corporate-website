# Project Progress

Repository: `y2inaction/noblen-corporate-website` (public, proprietary licence). Branch: `claude/cool-feynman-6n4u7n`. Draft PR #1 (not merged).

## Milestone status

| Milestone | Status | Notes |
|-----------|--------|-------|
| M1 Audit, content extraction and planning | **Documentation complete, awaiting owner sign-off** | Live-site audit not possible by crawling (see below) |
| M2 Brand system, theme, core plugin and publication gate | **In progress locally (not pushed). See [IMPLEMENTATION-STATUS.md](IMPLEMENTATION-STATUS.md). Nothing is accepted** | [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md) |
| M3 to M9 | Not started | |

## Approval status

| Gate | Status |
|------|--------|
| G0, final M2 technical plan | **Approved** (Revision 5.1 is the approved M2 technical baseline) |
| G0b, documentation-only commit of the approved plan | **Complete** (commit `8f920ff`) |
| Documentation-only commit of the CI and acceptance-test planning reference | One commit authorised. Push requires a separate decision |
| G1, dependency installation | Authorised by the execution directive; completed locally from the signed Ubuntu archive |
| G2, implementation branch and local commits | Authorised. Branch `feature/m2-implementation`, local only. **Pushing is not authorised** |
| SG-A, SG-B, SG-C, SG-D | Not authorised |
| G5, final release | Not authorised |

## CI and acceptance-test planning reference

[CI-ACCEPTANCE-TEST-PLAN.md](CI-ACCEPTANCE-TEST-PLAN.md) is the frozen, authoritative test register and validator specification for the M2 acceptance criteria. It incorporates the criteria and stage allocation of the approved baseline (`8f920ff`) by reference.

| Coverage field | Value |
|----------------|-------|
| Planned | True |
| Implemented | False |
| Executed | False |
| Accepted | False |

It is a planning reference only. It does not authorise tests, workflows, dependency installation or implementation.

## M1: what is completed

- Repository created by the owner, attached, inspected. It was empty, so nothing was overwritten.
- Scaffold, documentation skeleton and CI workflow.
- Brochure reviewed. Names-only project register built, with evidence levels.
- Portfolio architecture covering areas A to F, status models, 12 case-study fields and the publication gate.
- Owner decisions recorded: naming rule, privacy, exact brochure wording ("Matrix 360%"), design colour rule, proprietary licence.
- `PROJECT-PROGRESS.md`, `QA-REPORT.md` and the M2 plan added.

## M1: what remains

- **Live-site audit.** `noblen.org` rejects automated requests, and no workaround is used. An authorised WordPress export or supplied content is needed.
- **Group-level content:** legal structure, history, leadership, verified contact details, social accounts, logo and brand files.
- **Level B items:** status and evidence for CIDU, OnlineMart, ArewaGo, the Noblen AI Platform, the operating-system products, Property projects and corporate initiatives. They stay unpublished drafts.
- Owner sign-off of M1 and approval of the M2 plan.

## Commit history

| Commit | Description |
|--------|-------------|
| `da432a6` (main) | Initial repository base |
| `96b888a` | M1 scaffold, documentation skeleton, CI |
| `5d00d7f` | Portfolio architecture and content inventory |
| `e958b17` | Owner decisions recorded (naming, Matrix 360%, privacy) |
| `5492223` | M1 documentation complete: portfolio areas A to F, progress and QA documents, M2 plan |
| `d852ab4` | Progress record: commit history and CI results for `5492223` |
| `8f920ff` | M2 technical baseline approved (Revision 5.1, G0) |

## CI results

| Commit | theme | markdown-links |
|--------|-------|----------------|
| `96b888a` | pass | pass |
| `5d00d7f` | pass | pass |
| `e958b17` | pass | pass |
| `5492223` | pass | pass |
| `d852ab4` | pass | pass |
| `8f920ff` | pass | pass |
