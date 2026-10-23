# PGP-05 Checkpoint 5 — execution readiness and independent review packet

Date: 2026-10-22 (Europe/Berlin). Tracking: #27, Draft PR #32.
Reviewed code: `80eda724f2339afe1804fff91c3c10c27d694852`.
Scope: W0 / O01 / O03. **Preparation and author-side review, not independent signoff.**

## Decision and acceptance accounting

**Checkpoint 5 remains 7/10 (70%).** Accepted #1–5, #7 and owner-attested #9.
Open: #6 independent review of a live-capable design, #8 current authenticated
SSH/account/namespace/domain-root binding, #10 actual bounded metadata run and
acceptance. The current operator request explicitly authorizes free read-only
SSH identity/metadata work; repeated permission requests are unnecessary.
Authorization is not evidence that execution or security acceptance occurred.

No authenticated SSH session, keyscan of the actual endpoint, remote file
metadata access, PHP/WordPress execution, SQL, backup, deployment, restore,
release, merge or CI rerun was performed in this work section.

## Actual local readiness results

| Check | Actual result | Interpretation |
| --- | --- | --- |
| SSH client, ssh-keyscan, Git, Python | Installed | Previous environment's missing-client finding does not apply here |
| Existing SSH config / known_hosts | Absent | No configured reusable connection |
| SSH agent | Not available | No agent-backed authentication available |
| Required IONOS SSH environment names | Absent; values not printed or inspected | No usable host/user/authentication/pin configuration supplied here |
| DNS for provider help domain and project domain | Resolution failed for both | This runtime cannot currently establish the planned hostname-based transport |
| Connected PCjan | Offline, provider reported last seen 132 hours ago | No available connected device for the private connection |
| Actual OrgaVela endpoint scan | NOT RUN | Actual endpoint not supplied in this execution context; no guessing or scanning other provider hosts |

No private keys, passwords, account identifiers or database selectors were requested,
extracted from CI, printed or copied. No network-policy workaround or alternate
paid runner was attempted. Do not infer the SSH hostname from public website DNS.

## Provider trust reference, freshly checked

Official IONOS Webhosting help:
https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/

ED25519 SHA256 reference, observed on 2026-10-22:
`SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q`.

This equals the constant in the reviewed candidate verifier. **Reference-match
only, not presented-server-key match.** Do not substitute RSA/ECDSA, accept a
changed key automatically or disable strict hostkey verification. ssh-keyscan
only supplies a candidate; a subsequent pinned SSH key exchange must prove
possession. Provider-wide matching keys identify the provider key, not a
particular hosting contract or domain.

## Reproduced offline evidence

Nine PGP05 source/test files were read through the GitHub connector at the
reviewed SHA. Local Git blob hashes matched all nine source blobs before the
final regression run. Initial local materialization added a trailing newline;
that local formatting difference was removed and all hashes matched before the
reported exact-source run. Repository source was not changed.

`python -m unittest discover -s scripts/ci -p 'test_orgavela_pgp05_*.py'`:
**93 tests, OK**. Negative tests intentionally print REFUSED/argparse messages.
`bash -n scripts/ci/orgavela_pgp05_readonly_remote_collect.sh`: PASS.
Running that retired stub: **REFUSED, exit 78**, as required.

GitHub combined status for the same reviewed SHA:
`ci/circleci: pgp05-offline-security = success`,
https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/40 .
No new provider run was requested. Offline tests are not live source evidence.

## Author-side review findings to resolve before a live collector

