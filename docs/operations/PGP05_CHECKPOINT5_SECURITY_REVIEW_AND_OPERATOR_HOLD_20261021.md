# PGP-05 Checkpoint 5 — Security Review / Live Evidence Hold
Date: 2026-10-21. Project: OrgaVela / UOP Core. Scope: draft PR #32.

## Safety decision
**Offline synthetic-only implementation: PASS (development test).**
**Production/livesite readiness: BLOCKED** pending independent review and operator-evidenced source identity.
No file from this plan may authorize real backup collection, production DB access, a GitHub Release writer, restore, scheduler, or WordPress mutation.
No credential values, actual DB names, WordPress config contents, IONOS customer IDs or non-public filesystem paths may be logged, committed or sent to ChatGPT.

## Independently grounded reference evidence
- IONOS official published **Webhosting ED25519 host fingerprint** (read-only documentation):
  `SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q`.
  Source: https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/
  This agrees with the literal pin in the existing `scripts/ci/orgavela-readonly-audit.sh`.
  It proves **documented expected key**, NOT that the currently selected private IONOS endpoint presented that key during this checkpoint.
- Existing historical **GitHub Actions read-only 'probe'** run on **2026-10-12**:
  https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555
  GitHub API reports completed/success and successful audit job steps. Historical success != current read-only proof.
- Operator evidence 2026-10-21: Domain target `WordPress`, OrgaVela IONOS Webspace Explorer shows `/public` containing WordPress `wp-admin`, `wp-includes`, `wp-config.php` (UI permissions 600), UOP Core plugin tree; IONOS-linked phpMyAdmin shows 52 InnoDB tables and UOP tables. Strong site/project evidence but no independent effective DB configuration match.
- Operator evidence: exactly one visible MU-plugin `wp-content/mu-plugins/automation-by-installatron.php`. WP-CLI documentation confirms `--skip-plugins` **does not skip MU plugins**:
  https://developer.wordpress.org/cli/commands/plugin/list/

## Security review observations
1. Retired PR32 `scripts/ci/orgavela_pgp05_readonly_remote_collect.sh` is now an unconditional denial, exit 78. It does **not** run PHP, WP-CLI or SSH. This is a safety stop, **not a live identity probe**.
2. `orgavela_pgp05_static_identity_v2.py` is CLI-gated `--synthetic-only`. The scanner pins opened directories with `dir_fd`, `O_NOFOLLOW`, and when available `O_PATH`; checks selected WordPress-looking filenames and MU-file count; reads only a fixed synthetic marker. It has neither an authenticated SSH transport nor an independent domain/database identity proof.
3. `orgavela_pgp05_static_receipt_contract.py` rejects real/production flags, unknown fields, duplicate keys, Symlink/FIFO/Hardlink receipts and some change-in-read races. This contract is only a **synthetic receipt validator**.
4. The historical `scripts/ci/orgavela-readonly-audit.sh` on `main` is **not superseded by the new PR32 stub**. It still invokes WP-CLI in its `probe/schema/backup-preflight` operations and has alternate root fallback logic. **Do not use it as the PGP05 live validator** without redesign and separate approval.
5. Static file existence and mode checks are insufficient to prove ownership, active vHost mapping, current SSH chroot, an effective `DB_NAME` match, consistent snapshot, or restore success. `assess(..., synthetic=False)` is an internal review primitive, not an approved entry point. Linux path traversal is not a fully atomic snapshot; do not assert race freedom.
6. Existing 79/79 local tests and CircleCI Free build #33 successful on PR32 SHA `1a825c59046142a6c57a7a05dd2a3d7ac0a495d2`. Additional CircleCI Free build #34 independently tested stacked PGP05 synthetic PR28-31 branch. Green synthetic CI is not an independent production security assessment.
7. **Independent reviewer signoff: OPEN.** This author-side audit is not an external independent signoff.

