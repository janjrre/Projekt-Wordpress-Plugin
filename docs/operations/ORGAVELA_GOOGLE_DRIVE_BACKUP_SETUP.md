> **HISTORISCH / NICHT MEHR AUSFÜHREN – 13.10.2026.** Der Betreiber hat Google Drive als OrgaVela-Backupziel ausdrücklich abgewählt. Verbindlicher neuer Speicherpfad: **IONOS-Vertragsbackups + ausschließlich verschlüsselte GitHub-Release-Assets in einem gesonderten privaten Backup-Repository**. [Aktuelles Runbook](W0_O03_GITHUB_PRIVATE_BACKUP_RUNBOOK.md) · [Normativer Masterplan](V1_ACCEPTANCE_PLAN.md). Das nachfolgende OAuth-/rclone-Setup ist nur als früherer dokumentierter Ansatz erhalten und darf **nicht** für neue OrgaVela-Backups eingerichtet oder ausgeführt werden. Der alte Drive-Actions-Workflow wurde aus `main` entfernt. Bereits angelegter privater Drive-Ordner wird nicht mehr verwendet; weder dortige Inhalte noch Ordner werden ohne gesonderten Auftrag gelöscht.

---

# OrgaVela – einmalige Google-Drive-Anbindung für verschlüsselte Backups

**Stand 12.10.2026 | Paket W0/O03 | Status: Zielordner erstellt, lokale Kryptoprüfung grün; CI-OAuth-Verbindung noch NICHT authentifiziert und kein echter Drive-Upload.**

## Verifizierte Basis

