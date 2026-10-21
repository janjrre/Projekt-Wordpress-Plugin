<?php
/**
 * OrgaVela PGP05 metadata-only WP-CLI audit.
 * Load ONLY using WP-CLI --skip-plugins --skip-themes eval-file in an existing,
 * individually authorized SSH session. No backup, export, file reads or writes.
 * Never show paths, DB identifiers, rows, credentials or WP configuration data.
 * No real-site test has been authorized by existence of this source file alone.
 */

declare(strict_types=1);

function pgp05_refuse(): void {
    // Uniform failures: no secret paths, database IDs, SQL details or exception messages.
    fwrite(STDERR, "ORGAVELA_PGP05_IDENTITY=REFUSED\n");
    exit(2);
}

if (!defined('WP_CLI') || WP_CLI !== true || !defined('ABSPATH')) {
    pgp05_refuse();
}
$expectedRoot = getenv('ORGAVELA_PGP05_EXPECTED_WP_ROOT');
if (!is_string($expectedRoot) || $expectedRoot === '' || $expectedRoot[0] !== '/' ||
    str_contains($expectedRoot, '..') || str_contains($expectedRoot, "\n") ||
    str_contains(strtolower($expectedRoot), 'dreamloud')) {
    pgp05_refuse();
}
$root = realpath(ABSPATH);
$expected = realpath($expectedRoot);
if ($root === false || $expected === false || !hash_equals($root, $expected) ||
    !is_dir($root . '/wp-admin') || !is_dir($root . '/wp-content') ||
    !is_dir($root . '/wp-includes') ||
    !is_file($root . '/wp-config.php') ||
    !is_file($root . '/wp-content/plugins/uop-core/uop-core.php')) {
    pgp05_refuse();
}

if (!function_exists('get_option') ||
    rtrim((string)get_option('home'), '/') !== 'https://orgavela.com' ||
    rtrim((string)get_option('siteurl'), '/') !== 'https://orgavela.com') {
    pgp05_refuse();
}

// No credentials or configuration file contents are emitted. These calls
// inspect only the live WordPress connection and information_schema metadata.
global $wpdb;
if (!is_object($wpdb) || !isset($wpdb->dbname, $wpdb->prefix) ||
    !is_string($wpdb->dbname) || $wpdb->dbname === '' ||
    !is_string($wpdb->prefix) ||
    !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->prefix) ||
    !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'get_row') ||
    !method_exists($wpdb, 'prepare')) {
    pgp05_refuse();
}

try {
    $selectedDb = $wpdb->get_var('SELECT DATABASE()');
    if (!is_string($selectedDb) || !hash_equals($wpdb->dbname, $selectedDb)) {
        pgp05_refuse();
    }
    $counts = $wpdb->get_row(
        "SELECT COUNT(*) AS total, SUM(CASE WHEN ENGINE = 'InnoDB' THEN 1 ELSE 0 END) AS innodb " .
        "FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    );
    if (!is_object($counts) || !isset($counts->total, $counts->innodb) ||
        !ctype_digit((string)$counts->total) || !ctype_digit((string)$counts->innodb) ||
        (int)$counts->total !== 52 || (int)$counts->innodb !== 52) {
        pgp05_refuse();
    }

    $coreNames = ['options', 'posts', 'users', 'usermeta', 'comments'];
    $uopNames = ['organizations', 'persons', 'consent_records', 'registrations', 'waitlist_entries'];
    $requestedNames = [];
    foreach ($coreNames as $name) {
        $requestedNames[] = $wpdb->prefix . $name;
    }
    foreach ($uopNames as $name) {
        $requestedNames[] = $wpdb->prefix . 'uop_' . $name;
    }
    // Use only wpdb->prepare placeholders; never interpolate a raw table ID.
    $placeholders = implode(',', array_fill(0, count($requestedNames), '%s'));
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() " .
        "AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME IN ($placeholders)",
        ...$requestedNames
    );
    if (!is_string($query) || $query === '' ||
        (string)$wpdb->get_var($query) !== (string)count($requestedNames)) {
        pgp05_refuse();
    }
} catch (Throwable $e) {
    // Never log exception messages: DB errors may disclose identifiers.
    pgp05_refuse();
}

// The runtime's self-consistency and the UI's 52-table pattern are supported,
// but the IONOS phpMyAdmin database ID was not independently pinned.
echo "ORGAVELA_PGP05_IDENTITY=METADATA_ONLY_PASS\n";
echo "ORGAVELA_PGP05_HOME_AND_SITEURL=orgavela.com\n";
echo "ORGAVELA_PGP05_WP_ROOT=matches_supplied_root\n";
echo "ORGAVELA_PGP05_RUNTIME_DB_CONNECTION=matches_wp_config\n";
echo "ORGAVELA_PGP05_TABLE_ENGINES=52_of_52_innodb\n";
echo "ORGAVELA_PGP05_CORE_UOP_TABLES=10_of_10_present\n";
echo "ORGAVELA_PGP05_PHPMyADMIN_DB_IDENTIFIER=not_independently_compared\n";
echo "ORGAVELA_PGP05_DOMAIN_DOCUMENT_ROOT=not_independently_pinned\n";
echo "ORGAVELA_PGP05_BACKUP_CAPTURE=blocked\n";
