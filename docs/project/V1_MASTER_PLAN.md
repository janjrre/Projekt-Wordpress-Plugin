# UOP Core / OrgaVela – verbindlicher Gesamtplan bis zur V1-Abnahme

**Version 1.0 · Baseline 12. Oktober 2026 · Status: V1 NICHT abgenommen.**  
**Kanonischer Plan:** diese Datei. **Aktuelle, bei jedem Arbeitsauftrag zu prüfende Ist-Lage:** [V1_STATUS.md](V1_STATUS.md). **Prüffälle und Abnahmematrix:** [V1_ACCEPTANCE_MATRIX.md](V1_ACCEPTANCE_MATRIX.md). **Historie jeder Assistenzarbeit:** [ASSISTANT_WORK_LOG.md](../operations/ASSISTANT_WORK_LOG.md).  
**Verbindliche Quellenhierarchie:** [Phase 3 – Specification Freeze](../specification/phase-3-specification-freeze.md) > [Phase 2](../specification/phase-2-v1-specification.md) > [Phase 1](../specification/phase-1-product-strategy.md). Bei Abweichung neuer ADR und Migrations-/Kompatibilitätsentscheidung vor Codeänderung.

## 1. Ziel, Grenzen und Begriffe

**V1-Abnahme** bedeutet: Das eigenständige WordPress-Plugin lässt sich reproduzierbar neu installieren, sicher aktualisieren und zurückrollen; alle eingefrorenen MUST-Prozesse sind über WP-Admin, REST, Frontend-Blocks und Mitgliederportal konsistent, zugänglich und nachvollziehbar; Security, Privacy, Migration, Last, Betrieb, Dokumentation und reale Staging-Abnahme sind bestanden. Ein erfolgreiches CI, ein Alpha-Paket oder ein aktiviertes Plugin allein erfüllt dies ausdrücklich **nicht**.

**Technische Identität:** UOP Core; geplante Produktmarke OrgaVela. **Repository:** https://github.com/janjrre/Projekt-Wordpress-Plugin . **Eigene HTTPS-Installation:** https://orgavela.com bei IONOS. Die Website darf bis zur Abnahme nur kontrollierte **synthetische** Testdaten enthalten. Dreamloud, dreamloud.de und janjrre/dreamloud-portal bleiben ausnahmslos außen vor. **Keine neuen kostenpflichtigen Verträge, Nutzungsgebühren oder Abonnements ohne gesonderte Freigabe.** Vor externen Veröffentlichungen/WordPress.org-Einreichung eine explizite Freigabe nach bestandenen Gates einholen.

**V1-MUST-Scope:** Organisation als verbindliche Sicherheitsgrenze; Account ≠ Person; Identitäten, Beziehungen, Delegationen, Rollen und objekt-/feldbezogene Rechte; typisierte Profile; Events/Termine; Bedingungen; versionierte Formulare; Registrierungen/Gastprüfung/Kapazität/Warteliste; zielgerichtete dokumentversionierte Consent-Evidence; E-Mail-Outbox, Datenschutz, Retention, CSV/Privat-Export; Mitglieder- und Admin-UI; REST und dynamische Blocks; Audit, Site Health, Migration, Tests, Packaging.

**Nicht Teil von V1:** Payments, Rechnungen, allgemeine Dokument-/Datei-Workflows, qualifizierte eSignaturen, Check-in, Offline/PWA, SMS/WhatsApp, Automation Builder, großes CRM/LMS, freie CSV-Importe, Personen-Merge-UI, komplexe Recurrence, Platzpläne, allgemeines Reporting, Multi-Organisation-Produkt-UI. Keine stillschweigende Erweiterung des Freezes. Formulareinwilligungen sind keine automatisch rechtsgültigen elektronischen Unterschriften. Branchen-/Rechtsvorgaben müssen je Betreiber und Zweck geprüft werden.

## 2. Verbindliche Fortschrittsmethode

Jedes Arbeitspaket besitzt ID, verantwortliche Arbeit, Akzeptanzbeweis, Abhängigkeit, Blocker und Status im [dynamischen Statusbericht](V1_STATUS.md). Statusvokabular:

- **NICHT BEGONNEN:** weder Implementierung noch Abnahme belegt.
- **IN ARBEIT:** Code/Dokumentation/Test teilweise vorhanden; Exit-Kriterien noch offen.
- **CODE BEREIT / REVIEW OFFEN:** Implementation und historische Tests vorhanden, aber Integration, Freigabe oder aktueller Nachweis fehlt.
- **BLOCKIERT:** konkrete externe, technische, rechtliche oder Sicherheitsvoraussetzung fehlt.
- **ABGENOMMEN:** referenzierbarer Beleg auf dem **exakten** freigegebenen Commit und auf der richtigen Umgebung, alle Acceptance Criteria bestanden, Änderungen integriert, keine offenen Release-Stop-Findings.
- **ENTFÄLLT PER ADR:** ausschließlich dokumentierte bewusste Scope-Entscheidung, nie implizites Weglassen.

**Zwei getrennte Fortschrittsachsen:** (A) Code/Funktion implementiert und getestet und (B) in V1 integriert, real geprüft und freigegeben. Für M0–M6 darf „viel Code geschrieben“ nicht als Produktfertigstellung zählen. Prozentangaben nur bei einem vorher definierten, vollständig messbaren Nenner; keine erfundene globale Fertigstellungszahl.

