# OrgaVela – autorisierte Wiederverwendung des bestehenden IONOS-Zugangs in GitHub Actions

**Stand 12.10.2026 · W0/O01/O02/M1-08 · nur https://orgavela.com.**
Dieses Dokument beschreibt GitHub-Actions-Zugänge, nicht die Behauptung, dass ein Live-Audit erfolgreich war. Es ersetzt die frühere Anweisung, zwingend ein separates SSH-Konto und zusätzliche Secrets einzurichten.

## 1. Präzisierung der Betreiberfreigabe

Der Betreiber hat **ausdrücklich bestätigt**, dass OrgaVela und Dreamloud in **getrennten IONOS-Hosting-Verträgen** liegen. Er akzeptiert die innerhalb des OrgaVela-Hosting-Webspaces `/` weitreichenden Berechtigungen des **bereits bestehenden OrgaVela-SFTP/SSH-Accounts** und autorisiert dessen Verwendung für OrgaVela-Audits und sonstige sachlich zulässige OrgaVela-IONOS-SFTP/SSH-Arbeiten über GitHub Actions. Die Trennung der Verträge ist eine **Betreiberangabe**; sie wurde nicht eigenständig durch IONOS-Vertragsdokumente verifiziert.

Ein IONOS-Webhosting-Pfad `/` bedeutet **nicht** automatisch Administrator-`root` auf dem physischen IONOS-Server; er kann aber alle Ordner **des betreffenden Webspaces** zugänglich machen. Der Betreiber übernimmt dieses bewusst benannte Restrisiko. Der Zugriff auf Dreamloud (separater Vertrag/Repository) ist **nicht** autorisiert. Das persönliche IONOS-Kundenportal-Passwort, 2FA-Codes und IONOS-Billing-Zugang sind **nicht** Teil der Freigabe.

**Berechtigung zur Wiederverwendung eines Kontos ist keine pauschale Autorisierung, beliebige Hosting-Dateien, Datenbanken, Domains, Backups oder Plugin-Versionen zu ändern.** Deployment-Gates, ausdrückliche Verträge-/Kostenfreigaben, notwendige Restore-Proben, die schreibende Operationsfreigabe und die V1-Abnahmekriterien gelten fort.

## 2. Vorhandene GitHub-Konfiguration wiederverwenden

[GitHub Environment `orgavela-ionos`](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/environments): Bestehendes, bisher für OrgaVela-SFTP- und SSH-Deployments verwendetes Environment. **Keine neuen Secrets anlegen, keine Zugangsdaten erneut hier oder im GitHub-Issue posten, nicht in Workflows kopieren.** Der neue Audit-Workflow referenziert ausschließlich die bereits vom Deployment genutzten Namen:

| GitHub Environment | Geheimnis/Variable | Verwendung |
|---|---|---|
| `orgavela-ionos` | Secret `IONOS_SSH_HOST` | IONOS Webspace SSH/SFTP-Host |
| `orgavela-ionos` | Secret `IONOS_SSH_USER` | Bestehender OrgaVela SFTP/SSH-Benutzer |
| `orgavela-ionos` | Secret `IONOS_SSH_PASSWORD` | Bereits hinterlegtes Passwort des **gleichen** Benutzers |
| `orgavela-ionos` | Variable `IONOS_WP_ROOT` | Falls vorhanden: bestätigtes absolutes WordPress-Shell-Verzeichnis |

Wenn `IONOS_WP_ROOT` nicht existiert, verwendet der **read-only Audit allein** zunächst den bereits für OrgaVela-SFTP verifizierten relativen Hosting-Pfad `/public`. Dies ist **kein Nachweis**, dass eine SSH-Shell dort dasselbe WordPress-Root sieht: deshalb prüft der Audit vor jedem WordPress-Befehl, dass `wp-config.php`, `wp-admin/` und `wp-content/plugins/uop-core/` tatsächlich vorhanden sind, und vergleicht die per WP-CLI ausgelesene WordPress-Home-Adresse mit `https://orgavela.com`. Fehlt das WordPress-Root, bricht er ohne Änderung ab.

