# M6-10 – End-to-End-Nutzerabläufe (Entwicklungsstand)

Stand: 10. Oktober 2026. Branch `feat/m6-admin-portal-rest`, Draft-PR #6. **Kein Merge, kein Produktivdeployment, keine öffentliche Aktivierung.**

## Bereits implementierter erster Teil

- Öffentliche Anmelde-Block-Vorschau wird nur dann durch ein tatsächliches Formular ersetzt, wenn das aktuelle veröffentlichte Formular lesbar und die erforderlichen Betriebsgates erfüllt sind.
- Gäste: ausschließlich aktives öffentliches Event, Pflicht zur Kontaktbestätigung, gültiges eingehendes HTTPS, explizite Freischaltung per `uop_guest_verification_enabled`, betriebsbereiter Action Scheduler. Standard bleibt aus.
- Anmeldung läuft über die vorhandene, strikt validierende REST-Route `POST /uop/v1/registrations/guest`, ohne Identitäts-/Token-Rückgabe. Die vorhandene 24-Stunden-Verifizierung bleibt ein separater Schritt.
- Angemeldete Mitglieder: Personenliste wird über `GET /uop/v1/me/portal` mit WordPress REST-Nonce geladen; `POST /uop/v1/registrations` wird anhand der echten aktuellen Rechte, Delegation und Anmeldungserverlogik neu autorisiert. Eine E-Mail-Adresse wird nie als Berechtigungsnachweis verwendet.
- Historisch gepinnte und integrity-geprüfte Einwilligungstexte werden vor Checkboxen angezeigt. Fehlende Texte oder private/alterssensitive clientseitige Bedingungen blockieren eine öffentliche Formulareinreichung, bis ein gesicherter serverseitiger Prozess umgesetzt ist.
- Veröffentlichung, Ausfüllfelder, bedingte Sichtbarkeit und Einwilligungen werden beim Absenden nochmals unverändert in `RegistrationService` geprüft; kein clientseitiger Shortcut für Kapazität, Warteliste oder State-Transitions.
- PHPUnit-Integrationstests nutzen echte WordPress-Datenbank, Form-Service und Gutenberg-Rendering für Sicherheitsgates und Fail-Closed-Bedingungen; M4/M6-REST-Tests bleiben im Testlauf aktiv.

## Noch zu ergänzen und freizugeben

| Strecke | Automatisierungsstand | Betriebsabnahme |
| --- | --- | --- |
| Gast: öffentliches Formular → REST-Anmeldung → Outbox → E-Mail → einmalige Verifizierung → Status | Block- und separate REST-/Mail-Integration vorhanden; browserübergreifender durchgehender Szenariotest offen | Ausstehend auf HTTPS-Staging mit echter E-Mail |
| Erwachsene: Event → Anmeldung → eigene Übersicht / Kapazitätsentscheidung | Formular und bestehende REST-/Policy-Tests vorhanden; zusammengesetzter Browserjourney offen | Ausstehend |
| Minderjährige: Sorgeberechtigte → Delegation → Widerruf → Anmeldung | Bestehende M4/M6-Policy-Tests vorhanden; vollständiger Journey offen | Ausstehend |
| Warteliste: letzter Platz → Offer → 48h Annahme/Ablauf/Storno | Bestehende M4/M6-Backendtests vorhanden; durchgehender Staging-Journey offen | Ausstehend |
| Admin: Eingang → Prüfung → Entscheidung → Audit → E-Mail | Separate M6-Admin-/M5-Mail-Tests vorhanden; durchgehender Browserjourney offen | Ausstehend |
| Angriffe: Tokenablauf/-replay, parallele Anfragen, falsche Einwilligung, fremde/entzogene Rechte | Bestehende Fachregressionen vorhanden; zusätzliche kombinierte Szenarien offen | Ausstehend |

## Abnahmebedingungen

1. Beide GitHub-Workflows (Pull Request und Push) müssen mit je acht grünen Jobs abschließen; PHP-Lint, statische Analyse, JS-Lint, WordPress-Integration, Datenbankmatrix, Browser-Smoke und Plugin-Paketierung müssen grün sein.
2. Separat HTTPS-Staging mit tatsächlicher Gast- und Wartelisten-Mailzustellung, gültigen Einmallinks und sichtbarer Statusrückmeldung prüfen; `wp_mail`-Erfolg allein reicht nicht aus.
3. Erst danach kontrolliert über MU-Plugin-Filter die Gast-/Wartelistenoptionen aktivieren. Kein Merge oder Produktivdeployment im Rahmen dieses Draft-Schrittes.
