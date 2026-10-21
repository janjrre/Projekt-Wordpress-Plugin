# PGP-05 Checkpoint 5 – WordPress-Live-Quellidentität ohne Datenexport (Vorbereitung)

**Stand:** 21.10.2026 | **Status:** 20/20 lokale synthetische Tests PASS; **keine Ausführung auf IONOS** | **Budget:** 0 € zusätzlich, keine CI-/Actions-/CircleCI-Runs.

## Auftrag, Freigabe und Grenzen

Der Betreiber hat erlaubt, bei OrgaVela selbstständig mit allen **kostenfreien** sicheren Maßnahmen weiterzuarbeiten. Diese Zustimmung ersetzt nicht die Pflicht, echte Wiederherstellungs- oder Website-Überschreibungsaktionen nur in einer isolierten Zielumgebung zu prüfen. Kein GitHub Actions/CircleCI-Job wird gestartet, solange 0 € Abrechnung nicht zuverlässig nachweisbar ist.

**Direkte Prüfmöglichkeiten aktuell:** Die bestehende WordPress-MCP-Anbindung liefert Website-URL, MariaDB-Serverversion und Plugin-Status, aber über ihre deklarierten read-only-Abfragen **keinen effektiven WordPress-Server-Dateiroot/aktiven Datenbanknamen mit Tabellenmetadaten**. Die vom Betreiber gezeigten IONOS-UI-Belege bestätigen die WordPress-Dateien im Webspace-Verzeichnis `/public` und 52/52 InnoDB-Tabellen im gezeigten Datenbankschema, aber noch keine unabhängige Zuordnung der effektiv geladenen `wp-config.php` zu dieser Datenbank. Die lokal angebundene Desktop-Commander-Maschine `PCjan` war beim Check **offline**; über sie wurde nichts ausgeführt. Der ältere GitHub Actions Read-only-Workflow nutzt bestehende SSH-Secrets und ist potenziell abrechnungsrelevant; **kein Workflowstart**.

## Neue Dateien

- `scripts/ci/orgavela_pgp05_identity_probe.php` – WP-CLI-gebundener, nur lesender **Quellmetadatenprüfer**. Er kennt weder GitHub-Release- noch SSH-Credentials, legt keine Sicherungen an und schreibt/entpackt keine Daten. Er fordert einen bereits außerhalb des Skripts verifizierten absoluten WP-Root im Umgebungsparameter `ORGAVELA_PGP05_EXPECTED_WP_ROOT`, vergleicht ihn per `realpath` mit dem vom geladenen WordPress gelieferten `ABSPATH`, verifiziert vorhandene Standardordner und nur die **Existenz** von `wp-config.php` und `uop-core.php`. **Keine Konfigurationsdatei wird textuell gelesen oder ausgegeben.**
- Das Skript vergleicht `home` und `siteurl` exakt mit `https://orgavela.com`, überprüft die aktive WordPress-DB-Verbindung durch den **nur innerhalb des PHP-Prozesses** verglichenen Wert aus `SELECT DATABASE()` gegen `$wpdb->dbname`, prüft aggregiert `52/52` InnoDB-Tabellen in `information_schema.TABLES` und den Bestand von fünf WP-Core- und fünf UOP-Core-Tabellen. `wpdb->prepare` schützt die Tabellenname-Abfrage. Keine WordPress-Tabellenzeilen oder Nutzerdaten werden abgefragt.
- **Strikte Ausgabe:** nur bekannte Statuskennzeichen. Weder effektiver Pfad noch DB-Name, Prefix, SQL-Query, Benutzername, Host, Root-/Private-Key oder Throwable-Fehlermeldungen erscheinen im Erfolgs- oder Fehlertext. `REFUSED` bei allen Abweichungen. Die sichtbaren Erfolgsangaben enthalten ausdrücklich `PHPMyADMIN_DB_IDENTIFIER=not_independently_compared`, `DOMAIN_DOCUMENT_ROOT=not_independently_pinned`, `BACKUP_CAPTURE=blocked`. **METADATA_ONLY_PASS bedeutet kein PGP-05-Produktiv-GO.**
- `scripts/ci/test_orgavela_pgp05_identity_probe.py`: **20 lokale, rein künstliche** Unit-Tests gegen einen Fake-WP-/Fake-wpdb-Prozess. Testet sicher begrenzte Erfolgsausgabe, URL-/Siteurl-Abweichung, unsichere / Dreamloud-/Traversal-Rooteingaben, fehlendes WP-CLI, abweichenden DB-Konfigurationsbezug, InnoDB-/Tabellenzählung, fehlende WP-/UOP-Datei, SQL-Prefix-Eingabesanitierung und Geheimnis-/Pfad-Nichtoffenlegung.

