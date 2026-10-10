# M6-03 — Gast-E-Mail-Verifizierung (sicherer Freigabepfad)

Status: Implementiert im M6-Draft-PR, **nicht aktiviert, gemergt oder deployed**.

## Ablauf

1. Die Organisation hat ein aktives, öffentliches, nicht passwortgeschütztes Event mit veröffentlichtem Anmeldeformular und verpflichtender E-Mail-Verifizierung.
2. Der Betreiber stellt HTTPS (auch für tatsächliche eingehende REST-Anfragen über korrekt konfigurierten Reverse Proxy) und Action Scheduler mit funktionierendem `wp_mail`-Transport sicher. Die öffentliche Gastannahme muss ausdrücklich freigeschaltet werden.
3. Anonyme Clients senden strikt validiertes JSON an `POST /wp-json/uop/v1/registrations/guest`: `event_id` (UUID), `command_id` (UUID), `fields` (veröffentlichte Feldwerte), optional `occurrence_id`. Die Antwort ist ausschließlich `202 {"status":"received"}`; weder Registrierungs-ID noch Bearer-Token werden übertragen.
4. Der M4-Domänendienst schreibt eine neue, nicht verknüpfte Person und die Anmeldung atomar mit einem geheimnisfreien `registration.email_verification_required`-Outbox-Ereignis. Ein wiederholter `command_id`-Aufruf erzeugt keine zweite Anmeldung.
5. Der bestehende Outbox-Consumer löst nach dem Commit über `uop_scoped_domain_event` mit dem vertrauenswürdigen `OrgScope` (zusätzlich zum rückwärtskompatiblen allgemeinen Hook) den privaten M6-Versand aus: Token `random_bytes(32)`, SHA-256-Hash in `registrations`, Gültigkeit 24 Stunden. Der gerenderte Nachrichtentext wird als organisationsgebundene, idempotente M5-Mail-Nachricht in `email_messages` gespeichert und erst nach Commit über Action Scheduler/`wp_mail` versendet. Ein erneuter Empfang desselben Events erzeugt keine zweite E-Mail. Eine verpasste Versandplanung wird vom M5-Mail-Sweeper erneut für **queued**-Nachrichten aufgegriffen.
6. Der Link verwendet ausschließlich eine HTTPS-URL mit `#registration_id=…&token=…` im Browser-Fragment. Das Fragment wird bei Aufruf clientseitig sofort aus der Adresszeile entfernt; es erscheint nicht im ursprünglichen HTTP-GET. Die Seite zeigt zunächst nur eine Schaltfläche; erst deren expliziter Klick sendet einen `POST /wp-json/uop/v1/registration-verifications` mit `referrerPolicy:no-referrer`.
7. Der M4-Dienst konsumiert das gehashte Token unter Datenbanksperre nur einmal und speichert `email_verified_at`. Er verknüpft **kein** WordPress-Konto und erzeugt keine zusätzlichen Rollen/Berechtigungen. Bereits benutzte, abgelaufene oder ungültige Links erhalten dieselbe generische `202`-Antwort und keine Identitätsdaten.

## Freigabe

Standardmäßig liefert die öffentliche Gast-POST-Route **503**. Beide öffentlichen POST-Routen verweigern unverschlüsselte HTTP-Anfragen mit **503**, auch wenn die konfigurierte WordPress-Website-URL HTTPS verwendet. Erst nach Transport- und HTTPS-Abnahme einen kontrollierten MU-Plugin-Filter hinzufügen:

```php
add_filter( 'uop_guest_verification_enabled', '__return_true' );
```

Das darf ausschließlich eine Administratorin oder ein Administrator einer getesteten WordPress-Umgebung aktivieren. Vorher mindestens Domain/DNS, gültiges HTTPS-Zertifikat, Absenderkonfiguration/SPF/DKIM/DMARC, asynchrone Action-Scheduler-Verarbeitung und tatsächlichen Empfang einschließlich Spamfilter an einer Testadresse prüfen. `wp_mail`-Status `accepted` bedeutet nur Transportannahme, nicht bestätigte Zustellung. Bei Ausfall kann ein berechtigter Administrator eine **failed**-Mail über die vorhandene M5-Reparaturfunktion erneut zustellen; **sending** ist absichtlich nicht automatisch retrybar.

## Sicherheitsgrenzen und bewusst offene Punkte

- Clientseitig dokumentierter Captcha-/Missbrauchsschutz fehlt bislang; der REST-Controller begrenzt neue Versuche zusätzlich mit WordPress-Transients pro beobachteter IP und Event (maximal sechs pro 15 Minuten). Dies ist ein *sekundärer*, nicht verteilter oder atomarer Abuse-Guard. Für öffentliches Internet sind ergänzende Edge-/WAF-Ratenbegrenzung und Mailmonitoring empfohlen.

- Die Organisation wird **nie** aus einer Gast-Eingabe oder einem Event-UUID-Raten abgeleitet. Nur der vom Outbox-Dispatcher übergebene `OrgScope` darf die E-Mail-Queue ansprechen. Ein fremder Organisations-Scope führt zu keinem Versand.
- E-Mail-Aktionslinks sind im **privaten** eingefrorenen M5-Nachrichtenkörper bis zur bestehenden Retention gespeichert. Outbox, Action-Scheduler-Argumente, REST und Audit bleiben tokenfrei. Mail-Datenbanktabellen und Backups gehören zu den besonders vertraulichen Daten und müssen gemäß Datenschutz-/Löschkonzept geschützt und aufbewahrungsbegrenzt werden.
- Die M6-08-Gutenberg-Formularvorschau ist weiter **nicht absendbar**. Ein vollständiger barrierefreier Gastformular-Submit, erforderliche Einwilligungstexte und die End-to-End-Browserstrecke bleiben Teil von M6-10.
- **Wartelisten-Platzangebotslinks** sind eine gesonderte Funktion; deren Versand/Akzeptanz wird durch diese Gast-E-Mail-Verifizierung weder geöffnet noch als fertig markiert.
- Kein Merge und kein Produktionsdeployment durch diesen Arbeitsschritt.

## Automatisierte Regression

`tests/Integration/M6BlocksTest.php` prüft den anonymen REST-Receipt ohne Identifikatoren, private mailweise Idempotenz, vertrauliches Hashing, geheimnisfreien Outbox-Verlauf, echte `wp_mail`-Worker-Einmalzustellung, erfolgreiche und wiederholte Bestätigung sowie Passwortschutz. M4 sichert Token-Ablauf, Sitzplatzsperre und Status-/Zuständigkeitsgrenzen ab.
