# C26-003 to C26-006: Review Against the Frozen M2-D Scope

**Status: recommendation for owner review. Nothing here is accepted or implemented. The frozen plans are unchanged.**

## 1. What the frozen plans require

From the CI acceptance plan (criterion 26 and section 3) and the M2 plan (criterion 26, stage allocation):

| ID | Requirement | Tag | Stage |
|----|-------------|-----|-------|
| C26-003 | Plugin upgrade from the **registered source version and schema fixture** to the **registered target version**; counts and content hashes of posts, metadata, relationships, clearance, approvals and audit entries compared on a normalised logical dataset | `{ci\|PK\|E9, E11}` | M2-D |
| C26-004 | The audit chain verifies with the key before and after | `{ci\|PK\|E9, E11}` | M2-D |
| C26-005 | Every logical difference is covered by a declared transformation, and every declared transformation is exercised. No wildcards; no logical value lost or altered without an explicit preservation mapping | `{ci\|PK\|E9, E11}` | M2-D |
| C26-006 | Pre-run attestation and runtime binding check, with four failure conditions: missing, invalid, mismatched, late | `{ci\|PK\|E11}` | M2-D |

All four are automated (`ci`) and belong to **M2-D (packaging and release review)**, together with 19, 20, the final run of 16 and 17, and the manual items of 31 and 34. The M2 plan's standing rule says the four stages are never implemented in one uninterrupted operation, and each waits for the owner's review of the previous stage. C26-001 and C26-002 (theme switch, deactivate and reactivate) are M2-C and are already implemented.

## 2. What exists today

- A normalised logical-dataset export (`tests-e2e/wp-env/probes/logical-export.php`) covering posts with content hashes, metadata, relationships as ID sets, clearance, approvals, media rights, settings and existing audit entries, with a check that it detects a change. It already serves C26-001 and C26-002 and is the natural base for C26-003.
- A real migration (schema version 1 to 2, adding an index to the approvals table) with a lock, a backup confirmation and a failure state, tested for C25.
- An audit verifier and a key-custody implementation (C27, C34).

## 3. Facts that shape the boundary

1. **There is no earlier release.** The plugin has one version (0.1.0). Schema version 2 has existed since the plugin's first commit, so schema version 1 was never shipped. A "registered source version" therefore cannot be a past release; it has to be something the owner registers.
2. **The upgrade adds an audit entry.** The migrator records a `migration_applied` entry, so the audit table gains a row. The manifest must declare that as a transformation (existing entries unchanged, one entry appended), otherwise C26-005 fails.
3. **Approvals carry timestamps**, so a rebuilt fixture is not byte-identical across runs. The fixture must be built once and kept, or the comparison must map those fields explicitly.
4. **The attestation cannot be produced by the implementer.** It must be approved by a reviewer before the run, with a verifiable approval record, and its approval time must be strictly earlier than the platform-recorded start of the run attempt. A protected repository review on the commit that contains the manifest gives the stronger chronology, and that needs the branch to exist on GitHub.

## 4. Recommended implementation boundary

**Do not implement C26-003 to C26-006 in the current stage. Implement them as the first work of M2-D, after the owner's review of the M2-C report (SG-C).** No requirement is omitted or reclassified; all four stay automated.

When M2-D starts, the work splits cleanly:

**A. Repository-resident, implementable and testable locally**
- The manifest schema, and the manifest for migration 1 to 2 mapping every change to the logical field it preserves (index added; schema version option; `migration_applied` audit entry appended; backup-confirmation option).
- A fixture builder for the registered source (schema version 1 with a representative dataset: published and unpublished records, relationships, clearance, approvals, audit entries signed with a test key).
- The comparison harness for C26-003: export the logical dataset, upgrade, export again, and classify every difference against the manifest; fail on an undeclared difference (C26-005) and on a declared transformation that was never exercised (C26-005).
- C26-004: verify the audit chain with the key before and after.
- The CI runtime-binding generator (run ID and attempt, platform start time, reviewed-manifest commit, checked-out commit, SHA-256 of the manifest actually loaded, reference to the attestation) and the C26-006 validator, tested against **synthetic** records to prove each of the four failure conditions fires.

**B. Not producible by the implementer (prerequisites)**
1. **An owner decision registering the source and target**: I recommend registering the schema-version-1 fixture built from the current code as the source (it is the only upgrade path that exists) and the tip of the release commit as the target, recorded as fixed fields. The alternative is to wait for a first tagged release and register an upgrade from it, which defers C26-003 to a later release.
2. **A reviewer other than the author**, and a decision on the approval route: a protected repository review on the manifest commit (needs the branch pushed and branch protection with required review), or a separately retained signed record with its key fingerprint.
3. **Authorisation to push and run hosted CI**, because run IDs, attempt numbers and platform start times exist only there.
4. **The attestation itself**, created and approved before the comparison run, and a new attestation (new manifest hash and approval time) after any failed run.

## 5. What cannot pass until the prerequisites exist

C26-006 cannot pass without an approved attestation and a hosted run, and C26-003 to C26-005 cannot be accepted until the source and target are registered. Part A can be finished and verified locally first, so the only remaining steps afterwards are the approvals and the hosted run.

## 6. Interaction with the validator rules

Register validator rule 16 requires criterion 26 to pass only with C26-006 valid for every run attempt. The register validator itself (rules 1 to 16) is also not yet built; it is a separate M2-D item and should be built in the same stage.
