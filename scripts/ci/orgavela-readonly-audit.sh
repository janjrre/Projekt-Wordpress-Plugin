#!/usr/bin/env bash
# Explicit operator-authorized OrgaVela account from existing GitHub orgavela-ionos environment.
# Strictly bounded OrgaVela-only W0 audit. Does not deploy, change files,
# run migrations, export tables, execute arbitrary requests or print secrets.
set -euo pipefail
umask 077

mode="$1"
[[ "$mode" == 'sftp' || "$mode" == 'probe' || "$mode" == 'schema' ]] || exit 2
[[ "$GITHUB_REPOSITORY" == 'janjrre/Projekt-Wordpress-Plugin' ]] || exit 2
[[ "$GITHUB_REF" == 'refs/heads/main' ]] || exit 2

host="$IONOS_AUDIT_SSH_HOST"
user="$IONOS_AUDIT_SSH_USER"
root="$IONOS_AUDIT_WP_ROOT"
[[ -n "$host" && -n "$user" && -n "$SSHPASS" && -n "$root" ]] || {
  echo '::error::Missing existing orgavela-ionos SSH secret or IONOS WordPress path. No remote access attempted.'
  exit 2
}
[[ "$host" =~ ^[a-zA-Z0-9.-]+$ && "$user" =~ ^[a-zA-Z0-9._-]+$ ]] || exit 2
[[ "$root" =~ ^/[a-zA-Z0-9._/-]+$ && "$root" != *..* && "$root" != *dreamloud* ]] || exit 2

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
# A freshly discovered key is not implicitly trusted: pin to the ED25519
# fingerprint published at ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/.
ssh-keyscan -T 8 -p 22 -t ed25519 "$host" > "$tmp/known_hosts" 2>/dev/null || {
  echo '::error::SSH host key could not be fetched; no remote operation attempted.'
  exit 1
}
if ! ssh-keygen -lf "$tmp/known_hosts" | awk '{print $2}' | grep -Fxq 'SHA256:1gx2w8Rtv3wCgi7Jh8myf/KVd72cRQbow03UP8P095Q'; then
  echo '::error::SSH host fingerprint does not match official IONOS ED25519 reference.'
  exit 1
fi

# SFTP-only mode: verify the already operator-approved OrgaVela tree.
# Never download configurations/files or expose directory listings in logs.
# This proves SFTP access, NOT an interactive SSH shell or SQL access.
if [[ "$mode" == 'sftp' ]]; then
  {
    printf 'ls "%s/wp-admin"\n' "$root"
    printf 'ls "%s/wp-content/plugins/uop-core"\n' "$root"
  } > "$tmp/sftp-readonly.batch"
  if sshpass -e sftp -q -P 22 \
    -oBatchMode=no \
    -oPreferredAuthentications=password,keyboard-interactive \
    -oPubkeyAuthentication=no -oConnectTimeout=15 \
    -oStrictHostKeyChecking=yes \
    -oUserKnownHostsFile="$tmp/known_hosts" \
    -b "$tmp/sftp-readonly.batch" "$user@$host" \
    > "$tmp/sftp-out" 2> "$tmp/sftp-err"; then
    echo 'ORGAVELA_AUDIT_SFTP=verified'
    echo 'ORGAVELA_AUDIT_SHELL=not_checked'
    echo 'ORGAVELA_AUDIT_SCHEMA=not_checked'
    exit 0
  fi
  echo '::error::SFTP access or expected OrgaVela path was not verified. All raw connection output is withheld.'
  exit 1
fi

# Record BOTH streams privately and report only explicitly safe status keys.
if ! sshpass -e ssh -T -p 22 \
  -o StrictHostKeyChecking=yes \
  -o UserKnownHostsFile="$tmp/known_hosts" \
  -o PreferredAuthentications=password,keyboard-interactive \
  -o PubkeyAuthentication=no -o ConnectTimeout=15 \
  "$user@$host" "bash -s -- '$root' '$mode'" \
  > "$tmp/out" 2> "$tmp/err" <<'REMOTE'
