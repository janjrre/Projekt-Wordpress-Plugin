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
