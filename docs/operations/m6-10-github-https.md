# M6-10: temporäre GitHub-HTTPS- und E-Mail-Testumgebung (ohne zusätzlichen IONOS-Vertrag)

Stand: 10. Oktober 2026. Repository: `janjrre/Projekt-Wordpress-Plugin`, Draft-PR #6.

## Entscheidung

IONOS verlangt für eine separate, geeignete WordPress-Hostinginstallation einen zusätzlichen Vertrag. Dieser wird **nicht** abgeschlossen. Die bereits erstellte, bislang ungenutzte Subdomain `uop-test.dreamloud.de` wird **nicht** auf Dreamloud-Produktion umgeleitet und bleibt ungenutzt. UOP Core wird nicht in die produktive Dreamloud-WordPress-Installation geladen.

Stattdessen verwendet M6-10 die eigenständige GitHub-Actions-Datei `.github/workflows/uop-https-e2e.yml`. Diese erstellt eine **nur während des Jobs existierende** WordPress-Umgebung, mit MySQL, HTTPS-Proxy, automatisiertem Browser und isoliertem E-Mail-Testpostfach. Es gibt kein dauerndes Hosting, keine öffentliche Staging-URL und keine Änderung an Dreamloud-DNS oder -Daten.

## Technische Architektur

| Bestandteil | Implementierung |
| --- | --- |
| WordPress | Frische WordPress 6.9 Installation unter `$RUNNER_TEMP`, Plugin über Checkout-Symlink aktiviert |
| Datenbank | Einmaliger MySQL-8.0-Servicecontainer; zufälliger WP-Admin-Passwortwert im Workflow |
| TLS | Einmaliges selbstsigniertes OpenSSL-Zertifikat nur für Loopback `127.0.0.1` |
| HTTPS-Proxy | `tests/Fixtures/m610-https-proxy.mjs` leitet `https://127.0.0.1:8443` an lokalen PHP-Server weiter |
| Erkennung HTTPS | `tests/Fixtures/m610-https-router.php` akzeptiert `X-Forwarded-Proto: https` nur von Loopback |
| Test-E-Mail | Mailpit als Docker-Service; WordPress `phpmailer_init` an lokales SMTP-Port 1025 gebunden |
| E-Mail-API | Mailpit Inbox-Port 8025; Postfach wird nur im GitHub-Runner gelesen |
| Chromium | `tests/Browser/m610-https.spec.js` prüft HTTPS-Bestätigungsoberflächen, URL-Fragmententfernung und abgefangene WordPress-Test-E-Mail |
| Sicherheit | Keine externen Mailadressen, DNS/SFTP-Änderungen, IONOS-Zugänge, öffentlichen Webports oder Uploads aus GitHub |

Das HTTPS-Zertifikat wird ausschließlich im Test lokal als vertrauenswürdig behandelt (`ignoreHTTPSErrors`). Das ist **keine** Empfehlung für echte Staging- oder Produktivbrowser. Beim Ende des Jobs verschwinden Container und WordPress-Dateien.

## Verifikation und bekannte Grenzen

- Erster Lauf scheiterte nur an einer zu engen Browsertest-Annahme zur WordPress-Adresse `index.php?rest_route=...`. Korrektur in `df90d18`.
- Erfolgreicher HTTPS-/Mailpit-End-to-End-Infrastruktur-Job: https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38070326211
- Der Test prüft die öffentliche HTTPS-Bestätigungsseite und den WordPress-E-Mailtransport in ein isoliertes Postfach. **Noch nicht nachgewiesen** ist die vollständige fachliche Gastanmeldung einschließlich Formular-Einsendung, verifizierter Kontaktadresse, Kapazität, Warteliste, real empfangenem UOP-Einmallink und durchgehendem Browserstatus. Dafür folgen zusätzliche Fixture-/Browsertests.
- Echte externe SMTP-Zustellung auf einem beliebigen Mailprovider und DNS-/WAF-/Edge-Sicherheitsanforderungen einer späteren permanenten Staging-Instanz bleiben separate Themen.
- Das ist kein vollwertiger Ersatz für eine manuell über das Internet erreichbare Live-Testwebsite. Wenn eine solche später nötig wird, wird ein gesonderter Hosting-/Kostenentscheid getroffen.
- Actions-Minuten oder Docker-/CI-Usage können je nach Tarif und GitHub-Kontingent Kosten verursachen. **Keine IONOS-Hostingkosten hinzugefügt.**