**Verbindliche Statusroutine pro weiterer UOP-Antwort:** Die jeweils relevanten Fakten (GitHub PR/Issues/CI und Site, soweit zugänglich) neu prüfen, dieses Dokument bei Planänderungen versionieren, [V1_STATUS.md](V1_STATUS.md) nach jeder sachlich relevanten Statusänderung aktualisieren, alle Tätigkeiten/Fehlschläge im append-only Work Log protokollieren und in **jeder UOP-Abschlussantwort** einen kurzen V1-Fortschrittsblock samt Direktlinks zu **diesem vollständigen Plan**, dem Status und den nächsten drei Aufgaben wiedergeben. Ohne verifizierte Veränderung „unverändert“ ausweisen, statt ungeprüfte Erfolge zu behaupten. Automatische Ausführung außerhalb einer Chat-Sitzung ist damit **nicht** zugesichert; dafür wäre eine separat eingerichtete Automation notwendig. Bereits geltende Sicherheitschecks dürfen nicht umgangen werden.

## 3. Abhängigkeiten und kritischer Pfad

1. **G00 – Governance/Baseline** → referenzierbare Quellen, Statusschema, Berechtigungen und V1-Abnahmeentscheidung.
2. **G10 – OrgaVela Betriebsfähigkeit** → Backups/Restore; Search/Access, Cron/Action Scheduler, E-Mail, Logging und sichere Installation/Upgrades.
3. **M0/M1** → technisches Bootstrap, vollständiges Schema, Migration, OrgScope.
4. **M2** → Identitäten, zentrale Berechtigungen, Audit, Outbox.
5. **M3** → Felder, Bedingungen, Events, unveränderliche Formulare.
6. **M4** → Registrierungen/Kapazität/Warteliste.
7. **M5** → Consent/Datenschutz/E-Mail/Exports.
8. **M6** → REST, Admin, Portal, Blocks, Accessibility und gesamte M6-10-Journeys.
9. **P4 / Issues #7–#10** → belastbare Alters-/Vertreter-/Doppelentscheidung und rechtliche Betriebsgrenzen; P4 baut auf M2–M6 und darf nicht durch bloß vorhandene Delegationen übersprungen werden.
10. **G20 – Stack-Integration & GitHub-Freigaben** → PR #1 → #2 → #3 → #4 → #5 → #6 → #11 in dieser Abhängigkeitsreihenfolge reviewen und zielgerichtet integrieren; CI erneut auf zusammengeführtem Stand. Die Baseline M0–M7 aus Phase 3 bleibt maßgeblich.
11. **M7** → Last, Security, Upgrade, Kompatibilität, manuelle Accessibility, Betriebsdokumentation und Release-Build.
12. **R – Beta → RC → Stable / V1-Abnahmeprotokoll** → Betreiber-Unterschrift/Freigabe erst nach allen Gates, dann optional öffentliche Veröffentlichung.

**Keine Rückwärtsabkürzungen:** Der Teststand auf OrgaVela ist derzeit ein isoliertes **Alpha 0.1.0-alpha.2**, gebaut aus Source-Commit 4f2c4df (nicht aus GitHub-main). Die aktuellen PR-Branches sind weiterentwickelt und ungemergt. Ein grüner Alt-Commit oder ein Test auf der Alpha-Site ersetzt nicht die Abnahme des späteren V1-Kandidaten. **CI zuletzt auf PR #11 teils rot**: vollständige Neubewertung erforderlich.

## 4. Querschnittspakete G – vor, während und nach den Milestones

| ID | Paket & verbindliche Arbeit | Evidenz / Exit-Kriterium | Abhängigkeit |
| --- | --- | --- | --- |
| **G00** | Projektinventur; Scope/Non-Scope; PR-/Issue-Abhängigkeiten; ADR-Register; Rollen und Entscheidungen | Vollständiger Plan, aktueller Status, freigegebene Scope-Grenze, Links | sofort |
| **G01** | Isolierung der Testwebsite, Rollen, Zugriff, HTTPS, Suchmaschinen-/Robots-/Noindex-Schutz; kein echter Teilnehmertraffic, Consent-/Privacy-Hinweis | Nachweis nicht öffentlicher Teststrecke/kein Indexing, URL/DNS korrekt, Zugriffsfreigaben geprüft | vor externen Tests |
| **G02** | IONOS-Dateien + Datenbank sichern; Wiederherstellung **in isolierter Kopie** tatsächlich testen; Retention und Verantwortliche bestimmen | Backup-IDs/Zeit, Restore-Protokoll, Checksumme und RTO/RPO; keine öffentlichen Secrets | vor Upgrades / produktiven Daten |
| **G03** | SFTP-Berechtigung auf OrgaVela-Minimum reduzieren (aktuelles Konto kann weitere Verzeichnisse sehen); Hostkey, Least Privilege, GitHub Environment und Secret-Rotation | Zugriff nur auf OrgaVela /public; Negativtest andere Roots, Hostkey pinning, kein Secret in Logs | vor vollautomatischem Deploy |
| **G04** | WordPress-Scheduler / Action Scheduler: IONOS- oder kontrollierter Cron mit festem Intervall, Zeitüberwachung, Dead-letter/Retry und Überfälligkeitsalarm | DISABLE_WP_CRON erklärt und externer Runner belegt; Worker-Queue mit Testjob durchlaufen, keine dauerhaften Overdues | vor Gast-/Mail-/Retention-Journeys |
| **G05** | Mailtransport: Absender, DNS SPF/DKIM/DMARC, sichere SMTP-Konfiguration ohne neue Verträge, Testpostfach, Versand- und Empfangsbeleg, Bounce/Reconcile | echte Test-E-Mail an **autorisierte Testadresse**, nicht nur wp_mail(true), Einmallinks sicher, Wiederholungs-/Fehlerfall | G04; vor M6-10 extern |
| **G06** | Betrieb: Site Health, UOP-Schema-Inspector, Logsanitizing, Backup-/Restoretakte, Health/Queue/Security-Monitoring, Incident und Wartung | kontrollierter Alarmtest, Betriebs-Runbook, Ansprechpartner, reiner Synthetic-Data-Modus | G02–G05 |
| **G07** | Reproduzierbares Releasesystem: Artefakt aus source SHA, SHA-256/SBOM, signierte Freigabe, Versionierung, staged/atomarer Upload, Upgrade ohne Überschreiben, Rollback | Deploy- und Recovery-Probelauf mit gleicher Pluginversion, eingespielte DB-/Datei-Restoreprobe, in GitHub nachvollziehbar | vor Beta/RC |
| **G08** | Ein einziger autorisierter Workflow-Dispatch/PR-Gate-Weg, nachvollziehbare GitHub-Actions-Rechte und Einschränkungen; keine Umgehung von Tool-Sperren | echter berechtigter Start, verifizierbare Run-ID, branch- und workflowgebundene Rechte; sonst manuelle Freigabe | vor unattended Deploy |

