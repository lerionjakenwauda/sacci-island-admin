<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_RBAC {
    public static function hooks(): void {
        add_filter('register_post_type_args', [__CLASS__, 'post_type_capabilities'], 20, 2);
        add_action('admin_post_sacci_island_reset_role_preset', [__CLASS__, 'reset_role_preset_action']);
        add_action('admin_post_sacci_island_export_settings', [__CLASS__, 'export_settings']);
        add_action('admin_post_sacci_island_import_settings', [__CLASS__, 'import_settings']);
        add_action('wp_ajax_sacci_island_audit_ping', [__CLASS__, 'ajax_permission_check']);
        add_filter('rest_authentication_errors', [__CLASS__, 'rest_permission_check']);
    }

    public static function install_or_upgrade(bool $force = false): void {
        $installed = (string) get_option(SACCI_ISLAND_RBAC_SCHEMA_OPTION, '');

        if (!$force && version_compare($installed, SACCI_ISLAND_RBAC_SCHEMA_VERSION, '>=')) {
            return;
        }

        foreach (self::role_presets() as $slug => $preset) {
            $role = get_role($slug);

            if (!$role) {
                add_role($slug, $preset['name'], ['read' => true]);
                $role = get_role($slug);

                SACCI_Island_Audit_Log::record(
                    'role_created',
                    'role',
                    $slug,
                    ['display_name' => $preset['name']]
                );
            }

            if (!$role) {
                continue;
            }

            foreach ($preset['caps'] as $capability) {
                if (!$role->has_cap($capability)) {
                    $role->add_cap($capability);
                }
            }
        }

        $administrator = get_role('administrator');

        if ($administrator) {
            foreach (self::capability_registry() as $capability => $label) {
                if (!$administrator->has_cap($capability)) {
                    $administrator->add_cap($capability);
                }
            }

            if (!$administrator->has_cap('manage_sacci_island_admin')) {
                $administrator->add_cap('manage_sacci_island_admin');
            }
        }

        update_option(SACCI_ISLAND_RBAC_SCHEMA_OPTION, SACCI_ISLAND_RBAC_SCHEMA_VERSION, false);
    }

    public static function capability_registry(): array {
        return [
            'sacci_access_parish_admin'          => __('Access parish administration', 'sacci-island-admin'),
            'sacci_view_parish_overview'         => __('View Parish Overview', 'sacci-island-admin'),
            'sacci_manage_announcements'         => __('Manage announcements module', 'sacci-island-admin'),
            'sacci_create_announcements'         => __('Create announcements', 'sacci-island-admin'),
            'sacci_edit_announcements'           => __('Edit own announcements', 'sacci-island-admin'),
            'sacci_edit_others_announcements'    => __('Edit all announcements', 'sacci-island-admin'),
            'sacci_publish_announcements'        => __('Publish announcements', 'sacci-island-admin'),
            'sacci_delete_announcements'         => __('Delete announcements', 'sacci-island-admin'),
            'sacci_manage_events'                => __('Manage parish events module', 'sacci-island-admin'),
            'sacci_create_events'                => __('Create parish events', 'sacci-island-admin'),
            'sacci_edit_events'                  => __('Edit own parish events', 'sacci-island-admin'),
            'sacci_edit_others_events'           => __('Edit all parish events', 'sacci-island-admin'),
            'sacci_publish_events'               => __('Publish parish events', 'sacci-island-admin'),
            'sacci_delete_events'                => __('Delete parish events', 'sacci-island-admin'),
            'sacci_manage_bulletins'             => __('Manage parish bulletins module', 'sacci-island-admin'),
            'sacci_create_bulletins'             => __('Create parish bulletins', 'sacci-island-admin'),
            'sacci_edit_bulletins'               => __('Edit own parish bulletins', 'sacci-island-admin'),
            'sacci_edit_others_bulletins'        => __('Edit all parish bulletins', 'sacci-island-admin'),
            'sacci_publish_bulletins'            => __('Publish parish bulletins', 'sacci-island-admin'),
            'sacci_delete_bulletins'             => __('Delete parish bulletins', 'sacci-island-admin'),
            'sacci_manage_media'                 => __('Manage media library', 'sacci-island-admin'),
            'sacci_manage_pages'                 => __('Manage pages', 'sacci-island-admin'),
            'sacci_manage_users'                 => __('Manage parish users', 'sacci-island-admin'),
            'sacci_manage_roles'                 => __('Manage parish roles', 'sacci-island-admin'),
            'sacci_manage_menu_access'           => __('Manage menu access', 'sacci-island-admin'),
            'sacci_manage_admin_design'          => __('Manage admin design', 'sacci-island-admin'),
            'sacci_manage_parish_settings'       => __('Manage parish settings', 'sacci-island-admin'),
            'sacci_manage_office_bookings'       => __('Manage office bookings', 'sacci-island-admin'),
            'sacci_view_private_reports'         => __('View private reports', 'sacci-island-admin'),
            'sacci_view_audit_log'               => __('View audit log', 'sacci-island-admin'),
        ];
    }

    public static function role_presets(): array {
        $all = array_keys(self::capability_registry());

        return [
            'sacci_parish_administrator' => [
                'name' => __('Parish Administrator', 'sacci-island-admin'),
                'caps' => array_values(array_unique(array_merge(
                    ['read', 'upload_files', 'edit_pages', 'edit_others_pages', 'publish_pages', 'delete_pages'],
                    $all
                ))),
            ],
            'sacci_communications_manager' => [
                'name' => __('Parish Communications Manager', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'upload_files',
                    'edit_pages',
                    'edit_others_pages',
                    'publish_pages',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_manage_announcements',
                    'sacci_create_announcements',
                    'sacci_edit_announcements',
                    'sacci_edit_others_announcements',
                    'sacci_publish_announcements',
                    'sacci_manage_events',
                    'sacci_create_events',
                    'sacci_edit_events',
                    'sacci_edit_others_events',
                    'sacci_publish_events',
                    'sacci_manage_bulletins',
                    'sacci_create_bulletins',
                    'sacci_edit_bulletins',
                    'sacci_edit_others_bulletins',
                    'sacci_publish_bulletins',
                    'sacci_manage_media',
                    'sacci_manage_pages',
                ],
            ],
            'sacci_events_manager' => [
                'name' => __('Parish Events Manager', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'upload_files',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_manage_events',
                    'sacci_create_events',
                    'sacci_edit_events',
                    'sacci_edit_others_events',
                    'sacci_publish_events',
                    'sacci_delete_events',
                    'sacci_manage_media',
                ],
            ],
            'sacci_announcement_editor' => [
                'name' => __('Announcement Editor', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'upload_files',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_manage_announcements',
                    'sacci_create_announcements',
                    'sacci_edit_announcements',
                    'sacci_manage_media',
                ],
            ],
            'sacci_bulletin_editor' => [
                'name' => __('Bulletin Editor', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'upload_files',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_manage_bulletins',
                    'sacci_create_bulletins',
                    'sacci_edit_bulletins',
                    'sacci_manage_media',
                ],
            ],
            'sacci_media_manager' => [
                'name' => __('Parish Media Manager', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'upload_files',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_manage_media',
                ],
            ],
            'sacci_parish_secretary' => [
                'name' => __('Parish Secretary', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'upload_files',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_manage_office_bookings',
                    'sacci_manage_announcements',
                    'sacci_create_announcements',
                    'sacci_edit_announcements',
                    'sacci_manage_media',
                ],
            ],
            'sacci_content_reviewer' => [
                'name' => __('Content Reviewer', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_edit_announcements',
                    'sacci_edit_events',
                    'sacci_edit_bulletins',
                    'sacci_view_private_reports',
                ],
            ],
            'sacci_readonly_auditor' => [
                'name' => __('Parish Auditor', 'sacci-island-admin'),
                'caps' => [
                    'read',
                    'sacci_access_parish_admin',
                    'sacci_view_parish_overview',
                    'sacci_view_audit_log',
                    'sacci_view_private_reports',
                ],
            ],
        ];
    }

    public static function module_matrix(): array {
        return [
            'overview' => [
                'label' => __('Parish Overview', 'sacci-island-admin'),
                'caps'  => ['sacci_access_parish_admin', 'sacci_view_parish_overview'],
            ],
            'announcements' => [
                'label' => __('Announcements', 'sacci-island-admin'),
                'caps'  => [
                    'sacci_manage_announcements',
                    'sacci_create_announcements',
                    'sacci_edit_announcements',
                    'sacci_edit_others_announcements',
                    'sacci_publish_announcements',
                    'sacci_delete_announcements',
                ],
            ],
            'events' => [
                'label' => __('Parish Events', 'sacci-island-admin'),
                'caps'  => [
                    'sacci_manage_events',
                    'sacci_create_events',
                    'sacci_edit_events',
                    'sacci_edit_others_events',
                    'sacci_publish_events',
                    'sacci_delete_events',
                ],
            ],
            'bulletins' => [
                'label' => __('Parish Bulletins', 'sacci-island-admin'),
                'caps'  => [
                    'sacci_manage_bulletins',
                    'sacci_create_bulletins',
                    'sacci_edit_bulletins',
                    'sacci_edit_others_bulletins',
                    'sacci_publish_bulletins',
                    'sacci_delete_bulletins',
                ],
            ],
            'media' => [
                'label' => __('Media Library', 'sacci-island-admin'),
                'caps'  => ['upload_files', 'sacci_manage_media'],
            ],
            'pages' => [
                'label' => __('Website Pages', 'sacci-island-admin'),
                'caps'  => ['edit_pages', 'edit_others_pages', 'publish_pages', 'sacci_manage_pages'],
            ],
            'users_roles' => [
                'label' => __('Users, Roles and Access', 'sacci-island-admin'),
                'caps'  => ['list_users', 'edit_users', 'sacci_manage_users', 'sacci_manage_roles', 'sacci_manage_menu_access'],
            ],
            'admin_design' => [
                'label' => __('Island Admin Design', 'sacci-island-admin'),
                'caps'  => ['sacci_manage_admin_design', 'sacci_manage_parish_settings'],
            ],
            'office' => [
                'label' => __('Office Bookings', 'sacci-island-admin'),
                'caps'  => ['sacci_manage_office_bookings'],
            ],
            'audit' => [
                'label' => __('Audit Log', 'sacci-island-admin'),
                'caps'  => ['sacci_view_audit_log', 'sacci_view_private_reports'],
            ],
        ];
    }

    public static function post_type_capabilities(array $args, string $post_type): array {
        $map = [
            'sacci_event' => [
                'singular' => 'sacci_event',
                'plural'   => 'sacci_events',
                'module'   => 'events',
            ],
            'sacci_announcement' => [
                'singular' => 'sacci_announcement',
                'plural'   => 'sacci_announcements',
                'module'   => 'announcements',
            ],
            'sacci_bulletin' => [
                'singular' => 'sacci_bulletin',
                'plural'   => 'sacci_bulletins',
                'module'   => 'bulletins',
            ],
        ];

        if (!isset($map[$post_type])) {
            return $args;
        }

        $module = $map[$post_type]['module'];

        $args['capability_type'] = [$map[$post_type]['singular'], $map[$post_type]['plural']];
        $args['map_meta_cap'] = true;
        $args['capabilities'] = [
            'edit_post'              => 'sacci_edit_' . $module,
            'read_post'              => 'sacci_view_parish_overview',
            'delete_post'            => 'sacci_delete_' . $module,
            'edit_posts'             => 'sacci_edit_' . $module,
            'edit_others_posts'      => 'sacci_edit_others_' . $module,
            'delete_posts'           => 'sacci_delete_' . $module,
            'publish_posts'          => 'sacci_publish_' . $module,
            'read_private_posts'     => 'sacci_manage_' . $module,
            'delete_private_posts'   => 'sacci_delete_' . $module,
            'delete_published_posts' => 'sacci_delete_' . $module,
            'delete_others_posts'    => 'sacci_delete_' . $module,
            'edit_private_posts'     => 'sacci_edit_others_' . $module,
            'edit_published_posts'   => 'sacci_edit_' . $module,
            'create_posts'           => 'sacci_create_' . $module,
        ];

        return $args;
    }

    public static function role_slugs(): array {
        return array_keys(wp_roles()->roles);
    }

    public static function can_manage_roles(): bool {
        return current_user_can('sacci_manage_roles') || current_user_can('manage_options');
    }

    public static function can_manage_design(): bool {
        return current_user_can('sacci_manage_admin_design') || current_user_can('manage_options');
    }

    public static function save_role_capabilities(array $raw): void {
        if (!self::can_manage_roles()) {
            return;
        }

        $roles = wp_roles();
        $allowed_caps = array_keys(self::capability_registry());
        $native_caps = [
            'read',
            'upload_files',
            'edit_pages',
            'edit_others_pages',
            'publish_pages',
            'delete_pages',
            'list_users',
            'edit_users',
        ];
        $allowed_caps = array_values(array_unique(array_merge($allowed_caps, $native_caps)));

        foreach ($roles->roles as $role_slug => $role_data) {
            $role = get_role((string) $role_slug);

            if (!$role) {
                continue;
            }

            if ((string) $role_slug === 'administrator') {
                foreach (['manage_options', 'sacci_manage_roles', 'sacci_manage_admin_design'] as $required_cap) {
                    $role->add_cap($required_cap);
                }

                continue;
            }

            $requested = isset($raw[$role_slug]) && is_array($raw[$role_slug])
                ? $raw[$role_slug]
                : [];

            foreach ($allowed_caps as $capability) {
                $enabled = !empty($requested[$capability]);

                if ($enabled) {
                    $role->add_cap($capability);
                    continue;
                }

                $role->remove_cap($capability);
            }
        }

        SACCI_Island_Audit_Log::record('role_updated', 'rbac_matrix', 0, []);
    }

    public static function reset_role_to_preset(string $role_slug): void {
        if (!self::can_manage_roles()) {
            return;
        }

        $presets = self::role_presets();

        if (!isset($presets[$role_slug])) {
            return;
        }

        $role = get_role($role_slug);

        if (!$role) {
            return;
        }

        foreach (array_keys(self::capability_registry()) as $capability) {
            $role->remove_cap($capability);
        }

        foreach ($presets[$role_slug]['caps'] as $capability) {
            $role->add_cap($capability);
        }

        SACCI_Island_Audit_Log::record('role_updated', 'role', $role_slug, ['preset_reset' => true]);
    }

    public static function reset_role_preset_action(): void {
        if (!self::can_manage_roles()) {
            SACCI_Island_Access::deny_or_redirect('sacci_manage_roles');
        }

        check_admin_referer('sacci_island_reset_role_preset');

        $role = isset($_GET['role']) ? sanitize_key(wp_unslash($_GET['role'])) : '';
        self::reset_role_to_preset($role);

        wp_safe_redirect(add_query_arg([
            'page'  => 'sacci-island-admin',
            'tab'   => 'access',
            'reset' => 'role',
        ], admin_url('admin.php')));
        exit;
    }

    public static function export_settings(): void {
        if (!self::can_manage_roles()) {
            SACCI_Island_Access::deny_or_redirect('sacci_manage_roles');
        }

        check_admin_referer('sacci_island_export_settings');

        $payload = [
            'version'       => SACCI_ISLAND_VERSION,
            'settings'      => SACCI_Island_Settings::get(),
            'roles'         => self::export_role_caps(),
            'exported_at'   => wp_date('c'),
        ];

        SACCI_Island_Audit_Log::record('settings_exported', 'settings', 0, []);

        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename=sacci-island-admin-settings.json');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT);
        exit;
    }

    public static function import_settings(): void {
        if (!self::can_manage_roles()) {
            SACCI_Island_Access::deny_or_redirect('sacci_manage_roles');
        }

        check_admin_referer('sacci_island_import_settings');

        if (empty($_FILES['sacci_import_file']['tmp_name'])) {
            wp_safe_redirect(add_query_arg(['page' => 'sacci-island-admin', 'tab' => 'access', 'import' => 'missing'], admin_url('admin.php')));
            exit;
        }

        $raw = file_get_contents((string) $_FILES['sacci_import_file']['tmp_name']);
        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            wp_safe_redirect(add_query_arg(['page' => 'sacci-island-admin', 'tab' => 'access', 'import' => 'invalid'], admin_url('admin.php')));
            exit;
        }

        if (isset($decoded['roles']) && is_array($decoded['roles'])) {
            self::save_role_capabilities($decoded['roles']);
        }

        SACCI_Island_Audit_Log::record('settings_changed', 'settings', 0, ['import' => true]);

        wp_safe_redirect(add_query_arg(['page' => 'sacci-island-admin', 'tab' => 'access', 'import' => 'complete'], admin_url('admin.php')));
        exit;
    }

    public static function ajax_permission_check(): void {
        if (!current_user_can('sacci_view_audit_log')) {
            wp_send_json_error(['message' => __('You cannot access this audit endpoint.', 'sacci-island-admin')], 403);
        }

        check_ajax_referer('sacci_island_audit_ping');
        wp_send_json_success(['status' => 'ok']);
    }

    public static function rest_permission_check($result) {
        if ($result !== null && $result !== true) {
            return $result;
        }

        $route = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (!is_user_logged_in() || !str_contains($route, 'sacci')) {
            return $result;
        }

        if (
            current_user_can('sacci_access_parish_admin') ||
            current_user_can('manage_options')
        ) {
            return $result;
        }

        SACCI_Island_Audit_Log::record('failed_permission_attempt', 'rest', 0, ['route' => $route]);

        return new WP_Error(
            'sacci_rest_forbidden',
            __('You do not have permission to use this parish administration endpoint.', 'sacci-island-admin'),
            ['status' => 403]
        );
    }

    public static function export_role_caps(): array {
        $export = [];
        $allowed = array_values(array_unique(array_merge(
            array_keys(self::capability_registry()),
            ['read', 'upload_files', 'edit_pages', 'edit_others_pages', 'publish_pages', 'delete_pages', 'list_users', 'edit_users']
        )));

        foreach (wp_roles()->roles as $role_slug => $role_data) {
            $role = get_role((string) $role_slug);

            if (!$role) {
                continue;
            }

            foreach ($allowed as $capability) {
                if ($role->has_cap($capability)) {
                    $export[$role_slug][$capability] = 1;
                }
            }
        }

        return $export;
    }

    public static function capability_for_menu_slug(string $slug, string $fallback = 'read'): string {
        $slug = strtolower($slug);

        if (str_contains($slug, 'sacci_event')) {
            return 'sacci_manage_events';
        }

        if (str_contains($slug, 'sacci_announcement')) {
            return 'sacci_manage_announcements';
        }

        if (str_contains($slug, 'sacci_bulletin')) {
            return 'sacci_manage_bulletins';
        }

        if (str_contains($slug, 'upload.php')) {
            return 'sacci_manage_media';
        }

        if (str_contains($slug, 'edit.php?post_type=page') || $slug === 'edit.php') {
            return 'sacci_manage_pages';
        }

        if (str_contains($slug, 'users.php') || str_contains($slug, 'user-new.php')) {
            return 'sacci_manage_users';
        }

        if (str_contains($slug, 'sacci-island-admin')) {
            return 'sacci_manage_admin_design';
        }

        if (str_contains($slug, 'plugins.php') || str_contains($slug, 'themes.php') || str_contains($slug, 'tools.php')) {
            return 'manage_options';
        }

        return $fallback !== '' ? $fallback : 'read';
    }
}
