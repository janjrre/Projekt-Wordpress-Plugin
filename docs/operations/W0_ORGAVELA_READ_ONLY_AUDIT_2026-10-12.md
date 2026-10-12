# W0 – OrgaVela: read-only Schema-, Recovery- und Datenschutzbefund

**Datum:** 2026-10-12 · **Umgebung:** ausschließlich https://orgavela.com (IONOS) · **Arbeitsblöcke:** O02/M1-08, O03, O01, O06, O09.  
**Status:** erste authentifizierte, lesende Prüfungen durchgeführt; **keine** physische Datenbankschema-Abnahme, kein Restore, kein neuer Deployment- oder Release-Nachweis. Keine Dreamloud-Aktion.

## 1. Tatsächlich verifizierte Werte – OrgaVela WordPress MCP

- WordPress **7.1.3**, PHP **8.3.33**, MariaDB **11.8.8**, InnoDB/Tabellenstruktur **noch nicht unabhängig** überprüft, DB-Verbindungszeichensatz **utf8mb4**, Kollation **utf8mb4_unicode_520_ci**, HTTPS aktiv. `core__get-environment-info` meldet den WordPress-Umgebungstyp `production` (Hosting-Konfigurationswert, **keine** V1-Freigabe).
- `mosmcp__plugin-get`: `uop-core/uop-core.php` **0.1.0-alpha.2 aktiv**, keine automatische Plugin-Aktualisierung. Diese Live-Version ist **nicht** der neueste Entwicklungs-PR #11.
- `mosmcp__cron-test`: `DISABLE_WP_CRON=true`, Loopback HTTP 200, **5 überfällige Ereignisse**; `mosmcp__cron-list-events`: **19 Ereignisse**, darunter überfällige `action_scheduler_run_queue`. Ein funktionierender **externer** IONOS-Cron ist dadurch **nicht** bewiesen; keine manuellen Jobs ausgelöst.
- `mosmcp__site-get-search-visibility`: `discourage_search_engines=false`.
- `mosmcp__site-get-privacy-policy-page`: Datenschutzseite referenziert, aber `status=draft`; Besuchern nicht zugänglich. **Keine** Veröffentlichung eines ungeprüften Rechtstextes.
- Das WordPress-MCP stellt **keine** generische SQL-Abfrage, keinen UOP-Site-Health-Testaufruf, keinen SFTP-Chroot-Adminzugriff und keine DB-Backup-/Restore-Aktion bereit. Insbesondere **kein** fälschlich behauptetes `uop_schema=good`.

## 2. O02/M1-08 – Source-zu-Live-Prüfvertrag

**Im PR-#11-Source-Tree geprüft:** `schema/manifest.json` enthält die eingefrorenen **28** V1-Tabellen. `src/Infrastructure/Database/AgeAttestationSchema.php` definiert **eine** zusätzliche V2-Tabelle `age_attestations`. `SchemaInspector::verify_all()` kontrolliert Tabellenmenge, InnoDB, Zeichensatz/Kollation, Spalten, Defaults, Nullbarkeit, Typen und Indizes; `AgeAttestationSchema::verify()` kontrolliert die V2-Struktur. `Installer::health()` kontrolliert zusätzlich `uop_db_version=2`, `uop_data_version=2`, `uop_migration_status.status=complete`, `Bootstrap::errors()` und beide physischen Strukturen. Dieser Test wird mit `Bootstrap::boot()` unter WordPress **Werkzeuge → Website-Zustand (Site Health)** als `uop_schema` registriert.

**Vorliegende Evidenz reicht nicht für die Abnahme.** Für die physische Live-Abnahme sind nötig:
1. Authentifizierter, rein lesender Screenshot/Resultat des UOP-Site-Health-Tests **auf der installierten Alpha.2**, mit explizitem `status=good`. Alle Fehlerzustände `critical` belassen; **nicht** durch Reaktivierung/Migrationslauf „beheben“.
2. Unabhängige `information_schema`-Erhebung über das **genau OrgaVela zugeordnete** IONOS phpMyAdmin, gegen den **exakten** 28+1-Build-Manifeststand: 29 Soll-Tabellen, Tabellen-Engine, Collation, alle Indexnamen und Spaltenreihenfolgen, `UNIQUE`/Primärschlüssel; keinerlei Datensätze exportieren oder öffentlich dokumentieren.
3. Nur bei gesicherter Zuordnung der Datenbank/Tabellenpräfixe die Migrationsoptionen `uop_db_version`, `uop_data_version`, `uop_migration_status`, `uop_default_organization_id` aus der site-eigenen `<WP_PREFIX>options` **lesend** vergleichen sowie exakt eine aktive `default`-Organisation und OrgScope-Konformität nachweisen. Optionswerte und echte Identitätsdaten **nicht** in öffentlichen Worklogs speichern.
4. Keine Änderung der Datenbank, kein `dbDelta`, kein Plugin-Upgrade und kein Migrations-Replay ohne O03-Restore-Gate.

