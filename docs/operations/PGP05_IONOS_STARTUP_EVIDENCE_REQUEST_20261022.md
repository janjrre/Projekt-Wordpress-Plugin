# Superseded as mandatory prerequisite — 2026-10-22

This support request is now OPTIONAL targeted diagnosis for actual unusual
startup evidence. No need to send it for normal bounded metadata review.
See [revised scope and independent decision](PGP05_DREAMLOUD_REUSE_AND_BOUNDED_METADATA_DECISION_20261022.md).
Nothing was sent. Historical prepared draft follows.

# PGP05 — provider startup evidence request (prepared, NOT SENT)
Date 2026-10-22. #27 / Draft #32; W0/O01/O03.
No live login, credentials, configuration change or support contact by assistant.

## Smallest next operator action
Use existing authenticated IONOS support for the OrgaVela hosting contract.
Select the correct contract/account privately in the provider interface; do not
paste login credentials, contract identifiers or full private paths into GitHub.
Ask for the effective noninteractive SSH startup behavior, not a generic SSH
tutorial. Do not buy an additional support/service plan.

Copyable question:

> Ich möchte auf meinem bestehenden OrgaVela-Webhosting einen einmaligen,
> ausschließlich lesenden Metadatencheck per SSH durchführen, ohne PHP,
> WordPress oder WP-CLI zu starten und ohne Dateien zu verändern.
>
> Bitte bestätigen Sie für mein bestehendes SFTP+SSH-Konto:
> 1. Welche Login-Shell und welches tatsächliche Home-Verzeichnis gelten?
> 2. Welche systemweiten und kontobezogenen SSH-/Shell-Startdateien, Wrapper oder
>    erzwungenen Kommandos können bei einem NICHT interaktiven,
>    passwortauthentifizierten SSH-Kommando ohne TTY ausgeführt werden,
>    bevor mein übergebenes Kommando beginnt? Sind eigene SSH-RC-/Shell-Dateien
>    oder übernommene Startup-Umgebungsvariablen dafür wirksam?
> 3. Werden in dieser Startkette PHP, WordPress, WP-CLI, Website-/Datenbankcode
>    Installatron oder eigene Jobs gestartet oder Website-Dateien verändert? Übliche
>    Authentifizierungs-/Zugriffsprotokolle sind vom Website-Schreibverbot ausgenommen.
> 4. Wie kann ich die tatsächlich wirksamen kontobezogenen Dateien vor dem
>    ersten SSH-Login über den Webspace Explorer ausschließlich lesen/prüfen?
>    Bitte keine Dateien, Rechte, Passwörter oder Einstellungen verändern.
>
> Das anschließend übergebene Kommando soll eine feste /bin/sh-Metadatenprüfung
> über /usr/bin/env -i starten; mir geht es ausdrücklich um Vorgänge DAVOR.
> Ich benötige keine Zugangsdaten und keinen neuen Dienst, sondern eine
> technische Auskunft zum bestehenden Konto.

## How to assess the answer
Provider confirmation of actual shell/home/global startup chain plus private,
read-only review of the applicable account-controlled hooks can resolve the
remaining startup question. A generic statement 'SSH works' cannot.
A filename listing alone cannot prove absent/hidden/unreadable/global hooks.
Depending on actual shell/effective policy, examples to consider include
.ssh/rc, .bashrc, .bash_profile, .bash_login, .profile and shell startup environment.
These are examples, **not a claim that each applies to this IONOS account**.
Do not download private keys, environment contents, wp-config or unrelated files.
Potential secret values in startup files stay private; redacted code must retain
enough structure to identify commands and transitive sourced files.
Do not change/delete hooks simply to make this check pass.
Do not substitute an unreviewed SFTP connection as automatically hook-free.
Only after accepted startup evidence: review exact metadata context restrictions,
private credential setup, separate approval and actual metadata run.
Current provider domain-to-document-root mapping is still a separate #8 proof.

## Why this is a real prerequisite
OpenSSH documents execution of applicable SSH RC files before a command and
execution of commands through the account login shell:
https://man.openbsd.org/sshd.8
That describes possible mechanisms, not the actual effective IONOS policy.
Official IONOS SSH/Webspace help examined on 2026-10-22 did not establish this
particular account's shell, global hooks or startup chain. Search coverage is
not proof that IONOS lacks those controls/documentation.
No client env clearing, hash, test or Boolean attestation can establish these
server-side facts. Do not set PGP05_STARTUP_REVIEWED=yes without actual evidence.

## Current acceptance
Build44 is contextless pinned-image/runtime/117-test evidence on exact code
c952645000b2954c04f1c0446c333392048065e6, not an IONOS login.
CP5 **7/10**; #6 startup/live security acceptance, #8 authenticated namespace/
domain binding and #10 accepted real metadata run remain open.
production_capture_allowed=false; no backup/deployment/merge permission follows.
