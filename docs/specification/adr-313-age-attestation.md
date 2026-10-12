# ADR 313 – Altersatteste und Event-Eligibility: Version-2-Einführung

**Status:** Angenommen, Implementierung in Phasen. **Datum:** 12. Oktober 2026. **Issue:** #7.  
**Voraussetzungen:** Freeze ADR 310, ADR 311–312. Keine allgemeinen rechtlichen Altersgrenzen.

## Sicherheitsmodell

- WordPress-Account, Person, bloße Beziehung, beantragte Vertretung und Altersattest sind unterschiedliche Dinge. Ein Altersattest vergibt **keine** Delegations-, Consent- oder Person-Bearbeitungsrechte.
- Organisation und einzelne veröffentlichte Veranstaltung definieren ihren konkreten Alterszweck. Eine Altersgrenze wird *pro Eventregel* ausdrücklich konfiguriert und nie aus der E-Mail oder einem durch Teilnehmer eingetragenen Geburtsdatum abgeleitet.
- Die Organisation prüft einen Nachweis außerhalb des Plugins. Der prüfende und berechtigte Mitarbeiter erfasst **ausschließlich das Ergebnis einer Altersgrenze** (`gte` bzw. `lt`, Schwellwert), Methode aus fester Liste, prüfenden Account, Zeitpunkt und Ablauf. Ausweisnummern, Ausweiskopien, volle Geburtsdaten und Drittanbieter-Identitätsnummern sind **nicht** Bestandteile der Altersattestierung.
- Der zugeordnete Prüfstatus ist `unknown` (kein Datensatz), `verified` (aktuell), `expired` (abgelaufen) oder `revoked`. `declared` ist ausschließlich eine unverifizierte Selbstauskunft und wird niemals zur gleichen Evidenz wie `verified`; eine getrennte spätere Erfassung von Selbstauskünften darf keine Rechte auslösen.
- Für jede Kombination aus Tenant, Person, Event und Altersgrenze gilt ausschließlich das **neueste** Attest; bei abgelaufenem oder widerrufenem letzten Attest darf ein älteres positives nicht erneut wirksam werden. Ein Widerruf ist append-only, enthält keine vertraulichen Nachweise und widerruft sofort alle darauf basierenden künftigen Autorisierungen.
- Der prüfende Mitarbeiter muss Live-Objektberechtigungen besitzen; Selbstprüfung und Prüfung durch denselben vertretenen Account sind unzulässig. Widerruf und Ersatzentscheidung sind revisionsfähig, organisiert, aber nicht automatisch gesetzlich wirksam.
- Eine altersgeprüfte Eventregel ist nur als eigener positiver Prädikattyp zulässig (statt das DOB-Profilfeld umzudeuten). `not(unknown)` bleibt `unknown` (ADR 312). `age_gte_at` mit einem rein administrierten Geburtsdatum ist ausdrücklich **kein** solches Altersattest und darf nicht als extern verifiziert bezeichnet werden.
- Für Altersvergleiche ist ein eindeutig geplanter Eventstart nötig; Atteste müssen bei Aktionserteilung gültig sein und die Eventzeit einschließen. Keine Übernahme in andere Veranstaltungen.

## Datenmodell und Migration

Ein zusätzliches `age_attestations`-Datenset wird über eine **neue Migration V2** angelegt. Die eingefrorene V1-Baseline von 28 Tabellen und ihr unveränderter M1-Migrationsschritt bleiben bestehen. V2 verwendet eine eigene explizit validierte Definition und eine abschließende, tenant-spezifische Query- und Indexprüfung. Keine destruktiven Spaltenumbauten, keine automatischen positiven Atteste für Bestandsdaten, keine Backfills aus DOB, E-Mail, Guardian-Beziehungen oder historischen Consents.

Rollback: **kein automatisches Herabstufen**. Vor produktiver Migration Backup und Restore-Probe verpflichtend; bei Teilmigration mit Advisory Lock checkpoint-basiert fortsetzen. Frühere Versionen müssen beim Downgrade anhalten.

## Datenschutz, Abnahme, Grenzen

Datensparsamkeit: nur Altersklasse, Methode als unspezifischer Code, prüfender Mitarbeiter und minimaler Zweck-/Zeitbezug. Zugriff auf Atteste: ausschließlich nach aktueller Objekt-/Organisations-Policy. Eine Lösch-/Aufbewahrungsstrategie muss die geltende Betreiberregel und etwaige Holds abbilden; keine ungeprüfte Standardfrist. In V1 dürfen Einwilligung und Guardian-Grant **nicht allein** aus einem Attest entstehen.

**Release-Gates:** neue und Upgraded-Installation, SQL-Datenmodell und Cross-Org-Isolation, Reviewer-/Self-Review-Abwehr, Ablauf/Widerruf, negative Eventregeln, Warteliste/Annahme-Reevaluation, Migration-Recovery, Datenschutz/Löschung, realer Admin/REST-/Portal-Ablauf und rechtliche Betreiberprüfung. Bis diese Pfade vollständig implementiert und getestet sind, ist dies nur eine technische Teilabnahme und kein Abschluss von Issue #7.
