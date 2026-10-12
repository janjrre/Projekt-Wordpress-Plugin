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