**Read-only SQL für den IONOS-Administrator** (nur nach sicherer Datenbankidentifikation; `<WP_PREFIX>` durch den tatsächlichen WordPress-Site-Präfix ersetzen, **nicht** blind `wp_` annehmen). Diese Abfragen liefern **nur Metadaten**, keine Personen-/Kontaktinhalte:

```sql
-- Soll: 29 OrgaVela-UOP-Tabellen (28 V1 + age_attestations).
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND LEFT(TABLE_NAME, CHAR_LENGTH('<WP_PREFIX>uop_')) = '<WP_PREFIX>uop_'
ORDER BY TABLE_NAME;

-- Spaltenmetadata für unabhängigen Manifestvergleich.
SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE,
       IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND LEFT(TABLE_NAME, CHAR_LENGTH('<WP_PREFIX>uop_')) = '<WP_PREFIX>uop_'
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Unique- und Sekundärindizes mit stabiler Reihenfolge.
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND LEFT(TABLE_NAME, CHAR_LENGTH('<WP_PREFIX>uop_')) = '<WP_PREFIX>uop_'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Nur Status-Optionsnamen, keine gespeicherten privaten Optionswerte.
SELECT option_name FROM `<WP_PREFIX>options`
WHERE option_name IN ('uop_db_version','uop_data_version',
                      'uop_migration_status','uop_default_organization_id')
ORDER BY option_name;
```

**STOP:** Bei abweichendem Schema, fehlendem Tabellenpräfix, ungeprüftem Site-Health-Wert oder fehlendem Datenbankbezug den Exit M1-08 **nicht** erteilen. Site Health ist aus der aktiven Version zu lesen, nicht nur aus dem neueren Featurebranch abzuleiten.

## 3. O03 – gesicherter Wiederherstellungsweg, noch nicht ausgeführt

IONOS beschreibt zwei vom konkreten **Tarif** abhängige Möglichkeiten; das tatsächliche OrgaVela-Paket und die vorhandenen Sicherungspunkte sind noch **nicht** im IONOS-Konto geprüft:

