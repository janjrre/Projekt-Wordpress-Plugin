# PGP05 Checkpoint 5 — independent technical review request
Date: 2026-10-22. Tracking #27 / Draft #32.
Review target: **392053bb0e3f24ef79cc66a5142a6558b3fa5ee5**.
This document is a task handoff, **not a review or approval**.

## Operator instruction and completed evidence
Operator clarified: finish current Checkpoint 5, not a separate Checkpoint 7.
A new screenshot shows the temporary hostkey-only context has **zero environment
variables**, while project and revision/expression restrictions remain present.
Accept cleanup as a screenshot/UI observation; do not claim an API audit of
CircleCI or membership. No password/private key was ever needed for hostkey mode.

Exact-revision GitHub statuses:
- Offline #41 success: https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/41
- Hostkey candidate #43 success: https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/43
- Approval success: https://circleci.com/workflow-run/9e8eb44c-16a1-413b-9713-1c19b9383ac5

Owner screenshot receipt: mode=hostkey,
hostkey_candidate_matches_provider=true, authenticated_ssh=false,
production_capture_allowed=false. Receipt revision was masked by CircleCI;
GitHub status binds the provider jobs to the full source revision separately.
No authenticated SSH or metadata run has occurred.

## Files to review at the exact target revision
- .circleci/config.yml
- scripts/ci/orgavela_pgp05_circleci_ssh_probe.py
- scripts/ci/orgavela_pgp05_hostkey_candidate.py (parser dependency only)
- scripts/ci/test_orgavela_pgp05_circleci_ssh_probe.py
- scripts/ci/test_orgavela_pgp05_hostkey_candidate.py
- docs/operations/PGP05_CIRCLECI_SSH_PROBE_OPERATOR_GUIDE_20261022.md
- docs/operations/PGP05_CLOUD_SSH_READINESS_AND_REVIEW_PACKET_20261022.md

Verified local Git blob hashes (same as published candidate):
- config: fc80687b3b63da14935980d834751b8f03f09b6b
- new probe: ca222f91dcda2ddd978903efa7c100aa93b57414
- hostkey parser: 92b30edf3a33730b35c9ea5a1255e41351d232ad

Prior local suite: 116/116 OK; not rerun for this documentation-only task.
Provider job success does not substitute for independent code review.

## Reviewer remit
Read-only review first. No production connection, credential collection,
provider-context changes, CI run, merge, deploy, backup or restore.
Any synthetic local checks must use fictitious credentials and private fixtures.

Report APPROVED / CHANGES_REQUIRED / BLOCKED for the precise metadata scope.
Identify reviewer role and independence from the author. A second agent/model
assessment, if assigned, must be labeled as such, not a human or external audit.
A self-review or a fresh chat alone is not independent evidence. The reviewer
must actually assess code and boundaries, not simply endorse green tests.

Assess at least:
1. Context exposure before checkout: server-side exact repo/project/branch/SHA
   restrictions; no config/checkout source split; no API config or SSH reruns.
2. SSH candidate parsing vs actual server key exchange, credential destinations,
   password helper, one-attempt behavior, environment isolation and redaction.
3. Installed OpenSSH/runtime assumptions; floating cimg/python:3.12 tag and
   required executor image/binary evidence before credentials.
4. Bounded process streams/time, process-group termination, temporary filesystem
   handling and realistic cleanup limits on executor failure.
5. Exact remote command, validated root/user data, symlink/hardlink/type/owner/mode
   checks, path substitution/races and absence of site-content reads.
6. Login shell/SSH startup hooks run before the submitted shell script: distinguish
   what code review can establish from provider-side facts still missing.
   Never turn PGP05_STARTUP_REVIEWED=yes into fabricated provider evidence.
7. Active domain/vHost mapping is not proven by static WordPress filenames or
   common provider key. Specify the additional evidence required for gate #8.
8. Server-side account is broad/write-capable. Client allowlists cannot prove
   OS-enforced read-only privileges. No writes or test uploads to probe rights.
9. The review-reference field is an operator attestation, not an automated
   independent approval check. Review links need actual readable accepted scope.
10. No production backup permission; PHP/WordPress/SQL not requested by collector
    is distinct from unobserved provider/startup execution.

## Required review output
- exact source revision and reviewed files;
- reproducible findings with severity and affected functions/lines;
- what synthetic checks, if any, were actually run and results;
- R1–R7 disposition from the earlier packet;
- blockers that require code changes vs private provider/operator evidence;
- precise approved scope or reason for withholding approval;
- explicit statement: production_capture_allowed=false.

## Follow-on execution if and only if review is accepted
The operator must still configure the separate metadata context with
reviewed exact SHA, private host/user/password/root, remote-script hash,
real review reference and real startup-scope evidence. Secrets are entered
directly in CircleCI, never chat or repository. The assistant cannot infer
a password from the IONOS UI or extract CI deployment credentials.

Only then: exact-SHA provider tests, separate metadata approval and the fixed
bounded run; evaluate the sanitized receipt plus domain-root evidence.
A green metadata receipt alone leaves its vHost field false and is insufficient
for full checkpoint closure without the independent mapping evidence.

## Acceptance accounting
Checkpoint 5 remains **7/10 (70%)**. Gate #6 independent security review,
#8 authenticated account/namespace/domain binding and #10 accepted actual
metadata run are open. Cleanup completes an operational substep, not a new gate.
V1 counts unchanged: 62/71 historical, M0–M7 0/8, P4 0/4, R 0/18.
