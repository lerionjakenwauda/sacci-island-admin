<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/fixture/');

function home_url(string $path = ''): string {
    return 'https://example.test' . $path;
}

function site_url(string $path = ''): string {
    return 'https://example.test' . $path;
}

function plugins_url(): string {
    return 'https://example.test/wp-content/plugins';
}

function includes_url(): string {
    return 'https://example.test/wp-includes/';
}

function content_url(): string {
    return 'https://example.test/wp-content';
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

function trailingslashit(string $value): string {
    return untrailingslashit($value) . '/';
}

function wp_normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

require dirname(__DIR__) . '/includes/class-sacci-island-white-label.php';

$input = <<<'HTML'
<link rel="stylesheet" href="https://example.test/wp-admin/load-styles.php?c=1&load=common">
<script src="https://example.test/wp-admin/load-scripts.php?c=1&load=jquery"></script>
<script>window.ajaxurl = "https://example.test/wp-admin/admin-ajax.php";</script>
<form action="https://example.test/wp-admin/admin-post.php"></form>
<form action="https://example.test/wp-admin/async-upload.php"></form>
<a href="https://example.test/wp-admin/plugins.php">Plugins</a>
<a href="/wp-admin/load-styles.php?c=0">Relative core stylesheet</a>
<link rel="stylesheet" href="https://example.test/wp-content/plugins/demo/admin.css">
<script src="https://example.test/wp-includes/js/demo.js"></script>
<img src="https://example.test/wp-content/uploads/demo.png" alt="">
<a href="https://example.test/wp-login.php">Log in</a>
HTML;

$output = SACCI_Island_White_Label::rewrite_output($input);

$expectations = [
    'https://example.test/wp-admin/load-styles.php?c=1&load=common',
    'https://example.test/wp-admin/load-scripts.php?c=1&load=jquery',
    'https://example.test/wp-admin/admin-ajax.php',
    'https://example.test/wp-admin/admin-post.php',
    'https://example.test/wp-admin/async-upload.php',
    '/wp-admin/load-styles.php?c=0',
    'https://example.test/sacci-admin/plugins.php',
    'https://example.test/sacci-plugins/demo/admin.css',
    'https://example.test/sacci-core/js/demo.js',
    'https://example.test/sacci-assets/uploads/demo.png',
    'https://example.test/sacci-login/',
];

foreach ($expectations as $expected) {
    if (!str_contains($output, $expected)) {
        fwrite(STDERR, "Missing rewritten output: {$expected}\n");
        exit(1);
    }
}

foreach ([
    'https://example.test/sacci-admin/load-styles.php',
    'https://example.test/sacci-admin/load-scripts.php',
    'https://example.test/sacci-plugins//',
    'https://example.test/sacci-assets//',
] as $forbidden) {
    if (str_contains($output, $forbidden)) {
        fwrite(STDERR, "Unsafe rewrite produced: {$forbidden}\n");
        exit(1);
    }
}

$allowed_file = new ReflectionMethod(
    SACCI_Island_White_Label::class,
    'is_allowed_file'
);
$static_asset = new ReflectionMethod(
    SACCI_Island_White_Label::class,
    'is_static_asset'
);
$allowed_file->setAccessible(true);
$static_asset->setAccessible(true);

if (!$allowed_file->invoke(null, '/srv/www/wp-content/demo/admin.css', ['/srv/www/wp-content'])) {
    fwrite(STDERR, "A file inside an approved root was rejected.\n");
    exit(1);
}

if ($allowed_file->invoke(null, '/srv/www/wp-content-private/secret.css', ['/srv/www/wp-content'])) {
    fwrite(STDERR, "A sibling path escaped the approved root boundary.\n");
    exit(1);
}

if (!$static_asset->invoke(null, '/srv/www/wp-content/demo/admin.css')) {
    fwrite(STDERR, "A CSS asset was rejected.\n");
    exit(1);
}

if ($static_asset->invoke(null, '/srv/www/wp-content/demo/secret.php')) {
    fwrite(STDERR, "An executable PHP file was accepted as a static asset.\n");
    exit(1);
}

fwrite(STDOUT, "White-label rewrite regression checks passed.\n");
