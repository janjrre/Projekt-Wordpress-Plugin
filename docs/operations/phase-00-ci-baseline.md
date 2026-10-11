# Phase 00 – stabiler CI-Ausgangsstand und offene Arbeiten

Stand: 11. Oktober 2026 · Repository `janjrre/Projekt-Wordpress-Plugin` · Entwicklungszweig `feat/m6-admin-portal-rest` · Draft-PR #6.

**Geltungsbereich:** Nur UOP Core. Kein Merge, kein Deployment, kein Zugriff auf Dreamloud oder echte personenbezogene Daten.

## Verifizierter Ausgangsbefund (vor Phase 00)

Referenz-HEAD: `345ff661c9cf7f02e093dbb1114c76a16ac8c163`.

| Maßgeblicher Workflow | Lauf | Nachgewiesenes Ergebnis |
| --- | --- | --- |
| Foundation and persistence | [38174882030](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38174882030) | Sechs von sechs PHP 8.3/8.4/8.5 × MySQL 8.0/MariaDB 10.11 Integration-Jobs **grün**. Quality **rot** wegen genau eines PHPStan-`booleanAnd.rightAlwaysTrue` in `ConsentRecordService.php:107`. Artefakt-Smoke wurde deshalb übersprungen. |
| M6-10 disposable HTTPS and Mailpit E2E | [38174882022](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38174882022) | 17/18 echte Chromium-Tests bestanden; ein Guardian-Claim-Test scheiterte am 20-Sekunden-Navigations-Timeout bei `login()`, bevor der Antrag geöffnet wurde. Keine Bestätigung, dass der Fehler aus fachlicher Guardian-Logik stammt. |
| UOP staging – guarded HTTPS checks and isolated plugin delivery | [38174882015](https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38174882015) | Statische Staging-Sicherheitschecks erfolgreich. Kein manueller Staging-Deploy. |

## Phase-00-Korrekturen auf dem Draft-Branch

1. **PHPStan:** Nach der verpflichtenden Prüfung `delegate && !representative_confirmed` ist `representative_confirmed` im delegierten Erfolgsfall bereits wahr. Die Übergabe der booleschen Einwilligungsmetadaten an das Repository benötigt deshalb nur noch den tatsächlichen, aus der Policy abgeleiteten Kontext `delegate`. Kein Abschalten oder Verschweigen einer statischen Analysewarnung.
2. **GitHub CI:** `.github/workflows/ci.yml` nutzt für Push und PR getrennte, branch-/PR-spezifische Concurrency-Gruppen und bricht ausschließlich veraltete Revisionen desselben Kanals ab. Damit bleibt ein aktueller PR- und ein aktueller Push-Check erhalten. `uop-https-e2e.yml` ersetzt veraltete PR-Browserläufe auf derselben PR; manuelle Diagnose-Dispatches werden nicht automatisch abgebrochen.
3. **Staging-Schutz:** `uop-staging.yml` gruppiert PR-Static-Checks nach PR und hält manuelle `workflow_dispatch`-Aktionen in einer gemeinsamen **nicht abbrechbaren** Gruppe. Der bisherige verpflichtende ausdrückliche SHA-/Deployment-Guard bleibt unangetastet.
4. **HTTPS-Browser-Anmeldung:** Der Test verifiziert weiterhin die echte erfolgreiche WordPress-Anmeldung durch `/wp-admin/` **und** sichtbare Adminleiste. Nur wenn der Weg auf dem WordPress-Loginformular ohne ausdrücklich angezeigten Credential-Fehler stecken bleibt, versucht der Test **einmal** erneut die Anmeldung; ein WordPress-`#login_error` oder eine andere unerwartete Ziel-URL führt weiterhin zum Fehler. Das Fehlerprotokoll nennt danach Nutzer, erreichte URL, Versuch und Ursache.

## Nächste fachliche Teilaufträge (Paket 4)

