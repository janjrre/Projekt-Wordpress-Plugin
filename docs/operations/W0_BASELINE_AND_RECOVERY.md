# W0 – OrgaVela-Bestandsaufnahme, Schutzmaßnahmen und Restore-Freigabe

**Status:** 12.10.2026 – W0 **gestartet, nicht abgenommen**. Scope gemäß [normativem V1-Masterplan](V1_ACCEPTANCE_PLAN.md), W0 sowie O01/O02/O03/O06. [Fortschrittssnapshot](V1_PROGRESS.json). **Keine Teilnehmer-/Guardian-Daten, keine Dreamloud-Arbeiten, keine neuen kostenpflichtigen Verträge.**

## 1. Verbindliches Ziel und Definition of Done

W0 darf erst als **ABGENOMMEN** gelten, wenn alle vier Nachweise vorliegen:

| ID | Beleg | Ist 12.10.2026 |
|---|---|---|
| O01 – Isolation und Zugriff | `orgavela.com` kontrolliertes UOP-Testziel; echtes Least Privilege des SFTP-Benutzers nachgewiesen; Zugriff auf andere Projektpfade verweigert | **Teilweise:** Domain, Serverfingerprint und `/public` sicher fixiert; SFTP-Account kann aber alle Webspace-Ordner sehen |
| O02 – Physisches Datenbankschema | Migration-State `complete` mit tatsächlichem Schema-Inspector, Tabellen/Indizes/FKs-als-Repositories und V2-Age-Attestation-Tabellen übereinstimmend; keine Drift | **Teilweise:** `uop_db_version=2` / `uop_data_version=2` bestätigt. Keine tatsächliche Tabellen-/Indexanalyse per vorhandener WordPress-MCP-Funktion möglich |
| O03 – Sicherung und Wiederherstellung | Bestand und Integrität einer **Datei- und DB-Sicherung**, danach **tatsächlicher Restore auf isoliertem, nicht erreichbarem Test-Klon**; dokumentierte DB-/WP-Prefix-/Version-/Schema-Konsistenz und Recovery-Zeit | **Offen:** kein Backup-/Restore-Beleg; kein Download/DB-Dump über Chat |
| O06 – Datenschutz/geschützte Testumgebung | Schutz gegen unbefugte Nutzung und unfreiwillige Indexierung; Aufklärung/Retention/Logs/Consent passend zu tatsächlichen Diensten und synthetische Fixtures | **Blockiert:** WordPress-Umgebung meldet `production`, `discourage_search_engines=false`; Scope und erforderliche nichtöffentliche UOP-Pfade noch festzulegen |

**W0-Ergebnis ist kein Deploy-/V1-GO.** Vollständige UOP-Migrationen, Updates, Gast-Opt-in, echter Mailversand und reale Teilnehmerdaten bleiben bis zu den betroffenen Gates gesperrt.

## 2. Tatsächlich erhobene, nur lesende Live-Daten

WordPress-MCP-Anbindung **ausschließlich** für OrgaVela (`link_6acc9fc926c88191847cc839a24f0b87`), nicht Dreamloud:

