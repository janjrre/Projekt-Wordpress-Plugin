# Aktuell: diagnostischer Metadatenkandidat — 23.10.2026


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

**Nur neue freigegebene SHA/ScriptHash/Reviewreferenz aus obigem Plan benutzen. Historische Werte darunter sind insoweit überholt.**

# Aktuell maßgebliche Scopekorrektur — 22.10.2026

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

[Neue Entscheidung und konkreter Contextschritt](PGP05_DREAMLOUD_REUSE_AND_BOUNDED_METADATA_DECISION_20261022.md).

## Historischer Erstentwurf (durch obige Neufassung teilweise überholt)

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


## Aktueller Wiederanlauf nach Job50 — 2026-10-23
Diese Anweisung ersetzt frühere neue-SHA-/Reviewref-Werte; historische Einträge
bleiben als Verlauf bestehen. Unabhängig freigegebener Code:
`f5e35ea36b192288920558b783fee2c46c854c18`.
Review: https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359237075

Im Context orgavela-pgp05-reviewed-metadata genau drei Stellen ändern:
1. Vollständige Expressionrevision wie unten.
2. PGP05_APPROVED_SHA = `f5e35ea36b192288920558b783fee2c46c854c18`.
3. PGP05_METADATA_REVIEW_REF = `https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359237075`.

```text
pipeline.git.repo_owner == "janjrre"
and pipeline.git.repo_name == "Projekt-Wordpress-Plugin"
and pipeline.git.branch == "feat/pgp05-readonly-ssh-metadata-check-20261021"
and pipeline.git.revision == "f5e35ea36b192288920558b783fee2c46c854c18"
and not job.ssh.enabled
and not (pipeline.config_source starts-with "api")
```

PGP05_REMOTE_SHA256 bleibt
`1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58`.
Host/User/Passwort/Root und übrige Restrictions unverändert. Keine privaten
Werte öffentlich zeigen; maskierte Variablennamen/Expression reichen für UI-Abgleich.
Danach NEUE Pipeline auf feat/pgp05-readonly-ssh-metadata-check-20261021
mit pgp05_ssh_probe=metadata. Richtige VollSHA und aktuellen erfolgreichen
kontextfreien Offlinejob prüfen, erst dann approve-pgp05-metadata freigeben.
Kein alter Job50-Rerun. Noch kein Providerbeleg auf neuerSHA.
Bei erneutem Abbruch nur festes Schema/stage/reason senden; kein rohes SSH-Log.
SSH_REPORTED_AUTH_REFUSED beweist kein falsches Passwort. Bei generischem
oder widersprüchlichem Ergebnis stoppen statt Zugang/Pfad/Guards zu ändern.
CP5weiter8/10,#8/#10offen; keineProduktivbackupfreigabe.


## Aktuell: Dreamloud-Transportmuster übernommen (23.10.2026)
Ersetzt frühereSource-/Reviewwerte fürden nächstenTest. Keinefingierte
BehebungdesPROCESS_EXIT; tatsächlicherNeutest stehtaus. Drei Stellen im
bestehendenContextorgavela-pgp05-reviewed-metadata ändern:
- Expressionrevision und PGP05_APPROVED_SHA: `8c4b445ea5dced969f63b0f30e0e371342f64680`.
- PGP05_METADATA_REVIEW_REF: `https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6359766814`.

GesamteExpression:
```text
pipeline.git.repo_owner == "janjrre"
and pipeline.git.repo_name == "Projekt-Wordpress-Plugin"
and pipeline.git.branch == "feat/pgp05-readonly-ssh-metadata-check-20261021"
and pipeline.git.revision == "8c4b445ea5dced969f63b0f30e0e371342f64680"
and not job.ssh.enabled
and not (pipeline.config_source starts-with "api")
```
RemoteBodyHashbleibt1906f7c716d32983f2f0e412449c929f4d92ccedd27cb8716c485e5483d12c58.
AlleprivatenWerteunverändert. Kein neuerContext/Passwortwechsel nötig.
Erst neueexakteBranchpipeline mitpgp05_ssh_probe=metadata starten; neuer
OfflinejobmussPASSsein und richtigeSHA/Workflowgraph vorApproval prüfen.
NichtJobs50/53wiederholen. NurfesteReceipt/Diagnoseöffentlich, keineSSHargv/
Rohlogs oderunmaskierteContextwerte. NoPHP/WP/SQL/Sitewrites/Backups unverändert.
Die gesendeteAnweisung läuft direktinprovidermanagedPOSIXshell/environment;
beiUnverträglichkeitstoppen,keinFallback. WeitereCapture-/Restoreübernahme
bleibt getrennteArbeit; kein vollständigerBackupportbehauptet.


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


