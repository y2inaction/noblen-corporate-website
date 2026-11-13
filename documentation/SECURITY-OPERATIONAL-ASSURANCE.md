# Security and operational assurance report

Assessed at commit `7228471ebb22338bcab2e1c6ebaf1376b75dbbaa` (code unchanged since `28bbdbd`), on 2026-11-13. This report extends [SECURITY-REVIEW.md](SECURITY-REVIEW.md) and records what was checked, how, and what remains. It is an implementer's review plus automated evidence. **It is not an independent security assessment and not a penetration test.** Nothing was run against a production system and no secret was used or exposed.

Evidence kinds: **hosted** (GitHub Actions, run ids in [RELEASE-READINESS-REPORT.md](RELEASE-READINESS-REPORT.md)), **local** (this session, disposable site), **static** (reading and searching the source), **live probe** (requests to the seeded local site).

## 1. Results by area

| Area | What was checked | Result | Evidence |
|------|------------------|--------|----------|
| Authentication and authorisation | Roles and capabilities; publication gate on every write path (editor, quick edit, bulk edit, scheduling, REST, XML-RPC, import, WP-CLI); separation of duties; only the Publication Approver role reads the audit trail | No defect found | PHPUnit 146 cases (hosted and local); admin workflow in a real browser (hosted and local) |
| Capability enforcement in the operator commands | `wp noblen audit|migrate|key` refuse without a user, for a subscriber, and for an administrator who lacks the audit capability | Enforced; refusals write no audit entry | `noblen-cli-test.sh` 30 checks and the key rehearsal 69 checks (hosted and local) |
| Input validation and output escaping | Searched the source for raw superglobals, `eval`-family functions, `unserialize`, shell execution, `extract`, `base64_decode` | None found outside tests; PHPCS WordPress standards clean | static; PHPCS (hosted and local) |
| SQL | Every `$wpdb` call outside prepared statements uses table names built from the prefix and fixed identifiers, never user input; the two relationship queries use `prepare` | No injection path found | static |
| File uploads and SVG | SVG uploads limited to administrators; rebuilt from an allow-list (no scripts, no external references, no DOCTYPE or entities); all upload routes covered by a prefilter and an attachment safety net; hostile-input suite | Passes; no bypass found | `svg-test.php` 56 checks (mutation-tested), browser upload tests (hosted and local) |
| CSS injection through brand settings | Stored colour values validated; hostile stored values never reach CSS | Passes | `brand-persistence-test.php` 23 checks |
| REST exposure | The plugin registers no REST routes; the five content types are not exposed through REST (`/wp-json/wp/v2/noblen_company` returns 404 anonymously) | As designed | live probe; PHPUnit |
| XML-RPC | Enabled by WordPress default; unauthenticated calls cannot write (wrong credentials are refused); the publication gate applies | Residual risk R-03: disable at the host unless needed | live probe; PHPUnit |
| User enumeration | `/wp-json/wp/v2/users` and `/?author=1` reveal login names (WordPress default) | **Residual risk R-02.** Not changed in code, because it is core behaviour outside the approved scope; host-level mitigation in [DEPLOYMENT.md](DEPLOYMENT.md) | live probe |
| Secrets and configuration | The audit key is read only from `wp-config.php`; the plugin never creates, stores or prints one; no secret in the repository, in command output, exports, backups or the database; `wp-config.php` returns an empty body when requested | Passes | repository audit; rehearsal leak scan (secrets absent from every output, export, backup, database dump and the PHP log); live probe |
| Database migration safety | Upgrade refuses without a confirmed backup; failed upgrade blocks publication without data loss; pending upgrade notice; idempotent repeated runs | Passes; no real earlier release exists, so only fixtures were upgraded (C26-003 to C26-006 stay unsatisfied) | PHPUnit; `migration-notice-test.php` 9 checks; upgrade comparison on a code-built fixture |
| Backup and recovery | Procedures documented; restore from backup, with and without the signing key, exercised on a disposable site | Automated pass; **no human rehearsal** (C20-M02, C34-M01) | key rehearsal J1 and J2; [RECOVERY.md](RECOVERY.md) |
| Key custody and rotation | Planned, interrupted and compromise rotation; suspension; recovery after total loss with sign-off, recovery authority different from the executor and a typed confirmation; old key returning; tamper detection; wrong, missing and invalid keys | 69 of 69, mutants caught; history never rewritten | `tests-cli/key-custody-rehearsal.py` (hosted from run 64 and local) |
| Logging and audit trail | Hash-chained, key-signed trail; modification, removal and reordering detected; entries unverifiable only when their key is absent, never reported as verified | Passes | PHPUnit C27 and C34; rehearsal H1 and I15 to I21 |
| Dependency vulnerabilities | `composer audit`: no advisories. `npm audit --omit=dev`: 0 vulnerabilities. `npm audit` (all): 11 (10 moderate, 1 high) in the browser-test toolchain only | Accepted exception, documented; the toolchain is not shipped | local, 2026-11-13 |
| External services | No third-party requests from the theme or plugin (static scan and browser checks); no external fonts, scripts or analytics | Passes | `check-no-third-party.sh`; smoke tests (hosted and local) |

## 2. Accepted limitations and residual risks

The consolidated list is [RESIDUAL-RISK-REGISTER.md](RESIDUAL-RISK-REGISTER.md). The ones that matter most for security: R-01 no independent dynamic scan; R-02 user names are enumerable; R-03 XML-RPC enabled by default; R-04 host security headers not set by the repository; R-05 only Chromium tested; R-06 no human recovery rehearsal.

## 3. What was not done and why

* No penetration test, fuzzing campaign or dependency-confusion review beyond the audits above.
* No test against a production host, a CDN or a persistent object cache.
* No security scan of the host operating system or PHP configuration (the repository does not control them).
* Firefox and WebKit behaviour of the SVG and Customizer paths is untested.
