# PGP-05 – OrgaVela read-only Live-Metadatenabnahme (ausschließlich WordPress-MCP)

**Datum:** 21.10.2026 (Europe/Rome)  
**Freigabe:** Betreiber: „Ja, den read-only Check gebe ich frei“. Ausschließlich lesende technische Site-/Plugin-/Umgebungs-/Cron-Metadaten. **Keine** Genehmigung für IONOS-SSH, SFTP, direkten SQL-Zugriff, Backup-/Dateidownloads, CI-Jobs oder reale Backup-Erstellung.  
**Ziel:** `OrgaVela`, WordPress-Verbindung `link_6acc9fc926c88191847cc839a24f0b87`. Die separate Dreamloud-Verbindung wurde weder entdeckt noch aufgerufen.

## Genau ausgeführte Abfragen (alle als read-only ausgewiesene WordPress-Actions)

1. `core__get-site-info` mit `fields=[name,url,wpurl,language,version]`
2. `core__get-environment-info` mit `fields=[environment,php_version,db_server_info,wp_version]`
3. `mosmcp__site-get-url`, `mosmcp__site-get-wordpress-version`, `mosmcp__site-get-server-info`
4. `mosmcp__plugin-get` (`uop-core`), `mosmcp__plugin-list` (zunächst Filter `uop-core`, dann nur Plugin-Metadaten `status=all`), `mosmcp__plugin-count-by-status`
5. `mosmcp__cron-test`: Read-only-Schedulerdiagnose mit internem WordPress-Loopback-HTTP-Check. **Kein Cronjob ausgelöst, keine Cron-Einstellung verändert.**

Die Antworten wurden direkt aus der verbundenen OrgaVela-Site gelesen. Es wurden **keine Benutzerdaten, WP-Beiträge, Kundendatensätze, DB-Inhalte, SQL-Abfragen, Geheimnisse oder lokale Dateipfade** angefordert. Die Dokumentation enthält ausschließlich einen sanitierten Metadaten-Auszug; keine Credential- oder Roh-Request-Dumps.

## Aktueller belegter Laufzeitstatus

| Eigenschaft | Ergebnis | Evidenzquelle / Aussagekraft |
| --- | --- | --- |
| WordPress Website-Name | **OrgaVela** | `core__get-site-info` |
| Home-/Site-URL | **https://orgavela.com** / **https://orgavela.com** | `core__get-site-info` und `mosmcp__site-get-url`, über zwei WordPress-Actions konsistent |
| HTTPS | **true** | `mosmcp__site-get-url` und `mosmcp__site-get-server-info` |
| Multisite | **false** | `mosmcp__site-get-url` / `mosmcp__site-get-wordpress-version` |
| WordPress-Version | **7.1.3** | `core__get-site-info`, `core__get-environment-info`, `mosmcp__site-get-wordpress-version` |
| WordPress-DB-Schema-Version | **61833** | `mosmcp__site-get-wordpress-version` |
| PHP | **8.3.35**, CGI-FCGI | `core__get-environment-info`, `mosmcp__site-get-server-info`; früher im Projekt noch 8.3.33 dokumentiert |
| Datenbankserver | **MariaDB 11.8.8** (`11.8.8-MariaDB-log`) | `core__get-environment-info`, `mosmcp__site-get-server-info`; **dies belegt nicht Namen, Tabellen oder Rechte der konkreten WP-Datenbank** |
| Datenbankzeichensatz | **utf8mb4** | `mosmcp__site-get-server-info` |
| Serveranwendung | **Apache**, HTTPS | `mosmcp__site-get-server-info` |
| PHP memory limit | **512M** | `mosmcp__site-get-server-info`; keine Aussage über SSH-/Cron-Prozesse |
| WordPress memory / max memory | **40M / 512M** | `mosmcp__site-get-server-info` |
| Maximale PHP-Laufzeit | **360 Sekunden** | `mosmcp__site-get-server-info`; kein geeignetes Snapshot-Zeitbudget bewiesen |
| PHP Upload-/POST-Limits | **128M / 1024M** | `mosmcp__site-get-server-info` |
| WordPress-Laufzeittyp | **production** | `core__get-environment-info`: **WP-Umgebungskennung**, ausdrücklich **keine** PGP05-Produktionsfreigabe |
| UOP Core | **`uop-core/uop-core.php`, 0.1.0-alpha.2, aktiv** | `mosmcp__plugin-get`, `mosmcp__plugin-list` |
| UOP-Core-Minimum | WP >= 6.9, PHP >= 8.3 | Installierte Plugin-Metadaten; Mindestwerte erfüllt, aber **keine vollständige Kompatibilitäts-/Restore-Abnahme für WP 7.1.3** |
| Plugin-Überblick | **8 installiert, 7 aktiv, 1 inaktiv, 1 MU-Plugin** | `mosmcp__plugin-count-by-status` + `mosmcp__plugin-list`; kein konkret verifiziertes Backup-Plugin oder IONOS-Provider-Snapshot |
| WordPress-Cron | **`DISABLE_WP_CRON=true`**, Alternate-Cron=false | `mosmcp__site-get-server-info` und `mosmcp__cron-test` |
| Cron-Loopback | **HTTP 200, erreichbar** | `mosmcp__cron-test`; kein Nachweis, dass ein externer Server-Cron die Jobs ausführt |
| Überfällige WordPress-Aufgaben | **2** | `mosmcp__cron-test`; nur Anzahl, keine Plugin-/Nutzer- oder Inhaltsdaten abgefragt |

