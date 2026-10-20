# PGP-04 — Branch-Freeze: Betreiber-Screenshot und unabhängige SHA-Prüfung

Datum: 20.10.2026, 23:02 (Betreiber-Screenshot). Dokumentation absichtlich **auf separatem Auditbranch**, nicht auf dem eingefrorenen Testbranch.

## Tatsächlich sichtbarer Nachweis

- GitHub-Branchschutzregel: `security/pgp04-synthetic-release-20261020`
- GitHub-Oberfläche: `Applies to 1 branch` mit **exaktem** Branch.
- **`Lock branch` aktiviert**: Checkbox im Screenshot.
- **`Do not allow bypassing the above settings` aktiviert**: Checkbox im Screenshot.
- **`Allow force pushes` nicht aktiviert**: Checkbox im Screenshot.
- `Allow deletions` liegt **unterhalb des sichtbaren Screenshot-Ausschnitts** und ist auf diesem Screenshot nicht erneut belegbar (zuvor deaktiviert gezeigt).
- Ein sichtbarer Speichern-/Erfolgsbanner für diese *Änderung* fehlt im Screenshot; die tatsächliche Persistenz ist damit noch nicht unabhängig belegt.

## Unabhängige GitHub-Connector-Prüfung

`compare_commits` mit Base `62c9524f09d9f0ad0e4eea0648cb1be14b051f0f` gegen HEAD `security/pgp04-synthetic-release-20261020`:

- `status=identical`, `ahead_by=0`, `behind_by=0`.
- Der Branch zeigt aktuell auf den exakt vorher geprüften Commit `62c9524f09d9f0ad0e4eea0648cb1be14b051f0f`.
- Die verbundene GitHub-App kann die effektiven Branchschutzregeln und `protected=true` nicht auslesen. Ein öffentlicher REST-Abruf war nicht möglich. **Branch-Lock wirksam: UI-VORBEHALT, noch kein API-PASS.**

## Kritischer STOP / bevor Secret oder kostenpflichtiger CI-Run

1. Betreiber stellt sicher, dass die Regel über `Save changes` gespeichert wurde, und liefert gespeicherten Einstellungszustand bzw. GitHub-Branchansicht mit Lock-Markierung.
2. Vor Tokenfreigabe `Allow deletions` und die tatsächlichen Ruleset-Warnungen noch prüfen.
3. Ein dedizierter, zuvor per Screenshot auf genau Projekt und Branch eingegrenzter CircleCI-Context `orgavela-pgp04-release-only` existiert und hat **keine Variablen**. `PGP04_APPROVED_SHA` sowie der kurzlebige Token dürfen **noch nicht** eingetragen werden.
4. YAML-Parser/Context-Runtime und Quellcode-Release-Weg sind weiterhin nicht auf CircleCI geprüft. Einmaliger **synthetischer** Release erst nach bewusster gesonderter Betreiberfreigabe.
5. Keine echten WordPress-/MariaDB-Daten, keine privaten OpenPGP-Schlüssel oder Passphrasen, keine automatische Veröffentlichung, keine Merges.

### Projektzählstand
PGP02/PGP03 bestehende PASS, PGP04 Remote-Release offen; V1 Codinghistorie 62/71, M0–M7 0/8, P4 0/4, Release R01–R18 0/18. Keine weiteren CircleCI-Runs oder Kosten in diesem Schritt.
