<?php
/**
 * Plugin Name: SACCI Parish Administration Suite
 * Plugin URI: https://lerionjakenwauda.com/
 * Description: A responsive island-architecture WordPress admin shell with parish branding, menu customisation, drag ordering, role-based menu visibility, strict route protection and a redesigned dashboard.
 * Version: 2.1.4
 * Author: Lerion Jake Nwauda Digital Innovations
 * Author URI: https://lerionjakenwauda.com/
 * Text Domain: sacci-island-admin
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SACCI_ISLAND_VERSION', '2.1.4');
define('SACCI_ISLAND_FILE', __FILE__);
define('SACCI_ISLAND_DIR', plugin_dir_path(__FILE__));
define('SACCI_ISLAND_URL', plugin_dir_url(__FILE__));
define('SACCI_ISLAND_OPTION', 'sacci_island_admin_settings');
define('SACCI_ISLAND_MANIFEST_OPTION', 'sacci_island_admin_menu_manifest');
define('SACCI_ISLAND_VERSION_OPTION', 'sacci_island_admin_version');
define('SACCI_ISLAND_RBAC_SCHEMA_VERSION', '1.0.0');
define('SACCI_ISLAND_RBAC_SCHEMA_OPTION', 'sacci_rbac_schema_version');
define('SACCI_ISLAND_AUDIT_OPTION', 'sacci_island_admin_audit_log');
define('SACCI_ISLAND_GITHUB_REPO', 'lerionjakenwauda/sacci-island-admin');

require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-audit-log.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-rbac.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-white-label.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-updater.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-settings.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-menu.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-shell.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-dashboard.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-access.php';
require_once SACCI_ISLAND_DIR . 'includes/class-sacci-island-entry-route.php';

final class SACCI_Island_Admin_Plugin {
    public static function boot(): void {
        add_action('plugins_loaded', [__CLASS__, 'load_textdomain']);

        add_action('admin_init', [__CLASS__, 'maybe_upgrade'], 1);

        SACCI_Island_RBAC::hooks();
        SACCI_Island_Audit_Log::hooks();
        SACCI_Island_White_Label::hooks();
        SACCI_Island_Updater::hooks();
        SACCI_Island_Entry_Route::hooks();
        SACCI_Island_Settings::hooks();
        SACCI_Island_Menu::hooks();
        SACCI_Island_Shell::hooks();
        SACCI_Island_Dashboard::hooks();
        SACCI_Island_Access::hooks();
    }


    public static function maybe_upgrade(): void {
        $installed = (string) get_option(
            SACCI_ISLAND_VERSION_OPTION,
            '1.0.0'
        );

        if (version_compare($installed, SACCI_ISLAND_VERSION, '>=')) {
            return;
        }

        $defaults = SACCI_Island_Settings::defaults();
        $saved = get_option(SACCI_ISLAND_OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        /*
         * Preserve menu ordering, custom labels, icons and role rules while
         * replacing the previous experimental shell with the approved,
         * connected light administration layout.
         */
        $preserved = [
            'brand_name',
            'brand_tagline',
            'logo_id',
            'compact',
            'dashboard_welcome',
            'focus_dashboard',
            'strict_guard',
            'show_footer_brand',
            'menu_order',
            'menu_rules',
            'submenu_rules',
            'route_settings',
        ];

        $migrated = $defaults;

        foreach ($preserved as $key) {
            if (array_key_exists($key, $saved)) {
                $migrated[$key] = $saved[$key];
            }
        }

        update_option(SACCI_ISLAND_OPTION, $migrated, false);
        update_option(
            SACCI_ISLAND_VERSION_OPTION,
            SACCI_ISLAND_VERSION,
            false
        );

        SACCI_Island_RBAC::install_or_upgrade();
        SACCI_Island_Entry_Route::register_rewrite_rule();
        SACCI_Island_White_Label::rewrite_rules();
        flush_rewrite_rules(false);
    }

    public static function load_textdomain(): void {
        load_plugin_textdomain(
            'sacci-island-admin',
            false,
            dirname(plugin_basename(SACCI_ISLAND_FILE)) . '/languages'
        );
    }

    public static function activate(): void {
        if (!get_option(SACCI_ISLAND_OPTION)) {
            add_option(SACCI_ISLAND_OPTION, SACCI_Island_Settings::defaults(), '', false);
        }

        update_option(SACCI_ISLAND_VERSION_OPTION, SACCI_ISLAND_VERSION, false);
        SACCI_Island_RBAC::install_or_upgrade(true);
        SACCI_Island_Entry_Route::register_rewrite_rule();
        SACCI_Island_White_Label::rewrite_rules();
        flush_rewrite_rules(false);

        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap('manage_sacci_island_admin');
        }
    }

    public static function deactivate(): void {
        // Design settings and access rules intentionally remain available.
        flush_rewrite_rules(false);
    }
}

register_activation_hook(SACCI_ISLAND_FILE, ['SACCI_Island_Admin_Plugin', 'activate']);
register_deactivation_hook(SACCI_ISLAND_FILE, ['SACCI_Island_Admin_Plugin', 'deactivate']);

SACCI_Island_Admin_Plugin::boot();
