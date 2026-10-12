# OrgaVela – sicherer lesender IONOS-Zugang via GitHub Actions

**Stand 12.10.2026 · W0/O01/O02/M1-08 · ausschließlich https://orgavela.com.**
Dieses Dokument erklärt eine **vorbereitete** technische Verbindung, **keinen bestätigten Live-Zugang**. Dreamloud bleibt vollständig außerhalb des Scopes.

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

**Wichtig:** Ein Shell-Login hat ggf. trotz eingeschränkter Workflow-Kommandos weitreichende Webspace-Rechte; der Workflow selbst ersetzt **keine echte serverseitige Account-Isolation** (W0/O01 bleibt offen, bis der Betreiber sie unabhängig nachweist).

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

Zuerst unter [GitHub Actions → OrgaVela – strictly read-only SSH health audit](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/workflows/orgavela-readonly-audit.yml) auf **Run workflow → Branch main → `probe`** klicken. Ein erfolgreicher Probe-Lauf meldet nur generische Prüfzustände, **nicht** Serverpfade oder Datenbankinhalte. Scheitert er, die öffentliche CI-Meldung auf fehlenden SSH-/WP-CLI-Zugang oder ungeprüfte WordPress-Zielidentität untersuchen; **nicht** einfach Guard-Prüfungen entfernen.

Erst danach **`schema`** wählen; es werden nur die **installierten** Site-Health-/Migrationsstatus-Codes, Zahl der UOP-Tabellen, Default-Org-Konsistenz und Versionsbedingungen ermittelt. Die bekannte Alpha.2-Version wird bewusst vorausgesetzt; andere Versionen benötigen eine erneute Paket-/Abnahmeprüfung. Aus GitHub kann man diese Zustandswerte lesen und ihre Belege in Issues/Plan dokumentieren. Eine erfolgreiche Action allein bedeutet **nicht** bereits, dass alle 29 Tabellen **unabhängig** auf alle Spalten und Indizes geprüft wurden; ergänzend braucht O02 einen eigenen Metadata-Soll/Ist-Vergleich.

## 4 – Spätere überprüfbare Läufe durch den Assistenten

Der Workflow erlaubt einen genau begrenzten **`push`-Trigger auf `main` nur für `.github/audit-requests/orgavela-readonly.json`**. Nach nachgewiesener Einrichtung und der Betreiberfreigabe für den nächsten Lesecheck kann dieser operative Request erzeugt werden:

```json
{"operation":"probe"}
```

oder nach erfolgreicher Probe:

```json
{"operation":"schema"}
```

Damit kann der angeschlossene GitHub-Assistent einen **lesenden, durch das Workflow-/Environment-Gate begrenzten** GitHub-Actions-Lauf über eine gezielte Commit-Änderung auslösen und danach CI-Status und nur die bereinigten Berichte abrufen. **Die Request-Datei wird nicht vorsorglich erstellt oder ausgelöst, solange keine Secrets und serverseitige Isolation bestätigt sind.** Der Betreiber kann seine Secrets jederzeit in den Environment-Einstellungen löschen/rotieren; keine asynchrone Arbeit ohne konkreten Auftrag.

## 5 – Ausdrückliche Grenzen

- Der Audit ist **keine** allgemeine Remote-Shell für den Assistenten, **kein** IONOS-Kundenportal-Login, **kein** SQL-Dump, **kein** Backup und **kein** Restore.
- Der Quellcode enthält nur lesende SQL-Statements; WordPress-Bootstrapping und ein mit Schreibrechten versehenes Hosting-Konto können technisch trotzdem weiterreichende Nebenwirkungen oder Berechtigungen haben. Für eine streng rechtlich/technisch garantiert read-only Datenbankberechtigung wäre ein gesonderter serverseitig als read-only definierter DB-Nutzer erforderlich.
- Keine Daten realer Teilnehmer, Minderjähriger oder personenbezogene Tabellenzeilen in CI-Berichten. Kein UOP-Upgrade/Pluginaktivierung auf OrgaVela und **kein** Zugriff auf Dreamloud.
- Vor Änderungen an produktiver Datenbank bleibt die unabhängige IONOS-Backup-/Restore-Probe O03 zwingend erforderlich.

**Nächster Nachweis:** Betreiber richtet isolierte Secrets und Shell-Pfad ein → einmalig Probe starten → Ergebnis auf GitHub verifizieren → rein lesenden Schema-Check starten → O02-Live-Metadaten-Audit weiter vervollständigen. Bis dahin kein geändertes formales M1-/W0-/V1-Abnahmeergebnis.