**Der Workflow liest Secret-Referenzen zur Laufzeit**, der Assistent kann in GitHub die Passwörter nicht auslesen. Da das GitHub-Repository öffentlich ist, enthalten Workflows, Audit-Logs, Commits und Issues ausschließlich bereinigte technische Statuswerte, niemals Zugriffsschlüssel, Hostnamen, Benutzernamen oder echte Teilnehmerdaten.

## 3. Audit-Workflow und Aktionen

- [`.github/workflows/orgavela-readonly-audit.yml`](../../.github/workflows/orgavela-readonly-audit.yml): Umgebung `orgavela-ionos`, nur `main`, GitHub-Token `contents: read`, `sftp`, `probe` oder `schema`, ausschließlich explizite manuelle oder eng begrenzte Request-Starts.
- [`scripts/ci/orgavela-readonly-audit.sh`](../../scripts/ci/orgavela-readonly-audit.sh): prüft IONOS-ED25519-Hostkey gegen fest verankerten Anbieter-Fingerprint, WordPress-Root, genau `https://orgavela.com`, installierte UOP-Core-Version `0.1.0-alpha.2`. Keine Roh-SSH-/PHP-Fehlertexte oder Datenbankdatensätze in öffentlichen Actions-Logs.
- `sftp` prüft ausschließlich **per SFTP** die erwarteten OrgaVela-WordPress-Verzeichnisse, lädt keine Daten herunter und macht keine Änderungen. **Dies ist kein SQL-Nachweis.**
- `probe` prüft **nur** Erreichbarkeit einer SSH-**Shell** (nicht nur SFTP), verfügbare WP-CLI und die Identität der installierten OrgaVela-Seite.
- `schema` verwendet auf der **gleichen**, bereits geprüften OrgaVela-Installation read-only WP-CLI-Funktionen zur Ermittlung technischer Schema-Health-Werte, Anzahl UOP-Tabellen, Versions- und Default-Organisationskonsistenz. Keine schreibenden SQL-Abfragen, kein DB-Dump, keine Backup-/Restore- oder Migrationsfunktionen. Ein positiver Audit ersetzt noch nicht den unabhängigen Abgleich **sämtlicher 29 physischen Tabellen, Spalten und Indizes**.

Die bestehende OrgaVela-**SFTP-Installation** mit `/public` wird **nicht** durch diese Prüfungen verändert. Falls IONOS für diesen Benutzer **nur SFTP, aber keine ausführbare SSH-Shell** erlaubt, kann `probe` fehlschlagen. In diesem Fall beweist der bereits funktionierende SFTP-Zugang **nicht** automatisch, dass WP-CLI/SQL via SSH möglich ist; die verbleibende SQL-Prüfung muss gegebenenfalls über WordPress-Admin oder IONOS phpMyAdmin erfolgen.

## 4. Einmalige und später erneut abrufbare Prüfungen

Manueller Start: [Actions → OrgaVela – strictly read-only SSH health audit](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/workflows/orgavela-readonly-audit.yml) → `Run workflow` → Branch `main` → `sftp` für reinen Dateizugriff, `probe` für SSH + WP-CLI, danach `schema`.

Für durch den Assistenten zu einem **konkret beauftragten Prüftermin** selbst ausgelöste Lesechecks dient ausschließlich der Repo-Pfad `.github/audit-requests/orgavela-readonly.json` auf `main`, nicht eine Datei unter `.github/deploy-requests/`. Der bewusst autorisierte minimale Commit-Inhalt ist beispielsweise:

```json
{"operation":"probe","target":"orgavela.com"}
```

Nach einer bestätigten Shell-Probe darf beim nächsten gezielt beauftragten Lauf der Wert `schema` statt `probe` gesetzt werden. `push` wird nur bei Änderung dieser einen Datei ausgelöst; das Skript nimmt außer `probe`/`schema` keine Operationen an, verifiziert die Site-Identität und nutzt ausschließlich das bestehende `orgavela-ionos` Environment. **Keine stündliche Automatik, kein Hintergrundbetrieb ohne neuen Ausführungsauftrag.**

## 5. Voraussetzungen, Grenzen und Wiederherstellung

