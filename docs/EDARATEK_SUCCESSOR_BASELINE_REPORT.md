EDARATEK_SUCCESSOR_BASELINE_REPORT

SIX_DELTAS_PM_APPROVED=YES
APPROVAL_DATE=2026-11-19
APPROVAL_SOURCE=Current PM conversation; no human GitHub review or cryptographic signature claimed
HISTORICAL_PHASE8_MANIFEST=PRESERVED
HISTORICAL_CHECKER=PRESERVED
HISTORICAL_SOURCE_BYTES_IDENTICAL=NO; exactly six intentional approved differences
SUCCESSOR_MANIFEST=tools/testing/successor-acceptance-v1.json
SUCCESSOR_VERSION=edaratek-google-successor-v1
CANDIDATE_SHA=cec3e0797512a44b3f66325108d4619eea006984
SUCCESSOR_VALIDATION=PASS_LOCAL
NEGATIVE_TESTS=PASS; 37 negative and 2 positive checks
CANDIDATE_INVENTORY=PASS; 777 base sources, all 44 proposal candidate files; 781 current inventoried files
ORIGINAL_IMMUTABLE_SOURCES=162 checked; exactly six approved replacements
FUNCTIONAL_GATES=INDEPENDENT; existing test assertions and functional lanes retained
CI_WORKFLOW=PREPARED_LOCAL_ONLY; no new GitHub run executed
NEW_GITHUB_ACTIONS_RESULT=NOT_EXECUTED
GOOGLE_BUSINESS_LOGIC_CHANGED=NO
AUTHENTICATION_CONTRACT_CHANGED=NO
FLUTTER_CHANGED=NO
HISTORICAL_EVIDENCE_CHANGED=NO
COMMIT=NO
PUSH=NO
MERGE=NO
DEPLOYMENT=NO
SERVER_ACCESS=NO
DATABASE_MIGRATION_EXECUTION=NO
FIREBASE_CONSOLE_CHANGED=NO
PRODUCTION_TOUCHED=NO
FINAL_STATUS=SUCCESSOR_GATE_AWAITING_PM_REVIEW

The original Phase 8 checkpoint is `8020e8b9363e1d100a1012c9b0a68d245169f2c1`. Its `tools/testing/phase8-scope.json` remains byte-for-byte unchanged with SHA-256 `5862f8395099ac4546ec5a4252443fb5b40ec1c76b978d0233d556702ac9df3d`. The historical `check-frozen-baseline.php` remains unchanged (`6cb9d913405d56528b9ed6ff0e97d13c55d3f6b1aea504caccdf1abf4011e318`) and still reports six differences / exit 29 against current sources. That is historical comparison evidence, not a claim that those source bytes stayed identical.

The previous proposal and frozen review document are also unchanged. Their historical PENDING labels describe their original preparation. The new versioned successor manifest records the current explicit PM approval separately; the old proposal is not edited into a retrospective approval. The approval date is the current conversation date. No person identity, external review event or signature has been invented.

Prior actual Linux evidence: [GitHub Actions run 44535861202](https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/44535861202), for the candidate SHA above. Functional job passed 761/761 tests / 7254 assertions, Google 34/34, Excel 4/4, original four concurrency cases and Linux `/dev/null`. The run overall failed its historical fingerprint job. These are results for the earlier candidate, not proof of a new successor workflow run.

Approved six-delta review:

- `.github/workflows/deploy.yml`
  - Frozen: `349264dca87ac571ae508f130a9d79267e67ef18a0c1e52f640633d488e2c317`
  - Approved candidate: `2f3bbc1e7acfb1420847a78cbaec618d8c3b422c7362d059065c690d41b99a2f`
  - Original review reason: Current security-remediation authorization: remove automatic main push deployment; manual test-only validated source handoff

- `app/Providers/AppServiceProvider.php`
  - Frozen: `3242e8d5fd52a47da33e1a2f6ff761e47e35422aec114ecf439d1f5b0d74823f`
  - Approved candidate: `4eb4407ca4b2b8f8963ca6f1b9f304b3a43b65575d4bf7237c9125b7fd72c49f`
  - Original review reason: PM-authorized local Google verifier DI bindings; no existing provider removed

- `app/Services/Mobile/NativeRateLimiter.php`
  - Frozen: `7fb53c630169d05f476b1ecca39e4715bb4c3a4e26b8872c4d03ca53dd89a62d`
  - Approved candidate: `5843307ad0825ee5bc497655261c8841e04edaf7521b71e03a86172f15df1f17`
  - Original review reason: PM-authorized Google admission and shared account budgets; original assertions preserved

- `app/Support/ApiError.php`
  - Frozen: `a537e33e7195c34255aaf8a6c248c38f55b31a8dd57ff9429dd4900644a4f9f3`
  - Approved candidate: `33ab57126a97cc3ea60a9175c080fe9250b28635ec4e3819347624bf9c50c5f8`
  - Original review reason: PM-authorized six safe Google error codes; accepted native envelope retained

- `routes/api.php`
  - Frozen: `5a8d29999684115f3f45042c87318dab5bf9793a430ddbc351dcc83256e0223a`
  - Approved candidate: `c989d7aefe23829b5e5948017ada7bad0263c5f560f130cf23c1b36a33ef82c4`
  - Original review reason: PM-authorized four Google routes; existing password/session routes retained

- `app/Services/School/StudentImportService.php`
  - Frozen: `fad0e59a9b87fde756bbb5b8d6caef5f8bdb7505cecb1d08f87a3272fbb69e36`
  - Approved candidate: `08860e81c936f9d3b64b5ed7849c2437dd916ab24db5f713192ef7276a0e75fe`
  - Original review reason: Current PM Step1 authorizes the established one-line Excel fix: Query Builder whereKey incorrectly addresses schools.key; explicit where(id) preserves the school-row lock, transaction, authorization and original assertions. Additional sixth proposal only; no frozen baseline approval.

