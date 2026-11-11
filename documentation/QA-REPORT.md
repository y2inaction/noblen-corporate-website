# QA Report

Scope: **M1 (documentation and planning only).** There is no code, theme or website to test yet, so page, form, responsive, accessibility and performance testing start in M2 and run through M9.

## M1 checks

| Check | Method | Result |
|-------|--------|--------|
| Relative links in README and `documentation/*.md` resolve | CI job `markdown-links`, also run locally | Pass |
| PHP syntax | CI job `theme` | No PHP files yet, so nothing to lint |
| Required theme files | CI job `theme` | Theme not added yet, so the check is skipped by design |
| Long numeric identifiers (certificate, tax, phone-length numbers) absent from the repository | Local scan (`[0-9]{7,}`) | None found |
| Private staff names, photographs, certificate documents absent | Manual review of all files | None present |
| Clearance records absent | Manual review | None present. The design keeps them as private WordPress fields |
| Marketplace naming | Manual review | Only "OnlineMart" appears. See limitation below |
| Licence unchanged | Diff review | Unchanged (proprietary) |
| PR #1 still a draft and unmerged | GitHub state | Confirmed |

### Limitation

The restricted business name has not been disclosed to this project, so it cannot be searched for. What has been verified is that the only marketplace names used anywhere are "OnlineMart" and "Online Marketplace". The M2 plan adds an automated CI check driven by a blocked-term list held outside the repository.

## Test plan for later milestones

Defined in [M2-IMPLEMENTATION-PLAN.md](M2-IMPLEMENTATION-PLAN.md): static checks, install tests on clean WordPress, dashboard workflows, visual and responsive screenshots at three widths, accessibility checks, Lighthouse baseline, placeholder-content scan, console-error check and link check.

## Defects

None open for M1. Outstanding items are content gaps, tracked in [PROJECT-PROGRESS.md](PROJECT-PROGRESS.md).