**Ist:** G03-Zugangs- und SFTP-Erstinstallationsproben sowie Plugin-Aktivierung sind technisch erfolgreich; das SFTP-Konto ist aber zu weit berechtigt. **G02, G04, G05, G06, G07 und G08 noch nicht betriebsabgenommen.** Auf OrgaVela: WP-Cron laut Server deaktiviert, mehrere fällige Events; Suchmaschinenabschreckung aus, WordPress-Debuglog nicht aktiv. Das sind bewusste V1-Blocker, keine nachgewiesenen Plugin-Crashs.

## 5. Milestone 0 – Foundation (PR #1)

**Code-Stand:** historisch implementiert und CI grün; PR offen, noch kein V1-Merge. **Exit:** reproduzierbarer Bootstrap, alle Sicherheits-/Kompatibilitätsgates, CI-Gesamttests auf freizugebendem Commit.

| Paket | Verbleibende Abnahmeaufgabe |
| --- | --- |
| **M0-01 Bootstrap** | Plugin-Header, sichere Lade-Reihenfolge, Activation/Deactivation, Action-Scheduler-Bootstrap, keine Domain-Sideeffects im globalen Scope; echte IONOS-Alpha als Smoke, kein fatal. |
| **M0-02 Build** | Composer-/NPM-Lockfiles, reproduzierbares ZIP, Build-Provenienz/SBOM/Dependency-Notices, keine Secrets/Dev-Skripte im Release, Byte-/Hashvergleich. |
| **M0-03 Environment** | WP 6.9+, PHP 8.3+, MySQL 8.0+/MariaDB 10.11+, InnoDB/utf8mb4 mit Fail-closed auf inkompatibler Instanz und DB-Datenintegrität. |
| **M0-04 Container** | Modul-Registry, Service-Lifecycle, Dependency-Order, Doppelboots/Multisite, externe Plugin-Konflikte. |
| **M0-05 Codequalität** | PHPCS/PHPStan/JS-Lint, Secret-Scan, Dependency-Audit, erlaubte Lizenzen, Security Review. |
| **M0-06 CI-Harness** | Unit, MariaDB/MySQL/PHP-Matrix, WP-CLI-/Browser-Aktivierung, Artefakt-Smoke/HTTPS-E2E und stabile grüne Runs **auf integriertem SHA**. |

**Gate M0:** PR #1 reviewed, integriert, alle Checklisten und Zero-High-Security-Findings, keine überraschenden DB-/Filesystem-Änderungen bei inkompatibler Umgebung.

## 6. Milestone 1 – Datenmodell und Persistenz (PR #1)

**Code-Stand:** 28 feste V1-Grundtabellen und eine zusätzliche V2-Alterstabelle im Alpha-Build; gespeicherte Live-Migrationsoptionen 2/2, physische Tabellenprüfung **noch offen**.

| Paket | Verbleibende Abnahmeaufgabe |
| --- | --- |
| **M1-01 Manifest** | Normatives Schema (28 Grundtabellen plus V2 age_attestations gesondert), Indizes/Unique Keys/Collation/Schema-Drift exakt gegen Live-DB vergleichen. |
| **M1-02 Migration Runner** | Advisory Locks, idempotente Replays, Fortschrittsoptionen, Commit/Rollback und Unterbrechung; konkurrierende Aktivierungen. |
| **M1-03 Initial Migration** | Frische DB mit leerer WP-Instanz; Re-Run ohne Duplikate; negative Tests bei fehlenden Privilegien und abgebrochenem DDL. |
| **M1-04 IDs/Uhr** | UUID/Public-ID, UTC vs. Lokalzeit, DST, Correlation IDs, Datenbankpräfixe. |
| **M1-05 Transaktionen** | Deadlock, Reconnect, Lock Ownership, mehrere Manager, Fail-closed, Post-Commit-Callbacks. |
| **M1-06 Default Organization** | genau eine Default-Organisation; Organisation ist **tatsächliche** Daten- und Policy-Grenze, nicht nur Spalte. |
| **M1-07 Scoped Repositories** | alle tenant-owned Aufrufe mit OrgScope und sicheren Queries; keine ungeschützten Global Reads. |
| **M1-08 Schema/Recovery** | echten UOP Site-Health-Test „schema current“ inkl. Tabellen/Indizes/Version prüfen; Seed/Restore-/Upgrade-Reproduzierbarkeit. |

**Gate M1:** gespeicherte Migrationsversion **und** reales Schema stimmen überein; Wiederanlauf und Tenant-Isolation bestehen. Die vorliegende 2/2-Optionskontrolle reicht allein nicht.

## 7. Milestone 2 – Identität, Policy, Audit, Outbox (PR #2)

