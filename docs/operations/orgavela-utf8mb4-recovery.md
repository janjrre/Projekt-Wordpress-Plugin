# OrgaVela WordPress.com – sicherer UTF8MB4-Korrekturpfad

**Stand:** 2026-10-12 · ausschließlich UOP Core / [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12).
**Ziel:** Vom tatsächlich aktiven `latin1`-WordPress-Verbindungszeichensatz zu einer **persistent unterstützten** `utf8mb4`-Installation übergehen, ohne Bestandsdaten zu beschädigen oder UOP-Schutzgates zu umgehen.

## Gesicherte Fakten

| Prüfung | Ergebnis |
| --- | --- |
| Website | `https://orgavela.wpcomstaging.com` (Atomic, Coming Soon) |
| WordPress / PHP / DB | 7.1.3 / 8.4.26 / MariaDB 11.8.9 |
| Server-`utf8mb4` | Unterstützt (`information_schema.CHARACTER_SETS`, `$wpdb->has_cap('utf8mb4')`) |
| WordPress-`$wpdb->charset` | `latin1` |
| Verbindungs-/Clientzeichensatz | `latin1` / `latin1` |
| WordPress-Collation | `latin1_swedish_ci` |
| UOP Core | ZIP installiert, Aktivierung aus gutem Grund bei `utf8mb4_required` fehlgeschlagen; **inaktiv** |
| Backup | WordPress.com meldete zuletzt einen erfolgreichen Backupversuch 2026-10-12 08:24 UTC, aber **keine erfolgreiche Restore-Probe nachgewiesen** |

**Nicht bewiesen:** `DB_CHARSET`-Konstantenwert in `wp-config.php`, Tabellen-/Spaltenzeichensätze, freie SFTP-/SSH-Berechtigung oder ob WordPress.com Änderungen am DB-Clientzeichensatz in dieser Hostingkonfiguration dauerhaft unterstützt.

## Konkretes, sicheres Vorgehen (in Reihenfolge)

1. **Unverändert lassen:** UOP inaktiv; keine echten Teilnehmer anlegen; Website im Coming-Soon-Modus belassen. Nicht die UOP-Kompatibilitätsprüfung abschalten, keine globalen `SET NAMES`-Hooks im Plugin einführen.
2. **Hostingfähigkeit klären:** WordPress.com → Hosting-Dashboard → OrgaVela → Einstellungen → Server; auf vorhandene **kostenfrei** verfügbare SSH/SFTP- und Datenbanktools prüfen. Falls im aktuellen Tarif gesperrt: **keinen kostenpflichtigen Upgradeprozess starten**. WordPress.com-Support um den unterstützten dauerhaften UTF8MB4-Verbindungsmodus für den bestehenden Standort bitten.
3. **Nur lesen:** Falls SSH/WP-CLI oder phpMyAdmin tatsächlich bereits verfügbar ist, ohne Schreibzugriff auf Daten zuerst prüfen:
   - `wp config get DB_CHARSET` und `wp config get DB_COLLATE` (keine DB-Zugangsdaten/Geheimnisse ausgeben).
   - `wp db query "SELECT @@character_set_client, @@character_set_connection, @@collation_connection"`
   - `wp db query "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"`
   - `wp db query "SELECT TABLE_NAME,COLUMN_NAME,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL ORDER BY TABLE_NAME,COLUMN_NAME"`
   - Bestehende Unicode-Inhalte und etwaige gemischt kodierte Werte beachten; Ergebnisse nur auf geeignete, vertrauliche Ablage, nicht unredigiert in öffentliches GitHub kopieren.
4. **Recovery-Gate:** Vor irgendeiner Änderung einen **aktuellen, wiederherstellbaren** Snapshot/Backup und dessen Restore-Tauglichkeit bestätigen. Die bloße Existenz eines Backups reicht nicht als Restore-Test. Vorgehen und Zuständigkeit von WordPress.com berücksichtigen.
5. **Host-unterstützte Umstellung:** Erst nach den obigen Ergebnissen einen expliziten, für vorhandene Daten geeigneten Migrations-/Verbindungsplan festlegen. Wenn die DB-Tabellen latin1 sind, ist eine pauschale Änderung von `DB_CHARSET` allein **keine nachgewiesene sichere Konvertierung**. Je nach Ist-Daten können Tabellen-/Spalten-Konvertierung, Neubau der noch leeren isolierten Website oder vom Provider durchgeführte Umstellung sinnvoller sein. Nicht ohne geprüften Plan und Recovery-Gate ausführen.
6. **Nachweis an frischer HTTP-Anfrage:** `$wpdb->charset === 'utf8mb4'` **und** `@@character_set_connection = 'utf8mb4'` **und** `@@character_set_client = 'utf8mb4'`, kompatible UOP-Tabellenkollationen, Sonderzeichen/Emoji-Roundtrip und unbeschädigte WordPress-Bestandsdaten. Erst dann UOP-V1+V2-Installation/Schemahealth und synthetische E2E-Golden-Paths.
7. **Bei Fehlschlag:** Site wiederherstellen, Ursache protokollieren, UOP inaktiv belassen und Issue #12 nicht schließen.

## Aktuelle Bediengrenze

Der aktuell verbundene WordPress.com-Connector erlaubt Plugin-Upload und -Aktivierung sowie allgemeine WordPress-Einstellungen, **nicht** die Bearbeitung von `wp-config.php`, SQL-Verbindungsparametern oder Datenbanktabellen. Die fehlende Schnittstelle ist keine Berechtigung, über ein eigenes PHP-Plugin heimlich Core-Konfiguration zu verändern. Ein vorschneller Betrieb mit `latin1` wird nicht als Erfolg gemeldet.

## Betreiberhinweis

WordPress.com dokumentiert [Servereinstellungen](https://wordpress.com/support/hosting-configuration/), [SFTP-Zugriff](https://wordpress.com/support/sftp/), [SFTP/SSH-Problemlösung inklusive wp-config.php](https://wordpress.com/support/sftp/troubleshooting-sftp/) und [Datenbankzugriff](https://wordpress.com/support/database/). Die dort genannten Optionen können tarifabhängig sein; vor Freischaltung keinen Upgradevertrag abschließen.

**Freigabe:** Eigenständige kostenfreie Entwicklungs- und Stagingmaßnahmen ausdrücklich erlaubt, **keine** kostenpflichtigen Verpflichtungen, **kein** Zugriff auf Dreamloud.