set -euo pipefail
root="$1"
mode="$2"
[[ "$root" =~ ^/[a-zA-Z0-9._/-]+$ && "$root" != *..* && "$root" != *dreamloud* ]] || exit 2
[[ -f "$root/wp-config.php" && -d "$root/wp-admin" && -d "$root/wp-content/plugins/uop-core" ]] || exit 2
command -v wp >/dev/null || exit 3
home="$(wp --path="$root" --skip-plugins --skip-themes option get home 2>/dev/null)"
[[ "$home" == 'https://orgavela.com' || "$home" == 'https://orgavela.com/' ]] || exit 2
version="$(wp --path="$root" --skip-plugins --skip-themes plugin get uop-core --field=version 2>/dev/null)"
[[ "$version" == '0.1.0-alpha.2' ]] || exit 4

echo 'ORGAVELA_AUDIT_TARGET=verified'
echo 'ORGAVELA_AUDIT_SHELL=available'
echo 'ORGAVELA_AUDIT_PLUGIN_VERSION=alpha2'

if [[ "$mode" == 'probe' ]]; then
  echo 'ORGAVELA_AUDIT_PROBE=success'
  exit 0
fi

# WordPress CLI boot is restricted to --skip-plugins --skip-themes. It reads
# the installed UOP classes without registering their plugin lifecycle or
# calling migration runners. Never reveal DB name, table names, IDs, rows,
# WordPress configuration, participant fields or exception details.
php_code="$(cat <<'PHP'
require_once WP_PLUGIN_DIR . '/uop-core/vendor/autoload.php';
global $wpdb;
$prefix = $wpdb->prefix . 'uop_';
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $prefix)) {
    throw new RuntimeException('Unsafe site table prefix');
}
$health = \UOP\Infrastructure\Database\Installer::health();
$status = ($health['status'] ?? '') === 'good' ? 'good' : 'critical';
$physical_tables = (int) $wpdb->get_var(
    $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, %d) = %s',
        strlen($prefix), $prefix
    )
);
$rows = $wpdb->get_results(
    $wpdb->prepare(
        'SELECT id, status FROM %i WHERE slug = %s',
        $prefix . 'organizations', 'default'
    ),
    ARRAY_A
);
$stored_id = (int) get_option('uop_default_organization_id', 0);
$org_good = count($rows) === 1
    && $stored_id > 0
    && (int) $rows[0]['id'] === $stored_id
    && $rows[0]['status'] === 'active';
$state = get_option('uop_migration_status', array());
$versions_good = (int) get_option('uop_db_version', 0) === 2
    && (int) get_option('uop_data_version', 0) === 2
    && is_array($state) && ($state['status'] ?? '') === 'complete';
echo 'ORGAVELA_AUDIT_SCHEMA=' . $status . "\n";
echo 'ORGAVELA_AUDIT_TABLE_COUNT=' . $physical_tables . "\n";
echo 'ORGAVELA_AUDIT_DEFAULT_ORG=' . ($org_good ? 'good' : 'critical') . "\n";
echo 'ORGAVELA_AUDIT_VERSIONS=' . ($versions_good ? 'good' : 'critical') . "\n";
PHP
)"
wp --path="$root" --skip-plugins --skip-themes eval "$php_code" 2>/dev/null
REMOTE
then
  echo '::error::Audit SSH command failed. The existing IONOS account may be SFTP-only, WP-CLI may be absent, or the selected OrgaVela WordPress root may not be correct. No remote output or credentials are shown.'
  exit 1
fi

if ! grep -Fqx 'ORGAVELA_AUDIT_TARGET=verified' "$tmp/out"; then
  echo '::error::OrgaVela target identity not verified; report withheld.'
  exit 1
fi
awk -F= '
$1 == "ORGAVELA_AUDIT_TARGET" && $2 == "verified" { print; next }
$1 == "ORGAVELA_AUDIT_SHELL" && $2 == "available" { print; next }
$1 == "ORGAVELA_AUDIT_PLUGIN_VERSION" && $2 == "alpha2" { print; next }
$1 == "ORGAVELA_AUDIT_PROBE" && $2 == "success" { print; next }
$1 == "ORGAVELA_AUDIT_SCHEMA" && $2 ~ /^(good|critical)$/ { print; next }
$1 == "ORGAVELA_AUDIT_TABLE_COUNT" && $2 ~ /^[0-9]+$/ { print; next }
$1 == "ORGAVELA_AUDIT_DEFAULT_ORG" && $2 ~ /^(good|critical)$/ { print; next }
$1 == "ORGAVELA_AUDIT_VERSIONS" && $2 ~ /^(good|critical)$/ { print; next }
' "$tmp/out"
