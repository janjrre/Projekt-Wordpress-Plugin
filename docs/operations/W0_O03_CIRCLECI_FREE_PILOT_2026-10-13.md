# W0/O03 – OrgaVela / CircleCI Free: eigenständiger geheimnisfreier Integrationspilot

**Stand:** 13.10.2026. **Status:** Branch/Pilot-PR erstellt; externer CircleCI-Status erst nach tatsächlich erfolgtem Providerlauf akzeptiert. **Keine Cloud- oder echten WordPress-Backups.**

## 1. Auftrag, Ausgangslage und Isolation

Der Betreiber meldet, er habe **`janjrre/Projekt-Wordpress-Plugin`** bei CircleCI verbunden. GitHub-hosted Actions sollen wegen seines erreichten Account-Budgetlimits nicht mehr die Voraussetzungen für eine Backup-Strecke darstellen. Der getrennte Dreamloud-CircleCI-Free-Pilot ist eine **nur lesend analysierte Referenz**; Dreamloud-Code, Credentials, IONOS-Hosting und CircleCI-Contexts bleiben unberührt.

Der Quellstand auf `main` besitzt kein bestehendes `.circleci/config.yml`; die historisch begonnenen UOP-Fachimplementierungen liegen überwiegend in noch nicht gemergten PRs. Deshalb kontrolliert der allererste CircleCI-Job ausschließlich die reale UOP-Repo-Zuordnung, die Pflichtdokumentation sowie `bash -n` der vorhandenen CI-Shellskripte und maschinenlesbare Fortschrittsinvarianten – **keinen** vollen Integrationstest, kein B04-Backup und kein Freigabe-Gate.

## 2. Tatsächlich erstellt

