# UOP Core – verbindliche V1-Abnahmematrix und Prüfnachweise

**Version 1.0, 12.10.2026. Status: keine finale V1-Abnahme.**  
**Masterplan:** [V1_MASTER_PLAN.md](V1_MASTER_PLAN.md) · **Live-Fortschritt:** [V1_STATUS.md](V1_STATUS.md).  
Der jeweils verantwortliche Reviewer dokumentiert für jeden Test **Test-ID, exakte Plugin-/Git-SHA, Umgebung, synthetische Fixture-ID, Schritte/Eingaben, erwartetes und tatsächliches Ergebnis, CI-/Browser-Run-ID oder sanitisierten Screenshot, Defect-Issue, Datum und Freigabe**. Ein früherer grüner Lauf kann als Vorbeleg dienen, ist aber keine Abnahme einer neueren V1-Version.

## A. Verbindliche Abnahmestufen

| Gate | Ergebnis, das tatsächlich vorliegen muss | Wer bestätigt |
| --- | --- | --- |
| **A0 – Spezifikation** | Scope/ADRs, Rollen und V1-MUST-/OUT-Matrix unverändert oder genehmigte Change Requests | Produktverantwortlicher |
| **A1 – Code Review** | M0–M6 plus #7–#10 geprüft, in richtiger Reihenfolge in main, ausstehende PRs/Drafts beseitigt | Entwickler + unabhängiger Reviewer |
| **A2 – CI/Build** | alle Pflicht-Checks auf genau einem Git SHA grün, reproduzierbares Paket mit SHA-256/SBOM, kein Critical/High | Entwickler/Security |
| **A3 – Migration** | fresh-install, v1→v2→V1, Re-Install, DB-Schema, Interrupted-Run und Restore vollständig nachgewiesen | Entwicklung/Betrieb |
| **A4 – Fachliche E2E** | Adult, Guest, Guardian, Staff, Waitlist, Consent und Privacy vollständig mit echten REST/Browser-Strecken | Produkt/Fachteam |
| **A5 – Nichtfunktional** | Security, Last, WCAG, Mobile, Backups, Cron, Mail, Datenschutz und Monitoring bestanden | Security/Betreiber |
| **A6 – RC** | exakt signiertes/versioniertes ZIP getestet, kein offener Blocker; Changelog/Handbücher vollständig | Release-Verantwortliche |
| **A7 – V1 formale Abnahme** | unterschriebenes/erklärtes Abnahmeprotokoll und separate Veröffentlichungsentscheidung | Betreiber |

Jeder nachfolgende Fall besitzt zur Baseline den Status **OFFEN FÜR V1**. Historische erfolgreiche Teststrecken sind **verifizierte Vorarbeit** und dürfen nach erfolgreicher Aktualisierung für denselben Releasekandidaten angerechnet werden.

## B. Identität, Berechtigungen, Personen – S01 bis S09

| ID | Testablauf und Erwartung | Kanal/Prüftyp | Gate |
| --- | --- | --- | --- |
| **S01** | Zwei Konten besitzen identische Familien-E-Mail, aber getrennte Personen; weder Links noch Rechte/Exportdaten entstehen automatisch. | WP + REST + Portal + DB | A4/A5 |
| **S02** | Org A versucht per UUID/REST/Admin/CPT/Export auf Org B zuzugreifen; Antwort verborgen/403/404, kein Datenleck. | Multi-Org/Policy | A4/A5 |
| **S03** | Rolle „Viewer“ sieht ausschließlich freigegebene Felder, auch in CSV, Audit, Error Responses und Portal. | Field Projection | A4/A5 |
| **S04** | Delegation wird bei offener Browser-Session widerrufen: nächste REST-/UI-Aktion verweigert, kein Cache-Leak. | Portal/REST | A4/A5 |
| **S05** | Abgelaufene, inaktive oder falschem Event zugeordnete Delegation kann nicht gewährt/genutzt/replayed werden. | Policy/DB | A4 |
| **S06** | WordPress-Account-Entfernung entkoppelt Person, widerruft Zuordnungen, verliert nicht unnötig historische Registrierungsnachweise. | Account/Privacy | A3/A4 |
| **S07** | Staff kann sich nicht selbst Ceiling-Rechte verleihen; Reviewer=Antragsteller wird bei Guardian-Antrag verweigert. | Admin/REST | A4/A5 |
| **S08** | CRUD über alle Kanäle führt identische Org-/Objekt-/Feldberechtigungen aus; keine „Schattenberechtigung“. | Code Review + E2E | A1/A5 |
| **S09** | Abgelaufene Session/Nonce führt zu 401/403, private REST-Antworten enthalten keine Personendaten; Re-Login nötig. | Browser/HTTP | A4/A5 |

