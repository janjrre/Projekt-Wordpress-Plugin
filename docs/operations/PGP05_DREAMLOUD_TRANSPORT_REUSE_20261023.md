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


## 2026-10-23 — tatsächliche Dreamloud-Transportübernahme statt weiterer Sonderdiagnose
Der Betreiber verlangt erneut die vorhandene funktionierendeDreamloud-Lösung.
Die frühere reineÜbernahmeplanung wird nicht als fertigePortierung gewertet.
AktuelleVorlage backup/production-circleci07d4ffbcc3c6d70f0716fda070050d6532a69df6;
DreamloudIssue434 weist jetzt8/8Abnahme und Tagescapture1139/writer1141 aus.
DortigerSSH-Transport wird benötigt, um QuellDateien/DB aufIONOSzu sichern.
EigeneOrgaVelaStartup-/Wrapper-/Diagnosekonstruktion ist keineallgemeinePflicht.

**Umgesetzt auf Source 8c4b445ea5dced969f63b0f30e0e371342f64680:** initiale festeMetadata-Anweisung direkt als
SSH-command wieDreamloudsprobe_circleci_password_source.py; kein extraabsolutes
env-/bin/sh-Wrapper, kein stdinInterpreter. Validierte/gequoteteParameter vor
identischemMetadataBody. TatsächlicheSammlung/Guards/SSHoptionen unverändert.
PrivateSSHargv/Root/User undRohoutput nichtloggen. ManagedPOSIX-Shell/Environment
explizitvertrauenswürdigangenommen; inkompatibleShell verweigert ohneFallback.
Dies ist keineBeobachtungallerStartuphooks und keinBeweis fürhistorischeFehlerursache.
BodyHash1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58
bleibt; gesamteBuilder/Transportidentität ist durch neueSourceSHA gebunden.

**127/127lokaleTests PASS, separatwiederholt; exaktepublizierteBlobs geprüft,
APPROVED.** [Review](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359766814),
[Implementierung/Abgrenzung](PGP05_DREAMLOUD_TRANSPORT_REUSE_20261023.md).
NeueSourcehatnochkeineProviderCI und keinenLiveerfolg. Die historischgrüne
Offline51prüfung gilt fürf5e35ea, nichtfürneueSHA. KeineautomatischePipeline.

**WeitererÜbernahmepfad:** vorhandeneCapture/PublicKeyVerschlüsselung/separater
CiphertextWriter/ImmutableReadbacks/isolierterWPMariaDBRestore ausDreamloud
adaptieren; bestehendeOrgaVelaPGP04-Nachweise weiterverwenden. EigeneQuellidentität,
Tabellenerwartungen,PGPonyEmpfänger und orgavela-backups bleiben getrennt.
VollständigerProduktionscollector ist nochNICHTportiert/ausgeführt. PHP/DBlocks/
Wartung erst in späterer getrenntgegateterCapturephase, nicht im aktuellen
readonlyCheck. KeineneuenpauschalenStartup-Anforderungen/Blindrerruns.

NächsterkonkreterTest benötigtContextrevision+PGP05_APPROVED_SHA auf neueSHA
und PGP05_METADATA_REVIEW_REF aufobigesReview; RemoteBodyHash/privateWertegleich.
Dann neueexakteFeaturebranch-MetadataPipeline, aktuelleOfflineprüfung vorApproval.
CP5unverändert8/10,#8/#10offen,productioncapturefalse. V1offen62/71historisch,
M0–M7 0/8,P4 0/4,R0/18;PR32Draft,alpha.2zuletztbelegt. KeineDreamloudÄnderung,
keineSite-/Backup-/Deploy-/Merge-/Kostenaktion.
