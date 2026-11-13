# Security review

Reviewed at the commit recorded in the release report. **This is a code review plus automated checks, not a penetration test.** Nothing here claims the site is "secure"; it records what was checked, what was found and what remains. A passing result is stated only where a command or test produced it.

## Method and evidence

| Area | Check | Result |
|------|-------|--------|
| Coding standards and WordPress security sniffs | `vendor/bin/phpcs` (WordPress standard, includes escaping, nonce, input-validation and prepared-SQL sniffs) over theme and plugin | Clean; also green in hosted CI (C16-003, C16-004) |
| Dangerous functions | Search of plugin and theme PHP for `eval`, `assert`, `unserialize`, `exec`, `system`, `shell_exec`, `passthru`, `proc_open`, `popen`, `base64_decode`, `extract` | None used (one comment match only) |
| Request input | Search for `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, `$_FILES` | Used only in `Admin.php`; each use is unslashed and sanitised, or sanitised per field in `Store::save`, behind a nonce check |
| CSRF | Every state-changing handler | Fields and clearance saves verify a nonce; workflow and seed actions use `check_admin_referer`; links carry per-action, per-record nonces |
| Authorisation | Capability on every handler | `edit_post`, `noblen_manage_clearance`, `noblen_approve_publication`, `noblen_manage_settings` are checked server-side (for example `Workflow::approve()` returns `noblen_forbidden` without the capability; the gate tests exercise the forbidden paths) |
| Public attack surface | Search for AJAX, REST routes and `nopriv` actions | The plugin registers none. The theme registers three administration-only AJAX actions for the Customizer brand tools (logo analysis, save preset, delete preset); each verifies a nonce and `edit_theme_options`. No `nopriv` action exists. |
| SVG logo uploads | `tests-cli/svg-test.php` (56 hostile-input checks, mutation-tested) and browser uploads through the REST API, the admin media uploader and an unprivileged user | SVG is allowed for administrators only and rebuilt from an allow-list; scripts, event handlers, external references, embedded images, foreignObject, entities and DOCTYPE are removed or refused. A safety net on `add_attachment` covers other creation routes. Not browser-tested: XML-RPC and import routes. |
| Stored brand settings | `tests-cli/brand-persistence-test.php` | Invalid or hostile stored colour values (including CSS injection attempts) never reach the stylesheet; only validated hex colours are printed |
| Operator WP-CLI commands | `tests-cli/noblen-cli-test.sh` (30 checks) | `wp noblen audit` and `wp noblen migrate` run only from the command line (no web route). `audit list` and `audit export` need a user with the audit-reading capability (Publication Approver; an administrator is refused). Changing commands need `noblen_manage_settings`. Secrets are never printed (key identifier and fingerprint only); an export never overwrites a file. |
| Operator key-custody commands | `tests-cli/key-custody-rehearsal.py` (69 checks on a disposable site; three mutants caught) | `wp noblen key ...` delegates to the existing `KeyCustody` class, so capability, state, separation-of-duties and confirmation checks are unchanged and run only from the command line. Keys are only read from `wp-config.php`; output shows ids and 16-hex fingerprints; a leak scan of all output, exports, backups, the database and the PHP log found no secret. Refusals add no audit entry. Interrupted rotation, compromise rotation, loss and recovery, restore from backup, tamper detection and wrong or missing keys are all exercised. An automated rehearsal is not the owner's C34-M01 record |
| Logo analysis | Code review | Runs locally; reads only an image attachment the user may read; size limits; no external request |
| SQL | Queries using variables | Values go through `$wpdb->prepare`; the remaining interpolations are fixed table names built from `$wpdb->prefix` |
| Redirects | Handlers | `wp_safe_redirect` only |
| Output escaping | Theme and admin output | `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` (rich fields are also filtered by `wp_kses` in the accessor); phpcs `EscapeOutput` clean except the documented, locally escaped fact-panel output |
| File handling | Search for file writes and upload code | The plugin writes no files and handles no uploads itself; images come from the WordPress media library, and gallery items must be images (server-side check in `Store`) |
| Unapproved content exposure | Publication gate, read-time filter, REST, XML-RPC, quick edit, bulk edit, scheduling, import and WP-CLI paths | Covered by the PHPUnit and WP-CLI suites (C10) |
| Third-party requests | `scripts/check-no-third-party.sh`, browser tests | No external hosts in sources; smoke tests fail on any off-host request |
| Audit integrity | Hash chain with a configured key; tamper, removal and reorder detection | PHPUnit C27 and C34 |
| PHP/PHP-dependency advisories | `composer audit` | No advisories found |
| Node dev-tooling advisories | `npm audit` in `tests-e2e` | **11 reported (10 moderate, 1 high)**, all transitive dependencies of the pinned test tool `@wordpress/env` 12.1.0 (`isomorphic-git`, `glob`, `minimatch`, `qs` and others). See below. |
| Secrets | `scripts/audit-repo.sh` (credentials, tracked artifacts, local paths) | Clean |

## Findings

1. **Dev-tooling advisories (accepted, documented exception).** The `npm audit` findings are in the browser-test toolchain, which runs only in CI on disposable sites. It is not part of either installable package (the built ZIPs contain no `node_modules`, `tests-e2e` or `package.json`), and nothing in it is reachable from a running site. `npm audit fix --force` would downgrade `@wordpress/env` to 10.13.0, a breaking change to a pinned, verified tool. Recommendation: keep the pin, re-check when a newer `@wordpress/env` is adopted, and never run the test tools against a production site.
2. **The brand colours are printed as an inline style block**, so a strict Content-Security-Policy must allow inline styles (a nonce or `'unsafe-inline'` for styles). The values are validated hex colours only.
3. **No HTTP security headers are set by the application.** Content-Security-Policy, HSTS, X-Frame-Options (or `frame-ancestors`), Referrer-Policy and Permissions-Policy are server or CDN configuration. They are listed as deployment requirements, not implemented here, because a policy must match the real hosting.
4. **Audit key custody is operational.** The key must be generated by the owner and held in secure custody; the recovery procedure has not been rehearsed (C34-M01, C20-M02).
5. **No automated dynamic scan or penetration test was run.** A hosted production-like scan is a recommendation before launch.
6. **Comments, login and registration** use WordPress defaults and are outside this scope; harden them at the host (rate limiting, two-step sign-in).

No injection, authorisation-bypass, XSS or CSRF defect was found in the reviewed code. Absence of findings in a manual review is not proof of absence.

## Recommendations before launch

- Apply the HTTP security headers at the host or CDN and re-test the site.
- Enable two-step sign-in and rate limiting for administrator accounts.
- Run an independent dynamic scan on a staging copy.
- Keep WordPress, PHP and the host patched; re-run `composer audit` and `npm audit` at release time.
