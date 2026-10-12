# UOP Core – WordPress-Organisations- und Teilnehmermanagement (OrgaVela)

Eigenständiges WordPress-Plugin für Organisationen, Personen, Rollen/Berechtigungen, Events, Formulare, Registrierungen, Kapazitäten/Wartelisten, Consent-Evidence, Datenschutz und Kommunikation. Entwicklungs-/Testwebsite: **https://orgavela.com** bei IONOS; **nicht** mit Dreamloud verwechseln.

## V1 – verbindlicher Projektplan und aktueller Fortschritt

**Stand 12.10.2026: UOP Core 0.1.0-alpha.2 auf OrgaVela installiert und aktiviert, V1 ausdrücklich noch nicht abgenommen.**

- **[Verbindlicher V1-Masterplan – 71 M0–M7-Pakete, M6-10/P4, 18 Release-Gates](docs/operations/V1_ACCEPTANCE_PLAN.md)** · [Zentrales Abnahme-Issue #15](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/15).
- **[Maschinenlesbarer Fortschritt – V1_PROGRESS.json](docs/operations/V1_PROGRESS.json)** und [ausführliche erläuternde Roadmap](docs/project/V1_MASTER_PLAN.md).
- **[Aktueller V1-Status mit Meilenstein- und Risikoübersicht](docs/project/V1_STATUS.md)**.
- **[Prüffälle, technische und fachliche V1-Abnahmematrix](docs/project/V1_ACCEPTANCE_MATRIX.md)**.
- [Fortlaufendes Arbeitsprotokoll](docs/operations/ASSISTANT_WORK_LOG.md) mit Commit-, CI- und Betriebsnachweisen.
- [Verbindliche Arbeits-/Reportingregeln für jede UOP-Folgeantwort](AGENTS.md).

**Wichtigste offene Voraussetzungen:** sieben gestapelte PRs #1–#6/#11 noch nicht in main gemergt, Issues #7–#10 zu Alters-/Vertreter-/Consent-Gates offen, aktuelle PR-#11-CI nicht vollständig grün, OrgaVela SQL-Schema-Health/Backup-Restore/Cron/Mail/Staging-Schutz/M7-Abnahme noch offen. „Alpha aktiviert“ ist **nicht** gleich „V1 veröffentlicht“.

## Architektur und Spezifikation

Die bindenden Quellen stehen unter [docs/specification](docs/specification/README.md). Rangfolge: Phase 3 Specification Freeze > Phase 2 V1-Spezifikation > Phase 1 Strategie. Der normierte Meilensteinplan umfasst **M0 bis M7**. M6-10 bezeichnet **das zehnte Arbeitspaket von Milestone 6**, kein eigenes M10.

## Projektarbeit und nachvollziehbare Übergaben

Vor jeder Arbeit an UOP Core gelten [AGENTS.md](AGENTS.md) und der aktuelle [Masterplan](docs/project/V1_MASTER_PLAN.md) sowie die [Statusseite](docs/project/V1_STATUS.md). Jede beauftragte Tätigkeit wird mit Auftrag, verifizierten Ergebnissen, Entscheidungszusammenfassung, Commits/Tests und nächsten Schritten in GitHub protokolliert. Am Ende jeder UOP-Antwort werden ein knapper, aktueller Fortschrittsblock und diese Plandokumente erneut verlinkt. Änderungen erfolgen nur während tatsächlich ausgeführter Arbeiten, nicht automatisch im Hintergrund.

**Umgebungen strikt getrennt:** alte WordPress.com-latin1-Umgebung [Issue #12](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/12) ist kein aktuelles UOP-Deploy-Ziel; die eigene IONOS-Umgebung OrgaVela und deren Deploymenthistorie siehe [Issue #13](https://github.com/janjrre/Projekt-Wordpress-Plugin/issues/13). Kein Dreamloud-Zugriff, keine kostenpflichtigen Verträge ohne gesonderte Freigabe und keine echten Teilnehmerdaten in Entwicklungsumgebungen.
