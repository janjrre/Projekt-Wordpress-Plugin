# PGP05 — independent second-agent security review, 2026-10-22

Tracking: #27 / Draft PR #32. Original target: `392053bb0e3f24ef79cc66a5142a6558b3fa5ee5`.
Reviewer: separate Codex review agent pgp05_independent_review, explicitly assigned
by the operator, independent of the implementation author. This is an independent
AI second assessment, **not a human/external audit**. Publication by the author
does not turn this into a GitHub human approval.

## Decision
**CHANGES_REQUIRED** for the original live metadata candidate; execution also
**BLOCKED** on actual provider evidence. Criterion #6 is not accepted.
`production_capture_allowed=false`. CP5 remains **7/10**, gates #6/#8/#10 open.

## Reviewed evidence
Reviewed config, probe, imported hostkey parser, two corresponding test files,
operator guide, earlier R1–R7 packet, independent review request and AGENTS.md.
Reviewer verified the original exact-revision local Git blob hashes:
- config fc80687b3b63da14935980d834751b8f03f09b6b
- probe ca222f91dcda2ddd978903efa7c100aa93b57414
- parser 92b30edf3a33730b35c9ea5a1255e41351d232ad
- probe tests 26241a462601a8cc1ffdcb36aff0b240b4cac1c5
- parser tests 509e592461e59605fb8dc27f4a5376dd60ad4cff

Reviewer ran the original full synthetic suite: **116 tests OK**. A separate
synthetic reproduction supplied exact REMOTE_OK and stderr reporting a failed
startup safety check; original code incorrectly returned authenticated_ssh=true.
No network or real credentials were used. Reviewer independently fetched original
exact-SHA statuses: offline #41, approval and hostkey #43 success; no metadata job.

## Findings
### P2 — unexpected SSH stderr accepted (original probe lines 229–231)
The runner's stderr was discarded; only stdout was checked. Original positive
test at line 116 deliberately included nonempty stderr. This conflicts with the
fail-closed unexpected-output contract. Require empty bounded SSH stderr or a
precisely reviewed benign allowlist, never log raw diagnostics, and test refusal
with valid stdout plus invalid stderr. Keyscan stderr is a separate bounded,
unauthenticated phase.

Author remediation published at **4d7b257ae398d663cf35668777e7b5bbea95b17c**:
exact stdout AND empty stderr; no allowlist. Tests now cover warning, whitespace,
and binary/secret-like stderr plus cleanup. Author full suite: **117/117 OK**.
Independent rereview disposition is appended below.

### P2 — mutable credential-bearing executor (config lines 49–50)
`cimg/python:3.12` is floating. Exact source SHA does not identify an image that
will later receive a broad account password. Root-owned executable checks
(probe original lines 94–99) establish neither image nor binary provenance.
This is a missing prerequisite, **not evidence of an image compromise**.

Before metadata credentials: obtain a legitimate immutable image digest, verify
its source/runtime assumptions, pin the reviewed config to that digest, review
the new full source SHA, and obtain a **contextless** provider result on that SHA.
Do not invent a digest or treat hostkey success on a floating image as evidence
for a future password job. **Unresolved** by the stderr patch.

### Documentation consistency
Old packet prohibited passwords in any environment; later candidate intentionally
uses a private context and restricted child environment for SSH_ASKPASS. Explicit
supersession/rationale and same-UID/root/executor trust boundary are necessary.
Author patch adds this clarification; it does not claim absence of passwords
from process memory/environment.

## Provider/operator prerequisites, not solved by client code
- Review applicable provider/server SSH/login-shell startup and relevant account
  hooks **before** setting PGP05_STARTUP_REVIEWED=yes. Server may execute these
  before the supplied clean command; client code and green tests cannot prove
  no PHP/WordPress side effects from them.
- Compare current provider domain-to-document-root configuration, accepted
  runtime root and authenticated namespace. WordPress-looking files and a
  provider-wide key do not prove active vHost binding.
- Account remains broad/write-capable. No write/delete permission probes.
  Client allowlist is not a server read-only ACL or backup authorization.
- Exact metadata context restrictions must reject wrong config/repo/project/
  branch/SHA before credential injection; runtime checks cannot replace them.
  Review-reference syntax is an operator attestation, not verified approval.
- Path checks are non-atomic and can race. Scope remains bounded metadata-shape
  observation, not a snapshot or complete filesystem security proof.

