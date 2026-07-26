<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Settings {
    private const NONCE_ACTION = 'sacci_island_save_settings';
    private const NONCE_NAME = 'sacci_island_nonce';

    public static function hooks(): void {
        add_action('admin_menu', [__CLASS__, 'register_page'], 120);
        add_action('admin_post_sacci_island_save', [__CLASS__, 'save']);
        add_action('admin_post_sacci_island_reset', [__CLASS__, 'reset']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('plugin_action_links_' . plugin_basename(SACCI_ISLAND_FILE), [__CLASS__, 'plugin_links']);
    }

    public static function defaults(): array {
        return [
            'brand_name'        => 'St. Augustine MaryHill Ikorodu',
            'brand_tagline'     => 'Parish Administration',
            'logo_id'           => 0,
            'primary'           => '#0B5D2A',
            'primary_deep'      => '#073E1C',
            'accent'            => '#D99518',
            'surface'           => '#F3F0E3',
            'card'              => '#FFFDF7',
            'text'              => '#183126',
            'radius'            => 18,
            'rail_width'        => 0,
            'sidebar_width'     => 304,
            'header_height'     => 94,
            'compact'           => 0,
            'appearance_mode'   => 'light',
            'dashboard_welcome' => 1,
            'focus_dashboard'   => 1,
            'strict_guard'      => 1,
            'show_footer_brand' => 1,
            'menu_order'        => [],
            'menu_rules'        => [],
            'submenu_rules'     => [],
            'route_settings'    => [],
        ];
    }

    public static function get(): array {
        $saved = get_option(SACCI_ISLAND_OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function register_page(): void {
        add_menu_page(
            __('Island Admin', 'sacci-island-admin'),
            __('Island Admin', 'sacci-island-admin'),
            'sacci_manage_admin_design',
            'sacci-island-admin',
            [__CLASS__, 'render'],
            'dashicons-layout',
            81
        );
    }

    public static function enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'toplevel_page_sacci-island-admin') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_style(
            'sacci-island-settings',
            SACCI_ISLAND_URL . 'assets/css/settings.css',
            [],
            SACCI_ISLAND_VERSION . '.' . (string) filemtime(SACCI_ISLAND_DIR . 'assets/css/settings.css')
        );

        wp_enqueue_script(
            'sacci-island-settings',
            SACCI_ISLAND_URL . 'assets/js/settings.js',
            ['jquery', 'jquery-ui-sortable'],
            SACCI_ISLAND_VERSION . '.' . (string) filemtime(SACCI_ISLAND_DIR . 'assets/js/settings.js'),
            true
        );

        wp_localize_script('sacci-island-settings', 'SACCIIslandSettings', [
            'mediaTitle'  => __('Choose the parish admin logo', 'sacci-island-admin'),
            'mediaButton' => __('Use this logo', 'sacci-island-admin'),
            'defaultLogo' => SACCI_ISLAND_URL . 'assets/images/parish-mark.png',
        ]);
    }

    public static function plugin_links(array $links): array {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('admin.php?page=sacci-island-admin')) . '">' .
            esc_html__('Configure', 'sacci-island-admin') .
            '</a>'
        );

        $links[] = '<a href="https://lerionjakenwauda.com/" target="_blank" rel="noopener noreferrer">' .
            esc_html__('Developer', 'sacci-island-admin') .
            '</a>';

        return $links;
    }

    public static function render(): void {
        if (!SACCI_Island_RBAC::can_manage_design()) {
            wp_die(esc_html__('You do not have permission to manage the admin shell.', 'sacci-island-admin'));
        }

        $settings = self::get();
        $manifest = SACCI_Island_Menu::manifest();
        $roles = wp_roles()->roles;
        $logo_url = self::logo_url($settings);
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'appearance';
        $allowed_tabs = ['appearance', 'navigation', 'access', 'routes', 'audit'];

        if (!SACCI_Island_RBAC::can_manage_roles()) {
            $allowed_tabs = ['appearance', 'navigation', 'routes'];
        }

        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'appearance';
        }

        ?>
        <div class="wrap sacci-island-studio">
            <section class="sacci-island-studio__hero">
                <div>
                    <p><?php esc_html_e('St. Augustine’s Catholic Church', 'sacci-island-admin'); ?></p>
                    <h1><?php esc_html_e('Island Admin Studio', 'sacci-island-admin'); ?></h1>
                    <span><?php esc_html_e('Shape the WordPress dashboard, navigation and staff access without editing theme files.', 'sacci-island-admin'); ?></span>
                </div>

                <div class="sacci-island-studio__mini-shell" aria-hidden="true">
                    <aside>
                        <i></i><i></i><i></i><i></i>
                    </aside>
                    <main>
                        <header></header>
                        <section><b></b><b></b><b></b></section>
                        <article></article>
                    </main>
                </div>
            </section>

            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Island Admin settings saved.', 'sacci-island-admin'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Island Admin settings were reset to their defaults.', 'sacci-island-admin'); ?></p>
                </div>
            <?php endif; ?>

            <nav class="sacci-island-tabs" aria-label="<?php esc_attr_e('Island Admin settings sections', 'sacci-island-admin'); ?>">
                <?php
                $tabs = [
                    'appearance' => [__('Appearance', 'sacci-island-admin'), 'dashicons-art'],
                    'navigation' => [__('Menu Studio', 'sacci-island-admin'), 'dashicons-menu-alt3'],
                    'access'     => [__('Roles & Access', 'sacci-island-admin'), 'dashicons-shield-alt'],
                    'routes'     => [__('Route Settings', 'sacci-island-admin'), 'dashicons-admin-links'],
                    'audit'      => [__('Audit Log', 'sacci-island-admin'), 'dashicons-visibility'],
                ];

                foreach ($tabs as $slug => [$label, $icon]) :
                    if (!in_array($slug, $allowed_tabs, true)) {
                        continue;
                    }
                    ?>
                    <a
                        class="<?php echo $active_tab === $slug ? 'is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg(['page' => 'sacci-island-admin', 'tab' => $slug], admin_url('admin.php'))); ?>"
                    >
                        <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" class="sacci-island-form">
                <input type="hidden" name="action" value="sacci_island_save">
                <input type="hidden" name="return_tab" value="<?php echo esc_attr($active_tab); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                <?php if ($active_tab === 'appearance') : ?>
                    <?php self::render_appearance($settings, $logo_url); ?>
                <?php elseif ($active_tab === 'navigation') : ?>
                    <?php self::render_navigation($settings, $manifest); ?>
                <?php elseif ($active_tab === 'access') : ?>
                    <?php self::render_access($settings, $manifest, $roles); ?>
                <?php elseif ($active_tab === 'routes') : ?>
                    <?php self::render_routes($settings); ?>
                <?php else : ?>
                    <?php self::render_audit_log(); ?>
                <?php endif; ?>

                <footer class="sacci-island-form__footer">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Save Changes', 'sacci-island-admin'); ?>
                    </button>

                    <a
                        class="button button-secondary button-hero"
                        href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=sacci_island_reset'), 'sacci_island_reset')); ?>"
                        data-sacci-confirm-reset
                    >
                        <?php esc_html_e('Reset Design', 'sacci-island-admin'); ?>
                    </a>

                    <a href="https://lerionjakenwauda.com/" target="_blank" rel="noopener noreferrer" class="sacci-island-developer">
                        <?php esc_html_e('Lerion Jake Nwauda Digital Innovations', 'sacci-island-admin'); ?>
                        <span aria-hidden="true">↗</span>
                    </a>
                </footer>
            </form>
        </div>
        <?php
    }

    private static function render_appearance(array $settings, string $logo_url): void {
        ?>
        <div class="sacci-island-layout">
            <section class="sacci-island-panel">
                <header>
                    <p><?php esc_html_e('Parish Identity', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Build the parish header lockup', 'sacci-island-admin'); ?></h2>
                </header>

                <div class="sacci-island-brand-grid">
                    <div class="sacci-island-logo-control" data-sacci-logo-control>
                        <div class="sacci-island-logo-preview" data-sacci-logo-preview>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="">
                        </div>
                        <input type="hidden" name="settings[logo_id]" value="<?php echo esc_attr((string) absint($settings['logo_id'])); ?>" data-sacci-logo-id>
                        <button type="button" class="button button-secondary" data-sacci-select-logo>
                            <?php esc_html_e('Choose Parish Mark', 'sacci-island-admin'); ?>
                        </button>
                        <button type="button" class="button-link-delete" data-sacci-remove-logo <?php echo empty($settings['logo_id']) ? 'hidden' : ''; ?>>
                            <?php esc_html_e('Use bundled parish mark', 'sacci-island-admin'); ?>
                        </button>
                    </div>

                    <div>
                        <label class="sacci-island-field">
                            <span><?php esc_html_e('Church name beside the mark', 'sacci-island-admin'); ?></span>
                            <input type="text" name="settings[brand_name]" value="<?php echo esc_attr((string) $settings['brand_name']); ?>">
                        </label>

                        <label class="sacci-island-field">
                            <span><?php esc_html_e('Small administration label', 'sacci-island-admin'); ?></span>
                            <input type="text" name="settings[brand_tagline]" value="<?php echo esc_attr((string) $settings['brand_tagline']); ?>">
                        </label>
                    </div>
                </div>
            </section>

            <aside class="sacci-island-preview" data-sacci-live-preview>
                <div class="sacci-island-preview__sidebar">
                    <div class="sacci-island-preview__brand">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="" data-preview-logo>
                        <strong data-preview-name><?php echo esc_html((string) $settings['brand_name']); ?></strong>
                        <small data-preview-tagline><?php echo esc_html((string) $settings['brand_tagline']); ?></small>
                    </div>
                    <i class="is-active"></i><i></i><i></i><i></i><i></i>
                </div>
                <div class="sacci-island-preview__content">
                    <div class="sacci-island-preview__bar"></div>
                    <div class="sacci-island-preview__cards"><b></b><b></b><b></b></div>
                    <div class="sacci-island-preview__surface"></div>
                </div>
            </aside>

            <section class="sacci-island-panel sacci-island-panel--wide">
                <header>
                    <p><?php esc_html_e('Material Palette', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Outer shell and content island', 'sacci-island-admin'); ?></h2>
                </header>

                <div class="sacci-island-colours">
                    <?php
                    $colour_fields = [
                        'primary'      => __('Primary green', 'sacci-island-admin'),
                        'primary_deep' => __('Deep green', 'sacci-island-admin'),
                        'accent'       => __('Ecclesial gold', 'sacci-island-admin'),
                        'surface'      => __('Outer application shell', 'sacci-island-admin'),
                        'card'         => __('Main content island', 'sacci-island-admin'),
                        'text'         => __('Text', 'sacci-island-admin'),
                    ];

                    foreach ($colour_fields as $key => $label) :
                        ?>
                        <label class="sacci-island-colour">
                            <span><?php echo esc_html($label); ?></span>
                            <div>
                                <input type="color" name="settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $settings[$key]); ?>" data-preview-colour="<?php echo esc_attr($key); ?>">
                                <code><?php echo esc_html((string) $settings[$key]); ?></code>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="sacci-island-ranges">
                    <label>
                        <span><?php esc_html_e('Parish Overview panel radius', 'sacci-island-admin'); ?></span>
                        <strong data-radius-output><?php echo esc_html((string) absint($settings['radius'])); ?>px</strong>
                        <input type="range" min="12" max="28" step="1" name="settings[radius]" value="<?php echo esc_attr((string) absint($settings['radius'])); ?>" data-radius-range>
                    </label>

                    <label>
                        <span><?php esc_html_e('Connected sidebar width', 'sacci-island-admin'); ?></span>
                        <strong data-sidebar-output><?php echo esc_html((string) absint($settings['sidebar_width'])); ?>px</strong>
                        <input type="range" min="276" max="340" step="5" name="settings[sidebar_width]" value="<?php echo esc_attr((string) absint($settings['sidebar_width'])); ?>" data-sidebar-range>
                    </label>
                </div>

                <div class="sacci-island-approved-shell">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <div>
                        <strong><?php esc_html_e('Approved light administration shell', 'sacci-island-admin'); ?></strong>
                        <p><?php esc_html_e('The header and sidebar form one connected inverse-L layout. Dark mode and detached navigation islands are disabled.', 'sacci-island-admin'); ?></p>
                    </div>
                </div>

                <div class="sacci-island-toggle-grid">
                    <?php self::toggle('compact', __('Compact administration spacing', 'sacci-island-admin'), $settings); ?>
                    <?php self::toggle('show_footer_brand', __('Show developer credit in the true admin footer', 'sacci-island-admin'), $settings); ?>
                </div>
            </section>
        </div>
        <?php
    }

    private static function render_navigation(array $settings, array $manifest): void {
        $rules = is_array($settings['menu_rules']) ? $settings['menu_rules'] : [];
        $order = is_array($settings['menu_order']) ? $settings['menu_order'] : [];
        $items = $manifest['top'] ?? [];

        usort($items, static function (array $a, array $b) use ($order): int {
            $a_pos = array_search($a['slug'], $order, true);
            $b_pos = array_search($b['slug'], $order, true);
            $a_pos = $a_pos === false ? PHP_INT_MAX : $a_pos;
            $b_pos = $b_pos === false ? PHP_INT_MAX : $b_pos;
            return $a_pos <=> $b_pos;
        });
        ?>
        <section class="sacci-island-panel sacci-island-panel--wide">
            <header class="sacci-island-panel__split">
                <div>
                    <p><?php esc_html_e('Navigation Studio', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Reorder, rename and simplify the menu', 'sacci-island-admin'); ?></h2>
                </div>
                <span><?php esc_html_e('Drag items using the handle. Empty labels preserve the original WordPress name.', 'sacci-island-admin'); ?></span>
            </header>

            <input type="hidden" name="settings[menu_order]" value="<?php echo esc_attr(implode(',', $order)); ?>" data-sacci-menu-order>

            <div class="sacci-island-menu-builder" data-sacci-menu-builder>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $slug = (string) $item['slug'];
                    $rule = isset($rules[$slug]) && is_array($rules[$slug]) ? $rules[$slug] : [];
                    ?>
                    <article data-menu-slug="<?php echo esc_attr($slug); ?>">
                        <button type="button" class="sacci-island-drag" aria-label="<?php esc_attr_e('Drag menu item', 'sacci-island-admin'); ?>">
                            <span class="dashicons dashicons-move"></span>
                        </button>

                        <div class="sacci-island-menu-icon">
                            <span class="dashicons <?php echo esc_attr(self::normalise_dashicon((string) ($rule['icon'] ?? $item['icon']))); ?>"></span>
                        </div>

                        <div class="sacci-island-menu-title">
                            <strong><?php echo esc_html((string) $item['label']); ?></strong>
                            <code><?php echo esc_html($slug); ?></code>
                        </div>

                        <label>
                            <span><?php esc_html_e('Custom label', 'sacci-island-admin'); ?></span>
                            <input
                                type="text"
                                name="settings[menu_rules][<?php echo esc_attr($slug); ?>][label]"
                                value="<?php echo esc_attr((string) ($rule['label'] ?? '')); ?>"
                                placeholder="<?php echo esc_attr((string) $item['label']); ?>"
                            >
                        </label>

                        <label>
                            <span><?php esc_html_e('Dashicon', 'sacci-island-admin'); ?></span>
                            <input
                                type="text"
                                name="settings[menu_rules][<?php echo esc_attr($slug); ?>][icon]"
                                value="<?php echo esc_attr((string) ($rule['icon'] ?? '')); ?>"
                                placeholder="dashicons-admin-generic"
                            >
                        </label>

                        <label>
                            <span><?php esc_html_e('Required capability', 'sacci-island-admin'); ?></span>
                            <input
                                type="text"
                                name="settings[menu_rules][<?php echo esc_attr($slug); ?>][capability]"
                                value="<?php echo esc_attr((string) ($rule['capability'] ?? SACCI_Island_RBAC::capability_for_menu_slug($slug, (string) ($item['capability'] ?? 'read')))); ?>"
                                placeholder="sacci_view_parish_overview"
                            >
                        </label>

                        <label class="sacci-island-hide-check">
                            <input
                                type="checkbox"
                                name="settings[menu_rules][<?php echo esc_attr($slug); ?>][hidden]"
                                value="1"
                                <?php checked(!empty($rule['hidden'])); ?>
                            >
                            <span><?php esc_html_e('Hide from sidebar', 'sacci-island-admin'); ?></span>
                        </label>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private static function render_access(array $settings, array $manifest, array $roles): void {
        $top_rules = is_array($settings['menu_rules']) ? $settings['menu_rules'] : [];
        $sub_rules = is_array($settings['submenu_rules']) ? $settings['submenu_rules'] : [];
        ?>
        <section class="sacci-island-panel sacci-island-panel--wide">
            <header class="sacci-island-panel__split">
                <div>
                    <p><?php esc_html_e('Role-Based Navigation', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Choose who can see and enter each admin area', 'sacci-island-admin'); ?></h2>
                </div>
                <div class="sacci-island-access-warning">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e('Administrators are always protected from lockout.', 'sacci-island-admin'); ?>
                </div>
            </header>

            <div class="sacci-island-toggle-grid sacci-island-toggle-grid--access">
                <?php self::toggle('strict_guard', __('Block direct URLs when a role is denied', 'sacci-island-admin'), $settings); ?>
            </div>

            <?php self::render_rbac_matrix($roles); ?>

            <div class="sacci-island-access-table">
                <div class="sacci-island-access-row sacci-island-access-row--head">
                    <span><?php esc_html_e('Admin menu', 'sacci-island-admin'); ?></span>
                    <?php foreach ($roles as $role_slug => $role_data) : ?>
                        <span><?php echo esc_html(translate_user_role($role_data['name'])); ?></span>
                    <?php endforeach; ?>
                </div>

                <?php foreach (($manifest['top'] ?? []) as $item) : ?>
                    <?php
                    $slug = (string) $item['slug'];
                    $rule = isset($top_rules[$slug]) && is_array($top_rules[$slug]) ? $top_rules[$slug] : [];
                    $allowed = isset($rule['roles']) && is_array($rule['roles']) ? $rule['roles'] : [];
                    ?>
                    <div class="sacci-island-access-row">
                        <span>
                            <strong><?php echo esc_html((string) $item['label']); ?></strong>
                            <code><?php echo esc_html($slug); ?></code>
                            <input
                                type="hidden"
                                name="settings[menu_rules][<?php echo esc_attr($slug); ?>][capability]"
                                value="<?php echo esc_attr((string) ($rule['capability'] ?? SACCI_Island_RBAC::capability_for_menu_slug($slug, (string) ($item['capability'] ?? 'read')))); ?>"
                            >
                        </span>

                        <?php foreach ($roles as $role_slug => $role_data) : ?>
                            <?php
                            $checked = empty($allowed) || in_array($role_slug, $allowed, true) || $role_slug === 'administrator';
                            ?>
                            <label title="<?php echo esc_attr(translate_user_role($role_data['name'])); ?>">
                                <input
                                    type="checkbox"
                                    name="settings[menu_rules][<?php echo esc_attr($slug); ?>][roles][]"
                                    value="<?php echo esc_attr($role_slug); ?>"
                                    <?php checked($checked); ?>
                                    <?php disabled($role_slug === 'administrator'); ?>
                                >
                                <span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach (($manifest['sub'][$slug] ?? []) as $subitem) : ?>
                        <?php
                        $subslug = (string) $subitem['slug'];
                        $subkey = $slug . '|' . $subslug;
                        $subrule = isset($sub_rules[$subkey]) && is_array($sub_rules[$subkey]) ? $sub_rules[$subkey] : [];
                        $suballowed = isset($subrule['roles']) && is_array($subrule['roles']) ? $subrule['roles'] : [];
                        ?>
                        <div class="sacci-island-access-row sacci-island-access-row--sub">
                            <span>
                                <strong><?php echo esc_html((string) $subitem['label']); ?></strong>
                                <code><?php echo esc_html($subslug); ?></code>
                                <input
                                    type="hidden"
                                    name="settings[submenu_rules][<?php echo esc_attr($subkey); ?>][capability]"
                                    value="<?php echo esc_attr((string) ($subrule['capability'] ?? SACCI_Island_RBAC::capability_for_menu_slug($subslug, (string) ($subitem['capability'] ?? 'read')))); ?>"
                                >
                            </span>

                            <?php foreach ($roles as $role_slug => $role_data) : ?>
                                <?php
                                $checked = empty($suballowed) || in_array($role_slug, $suballowed, true) || $role_slug === 'administrator';
                                ?>
                                <label title="<?php echo esc_attr(translate_user_role($role_data['name'])); ?>">
                                    <input
                                        type="checkbox"
                                        name="settings[submenu_rules][<?php echo esc_attr($subkey); ?>][roles][]"
                                        value="<?php echo esc_attr($role_slug); ?>"
                                        <?php checked($checked); ?>
                                        <?php disabled($role_slug === 'administrator'); ?>
                                    >
                                    <span></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private static function render_rbac_matrix(array $roles): void {
        $registry = SACCI_Island_RBAC::capability_registry();
        $modules = SACCI_Island_RBAC::module_matrix();
        $export_url = wp_nonce_url(
            admin_url('admin-post.php?action=sacci_island_export_settings'),
            'sacci_island_export_settings'
        );
        ?>
        <section class="sacci-island-rbac-tools">
            <div class="sacci-island-rbac-filters">
                <label>
                    <span><?php esc_html_e('Search roles', 'sacci-island-admin'); ?></span>
                    <input type="search" data-sacci-role-search placeholder="<?php esc_attr_e('Filter role columns', 'sacci-island-admin'); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Search permissions', 'sacci-island-admin'); ?></span>
                    <input type="search" data-sacci-permission-search placeholder="<?php esc_attr_e('Filter capability rows', 'sacci-island-admin'); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Filter module', 'sacci-island-admin'); ?></span>
                    <select data-sacci-module-filter>
                        <option value=""><?php esc_html_e('All modules', 'sacci-island-admin'); ?></option>
                        <?php foreach ($modules as $module_key => $module) : ?>
                            <option value="<?php echo esc_attr($module_key); ?>"><?php echo esc_html($module['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="sacci-island-rbac-actions">
                <select data-sacci-clone-from aria-label="<?php esc_attr_e('Clone from role', 'sacci-island-admin'); ?>">
                    <option value=""><?php esc_html_e('Clone from role', 'sacci-island-admin'); ?></option>
                    <?php foreach ($roles as $role_slug => $role_data) : ?>
                        <option value="<?php echo esc_attr($role_slug); ?>"><?php echo esc_html(translate_user_role($role_data['name'])); ?></option>
                    <?php endforeach; ?>
                </select>
                <select data-sacci-clone-to aria-label="<?php esc_attr_e('Clone to role', 'sacci-island-admin'); ?>">
                    <option value=""><?php esc_html_e('Clone to role', 'sacci-island-admin'); ?></option>
                    <?php foreach ($roles as $role_slug => $role_data) : ?>
                        <option value="<?php echo esc_attr($role_slug); ?>"><?php echo esc_html(translate_user_role($role_data['name'])); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-secondary" data-sacci-clone-role>
                    <?php esc_html_e('Clone role permissions', 'sacci-island-admin'); ?>
                </button>
                <button type="button" class="button button-secondary" data-sacci-check-visible>
                    <?php esc_html_e('Select visible permissions', 'sacci-island-admin'); ?>
                </button>
                <button type="button" class="button button-secondary" data-sacci-clear-visible>
                    <?php esc_html_e('Clear visible permissions', 'sacci-island-admin'); ?>
                </button>
                <a class="button button-secondary" href="<?php echo esc_url($export_url); ?>">
                    <?php esc_html_e('Export settings', 'sacci-island-admin'); ?>
                </a>
            </div>

            <div class="sacci-island-import-box">
                <label>
                    <span><?php esc_html_e('Import settings JSON', 'sacci-island-admin'); ?></span>
                    <input type="file" name="sacci_import_file" accept="application/json">
                </label>
                <p><?php esc_html_e('Use the separate Import button only when replacing role capability settings from a trusted export.', 'sacci-island-admin'); ?></p>
            </div>
        </section>

        <div class="sacci-island-rbac-matrix" data-sacci-rbac-matrix>
            <div class="sacci-island-rbac-row sacci-island-rbac-row--head">
                <span><?php esc_html_e('Capability', 'sacci-island-admin'); ?></span>
                <?php foreach ($roles as $role_slug => $role_data) : ?>
                    <span data-role-column="<?php echo esc_attr($role_slug); ?>">
                        <strong><?php echo esc_html(translate_user_role($role_data['name'])); ?></strong>
                        <button type="button" class="button-link" data-sacci-toggle-role="<?php echo esc_attr($role_slug); ?>">
                            <?php esc_html_e('Toggle column', 'sacci-island-admin'); ?>
                        </button>
                        <?php if (isset(SACCI_Island_RBAC::role_presets()[$role_slug])) : ?>
                            <a
                                class="button-link"
                                href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'action' => 'sacci_island_reset_role_preset',
                                    'role'   => $role_slug,
                                ], admin_url('admin-post.php')), 'sacci_island_reset_role_preset')); ?>"
                            >
                                <?php esc_html_e('Reset preset', 'sacci-island-admin'); ?>
                            </a>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <?php foreach ($modules as $module_key => $module) : ?>
                <div class="sacci-island-rbac-module" data-module="<?php echo esc_attr($module_key); ?>">
                    <button type="button" class="button-link" data-sacci-toggle-module="<?php echo esc_attr($module_key); ?>">
                        <?php printf(esc_html__('Toggle %s module', 'sacci-island-admin'), esc_html($module['label'])); ?>
                    </button>
                    <strong><?php echo esc_html($module['label']); ?></strong>
                </div>

                <?php foreach ($module['caps'] as $capability) : ?>
                    <?php if (!isset($registry[$capability]) && !str_starts_with($capability, 'edit_') && !in_array($capability, ['read', 'upload_files', 'list_users'], true)) : ?>
                        <?php continue; ?>
                    <?php endif; ?>

                    <div
                        class="sacci-island-rbac-row"
                        data-module="<?php echo esc_attr($module_key); ?>"
                        data-permission-label="<?php echo esc_attr(strtolower((string) ($registry[$capability] ?? $capability))); ?>"
                    >
                        <span>
                            <strong><?php echo esc_html((string) ($registry[$capability] ?? $capability)); ?></strong>
                            <code><?php echo esc_html($capability); ?></code>
                        </span>

                        <?php foreach ($roles as $role_slug => $role_data) : ?>
                            <?php
                            $role = get_role((string) $role_slug);
                            $checked = $role ? $role->has_cap($capability) : false;
                            $locked = $role_slug === 'administrator' && in_array($capability, [
                                'sacci_manage_roles',
                                'sacci_manage_admin_design',
                                'sacci_manage_menu_access',
                            ], true);
                            ?>
                            <label data-role-cell="<?php echo esc_attr($role_slug); ?>">
                                <input
                                    type="checkbox"
                                    name="role_caps[<?php echo esc_attr($role_slug); ?>][<?php echo esc_attr($capability); ?>]"
                                    value="1"
                                    <?php checked($checked || $locked); ?>
                                    <?php disabled($locked); ?>
                                >
                                <span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_routes(array $settings): void {
        $destination = is_user_logged_in()
            ? SACCI_Island_Entry_Route::destination_for_current_user()
            : wp_login_url(home_url('/sacci-admin/'));
        ?>
        <section class="sacci-island-panel sacci-island-panel--wide">
            <header class="sacci-island-panel__split">
                <div>
                    <p><?php esc_html_e('Post-login entry', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Friendly SACCI admin routes', 'sacci-island-admin'); ?></h2>
                </div>
                <span><?php esc_html_e('The route uses WordPress rewrites and wp_login_url(), so existing custom login plugins and filters remain in control.', 'sacci-island-admin'); ?></span>
            </header>

            <div class="sacci-island-route-card">
                <strong><?php echo esc_html(home_url('/sacci-admin/')); ?></strong>
                <p><?php esc_html_e('/sacci-admin/ and /parish-office/ both send logged-out visitors through the existing customized login flow, then return them to a capability-aware administration destination.', 'sacci-island-admin'); ?></p>
                <code><?php echo esc_html(home_url('/parish-office/')); ?></code>
                <code><?php echo esc_html($destination); ?></code>
            </div>
        </section>
        <?php
    }

    private static function render_audit_log(): void {
        if (!current_user_can('sacci_view_audit_log') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view the parish audit log.', 'sacci-island-admin'));
        }

        $entries = SACCI_Island_Audit_Log::entries(200);
        ?>
        <section class="sacci-island-panel sacci-island-panel--wide">
            <header class="sacci-island-panel__split">
                <div>
                    <p><?php esc_html_e('Audit Log', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Recent administration activity', 'sacci-island-admin'); ?></h2>
                </div>
                <span><?php esc_html_e('The log stores a privacy-safe request hash instead of raw IP addresses.', 'sacci-island-admin'); ?></span>
            </header>

            <div class="sacci-island-audit-table">
                <div class="sacci-island-audit-row sacci-island-audit-row--head">
                    <span><?php esc_html_e('Time', 'sacci-island-admin'); ?></span>
                    <span><?php esc_html_e('User', 'sacci-island-admin'); ?></span>
                    <span><?php esc_html_e('Action', 'sacci-island-admin'); ?></span>
                    <span><?php esc_html_e('Object', 'sacci-island-admin'); ?></span>
                    <span><?php esc_html_e('Request', 'sacci-island-admin'); ?></span>
                </div>

                <?php if (!$entries) : ?>
                    <div class="sacci-island-audit-empty">
                        <?php esc_html_e('No audit entries have been recorded yet.', 'sacci-island-admin'); ?>
                    </div>
                <?php else : ?>
                    <?php foreach ($entries as $entry) : ?>
                        <?php $user = !empty($entry['user_id']) ? get_userdata((int) $entry['user_id']) : null; ?>
                        <div class="sacci-island-audit-row">
                            <span><?php echo esc_html(wp_date('M j, Y g:i a', (int) ($entry['timestamp'] ?? time()))); ?></span>
                            <span><?php echo esc_html($user ? $user->display_name : __('System', 'sacci-island-admin')); ?></span>
                            <span><code><?php echo esc_html((string) ($entry['action'] ?? '')); ?></code></span>
                            <span><?php echo esc_html((string) ($entry['object_type'] ?? '')); ?> #<?php echo esc_html((string) ($entry['object_id'] ?? 0)); ?></span>
                            <span><code><?php echo esc_html(substr((string) ($entry['request_hash'] ?? ''), 0, 16)); ?></code></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    private static function render_dashboard(array $settings): void {
        ?>
        <div class="sacci-island-layout">
            <section class="sacci-island-panel">
                <header>
                    <p><?php esc_html_e('Parish Overview Experience', 'sacci-island-admin'); ?></p>
                    <h2><?php esc_html_e('Create a calmer parish workspace', 'sacci-island-admin'); ?></h2>
                </header>

                <div class="sacci-island-toggle-grid sacci-island-toggle-grid--vertical">
                    <?php self::toggle('dashboard_welcome', __('Show parish overview island', 'sacci-island-admin'), $settings); ?>
                    <?php self::toggle('focus_dashboard', __('Remove default WordPress news and quick-draft widgets', 'sacci-island-admin'), $settings); ?>
                </div>

                <div class="sacci-island-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <p>
                        <?php esc_html_e('The dashboard automatically surfaces Events, Announcements and Parish Bulletins when those plugins are active and the current staff member has permission.', 'sacci-island-admin'); ?>
                    </p>
                </div>
            </section>

            <aside class="sacci-island-dashboard-preview">
                <header>
                    <div>
                        <small><?php esc_html_e('Welcome back', 'sacci-island-admin'); ?></small>
                        <strong><?php echo esc_html((string) $settings['brand_name']); ?></strong>
                    </div>
                    <span>✦</span>
                </header>
                <section><b></b><b></b><b></b></section>
                <article>
                    <i></i><i></i><i></i>
                </article>
            </aside>
        </div>
        <?php
    }

    private static function toggle(string $key, string $label, array $settings): void {
        ?>
        <label class="sacci-island-toggle">
            <input type="hidden" name="settings[<?php echo esc_attr($key); ?>]" value="0">
            <input type="checkbox" name="settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>>
            <span aria-hidden="true"></span>
            <strong><?php echo esc_html($label); ?></strong>
        </label>
        <?php
    }

    public static function save(): void {
        if (!SACCI_Island_RBAC::can_manage_design()) {
            wp_die(esc_html__('You do not have permission to save these settings.', 'sacci-island-admin'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $current = self::get();
        $raw = isset($_POST['settings']) && is_array($_POST['settings'])
            ? wp_unslash($_POST['settings'])
            : [];

        $settings = self::sanitize($raw, $current);
        update_option(SACCI_ISLAND_OPTION, $settings, false);

        if (
            SACCI_Island_RBAC::can_manage_roles() &&
            isset($_POST['role_caps']) &&
            is_array($_POST['role_caps'])
        ) {
            SACCI_Island_RBAC::save_role_capabilities(wp_unslash($_POST['role_caps']));
        }

        if (
            SACCI_Island_RBAC::can_manage_roles() &&
            !empty($_FILES['sacci_import_file']['tmp_name'])
        ) {
            $import = file_get_contents((string) $_FILES['sacci_import_file']['tmp_name']);
            $decoded = json_decode((string) $import, true);

            if (isset($decoded['roles']) && is_array($decoded['roles'])) {
                SACCI_Island_RBAC::save_role_capabilities($decoded['roles']);
            }
        }

        if (
            ($current['menu_rules'] ?? []) !== ($settings['menu_rules'] ?? []) ||
            ($current['submenu_rules'] ?? []) !== ($settings['submenu_rules'] ?? [])
        ) {
            SACCI_Island_Audit_Log::record('menu_access_changed', 'settings', 0, []);
        } else {
            SACCI_Island_Audit_Log::record('settings_changed', 'settings', 0, []);
        }

        $tab = isset($_POST['return_tab']) ? sanitize_key(wp_unslash($_POST['return_tab'])) : 'appearance';

        wp_safe_redirect(add_query_arg([
            'page'    => 'sacci-island-admin',
            'tab'     => $tab,
            'updated' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    public static function reset(): void {
        if (!SACCI_Island_RBAC::can_manage_design()) {
            wp_die(esc_html__('You do not have permission to reset these settings.', 'sacci-island-admin'));
        }

        check_admin_referer('sacci_island_reset');
        update_option(SACCI_ISLAND_OPTION, self::defaults(), false);
        SACCI_Island_Audit_Log::record('settings_changed', 'settings', 0, ['reset' => true]);

        wp_safe_redirect(add_query_arg([
            'page'  => 'sacci-island-admin',
            'reset' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    private static function sanitize(array $raw, array $current): array {
        $clean = self::defaults();

        $clean['brand_name'] = isset($raw['brand_name'])
            ? sanitize_text_field((string) $raw['brand_name'])
            : $current['brand_name'];

        $clean['brand_tagline'] = isset($raw['brand_tagline'])
            ? sanitize_text_field((string) $raw['brand_tagline'])
            : $current['brand_tagline'];

        $clean['logo_id'] = isset($raw['logo_id']) ? absint($raw['logo_id']) : absint($current['logo_id']);

        foreach (['primary', 'primary_deep', 'accent', 'surface', 'card', 'text'] as $colour) {
            $value = isset($raw[$colour]) ? sanitize_hex_color((string) $raw[$colour]) : '';
            $clean[$colour] = $value ?: (string) $current[$colour];
        }

        $clean['radius'] = isset($raw['radius'])
            ? max(12, min(28, absint($raw['radius'])))
            : absint($current['radius']);

        $clean['rail_width'] = 0;

        $clean['sidebar_width'] = isset($raw['sidebar_width'])
            ? max(276, min(340, absint($raw['sidebar_width'])))
            : absint($current['sidebar_width']);

        $clean['header_height'] = isset($raw['header_height'])
            ? max(64, min(80, absint($raw['header_height'])))
            : absint($current['header_height'] ?? 72);

        $clean['appearance_mode'] = 'light';

        foreach ([
            'compact',
            'dashboard_welcome',
            'focus_dashboard',
            'strict_guard',
            'show_footer_brand',
        ] as $boolean) {
            $clean[$boolean] = !empty($raw[$boolean]) ? 1 : 0;
        }

        $clean['menu_order'] = [];
        if (isset($raw['menu_order'])) {
            $order = is_array($raw['menu_order'])
                ? $raw['menu_order']
                : explode(',', (string) $raw['menu_order']);

            foreach ($order as $slug) {
                $slug = self::sanitize_menu_slug((string) $slug);
                if ($slug !== '' && !in_array($slug, $clean['menu_order'], true)) {
                    $clean['menu_order'][] = $slug;
                }
            }
        } elseif (!empty($current['menu_order']) && is_array($current['menu_order'])) {
            $clean['menu_order'] = array_values($current['menu_order']);
        }

        $roles = array_keys(wp_roles()->roles);
        $manifest = SACCI_Island_Menu::manifest();
        $valid_top = array_column($manifest['top'] ?? [], 'slug');
        $valid_sub = [];

        foreach (($manifest['sub'] ?? []) as $parent => $items) {
            foreach ($items as $item) {
                $valid_sub[] = $parent . '|' . $item['slug'];
            }
        }

        $clean['menu_rules'] = self::sanitize_rules(
            isset($raw['menu_rules']) && is_array($raw['menu_rules']) ? $raw['menu_rules'] : [],
            isset($current['menu_rules']) && is_array($current['menu_rules']) ? $current['menu_rules'] : [],
            $valid_top,
            $roles,
            true
        );

        $clean['submenu_rules'] = self::sanitize_rules(
            isset($raw['submenu_rules']) && is_array($raw['submenu_rules']) ? $raw['submenu_rules'] : [],
            isset($current['submenu_rules']) && is_array($current['submenu_rules']) ? $current['submenu_rules'] : [],
            $valid_sub,
            $roles,
            false
        );

        return $clean;
    }

    private static function sanitize_rules(
        array $raw_rules,
        array $current_rules,
        array $valid_keys,
        array $valid_roles,
        bool $top_level
    ): array {
        $clean = $current_rules;

        foreach ($raw_rules as $raw_key => $rule) {
            $key = (string) $raw_key;

            if (!in_array($key, $valid_keys, true) || !is_array($rule)) {
                continue;
            }

            $current_rule = isset($clean[$key]) && is_array($clean[$key]) ? $clean[$key] : [];
            $new_rule = $current_rule;

            if ($top_level) {
                $new_rule['label'] = isset($rule['label'])
                    ? sanitize_text_field((string) $rule['label'])
                    : (string) ($current_rule['label'] ?? '');

                $new_rule['icon'] = isset($rule['icon'])
                    ? sanitize_html_class((string) $rule['icon'])
                    : (string) ($current_rule['icon'] ?? '');

                $new_rule['hidden'] = !empty($rule['hidden']) ? 1 : 0;
            }

            $new_rule['capability'] = isset($rule['capability'])
                ? sanitize_key((string) $rule['capability'])
                : (string) ($current_rule['capability'] ?? '');

            if (array_key_exists('roles', $rule)) {
                $requested = is_array($rule['roles']) ? $rule['roles'] : [];
                $allowed_roles = [];

                foreach ($requested as $role) {
                    $role = sanitize_key((string) $role);
                    if (in_array($role, $valid_roles, true)) {
                        $allowed_roles[] = $role;
                    }
                }

                if (!in_array('administrator', $allowed_roles, true)) {
                    $allowed_roles[] = 'administrator';
                }

                $new_rule['roles'] = array_values(array_unique($allowed_roles));
            } elseif (isset($current_rule['roles'])) {
                $new_rule['roles'] = $current_rule['roles'];
            }

            $clean[$key] = $new_rule;
        }

        return $clean;
    }

    private static function sanitize_menu_slug(string $slug): string {
        $slug = trim($slug);
        return preg_replace('/[^A-Za-z0-9_\-\.\/\?\=\&\|:%]/', '', $slug) ?: '';
    }

    public static function logo_url(?array $settings = null): string {
        $settings = $settings ?: self::get();
        $logo_id = absint($settings['logo_id'] ?? 0);

        if ($logo_id) {
            $url = wp_get_attachment_image_url($logo_id, 'medium');
            if ($url) {
                return (string) $url;
            }
        }

        return SACCI_ISLAND_URL . 'assets/images/parish-mark.png';
    }

    private static function normalise_dashicon(string $icon): string {
        $icon = sanitize_html_class($icon);
        if ($icon === '') {
            return 'dashicons-admin-generic';
        }

        return str_starts_with($icon, 'dashicons-') ? $icon : 'dashicons-' . $icon;
    }
}
