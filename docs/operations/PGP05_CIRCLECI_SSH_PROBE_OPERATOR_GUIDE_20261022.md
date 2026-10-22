# PGP-05 CircleCI one-shot SSH probes — operator and reviewer guide

Date: 2026-10-22. Tracking #27 / Draft #32. W0/O01/O03.
**Implemented review candidate. No live run, independent signoff or gate acceptance.**

## What was created

Default `pgp05_ssh_probe=off` runs only the existing contextless offline suite.
Two explicit modes are available on the PR32 feature branch:
- `hostkey`: offline tests → manual approval → unauthenticated ED25519 key
  candidate scan. No username/password, login, remote command or site metadata.
- `metadata`: offline tests → a distinct manual approval → pinned SSH login
  and fixed shell metadata script. This mode is blocked operationally until
  independent code/startup-scope review, exact revision context restrictions
  and private target binding have been accepted.

No schedule, deployment, backup, database connection, PHP or WP-CLI command,
cache, artifact upload, workspace persistence or automatic retry was added.
No existing deployment/audit workflow, credential or account was changed.

## Implementation and evidence

- `scripts/ci/orgavela_pgp05_circleci_ssh_probe.py`
- `scripts/ci/test_orgavela_pgp05_circleci_ssh_probe.py`
- `.circleci/config.yml`

Local Python suite: **116 tests OK** (93 existing + 23 new test methods).
New tests cover wrong project/branch/SHA/cost/review flags, foreign credentials,
root/user injection, hostkey mismatch stopping before login, no-secret candidate
mode, exact SSH arguments and payload, private local temp modes and cleanup,
output floods on both streams, process timeout/nonzero, sanitized CLI refusal,
revision-bound output, local OpenSSH `-G` option parsing (no network), askpass
helper with fictitious input, and synthetic shell file-shape negative cases.
Remote shell syntax and parsed YAML safety structure pass.

**No provider config compilation or new CircleCI success is claimed.**
The local config check first used an overbroad text search that also matched
the comment "schedules"; it was corrected to inspect parsed YAML keys. The final
mapping/approval/context/default-off checks pass. The initial YAML token off
was quoted explicitly to keep it a string across YAML parsers.

The new transport consumes the existing strict ED25519 parser, but replaces its
unbounded subprocess capture with a bounded reader. Each stdout/stderr stream
is capped at 16 KiB while reading; keyscan has 9 s total and SSH has 30 s.
Process groups are killed/reaped on completion/failure. Missing trusted system
binaries or unsupported SSH/shell options fail closed; nothing is installed.

Provider pin:
`SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q`.
Official source freshly checked:
https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/

## Cost and trust prerequisites

Only use the already authorized **CircleCI Free** project, with sufficient
included credits and no paid purchase/auto-top-up. The code requires
`PGP05_ZERO_COST_CONFIRMED=yes` as an operator attestation. That value is **not**
a billing API verification or a technical spending cap. If zero additional
money cannot be assured, do not start a run.

Use the normal repository-stored config and checkout from the **same reviewed
commit in the same OrgaVela repository**. No split config source, alternate
config repository, config override, dynamic continuation, PR/fork config,
SSH rerun or deployment job. A runtime SHA check alone cannot protect secrets
from malicious code: **server-side context restriction must reject other SHAs
before injecting variables**. Do not put probe fields in project-wide variables.

CircleCI context project, security-group and expression restrictions combine
with AND. Use **both** the actual project restriction and the exact-revision
expression below. If expression values are unsupported/unset or a restriction
cannot be saved/verified, stop; never fall back to a branch-only rule.

Official references:
https://circleci.com/docs/guides/security/contexts/
https://support.circleci.com/en/articles/16968400-pipeline-values-you-can-use-in-context-expression-restrictions
https://circleci.com/docs/guides/orchestrate/pipeline-variables/
https://circleci.com/docs/guides/orchestrate/workflows/
https://man.openbsd.org/ssh.1

## Next operator step — hostkey-only context, NO login secret

Create a **new, separate** context `orgavela-pgp05-hostkey-only`.
Do not reuse the existing backup or deployment context.

1. Organization Settings → Contexts → Create Context.
2. Project restriction: **only Projekt-Wordpress-Plugin**. Review permitted
   members; a sole-owner All members setup is an owner assertion, not proof of
   future membership isolation.
3. Set and save this expression, replacing APPROVED_FULL_SHA with the complete
   final reviewed PR32 commit from the accompanying GitHub tracking comment:

```text
pipeline.git.repo_owner == "janjrre"
and pipeline.git.repo_name == "Projekt-Wordpress-Plugin"
and pipeline.git.branch == "feat/pgp05-readonly-ssh-metadata-check-20261021"
and pipeline.git.revision == "APPROVED_FULL_SHA"
and not job.ssh.enabled
and not (pipeline.config_source starts-with "api")
```

4. Only after the restrictions are verified, add **exactly three fields**:

| Name | Value entered privately in CircleCI |
| --- | --- |
| PGP05_SSH_HOST | Existing OrgaVela access hostname from IONOS, not the public website domain |
| PGP05_APPROVED_SHA | The same full reviewed commit |
| PGP05_ZERO_COST_CONFIRMED | yes, only after confirming the cost prerequisite |

