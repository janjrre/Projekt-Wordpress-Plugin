<?php
// Only use a disposable, explicitly opted-in WordPress database.
if (getenv('UOP_TEST_ALLOW_DATABASE') !== '1') {
    throw new RuntimeException('Set UOP_TEST_ALLOW_DATABASE=1 for a disposable integration database.');
}
$root = getenv('WP_ROOT');
if (!$root || !is_file($root . '/wp-load.php')) {
    throw new RuntimeException('WP_ROOT must point to an installed disposable WordPress.');
}
require dirname(__DIR__) . '/vendor/autoload.php';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';


// WordPress wp_die() ordinarily exits with HTTP/CLI status 0, which once
// made a failed PHPUnit integration suite look green despite prior E/F output.
// Throw inside PHPUnit instead; the runner must finish the entire suite.
add_filter(
    'wp_die_handler',
    static function (): callable {
        return static function ( $message = '', $title = '', $args = array() ): void {
            throw new RuntimeException( 'WordPress wp_die() intercepted inside an integration test.' );
        };
    },
    PHP_INT_MAX
);
