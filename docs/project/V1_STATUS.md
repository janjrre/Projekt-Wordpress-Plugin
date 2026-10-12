# UOP Core / OrgaVela – aktueller V1-Fortschrittsbericht

**Stand:** 12.10.2026, initiale V1-Baseline nach Liveprüfung. **V1-Status: NICHT ABGENOMMEN.**  
**Normativer Masterplan:** [V1_ACCEPTANCE_PLAN.md](../operations/V1_ACCEPTANCE_PLAN.md) · **Verbindliche Fortschrittsdaten:** [V1_PROGRESS.json](../operations/V1_PROGRESS.json) · **Zentrales Abnahme-Issue:** [#15](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/15) · **Ergänzende Roadmap:** [V1_MASTER_PLAN.md](V1_MASTER_PLAN.md) · **Abnahmematrix:** [V1_ACCEPTANCE_MATRIX.md](V1_ACCEPTANCE_MATRIX.md) · **Arbeitsjournal:** [ASSISTANT_WORK_LOG.md](../operations/ASSISTANT_WORK_LOG.md).

## Aktuelle Welle: W0 – Bestands- und Sicherheitsabnahme

**Stand 12.10.2026: IN ARBEIT, noch nicht abgenommen.** [W0-Live-/Restore-Bericht](../operations/W0_BASELINE_AND_RECOVERY.md) · [Issue #16](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/16) · [verbindliche maschinenlesbare Messwerte](../operations/V1_PROGRESS.json).

| Paket | Stand nach aktuellem Live-Abgleich | Nächster belastbarer Nachweis |
| --- | --- | --- |
| **O01 – OrgaVela-Isolation** | HTTPS/SFTP `/public` erfolgreich, Konto mit breiterem Webspace-Zugriff | engere IONOS-Rechte und begrenzter Negativtest |
| **O02 – M1-08 SQL-Schemahealth** | Plugin aktiv; DB-/Datenversion 2/2; keine physische Tabellen-/Indexkontrolle | bereinigter WordPress-Site-Health-/SQL-Strukturbeleg |
| **O03 – Backup/Restore** | Sicherungsverfahren anhand offizieller IONOS-Anleitungen spezifiziert; kein echtes Backup verifiziert | IONOS-Backupstände, privater DB-/Dateien-Export, isolierter Restore |
| **O06 – Staging-Datenschutz** | WordPress-Marker `production`; `discourage_search_engines=false`; keine Live-Änderung | öffentliche Produktseite vs. geschützter UOP-Testbereich entscheiden |
| **Parallel O09 – CI-Fehler** | PR #11 HEAD `7892a258`; Push Foundation **8/8 grün**, PR Foundation **7/8 rot** (PHP8.4/MariaDB 10.11 Aktivierungsnavigation), HTTPS-Playwright **rot** (Caregiver-Login-Timeout) | [Push](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248031834) / [PR Foundation](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040810) / [HTTPS Browser](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38248040695) – weiterhin Releaseblocker |

**Gesperrt bis W0-Belegen:** keine neue IONOS-DB-Migration, keine echten Teilnehmerdaten, kein ungeprüfter WP-Cron-/Mailer-Effekt, kein produktives UOP-Beta-Opt-in. Das aktuelle Alpha bleibt unverändert.

## 1. Übersicht auf einen Blick

| Zählbare Achse | Ist / Soll | Interpretation |
| --- | --- | --- |
| **Technisch implementierte normative Pakete** | **62 / 71** M0–M7-Pakete, gemäß PR-Belegen | Das ist nur ein Code-Umsetzungsindikator, **kein** V1-Gesamtfortschritt; M6-10 und M7-Gates fehlen. |
| **Formale V1-Meilensteinabnahmen** | **0 / 8** (M0–M7) | Code ist teilweise/weitgehend vorhanden, aber kein Meilenstein final gegen integrierte V1 akzeptiert. |
| **Formale Release-Gates R01–R18** | **0 / 18 grün** | Kein finaler RC-/Stable-Nachweis, trotz erfolgreicher Alpha-Aktivierung. |
| **Offene gestapelte PRs** | **7 / 7 offen**, **0 / 7 gemergt** | [#1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) bis [#6](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/6) und [#11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11). Draft: #2, #3, #6, #11. |
| **M6-10-Funktions-/Betriebspakete** | **P1–P3 technisch historisch bearbeitet**, P4 teilimplementiert, P5–P6 offen | Eine durchgehende IONOS-Betriebsabnahme auf V1-Kandidat fehlt. |
| **Minderjährigen-Pakete P4-01…04** | **0 / 4 formal abgeschlossen** | [#7](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/7), [#8](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/8), [#9](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/9), [#10](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/10) offen. |
| **M7-Releasepakete** | **0 / 8 formal abgeschlossen** | Frühere Teilprüfungen sind Ausgangspunkt, nicht abschließende M7-Abnahme. |
| **OrgaVela-Testbetrieb** | **Installation + Aktivierung + DB-Versionen 2/2 bestätigt** | Site Health physische Tabellen, Cron, Mail, Backup/Restore, Noindex weiter offen. |
| **Jüngste CI PR #11** | **Älterer Stand mit roten PR-Checks, neuer HEAD `7892a258` in Prüfung** | Noch keine vollständig grüne aktuelle Pflichtmatrix. |
| **Formale Stable-Freigabe** | **0 / 1** | V1 bleibt gesperrt, bis R01–R08 vollständig nachgewiesen. |

**Keine Gesamt-Prozentangabe:** Implementierung, Merge, Betrieb und Abnahme haben unterschiedliche Nenner. 0/8 formale Meilensteinabnahmen bedeutet **nicht**, dass 0 % Code geschrieben wurden. Umgekehrt bedeutet das aktive Alpha-Plugin **nicht**, dass V1 bereits fertig ist.

## 2. Jede Milestone-Stufe: Ist, Rest, nächste Prüfung

| Meilenstein | Code-Stand | V1-Abnahme | Wichtigster offener Punkt / Beleg |
| --- | --- | --- | --- |
| **M0 Foundation** | implementiert, historische 8/8 CI | **offen** | [PR #1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) Review/main-Integration, neuer Source-/Build-Nachweis |
| **M1 Persistence** | 28 Basis- + V2-Altersschema; Live-Options 2/2 | **offen** | physisches Schema / Site-Health / Restore / [PR #1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) |
| **M2 Identitäten/Policy** | Code auf gestapeltem Branch | **offen** | [PR #2](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/2) Draft/Review, Cross-Channel-Gate |
| **M3 Profile/Events/Forms** | M3-01…10 in Draft-PR | **offen** | [PR #3](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/3), Form-/Schema-/A11y-Abnahme |
| **M4 Registration/Capacity** | Service-Code 01…09, historisch grüne CI | **offen** | [PR #4](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/4), Mail/REST/Browser-Livepath |
| **M5 Consent/Privacy/Mail** | 01…10 als Code bereit | **offen** | [PR #5](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/5), Cron/Mail/Legal/Privacy |
| **M6 UI/REST/M6-10** | M6-01…09 weitgehend, M6-10 P4–P6 nicht abgenommen | **offen** | [PR #6](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/6), [PR #11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11) + #7–#10 |
| **M7 Hardening/Release** | noch kein eigener finaler Review/Release-Kandidat | **offen** | Performance, Security, Upgrades, A11y, Packaging, RC/Stable |

### Detailstatus der M6-10- und P4-Pakete

| Paket | Tatsächlicher Stand | Rest |
| --- | --- | --- |
| **M6-10/P1 CI** | historische 16/16 und HTTPS-Baselines, aber jüngstes PR-#11-CI rot | aktuellen Browser-/E2E-Defekt auf neuem Head ursächlich beheben |
| **M6-10/P2 Mobile** | automatische 320px-/Keyboard-Cases vorhanden | reale manuelle WCAG-/Screenreader-Abnahme auf V1-Kandidat |
| **M6-10/P3 Negative Security** | Session-/Nonces-/Revoke-Tests historisch vorhanden | wiederholen + Cross-Org und Pen-Test-Review |
| **M6-10/P4 Guardian/Consent** | Delegation/Einzelbestätigung technisch gehärtet; P4-01 teils | alle #7–#10 Abnahmekriterien, zwei unabhängige Entscheidungen, echte Nachweise |
| **M6-10/P5 Realbetrieb** | GitHub-HTTPS/Mailpit synthetische Gast-/Wartelisten-Journeys vorhanden | IONOS-Cron, externe Test-E-Mail, vollständiger End-to-End-Lauf |
| **M6-10/P6 Final** | noch nicht gestartet | zusammenhängende Browser- und fachliche Freigabe |
| **P4-01 #7** | Teilimplementation in PR #11 | Source-CI rot, externe Alters-Nachweis-/Retention-Regeln offen |
| **P4-02 #8** | kein belegter abgeschlossener zwei-Actor-Workflow | ADR, Schema, eigenständige Consent-Entscheidungen und Widerrufe |
| **P4-03 #9** | begrenzte Claim/Adminprüfung | verifizierbare Guardian- und Invite-Prozesse, Mehrfachvertretung |
| **P4-04 #10** | nicht formell begonnen | konsolidierte Minderjährigen-Rechts-/Security-/A11y-Golden-Paths |

## 3. Aktuelle, überprüfte technische Beweise (nur zugelassene Referenzen)

- **OrgaVela Hosting:** https://orgavela.com, WordPress 7.1.3, PHP 8.3.33, MariaDB 11.8.8, DB utf8mb4 und SSL.
- **SFTP Verbindung/Root:** [Run #38239184433](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38239184433) bestätigte /public/wp-admin, /public/wp-includes und /public/wp-content/plugins.
- **Installiertes Plugin:** [Installationslauf #38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035) mit SHA256 acbebdd9df25d996848bbca6ebe6726ee1a986207fc7c7b818474dc93134267a. WordPress-MCP bestätigte UOP Core 0.1.0-alpha.2 und Plugin-Datei.
- **Aktivierung:** via separatem OrgaVela-WordPress-MCP erfolgreich, unabhängiger Plugin-Get meldete active. WordPress-Options-Checks uop_db_version=2 und uop_data_version=2. **Kein SQL-Schema-Vergleich oder geprüfter DB-Restore.**
- **Zeitsteuerung:** cron_disabled=true; UOP Action Scheduler registriert, zu Prüfbeginn fünf fällige WP-Aufgaben insgesamt. [Issue #13](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/13) mit Deploymenthistorie.
- **Sichtbarkeit:** discourage_search_engines=false. Noch kein Nachweis eines separaten externen Cron oder eines gesicherten nichtöffentlichen Testbetriebs.
- **CI Paket-Source 4f2c4df:** [Foundation 8/8](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097092) und [HTTPS/Mailpit](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097051) grün. **Dieser SHA entspricht dem auf OrgaVela installierten Paket**, nicht dem aktuellsten PR #11.
- **Aktueller PR-#11-HEAD 1b986587:** [Push Foundation #38233559467](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233559467) 8/8 erfolgreich; [PR Foundation #38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475) 7/8 mit Playwright-Timeout in PHP 8.5/MySQL 8.0; [PR HTTPS/Mailpit #38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383) mit 2 fehlgeschlagenen Chromium-Szenarien. Keine Deaktivierung dieser Tests als „Fix“.
- **Alter WordPress.com-Stagingweg:** [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12), latin1-Konflikt. Für OrgaVela mit bestätigtem utf8mb4 kein Blocker mehr; Issue als historische Hostingentscheidung bereinigen, nicht die alte Site in V1 verwenden.

## 4. Offene Release-Stoppunkte (Risikoregister)

| ID | Schwere | Konkreter Befund | Besitzer / Maßnahme | Freigabe |
| --- | --- | --- | --- | --- |
| **B01** | P0 | neuesten PR #11 CI rot | Entwicklung: Fehler-RCA, stabile Repro und neue grüne Runs | nein |
| **B02** | P0 | #7–#10 / Zwei-Personen-Consent/Guardian-Verification ungeklärt | Entwicklung + Betreiber-Rechts-/Datenschutzprüfung | nein |
| **B03** | P0 | sieben gestapelte PRs ungemergt, Alpha-Source nicht GitHub-main | Entwicklung: Review & sequenzielle Integration | nein |
| **B04** | P0 | kein echter IONOS-DB-Restore-/Upgrade-Rollbackbeweis | Betreiber/Entwicklung: Backup und isolierter Restore | nein |
| **B05** | P0 | WordPress-Cron deaktiviert, überfällige Tasks | Betreiber/Entwicklung: externer Scheduler + Worker-Health | nein |
| **B06** | P0 | keine reale synthetische externe E-Mail-Zustellungsabnahme | Betreiber/Entwicklung: Mailkonfiguration/SMTP-Test | nein |
| **B07** | P1 | Suchmaschinenabschreckung aus, Datenschutz-/Zugriffsgrenze unvollständig | Betreiber: nichtöffentliches Staging/Noindex/Consent | nein |
| **B08** | P0 | UOP-Schema-Health vs. tatsächliche DB nicht nachgewiesen | Entwicklung: direkte Site-Health-/SQL-Strukturprüfung | nein |
| **B09** | P1 | SFTP-Account hat zu breite Verzeichnisrechte | Betreiber/Entwicklung: dedizierter enger Zugriff | nein |
| **B10** | P0 | M7 Performance/Security/A11y/Upgrade/RC-Abnahme offen | Entwicklung + Reviewer | nein |
| **B11** | P1 | privater authentifizierter Actions-Dispatch aus Chat nicht verfügbar | Betreiber: autorisierter Connector oder bewusst manuelle Runs | nein |

**Rechts-/Finanzstopps:** keine realen Minderjährigen-/Teilnehmerdaten; keine echten Zustimmungs-/Vertretungsbeweise ohne unabhängige Prüfung; kein Vertrag mit Gebühren; kein Dreamloud-Zugriff.

## 5. Nächste konkrete Aufgaben (Backlog in verbindlicher Reihenfolge)

1. **W0-01 – Aktueller PR #11 CI rot:** aus Run #38233565475 die Browser-Deaktivierungs-Timeoutursache und aus #38233565383 die HTTPS-/Chromium-Szenarien eingrenzen, Ursache beheben, CI auf **exaktem aktuellen SHA** komplett grün.
2. **W0-02 – Schema und Site Health:** UOP Site-Health-Diagnose auf OrgaVela abnehmen: Migrationsstatus complete, 29 erwartete Tabellen inkl. Attest, Indizes und Stringencoding; keine Secrets/privaten Tabelleninhalte veröffentlichen. 2/2-Optionswerte sind lediglich Zwischenbeleg.
3. **W0-03 – Datenschutz/Betrieb:** Staging-Sichtbarkeit, IONOS-Backup und echte Restore-Probe in isolierter Kopie sowie Cron/Action Scheduler verbindlich einrichten; keine Versand-/Gast-Testoptins bis danach.
4. **W1-01 – P4-01/PR #11:** vollständige #7-Abnahmekriterien, Nachweisarten, Retention, Consent-/Guardian-Gates und Migrationen.
5. **W1-02 – P4-02 bis P4-04:** ADR, getrennte Entscheider, externe Guardian-Verifikation und gesamte Minderjährigen-/A11y-/Abnahmematrix.
6. **W1-03 – M6-10/P5–P6:** sichere Mail-/Waitlist-/Member-/Admin-End-to-End-Teststrecken plus manuelles Review.
7. **W2 – M0…M6 integrieren:** PR #1 → #2 → #3 → #4 → #5 → #6 → #11 / Folgelösungen zu #8–#10 mit frischer CI.
8. **W3/W4 – M7 + Release:** Performance/Security/Compatibility/Migrations-Recovery/WCAG/Privacy, Beta/RC/Stable, schriftliche R-Abnahme.

**Nächster ausführbarer Arbeitsschritt:** W0-01 oder W0-02; ersteres ist reine GitHub-Entwicklung, letzteres betrifft die bereits autorisierte OrgaVela-Testwebsite, ohne Zugriff auf Dreamloud.

## 6. Updatepflicht und Historie

- Jede UOP-Folgeantwort verifiziert den berührten Ist-Zustand; wenn eine Veränderung belegt wird, diese Datei mit Datum, Evidence-Links, Paketstatus, Risiken und nächsten Schritten aktualisieren.
- Der vollständige Plan wird bei Scope-/Prioritäts-/Abhängigkeitsänderungen über einen versionierten GitHub-Commit aktualisiert; **nicht** für jede unbelegte Vermutung.
- Den jeweiligen Arbeitsauftrag, Entscheidungen, Commits, Fehlversuche, Testergebnisse und Übergabe verpflichtend in [ASSISTANT_WORK_LOG.md](../operations/ASSISTANT_WORK_LOG.md) fortführen.
- **Jede UOP-Antwort endet mit einem V1-Fortschrittsblock** (M0–M7, P4, PR-Integrationen, wesentliche Blocker, nächstes Paket) und **Direktlinks zu diesem Status sowie vollständigem Masterplan**. Ohne Fortschritt explizit „Stand unverändert“.
- Diese Regeln gelten bei neuen Chats aufgrund [AGENTS.md](../../AGENTS.md) für Coding-Arbeiten, solange der Assistent das Repository öffnet; sie sind **keine Hintergrundüberwachung**.
