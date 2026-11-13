# Final implementation report

Commit assessed: `7228471ebb22338bcab2e1c6ebaf1376b75dbbaa` (application code as of `28bbdbd`). Scope is the approved M2 scope plus the approved brand and logo requirement; nothing outside it was added. This report says what exists. What is verified is in [FINAL-QA-REPORT.md](FINAL-QA-REPORT.md); what is accepted is nothing ([ACCEPTANCE-TRACEABILITY-REPORT.md](ACCEPTANCE-TRACEABILITY-REPORT.md)).

## 1. Implemented

| Area | What exists |
|------|-------------|
| Plugin: content model | Five custom content types with relationships, clearance data, media rights, and a read-time filter so only cleared, approved content is public |
| Plugin: publication gate | Server-side gate on every write path; approvals bound to a snapshot; separation of duties; epochs invalidate stale approvals; roles for editor, clearance officer, approver |
| Plugin: audit trail | Hash-chained, key-signed trail; verification states; export with chain head; key custody (suspend, rotate, recover) |
| Plugin: operator interface | `wp noblen audit status|list|export`, `wp noblen migrate status|confirm-backup|run`, `wp noblen key status|suspend|resume|rotate|complete-rotation|abandon-rotation|recover|complete-recovery|old-key-available`; upgrade notice in the administration; Site Health configuration test |
| Plugin: data safety | Versioned schema, idempotent migration, refusal without a confirmed backup, retention on uninstall unless opted in, gallery media picker with a no-JavaScript fallback, languages folder |
| Theme: structure | Design tokens, header and navigation, breadcrumbs, page headers, archives, records with a facts panel, search, 404, empty and error states, home sections with order and visibility settings, partnerships section (renders nothing without data), SEO tags and Organization data from owner-entered settings only |
| Brand and logo | Logo upload, replace and remove through Site Identity and the Media Library; safe SVG; responsive sizes; alt text; wordmark fallback; dark-appearance logo. Brand Identity in the Customizer: 18 colour controls, live preview, light and dark appearance, built-in and saved presets, restore defaults, contrast warnings, optional logo-based palette suggestions (server-side, no external service). One change reaches every template through central tokens |
| Quality tooling | PHPUnit, WP-CLI suites, Playwright and axe browser suites, key custody rehearsal, fail-closed register generator, per-criterion counter, plan-register consistency test, record and designation validators |
| Packaging | Reproducible theme and plugin zips with checksums; clean install test; Configuration A and B Classic Editor isolation |
| Documentation | Deployment, installation, customization, administrator guide, recovery and key custody, security review and assurance, upgrade verification, risk and owner-decision registers, reviewer onboarding, acceptance guide and 17 record templates |

## 2. Defects and gaps found and fixed in this phase, with regression coverage

| Finding | Fix | Regression coverage |
|---------|-----|---------------------|
| SVG sanitiser could be bypassed through upload routes with their own prefilter (found while writing browser tests) | Safety net on attachment creation | `svg-test.php` 56 checks (mutation-tested); browser upload tests |
| Dark-appearance and default accent contrast failures | Palette derivation and defaults corrected | `brand-parity.js` (every preset in light and dark); axe on rendered pages |
| A refused database upgrade was silent at the moment of refusal | Administration notice and operator commands | `migration-notice-test.php`, `noblen-cli-test.sh` |
| Reading the audit trail, exporting and confirming a backup existed only as PHP functions | `wp noblen audit` and `migrate` commands | `noblen-cli-test.sh` (30 checks, hosted) |
| Key rotation and recovery had no operator interface, and wrong-key, compromise rotation and old-key cases were not each tested | `wp noblen key` commands and a disposable-site rehearsal | `key-custody-rehearsal.py` (69 checks; mutants caught; hosted from run 64). While writing it, two of its own checks passed for the wrong reason; they were corrected and re-proved with a mutant |
| Register title for criterion 26 differed from the plan; criterion 34's stated gap was out of date | Generator corrected; register regenerated | `plan-register-consistency-test.py` (144 checks) |
| Public files named the reviewer and used tick-box declarations | Role labels; private declaration reference; generator rejects declaration text | `acceptance-records-test.py`, `review-designation-test.py` |
| Contradictory status text (old branch and PR numbers, "pushing not authorised", "no records exist") | Corrected, with historical notes preserved | link check; manual reading |

## 3. Decisions made (routine technical judgement)

* User enumeration and XML-RPC are WordPress core defaults. They were **not** changed in code (outside the approved scope, and a behavioural change to core); they are documented as residual risks with host-level mitigation ([RESIDUAL-RISK-REGISTER.md](RESIDUAL-RISK-REGISTER.md)).
* The Configuration A job name is unchanged (cosmetic).
* The automated key rehearsal is supplementary evidence and does not stand in for the owner's C34-M01.

## 4. Not implemented, by design or by decision

Contact forms (deferred to a later milestone by the approved plan); a partner record type (owner decision); self-hosted brand fonts (needs a licensed file); the optional reveal animation; any administration screen for key custody; cross-browser test coverage beyond Chromium.

## 5. Obsolete or duplicate material

Historical documents (the M1 QA report, early progress notes, the pre-push review) are kept for auditability and now carry a note pointing to the current reports. A dedicated dead-code audit was not performed; none was noticed during this review.
