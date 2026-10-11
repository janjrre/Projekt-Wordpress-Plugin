# M6-10 Paket 4 – Minderjährige, Delegationen und Einwilligungen

Stand: 11. Oktober 2026. UOP Core, Draft-PR #6. Keine Produktions- oder Dreamloud-Änderungen.

## Technische Sicherheitsgrenzen

1. **Person ist nicht WordPress-Account.** Identische Kontaktadressen oder Namen führen niemals automatisch zu einem Link, einer Familienbeziehung oder einer Autorisierung.
2. **Relationship ist nicht Delegation.** `guardian_of` / `representative_of` dokumentiert einen fachlichen Bezug. Erst ein durch `DelegationService` administrativ gewährtes, ausdrücklich zweckgebundenes `registration_manage` erlaubt bestimmte Aktionen. Die Hinterlegung einer Beziehung ist kein behördlicher Nachweis der Sorge- oder Vertretungsberechtigung.
3. **Verknüpfte Nachweise gelten nur solange sie gültig sind.** `DelegationRepository::allows()` und `for_actor()` prüfen bei jedem Abruf, ob der Betroffene aktiv ist und bei einer mit `relationship_id` verknüpften Delegation die Beziehung denselben Mandanten und dieselbe Person betrifft, aktiv und zeitlich gültig ist, sowie das vertretende WordPress-Konto weiterhin mit dem ausgehenden Beziehungspartner verknüpft ist. Entfernen, Ablaufen oder Entkoppeln sperrt Folgezugriffe ohne eine Änderung früherer Consent-Records.
4. **Unverknüpfte Manager-Delegationen sind Alt-/Sonderfall.** Für diese kann technisch keine valide Sorgebeziehung abgeleitet werden. Die bestehende ausdrückliche Adminfreigabe bleibt kompatibel; eine technische Delegation darf rechtlich nicht als verifizierte Sorgeberechtigung bezeichnet werden. Eine verpflichtende geprüfte Relationship für Minderjährigenaktionen bedarf einer eigenen durchgesetzten Policy.
5. **Einwilligung bleibt explizit und historisch:** Formulare pinnen veröffentlichte Consent-Versionen; die Registrierung schreibt Entscheidung und Snapshot transaktional. `consent_records` unterscheidet `subject_person_id`, `actor_user_id`, `auth_context` (`self`, `delegate`, `manager`, `guest`) und `decided_at`. Rücknahmen erzeugen einen neuen Record; historische Zustimmungen bleiben bestehen. Eine Delegation darf nach Aufhebung nicht weiter entscheiden oder für die Person zurücknehmen.
6. **Guest-Verifizierung ist keine Identitätsprüfung:** E-Mail-Kontrolle, gemeinsame Familienadresse oder Tokenbesitz belegen weder Alter noch Sorgeberechtigung. Die Gastroute erteilt keine Delegationsrechte.

## Alters-/Einwilligungsentscheidung: getrennte fachliche Arbeit

Das Core-V1-Personenmodell enthält aktuell **kein verpflichtend erhobenes oder amtlich geprüftes Geburtsdatum** und keine beweiskräftige, organisationsspezifisch geprüfte Sorgebefugnis. Das ist ein bewusst sichtbares Release-Gate, kein Grund für heimliche Autogrants. Eine sichere künftige Alters-/Guardian-Oberfläche muss zwischen folgenden Zuständen unterscheiden:

| Fachlicher Zustand | Konsequenz |
| --- | --- |
| Alter unbekannt/nicht geprüft | Keine automatische Minderjährigen- oder Erwachsenenfreigabe; erforderliche Vertretungsnachweise werden manuell geprüft |
| Geburtsdatum selbst angegeben | Nur Selbstauskunft, keine Identitäts- oder Vertretungsprüfung; Zweckbindung und Datensparsamkeit |
| Alter nachvollziehbar geprüft | Nur die zur konkreten Veranstaltung geltende Altersregel anwenden; keinen globalen universellen Grenzwert annehmen |
| Guardian/Representative behauptet | Kein delegierter Zugriff aus der Behauptung oder E-Mail-Adresse |
| Guardian-/Vertretung geprüft und Adminfreigabe dokumentiert | Nur ausdrücklich genehmigte Objekt-/Eventrechte erteilen; bei Ablauf/Widerruf fail closed |
| Teilnehmer gibt eigene Zustimmung | Eigene Identität und Entscheidung getrennt von einer stellvertretenden Zustimmung erfassen; je nach Einwilligungstyp und wirksamer Regel |

