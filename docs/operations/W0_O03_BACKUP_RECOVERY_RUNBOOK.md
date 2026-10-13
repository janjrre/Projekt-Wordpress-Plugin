> **Aktuelle Speicherstrategie seit 13.10.2026:** Aus diesem älteren Backup-/Recovery-Runbook gelten die generellen Restore-/Isolations-/Konsistenzvorgaben weiter, aber **nicht mehr** die Google-Drive- oder Betreiber-PC-/Cloud-Auswahl und deren alte CI-Transportanweisungen. Der Betreiber wünscht **nur IONOS-Vertragssicherungen plus verschlüsselte Release-Assets in einem gesonderten privaten GitHub-Backup-Repository**. Neues verbindliches Setup: [`W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md`](W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md). Die frühere Strategie bleibt unten lediglich als chronologische Entwicklungsdokumentation. **O03 weiter offen bis zur tatsächlichen isolierten Wiederherstellung.**

---

# W0 / O03 – OrgaVela: Backup-Inventar, private Sicherung und isolierter Restore

**Status 12.10.2026: O03 TEILWEISE erfüllt, NICHT abgenommen.** OrgaVela unter `https://orgavela.com`, WordPress mit UOP Core Alpha.2; strikt getrennt von Dreamloud. Dies ist ein Betriebsrunbook und keine Freigabe für produktive Migrationen.

## 1. Bisher tatsächlich verifizierte Evidenz

