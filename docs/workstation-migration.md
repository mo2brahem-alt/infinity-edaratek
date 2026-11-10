# Edaratek workstation migration checkpoint — 2026-11-10

This is a Git checkpoint and handoff, not approval to resume investigation,
execute a probe, deploy, change APP_DEBUG, merge/push main, or start Phase9.

```text
CURRENT_BRANCH=001-mobile-backend-readiness
PREVIOUS_PUSHED_HEAD=fbc63a4615f48ac0f832d91945d9652aeabef466
PHASE_8_BLOCKED_AWAITING_PM_REVIEW
T103_T111_ACCEPTED=YES
H01_STATUS=BLOCKED
H05_STATUS=BLOCKED
T112_STATUS=BLOCKED
PHASE_9_STARTED=NO
MOBILE_BACKEND_NOT_READY
```

## Restore the repository

Run Git on the new workstation, using its own authorized GitHub login:

```powershell
git clone --single-branch --branch 001-mobile-backend-readiness https://github.com/mo2brahem-alt/infinity-edaratek.git edaratek
Set-Location edaratek
git branch --show-current
git log -1 --oneline
git status --short
```

Open this checkout root in the editor/Codex. It is the Laravel root containing
artisan, app, routes, .agents, .specify and specs. The old
D:\edaratek\public_html and C:\Users\HP paths in historical reports are provenance,
not paths to create on the new workstation. Use relative repository paths.

The migration commit is the commit containing this guide and
[workstation-migration-manifest.json](workstation-migration-manifest.json).
Compare its hash with the handoff response/origin feature branch. The manifest
records reviewed file hashes and important tracked source paths. The guide and
manifest do not include their own hashes, avoiding a circular/self-commit claim.

## Resume context, without executing further work

Read these files in order:

1. This guide, then CLAUDE.md and PROJECT_DETAILS.md.
2. .specify/memory/constitution.md and specs/001-mobile-backend-readiness/tasks.md.
3. [Latest T112 reassessment](../specs/001-mobile-backend-readiness/evidence/phase-08/t112/reassessment.md).
4. [Phase8 report](../specs/001-mobile-backend-readiness/evidence/phase-08/EDARATEK_PHASE_8_EXECUTION_REPORT.md).
5. [Complete probe4 operator evidence](../specs/001-mobile-backend-readiness/evidence/phase-08/t112/probe-4-operator-evidence.json).

The tested feature source is fbc63a4. The last reported test-server revision is
12b9d5326d942acda55309aac05c6cee1bf928b0, before native API routing; its /api/v1/me404
is not a feature API contract regression. No deployment was performed to change it.

```text
PHP_HANDLER=CGI_WRAPPER
PHP_EXECUTABLE=/bin/php-cgi8.3
FPM_NOT_APPLICABLE_FOR_THIS_VHOST=YES
CGI_WRAPPER_PATH=/home/test/fcgi-bin/php8.3.fcgi
CGI_PHPRC=UNKNOWN_DYNAMIC_OR_SUPPRESSED
CGI_PHP_INI_SCAN_DIR=UNSET_IN_WRAPPER_DEFAULT_UNKNOWN
APP_DEBUG_CACHED_VALUE=true
RUNTIME_ERROR_DISCLOSURE_EVIDENCE=UNAVAILABLE
```

H05 still lacks effective CGI INI/runtime or equivalent non-disclosure proof.
No INI sources were resolved; empty CGI_CONFIGURATION_FINDINGS is not a safety
PASS. The existing APP_DEBUG=true finding remains. FPM discovery is finished for
this vhost. H01 independently lacks private-file authorization, legacy/static
link and cache-related proof. T112 stays unchecked. No fifth probe was created.
The four committed read-only scripts are historical deliverables, not permission
to execute them. Do not automatically invoke Spec Kit implement/converge or Phase9.

Previous accepted CI results remain historical: focused462/462, both full
runners724/725, known Excel failure only, runtime errors0, skipped0. This migration
does not rerun or reopen Phase8 validation or change these results.

## Spec Kit configuration

.agents/skills/ contains the project's10 Codex Spec Kit skills. .specify/ contains
its reviewed integration settings, manifests, templates, PowerShell scripts,
workflow definitions and constitution. They are preserved, not reinstalled or
upgraded. [docs/spec-kit.md](spec-kit.md) records the pinned original CLI revision
if the CLI is later needed. Workstation-local credentials and trust are not copied.

.specify/feature.json is intentionally ignored by Spec Kit as a per-checkout
pointer. Recreate it locally, using PowerShell7 from the repository root:

```powershell
'{"feature_directory":"specs/001-mobile-backend-readiness"}' | Set-Content -LiteralPath .specify/feature.json -Encoding utf8NoBOM
pwsh -NoProfile -File .specify/scripts/powershell/check-prerequisites.ps1 -PathsOnly -Json
```

This resolves paths only; it does not boot Laravel or advance the workflow.
No machine-local integrations/packages or trust/secret configuration is committed.

## Preserved history and exclusions

All current specs and Phase1–8 evidence, T112 probe1–4 outputs and exact checkpoint
snapshots are included. Historical manifests remain dated records, with later
updates mapped through checkpoint-preservation manifests; they are not new PASS
claims. .gitattributes preserves Spec Kit/evidence bytes rather than normalizing
CRLF files and invalidating their historical hashes.

The original approved Phase0 audit formerly existed only at
C:\Users\HP\.codex\artifacts\edaratek-phase0-20261107\EDARATEK_MOBILE_BACKEND_AUDIT.md.
Its existing, reviewed report is preserved byte-for-byte at
[docs/audits/EDARATEK_MOBILE_BACKEND_AUDIT.md](audits/EDARATEK_MOBILE_BACKEND_AUDIT.md).
This is an archived baseline; no new audit or Phase8 investigation was performed.

The old workstation's13 missing hosting/static symlinks under public/awstats-icon,
public/awstatsicons and public/icon/mime/ were intentionally not committed or
restored. A fresh clone uses their existing repository versions. The untracked
.well-known/acme-challenge/.htaccess is an environment-specific certificate
validation rule and is excluded. It is not application source.

No .env, credentials, certificate/private-key material, database dump, uploads,
runtime/cache files, vendor or node_modules are added by this checkpoint. Restore
operational access separately through approved secure mechanisms; do not import
old cached credentials/config or boot host Laravel as a migration check.

```text
PUSH_TO_MAIN=NO
MERGE_PERFORMED=NO
DEPLOYMENT_PERFORMED=NO
PRODUCTION_TOUCHED=NO
REAL_FILES_MIGRATED=0
REAL_WHATSAPP_SENDS=0
WHATSAPP_ENABLED=NO
WHATSAPP_IN_SCOPE=NO
WHATSAPP_SENDS_ALLOWED=NO
PHASE_9_STARTED=NO
```
