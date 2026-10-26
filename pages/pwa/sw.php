<?php
/**
 * Service worker – generuje sa z PHP, aby:
 *  - mal správny rozsah (BASE_PATH) a zoznam súborov s verziou,
 *  - sa verzia cache zmenila automaticky pri každej zmene súborov v assets/.
 */
$precache = [
    url('offline'),
    asset('css/style.css'),
    asset('js/storage.js'),
    asset('js/app.js'),
    asset('js/renderer.js'),
    asset('js/editor.js'),
    asset('js/generator.js'),
    asset('js/gallery.js'),
    asset('js/guide.js'),
    asset('fonts/biome-w04-regular.otf'),
    asset('img/match-day-bg.png'),
    asset('img/match-result-bg.png'),
    asset('img/match-result-goals-bg.png'),
    asset('icons/icon.svg'),
    asset('icons/icon-192.png'),
    url('includes/vendor/bootstrap/bootstrap.min.css'),
    url('includes/vendor/bootstrap/bootstrap.bundle.min.js'),
    url('includes/vendor/jquery/jquery.min.js'),
    url('includes/vendor/select2/select2.min.js'),
    url('includes/vendor/select2/select2.min.css'),
];
$version = substr(sha1(implode('|', $precache)), 0, 10);

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('Service-Worker-Allowed: ' . BASE_PATH . '/');
?>
/* Match Day – service worker (verzia <?= $version ?>) */
const VERSION = '<?= $version ?>';
const STATIC_CACHE = 'md-static-' + VERSION;
const RUNTIME_CACHE = 'md-runtime-v1';
const LOGO_CACHE = 'md-logos-v1';
const BASE = <?= json_encode(BASE_PATH) ?>;
const OFFLINE_URL = <?= json_encode(url('offline')) ?>;
const PRECACHE = <?= json_encode($precache, JSON_UNESCAPED_SLASHES) ?>;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((c) => Promise.all(PRECACHE.map((u) => c.add(u).catch(() => null))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('md-static-') && k !== STATIC_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

async function staleWhileRevalidate(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    const network = fetch(req).then((res) => {
        if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone());
        return res;
    }).catch(() => cached);
    return cached || network;
}

async function cacheFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    if (cached) return cached;
    const res = await fetch(req);
    if (res.ok) cache.put(req, res.clone());
    return res;
}

async function networkFirstPage(req) {
    const cache = await caches.open(RUNTIME_CACHE);
    try {
        const res = await fetch(req);
        // stránky s "no-store" (napr. tajný log) sa do offline cache neukladajú
        if (res.ok && !/no-store/i.test(res.headers.get('Cache-Control') || '')) cache.put(req, res.clone());
        return res;
    } catch (e) {
        return (await cache.match(req)) || (await caches.match(OFFLINE_URL));
    }
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // stránky – najprv sieť, pri výpadku posledná uložená verzia / offline stránka
    if (req.mode === 'navigate') {
        event.respondWith(networkFirstPage(req));
        return;
    }

    if (url.origin === location.origin) {
        if (url.pathname.startsWith(BASE + '/api-logo')) { event.respondWith(cacheFirst(req, LOGO_CACHE)); return; }
        if (url.pathname.startsWith(BASE + '/assets/') || url.pathname.startsWith(BASE + '/includes/vendor/')) {
            event.respondWith(staleWhileRevalidate(req, STATIC_CACHE));
            return;
        }
        if (url.pathname.startsWith(BASE + '/api-match')) {
            event.respondWith(fetch(req).catch(() => caches.match(req)));
            return;
        }
        return;
    }

    // Google Fonts, ikony a select2 téma z CDN
    if (/^(fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net)$/.test(url.hostname)) {
        event.respondWith(staleWhileRevalidate(req, RUNTIME_CACHE));
    }
});
<?php exit; ?>
