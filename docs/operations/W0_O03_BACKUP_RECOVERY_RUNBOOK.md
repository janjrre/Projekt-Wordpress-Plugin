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
