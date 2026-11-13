# Remaining-work register

Established from the repository at commit `2901ceba929acb64a4c1a64f9e6ef49a58c10d00` (branch `feature/m2-implementation`, clean tree, local tip equal to remote tip), the frozen M2 plan (Rev 5.1) and the frozen CI acceptance plan. Hosted CI at that commit was green (CI run 14 and 15, Validate run 11). This register replaces no frozen document and records no owner acceptance. Status values: **open**, **in progress**, **done (evidence)**, **owner** (needs the owner or another human), **reserved** (separate approval gate).

## A. Theme (M2 section 8)

| ID | Requirement | Status |
|----|-------------|--------|
| T1 | Design tokens for colour, type scale, spacing, radii, shadows, breakpoints, motion (Rev 5.1 section 8). Only colour, radius and width existed. | open |
| T2 | Header, navigation and footer polish; footer shows only owner-entered facts. | open |
| T3 | Breadcrumbs on archives and records. | open |
| T4 | Homepage: group introduction, partnerships section (renders nothing without verified data), section order and visibility theme settings. | open |
| T5 | Internal templates: record layout with facts panel, related items, gallery, empty and error states. | open |
| T6 | `screenshot.png` for the theme. | open |
| T7 | Self-hosted fonts. Blocked on a licence-checked font file supplied or approved by the owner; until then the system font stack is the documented, licence-free choice. | owner |
| T8 | Reveal animation script (optional, reduced-motion safe). | open |
| T9 | Approved logo. A brand sheet (logo lockups, colour variants, sector icons, values) was shared in chat as an image only. No logo file is in the repository, so none is installed and none is recreated. The owner must supply the master file (SVG preferred, or PNG with transparency) and confirm which lockup is the approved one. The theme already supports the WordPress custom logo, so it can be uploaded in the Customizer or committed as an asset. The sheet also shows a green accent and a gold edition. The approved palette (M2 section 8) has no gold, so the gold edition is not used. Any green accent needs an owner decision. | owner |

## B. Plugin and integration

| ID | Requirement | Status |
|----|-------------|--------|
| P1 | Gallery field stores comma-separated media IDs; needs a media picker before launch. | open |
| P2 | `languages/` folder and text-domain loading. | open |
| P3 | Audit of input validation, output escaping, nonces, capabilities, SQL preparation and upload handling, with evidence. | open |
| P4 | Contact forms are deferred to a later milestone by the approved plan (M7). The theme offers an email link only. | deferred by plan |
| P5 | Persistent object-cache behaviour (criterion 31) is tested only without a persistent cache. | open |

## C. M2-D, packaging and release

| ID | Requirement | Status |
|----|-------------|--------|
| D1 | C26-003 to C26-006 (upgrade comparison, audit chain before and after, declared transformations, attestation and runtime binding). Boundary review recorded; prerequisites need an independent reviewer, a registered source and target, and an authorised hosted run. Part A (repository-resident tooling) is implementable. | open, owner for prerequisites |
| D2 | Release packaging reproducibility, checksums, fresh-install and package-install tests. | partly done; extend |
| D3 | Upgrade test from an earlier real release. None exists; a fixture must be described as a fixture. | owner |
| D4 | Container images used by the browser tests are not pinned (wp-env 12.1.0 offers no pinning). Record digests and document the exception. | open |
| D5 | Deployment, customisation, administrator, troubleshooting and rollback documents. `DEPLOYMENT.md` and `CUSTOMIZATION.md` are stubs. | open |
| D6 | Fresh install on a real WordPress.org download is unverified from this sandbox (the host is blocked). The hosted CI downloads it. | evidence from hosted CI only |

## D. Acceptance and owner inputs

| ID | Requirement | Status |
|----|-------------|--------|
| A1 | 17 manual acceptance records: C01-M01, C02-M01, C03-M01, C06-M01, C10-M01, C12-M01, C13-M01, C14-M01, C15-M01, C17-M01, C17-S01 (owner), C19-M01, C20-M01, C20-M02, C25-M01, C27-M01, C31-M01, C34-M01 (owner). None exist and none may be created by the implementer. Human-verification instructions are to be prepared. | owner |
| A2 | C21-021, C21-022 and C21-M01 stay pending until the owner decides. `accepted_count` stays 0. | owner |
| A3 | Owner naming scan (`scripts/naming-scan.sh`) to be run by the owner. | owner |
| A4 | Owner content: see `OWNER-CONTENT-REQUIREMENTS.md`. No corporate claims, people, addresses or contact details are invented. | owner |

## E. Reserved gates (not executed)

Merge of PR #2, production deployment, DNS, production database changes, destructive production operations, recording owner acceptance, and paid infrastructure.

## F. Known limitations carried forward

- The Configuration A job name still says "non-blocking until the pin is verified". It is cosmetic and the name is intentionally unchanged.
- CI runs twice per push on the PR branch (push and pull_request). No concurrency group; accepted.
- Hosted JUnit artifacts could not be downloaded from this sandbox; per-criterion evidence comes from the job logs.
