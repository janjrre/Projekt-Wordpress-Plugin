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

## Dauerhafte V1-Planungs- und Fortschrittspflicht (Betreiberauftrag vom 12.10.2026)

Diese Regel gilt ab sofort für **jede UOP-Core-/OrgaVela-Arbeit in diesem Repository und jede UOP-bezogene Chat-Antwort**, auch neue Chats und sehr kleine Folgeaufgaben.

**Kanonische Quellen:**
1. [V1_MASTER_PLAN.md](docs/project/V1_MASTER_PLAN.md) – vollständiger, versionierter **M0–M7-/M6-10-/P4-/G-/R-Weg bis zur V1-Abnahme**, normative Pakete, Abhängigkeiten, Risiken und Stop-Gates.
2. [V1_STATUS.md](docs/project/V1_STATUS.md) – **aktueller tatsächlich belegter Projektfortschritt**, PRs, CI/Tests, OrgaVela-Zustand, Blocker und unmittelbar nächste Pakete.
3. [V1_ACCEPTANCE_MATRIX.md](docs/project/V1_ACCEPTANCE_MATRIX.md) – Prüffälle, Kriterien, Release-Akte, signierbare Nachweise.
4. [ASSISTANT_WORK_LOG.md](docs/operations/ASSISTANT_WORK_LOG.md) – **append-only** Aufgaben-/Entscheidungs-/Test-/Commit-Journal.

**Vor jeder UOP-Antwort / jedem Entwicklungsschritt:**
- Diese vier Dokumente und die betroffenen GitHub-Issues, PRs, CI-Checks und – soweit verfügbar und notwendig – die OrgaVela-Testwebsite auslesen.
- Aufgaben einer bestehenden **M0–M7-, M6-01…10/P1…6-, P4-01…04-, G-, W- oder R-ID** zuordnen; nicht ohne sachlichen Grund neue Meilensteine erfinden.
- **Implementierung ≠ Merge ≠ getestete Installation ≠ technische/fachliche/Release-Abnahme.** Fortschritt nur mit zeitlich/versionsbezogen verifizierten Belegen verändern. Kein globaler Prozentsatz ohne sachlichen Nenner.
- Bei neuen Erkenntnissen **Masterplan** (Scope/Priorisierung/Abhängigkeiten) **versionieren**, **V1_STATUS.md** (Fortschritt/Beleg/Blocker/Nächste) fortschreiben, bei neuer/erfüllter Abnahme **V1_ACCEPTANCE_MATRIX.md** ergänzen. GitHub-Commit(s) angeben.
- Bei jeder UOP-Anfrage **ASSISTANT_WORK_LOG.md append-only** ergänzen, selbst bei fehlgeschlagenen Versuchen oder unverändertem Status; enthalten: Arbeitsauftrag, überprüfte Fakten, sichere Entscheidungszusammenfassung, Umsetzung, Tests, SHA/Run, noch offene Arbeit.
- **Am Ende jeder einzelnen UOP-Antwort** stets den **V1-Fortschrittsblock** mit (a) Gesamtstatus „V1 offen/abgenommen“, (b) M0–M7-Status, (c) P4-01…04- und PR-Integrationsstatus, (d) den wichtigsten tatsächlichen Blockern, (e) exakt nächstem Paket und (f) **Links auf kompletten Masterplan, Live-Status und Abnahmematrix** erneut senden. Ohne überprüfte Statusänderung ausdrücklich „Stand unverändert“.
- Antworten dürfen einen **kompakten Fortschrittsblock plus direkten Link zum vollständigen, jederzeit abrufbaren Masterplan** verwenden, statt jedes Mal 35.000 Zeichen zu wiederholen. Der Plan selbst muss vollständig im Repository bleiben und bei Änderungen neu verlinkt werden.
- Keine Zusage unbeaufsichtigter Hintergrundaktualisierung: Aktualisierungen erfolgen nur, wenn der Assistent **tatsächlich mit einer neuen Aufgabe ausgeführt wird** oder eine separat beauftragte Automation läuft.
- Bereits vorhandene Tool-Sicherheitssperren respektieren; keine indirekten GitHub-Trigger als Umgehung. Niemals reale Nutzer-, Minderjährigen- oder Secret-Daten in dem öffentlichen Repository speichern.

