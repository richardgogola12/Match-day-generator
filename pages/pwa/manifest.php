<?php
/** Web App Manifest – generuje sa, aby cesty sedeli s BASE_PATH */
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    'name'             => 'Match Day Generator',
    'short_name'       => 'Match Day',
    'description'      => 'Generátor zápasových grafík – Match day, výsledok a strelci.',
    'lang'             => 'sk',
    'id'               => BASE_PATH . '/',
    'start_url'        => BASE_PATH . '/?source=pwa',
    'scope'            => BASE_PATH . '/',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#0b2a1a',
    'theme_color'      => '#0b2a1a',
    'categories'       => ['sports', 'productivity', 'photo'],
    'icons' => [
        ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => asset('icons/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
    ],
    'shortcuts' => [
        ['name' => 'Hľadať zápasy', 'url' => BASE_PATH . '/', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
        ['name' => 'Galéria', 'url' => BASE_PATH . '/galeria', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
        ['name' => 'Šablóny', 'url' => BASE_PATH . '/nastavenia', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
        ['name' => 'Návod', 'url' => BASE_PATH . '/navod', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
