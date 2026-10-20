# W0/O03 – GH-06 native age GitHub Release Transport (20.10.2026)

Status: Draft / noch keine Live-GitHub-Release-Aktion oder GH-06-Abnahme. Neue Arbeiten auf einem von GH-05 Draft PR #20 abgezweigten Branch. V1-Masterplan-Nummerierung unverändert.

## Übernahme der funktionierenden GH-04-/Dreamloud-Prinzipien

Historischer GH-04-Transport wurde mit temporärem OpenPGP-Schlüssel, privatem GitHub Release, zweifachem vollständigen Asset-Readback und immutable-Flag erprobt; nicht als age-Protokoll nachweisen. GH-05 wurde mit nativer age-Verschlüsselung synthetisch in CircleCI-Pipeline #188, Commit 590d530686356d641774a27ff4004aa27fd063aa, Jobs #4 + #5 grün ausgeführt. Keine tatsächlichen Backupdaten.

Die GH-06-Skripte übertragen diese Kontrollarchitektur auf native age X25519 / .tar.age und bleiben ausdrücklich künstlich:
- scripts/ci/orgavela-upload-github-release-age-synthetic.sh: ausschließlich vorverschlüsselten künstlichen age-Ciphertext mit SHA256 prüfen, echtes Private-Repo janjrre/orgavela-backups verifizieren, bestehende Tags und Entwürfe nicht überschreiben, als GitHub Draft 3 Assets hochladen, jedes vollständig per numerischer ID herunterladen und lokal per SHA256 vergleichen; veröffentlichen per numerischer Release-ID und erneut alle drei Bytes readbacken. Der Writer nimmt keine Betreiber-Recovery-Schlüssel an.
- scripts/ci/orgavela-github-release-age-remote-e2e.sh: nur künstlichen Text, flüchtiges Test-X25519-Keypair, age encrypt/decrypt, nach Publish separate Tag-basierte Downloadprobe, Integritätskontrolle, immutable=true und synthetische Wiederherstellung; kein Original-AgePony-Key und keine echte Quelle.
- scripts/ci/test_orgavela_age_release_offline.py: 13 Negativ-/Syntax-/Fake-gh-Testfälle ohne echte GitHub-API, Token oder Live-Daten. Diese neuen Tests sind noch NICHT bei einem Provider ausgeführt.
- .circleci/config.yml: enable_gh06_age_release_offline boolean default false, genau ein branch-gefilterter geheimnisfreier Testjob gh06-age-release-offline-qa. Alle vorherigen GH05-/PR17-Jobs bleiben enthalten. Kein GH06 token-geschützter Remotejob, kein Cron, kein produktiver Modus.

## Nächste Prüfungen

A: Manuell einen einzigen Offline-Test nach bestätigtem freien Kontingent starten: Config- und Checkout-Branch feat/gh06-synthetic-age-release-20261020; Boolean enable_gh06_age_release_offline=true; alle anderen Flags false. Erwartet genau gh06-age-release-offline-qa mit Fake-gh. Echte Job-ID, Quell-SHA und Ergebnisse unabhängig abgleichen.

B: Noch NICHT freigeben: echter synthetischer GitHub-Release-E2E. Dafür vorab separaten Testbranch test/orgavela-age-release-o03 schützen, eigenen ausschließlich auf OrgaVela und Branch begrenzten CircleCI Writer-Context prüfen, neuen kurzlebigen Fine-grained GitHub Token ausschließlich für privates Repo orgavela-backups mit Contents Read/Write über Betreiber direkt in CircleCI hinterlegen, Fork-PR-Secretweitergabe deaktiviert belassen. Erst nach einzelnem Sicherheits-/Kosten-Check darf der künstliche E2E als gesonderter manuell gegateter Job integriert werden. Keine Credentials in öffentliche GitHub-Dokumente, Chat, Logs.

C: Echte Sicherungen bleiben gesperrt: GH-03 Owner-Key aktuell nur in AgePony und lokaler PDF auf ursprünglichem Gerät, keine unabhängig bestätigte Offline-Kopie-2-Recovery. GH-05 echte konsistente WordPress-/MariaDB-Quelle, GH-06 produktiver age Ciphertext-only Writer, GH-07 isolierter echter Restore und GH-08 täglicher Zeitplan sämtlich offen. Synthetischer Ein-Job-E2E mit Wegwerf-Privatekey ist kein produktives Vertrauensmodell.

## Evidenzgrenzen

Alle GH-06-Funktionen bislang Code/Draft. Fehlgeschlagene Erstfassungen des Shell-Formatkonverters wurden vor Tests korrigiert (age-Header-Newline, Asset-Endung, synthetische ID-Expansion, age-Statusschema). Aktuell noch kein bash -n/Unittest-/CircleCI-Ergebnis für den GH06-Branch behaupten. GH05 #188 wird NICHT wiederholt. Keine GitHub-Releases, kein Upload, kein echter IONOS-Zugriff, kein Dreamloud-Write und keine zusätzlichen Kosten.

Bezug: Issue #15, #16; GH03 ADR; GH05 Draft PR #20; GH04 OpenPGP Draft PR #19. V1 bleibt 62/71 historische Coding-Pakete, 0/8 M-Abnahmen, P4 0/4, R01–R18 0/18, Integration ausstehend.