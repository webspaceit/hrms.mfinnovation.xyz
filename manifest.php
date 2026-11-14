<?php
// ============================================================
// Web App Manifest - makes the site installable ("Add to Home
// Screen" / PWA install) on Android, iOS and desktop Chrome.
//
// Served from PHP rather than a static file because:
//   * the JSON needs BASE_URL, so it keeps working when the app
//     lives in a sub-folder (/rms/), on localhost or at a domain root;
//   * PHP sends the application/manifest+json content type that
//     Chrome expects, without an AddType line in .htaccess;
//   * .htaccess denies *.json here (secrets), .webmanifest would
//     need extra config on shared hosts.
//
// No session, no DB - this endpoint is public on purpose.
// ============================================================

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = BASE_URL; // always ends with "/"

$icon = function (string $file) use ($base): string {
    return $base . 'assets/icons/' . $file;
};

$manifest = [
    // "id" pins the installed app identity to the app root. Without it a
    // changed start_url (e.g. adding ?utm_source) would create a second
    // installable app on Android.
    'id'             => $base,
    'name'           => defined('SITE_NAME') ? SITE_NAME : 'Rent Management System',
    'short_name'     => 'RMS',
    'description'    => 'Rent, tenant, lease, invoice and payment management for landlords and tenants.',
    // Name/description below are English, so the manifest language is en even
    // though the UI itself defaults to Bengali (DEFAULT_LANG).
    'lang'           => 'en',
    'dir'            => 'ltr',
    'start_url'      => $base,
    'scope'          => $base,
    'display'        => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation'    => 'any',
    'background_color' => '#003820',
    'theme_color'    => '#007c47',
    'categories'     => ['business', 'finance', 'productivity'],
    'prefer_related_applications' => false,
    'icons' => [
        [
            'src'     => $icon('icon-192.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $icon('icon-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        // Maskable variants: Android crops these to the launcher's shape,
        // so the glyph is inset into the 80% safe zone.
        [
            'src'     => $icon('icon-maskable-192.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
        [
            'src'     => $icon('icon-maskable-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
    // Long-press shortcuts on the installed icon.
    'shortcuts' => [
        [
            'name'      => 'Collect rent',
            'short_name'=> 'Payments',
            'url'       => $base . 'payments',
            'icons'     => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
        ],
        [
            'name'      => 'Invoices',
            'short_name'=> 'Invoices',
            'url'       => $base . 'invoices',
            'icons'     => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
        ],
        [
            'name'      => 'Tenant portal',
            'short_name'=> 'My rent',
            'url'       => $base . 'tenant-login',
            'icons'     => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
        ],
    ],
];

echo json_encode(
    $manifest,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