**Code-Stand:** im gestapelten PR technisch umgesetzt, Draft/Review offen. **Pakete M2-01 bis M2-10 müssen vor V1 jeweils auf integriertem Commit abgenommen werden.**

| Paket | Verbleibende Abnahmeaufgabe |
| --- | --- |
| **M2-01** | Account und Person unabhängig, explizites Link/Unlink; gleiche E-Mail bindet nie automatisch Personen; WP-Kontolöschung hält gesetzlich notwendige Historie. |
| **M2-02** | Relationship/Guardian und Delegation getrennt; Gültigkeit, sofortiger Widerruf, Event-/Org-Scope, keine Ableitung aus Verwandtschaftsbezeichnung. |
| **M2-03** | Actor Assignments/Rollen mit minimalen Capabilities, Ceiling und Audit; Eskalation verweigern. |
| **M2-04** | Berechtigungs-Matrix je Rolle und Operation, Default Deny, fehlende Rechte mit 403/404 ohne Leaks. |
| **M2-05** | Zentrale PolicyService-Objekt-Entscheidung auf echten aktuellen Daten, TOCTOU/Revoke im selben Flow. |
| **M2-06** | Field Policy/Projection in REST, Admin, Portal, CSV und Logs; vertrauliche Werte unterdrückt. |
| **M2-07** | Audit enthält notwendige Akteur-/Subjekt-/Korrelationsevidenz, jedoch keine Tokens/PII-Volldumps. |
| **M2-08** | Atomare Outbox, idempotenter Worker, Crash-/Retry-/Duplikatbehandlung, Abstimmung mit Cron G04. |
| **M2-09** | Öffentliche Hooks erst nach Commit, garantierte Events nur über durable Outbox, Extension-Vertrag. |
| **M2-10** | Security-/Multi-Org-/Cross-Channel-Regression mit echten WP-Actors inklusive Familienmail, abgelaufenen Rollen und Race Cases. |

**Gate M2:** keine direkte Schattenberechtigung in einzelnen UI/API/Job-Pfaden; abgenommene Regression, PR #2 nach PR #1 integriert.

## 8. Milestone 3 – Profile, Events, Conditions, Forms (PR #3)

**Code-Stand:** M3-01…M3-10 historisch implementiert, Draft, Dependency-Reviews ausstehend.

| Paket | Verbleibende Abnahmeaufgabe |
| --- | --- |
| **M3-01** | 11 Feldtypen/Privacy-Metadaten/Validatoren; Definition-Lebenszyklus. |
| **M3-02** | Typisierte Werte und Multi-Select, Cross-Org/Field-Hiding, Bulk-Performance und sichere Aktualisierung. |
| **M3-03** | gebundener Conditions-AST, Zyklen/fehlende Referenzen/Alter/Consent-Ausschlüsse, Serverseite autoritativ. |
| **M3-04** | CPT uop_event, Sichtbarkeitsregeln, Organisation, Kollision mit anderen Themes/Plugins. |
| **M3-05** | Manuelle Occurrences mit Zeitzone/DST/UTC, Verschiebung, Audit, unveränderten alten Snapshots. |
| **M3-06** | Editierbarer Draft mit Versionskonflikt, konsistentem Zustand nach Reload/Retry. |
| **M3-07** | Unveränderlich publizierte Formularversion mit gebundenen Consent-Versionen; keine rückwirkende Änderung. |
| **M3-08** | Form Builder: Tastatur, Touch, Fokus, Fehler, mobile Breite und verständliches Publishing. |
| **M3-09** | REST Read Models nur mit zentraler Projektion, keine fremden Privatfelder/draft Formulare. |
| **M3-10** | Reale CRUD-/Browser-/Error-/Accessibility-Fälle, Versionskonflikte/Idempotenz. |

**Gate M3:** kompletter Event-/Formular-Editor mit geprüfter publizierter Version, ohne unautorisierte Teilnehmerdaten; PR #3 nach PR #2 integriert.

## 9. Milestone 4 – Registration, Capacity, Waitlist (PR #4)

**Code-Stand:** M4-01…M4-09 als Service/Integrationscode vorhanden und historisch grün; Anwendung mit echtem Mail/Browser bleibt M6-10/G05.

| Paket | Verbleibende Abnahmeaufgabe |
| --- | --- |
| **M4-01** | anonyme/eigene/delegierte Anmeldung, gespeicherte Snapshot-/Versionsgarantien, atomare Audit/Outbox. |
| **M4-02** | 24h private Einmalmail/Token, Hash-only, generische Antworten, keine Plätze für ungeprüfte Gäste. |
| **M4-03** | State Machine inkl. Cancel/Review/Rejection, Fehlübergänge und Replay. |
| **M4-04** | Capacity Buckets und InnoDB-Sperren, 50+ parallele Anfragen ohne Überbuchung. |
| **M4-05** | FIFO Waitlist, 48h private Offers, Ablauf/Neuzuteilung ohne Doppelanspruch. |
| **M4-06** | Storno/Release und Nachrücken atomar, keine geheimen Angebote im Self-Service. |
| **M4-07** | Wiederholter Concurrency- und Failure-Injection-Test auf Zielmatrix. |
| **M4-08** | Event-Storno, Verschiebung, historisierte Form-/Termindaten, >100 Registrierungen im Cursor. |
| **M4-09** | Vollständige Service-Golden-Paths plus später M6-10 UI/SMTP/HTTPS-E2E. |

**Gate M4:** keine Überbuchung, kein ungeprüfter Platz, keine fremden Tokens, nachvollziehbare Statuskette; PR #4 nach PR #3 integriert.

## 10. Milestone 5 – Consent, Privacy, E-Mail, Export (PR #5)

**Code-Stand:** M5-01…M5-10 historisch implementiert, Review/Integration und reale Betriebsabnahme ausstehend.

