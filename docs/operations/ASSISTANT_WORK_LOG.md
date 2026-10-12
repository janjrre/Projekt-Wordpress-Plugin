# UOP Core – fortlaufendes Assistenz-Arbeitsprotokoll

**Status:** 12.10.2026. Öffentlich einsehbares, personenbezogenes-freies Projektjournal. Kanonischer Ort im Default-Branch `main`; laufende Arbeitsbranches sollen Änderungen ebenfalls enthalten oder hierher zurückführen.

**Verbindliche Regel:** [AGENTS.md](../../AGENTS.md). Jede neue UOP-Anfrage und jede bearbeitete Folgeaufgabe als neue **chronologische, append-only** Eintragung mit Arbeitsauftrag, Begründungszusammenfassung, Umsetzung, Tests, Blockern und nächstem Schritt protokollieren. Nicht mit einem wortgetreuen Chatarchiv oder verborgenen Modell-Gedankengängen verwechseln. Keine Secrets, personenbezogenen Inhalte oder Rohdaten aus dem Chat hochladen.

---

## 2026-10-12 – 01: Projektübernahme und Issue #7

**Auftrag:** Stand des eigenständigen WordPress-Plugins UOP Core übernehmen, im Repository weiterentwickeln, Issue #7 technisch und fachlich abnehmen; anschließend #8–#10, Integration und V1-Roadmap. Freigaben: selbstständiges Entwickeln, Tests, Commits und bei erfüllten Gates Merges/Veröffentlichungen; **keine** finanziellen Verpflichtungen; **keine** Dreamloud-Nutzung.

**Ausgangslage:** Gestapelte PRs #1–#6, Draft-PR #11, Branch `feat/p4-age-evidence`, ursprünglicher HEAD `8eb1613d5383ca3b24685cc57474df3e5c50b84d`. Datenbank-V2 mit `age_attestations`. Bestehende CI-Baseline erfolgreich.

**Entscheidung/Gründe:** Positive Altersatteste bei unveröffentlichten Events und unzureichenden aktuellen Leserechten dürfen nicht zur Freischaltung führen. Die Altersprüfung ist von Guardian-/Consent-Rechten getrennt und bedarf später einer fachlichen Betreiberfreigabe.

**Umsetzung:** REST-Leseberechtigung, Eventstatus-Prüfung und serverseitige Eligibility-Abwehr geschärft; Adminoberfläche berechtigungsgebunden und verständlicher; Tests und Betriebsanleitung ergänzt. Zwischenfehler durch Formatierung und Servicecontainer-Zuordnung nach CI-Diagnose korrigiert. Sichtbarer stabiler Zwischen-HEAD `11ab3a066e361209b3397d16335c3c67ebbe4bf7`; Issues #7–#10 blieben offen, PR #11 Draft.

**Tests:** Auf diesem Zwischen-HEAD vollständige Foundation-Matrix 8/8 einschließlich sechs PHP-/DB-Kombinationen und HTTPS-/Mailpit-E2E grün (Runs 38220921549, 38220921887).

**Offen:** Vollständige Altersprüf-/Portalabnahme, belastbares externes Verifikationsverfahren, Retention/Betreiberfreigabe; #8–#10 separat. Keine Produktionsfreigabe.

## 2026-10-12 – 02: Namens- und Webauftritt

**Auftrag:** Einen eingängigen Plugin- und Websitenamen für eigenständiges Produkt festlegen; günstige Website erwogen.

**Ansätze/Entscheidung:** Als Marken-/Webpräferenz wurde **OrgaVela** / `orgavela.de` und die mögliche Bezeichnung **OrgaVela Core** vorgeschlagen; bestehende UOP-Codekennungen wegen Migrations-/Kompatibilitätsrisiko zunächst unverändert belassen. Verfügbarkeit und Markenrechte sind nicht abschließend geklärt.

**Umsetzung/Status:** Produktname vorgeschlagen, **keine** kostenpflichtige Domainbestellung/Vertragsauslösung und keine technische Umbenennung durchgeführt.

## 2026-10-12 – 03: Neue WordPress.com-Testwebsite genehmigt

**Auftrag:** Eine separate WordPress.com-Seite für UOP verwenden. Keine Nutzung von Dreamloud.

**Feststellung:** `orgavela.wordpress.com` war zunächst eine WordPress.com-`simple`-Site, die Plugins nicht verwalten konnte. Hostingumzug war laut WordPress.com ohne Upgrade möglich; Betreiber nahm ihn vor. Danach wurde `https://orgavela.wpcomstaging.com` als `atomic` mit zugänglicher Pluginverwaltung und Coming-Soon-Status verifiziert.

**Entscheidung/Gründe:** Eigenständige UOP-Installation und synthetische Tests sind erlaubt; keine kostenpflichtigen Verpflichtungen, kein Zugriff auf produktive Dreamloud-Seite.

## 2026-10-12 – 04: Plugin-Paket und Staging-Aktivierung

**Auftrag:** Plugin in der neuen OrgaVela-Umgebung installieren, aktivieren und E2E-Tests beginnen.

**Umsetzung:** CI-/staging-geprüftes `uop-core.zip` (Version 0.1.0-alpha.2) separat auf dem Branch `staging/uop-plugin-package` bereitgestellt und auf WordPress.com erfolgreich hochgeladen. UOP Core wird in der Pluginliste als **inaktiv** geführt.

**Problem und Tests:** Aktivierung wurde durch den UOP-eigenen Sicherheitscheck `utf8mb4_required` mit Fehlermeldung abgewiesen. Temporärer, rein lesender Diagnose-Plugin-Test berichtete WordPress 7.1.3, PHP 8.4.26, MariaDB 11.8.9; Server unterstützt utf8mb4, tatsächlicher WordPress- und Clientzeichensatz ist aber `latin1`, Kollation `latin1_swedish_ci`. Das Diagnose-Plugin wurde anschließend entfernt. Siehe [WordPress.com-Staging-Protokoll](orgavela-wordpress-com-staging.md) und [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12).

**Begründung:** Kein Runtime-`SET NAMES`, keine unkontrollierte Tabellenkonversion oder Umgehung der Kompatibilitätsprüfung; Gefahr von Dateninkonsistenz. Keine produktiven Tests oder realen Teilnehmerdaten.

**Offen:** Hosterunterstützten dauerhaften Wechsel der WordPress-Verbindung und Tabellen-/Datenintegrität klären, sichern und verifizieren. Erst danach aktivieren und mit synthetischen Daten testen.

## 2026-10-12 – 05: Feste Dokumentationspflicht und UTF8MB4-Korrekturauftrag

**Auftrag:** Zeichensatzproblem sicher lösen und UOP Core aktivieren; **zusätzlich** für jede künftige Chat-Arbeit den Auftrag, die Entscheidungsansätze, Umsetzungen, Testergebnisse und Fehler in GitHub dokumentieren, sodass auch bei verlorenen Chatantworten eine verlässliche Übergabe möglich ist. Diese Regel dauerhaft in GitHub verankern.

