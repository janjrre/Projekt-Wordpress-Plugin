# OrgaVela IONOS – GitHub → SFTP/SSH-Deployment

**Stand:** 12.10.2026 · Ziel **ausschließlich** `https://orgavela.com`. Keine Verbindung zu Dreamloud, keine Teilnehmerdaten in den Tests. **SFTP ist der Standard**, weil IONOS WordPress-Zugänge keine SSH-Shell garantieren. Die Workflows sind auf `main` beschränkt und lassen sich manuell oder durch jeweils eigene, exakt begrenzte Deployment-Request-Dateien auslösen; **normale Commits lösen kein Hosting-Deployment aus**.

## 1. Aktueller Iststand

- IONOS WordPress ist unter `https://orgavela.com` über den OrgaVela-MCP-Zugang verifiziert: WordPress 7.1.3, PHP 8.3.33, MariaDB 11.8.8; DB-Verbindung `utf8mb4`, Kollation `utf8mb4_unicode_520_ci`, HTTPS.
- UOP Core ist dort **noch nicht installiert**. Die bisherige WordPress.com-Installation bleibt getrennt.
- Verifiziertes, aber frühes UOP-Testpaket: GitHub-Branch `staging/uop-plugin-package`, Datei `uop-core.zip`, Provenienz `package-provenance.txt`, SHA-256 `acbebdd9df25d996848bbca6ebe6726ee1a986207fc7c7b818474dc93134267a`. Die Prüfsumme bei jedem neuen Paket neu abgleichen; **nicht** blind wiederverwenden.
- Workflows: [reiner SFTP-Weg](../../.github/workflows/deploy-orgavela-sftp.yml), [optionaler SSH/WP-CLI-Weg](../../.github/workflows/deploy-orgavela-ssh.yml). Beide müssen auf GitHub erst mit Zugangsdaten versehen und erfolgreich getestet werden. Sie deaktivieren die Sicherheitsprüfungen des Plugins nicht.

## 2. In IONOS: eigenen, auf OrgaVela begrenzten SFTP-Nutzer erstellen