## Prior R1–R7 disposition
| Item | Disposition |
| --- | --- |
| R1 synthetic bypass | Addressed by distinct opt-in live entry point; old contracts unchanged. |
| R2 unbounded capture | Active transport bounds both streams/time and cleans process groups; interpretation needed stderr fix. |
| R3 PATH/environment | Child execution substantially addressed; immutable runtime still unresolved. Older standalone network helper is not a hardened substitute. |
| R4 identity/vHost/atomicity | Account/root shape improved; vHost and snapshot remain unproven. |
| R5 broad account | Correctly disclosed, not technically removed; observational payload only. |
| R6 startup hooks | Blocked on actual provider/account evidence. |
| R7 old bootstrap audit | Not invoked; must remain excluded. |

## Actions not performed
Reviewer made no GitHub mutations, production connection, credential collection,
context change, PHP/WordPress/SQL execution, backup, deployment, merge or purchase.
Author published only source/docs remediation; no live runner requested.
Original hostkey context variables had already been removed. Its old SHA pin
was not changed and does not authorize the new source revision.

## Independent rereview addendum — exact source 4d7b257ae398d663cf35668777e7b5bbea95b17c
Separate reviewer fetched the published fixed probe/test blobs and confirmed
they match local files b8736525903f1c90cffdf61f87c16ab86a45de06 /
f892dfff5637ff53e3c765d9f8fc3e7e6a9e36b2. Independently reran **117/117 OK**.
Stderr finding **RESOLVED**; documentation discrepancy **RESOLVED** after reading
the published guide/packet. Remaining decision: **BLOCKED for live metadata**.
Immutable runtime and provider startup remain unresolved; vHost is separate
gate #8. Criterion #6 remains open; production_capture_allowed=false.

## Smallest next operator action — no credentials or new pipeline
Open the already completed contextless CircleCI #41:
https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/41
Expand **Spin-up environment** and provide the full cimg/python repository
reference and complete registry **manifest digest**. Hide account/project
identifiers or unrelated values; never share passwords, tokens or private keys.
This is candidate runtime evidence only, not live approval. A truncated digest,
image configuration ID or unrelated layer hash is insufficient.

Next assistant work: independently verify legitimate cimg registry/repository
provenance and correct manifest type; pin both jobs to repository@sha256;
review resulting exact code SHA; then the operator can run only its default-off
contextless tests and verify actual pulled image/tools. No password-context
setup before these and actual provider startup prerequisites are accepted.

## Independent immutable-runtime addendum — c952645000b2954c04f1c0446c333392048065e6
Reviewer independently fetched exact published config and verified blob
2085eb8e2296df0fa97908564de1faffe0931a1c. Both jobs pin the same linux/amd64
manifest. Independent HTTPS DockerHub/registry crosscheck reproduced raw-byte
SHA256 of index d0177be857b2c8492d9d655f082bd32b6d02cc4cb7497abb99f8f2d66064fc29
and manifest 793ca7ea5a2fc27fa5b379fd077bf571c456d103992c7b3098cfc885f46cfa08.
Only small index/manifest metadata fetched, no image layers; anonymous token
stayed in memory, never output. The uploaded historical log has no full digest;
the registry result is correctly treated as a new candidate.

Reviewer independently reran **117/117 OK**. New runtime step is local only,
no install/authentication/site execution. Default off stays contextless and
does not reach either SSH approval/probe. Stderr fix remains intact.

**APPROVED only for default-off contextless provider test**, subject to existing
free-credit prerequisite. Floating-image source finding remediated and registry
provenance independently checked; actual provider pull/runtime on this exact
source SHA remains unverified. No current GitHub statuses were present at author
final check. **Live metadata BLOCKED; criterion #6 open; CP5 7/10**.
Startup/hooks, domain/root and authenticated metadata evidence still missing.
No widening of old context restrictions. production_capture_allowed=false.


## Independent provider-runtime addendum — Build44, exact c952645000b2954c04f1c0446c333392048065e6

Separate reviewer independently inspected the six operator-supplied logs and
queried exact-SHA GitHub status: offline-security success,
https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/44 .
Step0 starts the exact pinned manifest from cache; this is not evidence of a
fresh registry download. Steps99/101 bind source/checkout, 102 isolationPASS,
103 trusted local runtimePASS, 104 **117/117 tests OK** including actual local
OpenSSH -G and fictitious askpass-helper tests. Versions: OpenSSH8.9p1
Ubuntu-3ubuntu0.17/OpenSSL3.0.2; system /usr/bin/python3=3.10.12 is the helper,
not a claim about pyenv application Python. No real IONOS credentials or login.

