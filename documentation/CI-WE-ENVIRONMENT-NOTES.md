# CI "WE" Environment: Implementation Notes and Contingency Proposal

**Status: for owner review. Not accepted.** This note does not change [CI-ACCEPTANCE-TEST-PLAN.md](CI-ACCEPTANCE-TEST-PLAN.md), which stays frozen.

**Update (2026-11-13):** this note was written before the later hosted runs, and sections 3 to 6 describe the state at that time. Where they conflict with later evidence, [IMPLEMENTATION-STATUS.md](IMPLEMENTATION-STATUS.md) is current: wp-env starts on hosted runners and the hosted WE job has passed in the later runs, so the section 6 contingency has not been needed, and the Classic Editor pin is now verified (see the Configuration A bullet in section 5). No acceptance decision is made or changed by this note or this update.

## 1. What the frozen plan requires

The plan defines environment **WE** as `wp-env` (section 1, the Environment row). Tests tagged `{ci|WE|...}` are therefore to run in wp-env. Environment **PU** is separate: PHPUnit on pinned WordPress core and **MySQL 8.4**. WE does not name a database.

WE-tagged tests include C01-001 to C01-003, C03-002 and C03-003, C04, C06-001 and C06-002, C11, C12-001, C13, the automated parts of C14, C15-002 (axe), C18-001 (network log), and C26-001 and C26-002.

## 2. What was implemented

`.wp-env.json` (committed) and the `we` job in `.github/workflows/ci.yml`:

- wp-env **12.1.0**, pinned exactly in `tests-e2e/package.json` and its lockfile (needs Node 22.13 or newer; the job uses Node 22).
- WordPress core `WordPress/WordPress#6.4.3`, PHP 8.3.
- The plugin and theme are mounted from the checkout. A test-only must-use plugin (`tests-e2e/wp-env/mu-plugins/noblen-ci-config.php`) supplies a throwaway audit key and marks the site as disposable. The seed script refuses to run anywhere without that marker (or an explicit variable) and a loopback address.
- The job runs `tests-cli/we-integration.sh` (real WP-CLI inside the wp-env container plus HTTP checks), then the browser smoke, admin-workflow and accessibility suites against `http://localhost:8888`.
- wp-env's tests environment is disabled (`testsEnvironment: false`), as wp-env itself recommends.

## 3. What was verified locally, and what was not

| Item | Verified? | How |
|------|-----------|-----|
| `.wp-env.json` is accepted and parsed by wp-env 12.1.0 | Yes | `wp-env start` read it and generated the Docker configuration |
| Generated configuration mounts the plugin, theme, must-use plugin, seed file and probes, uses PHP 8.3 and a MariaDB database image | Yes | Inspected the generated `docker-compose.yml` |
| WE test script logic | Yes, 20 of 20 | Run with a shim that routes `wp-env run cli` to local WP-CLI on a fresh database. This validates the script, not wp-env |
| Workflow syntax, SHA pinning, least-privilege permissions | Yes | actionlint, 0 errors |
| **The containers actually starting, and the browser suites running inside wp-env** | **No** | See section 4 |

## 4. The limitation

