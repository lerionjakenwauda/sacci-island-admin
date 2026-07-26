<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Dashboard {
    public static function hooks(): void {
        add_action('admin_menu', [__CLASS__, 'replace_dashboard_menu'], 99999);
        add_action('wp_dashboard_setup', [__CLASS__, 'setup'], 99999);
        add_action('load-index.php', [__CLASS__, 'prepare_dashboard']);
        add_filter('screen_options_show_screen', [__CLASS__, 'screen_options'], 9999, 2);
    }

    public static function replace_dashboard_menu(): void {
        global $menu;

        foreach ($menu as &$item) {
            if (isset($item[2]) && $item[2] === 'index.php') {
                $item[0] = __('Parish Overview', 'sacci-island-admin');
                break;
            }
        }
        unset($item);

        remove_submenu_page('index.php', 'index.php');
        remove_submenu_page('index.php', 'update-core.php');
    }

    public static function prepare_dashboard(): void {
        remove_action('welcome_panel', 'wp_welcome_panel');
        remove_all_actions('welcome_panel');
    }

    public static function screen_options(
        bool $show,
        WP_Screen $screen
    ): bool {
        if ($screen->id === 'dashboard') {
            return false;
        }

        return $show;
    }

    public static function setup(): void {
        global $wp_meta_boxes;

        /*
         * The dashboard is intentionally replaced, not decorated. This removes
         * WordPress News, Site Health, Activity, Quick Draft, At a Glance and
         * vendor dashboard widgets in every context and priority.
         */
        $wp_meta_boxes['dashboard'] = [];

        wp_add_dashboard_widget(
            'sacci_parish_dashboard',
            '',
            [__CLASS__, 'render'],
            null,
            null,
            'normal',
            'high'
        );
    }

    public static function render(): void {
        $stats = self::statistics();
        $actions = self::quick_actions();
        $activity = self::recent_activity();
        ?>
        <div class="sacci-admin-dashboard">
            <header class="sacci-dashboard-heading">
                <div>
                    <h1><?php esc_html_e('Parish Overview', 'sacci-island-admin'); ?></h1>
                    <p><?php esc_html_e('Welcome back. Here is what is happening with the parish website.', 'sacci-island-admin'); ?></p>
                </div>

                <div class="sacci-dashboard-date">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <time datetime="<?php echo esc_attr(wp_date('Y-m-d')); ?>">
                        <?php echo esc_html(wp_date('l, F j, Y')); ?>
                    </time>
                </div>
            </header>

            <section class="sacci-dashboard-stats" aria-label="<?php esc_attr_e('Parish website overview', 'sacci-island-admin'); ?>">
                <?php foreach ($stats as $stat) : ?>
                    <a
                        class="sacci-stat-card is-<?php echo esc_attr($stat['tone']); ?>"
                        href="<?php echo esc_url($stat['url']); ?>"
                    >
                        <span class="sacci-stat-icon">
                            <span class="dashicons <?php echo esc_attr($stat['icon']); ?>" aria-hidden="true"></span>
                        </span>

                        <div>
                            <small><?php echo esc_html($stat['label']); ?></small>
                            <strong><?php echo esc_html((string) $stat['count']); ?></strong>
                            <span><?php echo esc_html($stat['description']); ?></span>
                        </div>

                        <i aria-hidden="true">↗</i>
                    </a>
                <?php endforeach; ?>
            </section>

            <div class="sacci-dashboard-columns">
                <div class="sacci-dashboard-left">
                    <section class="sacci-dashboard-panel sacci-quick-actions">
                        <header>
                            <h2><?php esc_html_e('Quick Actions', 'sacci-island-admin'); ?></h2>
                        </header>

                        <div>
                            <?php foreach ($actions as $action) : ?>
                                <a href="<?php echo esc_url($action['url']); ?>">
                                    <span class="sacci-action-icon is-<?php echo esc_attr($action['tone']); ?>">
                                        <span class="dashicons <?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                                    </span>

                                    <div>
                                        <strong><?php echo esc_html($action['label']); ?></strong>
                                        <small><?php echo esc_html($action['description']); ?></small>
                                    </div>

                                    <i aria-hidden="true">›</i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="sacci-important-notice">
                        <span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('Important Notice', 'sacci-island-admin'); ?></strong>
                            <p><?php esc_html_e('Please verify all payment details with the Parish Office before making any transfers or payments.', 'sacci-island-admin'); ?></p>
                        </div>
                    </section>
                </div>

                <section class="sacci-dashboard-panel sacci-recent-activity">
                    <header>
                        <h2><?php esc_html_e('Recent Activity', 'sacci-island-admin'); ?></h2>
                    </header>

                    <?php if (!$activity) : ?>
                        <div class="sacci-empty-activity">
                            <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                            <p><?php esc_html_e('Recent parish publishing activity will appear here.', 'sacci-island-admin'); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="sacci-activity-list">
                            <?php foreach ($activity as $item) : ?>
                                <a href="<?php echo esc_url($item['url']); ?>">
                                    <span class="sacci-activity-icon is-<?php echo esc_attr($item['tone']); ?>">
                                        <span class="dashicons <?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                                    </span>

                                    <div>
                                        <strong><?php echo esc_html($item['title']); ?></strong>
                                        <small><?php echo esc_html($item['description']); ?></small>
                                    </div>

                                    <time datetime="<?php echo esc_attr($item['datetime']); ?>">
                                        <?php echo esc_html($item['relative']); ?>
                                    </time>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    private static function statistics(): array {
        return [
            [
                'label'       => __('Upcoming Events', 'sacci-island-admin'),
                'count'       => self::upcoming_event_count(),
                'description' => __('Scheduled parish events', 'sacci-island-admin'),
                'url'         => post_type_exists('sacci_event')
                    ? admin_url('edit.php?post_type=sacci_event')
                    : admin_url(),
                'icon'        => 'dashicons-calendar-alt',
                'tone'        => 'green',
            ],
            [
                'label'       => __('Announcements', 'sacci-island-admin'),
                'count'       => self::published_count('sacci_announcement'),
                'description' => __('Published notices', 'sacci-island-admin'),
                'url'         => post_type_exists('sacci_announcement')
                    ? admin_url('edit.php?post_type=sacci_announcement')
                    : admin_url(),
                'icon'        => 'dashicons-megaphone',
                'tone'        => 'amber',
            ],
            [
                'label'       => __('Bulletins', 'sacci-island-admin'),
                'count'       => self::published_count('sacci_bulletin'),
                'description' => __('Published editions', 'sacci-island-admin'),
                'url'         => post_type_exists('sacci_bulletin')
                    ? admin_url('edit.php?post_type=sacci_bulletin')
                    : admin_url(),
                'icon'        => 'dashicons-book-alt',
                'tone'        => 'blue',
            ],
            [
                'label'       => __('Pages', 'sacci-island-admin'),
                'count'       => self::published_count('page'),
                'description' => __('Published website pages', 'sacci-island-admin'),
                'url'         => admin_url('edit.php?post_type=page'),
                'icon'        => 'dashicons-admin-page',
                'tone'        => 'violet',
            ],
        ];
    }

    private static function quick_actions(): array {
        $actions = [];

        if (
            post_type_exists('sacci_event') &&
            current_user_can('edit_sacci_events')
        ) {
            $actions[] = [
                'label'       => __('Add New Event', 'sacci-island-admin'),
                'description' => __('Create a new parish event', 'sacci-island-admin'),
                'url'         => admin_url('post-new.php?post_type=sacci_event'),
                'icon'        => 'dashicons-plus-alt2',
                'tone'        => 'green',
            ];
        }

        if (
            post_type_exists('sacci_announcement') &&
            current_user_can('edit_sacci_announcements')
        ) {
            $actions[] = [
                'label'       => __('Add Announcement', 'sacci-island-admin'),
                'description' => __('Share important parish news', 'sacci-island-admin'),
                'url'         => admin_url('post-new.php?post_type=sacci_announcement'),
                'icon'        => 'dashicons-megaphone',
                'tone'        => 'amber',
            ];
        }

        if (
            post_type_exists('sacci_bulletin') &&
            current_user_can('edit_sacci_bulletins')
        ) {
            $actions[] = [
                'label'       => __('Add Bulletin', 'sacci-island-admin'),
                'description' => __('Publish a new bulletin edition', 'sacci-island-admin'),
                'url'         => admin_url('post-new.php?post_type=sacci_bulletin'),
                'icon'        => 'dashicons-book-alt',
                'tone'        => 'blue',
            ];
        }

        if (current_user_can('edit_pages')) {
            $actions[] = [
                'label'       => __('Add New Page', 'sacci-island-admin'),
                'description' => __('Create a new website page', 'sacci-island-admin'),
                'url'         => admin_url('post-new.php?post_type=page'),
                'icon'        => 'dashicons-admin-page',
                'tone'        => 'violet',
            ];
        }

        return $actions;
    }

    private static function recent_activity(): array {
        $types = ['page'];

        foreach ([
            'sacci_event',
            'sacci_announcement',
            'sacci_bulletin',
        ] as $type) {
            if (post_type_exists($type)) {
                $types[] = $type;
            }
        }

        if (current_user_can('upload_files')) {
            $types[] = 'attachment';
        }

        $query = new WP_Query([
            'post_type'              => $types,
            'post_status'            => ['publish', 'inherit'],
            'posts_per_page'         => 6,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $items = [];

        foreach ($query->posts as $post) {
            $mapping = self::activity_mapping($post->post_type);
            $modified = get_post_modified_time('U', true, $post);
            $edit_url = get_edit_post_link($post->ID, 'raw');

            if (!$edit_url && $post->post_type === 'attachment') {
                $edit_url = admin_url('upload.php');
            }

            $items[] = [
                'title'       => get_the_title($post) ?: __('Untitled item', 'sacci-island-admin'),
                'description' => $mapping['description'],
                'url'         => $edit_url ?: admin_url(),
                'icon'        => $mapping['icon'],
                'tone'        => $mapping['tone'],
                'datetime'    => wp_date('c', $modified),
                'relative'    => sprintf(
                    __('%s ago', 'sacci-island-admin'),
                    human_time_diff($modified, current_time('timestamp', true))
                ),
            ];
        }

        wp_reset_postdata();

        return $items;
    }

    private static function activity_mapping(string $post_type): array {
        return match ($post_type) {
            'sacci_event' => [
                'description' => __('Event published or updated', 'sacci-island-admin'),
                'icon'        => 'dashicons-calendar-alt',
                'tone'        => 'red',
            ],
            'sacci_announcement' => [
                'description' => __('Announcement published or updated', 'sacci-island-admin'),
                'icon'        => 'dashicons-megaphone',
                'tone'        => 'amber',
            ],
            'sacci_bulletin' => [
                'description' => __('Bulletin published or updated', 'sacci-island-admin'),
                'icon'        => 'dashicons-book-alt',
                'tone'        => 'blue',
            ],
            'attachment' => [
                'description' => __('Media uploaded', 'sacci-island-admin'),
                'icon'        => 'dashicons-format-image',
                'tone'        => 'green',
            ],
            default => [
                'description' => __('Page published or updated', 'sacci-island-admin'),
                'icon'        => 'dashicons-admin-page',
                'tone'        => 'violet',
            ],
        };
    }

    private static function upcoming_event_count(): int {
        if (!post_type_exists('sacci_event')) {
            return 0;
        }

        $query = new WP_Query([
            'post_type'      => 'sacci_event',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_sacci_event_end_ts',
                    'value'   => current_time('timestamp'),
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ],
            ],
            'no_found_rows' => false,
        ]);

        return (int) $query->found_posts;
    }

    private static function published_count(string $post_type): int {
        if (!post_type_exists($post_type)) {
            return 0;
        }

        $counts = wp_count_posts($post_type);
        return isset($counts->publish) ? (int) $counts->publish : 0;
    }
}