**Immutable-executor and actual-runtime subblock RESOLVED for this exactSHA.**
Full live approval remains **BLOCKED**: actual server/account startup chain
still needs provider confirmation plus private review of the relevant account
files and transitively sourced files. Listing alone is not absence proof.
Do not assume SFTP hook behavior; use provider filemanager pending clarification.
[Prepared provider question](PGP05_IONOS_STARTUP_EVIDENCE_REQUEST_20261022.md)
was not sent. Reviewer made no support/production/context mutations.

Criterion6 remains open; CP5 **7/10**, #6/#8/#10 open.
Domain/current namespace and actual accepted metadata run are separate.
production_capture_allowed=false.

## 2026-10-22 — verbindliche Neufassung PGP05-SSH-20261022.2

**Überholt die frühere pauschale Pflicht zur IONOS-Supportauskunft/Prüfung aller
globalen Startup-Hooks.** Diese Pflicht war für den begrenzten Metadatencheck
unverhältnismäßig. Die Revidierung ist keine Behauptung einer Hookinspektion.

Der separat beauftragte unabhängige Prüfagent erteilt **APPROVED** für genau
c952645000b2954c04f1c0446c333392048065e6, festen Remote-Skript-SHA256
7d8e383ca0e09738b11a31a20d79c88b38a540539763f0091f0c69f692882e99,
unter normalem Vertrauen in die IONOS-gemanagte SSH-/Serverinfrastruktur und
deren übliches Kontostartverhalten. Dieses Infrastrukturverhalten ist nicht
unabhängig beobachtet. Kein absolutes Null-Nebenwirkungsversprechen.

Scope: eine gepinnte Verbindung zum privat gebundenen bestehenden OrgaVela-Konto,
exakt festes stdin-Shellskript für Account/Root/Pfad/Typ/Owner/Mode/Dateinamen.
Kein angefordertes PHP, WordPress-Bootstrap, SQL, Inhaltslesen, Schreiben,
Backup oder Deployment. 117/117 aktuelle CircleCI44-Tests, stderr-Fix und
unabhängig geprüfte immutable Runtime bleiben gültige Nachweise.

Unverändert erforderlich: exakte Projekt/Repo/Branch/SHA-Contextrestriction
vor Secretinjektion, getrennte private Credentialablage, feste Hostpin-Prüfung,
Ausgabe-/Zeitlimits, exaktes stdout/leeres stderr, eigene manuelle Freigabe.
Der Providertrust überstimmt keine bekannte ungewöhnliche Konfiguration,
Customhooks mit Sitecode/Schreibwirkung, Warnungen oder abweichende Ausgabe:
bei konkretem Gegenbeleg stoppen und erneut prüfen. IONOS-Anfrage ist dann ein
gezieltes Diagnosemittel, keine normale pauschale Vorbedingung.

**PGP05_STARTUP_REVIEWED=yes** bezeichnet nach dieser dokumentierten Neufassung
ausschließlich die erfolgte unabhängige Prüfung/Annahme dieses Startup-Scope
und Infrastruktur-Vertrauensmodells. Es behauptet NICHT die tatsächliche
Inspektion oder Abwesenheit jedes Hooks. Der historische Feldname bleibt auf
der bereits getesteten SHA unverändert; keine stille Semantikänderung.
Receipt bleibt startup_hooks_independently_observed_by_probe=false,
server_enforced_readonly_account=false, domain_vhost_binding_proven=false,
production_capture_allowed=false.

**Checkpoint5 jetzt 8/10 (80%)**: Kriterium6 unabhängige Sicherheitsprüfung
im präzisen obigen Scope ERFÜLLT. #8 tatsächliche authentisierte Account-/
Namespace-/Domainbindung und #10 akzeptierter realer Metadatenlauf OFFEN.
Reviewabschluss ist weder durchgeführter Login noch Produktionsbackupfreigabe.
V1-Zähler unverändert62/71 historisch,M0–M7 0/8,P4 0/4,R0/18.


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


## 2026-10-23 — Metadatenjob47 verweigert; feste redigierte Diagnose freigegeben
Job47 auf c952645... endet REFUSEDexit78; GitHub bestätigt failure, Offline45/
Approvalsuccess. Ursache und erreichteAuthentisierungsstufe unbekannt.
KeinBlindretry/Pfad-/Permissionwechsel. Code **b31e6cc0684593cca63d493ba0f56dd562e11f53**
ergänzt nur feste lokaleStufen/Remote-reportedExitkategorien ohne Rohoutput.
KeinGuard/Command/Pin/Timeout/Cleanup gelockert, keinPHP/WP/SQL/Writes/Discovery.
**121/121 lokaleTests**, separate unabhängige Nachprüfung/exakteBlobprüfungPASS;
neueSHAProviderprüfung nochOFFEN. ScriptHash
1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
[Diagnose-/Wiederanlaufplan](../operations/PGP05_METADATA_REFUSAL_DIAGNOSTICS_20261023.md).
[Akzeptiertes unabhängiges Review](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6358998616).