## Betrieb

Änderungen an den workflow-relevanten Dateien starten den HTTPS-Workflow automatisch für einen PR. Nach Registrierung der Workflowdatei auf GitHubs Default-Branch kann der Workflow zusätzlich manuell ausgelöst werden. Der Workflow benötigt keine GitHub Secrets und hat ausschließlich `contents: read`-Berechtigung.

`docs/operations/ionos-staging.md` bleibt als **optionale zukünftige** Checkliste erhalten, ist aber derzeit nicht der beschlossene Einrichtungsweg. Insbesondere darf niemand eine Staging-Instanz auf gemeinsamem Dreamloud-PHP-Webspace aktivieren, nur um einen Zusatzvertrag zu vermeiden.


## 11. Oktober 2026 – tatsächlicher vollständiger Gast- und Wartelisten-Browserablauf

Die bisherige Infrastrukturprüfung wurde durch einen **echten Ende-zu-Ende-Nutzerablauf** erweitert:

- **Fixture:** `tests/Fixtures/m610-live-seed.php` erstellt ausschließlich auf dem einmaligen GitHub-CI-WordPress eine eigene öffentliche UOP-Veranstaltung, eine verbindlich veröffentlichte Formularversion und eine WordPress-Seite mit dem echten Gutenberg-Block `uop/registration-form`.
- **Browser:** `tests/Browser/m610-live-guest.spec.js` füllt und sendet das HTML-Formular über die bestehende REST-Route. Der Nutzer bekommt keine Tokenwerte aus der REST-Antwort.
- **Verarbeitung:** `tests/Fixtures/m610-live-helper.php` stößt ausschließlich die **registrierten echten** Outbox-/Mail-Worker über WordPress-Hooks an. Er erstellt keine Test-E-Mail oder Bestätigungs-Token durch Abkürzungen.
- **Bestätigung:** Der Browser liest die von UOP tatsächlich über WordPress `wp_mail()` erzeugte Mail aus dem per Loopback gebundenen Mailpit-Testpostfach, öffnet den privaten Einmallink und überprüft den bestätigten Status in der UOP-Datenbank.
- **Platzangebot:** Über die echten M4-Kapazitätsdienste wird der einzige Platz belegt, der Gast auf die Warteliste gesetzt, der Platz freigegeben und ein privates Wartelisten-Angebot erzeugt. Der Browser bestätigt dieses aus der tatsächlichen zweiten UOP-E-Mail. Abschließend wird genau ein akzeptierter Gastplatz in der Datenbank nachgewiesen.
- **Sicherheit:** Das CI-eigene MU-Plugin schaltet Gast-/Angebotsfilter nur unter `UOP_M610_CI=1` frei. Es ist nicht Bestandteil des regulären UOP-Pakets. Mailpit-SMTP- und API-Ports sind ausschließlich an `127.0.0.1` gebunden.

**Bei der Browserabnahme wurde ein echter Betriebsfehler entdeckt:** Die Composer-Abhängigkeit `woocommerce/action-scheduler` wurde vom bisherigen Plugin-Einstiegspunkt zwar autoloadbar bereitgestellt, aber ihre ausführende WordPress-Bootstrapdatei nicht eingebunden. Deshalb fehlte `as_enqueue_async_action()` in echten WordPress-Anfragen, sodass Gastanmeldungen bewusst im Vorschaumodus blieben. **Behoben im `uop-core.php`** durch das Einbinden der paketierten Action-Scheduler-Bootstrapdatei vor `plugins_loaded`.

**Nachweis:** https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38127084254 – **4/4 Chromium-HTTPS-Tests erfolgreich**, einschließlich realer WordPress-/Mailpit-/Gast-/Wartelisten-Nutzerreise.

Die bisherigen technischen Grenzen bleiben: Diese CI-Testumgebung ist nicht dauerhaft erreichbar; ein externer SMTP-Maildienst ist damit nicht unabhängig abgenommen, und produktives Dreamloud wurde **nicht** verändert. M6-10 verbleibt bis zum Abschluss aller relevanten Qualitäts- und Integrationsprüfungen sowie der übrigen manuellen Nutzeroberflächen als offener Meilenstein im Draft-PR. Kein Merge oder Deployment.
