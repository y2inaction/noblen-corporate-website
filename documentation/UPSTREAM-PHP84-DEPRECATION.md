# Upstream PHP 8.4 deprecation in WordPress 6.4.3 core

**Status:** informational, narrowly scoped. It is not a product defect and it does not change any acceptance criterion.

## What happens

M2 is built and tested against WordPress 6.4.3 (frozen by the plan). WordPress 6.4.3 predates PHP 8.4. On PHP 8.4 its core function `wp_getimagesize()` declares `$image_info = null` without a nullable type, and PHP 8.4 reports:

```
PHP Deprecated:  wp_getimagesize(): Implicitly marking parameter $image_info as nullable is deprecated,
the explicit nullable type must be used instead in <wordpress>/wp-includes/media.php on line 5481
```

This was observed on hosted run 43279338951 (job "Host WP-CLI, package install and uninstall retention (PK, PHP 8.4)"). It is the only kind of entry in the log in that job; every functional check in the job passes. PHP 8.3 does not report it. The plugin and theme are not involved, and the PHP 8.4 static, compatibility and PHPUnit jobs pass.

## Exactly what is set aside

`scripts/php-log-check.sh` (used by the host WP-CLI integration and package-install error-log checks C02-002, C01-002, C19-003) treats an entry as informational only when **all** of these hold:

| Condition | Required value |
|-----------|----------------|
| PHP version (reported by WordPress) | 8.4.x |
| WordPress version (reported by WordPress) | exactly 6.4.3 |
| Entry type | `PHP Deprecated` |
| Message | exactly the text above, for `wp_getimagesize()` and `$image_info` |
| Location | `<WordPress root>/wp-includes/media.php`, line 5481 (the root passed by the caller, so a copy under `wp-content` cannot match) |

Nothing else is set aside. There is no directory-wide or file-wide exclusion of WordPress core. In particular these all stay fatal (counted as failures): every plugin-originated and theme-originated entry of any type, every other core error or deprecation (including other lines of `media.php`), the same message under any other PHP or WordPress version, and the same text at any other line, file or root. If WordPress or PHP is upgraded, the exception stops applying by itself and the entry becomes a failure again, which is the signal to revisit it.

## How it is made visible

The script prints, in the CI log, how many entries were counted, a `NOTE informational upstream deprecation` line naming the exception and its count, and every unexpected entry (up to 40, truncated to 300 characters). The check still fails on anything unexpected.

## Regression tests

`tests-cli/php-log-check-test.sh` (32 checks, no WordPress or database needed, run in the static analysis job) proves the exception is narrow: positive cases for the exact message, and negative cases for other PHP and WordPress versions, other line, file, function, parameter, severity, appended text, plugin or theme or foreign roots, plugin and theme errors of every severity, other core deprecations, and mixed logs. It also checks that the diagnostics name the exception, and that the canary warning is still counted. Mutating the script to drop the path check, the line check, the PHP version check or the WordPress version check makes the tests fail.

## Scope limits

- Only the host WP-CLI jobs use this check. The wp-env job runs PHP 8.3, where the message does not occur, and its own log probe is unchanged.
- No acceptance criterion is changed, waived or accepted. The WordPress 6.4.3 requirement of the frozen plan is unchanged.
- Remaining PHP 8.4 evidence on hosted CI is pending until a push is separately approved.

## Local verification (not hosted evidence)

This sandbox has PHP 8.3.6 only (no PHP 8.4, no Docker daemon) and a MariaDB 10.11 server, not the pinned MySQL 8.4 image. Real WordPress 6.4.3 files were used.

| Check | Result |
|-------|--------|
| `php-log-check-test.sh` | 32 of 32, and four deliberate mutations of the script each make it fail |
| Host WP-CLI integration, PHP 8.3, MariaDB 10.11, real WP-CLI | 20 of 20 |
| Package build and clean install, PHP 8.3 | 12 of 12 |
| PHPUnit, PHP 8.3, MariaDB 10.11 | 146 of 146 (1722 assertions) |
| Replay of the actual hosted PHP 8.4 log lines (36 and 8 entries, hosted paths) | 0 unexpected on 8.4 / 6.4.3; the same 36 lines on 8.3 give 36 unexpected; the same lines plus one plugin warning give exactly 1 |
| PHPCS, PHP lint, PHPCompatibility 8.3, 8.4 and 8.5, no-third-party, repository audit, pin check, actionlint, package checksum reproducibility | pass |

**Not run locally:** the host WP-CLI and package-install suites on a real PHP 8.4, PHPUnit on PHP 8.4 and on MySQL 8.4. Those require a hosted run, which needs a separate push approval.