**Ausgangszustand:** [Aktueller V1-Status](docs/project/V1_STATUS.md) vor allem betreffend sieben offene gestapelte PRs #1–#6/#11, P4-Issues #7–#10 offen, Testsite `orgavela.com` mit **UOP Core 0.1.0-alpha.2 aktiv**, DB-Optionswerte 2/2, aber noch keine vollständig geprüfte SQL-Schema-/Backup-/Cron-/Mail-/V1-Abnahme. Keine Übernahme von Dreamloud in den UOP-Plan.

## Technische Arbeitsregeln

- Aktive technische Spezifikationen: `docs/specification/phase-2-v1-specification.md`, `docs/specification/phase-3-specification-freeze.md`, `docs/specification/README.md`, ADR 310–313 und Folge-ADRs.
- Projektfreigabe erlaubt selbstständige Entwicklung, Commits, Reviews, PR-Merges, kostenfreie Staging-Tests und Veröffentlichungen, **jedoch nur** bei bestandenen Qualitäts-, Abhängigkeits-, Sicherheits-, Rechts- und Betriebs-Gates.
- Gestapelte PRs/Branch-Abhängigkeiten respektieren. Kompatibilitäts-/Datenintegritätsprüfungen nicht umgehen, um einen Deployment-Erfolg vorzutäuschen.
- Vor produktiven Migrationen: Backup, Restore-Probe, unterstützte Konfiguration und verifizierte Tests.
- **Aktuelles UOP-Ziel:** `https://orgavela.com` bei IONOS, vollständig getrennt von Dreamloud. Für die SSH-Verbindung ausschließlich das fail-closed [IONOS-Deployment](docs/operations/orgavela-ionos-ssh-deployment.md) und geschützte GitHub-Environment-Secrets verwenden. Der Workflow kann manuell oder durch eine **gezielte Änderung an `.github/deploy-requests/orgavela.json` auf `main`** ausgelöst werden; diese Datei nur nach ausdrücklichem Auftrag und bestandenen Gates ändern, niemals als allgemeine Änderungsdatei. Vor Upload Zielidentität und Prüfsumme nachweisen; kein automatisches Aktivieren oder Migrieren. Die alte WordPress.com-Testinstanz `orgavela.wpcomstaging.com` ist wegen `latin1` weiterhin blockiert (Issue #12) und **nicht** das SSH-Ziel. Keine echten Nutzer- oder Testemails an Dritte.

- Der reine SFTP-Fallback bei fehlender SSH-Shell ist in `docs/operations/orgavela-ionos-sftp-deployment.md` und `.github/workflows/deploy-orgavela-sftp.yml` beschrieben. Er kann nach korrekter geschützter Secrets-Einrichtung entweder manuell oder **nur** über `.github/deploy-requests/orgavela-sftp.json` auf `main` ausgelöst werden. Deployment-Requests nur nach ausdrücklichem Auftrag/erfüllten Gates verändern; niemals parallel mit dem SSH-Deployment starten. Er verweigert Überschreiben, Aktivierung und Datenbankmigrationen.


## Verbindliche V1-Masterplanung, Abnahme und Fortschrittsanzeige (ab 12.10.2026)

**Kanonische Planquelle für sämtliche UOP-Core-Aufträge:** [V1_ACCEPTANCE_PLAN.md](docs/operations/V1_ACCEPTANCE_PLAN.md) mit vollständiger Milestone-/Paket-/Abnahmematrix; [V1_PROGRESS.json](docs/operations/V1_PROGRESS.json) ist die maschinenlesbare, auf belegbare Fakten gestützte Momentaufnahme. Diese beiden Dokumente sind **dauerhafte Pflicht**, keine einmalige Chat-Notiz. Sie ergänzen den unverändert append-only geführten [ASSISTANT_WORK_LOG.md](docs/operations/ASSISTANT_WORK_LOG.md).

1. **Bei jeder neuen UOP-/OrgaVela-Anfrage** vor der fachlichen Antwort die aktuelle Planung, zuletzt belegten GitHub-Stand (Issues/PRs/Checks), betroffene Staging-Version und Blocker angemessen prüfen. Nicht die in einem früheren Chat behaupteten Zwischenstände blind übernehmen.
2. **Bei jeder erledigten Arbeit** im Arbeitslog einen neuen datenschutzbereinigten Eintrag ergänzen. Wenn Meilenstein-, Paket-, Gate-, Risiko-, Abhängigkeits- oder Versionsstatus sich sachlich ändert, Masterplan und V1_PROGRESS.json **im selben Arbeitsabschnitt** aktualisieren und einen Nachweis (SHA, Job, Issue/PR, WordPress-Read-Check) hinterlegen. **Auch bei unverändertem Zustand** Abgleich und Datum im Log oder einer kleinen Prüfeintragung dokumentieren; keine unnötigen inhaltlichen Planänderungen behaupten.
3. **Nach jeder UOP-bezogenen Antwort** den **vollständigen Masterplan als GitHub-Link** erneut mitsenden und ein kompaktes **V1-Fortschrittscockpit sichtbar ausweisen**: Stand/Prüfdatum, technisch implementierte normative Pakete X/71, förmlich abgenommene M-Meilensteine Y/8, vollständig abgenommene P4-Pakete Z/4, grüne harte Release-Gates G/18, aktivierte OrgaVela-Version, die 1–3 wichtigsten Blocker, seit letztem Bericht erledigtes Ergebnis und die nächste priorisierte Aktion. Lange 39k-Zeichen-Masterplanung **nicht bei jeder Kurzantwort in voller Länge kopieren**; stets direkt verlinken, damit die vollständige und aktuelle Fassung zugänglich bleibt. Bei vom Nutzer ausdrücklich gewünschter Vollwiedergabe dagegen den vollständigen Plan geben.
4. **Status ehrlich trennen:** implementiert ≠ reviewt ≠ CI-grün ≠ integriert ≠ auf IONOS geprüft ≠ formal V1-abgenommen. **62/71** dokumentierte Coding-Pakete sind keine Gesamtprojektquote. Ein grüner Test auf altem SHA hebt keine rote aktuelle PR-CI auf. Keine fingierte Live-Verifikation.
5. **R01–R18 gelten als Release-Sperren.** M6-10 ist Paket 10 aus Milestone 6, **kein** Milestone 10. P4-01…P4-04 aus Issues #7–#10 sind verpflichtend gesondert zu verfolgen; M7-01…M7-08 sind ein eigener offener Release-Meilenstein. Zusatzaufgaben Operations O01…O12 nicht in der 71er-Zahl verstecken.
6. **Kein stilles Hintergrundmonitoring:** Der Assistent aktualisiert nur bei tatsächlichen Aufträgen/ausgeführten Arbeiten oder ausdrücklich eingerichteten automatischen Tasks. Kann eine Sitzung GitHub nicht lesen/schreiben, dies im Status offen sagen und nie behaupten, es sei aktualisiert worden.
7. Beim PR/Release die Belege für **denselben HEAD** und den zugehörigen Worklog-/Masterplan-/JSON-Stand referenzieren; Story-/Milestone-Exit und ADR-Freeze respektieren. Merges und Deploys nur nach echten Review-/Rollback-/Privacy-/Security-/CI-Gates, nicht nur wegen pauschaler Autonomie.

## Übergabe nach jedem Arbeitsabschnitt

Zuletzt verifizierten SHA, CI-Status, aktive Website-/Plugin-Version, offene Blocker und nächste konkrete Arbeit im Log schreiben, damit die Arbeit bei verschwundenen Chatantworten oder neuen Gesprächen nachvollziehbar fortgesetzt werden kann. In nutzerseitigen Berichten immer zwischen bestätigt erledigt, teilweise erledigt und noch offen unterscheiden.
