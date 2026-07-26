<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Updater {
    private const CACHE_KEY = 'sacci_island_github_release';

    public static function hooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
        add_filter('upgrader_source_selection', [__CLASS__, 'normalise_source_directory'], 10, 4);
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
        $transient->response[$plugin] = (object) [
            'id'          => SACCI_ISLAND_GITHUB_REPO,
            'slug'        => dirname($plugin),
            'plugin'      => $plugin,
            'new_version' => (string) $release['version'],
            'url'         => (string) ($release['html_url'] ?? 'https://github.com/' . SACCI_ISLAND_GITHUB_REPO),
            'package'     => (string) ($release['package'] ?? ''),
            'tested'      => '6.7',
            'requires'    => '6.4',
            'requires_php'=> '8.0',
        ];

        return $transient;
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
            'requires'      => '6.4',
            'tested'        => '6.7',
            'requires_php'  => '8.0',
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

    private static function latest_release(): ?array {
        $cached = get_site_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . SACCI_ISLAND_GITHUB_REPO . '/releases/latest',
            [
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                ],
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_site_transient(self::CACHE_KEY, [], HOUR_IN_SECONDS);
            return null;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($payload)) {
            set_site_transient(self::CACHE_KEY, [], HOUR_IN_SECONDS);
            return null;
        }

        $version = self::normalise_version((string) ($payload['tag_name'] ?? ''));

        if ($version === '') {
            set_site_transient(self::CACHE_KEY, [], HOUR_IN_SECONDS);
            return null;
        }

        $package = self::asset_download_url($payload);

        if ($package === '') {
            $package = (string) ($payload['zipball_url'] ?? '');
        }

        $release = [
            'version'  => $version,
            'html_url' => (string) ($payload['html_url'] ?? ''),
            'body'     => (string) ($payload['body'] ?? ''),
            'package'  => $package,
        ];

        set_site_transient(self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS);
        return $release;
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