- Der Betreiber hat Google Drive ausdrücklich als dauerhaften, PC-unabhängigen Sicherungsspeicher gewählt.
- Im mit ChatGPT verbundenen Google Drive wurde ein neuer Ordner **„OrgaVela – verschlüsselte Backups“** direkt in „Meine Ablage“ erstellt. Über Drive-Metadaten gelesen: `shared=false`, Berechtigungen ausschließlich **Eigentümer**, nicht öffentlich freigegeben. Die **Ordner-ID wird nicht im öffentlichen GitHub-Repository abgelegt**; der Betreiber übernimmt sie einmalig in sein lokales rclone-Konfigurationsprofil.
- GitHub Actions [`.github/workflows/orgavela-google-drive-connection.yml`](../../.github/workflows/orgavela-google-drive-connection.yml) hat den rein lokalen, ausschließlich **synthetischen** Age-Verschlüsselungs-/Entschlüsselungstest auf [Run #38294515636](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38294515636) **erfolgreich** bestanden. Der manuelle Zweig `upload-synthetic` ist als verschlüsselte Verbindungstest-Datei vorbereitet, **nicht** getestet: Es fehlen vom Betreiber autorisierte OAuth-Credentials und der **öffentliche** Age-Empfängerschlüssel im getrennten GitHub Environment.
- Bei Live-IONOS gilt weiter: echter Lese-Backup-Preflight [#38267570599](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267570599) grün; der isolierte *synthetische* MariaDB-Restore [#38267901371](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38267901371) grün. **Weder echter Backup-Dump noch Restore**.

## Warum Drive mit ChatGPT nicht für GitHub Actions ausreicht

Der Drive-Connector authentifiziert Aktionen **in dieser ChatGPT-Sitzung**; er übergibt keine wiederverwendbaren OAuth-Refresh-Tokens an GitHub Actions. Die dauerhafte automatische Sicherung benötigt einen **gesonderten, vom Betreiber bei Google bestätigten** GitHub-/rclone-API-Zugang. Den damit verbundenen **Refresh-Token** darf man niemals im Chat, in GitHub-Dateien, Commits, Issues oder Logs veröffentlichen. Im öffentlichen Repository werden nur technische Abläufe dokumentiert. Die Secrets müssen im geschützten GitHub Environment stehen.

## Einrichtung, einmalig auf Betreibergerät (keine neuen kostenpflichtigen Verträge)

### 1. Lokalen privaten Entschlüsselungsschlüssel anlegen

Das Open-Source-Programm [age](https://github.com/FiloSottile/age/releases) auf einem vertrauenswürdigen Betreiber-PC installieren. Lokal im Terminal:

```bash
age-keygen -o orgavela-private-age-key.txt
age-keygen -y orgavela-private-age-key.txt
```

Die zweite Zeile gibt eine **öffentliche Empfangsadresse** im Format `age1...` aus. Diese ist **nicht geheim** und wird als GitHub **Environment variable** `ORGAVELA_BACKUP_AGE_RECIPIENT` gespeichert. Die Datei `orgavela-private-age-key.txt` (privater Key) **nie** in GitHub, ChatGPT, Drive oder öffentliche Ablagen kopieren; sicher offline speichern und unabhängig davon eine geschützt gelagerte Wiederherstellungskopie verwahren. Ohne privaten Schlüssel sind spätere Backups nicht wiederherstellbar.

### 2. Google-OAuth für rclone installieren und die Zielordner-Identität fixieren

1. [Google Cloud Console](https://console.cloud.google.com/) öffnen und ein **eigenes** Projekt für „OrgaVela Backup“ einrichten; **keine Abrechnung aktivieren oder zahlungspflichtige Dienste buchen**. Die **Google Drive API** im Google Cloud APIs & Services-Bereich aktivieren, sofern dies ohne Kosten-/Billing-Verpflichtung möglich ist.
2. Über **Google Auth Platform** OAuth-Consent einrichten und einen **OAuth Client vom Typ Desktop-App** erstellen. Für langlebige regelmäßige Sicherungen wichtig: Google dokumentiert, dass **externe OAuth-Apps im Status Testing** Refresh-Tokens in der Regel nach **sieben Tagen** verlieren. Für unbeaufsichtigte Nutzung gegebenenfalls gemäß Google-Anleitung die App veröffentlichen; falls eine Freigabe oder Verifizierung verlangt wird, nicht umgehen. [Google OAuth-Gültigkeit](https://developers.google.com/identity/protocols/oauth2).
3. [rclone](https://rclone.org/install/) auf einem vertrauenswürdigen Betreiber-PC installieren. `rclone config` ausführen, neuen Remote **`orgavela_drive`** erstellen, Provider **Google Drive**, **eigene** Google-OAuth-Client-ID und OAuth-Client-Secret verwenden. Bei der Abfrage des Drive-Scopes muss die Berechtigung transparent entschieden werden: **Für den bereits über einen anderen Google-Connector erstellten Ordner ist in rclone praktisch die Berechtigung `drive` (vollständiger Google-Drive-Zugriff) notwendig.** `drive.file` erlaubt dem rclone-OAuth-Client normalerweise nur den Zugriff auf von **dieser** App erzeugte Dateien/Ordner; damit ist der bereits angelegte Zielordner nicht erreichbar. `root_folder_id` begrenzt die Anzeige/Zielpfade **in rclone**, aber **nicht** den eigentlichen Umfang des Google-OAuth-Tokens.
4. Wenn die breitreichende `drive`-Freigabe für einen normalen Google-Account nicht akzeptabel ist: **bevorzugt ein separates nur für verschlüsselte Backups verwendetes Google-Konto** oder – nach eigener Einrichtung – ein **durch rclone unter `drive.file` neu erstellter und ausschließlich von rclone verwalteter Ordner**, anstelle dieses vorhandenen Ordners. Keine automatische Erweiterung des Berechtigungsumfangs ohne Google-OAuth-Zustimmung.
5. In der rclone-Konfiguration bei **Advanced `root_folder_id`** die ID aus der URL **des zuvor erstellten privaten OrgaVela-Backup-Ordners** eintragen (oder bei `drive.file` dessen eigenes per rclone erzeugtes Verzeichnis). Im Google-Browserdialog den **explizit gewählten Google-Account** freigeben. Niemals OAuth-Client-Secret, Refresh-Token oder das gesamte `rclone.conf` im Chat mitteilen.
6. Lokal **ohne Upload** prüfen, dass `rclone lsf orgavela_drive:` nur die erlaubte Backup-Ordner-Ansicht zeigt. Die Ansicht ist **kein** serverseitiges OAuth-Rechteisolationsargument. Keine persönlichen Drive-Ordner außerhalb der Backup-Ablage durchsuchen.

Dokumentation: [rclone Google Drive](https://rclone.org/drive/), [Google `drive.file`-Scope](https://developers.google.com/workspace/drive/api/guides/api-specific-auth).

### 3. Separates GitHub Environment mit Drive-OAuth-Secret anlegen

In [GitHub → Repository → Settings → Environments](https://github.com/janjrre/Projekt-Wordpress-Plugin/settings/environments) ein neues Environment **`orgavela-backup-drive`** einrichten. **Nur `main`** als Deployment Branch zulassen; **Required reviewers**, soweit verfügbar, aktivieren. Der Workflow hat **keinen** IONOS-SSH-Zugang und keinen Zugriff auf `orgavela-ionos`. Das ist eine bewusste Trennung.

- **Environment secret `ORGAVELA_RCLONE_CONFIG`:** *nur den vollständigen einen Abschnitt* `[orgavela_drive]` aus der vom Betreiber lokal generierten `rclone.conf` hineinkopieren, einschließlich OAuth `client_id`, `client_secret`, `scope`, `root_folder_id` und `token`. Diese Werte sind hochsensibel und dürfen ausschließlich im **verschlüsselten GitHub-Secrets-Eingabefeld** hinterlegt werden. Keine anderen Remotes übernehmen. Unveränderte Chat- oder GitHub-Issues-Nachrichten mit diesen Inhalten sind untersagt.
- **Environment variable `ORGAVELA_BACKUP_AGE_RECIPIENT`:** ausschließlich die öffentliche `age1...`-Empfängeradresse aus Schritt 1. **Nie den privaten Schlüssel**.
- Keine zusätzliche Workspace-/IONOS-Zugangsfreigabe und keine kostenpflichtigen Verträge erforderlich. Die Möglichkeit einer Google-Drive-API-Zusatzkostenpflicht muss vor Aktivierung im Google Cloud Projekt geprüft werden, statt kostenlose Kontingente pauschal zu versprechen.

**Hinweis zur Trennung:** In diesem Environment liegen **Google-OAuth-Zugangsdaten mit eventuell vollständigem Drive-Scope**. Das öffentliche GitHub-Repository erhöht das Risiko bei kompromittiertem Workflow- oder `main`-Schreibzugriff. Deshalb Reviews, Branch-Protection, restriktives Environment und dedizierter Backup-Account sehr empfohlen. Eine Pfadkonfiguration allein ist keine echte OAuth-Scope-Grenze.

### 4. Einmaliger Test ohne echte OrgaVela-Daten

[Actions → „OrgaVela – encrypted Google Drive connection check“](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/workflows/orgavela-google-drive-connection.yml) → **Run workflow** → Branch `main` → Operation `upload-synthetic`.

Der Workflow installiert `age` und `rclone`, legt **nur einen fest kodierten synthetischen Text** an, verschlüsselt diesen mit der **öffentlichen** `age1...`-Adresse, speichert das OAuth-rclone-Config-Secret nur temporär mit Dateirechten 0600 auf einem kurzlebigen GitHub Runner, lädt ausschließlich eine Datei `*.age` in das per `root_folder_id` fixierte Drive-Ziel und bestätigt deren Existenz. Keine ZIPs, SQL-Dumps, WordPress-Dateien, privaten Schlüssel, OAuth-Werte oder Dateiinhalte in Workflow-Logs. Der Test nutzt **keine** IONOS-Secrets.

**Erfolg erst**, wenn der GitHub-Run **grün** ist und die verschlüsselte Testdatei im Zielordner tatsächlich angezeigt wird. Besteht die Anmeldung nicht, bleibt die Cloud-Verbindung **nicht fertig**; keinen live Backup-Upload versuchen. Sofern der User ein `age`-Tool hat, kann er anschließend die canary-Datei herunterladen und mit seinem **lokalen privaten** Schlüssel probeweise entschlüsseln.

### 5. Erst nach erfolgreichem Canary: echte OrgaVela-Sicherung und Automatik

Ein **separater, ausführlich reviewter** GitHub-Backupworkflow erhält die OrgaVela-SSH-Secrets ausschließlich in einer **Lesestufe**, verschlüsselt **vor irgendeinem Artefakt**, und ein **anderer Upload-Job** mit `orgavela-backup-drive`-Umgebung überträgt **nur das Ciphertext-Archiv** nach Drive. Die beide Zugänge zusammenführenden Berechtigungen dürfen nicht unkontrolliert in denselben Job gelangen. Keine Dropbox-/Drive-/GitHub-Dateiänderung an WordPress oder der produktiven DB. Erst danach regelmäßig per geplanten GitHub-Actions-`schedule` ausführen, mit Laufstatus-/Fehlerbenachrichtigung und Retention-/Rotation-/Restore-Plan. Ein verifizierter Drive-Upload ist **nicht** dasselbe wie ein erfolgreicher isolierter echter Restore – O03/B04-Abnahme bleibt bis dahin gesperrt.

**Statuscheck:** Google-Drive-Zielordner privat angelegt, `age`-Selbsttest erfolgreich; **OAuth/GitHub-Sync noch nicht eingerichtet**, kein echter OrgaVela-Dump, keine automatische Backup-Serie. O03 bleibt `partial`. [Normativer Masterplan](V1_ACCEPTANCE_PLAN.md), [Worklog](ASSISTANT_WORK_LOG.md), [Fortschritt](V1_PROGRESS.json).
