# UOP Core – verbindliche V1-Abnahmematrix und Prüfnachweise

**Version 1.0, 12.10.2026. Status: keine finale V1-Abnahme.**  
**Masterplan:** [V1_MASTER_PLAN.md](V1_MASTER_PLAN.md) · **Live-Fortschritt:** [V1_STATUS.md](V1_STATUS.md).  
Der jeweils verantwortliche Reviewer dokumentiert für jeden Test **Test-ID, exakte Plugin-/Git-SHA, Umgebung, synthetische Fixture-ID, Schritte/Eingaben, erwartetes und tatsächliches Ergebnis, CI-/Browser-Run-ID oder sanitisierten Screenshot, Defect-Issue, Datum und Freigabe**. Ein früherer grüner Lauf kann als Vorbeleg dienen, ist aber keine Abnahme einer neueren V1-Version.

## A. Verbindliche Abnahmestufen

| Gate | Ergebnis, das tatsächlich vorliegen muss | Wer bestätigt |
| --- | --- | --- |
| **A0 – Spezifikation** | Scope/ADRs, Rollen und V1-MUST-/OUT-Matrix unverändert oder genehmigte Change Requests | Produktverantwortlicher |
| **A1 – Code Review** | M0–M6 plus #7–#10 geprüft, in richtiger Reihenfolge in main, ausstehende PRs/Drafts beseitigt | Entwickler + unabhängiger Reviewer |
| **A2 – CI/Build** | alle Pflicht-Checks auf genau einem Git SHA grün, reproduzierbares Paket mit SHA-256/SBOM, kein Critical/High | Entwickler/Security |
| **A3 – Migration** | fresh-install, v1→v2→V1, Re-Install, DB-Schema, Interrupted-Run und Restore vollständig nachgewiesen | Entwicklung/Betrieb |
| **A4 – Fachliche E2E** | Adult, Guest, Guardian, Staff, Waitlist, Consent und Privacy vollständig mit echten REST/Browser-Strecken | Produkt/Fachteam |
| **A5 – Nichtfunktional** | Security, Last, WCAG, Mobile, Backups, Cron, Mail, Datenschutz und Monitoring bestanden | Security/Betreiber |
| **A6 – RC** | exakt signiertes/versioniertes ZIP getestet, kein offener Blocker; Changelog/Handbücher vollständig | Release-Verantwortliche |
| **A7 – V1 formale Abnahme** | unterschriebenes/erklärtes Abnahmeprotokoll und separate Veröffentlichungsentscheidung | Betreiber |

Jeder nachfolgende Fall besitzt zur Baseline den Status **OFFEN FÜR V1**. Historische erfolgreiche Teststrecken sind **verifizierte Vorarbeit** und dürfen nach erfolgreicher Aktualisierung für denselben Releasekandidaten angerechnet werden.

## B. Identität, Berechtigungen, Personen – S01 bis S09

| ID | Testablauf und Erwartung | Kanal/Prüftyp | Gate |
| --- | --- | --- | --- |
| **S01** | Zwei Konten besitzen identische Familien-E-Mail, aber getrennte Personen; weder Links noch Rechte/Exportdaten entstehen automatisch. | WP + REST + Portal + DB | A4/A5 |
| **S02** | Org A versucht per UUID/REST/Admin/CPT/Export auf Org B zuzugreifen; Antwort verborgen/403/404, kein Datenleck. | Multi-Org/Policy | A4/A5 |
| **S03** | Rolle „Viewer“ sieht ausschließlich freigegebene Felder, auch in CSV, Audit, Error Responses und Portal. | Field Projection | A4/A5 |
| **S04** | Delegation wird bei offener Browser-Session widerrufen: nächste REST-/UI-Aktion verweigert, kein Cache-Leak. | Portal/REST | A4/A5 |
| **S05** | Abgelaufene, inaktive oder falschem Event zugeordnete Delegation kann nicht gewährt/genutzt/replayed werden. | Policy/DB | A4 |
| **S06** | WordPress-Account-Entfernung entkoppelt Person, widerruft Zuordnungen, verliert nicht unnötig historische Registrierungsnachweise. | Account/Privacy | A3/A4 |
| **S07** | Staff kann sich nicht selbst Ceiling-Rechte verleihen; Reviewer=Antragsteller wird bei Guardian-Antrag verweigert. | Admin/REST | A4/A5 |
| **S08** | CRUD über alle Kanäle führt identische Org-/Objekt-/Feldberechtigungen aus; keine „Schattenberechtigung“. | Code Review + E2E | A1/A5 |
| **S09** | Abgelaufene Session/Nonce führt zu 401/403, private REST-Antworten enthalten keine Personendaten; Re-Login nötig. | Browser/HTTP | A4/A5 |

## C. Event/Form, Registration, Capacity – F01 bis F12

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **F01** | Staff erstellt Event mit korrekter org-/wp_post-Autorisierung, Start/Ende und Zeitzone inkl. DST-Grenze. | Admin/REST + DB | A4 |
| **F02** | Entwurfsformular versionieren und veröffentlichen; bereits publizierte Version/Snapshots bleiben unverändert. | Builder/DB | A4 |
| **F03** | Condition AST: Zyklus, nicht vorhandene Referenz, verbotene private Alterstatsache → serverseitig sicher abgelehnt. | Service/REST | A2/A4 |
| **F04** | Erwachsene Person veröffentlichtes Formular → Consent → REST → Annahme/Portal, genau ein Datensatz/Audit. | Chromium+REST | A4 |
| **F05** | Dasselbe Formular zweimal senden, stalen Client / Retry simulieren: idempotente oder explizit sichere Konfliktbehandlung, keine doppelte Kapazität. | E2E/Concurrency | A4/A5 |
| **F06** | Gast registriert → keine Platzvergabe vor E-Mailprüfung; private 24h Einmalmail, Einmallink nur über echte empfangene Mail, Wiederholung verweigert. | HTTPS + SMTP-Testpostfach | A4/A5 |
| **F07** | Volle Veranstaltung: zugelassener Gast auf FIFO-Warteliste; nach Admin-Storno entsteht genau ein 48h Angebot, private E-Mail, einmalige Annahme. | REST + DB + Mail + Browser | A4 |
| **F08** | 50+ parallele Registrierungen für letzten Platz: eine Bestätigung, keine Überbuchung/Double Offer; mehrere DB-/PHP-Konfigurationen. | Mehrprozess-Stress | A2/A5 |
| **F09** | Angebot abgelaufen/erneut angeklickt/fremder Token/falsche Organisation: denied, keine Platzvergabe und kein Bearer-Leak. | Security E2E | A4/A5 |
| **F10** | Event-Verschiebung/Abbruch auch bei 100+ Anmeldungen: Snapshot intakt, Claims/Offers atomar freigegeben, Audit konsistent. | Integration/Failure Injection | A3/A4 |
| **F11** | Abgemeldeter/abgelaufener/delegierter Actor versucht POST: endgültige Berechtigung serverseitig frisch geprüft. | REST Policy | A4/A5 |
| **F12** | Deaktivierter JS-Client, Mobile 320px, langsames Netzwerk: semantische Fehler-/Ladezustände und kein versehentliches Duplicate Submit. | Browser+Keyboard | A4/A5 |

## D. Minderjährige, Altersnachweise und Consent – P01 bis P12

| ID | Prüffall und Erwartung | Notwendiger Nachweis | Gate |
| --- | --- | --- | --- |
| **P01** | „Alter unbekannt“ erlaubt keine Eventregel, die einen verifizierten Altersstatus verlangt; weniger restriktive Policy funktioniert unabhängig weiter. | #7 Policy/REST/Browser | A4 |
| **P02** | Selbst angegebenes Alter ist **nicht** als verifiziert ausgewiesen oder für geprüfte Eligibility zugelassen. | UI/REST/Export | A4 |
| **P03** | Extern geprüfte Altersklasse ist Zweck, Org, Event und Schwelle zugeordnet, hat Status/Gültigkeit/Prüfer; keine unnötige Ausweiskopie. | #7 Audit + Datenschutz | A4/A5 |
| **P04** | Alter attestiert, danach Event privat, Attest abgelaufen oder widerrufen: alle positiven Eligibility-Auswertungen stoppen. | #7 cross-channel | A4 |
| **P05** | Guardian-Claim/Einladungscode/gleiche E-Mail ohne gesonderte Freigabe gewährt **null** Berechtigungen. | #9 Rechte-/Abuse-Test | A4/A5 |
| **P06** | Unabhängiger, dokumentiert berechtigter Reviewer akzeptiert eng befristeten Scope; Reviewer ist nicht Antragsteller. | #9 Admin-Browser + Audit | A4 |
| **P07** | Zwei Vertreter/Mehrfachvertretung, widersprüchliche Ansprüche, Ablauf und Widerruf: aktuelle Rechte konsistent und sofort neu abgeleitet. | #9 Integrationsmatrix | A4 |
| **P08** | Consent-Zweck mit ausschließlich Selbstentscheidung: separate Historie, Vertreter nicht automatisch erforderlich. | #8 ADR/Version/REST | A4 |
| **P09** | Consent-Zweck mit ausschließlich legitimer Vertretungsentscheidung: separate Actor-/Subject-Evidence, Zeitpunkt, Textversion. | #8 REST/DB/UI | A4 |
| **P10** | Consent-Zweck mit **zwei unabhängigen** erforderlichen Entscheidungen: erst nach beiden wird Status complete; ein Account/Session kann nicht beide vortäuschen. | #8 zwei Actor-Tests | A4/A5 |
| **P11** | Eigen- oder Vertreterentscheidung wird unabhängig widerrufen; aktueller Consent wird ungültig, historische Beweise bleiben unverändert. | #8 Revocation/Export | A4 |
| **P12** | Minderjährigen-Golden-Paths samt Alterskontrolle, Einladungen, Consent, Portal, Admin, Screenreader/mobile und zweckbezogener rechtlicher Dokumentation. | #10 vollständige Review-Signatur | A4/A5 |

**Rechtliche Grenze:** Technische Consent-Evidence, eine Staff-Checkbox oder ein Jugend-/Kind-Label ist keine amtliche Identitäts-, Alters- oder Sorgerechtsprüfung. Zweckbezogene zulässige Verfahren/Verantwortlichkeiten liegen in der Freigabe der Einsatzorganisation und müssen dokumentiert sein.

## E. Datenschutz, Kommunikation, Export, Arbeitersystem – D01 bis D10

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **D01** | Einwilligung exakt an immutable Dokument-/Formularversion mit getrennten Actor und Subject gebunden; keine zweite Wahrheit im Snapshot. | DB/REST/Audit | A4 |
| **D02** | Consent-Entzug stoppt nachfolgende Nutzung gemäß Policy, frühere Historie bleibt erforderlichenfalls als Nachweis. | Service/Privacy | A4 |
| **D03** | E-Mail-Vorlagen auf DE/EN, Variablen-escaping, niemals Bearer in Betreff/REST/Audit. | Template/Unit/E2E | A2/A4 |
| **D04** | Action Scheduler und Outbox nach Cron-Einrichtung: Queue tatsächlich abgearbeitet; ein fehlgeschlagener/ambiguous Versand nicht unkontrolliert doppelt gesendet. | IONOS Worker-/SMTP-Run | A5 |
| **D05** | WordPress-Privacy-Export/Eraser respektiert Person ≠ E-Mail, schützt fremde Familienkonten. | WP Datenschutz-Tools | A4/A5 |
| **D06** | Retention-Hold/Rechtspflicht/Lösch-Policy, Dry-Run und Cursor-Replay; kein irreversibler Datenverlust ohne Freigabe. | DB/Service/Betreiberreview | A5 |
| **D07** | Private Exportdateien nie weböffentlich; Scope-/Role-Recheck beim Erstellen, Verarbeiten und Download; 24h-Ablauf. | Pen/REST/Filepath | A5 |
| **D08** | CSV enthält nur berechtigte Felder, korrektes UTF-8; Formelpräfix-Schutz für =,+,-,@. | Export/Security | A5 |
| **D09** | Logs/Site Health/Audit zeigen minimale Diagnostik, keine Passwörter, private E-Mailkörper, Token oder andere PII. | Betrieb/Security | A5 |
| **D10** | Teilnehmer-, Export-, Mail- und Auth-Fehlpfade bieten verständliche Lade-/Fehler-/Recovery-UI und korrekte Retry/Queue-Status. | Browser/Operator | A4/A5 |