**Ausgangslage:** OrgaVela bleibt Atomic/Coming Soon; UOP-Core-Plugin 0.1.0-alpha.2 ist inaktiv. GitHub Issue #12 erfasst den tatsächlichen Hostingblocker.

**Ansätze und Gründe:** WordPress.com dokumentiert, dass SFTP/Servereinstellungen und unter passenden Berechtigungen `wp-config.php` bearbeitet werden können, warnt dabei aber vor Risiken. Die verbundene WordPress.com-Schnittstelle bietet Pluginverwaltung, jedoch **keinen** direkten Schreibzugriff auf die WordPress-DB-Konfiguration. Zeichensatz auf Plugin-Ebene temporär umzustellen reicht nicht für einen sicheren persistenten Betrieb. Vor eigentlichen Änderungen sind aktive WordPress-/Datenbankkonfiguration, Tabellenkodierungen, Backups und unterstützter Hostweg zu prüfen.

**Umsetzung:** Projektweite Instruktion [AGENTS.md](../../AGENTS.md) auf dem Default-Branch `main` angelegt (Commit `7975dba3c917ca0b3c7558f12d7b008a52c6e0e0`). Dieses Journal als kanonisches append-only-Protokoll neu eingeführt; bisherige wesentliche Aufträge und Projektergebnisse aus dem aktiven Chat und der Übergabe zusammengefasst.

**Prüfung/Status:** Regel ist als Repository-Datei hinterlegt; sie dokumentiert ausführbare Entscheidungen und Arbeitsschritte, **nicht** private Denkketten oder jede wörtliche Chatnachricht. Hostingblocker bis zu nachgewiesener unterstützter Umstellung offen, Plugin vorerst inaktiv.

**Nächste Schritte:** Unterstützte Hostingkonfiguration und ggf. Backup/Restore vor Änderung verifizieren; falls kein sicherer erlaubter Zugang erreichbar, Blocker präzise im GitHub-Issue dokumentieren statt Schutzprüfung zu deaktivieren. Branch/PRs mit zentraler Protokollregel synchron halten.

## 2026-10-12 – 06: Hoster-Supportpfad, Recovery-Prüfung und verbindlicher Übergabestatus

**Auftrag:** Fortsetzung des UTF8MB4-Reparaturauftrags und Absicherung der dauerhaften GitHub-Dokumentationspflicht.

**Durchgeführte Prüfungen:** Live-WordPress.com-Connector zeigt `orgavela.wpcomstaging.com` als Atomic/Coming Soon; UOP Core weiterhin `inactive`. Nachgeschlagene WordPress.com-Konfigurationsoperation `settings.update` unterstützt nur normale Website-Einstellungen, jedoch **nicht** `DB_CHARSET`, `DB_COLLATE`, MySQL-Tabellen oder `wp-config.php`. Read-only `backup.rewind_status`: aktiver Backupdienst, letzter erfolgreicher Backupversuch 2026-10-12 08:24 UTC, **ohne bestätigte Restore-Probe**. WordPress.com-Dokumentation bestätigt einen unterstützten SFTP/Servereinstellungen-Weg unter bestimmten Tarifen, die Zugriffsberechtigung für diese Website konnte über die verfügbaren Werkzeuge nicht geprüft werden.

**Abgewogene Optionen/Entscheidung:** Ein Plugin könnte in PHP möglicherweise die Verbindung im laufenden Request manipulieren, würde aber den Datenintegritätsvertrag unterlaufen und kein persistentes, hostunterstütztes Schema herstellen. Eine blinde Umstellung aller WordPress-Tabellen ist ohne verifizierte Kodierungen und Restore-Probe ebenfalls nicht verantwortbar. Deshalb **kein** `SET NAMES`-Workaround, **keine** Änderung an `wp-config.php` oder Bestandstabellen; Schutzprüfung bleibt absichtlich aktiv.

**Erledigte Umsetzung:** Reproduzierbaren Korrektur-/Recovery-Runbook [orgavela-utf8mb4-recovery.md](orgavela-utf8mb4-recovery.md) auf `main` angelegt (Commit `696cc48a6b2132b40aae22ae6182d9070725b457`), Daueranweisung `AGENTS.md` auch in aktive Entwicklung `feat/p4-age-evidence` übernommen (Commit `23dd84cff9262ccfff390c1a8d9c0c533a2ca387`), Verlinkungen im README des Haupt- und Entwicklungsbranches ergänzt (Commits `e56d6f8531173e99baf5c8c4417ed9a21a7f9c28` und `b41e507fac3e52135f37546ef48664e459050960`). GitHub [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12) mit Prüfungen, Verantwortlichkeiten und noch fehlenden Gates aktualisiert.

**Ergebnis:** Dokumentationsregel ist im Repository und auf dem aktiven Branch nachlesbar. Die Datenbankkonfiguration selbst konnte mit den aktuell verfügbaren WordPress.com-Werkzeugen **nicht** sicher geändert werden. UOP Core bleibt installiert, aber **inaktiv**; kein Deployment-Erfolg behauptet.

**Nächster erforderlicher externer Schritt:** In WordPress.com Hosting → OrgaVela → Einstellungen → Server prüfen, ob ohne Upgrade ein bestehender SSH/SFTP-/Datenbankzugriff verfügbar ist; andernfalls WordPress.com-Support nach einer betreiberseitig unterstützten UTF8MB4-Umstellung fragen. Vor SQL/Config-Schreibzugriff zunächst Tabellenkollationen und Backups/Restore-Fähigkeit prüfen, anschließend Korrektur und neue Aktivierung/Tests dokumentieren. Ohne sicheren Zugang bleibt Issue #12 ausdrücklich offen. Keine Dreamloud-Arbeit, keine kostenpflichtige Buchung.

## 2026-10-12 – 07: PR-Protokollpflicht, Remote-Verfügbarkeit und letzter verifizierter Zustand

**Arbeitsauftrag:** Derselbe fortgesetzte Auftrag, UOP-Datenbankzeichensatz zu beheben und die Pflichtdokumentation dauerhaft sicherzustellen.

**Ansatz/Entscheidung:** Dauerhaftes `AGENTS.md` und README-Verweise allein sind nicht immer sichtbar, wenn ein PR zur Prüfung ansteht. Deshalb ist zusätzlich ein PR-Checklisteneintrag mit Nachweispflicht sinnvoll. Ein möglicher SSH-/Serverkonfigurationszugriff über den bereits verbundenen Remote-Rechner wurde geprüft, um ohne neue Verträge den Hoster zu erreichen.

**Umsetzung:** `.github/pull_request_template.md` mit Pflichtangaben zu Arbeitsauftrag, Entscheidungsgründen, Logverweis, SHA/CI, Datenschutz, Sicherheits-Gates und offenen Schritten auf `main` (Commit `3520100c17a5222fd4ba19a78cda7606d981fdb0`) und `feat/p4-age-evidence` (Commit `7827805ca1a9059017cc6b6b1ca470c2769f3efb`) gespeichert.

