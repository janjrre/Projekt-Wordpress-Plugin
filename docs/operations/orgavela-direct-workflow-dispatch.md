# OrgaVela – GitHub Actions direkt starten

**Stand:** 12.10.2026. Gilt ausschließlich für `janjrre/Projekt-Wordpress-Plugin`, `https://orgavela.com`, und die getrennte IONOS-Deploymentumgebung.

## Arbeitsauftrag
Der Betreiber möchte, dass der Assistent GitHub Actions für sichere Probe-Läufe und später freigegebene UOP-Plugininstallationen selbst anstoßen kann, ohne dass der Betreiber jedes Mal im Actions-UI auf **Run workflow** klicken muss.

## Was technisch geht

GitHub unterstützt den direkten Start bestehender, für `workflow_dispatch` konfigurierter Workflows über die [GitHub Actions REST API](https://docs.github.com/en/rest/actions/workflows#create-a-workflow-dispatch-event) oder die GitHub CLI `gh workflow run`. Ein API-/GitHub-App-Zugang braucht für dieses Repo `Actions: write`.

**Die hier angebundene ChatGPT-GitHub-App stellt derzeit keine neue Workflow-Dispatch-Aktion bereit.** Sie kann Runs lesen und bestimmte existierende fehlgeschlagene oder abgebrochene Jobs erneut starten, aber keinen neuen frei parametrisierten Run über `workflow_dispatch` erstellen. Allein zusätzliche Berechtigungen für eine App reichen nicht, solange die konkrete Aktion nicht in der verbundenen Schnittstelle angeboten wird.

Eine frühere Dateierstellung unter `.github/deploy-requests/orgavela-sftp.json` wurde von einer Ausführungssicherheitsprüfung blockiert. Solche Sperren sind verbindlich und dürfen nicht durch versteckte Issue-Kommentare, alternative Events, indirekte Requests oder andere Auslöser umgangen werden.

## Implementierter CLI-Einstieg für einen ausdrücklich autorisierten Client

Skript: [`bin/dispatch-orgavela-workflow.sh`](../../bin/dispatch-orgavela-workflow.sh).

Das Skript kann **nur** die bestehenden OrgaVela-Workflows starten und akzeptiert ausschließlich:

```bash
# Rein lesender Verbindungstest
bash bin/dispatch-orgavela-workflow.sh sftp probe
bash bin/dispatch-orgavela-workflow.sh ssh probe

# Getrennte spätere Installations- und Upload-Aufträge, nur nach bestandenem Sicherheitsgate
bash bin/dispatch-orgavela-workflow.sh sftp install orgavela.com <VERIFIZIERTE_SHA256>
bash bin/dispatch-orgavela-workflow.sh ssh upload orgavela.com <VERIFIZIERTE_SHA256>
```

Voraussetzungen: `gh`-CLI, eine **privat authentifizierte** GitHub-Sitzung mit `Actions: write` für **nur** `janjrre/Projekt-Wordpress-Plugin`. Der Zugang darf nicht über öffentliche Repository-Dateien, Workflow-Logs oder den Chat übermittelt werden. Die Berechtigung muss in dem Ausführungskontext gelten, in dem das Skript tatsächlich läuft. Dieser Chat hat momentan keinen solchen ausführbaren authentifizierten CLI-Kontext.

Der Starter fügt **keine neuen Secret-/Token-Kontrollen** hinzu. Er delegiert alle Sicherheitskontrollen an die bereits geprüften GitHub Actions:
- Branch nur `main`, Ziel strikt `orgavela.com`.
- IONOS-Zugangsdaten ausschließlich als GitHub-Environment-Secrets.
- Gepinnte Hostidentität, geprüfte Paketprovenienz und SHA-256.
- Probe vor Installation; Dateiinstallation ohne automatische Plugin-Aktivierung oder DB-Migration.
- Keine Dreamloud-Zugriffe oder kostenpflichtigen Dienste.

**Wichtig:** Erfolgreicher Dispatch heißt lediglich, dass GitHub den Start angenommen hat – **nicht**, dass Verbindung oder Plugininstallation erfolgreich waren. Nach jedem Start die Run-ID, Jobs, Logs und den tatsächlichen Zustand von OrgaVela unabhängig kontrollieren.

## Wege zur tatsächlichen Selbstbedienung

1. **Work-Modus / Cloud Browser:** Ein Nutzer kann in Work den GitHub-Account autorisieren und den Workflow per GitHub-Oberfläche starten lassen. In dieser regulären Chat-Sitzung ist eine solche interaktive Browseranmeldung nicht als Tool verfügbar.
2. **Passender GitHub-Connector oder eigener enger MCP-Gateway:** Der Connector müsste die bestehende `workflow_dispatch`-API direkt anbieten, mit Repository- und Workflow-Allowlist, starker Authentifizierung, beschränkten Operationen und Audit-Trail. Ein eigener Server müsste zuvor sicher gehostet und ausdrücklich verbunden werden; nicht als versteckter Endpunkt in WordPress integrieren.
3. **Autorisierter CLI-Kontext:** Auf einem bereits autorisierten Computer/Work-Lauf mit `gh` und GitHub-Actions-Schreibberechtigung das obige Skript verwenden. Das Skript selbst verschafft keine Berechtigungen.

**Nächster konkret ausführbarer Schritt:** In GitHub einmal `Run workflow` → `deploy-orgavela-sftp.yml` → `probe` starten, falls weder Work-Modus noch Actions-fähiger Connector verfügbar ist. Das ist gegenwärtig ein einmaliger Übergangsschritt, nicht die angestrebte Dauerlösung.

Keine neuen Verträge oder kostenpflichtigen Dienste ohne separate Zustimmung; keine Secrets in die Dokumentation.
