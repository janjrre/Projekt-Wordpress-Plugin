# OrgaVela PGP05 — Dreamloud als Vorlage, vereinfachte Metadatenabnahme
Date:2026-10-22. W0/O01/O03; #27/Draft32.

## Zweck und Korrektur
Das Ziel ist ein wiederherstellbares, verschlüsseltes echtes OrgaVela-Backup.
Checkpoint5 prüft die Quelle dafür. Der Betreiber hinterfragt den Aufwand und
möchte Dreamloud übernehmen. Die zusätzliche generelle IONOS-Startup-Auskunft
wird nach erneuter unabhängiger Prüfung als unverhältnismäßig zurückgenommen.
Bestandene technische Tests werden genutzt, keine vollständige Neukonstruktion.

## Tatsächlich verglichene Quelle (read-only)
Dreamloud-Backupbranch backup/production-circleci:
**07d4ffbcc3c6d70f0716fda070050d6532a69df6**;
.circleci/config.yml, scripts/probe_circleci_password_source.py,
scripts/capture_quiesced_circleci.py, scripts/remote_quiesced_capture.php,
scripts/lib/backup_wp_config.php.
Main/Handoff **3e1b7dc16583e890df92053cfc8c0ba8adce98c6** enthält dokumentierte
echte Capture-/Writer-/Readback- und isolierte FullRestore-Nachweise.
Das ist ein Quellen-/Dokumentationsvergleich, keine erneute Liveüberprüfung
oder unabhängige Bestätigung aller aktuellen Dreamloud-Tagesläufe.
Dreamloud wurde nicht geändert, kein Backup/Workflow gestartet.

| Übernahme aus Dreamloud | OrgaVela-Anpassung/Nachweis |
| --- | --- |
| CircleCI statt lokalem PC | bestehendes kostenloses OrgaVela-Projekt, vorhandene getestete Runtime |
| gepinnter IONOS-SSH-Zugang, Passwort nur im Sourcecontext | eigener OrgaVela-Host/User/Root und streng passende Contextrestriction; keine Dreamloud-Credentials |
| Verschlüsselung mit Public Key vor Übergabe | vorhandener OrgaVela-PGPony-Empfänger; kein Dreamloud-Privatekey/Recoverycontext |
| getrennter Ciphertext-Writer | ausschließlich eigenes privates orgavela-backups; separate minimale Rechte |
| vollständige Readbacks/Hash/immutable Release | echter Nachweis für neues OrgaVela-Archiv, Dreamloud-Erfolg ersetzt ihn nicht |
| isolierter DB+WordPress-Restore | Restore der tatsächlichen OrgaVela-Sicherung ohne Produktivüberschreibung |
| konsistenter Capture mit kontrollierter Wartung/Sperren | später eigene begrenzte Phase; aktueller Metadatencheck bleibt ohne PHP/SQL/Writes |

Der Dreamloud-Collector verwendet Remote-PHP, liest geparste DB-Konfiguration,
verbindet direkt mit MariaDB und kann kontrollierte Wartung/Read-Locks erzeugen.
Er lässt sich deshalb nicht als angeblich rein statischer SSH-Check einsetzen.
Seine Architektur und geeignete Komponenten sollen übernommen/adaptiert werden;
es wurde in diesem Abschnitt noch kein vollständiger Producer portiert.
Keine Hosts, Tabellenerwartungen, Schlüssel, Backupziele oder Credentials blind kopieren.

## 2026-10-22 — verbindliche Neufassung PGP05-SSH-20261022.2

**Überholt die frühere pauschale Pflicht zur IONOS-Supportauskunft/Prüfung aller
globalen Startup-Hooks.** Diese Pflicht war für den begrenzten Metadatencheck
unverhältnismäßig. Die Revidierung ist keine Behauptung einer Hookinspektion.

Der separat beauftragte unabhängige Prüfagent erteilt **APPROVED** für genau
c952645000b2954c04f1c0446c333392048065e6, festen Remote-Skript-SHA256
7d8e383ca0e09738b11a31a20d79c88b38a540539763f0091f0c69f692882e99,
unter normalem Vertrauen in die IONOS-gemanagte SSH-/Serverinfrastruktur und
deren übliches Kontostartverhalten. Dieses Infrastrukturverhalten ist nicht
unabhängig beobachtet. Kein absolutes Null-Nebenwirkungsversprechen.

