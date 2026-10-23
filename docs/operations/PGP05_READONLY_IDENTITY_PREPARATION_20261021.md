# PGP-05 Checkpoint 5 – Vorbereitung eines einmaligen OrgaVela-SSH-/WP-Metadatenchecks

**Status:** ENTWURF / REVIEW ERFORDERLICH – NICHT AUSGEFÜHRT  
**Datum:** 21.10.2026  
**Sicherheitsfreigabe:** Betreiber hat „Fahre fort“ zur Vorbereitung und gemeinsamen Durchführung des eng begrenzten Read-only-Checks gesagt. **Keine** pauschale Kosten-, Merge-, Provider-CI-, Realdatenbackup- oder Restore-Freigabe. Vor **einem kostenpflichtigen CI-Lauf** nochmals gesondert anfragen.

## Ausgangslage und belegbare Quellen

- Domain und IONOS WordPress-Projekt `orgavela.com`; WordPress 7.1.3, PHP 8.3.35, MariaDB 11.8.8, UOP Core Alpha.2 aktiv laut direkt verbundener WordPress-Metadatenabfrage am 21.10.
- Betreiber-Ionos-Webspace-Explorer: WordPress-Root-Dateien und `wp-config.php` (UI-Rechte 600) unter `/public`; 52 Tabellen mit **52/52 InnoDB** im via phpMyAdmin inspizierten einzigen sichtbaren Schema. In der OrgaVela-IONOS-UI neun Anbieter-Restorepunkte, aber **kein** tatsächlicher Restore.
- Historische GitHub-Actions-Audits vom 12.10.2026: `probe` [#38264254555](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555), `schema` [#38264354774](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264354774), `backup-preflight` [#38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599), alle completed/success; kein aktueller Root/DB-Host-Identitätsbeweis. Die historischen Skripte verwenden optional alternative Shell-Roots.
- Quelle für zeitliche Beweisgrenzen: [PGP05 IONOS Read-only Bericht](PGP05_ORGAVELA_WORDPRESS_READONLY_METADATA_20261021.md) und [Zwei-Kanal-Wiederherstellungsplan](PGP05_DUAL_CHANNEL_RECOVERY_ACCEPTANCE_20261021.md). Diese Dokumente befinden sich derzeit im separaten Auditbranch, nicht notwendigerweise auf `main`.

## Drei neue, unabhängige Komponenten (noch nicht auf main)

1. `scripts/ci/orgavela_pgp05_readonly_remote_collect.sh` – **nur vorbereiteter Remote-Shell-Quellentwurf**, kein SSH-Client: verweigert standardmäßig Ausführung, erfordert sowohl expliziten Einmal-Modus `--metadata-only-approved` als auch das eng begrenzte Opt-in `ORGAVELA_PGP05_OWNER_APPROVED_READONLY=ORGAVELA-METADATA-ONLY`. Keine GitHub-Writer-Tokens im Kontext. Betrachtet nur vier feste OrgaVela-WP-Pfadkandidaten, stoppt bei widersprüchlichen Installationsroots, prüft lesbare WP-Konfigurationsdatei **ohne deren Inhalt auszugeben**.
2. Der Remote-Entwurf führt, **nur wenn später ausdrücklich freigegeben**, eine WP-CLI-Identitäts-/InnoDB-Zählabfrage mit `--skip-plugins --skip-themes` aus. Er fragt `home`, `siteurl`, UOP-Pluginstatus/-Version sowie **nur Strukturmetadaten** aus `information_schema.TABLES` für die **effektiv von diesem WordPress verwendete DB-Verbindung** ab. Keinerlei Benutzer-/Anmelde-/Einwilligungs-Datensätze, Backup-Dateiinhalte oder DB-Name im Ausgabeprotokoll. Kein Dump, Datei-Download, Restore, Cron-Trigger oder Seitenschreibbefehl.
3. `scripts/ci/orgavela_pgp05_identity_contract.py` mit `test_orgavela_pgp05_identity_contract.py`: strikt begrenztes lokales Receipt-Format. **15/15 lokale Python-Unittests PASS** (Python-Lauf in isolierter Arbeitsumgebung), inklusive Ablehnung falscher Domains, unbekannter Geheimfeldnamen, nichtboolescher Flags, Tabellen-/Engine-/Version-Drift, JSON-Dubletten und behaupteter unabhängig geprüfter DB-Identität. Der Vertrag lässt nur einen öffentlichen diskreten Kandidaten-Root-Typ zu, niemals rohe Hosts/Pfade/DB-Namen. Selbst ein `PASS` bedeutet **ausdrücklich nur erfolgreichen Metadaten-Vertragscheck, keine Produktionsfreigabe, keinen unabhängigen DB-Namensabgleich und keinen Restore**.

### Verifizierte lokale Prüfungen – exakt veröffentlichte Blobs

- Python: **15/15** Unit Tests PASS auf den byteidentischen Git-Blob-Dateien `8ecc94486ad8c8c6f5625852d895456821ad4641` (Contract) und `182086034788a3dc7305e082bca65e63ed796ad0` (Tests).
- Remote-Collector-Entwurf: `bash -n` **PASS**, eingebetteter PHP-Teil isoliert mit `php -l` **PASS**, byteidentischer Git-Blob `a9ed7cf84baa1d8f8a70ad6ca84bb453b3b41232`.
- **Nicht getestet:** Ausführung gegen das echte WordPress-/IONOS-SSH-System, WordPress-Laufzeit/MU-Plugins, SSH-Host/Root/Berechtigungen, DB-Identity-Referenz zur Betreiber-phpMyAdmin-Ansicht. Keine Code- oder Provider-CI-Runs gestartet.

## Vor jedem echten Aufruf zwingend zu klären

**Wichtige Bootstrap-Gefahr:** `wp --skip-plugins --skip-themes eval` lädt WordPress und kann trotz Skip-Optionen **MU-Plugins, Drop-ins und Boot-Code ausführen**. Ein PHP-Ausdruck mit nur `SELECT information_schema` garantiert daher ohne Vorprüfung keine völlig nebenwirkungsfreie WordPress-Ausführung. Die bereits gelistete aktive IONOS-/MCP-MU-Plugin-Landschaft ist als potentieller Trigger ausdrücklich zu bewerten. **Vor Review der Bootstrap-/MU-Plugin-Seiteneffekte: nicht remote starten.**

**Autorisierter Transport:** Das neue Skript **nicht** als blindes SFTP-Upload in `/public` deployen und niemals über eine öffentliche PHP-URL ausführen. Erst nach Code- und Operator-Abnahme wäre ein eigener, zeitlich begrenzter, geschützter GitHub- oder CircleCI-Read-only-Transport mit fest gepinntem ED25519-IONOS-SSH-Hostkey, OrgaVela-only-Identity, geschütztem Secret-Kontext, minimaler Credential-Scope, manuellem Auslöser und ausdrücklicher Kostenfreigabe zu erstellen. Der historische `orgavela-ionos`-GitHub-Environment-Kontext existiert, er wird aber **nicht** ungeprüft mit dem produktiven Release-Writer-Kontext vermischt. Keine Verwendung des CI-Deploy-Rechts für produktive Backups.

**Datenschutz und Ergebnisführung:** SSH-Remote-Stdout/Err und WP-CLI-Boot-Meldungen müssen in einen zugriffsgeschützten temporären Bereich umgeleitet werden; nur wenn das lokale Contract-Skript die JSON-Felder akzeptiert, darf eine kleine `PASS/REFUSED`-Zeile ohne Rohdaten in den Joblog. Dateipfade, DB-Name/-Host, Tabellenprefix, Kontonamen, SSH-Banner, PHP-Warnungen, `wp-config.php`-Inhalt, Secrets und Rechnungsdaten **niemals** nach GitHub/Chat kopieren. Schon das optionale Protokoll darf selbst keine neue CI starten.

**Baseline-Drift:** WP 7.1.3, UOP Alpha.2 und 52 InnoDB-Tabellen sind ein Zeitpunktstand. IONOS automatische Updates sind aktiv. Bei jeder Abweichung gilt `REFUSED` + separate fachliche Prüfung, **keine automatische Anpassung der Sollwerte**.

**Unabhängiger Datenbankabgleich:** Die Remote-Strukturabfrage bestätigt nur die DB, die WordPress selbst verwendet; ein passendes 52/52-InnoDB-Ergebnis stützt die phpMyAdmin-Zuordnung stark, **ersetzt aber keinen unabhängigen Namensabgleich**. Der Contract verbietet daher `db_identity_independently_verified=true`; bevor echte Source-Capture-Freigabe möglich wird, ist ein auf private, nicht geloggte Weise unabhängiger DB-Konfigurationsabgleich erforderlich.

## Konkrete nächste Abnahmeschritte

| Schritt | Stand | Neue explizite Entscheidung erforderlich |
| --- | --- | --- |
| Offline-Contract, negative Fixtures, PHP-/Shell-Syntax | **lokal vorbereitet, PASS** | Keine; nur Draft-Code |
| Remote-Skript und MU-/Bootstrap-Side-Effect-Review | **OFFEN** | Sicherheitsreview, kein produktiver Lauf |
| Verbindliche SSH-/Hostkey-/WordPress-Root-Autorisierung | **OFFEN** | Eng umrissener Betreiber-GO für genau diesen Transport |
| Einrichtung/Start eines CI-Jobs inkl. Kosten | **NICHT ERLAUBT** | **Gesondertes ausdrückliches Betreiber-GO** vor jedem neuen Run |
| PHP-/WP-Boot, MariaDB-Engine-Summen auf echtem Ziel | **NICHT AUSGEFÜHRT** | Erst nach allen vorherigen Freigaben |
| Backup-Quelle lesen, Archiv erstellen, GitHub-Release, isolierter Restore, Scheduler | **BLOCKED** | Mehrere eigene produktive GOs und reale Recoverytests |

**Keine Änderung der V1-Abnahmezahlen:** historische Codingpakete 62/71, formale M0–M7 0/8, P4 0/4, Release-Gates R01–R18 0/18. Kein Automatismus durch den neuen Draft.

## Arbeitsauftrag / Denkansatz / Umsetzung / Nachweis

**Auftrag:** „Fahre fort“ auf den zuvor angekündigten einmaligen SSH-Read-only-Metadatencheck.  
**Denkansatz:** Da ein echter GitHub-/CircleCI-Lauf separat kostenfreigabepflichtig ist und historischer SSH-Pfad noch unklar ist, zuerst den Metadatenvertrag und den begrenzten Remote-Entwurf mit negativen Offline-Tests in einem unabhängigen Draft-PR bereitstellen; den alten Deploy-Workflow und die Produktions-Backups unangetastet lassen.  
**Umsetzung:** neue Vertrags-/Fixture-/Remote-Collector-Dateien plus dieses Runbook, eigenständiger Featurebranch ohne Main-/Produktiv-Merge.  
**Ergebnis:** Nur Syntax-/Testnachweise. **Kein** OrgaVela-SSH-/DB-Zugriff, keine Secrets, keine CI-/Actions-Kosten, kein Backup, keine WP-Änderung, keine PR-Merges.
