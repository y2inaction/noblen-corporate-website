# Classic Editor 1.7.0: Hosted Verification Procedure

**Current state: the pin is VERIFIED; C21-021 and C21-022 are EVIDENCED — AWAITING OWNER ACCEPTANCE.** The pin was verified by the reviewed change in commit `ec38d8991a64a0ea0b666acd268964f02012cc00` after the independent review of 2026-11-12 (reference `NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12`), and the strict hosted run 43316091579 passed (job 131524371513; record artifact 13988531774, inspected by the owner: mode `STRICT`, C21-021 satisfied by that run). **Neither criterion is accepted**; acceptance is the owner's decision and criterion 21 as a whole still has the manual record C21-M01 pending. No stand-in or local result may be used as release evidence.

The frozen plan defines C21-021 as "Classic Editor plugin version and source **match the verified pin**" and C21-022 as "records the Classic Editor settings used for the run" (both `{ci|WE|E9}`).

## Principles

1. **No trust on first use.** The package hash is obtained from the official WordPress.org package, then **independently re-obtained and reviewed by a person**, then committed by a **reviewed change**, then checked again in a **later** run.
2. **No script writes the pin.** `scripts/check-pins.sh` fails if the pin is internally inconsistent or if it disagrees with the workflow.
3. **No silent update.** If a later download does not match the pinned hash, the run fails and the pin is never changed automatically.

## Roles

- **Developer:** triggers runs and prepares the pin change.
- **Reviewer (owner or someone the owner appoints):** performs the independent inspection and approves the pin change. The reviewer must not be the author of the pin change, otherwise it is not an independent control.

## Steps

**0. Prerequisite.** The owner separately authorises (a) pushing the commit that carries the capture workflow and (b) the capture run itself. Nothing below can happen before that.

**1. Capture run (developer).** Capture uses its own workflow, `.github/workflows/classic-editor-capture.yml`, so it can never be confused with verification. It is **not** started with a "Run workflow" button: GitHub offers manual dispatch only for workflows on the default branch, and the default branch (`main`) has no workflow files. The working trigger is the marker file:

   - The workflow runs on a `push` to a branch matching `feature/**` (this includes `feature/m2-implementation`) **when the push changes `tests-e2e/wp-env/capture-request.txt`**. It does not run on pull requests or on any other branch, and a push that does not change the marker does not start it.
   - The first line of the marker must be exactly `classic-editor <pinned version> capture request` (currently `classic-editor 1.7.0 capture request`). The workflow and `scripts/check-pins.sh` both refuse any other text.
   - The commit that first adds the capture workflow and marker therefore starts the first capture when it is pushed. To request another capture later, append a second line to the marker (for example `attempt 2`) in a reviewed commit and push it. Re-running the same run from the Actions page is also possible.
   - Pushing a feature branch also starts the normal CI. While the pin was unverified, the strict Configuration A job was expected to fail by design; the pin is now verified, so that job must pass.
   - `workflow_dispatch` is also declared, but it only becomes usable if the workflow file is on `main`.

   The `capture` job downloads the exact pinned URL (HTTPS only, official host check, size cap), checks it is a valid zip confined to `classic-editor/`, checks the plugin header version, installs it, runs `wp plugin verify-checksums` against WordPress.org's published per-file checksums, produces a **source-parity report** against the plugin's official GitHub commit, applies the Configuration A setting and runs the Configuration A suite. A later step asserts that the record says `mode: CAPTURE` and `C21-021: NOT SATISFIED`, that the pin is `unverified`, and that the pin file is unchanged. It writes the job summary and the `classic-editor-capture` artifact (the record, the downloaded zip and diagnostics). **The per-file source-parity report is printed in the job log** (step "Capture the pinned Classic Editor package"); it is not a file in the artifact, and the record carries only a one-line `source_parity` summary. The artifact cannot be downloaded without GitHub authentication, so a reviewer without repository read access must rely on a person who has it for the record's contents. **A capture run is material for review, not verification.** The workflow has read-only permissions, every action is pinned to a full commit SHA, and nothing in it can write to the repository or the pin. Once the pin is verified, capture mode refuses to run.

