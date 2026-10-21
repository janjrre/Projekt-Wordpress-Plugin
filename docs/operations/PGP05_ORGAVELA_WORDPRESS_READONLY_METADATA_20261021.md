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


## Nachtrag Betreiber-Screenshot: phpMyAdmin-Datenbankübersicht 14:11

**Evidenzart:** vom Betreiber geteilter Screenshot der phpMyAdmin-Ansicht `Datenbanken`; keine neue Verbindung, kein SQL-Kommando und keine PHP-Datei- oder Datenbankzeilenlesung durch den Assistenten. Screenshot und explizite Server-/Datenbank-IDs wurden nicht ins GitHub-Repo kopiert.

| Sichtbare Eigenschaft | Aussage | Beweisgrenze |
| --- | --- | --- |
| `Datenbanken` | **1 Datenbank** in der aktuell authentifizierten phpMyAdmin-Verbindung aufgelistet | Andere IONOS-Datenbanken oder andere DB-Konten nicht ausgeschlossen |
| Datenbank-Kollation | **`utf8mb3_general_ci`** | Steht nicht im Widerspruch zur zuvor angezeigten anderen Server-Connection-/Default-Kollation `utf8mb4_unicode_520_ci`; Tabelle-/Spaltenzeichensätze bleiben zu prüfen |
| IONOS-Datenbankservername | Auf Screenshot sichtbar, absichtlich aus öffentlicher Dokumentation ausgelassen | Keine WP-zu-Datenbank-Zuordnung oder Host-Key-Pinning |
| `Datenbankstatistiken aktivieren` | UI-Warnung nennt möglichen **starken Traffic zwischen Web-/MySQL-Server** | Ausdrücklich **nicht aktivieren**; keine zusätzliche Abfrage ausgelöst |

**Nächste Betreiber-UI-Aktion:** ausschließlich auf den blauen Datenbanknamen tippen, im Bereich `Struktur` die **Metadaten** der Tabelle-/Typ-/Engine-Spalten ansehen und den Screenshot auf Wunsch mit maskierter Datenbank-/Hostkennung teilen. Nicht `SQL`, `Anzeigen` (Zeilen), `Exportieren`, `Importieren`, `Operationen`, `Rechte überprüfen` oder `Datenbankstatistiken aktivieren` benutzen. Die Tabellenstruktur darf zunächst nur mit allgemeinen WordPress-Kennzeichen plausibilisiert werden; der echte Zusammenhang mit WordPress muss über weitere vertrauenswürdige Identitätsbelege abgesichert werden.

**Status:** Ein DB-Schema ist sichtbar und seine Kollation bekannt. **Noch kein Beweis**, dass genau dieses Schema das aktive WordPress-Schema ist, dass alle Tabellen InnoDB verwenden oder dass konsistente echte DB+Datei-Snapshots möglich sind. Keine Änderung am Masterplan-v2.43-Gate oder Produktionsfreigaben.


## Nachtrag phpMyAdmin-Strukturansicht mit 52 Tabellen (Betreiber-Screenshot)

**Zeitpunkt:** 21.10.2026, nach der zuvor dokumentierten 14:11-Datenbankübersicht. **Evidenzart:** ausschließlich direkt vom Betreiber übergebene phpMyAdmin-`Struktur`-Ansicht. Der Assistent hat phpMyAdmin **nicht** selbst bedient, keine Tabellendaten abgerufen, keine SQL-Abfrage ausgeführt und keine der Bildschirmkopien ins Repository hochgeladen.

**Sichtbar und bestätigt:**
- phpMyAdmin-Strukturseite meldet **52 Tabellen** in dem vom Betreiber zuvor geöffneten einzelnen Schema.
- Namensmuster typischer WordPress-Tabellen (`*_options`, `*_posts`, `*_users`, `*_comments`, `*_terms`) sowie UOP-Core-spezifischer Tabellen (z. B. `*_uop_organizations`, `*_uop_persons`, `*_uop_consent_records`, `*_uop_registrations`, `*_uop_waitlist_entries`), außerdem Action-Scheduler-Tabellen und MCP-Plugin-Metadaten. Der konkrete WordPress-Tabellenprefix und Datenbank-/Serverkennungen sind hier **bewusst redigiert**.
- Dies ist eine **starke strukturelle Plausibilisierung**, dass die Tabellen zur aktiven OrgaVela-/UOP-Core-Installation gehören; noch kein unabhängiger Abgleich mit der effektiv verwendeten `wp-config.php` oder der tatsächlichen Konfigurationsdatei.

**Nicht sichtbar:** Die Aufnahme schneidet rechts nach Spalte `Aktion` ab; `Typ`/`Engine` ist darauf **nicht** lesbar. Folglich **nicht** als InnoDB-only bestätigen oder einen transaktionskonsistenten DB-Dump als zulässig markieren. Tabelleninhalte, Benutzerdaten, Backup-Erstellung und Datenbankgrößen pro Tabelle wurden nicht eingesehen oder angefordert.

