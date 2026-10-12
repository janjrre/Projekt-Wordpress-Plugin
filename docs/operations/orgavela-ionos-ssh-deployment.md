# OrgaVela (IONOS): GitHub Actions → SFTP/SSH

**Stand:** 12.10.2026. **Nur:** https://orgavela.com, UOP Core und Repository `janjrre/Projekt-Wordpress-Plugin`. **Striktes Verbot:** Änderungen an Dreamloud oder Verwendung des Dreamloud-Hostings.

## Architektur und Sicherheitsgrenzen

Die GitHub-Action [deploy-orgavela-ssh.yml](../../.github/workflows/deploy-orgavela-ssh.yml) ist absichtlich **nur manuell** über `workflow_dispatch` startbar und nur vom Branch `main`. Sie verwendet die GitHub-Deployment-Umgebung `orgavela-ionos` und ausschließlich die dort konfigurierten Secrets. Sie setzt **keinen** fremden SSH-Hostschlüssel automatisch als vertrauenswürdig voraus: der hinterlegte `known_hosts`-Eintrag muss zur IONOS-Hostidentität passen.

Betriebsarten:
- `probe`: SSH-Anmeldung und rein lesende Prüfungen, einschließlich WordPress-Verzeichnis, `WP-CLI`, HTTPS-Adresse **genau `https://orgavela.com`**, vorhandener Schreibrechte und `unzip`. **Keine Datei-Uploads und keine Änderungen am Ziel.**
- `upload`: Nur nach erfolgreichem `probe`, zusätzlicher Eingabe `orgavela.com` und **explizit geprüfter SHA-256-Prüfsumme**. Lädt das bereits separat gebaute `uop-core.zip` von `staging/uop-plugin-package`, prüft ZIP-Struktur und SHA-256 gegen Package-Provenance **und** eingegebene Prüfsumme, transferiert es verschlüsselt per SSH/SCP und installiert ausschließlich den neuen Pluginordner. **Keine Aktivierung, keine Datenbankmigration, keine weiteren WordPress-Änderungen.**

**Wichtig:** Wenn `uop-core` schon installiert ist, verweigert der erste Workflow jegliche Überschreibung; Updates benötigen später ein eigenes geprüftes Backup-/Rollback-Verfahren. Die SHA-Prüfung ist eine Dateiintegritätsprüfung, ersetzt aber kein unabhängiges Code-/Security-Review. Keine Tests mit echten Teilnehmerdaten. `orgavela.com` ist aktuell eine Live-Adresse und darf nicht als private Stagingumgebung betrachtet werden, solange Zugangsschutz/Suchmaschinenindexierung nicht geprüft sind.

## 1. Dedizierten IONOS-Zugang anlegen (durch Kontoinhaber)