- **Hosting für WordPress / Managed WordPress:** tägliche, über das Webprojekt verwaltete WordPress-plus-Datenbank-Wiederherstellungspunkte, laut IONOS-Dokumentation bis zu **7 Tage**; eine solche Wiederherstellung überschreibt Änderungen seit dem Wiederherstellungsdatum und darf **niemals** als ungeprüfter Probelauf auf OrgaVela benutzt werden. [IONOS WordPress-Backup](https://www.ionos.de/hilfe/wordpress/wordpress-installation-und-datenbank-ueber-ionos-konto-wiederherstellen/).
- **Allgemeines Webhosting:** IONOS beschreibt System-Backups mit einem möglicherweise anderen Retentionsfenster (in dieser Dokumentation bis zu **14 Tage**) sowie separate **manuelle** Datei- und DB-Exporte. Diese Tarifangaben dürfen **nicht** ohne Nachweis auf OrgaVela übertragen werden. [IONOS manuelle Datei-/DB-Backups](https://www.ionos.de/hilfe/hosting/dateien-sichern-wiederherstellen/webspace-und-datenbanken-manuell-sichern-backups-erstellen/).
- Unabhängiges Backup: **OrgaVela-/public-Webroot** ausschließlich lesend per OrgaVela-gebundenem SFTP exportieren und die **zugehörige MariaDB vollständig** über phpMyAdmin als SQL-Dump exportieren, getrennt vom öffentlichen Webroot speichern, SHA-256/Dateigrößen/Erstellzeit erfassen und Zugriffsrechte beschränken. `wp-config.php`, SQL-Dumps und Dateiarchive **niemals** in das öffentliche GitHub-Repository, GitHub-Artefakte oder Logs stellen. [phpMyAdmin Export](https://www.ionos.de/hilfe/hosting/mysql-datenbank-sichern-wiederherstellen/datenbanken-sichern-mit-phpmyadmin/); [phpMyAdmin Import](https://www.ionos.de/hilfe/hosting/mysql-datenbank-sichern-wiederherstellen/wiederherstellen-von-mysql/mariadb-datenbanken-mit-ionos-phpmyadmin/).
- **Restore-Probe nur isoliert:** neue separate temporäre Datenbank, separates Webroot, eingeschränkter Zugang, abgetrennte externe E-Mail-, Cron- und Webhook-Ausgänge, synthetische/geschützte Daten und Anpassung der temporären URL. Prüfen: WordPress-Boot, UOP-Site-Health, 29 Tabellen, Optionen, Logins und Berechtigungen, Migrationsstopp/Zurückrollen. Anschließend isolierte Umgebung sauber entfernen. **Niemals** Live-OrgaVela oder Dreamloud überschreiben.
- Ohne phpMyAdmin-/SFTP-Exportbelege und unabhängigen Restore-Test **O03 offen**, keinerlei riskantes Upgrade.

## 4. O01 – SFTP-Risikogrenze

Der kontrollierte GitHub-SFTP-Workflow adressiert nur `/public`; laut vorheriger IONOS-/Betreiberprüfung kann derselbe SFTP-Account jedoch weitergehende Webspace-Pfade erreichen. Das ist **keine** echte Account-Isolation. Abhilfe nur über **IONOS-seitigen eigenen, verzeichnisgebundenen SFTP-Nutzer oder separat isoliertes Webspace-Projekt**, nach belegter Machbarkeit und ohne Kosten/andere Websites. Bis dahin bestehende Scope-Prüfungen beibehalten, keine weiten Lösch-/Downloadoperationen und keine geänderten Deploy-Requests. **O01 nicht abgenommen.**

## 5. O06 – akutes Staging-Gate

OrgaVela ist trotz „Testinstallation“ WordPress-intern als `production` konfiguriert. `discourage_search_engines=false`, zugewiesene Datenschutzerklärung `draft`. Die lesenden MCP-Abilities erlauben **keine** Änderung der Suchmaschinenoption. Nächster zulässiger Eingriff: im **OrgaVela**-Admin `Einstellungen → Lesen → Suchmaschinen davon abhalten ...` setzen und anschließend erneut lesen; für private Tests vorrangig **echten HTTP-Zugriffsschutz** (z.B. IONOS Passwortschutz), da `noindex` kein Zugriffsschutz ist. Datenschutztext nur nach Rechtsprüfung veröffentlichen. Bis zur Absicherung keine Teilnehmer- oder Minderjährigendaten, keine öffentliche Beta. **O06 blockiert.**

## 6. O09 – aktuelle CI-Fakten

Der frühere PR-#11-HEAD `1b98658704e1b4e8fb5350d7817e20af4fdecc35` hatte zwei rote Läufe: [Foundation #38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475), PHP-8.5/MySQL-8.0 Browser-Test `activation.spec.js`: `page.goto` bei Deaktivierung nach 30 Sekunden Timeout; [HTTPS #38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383), Admin-Record-Klick vom WordPress-Menü überdeckt/Timeout; 40 statt mindestens 44 Pixel echte Control-Höhe. **Nicht** die 44px-A11y-Schwelle absenken.

**Neuer aktueller HEAD zum Prüfzeitpunkt:** `7892a25894c184a624ab4ebea13aab0e1a1c88dd`; [Foundation #38248040810](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040810) zunächst noch laufend; [HTTPS #38248040695](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040695) **fehlgeschlagen** – Login bei `m610-members-admin.spec.js` Sorgeberechtigtenfall: `page.waitForURL('/wp-admin/')` nach 20 Sekunden, **keine bestätigte Ursache** der ausbleibenden Navigation. Gegenüber dem alten SHA gab es neue Browserfix-Commits; vor weiteren Änderungen neue Runner-Diagnostik und Login/Redirect/HTTP-Status prüfen, **nicht** nur Timeouts erhöhen oder Assertions aussetzen.

## 7. Sicherheits- und Exit-Vermerk

**Ausgeführt:** ausschließlich quelllesende GitHub- und authentifizierte OrgaVela-MCP-Abfragen, CI-Loganalyse und dokumentierter Verfahrenabgleich mit IONOS-Hilfe.  
**Nicht ausgeführt:** SQL-Statements auf OrgaVela, phpMyAdmin-Zugriff, Backup, Restore, Plugin-Upgrade/Deployment, Cron-Trigger, Website-Publikation, Indexierungsänderung, SFTP-Accountverwaltung, Real-User-Tests oder Dreamloud-Zugriff.  
**Nächste harte Blocker:** direkter `uop_schema`- und `information_schema`-Livebeleg; OrgaVela-spezifischer Backup-/Restore-Nachweis; Zugangsschutz/Datenschutz; rote neueste CI.

**Abnahmeentscheidung:** O01, O02/M1-08, O03, O06, O09 **nicht abgeschlossen**. Die Gesamtzählung 62/71, 0/8, 0/4 und 0/18 bleibt unverändert.
