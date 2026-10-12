# AGENTS.md – UOP Core: verbindliche Arbeitsbedingungen für Coding-Assistenten

**Geltungsbereich:** Gesamtes Repository `janjrre/Projekt-Wordpress-Plugin`, alle Branches, Pull Requests, Testumgebungen, Veröffentlichungs- und Wartungsarbeiten. **Verbindlich ab 2026-10-12**, auf ausdrücklichen Auftrag des Projektbetreibers.

## Vor jeder Änderung

1. Diese Datei sowie `docs/operations/ASSISTANT_WORK_LOG.md`, die geltende Spezifikation und relevante ADRs/Issues lesen.
2. Tatsächlichen Branch/HEAD, vorhandene Tests und Zielumgebung feststellen.
3. Nie stillschweigend auf Dreamloud zugreifen: `dreamloud.de` und `janjrre/dreamloud-portal` dürfen **nicht** als Testumgebung dienen oder geändert werden.
4. Keine kostenpflichtigen Verträge, Testangebote mit späterer Zahlung, Dienste mit Nutzungsabrechnung oder sonstige finanziellen Verpflichtungen ohne vorherige gesonderte Zustimmung auslösen.
5. Keine echten Teilnehmerdaten in Entwicklungs-/Testumgebungen; keine Veröffentlichung mit bekannten kritischen Sicherheitsmängeln, keine Umgehung verpflichtender Prüfungen oder rechtlicher Einwilligungs-/Vertretungsregeln.

## Pflicht: Jede beauftragte Arbeit in GitHub dokumentieren

**Bei jeder Chat-Anfrage zu UOP Core** den für Menschen lesbaren Arbeitsverlauf in `docs/operations/ASSISTANT_WORK_LOG.md` fortführen; auch reine Analysen, Prüfungen, Entscheidungen, fehlgeschlagene Versuche und nicht ausführbare Aufgaben sind nachvollziehbar zu protokollieren. Die Dokumentation ist Teil der Definition of Done, nicht eine freiwillige Nacharbeit.

Jede neue Eintragung soll enthalten:
- Zeitstempel/Datum, **Arbeitsauftrag** (sinngetreue Wiedergabe), betroffene Issues/PRs und Umgebung.
- Ausgangslage mit geprüftem Branch/Commit und Faktenlage.
- **Ansätze und Begründungen**: Optionen, Entscheidungskriterien, Risiken und warum eine Variante gewählt oder verworfen wurde; eine nachvollziehbare Entscheidungszusammenfassung, **keine internen/vertraulichen Gedankengänge**.
- Tatsächliche Umsetzung: bearbeitete Pfade, GitHub-Commit-IDs, Aktionen auf Staging/WordPress, *auch Fehler und gescheiterte Versuche*.
- Testergebnisse mit Run-IDs, Schema-/Sicherheitszustand; Unterschied zwischen geplant, ausgeführt und verifiziert.
- Offene Punkte, genaue nächste Schritte, Blocker, Freigaben und Abbruch-/Rollback-Bedingungen.

Diese Eintragungen **append-only** führen (frühere Zustände nicht rückwirkend umdeuten; Korrekturen als neue Einträge), spätestens vor Ende einer Ausführung committen. Bei längeren Aufgaben Zwischenstände sichern; Commits nach Möglichkeit mit Issue/PR verlinken. Bei rein beratenden Chat-Antworten mindestens die Entscheidung bzw. Aussage samt zugrunde liegender öffentlich belegbarer Fakten und etwaigen Prüfgrenzen festhalten.

**Nicht speichern:** vollständige vertrauliche Chat-Inhalte, private Personenangaben, Zugangsdaten, Tokens, Kontoinformationen, reale Teilnehmerdaten, Geheimnisse, Rohdaten aus WordPress, komplette private Modell-Überlegungen oder unredigierte Toolausgaben. Dieses GitHub-Repository ist öffentlich: nur notwendige, sanitiserte Projektinformationen dokumentieren. Vertrauliche Befunde ausschließlich in dafür freigegebene private Systeme überführen; ansonsten im Log kenntlich machen, dass Details aus Datenschutz-/Sicherheitsgründen ausgelassen wurden.

Eine vollständige automatische Übertragung des Chatverlaufs ist ohne entsprechende technische Anbindung **nicht** möglich. Das Protokoll wird bei tatsächlich ausgeführten Chat-Aufträgen fortgeschrieben, nicht automatisch nach Beendigung der Sitzung; spätere Assistenten müssen diese Pflicht selbst beachten.

## Technische Arbeitsregeln

- Aktive technische Spezifikationen: `docs/specification/phase-2-v1-specification.md`, `docs/specification/phase-3-specification-freeze.md`, `docs/specification/README.md`, ADR 310–313 und Folge-ADRs.
- Projektfreigabe erlaubt selbstständige Entwicklung, Commits, Reviews, PR-Merges, kostenfreie Staging-Tests und Veröffentlichungen, **jedoch nur** bei bestandenen Qualitäts-, Abhängigkeits-, Sicherheits-, Rechts- und Betriebs-Gates.
- Gestapelte PRs/Branch-Abhängigkeiten respektieren. Kompatibilitäts-/Datenintegritätsprüfungen nicht umgehen, um einen Deployment-Erfolg vorzutäuschen.
- Vor produktiven Migrationen: Backup, Restore-Probe, unterstützte Konfiguration und verifizierte Tests.
- **Aktuelles UOP-Ziel:** `https://orgavela.com` bei IONOS, vollständig getrennt von Dreamloud. Für die SSH-Verbindung ausschließlich das fail-closed [IONOS-Deployment](docs/operations/orgavela-ionos-ssh-deployment.md) und geschützte GitHub-Environment-Secrets verwenden. Der Workflow kann manuell oder durch eine **gezielte Änderung an `.github/deploy-requests/orgavela.json` auf `main`** ausgelöst werden; diese Datei nur nach ausdrücklichem Auftrag und bestandenen Gates ändern, niemals als allgemeine Änderungsdatei. Vor Upload Zielidentität und Prüfsumme nachweisen; kein automatisches Aktivieren oder Migrieren. Die alte WordPress.com-Testinstanz `orgavela.wpcomstaging.com` ist wegen `latin1` weiterhin blockiert (Issue #12) und **nicht** das SSH-Ziel. Keine echten Nutzer- oder Testemails an Dritte.

- Der reine SFTP-Fallback bei fehlender SSH-Shell ist in `docs/operations/orgavela-ionos-sftp-deployment.md` und `.github/workflows/deploy-orgavela-sftp.yml` beschrieben. Er kann nach korrekter geschützter Secrets-Einrichtung entweder manuell oder **nur** über `.github/deploy-requests/orgavela-sftp.json` auf `main` ausgelöst werden. Deployment-Requests nur nach ausdrücklichem Auftrag/erfüllten Gates verändern; niemals parallel mit dem SSH-Deployment starten. Er verweigert Überschreiben, Aktivierung und Datenbankmigrationen.

## Übergabe nach jedem Arbeitsabschnitt

Zuletzt verifizierten SHA, CI-Status, aktive Website-/Plugin-Version, offene Blocker und nächste konkrete Arbeit im Log schreiben, damit die Arbeit bei verschwundenen Chatantworten oder neuen Gesprächen nachvollziehbar fortgesetzt werden kann. In nutzerseitigen Berichten immer zwischen bestätigt erledigt, teilweise erledigt und noch offen unterscheiden.