- Branch: [`ci/circleci-free-pilot-o03`](https://github.com/janjrre/Projekt-Wordpress-Plugin/tree/ci/circleci-free-pilot-o03), separat von `main`.
- [Draft-Pull-Request **#17**](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17), **nicht gemergt**, keine automatische Merge-Freigabe.
- Neue [`.circleci/config.yml` auf Pilotbranch](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/ci/circleci-free-pilot-o03/.circleci/config.yml), Commit [`d14069c8302da177ee93458950ecd82ed7726655`](https://github.com/janjrre/Projekt-Wordpress-Plugin/commit/d14069c8302da177ee93458950ecd82ed7726655).

**Ein einzelner ressourcensparender CircleCI-Job:**
- `cimg/python:3.12`, `resource_class: small`, kein Docker-in-Docker, keine Zusatzdienste.
- **Nur Branch `ci/circleci-free-pilot-o03`**, kein Main-/Tag-/Cron-/Release-Trigger.
- Positiv-Prüfung Projekt `janjrre/Projekt-Wordpress-Plugin` und exakter Branch; Pflichtdateien/JSON-Invarianten; Bash-Syntax der beiden vorhandenen `scripts/ci/*.sh`.
- **Negativ-Prüfung**, dass keine **der bekannten** IONOS-/SFTP-/Drive-/Duplicati-/Private-Key-Secrets in den Pilot injiziert werden. **Kein** CircleCI Context oder Secret wird von der Konfiguration angefordert.
- Kein IONOS/SSH/SFTP, WordPress, Drittanbieter-Netzwerkaufruf, GitHub-Releases-Upload, Google Drive, personenbezogene Daten, `schedule`, Deploy, Cache, hochladbare Artefakte oder neues kostenpflichtiges Angebot.

**Geringer Ressourcenbedarf** ersetzt keine Prüfung der tatsächlich verbleibenden CircleCI-Free-Credits oder der automatischen GitHub-Webhook-Auslösung. Kein Ergebnis behaupten, solange der Provider keinen erfolgreichen Status/Job für den **exakten** Commit meldet.

## 3. Abnahmebeleg und Stop-Gate

`O03-CI-01` ist erst technisch bestanden, wenn CircleCI **selbst** für Commit `d14069c8302da177ee93458950ecd82ed7726655` das Projekt `janjrre/Projekt-Wordpress-Plugin` und den Job `offline-connection-smoke` **success** meldet und die Ausführung/Run-ID belegbar ist. Status `pending` oder `statuses=[]` ist **kein** erfolgreicher CircleCI-Lauf.

**Nicht** durch Erfolg freigegeben: GitHub-Actions-/vollständige PR-Checks, OrgaVela-IONOS-Audit, echte Produktionsexporte, Cloud-Verbindungs-Secrets, B04 / O03 echte Wiederherstellung, kostenpflichtige Pläne, M0–M7-/P4-/Releaseabnahme oder Merge von PR #17.

## 4. Falls kein CircleCI-Build erscheint

1. In [CircleCI → Projects](https://app.circleci.com/projects) prüfen, ob **`janjrre/Projekt-Wordpress-Plugin`**, nicht Dreamloud, als *Follow / Set Up Project* tatsächlich aktiv und nicht nur CircleCI als ChatGPT-App verknüpft ist.
2. Als Config-Quelle **Repo-/GitHub-YAML** und Pfad `.circleci/config.yml` nutzen. CircleCI-GitHub-App/Projekt-Trigger-Integration für Branch-Pushes auf `ci/circleci-free-pilot-o03` prüfen (kein Trigger neu beauftragt, keine geheimen Tokens).
3. CircleCI **Plan / Usage** auf Free-Tarif und verfügbare Credits untersuchen, ohne Billing-Limit zu erhöhen oder Zahlungsverpflichtung einzugehen.
4. Falls CircleCI nur den Defaultbranch liest: nicht eigenmächtig PR #17 mergen, sondern den alternativen CircleCI-Trigger-/Konfigurationsweg reviewen und die Restriktionen des Operators beibehalten.
5. Bei Jobfehler ausschließlich bereinigte, nicht vertrauliche Diagnose lesen, dann kleine branchgebundene Korrektur committen und identischen Sicherheitsumfang wahren.

## 5. Nach grünem Pilot – nächste W0-O03-Pakete

1. CircleCI-Free-Kontingent, Branchschutz und Projekt-Zuordnung nachweisen; eine nur OrgaVela betreffende Context-/Secret-Matrix mit Review/Restricted Expressions planen. **Keinerlei Dreamloud- oder Google-GitHub-Secrets übernehmen.**
2. Zunächst unabhängigen **synthetischen** `age`-Backup-/Transport-/Hash-Restore-Test mit *öffentlich zugänglichem Testempfänger* und ausschließlich künstlichen Daten auf CircleCI ausführen.
3. Betreiber erstellt und verwahrt dauerhaft den **privaten** Age-Recovery-Key, gibt nur den Public-Recipient frei und autorisiert eine eigene, eingeschränkte Google-Drive-OAuth-Verbindung **direkt bei CircleCI**. Bereits vorhandener privater OrgaVela-Drive-Ordner bleibt Primärziel; optional verschlüsselte Release-Assets in **separatem privaten GitHub-Backup-Repo** als zweite Kopie.
4. Erst nach sicherer synthetischer Cloud-Upload-/Readback-/Integrity-Probe die **echte** OrgaVela-Datei+DB-Sicherung (nicht auf Dreamloud), vollständig vor Cloud-Transfer verschlüsselt, ausführen und exakt diese Sicherung **offline/isoliert** zurückspielen; Mail, Cron und Webhooks beim Restore blockieren.

**Quellen:** [Masterplan](V1_ACCEPTANCE_PLAN.md), [OrgaVela-Google-Drive-Runbook](ORGAVELA_GOOGLE_DRIVE_BACKUP_SETUP.md), [W0 CircleCI-/GitHub-Vergleich](W0_O03_CIRCLECI_GITHUB_BACKUP_ASSESSMENT_2026-10-13.md), [Worklog](ASSISTANT_WORK_LOG.md), [maschinenlesbarer Fortschritt](V1_PROGRESS.json).