The original review reasons are retained verbatim as provenance; the new approval block determines current successor status. No seventh historical-source exception is permitted.

The new source inventory is extracted from raw blobs in the exact candidate Git tree, including export-ignored `.github` workflow files. Its SHA-256 is `d07dca0602ff4db34d8ddc165c8cf6b8c49baad92836b406bd4bf10fe91c1361`. It pins 777 source files across app/bootstrap/config/database/lang/resources/routes/tests/tools/testing/tools/release/.github/workflows, selected root build files, four public bootstrap assets and four contract/review documents. Generated bootstrap cache, vendor, storage and build artifacts are outside this source inventory. All 44 original proposal candidate entries must match this pinned base inventory. New files under the protected source directories fail unless explicitly inventoried gate files.

The strict validator pins the historical manifest, old checker, old proposal and original review document. It requires the exact six original paths and reviewed hashes, the exact PM approval metadata, the exact candidate SHA and the prior CI provenance. It checks every one of the 162 historical immutable entries, then all current inventory hashes and path membership. Missing files, extra source files, duplicate entries, unapproved paths/hashes, approval omission, false historical identity and invented GitHub/signature claims fail. CLI errors emit only safe classifications and exit 29.

The current task explicitly requires gate tooling changes beyond the six business-source differences. Those are separately inventoried, with their candidate-parent hashes and current hashes: the security-validation workflow, run-security-gate.sh and run-tests.ps1. Two new PHP gate tools are hashed as gate files. The fixed base inventory and the strictly validated successor manifest account for the other two new files. The manifest excludes its own hash to avoid a circular self-hash; the fixed schema, fixed approval fields and pinned provenance anchors are validated instead. These are repository integrity checks, not tamper-proof signed human attestations; their code and manifest still require normal source review before any separately authorized commit.

Source and disposable-image profiles are explicit. Image mode allows only the two existing, hash-pinned Phase 8 image overrides for the isolated test superclass and phpunit configuration; it adds no arbitrary source exceptions. The negative tests also prove a fixture using those exact overrides passes. Neither profile starts Laravel or connects to a database.

The existing frozen_phase8 job ID is retained with the visible name "SUCCESSOR_BASELINE_ACCEPTANCE (historical Phase 8 preserved)". A separate HISTORICAL_PHASE8_INTEGRITY step checks the exact manifest and historical-checker SHA-256 values; this does not assert that all historical source bytes remain identical. The job runs the negative tests and strict successor gate using the existing pinned PHP image, a read-only source mount and no runtime network. The original historical checker remains available independently. The functional isolated_linux job is labeled LINUX_FUNCTIONAL_VALIDATION and remains independent, with no needs condition, skip or continue-on-error added. Functional test lanes and assertions are retained, and strict successor source validation runs before and after them. The original source-guard tests are also retained.

The filtered test runner adds the unchanged frozen review document for provenance verification. Its empty Compose environment input is moved to the task-owned scratch root as `.env.runner-empty`, which existing `.dockerignore` rules exclude from the image; this avoids allowing a generated file in the source inventory. No real environment file is read or overwritten. Source copies remain allowlisted and local Docker/MySQL isolation remains unchanged.

Local validation: PHP 8.2.35 syntax checks, strict successor source check, 39 successor guard checks (37 negative), existing source-guard 8 / case-coverage 11 / summary-guard 17 checks, actionlint 1.7.12, PowerShell parsing/self-test, shell syntax, filtered scratch preparation and Git whitespace checks. No full Laravel, new Linux container, database or new GitHub run was executed for this source-only gate change. The earlier 761/761 Linux result remains evidence for the earlier candidate only.

Negative coverage includes absent/pending approval and missing date/authority; invented review/signature claims; absent or wrong provenance; wrong CI/commit identity; falsely identical history; five/seven/duplicate/unapproved deltas; wrong old/new hashes; additional immutable-source drift; missing/changed approved and candidate files; extra source files; changes to frozen evidence, checker, proposal, review or base inventory; missing inventory/gate files; unauthorized tooling exceptions; workflow drift; unknown metadata and invalid profiles. All use disposable local fixtures, restored after each case, with no database/network operations.

Changed paths:

- `.github/workflows/security-validation.yml`
- `tools/testing/run-security-gate.sh`
- `tools/testing/run-tests.ps1`
- `tools/testing/check-successor-baseline.php`
- `tools/testing/test-successor-baseline.php`
- `tools/testing/successor-source-inventory-v1.json`
- `tools/testing/successor-acceptance-v1.json`
- `docs/EDARATEK_SUCCESSOR_BASELINE_REPORT.md`

The manual deployment-review workflow is not changed. It still uses the old release/proposal gate, which remains closed; successor validation does not silently authorize deployment. No new Git operation or GitHub execution is authorized by this local preparation. Any later feature-branch commit/push and actual Ubuntu run require separate PM authorization. Stop for PM review.

Subsequent PM authorization, 2026-11-19: "EDARATEK — SUCCESSOR BASELINE FINAL GITHUB CI" explicitly permits one reviewed additional commit and one push to `ci/edaratek-google-linux-gate`, followed by observing its isolated GitHub Actions execution. It does not permit a main push, PR, merge, tag, release, deployment, server operation or another fix-and-push cycle. The preparation results above remain a record of local validation; actual new run results must be recorded separately after execution, with no advance PASS claim. The prior manifest's approval and CI evidence continue to describe their original successor preparation, not an invented GitHub approval or signature.