Im Plugin-Metadaten-Inventar sind IONOS-Essentials, IONOS-Marketplace und IONOS-SSO installiert/aktiv. **Diese Pluginpräsenz ist kein unabhängiger Beleg für einen bestimmten IONOS-Vertrag, Host, SSH-Account oder Wiederherstellungspunkt.**

## Bewusst NICHT als verifiziert markieren

- **IONOS Vertrags-/Storage-Identität** (getrennt von Dreamloud), SSH-Server, bestätigter SSH-ED25519-Host-Key, SFTP-vs.-Shell-Root-Zuordnung und tatsächlicher `wp-config.php`-Pfad. Der frühere Auditcode benutzt u.a. historische `/public`-Mappings; **keine neue Remote-SSH-Verbindung** wurde versucht.
- **Konkretes Datenbankschema**, DB-Nutzer/Host, DB-Tabellenliste/-Engines (InnoDB/MyISAM), Triggers, Views, Routinen, DDL-/Transaction-Eigenschaften, DB-Leseberechtigung und DB-Speichergröße. MariaDB **Serverversion** ist bekannt, **Tabellenstruktur** nicht.
- **WordPress-Datei-/Uploadgrößen**, Dateisystemänderungsrate, mögliche externe Schreibzugriffe, zusammenpassender Dateien+DB-Snapshot, Quiescence, Wartungs-/DDL-Sperren.
- **IONOS-Provider-Backups**, Wiederherstellungszeitpunkte, Retention, Zuweisung und belastbare Recovery Points, freien SSD-Platz oder Restore-Test.
- Existenz/Funktion eines **externen Cronjobs** trotz `DISABLE_WP_CRON=true`. Zwei überfällige Events sind ein Abklärungsbedarf, aber kein Beweis eines Ausfalls oder einer fehlenden externen Planungsfunktion.
- Verfügbarkeit von SSH-/SFTP-/MariaDB-CLI durch die **WordPress-Connector-Schnittstelle** und eine verifizierte sichere Möglichkeit, minimale IONOS-Rechte nachzuweisen.
- Keine Eignung für einen echten PGP05-DB+Files-Capture oder produktiven GitHub-Release abgenommen.

## Interpretation und nächster Freigabehold

1. **Read-only WordPress-Metadatencheck bestanden (Teilabnahme):** Installation `orgavela.com`, WordPress 7.1.3, PHP 8.3.35, MariaDB-Server 11.8.8, aktives UOP Core Alpha.2, SSL und Stand der Cron-Konfiguration belegbar.
2. **Host-/DB-Quellenidentität und konsistenter Backup-Snapshot: weiterhin OFFEN**, weil der verbundene WordPress-Metadata-Kanal keine unabhängige IONOS-Account/SSH/Webroot/Tabellenengine-Verifikation leistet. **Kein** produktiver Realdaten-Capture.
3. **Cron:** `DISABLE_WP_CRON=true`, zwei überfällige WordPress-Events, externer Job **ungeklärt**. Nur zur Betreiberinformation und für separate autorisierte Diagnose; keine automatische Scheduler-/WordPress-Konfigurationsänderung.
4. **Nächster klarer Entscheidungspunkt:** Falls die noch fehlenden IONOS-SSH-/DB-Metadaten direkt gelesen werden sollen, ist eine **neue spezifische Betreiberfreigabe für diesen Kanal** erforderlich. Die aktuelle Freigabe zum WordPress-Read-only-Check ist dafür nicht stillschweigend erweitert. Die CI-/Backup-Run- und Produktiv-Capture-/Offsite-Freigaben bleiben ausgeschlossen.
5. PR #28–#31 bleiben Draft/unmerged; keine neuen Tokens, Keys, Verträge oder CI-Jobs. PGP04 synthetisch PASS. PGP05 echte Sicherung, echter Isolations-Restore, automatisches Scheduling GESPERRT.

