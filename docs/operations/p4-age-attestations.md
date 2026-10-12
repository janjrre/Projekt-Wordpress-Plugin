# UOP Core – Altersattestierung, V2: Betriebsanleitung und Grenzen

Stand: 12. Oktober 2026. Geltungsbereich: Issue #7 / ADR 311–313, ausschließlich UOP Core. Keine Inbetriebnahme mit echten Teilnehmerdaten, keine automatische rechtliche Freigabe.

## Ablauf und Datenminimierung

Die Altersattestierung hält **keine** Ausweiskopien, amtlichen Kennnummern oder Geburtsdaten vor. Der berechtigte Organisationsadministrator führt einen externen, für Zweck und Einsatzfall geeigneten Nachweisprozess durch und hält nur **die überprüfte Klasse für eine bestimmte Altersgrenze** (`gte` = mindestens, `lt` = darunter), Methode, Prüfzeitpunkt, prüfendes Konto, Event und begrenzte Gültigkeit fest. Die zentrale Berechtigungsprüfung kontrolliert die Live-Rolle, den tatsächlichen Event-Status, die Person und verhindert eine Selbstprüfung.

Der Ablauf in WordPress ist **Age attestations → Status abfragen → Person-UUID, veröffentlichte Veranstaltung und konkrete Schwelle wählen → Prüfmethode und Gültigkeit angeben → externe Prüfung ausdrücklich bestätigen → Klasse erfassen**. Die Bearbeitung erfolgt über ein eigenes nonce-geschütztes WordPress-Adminformular; Teilnehmer dürfen diese Prüfung nicht selbst auslösen. Eine Rücknahme erfordert eine separate Aktion `Revoke current class` und schreibt einen unveränderlichen widerrufenden Datensatz. Für unbekannte, abgelaufene und widerrufene Nachweise greift die Event-Eignung unmittelbar fail closed. Die frühere positive Bestätigung wird nach einer Rücknahme **nicht** erneut aktiv. Das Plugin übernimmt keine Nachweise einer anderen Veranstaltung.

**REST (nur Manager, aktuelle Berechtigung):**
- `GET /wp-json/uop/v1/admin/age-attestations?person_id=<UUID>&event_post_id=<ID>&threshold_age=<ALTER>` – `unknown`, `verified`, `expired` oder `revoked` ohne DOB oder Unterlagen. Status-Lesezugriffe erfordern zusätzlich zum Organisationsrecht eine aktuelle `event.manage`-Berechtigung, WordPress-`edit_post` und eine veröffentlichte Veranstaltung (sonst 403/404). Nach dem Zurückziehen der Veröffentlichung gelten Atteste sofort nicht mehr als positiver Eligibility-Nachweis.
- `POST /wp-json/uop/v1/admin/age-attestations` (JSON):
  `{"person_id":"<UUID>","event_post_id":123,"threshold_age":18,"outcome":"gte","method":"in_person_review","valid_until":"2030-05-01 23:59:59","evidence_checked":true,"revoke":false}`.
- `revoke:true` nimmt die aktuell gültige attestierte Klasse zurück; nur die tatsächlich letzte bestätigte Klasse ist widerrufbar. Gleiches REST-Nonce-/Login- und Live-Manager-Scoping wie andere geschützte UOP-Endpunkte.
- Keine Nutzerübernahme aus `person_id`, E-Mail, behaupteter Familienbeziehung, Profilgeburtsdatum oder Query-Parametern. Es werden keine Delegations- oder Consent-Rechte geschaffen.

**Event-Policy** nutzt die bestehende versionierte Conditions-AST, z. B.:

```json
{"schema_version":1,"all":[{"source":"profile","field":"date_of_birth","operator":"age_verified_gte_at","value":18,"context":"event.start"}]}
```

Das Feld `date_of_birth` steht hier als reservierter AST-Pfad, **nicht** als vertrauenswürdiger DOB-Wert. Die tatsächliche Entscheidung wird nur anhand des separaten Attests und des vertrauenswürdigen Eventstarts vom Server erzeugt. `not(unknown)` bleibt `unknown` und berechtigt niemals. Alte `age_gte_at`-Prädikate auf einem adminverwalteten Datum sind **nicht** gleichbedeutend mit einer unabhängigen Altersprüfung.

## Migration, Recovery und Aufbewahrung

`Installer::runner()` führt zuerst die unveränderte **28-Tabellen-V1**, dann die checkpointfähige additive V2-Tabelle `age_attestations` aus. Keine automatische Befüllung mit bestehenden Profilwerten, Attestierungen oder Consent-Einträgen. Ein Upgrade verifiziert auch die installierte V1; Schema-Drift oder fehlender Lock hält das Upgrade an. `Installer::health()` prüft beide Versionen. **Vor einem echten Upgrade eine verifizierte Backup-/Restore-Probe durchführen; automatische Downgrades sind ausgeschlossen.** Die isolierte GitHub-WordPress- und Datenbankmatrix testet den Updatepfad.

Eine gezielte, bereits zugelassene Profilwerte-Löschung und die archivierende Personen-Retention entfernen attestierte Altersklassen für dieselbe Person und Organisation; rechtliche Holds und die bestehenden Retention-Zulassungskriterien bleiben verbindlich. Es existiert **keine allgemeine rechtlich verbindliche Standard-Löschfrist**. Betreiber müssen passende Fristen und einen datenschutzrechtlichen Review-Prozess definieren. Die manuell erfasste `valid_until`-Gültigkeit wird auf maximal 366 Tage begrenzt.

## Offene Freigabevoraussetzungen

Ein Häkchen, dass ein Administrator außerhalb des Plugins etwas geprüft habe, ist eine **prüferbezogene Erklärung**, kein unabhängiger technisch verifizierter Personalausweis- oder Sorge-Nachweis. Vor produktivem Einsatz muss die Organisation das tatsächlich zum jeweiligen Zweck geeignete externe Verfahren, Prüfertrennung, rechtliche Grundlage für die Datenspeicherung, Aufbewahrung und Löschung, Auditzugang und datenschutzrechtliche Dokumentation verbindlich festlegen. **Altersatteste sind weder Sorgeberechtigungsnachweis noch eine Einwilligung.** Für unabhängige Eigen-/Vertreter-Consent-Entscheidungen und Guardian-Prüfung bleiben Issues #8–#10 separate Release-Gates. Auch erfolgreiche automatisierte Tests ersetzen diese fachliche Freigabe nicht.

**Betriebliche Einschränkung:** Keine kostenpflichtigen Verträge oder Abrechnung, keine echte Teilnehmerdatenverarbeitung bei Tests, keine Dreamloud-Änderung.
