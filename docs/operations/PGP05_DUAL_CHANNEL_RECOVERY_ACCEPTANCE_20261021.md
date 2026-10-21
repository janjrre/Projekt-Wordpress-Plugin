# OrgaVela / PGP-05 – Zwei Sicherungswege und isolierte Wiederherstellungsabnahme

**Planstand:** 21.10.2026 | **Version:** 1.0 | **Betriebsfreigabe:** NEIN | **Betroffener Bereich:** ausschließlich OrgaVela, niemals Dreamloud

Dieses Dokument ist ein **freigabepflichtiger Ablaufplan, keine Aufforderung zur Ausführung**. Keine produktive Rücksicherung, kein Download echter WP-/DB-Inhalte, keine CI-Pipeline und keine Quell- oder GitHub-Schreibrechte aus diesem Dokument ableiten. Normativ für Abnahmen bleiben [V1_ACCEPTANCE_PLAN.md](V1_ACCEPTANCE_PLAN.md), [V1_PROGRESS.json](V1_PROGRESS.json) und [Issue #27](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/27).

## A. Evidenz zum Stichtag (streng getrennt)

| Aspekt | IONOS – vorhandene Anbieter-Sicherung | GitHub / OpenPGP – zusätzlich geplanter Weg |
| --- | --- | --- |
| Vorhanden | **Ja, als Funktion und neun Restorepunkte im authentifizierten IONOS-Projekt-UI sichtbar** | Privates Backup-Repo vorhanden; ausschließlich **synthetischer PGP-04 V3 Release** erfolgreich getestet |
| Inhalt laut Anbieter/Design | IONOS nennt **WordPress-Dateien plus Datenbank** | Später vor dem Transport verschlüsseltes zusammengehöriges OrgaVela-WP-/MariaDB-Archiv; derzeit nur **fiktive Fixture** |
| Zeitpunkte | Sichtbare UI-Restorepunkte **12. und 14.–21.10.2026 je 14:03**; **13.10. nicht sichtbar** | Noch **keine** echten OrgaVela-Produktivzeitpunkte |
| Aufbewahrung | IONOS **behauptet mindestens 14 Tage**; die 9 angezeigten Punkte beweisen keine lückenlose tägliche Historie | Noch keine freigegebene Retention oder kontinuierliche Frequenz |
| Verschlüsselung / Recovery | Technisches internes IONOS-Backupformat, Integrität und Wiederherstellbarkeit **nicht unabhängig inspiziert** | OrgaVela-PGPony-Public-Key festgelegt; Offline-Kopien und Betreiber-Recovery **owner-attested**; realer Backup-Decrypt und Site-Restore **offen** |
| Kontrolle und Fehlerdomäne | IONOS-Projekt/Vertragszugang; Account-/Providerverlust möglich | Eigenes privates GitHub-Repo; Sourcecode+Offsite-Releases hängen von GitHub-Konto ab; PGP-Privatschlüssel nur beim Betreiber |
| Echtes Restore-Ergebnis | **Kein** IONOS-`Wiederherstellen` betätigt, keine Validierung des gespeicherten Inhalts | **Kein** Restore eines echten OrgaVela-Snapshots; separate historische synthetische CI-MariaDB-Restoreprobe ist **kein** Echtdaten-Nachweis |
| Produktiv-Freigabe | IONOS-UI-Sichtung war **nur read-only** | Echt-Source, Writer, neue CI-Läufe, Scheduler und realer Restore **nicht freigegeben** |

**Weitere gemessene Ausgangsdaten:** WordPress 7.1.3, PHP 8.3.35, MariaDB **Server** 11.8.8, UOP Core 0.1.0-alpha.2 aktiv. Das vom Betreiber gezeigte phpMyAdmin-Schema umfasst **52 InnoDB-Tabellen**, ca. 4,2 MiB Tabellen-Gesamtgröße. Das ist ein InnoDB-**Engine-Metadaten-PASS für das dargestellte Schema**, noch keine unabhängige `wp-config.php`-Zuordnung oder transaktionskonsistente DB+Filesystem-Sicherung. Der IONOS-Webspace-Explorer zeigt die WordPress-Root-Dateien und `wp-config.php` (GUI-Modus 600) unter **`/public`**; ein expliziter Domain-Document-Root-/SSH-Chroot-Mappingbeleg fehlt. WordPress `DISABLE_WP_CRON=true`, zwei überfällige Events; externer Scheduler nicht bestätigt. [Bereinigter IONOS-Nachweisbericht](PGP05_ORGAVELA_WORDPRESS_READONLY_METADATA_20261021.md).

## B. Unveränderliche Sicherheitsregeln

1. **Keine echte Wiederherstellung auf `orgavela.com` für Tests.** Der IONOS-Knopf `Wiederherstellen` kann neuere Daten überschreiben. Der bloße UI-Bestand ist ein schwächerer Beleg als ein isolierter Restore.
2. **Isolierte Zielumgebung:** vom aktiven WordPress-, Datenbank-, DNS- und Deploy-Ziel getrennt, nicht öffentlich erreichbar, **kein** Netzwerk-Ausgang zu SMTP, Zahlungsdiensten, REST-Webhooks, SSO, Cron/Aktionsplanung oder produktiven IONOS-/GitHub-APIs. Keine echten Personenprofile in einem öffentlich zugänglichen Testhost; private Daten rechtlich/organisatorisch geschützt. Keine Nutzung von GitHub-Artefakten oder CI-Logs für echte SQL-Dumps.
3. **Source-Identitätsgates:** exaktes OrgaVela-IONOS-Projekt, unabhängig verifizierter Domain-Document-Root, SSH-Hostkey, effektiver WordPress-Konfigurationsbezug zum ausgewählten DB-Schema, Dump-/Dateileseberechtigungen und Datenschutz-/Aufbewahrungszweck. `/public` und 52/52 InnoDB sind starke Teilbelege, ersetzen diese Verifikation **nicht**.
4. **Kohärenter Snapshot:** passender Dateistand/DB-Transaktion; `--single-transaction` für InnoDB genügt alleine **nicht** bei DDL oder WordPress-Dateien, Uploads, Jobs und externen Schreibern. Ohne garantierte Quiescence bzw. vom Hoster nachgewiesene atomare Provideraufnahme **Stop**.
5. **Zwei getrennte Freigaben für neue Offsite-Realdaten:** zuerst echte, auf OrgaVela eng begrenzte **Source-Capture-Freigabe**; danach unabhängig die **Freigabe zur Veröffentlichung ausschließlich bereits lokal verschlüsselter Ciphertext-Dateien** im privaten GitHub-Backuprepo. Keine Produktiv-Credentials im Writer-Kontext und keine Writer-PATs im Source-Kontext. Kein privater PGP-Schlüssel in IONOS, CI oder GitHub.
6. **Keine stillschweigende Vertrags-/Kosten- oder Schedulerfreigabe.** IONOS-interner täglicher Backupdienst läuft bereits laut Anbieter; Jetpack-Werbung ist **keine** aktivierte oder erforderliche Backupmaßnahme. Keine neue Job-Ausführung, Preisverpflichtung oder automatische Wiederholung.

## C. Isolierter Wiederherstellungsablauf – erst NACH gesonderter Betreiberfreigabe

**C0 – Entscheid/Schutz:** incident_type, Wiederherstellungsziel, zulässiger Datenverlust, Datenschutzklassifizierung, Zeitpunkt und Betreiberzustimmung dokumentieren. Vermeiden, dass ein Test die produktive Site oder reale Nachrichten/Events berührt. Bei unklaren Grenzen **nicht starten**.

**C1 – Quelle und Moment wählen:**
- Bei IONOS: nur sichere Abfrage, ob zu einem gewählten historischen Zeitpunkt ein vollständiges **exportierbares oder in einen isolierten Klon restaurierbares** Providerbackup für Dateien und DB bereitgestellt werden kann. Ist die einzige IONOS-Option ein direktes Überschreiben der aktiven Site, **keinen Restore-Test ausführen**, sondern den Gate als offen dokumentieren.
- Bei GitHub: **erst nach expliziter Capture-/Release-Freigabe und vorhandenen echten verschlüsselten Paketen** private Release-ID/Tag/Immutable-/Assetliste gegen die vorab fixierten Erwartungen prüfen. Bestehende PGP-04-Pakete sind ausdrücklich **nur synthetisch**.

**C2 – Integrität und Isolation:** Für GitHub Ciphertext-Byteanzahl und SHA-256 vor/nach Transport sowie Release-Metadaten und genau erlaubte Assets prüfen. Public-Key-Fingerprint und tatsächliche Recipient-Subkey-ID prüfen; durch den Betreiber offline entschlüsseln. Das innere Inhalts-/Dateimanifest und die DB- und Dateienkomponenten prüfen, bevor Daten in irgendein Ziel importiert werden. **Hash-Übereinstimmung alleine ist keine Quell-Authentifizierung**, wenn Metadaten und Nutzdaten gemeinsam manipuliert werden können. Bei IONOS vom Anbieter bereitgestellte verifizierbare Vollständigkeits-/Checksumdaten einbeziehen; falls nicht verfügbar, Nachweisgrenze transparent markieren.

**C3 – Leeres, nicht vernetztes Ziel:** Vorhandensein einer vollständig separaten MariaDB-Instanz und eines nicht öffentlich erreichbaren Dateiroots **vor jedem Import** anhand unabhängiger Daten prüfen. Ein Verweis auf produktiven DB-Host, produktives Webroot oder DNS löst **Abbruch vor Import** aus. Kein SMTP, WP-Cron, Action Scheduler oder Webhook darf in Richtung produktiver Systeme feuern. Kopierte `wp-config.php`-Secrets nicht automatisch aktivieren; Konfiguration und URLs nur innerhalb des isolierten Clones passend umsetzen.

**C4 – Restore und fachliche Prüfung:** Privaten Datenbankdump im Testziel importieren, WP-Dateien dort einspielen, WP-/PHP-/MariaDB-/UOP-Versionen, Tabellenschema/Indizes, `wp-config.php`-Bezug, Upload-Referenzen, UOP-Organisationen/Anmeldungen/Einwilligungen und Plugin-Initialisierung **ausschließlich datensparsam und ohne Dateninhalte in Logs** prüfen. Öffentliche oder produktive Anmeldung, E-Mail-Zustellung und Nutzeraktionen blockieren; funktionale Tests nach Möglichkeit mit zusätzlichen synthetischen Testdatensätzen auf dem Klon. Erfolg muss **reproduzierbar tatsächlich auf eigener Test-DB und eigenen Testdateien** gemessen worden sein.

**C5 – Abschluss:** Fehlende Tabellen/Dateien, Versions-/Zeitpunktabweichungen, Integritätsfehler, Berechtigungen, Restaurationsdauer und hypothetischen Datenverlust protokollieren. Danach Testkopien unter dokumentierter Retention kontrolliert beseitigen. Ein fehlgeschlagener oder unvollständiger Test ist **FAIL/OPEN**, nicht "teilweise Restore bestanden". Keine automatische Publikation privater Restore-Daten.

## D. Acceptance-Matrix: Was ein grünes Gate wirklich erfordert

| Gate | Akzeptanzkriterium | Stand 21.10.2026 |
| --- | --- | --- |
| D1 Providerbestand | Anbieter zeigt OrgaVela-WordPress-Dateien+DB-Backup und datierte Wiederherstellungspunkte | **UI-TEILBELEG PASS** (9 Einträge; keine Inhaltssichtung) |
| D2 Provider-Restorefähigkeit | Dateiintegrität und erfolgreicher isolierter Providerbackup-Klon-Restore, keine Produktivüberschreibung | **OFFEN** |
| D3 OrgaVela-Quellidentität | IONOS-Projekt + effektiver WordPress-Root + live verwendete DB + Hostkey unabhängig verbunden | **TEILWEISE**; Root/DB-Verbindung offen |
| D4 Konsistenter Real-Snapshot | Sicherer zusammengehöriger DB+Dateien-Stand, keine DDL-/Writer-Races, keine Klartextpersistenz | **OFFEN** |
| D5 Externes privates GitHub-Backup | Betreiberfreigegebener echter OpenPGP-Source+Writer, unabhängiger Byte-Readback, zweiter Recoveryweg | **OFFEN**; PGP-04 synthetisch PASS |
| D6 Private Owner-Decryption | Echte Offsite-Datei vom Betreiber entschlüsselt, Fingerprint/Dateiinhalte/Digests konsistent | **OFFEN für Realdaten**; historische Schlüsselwiederherstellung owner-attested |
| D7 Tatsächlicher isolierter WP+MariaDB-Recoverytest | Getrennte Test-DB, vollständige Dateien+SQL, Plugin/Schema/Datei-Funktionen und Dauer nachgewiesen | **OFFEN**; nur bisheriger synthetischer CI-Test |
| D8 Freigabe für kontinuierlichen Betrieb | Getrennte Source-/Writer-/Cron-Contexts, Rotation, Monitoring, Retention, Incidentplan, Budgetgrenzen und Betreiberfreigabe | **GESPERRT** |

**RPO/RTO:** IONOS-UI-Zeitpunkte sind **keine** verifizierten RPO-/RTO-Werte. Erst nach echten Recovery-Proben Verlustfenster (letzte vollständige, konsistente Wiederherstellung) und Testdauer messen. Vor jeder späteren periodischen Einrichtung muss der Betreiber akzeptable Zielwerte und Kosten freigeben.

## E. Szenarien und Grenzen der beiden Kanäle

- **Fehlerhaftes Pluginupdate / fehlerhafte Daten:** IONOS-UI kann früheren Punkt anbieten; erst Ausmaß des Datenverlusts entscheiden und wo möglich offline Klon testen. Bei reinem Updatefehler ggf. gezieltes Rollback besser als komplette Site-Überschreibung; gesonderte Freigabe nötig.
- **IONOS-System oder Konto nicht verfügbar:** IONOS-Restorepunkte sind eventuell gleichzeitig unerreichbar; ein **erst künftig produktiv eingerichteter** GitHub-Backupweg kann dann eine zweite Quelle sein, aber nicht ohne extern verfügbares Hosting für den Restore und den privaten Schlüssel.
- **GitHub-Konto/Repo nicht verfügbar:** Weiterhin auf IONOS-Konto/Restorepunkte angewiesen. Beide Wiederherstellungswege haben unterschiedliche, aber nicht zwingend vollständig unabhängige Fehlerdomänen. `immutable=true` für GitHub-Releases ist kein von GitHub unabhängiger WORM-Schutz.
- **PGP-Key verloren:** Verschlüsselte GitHub-Pakete können ohne gültigen privaten Schlüssel nutzlos sein. Zwei getrennt verwahrte Recovery-Kopien wurden vom Betreiber bestätigt; diese Bestätigung ist keine von uns durchgeführte Realdaten-Wiederherstellung.
- **Änderungen nach letztem Snapshot:** Kein Restore darf automatisch den aktiven Stand überschreiben; aktuelles RPO/RTO, Datenverlust und Kommunikationsplan vorher neu bewerten.

## F. Wichtige Alt-Dokumente und bereits existierender synthetischer Restore-Test

- [W0_BASELINE_AND_RECOVERY.md](W0_BASELINE_AND_RECOVERY.md) und [W0_O03_BACKUP_RECOVERY_RUNBOOK.md](W0_O03_BACKUP_RECOVERY_RUNBOOK.md) enthalten teils **historische** Passagen ("IONOS Restorepunkte unbekannt", ältere Hosting-/WordPress-Versionen, ehemals Google Drive/`age`). Sie sind ab 21.10.2026 anhand dieses Nachtrags und der [PGPony/OpenPGP-ADR](W0_O03_PGPONY_OPENPGP_MIGRATION_ADR_2026-10-20.md) auszulegen. Historische frühere CI-SSH-Checks sind nicht dasselbe wie die aktuelle unabhängige produktive Quellfreigabe.
- Bereits im Repository: [orgavela-restore-rehearsal.yml](../../.github/workflows/orgavela-restore-rehearsal.yml) und [orgavela-synthetic-restore.sh](../../scripts/ci/orgavela-synthetic-restore.sh), mit einer **synthetischen** MariaDB-11.8-Container-Dump-/Import-/Datei-/Index-Probe. Deren Schutzbedingungen (Hauptbranch, künstliche DB-Namen, keine Echt-Credentials) bestehen unabhängig von den PGP-05-Draft-PRs #28–#31. **In diesem Arbeitsschritt wurde kein Workflow gestartet oder modifiziert.**
- [PGP05_SYNTHETIC_ARCHIVE_RESTORE_GUARD_20261021.md (Draft PR #31)](https://github.com/janjrre/Projekt-Wordpress-Plugin/blob/feat/pgp05-synthetic-archive-restore-guard-20261021/docs/operations/PGP05_SYNTHETIC_ARCHIVE_RESTORE_GUARD_20261021.md) behandelt **nur künstliche** verschlüsselte Archiv-Inhalte; auch 86/86 lokale Prüfungen beweisen kein echtes Providerbackup, keinen isolierten echten Restore und keinen produktiven Writer.

## G. Übergabe-/Abnahmeprotokoll-Vorlage (bei künftiger Freigabe)

`Kanal | Quelle und Zeitpunkt (ohne Secrets) | Operator-Go-ID | Zielisolation separat nachgewiesen | Daten-/Dateien-Zeitkohärenz | Archiv-/Ciphertextintegrität | DB-/WP-/UOP-Funktionsprüfung | gemessene Dauer | effektiv verlorene Minuten | Entsorgung/Retention | FAIL/PASS | freigebende Person`

**Keine der derzeit offenen realen Recovery-Abnahmen darf allein anhand dieser Vorlage als PASS eingetragen werden.**

**Stand der formalen V1-Abnahme:** historisch 62/71 Codingpakete, M0–M7 0/8, P4 0/4, R01–R18 0/18. PGP-05 Produktivfreigaben weiter `false`.