| Nachweis | Quelle | Ergebnis |
|---|---|---|
| SSH, WP-CLI und richtige OrgaVela-Site | [Actions #38264254555](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555) | SUCCESS, installierte Alpha.2 |
| 29 UOP-Tabellen, gültige Standardorganisation/Versionen | [Actions #38264354774](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264354774) | SUCCESS, alle Statusflags `good` |
| Read-only Größen-/Werkzeug-/Freiplatz-Preflight | [Actions #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599) | SUCCESS; WP-Dateibestand **100–500 MiB**, gesamte WP-Datenbank **unter 25 MiB**, freier Hosting-Speicher **über 5 GiB**. Die Kategorien sind bewusst grob, **keine exakt gemessenen Backup-Dateigrößen** |
| Werkzeuge auf OrgaVela-SSH-Webspace | [Actions #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599) | `mysqldump`, `mariadb-dump`, `mysql`, `tar`, `gzip`, `sha256sum` verfügbar; SQL-Speichermetadaten lesbar |
| Synthetische SQL+Datei-Rücksicherung (getrennte MariaDB 11.8) | [Actions #38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371) | SUCCESS: Archiv/Dump erzeugt, SHA-256 validiert, in **eigene CI-Test-DB/-Verzeichnisse** rückgesichert; 2 synthetische Zeilen, Index und Dateien verifiziert |
| IONOS-Backupzeitpunkte und Restorepunkte **dieses** Vertrags | Noch kein Konto-/Panel-Beleg | **UNBEKANNT** |
| Dauerhaft privat abgelegtes echtes OrgaVela-DB+Datei-Backup | Keine Sicherung gespeichert oder exportiert | **FEHLT** |
| Isolierter Restore genau dieser echten OrgaVela-Sicherung | Kein Realbackup-Restore | **FEHLT** |

Die vorstehenden grünen CI-Läufe sind echte Prüfungen – aber ein synthetischer Testlauf in GitHub ist **keine** Wiederherstellung eines realen Backups und darf nicht als O03-Abnahme bezeichnet werden. Keine Daten von OrgaVela wurden für den synthetischen Test in GitHub übertragen.

## 2. IONOS-Anbietergrenzen (allgemein, nicht kontoverifiziert)

Offizielle IONOS-Webhosting-Hilfe beschreibt **tägliche automatische Systemsicherungen mit bis zu 14 Tagen Aufbewahrung**, getrennt von zusätzlich manuell vorzunehmenden Website-Datei- und Datenbank-Sicherungen. **Ob** solche Wiederherstellungspunkte für den konkreten OrgaVela-Tarif vorliegen und von welchem Zeitpunkt, ist über den SSH-Webspace allein **nicht** feststellbar. Keine Aussagen über konkrete tägliche Wiederherstellungspunkte ohne sichtbaren IONOS-Vertragsbeleg.

- [IONOS Webspace und Datenbanken manuell sichern](https://www.ionos.de/hilfe/hosting/dateien-sichern-wiederherstellen/webspace-und-datenbanken-manuell-sichern-backups-erstellen/): allgemeine 14-Tage-Systembackup-Information und getrennte Daten-/Dateisicherung
- [IONOS Datenbankexport mit phpMyAdmin](https://www.ionos.de/hilfe/hosting/mysql-datenbank-sichern-wiederherstellen/datenbanken-sichern-mit-phpmyadmin/): Export als SQL, keine Dateisicherung; IONOS empfiehlt bei DB über 1 GB SSH wegen Timeouts
- [IONOS MySQL/MariaDB-Backup und -Restore über SSH](https://www.ionos.de/hilfe/hosting/mysql-datenbank-sichern-wiederherstellen/sichern-und-wiederherstellen-von-mysqlmariadb-datenbanken-ueber-ssh/): `mysqldump` / `mysql` auf Hosting-Shell
- [IONOS phpMyAdmin-Import](https://www.ionos.de/hilfe/hosting/mysql-datenbank-sichern-wiederherstellen/wiederherstellen-von-mysql/mariadb-datenbanken-mit-ionos-phpmyadmin/): **nicht** für Rücksicherung auf die laufende OrgaVela-Datenbank bei O03 benutzen

## 3. Checkliste für eine echte unabhängige OrgaVela-Sicherung

**A. Provider-Inventar:** Im authentifizierten IONOS-Konto ausschließlich den **OrgaVela-Vertrag** wählen, Wiederherstellungsansicht für Webspace **und** Datenbank getrennt öffnen, ohne `Wiederherstellen` auszuführen. Nur vorhanden/nicht vorhanden, Zeitpunkt, Produktart und ggf. Lösch-/Aufbewahrungsfenster dokumentieren. Kein Login, Kundennummer, genauer Datenbankname oder Serverpfad in öffentliche GitHub-Issues.

**B. Private Backup-Ablage:** Ein bestätigtes **zugriffsbeschränktes und nicht über das Web öffentlich erreichbares** Ziel festlegen. Dauerhaftes Backup **nicht** in `/public`, `wp-content/uploads` oder das öffentliche GitHub-Repository schreiben. Ein Backup auf demselben Hosting-Webspace ist kein ausreichend unabhängiges Offsite-Backup; Verfügbarkeit und Kapazität eines privaten externen Speichers vor Verwendung bestätigen. Vertrauliche Sicherungen ggf. vor Offsite-Übertragung mit einem außerhalb des Backups verwahrten Betreiber-Schlüssel verschlüsseln. **Kein** neues kostenpflichtiges Speicherprodukt ohne separate Zustimmung.

**C. Quelle eindeutig identifizieren:** Vor jeder Sicherung `https://orgavela.com` per WP-CLI, aktives Plugin, die Quelle/Version und das WordPress-Root bestätigen. Schützen von `wp-config.php` und sonstigen Geheimnissen ist obligatorisch. Niemals das WordPress-Root des getrennten Dreamloud-Vertrags verwenden.

**D. Konsistente private DB-Sicherung:** `mysqldump`/`mariadb-dump` nur gegen die vorab bestätigte OrgaVela-Datenbank und mit geprüften Optionen für konsistenten Snapshot (`--single-transaction` soweit Tabellen transactional sind, `--no-tablespaces`). Keine Datenbankpasswörter in Prozesskommandozeilen, Repository, Shell-History oder öffentlichen Logs. Bei Änderungen während des Exports Dateikonsistenz abstimmen; für einen vollständigen transaktional konsistenten WordPress-Snapshot ggf. Schreibpause/Wartungsfenster, keine ungeplante produktive Aktivierung. Ohne sicheren privaten Ablageort **keinen Live-Dump durchführen**.

**E. Datei-Sicherung:** Gesamtes relevante WordPress-Installationsverzeichnis inklusive Plugins, Themes, Uploads, versteckten WP-/Serverkonfigurationsdateien und ggf. außerhalb des Document Root hinterlegten zugehörigen Konfigurationen privat sichern. DB-Dateistand und Datei-Zeitpunkt gemeinsam dokumentieren. Bei `tar` auf Symlinks und Archivpfade achten; die später rückgesicherten Dateien müssen zum Datenbankstand passen. Die Hosting-Kontokonfiguration (z. B. externer Cron, PHP-Version, Mail-/DNS-Einstellungen) kann außerhalb des Dateisystems liegen und ist **separat** zu inventarisieren.

**F. Nachweis:** Hash-Prüfung (SHA-256) der **privaten** Datei- und SQL-Archive, Größe, UTC-Zeitpunkt, Version/DB-Prefix, Lesezugriff und Wiederherstellbarkeit kontrollieren; nur die Aussage „Hashes geprüft: ja/nein“ und nachweisbaren Run-/Signoff-Code öffentlich protokollieren. Keine tatsächlichen Dateninhalte, Hashes von Geheimdateien, Pfade oder Zugangsdaten in den öffentlichen Bericht.

## 4. Sicherer echter Restore ausschließlich in isolierter Umgebung

1. Separaten, **nicht öffentlich erreichbaren**, kontrollierten Test-Host und **eigene** leere MariaDB-Instanz einrichten; sicherstellen, dass weder DNS noch lokale Konfig auf `orgavela.com` noch auf die OrgaVela-Live-DB zeigen.
2. Netzwerk-Ausgänge, SMTP-Mail, Webhooks, externe Auth-, Queue-/Cron-Aufrufe für die Restore-Probe unterbinden. Echte Teilnehmer-/WP-Kontodaten dürfen nicht in öffentliche Entwicklungssysteme oder GitHub-Artefakte geraten. Gegebenenfalls Daten vor einer Testnutzung in einer privaten geschützten Umgebung anonymisieren.
3. Vor dem Import **statische Zielidentitätsprüfungen**: Test-Datenbankname, Test-Hostname, Ziel-Document-Root und Restore-Verzeichnis unterscheiden sich nachweislich von Produktivwerten. Jeder Treffer auf die Produktionszielidentität: **sofortiger Abbruch ohne Import**.
4. Verifizierten privaten SQL-Dump **nur in die isolierte Testdatenbank** importieren und Dateisicherung ausschließlich **im isolierten Clone** entpacken. Die Live-Datenbank und Live-Dateien werden unter keinen Umständen überschrieben.
5. Plugin-Version Alpha.2, WordPress-/PHP-/MariaDB-Kompatibilität, UOP-`schema`-Status (28+1 Tabellen/Indizes und Default-Org), WordPress-Installation, interne Login-/REST-Tests **mit synthetischen Identitäten** vergleichen.
6. Prüfsummen, Importfehler, Zeitbedarf und Abweichungen dokumentieren, dann isoliertes Testmaterial vertraulich nach Retention-Regeln löschen. **Erst nach durchgeführtem Test O03-Abnahme unterschreiben**.

## 5. Bereits implementierter ungefährlicher Probeablauf

- [Read-only Bestandsprüfung](../../.github/workflows/orgavela-readonly-audit.yml): `backup-preflight` liest auf OrgaVela nur Tool-Verfügbarkeit und gerundete Größen-/Freispeicherbereiche; keine SQL-Daten oder WordPress-Dateien in GitHub übertragen. [Skript](../../scripts/ci/orgavela-readonly-audit.sh)
- [Synthetischer Restore-CI](../../.github/workflows/orgavela-restore-rehearsal.yml): startet eine **disposable MariaDB 11.8** mit **explizit synthetischen Fixtures**, dump+gzip+SHA-256 und separatem Import/Dateirestore. [Skript](../../scripts/ci/orgavela-synthetic-restore.sh)
- Beide prüfen Prinzipien, **nicht die Vollständigkeit oder Wiederherstellbarkeit eines echten Providerbackups**. GitHub Actions hält keine echten Backupartefakte bereit.

## 6. O03-Gate und nächste tatsächlich notwendige Belege

| Gate | Status 12.10.2026 |
|---|---|
| O03-G1 Schreib-/Lese- und Kapazitätsplanung der Hosting-Sicherung | **geprüft** (Live-Preflight #38267570599) |
| O03-G2 Exakte Provider-Backup-Punkte im OrgaVela-Vertrag | **offen** (IONOS-Kontoverwaltung, nicht SSH) |
| O03-G3 Vollständiges, unabhängiges, privat gespeichertes DB+Datei-Backup | **offen** |
| O03-G4 Privater isolierter Restore mit echtem Sicherungsstand, Hash-/Versions-/Schema-Vergleich und Dauer | **offen** |
| O03-G5 Nachweisbare Recovery-Verantwortung und Wiederholung | **offen** (synthetischer CI-Smoketest läuft bereits und kann als Hilfsbeleg dienen) |

**Stop-Regel:** Bis G2–G4 tatsächlich belegt sind, **keine produktiven UOP-Migrationen, Upgrades, Restore-Klicks oder automatisierten echten Teilnehmerprozesse**. Der Betreiber muss keine neuen Zugangsdaten in Chat/GitHub veröffentlichen, da die bestehenden OrgaVela-SSH-Secrets ausschließlich für enge Metadaten-Audits weitergenutzt werden.


## 7. Konkreter nächster Durchlauf – minimaler Betreiberaufwand, keine Vertragskosten (Entscheidungsstand 12.10.2026)

**Empfohlener Ablauf:** Die vorhandene OrgaVela-SSH-/WP-CLI-Verbindung erzeugt unter einem eigenen, künftig zu entwickelnden **streng reviewten** GitHub-Actions-Workflow eine **konsistente vollständige WordPress-Datei- und SQL-Sicherung**. Der Workflow soll die Nutzdaten **vor jeder Veröffentlichung als Actions-Artefakt mit Public-Key-Verschlüsselung (z. B. `age`) verschlüsseln**. Der Betreiber lädt das **ausschließlich verschlüsselte** Ergebnis herunter, bewahrt es auf **einem eigenen Rechner und idealerweise zusätzlich einer externen SSD** außerhalb des IONOS-Webspaces auf und behält den privaten Entschlüsselungsschlüssel ausschließlich bei sich. Ein vorhandener eigener privater Offsite-Cloudspeicher ist nach gesonderter Wahl ebenfalls möglich; keine kostenpflichtige Neubuchung. In einer zweiten Phase wird genau dieses Backup in einem **offline-/netzwerkisolierten lokalen Container/VM-Klon** restauriert, nicht in OrgaVela.

**Grundsatz:** Das Repository ist öffentlich. GitHub beschreibt, dass Artefakte für Repository-Lesende abrufbar sind und die Aufbewahrung bei öffentlichen Repositories standardmäßig 90 Tage beträgt und höchstens 90 Tage erreichen kann. Auch verschlüsselte GitHub-Artefakte dürfen daher **nur temporärer Transfer**, **nicht die einzige Langzeitkopie**, sein. Quellen: [GitHub Artefakte herunterladen](https://docs.github.com/de/actions/how-tos/manage-workflow-runs/download-workflow-artifacts), [GitHub Aufbewahrung](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/enabling-features-for-a-repository/managing-github-actions-settings-for-a-repository). **Nie unverschlüsselte Dumps, `wp-config.php`, personenbezogene WP-Daten oder den privaten Key in Commit, Issue, Logs oder Artefakt schreiben.**

### G0 – zwei Betreiberentscheidungen (noch nicht vorliegend)

1. **Dauerhafter Speicherort festlegen:** empfohlene kostenlose Standardvariante = eigener PC + verschlüsselte externe Festplatte/SSD. Alternative: bereits vorhandene private Cloudablage mit explizit vereinbartem Zugriffs-/Löschkonzept. Genug Kapazität für mindestens mehrere Versionen einplanen; Live-Preflight zeigt 100–500 MiB WP-Dateien und unter 25 MiB DB, aber gepackte Sicherungsgröße ist noch unbekannt.
2. **Empfänger-Schlüsselpaar beim Betreiber erstellen:** z. B. über `age-keygen` auf einem Betreibergerät. Privater Schlüssel **ausschließlich lokal**, mit getrennter sicherer Recovery-Kopie; in GitHub später **nur der öffentliche Empfängerschlüssel** als nicht geheime Variable/konfigurierter Wert. **Nicht** im Chat nach Passwort, privatem Schlüssel, 2FA oder `wp-config.php` fragen. Der Betreiber muss den Entschlüsselungstest lokal nachweisen; sonst ist ein erfolgreich erstelltes verschlüsseltes Backup unbrauchbar.

### G1 – sichere Implementierung und Test, bevor echte Daten fließen

- Backup-Skript in separatem neuen GitHub-Workflow auf `main` **nur manuell/mit explizitem Backup-Request** ausführen; **kein** automatisches Triggern bei normalen Commits, keine durch frei formatierten Inputs injizierbaren Shell-Befehle. Bestehende `orgavela-ionos`-Secrets nur zur SSH-Identifizierung verwenden.
- Exakte WordPress-Zielidentität `orgavela.com`, aktiver Plugin-/WP-Stand, zulässiges Root und IONOS-Hostkey **vor dem Lesen** prüfen. Ausschließlich OrgaVela-WordPress und die dazugehörige DB; keine Dreamloud-URLs, -Secrets oder -Repos.
- DB-Export möglichst als **konsistenter Snapshot** mit `--single-transaction --no-tablespaces`; sicherstellen, dass Tabellen transaktional sind und die Sicherung mit den Dateien zeitlich korrespondiert. Keine Credentials in Kommandoargumenten/Output/Shell-History. `wp db export`-Ausgabe-/Stream-Semantik und Rechte **zuerst ausschließlich mit synthetischen Daten** verifizieren.
- Kompletter notwendiger WordPress-Dateibestand einschließlich Uploads/Themes/Plugins/config außer eventuell generierten Caches; Symlinks, Archive-Pfade und `wp-config.php` sorgfältig behandeln. Die Dateiarchive dürfen **nicht** in einem öffentlich erreichbaren IONOS-Pfad zurückbleiben.
- Verschlüsseln **bevor** GitHub `upload-artifact` aufgerufen wird; nur `*.age`/verschlüsseltes Paket darf in Artefakte gelangen. Verschlüsselte Prüfsummen-/Manifestdatei beilegen; Logs nur mit booleschen Status/Größenklassen. Unverschlüsselte temporäre Daten nach Ausführung bereinigen und CI-Runner verwerfen. Eine kurzzeitige Verarbeitung echter WP-Daten auf dem GitHub-Runner bleibt ein relevantes Risiko und erfordert Review des **exakten Workflows**.
- Zuerst vollständige CI-Probe **nur mit synthetischer WordPress-/DB-Fixture und öffentlichem Testempfängerschlüssel**, inklusive negativem Test bei falschem Ziel, fehlender Verschlüsselung, fehlerhafter Dateiintegrität und fehlender SSH-Verbindung. Erst danach einmaliger bewusster Echtgeld-/Live-Snapshot-Start.

### G2 – erster tatsächlich gesicherter OrgaVela-Snapshot

- Als Betreiber zunächst den **IONOS-Vertrag mit OrgaVela** auf aktuelle Webspace- und Datenbank-Recoverypunkte kontrollieren (nur **Sichten**, nie „Wiederherstellen“ anklicken).
- Einmaligen bestätigten Backupworkflow ausführen: berechtigter SSH-/WP-CLI-Lesezugriff → SQL- und Datei-Backup → SHA-256-Prüfung → Public-Key-Verschlüsselung → **nur verschlüsseltes** temporäres Artefakt; kein WordPress-Upload/Restore, kein DB-Write.
- Betreiber lädt das Artefakt herunter, verwahrt es lokal und mindestens an einem unabhängigen Ort, entschlüsselt/testet es **außerhalb GitHub** und bestätigt Prüfsummen, erwartete Archivstruktur und SQL-Lesbarkeit. Original-Backup nicht in öffentliche GitHub-Dateien einchecken; nach bestätigter privater Kopie temporäres GitHub-Artefakt entsprechend Retention-/Datenschutzplan löschen.

### G3 – echte, abgeschottete Wiederherstellung

- Auf Betreiber-PC oder separatem privatem Testgerät einen **isolierten** Container/VM-Stack aufbauen: WordPress/PHP mit zur Sicherung passender Version, MariaDB mit passender Version, eigenem Test-DB-Namen/Passwort, kein öffentlicher Port, Internet-Egress blockiert, WordPress-Cron/Mail/Webhooks ausgeschaltet.
- Privaten Schlüssel nur auf dem lokalen/privaten Restore-Host verfügbar halten; Backup **nur dort** entschlüsseln. Niemals private Schlüssel als GitHub-Secret zur öffentlichen CI hochladen.
- Vor Dateiextraktion/SQL-Import Originalziel `orgavela.com` oder reale DB-Hostnamen **hart als verboten** prüfen. Separaten physischen Restore-Pfad/DB-Namen bestätigen. In die private Test-DB importieren, Dateien in die private Clone-Struktur kopieren; URLs und Dienste für Tests neutralisieren, niemals Originalkonfiguration unverändert ins Internet verbinden.
- Mit realem gesichertem Stand exakt prüfen: Konsistenz WP-Prefix/DB-/Datenversionen (2/2), UOP-Health, 29 Tabellen/Indizes, Standardorganisation, Datei-/DB-Zähler, Test-Benutzerfluss nur mit **synthetischen** Daten, Dauer und Hash-Nachweise. Bei Fehlschlag **O03 rot/offen**, echte OrgaVela-Installation unangetastet.

### G4 – signoff, Recovery und Wartung

- Nur nach bestätigtem G2 und G3 formale O03-/B04-Abnahme setzen; vollständigen Ersatz/PR-/Release-Stack nicht automatisch freigeben.
- Betreiber verwahrt privaten Wiederherstellungsschlüssel redundant und getrennt von verschlüsselten Backups; Backup-Recovery-Verantwortliche und Rotation/Retention festlegen. Regelmäßige Restore-Proben künftig nur nach expliziter Automationsfreigabe planen.

**Aktueller Entscheidungspunkt:** Wie soll die **dauerhafte private Backup-Kopie** abgelegt werden: auf dem eigenen Rechner mit zusätzlicher externer SSD (empfohlen) oder in einem bereits vorhandenen, privaten Cloudspeicher? Vor dieser Entscheidung und dem lokalen Public-Key-Setup **noch keinen Live-Backup-Workflow starten**. `O03` bleibt `partial`; [Read-only-Preflight #38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599) und [synthetischer Restore #38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371) sind nur Vorabbelege.


## 8. Google Drive als gewähltes Offsite-Ziel – tatsächlicher Einrichtungsstand (12.10.2026)

**Betreiberentscheidung:** OrgaVela-Backups sollen künftig bei Google Drive gespeichert werden, auch wenn der Betreiber-PC ausgeschaltet ist. Die langfristige Zielwahl ersetzt die frühere offene Auswahl PC/Cloud in Abschnitt 7, jedoch noch **nicht** die authentifizierte GitHub-Actions-Upload-Anbindung. Über den mit ChatGPT verbundenen Google-Drive-Connector wurde unter „Meine Ablage“ der neue Ordner **„OrgaVela – verschlüsselte Backups“** angelegt, anschließend `shared=false` und nur der **Eigentümer** als Berechtigter verifiziert. Folder-ID/OAuth-Token bewusst nicht im öffentlichen Repository abgelegt.

Der [neue nur synthetische Testworkflow](../../.github/workflows/orgavela-google-drive-connection.yml) nutzt **keine OrgaVela-SSH-Secrets**, kennt keine echte WordPress-Datenbank und enthält lediglich die Modi `selftest` (lokale Age-Encryption-Roundtrip) und **ausschließlich manuell** `upload-synthetic` (Age-Verschlüsselung eines fest kodierten synthetischen Texts, Google-rclone-Upload als `*.age`). [Github Actions Crypto-Test #38294515636](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38294515636): **success**, Google-Canary-Upload **noch nicht durchgeführt**. Für diesen fehlt eine separat durch den Betreiber in Google legitimierte OAuth-Verbindung und der **öffentliche** Age-Empfängerschlüssel im GitHub Environment `orgavela-backup-drive`. ChatGPT-Drive-OAuth kann nicht unmittelbar als GitHub-Secret wiederverwendet werden.

**Einmalige Betreiberaufgaben, technisch Schritt für Schritt:** [`ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md`](ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md) beschreibt den lokal zu sichernden privaten Age-Key, das nur in GitHub-Secrets einzutragende rclone-OAuth-Konfigurationsprofil, die separate `orgavela-backup-drive`-Umgebung und den GitHub-`upload-synthetic`-Canary. Der bestehende Ordner erfordert bei rclone i. d. R. Google-`drive`-Vollzugriff statt engerem `drive.file`-Scope, weil er von einer anderen App erstellt wurde. Wer diese Berechtigung nicht erteilen möchte, sollte einen getrennten Backup-Google-Account oder einen nur von rclone mit `drive.file` erstellten Backup-Ordner verwenden. Diese Abwägung liegt beim Betreiber und muss in der Google-OAuth-Oberfläche explizit bestätigt werden; `root_folder_id` ist keine OAuth-Zugriffsschranke.

**Stopp:** Weder echter Datei-/DB-Export noch Zeitplan oder privater Real-Restore startet vor erfolgreichem, separat autorisierten *verschlüsselten* Google-Testupload und bestätigtem offline gesicherten privaten Schlüssel. O03 `partial`; keine neue V1-Abnahme.