| Read-only Prüfung | Ausleseergebnis |
|---|---|
| Plugin `uop-core/uop-core.php` | Name **UOP Core**, `0.1.0-alpha.2`, **active**, keine automatische Aktualisierung |
| `core__get-environment-info` | `environment=production`, WordPress **7.1.3**, PHP **8.3.33**, MariaDB **11.8.8** |
| DB/WordPress-Kompatibilität aus vorherigem Live-Audit | `utf8mb4`, HTTPS bestätigt |
| `uop_db_version` | `2` (Read-only WordPress-Option) |
| `uop_migration_status` | Existiert, enthält **keinen mit der verfügbaren Ability lesbaren reinen Text**; `complete` nicht bestätigt |
| `mosmcp__cron-test` | `DISABLE_WP_CRON=true`, HTTP-Loopback **200**, insgesamt **5** überfällige WordPress-Aufgaben; **nicht** behaupten, alle 5 seien UOP-Aufgaben oder externer Cron sei sicher kaputt |
| `mosmcp__site-get-search-visibility` | `discourage_search_engines=false` |
| SFTP | [Read-only Probe #38239184433](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38239184433) erfolgreich auf `/public`, ohne Upload |
| Erstinstallation | [Install #38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035) erfolgreich, Aktivierung anschließend separat verifiziert |

### O02 – unabhängige Schema-Health-Prüfung

Die öffentliche OrgaVela-WordPress-MCP-Oberfläche stellt derzeit **keine** deklarierte Fähigkeit für direkte Tabellen-/Indexabfrage oder den kompletten WP-Site-Health-Resultatbaum bereit. Vermeide insbesondere:

- einen öffentlichen diagnostischen PHP-Endpunkt auf `/public`;
- DB-Credentials in öffentlichen Tests, PRs oder Chats;
- Diagnoseskripte als WordPress-Beiträge, Mediendateien oder Plugins ohne Backup;
- die Behauptung, gespeicherte Version `2` belege physisch 29 fehlerfreie Tabellen.

**Nächster sicherer Nachweis:** Betreiber in `https://orgavela.com/wp-admin/site-health.php` → UOP-Schema-Diagnose öffnen (berechtigter Admin), **nur** Status „good/critical“ und ggf. **bereinigte** Fehlermeldung liefern; oder isoliert via read-only DB-Account/phpMyAdmin (keine Geheimnisse im Chat) Tabellen- und Indexmanifest gegen Source vergleichen. Quelle für die erwarteten Tabellen/Indizes ist das immutable Manifest des aktiven Source-SHA `4f2c4df800caf1e5708f05cce9536a7afb34aaa0`; V2 enthält zusätzlich `age_attestations`. Für belastbaren Beleg sind tatsächliche Struktur-/Indexwerte notwendig.

## 3. O03 – sichere Backups und Restore, ohne Produktionsdatenverlust

### Schritt A – Hostingtyp und vorhandene IONOS-Sicherung feststellen

Betreiber öffnet **IONOS → Menü → Web-Projekte → OrgaVela → Website verwalten → Backups**, **falls** dies im gebuchten WordPress-Produkt angeboten wird. [Offizielle IONOS-Anleitung](https://www.ionos.de/hilfe/wordpress/wordpress-installation-und-datenbank-ueber-ionos-konto-wiederherstellen/).

- Nur die **Liste** und Zeitpunkte verfügbarer Backups sichten und dokumentieren, **nicht „Wiederherstellen“ klicken**.
- Bei manchen IONOS-WordPress-Tarifen dokumentiert IONOS tägliche Sicherungen für maximal sieben Tage; bei allgemeinen Webhosting-Systembackups können andere Fristen gelten. **Keine Pauschalbehauptung über den konkreten Tarif** ohne Kontoprüfung.
- Eine komplette Wiederherstellung am aktiven OrgaVela-Projekt würde zwischenzeitliche Daten überschreiben und die Website vorübergehend unzugänglich machen; **untersagt für W0 ohne gesonderten Clone-/Restoreplan**.

### Schritt B – unabhängiger manueller Backup-Bestand

- Datenbank über den zu **OrgaVela** gehörenden IONOS-phpMyAdmin-Zugang als SQL-Export sichern und **privat** ablegen. [IONOS phpMyAdmin-Backup](https://www.ionos.de/hilfe/hosting/mysql-datenbank-sichern-wiederherstellen/datenbanken-sichern-mit-phpmyadmin/).
- Dateibestand von `/public` **rein lesend** über SFTP oder das offizielle Webspace-Werkzeug exportieren, dabei Datensicherung nicht innerhalb eines öffentlich erreichbaren WordPress-Verzeichnisses zurücklassen. [IONOS manuelle Backups](https://www.ionos.de/hilfe/hosting/dateien-sichern-wiederherstellen/webspace-und-datenbanken-manuell-sichern-backups-erstellen/).
- Zeitstempel, exakten Scope (DB + Dateien), getrennte Versionsangaben und Prüfsummen lokal/private Datei erfassen. Backups **niemals** in das öffentliche GitHub-Repository oder Chatdateien mit Secrets laden.

### Schritt C – tatsächliche Restore-Probe

- Erst nach isolierter temporärer **nicht öffentlich zugänglicher** WordPress-/MariaDB-Testumgebung (z. B. temporärer GitHub-CI-Container; keine echte externe Teilnehmermail).
- Dateiarchiv und SQL-Backup in der **isolierten Kopie** wiederherstellen; URLs/Auth-Secrets für den Clone sicher neutralisieren und niemals E-Mails oder HTTP-Callbacks an reale Adressen zulassen.
- Plugin, Schemazustand, Indizes, Registrierungssnapshot, Aus-/Einloggen und ein minimales synthetisches Testset prüfen.
- Exakte Wiederherstellungsdauer, Ergebnis und Fehler im privaten Betriebstracker/abstrahiert im öffentlichen Worklog protokollieren.
- **Kein Backup ist „restore-getestet“, solange nur eine Datei existiert oder eine Restore-Schaltfläche sichtbar ist.**

## 4. O06 – Testbetrieb vs. OrgaVela-Website

- Die WP-Installation liefert `environment=production`, obwohl sie im UOP-Projekt als **Staging-Testsite** genutzt wird. `production` ist ein WordPress-Betriebskennzeichen und kein Beleg produktiver UOP-Benutzer.
- Suchmaschinenabschreckung ist aus. Ein allgemeines **Noindex für die gesamte OrgaVela-Domain** kann eine später öffentliche Produkt-/Marketingseite beeinträchtigen. **Deshalb vorerst keine globale WordPress-Sichtbarkeit ohne Betreiberentscheid ändern.**
- Bevor Gast-/Mitglieder-Testseiten entstehen: UOP-Testseiten und eventuelle REST-Routen per authentifiziertem Zugriff oder separat passwortgeschützter Umgebung begrenzen; für private Daten `Cache-Control: private/no-store`, starke CSRF-/REST-/Policy-Guards, begrenzte Testuser und Cleanup. Noindex allein ist kein Zugriffsschutz.
- Öffentliche Produktseiten und UOP-Staging-Prozesse getrennt betrachten; eindeutig kenntlich machen, dass Testanmeldungen **nicht für reale Personen** bestimmt sind.
- Datenschutz/-Impressum/-Dateninventar für tatsächliche Hosting-/Mail-/Analytics-Dienste überprüfen, ohne allgemeine Universal-Rechtstexte zu erfinden.

## 5. W1-Vorgriff: Cron-Sichtung, noch kein W0-Exit

IONOS stellt im Webhosting einen **HTTP-Aufruf-Cronjob** über **Hosting → Cronjobs → Verwalten** bereit, z. B. `https://orgavela.com/wp-cron.php`. [IONOS Cron-Einrichtung](https://www.ionos.de/hilfe/hosting/cronjobs/cronjobs-erstellen-und-verwalten-im-ionos-konto/). IONOS empfiehlt mindestens **5 Minuten Abstand** zwischen Jobs und nennt für Konto-Cron-Requests ein Limit von **60 Sekunden**. [IONOS Best Practices](https://www.ionos.de/hilfe/hosting/cronjobs/best-practices-fuer-die-einrichtung-von-cronjobs/).

**Nicht ungeprüft starten:** Erst den vorhandenen externen Cron-Kalender auf **OrgaVela** prüfen und Doppel-Runner vermeiden. Ein Cron-Aufruf löst potenziell echte WordPress-Queue-Aktionen aus; ohne Backup und Prüfung der Mail-/Testdaten-/Worker-Konfiguration keine manuelle Triggerung nur zur kosmetischen Beseitigung des Rückstands. Cron-Abnahme ist W1/O04, wird durch diesen W0-Bericht **nicht** vorweggenommen.

## 6. Konkrete W0-Arbeitspakete und Verantwortlichkeit

| Task | Nächste Ausführung | Benötigte Evidenz | Zuweisung |
|---|---|---|---|
| **W0.1 Schema** | UOP-Site-Health-„schema current“ und separate physische DB-Indexprüfung | bereinigter Status + geprüfter Tabellen/Indexbericht | Betreiber für WP-Admin-Zugang; Entwicklung für Manifest/Tests |
| **W0.2 Backup** | IONOS-Backup-Liste und privaten DB-/Datei-Export verifizieren | Zeitstempel/Sicherungsumfang ohne Secrets | Betreiber/IONOS; Assistenz dokumentiert |
| **W0.3 Restore** | isolierter, sicherer Clone und Restore-Probe | Testprotokoll mit Schema und Versionsvergleich | Entwicklung mit freigegebener privater Sicherung |
| **W0.4 Zugriff** | SFTP-Benutzerrechte bei IONOS auf nur OrgaVela einschränken | Negativtest andere Webprojekte (ohne Zugriff auf Dreamloud) | Betreiber/IONOS |
| **W0.5 Privatheit** | öffentliches Produktmarketing vs. UOP-Testseite abstimmen | beschlossene Seiten-/Zugriffs-/Noindex-Matrix | Betreiber; Entwicklung |
| **W0.6 Bericht** | alle Belege in V1_PROGRESS und Issue #15 aktualisieren, unvollständige Gates nicht als grün zählen | Commit + Teststatus | Assistenz |

**Stop-Gates:** Vor W0-Abnahme keine neue IONOS-DB-Migration, keine Gäste-/Waitlist-/Mail-Opt-ins, kein echtes Teilnehmerprofil. Für ein unverändert aktives Alpha werden keine stillen Änderungen vorgenommen.

## 7. Begleitender CI-Befund – vorgezogene O09-Triage (W2)

PR #11 HEAD `1b986587` hatte [PR Foundation #38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475) (1/8 Job rot: Aktivierungs-Browser-Zeitüberschreitung PHP8.5/MySQL8) und [PR HTTPS #38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383) mit zwei konkreten Fehlern:

1. Ein WordPress-Admin-Navigations-Untermenü fing beim zweiten Tabellenzeilen-Klick den Pointer ab; Playwright wartete 30s. Testanpassung `710b413e`: Mauspointer zuerst in freien Inhaltsbereich bewegen, **anschließend echten .click() ohne force** ausführen und selektierten Status prüfen. **Kein Deaktivieren von Tests.**
2. Ein 44px-Button wurde als 40px gemessen; Quellstylesheet fordert bereits `min-height:44px`, daher CSS-Ausfall/Specificity untersuchen. Testanpassung `7892a258`: exakt einmal nur bei nachweislich **nicht geladenem** UOP-Stylesheet neu laden; falls CSS geladen aber 40px, weiterhin strikt **fail**. **Kein Absenken der WCAG-internen 44px-Grenze.**
3. PHP8.5/MySQL8: WordPress-`plugins.php?action=deactivate` navigiert 30s ohne DOMContentLoaded. Log beweist die Verzögerung, aber noch nicht eine PHP-Exception/Deadlock-Ursache. **Keine kosmetische Timeouterhöhung**, bevor neuer CI-Run und Request-/DB-/Serverdiagnostik vorliegen.

**Aktueller PR-Branch:** `feat/p4-age-evidence` auf Source-Commit `7892a25894c184a624ab4ebea13aab0e1a1c88dd`; jüngere GitHub-Push-/PR-Workflows wurden ausgelöst; deren Erfolg **noch nicht belegt**. W0-Fortschritt und V1-Gate bleiben bis grünem aktuellen HEAD unverändert.
