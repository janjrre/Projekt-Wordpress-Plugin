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

## Independent exact-source review — 2026-10-23
**APPROVED b31e6cc0684593cca63d493ba0f56dd562e11f53** for same bounded scope/
normal managed IONOS trust. Separate reviewer fetched matching published blobs
probe f0634f79aba1ddd8611e1feccfbdc0e9a68c3a86,
tests d2046853f577f6a12b2a9ccac56bbafeedc265e0,
unchanged config2085eb8e2296df0fa97908564de1faffe0931a1c and read guide.
Independent **121/121 local tests PASS**. Remote script normalized old exits
matches prior exact commands/checks/order; SSH args unchanged.
Review reference:
https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6358998616
No provider test or authentication claim for new source.


## Follow-up: Job50 and narrower SSH-reported diagnostics — 2026-10-23
Job50 on b31e6cc0684593cca63d493ba0f56dd562e11f53 failed with fixed
stage SSH / reason PROCESS_EXIT. GitHub confirms offline48 and approval
success, metadata50 failure. Settings and keyscan/pin passed, but SSH
failure does not establish authentication, remote execution or its cause.
No account/password/root/permissions changed and no blind retry requested.

New parser recognizes bounded complete canonical stderr lines for known
exit classes and emits only static SSH_REPORTED_* labels (authentication
refusal, hostkey, DNS, connect, askpass, command unavailable). Empty stdout
required; conflicting/unknown signatures stay PROCESS_EXIT. AUTH_REFUSED
is not proof of a wrong password; exec-like messages are not login proof.
Raw output, matched strings and arbitrary return codes stay private.
Remote script/hash/SSHargs/config/guards remain unchanged. The script hash
above remains1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
125/125 local tests PASS, independently repeated by separate reviewer.
Actual final published-SHA confirmation and new provider offline test are
separate next gates. Previous reviewref/CI do not approve this new source.

Updated operator sequence supersedes prior repin steps for this refinement:
change only exact context expression revision, PGP05_APPROVED_SHA and
PGP05_METADATA_REVIEW_REF to the newly published/reviewed source/reference.
PGP05_REMOTE_SHA256 and private settings stay unchanged. Then one new exact
branch metadata workflow; require current contextless offline success before
manual approval. Do not rerun old50 or infer success from predecessor CI.
CP5 remains8/10, #8 and #10 open; production_capture_allowed=false.


## 2026-10-23 — Job50 eingegrenzt; SSH-Diagnostik unabhängig freigegeben
Job50 auf b31e6cc0684593cca63d493ba0f56dd562e11f53 meldet
SSH / PROCESS_EXIT; Offline48 und Approval unabhängig als success bestätigt.
Settings und Hostkeypin passiert, aber Authentisierung/Ursache unbekannt.
Kein Passwort-/Pfad-/Rechtewechsel auf Verdacht, kein alter Job-Rerun.

Neuer exakter Code **f5e35ea36b192288920558b783fee2c46c854c18**:
125/125 lokale synthetische Tests PASS, separat unabhängig wiederholt;
Prüfer hat publizierte Probe3fd7610d53978f722485f27c2a337beb88deac7d,
Testsbf4064608f5613d372d41b5dd1e2e1320f0ed8e8 und unveränderte
Config2085eb8e2296df0fa97908564de1faffe0931a1c nachgeprüft. **APPROVED**
im unveränderten PGP05-SSH-20261022.2 Scope, keine externe menschliche
Auditbehauptung. [Exakter Reviewbeleg](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359237075).
Nur vollständige kanonische bounded stderr-Zeilen werden festen
SSH_REPORTED_* Kategorien zugeordnet; leeres stdout/zulässige Exitklasse
nötig, mehrdeutig/unbekannt generic. Keine Rohtexte/Identifiers/Returncodes.
Die Kategorie beschreibt gemeldeten Text, keinen bewiesenen Fehlergrund.
Remote-Skript/SSHargs/Config/Guards unverändert, ScriptSHA256 weiter
1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
NeueSHA hat noch keine Provider-CI; VorgängerOffline48 ist kein solcher Beleg.

Nächste tatsächliche Betreiberaktion: im vorhandenen MetadataContext nur
Expressionrevision und PGP05_APPROVED_SHA auf f5e35ea36b192288920558b783fee2c46c854c18,
PGP05_METADATA_REVIEW_REF auf https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359237075 setzen.
ScriptHash und private Settings bleiben unverändert. Dann eine NEUE Pipeline
auf exakt dem Featurebranch mit pgp05_ssh_probe=metadata; aktueller
kontextfreier Offlinejob muss vor manueller Approval erfolgreich sein.
Keine Contextmutation/Liveausführung durch Assistent; keine Secrets gelesen.

**CP5 unverändert8/10 (80%)**, #8 authentisierte Account-/Namespace-/Domainbindung
und #10 akzeptierter echter Metadatenlauf offen. production_capture_allowed=false.
V1 offen:62/71 historische Codingpakete, M0–M7 0/8,P4 0/4,R0/18;
PR32 Draft/unmerged, alpha.2 zuletzt belegt. Kein Backup/Deploy/Merge/Kauf.