## Lokale Nachweise (vor GitHub-Commit)

Auf dem isolierten Assistenz-Container: **PHP CLI 8.4.24**, `php -l scripts/ci/orgavela_pgp05_identity_probe.php` ergibt **No syntax errors**; mit Python `python3 -m unittest discover -v -s scripts/ci -p 'test_orgavela_pgp05_identity_probe.py'` **20/20 PASS in unter einer Sekunde**. Für diese künstlichen Tests wurden **kein** echter WordPress-Server, MariaDB-Server, SSH-Prozess, GitHub Action oder externe Netzwerkanfrage verwendet.

Exakt getestete Git-Blobs:
- PHP-Quellcode `7a5801d0c2c8405901e50acd95f5aab96cf18b48`
- Python-Test `35a695566a3a906c6e8be63277da8a9dcf0fbab2`

Diese Prüfung der Shell-/PHP-Syntax und künstlicher WP-Simulation **beweist nicht**, dass sich der Quellroot oder die reale DB-Verbindung in der aktiven OrgaVela-WP-CLI-Sitzung erfolgreich verifizieren lassen.

## Sichere spätere Verwendung – ausdrücklich nicht jetzt gestartet

1. Zunächst unabhängig den zu `orgavela.com` gehörenden IONOS-SSH-Endpunkt, dessen gepinnten Hostkey, das Shell-Namespace-Mapping des WordPress-Document-Roots sowie die Autorisierung der übergebenen Account-Rolle prüfen. `/public` aus SFTP- und Webspace-Oberfläche muss nicht im Shell-Namespace derselbe absolute Pfad sein. Niemals Dreamloud-Verzeichnisse durchsuchen.
2. Erst auf bereits freigegebenem **eng begrenztem** OrgaVela-SSH-Kanal und mit lokal gesetztem/validiertem `ORGAVELA_PGP05_EXPECTED_WP_ROOT` den Probe-PHP-Code über WP-CLI `--skip-plugins --skip-themes` **rein lesend** ausführen. Eine solche Ausführung **ist bislang nicht erfolgt**. Der spätere Betreiber-/Runner-Prozess muss die gesamten ungefilterten WP-CLI-STDOUT-/STDERR-Streams zunächst **nur ephemer und privat** zurückhalten, daraus nur die dokumentierten festen Statuskennzeichen allowlist-filtern und die temporären Daten im Fehlerfall sicher bereinigen.
3. Selbst ein grünes **METADATA_ONLY_PASS** ist **kein Beweis**, dass die private IONOS-phpMyAdmin-DB-Kennung unabhängig bestätigt wurde. Dazu wäre ein getrenntes Identifikationsverfahren ohne Offenlegung oder Speicherung des DB-Namens nötig. Auch Host-/Root-Bindung, Dateileserechte, DB+FS-Zeitkohärenz und DDL-/WordPress-Schreibbarriere bleiben für einen echten Backup-Capture eigens abzunehmen.
4. Erst nach Nachweis, dass der neue Lauf keine Kosten auslösen kann bzw. ausdrücklich separat freigegebener Kostenübernahme: Entscheidung über eine Runnerausführung. **Keine** automatische Main-Merge-, Cron-, CI-/Actions- oder kostenpflichtige Pipeline-Aktivierung durch die Existenz dieses Skripts. Keine persönlichen Daten oder Produktivkonfiguration nach GitHub kopieren.

## Checkpoint-Stoppgrenze

**Offline-Code/Test fertig:** ja.  
**Code gemergt / CI-Livecheck:** nein.  
**Direkter SSH-Metadatencheck gegen OrgaVela:** nein.  
**Effektive produktive Source-Identity und Credentialrechte:** offen.  
**Echter SQL-Dump / GitHub-Offsite-Release / isolierter Real-Restore / Scheduler:** gesperrt.  
**Kosten:** keine zusätzlichen bezahlten Dienste oder Runs veranlasst.

Bezug: [PGP-05 Source-Consistency-Draft #30](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/30), [Synthetischer Archivprüfer-Draft #31](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/31), [O03-Sicherheitsissue #27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27), [Dual-Channel-Recovery-Plan](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/docs/pgp04-freeze-audit-20261020/docs/operations/PGP05_DUAL_CHANNEL_RECOVERY_ACCEPTANCE_20261021.md).
