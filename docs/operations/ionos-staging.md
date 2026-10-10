# UOP Core – isoliertes IONOS Staging

Stand: 10. Oktober 2026. Geplante URL: **https://uop-test.dreamloud.de**.

**Aktueller Status:** Vorbereitet auf dem UOP-Branch; **noch nicht als IONOS-Website angelegt**. Die Produktivinstallation Dreamloud, das Dreamloud-Repository und dessen SFTP-Secrets bleiben unangetastet.

## IONOS-Einrichtung (noch ausstehend)

1. Im IONOS-Konto prüfen, ob der bestehende Vertrag eine **zweite eigenständige WordPress-Installation**, eine separate MySQL/MariaDB-Datenbank, eine SSL-geschützte Subdomain und isolierbaren SFTP-Zugang zulässt.
2. Subdomain **uop-test.dreamloud.de** anlegen und auf einen **eigenen** WordPress-Webspace-Ordner namens **uop-test-dreamloud-de** richten. Niemals auf das bestehende Dreamloud-DocumentRoot zeigen lassen.
3. Neue, leere Datenbank und neue WordPress-Installation anlegen. Keine echten Benutzer, Daten, Plugins oder Zugangsdaten der Produktionssite kopieren.
4. Vor Freigabe der Testsubdomain **Webserver-Zugriffsschutz** (HTTP Basic Auth oder gleichwertig) einrichten. Noindex/robots.txt allein schützt nicht vor fremden Zugriffen.
5. Ausschließlich in der *neuen* Staging-wp-config.php eintragen:
   - `define( 'WP_ENVIRONMENT_TYPE', 'staging' );`
   - `define( 'UOP_STAGING_SITE_HOST', 'uop-test.dreamloud.de' );`
   - `define( 'UOP_STAGING_ALLOWED_RECIPIENTS', '' );` — ohne explizite Testadressen ist aller WordPress-Mailversand gesperrt.
   - `define( 'DISALLOW_FILE_EDIT', true );`
6. Die Datei `staging/mu-plugins/uop-staging-guard.php` aus **diesem UOP-Repository**, nicht aus dem Dreamloud-Repository, als `wp-content/mu-plugins/uop-staging-guard.php` in der **neuen** Installation hinterlegen. Sie verweigert falsche Umgebung und setzt Noindex/No-Store-Header.
7. Separaten SFTP-Zugang erstellen, nur für das neue Staging-Verzeichnis berechtigen. Nicht mit den Produktionszugängen arbeiten. Das Staging-Zielverzeichnis muss auf `/uop-test-dreamloud-de` enden.
8. Im UOP-GitHub-Repository ein eigenes geschütztes Environment `uop-staging` anlegen, idealerweise mit Pflichtfreigabe. Die **staging-eigenen** Secrets dort speichern:

| GitHub Environment Secret | Inhalt |
| --- | --- |
| `UOP_STAGING_BASIC_USER` | HTTP-Zugriffsschutz, Benutzer |
| `UOP_STAGING_BASIC_PASSWORD` | HTTP-Zugriffsschutz, Passwort |
| `UOP_STAGING_SFTP_HOST` | IONOS-SFTP-Hostname |
| `UOP_STAGING_SFTP_PORT` | SFTP-Port |
| `UOP_STAGING_SFTP_USER` | Separater eingeschränkter SFTP-Benutzer |
| `UOP_STAGING_SFTP_PASSWORD` | Nur Staging-SFTP-Passwort |
| `UOP_STAGING_SFTP_ROOT` | Exakter WordPress-Staging-Ordner, endet auf `/uop-test-dreamloud-de` |
| `UOP_STAGING_SSH_KNOWN_HOSTS` | Unabhängig verifizierte SSH-Hostkey-Zeilen |

9. Zuerst **nur prüfen**, danach im kontrollierten Staging-Ziel UOP installieren und **nur dort manuell** aktivieren.
10. Getrennt echte Browserabläufe, synthetische Nutzer, E-Mail-Empfang an explizit erlaubte Testpostfächer und HTTPS-Einmallinks abnehmen. Gast- und Wartelisten-Opt-ins bleiben standardmäßig aus.

## Automatische Prüfungen

- `scripts/staging-probe.mjs` prüft die feste HTTPS-URL, authentifizierten Zugriff, staging-eigenen Header, Noindex, No-Store und echten WordPress-REST-Index. Es werden **keine Schreibzugriffe** durchgeführt.
- Mit `UOP_STAGING_EXPECT_ACTIVE=1` fordert die Prüfung die REST-Namespace `uop/v1` (nach Pluginaktivierung).
- `.github/workflows/uop-staging.yml` erlaubt ausschließlich manuelle Staging-Checks und ausdrücklich bestätigte Plugin-ZIP-Uploads. Kein automatisches Deployment beim Push und kein Zugriff auf Dreamloud-Produktionssecrets.
- Der neue GitHub-Workflow steht vorerst nur im **ungemergten Draft-PR**. GitHubs manueller `workflow_dispatch` ist normalerweise erst verfügbar, wenn die Workflowdatei auch im Default-Branch liegt. Bis zur späteren Freigabe ist das Vorhandensein der Konfiguration **keine** erfolgte Staging-Bereitstellung.
- Niemand soll Datenbank- oder FTP-Passwörter in Tickets/Commit-Meldungen/Chat veröffentlichen. Sie gehören ausschließlich in die Staging-Secrets.

## Sperren

Kein Deployment auf `dreamloud.de` oder `www.dreamloud.de`; kein Import realer Daten, kein geteilter Datenbankbenutzer, kein Übernehmen von Dreamloud-Secrets; kein ungeprüftes produktives SMTP. Wenn das Hosting keine eigene Instanz und Datenbank zulässt, stattdessen einen gesonderten Staging-Vertrag oder flüchtige GitHub-Tests verwenden. Eine überprüfte Mail-Queue ersetzt noch nicht den tatsächlichen externen E-Mail-Empfang.