| ID | Observation | Required disposition |
| --- | --- | --- |
| R1 | Public scanner CLI and receipt contract deliberately accept synthetic scope only | Do not bypass the CLI by importing assess(..., synthetic=False), removing the marker, or inventing a production receipt; review a distinct live entry point first |
| R2 | Keyscan checks MAX_SCAN_BYTES after subprocess capture_output has accumulated stdout; stderr is also captured without a byte cap | Bound both streams during collection and enforce total wall-clock/resource limits before using this as a hardened live transport component |
| R3 | Candidate verifier resolves ssh-keyscan through inherited PATH and passes inherited process environment | Resolve/trust the local binary and sanitize execution environment; do not use secret-bearing CI contexts for this step |
| R4 | Scanner checks selected inode types/modes and re-resolves the root, but does not prove UID/contract ownership, active vHost routing or an atomic snapshot | Keep these claims false; define separate identity evidence and race/failure handling |
| R5 | Existing account has contract-wide / scope and potential write authority | Command restrictions in a client are not server-enforced read-only ACLs; document the boundary and do not promote it to a production backup credential |
| R6 | Noninteractive ssh remote commands can still invoke server shell/SSH startup hooks | Review applicable startup behavior; do not promise no PHP or site side effects merely because the submitted command contains no PHP |
| R7 | The older orgavela-readonly-audit.sh is distinct from the retired PGP05 stub | Do not run it or a deployment workflow as a shortcut; it can invoke WP-CLI/bootstrap |

These are readiness findings. No exploit or production misconfiguration was
demonstrated. The synthetic-only tools stay unchanged and gated.

## Concrete staged execution contract for review

### A — private target binding and key candidate

1. On an authorized online device, obtain the **existing OrgaVela** host/account
   from its IONOS UI; keep identifiers private. No credential extraction from
   GitHub Actions, no new account, password reset, key export or deployment.
2. Confirm the independently documented root/domain/DB attestations apply to
   that same contract. The accepted DB evidence remains owner-attested; no SQL
   or wp-config content is needed for this step.
3. Make one ED25519 candidate scan of that exact host on port 22, with bounded
   output and time. Compare the fingerprint with the freshly checked official
   reference. Stop on mismatch, DNS/network failure or unexpected output.
4. Only the verified public candidate may populate a private ephemeral
   known_hosts file, mode 0600. Never use accept-new, StrictHostKeyChecking=no,
   /dev/null as the user pin store, or an unverified keyscan pipe to trust.

### B — authenticated identity and domain mapping

Proposed Unix-client options (design, **not an executed command**):
`-F /dev/null -T -o StrictHostKeyChecking=yes
-o UserKnownHostsFile=<private-pinned-file> -o GlobalKnownHostsFile=/dev/null
-o HostKeyAlgorithms=ssh-ed25519 -o IdentitiesOnly=yes
-o ForwardAgent=no -o ClearAllForwardings=yes -o PermitLocalCommand=no
-o RequestTTY=no -o ConnectTimeout=8 -o ServerAliveInterval=5
-o ServerAliveCountMax=1 -o BatchMode=yes -o LogLevel=ERROR`.
The reviewing party must verify the installed client's exact supported options.
Batch mode assumes existing suitable noninteractive authentication; if that is
unavailable, stop and arrange operator-local secure authentication. No password
in arguments, environment, repository, chat, logs or artifacts.

Submit only a separately reviewed **fixed** metadata command/script, no
interactive shell or freely supplied command string. Pin and record its source
hash. Review server startup hooks first; successful login may create provider
audit records, so the promise is no intentional site/config/DB modifications,
not zero operating-system bookkeeping.

Private observations may compare effective account ID and namespace against the
owner's existing account evidence. Inspect only the independently pinned root
and selected named entries via metadata/no-follow operations. No recursion into
unrelated webspace, file content reads, wp-config reads, PHP, WP-CLI, HTTP request
to the site, DB connection, ACL changes or write test. Never use a write/delete
probe to establish whether an account is read-only.

**Domain binding is an additional evidence step**: matching WordPress-looking
files, pwd and a provider-wide key cannot prove the active vHost. Use
provider-side domain-to-document-root configuration evidence crosschecked
against existing owner runtime evidence and the authenticated namespace.
If the available provider UI/config does not establish that relation, mark
domain binding UNPROVEN and keep gate #8 open; do not execute WordPress to
manufacture proof.

### C — no-bootstrap live metadata collector and receipt