## C. Event/Form, Registration, Capacity – F01 bis F12

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **F01** | Staff erstellt Event mit korrekter org-/wp_post-Autorisierung, Start/Ende und Zeitzone inkl. DST-Grenze. | Admin/REST + DB | A4 |
| **F02** | Entwurfsformular versionieren und veröffentlichen; bereits publizierte Version/Snapshots bleiben unverändert. | Builder/DB | A4 |
| **F03** | Condition AST: Zyklus, nicht vorhandene Referenz, verbotene private Alterstatsache → serverseitig sicher abgelehnt. | Service/REST | A2/A4 |
| **F04** | Erwachsene Person veröffentlichtes Formular → Consent → REST → Annahme/Portal, genau ein Datensatz/Audit. | Chromium+REST | A4 |
| **F05** | Dasselbe Formular zweimal senden, stalen Client / Retry simulieren: idempotente oder explizit sichere Konfliktbehandlung, keine doppelte Kapazität. | E2E/Concurrency | A4/A5 |
| **F06** | Gast registriert → keine Platzvergabe vor E-Mailprüfung; private 24h Einmalmail, Einmallink nur über echte empfangene Mail, Wiederholung verweigert. | HTTPS + SMTP-Testpostfach | A4/A5 |
| **F07** | Volle Veranstaltung: zugelassener Gast auf FIFO-Warteliste; nach Admin-Storno entsteht genau ein 48h Angebot, private E-Mail, einmalige Annahme. | REST + DB + Mail + Browser | A4 |
| **F08** | 50+ parallele Registrierungen für letzten Platz: eine Bestätigung, keine Überbuchung/Double Offer; mehrere DB-/PHP-Konfigurationen. | Mehrprozess-Stress | A2/A5 |
| **F09** | Angebot abgelaufen/erneut angeklickt/fremder Token/falsche Organisation: denied, keine Platzvergabe und kein Bearer-Leak. | Security E2E | A4/A5 |
| **F10** | Event-Verschiebung/Abbruch auch bei 100+ Anmeldungen: Snapshot intakt, Claims/Offers atomar freigegeben, Audit konsistent. | Integration/Failure Injection | A3/A4 |
| **F11** | Abgemeldeter/abgelaufener/delegierter Actor versucht POST: endgültige Berechtigung serverseitig frisch geprüft. | REST Policy | A4/A5 |
| **F12** | Deaktivierter JS-Client, Mobile 320px, langsames Netzwerk: semantische Fehler-/Ladezustände und kein versehentliches Duplicate Submit. | Browser+Keyboard | A4/A5 |

## D. Minderjährige, Altersnachweise und Consent – P01 bis P12

| ID | Prüffall und Erwartung | Notwendiger Nachweis | Gate |
| --- | --- | --- | --- |
| **P01** | „Alter unbekannt“ erlaubt keine Eventregel, die einen verifizierten Altersstatus verlangt; weniger restriktive Policy funktioniert unabhängig weiter. | #7 Policy/REST/Browser | A4 |
| **P02** | Selbst angegebenes Alter ist **nicht** als verifiziert ausgewiesen oder für geprüfte Eligibility zugelassen. | UI/REST/Export | A4 |
| **P03** | Extern geprüfte Altersklasse ist Zweck, Org, Event und Schwelle zugeordnet, hat Status/Gültigkeit/Prüfer; keine unnötige Ausweiskopie. | #7 Audit + Datenschutz | A4/A5 |
| **P04** | Alter attestiert, danach Event privat, Attest abgelaufen oder widerrufen: alle positiven Eligibility-Auswertungen stoppen. | #7 cross-channel | A4 |
| **P05** | Guardian-Claim/Einladungscode/gleiche E-Mail ohne gesonderte Freigabe gewährt **null** Berechtigungen. | #9 Rechte-/Abuse-Test | A4/A5 |
| **P06** | Unabhängiger, dokumentiert berechtigter Reviewer akzeptiert eng befristeten Scope; Reviewer ist nicht Antragsteller. | #9 Admin-Browser + Audit | A4 |
| **P07** | Zwei Vertreter/Mehrfachvertretung, widersprüchliche Ansprüche, Ablauf und Widerruf: aktuelle Rechte konsistent und sofort neu abgeleitet. | #9 Integrationsmatrix | A4 |
| **P08** | Consent-Zweck mit ausschließlich Selbstentscheidung: separate Historie, Vertreter nicht automatisch erforderlich. | #8 ADR/Version/REST | A4 |
| **P09** | Consent-Zweck mit ausschließlich legitimer Vertretungsentscheidung: separate Actor-/Subject-Evidence, Zeitpunkt, Textversion. | #8 REST/DB/UI | A4 |
| **P10** | Consent-Zweck mit **zwei unabhängigen** erforderlichen Entscheidungen: erst nach beiden wird Status complete; ein Account/Session kann nicht beide vortäuschen. | #8 zwei Actor-Tests | A4/A5 |
| **P11** | Eigen- oder Vertreterentscheidung wird unabhängig widerrufen; aktueller Consent wird ungültig, historische Beweise bleiben unverändert. | #8 Revocation/Export | A4 |
| **P12** | Minderjährigen-Golden-Paths samt Alterskontrolle, Einladungen, Consent, Portal, Admin, Screenreader/mobile und zweckbezogener rechtlicher Dokumentation. | #10 vollständige Review-Signatur | A4/A5 |

