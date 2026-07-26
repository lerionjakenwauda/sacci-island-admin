<?php

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SACCI_ISLAND_VERSION', '2.1.4');
define('SACCI_ISLAND_FILE', dirname(__DIR__) . '/sacci-island-admin.php');
define('SACCI_ISLAND_GITHUB_REPO', 'lerionjakenwauda/sacci-island-admin');

$GLOBALS['sacci_test_actions'] = [];
$GLOBALS['sacci_test_deleted_transients'] = [];
$GLOBALS['sacci_test_remote_urls'] = [];
$GLOBALS['sacci_test_stored_transients'] = [];

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

function get_site_transient(string $transient) {
    return false;
}

function set_site_transient(string $transient, $value, int $expiration): bool {
    $GLOBALS['sacci_test_stored_transients'][$transient] = [
        'value' => $value,
        'expiration' => $expiration,
    ];
    return true;
}

function add_query_arg(string $key, string $value, string $url): string {
    return $url . '?' . rawurlencode($key) . '=' . rawurlencode($value);
}

function wp_remote_get(string $url, array $args = []): array {
    $GLOBALS['sacci_test_remote_urls'][] = $url;

    return [
        'response' => ['code' => 200],
        'body' => json_encode([
            'version' => '2.1.5',
            'url' => 'https://github.com/lerionjakenwauda/sacci-island-admin/releases/tag/v2.1.5',
            'package' => 'https://github.com/lerionjakenwauda/sacci-island-admin/releases/download/v2.1.5/sacci-island-admin-v2.1.5.zip',
            'tested' => '6.7',
            'requires' => '6.4',
            'requires_php' => '8.0',
        ], JSON_THROW_ON_ERROR),
    ];
}

function is_wp_error($value): bool {
    return false;
}

function wp_remote_retrieve_response_code(array $response): int {
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body(array $response): string {
    return (string) ($response['body'] ?? '');
}

function plugin_basename(string $file): string {
    return 'sacci-island-admin/sacci-island-admin.php';
}

require dirname(__DIR__) . '/includes/class-sacci-island-updater.php';

SACCI_Island_Updater::hooks();

$hook = $GLOBALS['sacci_test_actions']['delete_site_transient_update_plugins'] ?? null;
$native_hook = $GLOBALS['sacci_test_actions']['update_plugins_github.com'] ?? null;
$read_hook = $GLOBALS['sacci_test_actions']['site_transient_update_plugins'] ?? null;
$plugin_source = file_get_contents(dirname(__DIR__) . '/sacci-island-admin.php');
$manifest_source = file_get_contents(dirname(__DIR__) . '/update.json');
$manifest = is_string($manifest_source)
    ? json_decode($manifest_source, true)
    : null;

if (!is_array($hook)) {
    fwrite(STDERR, "Updater did not register the update_plugins cache-deletion hook.\n");
    exit(1);
}

if ($hook['accepted_args'] !== 1) {
    fwrite(STDERR, "Updater cache-deletion hook must accept WordPress's transient argument.\n");
    exit(1);
}

if (!is_array($native_hook) || $native_hook['accepted_args'] !== 4) {
    fwrite(STDERR, "Updater did not register WordPress's native Update URI hook.\n");
    exit(1);
}

if (!is_array($read_hook)) {
    fwrite(STDERR, "Updater did not register the read-time update transient fallback.\n");
    exit(1);
}

if (
    !is_string($plugin_source) ||
    !str_contains(
        $plugin_source,
        'Update URI: https://github.com/lerionjakenwauda/sacci-island-admin'
    )
) {
    fwrite(STDERR, "Plugin header is missing the GitHub Update URI.\n");
    exit(1);
}

if (
    !is_array($manifest) ||
    ($manifest['version'] ?? '') !== '2.1.5' ||
    !str_contains(
        (string) ($manifest['package'] ?? ''),
        '/releases/download/v2.1.5/sacci-island-admin-v2.1.5.zip'
    )
) {
    fwrite(STDERR, "Public updater manifest does not match version 2.1.5.\n");
    exit(1);
}

call_user_func($hook['callback'], 'update_plugins');

if ($GLOBALS['sacci_test_deleted_transients'] !== ['sacci_island_github_release']) {
    fwrite(STDERR, "A forced WordPress update check did not clear SACCI's release cache.\n");
    exit(1);
}

$transient = (object) ['response' => []];
$updated = SACCI_Island_Updater::check_for_update($transient);
$plugin_file = 'sacci-island-admin/sacci-island-admin.php';
$response = $updated->response[$plugin_file] ?? null;

if (
    !is_object($response) ||
    ($response->new_version ?? '') !== '2.1.5' ||
    ($response->package ?? '') !==
        'https://github.com/lerionjakenwauda/sacci-island-admin/releases/download/v2.1.5/sacci-island-admin-v2.1.5.zip'
) {
    fwrite(STDERR, "Manifest release was not injected into WordPress updates.\n");
    exit(1);
}

if (
    empty($GLOBALS['sacci_test_remote_urls']) ||
    !str_contains($GLOBALS['sacci_test_remote_urls'][0], 'sacci-refresh=')
) {
    fwrite(STDERR, "Forced update check did not bypass the public manifest cache.\n");
    exit(1);
}

echo "SACCI updater cache regression checks passed.\n";