## 2026-10-23 — PGP05 CHECKPOINT5 ABGESCHLOSSEN: 10/10 (100%)
**Kriterium#8jetztACCEPTED:** Betreiberbildimage(20261023-075106).png zeigt
IONOSWeb-ProjektOrgaVela,zugewieseneDomainorgavela.com unddenselbenprivat
verglichenenHostingvertragwiedieaktuelleDomainansicht074717. ZweitesBild
075109istnureinwinzigerAusschnitt,keinzusätzlicherNachweis. Separatebeauftragte
PrüfinstanzhatdasvolleOriginalbild selbstgelesenunddieKombinationsketteabgenommen:
aktuelleDomain->konkretesProjekt/Installation -> vorherakzeptierteprojektbezogene
Explorer-/WordPressSiteHealthRootbelege -> authentisierterMetadatenlauf56.
DasneueBildzeigtdenRootnichtselbst; keinefingierteEinbild-/ProviderAPIprüfung.
KeineVertragskennungen/privatenPfade/Rohbilderöffentlichübernommen.

[Abschluss-/Reviewbeleg](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27#issuecomment-6360194115).
PrüferistseparaterAgent,keinexternerMenschenaudit. Sourceweiterexakt
8c4b445ea5dced969f63b0f30e0e371342f64680,PR32Draft/unmerged;
GitHubbestätigtOffline54/Approval/Metadata56alle success.127lokale/separat
wiederholteTestsbelegt,keinneuerProviderEinzeltestcountgelesen.
Kriterium#10bereitsseparatalsausgeführterbegrenzterMetadataCheckakzeptiert.

**Alle10festenKriterienerfüllt,keinoffenesCheckpoint5-Kriterium:**
1. IONOSProjekt-/DomainUIbelege.
2. WordPressStruktur/ConfigmodeUIbelege.
3. 52/52InnoDBTabellen-Metadaten.
4. MUPluginDateinameninventar.
5. SynthetischerNoBootstrapScanner/Guards/Tests.
6. UnabhängigertechnischerScope-/SourceReview.
7. PR32Implementierung+aktuelleProviderOfflineCI(keinMerge).
8. Hostpin/authentisiertesKonto/Namespace+separateDomain/Projekt/RootKette.
9. PrivaterWP-/IONOSDBNamensvergleich(owner-attested).
10. BetreiberfreigegebenerrealerMetadatenlauf+redigiertergebnis+separateAbnahme.

**Evidenzgrenzen bleiben:** domain_vhost_binding_provenimProbeReceipt=false,
daProviderrelationexternbelegt; Receiptwirdnichtrückwirkendumgeschrieben.
server_enforced_readonly_account/startup_hooks_independently_observed_by_probe/
database_connected/production_capture_allowed/isolated_restore_provenbleibenfalse.
CP5AbschlussistwederVollbackupportnochBackup-/Deploy-/Merge-/Schedulerfreigabe.
KeineweitereSSH-/PHP-/WordPress-/SQL-/DateioperationindiesemAbschnitt.

**OperativerNachlauf(keinerfundenes11.Kriterium):** nurPGP05_SSH_PASSWORDaus
orgavela-pgp05-reviewed-metadataentfernen. KeinIONOSPasswortreset/Keyrotation,
keineÄnderungandererDeploy-/Backupcontexts. OhnePflichtfeldverweigertder
Metadatentool-SettingscheckvorNetzwerk; Contextrestriktionenstehenlassen.
Bereinigungistvorbereitet,nichtdurchgeführt/nichtbestätigt. Danachkeine
weitereMetadataPipeline. ÜbergangzurvorhandenenDreamloudCapture-/Verschlüsselungs-/
CiphertextWriter-/Readback-/RestoreübernahmefürOrgaVela,eigeneWerte/Gates.
Gesamtissue27bleibtoffen,keinProduktivbackup/Restore/Deploy/Merge/Kauf.

V1unverändertoffen:62/71historischeCodingpakete,M0–M7 0/8,P4 0/4,R0/18,
alpha.2zuletztbelegt. CP5AbnahmeerhöhtkeinenanderenAbnahmezähler.