| Paket | Verbleibende Abnahmeaufgabe |
| --- | --- |
| **M5-01** | dokumentversionierte Consent-Definitions, gebundene Hashes/Scopes und unveränderliche alte Inhalte. |
| **M5-02** | Actor ≠ Subject, Consent-Evidence statt Boolean, unabhängiger Widerruf, kein Race bei Registration. |
| **M5-03** | DE/EN-Mailvorlagen, erlaubte Tokens, HTML-/Text-escaping, sichere Admin-Vorschau. |
| **M5-04** | Private, unveränderliche Mailjobs; Singleton-Claim, Idempotenz, Error-Reconcile ohne zweideutigen Auto-Resend; G04/G05. |
| **M5-05** | WP-Privacy-Exporter/Eraser ohne E-Mail-Familienmatch, rechtmäßige historische Retention und verständliche Löschgrenzen. |
| **M5-06** | regelbare datensparsame Retention, kein frei erfundenes gesetzliches Zeitlimit, Legal Holds und Audit. |
| **M5-07** | private Exportjobs, Auth-Recheck, 24h-Expiry, sichere Speicherung/Download/Revocation. |
| **M5-08** | CSV mit Projektion, festem Spaltenvertrag, Formula-Injection-Schutz, Encoding/Record-Limits. |
| **M5-09** | Berechtigte Ops-/Export-/Retention-/Audit-Admin-Ansichten, keine Mailkörper/Empfänger/Secrets. |
| **M5-10** | Queue/Crash/Duplicate/Mail-/Disk-Error/Retention-Hold/Sicherheits-Regression. |

**Gate M5:** identische Consent-/Privacy-Wahrheit in REST/Portal/Admin/Mail, belegt bis zur tatsächlichen Auslieferung, nicht bloß Test-Outbox. PR #5 nach PR #4 integriert.

## 11. Milestone 6 – REST, Admin, Portal, Blocks, vollständige Journeys (PR #6)

**Code-Stand:** M6-01…M6-09 historisch implementiert. **M6-10 und seine Pakete bleiben nur teilweise fachlich/operativ abgenommen; Draft PR #6 ist offen.**

| Paket | Abnahme- und Restarbeiten |
| --- | --- |
| **M6-01 REST-Vertrag** | Schema, Permission Callback, DTO-Allowlist, Fehlercodes, CSRF/Nonce, Idempotenz, Abwärtskompatibilität. |
| **M6-02 People/Registration REST** | eigene/fremde/delegierte CRUD/Status-Cases, 401/403/404, stale/conflict, kein internes BIGINT. |
| **M6-03 Event/Form/Capacity/Consent/Audit/Export** | vollständiger minimaler REST-Vertrag; Gast-E-Mail und Wartelisten-Angebote separat opt-in, erst nach G04/G05; 24h/48h-Tokenablauf. |
| **M6-04 Admin People/Registrations** | Rollen, Listen/Details, Review/Reject/Cancel/Empty/Error/Loading, mobile Tabellen, Audit. |
| **M6-05 Admin Forms/Privacy/Health** | Safe Editor, Vorlagen, Mailstatus, export/privacy/retention/health, keine internen PII-Exports in UI. |
| **M6-06 Dynamic Blocks** | Event List/Details, Registration Form, Portal, My Registrations: SSR/Publicity, Block-JSON, Cache/SEO, Paketassets. |
| **M6-07 Self/Delegated Portal** | nur Live-Delegationen, Subject Switcher, Widerruf mitten in Sitzung, keine Cache-/Cross-User-Leaks. |
| **M6-08 Interactivity** | progressive öffentliche Filter/Conditions, JS-aus, private Daten nur serverseitig, sichere Error States. |
| **M6-09 Responsive/A11y** | 320px+, Keyboard/Screenreader, WCAG-2.2-AA-Check, Focus-Management und Formularfehler, reale mobile Geräte. |
| **M6-10 End-to-End** | letzte Gesamtprüfung **über einen einzigen Release-Kandidaten**; komplette Browser-/Mail-/Privatsphäre-/Admin-/Guardian-/Kapazitätsabläufe, G04/G05/G06 nötig; siehe unten. |

**M6-10: sechs operative Unterpakete des Gesamt-Abnahmeblocks** (P1–P3 wurden auf historischen Commits technisch bearbeitet; P4 Teil, P5–P6 noch offene Abnahmepakete):

- **M6-10/P1 CI-Stabilisierung:** alle Workflows reproduzierbar grün, Flakes ursächlich beheben statt Tests auszuschalten; aktuelle PR-#11-Fehler priorisieren.
- **M6-10/P2 Mobile und UX:** durchgängige Nutzeraufgaben auf 320/375/768/desktop, Keyboard, Fokus, verständliche Zustände, Testprotokoll.
- **M6-10/P3 Security und Negativfälle:** Expired Sessions, falsche Nonces, Replay, Cross-Org/Familien-E-Mail, Gast/Privatheit/Rate-Limits; last verified baseline gegen aktuellen HEAD wiederholen.
- **M6-10/P4 Alters-/Guardian-/Consent-Sonderfälle:** die vier Pakete P4-01…P4-04 aus Abschnitt 12 abnehmen, nicht wegen einer beliebigen Checkbox freigeben.
- **M6-10/P5 echte Betriebsjourneys:** OrgaVela-Staging: Adult Self, Manager, Guardian, Guest, verifizierte externe Test-E-Mail, Waitlist Offer/Accept/Expiry/Cancellation; Action Scheduler aktiv und nachweisbar, Testdaten separat, Mail-Opt-ins danach zurücksetzen.
- **M6-10/P6 finale integrierte UX-/REST-/Browser-Abnahme:** positive und negative Testmatrix auf exakt einem Git SHA, WordPress-UI und E2E-Beleg, Benutzerhandbuch, offene Defects bewertet, Produktverantwortlicher nimmt fachlich ab.

