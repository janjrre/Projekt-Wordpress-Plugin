# OrgaVela / W0–O03: ausschließlich IONOS + privates GitHub-Backup-Repository

**Verbindlicher Betreiberentscheid: 13.10.2026** · Status: **Strategie geändert, noch kein privates Repository und kein echtes Backup/Restore nachgewiesen**. Dieses Runbook ersetzt das externe Google-Drive-Backupziel aus früheren O03-Arbeitsschritten; alte Einträge bleiben als Historie bestehen.

## 1. Architektur und Grenzen

**Alle künftig selbst erstellten OrgaVela-Offsite-Backups** werden in **einem separaten GitHub-Repository mit Sichtbarkeit `private`** als **vor dem Upload verschlüsselte GitHub-Release-Assets** abgelegt. Zusätzlich sollen die Sicherungsmöglichkeiten des **eigenen** IONOS-OrgaVela-Hostingvertrags bereitstehen. Es findet **kein** Google-Drive-Backup oder Cloud-Upload mehr statt; der zuvor manuell angelegte leere private Drive-Ordner wird nicht als Ziel benutzt. Der bisherige nur synthetische GitHub-Actions-Drive-Workflow wurde auf `main` entfernt, Commit `58b148cb2db1f8e9031d2e9a14a05db62b1c5c7a`.

