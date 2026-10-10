# M6-03 – Private Wartelistenangebote für Gastkontakte

Status: Im Entwicklungsbranch implementiert, standardmäßig deaktiviert, nicht gemergt oder deployed.

## Ablauf

1. Eine verifizierte Gastanmeldung steht nach dem bestehenden FIFO-Regelwerk auf der Warteliste.
2. Ein autorisierter Manager storniert einen Platz und startet die bestehende CapacityLifecycleService-Operation offer_next oder cancel_and_offer_next. Die normale Selbststornierung erzeugt zunächst nur ein geheimnisfreies Promotion-Signal; ein Manager stößt das echte Angebot an.
3. Unter dem Kapazitäts-Bucket-Lock werden Registrierung, aktiver Event, aktuelle Zulassung, Kontaktverifizierung und freie Kapazität geprüft. Ein 256-Bit-Token wird mit random_bytes(32) erzeugt. Nur sein SHA-256-Hash wird beim Angebot gespeichert. Das Angebot läuft nach 48 Stunden ab.
4. Bei eingeschaltetem privaten Versand müssen eine überprüfte Kontakt-E-Mail und ein öffentlicher, passwortfreier Veranstaltungspost vorhanden sein. Held Claim, Registrierungszustand und eine immutable M5-Mailnachricht mit dem Angebot werden in **einer** Datenbanktransaktion persistiert. Schlägt die Vorbereitung der Mail fehl, wird auch die Platzreservierung vollständig zurückgerollt.
5. Nach dem Commit übernimmt der existierende Action-Scheduler-/M5-Mail-Worker die Zustellung. Die Job-Argumente enthalten nur Organisations-ID und Mail-UUID, keine Bearer-Geheimnisse. Der M5-Sweeper darf nur noch nicht versendete, eindeutig queued-Nachrichten wieder aufnehmen.
6. Der verschickte HTTPS-Link enthält die Angebots-ID und das Geheimnis ausschließlich in einem Browser-Fragment. Die Landing Page entfernt das Fragment sofort aus der Adresszeile und verwendet keine Referrer-Weitergabe.
7. Erst durch den bewussten Klick auf „Platz annehmen“ erfolgt ein POST an /wp-json/uop/v1/waitlist-offers/guest-accept. Der Server prüft One-Time-Hash, Frist, Organisation, Gastquelle, aktuell verifizierte E-Mail, aktiven Event und Eligibility unter dem Bucket-Lock. Er schreibt den normalen akzeptierten Angebots-/Registrierungszustand und den bestätigten Claim; es entsteht kein WordPress-Konto.
8. Syntaktisch gültige, bereits verwendete oder fremde Tokens erhalten eine generische 202-Empfangsbestätigung ohne Status- oder Identitätsauskunft.

## Betriebsfreigabe

Die getrennte Operator-Option bleibt standardmäßig aus. Nur nach echter HTTPS- und Mail-Transport-Abnahme:

    add_filter( 'uop_waitlist_offer_enabled', '__return_true' );

Voraussetzungen: korrekter HTTPS-Reverse-Proxy, Action Scheduler, M5 Mail Worker, funktionierende SMTP- oder wp_mail-Konfiguration, SPF/DKIM/DMARC, Mailbox-Empfangstest sowie Monitoring für queued, failed und ambiguous sending.

**Nicht übersehen:** wp_mail-Erfolg bestätigt nicht, dass die E-Mail im Zielpostfach angekommen ist. Ein ergänzendes WAF-/Edge-Ratelimit wird empfohlen. WordPress Transients schützen den öffentlichen Accept-Endpunkt zusätzlich, sind aber keine verteilte atomare Sperre.

## Sicherheits- und Funktionsgrenzen

- Ein nicht verifiziertes oder fehlendes Kontaktpostfach verhindert bei aktiviertem Versand eine neue Platzreservierung; es verbleibt kein unzustellbares Angebot.
- Gast-Bearer-Links vermitteln ausschließlich die eng begrenzte, nachweislich verifizierte Angebotsannahme. Account- und Delegationsrechte bleiben für reguläre Portal-Commands verpflichtend.
- Profilbasierte Eligibility muss weiterhin autorisiert ermittelt werden können. Darf Actor 0 diese Fakten nicht lesen, wird die Gastannahme verweigert; kein Bypass.
- Automatische Promotion nach Selbststornierungen, der vollständige Gast-Anmeldebildschirm und alle M6-10-Golden-Paths sind noch nicht abgeschlossen.
- Das Mailarchiv enthält private Aktionslinks. M5-Zugriffsschutz, Retention und Löschung müssen vor dem Produktivbetrieb separat geprüft werden.
- Änderungen sind nur in Draft-PR #6. Kein Merge, Deployment oder automatisches Opt-in.

## Testabdeckung

Die Integrations-Tests prüfen einmalige Gastannahme, fremde/ungültige Bearer, HTTPS-Verweigerung, organisationsbezogene Sperren, FIFO-Zustand, geheimnisfreie Audit-/Outbox-Ereignisse und Rollback bei fehlendem verifiziertem Kontakt. Den finalen CI-Status stets gegen PR #6 prüfen.