Vor einem als **rechtssicher** beworbenen Go-live sind die konkreten Rechtsgrundlagen, Arten der Zustimmung (insbesondere Datenschutz vs. Bild-/Textnutzung), etwaige Einwilligungsfähigkeit, Vertretungsumfang, Nachweisverfahren, Speicherfristen und Löschkonzepte für die Einsatzorganisation rechtlich zu prüfen. Die technische Implementierung trifft keine pauschale Aussage, dass alle Minderjährigen ab einem bestimmten Alter selbst oder nur durch Sorgeberechtigte wirksam einwilligen könnten.

## Automatische Regressionen

- `tests/Integration/M2IdentityTest.php`: geteilte E-Mail und reine Beziehung reichen nicht; abgelaufene/noch nicht gültige/widerrufene Beziehungen, Account-Unlink, Regrant trotz Verfalls – jeweils keine Berechtigung
- `tests/Integration/M5ConsentRecordsTest.php`: bei abgelaufener Beziehung weder Registrierung noch Consent-Evidence/Outbox; nach explizit gültiger Delegation prüfbar getrennter Actor/Subject/Context; nach Widerruf keine Consent-Rücknahme und keine historische Umschreibung
- `tests/Integration/M610EndToEndTest.php`: echter WordPress-REST-GET, Cancel und Portal-Zugriff verlieren die abhängige Person unmittelbar nach Beziehungswiderruf; bestehende explizite Delegation bleibt getrennt und widerrufbar

Diese Prüfungen verwenden nur disposable WordPress-Daten, keine realen Familienbeziehungen oder Kontaktadressen.

## Noch offene Phase-4-Abnahme vor voller Freigabe

- Bedarfsorientierte, datensparsame Altersabfrage mit konfigurierbarer Event-/Organisationspolicy und Reviewstatus, ohne automatische Rechtseinräumung
- Prüffähiger Antrag auf Guardian-Freigabe, administrative Entscheidung und eng zweckgebundene, ggf. zeitlich befristete Delegation mit belastbarem Freigabenachweis
- UI für unterschiedliche Entscheidende, bei Bedarf getrennte eigene und gesetzlich vertretende Einwilligung sowie klare Widerrufhinweise
- Browserabnahme dieses Antrags-/Review-Ablaufs mit abgewiesenen Behauptungen, abgelaufenen Nachweisen, mehreren Sorgepersonen und nachträglichem Widerruf
- Rechtliche Prüfung des tatsächlichen Anwendungsfalls und Datenschutzhinweise

**Release-Status:** Technische Härtung von bestehenden Delegations- und Consent-Grenzen ist nicht gleich vollständiger Abschluss von Paket 4. PR bleibt Draft; nichts mergen/deployen.

## 11. Oktober 2026 – Implementierter Guardian-Antrag und administrative Prüfung

Der erste **operative** Claim-/Review-Pfad ist auf dem ungemergten M6-Branch implementiert, ohne eine Änderung des eingefrorenen 28-Tabellen-Manifests:

- Mitgliederportal: Ein angemeldetes Konto kann den privaten **Personen-Einladungscode (UUID)**, den die Organisation oder die betroffene Person außerhalb des Portals bereitstellt, eingeben und eine Prüfung beantragen. Die Oberfläche nutzt den vorhandenen WordPress-REST-Nonce und gibt für unbekannte ebenso wie für bekannte Personen den generischen Status „received“ aus. Sie fordert kein Geburtsdatum oder Personalausweisdokument an.
- `POST /uop/v1/me/guardian-claims` akzeptiert ausschließlich `{"subject_id":"<UUID>"}` und liefert nach Authentifizierung einen **HTTP-202-Receipt**, nie ein Delegationsrecht. Die serverseitig bereits mit dem Account verknüpfte eigene Person ist Antragsteller; eine gleiche E-Mail gibt keine fremde Person frei. Ein accountbezogener Transient reduziert wiederholte Anträge (maximal drei Versuche pro Tag; kein global atomarer Spam-Schutz).
- `RelationshipRepository::request_guardian` speichert `guardian_of` ausdrücklich als `pending`. Bestehende `active`-/`rejected`-Beziehungen können nicht durch eine erneute Behauptung reaktiviert werden. Der Unique Edge und die transaktionale Ereignis-/Auditprotokollierung bleiben wirksam.
- `GET /uop/v1/admin/guardian-claims` ist ausschließlich nach `delegation.manage`-Policy und mit begrenzter Liste nutzbar. Es werden nur Claim-ID, anonymitätsarm erforderliche Personennamen und UTC-Eingangsdatum, keine E-Mails und keine Prüfunterlagen angezeigt.
- `POST /uop/v1/admin/guardian-claims/<uuid>/review` akzeptiert ausschließlich `decision`, `evidence_checked`, `event_post_id`. Für eine Genehmigung ist die ausdrückliche **Bestätigung eines außerhalb des Portals durchgeführten Vertretungsnachweises** erforderlich. Der prüfende WordPress-Account darf **nicht** zugleich Antragsteller sein.
- Genehmigungen werden in **einer DB-Transaktion** auf den gerade geprüften Claim bezogen: `pending → active` und **nur `registration_manage` für genau eine veröffentlichte Veranstaltung**, mit `valid_to` **90 Tage nach Prüfdatum**. Eine abgelehnte Anfrage wird `rejected` ohne Grant. Ungültige oder entkoppelte Antragsteller dürfen weiterhin abgelehnt, jedoch nicht genehmigt werden. Replay oder parallele Prüfungen bleiben konflikthaft.
- WordPress-Admin: `Guardian requests` besitzt ein separat berechtigtes Menü, eine nonce-geschützte Entscheidungsschaltfläche, klaren Hinweis auf notwendige Nachweisprüfung, Event-Auswahl, eindeutige Genehmigung/Ablehnung und barrierearme Bedienelemente. Die Control-Center-Navigation verlinkt den Bereich.
- `tests/Integration/M610GuardianReviewTest.php` prüft echte REST-Endpunkte samt unbekanntem UUID, gemeinsamem Kontakt, fehlender Prüferberechtigung, Mass Assignment, fehlendem Nachweis, falschem Event, 90-Tage-Gültigkeit, fremdem Event, Replay, Widerruf sowie dem Fall eines zwischenzeitlich entkoppelten Kontos. `tests/Browser/m610-responsive-a11y.spec.js` prüft die Oberfläche mit einem anonymen (aus Sicht des Child-Identifiers nicht auflösbaren) synthetischen Code und die Trennung zwischen Mitglied und Administrator.

**Abnahmegrenze:** Eine bloße Reviewer-Checkbox ist keine automatische oder unabhängige Verifikation einer gesetzlichen Sorgeberechtigung. Bevor eine Organisation rechtssicher damit arbeiten kann, braucht sie einen festgelegten dokumentierten Nachweisprozess, Retention-/Löschfristen, einen verlässlichen Weg zur Ausgabe/Bestätigung der privaten Einladungscodes, risikobasiertes Missbrauchsmonitoring und eine juristisch passende Unterscheidung zwischen kindlicher Eigenzustimmung und vertretender Einwilligung. Alters-/Geburtsdatenlogik sowie die **separaten eigen- und vertretungsbezogenen Einwilligungs-Screens** sind weiterhin offen. Es werden keine frei behaupteten Geburtsdaten zu Berechtigungen umgedeutet.

**Sicherheitsregel für den späteren Betrieb:** In der aktuellen V1 ist der Code nur ein schwer erratbarer Such-/Antragsbezug, kein Berechtigungstoken. Auch bei Besitz des Codes bleiben alle geschützten Datensätze ohne ausdrückliche Adminfreigabe unzugänglich. Keine echten Teilnehmer für Tests oder E-Mails an Dreamloud.
