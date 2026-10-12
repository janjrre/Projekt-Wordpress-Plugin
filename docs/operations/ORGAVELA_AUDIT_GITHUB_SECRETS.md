# OrgaVela – sicherer lesender IONOS-Zugang via GitHub Actions

**Stand 12.10.2026 · W0/O01/O02/M1-08 · ausschließlich https://orgavela.com.**
Dieses Dokument erklärt eine **vorbereitete** technische Verbindung, **keinen bestätigten Live-Zugang**. Dreamloud bleibt vollständig außerhalb des Scopes.

> **Wichtiger Sicherheitsnachtrag 12.10.2026:** Der Betreiber hat bestätigt, dass das **bereits hinterlegte** SFTP/SSH-Konto den gesamten Pfad `/` des IONOS-Webspaces erreichen kann. Bei Shared Webhosting ist `/` üblicherweise die eigene eingeschränkte Webspace-/Chroot-Umgebung, **nicht** System-Root des IONOS-Servers. Dennoch kann das Konto andere Websites im selben Hosting-Vertrag erreichen. **Bestehende `orgavela-ionos`-Deployment-Secrets keinesfalls in `orgavela-audit` kopieren.** Die zusätzliche Audit-Action ist vorsorglich nur noch **manuell** mit einer expliziten Isolationsbestätigung verfügbar; der automatische Commit-/Push-Auslöser ist abgeschaltet. Keine bisherige GitHub-Secret-Konfiguration wurde geändert. Das Kennwort eines solchen breit berechtigten Kontos darf nicht als harmloser Lesezugang behandelt werden. Quelle: [IONOS SFTP/SSH-Konto erstellen](https://www.ionos.de/hilfe/hosting/ftp-zugaenge-einrichten-und-verwalten/sftp/ssh-konto-erstellen/) und [IONOS SSH/chroot](https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/was-ist-ssh/).

## Weshalb kein IONOS-Hauptlogin oder direkter MySQL-Runner?

- Das **persönliche IONOS-Kundenkonto** besitzt unnötig weitgehende Hosting-, Vertrags-, Domain- und Billing-Berechtigungen und ggf. Zwei-Faktor-Authentifizierung. **Niemals** dessen Passwort, 2FA-Seed, Recovery-Codes oder Sessions in GitHub-Secrets, Issues oder den Chat kopieren.
- Bei IONOS Shared Webhosting nimmt die MariaDB/MySQL-Datenbank normalerweise **keine Verbindungen von externen GitHub-Runnern** an. Die GitHub-Action nutzt deshalb nur einen **SSH-Shell-Zugang zum eigenen IONOS-Webspace** und dort vorhandene WordPress-/WP-CLI-Lesefunktionen. Quelle: [IONOS externe DB-Verbindungen](https://www.ionos.de/hilfe/hosting/problemloesungen-fuer-mysql-datenbanken/verbindungs-und-zugriffsprobleme-bei-mysqlmariadb-datenbanken-beheben/).
- **SFTP allein ist nicht SSH-Shell-Zugriff.** Bestimmte ältere IONOS-Verträge erlauben SSH nur dem SFTP-Hauptbenutzer. Wenn kein eigener auf **OrgaVela beschränkter** Shell-Zugang möglich ist, wird die Datenbankprüfung **nicht** durch Hinterlegen des IONOS-Hauptpassworts oder einer breit berechtigten Dreamloud-Shell umgangen. Dann in IONOS phpMyAdmin nur freigegebene reine Metadaten-Abfragen manuell ausführen bzw. ein alternatives isoliertes Betreiberverfahren vereinbaren. Quelle: [IONOS SSH-Zugang](https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/ssh-verbindung-unter-linux-herstellen/).

## 1 – In IONOS getrennten SSH-Zugang einrichten und prüfen

Über das IONOS-Konto **SFTP/SSH-Verwaltung** für das **OrgaVela-Projekt** öffnen. Prüfen, ob ein dedizierter SSH-fähiger Benutzer mit möglichst OrgaVela-beschränktem Dateisystemzugriff eingerichtet werden kann; auf **keinen Fall** bestehende Dreamloud-Zugänge verwenden oder dessen Dateien/Verzeichnisse öffnen.

Zur Vorbereitung nur intern bereithalten:
1. **IONOS SSH-/SFTP-Hostname**, wie im IONOS-Portal angezeigt (nicht die Domain raten).
2. **SSH-Benutzername** des neuen dedizierten OrgaVela-Kontos.
3. **SSH-Passwort** dieses Kontos, nur direkt in GitHub-Secrets eintragen.
4. **Absolutes Shell-Verzeichnis der WordPress-Installation**, in dem `wp-config.php`, `wp-admin/` und `wp-content/plugins/uop-core/` vorhanden sind. **SSH-Shell-Pfad ist nicht zwingend der bereits geprüfte SFTP-Pfad `/public`.** Kein blindes Raten und keine Suche durch andere Websites.

**Wichtig:** Eine IONOS-Einstellung „Verzeichnis `/public`“ für einen zusätzlichen Benutzer beschränkt laut IONOS **nur SFTP**, nicht automatisch PHP- oder SSH-Zugriffe. Das ist kein Beleg für einen wirklich von anderen Websites isolierten SSH-Nutzer. Wenn sich keine wirksame unabhängige Shell-/Account-Trennung nachweisen lässt, **keinen** solchen Secret-Zugang einrichten oder freigeben; für O02 stattdessen den WordPress-Admin-/IONOS-phpMyAdmin-Weg unter Betreiberkontrolle verwenden. O01 bleibt offen. Eine kostenpflichtige Trennung auf einen eigenen Webspace wird **nicht** automatisch bestellt.

## 2 – GitHub-Environment einrichten (Betreiberaktion)

[Repository → Settings → Environments](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/environments) öffnen und eine **eigene** Umgebung exakt **`orgavela-audit`** anlegen, getrennt von `orgavela-ionos` für Deployments.

- Bei **Deployment branches/tags** nach Möglichkeit **Selected branches and tags → `main`** erlauben.
- **Required reviewers** aktivieren, soweit im Konto verfügbar; sensible Läufe dann vor Freigabe prüfen.
- Unter **Environment secrets → Add secret** genau diese drei Werte sicher eingeben:

| Secret-Name | Inhalt |
|---|---|
| `IONOS_AUDIT_SSH_HOST` | Verifizierter IONOS-SSH-Hostname nur für OrgaVela |
| `IONOS_AUDIT_SSH_USER` | Dedizierter SSH-fähiger OrgaVela-Nutzer |
| `IONOS_AUDIT_SSH_PASSWORD` | Passwort **dieses dedizierten Nutzers**, nicht das IONOS-Hauptlogin |

Unter **Environment variables → Add variable** diesen **nicht geheimen**, aber verifizierten Wert eintragen:

| Variable | Inhalt |
|---|---|
| `IONOS_AUDIT_WP_ROOT` | Absoluter **SSH-Shell-Pfad** der OrgaVela-WordPress-Installation |

Diese Namen sind die exakt im Workflow referenzierten Werte. **Keine Datenbankpasswörter oder IONOS-Panel-Logins erforderlich.** Secrets sind für den Assistenten nicht direkt lesbar; sie sind nur an den kontrollierten Workflow gebunden. Wer den Workflow auf einem berechtigten Branch verändern kann, kann allerdings unter Umständen Zugriff über diesen Workflow auf die verwendeten Secrets erlangen: deshalb Branch-/Environment-Beschränkungen und Reviews ernst nehmen.

## 3 – Sichere Verbindung testen

Vorbereitete, im Repository überprüfbare Dateien:
- [GitHub-Workflow](../../.github/workflows/orgavela-readonly-audit.yml): ausschließlich `main`, `orgavela-audit`-Environment, GitHub-Token mit `contents: read`, `probe` oder `schema`; niemals Upload-/Delete-/Migrate-Operationen.
- [Eng begrenztes SSH-Prüfskript](../../scripts/ci/orgavela-readonly-audit.sh): Offiziellen IONOS-ED25519-Fingerprint prüfen, Domain `https://orgavela.com`, UOP-Version `0.1.0-alpha.2`, verifiziertes WordPress-Root; lokale Shell-/PHP-Fehlertexte, WP-Config und Ausgaben aus der DB bleiben **aus öffentlichen Logs ausgeschlossen**.

**Nur nach belegter OrgaVela-exklusiver SSH-Kontotrennung** unter [GitHub Actions → OrgaVela – strictly read-only SSH health audit](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/workflows/orgavela-readonly-audit.yml) **Run workflow → Branch main → `probe`** wählen und den Pflichtschalter **`confirm_dedicated_access`** nur bei tatsächlich unabhängigen Zugriffsrechten aktivieren. **Für das derzeit vorhandene `/`-Konto den Schalter NICHT aktivieren.** Ein erfolgreicher Probe-Lauf meldet nur generische Prüfzustände, **nicht** Serverpfade oder Datenbankinhalte. Scheitert er, die öffentliche CI-Meldung auf fehlenden SSH-/WP-CLI-Zugang oder ungeprüfte WordPress-Zielidentität untersuchen; **nicht** einfach Guard-Prüfungen entfernen.

Erst danach **`schema`** wählen; es werden nur die **installierten** Site-Health-/Migrationsstatus-Codes, Zahl der UOP-Tabellen, Default-Org-Konsistenz und Versionsbedingungen ermittelt. Die bekannte Alpha.2-Version wird bewusst vorausgesetzt; andere Versionen benötigen eine erneute Paket-/Abnahmeprüfung. Aus GitHub kann man diese Zustandswerte lesen und ihre Belege in Issues/Plan dokumentieren. Eine erfolgreiche Action allein bedeutet **nicht** bereits, dass alle 29 Tabellen **unabhängig** auf alle Spalten und Indizes geprüft wurden; ergänzend braucht O02 einen eigenen Metadata-Soll/Ist-Vergleich.

## 4 – Sicherer Auslöseweg und Grenzen der Autonomie

Der automatische `push`-Trigger über `.github/audit-requests/orgavela-readonly.json` wurde **deaktiviert**, nachdem der Betreiber die weitreichenden `/`-Rechte des bestehenden SSH/SFTP-Kontos gemeldet hat. Aktuell sind ausschließlich **manuelle GitHub-Actions-Läufe** mit vorausgesetztem `confirm_dedicated_access=true` vorgesehen, und zwar **nur** wenn eine reale serverseitige OrgaVela-Isolation unabhängig nachgewiesen wurde. Ein bestätigtes Kontrollkästchen ist eine Betreiberbestätigung, **keine technische Rechteabsicherung**.

Bis diese Voraussetzung erfüllt ist, können Assistenten über GitHub **keine autonomen SSH-Abfragen** starten; der bisherige SFTP-Deployment-Workflow wird nicht stillschweigend wiederverwendet. Ein späteres autonomes Leseverfahren erfordert eine neue dokumentierte Sicherheitsbewertung, eingeschränkte Berechtigungen, Schutz von `main`/GitHub-Environment und ausdrückliche Freigabe. Als sichere Alternative können die benötigten Schema-Metadaten manuell im WordPress-Admin oder in IONOS phpMyAdmin **lesend** erhoben und anschließend als anonymisierte Ergebnisse im Projekt dokumentiert werden.

## 5 – Ausdrückliche Grenzen

- Der Audit ist **keine** allgemeine Remote-Shell für den Assistenten, **kein** IONOS-Kundenportal-Login, **kein** SQL-Dump, **kein** Backup und **kein** Restore.
- Der Quellcode enthält nur lesende SQL-Statements; WordPress-Bootstrapping und ein mit Schreibrechten versehenes Hosting-Konto können technisch trotzdem weiterreichende Nebenwirkungen oder Berechtigungen haben. Für eine streng rechtlich/technisch garantiert read-only Datenbankberechtigung wäre ein gesonderter serverseitig als read-only definierter DB-Nutzer erforderlich.
- Keine Daten realer Teilnehmer, Minderjähriger oder personenbezogene Tabellenzeilen in CI-Berichten. Kein UOP-Upgrade/Pluginaktivierung auf OrgaVela und **kein** Zugriff auf Dreamloud.
- Vor Änderungen an produktiver Datenbank bleibt die unabhängige IONOS-Backup-/Restore-Probe O03 zwingend erforderlich.

**Nächster Nachweis:** Betreiber richtet isolierte Secrets und Shell-Pfad ein → einmalig Probe starten → Ergebnis auf GitHub verifizieren → rein lesenden Schema-Check starten → O02-Live-Metadaten-Audit weiter vervollständigen. Bis dahin kein geändertes formales M1-/W0-/V1-Abnahmeergebnis.