**No username, SSH password, DB value, GitHub PAT, backup token, recovery key or
private SSH key belongs in this hostkey-only context.** Do not copy values into
chat, screenshots, GitHub, pipeline parameters, command lines or run logs.
Request screenshots only of restriction summaries with identifiers redacted.

This context has not been created or changed by the assistant. A context creates
new access; the operator must perform/review that secure setup before use.

## First provider run

After exact code/restriction review, start a pipeline from the same feature
branch with parameter **pgp05_ssh_probe = hostkey**. Leave all other modes off.
Confirm the pipeline's actual commit equals the approved SHA. The contextless
offline job must succeed on that SHA and the workflow must pause at
`approve-pgp05-hostkey`. Inspect that job graph before approving.

If there is a config error, wrong SHA, unexpected context/job or failed test,
stop without approving. An approval button is orchestration, not independent
code or cost review. Once approved, the hostkey job performs one bounded scan.
Expected output: `PGP05_CIRCLECI_SSH_PROBE=PASS` plus a sanitized JSON receipt:
`hostkey_candidate_matches_provider=true`, `authenticated_ssh=false`,
`production_capture_allowed=false`, source_revision matching the exact SHA.

A provider-wide fingerprint match is only a key candidate; it does **not**
complete gate #8 or identify the domain/vHost. On REFUSED do not retry, broaden
host patterns, switch accounts, disable checking or print raw diagnostics.
After the attempt, remove the temporary context fields or disable its
restriction. The config does not enforce global one-time use: retaining the
context would allow another manually approved run on the same SHA.

## Metadata context — do NOT configure yet

Only after an independent reviewer accepts the exact transport and remote script
and the applicable server shell/SSH startup behavior, use a different context:
`orgavela-pgp05-reviewed-metadata`. Apply the same project/member/revision
restrictions before any credentials, with a current reviewer-approved full SHA.

It additionally requires the following private fields:

| Name | Meaning |
| --- | --- |
| PGP05_SSH_USER | Existing OrgaVela account username |
| PGP05_SSH_PASSWORD | Existing password, entered directly in CircleCI's secret form |
| PGP05_WP_ROOT | Independently verified absolute OrgaVela root, entered privately |
| PGP05_REMOTE_SHA256 | Exact remote script hash below |
| PGP05_STARTUP_REVIEWED | yes only after actual startup-scope review |
| PGP05_METADATA_REVIEW_REF | GitHub #27/#32 review/comment URL recording accepted review scope |

Exact current remote script SHA256:
`7d8e383ca0e09738b11a31a20d79c88b38a540539763f0091f0c69f692882e99`.

Review fields are operator-configured attestations, **not** automated proof of
independence. No review reference has yet been accepted for live use.
Never add credentials merely to make a red check green.

The SSH client uses an ephemeral 0600 provider-verified known_hosts file,
StrictHostKeyChecking=yes, only ED25519, no forwarding/proxy/user config,
no agent/public-key/GSSAPI fallback, one password prompt and no interactive TTY.
A small local 0700 askpass helper contains **no password literal**; OpenSSH
consumes the context password through its private pipe. The child environment
contains only explicitly constructed values. No secret appears in command args,
helper file, receipt or displayed SSH stderr. The whole private local temporary
directory is removed at normal/failure cleanup; provider/container failure uses
ephemeral executor lifecycle, not a claimed forensic secure erase.

The fixed remote shell script is supplied on stdin, never uploaded or installed.
It checks expected account, canonical absolute root with no symlink components,
root ownership, selected WordPress/UOP paths, regular single-link PHP files,
private wp-config mode and exact MU filename inventory. It uses only id, pwd,
test, stat, shell globbing and printf; it never reads PHP file contents. Extra
MU files, ownership mismatches or different permissions are REFUSED, not fixed.
Do not change production permissions to satisfy the probe.

**Limits:** SSH startup hooks execute before the submitted command; this client
cannot prevent or independently observe their side effects. Reads and provider
login auditing can change access bookkeeping. The account remains write-capable
at the server. Filesystem checks are not atomic, cannot establish a snapshot,
and do not independently prove active vHost routing or effective WP DB identity.
The receipt keeps domain_vhost_binding_proven=false, database_connected=false,
server_enforced_readonly_account=false and production_capture_allowed=false.
It says PHP/WP bootstrap were not *requested*, not that unreviewed server hooks
were impossible. Any unsupported environment or unexpected output is refused.

## Acceptance, rollback and current stop

The previous synthetic-only entry points and retired exit-78 collector remain
unchanged. This new entry point is a review candidate with explicit live flags,
not a workaround that imports a synthetic scanner in production scope.

Independent reviewer must resolve the previous R1–R7 review packet against this
diff, especially context injection before checkout, startup hooks, non-atomic
namespace checks, redaction and the broad account. No independent reviewer or
approval was fabricated. Metadata mode must not run before that signoff.

Rollback: revert this isolated PR32 commit or leave pgp05_ssh_probe=off and remove
the two new contexts. No production rollback is needed because no site changes
were made. No merge required to review or run the explicitly scoped feature-
branch job after the security prerequisites are met.

**Checkpoint 5 remains 7/10 (70%)**: #6 independent live review, #8 authenticated
SSH/namespace/domain binding and #10 actual accepted metadata run remain open.
The hostkey-only setup is the next concrete operator action, with no SSH
password needed. V1 counts remain 62/71 historical, M0–M7 0/8, P4 0/4, R 0/18.