**2. Independent inspection (reviewer).** Do not rely on the CI output alone.
   a. On your own machine, download `https://downloads.wordpress.org/plugin/classic-editor.1.7.0.zip` and compute its SHA-256 (`shasum -a 256 classic-editor.1.7.0.zip`). It must equal `package_sha256` in the record.
   b. Open `https://wordpress.org/plugins/classic-editor/` and confirm that version 1.7.0 is listed as the stable version, and that the Advanced tab's download for 1.7.0 is the same address.
   c. Compare the package file by file (SHA-256) with the plugin's SVN tag `https://plugins.svn.wordpress.org/classic-editor/tags/1.7.0/`, and check that both file lists are identical in both directions. **The SVN tag is the primary per-file release reference.** Record the SVN revision of the tag at the time of comparison where possible; without it, a later change to the tag cannot be told apart from the state that was reviewed. (The review of 2026-11-12 did not record it; see `release_reference` in the pin.)
   d. Read the source-parity report in the job log. It compares the package with the GitHub development commit recorded in the pin, which is a **secondary** source: that repository has no release tags, so its commit is not tied to the release. Any `DIFFERENT` file, or any file listed as present only in the package, needs an explanation you are satisfied with. A difference is acceptable only if it is explained and the package equals the SVN tag; a difference in executable code with no explanation is not acceptable. A comment-only difference where the SVN release copy is ahead of the GitHub mirror is an acceptable explanation, and the explanation must be written into `source_parity_reviewed`.
   e. Confirm the job reported the WP-CLI checksum verification as successful, and that the settings record shows the default editor is the block editor. WP-CLI reads per-file checksums from `https://downloads.wordpress.org/plugin-checksums/<slug>/<version>.json`. The older-looking form `https://api.wordpress.org/plugins/checksums/1.0/?slug=<slug>&version=<version>` returned HTTP 404 for the independent reviewer, including for a control plugin, so it must not be assumed to work. Live reachability of an endpoint may be stated only where an actual successful response was obtained.
   If anything does not match, **stop**. Do not change the pin. Record what you found and tell the owner.

**3. Pin change (developer, reviewed by the reviewer).** One commit that changes only `tests-e2e/wp-env/classic-editor.pin.json` and the `continue-on-error: true` line of the `we-classic-editor-a` job:
   - `zip_sha256`: the hash from step 2a;
   - `verification.state`: `verified`;
   - `verification.zip_sha256_obtained_from`: the run ID and attempt of the capture run;
   - `verification.independent_download_sha256`: the reviewer's own hash (must equal `zip_sha256`);
   - `verification.source_parity_reviewed`: what was reviewed and the conclusion;
   - `verification.reviewed_by`, `reviewed_utc`, `review_record`. `reviewed_utc` must be substantiated by the review record: if the record establishes only a date, record only the date and say so in a note, and never invent a time. `review_record` is a reference identifier for the review, not proof that an archive copy exists;
   - `official_checksums`: the URL form WP-CLI uses (above), with a note that does not claim live reachability unless a successful response was recorded;
   - `release_reference` (the SVN tag comparison, including the SVN revision or the statement that it was not recorded) and `artifact_record_confirmed` (who or what read the capture record, without personal identities).
   `scripts/check-pins.sh` fails if any of these is missing, if the independent hash differs, or if the job is still non-blocking.

**4. Subsequent strict run.** Push the reviewed change and let the normal CI run (`ci.yml`, not the capture workflow). The `we-classic-editor-a` job runs in strict mode: it refuses to run on an unverified pin; it requires the downloaded SHA-256 to equal the pinned value; it requires the installed version to equal the pin; it re-runs the WP-CLI checksum verification; and it runs the Configuration A suite. The strict run's record and job summary are the E9 evidence for **C21-021** and **C21-022**. The reviewer then records the result in the acceptance register. Acceptance remains the owner's decision: it is recorded only in `documentation/owner-acceptance-decisions.yml`, on the owner's explicit instruction, and is never inferred from a green run. Criterion 21 is accepted only when C21-021, C21-022 and C21-M01 are all accepted there.

**5. Afterwards.** The job is blocking: a failed strict job fails the run (making it a required check in repository settings is a separate owner action). A hash mismatch, a version change or a failed checksum verification fails the run and requires a new review. A re-release under the same version number is an anomaly to investigate, not to accept.

## Limits of this procedure

- The runner and the reviewer both download from WordPress.org, so a compromise of WordPress.org itself would not be detected. The per-file checksums come from the same organisation. The GitHub comparison provides a second source for the code files only.
- The source-parity report covers the code and readme files, not assets.
- The development sandbox cannot reach WordPress.org or the GitHub artifact storage host, so it cannot perform the independent steps or read the capture artifact itself.
- The hosted capture has run (run 43286920565, commit `cb211fc39b05ebfffe5ea9fd6574206b089d3500`) and an independent review took place on 2026-11-12 (reference `NOBLEN-CORP-CE-INDEPENDENT-REVIEW-2026-11-12`). The strict hosted run on the verified pin has happened (run 43316091579, commit `ec38d8991a64a0ea0b666acd268964f02012cc00`, job 131524371513, success).
- The whole-ZIP SHA-256 was reproduced across two independent downloads and the runner's download on one day. Whether WordPress.org serves identical bytes over the long term is not established; a later strict run will test it.
- `reviewed_by` and `review_record` must not contain private staff names, certificate numbers or paths into private records. Use a role label (for example "owner-appointed independent reviewer") and a reference to a record held in the owner's own archive.