## F. Migration, Betrieb, Sicherheit, Performance – B01 bis B12

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **B01** | Frische WP-Installation inkl. MySQL/MariaDB, neuestes UOP-ZIP aktiviert; genau erwartete Tabellen/Indizes/Org, keine Fehler. **M1-08 zusätzlich:** `uop_schema` liest das aktuelle 29-Tabellen-Schema, Migration 2/2/complete sowie gespeicherten Default-Org-Verweis auf genau eine tatsächlich aktive Organisation; Negativtest: archivierte Organisation/falscher Verweis ergibt `critical` ohne automatische Reparatur. Site-Health-Befund und DB-Metadaten auf *installiertem* Release-SHA separat belegen. | Install/Schema Health + M1-08-Negativtests + unabhängige OrgaVela-Liveprüfung | A3 |
| **B02** | Bereits installierte Alpha mit schema 2 auf Kandidat upgraden, kompatibel und ohne Datenverlust, neuer Source/Hash exakt geprüft. | isolierter Upgrade-Klon | A3 |
| **B03** | Unterbrochene Migration, Lock-Takeover, Deadlock, defekter Index: fail-closed, protokolliert, Recovery erfolgreich. | Failure Injection | A3/A5 |
| **B04** | Datenbank-/Datei-Backup wird auf isolierter Umgebung real wiederhergestellt, übereinstimmende Datenversion und Funktion. | IONOS Restore-Protokoll | A3/A5 |
| **B05** | WP-Cron deaktiviert? Belegter externer Cron-Runner mit Fälligkeits-/Überfälligkeitsprobe und Alarm. | IONOS Cron/Scheduler | A5 |
| **B06** | Externe E-Mail-Zustellung in autorisiertes Testpostfach, SPF/DKIM-/Sender-/Bounce-Regeln; keine produktiven Testpersonen. | synthetischer Mailbetrieb | A5 |
| **B07** | GitHub-SFTP/SSH Least Privilege; kein Zugang zu Dreamloud/anderen Projekten; Secret-/Hostkey-/Checksum-Guards bestehen. | Scope-Negativtest/Review | A5 |
| **B08** | Site-URL HTTPS und erforderlicher Testzugriffsschutz/Noindex vorhanden, vor Freigabe eigener Publikationsentscheid. | Public Surface/Robots | A5 |
| **B09** | PHP 8.3/8.4/8.5 x MySQL 8.0/MariaDB 10.11; unterstützte WP-Versionen und Browser-Testmatrix vollständig aktuell grün. | CI-Matrix | A2/A5 |
| **B10** | Lastfixtures 100, 10.000, 100.000 Personen inkl. Zeit-/Querybudgets und aktiver Indizes; keine N+1-Ausreißer. | M7 Performance Report | A5 |
| **B11** | Manuelle WCAG 2.2 AA/Keyboard/Screenreader/320px für alle fünf Blocks, Admin, Builder, Portal, Consent-UI. | A11y-Protokoll mit Findings | A5 |
| **B12** | Sicherheits-Angreifermatrix BOLA/IDOR, CSRF, XSS, SQLi, CSV, Token, Replay, Privilege, Supply Chain; kein offenes Critical/High. | M7 Security Report | A5 |

## G. Release und Verantwortung – R01 bis R08

| ID | Ergebnis | Muss vorliegen |
| --- | --- | --- |
| **R01** | Feature Freeze | sämtliche V1-MUST-Funktionen, Versionen, Scope-/Deferred-Issues, ADRs |
| **R02** | Integrations-Freeze | keine gestapelten PRs offen, main grün, Review-Gates |
| **R03** | Betreiberbereitschaft | Backup/Restore, Cron, Mail, Datenschutz, Monitoring, SFTP |
| **R04** | Fachliche Akzeptanz | alle relevanten S/F/P/D-Fälle bestanden |
| **R05** | Nichtfunktionale Akzeptanz | B01–B12 bestanden, keine High/Critical |
| **R06** | RC-Paket | ZIP-SHA, Version, SBOM, Changelog, Upgrade/Uninstall |
| **R07** | Formale V1-Abnahme | Betreiberfreigabe mit Version, Commit, Run-Links, Restrisiken |
| **R08** | Separate Veröffentlichung | öffentliche Ausrollung explizit autorisiert, Monitoring/Support |

## H. Vorbelege vom 12.10.2026 – NICHT finales Release-Gate

- **OrgaVela SFTP Probe:** [38239184433](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38239184433) – erfolgreich, /public.
- **OrgaVela Paketupload:** [38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035) – erfolgreich, ursprüngliches Alpha-2-Paket. Aktivierung und WP-Migrationsoptionen 2/2 separat bestätigt, SQL-Health noch nicht.
- **Quelle des Alpha-Builds:** SHA 4f2c4df800caf1e5708f05cce9536a7afb34aaa0; historisch [Foundation 38225097092](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097092) und [HTTPS E2E 38225097051](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097051) grün.
- **Letzter PR-#11-HEAD:** 1b98658704e1b4e8fb5350d7817e20af4fdecc35; Push Foundation [38233559467](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233559467) 8/8, PR Foundation [38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475) 7/8, PR HTTPS [38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383) rot. Das ist aktuell **kein gültiges A2-Gate**.
- **Achtung:** E2E mit lokalen Mailpit-Mails beweist die Funktion der isolierten CI-Transportstrecke, **nicht** Zustellung über einen echten externen Mailprovider.
- **Aktivierung:** zwei UOP-Optionswerte 2/2 beweisen **keine** Prüfung jedes realen DB-Index. Noindex, Cron, Backup, Mail und zweckbezogener Minderjährigen-Nachweis bleiben offen.

## I. Ablage des finalen Abnahmeprotokolls

Erst beim tatsächlichen RC wird eine Datei **V1_RELEASE_SIGNOFF.md** angelegt, mit: freizugebender Version/Plugin-ZIP-SHA256/Source-SHA, Ziel-WP-/PHP-/DB-Matrix, Testfälle/Sicherheitsbefunde, manuelle Accessibility-/Datenschutzfreigaben, Protokoll eines getesteten DB-Restores, Ergebnis aller R01–R07-Pakete, verantwortliche bestätigende Person und Datum. Vorher nicht als „signiert“ simulieren.

**Stop-Bedingung:** Beim ersten Critical/High ohne akzeptable, dokumentierte und geprüfte Lösung kein Stable-/öffentlicher Go-Live, selbst wenn ein Workflow grün ist.


### O03 / B04 Belegabgleich (12.10.2026 – **KEINE B04-Abnahme**)

