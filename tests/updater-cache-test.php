<?php

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['sacci_test_actions'] = [];
$GLOBALS['sacci_test_deleted_transients'] = [];

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['sacci_test_actions'][$hook] = [
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    add_filter($hook, $callback, $priority, $accepted_args);
}

function delete_site_transient(string $transient): bool {
    $GLOBALS['sacci_test_deleted_transients'][] = $transient;
    return true;
}

require dirname(__DIR__) . '/includes/class-sacci-island-updater.php';

SACCI_Island_Updater::hooks();

$hook = $GLOBALS['sacci_test_actions']['delete_site_transient_update_plugins'] ?? null;

if (!is_array($hook)) {
    fwrite(STDERR, "Updater did not register the update_plugins cache-deletion hook.\n");
    exit(1);
}

if ($hook['accepted_args'] !== 1) {
    fwrite(STDERR, "Updater cache-deletion hook must accept WordPress's transient argument.\n");
    exit(1);
}

call_user_func($hook['callback'], 'update_plugins');

if ($GLOBALS['sacci_test_deleted_transients'] !== ['sacci_island_github_release']) {
    fwrite(STDERR, "A forced WordPress update check did not clear SACCI's release cache.\n");
    exit(1);
}

echo "SACCI updater cache regression checks passed.\n";
