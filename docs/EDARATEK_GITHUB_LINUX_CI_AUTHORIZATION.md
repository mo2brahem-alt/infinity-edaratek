# Authorized feature-branch Linux CI execution

PM authority: the attached **Authorized GitHub Linux CI Execution** request in
the current conversation, 2026-11-19. This supersedes earlier local-only Git
restrictions solely for reviewed backend commits and one push to
`ci/edaratek-google-linux-gate` in public repository
`mo2brahem-alt/infinity-edaratek`.

Scope: existing Google Sign-In backend implementation, compatible security
dependency patches, the one-line Excel correction, isolated Linux validation
infrastructure and non-sensitive documentation. Flutter remains read-only.
No main push/merge, PR, tag/release, deployment, server database operation,
Firebase Console mutation or production action is authorized. No second push
for CI fixes is authorized without separate PM review.

`security-validation.yml` accepts only a push to the authorized branch or a
manual dispatch while on that branch. It uses `contents: read`, pinned checkout,
no stored checkout credentials and no service/deployment secrets. There is no
`workflow_run`, PR-target or SSH/deployment bridge. The other workflows remain
manual-only test review handoff and the historical tests on their separate
backend-readiness branch; neither is triggered by this feature push.

Two independent jobs preserve the approval boundary:

- `isolated_linux`: digest-pinned PHP/MySQL containers under Ubuntu 24.04,
  disposable data/cache and a private internal test network. Composer validation,
  fresh locked audit and platform checks precede original concurrency, Google,
  native-session, Excel, static-file and full active-suite execution. Structured
  results contain counts/assertions and safe case symbols, not raw credentials
  or request/SQL diagnostics. No unexecuted case becomes PASS.
- `frozen_phase8`: compares every authoritative immutable source fingerprint
  without proposal overrides. The six known differences remain a failing PM
  gate. It has no `continue-on-error` and no dependency that could prevent the
  independent functional job from running. An overall failed workflow is not
  relabeled PASS when this gate remains pending.

The authoritative frozen manifest is unchanged. Proposed hashes and reasons are
in `security-baseline-proposal.json` and the frozen-baseline review document;
approval remains pending. Candidate source integrity checks protect the exact
reviewed implementation without approving new frozen fingerprints.

Pre-push security evidence and preserved worktree snapshots are held privately
outside the repositories. No raw logs, screenshots, database files, environment
files, service accounts or private keys are part of the reviewed change set.
Actual run results will be reported separately after GitHub execution. These
instructions do not claim that Linux tests have already run.