NächsterBetreiberpunkt: nur exakteContextrevision+PGP05_APPROVED_SHA auf neueSHA,
PGP05_REMOTE_SHA256 auf neuenHash, PGP05_METADATA_REVIEW_REF auf neuesReview
aktualisieren. Host/User/Passwort/Root unverändert. NeueMetadata-Pipeline, erst
aktuellerOfflineerfolg und richtigeExpression/Graph vor erneutemApproval prüfen.
KeinAssistenten-Livejob/Contextmutation/Backup/Deploy/Merge/Kauf.
CP5 weiter8/10,#8/#10offen; productioncapturefalse. V1 unverändert62/71historisch,
M0–M7 0/8,P4 0/4,R0/18; PR32Draft,alpha.2zuletztbelegt.


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


## 2026-10-23 — tatsächlicher Metadatenlauf PASS; CP5 9/10
**Neue verbindlicheEvidenz:** Pipeline542,Source8c4b445ea5dced969f63b0f30e0e371342f64680,
Workflowb97d93e3-802e-466d-9124-89a73a553314. GitHub unabhängig bestätigt
Offline54/Approval/Metadata56 alle success:
https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/56 .
BetreiberScreenshotimage(20261023-074040).png lokal gelesen: PASS,
authenticated_ssh=true,expected_user_and_root_shape_matched=true,
hostkey_candidate_matches_provider=true. MaskierteSource/Hashwerte nicht
"zurückgelesen"; Sourcebindung überGitHubStatus+geprüfteImplementierung/Context.
GenauerErfolg benötigt erfolgreichenboundedSSH-Prozess,exaktesstdout/leeresstderr.
Providerhostpin jetzt im authentisiertenHandshake genutzt, nichtnurKeyscan.

**UnabhängigeResultatprüfung:** separatbeauftragterPrüfagent las Originalbild
undexakteGitHubstatuses selbst. Kriterium#10 ausdrücklichACCEPTED im begrenzten
MetadataScope,keineexterneHuman-Auditbehauptung.
[Ergebnis-/Reviewbeleg](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6360036292).
**Checkpoint5 jetzt9/10 (90%), genau#8 offen.** DessenAccount-/Namespace-Teil
ist bestanden; nuraktuelleDomain->Installation/DocumentRootRelation muss durch
expliziteProviderUI-Kette komplettiert werden. BisherigeWordPressTarget-/Pfad-
undWP_HOME/SITEURL-Indizien allein ersetzen dievollständigeKette nicht.

**Einzige verbleibende#8Beobachtung:** IONOS orgavela.com aktuelleVerwendung/Ziel
ansehen; beiProjekt/Appziel dessenverknüpfteInstallationbiszumWebspacefolder
verfolgen undprivatmitbereitsgeprüftemRoot vergleichen. ZusammenhängendeUI-Kette
reicht,keineEinbild-LiteralRootpflicht/Providerbrief/neuerSSH-/PHP-/WP-Lauf.
Nichtsverbinden/trennen/speichern/DomainoderDateienändern. Nurrelevante
redigierteAnsichtteilen; keineCredentials/Kundendaten. OffizielleUI-Hilfe:
https://www.ionos.de/hilfe/domains/mit-webspace-verbinden/domain-mit-einem-webspace-verzeichnis-verbinden/
und https://www.ionos.de/hilfe/wordpress/administration-von-wordpress/verbinden-einer-domain-mit-wordpress/ .
DomainvhostFeldimProbeReceipt bleibtfalse; externeEvidenzkann#8erfüllen,ohne
historischesReceipt umzudeuten. NachAbschluss temporärenMetadata-Credentialpfad
bereinigen; nochkeineBereinigungbehauptet.

**Grenzen:** database_connected/php_requested/wordpress_bootstrap_requested,
server_enforced_readonly_account,startup_hooks_independently_observed_by_probe,
production_capture_allowed,isolated_restore_proven bleibenfalse.
FrühereFehlerursache nichtbewiesen; keinweitererRerun nötig. KeinBackup,
DB-Dump,Restore,Deploy,Merge,ScheduleroderKauf. Dreamloudunverändert.
V1offen62/71historisch,M0–M7 0/8,P4 0/4,R0/18,PR32Draft,
alpha.2zuletztbelegt. NachCP5: tatsächlicheCapture-/Writer-/Restoreübernahme
mitgetrenntenGatesweiterbearbeiten; keinProductionCollectorportvorgegeben.
