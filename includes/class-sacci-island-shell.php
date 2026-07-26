<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Shell {
    public static function hooks(): void {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue'], PHP_INT_MAX);
        add_filter('admin_body_class', [__CLASS__, 'body_class']);
        add_filter('admin_footer_text', [__CLASS__, 'footer_text'], 9999);
        add_filter('update_footer', [__CLASS__, 'footer_status'], 9999);

        add_action('admin_bar_menu', [__CLASS__, 'build_admin_bar'], 1);
        add_action('admin_bar_menu', [__CLASS__, 'clean_admin_bar'], 99999);
    }

    public static function enqueue(): void {
        $settings = SACCI_Island_Settings::get();
        $style_path = SACCI_ISLAND_DIR . 'assets/css/admin-islands.css';
        $script_path = SACCI_ISLAND_DIR . 'assets/js/admin-islands.js';

        wp_enqueue_style(
            'sacci-island-admin-shell',
            SACCI_ISLAND_URL . 'assets/css/admin-islands.css',
            [],
            self::asset_version($style_path)
        );

        wp_enqueue_script(
            'sacci-island-admin-shell',
            SACCI_ISLAND_URL . 'assets/js/admin-islands.js',
            [],
            self::asset_version($script_path),
            true
        );

        $variables = sprintf(
            ':root{
                --sacci-admin-radius:%1$dpx;
                --sacci-admin-sidebar:%2$dpx;
                --sacci-admin-header:%3$dpx;
            }',
            absint($settings['radius']),
            absint($settings['sidebar_width']),
            absint($settings['header_height'])
        );

        wp_add_inline_style('sacci-island-admin-shell', $variables);

        wp_localize_script(
            'sacci-island-admin-shell',
            'SACCIIslandAdmin',
            [
                'brandName'    => (string) $settings['brand_name'],
                'brandTagline' => (string) $settings['brand_tagline'],
                'logoUrl'      => SACCI_Island_Settings::logo_url($settings),
                'homeUrl'      => home_url('/'),
                'openLabel'    => __('Open navigation', 'sacci-island-admin'),
                'closeLabel'   => __('Close navigation', 'sacci-island-admin'),
                'searchLabel'  => __('Search administration', 'sacci-island-admin'),
                'storageKey'   => 'sacci_connected_sidebar_state',
            ]
        );
    }

    private static function asset_version(string $path): string {
        return SACCI_ISLAND_VERSION . '.' . (file_exists($path) ? (string) filemtime($path) : '0');
    }

    public static function body_class(string $classes): string {
        $settings = SACCI_Island_Settings::get();

        $classes .= ' sacci-island-admin sacci-connected-light-shell';
        $classes .= !empty($settings['compact'])
            ? ' sacci-island-compact'
            : ' sacci-island-comfortable';

        $sidebar_cookie = isset($_COOKIE['sacci_connected_sidebar'])
            ? sanitize_key(wp_unslash($_COOKIE['sacci_connected_sidebar']))
            : 'open';

        if ($sidebar_cookie !== 'closed') {
            $classes .= ' sacci-sidebar-open';
        }

        return trim($classes);
    }

    public static function footer_text(string $text): string {
        $settings = SACCI_Island_Settings::get();

        if (empty($settings['show_footer_brand'])) {
            return '';
        }

        return sprintf(
            '<span class="sacci-admin-footer-credit">%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></span>',
            esc_html__('Developed by', 'sacci-island-admin'),
            esc_url('https://lerionjakenwauda.com/'),
            esc_html__('Lerion Jake Nwauda Digital Innovations', 'sacci-island-admin')
        );
    }

    public static function footer_status(string $text): string {
        return sprintf(
            '&copy; %1$s %2$s',
            esc_html(wp_date('Y')),
            esc_html__('St. Augustine MaryHill Parish, Ikorodu', 'sacci-island-admin')
        );
    }

    public static function build_admin_bar(WP_Admin_Bar $admin_bar): void {
        $settings = SACCI_Island_Settings::get();
        $logo_url = SACCI_Island_Settings::logo_url($settings);

        $admin_bar->add_node([
            'id'     => 'sacci-sidebar-toggle',
            'parent' => false,
            'title'  =>
                '<span class="sacci-sidebar-toggle-icon" aria-hidden="true">' .
                    '<svg viewBox="0 0 24 24" focusable="false">' .
                        '<path d="M4 5h16M4 12h16M4 19h16"></path>' .
                    '</svg>' .
                '</span>' .
                '<span class="screen-reader-text">' .
                    esc_html__('Toggle navigation', 'sacci-island-admin') .
                '</span>',
            'href'   => '#',
            'meta'   => [
                'class' => 'sacci-sidebar-toggle-node',
                'title' => esc_attr__('Toggle navigation', 'sacci-island-admin'),
            ],
        ]);

        $admin_bar->add_node([
            'id'     => 'sacci-island-brand',
            'parent' => false,
            'title'  =>
                '<span class="sacci-adminbar-lockup">' .
                    '<span class="sacci-adminbar-mark" aria-hidden="true">' .
                        '<img src="' . esc_url($logo_url) . '" alt="">' .
                    '</span>' .
                    '<span>' .
                        '<strong>' . esc_html((string) $settings['brand_name']) . '</strong>' .
                        '<small>' . esc_html((string) $settings['brand_tagline']) . '</small>' .
                        '<em>' . esc_html__('SACCI Admin', 'sacci-island-admin') . '</em>' .
                    '</span>' .
                '</span>',
            'href'   => home_url('/'),
            'meta'   => [
                'class' => 'sacci-island-adminbar-brand',
                'title' => esc_attr((string) $settings['brand_name']),
            ],
        ]);

        $admin_bar->add_node([
            'id'     => 'sacci-admin-search',
            'parent' => 'top-secondary',
            'title'  =>
                '<span class="sacci-admin-search-icon" aria-hidden="true">' .
                    '<svg viewBox="0 0 24 24" focusable="false">' .
                        '<circle cx="11" cy="11" r="7"></circle>' .
                        '<path d="m20 20-4-4"></path>' .
                    '</svg>' .
                '</span>' .
                '<span class="ab-label">' .
                    esc_html__('Search', 'sacci-island-admin') .
                '</span>',
            'href'   => '#',
            'meta'   => [
                'title' => esc_attr__('Search administration (Ctrl+K)', 'sacci-island-admin'),
            ],
        ]);

        $notice_url = admin_url();

        if (current_user_can('manage_options')) {
            $notice_url = admin_url('admin.php?page=sacci-website-tools-notices');
        }

        $admin_bar->add_node([
            'id'     => 'sacci-admin-notifications',
            'parent' => 'top-secondary',
            'title'  =>
                '<span class="sacci-notification-icon" aria-hidden="true">' .
                    '<svg viewBox="0 0 24 24" focusable="false">' .
                        '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>' .
                        '<path d="M10 21h4"></path>' .
                    '</svg>' .
                    '<i></i>' .
                '</span>',
            'href'   => $notice_url,
            'meta'   => [
                'title' => esc_attr__('Administration notifications', 'sacci-island-admin'),
            ],
        ]);
    }

    public static function clean_admin_bar(WP_Admin_Bar $admin_bar): void {
        foreach ([
            'wp-logo',
            'site-name',
            'updates',
            'comments',
            'search',
            'about',
            'wporg',
            'documentation',
            'support-forums',
            'feedback',
        ] as $node) {
            $admin_bar->remove_node($node);
        }

        /*
         * Keep the parish controls, native New menu and account menu. Everything
         * else is removed so vendor shortcuts cannot leak into the header.
         */
        $protected = [
            'top-secondary',
            'sacci-sidebar-toggle',
            'sacci-island-brand',
            'sacci-admin-search',
            'sacci-admin-notifications',
            'new-content',
            'new-post',
            'new-page',
            'new-media',
            'my-account',
            'user-actions',
            'user-info',
            'edit-profile',
            'profile',
            'logout',
        ];

        $protected_parents = [
            'new-content',
            'my-account',
            'user-actions',
        ];

        foreach ((array) $admin_bar->get_nodes() as $node) {
            if (
                !$node ||
                in_array((string) $node->id, $protected, true) ||
                in_array((string) $node->parent, $protected_parents, true)
            ) {
                continue;
            }

            $admin_bar->remove_node((string) $node->id);
        }
    }
}
