<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Updater {
    private const CACHE_KEY = 'sacci_island_github_release';
    private const MANIFEST_URL = 'https://raw.githubusercontent.com/lerionjakenwauda/sacci-island-admin/main/update.json';
    private const RELEASE_API_URL = 'https://api.github.com/repos/lerionjakenwauda/sacci-island-admin/releases/latest';

    private static bool $force_refresh = false;

    public static function hooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('update_plugins_github.com', [__CLASS__, 'native_update'], 10, 4);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
        add_filter('upgrader_source_selection', [__CLASS__, 'normalise_source_directory'], 10, 4);
        add_action('admin_init', [__CLASS__, 'maybe_force_refresh'], 1);
        add_action(
            'delete_site_transient_update_plugins',
            [__CLASS__, 'clear_release_cache'],
            10,
            1
        );
    }

    /**
     * Keep SACCI's release cache in step with WordPress's plugin-update cache.
     *
     * WordPress deletes update_plugins when an administrator clicks
     * "Check again". Clearing our cache on the same event guarantees that the
     * next update pass asks GitHub for the latest release immediately.
     */
    public static function clear_release_cache(string $transient = ''): void {
        self::$force_refresh = true;
        delete_site_transient(self::CACHE_KEY);
    }

    /**
     * "Check again" is a force-check request on WordPress's Updates screen.
     * Clear our release data before core rebuilds update_plugins so the new
     * release is visible on that same request.
     */
    public static function maybe_force_refresh(): void {
        if (
            !isset($_GET['force-check']) ||
            (string) $_GET['force-check'] !== '1' ||
            !current_user_can('update_plugins')
        ) {
            return;
        }

        self::clear_release_cache('update_plugins');
    }

    public static function check_for_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $release = self::latest_release();

        if (!$release || empty($release['version'])) {
            return $transient;
        }

        if (!version_compare((string) $release['version'], SACCI_ISLAND_VERSION, '>')) {
            return $transient;
        }

        $plugin = plugin_basename(SACCI_ISLAND_FILE);
        $update = self::wordpress_update($release, $plugin);

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        $transient->response[$plugin] = (object) $update;

        return $transient;
    }

    /**
     * WordPress 5.8+ routes plugins with an Update URI through this native,
     * hostname-specific filter. Keep the transient hook as a compatibility
     * path, but use this as the canonical third-party update integration.
     *
     * @param false|array $update
     * @return false|array
     */
    public static function native_update(
        $update,
        array $plugin_data,
        string $plugin_file,
        array $locales
    ) {
        if ($plugin_file !== plugin_basename(SACCI_ISLAND_FILE)) {
            return $update;
        }

        $release = self::latest_release();

        if (
            !$release ||
            empty($release['version']) ||
            !version_compare((string) $release['version'], SACCI_ISLAND_VERSION, '>')
        ) {
            return false;
        }

        $data = self::wordpress_update($release, $plugin_file);
        $data['version'] = $data['new_version'];
        unset($data['new_version']);

        return $data;
    }

    public static function plugin_info($result, string $action, object $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'sacci-island-admin') {
            return $result;
        }

        $release = self::latest_release();

        if (!$release) {
            return $result;
        }

        return (object) [
            'name'          => __('SACCI Parish Administration Suite', 'sacci-island-admin'),
            'slug'          => 'sacci-island-admin',
            'version'       => (string) ($release['version'] ?? SACCI_ISLAND_VERSION),
            'author'        => '<a href="https://lerionjakenwauda.com/">Lerion Jake Nwauda Digital Innovations</a>',
            'homepage'      => (string) ($release['html_url'] ?? 'https://github.com/' . SACCI_ISLAND_GITHUB_REPO),
            'requires'      => (string) ($release['requires'] ?? '6.4'),
            'tested'        => (string) ($release['tested'] ?? '6.7'),
            'requires_php'  => (string) ($release['requires_php'] ?? '8.0'),
            'download_link' => (string) ($release['package'] ?? ''),
            'sections'      => [
                'description' => __('A SACCI-branded administration shell, role access system and parish dashboard for WordPress.', 'sacci-island-admin'),
                'changelog'   => wp_kses_post((string) ($release['body'] ?? '')),
            ],
        ];
    }

    public static function normalise_source_directory(string $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra): string {
        if (
            empty($hook_extra['plugin']) ||
            $hook_extra['plugin'] !== plugin_basename(SACCI_ISLAND_FILE)
        ) {
            return $source;
        }

        $desired = trailingslashit($remote_source) . 'sacci-island-admin';

        if (basename(untrailingslashit($source)) === 'sacci-island-admin') {
            return $source;
        }

        if (file_exists($desired)) {
            return $source;
        }

        if (@rename($source, $desired)) {
            return trailingslashit($desired);
        }

        return $source;
    }

    private static function wordpress_update(array $release, string $plugin): array {
        return [
            'id'           => 'https://github.com/' . SACCI_ISLAND_GITHUB_REPO,
            'slug'         => 'sacci-island-admin',
            'plugin'       => $plugin,
            'new_version'  => (string) $release['version'],
            'url'          => (string) ($release['html_url'] ?? 'https://github.com/' . SACCI_ISLAND_GITHUB_REPO),
            'package'      => (string) ($release['package'] ?? ''),
            'tested'       => (string) ($release['tested'] ?? '6.7'),
            'requires'     => (string) ($release['requires'] ?? '6.4'),
            'requires_php' => (string) ($release['requires_php'] ?? '8.0'),
        ];
    }

    private static function latest_release(): ?array {
        $cached = get_site_transient(self::CACHE_KEY);

        if (!self::$force_refresh && is_array($cached)) {
            return $cached;
        }

        $manifest_url = self::MANIFEST_URL;

        if (self::$force_refresh) {
            $manifest_url = add_query_arg(
                'sacci-refresh',
                (string) time(),
                $manifest_url
            );
        }

        $payload = self::request_json($manifest_url, 'application/json');
        $release = is_array($payload)
            ? self::normalise_release($payload)
            : null;

        if (!$release) {
            $payload = self::request_json(
                self::RELEASE_API_URL,
                'application/vnd.github+json'
            );
            $release = is_array($payload)
                ? self::normalise_release($payload)
                : null;
        }

        self::$force_refresh = false;

        if (!$release) {
            set_site_transient(self::CACHE_KEY, [], MINUTE_IN_SECONDS);
            return null;
        }

        set_site_transient(self::CACHE_KEY, $release, 5 * MINUTE_IN_SECONDS);
        return $release;
    }

    private static function request_json(
        string $url,
        string $accept
    ): ?array {
        $headers = [
            'Accept'     => $accept,
            'User-Agent' => 'SACCI-Island-Admin/' . SACCI_ISLAND_VERSION .
                '; https://github.com/' . SACCI_ISLAND_GITHUB_REPO,
        ];

        if (self::$force_refresh) {
            $headers['Cache-Control'] = 'no-cache';
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => 12,
                'redirection' => 5,
                'headers'     => $headers,
            ]
        );

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) !== 200
        ) {
            return null;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($payload) ? $payload : null;
    }

    private static function normalise_release(array $payload): ?array {
        $version = self::normalise_version(
            (string) ($payload['version'] ?? $payload['tag_name'] ?? '')
        );

        if ($version === '') {
            return null;
        }

        $package = (string) ($payload['package'] ?? '');

        if ($package === '') {
            $package = self::asset_download_url($payload);
        }

        if ($package === '') {
            $package = (string) ($payload['zipball_url'] ?? '');
        }

        if ($package === '') {
            return null;
        }

        return [
            'version'      => $version,
            'html_url'     => (string) ($payload['html_url'] ?? $payload['url'] ?? ''),
            'body'         => (string) ($payload['body'] ?? ''),
            'package'      => $package,
            'tested'       => (string) ($payload['tested'] ?? '6.7'),
            'requires'     => (string) ($payload['requires'] ?? '6.4'),
            'requires_php' => (string) ($payload['requires_php'] ?? '8.0'),
        ];
    }

    private static function asset_download_url(array $payload): string {
        $assets = isset($payload['assets']) && is_array($payload['assets'])
            ? $payload['assets']
            : [];

        foreach ($assets as $asset) {
            $name = isset($asset['name']) ? (string) $asset['name'] : '';

            if (
                str_starts_with($name, 'sacci-island-admin-v') &&
                str_ends_with($name, '.zip') &&
                !empty($asset['browser_download_url'])
            ) {
                return (string) $asset['browser_download_url'];
            }
        }

        return '';
    }

    private static function normalise_version(string $tag): string {
        $tag = trim($tag);
        $tag = ltrim($tag, 'vV');

        return preg_match('/^\d+\.\d+\.\d+(?:[-.][A-Za-z0-9]+)?$/', $tag)
            ? $tag
            : '';
    }
}