## Required operator-only IONOS evidence (no mutations)
A. Within the existing separate OrgaVela IONOS contract navigate to Webspace Explorer > `public`. Click the path shown above the file list (the `Pfad:` control) to open **Pfaddetails / Absoluter Pfad**. The operator should view and record the actual absolute path privately; in screenshots for review obscure personal/contract identifiers and any unrelated hosted sites. Do not upload or execute PHP "path-info" scripts. Official IONOS UI method:
https://www.ionos.de/hilfe/hosting/php-fuer-web-projekt-verwenden/absoluten-pfad-document-root-ermitteln/
B. Check the existing SSH account under OrgaVela's own IONOS FTP/SSH settings, note privately its project binding and whether the account is limited to the intended contract. **No passwords or tokens in chat/repository.** Do not reuse a Dreamloud account.
C. At the **actual** SSH handshake, only after a scoped operator Go and a security-reviewed transport, compare the presented ED25519 public key fingerprint with the official pin above, then verify the SSH shell namespace resolves exactly to the independently operator-viewed OrgaVela root. Never trust newly discovered keys automatically.
D. Effective WordPress database relation: require a **private independent comparison** of the actual WordPress DB selector with the IONOS OrgaVela MariaDB schema. A match of 52 tables alone is insufficient. Avoid copying `wp-config.php` or using publicly reversible hashes of low-entropy DB identifiers. When dynamic PHP environment values cannot be resolved without executing bootstrap code, **REFUSE** and choose safer provider/owner-assisted verification. Do not include DB host/name in check receipts.
E. Once A-D and independent code review are completed, design/approve one **non-bootstrapping**, allowlisted, minimal-rights remote metadata run with strict credential isolation and encrypted transport. Operator-go must be explicit for its exact SSH host/account/path/commands. Public CI logs reveal only `PASS/REFUSED`; no raw stdout/stderr or credentials.
F. The first real metadata-only receipt can be accepted for Checkpoint 5 **only** after its exact source, operator approval, hostkey, root and DB mapping are verified and recorded privately; it still cannot approve real backup capture.

## Exact Checkpoint-5 acceptance accounting
Ten fixed gates, now **7/10** formal, with gate #9 accepted as owner-attested UI-to-UI comparison on 2026-10-22:
1. [x] IONOS project/domain screenshots
2. [x] /public WordPress structure and wp-config UI-mode evidence
3. [x] phpMyAdmin 52/52 InnoDB UOP table metadata
4. [x] MU plugin filename inventory
5. [x] synthetic non-bootstrapping scanner + tests
6. [ ] independent production security review and live-scope scanner design
7. [x] PR32 branch implementation + provider CircleCI Free green test (not merge)
8. [ ] current SSH host/account/namespace and real domain DocumentRoot independently bound
9. [x] OWNER-ATTESTED: effective WP Site Health DB name privately matched against IONOS OrgaVela phpMyAdmin schema; no name or identifier revealed
10. [ ] owner-approved real metadata-only run + sanitized evidence, final gate signoff