Scope: eine gepinnte Verbindung zum privat gebundenen bestehenden OrgaVela-Konto,
exakt festes stdin-Shellskript für Account/Root/Pfad/Typ/Owner/Mode/Dateinamen.
Kein angefordertes PHP, WordPress-Bootstrap, SQL, Inhaltslesen, Schreiben,
Backup oder Deployment. 117/117 aktuelle CircleCI44-Tests, stderr-Fix und
unabhängig geprüfte immutable Runtime bleiben gültige Nachweise.

Unverändert erforderlich: exakte Projekt/Repo/Branch/SHA-Contextrestriction
vor Secretinjektion, getrennte private Credentialablage, feste Hostpin-Prüfung,
Ausgabe-/Zeitlimits, exaktes stdout/leeres stderr, eigene manuelle Freigabe.
Der Providertrust überstimmt keine bekannte ungewöhnliche Konfiguration,
Customhooks mit Sitecode/Schreibwirkung, Warnungen oder abweichende Ausgabe:
bei konkretem Gegenbeleg stoppen und erneut prüfen. IONOS-Anfrage ist dann ein
gezieltes Diagnosemittel, keine normale pauschale Vorbedingung.

**PGP05_STARTUP_REVIEWED=yes** bezeichnet nach dieser dokumentierten Neufassung
ausschließlich die erfolgte unabhängige Prüfung/Annahme dieses Startup-Scope
und Infrastruktur-Vertrauensmodells. Es behauptet NICHT die tatsächliche
Inspektion oder Abwesenheit jedes Hooks. Der historische Feldname bleibt auf
der bereits getesteten SHA unverändert; keine stille Semantikänderung.
Receipt bleibt startup_hooks_independently_observed_by_probe=false,
server_enforced_readonly_account=false, domain_vhost_binding_proven=false,
production_capture_allowed=false.

**Checkpoint5 jetzt 8/10 (80%)**: Kriterium6 unabhängige Sicherheitsprüfung
im präzisen obigen Scope ERFÜLLT. #8 tatsächliche authentisierte Account-/
Namespace-/Domainbindung und #10 akzeptierter realer Metadatenlauf OFFEN.
Reviewabschluss ist weder durchgeführter Login noch Produktionsbackupfreigabe.
V1-Zähler unverändert62/71 historisch,M0–M7 0/8,P4 0/4,R0/18.


## Konkreter nächster Betreiberpunkt — nur leeren Context vorbereiten
CircleCI Organization Settings → Contexts → neuer separater Context
**orgavela-pgp05-reviewed-metadata**, ausschließlich Projekt
**Projekt-Wordpress-Plugin**. Zulässige Mitglieder prüfen; bestehende
Backup-/Deploy-/Hostkeycontexts unangetastet lassen.

Expression exakt:
```text
pipeline.git.repo_owner == "janjrre"
and pipeline.git.repo_name == "Projekt-Wordpress-Plugin"
and pipeline.git.branch == "feat/pgp05-readonly-ssh-metadata-check-20261021"
and pipeline.git.revision == "c952645000b2954c04f1c0446c333392048065e6"
and not job.ssh.enabled
and not (pipeline.config_source starts-with "api")
```

Speichern und zunächst nur Projekt-/Expressionbeschränkung mit leeren
Environment variables zeigen, Kennungen/Privatdaten verdecken. Noch keine
Credentials, kein Pipeline-/Approvalstart. Dies ist ein neuer Credentialpfad,
dessen konkrete Restriktion vor Passwortinjektion verifiziert werden muss.
Danach wird die vorhandene private Ziel-/Passwortkonfiguration direkt in
CircleCI geführt, mit der dokumentierten Reviewreferenz und festem Script-Hash.
Keine Secrets in Chat/Screenshot/GitHub und keine IONOS-Supportanfrage nötig,
solange keine konkrete ungewöhnliche Startup-Evidenz vorliegt.
