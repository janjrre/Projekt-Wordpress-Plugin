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

## M6-10 kombinierte Integration und Browser-Sicherheitsprüfung

- `tests/Integration/M610EndToEndTest.php`: reale WordPress-Benutzer und Events, veröffentlichte Version, REST-Absendung, doppelte/abgewandelte Requests, fehlende Pflichtfelder, Eltern-/Kind-Familien-E-Mail ohne Rechtewirkung, explizite `registration_manage`-Delegation mit Widerruf, Administratorprüfung, Kapazitätsentscheidung und Audit.
- Dieselbe Suite testet Gäste mit standardmäßig gesperrter Route, gezieltem Test-Opt-in, echtem Outbox-Dispatcher, tokenisiertem Mail-Snapshot, einmaliger REST-Verifizierung, Kapazitätsengpass, Wartelistenplatz, Admin-Stornierung und separat gesichertem Gast-Platzangebot einschließlich wiederholter Annahme.
- `tests/Browser/m6-public-security.spec.js`: echtes Chromium/WordPress verweigert beide Bestätigungsseiten und alle drei sensitiven Gast-POST-Routen auf HTTP. Die Landing-Pages liefern ausdrücklich HTTP 403 statt einer generischen WordPress-Fehlerseite mit uneindeutigem Status.
- Bereits bestehend: M4-Tests für Ablauf, FIFO, Bucket-Transaktionssperren und Mehrprozess-Stress; M5-Tests für Consent-Evidence und E-Mail-Queue; M6-Tests für öffentliches Rendering, unveränderliche Felder und gefilterte Portal-/Admin-DTOs.
- CI-Regression der verbundenen WordPress-REST-Suite auf Commit `1d82444`: PR https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38066081729 (8/8), Push https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38066078920 (8/8). Der separate neue Browser-HTTPS-Gate-Test benötigt weiterhin das finale grüne CI-Urteil.

## Noch offene Grenzen

| Strecke | Automatisiert | Noch zu prüfen |
| --- | --- | --- |
| Erwachsene: Formular → REST → Status / Admin-Kapazität | Echte WP-REST-Integration grün | HTTPS-Browserreise im Staging |
| Minderjährige: Elternrecht → explizite Delegation → Widerruf | Echte WP-REST-Integration grün | Barrierefreie Browser-Bedienung einschließlich Rechtewechsel |
| Gäste: Formular → Outbox-Mail → Verifizierung | Echte WP-REST-Integration, Mail-Snapshot und Wiederholung grün | Tatsächliche SMTP-Zustellung und Browser-Linkaktivierung auf HTTPS |
| Warteliste: Platz 1 → Warteliste → Angebot → Annahme | Echte WP-REST-Integration plus separate Ablauf-/Konkurrenzfälle grün | Ende-zu-Ende über echte empfangene E-Mail auf HTTPS |
| Admin: prüfen → entscheiden → Audit | Echte WP-REST-Integration grün | Zusammenhängender Browser-Journey und E-Mail-Transportnachweis |
| Private Alters-/Profil-Bedingungen | Servervalidierung und bewusstes UI-Fail-Closed | Sicherer bedienbarer UX-Pfad ohne Veröffentlichung privater Profildaten |
| Öffentliche Sicherheit | Default-Closed, HTTPS-REST-Gates, unveränderliche Tokens, Retry, Rate-Guard | WAF/Edge-Abuse-Guard, Mailmonitoring, reale Missbrauchssimulation |

**M6-10 bleibt als gesamter Meilenstein offen, bis die Staging- und Browserabnahme explizit dokumentiert ist.** Insbesondere bestätigt ein WordPress-Test-Mail-Queue-Eintrag keinen tatsächlichen externen E-Mail-Empfang.

## Abnahmebedingungen

1. Beide GitHub-Workflows (Pull Request und Push) müssen mit je acht grünen Jobs abschließen; PHP-Lint, statische Analyse, JS-Lint, WordPress-Integration, Datenbankmatrix, Browser-Smoke und Plugin-Paketierung müssen grün sein.
2. Separat HTTPS-Staging mit tatsächlicher Gast- und Wartelisten-Mailzustellung, gültigen Einmallinks und sichtbarer Statusrückmeldung prüfen; `wp_mail`-Erfolg allein reicht nicht aus.
3. Erst danach kontrolliert über MU-Plugin-Filter die Gast-/Wartelistenoptionen aktivieren. Kein Merge oder Produktivdeployment im Rahmen dieses Draft-Schrittes.
