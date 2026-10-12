# WordPress.com OrgaVela – Isolierte UOP-Testumgebung

Stand: 12. Oktober 2026. Nur UOP Core. Keine Verbindung zu Dreamloud.

## Website und Freigabe

- Testwebsite: `https://orgavela.wpcomstaging.com` (WordPress.com Atomic, nicht öffentlich gestartet / Coming Soon).
- Repository: `janjrre/Projekt-Wordpress-Plugin`; PR #11 auf `feat/p4-age-evidence`.
- Ein eigenständiges signiertes/überprüftes ZIP wird in einem getrennten Staging-Artefaktbranch `staging/uop-plugin-package` unter `uop-core.zip` bereitgestellt. Die Herkunft ist in `package-provenance.txt` dokumentiert. **Kein öffentlicher V1-Release.**
- Der Betreiber hat technische Test- und Entwicklungsarbeiten ohne zusätzliche einzelne Zustimmung erlaubt. Kostenpflichtige Verpflichtungen sind ausgeschlossen; keine echten Teilnehmerdaten.

## Tatsächliche Kompatibilitätsprüfung vom 12.10.2026

- WordPress **7.1.3**; PHP **8.4.26**; MariaDB **11.8.9**.
- Datenbankserver kann **utf8mb4**, inklusive nachgewiesener `information_schema.CHARACTER_SETS`-Eintragung.
- Aktiver WordPress DB-Client und die SQL-Verbindung sind jedoch **latin1** (`latin1_swedish_ci`).
- Upload von `UOP Core 0.1.0-alpha.2` erfolgreich. Die Aktivierung wurde durch den absichtlichen, fail-closed `utf8mb4_required`-Check **abgewiesen**. Plugin bleibt **inaktiv**.
- Ein vorübergehend hochgeladenes, rein lesendes `UOP Environment Probe`-Plugin bestätigte diese Fakten und wurde wieder deinstalliert.

**Nicht unterlaufen:** Das `utf8mb4`-Erfordernis ist Bestandteil der eingefrorenen UOP-Datenintegritäts- und Unicode-Anforderungen. Kein `SET NAMES`-Hack am globalen WordPress-Connection-Handle, keine stillschweigende `latin1`-Migration, keine Produktivfreischaltung.

## Nächste Schritte

1. Offene Hosting-Kompatibilitätsfrage siehe zugehöriges GitHub-Issue: WordPress.com-unterstützten, persistierenden UTF8MB4-Verbindungsmodus und tatsächliche Tabellenzeichensätze klären. Gegebenenfalls sicher mit Backup, Restore-Nachweis und konkreten Datenkonversionsprüfungen umstellen; keine eigenmächtigen Änderungen an `wp-config.php` oder Bestandstabellen.
2. Bis dahin Golden-Path- und Datenbanktests weiter über die bereits grüne CI- und isolierte HTTPS-Testmatrix ausführen.
3. Nach erfüllter Hosting-Voraussetzung erneut UOP Core auf OrgaVela aktivieren. Dabei ausschließlich synthetische Testpersonendaten, `.invalid`-Adressen und kontrollierte Mailzustellung nutzen.
4. Ergebnisse, Änderungen, Schemahealth, eventuelle Rücknahme und getesteten Commit nachvollziehbar dokumentieren.

Die frühere `uop-test.dreamloud.de`-Staging-Pipeline ist für diesen Auftrag **nicht** freigegeben. Die neue WordPress.com-Umgebung ist vollständig davon getrennt.
