<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Entry_Route {
    private const QUERY_VAR = 'sacci_parish_office';

    public static function hooks(): void {
        add_action('init', [__CLASS__, 'register_rewrite_rule']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_redirect']);
    }

    public static function register_rewrite_rule(): void {
        add_rewrite_rule(
            '^parish-office/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );

        add_rewrite_rule(
            '^sacci-admin/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public static function query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function template_redirect(): void {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        $path = isset($_SERVER['REQUEST_URI'])
            ? wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH)
            : '/parish-office/';
        $return_url = str_contains((string) $path, 'sacci-admin')
            ? home_url('/sacci-admin/')
            : home_url('/parish-office/');

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($return_url));
            exit;
        }

        wp_safe_redirect(self::destination_for_current_user());
        exit;
    }

    public static function destination_for_current_user(): string {
        if (
            current_user_can('sacci_manage_roles') ||
            current_user_can('sacci_manage_admin_design') ||
            current_user_can('sacci_manage_menu_access') ||
            current_user_can('sacci_manage_pages') ||
            current_user_can('sacci_view_parish_overview') ||
            current_user_can('manage_options')
        ) {
            return admin_url('index.php');
        }

        if (current_user_can('sacci_manage_events')) {
            return post_type_exists('sacci_event')
                ? admin_url('edit.php?post_type=sacci_event')
                : admin_url('index.php');
        }

        if (current_user_can('sacci_manage_bulletins')) {
            return post_type_exists('sacci_bulletin')
                ? admin_url('edit.php?post_type=sacci_bulletin')
                : admin_url('index.php');
        }

        if (current_user_can('sacci_manage_announcements')) {
            return post_type_exists('sacci_announcement')
                ? admin_url('edit.php?post_type=sacci_announcement')
                : admin_url('index.php');
        }

        if (current_user_can('sacci_manage_media') || current_user_can('upload_files')) {
            return admin_url('upload.php');
        }

        if (current_user_can('sacci_manage_office_bookings')) {
            return admin_url('admin.php?page=sacci-office-bookings');
        }

        if (current_user_can('read')) {
            return admin_url('index.php');
        }

        return home_url('/');
    }
}
