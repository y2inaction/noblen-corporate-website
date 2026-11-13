# Known limitations and residual-risk register

As of commit `7228471` (code `28bbdbd`). Severity is the implementer's judgement for a corporate site that has not launched. "Owner" means the owner must decide or act; nothing here is accepted on the owner's behalf.

| ID | Risk or limitation | Severity | Mitigation today | What closes it | Owner |
|----|--------------------|----------|------------------|----------------|-------|
| R-01 | No independent dynamic security scan or penetration test | Medium | Implementer review, static checks, hostile-input suites ([SECURITY-OPERATIONAL-ASSURANCE.md](SECURITY-OPERATIONAL-ASSURANCE.md)) | An independent scan on staging | owner decision |
| R-02 | Login names are publicly enumerable (`/wp-json/wp/v2/users`, `/?author=1`) by WordPress default | Medium | Separation of duties; documented host mitigation | Host rule, or an approved small hardening change; strong passwords and two-factor sign-in | owner decision |
| R-03 | XML-RPC enabled by WordPress default | Low to medium | The publication gate applies (tested); unauthenticated writes refused | Block `xmlrpc.php` at the host | host |
| R-04 | Security headers (frame policy, HSTS, CSP allowing the inline brand style block) are not set by the repository | Medium | Documented in [DEPLOYMENT.md](DEPLOYMENT.md) | Host or CDN configuration | host |
| R-05 | Only Chromium was tested; no Firefox, WebKit, real devices or screen reader | Medium | Axe, keyboard and contrast automation; scripted layout checks | Human records C13, C14, C15 on real devices | reviewer |
| R-06 | Recovery, backup restore and key rotation have not been rehearsed by a person | High until done | Automated rehearsal of the commands (69 checks) and documented runbooks | C20-M02 (reviewer) and C34-M01 (owner) on staging | reviewer, owner |
| R-07 | No real earlier release exists; upgrade verification used a code-built fixture. C26-003 to C26-006 are unsatisfied | High for the first upgrade | Upgrade comparison tooling, attestation checker, backup-confirmation gate | Registered source and target, independent reviewer, approved attestation, authorised hosted run | owner, reviewer |
| R-08 | No approved logo file, verified content, contact details or legal pages | High for launch | Site shows structure only; nothing invented | Owner-supplied content ([OWNER-CONTENT-REQUIREMENTS.md](OWNER-CONTENT-REQUIREMENTS.md)) | owner |
| R-09 | Reviewer independence is not verified; no record has been performed or signed | High for acceptance | Fail-closed tests; nothing accepted | Declarations and evidence from the reviewer ([REVIEWER-ONBOARDING.md](REVIEWER-ONBOARDING.md)) | reviewer, owner |
| R-10 | C27-M01 is outside the frozen plans | Low | Labelled supplementary ([C27-M01-PROVENANCE.md](C27-M01-PROVENANCE.md)) | Owner chooses keep, amend or withdraw | owner |
| R-11 | How a signed record feeds the register's accepted field is undecided | Medium | A record never changes `accepted_count` | Owner decision (REVIEW-PLAN.md section 7) | owner |
| R-12 | Test containers (wp-env 12.1.0) are not image-pinned | Low | Digests recorded in the CI log; documented exception ([DEPENDENCIES.md](DEPENDENCIES.md)) | A wp-env release that supports pinning | maintainer |
| R-13 | 11 `npm audit` advisories (10 moderate, 1 high) in the browser-test toolchain | Low | Not shipped; production dependencies: 0 | Toolchain upgrade when compatible | maintainer |
| R-14 | Persistent object-cache behaviour is untested (criterion 31 without a persistent cache) | Medium | Cache invalidation logic tested without one | Test on the real host; owner record C31-M01 | owner |
| R-15 | Performance measured only locally on fixture content over an uncompressed local server | Low | Lighthouse 98 to 99 locally; no third-party requests; tiny inline CSS | Re-measure on staging with real hosting and compression | host |
| R-16 | A reviewer name appears in earlier commits of the public history | Low | Public files now use role labels, except the designation file and review plan by the owner's instruction | A history rewrite, which needs separate approval | owner |
| R-17 | Fonts: system font stack only; no licensed brand font | Low | Documented | Owner supplies a licensed font if wanted | owner |
| R-18 | Optional reveal animation not built; no partner record type (partnerships section renders nothing without verified data) | Low | Both are out of the approved scope or need an owner decision | Owner decision | owner |