**Formale V1-Werte unverändert:** 62/71 historische Codingpakete, M0–M7 0/8, P4 0/4, R01–R18 0/18.


## Nachtrag Betreiber-Screenshots: IONOS-Hosting / phpMyAdmin / Domain

**21.10.2026, 14:04–14:07 Ortszeit der vom Betreiber aufgenommenen Mobilgerät-Screenshots; Evidenzart: vom Betreiber direkt gelieferte IONOS-Oberflächenansichten, keine durch uns neu ausgeführte IONOS-Connector-/SSH-/SQL-Abfrage.** Drei Screenshots wurden ausschließlich visuell bewertet; die Bilder selbst, technische Server-FQDN, Kontaktdaten und Datenbankkennungen wurden **nicht** nach GitHub kopiert.

| Sichtbarer IONOS-Bereich | Beobachtung | Begrenzung |
| --- | --- | --- |
| WordPress-Erweiterte Einstellungen / Webspace | `0,19 GB von 49 GB` belegt | Momentaufnahme; noch keine gesicherte Aussage zu tatsächlich erforderlicher Backup-Archivgröße |
| Zweiter Belegungszähler | `13.799 von 262.144` genutzt | Einheit in der Aufnahme nicht ausdrücklich benannt; daher **nicht** ohne weiteren Beleg als Inode-Anzahl klassifiziert |
| Datenbank-Ressource innerhalb IONOS-Einstellungen | `Maria DB v11.8`, `0,01 GB von 2 GB` belegt | Nicht die konkrete WP-Datenbank oder deren Tabellen-/Engine-Liste nachgewiesen; Größen gerundet |
| Verwaltungsfunktionen | `Webspace Explorer`, `SFTP & SSH – Konten verwalten`, `phpMyAdmin öffnen` | UI-Optionen sichtbar, **keine** Dateilesung, Loginweitergabe, Schreibaktion oder SSH-Verbindung ausgeführt |
| phpMyAdmin | Version `4.9.11`; Server-FQDN oben erkennbar, **bewusst nicht in GitHub notiert**; Ansicht zeigt allgemeine Einstellungen | phpMyAdmin ist erreichbar, aber **keine** Datenbankliste, `information_schema`-/Engine-Abfrage oder Zuordnung zur WP-Konfiguration geprüft |
| Domains & SSL – `orgavela.com` | Verwendungsart `Durch IONOS verwaltet`, Ziel `WordPress`, SSL-Zertifikat `Zugewiesen` | Domain-Ziel ist belegt; dies identifiziert nicht beweissicher SSH-Root, konkrete Datenbank, Hosting-Vertrag oder Backup-Pfad |
| Domain Guard | kostenpflichtiges Zusatzangebot eingeblendet | **Keine** Bestellung erforderlich/erteilt, von PGP05-Backup getrennt |

**Aktualisierte Evidenzbewertung:** Die Screenshots ergänzen die durch den OrgaVela-WordPress-Connector belegte Webinstanz um einen IONOS-UI-Beleg zu Domain-Ziel und Ressourcenbelegung. Sie verbessern die Plausibilität der Hosting-Zuordnung. **Noch nicht** belegbar sind der Name der tatsächlich von dieser WordPress-Installation verwendeten Datenbank, Engines ihrer Tabellen (InnoDB/MyISAM), SFTP-/SSH-Root, Host-Key-Fingerprint oder transaktionskonsistente DB+FS-Sicherung.

**Nächster risikoarmer Schritt durch den Betreiber:** In phpMyAdmin zunächst **„Datenbanken“** öffnen und ausschließlich die Übersichtsseite ansehen/screenshotten. Datenbankkennungen, Hostnamen und sonstige Infrastrukturkennungen auf geteilten Screenshots möglichst teilweise maskieren. Danach gegebenenfalls die Registerkarte **„Struktur“** der korrekten WordPress-Datenbank betrachten, ohne Daten-Tabellen oder `SQL` zu öffnen, keine Downloads, Export-/Import-, Änderungs- oder Backupaktionen. Die Zuordnung zu WordPress bleibt dabei mit anderen Belegen abzugleichen.

**Freigaben unverändert:** Diese UI-Screenshots sind kein Go für neue Connector-SSH-/SFTP-/SQL-Abfragen, echte Datensicherungen, CI, Release-Writer, private Schlüssel, Scheduler oder kostenpflichtige Produkte. PGP05 Produktivgates OFFEN. Formale V1-Kennzahlen unverändert.
