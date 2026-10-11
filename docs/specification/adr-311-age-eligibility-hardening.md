# ADR 311 – Fail-closed-Auswertung von altersbezogenen Eventregeln

**Status:** Angenommen als technische Sicherheitskorrektur zu Issue #7; eine vollständige fachliche Altersverifikation bleibt offen.  
**Datum:** 12. Oktober 2026  
**Scope:** UOP Core V1; zentraler Condition-/Policy-/Eligibility-Vertrag nach Phase-3-ADR 310.

## Kontext und Gefährdung

UOP Core unterstützt `age_lt_at` und `age_gte_at` als deklarative Event-/Capacity-Bedingungen. Die Werte werden bereits aus dem serverseitigen Profil und nicht aus der Registration-Payload geladen. **Allerdings ist `subject_edit` bei regulären Profilfeld-Definitionen standardmäßig aktiviert.** Ein vom Betroffenen frei änderbares Geburtsdatum darf deshalb nicht als für Alters-Zugangsbeschränkungen hinreichend verlässliches Merkmal behandelt werden.

Außerdem erlaubt ein strikt boolescher Vergleich den gefährlichen Ausdruck `not(age_lt_at(...))`: Ein fehlendes oder fehlerhaftes Geburtsdatum würde zunächst `false` ergeben und unter `not` in `true` umkippen.

## Entscheidung – Sicherheitsgrenze für bestehende Altersprädikate

1. Die beiden bestehenden `age_*_at`-Operatoren dürfen ausschließlich auf dem zentralen Profilfeld `date_of_birth` ausgeführt werden, mit Datentyp `date` und eindeutiger geplanter `event.start`-Zeit. Arbiträre Profil-/Registration-Felder werden nicht als Geburtsdaten umgedeutet.
2. Der für Altersregeln genutzte Feldwert muss **gegenwärtig** weder durch die betroffene Person (`subject_edit = false`) noch durch einen Delegierten (`delegate_edit = false`) bearbeitbar sein. Die berechtigte Organisationsverwaltung trägt den Wert im dafür vorgesehenen Verwaltungsprozess ein. Die normale feld-/objektbezogene `PolicyService`-Prüfung bleibt daneben verpflichtend.
3. Ein fehlender, leerer, syntaktisch ungültiger oder in der Zukunft liegender Geburtstermin verweigert die Auswertung der Altersregel **vor** dem booleschen Condition-Engine-Evaluator. Damit können `not`- und `any`-Verschachtelungen keine ungeprüften Angaben in eine Erlaubnis verwandeln.
4. Die restriktive Prüfung erfolgt in `RegistrationFactsService::load()`, dem gemeinsamen Pfad für Registrierung und die erneute Prüfung von Seat/Waitlist-Übergängen. Die Autorisierung darf weder aus einer Browser-Payload, einer gemeinsam genutzten E-Mail-Adresse noch allein aus einer Guardian-Beziehung abgeleitet werden.
5. Keine Änderung an Tabellenzahl, Schema, Public IDs, WordPress-Version, REST-Version oder gespeicherten Registrierungs- und Consent-Snapshots. Bereits vorhandene Altersregeln mit editierbarem Profilfeld werden nach dieser Härtung **nicht mehr positiv bewertet**, bis eine sichere Felddefinition und valide Administratoreingabe vorliegen. Das ist die bewusst gewählte Fail-closed-Upgradewirkung.
6. Fehlende Altersregeln bleiben in der V1-Funktionalität unverändert. **Es gibt keine globale Altersgrenze** und keine automatischen gesetzlichen Zustimmungsvorgaben.

## Grenzen dieser Entscheidung

Ein nur von Organisationsmitarbeitern gepflegtes Geburtsdatum ist **nicht automatisch unabhängig verifiziert**. Diese Korrektur verhindert technische Selbstattestierungs- und Negations-Bypasses, ersetzt aber weder die organisatorische Nachweisprüfung noch eine Speicherung von `unbekannt / ungeprüft / geprüft / abgelaufen` mit Zweckerfassung, Aufbewahrungs-/Löschplan und überprüfbarem Prüfer.

Vor einer produktiven Altersprüfung müssen diese Zustände und ihre Nachweisbeziehungen unter einem **weiteren ADR samt ggf. Migration** gemäß Issue #7 spezifiziert und implementiert werden. Keine vollen Geburtsdaten ohne Erforderlichkeit sammeln; wo möglich erst eine attestierte Altersklasse speichern. Die getrennten Eigen-/Vertreter-Consent-Entscheidungen bleiben Issue #8; Guardian-Nachweise bleiben Issue #9.

## Kompatibilität, Migration und Abnahme

- **Schema-Migration:** nicht erforderlich für diese rein serverseitige zusätzliche Bedingung.
- **Kompatibilität:** bestehende editierbare `date_of_birth`-Felder können Altersprädikate nicht mehr erfüllen; Betreiber müssen den Zutrittsregelvertrag überprüfen, bevor ein Event mit Altersanforderung genutzt werden kann. Kein automatisches Überschreiben oder stilles Vertrauen in Bestandsangaben.
- **Tests:** Altersgrenze auf ein serverseitig kontrolliertes Datum, unbekanntes Datum unter `not` und `any`, selbst- oder delegiert editierbare Geburtsdaten, falscher Feldtyp/Feldname, Cross-Org und nachträgliche Änderung bei Capacity-Angebot und -Annahme.
- **Release-Gate:** Dies ist ein Sicherheits-Teilergebnis, **nicht** Abschluss von Issue #7 oder eine rechtliche Altersprüf-/Minderjährigenfreigabe.
