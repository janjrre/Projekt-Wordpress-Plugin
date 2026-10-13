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


## 6. Neuer Live-Befund aus Betreiber-Screenshot: Pipeline #8/#9 existieren, Config-Fetch schlägt fehl (13.10.2026)

**Betreiber-Screenshot:** In der CircleCI-Mobile-Ansicht werden die **Pipelines #8 und #9** für „Projekt-Wordpres…“ angezeigt, jeweils **roter Konfigurationsabruf-Fehler** mit sichtbarem, **abgeschnittenem** Wortlaut `Could not fetch configuration: No co...`; darunter `No workflows for this pipeline.` Der vollständige Fehler und die jeweiligen Source-/Checkout-Branches sind **nicht** sichtbar. Sicher ist: CircleCI hat zwei Pipeline-Ereignisse erfasst; daraus folgt **nicht**, dass die Konfiguration geladen oder ein Job gestartet wurde.

**GitHub-API-Verifikation desselben Tages:** `GET .circleci/config.yml?ref=main` **HTTP 404 / Datei nicht vorhanden**; `GET .circleci/config.yml?ref=ci/circleci-free-pilot-o03` **Datei vorhanden**, Inhalt geprüft (`version: 2.1`, genau ein kleiner `offline-connection-smoke` nur für den Testbranch). Damit ist fehlende Konfiguration auf `main` oder einem anderen ausgelösten Branch eine **konkrete plausible Ursache**, aber das Screenshot allein weist den genauen Branch nicht nach.

**Offizielle CircleCI-Hinweise:** [Pipeline-Konfigurationsquelle](https://circleci.com/docs/guides/orchestrate/pipelines/) stellt klar, dass CircleCI im Standard `.circleci/config.yml` als Quelle verwendet; [Trigger-Dokumentation](https://github.com/circleci/circleci-docs/blob/main/docs/guides/modules/orchestrate/pages/triggers-overview.adoc) nennt die separate Auswahl von **Config source branch** und bei GitHub App zusätzlich Checkout-Branch. [CircleCI-Forum](https://discuss.circleci.com/t/how-to-limit-pipeline-or-workflow-only-on-1-branch-or-how-to-prevent-pipeline-from-be-called-on-branch-without-config-yml/42254) dokumentiert, dass Branchfilter **keinen Config-Fetch-Fehler verhindern**, wenn die Datei auf dem vom Pipeline-Trigger verwendeten Branch fehlt.

**Bevorzugte Reparatur ohne PR-Merge oder kostenpflichtige CI:**
1. In CircleCI beim Projekt `Projekt-Wordpress-Plugin` die rote **Pipeline #9** öffnen und im Detail den vollständigen **Config-Fetch-Fehler** sowie den **Config source branch** (ggf. getrennt Checkout branch) prüfen. Bei `main` oder anderem Branch ohne `.circleci/config.yml` bestätigt sich die direkte Ursache.
2. Aus dem Projekt **„Trigger Pipeline“** mit Config source branch `ci/circleci-free-pilot-o03` starten; falls GitHub App Checkout-Branch separat abgefragt wird, **denselben** Branch wählen. Standard-Konfigurationspfad `.circleci/config.yml`; keine externen Parameter, Contexts, Secrets, Restore- oder Backupjobs.
3. Vor dem Auslösen bestätigen: Free-Credits vorhanden; einzig zulässiger Job `offline-connection-smoke` mit `resource_class: small`. **Nur ein** manueller Test, keine wiederholte Ausführung bei ausbleibender Konfiguration.
4. Nach CircleCI-Lauf **echte** Job-ID/URL und Provider-Status bzw. Step-Logs prüfen; nur bei tatsächlich bestandenem Job als erfolgreich dokumentieren. Wenn weiterhin Config-Fetch scheitert, CircleCI **Project Settings → Project Setup / Pipelines / Config source** und gegebenenfalls GitHub-App-Branch-/Zugriffsrechte überprüfen. **Kein** reguläres GitHub-Actions-Kontingent als Ersatz verbrauchen.
5. Nicht einfach [Draft-PR #17](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/17) eigenmächtig mergen oder die Pilot-Datei nach `main` kopieren: bestehendes ausdrückliches **No-auto-merge-Gate** beibehalten. Ein künftiger gezielter Main-Pilot-Config-Rollout ist eine getrennt freizugebende Entscheidung und benötigt streng begrenzte Branchfilter/Trigger, weil auch Code aus fremden Forks/Pull Requests sonst Workflows auslösen könnte.

**Status:** Die Diagnose mit tatsächlichen GitHub-Dateibelegen ist bestätigt, das Branch-Mismatch als Ursache **hoch plausibel**. Weder die beiden historischen roten Pipelines noch der technisch vorbereitete Draft-PR sind bisher erfolgreiche CircleCI-CI- oder Backup-Abnahmen. Kein neuer CircleCI-Trigger in dieser Prüfung, keine Kosten, Secrets, Live-Datei-/DB-Sicherung, Dreamloud-Änderung oder produktive Migrationsaktion.
