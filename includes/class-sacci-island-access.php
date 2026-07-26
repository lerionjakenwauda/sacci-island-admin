<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Access {
    public static function hooks(): void {
        add_action('admin_init', [__CLASS__, 'guard'], 2);
    }

    public static function guard(): void {
        if (current_user_can('manage_options') || wp_doing_cron() || defined('WP_CLI')) {
            return;
        }

        $settings = SACCI_Island_Settings::get();

        if (empty($settings['strict_guard'])) {
            return;
        }

        $candidates = self::request_candidates();

        if (!$candidates) {
            return;
        }

        $top_rules = is_array($settings['menu_rules']) ? $settings['menu_rules'] : [];
        $sub_rules = is_array($settings['submenu_rules']) ? $settings['submenu_rules'] : [];
        $manifest = SACCI_Island_Menu::manifest();

        foreach (($manifest['sub'] ?? []) as $parent_slug => $items) {
            foreach ($items as $item) {
                $subslug = (string) ($item['slug'] ?? '');

                if (!self::matches_any($subslug, $candidates)) {
                    continue;
                }

                $key = (string) $parent_slug . '|' . $subslug;
                $rule = isset($sub_rules[$key]) && is_array($sub_rules[$key]) ? $sub_rules[$key] : [];
                $capability = isset($rule['capability']) && $rule['capability'] !== ''
                    ? (string) $rule['capability']
                    : SACCI_Island_RBAC::capability_for_menu_slug(
                        $subslug,
                        isset($item['capability']) ? (string) $item['capability'] : 'read'
                    );

                if (
                    !current_user_can($capability) ||
                    !SACCI_Island_Menu::roles_allow($rule, SACCI_Island_Menu::current_roles())
                ) {
                    self::deny_or_redirect($capability);
                }

                return;
            }
        }

        foreach (($manifest['top'] ?? []) as $item) {
            $slug = (string) ($item['slug'] ?? '');

            if (!self::matches_any($slug, $candidates)) {
                continue;
            }

            $rule = isset($top_rules[$slug]) && is_array($top_rules[$slug]) ? $top_rules[$slug] : [];
            $capability = isset($rule['capability']) && $rule['capability'] !== ''
                ? (string) $rule['capability']
                : SACCI_Island_RBAC::capability_for_menu_slug(
                    $slug,
                    isset($item['capability']) ? (string) $item['capability'] : 'read'
                );

            if (
                !empty($rule['hidden']) ||
                !current_user_can($capability) ||
                !SACCI_Island_Menu::roles_allow($rule, SACCI_Island_Menu::current_roles())
            ) {
                self::deny_or_redirect($capability);
            }

            return;
        }
    }

    private static function request_candidates(): array {
        global $pagenow;

        $candidates = [];
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';

        if ($page !== '') {
            $candidates[] = $page;
            $candidates[] = 'admin.php?page=' . $page;
        }

        if ($pagenow) {
            $candidates[] = (string) $pagenow;
        }

        if ($post_type !== '') {
            $candidates[] = 'edit.php?post_type=' . $post_type;
            $candidates[] = 'post-new.php?post_type=' . $post_type;
        }

        if ($taxonomy !== '') {
            $candidate = 'edit-tags.php?taxonomy=' . $taxonomy;
            if ($post_type !== '') {
                $candidate .= '&post_type=' . $post_type;
            }
            $candidates[] = $candidate;
        }

        if (($pagenow === 'post.php' || $pagenow === 'post-new.php') && !$post_type) {
            $resolved_type = '';

            if ($pagenow === 'post.php' && isset($_GET['post'])) {
                $resolved_type = (string) get_post_type(absint($_GET['post']));
            }

            if ($pagenow === 'post-new.php' && isset($_GET['post_type'])) {
                $resolved_type = sanitize_key(wp_unslash($_GET['post_type']));
            }

            if ($resolved_type !== '') {
                $candidates[] = 'edit.php?post_type=' . $resolved_type;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private static function matches_any(string $menu_slug, array $candidates): bool {
        if ($menu_slug === '') {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (self::normalise($menu_slug) === self::normalise((string) $candidate)) {
                return true;
            }

            $menu_page = self::query_arg($menu_slug, 'page');
            $candidate_page = self::query_arg((string) $candidate, 'page');

            if ($menu_page !== '' && $menu_page === $candidate_page) {
                return true;
            }

            $menu_post_type = self::query_arg($menu_slug, 'post_type');
            $candidate_post_type = self::query_arg((string) $candidate, 'post_type');

            if ($menu_post_type !== '' && $menu_post_type === $candidate_post_type) {
                return true;
            }
        }

        return false;
    }

    private static function normalise(string $slug): string {
        return ltrim(html_entity_decode(trim($slug)), '/');
    }

    private static function query_arg(string $slug, string $key): string {
        $parts = wp_parse_url($slug);

        if (!isset($parts['query'])) {
            return '';
        }

        parse_str((string) $parts['query'], $query);
        return isset($query[$key]) ? sanitize_text_field((string) $query[$key]) : '';
    }

    public static function deny_or_redirect(string $capability = ''): void {
        SACCI_Island_Audit_Log::record(
            'failed_permission_attempt',
            'admin',
            0,
            [
                'capability' => $capability,
                'uri'        => isset($_SERVER['REQUEST_URI'])
                    ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
                    : '',
            ]
        );

        self::deny();
    }

    private static function deny(): void {
        wp_die(
            '<div class="sacci-access-denied-card">' .
            '<h1>' . esc_html__('This admin area is not available to your role.', 'sacci-island-admin') . '</h1>' .
            '<p>' . esc_html__('Ask a WordPress administrator to update your Parish Role Access settings when this area is required for your work.', 'sacci-island-admin') . '</p>' .
            '<p><a href="' . esc_url(admin_url()) . '">' .
            esc_html__('Return to Parish Overview', 'sacci-island-admin') .
            '</a></p></div>',
            esc_html__('Access restricted', 'sacci-island-admin'),
            ['response' => 403, 'back_link' => true]
        );
    }
}
