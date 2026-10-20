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
| GH06-AGE-06 | Protected GH06 Testbranch, auf Projekt/Branch beschränkter neuer Writer-Context und kurzlebiger Einzelrepo-Token | **TEILSTATUS 20.10.: Testbranch `test/orgavela-age-release-o03` ANGELEGT, aber GitHub-API `protected=false` – HARTER STOPP**. Kein CircleCI-Context, kein Token, kein GH06 Remote-Job und kein Upload; Einstellungen müssen erst durch Betreiber geschützt/abgenommen werden. |
| GH06-AGE-07 | Produktiver age Writer nach Owner-GH03-Recovery, echter GH05 Konsistenz, eigenem isolierten GH07-Restore | **BLOCKIERT / NICHT ABGENOMMEN** |

Keiner dieser Vorbereitungspunkte erhöht die historischen 62/71-Coding-Pakete oder M0–M7 0/8, P4 0/4, R01–18 0/18 formalen Gates. Historische GH05-CircleCI #188 ist ein anderer Quellcommit/Job. [GH-06-Runbook](../operations/W0_O03_GH06_AGE_RELEASE_PREPARATION_2026-10-20.md).


**GH-06 Offline-Abnahme 20.10.2026 – CircleCI #209:** [GH06-Job #6](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/6) auf GitHub-Commit `6f885c585557661e3250fc477e2464779cf01adc` vom Anbieterstatus `ci/circleci: gh06-age-release-offline-qa=success` unabhängig bestätigt. Benutzer-Screenshot zeigt **13 Tests, 0 Fehler, OK**. GH06-AGE-01/02/05 sind als **Offline-Vorprüfung** bestanden. GH06-AGE-03/04 (echter Remote-GitHub Release), GH06-AGE-06 (neue Branch-/Context-/Token-Gates) und GH06-AGE-07 (Produktivbetrieb) bleiben **offen**. Keine Änderung von V1-Zählwerten: 62/71, M0–M7 0/8, P4 0/4, R01–R18 0/18.
