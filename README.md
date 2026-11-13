# Noblen Corporate Website

Official corporate website and custom WordPress theme for Noblen Technologies Limited, showcasing its subsidiaries, industries, solutions, projects and corporate ecosystem.

> Status: **M2 implementation is on the `feature/m2-implementation` branch (draft pull request, not merged, not deployed).** Hosted CI is green; manual acceptance records, owner content and several owner decisions are still outstanding. See [REMAINING-WORK-REGISTER.md](documentation/REMAINING-WORK-REGISTER.md) and [IMPLEMENTATION-STATUS.md](documentation/IMPLEMENTATION-STATUS.md).

## Repository layout

| Path | Purpose |
|------|---------|
| `wordpress-theme/noblen-corporate/` | The installable WordPress theme: presentation only (planned for M2) |
| `wordpress-plugin/noblen-corporate-core/` | The companion core plugin: content types, CMS and the publication gate (planned for M2) |
| `documentation/` | Architecture, content inventory, installation, customization, deployment |
| `.github/workflows/` | Automated validation (CI) |

## Roadmap

| Milestone | Scope | Status |
|-----------|-------|--------|
| M1 | Repository creation, audit and planning | Documentation complete, awaiting sign-off |
| M2 | Brand identity, theme, core plugin and publication gate | Implemented on a draft pull request; under release review ([plan](documentation/M2-IMPLEMENTATION-PLAN.md)) |
| M3 | Homepage and corporate navigation | Pending |
| M4 | Subsidiary websites and group structure | Pending |
| M5 | Industries, solutions and services | Pending |
| M6 | Portfolio, projects and corporate insights | Pending |
| M7 | Contact, partnerships and WordPress CMS | Pending |
| M8 | SEO, security and performance | Pending |
| M9 | QA and final documentation | Pending |

## Workflow

- `main` is kept stable; work happens on branches and lands through reviewed pull requests.
- Nothing here deploys, and nothing touches the live site, hosting, DNS or production databases.
- Technical decisions are recorded in `documentation/WEBSITE-ARCHITECTURE.md`.