**Gate M6:** jeder gefrorene Screen/Endpoint/Block hat verifizierten Happy- und Denied-Path, alle Rollen-/Guardian- und Mailabläufe stimmen überein; PR #6 nach PR #5 integriert. **Nicht** schon abgeschlossen durch die grüne Test-Infrastruktur in disposable GitHub Actions.

## 12. Paket 4 – Minderjährige / Consent (PR #11, Issues #7–#10)

Dieses querschnittliche Paket ist im ursprünglichen M6-10-Fortschritt aufgekommen und als **eigene offene Issues** ausgegliedert. Es wird nicht als universelles „Eltern unterschreiben immer“-System verkauft; welche Entscheidung gesetzlich erforderlich ist, hängt von Zweck, Alter, Rechtsgrundlage und Organisation ab.

### P4-01 / Issue #7 – Alters-/Eligibility-Policy
- Ist: ADR 311–313, dreiwertige Fail-closed-Regeln, V2-Atteste mit begrenzter Gültigkeit, überprüfter Eventbezug und private Staff-APIs/UI teilweise vorhanden.
- Rest: Self-report ≠ überprüfter Status; Event-/Altersregeln und Nachweisarten präzisieren; externe prüfbare Arbeitsanweisung, Gültigkeit/Widerruf, Informations- und Retentionstexte, Status/Frontend verständlich, keine Datenmissbrauchslücke.
- Tests: unbekannt/deklarativ/verifiziert/abgelaufen/widerrufen, fremde Organisation/Einladungscode/gleiche E-Mail, Event unveröffentlicht, Browser und Migration; exakter HEAD CI grün, Reviewer bestätigt. PR #11 erst nach #6.

### P4-02 / Issue #8 – eigenständige und vertretende Consent-Entscheidungen
- Vor jedem Datenmodell-/State-Change ADR mit Migrations-/Upgrade-Konzept. Pro konkretem Consent-Zweck konfigurieren: selbst, Vertretung oder **beide unabhängig**, wenn erforderlich.
- Zwei getrennte Actors/Sessions, separate versionierte Evidence, unabhängige Widerrufe, rechtssichere Darstellung **ohne** pauschale Unterschriftsbehauptung; fehlender zweiter Actor erzeugt nicht „alle erteilt“.
- Tests: Self-only/Delegate-only/Both/Revoked/Expired/Shared-Email/Second-Guardian/Replay/Cross-Org, UI/Browser/Screenreader; Privacy/Audit/Export konsistent.

### P4-03 / Issue #9 – Guardian-Verifikation, Mehrfachvertretung, Abuse
- Definiere eine externe, tatsächlich ausführbare und möglichst datensparsame Überprüfung durch **unabhängige Prüfer**; der Einladungscode ist nie selbst ein Grant; Verlängerung/Ablauf/Sperre festlegen.
- Sichere Einladung und Mail, adminseitiger Prüfpfad mit expliziten Gründen, mehrere Sorge-/Vertretungspersonen, Konflikte, Rate Limits, Beleg-/Löschregeln, sofortige Widerrufe.
- Tests: Claim allein = keine Rechte; unabhängiger Review für genau einen Scope = begrenzte Rechte; Reviewer != Antragsteller, Brute-Force, fremde UUID, Mehrfachbeziehung, Entkoppelung.

### P4-04 / Issue #10 – rechtliche, mobile und Ende-zu-Ende-Golden-Paths
- Echte WP-Admin/Portal/REST/Browser-Journeys Erwachsene und Minderjährige, getrennte Entscheider, Revoke während Sitzung, 320px+, Screenreader, sichere E-Mail und Prüfqueue, Failure-/Timeout-/Replay-Cases.
- Betreiber oder geeignete Rechts-/Datenschutzprüfung legt zweckbezogene Einsatzgrenzen und Consent-Muster fest; keine pauschale Produktrechtsgarantie.
- Abnahme: alle relevanten HTTPS-/Browser-/DB- und CI-Läufe auf **identischem** Commit grün, keine Critical/High, dokumentierte Gegenzeichnung.

**Aktueller strenger Status:** alle vier Issues **offen**, PR #11 **Draft**; neuester PR-#11-HEAD 1b986587 hatte zwar 8/8 grüne Push-Fundamentchecks, aber zwei fehlerhafte PR-Läufe (Browserdeaktivierung auf PHP 8.5/MySQL 8.0, zwei HTTPS-/Chromium-Szenarien). P4 also ausdrücklich **nicht** abgenommen.

## 13. GitHub-Integration G20 – gestapelte PRs sauber auf main bringen

