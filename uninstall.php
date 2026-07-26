<?php
/**
 * SACCI Island Admin uninstall handler.
 *
 * Settings are kept by default to prevent accidental loss during a plugin
 * replacement or upgrade. Define SACCI_REMOVE_DATA_ON_UNINSTALL as true before
 * uninstalling when permanent removal is explicitly required.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!defined('SACCI_REMOVE_DATA_ON_UNINSTALL') || SACCI_REMOVE_DATA_ON_UNINSTALL !== true) {
    return;
}

delete_option('sacci_island_admin_settings');
delete_option('sacci_island_admin_menu_manifest');
delete_option('sacci_island_admin_version');
delete_option('sacci_rbac_schema_version');
delete_option('sacci_island_admin_audit_log');

$administrator = get_role('administrator');
if ($administrator) {
    $administrator->remove_cap('manage_sacci_island_admin');
}
