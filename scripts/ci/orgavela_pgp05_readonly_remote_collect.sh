#!/usr/bin/env bash
# PREPARED, NOT AUTHORIZED FOR DEPLOYMENT OR EXECUTION WITHOUT REVIEW.
# This runs ONLY *inside* a separately-approved, pinned OrgaVela SSH session.
# It does not open SSH, create dumps, upload files, or change WordPress settings.
set -euo pipefail
umask 077
refuse() { printf 'PGP05_REMOTE_IDENTITY=REFUSED\n' >&2; exit 1; }
[[ $# == 1 && "$1" == '--metadata-only-approved' ]] || refuse
[[ "${ORGAVELA_PGP05_OWNER_APPROVED_READONLY:-}" == 'ORGAVELA-METADATA-ONLY' ]] || refuse
[[ -z "${GH_TOKEN:-}${GITHUB_TOKEN:-}${BACKUP_GITHUB_TOKEN:-}" ]] || refuse
command -v wp >/dev/null 2>&1 || refuse
[[ -n "${HOME:-}" && "$HOME" == /* && "$HOME" != '/' ]] || refuse
# Fixed candidates only, never 'find /', never touch another hosted contract.
paths=( '/public' '/home/www/public' "$HOME/public" '/home/www' )
labels=( 'public-direct' 'home-www-public' 'home-public' 'home-www' )
selected=''
selected_label=''
for i in "${!paths[@]}"; do
  candidate="${paths[$i]}"
  [[ -d "$candidate/wp-admin" && -d "$candidate/wp-includes" &&
     -d "$candidate/wp-content/plugins/uop-core" &&
     -f "$candidate/wp-config.php" ]] || continue
  canonical="$(cd -P "$candidate" 2>/dev/null && pwd -P)" || refuse
  if [[ -n "$selected" && "$selected" != "$canonical" ]]; then
    refuse  # Ambiguous WordPress roots: require fresh operator review.
  fi
  selected="$canonical"
  selected_label="${labels[$i]}"
done
[[ -n "$selected" && -r "$selected/wp-config.php" ]] || refuse
# WP-CLI loads WordPress/bootstrap and therefore can execute host MU drop-ins.
# Review installed MU/drop-in policy before a real run; skip-plugins is not a
# formal guarantee of zero side effects. No WP writes are instructed below.
php_code="$(cat <<'PHP'
global $wpdb;
$expected = 'https://orgavela.com';
$home = untrailingslashit((string) get_option('home')) === $expected;
$siteurl = untrailingslashit((string) get_option('siteurl')) === $expected;
$plugin = 'uop-core/uop-core.php';
$active = in_array($plugin, (array) get_option('active_plugins', array()), true);
$meta = get_file_data(WP_PLUGIN_DIR . '/uop-core/uop-core.php', array('Version' => 'Version'), 'plugin');
$groups = $wpdb->get_results(
    "SELECT COALESCE(ENGINE, 'unknown') AS engine, COUNT(*) AS n " .
    "FROM information_schema.TABLES " .
    "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' " .
    "GROUP BY ENGINE", ARRAY_A
);
if (!is_array($groups)) { exit(51); }
$total = 0; $innodb = 0; $other = 0;
foreach ($groups as $group) {
    $n = (int) ($group['n'] ?? -1);
    if ($n < 0) { exit(52); }
    $total += $n;
    if (strcasecmp((string) $group['engine'], 'InnoDB') === 0) $innodb += $n;
    else $other += $n;
}
$table_present = static function ($table) use ($wpdb) {
    if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D', $table)) return false;
    $sql = $wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $table
    );
    return (int) $wpdb->get_var($sql) === 1;
};
$core = $table_present($wpdb->options) && $table_present($wpdb->posts) &&
    $table_present($wpdb->users) && $table_present($wpdb->term_taxonomy);
$uop = $table_present($wpdb->prefix . 'uop_organizations') &&
    $table_present($wpdb->prefix . 'uop_persons') &&
    $table_present($wpdb->prefix . 'uop_registrations');
$family = stripos((string) $wpdb->db_version(), 'MariaDB') !== false ? 'MariaDB' : 'unknown';
$receipt = array(
    'schema' => 'orgavela-pgp05-readonly-identity/v1',
    'scope' => 'metadata-only', 'target' => $expected,
    'source' => 'runtime-wp-cli',
    'wp_home_match' => $home, 'wp_siteurl_match' => $siteurl,
    'wp_config_present' => true, 'wp_core_dirs_present' => true,
    'uop_core_active' => $active,
    'uop_core_version' => (string) ($meta['Version'] ?? ''),
    'wp_version' => get_bloginfo('version'),
    'db_server_family' => $family, 'db_table_count' => $total,
    'db_innodb_count' => $innodb, 'db_other_engine_count' => $other,
    'wp_core_tables_present' => $core, 'uop_tables_present' => $uop,
    'db_identity_independently_verified' => false,
    'contains_secret_values' => false, 'personal_records_exported' => false,
    'file_contents_exported' => false, 'dump_created' => false,
    'website_writes_requested' => false, 'new_ci_runs_requested' => false,
);
echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
PHP
)"
# The caller must CAPTURE stdout privately, validate it through the
# strict local Python receipt contract, then reveal only PASS/REFUSED.
# Do not copy raw stdout/stderr into GitHub logs, chat or repository files.
output="$(wp --path="$selected" --skip-plugins --skip-themes --quiet eval "$php_code" 2>/dev/null)" || refuse
[[ ${#output} -gt 0 && ${#output} -le 4096 ]] || refuse
# Insert only a fixed enum: never expose the canonical filesystem path.
if command -v php >/dev/null 2>&1; then
  # JSON already produced by PHP, inject a fixed, validated enum key using
  # locally available PHP; prevents unsafe string concatenation.
  printf '%s' "$output" | php -r '
    $j=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
    if (!is_array($j) || isset($j["wp_root_candidate"])) exit(2);
    $j["wp_root_candidate"]=$argv[1];
    echo json_encode($j,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  ' "$selected_label" || refuse
else
  refuse
fi
