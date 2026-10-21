# PGP-04 V3 – unabhängige GitHub-Release-Prüfung und Produktionsbackup-Grenze

**Datum:** 21.10.2026 · **Status:** technischer synthetischer Release PASS; *vollständige PGP-04-End-to-End-/Restore-Abnahme OFFEN* · **Referenz:** [Issue #27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27).

## 1. Quellenevidenz und Methode

- Verbundener GitHub-Connector: `GET /repos/janjrre/orgavela-backups` meldet **PRIVATE**, nicht archiviert. Keine öffentlichen oder personenbezogenen Backup-Inhalte eingesehen.
- Direkte unabhängige GitHub-REST-API-Abfragen `GET /repos/janjrre/orgavela-backups/releases/419226447` und `.../releases/tags/pgp04-synthetic-32`: gleicher Release, `draft=false`, `prerelease=true`, `immutable=true`, Veröffentlichung `2026-10-21T06:28:54Z`; drei Assets mit `state=uploaded`.
- `GET /repos/janjrre/orgavela-backups/git/ref/tags/pgp04-synthetic-32`: Git-Tag existiert (getrennter Git-Ref-Readback). Die Tag-SHA ist kein Hash der WordPress-Dateien.
- Quellbranch `security/pgp04-synthetic-release-v3-20261021`: direktes `GET /branches/...`, `protected=true`, HEAD `3c9f0bb8bd85fb97e09b84c870fb42fdf491a8de`. Detailanforderungen (No Bypass, No Force Push, No Delete) wurden **nicht** erneut separat über Protection-API bestätigt.
- Draft [PR #26](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/26): open, draft, unmerged, V3 HEAD unverändert, V2 Basis. V1/V2-Branches nicht angefasst.
- Provider CircleCI #505 gemäß bereits vom Betreiber dokumentierten Screenshots: Source PASS, Owner-Approval erfolgt, Context-SHA-/Token-Smoke PASS, Writer PASS, 6/6 Negativtests; synthetisches Ciphertext-Workspace. Commit `3c9f0bb8bd85fb97e09b84c870fb42fdf491a8de`. Writer-Skript prüft SHA256-Sidecar, öffentliche PGP-Verschlüsselung, öffentliche Manifeststruktur, drei Uploads, zweimalige entfernte Bytevergleiche (vor/nach Publish) und immutable=true. Marker `PGP04_SYNTHETIC_RELEASE_METADATA_VERIFIED=PASS` ist Writer-/Provider-, **nicht** eine neue eigenständige Binärberechnung.

## 2. Release-Daten (unabhängig API-belegt)

| Datei | Asset-ID | Bytes | GitHub-Digest SHA-256 |
| --- | ---: | ---: | --- |
| `orgavela-synthetic-pgpony-handoff-001.tar.gpg` | 661581079 | 1151 | `4d209133b371f41799032fb207077dbcb7f205405622fe037d5c4035aa901621` |
| `orgavela-synthetic-pgpony-handoff-001.tar.gpg.sha256` | 661581099 | 112 | `267768b643b6cd6d88c3b552d1e2806d8e03ebb6bbcf30fcc80dd2079207bede` |
| `manifest.json` | 661581135 | 427 | `1616aab19505ee0081a41bc5bd169dc4a4e2e3c12a84e85f2f16812d7e1efedc` |

**Evidenzgrenze:** `digest` ist der serverseitige SHA-256-Wert **jedes Assets**, nicht die aus der Sidecar-Datei ausgelesene behauptete Ciphertext-Prüfsumme. Die private Asset-Download-URL konnte über den Connector nicht als Byteinhalt abgerufen werden (404), die private GitHub-REST-API lieferte die Asset-Metadaten. Deshalb **kein** zusätzliches unabhängig berechnetes Asset-/Sidecar-/Manifest-Byte-Matching behaupten; nur Writer-Nachweis plus GitHub-API-Digests. Der erfolgreiche PGPony-Decrypt ist eine separate Betreiberbestätigung.

## 3. Offene PGP-04-Abnahme / Security

- **PASS (betreiberbestätigt):** verschlüsseltes synthetisches Testbackup in PGPony entschlüsselt, privater Key bleibt beim Betreiber.
- **PASS (betreiberseitiger UI-Screenshot):** `GH_TOKEN` aus dem dedizierten CircleCI-Context gelöscht, `PGP04_APPROVED_SHA` bleibt.
- **OFFEN – sofort:** Fine-Grained-PAT `orgavela-pgp04-onetime-test` direkt in GitHub **widerrufen** und Betreiberbestätigung dokumentieren. CircleCI-Löschung ersetzt keinen PAT-Widerruf; kein Token im Chat oder Repo.
- **OFFEN – lokal beim Betreiber:** nach Entschlüsselung synthetisches Tar kontrollieren: `database.sql.gz` mit ausschließlich `CREATE TABLE orgavela_synthetic_only (id INTEGER);` und `wordpress-files.tar.gz` mit `wp-content/uploads/synthetic.txt` = `SYNTHETIC TEST ONLY - NO REAL SITE DATA`. SHA-256 der heruntergeladenen `.tar.gpg` gegen Sidecar/Manifest lokal prüfen. **Nicht** in Live-Datenbank oder Website einspielen.
- **OFFEN:** tatsächlicher vollständiger struktureller Restore/Entpacktest; technische Release-Prüfung und reines Entschlüsseln sind keine Daten-Restore-Abnahme.
- **GESPERRT:** echte WordPress-/MariaDB-Captures, Live-IONOS-Zugriff, neue PAT/Contexts, Pipeline-Reruns, Scheduler, automatische PR-Merges und jegliche reale Backup-Publikation.

## 4. PGP-05: vorbereiteter, aber NICHT freigegebener Produktionsweg

1. **Architekturfreigabe und Formatentscheidung:** PGPony/OpenPGP als vorgeschlagener Schlüsselweg; vorhandene ältere `age`-Runbook-/ADR-Entscheidungen ausdrücklich reconciliieren statt `age` und PGPony automatisch zu mischen. Konkretes, versioniertes OpenPGP-Recovery-Restore-Format und Schlüsselerneuerung dokumentieren; nur öffentlicher Schlüssel in Producer/Writer.
2. **Quell- und Vertrauensgrenzen:** tatsächliche OrgaVela-Domain/IONOS-Vertragsidentität, MariaDB-Zugang nur für eigenes Datenbankschema, WordPress-Dateipfade, SSH Host-Key und RO-Rechte nachweisbar prüfen; Dreamloud-Quellen/Kontexte ausgeschlossen. Keine realen Daten vor gesonderter Eigentümerfreigabe anfordern.
3. **Getrennter Ciphertext-Transport:** Producer darf OrgaVela-Read-Only-Datenzugang, **nicht** GitHub-PAT besitzen; ein separater Writer darf nur Ciphertext + signatur-/digestgesichertes Manifest und scoped Backup-Repo-Releasetoken sehen, **nie** IONOS-/DB-/Private-Key-Secrets. Daten müssen vor Persistenz/CI Workspace verschlüsselt sein. Ephemere lokale Klartextreste strikt minimieren und auch bei Fehlern löschen; keine Klartexte in Git, Logs, CircleCI Artifacts oder öffentlichem Manifest.
4. **Harte Freigabegates:** Produktivjobs default-off, spezifischer geschützter Branch/Review-SHA, engste Projekt-/Branch-Contextrestriktionen, manueller Eigentümer-Hold vor erstem echtem Lesejob UND separate Publikationsfreigabe nach vertraulichem Ciphertext- und Datenminimierungscheck. Security-Negativtests und synthetische Probeläufe erst nach Code-Review; keine unnötigen Run-Wiederholungen.
5. **Integrität/Recovery:** eindeutige Snapshot-ID, konsistentes DB+Files-Paar, intern verschlüsselte Inventar- und Datei-Prüfsummen, Ciphertext-SHA256 vor/remote/nach Download, Restore isoliert auf anderer Umgebung mit WP+MariaDB einschließlich Konfiguration/DB-Rechten und Test gegen vertrauliche Bereiche. Schlüssel nur auf Eigentümergerät.
6. **Betrieb, Kosten und Fehlermanagement:** verifizierte IONOS Provider-Backups und vertragsspezifische Aufbewahrung; GitHub-Release-Immutability **nicht** als unabhängiges WORM oder als Schutz gegen Repository-Löschung bewerten; gewünschtes RPO/RTO, Größenlimits (ein Release-Asset < 2 GiB), Rotation, Failure-Alerts, Retry-Verhalten, Retention, Kosten- und Free-Credits-Budget mit Betreiber abstimmen.
7. **Erst nach E2E-Go:** explizite separate Freigabe für **einen** realen Test-Snapshot; Isolation und echte Restore-Abnahme danach; Scheduler erst mit nochmals eigener Freigabe aktivieren.

### Formale Zähler
Historische Codingpakete **62/71**; M0–M7 **0/8**; P4 **0/4**; R01–R18 **0/18**. Technischer PGP-04-Release PASS ist **keine** V1-Releasefreigabe. Offene Punkte in [#27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27). Keine CI-Run-/Live-Änderungen durch diese Audit-Dokumentation.

## 5. Nachtrag: Betreiber bestätigt PAT-Revoke und synthetische Archivdateien (21.10.2026)

**Betreiberbestaetigung (21.10.2026):** „Der Token wurde bereits widerrufen.. Die Synthetischen Archivinhalte habe ich kontrolliert und diese Datein sind alle da“. Ausdruecklich belegt ist GitHub Fine-Grained-PAT-Revoke (**betreiber-attestiert**) und die Anwesenheit der erwarteten archivierten synthetischen Komponenten nach erfolgreicher PGPony-Entschluesselung (**betreiber-attestiert**). Der Widerruf ist nicht separat via GitHub-PAT-API nachpruefbar. Der Betreibersatz belegt weder explizit den exakten SQL-Satz `CREATE TABLE orgavela_synthetic_only (id INTEGER);`, noch den wortgetreuen Dateiinhalt `SYNTHETIC TEST ONLY - NO REAL SITE DATA`, noch den **lokalen** SHA-256-Abgleich mit der Sidecar-Datei. Die Source-Funktion im auf SHA 3c9f0bb8bd85fb97e09b84c870fb42fdf491a8de eingefrorenen Branch schreibt diese beiden erwarteten synthetischen Marker deterministisch, doch das ist ein Code-Nachweis, kein eigenstaendiger Betreiber-Bytevergleich. **Formales PGP-04 Full-E2E-/Content-Gate bleibt mangels wortgetreuer Inhalts-/Hashattestation offen; Tokenwiderruf und Archivstruktur sind abgeschlossen.**

**PGP-04 aktualisierter Stand:** Release-Metadaten/GitHub immutable/3 Assets PASS, CircleCI Writer/Provider PASS, PGPony Decrypt PASS, CircleCI GH_TOKEN-Cleanup per Screenshot PASS, GitHub PAT-Revoke **nur Betreiberattestation PASS**, synthetische archivierte Datei-Struktur **nur Betreiberattestation PASS**. Auf wortgetreue innere Marker und lokalen Ciphertext-Checksum-Abgleich beschränkte dokumentarische Restpruefungen noch OPEN; weder ein produktiver Restore noch Realdaten-Capture wurde ausgeführt oder autorisiert.