1. Vor jedem Merge: exakte Source-/Target-SHA, Diff/Review, offene Blocker/ADRs, Lizenz/CI prüfen; keine Änderungen an Dreamloud.
2. [PR #1](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/1) M0/M1 gegen main abnehmen; seit Paket-Baseline neuer main-Docsstand, Branch aktualisieren/rebasen und Integration-CI neu starten.
3. [PR #2](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/2) M2 aus Draft nach Sicherheitsreview und aktualisiertem Upstream; Base/Stack nach #1 neu setzen, Rechte-Projektionen kontrollieren.
4. [PR #3](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/3) M3 analog inklusive Form/REST-Integration.
5. [PR #4](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/4) M4 Kapazität und Concurrency bei verändertem Upstream erneut prüfen.
6. [PR #5](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/5) M5 Mail, Privacy, Consent, Export/RFC vollständig.
7. [PR #6](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/6) M6 erst nach M6-10-Freigabe und durchgängigem End-to-End-Test; Draft bis dahin.
8. [PR #11](https://github.com/janjrre/Projekt-Wordpress-Plugin/pull/11) P4-01 nach P4-Abnahme/CI und #6, nicht vorher; #8–#10 als gesonderte PRs/ADR-Änderungen ebenfalls sequenziert integrieren.
9. Auf integriertem main **neuen** deterministischen Build erzeugen, Versions- und Herkunftsnachweis aktualisieren. OrgaVela-Alpha nicht blind überschreiben; Migration von 0.1.0-alpha.2 nur mit Backup/Restore- und Upgrade-Probe. Nach jedem Merge eine Testmatrix mit Job-/Run-URLs dokumentieren.

**Merge-Regel:** PRs dürfen bei erfüllten Gates gemäß allgemeiner Projektfreigabe selbstständig gemergt werden. Entgegen der tatsächlichen Stacked-Abhängigkeiten oder bei roten Release-Checks kein Merge; keine Schutzprüfungen übergehen. Öffentliche Stable-Veröffentlichung bleibt gesonderter Schritt.

## 14. Milestone 7 – Release-Hardening, Qualität und Kompatibilität

| Paket | Arbeitsumfang und messbares Abnahmekriterium |
| --- | --- |
| **M7-01 Last/Performance** | Standardisierte 100-, 10.000- und 100.000-Personen-Fixtures und ggf. Millionen typisierte Werte; Phasen-2-P95-Zielbudgets für Event/Person/Registration/Capacity, Indizes/Queries/Memory gemessen und erklärt, keine vertraulichen Daten in Reports. |
| **M7-02 Security** | Vollständige BOLA/IDOR, Org/Field-Leak, CSRF, XSS, SQLi, Mass Assignment, SSRF, CSV Formula, Rate-Limit, Tokenreplay, Bearer-Leak, Rechte-Revoke, Workflow-/Supply-Chain-Risiken; Threat Model; kein ungelöstes Critical/High. |
| **M7-03 Migration/Upgrade/Recovery** | fresh 0→v2→V1, Alpha→Beta→RC, interrupted DDL/backfill, beschädigter Index, doppelte Aktivation, Site Restore, Queue-Replay; dokumentierter Recovery Runbook und verifizierter Dry Run auf separater DB. |
| **M7-04 Compatibility** | WP 6.9+, aktuell stabile WP-Version, PHP 8.3/8.4/8.5, MySQL 8.0/MariaDB 10.11, Multisite-Subsite-Smoke, Plugin-/Theme-Konflikte, Browserslist und shared-hosting-Timeouts. |
| **M7-05 Accessibility manuell** | WCAG 2.2 AA, reale Tastaturnavigation und mindestens ein Screenreader für Admin/Builder/Portal/Form/Error; Mobile 320px; Defects priorisiert/fixed/retested. |
| **M7-06 Privacy/Security/Developer Docs** | DSGVO-Dateninventar, Zwecke, Verantwortliche, Aufbewahrungs-/Hold-Vorgaben, Löschung/Export, Betreiberhinweise, REST/Hooks/Schema/ADRs, Install/Upgrade/Incident-Runbook, API-Beispiele, Admin-/Teilnehmerhandbuch. |
| **M7-07 WordPress.org-ready Paket** | LICENSE, readme.txt, Screenshots nur freigegebene Inhalte, Third-Party Notices, WordPress.org-Plugin-Checks, vollständige Quellen, kein Tracking, SBOM/Dependency-Audit, deterministischer Build, Testinstallation der **genauen** Release-ZIP. Einreichen nur nach gesonderter Freigabe. |
| **M7-08 Version-/Release-Gates** | Alpha → Beta (Feature Freeze) → RC (nur blockerfixes) → Stable V1; exakte geprüfte SHAs/Artefakthashes, signierter Abnahmebericht, Rollback-Plan, Veröffentlichung/Supportkanal nach Betreiberentscheidung. |

**Gate M7:** gesamter Scope, Abhängigkeiten, Integrität, manuelle Tests und Dokumentation auf demselben Kandidaten bestanden; öffentliche V1-Veröffentlichung danach möglich, nicht automatisch zwingend.

## 15. Abnahmeprogramm R – V1 darf erst dann abgeschlossen werden

- **R01 Feature-Freeze:** Must-Matrix vollständig, P4 zweckbezogen geklärt, Deferred-Liste dokumentiert; keine Features ohne ADR hinzugenommen.
- **R02 Integrations-Freeze:** PR-Stack zusammengeführt, Branch-Schutz/Required Checks auf main, keine roten CI, reproduzierbares Beta-Artefakt.
- **R03 Betriebsfreigabe:** isolierte OrgaVela-Installation, geprobtes Backup/Restore, least-privilege SFTP, HTTPS/Noindex oder freigegebene Veröffentlichung, realer Scheduler, Mail/Deliverability, Privacy, Monitoring.
- **R04 Fachabnahme:** vollständige positive und negative Nutzerabläufe, Acceptance Matrix sowie echte Betreiber-UI-Prüfung dokumentiert; keine unautorisierte Dateneinsicht.
- **R05 Nichtfunktionale Abnahme:** Security, Last, Migration/Recovery, unterstützte Matrix, WCAG-2.2-AA-Bericht, Legal/Privacy-Review.
- **R06 Release Candidate:** versioniertes/pinned ZIP aus main, SBOM, Changelog, Upgrade-/Uninstall-/Rollback-Tests, Kandidat mit exakter SHA für sämtliche Belege.
- **R07 Formale V1-Abnahme:** Betreiber bestätigt Abnahmeprotokoll mit Datum, Version, Git SHA, ZIP-Hash, Job-Links, bekannten akzeptierten niedrigen Findings, Betriebsverantwortung, externen Integrationen, No-Go-Liste und Rollback-Punkt. **Keine Abnahme bei offenem Critical/High oder unklarer Zustimmung/Vertretungsberechtigung.**
- **R08 Veröffentlichung:** separat genehmigte WordPress.org-Einreichung oder andere öffentliche Ausrollung; ansonsten V1 intern abgenommen und weiterhin private Testsite. Erste 24–72h Monitoring und Support-/Patchpfad.

**Evidence-Regel:** Jeder R-Punkt bekommt Datum, verantwortlichen Reviewer, konkrete Test-Run-ID/Issue/PR/Screenshot-Sanitization und Ergebnis in [V1_ACCEPTANCE_MATRIX.md](V1_ACCEPTANCE_MATRIX.md). „Möglich“ oder „Test geschrieben“ ist nicht „bestanden“.

## 16. Priorisierte Ausführung und operative Lieferwellen

| Welle | Priorität | Inhalt | Lieferobjekt / Stop bei |
| --- | --- | --- | --- |
| **W0 – Stabilisieren** | P0 | G00, G01, G02, G04; UOP-Site-Health-Schema; Security-/Index-/Cron-Inventur; CI #11 Failure-RCA | Staging-Readiness Bericht + Schema-Health + grüne Fix-CI |
| **W1 – M6-/P4-Lücken schließen** | P0 | M6-10/P1–P6, P4-01 #7, P4-02 #8, P4-03 #9, P4-04 #10; G05 SMTP | durchgängige synthetische Journeys + ADRs + UX-/Rechtsabnahme |
| **W2 – PR-Stack integrieren** | P0 | G20, Rebase/Reviews/Merge #1–#6/#11 plus #8/#9/#10 Implementierungen | main hat vollständigen, aktuell grünen V1-Scope |
| **W3 – M7 Last/Sicherheit/Betrieb** | P0 | G03/G06/G07/G08 und M7-01…M7-06; Upgrades/Restore | Security-/Perf-/A11y-/Recovery-Berichte ohne Critical/High |
| **W4 – Kandidaten & formale Abnahme** | P0 | M7-07/M7-08 und R01…R08; Beta, RC, Stable | unterschriebenes V1-Abnahmeprotokoll, exakt geprüfte Release-ZIP |

**Parallele Arbeit nur bei klarer Isolation:** CI-Stabilisierung und Operator-Dokumentation können parallel zur fachlichen P4-Implementierung laufen; Merge-/Migrations-/Release-Gates bleiben seriell. Stop bei gefährdeter Datenintegrität, Auth-Leak, unklarer Repräsentantenfreigabe, fehlgeschlagener Wiederherstellung, unerwarteten Kosten oder Zugriff auf fremde Websites.

## 17. Definition of Ready, Definition of Done und Change-Prozess

**Ready:** Anforderung und feste Quellenrangfolge klar; V1-MUST oder zulässiger Defekt; ADR-/Schema-Folgen geprüft; Repro/Fixtures/testbarer Negativpfad; verantwortlicher Reviewer und Stop-Gate; keine echten Daten/ungeklärten kostenpflichtigen Services.

**Done pro Paket:** Implementierung + vollständiges Testprotokoll inklusive Failure-Paths; OrgScope/Policy/Projection geprüft; Nebenwirkungen/Audit/Outbox/Retention betrachtet; API/Schema-/Version-Kompatibilität geprüft; A11y/Mobile und Datenschutzwirkung bewertet; sichere Dokumentation; aktueller HEAD mit CI; Review nach Bedarf; GitHub-Issue und [V1_STATUS.md](V1_STATUS.md) aktualisiert. Nur danach Status **ABGENOMMEN**.

**Änderungssteuerung:** Freeze-Änderungen ausschließlich mit ADR + Diff zu Phase 3, Privacy-/Migrations-/Rollbackanalyse, neuen Testfällen und explizitem Review. Zusätzliche Wünsche außerhalb V1 in einen separaten späteren Backlog. Priorität P0 (Release blockiert), P1 (vor Stable), P2 (nach V1) mit Begründung. Keine neue Meilenstein-Nummer ohne Spezifikationsbeschluss.

## 18. Feste Regel für alle folgenden Chat-Sitzungen

Für die nächste UOP-Antwort gilt in dieser Reihenfolge: 1. [AGENTS.md](../../AGENTS.md) + Masterplan + Status + jüngstes Work Log lesen; 2. GitHub/OrgaVela-Zustand an den betroffenen Punkten verifizieren; 3. konkreten Arbeitsauftrag tatsächlich abarbeiten, CI/Ergebnisse prüfen; 4. bei neuen Erkenntnissen Plan und Status **als Commit** aktualisieren, jeden Arbeitsauftrag append-only protokollieren; 5. **am Ende jeder Antwort** den aktuellen **V1-Fortschrittsblock** mit „V1 noch nicht abgenommen“, letztem überprüftem Commit/Run, Milestone-/P4-/PR-Kurzstatus, größten Blockern und **Links zu vollständigem Plan, aktueller Übersicht und nächstem Arbeitspaket** erneut anzeigen. Wenn nichts verändert wurde, explizit „keine belegte Statusänderung“ schreiben. Keine Hintergrundarbeit oder automatische GitHub-Änderung ohne tatsächlich laufende Chat-Ausführung behaupten.

**Abnahmekritischer Grundsatz:** Die V1 wird nicht durch Menge von Commits oder grünen Alt-Tests fertig; sie ist erst fertig, wenn die **definierten fachlichen, technischen und betrieblichen Voraussetzungen auf dem tatsächlich auszuliefernden Build belegt** sind.