**Rechtliche Grenze:** Technische Consent-Evidence, eine Staff-Checkbox oder ein Jugend-/Kind-Label ist keine amtliche Identitäts-, Alters- oder Sorgerechtsprüfung. Zweckbezogene zulässige Verfahren/Verantwortlichkeiten liegen in der Freigabe der Einsatzorganisation und müssen dokumentiert sein.

## E. Datenschutz, Kommunikation, Export, Arbeitersystem – D01 bis D10

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **D01** | Einwilligung exakt an immutable Dokument-/Formularversion mit getrennten Actor und Subject gebunden; keine zweite Wahrheit im Snapshot. | DB/REST/Audit | A4 |
| **D02** | Consent-Entzug stoppt nachfolgende Nutzung gemäß Policy, frühere Historie bleibt erforderlichenfalls als Nachweis. | Service/Privacy | A4 |
| **D03** | E-Mail-Vorlagen auf DE/EN, Variablen-escaping, niemals Bearer in Betreff/REST/Audit. | Template/Unit/E2E | A2/A4 |
| **D04** | Action Scheduler und Outbox nach Cron-Einrichtung: Queue tatsächlich abgearbeitet; ein fehlgeschlagener/ambiguous Versand nicht unkontrolliert doppelt gesendet. | IONOS Worker-/SMTP-Run | A5 |
| **D05** | WordPress-Privacy-Export/Eraser respektiert Person ≠ E-Mail, schützt fremde Familienkonten. | WP Datenschutz-Tools | A4/A5 |
| **D06** | Retention-Hold/Rechtspflicht/Lösch-Policy, Dry-Run und Cursor-Replay; kein irreversibler Datenverlust ohne Freigabe. | DB/Service/Betreiberreview | A5 |
| **D07** | Private Exportdateien nie weböffentlich; Scope-/Role-Recheck beim Erstellen, Verarbeiten und Download; 24h-Ablauf. | Pen/REST/Filepath | A5 |
| **D08** | CSV enthält nur berechtigte Felder, korrektes UTF-8; Formelpräfix-Schutz für =,+,-,@. | Export/Security | A5 |
| **D09** | Logs/Site Health/Audit zeigen minimale Diagnostik, keine Passwörter, private E-Mailkörper, Token oder andere PII. | Betrieb/Security | A5 |
| **D10** | Teilnehmer-, Export-, Mail- und Auth-Fehlpfade bieten verständliche Lade-/Fehler-/Recovery-UI und korrekte Retry/Queue-Status. | Browser/Operator | A4/A5 |

## F. Migration, Betrieb, Sicherheit, Performance – B01 bis B12

| ID | Prüffall und Erwartung | Testart | Gate |
| --- | --- | --- | --- |
| **B01** | Frische WP-Installation inkl. MySQL/MariaDB, neuestes UOP-ZIP aktiviert; genau erwartete Tabellen/Indizes/Org, keine Fehler. | Install/Schema Health | A3 |
| **B02** | Bereits installierte Alpha mit schema 2 auf Kandidat upgraden, kompatibel und ohne Datenverlust, neuer Source/Hash exakt geprüft. | isolierter Upgrade-Klon | A3 |
| **B03** | Unterbrochene Migration, Lock-Takeover, Deadlock, defekter Index: fail-closed, protokolliert, Recovery erfolgreich. | Failure Injection | A3/A5 |
| **B04** | Datenbank-/Datei-Backup wird auf isolierter Umgebung real wiederhergestellt, übereinstimmende Datenversion und Funktion. | IONOS Restore-Protokoll | A3/A5 |
| **B05** | WP-Cron deaktiviert? Belegter externer Cron-Runner mit Fälligkeits-/Überfälligkeitsprobe und Alarm. | IONOS Cron/Scheduler | A5 |
| **B06** | Externe E-Mail-Zustellung in autorisiertes Testpostfach, SPF/DKIM-/Sender-/Bounce-Regeln; keine produktiven Testpersonen. | synthetischer Mailbetrieb | A5 |
| **B07** | GitHub-SFTP/SSH Least Privilege; kein Zugang zu Dreamloud/anderen Projekten; Secret-/Hostkey-/Checksum-Guards bestehen. | Scope-Negativtest/Review | A5 |
| **B08** | Site-URL HTTPS und erforderlicher Testzugriffsschutz/Noindex vorhanden, vor Freigabe eigener Publikationsentscheid. | Public Surface/Robots | A5 |
| **B09** | PHP 8.3/8.4/8.5 x MySQL 8.0/MariaDB 10.11; unterstützte WP-Versionen und Browser-Testmatrix vollständig aktuell grün. | CI-Matrix | A2/A5 |
| **B10** | Lastfixtures 100, 10.000, 100.000 Personen inkl. Zeit-/Querybudgets und aktiver Indizes; keine N+1-Ausreißer. | M7 Performance Report | A5 |
| **B11** | Manuelle WCAG 2.2 AA/Keyboard/Screenreader/320px für alle fünf Blocks, Admin, Builder, Portal, Consent-UI. | A11y-Protokoll mit Findings | A5 |
| **B12** | Sicherheits-Angreifermatrix BOLA/IDOR, CSRF, XSS, SQLi, CSV, Token, Replay, Privilege, Supply Chain; kein offenes Critical/High. | M7 Security Report | A5 |