wp-env builds its own Docker images, and those builds download packages (`apt-get`, `apk`, getcomposer.org) at build time. This sandbox allows outbound traffic only through an allow-listed proxy that containers cannot reach or trust (documented in the sandbox's own proxy notes, which say to report rather than work around it). The images were pulled from Docker Hub after retries on rate limiting, but the build step failed at `apk update`. I did not patch wp-env, install a trust certificate into images, or use an unofficial mirror. wp-env's core lookup of the "latest WordPress version" also needs wordpress.org, so I primed wp-env's local cache entry for that one lookup in this sandbox only. CI needs none of this.

GitHub-hosted runners have unrestricted outbound access, so wp-env is expected to work there. That has not been demonstrated.

## 5. Known risks of the first hosted run

- Non-interactive `wp-env run cli` behaviour (no terminal) and its output format could differ from the shim; the script trims carriage returns and blank lines but this is untested against the real tool.
- Apache rewrite rules and permalinks in the wp-env container were untested; they are now exercised against an Apache replica, but not in wp-env itself.
- The Docker Hub rate limit seen here could affect a runner on a shared address, although less likely.
- wp-env uses MariaDB, not MySQL 8.4. Plugin behaviour on MySQL 8.4 is covered by the PU job only.
- The WE job now also runs: all five content types through the admin UI (C06), partial data on a pristine site before seeding (C11), Classic Editor Configuration B (C21), and a site crawl including the core sitemap (C12-001). Content hashing for C26-001 and C26-002 is in the WE integration script. None of these has yet run inside wp-env on GitHub (the first hosted run stopped at the theme defect). They were run locally in real Chromium against PHP's built-in server and, after the correction, against the Apache replica described below.
- C21 Configuration A runs in its **own** job (`we-classic-editor-a`), separate from the main WE job, so Configuration B never sees the plugin. At the time of this note the job was non-blocking and refused to run in strict mode until the Classic Editor pin was verified. **Current state:** the pin is verified, the job is blocking, and the real WordPress.org package has been exercised in the hosted capture run 43286920565 and the strict runs that followed. C21-021 and C21-022 are evidenced and await the owner's acceptance decision. See [DEPENDENCIES.md](DEPENDENCIES.md), [CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md](CLASSIC-EDITOR-VERIFICATION-PROCEDURE.md) and [IMPLEMENTATION-STATUS.md](IMPLEMENTATION-STATUS.md).
- Still not covered: C26-003 to C26-006 (M2-D; see [C26-M2D-BOUNDARY-REVIEW.md](C26-M2D-BOUNDARY-REVIEW.md)).
- Reviewed against wp-env 12.1.0 source: `wp-env run` adds `-T` without a terminal, passes arguments as an array (quoting survives), prints its notices on stderr, exits non-zero on failure, **activates plugins but NOT themes**, and the core tag `6.4.3` exists upstream.
- **Correction.** An earlier version of this note said wp-env also activates the first theme. That was wrong: it rested on a comment in wp-env's source, and the code has no theme activation. The first hosted run exposed it (see the next section).

## 5a. First hosted run (commit `de59ce1`, run 43259095276) and what it established

**Demonstrated on GitHub:** wp-env 12.1.0 starts (59 seconds), runs WordPress 6.4.3 on PHP 8.3 (asserted inside the container), and `wp-env run cli wp ...` works non-interactively. The empty-site check ran.

**Defects found (all developer-owned, corrected locally, not yet re-run on GitHub):**
- The Noblen theme was **never active** in the wp-env site (see the correction above). `tests-cli/we-site-state.sh` now activates it and proves it from the rendered front page; both WE jobs run it before any test, and `partial-data.sh` refuses to run without it, because "no sections" passed for the wrong reason.
- Single-file mappings made Docker create **root-owned parent directories** inside the checkout, so `wp-env destroy` could not remove the mapped file. Reproduced locally with a non-root user; directory mappings (`wp-content/mu-plugins`, `wp-content/noblen-e2e`) remove cleanly.
- The debug-log artifact was empty when WordPress logged nothing. A probe now prints an explicit message, and container state and logs are collected as well.

**Local reproduction of the wp-env topology (not wp-env itself):** an Apache container (`wordpress:php8.3`), a separate WP-CLI container (`wordpress:cli-php8.3`) with the same directory mounts, and MariaDB 12.3, with `wp-env run cli` replaced by a shim. This exercised real Apache rewrite rules and the core sitemap, which PHP's built-in server cannot. It found a gap in my replica (missing `mod_rewrite` hint), not in the product. It cannot show how wp-env itself behaves.

## 6. Contingency proposal (to use only if wp-env proves infeasible on hosted runners)

**Proposed deviation, not accepted:** run WE-tagged tests on the retained non-WE environment, a disposable WordPress 6.4.3 served by PHP's built-in server with real WP-CLI and MySQL 8.4 (the `parity-browser` and `host-wpcli` jobs).

- **Why it might be needed:** a persistent wp-env or Docker failure on hosted runners that cannot be resolved by configuration.
- **Alternatives:** WordPress Playground (named in the M2 plan as an alternative test environment, but not in the frozen CI plan); pinning wp-env to another version; a self-hosted runner.
- **Risks:** it is not the environment the plan names, so WE evidence would need an explicit owner decision to count. Apache, PHP-FPM and container behaviour are not exercised.
- **Affected criteria:** every criterion listed in section 1.

Both non-WE jobs already exist and ran locally with the results in [IMPLEMENTATION-STATUS.md](IMPLEMENTATION-STATUS.md). No acceptance decision is made here.