**Remote-Prüfung:** Ein bereits bekannter autorisierter Desktop-Commander-Rechner ist aktuell **offline**. Keine SSH-/SFTP-Verbindung und kein Webhosting-Dateizugriff über den Rechner ausgeführt; keine Zugangsdaten beschafft. Der WordPress.com-Connector bietet weiter keinen unterstützten Schreibzugriff auf `wp-config.php` oder Datenbank-Konfiguration.

**Endzustand:** Auf OrgaVela WordPress.com bleibt `UOP Core 0.1.0-alpha.2` installiert und **inaktiv**, Website Coming Soon, keine Migration ausgeführt. Latest geprüfter PR-#11-HEAD `7827805ca1a9059017cc6b6b1ca470c2769f3efb` (Dokumentationsänderung). Neue CI-Läufe `38226299052` (Foundation), `38226299063` (HTTPS/Mailpit) und Push-Lauf `38226292812` waren beim letzten Check **noch ausstehend**; keine Erfolgsaussage für diesen HEAD. Issue #12 bleibt offen; der letzte historische technisch grüne Commit ist nicht mit diesem neuen HEAD gleichzusetzen.

**Nächste Aktion:** Bereits verfügbaren WordPress.com-SSH/SFTP-Zugriff im Hosting-Dashboard prüfen oder Betreiber-Support um sichere, persistente UTF8MB4-Konfiguration bitten. Nur nach bestätigtem Zugriff, tatsächlicher Tabellen-/Datenprüfung und Restore-Gate konfigurieren; erneut aktivieren, Schemahealth/CI/Portal testen, Ergebnisse als neuen Eintrag dokumentieren. Keine kostenpflichtige Buchung und keine Dreamloud-Änderung.


## 2026-10-12 – 08: Neuer OrgaVela-MCP-Connector und tatsächliche Berechtigungen

**Arbeitsauftrag:** Der Betreiber hat zusätzlich „MCP Server For WordPress“ auf OrgaVela verbunden. Prüfen, ob dieser Connector weitergehende Rechte zur UOP-Core-Entwicklung sowie insbesondere zur Behebung des `latin1`-/`utf8mb4`-Blockers bietet. **Keine** Änderungen an Dreamloud.

**Ausgangslage:** OrgaVela-Connector über ausdrücklich dafür eingerichtete Verbindung; WordPress.com Atomic `https://orgavela.wpcomstaging.com`. UOP Core `0.1.0-alpha.2` auf dieser Site installiert, aber wegen `utf8mb4_required` nicht aktiviert.

**Durchgeführte Checks (nur lesend):**
- `discover_abilities` meldete **278 registrierte WordPress-Abilities**; zusätzliche Suchabfragen nach `database`, `sql`, `charset`, `server`, `plugin`, `configuration`, `file`, `option`, `backup`, `wp-config`, `php` und `capability`.
- Breiter Zugriff auf WordPress-Inhalte, Benutzer-/Rollenfunktionen, Pluginaktivierung/-Updates, Theme-Editor (nur Draft/PUBLISH-Abläufe), Health-/Serverinformationen sowie WordPress-Debuglog-Diagnose. Die vollständige Liste beinhaltet auch schreibende Fähigkeiten; diese wurden **nicht** ausgeführt.
- Keine Fähigkeit für allgemeine SQL-Abfragen oder Schreibzugriff auf `wp-config.php`, `DB_CHARSET`, `DB_COLLATE` oder Datenbanktabellen gefunden; Theme-Codebearbeitung ist **kein** zulässiger Ersatz für Hostkonfiguration.
- `core__get-environment-info`: WordPress `7.1.3`, PHP `8.4.26`, MariaDB `11.8.9`.
- `mosmcp__site-get-server-info`: WordPress-Datenbankverbindung meldet `charset=latin1`, `collate=latin1_swedish_ci`; HTTPS aktiv, WordPress-Dateimodifikationen erlaubt. Dies ersetzt weder SQL-Schema-Inspektion noch den erforderlichen Nachweis einer sicheren UTF8MB4-Konversion.
- `mosmcp__plugin-get`: UOP Core `0.1.0-alpha.2` weiterhin **inactive**.
- `mosmcp__site-read-error-log`: WordPress-Debuglog nicht aktiviert; kein vorhandenes Log. Diese Einschränkung wurde nicht umgangen.
- `mosmcp__site-get-url` bestätigt ausschließlich OrgaVela.

**Entscheidung und Gründe:** Der neue Connector verbessert WordPress-Bedienung, aber eröffnet keinen unterstützten dauerhaften Host-Datenbankzugriff. Eine Aktivierung durch Deaktivieren/Manipulieren der Unicode-Schutzprüfung wäre ein Datenintegritätsrisiko. Deshalb **keine** Aktivierung, keine mutierenden Site-Operationen und kein Theme-Code-Workaround.

**Ergebnis/Status:** Neue Berechtigungen nachgewiesen; Issue #12 bleibt offen, UOP Core weiterhin inaktiv. Sichere Behebung weiterhin über unterstützten Hosting-SSH/SFTP/DB-Zugang oder WordPress.com-Support, Tabellen-/Datenanalyse und verifizierten Backup-/Restore-Pfad; danach Aktivierung und synthetische Tests. Keine kostenpflichtigen Verpflichtungen oder Dreamloud-Änderungen.

## 2026-10-12 – 09: Hostingzugriff nicht vorhanden – kostenfreier Fortsetzungsplan

**Arbeitsauftrag / Rückmeldung:** Der Betreiber teilt mit, dass auf der eigenen WordPress.com-Testwebsite OrgaVela **kein SFTP-/SSH-Zugriff** verfügbar ist. Gesucht ist ein praktikabler Fortsetzungsweg ohne ausgabenauslösende Verträge.

**Verifizierte Informationen:** Laut WordPress.com-Support (zuletzt geprüft im Oktober 2026) sind SFTP/SSH, die erweiterten Servereinstellungen und phpMyAdmin normalerweise Business-/Commerce-Funktionen: https://wordpress.com/support/hosting-configuration/ und https://wordpress.com/support/database/ . Die konkrete fehlende Zugriffsberechtigung wurde vom Betreiber mitgeteilt. Der zuletzt über WordPress-Connector bestätigte technische Befund bleibt: Datenbankverbindung `latin1` / `latin1_swedish_ci`, UOP Core `0.1.0-alpha.2` installiert, aber inaktiv wegen Fail-closed-Anforderung `utf8mb4_required`.

**Bewertete Ansätze / Entscheidung:** Kein kostenpflichtiges Upgrade, kein Erzwingen der Aktivierung, kein PHP-/Theme-Hook zur Umgehung der DB-Prüfung. Stattdessen (1) separate OrgaVela-Website beibehalten, (2) unabhängige synthetische WordPress-/Datenbank-/HTTPS-Testumgebung per bestehendem GitHub Actions CI verwenden, (3) WordPress.com-Support nach kostenloser, vom Anbieter unterstützter dauerhafter UTF8MB4-Konfigurationskorrektur fragen. Nur nach konkreter unterstützter Migrationsmöglichkeit und Backup-/Restore-Prüfung auf der Staging-Site aktivieren.