1. IONOS → **Menü → Web-Projekte → OrgaVela / orgavela.com → Einstellungen → Erweiterte Einstellungen → SFTP & SSH**. [Offizielle IONOS-Anleitung](https://www.ionos.de/hilfe/wordpress/administration-von-wordpress/sftp-benutzer-fuer-wordpress-anlegen/).
2. **Neues Konto erstellen**, möglichst nur für diese WordPress-Installation. Wenn die Auswahl besteht, **SFTP + SSH** aktivieren und den Zugriff auf das OrgaVela-Projekt begrenzen. Kein Zugang zu Dreamloud oder anderen Projekten erteilen.
3. IONOS **SFTP-Serverhostname**, **Benutzername**, **Passwort** und den tatsächlichen absoluten WordPress-Installationspfad ermitteln. Port im Workflow: **22**. Das SFTP/SSH-Konto darf WordPress-Plugin-Dateien schreiben und SSH-/WP-CLI-Befehle ausführen können.
4. **Keine Passwörter, Zugangsschlüssel, DB-Credentials oder `wp-config.php` in Chats oder GitHub-Issues/Commits veröffentlichen.**

Wenn der Pfad unklar ist: zunächst selbst eine SSH-Terminalverbindung herstellen und im zugewiesenen Projektverzeichnis nach `wp-config.php`, `wp-content/plugins` und dem absoluten `pwd` suchen. Der Wert für `IONOS_WP_ROOT` ist der **absolute Pfad zum Verzeichnis, das `wp-config.php` und `wp-content` enthält**. Das Workflow-Skript prüft diese Eigenschaft erneut und bricht bei Abweichungen ab. Wenn IONOS WP-CLI nicht als `wp` im PATH bereitstellt, schlägt der Verbindungstest bewusst fehl; zuerst den unterstützten CLI-Aufruf klären statt die Site-ID-Prüfung zu entfernen.

## 2. SSH-Hostidentität unabhängig prüfen

Der Hostschlüssel muss vor dem Eintrag in GitHub unabhängig verifiziert werden. Auf einem lokalen Rechner kann `ssh-keyscan -p 22 HOSTNAME > ionos_known_hosts` die öffentliche Hostschlüssel-Zeile erfassen; `ssh-keygen -lf ionos_known_hosts` zeigt den Fingerabdruck. **Das blinde Vertrauen in `ssh-keyscan` allein ist unsicher.** Den Fingerabdruck mit den aktuellen [offiziellen IONOS-Fingerabdrücken](https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/) oder einer ausdrücklich vom Anbieter bestätigten Hostidentität vergleichen. Erst den **verifizierten** `known_hosts`-Inhalt als Secret eintragen. Bei WordPress-Hosting können die veröffentlichten Webhosting-Fingerabdrücke abweichen; dann den zuständigen IONOS-Support fragen, keinesfalls unkontrolliert die SSH-Hostprüfung abschalten.

## 3. Geschützte GitHub-Umgebung erstellen

Im **UOP-Repository** (nicht in Dreamloud!) die [GitHub-Environments](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/environments) öffnen und die Umgebung **`orgavela-ionos`** anlegen. Wenn verfügbar, erlaubte Deployment-Branches auf **`main`** begrenzen und erforderliche Reviewer aktivieren; das Workflow-Skript kontrolliert den Branch ebenfalls.

In dieser Umgebung unter **Environment secrets** genau diese Namen anlegen:

| Name | Wert |
| --- | --- |
| `IONOS_SSH_HOST` | SSH/SFTP-Serverhostname, **nicht automatisch orgavela.com** |
| `IONOS_SSH_USER` | Dedizierter IONOS-SSH/SFTP-Benutzer |
| `IONOS_SSH_PASSWORD` | Passwort des dedizierten Kontos, **nur in GitHub Secret** |
| `IONOS_KNOWN_HOSTS` | Unveränderter und unabhängig verifizierter `known_hosts`-Eintrag für den Host |

Unter **Environment variables** zusätzlich:

| Name | Wert |
| --- | --- |
| `IONOS_WP_ROOT` | Absoluter WordPress-Installationspfad auf dem IONOS-SSH-Host, z. B. `/home/<ionos-benutzer>/htdocs` **nur falls tatsächlich so geprüft** |

Weder diese Secrets noch das Passwort dürfen im Repository als Datei oder im Chat übermittelt werden. Die `known_hosts`-Zeile ist kein persönlicher Geheimschlüssel, wird zur Vermeidung eines versehentlichen Hostwechsels dennoch zentral verwaltet.

## 4. Verbindung testen

[Workflow auf GitHub starten](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/workflows/deploy-orgavela-ssh.yml) → **Run workflow** → Branch `main` → `operation = probe`. Keine Zielbestätigung oder SHA für Probe notwendig.

Erwartet: `Verified OrgaVela WordPress root, SSH identity and plugin write access.` Bei Fehler: nur die Fehlermeldung (ohne Secrets) im Arbeitsprotokoll festhalten und Ursachen prüfen. Ohne erfolgreichen Probe-Lauf **kein** Upload.

## 5. Erstes Plugin-Paket hochladen

Nach erfolgreicher Probe das Paket vom Branch `staging/uop-plugin-package` samt `package-provenance.txt` gegen den freigegebenen Entwicklungsstand vergleichen. Stand vor dem ersten SSH-Upload: `source_sha=4f2c4df800caf1e5708f05cce9536a7afb34aaa0`, `sha256=acbebdd9df25d996848bbca6ebe6726ee1a986207fc7c7b818474dc93134267a`. Der aktuelle SHA muss erneut geprüft werden; beim Neubau ändert sich die Prüfsumme.

Workflow → **Run workflow** → Branch `main` → `operation=upload`, `confirm_target=orgavela.com`, `expected_sha256=<tatsächlicher verifizierter 64-stelliger SHA-Wert>`.

Erwartet: `UOP plugin files installed and verified – NOT activated; no DB migration.`. Anschließend `mosmcp__plugin-list` lesen, UOP-Dateien und Version verifizieren, separate Sicherheits-/Backup-Gates für **Aktivierung/Migration** prüfen und erst dann aktivieren. Die Plugin-Berechtigung für Aktivierung ist über den bereits auf `orgavela.com` verbundenen WordPress-MCP vorhanden.

## Kosten, Fehlerfälle, Übergabe

GitHub-hosted Actions und IONOS-Zugang nur innerhalb bereits freigegebener Tarifleistungen verwenden; **keine automatischen zusätzlichen Verträge**. Fehlgeschlagene Probe verursacht keine WordPress-Schreiboperation. Fehlgeschlagener erstmaliger ZIP-Upload versucht, die neu angelegte Plugininstallation wieder zu entfernen; Ausfälle müssen vor einem erneuten Lauf überprüft werden. Wiederherstellung bereits vorhandener/aktiver Pluginversionen ist ausdrücklich nicht Teil dieses Erstinstallationsworkflows.

Nach **jedem** Verbindungs-/Uploadversuch: Auftrag, Wahl des Weges, Branch/Commit, Workflow-Run-ID, Prüfergebnis und nächster Schritt in `docs/operations/ASSISTANT_WORK_LOG.md` dokumentieren, ohne Secrets oder vertrauliche Hostdetails zu veröffentlichen.
