#!/usr/bin/env bash
# Manually gated synthetic encrypted GitHub Release E2E. NEVER production data.
set -euo pipefail
set +x
umask 077

fail() { echo "Synthetic GitHub Release E2E: $1" >&2; exit 1; }
[[ "${ORGAVELA_GITHUB_RELEASE_E2E_APPROVED:-}" == YES ]] || fail 'manual test approval missing'
[[ "${CIRCLE_BRANCH:-}" == test/orgavela-github-release-o03 ]] || fail 'invalid branch'
[[ "${CIRCLE_PROJECT_REPONAME:-}" == Projekt-Wordpress-Plugin ]] || fail 'invalid project'
[[ "${CIRCLE_PROJECT_USERNAME:-}" == janjrre ]] || fail 'invalid project owner'
[[ "${GH_TOKEN:-}" != '' ]] || fail 'restricted GitHub token missing'
for key in SFTP_HOST SFTP_PASSWORD SFTP_USER SSH_PRIVATE_KEY GOOGLE_DRIVE_TOKEN_JSON DUPLICATI_GOOGLE_DRIVE_AUTH_ID; do
  [[ -z "${!key:-}" ]] || fail "unexpected production/Drive credential: $key"
done
for cmd in gpg jq gh tar sha256sum mktemp; do
  command -v "$cmd" >/dev/null || fail "missing $cmd"
done
root="$(mktemp -d)"
trap 'rm -rf -- "$root"' EXIT
export GNUPGHOME="$root/gnupg"
mkdir -m 700 "$GNUPGHOME" "$root/work" "$root/input"
printf 'OrgaVela synthetic archive for GitHub Releases transport test.\n' > "$root/input/data.txt"
printf 'The contents contain no WordPress or MariaDB data.\n' >> "$root/input/data.txt"
tar -cf "$root/input.tar" -C "$root/input" data.txt
gpg --batch --pinentry-mode loopback --passphrase '' --quick-generate-key \
  'OrgaVela Synthetic Backup <synthetic@example.invalid>' rsa2048 encr 0 >/dev/null 2>&1
backup_id="orgavela-synthetic-circleci-${CIRCLE_BUILD_NUM:?}"
archive="$root/work/$backup_id.tar.gpg"
gpg --batch --yes --trust-model always --recipient synthetic@example.invalid \
  --output "$archive" --encrypt "$root/input.tar"
# Independently validate synthetic OpenPGP recovery with the ephemeral private key.
gpg --batch --yes --output "$root/recovered.tar" --decrypt "$archive" >/dev/null 2>&1
cmp -- "$root/recovered.tar" "$root/input.tar" || fail 'synthetic OpenPGP decrypt mismatch'
export BACKUP_WORK="$root/work" BACKUP_ID="$backup_id"
export ENCRYPTED_BACKUP_FILE="$archive"
export EXPECTED_ENCRYPTED_SHA256="$(sha256sum "$archive" | cut -d' ' -f1)"
export GITHUB_BACKUP_REPOSITORY='janjrre/orgavela-backups'
export ORGAVELA_SYNTHETIC_ONLY=YES
bash scripts/ci/orgavela-upload-github-release-synthetic.sh
jq -e '.uploaded == true and .published == true and .readback_verified == true' \
  "$BACKUP_WORK/github-release-transport-status.json" >/dev/null
# Verify the newly published release via its immutable NUMERIC REST ID.
# A tag-based lookup can lag immediately after publication; the ID is stable.
report="$BACKUP_WORK/github-release-transport-status.json"
release_id="$(jq -er '.release_id | select(type == "number" and . > 0)' "$report")" \
  || fail 'numeric published release ID missing from transport report'
published_metadata="$(gh api "repos/$GITHUB_BACKUP_REPOSITORY/releases/$release_id")" \
  || fail 'published release cannot be inspected by numeric REST ID'