**Tatsächliche Umsetzung:** Aktuelle offizielle Bedingungen geprüft, sichere nächste Schritte für die Betreiberentscheidung formuliert, diesen Status und Issue #12 dokumentiert. Keine Änderung an WordPress-Konfiguration, keinen SQL-Eingriff, keine neuen Kosten, keine Dreamloud-Änderung.

**Blocker / nächster Schritt:** Betreiber/Hosting-Support muss den gesicherten Konfigurationsweg klären; andernfalls E2E-Entwicklung unabhängig von OrgaVela fortsetzen. Issue #12 bleibt offen. Dieses Protokoll ist keine automatische vollständige Chatkopie und enthält nur notwendige technische Informationen.

## 2026-10-12 – 10: IONOS-Alternative statt WordPress.com-Hosting

**Arbeitsauftrag:** Der Betreiber fragt, ob die OrgaVela-WordPress-Website von WordPress.com zu IONOS umziehen kann, nachdem im aktuellen WordPress.com-Tarif SFTP/SSH fehlt und UOP Core wegen `latin1` inaktiv bleibt.

**Prüfung und Quellen:** IONOS bestätigt grundsätzlich WordPress-Migration von anderen Anbietern (https://www.ionos.de/hilfe/wordpress/wordpress-bei-ionos/mit-einer-wordpress-installation-zu-ionos-wechseln/) sowie neue WordPress-Installationen über Click & Build (https://www.ionos.de/hilfe/hosting/click-build-anwendungen/wordpress-installieren/) und bei geeignetem Webhosting eigene MariaDB-Datenbanken/phpMyAdmin (https://www.ionos.de/hilfe/hosting/mysql-datenbank-einrichten/mysql-oder-mariadb-datenbank-im-ionos-konto-erstellen/; https://www.ionos.de/hilfe/hosting/mysql-datenbank-fuer-web-projekt-verwenden/phpmyadmin-oeffnen-fuer-die-datenbankadministration/). WordPress.com erklärt Content-Export und komplette Plugin-Migration als unterschiedliche Wege (https://wordpress.com/support/export/; https://wordpress.com/support/export-an-entire-website-with-a-plugin/).

**Bewertete Optionen / Begründung:** Ein echter Komplettumzug ist möglich, aber für die bisher kaum eingerichtete OrgaVela-Testwebsite unnötig komplex. Zweckmäßiger wäre eine *frische* separate WordPress-Installation bei IONOS mit eigener, nachweislich `utf8mb4`-fähiger Datenbank und anschließender Installation des geprüften UOP-Core-GitHub-Pakets; nur tatsächlich benötigte Inhalte separat übernehmen. Die WordPress.com-Unteradresse `orgavela.wordpress.com` ist keine frei übertragbare eigene Domain; IONOS bräuchte eine separate Domain/Subdomain/geeignete Hostingadresse. **Kosteneinsparung ist nicht verifiziert:** Es muss geprüft werden, ob der bestehende IONOS-Vertrag des Betreibers bereits eine weitere Installation, eigene Datenbank und Adresse ohne Aufpreis erlaubt. Die Website und das Repo von Dreamloud dürfen keinesfalls verändert oder als Testumgebung verwendet werden; selbst eine gemeinsame Hosting-Umgebung käme nur mit wirksamer technischer Trennung und ohne Zugriff auf Dreamloud in Betracht.

**Tatsächlich umgesetzt:** Öffentlich dokumentierte IONOS- und WordPress.com-Anleitungen recherchiert, technische Empfehlung und notwendige Prüfungen formuliert; **kein Umzug, keine Installation, kein Vertrag/Upgrade, kein Domainkauf und keine Änderung an Dreamloud** vorgenommen.

**Status / nächster Schritt:** Betreiber muss den konkreten IONOS-Tarif und freie Ressourcen (zusätzliche Website/DB, PHP/SFTP, DNS/Domain, keine Mehrkosten) prüfen lassen. Dann separate WordPress-Installation auf IONOS vorbereiten, Datenbankzeichensatz und Rollbackfähigkeit prüfen, UOP Core sicher aktivieren und synthetische E2E-Tests ausführen. Bis dahin OrgaVela auf WordPress.com unverändert lassen; Hostingblocker Issue #12 bleibt offen.

## 2026-10-12 – 11: IONOS-WordPress-Tarif, Inklusiv-Domain und Folgekosten

**Arbeitsauftrag:** Nutzer erwägt ausdrücklich einen neuen IONOS-WordPress-Vertrag zum beworbenen Einstiegspreis von 1 EUR/Monat und fragt, was mit der Domain geschieht. Keine Bestellung beauftragt.

**Recherche:** IONOS-Produktübersicht `https://www.ionos.de/hosting/wordpress-hosting` (Stand 2026-10-12) bewirbt WordPress **Grow** für 1 EUR/Monat in den ersten 12 Monaten und danach 10 EUR/Monat, Preise inkl. MwSt. Laut Übersicht: **eine Inklusiv-Domain**, SSL, SFTP/SSH und WP-CLI, ein WordPress-Website-Slot und unbegrenzt Subdomains. IONOS-Inklusivdomain-Hilfe `https://www.ionos.de/hilfe/domains/preise-und-rechnung/inklusiv-domains-antworten-auf-haeufig-gestellte-fragen/` beschreibt Inklusiv-Domains als ohne gesonderte Registrierungs-/Verlängerungskosten während der Laufzeit des dafür berechtigten Vertrags; konkretes Angebot, Endung und freie Inklusivdomain müssen vor Abschluss im Warenkorb geprüft werden.

**Entscheidung und Begründung:** `orgavela.de` ist als dauerhafte Produktadresse sinnvoll, vorbehaltlich live registrierbarer Verfügbarkeit, möglicher Markenrechte und Auswahl als Inklusiv-Domain. `orgavela.wordpress.com` ist eine von WordPress.com bereitgestellte Unteradresse, **keine** frei übertragbare Domain. Bei neuem IONOS-Hosting frische isolierte WordPress-Installation mit eigenständiger DB und `utf8mb4` statt Übertragung der noch kaum gefüllten WordPress.com-Testseite. Perspektivische `demo.` und `staging.`-Subdomains nicht mit mehreren enthaltenen WordPress-Instanzen verwechseln (nur 1 Website im Grow-Angebot).

**Kostenhinweis / Freigabegrenze:** 1 EUR pro Monat ist ausdrücklich nur ein zeitlich begrenzter Einstiegspreis; ab Monat 13 laut Angebot **10 EUR monatlich** (120 EUR/Folgejahr) und ggf. weitere Bestellbedingungen. Die Aussage, 1 EUR monatlich wäre akzeptabel, ist **keine** pauschale Freigabe für höhere Verlängerungskosten oder eigenständige Vertragsschlüsse durch den Assistenten. Keine Bestellungen/Domainregistrierung, keine zahlungspflichtigen Leistungen, keine Änderungen an Dreamloud.

**Nächster Schritt:** Nutzer prüft im IONOS-Bestellvorgang die tatsächlich verfügbare Domain `orgavela.de`, die Einbeziehung der TLD in die Inklusivdomain, Mindestlaufzeit, Folgekosten und optionale Extras. Nur nach bewusster Zustimmung zum vollständigen Preisverlauf selbst abschließen. Danach getrennte WordPress-/DB-Einrichtung unter `orgavela.de` sowie Sicherheits-/PHP-/UTF8MB4-Prüfung vor Pluginaktivierung.

## 2026-10-12 – 12: Neue eigenständige IONOS-Website unter orgavela.com angeschlossen

**Auftrag:** Betreiber meldet die Einrichtung der neuen OrgaVela-WordPress-Website bei IONOS unter `https://orgavela.com` und adressiert den verbundenen „MCP Server For WordPress“. Ziel: neue Site technisch verifizieren und den Weg zur UOP-Core-Installation feststellen. Die neue Domain ist **.com**, nicht die zuvor vorgeschlagene .de.

**Erkundung:** Nur OrgaVela-MCP-Verbindung verwendet (keine Dreamloud-Verbindung). `discover_abilities` meldet **268** WordPress-Abilities. `mosmcp__site-get-url` und `core__get-site-info` bestätigen `https://orgavela.com` als neue WordPress-Site. `core__get-environment-info`: WordPress 7.1.3, PHP 8.3.33, MariaDB 11.8.8. `mosmcp__site-get-server-info`: Datenbankverbindung `utf8mb4`, Kollation `utf8mb4_unicode_520_ci`, HTTPS aktiv, PHP-Speicherlimit 512M, WordPress max. 512M, Dateimodifikationen erlaubt. Das bisherige WordPress.com-`latin1`-Hindernis besteht auf IONOS **nicht**.

**Plugin-/Betriebsbefunde:** `mosmcp__plugin-list`: 7 Plugins, davon 6 aktiv; UOP Core auf `orgavela.com` **noch nicht installiert**. WordPress-MCP bietet Pluginaktivierung bestehender Plugins, **keine** freigegebene ZIP-Installation auf dieser Website. `mosmcp__site-get-search-visibility`: Indexierung nicht untersagt (`discourage_search_engines=false`); die Website ist vorläufig kein abgeschottetes Staging. `mosmcp__site-get-privacy-policy-page`: Datenschutzseite mit ID 3 ist vorhanden, aber **Entwurf**, nicht veröffentlicht. `mosmcp__cron-test`: Loopback HTTP 200 und grundsätzlich ausführbar, `DISABLE_WP_CRON` ist gesetzt; nur bei zuverlässig eingerichtetem externem Cron sind terminierte Aufgaben abgesichert. Keine Veränderung durchgeführt.

**Artefaktprüfung:** GitHub-Branch `staging/uop-plugin-package` enthält `uop-core.zip` (534007 Bytes) und `package-provenance.txt` (SHA-256 `acbebdd9df25d996848bbca6ebe6726ee1a986207fc7c7b818474dc93134267a`, Ursprungs-Commit `4f2c4df800caf1e5708f05cce9536a7afb34aaa0`). GitHub-Vergleich zum aktuellen PR-#11-HEAD `7827805ca1a9059017cc6b6b1ca470c2769f3efb`: drei neuere Commits ändern **nur** `AGENTS.md`, `README.md`, `.github/pull_request_template.md`; Plugin-Code ist damit insoweit gleich. PR #11 bleibt Draft und darf nicht allein wegen der neuen Testsite gemergt werden.

**Entscheidung und Risiken:** UOP-Core-Aktivierung kann auf der neuen IONOS-Site nach sicherer Installation grundsätzlich getestet werden. Vorher Indexierung/Schutz der Testumgebung, Backup-/Restore-Fähigkeit und Cron-Scheduler klären und synthetische Daten verwenden; öffentlich sichtbare Seiten nicht als private Stagingumgebung interpretieren. Der MCP-Connector kann das ZIP derzeit nicht installieren, daher ist im WordPress-Admin unter `https://orgavela.com/wp-admin/plugin-install.php?tab=upload` ein einmaliger Upload des geprüften ZIP durch den Betreiber nötig oder später ein separat autorisierter sicherer Deploy-Zugang.

**Umsetzung:** Nur lesende Live-Abfragen und GitHub-Artefakt-/Vergleichsprüfung, Protokolleintrag und Issue-Nachführung; **keine** WordPress-Datenänderung, Installation, Aktivierung, neuer Vertrag oder Dreamloud-Modifikation. Domainwechsel `orgavela.com` verifiziert.

**Offene Punkte:** Geprüfte Backup-/Restore-Route und Testumgebungs-Sichtbarkeit, tatsächliche Plugininstallation/Aktivierung, Prüfung UOP-V1/V2-Migration, Event-/Guardian-Golden-Paths sowie Server-Cron nachweisen. WordPress.com-Issue #12 nicht als „Datenbank repariert“ schließen; dort die erfolgreiche neue Hosting-Alternative dokumentieren. Für die IONOS-Site sind Bedingungen zu Hosting-Folgekosten vom Betreiber separat zu prüfen.

## 2026-10-12 – 13: Dauerhafte SSH/SFTP-Bereitstellung für IONOS OrgaVela vorbereitet

**Arbeitsauftrag:** Der Betreiber möchte statt einzelner manueller Plugin-ZIP-Uploads eine eigene SSH/SFTP-Verbindung einrichten, damit UOP Core auf `orgavela.com` künftig direkt aus dem GitHub-Repository installiert werden kann. Weiterhin: Keine Dreamloud-Änderungen, keine kostenpflichtigen Zusatzverträge, GitHub-Dokumentationspflicht.

**Ausgangslage und Prüfungen:** Neue WordPress-Installation unter `https://orgavela.com` bei IONOS über WordPress-MCP bestätigt, MariaDB-Verbindungszeichensatz `utf8mb4`, HTTPS aktiv, UOP-Core-Plugin noch **nicht** installiert. Der bisher verbundene WordPress-MCP bietet Pluginverwaltung einschließlich Aktivierung, aber keinen allgemeinen ZIP-Upload oder SSH-Dateizugriff. IONOS dokumentiert für aktuelle WordPress-Tarife den separaten SFTP-Benutzer unter Menü → Web-Projekte → Einstellungen → Erweiterte Einstellungen → SFTP & SSH (https://www.ionos.de/hilfe/wordpress/administration-von-wordpress/sftp-benutzer-fuer-wordpress-anlegen/). SSH-Kommandos und Host-Fingerabdrücke sind bei geeigneten Tarifen dokumentiert (https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/).

**Abgewogene Wege:** Direkte Passwortübermittlung im Chat und unauthentifizierte/ungeprüfte Remote-Zugriffe sind ausgeschlossen. Ein lokaler Dauerzugang zum Hosting ist derzeit nicht nachgewiesen. Ein manuell ausführbarer GitHub-Actions-Workflow mit Secrets in geschützter Deployment-Umgebung ist wiederholbar, kontrollierbar und trennt Plugin-Auslieferung von Codeentwicklung. SSH-Hostidentität wird per vorab verifiziertem `known_hosts` festgeschrieben; `StrictHostKeyChecking=yes`, kein automatisches `ssh-keyscan`-Vertrauen. Passwort-Authentifizierung über `sshpass -e` ohne Passwort im Code oder Kommandoargument; GitHub-Environment-Secrets sind durch Benutzer selbst einzutragen.

**Tatsächliche Umsetzung:**
- [SSH-Workflow](../../.github/workflows/deploy-orgavela-ssh.yml) auf `main` erstellt (Commit `3c9a154e43f833ffcb022877aeaa526e069016f5`): ausschließlich `workflow_dispatch` und Branch `main`; `operation=probe` überprüft read-only SSH, WP-CLI, genaue URL `https://orgavela.com`, Plugins-Verzeichnis und Schreibrechte; `operation=upload` erfordert schriftliche Zielbestätigung, geprüften SHA-256-Wert und ZIP-Provenance, nutzt SCP und erstellt ausschließlich einen **neuen** Pluginordner. Bereits vorhandenes/aktives UOP Core wird **nicht** überschrieben. Keine Aktivierung, DB-Schreiboperation oder Migration.
- [Konfigurations- und Sicherheitsanleitung](orgavela-ionos-ssh-deployment.md) neu angelegt (Commit `6af4a533c810430e78963e138cd3416f04e67c6f`). Beinhaltet die vier erforderlichen Environment-Secrets (`IONOS_SSH_HOST`, `IONOS_SSH_USER`, `IONOS_SSH_PASSWORD`, `IONOS_KNOWN_HOSTS`) und die Variable `IONOS_WP_ROOT`, Fingerabdruckverifizierung, GitHub-Environment-Konfiguration und Probelauf.
- [AGENTS.md](../../AGENTS.md) auf den aktuellen IONOS-Zielhost aktualisiert (Commit `b25839ac677456bec4b1f3a1469205dff533b917`); Dreamloud-Sperre unverändert.

**Testergebnis:** Workflow-Datei und Anleitung in GitHub angelegt und erneut gelesen. Struktur und Fail-closed-Gates überprüft. **Es fand noch kein echter SSH-Verbindungsversuch statt**, denn ein dediziertes IONOS-SFTP/SSH-Konto und sichere GitHub-Environment-Secrets sind noch nicht eingerichtet. Auch ein tatsächlicher GitHub-Actions-Lauf auf der neuen Umgebung ist **noch nicht nachgewiesen**. UOP Core auf IONOS nicht installiert oder aktiviert. Der Github-Actions-Workflow ist ein vorbereiteter, noch nicht live erprobter Transportweg, kein abgeschlossener produktiver Deploymentprozess.

**Blocker / nächster Schritt:** Betreiber legt dedizierten SSH-fähigen SFTP-Zugang für das OrgaVela-Projekt in IONOS an und hinterlegt Host, Benutzer, Passwort, überprüften Serverhostschlüssel und verifizierten WordPress-Pfad ausschließlich in der GitHub-Umgebung `orgavela-ionos` (keine Passwörter im Chat). Dann in GitHub zuerst den manuellen `probe`-Workflow starten; Erfolg/Fehler mit Run-ID dokumentieren, danach erst bei bestandenen Gates eine erste ZIP-Installation via `upload` mit verifiziertem SHA. Für spätere Plugin-Aktualisierungen separates Backup-/Rollbackverfahren ergänzen. Website ist derzeit nicht als private Testumgebung abgesichert, daher keine echten Teilnehmerdaten und keine automatische Aktivierung.

## 2026-10-12 – 14: Selbstständige GitHub-SSH-Deployments ohne erneutes „Run workflow“

**Arbeitsauftrag:** Fortsetzung der Einrichtung einer dauerhaften SFTP/SSH-Verbindung für `orgavela.com`, sodass UOP-Core-Plugins nach Einrichtung durch den Betreiber künftig per GitHub-Arbeitsauftrag vom Assistenten hochgeladen werden können.

**Ergänzende Entscheidung/Gründe:** Ein rein manuelles `workflow_dispatch` würde den Betreiber weiterhin zum Starten jedes Uploads verpflichten. Ein strikt auf `main` und einen einzigen Deployment-Request-Pfad beschränkter `push`-Trigger ermöglicht danach die gewünschte Eigenständigkeit, ohne dass reguläre Commits oder Pull Requests automatisch zur Website übertragen werden.

**Tatsächlich implementiert:** [`.github/workflows/deploy-orgavela-ssh.yml`](../../.github/workflows/deploy-orgavela-ssh.yml) unterstützt zusätzlich zu `workflow_dispatch` den Push-Trigger **ausschließlich** für `.github/deploy-requests/orgavela.json` auf `main` (Commit `23409aab11e07291ca497729f5d8a94bbfb73053`). Die Request-Datei enthält `operation` (`probe`/`upload`), `confirm_target`, `expected_sha256` und optional die Audit-Request-ID. Der Workflow validiert diese Eingaben vor dem Zugriff auf den SSH-Host. Sicherheits-/Deploy-Gates und geschützte Environment-Secrets gelten unverändert. Die [Deployment-Anleitung](orgavela-ionos-ssh-deployment.md) beschreibt die Auslösung über kontrollierte Commits (Commit `7e204da03a4301a5e707549f2943b225bd51cf30`); [AGENTS.md](../../AGENTS.md) verbietet unbeauftragte Triggerdatei-Änderungen (Commit `9ed752c139557df6537489d7f5eb48c0048e709f`).

**Prüfung:** Dateien im GitHub-Hauptbranch gespeichert; Sicherheitsprüfungen umfassen fixiertes Ziel `https://orgavela.com`, `main`-Branch, verifizierten Hostschlüssel, getrennte Secrets, ZIP-SHA-256 und keine automatische Pluginaktivierung. **Kein** SSH-Handshake, kein GitHub-Workflow-Run, kein Plugin-Upload; ein echter Live-Test ist ohne vom Betreiber eingerichtetes SSH-Konto und GitHub-Secrets noch nicht möglich.

**Offen:** Betreiber richtet in IONOS ein SSH-fähiges SFTP-Konto für `orgavela.com` ein; unter `orgavela-ionos` in GitHub vier Environment-Secrets und die geprüfte Root-Pfadvariable hinterlegen. Danach kann über einen gezielten GitHub-Commit zunächst die **read-only** Probe und bei erfolgreicher Prüfung der erste Plugin-Upload ausgelöst werden. Die Triggerdatei wird **erst danach** neu angelegt, damit kein absehbar fehlgeschlagener Workflow startet. Dreamloud unverändert; UOP auf IONOS weiter noch nicht installiert/aktiviert.

## 2026-10-12 – 15: IONOS-SFTP-Fallback, geschützter Erstupload und Verbindungskonfiguration

**Arbeitsauftrag:** Nach der Zustimmung zur Einrichtung einer eigenen SFTP/SSH-Verbindung „fahre fort“; die Plugin-Bereitstellung soll aus GitHub möglich werden, ohne manuelle ZIP-Uploads und ohne Dreamloud zu verändern.

**Ausgangslage:** `https://orgavela.com` (IONOS WordPress) ist mit OrgaVela-MCP erreichbar; PHP 8.3, MariaDB 11.8 mit `utf8mb4`. UOP Core auf dieser IONOS-Website noch nicht installiert. Ein SSH/WP-CLI-Workflow war auf `main` vorhanden, erfordert aber funktionsfähige SSH-Shell und geschützte GitHub-Secrets. Vom Betreiber liegen **keine** IONOS-SFTP-Zugangsdaten in den verbundenen Werkzeugen vor. Das öffentliche GitHub-Repository darf keinerlei Zugangsdaten enthalten.

**Begründete Entscheidung:** IONOS-SFTP-Benutzer können abhängig von Tarif/Rechtevergabe reine Dateiübertragungsrechte ohne SSH-Shell haben (offizielle Anleitung: https://www.ionos.de/hilfe/wordpress/administration-von-wordpress/sftp-benutzer-fuer-wordpress-anlegen/ ). Als ergänzender Pfad wurde eine **reine SFTP-Variante** implementiert, die ohne `wp`/`unzip` auf dem Host auskommt. Ein erstes Deployment bleibt ohne vorherigen SSH-Hostfingerprint-/Pfad-Abgleich riskant; deshalb zuerst `probe`, erst danach ausdrücklich hash-geprüfte Installation mit getrenntem `install`-Schritt. Für den Push-gesteuerten SSH-Weg gilt weiterhin die gesonderte kontrollierte Requestdatei; der reine SFTP-Weg erhält bewusst **keinen** parallelen Auto-Trigger.

**Tatsächliche Implementierung:**
- `.github/workflows/deploy-orgavela-sftp.yml` als manuell startbaren `probe`-/`install`-Workflow ergänzt (Commits `cdd524ab04a25fbe7f2adfdbeb1b614d1908cba1`, `9c15fb4d44a704074add9bd97a14d3c3b36fb8bc`, `6c251ae552a046e7e6c476334e903ca0e318b905`). Eingaben/Domain auf `orgavela.com` und `main` eingeschränkt; Secrets in GitHub Environment `orgavela-ionos`, fixierter `known_hosts`-Hostschlüssel, SHA-256 und Paketprovenienz, Plugin-ZIP-Sicherheitsprüfung; SFTP-Transfer in ein temporäres Verzeichnis, anschließende Umbenennung ohne bestehenden `uop-core`-Ordner zu überschreiben. Keine automatische Aktivierung, keine DB-Migration. Die Korrekturen nutzten die OpenSSH-Dokumentation für rekursive `put -R`-Übertragung und einen temporären, beim Upload vorhandenen Zielordner.
- Anleitung `docs/operations/orgavela-ionos-sftp-deployment.md` erstellt (Commit `6d973307d40383c25da7fae7ef03ff808d0855b3`), GitHub [Issue #13](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/13) zur Nachverfolgung eröffnet.
- `AGENTS.md` im Hauptbranch auf den manuellen SFTP-Fallback ergänzt (Commit `676107dbcfbb18f8c9cc7bbc7706f77dc1870352`), ergänzend den aktiven Entwicklungsbranch bzgl. IONOS-Kontext aktualisiert (Commit `1b98658704e1b4e8fb5350d7817e20af4fdecc35`).
- SSH-Deployment-Anleitung korrigiert, damit der bereits bestehende kontrollierte Push-Trigger und der alternative, reine SFTP-Weg korrekt beschrieben sind (Commit `3c926aab8d4be9c75801bc951faef69599768290`).

**Prüfstatus:** Neue GitHub-Dateien nach Speicherung aus `main` erfolgreich zurückgelesen; Ablauf und Fail-closed-Guards statisch überprüft. **Keine** GitHub Actions Live-Probe, keine IONOS-Authentifizierung, kein Plugin-Upload und keine Aktivierung möglich, bevor der Betreiber einen dedizierten, auf OrgaVela begrenzten IONOS-Zugang und GitHub-Environment-Secrets eingerichtet hat. Kein bestehender Staging-/Backup-/Restore-Status für Datenbankmigrationen behauptet.

**Notwendige Betreiberhandlung:** In IONOS unter Web-Projekte → OrgaVela → Einstellungen → SFTP & SSH einen dedizierten Benutzer ausschließlich für das OrgaVela-Webprojekt erstellen. In GitHub unter Settings → Environments → `orgavela-ionos` die Secrets `IONOS_SSH_HOST`, `IONOS_SSH_USER`, `IONOS_SSH_PASSWORD`, `IONOS_KNOWN_HOSTS` und die Variable `IONOS_SFTP_ROOT` hinterlegen (für optionalen SSH-Weg zusätzlich `IONOS_WP_ROOT`). Hostschlüssel unabhängig prüfen, Zugangsdaten nicht im Chat teilen. Danach `probe` auf dem reinen SFTP-Workflow ausführen und die Run-ID berichten; falls SSH-Shell wirklich verfügbar ist, kann der kontrollierte SSH-Push-Trigger nach Freigabe aus dem Repository ausgelöst werden. Erst nach erfolgreichem Test, Host-/Pfadabgleich, Backup-Gate und geprüfter SHA-256 den Erstupload durchführen; Aktivierung, DB-Migrationen und weiterführende E2E-Abnahme separat nachweisen. Dreamloud unverändert; **kein** kostenpflichtiger Vertrag ausgelöst.

## 2026-10-12 – 16: Automatisierte SFTP-Request-Auslösung nach Einrichtung der Secrets

**Folgeauftrag:** Für die bereits eingerichtete reine SFTP-Alternative ebenfalls eigenständige Uploads aus ChatGPT/GitHub ermöglichen, falls der bei IONOS verfügbare Benutzer zwar SFTP-, aber keine Shell-Rechte hat.

**Ansatz / Entscheidung:** Die bereits vorhandene automatische SSH-Request-Datei `.github/deploy-requests/orgavela.json` darf nicht auch den SFTP-Workflow auslösen. Deshalb separate, exakt namensgebundene SFTP-Requestdatei `.github/deploy-requests/orgavela-sftp.json`; ausschließlicher `push` auf `main`, plus manuelle Option. Kein gewöhnlicher Code-/Dokumentationscommit startet ein Deployment; Secrets und IONOS-Zugang müssen vorher vom Betreiber sicher eingerichtet werden.

**Implementiert:** `.github/workflows/deploy-orgavela-sftp.yml` um den path-gefilterten Push-Trigger, Checkout ohne persistierte GitHub-Zugangsdaten und fail-closed Prüfung des JSON-Auftrags (Operation `probe` oder `install`, Ziel `orgavela.com` und geprüfter 64-stelliger SHA-Wert für Installationen) erweitert. Nachgelagerte Upload-Schritte werten ausschließlich das validierte `env.OPERATION` aus, sodass eine Push-Auslösung bei `install` nicht fälschlich ohne Upload endet (Commits `19dc33e187802762f874582f4ac7e4b4ee09e93c`, `b1fa40afd9d5e93c48898b271d6fa29803d95eaa`). Dokumentation und GitHub-AGENTS-Regel aktualisiert (Commits `c5fedf80b404c7779fd2cf3652e3b457252b4304`, `5fb42efa19dfc998a9a80dd88e4cffcbf14b8b28`); Issue #13 ergänzt.

**Prüfstatus:** Workflow-Dateien in GitHub erstellt, per API zurückgelesen und kritische Conditions geprüft; **noch kein echter GitHub-Actions-Run und kein erfolgreicher SFTP-Handshake**, da `IONOS_SSH_HOST`, `IONOS_SSH_USER`, `IONOS_SSH_PASSWORD`, `IONOS_KNOWN_HOSTS` und `IONOS_SFTP_ROOT` nicht aus vertrauenswürdiger Quelle konfiguriert oder bestätigt wurden. Keine Host-/Pfad-Identität und kein PHP-Live-Deploy verifiziert. Keinerlei Änderungen an Dreamloud, WordPress-Inhalten, Bestandsdaten oder Verträgen.

**Nächste Schritte:** Betreiber legt ausschließlich für `orgavela.com` freigegebenen SFTP-User bei IONOS an und hinterlegt die vier GitHub-Environment-Secrets plus `IONOS_SFTP_ROOT` (für SSH-Weg außerdem `IONOS_WP_ROOT`). Erst nach unabhängiger Prüfung des SSH-Hostfingerprints und des auf OrgaVela begrenzten SFTP-Zielverzeichnisses soll der Assistent kontrolliert einen **nur lesenden** `probe`-Commit auslösen; bei erfolgreichen CI-, Backup-/Restore- und Sicherheitsgates kann ein separater `install`-Request folgen. Sichere Paketupdates/Aktivierungen sind ein weiteres Arbeitspaket.

## 2026-10-12 – 17: IONOS SFTP+SSH-Konto nachgewiesen, Secrets-Konfiguration vereinfacht

**Arbeitsauftrag:** Der Betreiber zeigt einen IONOS-Erfolgsbildschirm für ein neu angelegtes SFTP+SSH-Konto, mit dem die künftige GitHub-Verbindung für das eigenständige UOP-WordPress-Projekt `orgavela.com` hergestellt werden soll.

**Ausgangslage / Beleglage:** Bildschirm „Konto wurde erstellt“ mit SFTP+SSH, Port 22 und zugewiesenem Server sowie Benutzernamen; laut IONOS kann die Bereitstellung des Zugangs bis zu fünf Minuten dauern. **Passwort nicht gezeigt und niemals benötigt im Chat.** Server- und Kontonamen werden nicht in dieses öffentliche Protokoll kopiert. Verzeichnis laut Kontoanzeige `/` – **keine** Bestätigung, dass in diesem Startordner bereits `wp-admin`, `wp-content` und `wp-includes` liegen. Ein tatsächlicher SSH/SFTP-Handshake wurde noch **nicht** ausgeführt.

**Technische Prüfung / Entscheidung:** IONOS veröffentlicht aktuelle SHA256-Fingerprints für Webhosting in der offiziellen Dokumentation https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/ . Für ED25519 ist der dokumentierte Fingerprint `SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q`. Eine nicht authentifizierte Schlüsselabfrage allein wäre kein Vertrauensnachweis; deshalb wird der vom Ziel gelieferte ED25519-Public-Key nach dem Abruf **gegen den fest hinterlegten, unabhängig aus der IONOS-Dokumentation stammenden Fingerprint verifiziert**. Abweichung führt zur Sperre; ein optionales, unabhängig bestätigtes `IONOS_KNOWN_HOSTS` bleibt als Ausnahme möglich. Der Agent kann das IONOS-Passwort weder aus dem Bild auslesen noch über die vorhandene GitHub-Connectorfunktionalität selbst sicher in Secrets eintragen.

**Tatsächliche Umsetzung:** Beide bereits angelegten GitHub-Deployment-Workflows (`.github/workflows/deploy-orgavela-sftp.yml` und `deploy-orgavela-ssh.yml`) so angepasst, dass **`IONOS_KNOWN_HOSTS` nicht mehr zwingend einzurichten** ist: Abfrage des ED25519-Schlüssels beim GitHub-Run plus Fail-closed-Prüfung gegen den offiziellen IONOS-Wert, weiterhin `StrictHostKeyChecking=yes`. Commits: `541da59b91d6da6ee9c6edda2f18682c543ed9f3`, `f9bab3480cbcbf8337af3c101564e24c7c591901`. Für die erste, ausschließlich lesende SFTP-Probe ist `IONOS_SFTP_ROOT` nun mit dem sicheren Standard `.` vorbelegt; falls nicht im WordPress-Stammverzeichnis, schlägt die Prüfung ohne Schreibzugriff fehl und es muss nach Kontrolle der richtige Pfad hinterlegt werden (Commit `e3cd279870c45cd7086249dc72b6fa91391ce1ea`). Die Handbücher zum SFTP-/SSH-Deployment in `docs/operations` wurden aktualisiert (Commits `306d9a749c4f96d14edbd61d19de3a2fbd7f1868` und `5695a49d524f9b3c51e3d630ed06f315d2995c67`).

**Abnahme/Status:** Code/Dateien in GitHub gesichert, offizielle Fingerprintreferenz im Supportportal geprüft. Eine lokale unauthentifizierte Hostkeyabfrage aus dem ausführenden Container war nicht erreichbar; diese Prüfung findet deshalb **erst im GitHub-Actions-Runner** statt. Zugang in IONOS erstellt, aber GitHub-Environment-Secrets bislang **nicht bestätigt**. Noch **keine erfolgreiche Verbindung, keine Dateiänderungen auf IONOS, keine Plugininstallation, keine Pluginaktivierung und keine Datenbankmigration** nachgewiesen. Kein Zugriff auf Dreamloud.

**Nächster Schritt (Betreiberhandlung):** In GitHub `Settings → Environments → orgavela-ionos` drei Environment-Secrets `IONOS_SSH_HOST`, `IONOS_SSH_USER`, `IONOS_SSH_PASSWORD` nach dem IONOS-Erfolgsbildschirm anlegen. Das Passwort ausschließlich unmittelbar in GitHub speichern. Optional `IONOS_SFTP_ROOT` erst nach Bedarf einstellen; für den ersten read-only SFTP-Run gilt `.`. Nach Bestätigung des Nutzers den eingeschränkt automatisierten `probe`-Request `.github/deploy-requests/orgavela-sftp.json` auf `main` erstellen und den tatsächlichen Host-/Pfadtest auswerten. Erst nach erfolgreicher Probe und getrenntem Sicherheits-/Backup-Gate einen `install`-Request auslösen. Beide Wege nie parallel betreiben.