- Der Betreiber akzeptiert die innerhalb **dieses** OrgaVela-Hosting-Webspaces umfangreichen Rechte des Accounts. Dadurch wird W0/O01 **bezüglich der Risikoentscheidung dokumentiert, aber nicht allein technisch abgenommen**.
- SSH-Zugang samt Geheimnissen darf über bestehende SFTP-/SSH-**Deployment**-Workflows wiederverwendet werden; neue schreibende Workflows erfordern weiterhin einen klar begrenzten Auftrag, Bestätigung von `orgavela.com`, aktuelle CI-Prüfungen und angemessenen Backup-/Rollback-Plan.
- Der Read-Audit führt keine Datenbank-/Dateiänderungen aus; `wp`-Leseoperationen bootstrappen WordPress, können technisch aber je nach Hosting/Plugins trotzdem Nebeneffekte haben. Die reine Befehlsvorgabe ist kein serverseitig erzwungenes Read-only-Berechtigungsmodell.
- Kein echtes Teilnehmerdatum/-datensatz, kein Versand an Dritte, kein Dreamloud-Zugriff. Vor produktiven DB-Migrationen bleibt **O03 isolierter Restore-Nachweis zwingend**.
- Die Secrets lassen sich über `orgavela-ionos` vom Betreiber deaktivieren, austauschen oder widerrufen. Die bestehende Deployment-Umgebung wurde durch diese Audit-Einrichtung weder gelöscht noch neu beschrieben.

## 6. Tatsächlich ausgeführte Belege (12.10.2026)

- [Run #38263245050](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263245050): Erster SSH-Test **fehlgeschlagen**, da der SFTP-Pfad `/public` nicht dem SSH-Shell-Pfad entsprach; Secret-Referenzen, Workflow-Prüfung und Runner waren funktionsfähig. Keine Geheimnisse in Logs.
- [Run #38263689846](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263689846): **SUCCESS** `ORGAVELA_AUDIT_SFTP=verified`, **Shell/Schema nicht geprüft**. Der existierende OrgaVela-Benutzer darf per SFTP die erwarteten WP-Verzeichnisse lesen.
- [Run #38263987605](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38263987605): SSH-Shell tatsächlich erreichbar, das ursprünglich getestete WordPress-Root jedoch falsch. Nach IONOS-[Dokumentation zum absoluten Webspace-Pfad](https://www.ionos.de/hilfe/hosting/php-fuer-web-projekt-verwenden/absoluten-pfad-document-root-ermitteln/) wurde der Audit um eine eng begrenzte Liste üblicher Shell-/Document-Root-Varianten ergänzt; kein komplettes Webspace-Scanning.
- [Run #38264254555](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555): **SUCCESS** `ORGAVELA_AUDIT_TARGET=verified`, `ORGAVELA_AUDIT_SHELL=available`, `ORGAVELA_AUDIT_PLUGIN_VERSION=alpha2`, `ORGAVELA_AUDIT_PROBE=success`.
- [Run #38264354774](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264354774): **SUCCESS**, echte OrgaVela-WordPress-Instanz: `ORGAVELA_AUDIT_SCHEMA=good`, `ORGAVELA_AUDIT_TABLE_COUNT=29`, `ORGAVELA_AUDIT_DEFAULT_ORG=good`, `ORGAVELA_AUDIT_VERSIONS=good` (2/2). Installer `health()` im nachgewiesenen Alpha.2-Source verwendet `SchemaInspector::verify_all()` und `AgeAttestationSchema::verify()`; es prüft über `information_schema` physische Tabellen und deren Spalten, Indizes, Engines und Kollation gegen erwartete Definitionen. Zusätzlich wurden **unabhängig** Tabellenanzahl, Referenz auf die aktive Default-Organisation und Options-Versionen abgefragt. Keine Teilnehmerwerte ausgegeben.

**Ergebnis:** SFTP, SSH-Shell, WP-CLI, Domain/Plugin-Identität und 29 physische UOP-Tabellen mit gesundem Verifikationsstatus sind **jetzt live nachgewiesen**. Es waren keine zusätzlichen Zugangsdaten, kein PHP-Upload und kein SSH-Accountwechsel notwendig. O02 hat substanziell mehr reale Evidenz; das formale M1-/V1-Signoff und O03 Backup-/isolierter Restore sind noch nicht erfolgt. Keine schreibenden Website-/SQL-/Deployment-Operationen.