| Teilnachweis | Tatsächlich beobachtet | B04-Bewertung |
|---|---|---|
| Live-Backupvoraussetzungen auf OrgaVela | [SSH-/WP-CLI-Read-only-Audit #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599): `mysqldump`/`mariadb-dump`, MySQL-Client, `tar`, `gzip`, `sha256sum` vorhanden; DB unter 25 MiB, Dateiumfang 100–500 MiB, freier Webspace über 5 GiB | **Hilfsbeleg erfüllt**, noch keine Backup-Datei |
| Technische SQL-/Datei-Restore-Prozedur mit synthetischen Fixtures | [isolierter MariaDB-11.8-Test #38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371): dump+archive+Hashes, separate Test-DB und -Dateien, 2 synthetische Datensätze, Indexkontrolle **grün** | **Hilfsbeleg erfüllt**, **keine** produktive Sicherung benutzt |
| IONOS-OrgaVela-Backupzeiten und Wiederherstellungspunkte | Anbieterhilfe beschreibt bis 14 Tage bei regulärem Webhosting; kein Zugriff auf **konkrete** Konto-Backup-Liste | **Offen** |
| Echtes privates unabhängiges OrgaVela-SQL+Datei-Backup | Nicht erstellt, nicht auf sicheren Offsite-Speicher kopiert, Hash nicht verifiziert | **Offen** |
| Tatsächliche Wiederherstellung dieser OrgaVela-Sicherung im nichtöffentlichen Clone | **Nicht durchgeführt** | **Offen – blockiert B04/R03/Release** |

Bewusst **nicht** B04/R03 als grün zählen. [Operatives O03-Runbook](../operations/W0_O03_BACKUP_RECOVERY_RUNBOOK.md) benennt den erforderlichen echten Offsite-Backup- und isolierten Restore-Nachweis; [Masterplan v2.4](../operations/V1_ACCEPTANCE_PLAN.md) und [kanonischer Fortschritt](../operations/V1_PROGRESS.json) bleiben maßgeblich. Keine produktiven Migrationen/Upgrades vor Erfüllung.


### O03 / B04 Speichervariante seit Betreiberentscheid 13.10.2026 – **Private GitHub Releases statt Google Drive**

**Korrektur zu historischen Aussagen oben:** Die dortige allgemeine Forderung eines unabhängigen „Offsite“-Speichers wird nach neuer **expliziter** Betreiberentscheidung durch **separate private GitHub Release-Assets** als einziges selbstverwaltetes Backupziel neben IONOS ersetzt. Google Drive ist **nicht mehr** als O03-Ziel autorisiert. Diese Speicherwahl senkt die Zahl unabhängiger Anbieter; die korrelierte GitHub-Konto-/Ausfallproblematik wird im neuen [Runbook](../operations/W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md) offengelegt und von der Wiederherstellungsanforderung **nicht** befreit.

| Neuer B04-Einzelnachweis | Status 13.10.2026 |
|---|---|
| Separates `private` GitHub-Backup-Repository inkl. geprüfter Besitzer-/Schreibrechte | **Offen** – bevorzugtes `janjrre/orgavela-backups` wurde **noch nicht** angelegt/verifiziert; Connector besitzt keine Repo-Erstellungsaktion |
| Verschlüsselung vor GitHub-Upload, Betreiber besitzt privaten Recovery-Key offline | **Offen** – kein privater Key in GitHub/CircleCI erlaubt |
| Authentifizierter CircleCI-Free-GitHub-Release-Push ausschließlich mit auf Backuprepo begrenztem Token | **Offen** – Context/Security/Token erst Betreiber-Setup und Review |
| Synthetischer verschlüsselter GitHub Release Canary inklusive vollständigem Download/Hash/Entschlüsselung | **Offen** – bisher nur der secret-freie CircleCI-Offline-Runner [#2](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) grün |
| Tatsächlicher vollständiger OrgaVela-SQL+WP-Datei-Snapshot in verschlüsselten privaten Release-Assets und vollständiges Remote-Readback | **Offen** |
| Isolierter Restore **dieses echten Releases** in sicherem WP/MariaDB-Klon, E-Mail/Jobs/Egress deaktiviert, 29 Tabellen/Version 2/2/Dateien geprüft | **Offen, formale B04-Abnahme gesperrt** |
| IONOS-OrgaVela-Vertragsbackups und konkrete Restorepunkte im Hosting-Konto überprüft | **Offen**, allgemeine Anbieterangaben genügen nicht |

Abnahme nur bei belegtem GH-01…GH-07 und tatsächlichem IONOS-Recoveryinventar. [Masterplan v2.11](../operations/V1_ACCEPTANCE_PLAN.md) · [Maschinenstatus v2.11](../operations/V1_PROGRESS.json); Release-Gates (darunter R03 und R15) bleiben ausdrücklich offen. Keine Datensicherungs- oder Löschaktivität in diesem Matrix-Nachtrag.


### 14.10.2026 – GH-03/GH-04 Reihenfolge für rein synthetischen GitHub-Release-Test korrigiert (Masterplan v2.12)

| Teilgate | Evidenz/Anforderung | Stand |
|---|---|---|
| Externes Referenzverfahren Dreamloud (nicht auf OrgaVela anrechenbar) | [Echtes privates synthetisches `immutable=true` Release #78](https://github.com/janjrre/dreamloud-backups/releases/tag/backup-dreamloud-synthetic-circleci-78), [CircleCI #78 success](https://circleci.com/gh/janjrre/dreamloud-portal/78); temporär erzeugtes CI-GPG-Keypair mit ausschließlich künstlichen Daten, `GH_TOKEN` nur Upload-Berechtigung | **Referenz nachgewiesen, kein OrgaVela-Gate** |
| OrgaVela GH-02 (geschützter Quellbranch+Context+Free-Credits) | Projekt-Context und `main protected=true` teilweise belegt; Restriktions-/PR-/No-Bypass-/Fork-Details nicht vollständig geprüft, testbranch-/Context-Abgleich offen | **Weiter offen vor Secret-Nutzung** |
| OrgaVela GH-04a synthetischer verschlüsselter Remote-Release-Canary | Darf mit **nur temporärem CI-Test-Schlüsselpaar**, eigenem eingeschränktem `GH_TOKEN` auf dem privaten `janjrre/orgavela-backups`, manuellem Opt-in, vollständigem Readback/SHA/Immutable-Release/Restore getestet werden – **kein** Betreiber-PC nötig | **Noch nicht ausgeführt; GH-02 Voraussetzung, permanenter GH-03-Key keine Voraussetzung** |
| OrgaVela GH-03 dauerhafter Betreiber-Recovery-Key | Echter privater Schlüssel muss **vor GH-06** außerhalb CI/GitHub erstellt, mit zweiter getrennter Offline-Kopie/Entschlüsselungsprobe gesichert werden; niemals temporärer CI-Key für reale Daten | **Offen; zwingend vor echtem Backup** |
| OrgaVela GH-06/GH-07 (echte WordPress-Dateien+MariaDB und isolierter Restore) | Nur mit dauerhaftem Betreiber-Empfänger, echter isolierter Wiederherstellung und real geprüftem IONOS-Backupinventar abnahmefähig | **Offen; B04/R03 weiter gesperrt** |

Diese Änderung lockert **ausschließlich** die Test-Voraussetzung für künstliche Daten, nicht die Verschlüsselungs-, Restore- oder Datenschutzpflichten bei Produktivdaten. Kein Merge und keine formale Abnahme ausgelöst.


**GH-04 Code-/Sicherheitsnachtrag 14.10.2026:** [Draft PR #19](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/19) enthält einen **synthetischen** OrgaVela-GitHub-Releases-Transporttest mit ephemerem Runner-GPG-Testkey, festen Repo-/Branch-/Context-Grenzen, nur manuell aktivierbarem Parameter (Default `false`) und geplanter voller Remote-Asset-/Hash-/Immutable-/Restoreprüfung. **Bisher nur vorbereitet und statisch gelesen**, nicht auf CircleCI ausgeführt. Der neue Branch [`test/orgavela-github-release-o03`](https://github.com/janjrre/Projekt-Wordpress-Plugin/tree/test/orgavela-github-release-o03) ist laut GitHub **`protected=false`**; die Betreiber-Expression für den OrgaVela-Context ist noch main-only. Bis beide behoben sind **kein GH_TOKEN und kein Live-E2E**; GH-02 nicht bestanden. GH-04/GH-03/GH-06/GH-07 bleiben offen, formale B04/R03 nicht abgenommen.


## Ergänzender W0/O03-Betriebsnachweis: GH-04 Synthetik-Canary (17.10.2026)

**Einordnung:** Dies ist eine **Operations-Vorprüfung GH-04** und **keine** Erfüllung oder Teilfreigabe von R01–R18. Die R01–R18-Abnahmematrix und deren formale Zähler bleiben unverändert. **GH-04 `accepted` bezieht sich ausschließlich auf einen künstlichen GitHub Releases End-to-End-Test.**

| Prüfschritt / Evidenz | Ergebnis und Nachweistyp |
|---|---|
| GH-02-Sicherheitsvorprüfung | Accepted auf Github-API-Beleg plus expliziter Betreiberbestätigung der GitHub-/CircleCI-Admin-Einstellungen |
| Unveränderliche Quellimplementierung | Protected Branch `test/orgavela-github-release-o03`, geprüfter HEAD `a03b0e079ad0f8bd782574abd5b0ed581bf3c12a`, Skripte verweigern echte Daten und erfordern manuelle Pipeline-Freigabe |
| Erfolgreicher CircleCI-Canary | Betreiber bestätigt `success` für Workflow/Job `d70d145b-b76b-47f4-854c-838d7ad227ed`; keine unabhängige CircleCI-Step-Log-Sicht |
| Privates unveränderliches GitHub-Testrelease | **GitHub REST unabhängig bestätigt**: `janjrre/orgavela-backups`, Release-ID `414750281`, Tag `backup-orgavela-synthetic-circleci-3`, `draft=false`, `immutable=true`, Published `2026-10-16T09:06:20Z` |
| Verschlüsseltes künstliches Archiv | GitHub Asset `646772868`, `orgavela-synthetic-circleci-3.tar.gpg`, 549 Bytes, `uploaded`, GitHub digest `sha256:0fbb3b4077b7b360f76ec9d105772936f48b76f9c531e3c490804267eda4e70a` |
| Nachweis-Assets | `manifest.json` Asset `646772871`; `.tar.gpg.sha256` Asset `646772865`; beide in privaten Release-Metadaten als `uploaded` bestätigt |
| Remote-HASH/Decrypt/Restore | Aus dem unveränderten fail-closed Job-Skript und Betreiber-`success` abgeleiteter Test-PASS; **binäre Asset-Bytes/CI-Einzellogs nicht unabhängig vom Assistenten nachgelesen** |
| Tokenbereinigung | Betreiber bestätigt 17.10.2026: GitHub-Test-PAT **widerrufen** und CircleCI `orgavela-backup-releases` `GH_TOKEN` **gelöscht**; keine unabhängige Secret-API-Lesung |
| Gesamtentscheidung | **GH-04 `accepted` (synthetic only)** bei gemischten Nachweisen; **GH-03 und GH-05–08 offen**, echter Snapshot/Full-Restore/Schedule **nicht** bestanden |

**Stop-Gates für reale OrgaVela-Sicherungen:** Besitzer kontrolliert dauerhaften privaten Offline-Recovery-Key mit zwei separat gesicherten Kopien, dessen kryptografisches Format mit der zukünftigen Writer-/Restore-Architektur übereinstimmt, unabhängige Decryption-Recovery-Probe. Erst nach GH-03 Owner-Abnahme weitere GH-05/06-Runs für echte WP-/MariaDB-Daten und vollständiger isolierter GH-07-Real-Restore. Die synthetische Cloudfreigabe hebt **kein** R01–R18-Releasestor auf.

**Belegort:** [GitHub synthetisches privates Backuprelease](https://github.com/janjrre/orgavela-backups/releases/tag/backup-orgavela-synthetic-circleci-3), [GH-04-Runbook](../operations/W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md), [append-only Arbeitsjournal](../operations/ASSISTANT_WORK_LOG.md), [kanonischer V1-Masterplan v2.20](../operations/V1_ACCEPTANCE_PLAN.md).


## Ergänzender Operations-Gate GH-03: Private age-Recovery vollständig unabhängig vom Cloud-Provider (17.10.2026)

**Abnahmeart:** W0/O03-/GH-03-Betriebs-Gate, **kein** R01–R18-Release-Gate und keine Änderung der formalen Release-Zähler.

| Test-ID | Konkreter Abnahmetest | Aktueller Status/Nachweis |
|---|---|---|
| GH03-A | Produktives Verschlüsselungsformat gegenüber GPG-Synthetik eindeutig festlegen | **PASS Architektur**: `age` gewählt, [GH-03-ADR](../operations/W0_O03_GH03_ENCRYPTION_RECOVERY_ADR.md) committed |
| GH03-B | Privater Owner-Identity-Key nur lokal auf vertrauenswürdigem Gerät erzeugt; niemals GitHub/CircleCI/IONOS/ChatGPT | **PARTIAL (19.10.)**: native AgePony-Identity `age X25519` durch Betreiber-Screenshot im lokalen App-Vault bestätigt; passphrasegeschützte Recovery-PDF lokal gemeldet, jedoch keine zwei physisch getrennten Offline-Kopien / noch kein unabhängiger Importbeweis |
| GH03-C | Zwei getrennte, geschützte physische Offline-Recovery-Kopien mit identischer Public-Recipient-Identität und bytegleichem Key; getrennte Aufbewahrung | **OPEN**, keine Besitzerbestätigung |
| GH03-D | Kunsttext mit eigenem `age1…`-Recipient verschlüsseln und aus Offline-Kopie 2 wieder entschlüsseln; SHA256 der Klartextdateien gleich; Testartefakte bereinigen | **OPEN (19.10.)**: Betreiber meldet erfolgreiches Verschlüsseln/Entschlüsseln einer künstlichen AgePony-Notiz **nur mit Original-App-Key**. Kein SHA256-Beleg und kein Restore aus Offline-Kopie 2; GH03-D daher nicht bestanden |
| GH03-E | Wiederherstellung ohne Original-PC aus getrenntem Medium praktisch möglich; Verlust-/Kompromittierungs-/Rotationsverfahren dokumentiert und vom Betreiber angenommen | **OPEN**, Anleitung verfügbar, kein Eigentümer-Test |
| GH03-F | Nur Public Recipient im künftigen Writer; echte Writer-/Restore-Implementierung später separat mit einem einheitlichen `.tar.age`-Format getestet | **GEPLANT GH-05–GH-07**, noch kein Runtime-/CI-Einsatz und kein reales Backup |

**GH-03 formale Abnahme:** nur bei **GH03-A bis E vollständig** und belegter Owner-Bestätigung ohne private Schlüsselübermittlung. GH03-F ist nachgelagertes Schutzgate für den echten Workflow. `age1…`-Recipient darf später als nicht geheimes Konfigurationsdatum eingebracht werden, dessen Zuordnung zum Besitzerkey muss vor jeder echten Verschlüsselung verifiziert werden. Der synthetische GH-04 GPG-Canary genügt nicht für GH-03. [Offline-Anleitung](../operations/W0_O03_GH03_OWNER_OFFLINE_KEY_RUNBOOK.md). **M0–M7 0/8, P4 0/4, R01–18 0/18 weiterhin unverändert.**


## GH-05 Synthetik-Vorbereitung (19.10.2026, keine Abnahme)

Diese Fälle sind nur lokale Vorprüfungen für O03/GH-05 und zählen **nicht** zu den finalen V1 R01–R18 Release-Gates. Branch Draft PR [#20](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/20), Basis Draft PR #18.

| Check | Nachweis | Status |
| --- | --- | --- |
| GH05-SYN-A | Archiv ausschließlich aus fest codierten künstlichen DB/WP-Dateien; keine externen Eingaben | **PASS lokale Unittests**, 19.10.2026 |
| GH05-SYN-B | Genaues Inventar, SHA256-Manifest, verweigerte Zusatz-/Doppeldateien, Traverse und Symlinks | **PASS lokale Unittests**, 19.10.2026 |
| GH05-SYN-C | CLI nur mit --synthetic-only; kein Real-Daten-/Site-Modus | **PASS lokale Unittests**, 19.10.2026 |
| GH05-SYN-D | Original native age Binary encrypt/decrypt; entschlüsselter TAR bytegleich, manipuliertes Ciphertext abgewiesen | **PASS im CircleCI-Source-Job #4 (20.10.2026)**: vorherige lokale Runtime hatte ohne `age` einen SKIP; offizielles Distributionspaket `age` wurde im erfolgreichen Provider-Job mit erforderlicher Installation und künstlicher Encrypt/Decrypt/Tamper-Prüfung ausgeführt. GitHub-Status auf `590d530` = SUCCESS. Exakter age-Versionsstring und Roh-Test-stdout nicht separat gesichert; dies ist ausschließlich synthetisch. |
| GH05-SYN-E | Sicherer, konsistenter echter OrgaVela/MariaDB-Capture, Freigabe GH-03, Upload, echter isolierter Restore | **NICHT GESTARTET / GESPERRT**, kein echter Zugriff |

**Testgrenze:** Insgesamt 8 Unit-Tests PASS, 1 Integration SKIP auf lokalem Stagingcode; keine Aussage über PR-HEAD-CI. GH-03 bleibt offen wegen fehlendem unabhängigem Recovery. Keine GH-05-Freigabe, keine realen Sicherungen oder autom. Backups. Verbindlich [Fortschrittsdaten](../operations/V1_PROGRESS.json) und [GH-05-Runbook](../operations/W0_O03_GH05_SYNTHETIC_PREPARATION.md).


### 20.10.2026 – Dreamloud-Parität: getrennte age-Producer/Writer-Sicherheits-Gates

| Vorprüfung | Aktueller Nachweis | Status |
| --- | --- | --- |
| GH05-DP-01 | GitHub-Source auf Draft PR #20 für synthetic-only age Producer mit ephemerem Testkey und fest codierter Kunst-SQL/WordPress-Fixture zurückgelesen | **PASS – CircleCI #188 / Source-Job #4 (20.10.2026)**: synthetische age-Producer-Pipeline auf geprüftem Commit `590d530`, Status unabhängig via GitHub = SUCCESS; keine echten Daten |
| GH05-DP-02 | Separer ciphertext-only Writer akzeptiert exakt 3 Asset-Dateien; keine extrahierbaren Quelldaten oder private Recovery-Schlüssel im Workspace vorgesehen | **PASS – CircleCI #188 / Writer-Job #5 (20.10.2026)**: abhängiger Job SUCCESS am selben Commit, Ciphertext-only Übergabe und SHA-/Manifest-Guard verpflichtender Bestandteil des getesteten Jobs |
| GH05-DP-03 | 13 Unittest-Fälle definiert für Modus-/Secrets-/Metadaten-/Symlink-/Hash-/Producer-Writer-Schranken, einschließlich optionaler age-Integration | **PROVIDER-PASS via Source-Job #4**: beide Python-Suiten sind im erfolgreichen CI-Job obligatorisch; 13 neue Fälle definiert, Rohlogs/individuelle Zählwerte nicht unabhängig gelesen, daher keine erfundenen Testzeilenzahlen |
| GH05-DP-04 | CircleCI: zwei Jobs mit `requires`, nur manuelles `enable_gh05_synthetic_parity=true`; Default false, Branchfilter nur Draft PR #20, keine Contexts, Secrets oder Cron | **PASS als isolierte GH-05-Synthetik-Pipeline #188**: `enable_gh05_synthetic_parity=true` manuell; CI-Jobstatus #4/#5 am `590d530` beide SUCCESS. Kein Cron/Secret-Context/Upload. PR #17+PR #20 weiterhin nur im Draft, spätere Integration separat |
| GH05-DP-05 | Kompatibler echter OrgaVela-Snapshot, native age-Offsite-Release-Writer/Immutable-Readbacks, isolierte echte Recovery, freigegebener täglicher Job | **BLOCKED**, GH-03 und reale GH-05/06/07/08 offen |

Die Dreamloud-Produktionsbelege #348/#353 gelten **nur** für Dreamloud (OpenPGP), nicht für OrgaVela (age). Diese Checkliste zählt weder zu 71 normativen Coding-Paketen noch zu finalen R01–R18. [Architektur-ADR](../operations/W0_O03_DREAMLOUD_PARITY_ADR_2026-10-20.md). V1 weiterhin 62/71 historische Codepakete, M0–M7 0/8, P4 0/4, R01–R18 0/18.


**Nachweis-Nachtrag 20.10.2026 – tatsächlich ausgeführte CircleCI-Pipeline #188:** [Producer-Job #4](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/4) und [Writer-Job #5](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/5) von GitHub auf Commit `590d530686356d641774a27ff4004aa27fd063aa` unabhängig als **SUCCESS** verifiziert; Betreiber-Screenshots: kompletter 20-s-Workflow, source 11 s, writer 3 s, verschlüsselter Workspace-Transfer 11 KiB. Alle Test-/Key-/Writer-Schritte im geprüften CI-Skript sind zwingend; Roh-stdout nicht separat überprüft. **Formal weiterhin KEIN GH-03**, kein produktives GH-05, kein GH-06-Release, GH-07-Restore oder GH-08-Zeitplan; keine Erhöhung von M0–M7-, P4-, R-Gates oder historischen Coding-Paket-Zählern. 

### GH-06 age Release synthetische Vorprüfung (20.10.2026, Draft PR #21)

| Prüfpunkt | Evidenz | Status |
| --- | --- | --- |
| GH06-AGE-01 | Native age statt GH04 OpenPGP: nur .tar.age, Header age-encryption.org/v1, fake synthetic input | **PASS im GH-06-Offline-Sicherheits-Test #209 (20.10.)**: synthetisches age-Format, Header und Bash-Prüfung unter Fake-Provider geprüft; keine reale Kryptografie-/Release-E2E-Abnahme |
| GH06-AGE-02 | Privates Repo-Pin janjrre/orgavela-backups, keine produktive Datenquelle oder Owner-Privatekey | **PASS im GH-06-Offline-Sicherheits-Test #209**: Repo-Pin und Secret-Ablehnung negativ geprüft, echte Providerautorisierung bleibt offen |
| GH06-AGE-03 | Exakt drei Releaseassets, unberührte Drafts/Tags, double SHA256 Full Remote ID-Readbacks und Verified Publish | **CODE VORBEREITET; KEIN UPLOAD** |
| GH06-AGE-04 | Published Release immutable=true, unabhängiger Tag-Download, Wiederherstellung ausschließlich künstlicher age-Daten | **CODE VORBEREITET; KEIN REALER PROVIDERLAUF** |
| GH06-AGE-05 | 13 lokale Fake-gh-Checks, keine echten GitHub Calls oder Tokens; manuell parametrisierter no-secret CircleCI-Job | **PASS, 13/13 Offline-Tests in CircleCI #209**, Raw-Stdout `Ran 13 tests in 0.147s` / `OK`, GitHub-CI-Status `success` auf SHA `6f885c5`; Test nutzt ausschließlich Fake-gh, Flag weiterhin default false |
| GH06-AGE-06 | Protected GH06 Testbranch, auf Projekt/Branch beschränkter neuer Writer-Context und kurzlebiger Einzelrepo-Token | **TEILSTATUS 20.10.: GitHub-Branch `test/orgavela-age-release-o03` ist `protected=true` (API); Operator-Screenshots zeigen PR required=ON, do not bypass=ON, force pushes=OFF, deletions=OFF, allerdings Save changes sichtbar (gespeicherter UI-Endstand nicht vollständig bewiesen). CircleCI Context-Projekt-/Branch-/SSH-/API-Configrestriktionen visuell bestätigt.** PR approvals und required status checks OFF; noch kein Token, kein tokenbetriebener GH06-Remote-Job, kein Release. GH06-AGE-06 daher PARTIAL, nicht accepted. |
| GH06-AGE-07 | Produktiver age Writer nach Owner-GH03-Recovery, echter GH05 Konsistenz, eigenem isolierten GH07-Restore | **BLOCKIERT / NICHT ABGENOMMEN** |

Keiner dieser Vorbereitungspunkte erhöht die historischen 62/71-Coding-Pakete oder M0–M7 0/8, P4 0/4, R01–18 0/18 formalen Gates. Historische GH05-CircleCI #188 ist ein anderer Quellcommit/Job. [GH-06-Runbook](../operations/W0_O03_GH06_AGE_RELEASE_PREPARATION_2026-10-20.md).


**GH-06 Offline-Abnahme 20.10.2026 – CircleCI #209:** [GH06-Job #6](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/6) auf GitHub-Commit `6f885c585557661e3250fc477e2464779cf01adc` vom Anbieterstatus `ci/circleci: gh06-age-release-offline-qa=success` unabhängig bestätigt. Benutzer-Screenshot zeigt **13 Tests, 0 Fehler, OK**. GH06-AGE-01/02/05 sind als **Offline-Vorprüfung** bestanden. GH06-AGE-03/04 (echter Remote-GitHub Release), GH06-AGE-06 (neue Branch-/Context-/Token-Gates) und GH06-AGE-07 (Produktivbetrieb) bleiben **offen**. Keine Änderung von V1-Zählwerten: 62/71, M0–M7 0/8, P4 0/4, R01–R18 0/18.


**GH06-AGE-06 Kontext-Autorisierungszwischenstand 20.10.2026:** Nach Benutzerbestätigung ist `Save changes` auf klassischer Testbranchregel betätigt (Screenshot: PR required & no bypass AN, force push/delete AUS). GitHub-API meldet `protected=true`, Detailregel-API weiterhin 403; Required Checks/Approvals bleiben nicht aktiviert. CircleCI project-/branch-/SSH-/API-config-Expressionrestriktionen sind visuell nachgewiesen, Context derzeit ohne Secrets. **[Draft PR #22](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/22)** implementiert nur einen default-FALSE `gh06-age-context-smoke` mit angeschlossenem LEEREM restricted Context und ohne GitHub-Release/Token/Privatekeys. **NOCH NICHT gemergt oder gelaufen**, daher keine Kontext-Provider-Abnahme. GH06-AGE-06 bleibt PARTIAL, GH06-AGE-03/04/07 weiter OPEN. GH05 #188, GH06 no-secret Fake-GitHub #209 (13/13) behalten ihren abgegrenzten PASS. V1-Fortschritt 62/71 Coding, formal 0/8 M, 0/4 P4, 0/18 R unverändert.


**GH06-AGE-06 Korrektur 20.10.2026 – enger PR #23:** GitHub-Review ergab für ursprünglichen [PR #22](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/22) trotz gewünschtem Context-Only-Scope **6 geänderte Dateien/28 Commits**. Dieser PR ist als superseded **ungemergt geschlossen**. Neuer [Draft PR #23](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/23) wurde direkt vom geschützten Base-Branch `test/orgavela-age-release-o03` aus angelegt: **nur `.circleci/config.yml`, 1 Commit, 44 hinzugefügte Zeilen**, default-false Context-Smoke, keine Release-/Owner-Privatekeys. **Status weiter PARTIAL:** #23 noch Draft/ungemergt, Provider-Smoke noch nicht gelaufen, Context weiter ohne Token, GH03/echtes GH05/06/07/08 offen. V1 62/71 historische Coding-Pakete, 0/8 M, 0/4 P4, 0/18 Release-Gates unverändert.


**GH06-AGE-06 Checkpoint 20.10.2026 – minimaler geschützter PR23-Merge:** [PR #23](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/23) war nach Benutzer-Ready-Status `mergeable=true`, Review bestätigte genau **1 Datei `.circleci/config.yml`, 1 Commit, 44 Insertions**, keine weiteren Änderungen. Normaler geschützter PR-Squash-Merge durch GitHub mit erwartetem Head SHA erfolgreich: `merged=true`, Zielbranch `test/orgavela-age-release-o03` jetzt SHA `cb22a2b434ee18ef79811afd3c99f47a7ac36a4b`, per Branch-API `protected=true`. Kein required CI status check aktiv, Head-Statuses vor Merge leer; daher diese Merge-Prüfung ist **keine** CircleCI-Runtime-Abnahme. Neuer Context-Smoke-Workflow hängt an **default=false** `enable_gh06_age_context_smoke`, nutzt den leeren restricted Context und keinerlei GitHub Release API. Betreiber muss jetzt manuell einmal genau diesen Job auf dem Testbranch ausführen. **GH06-AGE-06 bleibt PARTIAL bis CircleCI-Ergebnis auf Merge-SHA**, GH06 remote-age-E2E und echte produktive Backups weiter BLOCKED. V1-Zähler historisch Coding 62/71, M0–M7 formal 0/8, P4 0/4, R01–18 0/18 unverändert.


**GH06-AGE-06/07 neuer Nachtrag – Context-Run #255 und künstlicher Release-PR24:** GitHub Combined-Status **`ci/circleci: gh06-age-context-smoke=success`** bei geschütztem Testbranch `cb22a2b434ee18ef79811afd3c99f47a7ac36a4b` und [CircleCI Job #7](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/7) unabhängig bestätigt, Benutzerlog zeigt `GH06_CONTEXT_ACCESS=PASS`, `GH06_CONTEXT_SECRETS=NONE`, `GH06_GITHUB_RELEASE_UPLOADED=NO`. Somit **GH06-AGE-06 Context-Erlaubnis-Vorprüfung PASS ausschließlich für den positiven zugelassenen Branch ohne bekannte Secrets**; negatives Projekt-/Branch-Abweisen, beliebige Environment Secrets oder tokengetragener GitHub-Release-Zugang sind nicht getestet. GH06-AGE-07 real/produktiv weiterhin BLOCKED, GH03 fehlt. Als **nächste noch offene** künstliche GH06-AGE-03/04-Provider-Abnahme ist [Draft PR #24](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/24) vorbereitet: GitHub-Prüfung **1 Datei/1 Commit/+68/-0**, Branchfilter `test/orgavela-age-release-o03`, `enable_gh06_age_release_remote` bool default=false sowie separates CircleCI Approval-Gate. Kein Merge, PAT oder GitHub age Release ausgeführt, keine Kostenzusage. Noch Script-/Scope-/Immutability-/Context-Sicherheitsreview vor Aktivierung. Normative V1 62/71, 0/8 M, 0/4 P4, 0/18 R unverändert.


**GH06-AGE-03/04 vor echter Remote-Abnahme – Sicherheitsreview 20.10.2026:** [PR #24](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/24) weiterhin DRAFT (1 CI-Datei, 1 Commit, +68, Default-false + CircleCI Approval-Hold), kein PAT oder echter age GitHub Release-Lauf. Statischer Writer-E2E-Abgleich: synthetische Quelle mit wegwerfbarem age-Key, private Repo-Pin `janjrre/orgavela-backups`, 3 Assets, SHA256-Checks vor/nach numerischem Publish sowie post-Publish-`immutable=true` und Independent Tag-Download/Decrypt beabsichtigt; **noch kein providerseitiger PASS**. GitHub Backup-Repo ist privat/unarchiviert; historisches synthetisches **OpenPGP** Release ID 414750281 aktuell `immutable=true`. **Einstellungen für alle zukünftigen Releases nicht unabhängig verifizierbar:** Admin(read)-geschützter neuer `GET /immutable-releases` nicht über GitHub-Connector lesbar (Allowlist), UI-Gate erforderlich, temporärem Writer-PAT keine Admin-Berechtigung verleihen. CircleCI Context `orgavela-age-release-tests` weiterhin `All members` aus früherem Screenshot; gemäß offizieller Context-Doku **alle Gruppen entfernen => nur Org-Admins**, ergänzende Project/ANDed Expressions unbedingt beibehalten. Erst UI-Nachweis beider Gates, dann Review der Freigabe. Der age Test-Privatekey im selben *synthetischen* Writer-Job ist **nicht** produktionsfähig; GH03 Betreiber-Recovery separat auf unabhängigem Medium fehlt. Frühere #188, #209 (13/13), #255 weiterhin dokumentierte begrenzte PASS. V1-Kennzahlen unverändert 62/71 Coding, M 0/8, P4 0/4, R 0/18.


**GH06-AGE-06/07 Korrektur – CircleCI kein Gruppeneintrag ist UNUSABLE; GitHub immutable UI PASS (20.10.2026):** Betreiber-GitHub-Settings-Screenshot zeigt `janjrre/orgavela-backups > Releases > Enable release immutability` CHECKED. Dieser separate Future-Repo-Gate ist für die aktuelle UI-Konfiguration **PASS**, jedoch kein echtes age Release-Providerresultat. Betreiber-CircleCI-Context-Screenshot zeigt nach Entfernen `All members` **leere Group restrictions** und explizit `The Context is currently unusable. To allow usage, add a security group`. Damit wird die vorige Annahme `keine Gruppe = nur Admins = betriebsbereit` **zurückgenommen**, Context-Group-Gate **BLOCKED**. Der historische positive Context Smoke #255 beweist nur den früheren Zustand **mit** `All members`, nicht die aktuelle Gruppenlosigkeit. Nächster Besitzer-Schritt: `Add group restriction` öffnen und verfügbaren Nutzerkreis bewerten, Projekt+2 Expression-Restrictions nicht verändern, dann erst ggf. neuen no-secret Smoke. PR24 weiterhin Draft/unmerged, GH_TOKEN nicht vorhanden, keine echte GH06-age-Publikation. GH03 unabhängige Owner-Recovery und GH05–08-Prod-Gates bleiben offen; V1 62/71 Coding und 0/8 M, 0/4 P4, 0/18 Release formal unverändert.


**GH06-AGE-06/07 Fortschritt 20.10.2026 – CI #283 Provider-PASS nach Group-Fix; GH06 E2E-Workflow geschützt installiert:** Nach Nutzer-Screenshot Pipeline #283 auf exakt protected GH06-Testbranch SHA `cb22a2b434ee18ef79811afd3c99f47a7ac36a4b`: ein grüner `gh06-age-context-smoke` Job, GitHub `ci/circleci: gh06-age-context-smoke=success` bestätigt, [Job #8](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/8). `GH06_CONTEXT_ACCESS=PASS`, `GH06_CONTEXT_SECRETS=NONE` nur bzgl. expliziter Variablen, `GH06_GITHUB_RELEASE_UPLOADED=NO`. GitHub `janjrre/orgavela-backups` Release-Immutability im Betreiber-UI checked. **PR #24** (ein File `.circleci/config.yml`, +68/-0, ein Commit) danach bei unverändertem Scope zum Review freigegeben und **über regulären geschützten PR-Squash** gemergt auf `test/orgavela-age-release-o03`, Ziel-SHA `eba067913eb0290b2a95e40029f77e3b803ed924` unabhängig `protected=true` verifiziert. Der neue GH06-AGE-03/04-Synthetic-Remote-Workflow erfordert explizites Boolean `enable_gh06_age_release_remote=true` und **separate CircleCI Approval** und bleibt default false. **Weder Token noch GH06 age Release angelegt, CI Remotejob noch nicht ausgeführt**; GH06-AGE-03/04/07 nicht abgenommen. Zunächst freier Kostenrahmen/eng temporärer Backup-Repo-only Fine-grained Token separat zu autorisieren und sofort nach E2E widerrufen. GH03 owner-independent age Recovery weiter fehlt, alle echten Backups gesperrt. Normativ weiterhin 62/71 Coding, 0/8 M, 0/4 P4, 0/18 R formal.

## 20.10.2026 – O03/GH06 synthetisches Release-E2E (nicht als V1 R01–R18-Gate werten)

| Beleg | Geprüfter Scope | Ergebnis | Grenze |
| --- | --- | --- | --- |
| CircleCI Pipeline #298, `test/orgavela-age-release-o03` @ `eba0679` | Manuell freigegebener rein synthetischer age-X25519-Release-Writer, SHA-256 Readback vor/nach Veröffentlichung, unabhängige entschlüsselte synthetische Wiederherstellung | PASS laut Betreiber-Screenshot / Provider-Step-Log | Keine reale OrgaVela-DB/Dateien, nicht Owner-GH03-Key |
| GitHub privates Backup-Repo, Release ID `418380590` | GitHub API Release und alle drei Asset-Metadaten | `draft=false`, `immutable=true`, 3 Assets, SHA-256 Digest vorhanden | Kein zusätzlicher eigener Binär-Download/Restore durch Assistenz |
| CircleCI Pipeline #299, identischer Source-Branch | Ohne Approval darf Writer nicht starten | HOLD durch Betreiber-Screenshot nachgewiesen | Cancel #299 nur empfohlen, nicht verifiziert |
| GH06 nach dem Lauf | Kurzlebigen Fine-grained PAT widerrufen, `GH_TOKEN` im Context löschen | **OFFEN**, Betreiberbestätigung erforderlich | Keine erneuten geheimnistragenden Läufe vorher |

Dies ist ergänzende **synthetische Transport-Sicherheits-Evidenz** unter W0/O03, **keine** Abnahme einer der 18 formalen V1-Release-Sperren und kein realer Backup-/Recovery-SLA-Nachweis. Maßgebliches Fortschrittsregister bleibt `docs/operations/V1_PROGRESS.json`.

## 20.10.2026 – Ergänzender GH06-Cleanup-Beleg (Betreiberbestätigung)

| Synthetisches GH06-Ereignis | Status | Belegqualität |
| --- | --- | --- |
| CircleCI #298 native age Release-E2E | BESTANDEN | Provider-Screenshot und unabhängiger GitHub Release-ID-418380590-Metadaten-Read |
| CircleCI #299 Approval-HOLD | BESTANDEN | Betreiber-Screenshot: Writer wartet |
| CircleCI #299 abgebrochen | Betreiber bestätigt | Antwort „Erledigt“ auf konkrete Abbruchanweisung, kein CircleCI-Admin-API-Read |
| GH06-Test-PAT widerrufen | Betreiber bestätigt | Antwort „Erledigt“, privater GitHub-Tokenstatus nicht per Connector lesbar |
| GH_TOKEN aus `orgavela-age-release-tests` entfernt | Betreiber bestätigt | Antwort „Erledigt“, CircleCI-Context nicht per API gelesen |
| Dauerhafter Owner-age-Key, zwei unabhängige Offlinekopien + echter Restore | **OFFEN** | GH03 – separates Gate |
| Produktions-GH05/06/07/08 | **OFFEN** | Keine Live-Sicherungen, kein Zeitplan |

**GH06 synthetic E2E + Operator-Cleanup geschlossen**, aber W0/O03-Produktionsbackup sowie sämtliche **R01–R18 formal 0/18** bleiben offen. Diese Zeile ergänzt den vorherigen „cleanup OPEN“-Momentstand chronologisch, ohne historische Belege umzudeuten.

## 20.10.2026 – GH03 Papier-Backup im Safe: neuer Sicherheits-Teilnachweis

| GH03-Recoverykriterium | Ist | Evidenz |
| --- | --- | --- |
| Eigentümer besitzt native `age1…`-AgePony-Identität | zuvor lokal getestet | Betreiberangabe und früherer AgePony-Test; Original-iPhone-Vault nicht weitergeben |
| PDF-Paper-Backup auf **Papier** vorhanden | **1 Kopie bestätigt** | Betreiber: eigener Drucker, ausgedrucktes PDF im Safe |
| Zweite **physisch/räumlich getrennt geschützte** Offline-Kopie | **offen** | noch nicht bestätigt |
| Papier-Backup-Passphrase/Notfallzugang | **unbekannt** | nicht nachgefragt, Passphrase niemals teilen |
| Original-Recipient vs. nur aus Papier wiedergewonnener Recipient | **offen** | noch kein unabhängiger lokaler Test |
| Nur mit Papier-Recovery-Key synthetische Datei entschlüsselt + SHA-256 bytegleich | **offen** | bisher nur Test im ursprünglichen AgePony-Vault |
| GH03 formell `accepted` / GH05–08 echte Backups | **NEIN / gesperrt** | 1 Kopie ersetzt keine geprüfte Wiederherstellung oder operative Backup-Gates |

**Entscheidung:** `gh03_offline_copy1_attested=true` ausschließlich als durch Betreiber belegter Papier-Druck-/Safe-Verwahrstatus, `gh03_offline_copy2_attested=false`, `gh03_formal_accepted=false`. Keine öffentlichen Secrets, keine neue CircleCI- oder produktive WordPress-Ausführung.

## 20.10.2026 – O03 Wechsel auf OpenPGP/PGPony

| Kontrollpunkt | Beleg / Status |
| --- | --- |
| Formatwahl für neue produktive Sicherungen | PGPony/OpenPGP vom Betreiber bestätigt, Masterplan v2.22 |
| Dreamloud-Architekturprüfung | Read-only Source: OpenPGP public key fingerprint, separated capture/writer, immutable releases, isolated restore; keine geteilten Secrets |
| Historische GH04 GPG- und GH06 age-Tests | Beide synthetisch bestanden; nicht owner-PGPony-Interoperabilität |
| PGPony-Owner-Key mit GnuPG kompatibel | OFFEN |
| Zwei separate geschützte neue OpenPGP-Recovery-Kopien | OFFEN; gedrucktes AgePony-PDF zählt nicht |
| Decrypt aus unabhängigem PGPony-Private-Key | OFFEN |
| GnuPG-OpenPGP-Synthetik-Prototyp | Lokal unter isoliertem Einmal-Testschlüssel erfolgreich, noch kein PR-HEAD-Provider-E2E |
| Produktive GH05–08/Release R01–18 | BLOCKIERT, 0/18 |

Dies ist ein nicht-produktiver O03-Architekturwechsel; er erzeugt **keine** formale V1-Abnahme.

## 20.10.2026 – PGP-02 Synthetik-Vorbereitung, nicht provider-abgenommen

| Bestandteil | Status | Nachweis |
| --- | --- | --- |
| Public-only OpenPGP-Packer ohne Produktionsmodus | Code angelegt | `scripts/ci/orgavela-openpgp-synthetic-pack.sh`, Commit `64b0a81` |
| Synthetischer lokaler GnuPG-Roundtrip mit temporärem Testschlüssel | PASS für lokalen unabhängigen Prototyp | lokales Bash/GPG, kein PGPony-Owner-Key, kein CI-SHA |
| Testskript im Draft-Branch | Code angelegt, Lauf auf exaktem GitHub-SHA ausstehend | `scripts/ci/test-orgavela-openpgp-synthetic-pack.sh`, Commit `2e13391` |
| GnuPG gegen PGPony v4/v6 Verschlüsselungs-Key | NICHT AUSGEFÜHRT | noch keine eigene PGPony-Identität für OrgaVela bestätigt |
| Eigene OpenPGP-Recovery-Kopien + Restore | NICHT AUSGEFÜHRT | GH03 weiter offen |
| GitHub OpenPGP immutable Release / produktive GH05-08 | NICHT AUSGEFÜHRT | erst nach expliziten Freigaben |

Historische GH04-GPG- und GH06-age-CI-Belege bleiben unabhängig gültig, erfüllen diese neuen Owner-Key- und Produktiv-Gates **nicht**.

## 20.10.2026 – GH03-PGPony Public-Key-Prüfung

| Bedingung | Resultat | Nachweisgrenze |
| --- | --- | --- |
| OpenPGP-Public-Key syntaktisch korrekt & signiert | PASS | GnuPG 2.4.7, 2 gute Self-Signatures |
| Version, Algorithmen, Fingerprints | PASS | v4, Ed25519 `7C9DC60D8E1B293E514696EB333D15A4D8BA625C`, cv25519 `CF5D8A8498F65C6D894ECE2FD9E58CC6484AF1DE` |
| Temporäre Verschlüsselung nur mit öffentlichem Empfänger | PASS | isolierter GnuPG-Test ohne irgendeinen Secret-Key |
| Betreiber-PGPony entschlüsselt externes GnuPG-Archiv | **OFFEN** | Ciphertext `orgavela-pgpony-kompatibilitaetstest.gpg`, 288 Bytes, SHA256 `8da079bd89e8048a3238133fb7fe5f5daf6076c6e2d3b3947733dd1522da2be1` für Owner bereitgestellt |
| 2 unabhängige geschützte Backup-Kopien des NEUEN PGPONY-Private-Key | **OFFEN** | Altes AgePony-Paper zählt nicht |
| Recovery allein aus unabhängigem Private-Key-Export | **OFFEN** | Keine Kopie-2-Probe ohne Original-iPhone |
| GitHub Public-Key-Policy | Candidate only | `.github/backup/orgavela-backup-candidate-public.asc`, `.github/backup/orgavela-pgpony-candidate-policy.json`; öffentlich sichtbarer Draft, keine Produktionsfreigabe |
| GH03/Produktion GH05–08 | **BLOCKIERT** | Kein produktiver Writer/Restore/Schedule |

**Technischer Teilfortschritt, keine formale GH03-, Milestone- oder Release-Gate-Abnahme.**

## 20.10.2026 – PGPony-Entschlüsselung extern erzeugter GnuPG-Datei

| GH03-Test | Status | Abnahmegrenze |
| --- | --- | --- |
| GnuPG-Public-Key-Import und künstliche Verschlüsselung | PASS | separater, bereits dokumentierter GnuPG-2.4.7-Test ohne Private-Key |
| PGPony auf iPhone: externe `.gpg`-Datei mit ursprünglichem Privatkey entschlüsselt | **PASS laut Betreiber** | ausdrückliche Nutzerantwort „ja ich konnte es entschlüsseln“, tatsächliche Klartext-Sollcode-Gleichheit nicht zusätzlich bestätigt |
| Zwei geschützte physisch getrennte Private-Key-Kopien des neuen PGPony-Schlüssels | **OFFEN** | kein Nachweis |
| Wiederherstellung allein aus zweiter Kopie ohne ursprünglichen iPhone-Vault | **OFFEN** | kein Nachweis |
| GH03 endgültig / produktive GH05–08 | **NICHT ABGENOMMEN** | Candidate-Key bleibt produktiv gesperrt |

Dies ist ein Fortschritt für die **Betreibergeräte-Interoperabilität**, nicht für eine nachweisbar vom Smartphone-Verlust unabhängige Recovery oder formale V1-Abnahme.

## 20.10.2026 – OpenPGP-QA im echten, Git-Hash-abgeglichenen Testskript

| Prüffall | Resultat | Belastbarer Nachweis |
| --- | --- | --- |
| `scripts/ci/orgavela-openpgp-synthetic-pack.sh` Skriptidentität | PASS | GitHub Blob SHA = lokal `git hash-object` `0bdf88dab604297a0fa21ba5456f346fbc878766` |
| `scripts/ci/test-orgavela-openpgp-synthetic-pack.sh` Skriptidentität | PASS | Blob `0419da15160e885a83c0042514e4048a29a69a58` |
| `scripts/ci/test-orgavela-pgpony-public-key.sh` Skriptidentität | PASS | Blob `94727028ad9dc3263c62ce954999967223efd017`; Public .asc Blob ebenfalls Git-hash-abgeglichen |
| Öffentlicher PGPony-Key, v4 Ed25519/cv25519, Fingerprints, lokale Verschlüsselung | PASS | GnuPG 2.4.7; keine Owner-Private-Daten |
| Synthetisches PGP-Archiv erzeugen / entschlüsseln / SHA256 | PASS | nur temporärer Testschlüssel und künstliche Dateien |
| Negativtests falscher Fingerprint, synthetischer Private-Key-Input, Prod-Modus, vorhandene Ausgabe, fiktives `GH_TOKEN`, Opt-in fehlt, ungültige ID, Symlink | **8/8 PASS** | alle fehlertoleranzprüfenden Aufrufe endeten erwartungsgemäß mit Fehlercode |
| Neuer CircleCI-Parameter `enable_pgpony_openpgp_offline` | Code: Default `false` | Featurebranch-Filter, keine Secret-Contexts, kein CI-Ausführungslauf |
| CircleCI-PGPony-Job auf exakt diesem PR HEAD | **AUSSTEHEND** | nicht automatisch grün behaupten |
| iPad-Kopie und unabhängige PGPony-Recovery | **AUSSTEHEND** | Betreiber kündigte Test an; noch kein Ergebnis gemeldet |
| Echte GH05–08-Backups / R01–18 | **BLOCKIERT** | weiter keine GH03-Recovery-Abnahme |

**Interpretation:** Lokaler Code-QA-PASS ist keine vollständig abgenommene CircleCI-Pipeline, kein aus zweiter Offlinekopie geprüfter PGPony-Owner-Key und keine Produktionsfreigabe.

## 20.10.2026 – PGPony CircleCI Fehlversuch und Diagnose

| Kontrolle | Stand | Beleg/Abgrenzung |
| --- | --- | --- |
| Manueller CircleCI `pgpony-openpgp-offline-qa` | **FAIL** | Source SHA `76d8ebd...`, GnuPG-Installation PASS, Synthetic-QA-Step Exit 1 |
| Konkrete Fehlursache | **OFFEN** | Screenshot zeigt nur Exit 1, keinen vollständigen Teiltest-Log |
| Diagnostische Nachbesserung | **Code aktualisiert** | CircleCI Stage-Marker und sanitized ERR Script-/Zeilenangabe |
| GnuPG-Signaturprüfung robuster | **lokal PASS** | Temporäres `gpg --check-sigs`-Log vermeidet potenzielles `grep -q`/`pipefail`-Verhalten; ursprüngliche Ursache nicht bewiesen |
| Exakter neuer Public-Key-Testcode | **lokal PASS** | Blob `3003f07073f8398e986546480c9b38d5a1506b44`, GnuPG 2.4.7 |
| Exakter neuer Archiv-Testcode | **lokal PASS** | Blob `04802b19a51ca1e68a3d538ad667e81b38d84323`, 8/8 negative Prüfungen |
| CircleCI-Retest auf neuer HEAD | **AUSSTEHEND** | Neue manuelle Pipeline nach Creditprüfung statt Rerun auf alter Revision |
| GH03 private Copy-2 Recovery / echte GH05–08 / V1 Gates | **OFFEN/BLOCKIERT** | Keine formale Abnahme |

Kein Provider-PASS wird durch lokale grüne Tests ersetzt.

## 20.10.2026 – Zweiter PGPony-CircleCI-Fehler: genaue Zeile 27, Bitlängenassertion korrigiert

| Prüffall | Ergebnis | Konkreter Beleg |
| --- | --- | --- |
| Manueller CircleCI Lauf 2 auf Commit `49ace9d...` | **FAIL** | Step `public_key_preflight`, `test-orgavela-pgpony-public-key.sh` **Zeile 27**, `exit=1` |
| GnuPG-Colons-Public-Key-Verifikation alt | **zu strikt** | Regex verlangt fest `:255:22:`, obwohl Längenanzeige nicht stabil ist; beobachteter CI-Zeilenfehler eindeutig, genaue CI-GnuPG-Längenanzeige nicht gezeigt |
| Korrigierter öffentlicher Schlüsseltest | **lokal PASS** | Commit `22952acf...`, GitHub Blob `1f23cbbd625cb60e51b57f4db2643f5fee3ec162`, exakter Datei-Hash lokal bestätigt |
| Variantenprüfung 255/256 Bit | **lokal PASS** | Primär-Algorithmus 22 und Encryption-Subkey-Algorithmus 18 mit Capability e; Fingerprints weiterhin vollständig angeheftet |
| CircleCI-Test auf korrigiertem Commit | **AUSSTEHEND** | Kein Run 3, keine Provider-Abnahme |
| Owner Private-Key Copy 2 / GH03 / reale GH05–08 | **BLOCKIERT** | Noch kein unabhängig geprüfter Backup-Restore |

**Gezielt bekannte Fehlerstelle entfernt; erneuter Provider-PASS darf nicht vor tatsächlichem Lauf behauptet werden.**

## 20.10.2026 – Dritter PGPony/OpenPGP-CircleCI-Offline-Run grün

| Prüffall | Ergebnis | Belastbare Beobachtung |
| --- | --- | --- |
| Manueller CircleCI `pgpony-openpgp-offline-qa` auf Quellcommit `50509600f5e3df842e8f320bcf5160726072a374` | **PASS** | Betreiber-Screenshots ~17:48 Uhr, grüner Workflow und Job, ~14 Sekunden |
| GnuPG auf CircleCI-Runner | **PASS** | `gpg (GnuPG) 2.2.19`, nicht lokale GnuPG-2.4.7-Version |
| Öffentlicher PGPony-Schlüssel, Fingerprints, CV25519-Unterschlüssel, gültige Selbstsignatur, synthetische Verschlüsselung | **PASS** | Sichtbare `PGPONY_QA_STAGE=public_key_preflight` und positive Public-Key-Testausgabe; Private-Key beim Provider nicht vorhanden |
| Isoliertes synthetisches Backup, OpenPGP, SHA-256 und Restore | **PASS** | `PGPONY_QA_STAGE=synthetic_openpgp_roundtrip`, `PASS: OpenPGP public-key encrypted backup package and SHA-256 sidecar generated` |
| Acht Ablehnungstests für unsichere Eingaben | **8/8 PASS** | `PASS: isolated OpenPGP synthetic pack, SHA256, recovery and 8 negative checks` |
| Abschließender CI-Marker | **PASS** | `PGPONY_OFFLINE_QA=PASS` |
| Erstes und zweites manuelles CI-Experiment | **historisch FAIL** | Präzise im Log erhalten; nicht rückwirkend umetikettiert |
| Vollständige Job-ID und tatsächliche CircleCI-Abrechnung | **nicht unabhängig geprüft** | Nur sichtbare Betreiber-Screenshots; kein autorisierter providerseitiger Log-/Billing-Zugriff |
| Owner-iPad-Schlüsselkopie, zwei physisch unabhängige geschützte Offline-Exporte, zweites unabhängiges Recovery | **OFFEN** | Noch keine Bestätigung des Import-/Restores |
| Reales Source-Capture, private unveränderliche GitHub Releases, produktive GH05–GH08 | **BLOCKIERT** | CI-Job ist synthetisch und arbeitet ohne Secrets/Produktivdaten |
| Formale GH03/M0–M7/P4/R01–R18-Abnahmen | **NICHT bestanden** | Kein V1-Release- oder Meilenstein-Signoff durch diesen Teiltest |

**Dieser PASS ist ausschließlich die technische, synthetische CI-Prüfung auf benanntem GitHub-SHA.**

## 20.10.2026 – GH03 Geräteunabhängige Entschlüsselung auf iPad, Offline-Recovery weiter ausstehend

| Nachweis | Status | Bewertungsgrenze |
| --- | --- | --- |
| Neues PGPony-Gerät: iPad-Import der OrgaVela-Privatidentität | **PASS laut Betreiber** | „iPad-Import erfolgreich“; Importweg nicht ausdrücklich spezifiziert |
| Vollständiger Hauptschlüssel-Fingerprint auf iPad stimmt | **PASS laut Betreiber** | Abgleich mit `7C9DC60D8E1B293E514696EB333D15A4D8BA625C` |
| Externes synthetisches OpenPGP-Testarchiv auf iPad entschlüsselt | **PASS laut Betreiber** | Entschlüsselung als erfolgreich bestätigt; nicht selbst beobachtet, Sollcode nicht erneut wörtlich angegeben |
| Echte separate, dauerhaft aufbewahrte Offline-Privatkey-Kopie #1 | **OFFEN** | Kein Nachweis über längerfristig gesichertes Medium |
| Zweite unabhängig geschützte Offline-Kopie #2 / Recovery aus diesem Medium | **OFFEN** | iPad-Geräteimport allein ist nicht der Nachweis zweier physisch und technisch getrennter Datenträger |
| Unabhängig abrufbare Passphrase | **OFFEN** | Keine Bestätigung, keine Offenlegung von Geheimnissen erwünscht |
| GH03 formale Abnahme | **BLOCKIERT** | Erst Notfallwiederherstellung aus gesicherter Offlinekopie und getrennte Kopien belegen |
| Private immutable GitHub-Releases, echte GH05–GH08, V1 R01–18 | **BLOCKIERT** | No-secret CircleCI synthetisch PASS, aber kein Produktiv-/Recovery-Gate freigegeben |

**Operator-Attestation genügt für den Geräte-Funktionstest, nicht für die vollständige GH03-Schlüsselverlustwiederherstellung.**

## 20.10.2026 – GH03: Eine PGPony-Offline-Papierkopie im Safe bestätigt

| GH03-Kriterium | Status | Abgrenzung |
| --- | --- | --- |
| Neuer PGPony-Private-Key als Papier-Wiederherstellungskopie geschützt verwahrt | **BETREIBERBESTÄTIGT – 1 Kopie** | Papierausdruck laut Betreiber im eigenen Safe; private Inhalte, Bilder und Orte weder gesehen noch gespeichert |
| Vollständigkeit / Importfähigkeit der Papierkopie | **OFFEN** | Papier allein ist kein Restorebeweis |
| Restore ausschließlich aus der **Papierkopie** und synthetisches GPG-Decrypt | **OFFEN** | Bisheriger iPad-Decrypt erfolgte nach AirDrop-Import, nicht belegter Papier-Import |
| Zweite eigenständig und getrennt geschützte Offline-Privatkopie | **OFFEN** | iPhone/iPad oder ältere AgePony-Papierkopie zählt nicht |
| Notfallpassphrase getrennt wiederherstellbar | **OFFEN** | Keine Passphrase oder sensible Lageradresse erfragen |
| GH03 insgesamt | **NICHT FORMAL ABGENOMMEN** | Zuerst Wiederherstellung aus Papier/gesichertem Medium und zwei separate Kopien |
| Produktive GH05–08, GitHub Backup-Releases, automatische Jobs, V1 R01–18 | **BLOCKIERT** | Kein neuer Datenzugriff oder Release freigegeben |

Diese Papierkopie ist ein **neuer echter Offline-Fortschritt**, aber kein unabhängig beobachteter Restore oder ein zweites geschütztes Ausfallgebiet.

## 20.10.2026 – GH03 PGPony-Papierkopie #1 wiederhergestellt

| Abnahmekriterium | Status | Nachweisgrenze |
| --- | --- | --- |
| Neue PGPony-Private-Key-Kopie #1 physisch im Safe | **OWNER-BESTÄTIGT** | Vorherige Aussage über Papierausdruck im Safe |
| Private Key **direkt aus dem Papierausdruck** rekonstruiert/importiert | **PASS laut Betreiber** | Antwort „Ja habe ich es hat geklappt“ auf eindeutige Papier-Restore-Frage; kein Secret an Chat gesendet |
| Extern verschlüsselte synthetische OrgaVela-Testdatei mit Papier-Restore-Schlüssel entschlüsselt | **PASS laut Betreiber** | Eigenständige Betreiberbestätigung, nicht vom Assistenten selbst beobachtet |
| Vollständig isolierter Keyring / erneut identischer FPR beim Papier-Restore | **nicht separat belegt** | Nur früherer iPad-Fingerprint-Abgleich bestätigt |
| Zweite unabhängig geschützt und anderswo aufbewahrte neue PGPony-Offline-Kopie #2 | **OFFEN** | Erster Safe und Geräte allein decken keinen Verlust des Safes ab |
| Wiederherstellung aus echter Copy #2 | **OFFEN** | Noch keine Kopie oder Test |
| Passphrase-Notfallverfügbarkeit getrennt von Schlüsselkopien | **OFFEN** | Kein Zugriff auf Passphrase nötig/erwünscht |
| Formale GH03-Abnahme / produktive GH05–08 | **BLOCKIERT** | Zuerst Copy #2 und Notfall-Wiederherstellung belegen |

**Evidenzlevel:** Operator-Attestation für Papier-Recovery aus Copy #1, aber noch kein vollständig abgesicherter, kopie-2-unabhängiger Disaster-Recovery-Test. Früherer synthetischer CircleCI-PASS bleibt gültig; V1-Gates nicht neu abgenommen.

## 20.10.2026 – GH03 PGPony owner-controlled key recovery

| Kriterium | Ergebnis | Evidenz |
| --- | --- | --- |
| Zweite eigene private PGPony-Offlinepapierkopie an unabhängig geschütztem Ort | **PASS (Owner-Attestation)** | Nutzer bestätigt zusätzliche Kopie und erfolgreiche Wiederherstellung aus dieser |
| Wiederherstellung aus Papierkopie #1 | **PASS (Owner-Attestation)** | Voriger Test: PGPony-Key direkt aus Papier importiert, synthetisches GPG-Fixture entschlüsselt |
| Wiederherstellung aus Papierkopie #2 | **PASS (Owner-Attestation)** | Betreiber bestätigt auch beim zweiten Papier wieder erfolgreiche Wiederherstellung |
| Passphrase im Notfall offline und auf Zweitgerät verfügbar | **PASS (Owner-Attestation)** | Nur erfolgreiche Sicherungsmethode, keine Passphrase empfangen |
| GH03 Besitzer-Key-Recovery insgesamt | **PASS, owner-attested** | Keine externe physische Auditierung, kein eigens verifizierter Copy2-Paket-SHA/isolierter Keyring |
| PGP-03 Quell-/Ciphertext-only Writer-Grenze | **NEU VORZUBEREITEN, NICHT ABGENOMMEN** | Default-OFF synthetische Zwei-Job-Pipeline |
| PGP-04 privater immutable Release und echter GH05–08-Vollrestore | **BLOCKIERT** | Keine Token, keine reale Quelle, keine isolierte reale Wiederherstellung |
| M0–M7, P4, R01–R18 | **0/8, 0/4, 0/18** | Historische Codingzählung 62/71 bleibt unverändert |

**GH03 ist eine Key-Recovery-Teilabnahme, nicht die O03-Live-Backup- oder V1-Abnahme.**

## 20.10.2026 – GH03 Key-Recovery-Teilabnahme und PGP03 Ciphertext-only Source/Writer-Vorprüfung

| Nachweis | Status | Quelle / Grenze |
| --- | --- | --- |
| Zwei getrennt geschützte PGPony-Offlinepapierkopien | **PASS (Betreiberbestätigung)** | Besitzer bestätigt zweite Kopie nach Frage zur getrennten sicheren Verwahrung; genaue Orte nicht gespeichert |
| Private-Key-Wiederherstellung aus zweiter Papierkopie | **PASS (Betreiberbestätigung)** | Nutzer bestätigt wiederhergestellten Key und erfolgreiche Entschlüsselung auch aus Kopie 2; kein externer Audit |
| Passphrase offline und auf zweitem Gerät | **PASS (Betreiberbestätigung)** | Keine private Passphrase empfangen |
| **GH03 OpenPGP Key-Recovery-Teil-Gate** | **PASS, owner-attested** | Öffentlicher PGP-FPR/cv25519 geprüft, iPhone/iPad/Papier 1+2, letzte no-secret CircleCI-QA grün; keine Real-Backup-Abnahme |
| PGP03 nur künstliche SQL-/WordPress-Daten vor Transport OpenPGP-verschlüsseln | **LOKAL PASS** | `scripts/ci/orgavela-openpgp-synthetic-handoff.sh` SHA `a20169ada8779c4c394b48d26e5ffc93e6756f2f` |
| Writer erhält ausschließlich Ciphertext, SHA-256-Sidecar und bereinigtes Manifest | **LOKAL PASS** | SHA-256 + Recipient-Key-ID + Manifest + drei-Dateien-Invariante geprüft, ohne Private Key oder Token |
| PGP03 Negativtests | **9/9 LOKAL PASS** | `scripts/ci/test-orgavela-openpgp-synthetic-handoff.sh` SHA `b23fb8253813ee7a18765e30641eddb64abcfb2d` |
| PGP03 No-Secret CircleCI Workflow und getrennte Source-/Writer-Jobs | **CODE DA / PROVIDER-RUN OFFEN** | default-off `enable_pgpony_ciphertext_handoff`; kein Context, nur Featurebranch |
| PGP04 privater synthetischer immutable GitHub Release | **BLOCKIERT** | Separates Token-/Branchschutz-/Releasesicherheits-Gate fehlt |
| Produktive GH05–08 mit realer DB und echtem unabhängigem Restore | **BLOCKIERT** | GH03 allein genügt ausdrücklich nicht |
| V1 M0–M7 / P4 / R01–R18 | **0/8 · 0/4 · 0/18** | Historisch 62/71 Coding bleibt unverändert |

**Aussagegrenze:** CI-Proof zu PGP03 ist **noch nicht** bestanden. Frühere grüne CI gehört nur zur älteren Public-Key-/Synthetik-Einzeljob-QA, nicht zum neuen Zwei-Job-Handoff.

## 20.10.2026 – PGP03 tatsächliche No-Secret-CircleCI Zwei-Job-Abnahme (18:57)

| Test/Blocker | Status | Beleg |
| --- | --- | --- |
| GitHub Source SHA | **ABGEGLICHEN** | PR #25 Head `4eac786c3868dc4e79f1cf1dd16dc11b93512ff8` entspricht CircleCI-UI `4eac786` |
| Synthetic Source OpenPGP | **PASS** | `pgpony-handoff-source`, ca. 21 s, `PGPONY_HANDOFF_CI_SOURCE=PASS` |
| Keine Klartextbestandteile im Handoff | **PASS** | `PGPONY_WORKSPACE_PLAINTEXT=NONE`, nur drei erlaubte Workspace-Dateien laut eingefrorenem CI-Quellcode |
| CircleCI-Workspace-Übergabe | **PASS** | 1,7 KiB hochgeladen, Writer hat Workspace erfolgreich eingebunden |
| Synthetic Writer ohne Key/Token | **PASS** | `pgpony-handoff-writer`, ca. 12 s, `PGPONY_HANDOFF_CI_WRITER=PASS` |
| Integritäts-/Empfänger-/Manifest-Checks und fail-closed | **PASS, 9/9** | `PASS: OpenPGP ciphertext-only producer/writer and 9 fail-closed regressions` |
| Keine echte GitHub-Release-Veröffentlichung | **bestätigt aus Script/Log** | `PGPONY_RELEASE_UPLOAD=DISABLED` |
| **PGP03 auf echte CircleCI-Zwei-Job-Grenze** | **PASS** | Zwei grüne Provider-Jobs/Workflow, Betreiber-Screenshots 18:57, ~37 s |
| GH03 Owner-Key-Recovery | **PASS, Betreiberbestätigung** | Beide PGPony-Papierkopien und Wiederherstellungen, getrennt passphrase-wiederbeschaffbar; keine externe physische Prüfung |
| PGP04 Secret-/Branchschutz für neuen verschlüsselten Remote-Release | **BLOCKIERT** | Aktueller PR #25 Featurebranch per GitHub API `protected=false`, Branchfilter schützt kein GH_TOKEN |
| PGP04 neuer PGPony Ciphertext-Release in privatem `janjrre/orgavela-backups` | **NICHT AUSGEFÜHRT** | Nur ältere GH04-GPG- oder GH06-age-Synthetik-Ergebnisse bestehen separat |
| Reale GH05–08, produktiver isolierter Restore, automatischer Plan | **BLOCKIERT** | Kein echter Capture/Remote Recovery/Release-Gate |
| M0–M7 / P4 / Release-R01–R18 formal | **0/8 · 0/4 · 0/18** | Codinghistorie 62/71 unverändert |

PGP03-PASS ist kein GH04-PASS, kein produktiver Backup-PASS und keine formale V1-Gesamtabnahme.

## 20.10.2026 – PGP04 lokale Fake-GitHub-API-Testmatrix (KEINE Remote-Abnahme)

| Prüfung | Status | Evidenzgrenze |
|---|---|---|
| GitHub-Schutzregel für PGP04, im richtigen Quellrepository, trifft 1 Branch | BETREIBER-UI BESTÄTIGT | Screenshot 21:32; endgültiger Branch-Lock noch AUS |
| Exakt mit GitHub-Blob identischer Writer, Shell-Syntax | LOKAL PASS | git hash-object = 5ecb211eb7f856ff1d676201ee2a5ede55d11fe2; bash -n Exit 0 |
| Nur synthetische künstliche Datei mit PGPony PUBLIC-Key GnuPG-verschlüsselt | LOKAL PASS | temporäres GnuPG-Homedir; kein privater Owner-Key |
| Fake-API: Draft erstellen, drei Assets übertragen/lesen, immutable=true | LOKAL PASS | Nur simulierter GitHub-Endpunkt, kein echter Release |
| Fake-API: öffentliches Repo, 403/500, doppeltes Release/Tag | LOKAL PASS | jeweils fail-closed |
| Fake-API: Asset fehlt, Readback beschädigt, immutable=false | LOKAL PASS | jeweils fail-closed |
| Fehlende Freigabe, falscher SHA, falscher Branch, fehlender Fake-Token | LOKAL PASS | jeweils vor Remote-Aufruf abgewiesen |
| Lokale Mock-Tests | 13/13 erwartete Ergebnisse | Python Exit 0; Test-Harness lokal, NICHT in GitHub/CI committed oder ausgeführt |
| CircleCI Default-OFF, Source ohne Context, Writer-Context und Branchfilter | STATISCH GESICHTET | GitHub Blob d430033f001d13bb83bb1b086b336b2dd43c522b, noch kein CircleCI-Config-Lint |
| Remote-PGPony-GitHub-Release und Besitzer-Restore | NICHT AUSGEFÜHRT | Kein Token, kein echter Upload |
| Finaler Branch-Lock, SHA-Attestierung, restriktiver CircleCI Context | BLOCKIERT | Vor Secretzugriff nachweisen |

**Grenze:** Lokale 13/13-Mock-QA ist nicht PGP04-E2E-Release-Abnahme. Versuchte GitHub-Erstellung einer versionierten Fake-API-Testdatei wurde durch Sicherheitskontrolle blockiert; deshalb Reproduzierbarkeit im Repository noch nicht belegt. Historisches PGP03 bleibt PASS, M0–M7 0/8, P4 0/4 und R01–18 0/18 unverändert.

## 20.10.2026, 22:33 – PGP04 Konfigurations-Teilgate

| Prüfung | Status | Beleg | 
|---|---|---|
| Privates GitHub-Zielrepo: Release Immutability in Settings → General | **PASS, Betreiber-Screenshot** | `orgavela-backups`, Checkbox aktiviert; kein neuer PGP04-Release erzeugt |
| PGP04 CircleCI-Writer erst nach manueller Freigabe | **Code vorhanden, statisch geprüft** | Commit `6c8431fb1dab860ad52c77c1d88ca7eefd23ca64`, Approval-Job vor Writer; Provider-Test noch offen |
| Reale GitHub-Release-API-Immutability und verschlüsselter Betreiber-Restore | **OFFEN** | Kein PGP04-Runtime-Lauf, kein Token |
| GitHub finaler Branch-Lock und CircleCI Context-Restriktionen | **OFFEN** | Noch kein Freigabenachweis |

Keine neue formale V1-Abnahme; M 0/8, P4 0/4, R 0/18.

## 20.10.2026, 22:48 – PGP04 Restriktions- und Publisher-Nachtrag

| Prüfung | Status | Beleg und Abgrenzung |
|---|---|---|
| CircleCI Context `orgavela-pgp04-release-only` existiert | **Betreiber-UI bestätigt** | Screenshot Organization Settings → Contexts |
| Nur Projekt `Projekt-Wordpress-Plugin` | **Betreiber-UI bestätigt** | Exactly one project restriction |
| Expression: exakter PGP04-Branch, Name, Owner, SSH off, API-config-source off | **Betreiber-UI bestätigt** | Ein gespeicherter Ausdruck sichtbar; noch kein Runtime-Zugriffstest |
| Context-Variablen leer | **Betreiber-UI bestätigt** | Keine Variablen gelistet; GitHub-Token NICHT konfiguriert |
| Group Restriction `All members` | **Sichtbar** | Kein Einzelgruppen-Nachweis; Organisationsmitgliedschaft nicht unabhängig ermittelt |
| Publisher-Manifest strikt, nach veröffentlichtem Release alle 3 Remoteassets binär erneut gelesen | **CODE COMMITTED** | Commit `71c0d6cc3dc9ec619b13096b350d1670bee7ef46`, Blob `6e207aefa7edcb9e9302efccacd88fa0c633287a` |
| Exakt identische neue Publisher-Version, Syntax + Mock-API-Regressionsfälle | **LOKAL 14/14 PASS** | `git hash-object` stimmt, Python Exit 0, Fake-API-Erfolg/Fehler; KEINE Provider-Abnahme |
| CircleCI Branch-Lock, Runtime-/Token-Permissions- und GitHub-Release-E2E | **OFFEN** | Vor Secret-Nutzung vollständig nachweisen |

Die lokale Fake-GitHub-Testmatrix darf **nicht** als produktiver oder echter GitHub-Release-PASS gewertet werden. M0–M7 0/8, P4 0/4, R01–R18 0/18 unverändert.

## 20.10.2026, 23:02 – PGP04 Branch-Freeze-Nachweis

| Kontrolle | Ergebnis | Abgrenzung |
|---|---|---|
| Lock branch eingeschaltet | **UI-Screenshot sichtbar** | Edit-Ansicht; Speicherung nicht bestätigt |
| Do not allow bypassing | **UI-Screenshot sichtbar** | Aktiv |
| Allow force pushes | **UI-Screenshot sichtbar** | Deaktiviert |
| Allow deletions | **Zuletzt deaktiviert; aktuell nicht im Bild** | Nochmals gespeicherte Regel prüfen |
| Regel gilt für exakt einen benannten PGP04-Branch | **UI-Screenshot sichtbar** | `Applies to 1 branch` |
| Branch-Code-SHA konstant | **GitHub-Connector geprüft** | `62c9524f09d9f0ad0e4eea0648cb1be14b051f0f`, compare = identical |
| GitHub `protected=true`, Lock serverseitig wirksam | **NICHT API-BESTÄTIGT** | Kein Branchschutz-Leseendpunkt der GitHub-App; Edit-Screenshot ohne Save-Erfolg |
| CircleCI-/GitHub-Synthetic-E2E | **OFFEN** | Kein Token oder Provider-Lauf |

Keine Hochstufung der V1-Abnahme. Stand M0–M7 0/8, P4 0/4, R01–R18 0/18.

## 20.10.2026 – PGP04 Lock Save und SHA-Rückprüfung

| Nachweis | Ergebnis | Einschränkung |
|---|---|---|
| GitHub Lock branch und No-Bypass sichtbar + Save changes gedrückt | **Betreiberbestätigt** | Screenshot und anschließende explizite Save-Bestätigung; API-Wirksamkeit nicht selbst überprüft |
| PGP04-Testbranch-HEAD unverändert | **GitHub-Connector PASS** | `compare_commits` gegen `62c9524f09d9f0ad0e4eea0648cb1be14b051f0f`: identical, 0/0 |
| PGP04 config/publisher Inhalte unverändert | **GitHub-Connector PASS** | Blobs `3cd7188ef14d7f5ad830b68f1a609251984b1d4e` / `6e207aefa7edcb9e9302efccacd88fa0c633287a` |
| CircleCI Context SHA-Variable / Runtime-Access | **OFFEN** | Context UI-beschränkt, `PGP04_APPROVED_SHA` noch nicht gesetzt |
| PGP04 echter synthetischer GitHub Release / Restore | **OFFEN** | Keine Token und kein CI-Run |

V1-Zähler unverändert: historische Implementierung 62/71, M0–M7 0/8, P4 0/4, R01–R18 0/18.

## 20.10.2026, 23:11 – CircleCI Context SHA und erstmaliger no-token Source-Run

| Prüfung | Stand | Evidenz |
|---|---|---|
| `PGP04_APPROVED_SHA` in dediziertem Context vorhanden | **BETREIBER-UI bestätigt** | Screenshot 23:11, Maskierung endet `1f0f`; vollständiger Wert nicht unabhängig lesbar |
| Im Context kein `GH_TOKEN` | **BETREIBER-UI bestätigt** | einzige Variable `PGP04_APPROVED_SHA` gelistet |
| Lock-Branch HEAD nachfolgend unverändert | **GitHub-Connector PASS** | `62c9524f09d9f0ad0e4eea0648cb1be14b051f0f`, 0 SHA-Abweichungen |
| CircleCI PGP04 Source/Approval/Writer-Logik | **CODE STATISCH BESTÄTIGT** | Manuelles `enable_pgp04_synthetic_release=true`, Producer ohne Context, Writer nur nach explizitem Approval |
| Ausführung `pgp04-synthetic-source` | **NOCH NICHT AUSGEFÜHRT** | Betreiber muss bewusst manuell auslösen und Provider-Logs liefern |
| Approval-Job / Writer | **NICHT GENEHMIGT** | kein Token; manuellen CircleCI-Hold keinesfalls freigeben |
| PGP04 Remote Release / Besitzer-iPad-Restore | **NOCH NICHT AUSGEFÜHRT** | weiter strikt getrennte Freigabe-/Secret-Gates |

W0/O03 partial und V1 formale Zahlen unverändert: M0–M7 0/8, P4 0/4, Release R01–18 0/18.
