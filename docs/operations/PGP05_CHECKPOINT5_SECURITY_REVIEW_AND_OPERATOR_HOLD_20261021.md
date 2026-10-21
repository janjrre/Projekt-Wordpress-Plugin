# PGP-05 Checkpoint 5 — Security Review / Live Evidence Hold
Date: 2026-10-21. Project: OrgaVela / UOP Core. Scope: draft PR #32.

## Safety decision
**Offline synthetic-only implementation: PASS (development test).**
**Production/livesite readiness: BLOCKED** pending independent review and operator-evidenced source identity.
No file from this plan may authorize real backup collection, production DB access, a GitHub Release writer, restore, scheduler, or WordPress mutation.
No credential values, actual DB names, WordPress config contents, IONOS customer IDs or non-public filesystem paths may be logged, committed or sent to ChatGPT.

## Independently grounded reference evidence
- IONOS official published **Webhosting ED25519 host fingerprint** (read-only documentation):
  `SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q`.
  Source: https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/
  This agrees with the literal pin in the existing `scripts/ci/orgavela-readonly-audit.sh`.
  It proves **documented expected key**, NOT that the currently selected private IONOS endpoint presented that key during this checkpoint.
- Existing historical **GitHub Actions read-only 'probe'** run on **2026-10-12**:
  https://github.com/janjrre/Projekt-Wordpress-Plugin/actions/runs/38264254555
  GitHub API reports completed/success and successful audit job steps. Historical success != current read-only proof.
- Operator evidence 2026-10-21: Domain target `WordPress`, OrgaVela IONOS Webspace Explorer shows `/public` containing WordPress `wp-admin`, `wp-includes`, `wp-config.php` (UI permissions 600), UOP Core plugin tree; IONOS-linked phpMyAdmin shows 52 InnoDB tables and UOP tables. Strong site/project evidence but no independent effective DB configuration match.
- Operator evidence: exactly one visible MU-plugin `wp-content/mu-plugins/automation-by-installatron.php`. WP-CLI documentation confirms `--skip-plugins` **does not skip MU plugins**:
  https://developer.wordpress.org/cli/commands/plugin/list/

## Security review observations
1. Retired PR32 `scripts/ci/orgavela_pgp05_readonly_remote_collect.sh` is now an unconditional denial, exit 78. It does **not** run PHP, WP-CLI or SSH. This is a safety stop, **not a live identity probe**.
2. `orgavela_pgp05_static_identity_v2.py` is CLI-gated `--synthetic-only`. The scanner pins opened directories with `dir_fd`, `O_NOFOLLOW`, and when available `O_PATH`; checks selected WordPress-looking filenames and MU-file count; reads only a fixed synthetic marker. It has neither an authenticated SSH transport nor an independent domain/database identity proof.
3. `orgavela_pgp05_static_receipt_contract.py` rejects real/production flags, unknown fields, duplicate keys, Symlink/FIFO/Hardlink receipts and some change-in-read races. This contract is only a **synthetic receipt validator**.
4. The historical `scripts/ci/orgavela-readonly-audit.sh` on `main` is **not superseded by the new PR32 stub**. It still invokes WP-CLI in its `probe/schema/backup-preflight` operations and has alternate root fallback logic. **Do not use it as the PGP05 live validator** without redesign and separate approval.
5. Static file existence and mode checks are insufficient to prove ownership, active vHost mapping, current SSH chroot, an effective `DB_NAME` match, consistent snapshot, or restore success. `assess(..., synthetic=False)` is an internal review primitive, not an approved entry point. Linux path traversal is not a fully atomic snapshot; do not assert race freedom.
6. Existing 79/79 local tests and CircleCI Free build #33 successful on PR32 SHA `1a825c59046142a6c57a7a05dd2a3d7ac0a495d2`. Additional CircleCI Free build #34 independently tested stacked PGP05 synthetic PR28-31 branch. Green synthetic CI is not an independent production security assessment.
7. **Independent reviewer signoff: OPEN.** This author-side audit is not an external independent signoff.

## Required operator-only IONOS evidence (no mutations)
A. Within the existing separate OrgaVela IONOS contract navigate to Webspace Explorer > `public`. Click the path shown above the file list (the `Pfad:` control) to open **Pfaddetails / Absoluter Pfad**. The operator should view and record the actual absolute path privately; in screenshots for review obscure personal/contract identifiers and any unrelated hosted sites. Do not upload or execute PHP "path-info" scripts. Official IONOS UI method:
https://www.ionos.de/hilfe/hosting/php-fuer-web-projekt-verwenden/absoluten-pfad-document-root-ermitteln/
B. Check the existing SSH account under OrgaVela's own IONOS FTP/SSH settings, note privately its project binding and whether the account is limited to the intended contract. **No passwords or tokens in chat/repository.** Do not reuse a Dreamloud account.
C. At the **actual** SSH handshake, only after a scoped operator Go and a security-reviewed transport, compare the presented ED25519 public key fingerprint with the official pin above, then verify the SSH shell namespace resolves exactly to the independently operator-viewed OrgaVela root. Never trust newly discovered keys automatically.
D. Effective WordPress database relation: require a **private independent comparison** of the actual WordPress DB selector with the IONOS OrgaVela MariaDB schema. A match of 52 tables alone is insufficient. Avoid copying `wp-config.php` or using publicly reversible hashes of low-entropy DB identifiers. When dynamic PHP environment values cannot be resolved without executing bootstrap code, **REFUSE** and choose safer provider/owner-assisted verification. Do not include DB host/name in check receipts.
E. Once A-D and independent code review are completed, design/approve one **non-bootstrapping**, allowlisted, minimal-rights remote metadata run with strict credential isolation and encrypted transport. Operator-go must be explicit for its exact SSH host/account/path/commands. Public CI logs reveal only `PASS/REFUSED`; no raw stdout/stderr or credentials.
F. The first real metadata-only receipt can be accepted for Checkpoint 5 **only** after its exact source, operator approval, hostkey, root and DB mapping are verified and recorded privately; it still cannot approve real backup capture.

## Exact Checkpoint-5 acceptance accounting
Ten fixed gates, currently **6/10** formal:
1. [x] IONOS project/domain screenshots
2. [x] /public WordPress structure and wp-config UI-mode evidence
3. [x] phpMyAdmin 52/52 InnoDB UOP table metadata
4. [x] MU plugin filename inventory
5. [x] synthetic non-bootstrapping scanner + tests
6. [ ] independent production security review and live-scope scanner design
7. [x] PR32 branch implementation + provider CircleCI Free green test (not merge)
8. [ ] current SSH host/account/namespace and real domain DocumentRoot independently bound
9. [ ] live WP effective DB ↔ IONOS database independently privately matched
10. [ ] owner-approved real metadata-only run + sanitized evidence, final gate signoff

**Four formal gates remain.** Nothing above changes V1's historical 62/71 coding packages, M0-M7 0/8 accepted, P4 0/4 accepted, release R01-R18 0/18 green.

## After Checkpoint 5: overall PGP-05 remains unfinished
(1) coherent real WordPress files+MariaDB capture design and fail-closed source;
(2) separately operator-approved real source capture plus local OpenPGP ciphertext before offsite transit;
(3) independently authorized ciphertext-only immutable GitHub Release writer and digest readback;
(4) isolated, safely disconnected real WordPress+MariaDB restore and owner PGPony validation;
(5) approved scheduler, RPO/RTO, retention, alerts, key recovery and ongoing cost boundaries.