jq -e --arg tag "backup-$BACKUP_ID" --argjson rid "$release_id" \
  '.id == $rid and .tag_name == $tag and .draft == false and .immutable == true' \
  <<< "$published_metadata" >/dev/null \
  || fail 'published release metadata / immutable=true validation failed: NOT accepted'

# Prove an INDEPENDENT restoration from the published remote Release, not only
# the readback performed by the original uploader with its local source present.
# The private key belongs ONLY to this artificial dataset; production recovery
# secrets must never be placed in a CircleCI context.
gpg --batch --armor --export-secret-keys synthetic@example.invalid > "$root/synthetic-test-recovery-key.asc"
rm -f -- "$archive" "$root/input.tar" "$root/recovered.tar"
rm -rf -- "$root/input"
remote_only="$root/independent-remote"
mkdir -m 700 "$remote_only"
# This deliberately uses the PUBLICATION/TAG-based GitHub CLI path rather
# than the numeric REST path used by the transport, verifying a second
# consumer. The newly created published tag may need brief propagation time.
restored_download=NO
for attempt in 1 2 3 4 5 6 7 8; do
  rm -rf -- "$remote_only"
  mkdir -m 700 "$remote_only"
  if gh release download "backup-$BACKUP_ID" --repo "$GITHUB_BACKUP_REPOSITORY" \
      --dir "$remote_only" >/dev/null 2>&1; then
    restored_download=YES
    break
  fi
  if (( attempt < 8 )); then sleep 2; fi
done
[[ "$restored_download" == YES ]] || fail 'independent restore cannot download published release by tag'
remote_archive="$remote_only/$BACKUP_ID.tar.gpg"
[[ -s "$remote_archive" ]] || fail 'independent restore missing encrypted backup'
[[ "$(sha256sum "$remote_archive" | cut -d' ' -f1)" == "$EXPECTED_ENCRYPTED_SHA256" ]] \
  || fail 'independent restore encrypted SHA-256 mismatch'
(
  cd "$remote_only"
  sha256sum --check --status "$BACKUP_ID.tar.gpg.sha256"
) || fail 'remote checksum sidecar invalid'
jq -e --arg id "$BACKUP_ID" --arg sha "$EXPECTED_ENCRYPTED_SHA256" \
  '.synthetic == true and .contains_production_data == false and .backup_id == $id and .archive_sha256 == $sha' \
  "$remote_only/manifest.json" >/dev/null || fail 'remote synthetic manifest mismatch'
export GNUPGHOME="$root/separate-recovery-gnupg"
mkdir -m 700 "$GNUPGHOME"
gpg --batch --import "$root/synthetic-test-recovery-key.asc" >/dev/null 2>&1 \
  || fail 'synthetic recovery key import failed'
rm -f -- "$root/synthetic-test-recovery-key.asc"
gpg --batch --yes --output "$root/independent-recovered.tar" --decrypt "$remote_archive" >/dev/null 2>&1 \
  || fail 'independent remote OpenPGP recovery failed'
tar -tf "$root/independent-recovered.tar" | grep -Fxq data.txt \
  || fail 'synthetic restored archive missing data.txt'
actual="$(tar -Oxf "$root/independent-recovered.tar" data.txt)"
expected="$(printf 'OrgaVela synthetic archive for GitHub Releases transport test.\nThe contents contain no WordPress or MariaDB data.\n')"
[[ "$actual" == "$expected" ]] || fail 'independent synthetic restore content mismatch'

# Mark the report true only after successful immutable-flag verification and a
# distinct full remote download, decryption and byte-exact synthetic recovery.
temp_status="$root/remote-restore-status.json"
jq '.immutable_verified = true | .independent_restore_tested = true | .restore_scope = "synthetic_only"' \
  "$BACKUP_WORK/github-release-transport-status.json" > "$temp_status"
mv -f -- "$temp_status" "$BACKUP_WORK/github-release-transport-status.json"
echo 'PASS: synthetic immutable GitHub Release, remote SHA-256 readback and independent decrypted restore'
