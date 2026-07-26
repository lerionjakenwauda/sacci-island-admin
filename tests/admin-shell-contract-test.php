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
            'link.addEventListener("click", (event) => {'
        ) &&
        str_contains($script, 'toggleItem(item);'),
        'Parent WordPress menu links must be accordion controls.',
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
            '/#adminmenuwrap\s*\{[^}]*\btop:\s*0;[^}]*padding-top:\s*var\(--sacci-admin-header\);/s',
            $style
        ) === 1,
        'The sidebar must begin at the top of the viewport and continue behind the header.',
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
            '/#wpbody\s*\{[^}]*border-radius:\s*26px;[^}]*background:\s*var\(--sacci-ivory\)/s',
            $style
        ) === 1,
        'The WordPress workspace must render as the primary rounded content surface.',
    ],
    [
        str_contains($shell, "assets/images/parish-logo.png") &&
        str_contains($shell, "admin_url('index.php')"),
        'The header wordmark must link to Parish Overview.',
    ],
    [
        str_contains($settings, "'sidebar_width'     => 264") &&
        str_contains($settings, "'header_height'     => 72"),
        'The connected shell must keep its approved compact dimensions.',
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