## G. Release und Verantwortung – R01 bis R08

| ID | Ergebnis | Muss vorliegen |
| --- | --- | --- |
| **R01** | Feature Freeze | sämtliche V1-MUST-Funktionen, Versionen, Scope-/Deferred-Issues, ADRs |
| **R02** | Integrations-Freeze | keine gestapelten PRs offen, main grün, Review-Gates |
| **R03** | Betreiberbereitschaft | Backup/Restore, Cron, Mail, Datenschutz, Monitoring, SFTP |
| **R04** | Fachliche Akzeptanz | alle relevanten S/F/P/D-Fälle bestanden |
| **R05** | Nichtfunktionale Akzeptanz | B01–B12 bestanden, keine High/Critical |
| **R06** | RC-Paket | ZIP-SHA, Version, SBOM, Changelog, Upgrade/Uninstall |
| **R07** | Formale V1-Abnahme | Betreiberfreigabe mit Version, Commit, Run-Links, Restrisiken |
| **R08** | Separate Veröffentlichung | öffentliche Ausrollung explizit autorisiert, Monitoring/Support |

## H. Vorbelege vom 12.10.2026 – NICHT finales Release-Gate

- **OrgaVela SFTP Probe:** [38239184433](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38239184433) – erfolgreich, /public.
- **OrgaVela Paketupload:** [38241807035](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38241807035) – erfolgreich, ursprüngliches Alpha-2-Paket. Aktivierung und WP-Migrationsoptionen 2/2 separat bestätigt, SQL-Health noch nicht.
- **Quelle des Alpha-Builds:** SHA 4f2c4df800caf1e5708f05cce9536a7afb34aaa0; historisch [Foundation 38225097092](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097092) und [HTTPS E2E 38225097051](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38225097051) grün.
- **Letzter PR-#11-HEAD:** 1b98658704e1b4e8fb5350d7817e20af4fdecc35; Push Foundation [38233559467](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233559467) 8/8, PR Foundation [38233565475](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565475) 7/8, PR HTTPS [38233565383](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38233565383) rot. Das ist aktuell **kein gültiges A2-Gate**.
- **Achtung:** E2E mit lokalen Mailpit-Mails beweist die Funktion der isolierten CI-Transportstrecke, **nicht** Zustellung über einen echten externen Mailprovider.
- **Aktivierung:** zwei UOP-Optionswerte 2/2 beweisen **keine** Prüfung jedes realen DB-Index. Noindex, Cron, Backup, Mail und zweckbezogener Minderjährigen-Nachweis bleiben offen.

## I. Ablage des finalen Abnahmeprotokolls

Erst beim tatsächlichen RC wird eine Datei **V1_RELEASE_SIGNOFF.md** angelegt, mit: freizugebender Version/Plugin-ZIP-SHA256/Source-SHA, Ziel-WP-/PHP-/DB-Matrix, Testfälle/Sicherheitsbefunde, manuelle Accessibility-/Datenschutzfreigaben, Protokoll eines getesteten DB-Restores, Ergebnis aller R01–R07-Pakete, verantwortliche bestätigende Person und Datum. Vorher nicht als „signiert“ simulieren.

**Stop-Bedingung:** Beim ersten Critical/High ohne akzeptable, dokumentierte und geprüfte Lösung kein Stable-/öffentlicher Go-Live, selbst wenn ein Workflow grün ist.
