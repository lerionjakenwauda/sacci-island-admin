<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_White_Label {
    private const ADMIN_QUERY = 'sacci_admin_path';
    private const LOGIN_QUERY = 'sacci_login_alias';
    private const ASSET_QUERY = 'sacci_asset_path';
    private const NATIVE_ADMIN_ENDPOINTS = [
        'admin-ajax.php',
        'admin-post.php',
        'async-upload.php',
        'load-scripts.php',
        'load-styles.php',
    ];
    private const STATIC_ASSET_EXTENSIONS = [
        'avif',
        'avi',
        'bmp',
        'css',
        'csv',
        'doc',
        'docx',
        'eot',
        'gif',
        'ico',
        'jpeg',
        'jpg',
        'js',
        'json',
        'm4a',
        'm4v',
        'map',
        'mov',
        'mp3',
        'mjs',
        'mp4',
        'ogg',
        'otf',
        'pdf',
        'png',
        'ppt',
        'pptx',
        'rtf',
        'svg',
        'ttf',
        'txt',
        'wav',
        'webm',
        'webp',
        'woff',
        'woff2',
        'xls',
        'xlsx',
        'xml',
        'zip',
    ];

    public static function hooks(): void {
        add_action('init', [__CLASS__, 'rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_redirect'], 0);
        add_filter('login_url', [__CLASS__, 'login_url'], 100, 3);
        add_filter('lostpassword_url', [__CLASS__, 'lostpassword_url'], 100, 2);
        add_filter('admin_url', [__CLASS__, 'admin_url'], 100, 4);
        add_action('admin_head', [__CLASS__, 'admin_history_alias'], 1);
        add_action('login_init', [__CLASS__, 'redirect_native_login']);
        add_action('template_redirect', [__CLASS__, 'start_output_buffer'], 1);
        add_action('admin_init', [__CLASS__, 'start_output_buffer'], 1);
    }

    public static function rewrite_rules(): void {
        add_rewrite_rule('^sacci-admin/(.+)?$', 'index.php?' . self::ADMIN_QUERY . '=$matches[1]', 'top');
        add_rewrite_rule('^sacci-login/?$', 'index.php?' . self::LOGIN_QUERY . '=login', 'top');
        add_rewrite_rule('^sacci-password/?$', 'index.php?' . self::LOGIN_QUERY . '=lostpassword', 'top');
        add_rewrite_rule('^sacci-assets/(.+)?$', 'index.php?' . self::ASSET_QUERY . '=content/$matches[1]', 'top');
        add_rewrite_rule('^sacci-core/(.+)?$', 'index.php?' . self::ASSET_QUERY . '=includes/$matches[1]', 'top');
        add_rewrite_rule('^sacci-plugins/(.+)?$', 'index.php?' . self::ASSET_QUERY . '=plugins/$matches[1]', 'top');
    }

    public static function query_vars(array $vars): array {
        $vars[] = self::ADMIN_QUERY;
        $vars[] = self::LOGIN_QUERY;
        $vars[] = self::ASSET_QUERY;
        return $vars;
    }

    public static function template_redirect(): void {
        $login = (string) get_query_var(self::LOGIN_QUERY);

        if ($login !== '') {
            self::serve_login_alias($login);
        }

        $asset = (string) get_query_var(self::ASSET_QUERY);

        if ($asset !== '') {
            self::serve_asset_alias($asset);
        }

        $admin_path = (string) get_query_var(self::ADMIN_QUERY);

        if ($admin_path !== '') {
            self::redirect_admin_alias($admin_path);
        }
    }

    public static function login_url(string $login_url, string $redirect, bool $force_reauth): string {
        $url = home_url('/sacci-login/');

        if ($redirect !== '') {
            $url = add_query_arg('redirect_to', $redirect, $url);
        }

        if ($force_reauth) {
            $url = add_query_arg('reauth', '1', $url);
        }

        return $url;
    }

    public static function lostpassword_url(string $lostpassword_url, string $redirect): string {
        $url = home_url('/sacci-password/');

        if ($redirect !== '') {
            $url = add_query_arg('redirect_to', $redirect, $url);
        }

        return $url;
    }

    public static function admin_url(string $url, string $path, ?int $blog_id, ?string $scheme): string {
        if (self::is_native_admin_endpoint($path)) {
            return $url;
        }

        $alias = home_url('/sacci-admin/' . ltrim($path, '/'));
        $parts = wp_parse_url($url);

        if (!empty($parts['query'])) {
            $alias .= '?' . $parts['query'];
        }

        return $alias;
    }

    public static function admin_history_alias(): void {
        ?>
        <script>
            (() => {
                const current = new URL(window.location.href);
                if (!current.pathname.includes("/wp-admin/")) {
                    return;
                }

                const alias = current.pathname.replace("/wp-admin/", "/sacci-admin/");
                window.history.replaceState({}, document.title, alias + current.search + current.hash);
            })();
        </script>
        <?php
    }

    public static function redirect_native_login(): void {
        if (!empty($GLOBALS['sacci_island_serving_login_alias'])) {
            return;
        }

        $request = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';

        if (!str_contains($request, 'wp-login.php')) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
        $target = in_array($action, ['lostpassword', 'retrievepassword', 'rp', 'resetpass'], true)
            ? home_url('/sacci-password/')
            : home_url('/sacci-login/');

        foreach ($_GET as $key => $value) {
            $target = add_query_arg(sanitize_key((string) $key), rawurlencode(sanitize_text_field(wp_unslash($value))), $target);
        }

        wp_safe_redirect($target);
        exit;
    }

    public static function start_output_buffer(): void {
        if (
            wp_doing_ajax() ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            wp_doing_cron()
        ) {
            return;
        }

        ob_start([__CLASS__, 'rewrite_output']);
    }

    public static function rewrite_output(string $html): string {
        if ($html === '') {
            return $html;
        }

        /*
         * WordPress prints its concatenated admin CSS and JavaScript through
         * wp-admin/load-styles.php and wp-admin/load-scripts.php. Rewriting
         * either URL to the friendly admin route returns an HTML redirect
         * instead of the requested asset and leaves wp-admin as unstyled HTML.
         *
         * Protect every native runtime endpoint before applying the public
         * aliases, then restore the original URLs after the replacement pass.
         */
        $protected = [];
        $admin_bases = array_values(array_unique([
            home_url('/wp-admin/'),
            site_url('/wp-admin/'),
            '/wp-admin/',
        ]));

        foreach ($admin_bases as $base) {
            foreach (self::NATIVE_ADMIN_ENDPOINTS as $endpoint) {
                $url = $base . $endpoint;

                if (!str_contains($html, $url)) {
                    continue;
                }

                $token = '___SACCI_NATIVE_ADMIN_' . count($protected) . '___';
                $protected[$token] = $url;
                $html = str_replace($url, $token, $html);
            }
        }

        $replacements = [
            home_url('/wp-admin/')   => home_url('/sacci-admin/'),
            site_url('/wp-admin/')   => home_url('/sacci-admin/'),
            plugins_url()            => untrailingslashit(home_url('/sacci-plugins/')),
            includes_url()           => home_url('/sacci-core/'),
            content_url()            => untrailingslashit(home_url('/sacci-assets/')),
            site_url('/wp-login.php') => home_url('/sacci-login/'),
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        return $protected
            ? str_replace(array_keys($protected), array_values($protected), $html)
            : $html;
    }

    private static function serve_login_alias(string $action): void {
        $GLOBALS['sacci_island_serving_login_alias'] = true;

        if ($action === 'lostpassword') {
            $_REQUEST['action'] = 'lostpassword';
            $_GET['action'] = 'lostpassword';
        }

        require ABSPATH . 'wp-login.php';
        exit;
    }

    private static function serve_asset_alias(string $asset): void {
        $asset = ltrim(str_replace('\\', '/', $asset), '/');

        if (str_starts_with($asset, 'content/')) {
            $path = WP_CONTENT_DIR . '/' . substr($asset, strlen('content/'));
        } elseif (str_starts_with($asset, 'includes/')) {
            $path = ABSPATH . WPINC . '/' . substr($asset, strlen('includes/'));
        } elseif (str_starts_with($asset, 'plugins/')) {
            $path = WP_PLUGIN_DIR . '/' . substr($asset, strlen('plugins/'));
        } else {
            status_header(404);
            exit;
        }

        $real = realpath($path);
        $allowed = array_filter([
            realpath(WP_CONTENT_DIR),
            realpath(ABSPATH . WPINC),
            realpath(WP_PLUGIN_DIR),
        ]);

        if (
            !$real ||
            !is_file($real) ||
            !self::is_allowed_file($real, $allowed) ||
            !self::is_static_asset($real)
        ) {
            status_header(404);
            exit;
        }

        $mime = wp_check_filetype($real);
        $type = $mime['type'] ?: self::fallback_content_type($real);

        header('Content-Type: ' . $type);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($real);
        exit;
    }

    private static function redirect_admin_alias(string $path): void {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url('/sacci-admin/' . $path)));
            exit;
        }

        if ($path === '') {
            wp_safe_redirect(SACCI_Island_Entry_Route::destination_for_current_user());
            exit;
        }

        $target = home_url('/wp-admin/' . $path);

        foreach ($_GET as $key => $value) {
            if ($key === self::ADMIN_QUERY) {
                continue;
            }

            $target = add_query_arg(sanitize_key((string) $key), sanitize_text_field(wp_unslash($value)), $target);
        }

        wp_safe_redirect($target);
        exit;
    }

    private static function is_native_admin_endpoint(string $path): bool {
        $path = ltrim($path, '/');

        if ($path === '') {
            return true;
        }

        foreach (self::NATIVE_ADMIN_ENDPOINTS as $endpoint) {
            if (str_starts_with($path, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    private static function is_allowed_file(string $file, array $roots): bool {
        $file = wp_normalize_path($file);

        foreach ($roots as $root) {
            if (
                $root &&
                str_starts_with(
                    $file,
                    trailingslashit(wp_normalize_path($root))
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private static function is_static_asset(string $file): bool {
        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        return in_array($extension, self::STATIC_ASSET_EXTENSIONS, true);
    }

    private static function fallback_content_type(string $file): string {
        return match (strtolower((string) pathinfo($file, PATHINFO_EXTENSION))) {
            'css'  => 'text/css; charset=UTF-8',
            'js',
            'mjs'  => 'application/javascript; charset=UTF-8',
            'map'  => 'application/json; charset=UTF-8',
            'svg'  => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            default => 'application/octet-stream',
        };
    }
}
