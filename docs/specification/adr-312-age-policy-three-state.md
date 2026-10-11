# ADR 312 – Dreiwertige Altersprüfung und kontrollierte DOB-Felddefinition

**Status:** Angenommen (technische Teilentscheidung zu #7; kein Abschluss der Nachweisprüfung)  
**Datum:** 12. Oktober 2026  
**Vorgänger:** Phase-3-Freeze ADR 310, Alters-Härtung ADR 311

## Problem

Das deklarative V1-Conditions-AST erlaubt `age_lt_at` und `age_gte_at` zusammen mit `not`, `all` und `any`. Altersprädikate, für die keine gültige Geburtsangabe bzw. kein eindeutiger Veranstaltungszeitpunkt existiert, sind **unbekannt**, nicht falsch. Die bisherige rein boolesche Auswertung hat `not(false)` in eine positive Bedingung umgedreht. `RegistrationFactsService` versagt solche Berechnungen bereits vor der Auswertung; die Domain-Engine muss dennoch selbst dieselbe Schranke besitzen, damit neue Aufrufpfade sie nicht versehentlich umgehen.

Zugleich ist `date_of_birth` ein spezieller Security-Fact und darf nicht als beliebiges, standardmäßig selbst bearbeitbares Formularfeld angelegt werden. Eine explizit vertrauliche Felddefinition ist **kein amtlicher Altersnachweis**.

## Entscheidung

1. `ConditionEngine` bewertet Altersbedingungen dreiwertig: `true`, `false`, `unknown` (intern `null`). Das öffentliche `evaluate()` liefert ausschließlich `true`, wenn das Ergebnis gesichert wahr ist; `unknown` wird `false`.
2. `not(unknown) = unknown`, `all` und `any` verwenden nachvollziehbare Dreiwertlogik: `all` mit mindestens einem `false` ist `false`; `any` mit mindestens einem `true` ist `true`; ansonsten breitet sich `unknown` fort. Unbekanntes Alter kann durch bloße Negation nicht wahr werden.
3. `age_lt_at` und `age_gte_at` sind ausschließlich für `profile.date_of_birth` und `event.start` zulässig. Andere Feldnamen und Registration-Quellen werden im AST abgewiesen, also vor Veröffentlichung/Verwendung.
4. Das Profilfeld mit dem reservierten Key `date_of_birth` muss vom Typ `date` sein und für die betroffene Person sowie delegierte Konten **nicht editierbar** sein. Bei Neuanlage genügt nicht die implizite V1-Voreinstellung `subject_edit=true`; beide Flags müssen ausdrücklich `false` sein. Für sonstige Profilfelder bleibt das bestehende V1-Verhalten unverändert.
5. Der Application-Service prüft zusätzlich, ob das tatsächliche persistierte Feld den Konfigurations- und Berechtigungsbedingungen entspricht, ob ein reales, datumsvalides Datum geladen wurde und ob der Eventstart eindeutig ist (ADR 311).
6. Keine impliziten Altersgrenzen oder automatisch gewährten Vertreterrechte; identische E-Mail-Adressen belegen keinen Familienbezug.

## Migration / Bestandskompatibilität

Dies ist eine **fail-closed Policy-Änderung ohne physische Schemaänderung**. Bislang fehlerhaft angelegte `date_of_birth`-Felder werden durch `RegistrationFactsService` schon abgelehnt; neue Definitionen können nicht mehr versehentlich mit offenen Edit-Rechten angelegt werden. Andere Profilfelder, reguläre altersfreie Registrierungen und gespeicherte Einwilligungs-Records bleiben unverändert. Bestehende ungültige Age-ASTs müssen beim nächsten Authoring/Pub­lish erneuert werden, nicht automatisch konvertiert.

## Nachweise und noch offene Anforderungen

- Unit-Tests für fehlende und ungültige Geburtsdaten unter `not`, `all` und `any`, Altersgrenzen, ungültige AST-Feldnamen und Felddefinitionsflags.
- CI mit vollständigen Qualitäts-, PHP/DB- und HTTPS-Gates auf exakt einem Commit.
- **Nicht enthalten:** unabhängig überprüfbare Altersatteste, Ablauf/Widerruf, Nachweisart und -prüfer, geschützte REST/Admin-Bearbeitung, Version-2-Datenbankmigration, datensparsame Altersklassen sowie tatsächliche rechtliche Betreiberfreigabe. Das ist der nächste dedizierte Arbeitsschritt und wird weder als bestehendes Feature dargestellt noch mit Geburtsdatums-Selbstauskunft simuliert.