**Sicherer nächster Betreiber-UI-Schritt:** Auf derselben phpMyAdmin-`Struktur`-Übersichtsseite **nur horizontal scrollen** (gegebenenfalls iPhone-Querformat oder Safari `Desktop-Website anfordern`) und den sichtbaren Abschnitt mit `Typ`/`Engine` für die Tabellen fotografieren. Keine Checkboxen, `SQL`, `Anzeigen`, `Operationen`, `Exportieren` oder `Datenbankstatistiken aktivieren` verwenden. Maskieren von Server- und DB-Identifikatoren empfohlen. Wenn `Typ` auch dann nicht angezeigt wird, zunächst ohne Änderungen zurückmelden.

**Status:** Metadatenbeleg **52 Tabellen + WordPress/UOP-Namensmuster**, weiter kein Engine-Gate bestanden, kein echtes DB-/Filesystem-Capture, keine neue Produktionsfreigabe. Masterplan **v2.43** und formale Release-Counts unverändert.


## Nachtrag PDF-Strukturexport 14:16: sämtliche 52 Tabellen InnoDB

**Datum/Evidenz:** 21.10.2026, gegen 14:16 gemäß vierseitigem phpMyAdmin-Struktur-Ausdruck, direkt vom Betreiber in die Konversation hochgeladen. **Die unredigierte PDF enthält Server-, Datenbank- und Tabellenprefixkennungen; das Original wurde bewusst NICHT in GitHub hochgeladen, zitiert oder verlinkt.** Für den Repository-Nachweis werden ausschließlich aggregierte und sanitierte technische Feststellungen erfasst. Kein eigener SSH-, SQL-, phpMyAdmin- oder Serverzugriff des Assistenten.

| Sichtbares Metadatum | Ergebnis aus den vier PDF-Seiten | Belastbarkeit |
| --- | --- | --- |
| Strukturansicht einer IONOS-MariaDB-Datenbank | **52 Tabellen** | Vierseitige vom Betreiber gesicherte Momentaufnahme derselben bereits gezeigten Datenbank |
| Typ/Storage Engine | **Alle 52 Tabellen = InnoDB** (jeweils die Engine-Spalte, Seiten 1–4) | **ENGINE-METADATENPRÜFUNG für dieses sichtbare Schema BESTANDEN**. Kein MyISAM-Eintrag in der vollständigen Liste. Noch keine unabhängige Identifikation via tatsächlich geladener WordPress-Konfigurationsdatei |
| Tabellenkollation | Durchgehend **utf8mb4_unicode_520_ci** | Der Datenbank-Default in der Zusammenfassungszeile lautet hingegen **utf8mb3_general_ci**; das ist kein Widerspruch, da Tabellen- und Schema-Kollation getrennt sein können |
| Angezeigte Datensätze | **2.929 gesamt** | phpMyAdmin-Zählung, bei InnoDB potentiell eine Schätzung; keine echten Datensätze/Tabellenzeilen gelesen |
| Gesamtgröße (phpMyAdmin) | **4,2 MiB** | Nur tabellarische Datenbank-Speicherbelegung, kein vollständiger WordPress-Dateien-/Backupumfang |
| Überhang in Zusammenfassungszeile | **0 B** | Kein Nachweis von DB-/WP-Datei-Konsistenz oder Backupfähigkeit |
| Strukturplausibilität | WordPress-Core, UOP-Core, Action-Scheduler, MCP-Plugin-Tabellengruppen | Starke Plausibilisierung der OrgaVela-Zuordnung, aber **noch kein** unabhängiger Proof von Datenbankname/-Host in verwendeter WP-Konfiguration |

**Ergebnis / wesentliche Gate-Trennung:** Für die im PDF sichtbare Datenbank ist die bisher offene Frage nach nichttransaktionalen **MyISAM-Tabellen beantwortet: 52/52 InnoDB**. Ein auf InnoDB basierender konsistenter transaktionaler *Datenbank*-Snapshot ist damit grundsätzlich technisch plausibel (geeigneter Dump-/Transaktionsmodus vorausgesetzt). **KEIN PASS** für vollständigen Backup-Source-Identity-/Quiescence-/DDL-/WP-Files-Snapshot: `--single-transaction` allein verhindert keine gleichzeitige DDL und garantiert keinen mit Dateien zeitgleichen Wiederherstellungspunkt. Weder aktive WordPress-Datenbankkonfiguration noch Quellroot, Credentials/Rechte, externer Cron, Schreibbarriere, echt verschlüsselter Capture, Release oder System-Restore wurden getestet.

**Nächste sinnvolle risikoarme Betreiberaktion:** Nun nicht mehr zum Tabellen-Engine-Scrollen zurückkehren. Als Nächstes die bereits vorhandene IONOS-Oberfläche **SFTP & SSH → Konten verwalten** nur in der Übersicht öffnen und (nur wenn möglich) die Hosting-/Pfadzuordnung beurteilen. **Keine** Passwörter, privaten Schlüssel, neuen Konten oder Tokens anzeigen/anlegen, keinen Verbindungstest über SSH starten und keine aufwändigen Statistiken aktivieren. Server-/Vertrags-IDs auf neuen Bildern maskieren. Direkter neuer SSH-/SQL-Connectorlauf bleibt bis gesonderter Freigabe untersagt.

**Projektstatus:** Nur die Engine-Metadaten dieses sichtbaren Schemas sind PASS. PGP05 reale Backup-Quellidentität und kombinierte WordPress-Dateien/MariaDB-Recovery-Konsistenz bleiben BLOCKED. Formaler V1-Meilensteinzähler wird nicht erhöht.