1. IONOS Konto → **Menü → Web-Projekte → OrgaVela → Einstellungen → Erweiterte Einstellungen → SFTP & SSH → Neues Konto erstellen**. Siehe [offizielle Anleitung für WordPress](https://www.ionos.de/hilfe/wordpress/administration-von-wordpress/sftp-benutzer-fuer-wordpress-anlegen/).
2. Einen **eigenen** Benutzer für den OrgaVela-Webspace statt eines Kontos mit Zugriff auf Dreamloud anlegen. Berechtigungen auf **OrgaVela allein** begrenzen. Ein Passwort direkt bei IONOS setzen, **niemals** im Chat, Issue, Commit oder öffentlichen Workflow-Log mitteilen.
3. Den SFTP-Servernamen, Benutzernamen, Port (typischerweise **22**) und den vom SFTP-Benutzer aus sichtbaren WordPress-Ordner ermitteln. Der Zielordner muss `wp-admin`, `wp-includes` und `wp-content/plugins` enthalten. Er darf weder Dreamloud noch ein anderes Projekt umfassen.
4. Der GitHub-Workflow ermittelt beim Verbindungsaufbau den öffentlichen ED25519-Hostschlüssel und prüft dessen **SHA-256-Fingerabdruck gegen die offizielle, fest hinterlegte IONOS-Webhosting-Referenz** (`SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q`). Nur bei Übereinstimmung wird fortgefahren. Quelle: [IONOS SSH-Fingerabdrücke](https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/). Bei abweichendem Fingerabdruck: **abbrechen**, IONOS-Support fragen. Auf keinen Fall `StrictHostKeyChecking=no` verwenden.
5. Falls IONOS SSH-Shell erlaubt, optional den SSH-Workflow nutzen; zusätzliche SFTP-Benutzer haben **nicht zwingend** Shell-Rechte. Der SFTP-Workflow benötigt ausdrücklich **keine** SSH-Shell, `wp` oder `unzip` auf dem Server.

## 3. In GitHub: Umgebung und Secrets einrichten

Repository: `janjrre/Projekt-Wordpress-Plugin` → **Settings → Environments → New environment → `orgavela-ionos`**.

- **Deployment branches/tags:** ausschließlich `main` erlauben; idealerweise eine Freigabeprüfung aktivieren.
- **Environment secrets** (Werte **nicht** hier oder im Chat eintragen):
  - `IONOS_SSH_HOST`: der tatsächliche von IONOS genannte SFTP-Hostname – nicht pauschal `orgavela.com`.
  - `IONOS_SSH_USER`: ausschließlich für OrgaVela angelegter SFTP-Benutzer.
  - `IONOS_SSH_PASSWORD`: Passwort dieses SFTP-Benutzers.
  - `IONOS_KNOWN_HOSTS` **optional**: Nur falls der Host einen anderen, von IONOS unabhängig bestätigten Fingerprint hat: vollständige geprüfte `known_hosts`-Zeile. Ohne dieses Secret prüft der Workflow automatisch den veröffentlichten IONOS-ED25519-Fingerprint. Nicht blind auf abweichende Schlüssel umstellen.
- **Environment variable** `IONOS_SFTP_ROOT` **optional für die erste Probe**: Standardwert ist `.` (Startverzeichnis des SFTP-Kontos). Falls dort nicht direkt `wp-admin`, `wp-includes` und `wp-content` liegen, nach dem fehlgeschlagenen rein lesenden Test den richtigen relativen WordPress-Stammordner bei IONOS ermitteln und als Variable hinterlegen. Dieses Verzeichnis muss genau `orgavela.com` gehören; niemals einen Pfad zu Dreamloud verwenden.

Der getrennte SSH-/WP-CLI-Workflow benötigt zusätzlich `IONOS_WP_ROOT` als **Shell-Pfad** und funktionierendes `wp` und `unzip` auf IONOS. SFTP-Pfad und SSH-Shell-Pfad können unterschiedlich sein; diese Werte niemals ungeprüft gleichsetzen.

[GitHub Actions: Environment-Secrets](https://docs.github.com/en/actions/concepts/security/secrets) · [Deployment-Environments](https://docs.github.com/en/actions/concepts/workflows-and-actions/deployment-environments).

## 4. Schritt 1 – nur lesen: SFTP-Probe

Unter [GitHub Actions](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/workflows/deploy-orgavela-sftp.yml) → **Run workflow**, Branch `main`, operation `probe` wählen.

Der Workflow prüft nur die SSH-Hostidentität, die Anmeldung und lesend `wp-admin`, `wp-includes`, `wp-content/plugins`. Er führt **keine** Plugininstallation aus. Eine erfolgreiche Probe allein beweist **nicht** die Domainzuordnung des Verzeichnisses; die ist vorher in IONOS unabhängig zu verifizieren.

Bei fehlenden Secrets, falschem Hostschlüssel, falschem Pfad oder fehlendem Zugang bricht er ab. Die Logs dürfen keine Passwörter enthalten.

### Live-Diagnose: erster Testlauf vom 12.10.2026

Der erste manuelle [SFTP-Probe-Lauf #38236861923](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38236861923) hat **Secrets, Hostfingerprint, Anmeldung und SFTP-Transport bestanden**. Gescheitert ist erst die WordPress-Verzeichnisprüfung an `/./wp-admin` mit `not found`.

**Ursache:** Das durch `IONOS_SFTP_ROOT=.` angesprochene SFTP-Startverzeichnis ist nicht das WordPress-Stammverzeichnis. Dies ist **kein** Passwort- oder Netzwerkfehler.

**Unterstützte Ermittlung ohne Shell und ohne Änderungen:** Im IONOS-Dashboard **Menü → Web-Projekte → OrgaVela → Website verwalten → Technische Daten** das **Installationsverzeichnis (Webspace)** nachsehen. [IONOS-Anleitung für Installationsverzeichnisse](https://www.ionos.de/hilfe/mein-konto/hosting/click-build-daten-exportieren/). Bei Hostingpaketen kann alternativ der **Webspace Explorer** genutzt werden. Das dort angezeigte absolute Server-Dateisystemverzeichnis kann sich vom im SFTP-Chroot sichtbaren Pfad unterscheiden; deshalb keinen Shell-Pfad blind als SFTP-Pfad übernehmen.

GitHub → `Settings → Environments → orgavela-ionos → Environment variables` den anhand des ausschließlich zu OrgaVela gehörenden SFTP-Projektverzeichnisses überprüften Wert `IONOS_SFTP_ROOT` setzen. Anschließend die **nur lesende Probe** erneut starten. Kein Passwort erforderlich; keine Dateien hochladen. Nur bestätigte, OrgaVela zugeordnete Verzeichnisse zulassen. Insbesondere **keine Verzeichnis-Suche in Dreamloud oder anderen Webprojekten** ausführen.

Ab Code-Änderung `f9e985cdc68b225bf780d03e444eb4b9562334d3` erkennt der Workflow den häufigen `not found`-Fehler ausdrücklich und verweigert **jede** Installation, solange das unbestätigte Standardziel `.` oder `/` eingetragen ist. Der erfolgreiche SFTP-Handshake ersetzt nicht den belegten Domain-/Projektpfad.

## 4a. Künftig autonomer Start aus einem GitHub-Commit

**Erst nach verifizierten GitHub-Secrets und IONOS-Verbindung** kann ein autorisierter Assistent die Datei `.github/deploy-requests/orgavela-sftp.json` auf `main` neu anlegen oder ändern. Diese **genau benannte Datei** löst den SFTP-Workflow automatisch aus, statt dass der Betreiber jedes Mal `Run workflow` klicken muss. Alle Ziel-/Hostschlüssel-/SHA- und Secrets-Gates bleiben aktiv. Der separate SSH-Workflow verwendet eine **andere** Datei (`orgavela.json`): niemals beide Deployments parallel auslösen.

Beispiel für den ersten, rein lesenden Test (erst nach Einrichtung des SFTP-Zugangs anlegen):

```json
{
  "request_id": "first-sftp-probe",
  "operation": "probe",
  "target_domain": "",
  "expected_sha256": ""
}
```

Für einen späteren Upload müssen `operation=install`, `target_domain=orgavela.com` und `expected_sha256` mit dem nachgewiesenen aktuellen 64-stelligen Paket-Hash gesetzt werden. Änderungen an dieser Datei müssen vorher gegen `AGENTS.md` und den Sicherheits-/Recovery-Plan geprüft und im Arbeitsprotokoll begründet werden. **Noch keinen Deploy-Request anlegen**, solange Geheimnisse fehlen – andernfalls startet nur ein zwangsläufig fehlschlagender Job.

## 5. Schritt 2 – einmalige Installation ohne Aktivierung

Nur nach bestandener Probe, bestätigter OrgaVela-Pfadzuordnung, geeignetem Backup-/Restore-Weg, CI-Validierung des konkreten Pakets und geklärter Testseiten-Sichtbarkeit:

- In demselben Workflow: operation `install`, `target_domain=orgavela.com`, `expected_sha256` = **aktuell geprüfte** Paket-Checksumme aus GitHub. Kein automatisches Upgrade bestehender Plugin-Dateien.
- Paket und veröffentlichte Provenienz werden verglichen; das ZIP wird auf sichere Dateinamen und Plugin-Struktur kontrolliert.
- Per **SFTP** in ein eindeutiges temporäres, verstecktes Plugin-Verzeichnis übertragen, anschließend auf `wp-content/plugins/uop-core` umbenannt. Existiert UOP Core bereits, verweigert der Workflow ein Überschreiben.
- **Keine Aktivierung, keine Tabellenmigration, kein Zugriff auf Dreamloud**. Bei gescheitertem Upload kann ein verstecktes `.uop-stage-<Run-ID>`-Verzeichnis zurückbleiben; nur nach exakter Pfadprüfung aufräumen.

Nach erfolgreicher Dateiinstallation den vorhandenen **OrgaVela WordPress-MCP-Zugang** verwenden, um das Plugin unabhängig lesend zu verifizieren. Aktivierung erst in einem separaten Schritt nach gesicherten Datenbank-/Backup-Gates und synthetischem Testszenario.

## 6. Noch nicht freigegeben / nächste Schritte

- Noch **keine** SFTP/SSH-Secrets eingerichtet oder verifiziert, **keine** Verbindung aus GitHub Actions bestätigt, **keine** Übertragung nach IONOS ausgeführt. Die nötigen Kontodaten können ausschließlich vom Betreiber sicher in GitHub hinterlegt werden.
- `orgavela.com` verlangt vor echten E2E-Tests besonderen Sichtbarkeitsschutz: Suchmaschinen-Indexierung ist aktuell nicht verhindert, Datenschutzseite noch ein Entwurf, `DISABLE_WP_CRON` gesetzt. Ein separater externer Scheduler ist nicht belegt.
- Vor produktiven Migrationen Backup und Restore-Nachweis; für die Tests nur synthetische Teilnehmerdaten.
- Spätere Updates benötigen einen eigenen atomaren Rollback-/Versionierungsworkflow. Dieser erste Installationsworkflow **verweigert** bewusst jegliches Überschreiben.

**Verbindlich:** Jede Aktion, einschließlich fehlgeschlagener Probes/Deployments, im [UOP-Arbeitslog](ASSISTANT_WORK_LOG.md) nachvollziehbar protokollieren.

### 12.10.2026 – Installationsverzeichnis durch Betreiber bestätigt

Neuer Screenshot aus **IONOS → OrgaVela → Webspace Explorer** zeigt ausdrücklich den Pfad `/public/wp-content/plugins` unter `public`; darüber sind `wp-admin` und `wp-content` zu sehen. Damit ist der **für OrgaVela vorgesehene** SFTP-Stammpfad **`/public`**. Der Betreiber bestätigt, dass der SFTP-Benutzer sämtliche Ordner dieses Webspaces erreichen kann – deshalb ist ab Commit `2cd04a135be771ad6a1aebaab15e51a6d0c9c5cf` für **jede** Probe/Installation ausschließlich `IONOS_SFTP_ROOT=/public` zugelassen. Ein anderer Pfad (einschließlich `.` oder `/`) scheitert vor der Verbindung/Übertragung. Es darf nicht in andere Projekte navigiert werden.

**Einmalige Einstellung:** GitHub → Repository → **Settings → Environments → `orgavela-ionos` → Environment variables → Add variable**, Name `IONOS_SFTP_ROOT`, Wert `/public` (ohne Anführungszeichen). Das ist eine *Variable*, **kein Secret**. Die bisherigen drei SFTP-Secrets bleiben unverändert.

**Re-Test:** Nach dieser Einstellung kann der Assistent den zuvor fehlgeschlagenen Job des GitHub-Laufs [#38236861923](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38236861923) mit dem GitHub-Connector einmal **gezielt erneut ausführen**. Dabei ist zu berücksichtigen, dass GitHub beim Re-Run den **ursprünglichen Workflow-Commit** verwendet; neuere Code-Guards werden erst in einem frischen `main`-Workflowlauf ausgeführt. Ein Re-Run dient deshalb **ausschließlich dem lesenden `probe`**, ausdrücklich **nicht** einem Installations- oder Uploadlauf. Zur Verifikation müssen die neuen Runner-Logs `IONOS_SFTP_ROOT=/public` anzeigen, die SFTP-Abfragen `/public/wp-admin`, `/public/wp-includes` und `/public/wp-content/plugins` erfolgreich sein, und es darf kein Upload stattgefunden haben. Falls das Re-Run alte Environment-Variablen beibehält, einen neuen rein lesenden Workflow manuell auf `main` starten; niemals die Sicherheitsprüfung entfernen.