- **Quelle:** ausschließlich echte OrgaVela-WordPress-Dateien + zugeordnete MariaDB; WP-Installationsidentität und Host-Key vor dem Lesen prüfen; **nie Dreamloud**.
- **Ausführungsanbieter:** **CircleCI Free** als getrenntes OrgaVela-Projekt; dessen kleiner geheimnisfreier Offline-Pilot hat echten [Build #2 success](https://circleci.com/gh/janjrre/Projekt-Wordpress-Plugin/2) nachgewiesen. Das ist **noch kein** Backupjob. GitHub-hosted Actions werden wegen des berichteten Budgetstopps nicht vorausgesetzt.
- **Ziel:** empfohlenes neues Repo **`janjrre/orgavela-backups`**, zwingend **Private**, nur notwendige Benutzer/Apps, nie GitHub Pages. Repo noch **nicht existierend/verifiziert**. In das Git-Repository selbst dürfen **keine** Backup-TARs, SQL-Dumps, Konfigurationen, Archive oder privaten Schlüssel committed werden.
- **Speicherart:** GitHub **Release-Assets**; je Asset **unter 2 GiB**, bei größeren Gesamtarchiven mehrere einzeln verschlüsselte Paketteile mit Manifest und prüfbarer Reihenfolge. Release-Metadaten/Tag/Namen nur mit nicht vertraulichen Zeit-/Typinformationen, keine WordPress-Personendaten. Die normalen per Release automatisch generierten Source-ZIPs sind **keine** WordPress-Backups.
- **Verschlüsselung:** lokale, vom Betreiber kontrollierte `age`-Public-/Private-Key-Verschlüsselung **vor** Veröffentlichung bei GitHub. CircleCI erhält **nur den öffentlichen Empfängerschlüssel**. Privater Recovery-Key mit zweiter offline gelagerter Kopie **nie** in GitHub, CircleCI, IONOS, Google Drive, Chat oder Issues. Es muss für den **echten Wiederherstellungstest** ein erreichbarer Betreiber-/privater Restorehost existieren.
- **Nachweis:** Snapshot-Konsistenz und Zeitabgleich Datei/SQL, SHA-256 der verschlüsselten Daten **vor Upload und nach vollständigem Download**, verschlüsselt enthaltenes Inventar/Hashliste für WordPress und SQL, separates privates Entschlüsseln und **wirklich isolierter WordPress+MariaDB-Restore**. Nur beim Bestehen ist O03/B04 abnahmefähig.

**Bewusst akzeptierter Nachteil:** Quellcode und zusätzliche Backups liegen beim **gleichen Anbieter und mutmaßlich Konto GitHub**. Ein Kontoverlust oder GitHub-Ausfall betrifft beide. Der Betreiber hat diese Variante **bewusst statt Google Drive gewählt**. Die separate private Repository-Grenze und selbst kontrollierte Verschlüsselung verringern Datenoffenlegung, sind aber **kein unabhängiger Drittanbieter / unveränderlicher WORM-Speicher / vollständiges 3-2-1-Backup**. Daher IONOS-Recoveryverfügbarkeit tatsächlichen Hostingvertrags prüfen, unabhängig vom GitHub-Quellzugang, und Notfallkontakt/Key/Wiederherstellungsablauf außerhalb GitHub verfügbar halten (kurze schriftliche Runbook-/Key-Kopie offline, **keine** zusätzliche Daten-Backupablage entgegen Betreiberentscheidung).

## 2. Einmalige Betreiberaktion: privates Repository anlegen

Der hier verfügbare GitHub-Connector kann GitHub-Dateien/Issues/PRs bearbeiten, aber **kein neues Repository erstellen und keine privaten Repo-/CircleCI-Secrets konfigurieren**. Ohne manuelle GitHub-Oberfläche nicht als „bereits angelegt“ deklarieren.

1. [GitHub → Neues Repository](https://github.com/new) öffnen.
2. **Owner:** `janjrre` · **Repository name:** empfohlen `orgavela-backups` · **Visibility:** ausdrücklich **Private**.
3. Kein Template, keine importierten persönlichen Dateien. Eine kurze README ist zulässig und als Tag-Ziel für Releases praktisch (falls GitHub sie anbietet, „Add a README“ aktivieren); keine privaten Tokens dort hinterlegen.
4. Neue Seite öffnen und verifizieren, dass die Sichtbarkeit **Private** ist. Eine neue URL ist **erst nach tatsächlicher Erstellung** der Beleg: `https://github.com/janjrre/orgavela-backups`.
5. Kein Zahlungsupgrade/Actions-Budgetanschub nötig. Ein normales privates Repository ist grundsätzlich auf GitHub Free nutzbar; zusätzliche kostenpflichtige Funktionen, automatisierte Credits oder Verträge **nicht** aktivieren.

**Wichtig:** Bis die geschützte Repo-Identität und tatsächliche Rechte bestätigt wurden, weder Testarchiv noch echten WordPress-/SQL-Snapshot dorthin hochladen. Falls der Name bereits belegt ist: anderes privates `janjrre/*`-Repo wählen und Dokumentation/Werte **vor dem ersten Upload** konsistent anpassen.

## 3. Einmalige Betreiberaktion: Schlüssel und GitHub-API-Zugriff

- Auf eigenem vertrauenswürdigem Rechner `age-keygen -o orgavela-private-age-key.txt`; `age-keygen -y orgavela-private-age-key.txt` gibt den **öffentlichen** `age1...`-Empfänger aus. Privaten Schlüssel und separate Recovery-Kopie sicher offline verwahren. Niemand in ChatGPT/CircleCI/GitHub fragt den privaten Schlüssel an.
- Im [GitHub Developer settings → Fine-grained tokens](https://github.com/settings/personal-access-tokens/new) **einen** ablaufenden, nur auf das **private Backup-Repo** eingegrenzten **fine-grained PAT** erstellen: Resource owner `janjrre`, **Only select repositories → `orgavela-backups`**, `Contents: Read and write`, Minimum Metadata-Lesen; **keine** Admin/Secrets/Workflow-Berechtigungen, keine `Projekt-Wordpress-Plugin`-/Dreamloud-Rechte. Ablauf/Rotation/Revocation dokumentieren. [Offizielle GitHub REST: Release create / Contents write](https://docs.github.com/en/rest/releases/releases).
- Private Token-Eingabe **nur** im geschützten CircleCI-Context für **OrgaVela**, beispielsweise `orgavela-backup-releases`, als `ORGAVELA_BACKUP_GITHUB_TOKEN`; **niemals** ChatGPT, öffentliche Repo-Datei, Commit oder Issue. Öffentlicher `age1...`-Empfänger kann als CircleCI-Environment-Variable `ORGAVELA_BACKUP_AGE_RECIPIENT` gesetzt werden. **Noch keine** produktiven SSH-Credentials eintragen.
- Den CircleCI-Context **mindestens** auf das Projekt `janjrre/Projekt-Wordpress-Plugin` und genau geschützte Branches/Workflows beschränken; **SSH-Reruns unterbinden**, soweit CircleCI UI unterstützt; GitHub-Branchschutz auf tatsächliche Push-Rechte prüfen. **Solange nicht beweisbar, dass nicht vertrauenswürdige PR-/Branch-Codeänderungen keinen Zugriff auf den Token erhalten, Token nicht eintragen und keine geheime Jobstufe starten.** Bei CircleCI öffentlichem Quellrepository sind normale Config-Branch-Filter allein kein ausreichender Schutz gegen bösartigen Commit auf demselben Branch.
- Für die spätere *echte* Daten-Sicherung werden separate **OrgaVela-only** IONOS-SSH-Geheimnisse im streng beschränkten **Quelljob** benötigt (GitHub-Environment-Secrets werden nicht automatisch an CircleCI übertragen). Ein Uploadjob sollte nur ein **bereits verschlüsseltes Artefakt** plus beschränkte GitHub-Repo-Schreibrechte sehen; **nicht** gleichzeitig IONOS- und GitHub-Secrets in einem unüberprüften Job kombinieren.
- Beim ersten Test vor echten Daten nur **synthetische** Dateien und verschlüsselte Testarchives benutzen; keine echten Produktiv-Backups im öffentlichen CircleCI-Artifactspeicher/Logs. Credentials nur unter Betreiberfreigabe.

## 4. Erste technische Teststufen und Erfolgskriterien

| Gate | Aktion | Nachweis und Stopbedingung |
|---|---|---|
| GH-01 | Privates separates GitHub-Repo + Sichtbarkeit/Owner/Release-Rechte prüfen | GitHub API belegt `visibility=private`; bei `public`, 404 oder ungeklärten Rechten: **stop** |
| GH-02 | CircleCI Free-Credits, Branch-Protection und Context-Sichtbarkeit prüfen | Nur Betreiber-/Reviewed-Branchcode darf Release-PAT erhalten; keine Context-Secrets auf offenen PRs |
| GH-03 | age-Public-Key-Encrypt/Decrypt und synthetische Manifest-/SHA-256-Regressionsprobe | Privater Schlüssel ausschließlich lokal, Prüfdaten künstlich, Negativtests für defekten Ciphertext |
| GH-04 | Einmaliger **manueller**, rein synthetischer encrypted Release-Asset-Upload aus CircleCI → privates Backup-Repo | GitHub API liefert Release-/Asset-ID, ausschließlich Ciphertext unter 2 GiB; voller Rückdownload + SHA-256 identisch. Keine echte WP-/SQL-Datei |
| GH-05 | Betreiber entschlüsselt genau dieses synthetische Asset **privat**, Prüfinhalt stimmt | Erst danach Authentifizierung/Backups mit echten Daten überdenken |
| GH-06 | Geprüfter vollständiger **einmaliger echter** WordPress-Datei-/MariaDB-Read-Snapshot (nur OrgaVela) → vor Upload `age` → Release Asset → unabhängiger Rückdownload | Alle Files/DB, kontextgebundenes Manifest, SQL-Export-Konsistenz, Vollständigkeit + Hash. Keine Klartexte in Release, Git-Historie, Jobartefakten oder Logs |
| GH-07 | Genau GH-06 in einer isolierten privaten WordPress/MariaDB-Kopie zurückspielen, ohne externen Netzwerkzugang/Cron/Mail/Webhooks | 29 UOP-Tabellen-/Index-/Optionen-Health, DB-Version 2/2, notwendige Dateien, reale Restorezeit. Bei Fehlschlag O03/B04 **offen** |
| GH-08 | Regelmäßigen CircleCI-Cron/Zeitplan, Benachrichtigungen und ggf. Rotation konfigurieren | **Nur nach GH-07.** Bis Betreiber ausdrückliche Aufbewahrungsentscheidung trifft: **keine automatische Löschung** bestehender Releases. Speicher-/API-/Quota-Folgen prüfen |

GitHub Releases erlauben laut [GitHub Docs](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases) jeweils Assets **unter 2 GiB**, bis 1000 Assets je Release, ohne publiziertes Gesamtvolumen- oder Bandbreitenlimit. **Kein Backup-SLA:** Release-Assets lassen sich mit ausreichenden Repository-Rechten löschen, ein gesperrtes Konto kann Recovery erschweren. GitHub-Artefakte und Git-Commits sind keine gleichwertige langfristige Backupablage.

## 5. Status, Rollback und Kosten

**Aktueller Stand nach Betreiberentscheidung:** Noch **kein** `janjrre/orgavela-backups`-Repository durch GitHub API nachgewiesen; hierfür besitzt der verbundene GitHub-Connector keine `create_repository`-Aktion. **Kein** CircleCI Context/PAT, kein privater `age`-Recovery-Key, kein GitHub Releases Asset, kein echter WordPress-/MariaDB-Dump, kein echter isolierter Restore erstellt oder abgenommen. Existing `O03-CI-01` CircleCI Offline-Test bleibt weiterhin grün; O03 bleibt `partial`, B04 und R03 weiter offen.

**Rollback:** Neuer Release-/Backupmechanismus wird niemals ungeprüft produktiv aktiviert. Bei Testfehler keine Löschung oder Wiederherstellung auf der realen OrgaVela-Site; Pilotbranch/Workflow lässt sich ohne Datenmutation stoppen, Tokens im GitHub/CircleCI UI zurückziehen. Der alte Google-Drive-Workflow ist stillgelegt, kein automatischer Fallback dorthin. IONOS-Sicherungen/Vertrag unabhängig davon unverändert; ihren echten verfügbaren Verlauf im IONOS-Control-Panel bestätigen.

**Kosten:** Nur bestehender GitHub-Account und CircleCI Free, kein bezahlter Anbieter/Upgrade ohne gesonderte Zustimmung. GitHub-Free-/CircleCI-Free-/API-/Sicherheitsregeln vor geplanten regelmäßigen Übertragungen bestätigen, keine Annahme unendlicher freier Backup-Infrastruktur.
