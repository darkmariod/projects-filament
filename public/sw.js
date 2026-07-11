// Service Worker — Productos Paraíso (Garantías y Etiquetas)
// Auth-safe: NUNCA cachea navegaciones ni HTML (así tras cerrar sesión no se ve
// ninguna pantalla del panel vieja). Solo cachea assets estáticos.

const CACHE_VERSION = 'paraiso-garantias-v1';
const ASSET_CACHE = `assets-${CACHE_VERSION}`;

const ASSET_PATTERN = /\.(?:js|css|woff2?|ttf|otf|png|jpg|jpeg|svg|gif|webp|ico)$/i;

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k !== ASSET_CACHE).map((k) => caches.delete(k)));
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // Navegaciones / HTML -> siempre a la red (auth-safe).
    if (request.mode === 'navigate' || request.destination === 'document') {
        return;
    }

    // Assets estáticos -> cache-first con revalidación.
    if (ASSET_PATTERN.test(new URL(request.url).pathname)) {
        event.respondWith(
            (async () => {
                const cache = await caches.open(ASSET_CACHE);
                const cached = await cache.match(request);
                const network = fetch(request)
                    .then((response) => {
                        if (response && response.status === 200) {
                            cache.put(request, response.clone());
                        }
                        return response;
                    })
                    .catch(() => cached);
                return cached || network;
            })(),
        );
    }
});
