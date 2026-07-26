<?php

if (!defined('ABSPATH')) {
    exit;
}

final class SACCI_Island_Audit_Log {
    private const LIMIT = 500;

    public static function hooks(): void {
        add_action('transition_post_status', [__CLASS__, 'record_publish'], 10, 3);
        add_action('before_delete_post', [__CLASS__, 'record_delete']);
    }

    public static function record(
        string $action,
        string $object_type = '',
        $object_id = 0,
        array $metadata = []
    ): void {
        $log = get_option(SACCI_ISLAND_AUDIT_OPTION, []);
        $log = is_array($log) ? $log : [];

        array_unshift($log, [
            'user_id'       => get_current_user_id(),
            'action'        => sanitize_key($action),
            'object_type'   => sanitize_key($object_type),
            'object_id'     => is_numeric($object_id) ? (int) $object_id : sanitize_text_field((string) $object_id),
            'timestamp'     => time(),
            'request_hash'  => self::request_hash(),
            'metadata'      => self::sanitize_metadata($metadata),
        ]);

        if (count($log) > self::LIMIT) {
            $log = array_slice($log, 0, self::LIMIT);
        }

        update_option(SACCI_ISLAND_AUDIT_OPTION, $log, false);
    }

    public static function entries(int $limit = 100): array {
        $log = get_option(SACCI_ISLAND_AUDIT_OPTION, []);
        $log = is_array($log) ? $log : [];
        return array_slice($log, 0, max(1, min(500, $limit)));
    }

    public static function record_publish(string $new_status, string $old_status, WP_Post $post): void {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        if (!self::is_parish_post_type($post->post_type)) {
            return;
        }

        self::record('content_published', $post->post_type, $post->ID, [
            'title' => get_the_title($post),
        ]);
    }

    public static function record_delete(int $post_id): void {
        $post = get_post($post_id);

        if (!$post || !self::is_parish_post_type($post->post_type)) {
            return;
        }

        self::record('content_deleted', $post->post_type, $post_id, [
            'title' => get_the_title($post),
        ]);
    }

    private static function is_parish_post_type(string $post_type): bool {
        return in_array($post_type, [
            'sacci_event',
            'sacci_announcement',
            'sacci_bulletin',
            'page',
            'attachment',
        ], true);
    }

    private static function request_hash(): string {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        $agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        return wp_hash($ip . '|' . $agent);
    }

    private static function sanitize_metadata(array $metadata): array {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $key = sanitize_key((string) $key);

            if ($key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = sanitize_text_field((string) $value);
                continue;
            }

            $clean[$key] = wp_json_encode($value);
        }

        return $clean;
    }
}
