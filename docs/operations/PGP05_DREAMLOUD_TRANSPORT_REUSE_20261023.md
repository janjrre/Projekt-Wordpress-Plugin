# Dreamloud reuse: implemented SSH transport adaptation — 2026-10-23

Source reference: janjrre/dreamloud-portal, backup/production-circleci,
07d4ffbcc3c6d70f0716fda070050d6532a69df6,
scripts/probe_circleci_password_source.py (ssh_capture and REMOTE_COMMAND).
OrgaVela #27 / Draft PR32; W0/O01/O03. No changes to Dreamloud.

## Why SSH
Dreamloud also uses SSH to collect WordPress files and database on IONOS.
Reusing the backup does not eliminate server access. The previous extra
OrgaVela env/inner-shell/stdin wrapper and repeated diagnostic refinements
were a bespoke implementation, not prerequisites of the backup architecture.

## Actual adaptation in this change
OrgaVela now submits the fixed metadata body directly as the SSH command,
as Dreamloud does for its initial password-source probe. A fixed 'set --'
prefix supplies validated and shell-quoted own root/user; no caller-selected
command is exposed. The SSH operand separator is explicit. No script bytes
are sent on stdin; its source is empty. No remote /usr/bin/env or explicit
inner /bin/sh path is required. Same account/password/pin/strict output,
source/context isolation, one attempt, output/time bounds and cleanup.

The metadata body and its hash remain identical:
1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
This has not been proven to fix the historical PROCESS_EXIT failures.
We explicitly trust the managed provider POSIX login shell and environment;
unsupported shell behavior must refuse without fallback. No absolute guarantee
about startup hooks or server-enforced read-only access is asserted.
The private command/root/user arguments and raw streams must never be logged.
Only strict fixed success or existing sanitized failure categories are public.

127 local tests passed; new actual-command local fixtures verify success and
existing refusal checks without PHP execution/content reads, plus injection
rejection. Separate exact-source review and current provider offline success
are distinct gates, not inferred from this report.

## Reuse scope beyond this transport
Adopt existing Dreamloud capture->public-key encryption->separate ciphertext
writer->immutable3assets/fullreadback->isolatedWordPress+MariaDBrestore flow.
Keep OrgaVela's own source account/root, validated table scope, PGPony public
recipient and private backup repository. Never copy credentials or private keys.
OrgaVela's previously accepted synthetic encryption/transport tests are reused.
Full production collector/restore adaptation is still outstanding. Dreamloud's
remote PHP/DB locks/maintenance may be used only in the separately gated later
capture phase, not silently imported into this read-only checkpoint.

No automatic live rerun, production backup, deployment, merge or scheduler.
CP5 remains8/10; authenticated account/namespace/domain and actual accepted
metadata result are still missing. production_capture_allowed=false.