**Three formal gates remain** (#6, #8, #10); #9 is accepted as owner-attested, not cryptographically independent. Nothing above changes V1's historical 62/71 coding packages, M0-M7 0/8 accepted, P4 0/4 accepted, release R01-R18 0/18 green.

## After Checkpoint 5: overall PGP-05 remains unfinished
(1) coherent real WordPress files+MariaDB capture design and fail-closed source;
(2) separately operator-approved real source capture plus local OpenPGP ciphertext before offsite transit;
(3) independently authorized ciphertext-only immutable GitHub Release writer and digest readback;
(4) isolated, safely disconnected real WordPress+MariaDB restore and owner PGPony validation;
(5) approved scheduler, RPO/RTO, retention, alerts, key recovery and ongoing cost boundaries.

## Operator evidence update — 2026-10-22, 00:08 local (IONOS UI)

- The operator provided a screenshot from **IONOS > Websites & Shops > OrgaVela > Webspace Explorer > Pfaddetails** of the WordPress-containing `/public` folder. The UI now **explicitly displays the absolute path** and resolves to the previously allowlisted **`home-www-public` root class**. The literal provider-internal path is intentionally omitted from this **public** GitHub document; retain the screenshot in the operator-controlled evidence context. Screenshot also displays a last-modified timestamp, which is not proof of a recently changed WordPress configuration.
- **Operator UI absolute-path display: PASS as supporting evidence.** Step A in the operator evidence list above is now fulfilled. Earlier UI evidence showed WordPress core dirs, `wp-config.php` existence/mode, UOP Core and a visible MU plugin in this project tree.
- **Do not overclaim:** this screenshot proves the webspace explorer's own filesystem mapping, not an independent current **SSH session** view, the externally active domain's virtual-host DocumentRoot, `ABSPATH`, or the effective WordPress database selector. Formal gate **#8 stays OPEN** pending SSH hostkey handshake / SSH account permission boundary / shell-root and domain-DocumentRoot crosscheck.
- The next owner action is **read-only inspection** of `Websites & Shops > OrgaVela > Erweiterte Einstellungen > SFTP & SSH > Konten verwalten`. Show account-list / project-scope / allowed access-root information if present, **mask usernames, hostnames, customer/contract identifiers, account IDs and all authentication secrets**. Do not click create/edit/reset/delete and do not export a key or password.
- The remote WP-CLI collector on draft PR #32 remains **unconditionally disabled** and the scanner's public CLI remains **synthetic-only**. No real SSH/DB/file-content read authorized by this evidence.
- **Checkpoint-5 unchanged: 6/10 formally accepted; four open gates #6 independent live security review, #8 current host/SSH/domain binding, #9 private independent DB mapping, #10 explicitly authorized no-bootstrap live metadata test.** Supporting operator subcheck A is now complete but is not a separate gate.

## Operator evidence update — 2026-10-22, 00:21 local (existing IONOS account)

The operator provided a **read-only screenshot** of **IONOS > Websites & Shops > OrgaVela > Sichere FTP-Zugänge verwalten**:
- The account overview displays **1 of 200 in use**.
- Exactly **one visible SFTP + SSH** account, with project-relevant personal note `ChatGPT / GitHubActions`.
- Its **assigned directory is `/`**, i.e. the **whole webspace of the selected hosting contract** according to IONOS official guidance. The screenshot masked the username; no password, token or SSH key was shared. The account's listed root is **not** evidence for exclusive `/public` access or an actual shell chroot.
- IONOS official documentation (current-generation hosting): https://www.ionos.de/hilfe/hosting/ftp-zugaenge-einrichten-und-verwalten/sftp/ssh-konto-erstellen/ states the default `/` assignment permits the *entire webspace*, and that restricting a subdirectory is an SFTP-level limitation **not necessarily a PHP/SSH security boundary**. The IONOS contract appears separate from Dreamloud per operator project context; verify the actual technical account isolation before claiming it.
- Do **NOT** narrow, delete, rotate, reconfigure, clone or revoke this existing account without a separately agreed deployment compatibility plan: current GitHub Actions/other pipelines might rely on it. No new hosting contract or paid service is needed just for this review.

**Safety finding:** The existing SFTP+SSH account is **broader than the intended PGP05 read-only source**. Treat its credentials as write-capable across that contract webspace. Both the future source identity probe and eventual producer need a dedicated scope/isolation design. A file-path allowlist in an application is *defense-in-depth* but **not** an operating-system-enforced restriction against a compromised general-purpose shell account.

**Checkpoint-5 gate impact:** The owner/account-inventory substep of gate #8 is now **PASS (UI inventory)**. This does **not** complete #8: current SSH hostkey handshake, effective account-to-contract mapping, SSH current working directory/chroot and domain virtual-host binding still await independently verified evidence. #6 live security review, #9 effective WP DB identity, #10 explicit owner-approved no-bootstrap live metadata run remain OPEN. The fixed **checkpoint total remains 6/10**.

**Next actions:** 
1. Offline: design a pinned, non-bootstrapping **read-only** probe that never calls PHP/WP-CLI, never reads `wp-config.php`, emits only sanitized PASS/REFUSED and is not bundled with production secrets. Keep it in a Draft branch and synthetic test mode until separately approved.
2. Owner: in this *same* existing OrgaVela account, optionally open **account details** in IONOS in view-only mode and privately compare the displayed server/contract assignment with the OrgaVela project; do not share account name, host, password or any credentials in GitHub/chat.
3. For an actual connection, separately authorize the precise operator/runner, known host key, account, and command allowlist; do **not** use the pre-existing broader `orgavela-readonly-audit.sh` WP-CLI probe as PGP-05 evidence.

## Operator evidence update — 2026-10-22, SSH connection details (IONOS UI)

The owner viewed the **existing OrgaVela IONOS SFTP + SSH account detail page**:
- The IONOS interface visibly displays a concrete **server/host** value, standard **SSH port 22**, **SFTP + SSH** protocol and an account name; the password is **not provided** (only a password help/reset link is displayed).
- **Data minimization:** This public repository deliberately does **not** reproduce the server hostname, account identifier or visible username, nor does it store an unredacted screenshot. The owner should separately keep those details within the IONOS account interface. Hostname and username are not private keys, but they can assist credential-targeting and are unnecessary in this public audit.
- This is an **account-side connection-target inventory**, not a successful SSH session, hostname-to-contract attestation, server-key handshake, or root-vHost binding. In particular, an official IONOS ED25519 fingerprint listed in documentation is only an **expected value**; before remote work the **actually presented** key must be matched to that independently verified reference and strict host-key checking enabled.
- Earlier account-list evidence remains: its assigned SFTP directory is `/`, the entire webspace scope of the chosen hosting contract, and is not a read-only root boundary. Never modify its password, location or permissions as an incidental part of this checkpoint.
- **Gate #8 still OPEN**, now with more complete owner UI evidence. A future remote SSH test still needs separate explicit source-specific owner permission and reviewed non-bootstrapping implementation. No credentials in CircleCI/GitHub plaintext; no WP-CLI/PHP, no general directory scans.

### Low-risk operator-only confirmation for Gate #9 (no secrets transmitted)

The WordPress.org *Learn WordPress* tutorial **Introduction to phpMyAdmin** explicitly identifies the way to determine which actual database a WordPress instance uses: WordPress Dashboard > **Tools > Site Health > Info > Database > Database name**. Compare that value **privately on the owner's device** with the name shown by IONOS phpMyAdmin for the **same OrgaVela project**:
https://learn.wordpress.org/lesson/introduction-to-phpmyadmin/
https://wordpress.org/documentation/article/site-health-screen/

* Do **not** send either database name, host, DB user, DB prefix or the full Site Health screenshot into chat, tickets or this public repo. Never click `Copy site info to clipboard` to share the whole diagnostics.
* Owner may attest **MATCH / MISMATCH / UNAVAILABLE** without disclosing the name. A match is owner-attested evidence of the configured live WP DB versus IONOS UI; it does **not** independently prove credential integrity, SSH mapping, backup consistency or restoreability. Retain Gate #9 pending owner attestation and any needed additional verification.
* The Site Health **Info** screen itself is read-only; do not use Database repair, optimization, table changes, WP config reads, phpMyAdmin SQL exports or PHP code for this comparison.

**Exact Checkpoint-5 result remains 6/10**, four gates open (#6 independent security review, #8 authenticated host/SSH/vHost binding, #9 effective WP-DB match, #10 authorized safe live metadata probe). This is UI evidence only, not real PGP05 backup authorization.

## Owner-attested WordPress-to-IONOS database match and runtime metadata — 2026-10-22

**Gate #9 ACCEPTED at the owner-UI attestation standard**, with scope and provenance explicitly recorded:
- The owner expressly confirmed that the **database name as displayed under the logged-in production WordPress Site Health information** matches the **database name shown by IONOS phpMyAdmin under this OrgaVela contract**, by **comparing them privately on their own device**.
- **No database identifier** (name, user, host, prefix or credential) was supplied in the chat or committed to this public repo. Do not print or publicly hash a low-entropy DB name. This is a **human operator attestation across two interfaces**, not a database read by the assistant, not an independent technical connection proof and not a permit to export any rows or run SQL.
- The owner also supplied a reduced WordPress Site Health diagnostic showing **configured runtime WordPress path** in the previously evidenced `home-www-public` class, `WP_HOME` / `WP_SITEURL` on **orgavela.com**, WordPress **7.1.3**, MariaDB server **11.8.8**, PHP **8.3.36** and reported DB size **about 4.33 MB**. These align with earlier IONOS webspace/phpMyAdmin UI screenshots, giving independent **interface consistency**, not a current SSH/vHost mapping or comprehensive data integrity proof.
- Site Health reported **one Installatron MU-plugin** and UOP Core **0.1.0-alpha.2** active, consistent with prior plugin-structure evidence. Site Health also indicates writable PHP-owned WP directories. A **write-capable** web runtime and the contract-wide SSH account reinforce the prohibition on unreviewed live-script execution.
- The owner posted the full diagnostics in a conversation. **Do not copy/paste that full report to GitHub tickets, CI logs, public release notes, or third-party tools.** It contains software versions, internal folder layout and security-relevant environmental hints even when not passwords. Public evidence here is deliberately aggregated.

**Remaining Checkpoint 5 security holds unchanged:** #6 independent production-scope security review, #8 present-day pinned SSH handshake/identity-to-domain-root mapping, #10 separate explicit authorization plus bounded no-bootstrap read-only real source metadata run. Gate #9 cannot substitute for those.

**Updated Checkpoint 5 count: 7 / 10 (70%), exactly 3 unaccepted gates.** The first PGP-05 real backup capture, remote writer, isolated restore and scheduler are different later approvals. No WordPress setting changed and no production information was fetched programmatically for this note.


## 2026-10-22 — Current execution-context recheck and concrete review packet

See [Cloud SSH readiness and independent review packet](PGP05_CLOUD_SSH_READINESS_AND_REVIEW_PACKET_20261022.md) for the exact tested SHA, 93/93 reproduced tests, official reference revalidation, permitted staged transport design, review findings R1–R7 and reviewer decision template.

This runtime **has** SSH tools; DNS resolution fails, existing SSH configuration/agent/IONOS connection parameters are unavailable, and connected PCjan is offline. No actual endpoint keyscan, authenticated SSH, namespace/vHost metadata or database access occurred. Fresh official fingerprint equals the source constant, **not** a presented server key. Current user authorization permits free bounded read-only work without repeated blanket approval; security prerequisites remain. No current live-capable approved entry point or independent reviewer signoff exists. Bring the existing PCjan online for the next private transport-readiness step. **Checkpoint 5 remains 7/10; #6/#8/#10 open.** No site changes, backup, deploy, merge or new CI run requested.


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
