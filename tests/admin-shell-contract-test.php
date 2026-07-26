<?php

$root = dirname(__DIR__);
$script = file_get_contents($root . '/assets/js/admin-islands.js');
$style = file_get_contents($root . '/assets/css/admin-islands.css');
$shell = file_get_contents($root . '/includes/class-sacci-island-shell.php');
$settings = file_get_contents($root . '/includes/class-sacci-island-settings.php');

if (
    $script === false ||
    $style === false ||
    $shell === false ||
    $settings === false
) {
    fwrite(STDERR, "Could not read one or more admin shell source files.\n");
    exit(1);
}

$checks = [
    [
        str_contains(
            $script,
            'menu.addEventListener("click", (event) => {'
        ) &&
        str_contains($script, 'event.stopImmediatePropagation();') &&
        str_contains($script, 'toggleItem(item);'),
        'Parent WordPress menu links must be capture-phase accordion controls.',
    ],
    [
        str_contains($script, 'event.preventDefault();') &&
        str_contains($script, 'link.setAttribute("aria-expanded", "false");'),
        'Accordion controls must prevent navigation and expose expanded state.',
    ],
    [
        str_contains($script, 'initialiseAdminBarMenus();') &&
        str_contains($script, 'sacci-adminbar-menu-open'),
        'Header dropdowns must use the click-controlled menu state.',
    ],
    [
        preg_match(
            '/#adminmenuwrap\s*\{[^}]*\btop:\s*var\(--sacci-admin-header\);[^}]*padding-top:\s*0;/s',
            $style
        ) === 1,
        'The sidebar must start directly below the full-width WordPress top bar.',
    ],
    [
        str_contains(
            $style,
            '#wpadminbar .menupop.sacci-adminbar-menu-open > .ab-sub-wrapper'
        ),
        'Header dropdown visibility must be tied to the controlled open class.',
    ],
    [
        preg_match(
            '/#wpbody\s*\{[^}]*border-radius:\s*28px;[^}]*background:\s*var\(--sacci-ivory\)/s',
            $style
        ) === 1,
        'The WordPress workspace must render as the primary rounded content surface.',
    ],
    [
        str_contains($shell, 'sacci-adminbar-brand-copy') &&
        str_contains($shell, '$mark_url') &&
        str_contains($shell, "admin_url('index.php')"),
        'The header parish identity must link to Parish Overview.',
    ],
    [
        str_contains($settings, "'sidebar_width'     => 252") &&
        str_contains($settings, "'header_height'     => 76"),
        'The connected shell must keep its approved compact dimensions.',
    ],
    [
        str_contains($style, 'background: var(--sacci-shell) !important;') &&
        str_contains($style, 'background: var(--sacci-canvas) !important;'),
        'Header/sidebar chrome and content canvas must use distinct shell layers.',
    ],
];

$failed = false;

foreach ($checks as [$passed, $message]) {
    if ($passed) {
        continue;
    }

    $failed = true;
    fwrite(STDERR, $message . "\n");
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "Admin shell contract checks passed.\n");
