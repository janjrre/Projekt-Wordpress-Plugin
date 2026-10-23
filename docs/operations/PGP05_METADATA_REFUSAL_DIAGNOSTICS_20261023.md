# PGP05 metadata refusal diagnostics — 2026-10-23
#27 / Draft32. No live retry performed by assistant.

## Observed failure
Operator screenshot pipeline539: offline45 and metadata approval succeed;
metadata job47 prints only PGP05_CIRCLECI_SSH_PROBE=REFUSED, exit78.
GitHub exact c952645000b2954c04f1c0446c333392048065e6 status independently
confirms metadata failure: https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/47 .
This generic refusal does **not** establish the failing phase, authentication,
server/root identity, correct path or absence of requested remote execution.
Do not rerun that unchanged candidate or alter site permissions/paths to pass.

## Narrow source change
Success guards, script commands/check order, pin/credential scope, retry count,
16KiB-per-stream/9s/30s limits and process/temp cleanup remain unchanged.
Failure now additionally emits one fixed-schema JSON with allowlisted
stage/reason and production_capture_allowed=false. Exception messages,
inputs/argv/env, raw stdout/stderr, concrete paths/users/hosts and arbitrary
return codes remain private. Public codes are validated again at output.

Stage identifies last local attempted boundary, never completed authentication:
CLI, SETTINGS, RUNTIME, KEYSCAN, HOSTKEY_PIN, SSH, RECEIPT or INTERNAL.
Reasons identify local validation class, timeout/stream limit/process status,
or **REMOTE_REPORTED_*** fixed categories. SSHexit255 remains PROCESS_EXIT;
it does not mean 'wrong password'. Unknown failures stay generic.

Remote checks use fixed exits81–90 (previously all78), adding no commands:
user, root component/canonical/owner, directory/file shape, hardlink,
fileowner, configmode and MUinventory. Recognized SSH exit is interpreted only
with empty stdout AND stderr. These remain remote-reported hints, not
independent proof of account state or authentication.

Remote script SHA256 is now:
`1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58`.
Old 7d8e383c... hash must not be used with this revised payload.
No path discovery, directory recursion, PHP/WP/SQL, content read, writes,
permission changes or server diagnostic file was introduced.

## Local validation actually performed
**121/121 synthetic tests OK**, plus /bin/sh -n fixedpayload PASS.
New tests: settings refusals stop before any runner; fictitious Python process
stands in for SSH to test all fixed remote codes,255/unknowncodes/contaminated
streams; attemptedstage vs login; exceptionmessage and mutated/nonstringcode
redaction. Existing success/strictstderr/timeout/flood/askpass and noPHP tests
remain. Tests use only private fixtures/fictitious credentials, no IONOS.
New exact source needs independent review; provider tests not yet claimed.

## Operator sequence after independent exact-SHA review
No old-job rerun. Keep account/password/root values unchanged.
1. Update existing metadata context expression to the newly reviewed full source
   SHA and PGP05_APPROVED_SHA to that same SHA; maintain all other restrictions.
2. Update only PGP05_REMOTE_SHA256 to the exact new hash above and
   PGP05_METADATA_REVIEW_REF to the actual recorded accepted new review.
   Never set an invented review or weaken guards.
3. New exact-featurebranch metadata pipeline: its contextless offline job
   must succeed on the newSHA before manual approval injects metadata context.
   Provider execution of the changed candidate is not inferred from older117tests.
4. Inspect expectedSHA/graph, then one approved bounded attempt.
   On refusal send only fixed diagnostic JSON, no raw private streams.
   If explicit ambiguous/noisy result remains, stop and investigate without
   changing source identity or guessing SSH namespace.

Normal managed IONOS trust model PGP05-SSH-20261022.2 remains; hookobservation,
server-enforcedreadonly, vhostproof and productioncapture remainfalse.
CP5 count remains8/10 for prior approved scope; revised candidate must receive
its own independent review before further use. #8/#10 still open.
