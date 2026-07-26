<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Menu {
    private static array $runtime_manifest = [];

    public static function hooks(): void {
        add_action('admin_menu', [__CLASS__, 'capture_and_apply'], 99998);
        add_filter('custom_menu_order', [__CLASS__, 'enable_custom_order']);
        add_filter('menu_order', [__CLASS__, 'menu_order']);
    }

    public static function capture_and_apply(): void {
        global $menu, $submenu;

        if (!is_array($menu)) {
            return;
        }

        self::$runtime_manifest = self::build_manifest($menu, is_array($submenu) ? $submenu : []);

        if (current_user_can('manage_options')) {
            $saved = get_option(SACCI_ISLAND_MANIFEST_OPTION, []);
            if ($saved !== self::$runtime_manifest) {
                update_option(SACCI_ISLAND_MANIFEST_OPTION, self::$runtime_manifest, false);
            }
        }

        $settings = SACCI_Island_Settings::get();
        $top_rules = is_array($settings['menu_rules']) ? $settings['menu_rules'] : [];
        $sub_rules = is_array($settings['submenu_rules']) ? $settings['submenu_rules'] : [];
        $user_roles = self::current_roles();
        $administrator = current_user_can('manage_options');

        foreach ($menu as $index => &$item) {
            if (!isset($item[2])) {
                continue;
            }

            $slug = (string) $item[2];
            $rule = isset($top_rules[$slug]) && is_array($top_rules[$slug]) ? $top_rules[$slug] : [];

            if (!empty($rule['label'])) {
                $item[0] = esc_html((string) $rule['label']);
            }

            if (!empty($rule['icon']) && isset($item[6])) {
                $icon = sanitize_html_class((string) $rule['icon']);
                $item[6] = str_starts_with($icon, 'dashicons-') ? $icon : 'dashicons-' . $icon;
            }

            $required_capability = isset($rule['capability']) && $rule['capability'] !== ''
                ? sanitize_key((string) $rule['capability'])
                : SACCI_Island_RBAC::capability_for_menu_slug(
                    $slug,
                    isset($item[1]) ? (string) $item[1] : 'read'
                );

            if ($required_capability !== '' && isset($item[1])) {
                $item[1] = $required_capability;
            }

            if (
                $slug !== 'sacci-island-admin' &&
                (
                    !empty($rule['hidden']) ||
                    !$administrator && !current_user_can($required_capability) ||
                    (!$administrator && !self::roles_allow($rule, $user_roles))
                )
            ) {
                unset($menu[$index]);
                unset($submenu[$slug]);
            }
        }
        unset($item);

        if (!is_array($submenu)) {
            return;
        }

        foreach ($submenu as $parent_slug => &$items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $index => &$item) {
                if (!isset($item[2])) {
                    continue;
                }

                $subslug = (string) $item[2];
                $key = (string) $parent_slug . '|' . $subslug;
                $rule = isset($sub_rules[$key]) && is_array($sub_rules[$key]) ? $sub_rules[$key] : [];
                $required_capability = isset($rule['capability']) && $rule['capability'] !== ''
                    ? sanitize_key((string) $rule['capability'])
                    : SACCI_Island_RBAC::capability_for_menu_slug(
                        $subslug,
                        isset($item[1]) ? (string) $item[1] : 'read'
                    );

                if ($required_capability !== '' && isset($item[1])) {
                    $item[1] = $required_capability;
                }

                if (
                    !$administrator &&
                    (
                        !current_user_can($required_capability) ||
                        !self::roles_allow($rule, $user_roles)
                    )
                ) {
                    unset($items[$index]);
                }
            }
            unset($item);
        }
        unset($items);
    }

    public static function manifest(): array {
        if (self::$runtime_manifest) {
            return self::$runtime_manifest;
        }

        $saved = get_option(SACCI_ISLAND_MANIFEST_OPTION, []);
        return is_array($saved) ? $saved : ['top' => [], 'sub' => []];
    }

    private static function build_manifest(array $menu, array $submenu): array {
        $manifest = ['top' => [], 'sub' => []];

        foreach ($menu as $item) {
            if (!isset($item[0], $item[2])) {
                continue;
            }

            $slug = (string) $item[2];

            if ($slug === 'separator1' || str_starts_with($slug, 'separator')) {
                continue;
            }

            $label = wp_strip_all_tags((string) $item[0]);
            $label = preg_replace('/\s+\d+\s*$/', '', $label) ?: $label;

            $manifest['top'][] = [
                'label'      => trim($label),
                'slug'       => $slug,
                'capability' => isset($item[1]) ? (string) $item[1] : '',
                'icon'       => isset($item[6]) ? (string) $item[6] : 'dashicons-admin-generic',
            ];

            if (!isset($submenu[$slug]) || !is_array($submenu[$slug])) {
                continue;
            }

            foreach ($submenu[$slug] as $subitem) {
                if (!isset($subitem[0], $subitem[2])) {
                    continue;
                }

                $sublabel = wp_strip_all_tags((string) $subitem[0]);
                $sublabel = preg_replace('/\s+\d+\s*$/', '', $sublabel) ?: $sublabel;

                $manifest['sub'][$slug][] = [
                    'label'      => trim($sublabel),
                    'slug'       => (string) $subitem[2],
                    'capability' => isset($subitem[1]) ? (string) $subitem[1] : '',
                ];
            }
        }

        return $manifest;
    }

    public static function enable_custom_order($enabled): bool {
        $settings = SACCI_Island_Settings::get();
        return !empty($settings['menu_order']) ? true : (bool) $enabled;
    }

    public static function menu_order(array $menu_order): array {
        $settings = SACCI_Island_Settings::get();
        $saved_order = is_array($settings['menu_order']) ? $settings['menu_order'] : [];

        if (!$saved_order) {
            return $menu_order;
        }

        $result = [];

        foreach ($saved_order as $slug) {
            if (in_array($slug, $menu_order, true)) {
                $result[] = $slug;
            }
        }

        foreach ($menu_order as $slug) {
            if (!in_array($slug, $result, true)) {
                $result[] = $slug;
            }
        }

        return $result;
    }

    public static function current_roles(): array {
        $user = wp_get_current_user();
        return is_array($user->roles) ? array_values($user->roles) : [];
    }

    public static function roles_allow(array $rule, array $user_roles): bool {
        if (!isset($rule['roles']) || !is_array($rule['roles']) || !$rule['roles']) {
            return true;
        }

        return (bool) array_intersect($rule['roles'], $user_roles);
    }
}