Before this phase, the reviewer must approve an actual, versioned live-capable
implementation and its synthetic adversarial tests; this document is not that
implementation. Current PR32 remains intentionally synthetic-only.

Allowlist: types/modes/ownership comparisons for the single expected root and
the named core/plugin/MU paths already reviewed. Exact observed identifiers stay
private. No new marker file on IONOS, uploads, temporary server scripts,
chmod/chown, cron, maintenance mode, content downloads or cleanup commands.
Directory metadata reads may affect provider access accounting; no site writes
are intentionally requested.

Receipt must distinguish:
- pinned key exchange: proven / refused / not run;
- authenticated account and namespace: proven / refused / not run;
- domain-root binding: independently evidenced / owner-attested / unproven;
- file-shape checks and no-content/no-bootstrap scope;
- independent review identity, reviewed SHA, UTC time and approved command hash;
- database evidence provenance: owner-attested UI comparison;
- production_capture_allowed=false and backup/restore permission unchanged.

Do not reuse the synthetic receipt schema for a real run or report unavailable
facts as true. Bound and sanitize remote stdout/stderr **before** any public
logging. Abort on unexpected type/symlink, root mismatch, unsupported primitive,
timeout, unreviewed startup behavior, extra output or missing provenance.

## Independent review handoff

Reviewer must not be represented as independent merely because the same author
started a fresh chat or reran tests. This section prepares review; it does not
assign a reviewer or claim approval.

Required review record:
- reviewer identity/role and independence basis;
- exact code SHA and full live entry point/transport diff;
- R1–R7 dispositions, threat boundary and residual risks;
- tests for hostile/symlinked roots, changed key, excessive output, inherited
  environment, unexpected startup behavior, invalid scope and redaction;
- command allowlist, credential transport and provider/domain evidence plan;
- decision APPROVED / CHANGES_REQUIRED, timestamp and approved scope.

**Current decision: NOT SUBMITTED / NOT APPROVED.**
No independent reviewer was contacted or review approval fabricated.

## Next actual operator dependency

Bring the existing connected **PCjan** online with its existing authorized
connection available. No new hosting service, cloud machine or paid SSH app is
required by this plan. On reconnection, first inspect transport readiness and
existing configuration without reading secret values; if authentication is
unavailable, operator-local secure setup will still be necessary.

Until then the actual key, namespace and vHost checks are **NOT RUN**.
After transport becomes available, complete independent design/code review
before the real metadata collector; ordinary free preparatory checks need no
renewed blanket authorization. Checkpoint 5 stays 7/10; V1 62/71 historical,
M0–M7 0/8, P4 0/4, R01–R18 0/18, PR32 Draft/unmerged.


## 2026-10-22 — supersession for the CircleCI password candidate

The earlier section B describes the superseded PCjan/batch-key design. Its
blanket ban on password environment variables and BatchMode=yes is **not** an
accurate description of the later, separately reviewed CircleCI candidate.
That candidate uses one password attempt with SSH_ASKPASS, an ephemeral helper
containing no password literal, and a minimal child environment that includes
the private context password. The helper sends the password to the SSH client
pipe; it is absent from arguments, helper contents, receipts and public logs.
The password necessarily exists in executor process memory/environment, which
is not a secure enclave. Same-UID/root or compromised image/code can read it.
Exact source restrictions, trusted immutable executor identity and isolated
private context are prerequisites; these are not proven by a root-owned binary
check. This is a design change for independent review, **not live approval**.

The later distinct live-capable entry point is
scripts/ci/orgavela_pgp05_circleci_ssh_probe.py. Original synthetic CLIs and the
retired exit-78 collector remain unchanged. PCjan is no longer a prerequisite
for that candidate. Actual hostkey-only success and context cleanup are recorded
on the separate documentation branch; no authenticated metadata run occurred.
SSH stderr must now be empty for a metadata receipt. Startup and vHost claims
remain unproven; production_capture_allowed=false.