- [#7](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/7): datensparsame Alters-/Eventeligibility-Policy mit ADR, ohne pauschale Universal-Altersgrenze.
- [#8](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/8): echte, **nur sofern vorgeschrieben** unabhängige Eigen-/Vertreter-Einwilligungen mit getrennten Nachweisen und Widerrufen.
- [#9](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/9): prüfbare Guardian-Verifikation, sichere Code-Ausgabe, Mehrfachvertretung und Missbrauchsschutz.
- [#10](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/10): vollständige Golden Paths, Mobile-/Screenreader-, Datenschutz- und juristische Betriebsabnahme.

**Reihenfolge:** Erst Phase-00-CI-Gate, dann #7 (ADR/Policy), #8 und #9 entlang der geklärten Verträge, zuletzt #10 als übergreifende Abnahme. Fachliche Rechtsprüfung bleibt ein explizites Release-Gate; eine technische Checkbox ist kein legaler Vertretungsnachweis.

## Zusätzlicher Befund beim vollständigen Retest

- Auf `308ba6e559f9fb65f9106a6b49bd5b8c10d7d578` war der Quality-Job inkl. PHPStan und Artefakt-Smoke **grün**; in der E2E-Suite bestanden **17/18** Chromium-Szenarien. Die ursprüngliche Guardian-Login-Stelle ist nicht mehr fehlgeschlagen.
- Stattdessen zeigte die Browserprüfung einen **echten, nicht wegzuignorierenden** Barrierefreiheitsbefund: Der "Create draft"-Button des M3-Form-Builders war trotz geladener `m3-form-builder.css` in einem CI-Viewport nur **40px statt der geforderten 44px** hoch; berechnetes `min-height` = `40px`.
- Korrektur in `assets/m3-form-builder.css`: expliziter, auf das WP-Admin-Form-Builder-Element begrenzter Mindestwert von **44px mit `!important`**, damit WordPress-Adminvarianten den Touch-Target-Floor nicht überschreiben. **Die 44px-Playwright-Assertion wurde nicht abgeschwächt.**
- Die korrigierte Version benötigt erneut vollständige CI-Abnahme **auf dem jeweils finalen Commit**.

## Weitere CI-Befunde und gezielte Testhärtung

- Auf dem nachfolgenden HTTPS-Test waren die 44px beim M3-Form-Builder weiter nicht nachgewiesen und ein WordPress-Adminmenü überdeckte zeitweise den Guardian-Review-Button. Der CSSOM-Diagnosewert `Stylesheet rules unavailable` zeigt, dass `link.sheet` oder ein sichtbarer Link allein **kein Beweis für anwendbare/auslesbare Regeln** ist.
- Eine allgemeine Pflicht, *alle* WordPress-CSS-Dateien per `cssRules` auslesen zu können, verursachte weitere falsche CI-Abbrüche. Diese Pflicht wurde wieder entfernt; statt dessen wartet der Browser in Admin-Journeys auf das tatsächliche `load`-Ereignis und prüft den 44px-Zielwert per **computed geometry**. Ein Button unter 44px bleibt ein roter Test. Clicks erfolgen weiter physisch, nicht mittels `force` oder manipulierter DOM-Events.
- Die CSS-Korrektur am Form-Builder wurde zusätzlich auf dessen eindeutigen Mount-Punkt `#uop-m3-form-builder` begrenzt und gegen konkurrierende WordPress-Buttonregeln priorisiert.
- Auf PHP 8.5 + MariaDB 10.11 trat im isolierten Migration-Integrationstest einmal `Another migration runner is active` direkt **nach dem Schließen einer konkurrierenden Datenbankverbindung** auf. Die Testfixture prüft jetzt vor der Fortsetzung mit einer maximal 2,5 Sekunden begrenzten Schleife `IS_FREE_LOCK()` und verlangt den echten Datenbank-Release. Die produktive `MigrationRunner`-Semantik bleibt korrekt **fail closed** und verwendet weiterhin `GET_LOCK(...,0)`; es wurde **kein** Retry in die Produktivmigration eingeführt.
- Diese Korrekturen sind Test-/CSS-Härtungen, **keine** Abschwächung von REST-Rechten, Einwilligungsprüfungen, Klick-Interaktions- oder Mindestgrößen-Assertions.

## Phase-00-Exit-Kriterien (nicht aufgrund älterer grüner Commits erfüllen)

- [ ] Auf **ein und demselben neuesten HEAD**: `Foundation and persistence` vollständig abgeschlossen; Quality (Composer-Lint, PHPStan, Security-/Node-Checks, Paketierung), alle sechs Datenbank-/PHP-Matrix-Jobs, Artefakt-Smoke grün.
- [ ] Auf demselben HEAD: `M6-10 disposable HTTPS and Mailpit E2E` erfolgreich, einschließlich **aller 18** Chromium-Szenarien (bei Änderung der Anzahl mindestens dieselben Fälle).
- [ ] Staging-Sicherheitsworkflow erfolgreich; keine manuelle Deploy-Operation.
- [ ] Fehlgeschlagene Tests sind nicht durch Skip/Abschwächung sicherheitsrelevanter Assertions oder dauerhafte blinde Retries kaschiert.
- [ ] CI-Logs, Tests, Commits und offene Fachaufgaben im Draft-PR dokumentiert.
- [ ] Kein Merge von PR #1–#6, keine produktive Aktivierung und keine Veröffentlichung.

**Status:** Dokumentation eines überprüfbaren Gates – nicht dessen vorzeitige Freigabe. Sobald der finale CI-Commit grün ist, Nachweise einschließlich Run-ID und SHA ergänzen.
