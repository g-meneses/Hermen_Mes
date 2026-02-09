/**
 * Service Worker - PWA Mobile Hermen
 * Maneja cache y funcionamiento offline
 * v3 - Con fallback offline.html
 */

const CACHE_VERSION = 'v3';
const CACHE_NAME = `hermen-mobile-${CACHE_VERSION}`;
const API_CACHE_NAME = `hermen-api-${CACHE_VERSION}`;

// El archivo offline.html es CRÍTICO - es el fallback cuando no hay red
const OFFLINE_PAGE = './offline.html';

// Assets que deben cachearse SIEMPRE
const PRECACHE_ASSETS = [
    './',
    './index.php',
    './offline.html',
    './css/mobile.css',
    './js/app.js',
    './js/db.js',
    './js/sync.js',
    './manifest.json'
];

// Instalación - cachear assets estáticos
self.addEventListener('install', event => {
    console.log('[SW] Instalando versión:', CACHE_VERSION);

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(async cache => {
                console.log('[SW] Pre-cacheando assets...');

                // Cachear cada asset individualmente para mejor manejo de errores
                const results = await Promise.allSettled(
                    PRECACHE_ASSETS.map(async url => {
                        try {
                            const response = await fetch(url, { cache: 'reload' });
                            if (response.ok) {
                                await cache.put(url, response);
                                console.log('[SW] ✓ Cacheado:', url);
                                return { url, success: true };
                            }
                            throw new Error(`HTTP ${response.status}`);
                        } catch (err) {
                            console.warn('[SW] ✗ Error cacheando:', url, err.message);
                            return { url, success: false, error: err.message };
                        }
                    })
                );

                // Cachear CDNs externos
                const externalAssets = [
                    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
                ];

                for (const url of externalAssets) {
                    try {
                        const response = await fetch(url, { mode: 'cors' });
                        if (response.ok) {
                            await cache.put(url, response);
                            console.log('[SW] ✓ CDN cacheado:', url);
                        }
                    } catch (e) {
                        console.warn('[SW] CDN no disponible:', url);
                    }
                }

                console.log('[SW] Pre-cache completado');
            })
            .then(() => {
                console.log('[SW] Skip waiting...');
                return self.skipWaiting();
            })
    );
});

// Activación - limpiar caches viejos y tomar control
self.addEventListener('activate', event => {
    console.log('[SW] Activando...');

    event.waitUntil(
        caches.keys()
            .then(keys => {
                return Promise.all(
                    keys.filter(key => !key.includes(CACHE_VERSION))
                        .map(key => {
                            console.log('[SW] Eliminando cache viejo:', key);
                            return caches.delete(key);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Tomando control de clientes');
                return self.clients.claim();
            })
    );
});

// Fetch - estrategia de cache
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Solo manejar GET
    if (event.request.method !== 'GET') {
        return;
    }

    // Ignorar chrome-extension y otros
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // APIs - Network First
    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Navegación (páginas HTML) - Network First con fallback a offline.html
    if (event.request.mode === 'navigate') {
        event.respondWith(handleNavigation(event.request));
        return;
    }

    // Assets estáticos - Cache First
    event.respondWith(cacheFirst(event.request));
});

// Manejo especial para navegación
async function handleNavigation(request) {
    try {
        // Intentar red primero
        const response = await fetch(request);

        if (response.ok) {
            // Cachear la respuesta exitosa
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
            return response;
        }

        throw new Error(`HTTP ${response.status}`);

    } catch (error) {
        console.log('[SW] Navegación falló, buscando cache:', error.message);

        // Buscar en cache
        const cached = await caches.match(request);
        if (cached) {
            console.log('[SW] Sirviendo desde cache:', request.url);
            return cached;
        }

        // Fallback a offline.html
        console.log('[SW] Sirviendo offline.html');
        const offlinePage = await caches.match(OFFLINE_PAGE);

        if (offlinePage) {
            return offlinePage;
        }

        // Última opción: página inline
        return new Response(
            `<!DOCTYPE html>
            <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Sin Conexión</title>
            <style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#1a1a2e;color:#fff;text-align:center;padding:20px}
            .icon{font-size:64px;margin-bottom:20px}button{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:0;padding:15px 30px;border-radius:25px;font-size:16px;cursor:pointer;margin-top:20px}</style>
            </head><body><div class="icon">📡</div><h1>Sin Conexión</h1><p>Por favor verifica tu conexión a internet</p>
            <button onclick="location.reload()">Reintentar</button></body></html>`,
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

// Estrategia: Cache First
async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);

        if (response.ok && response.type === 'basic') {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.error('[SW] Fetch falló:', request.url);
        return new Response('Offline', { status: 503 });
    }
}

// Estrategia: Network First
async function networkFirst(request) {
    try {
        const response = await fetch(request);

        if (response.ok && request.url.includes('catalogos')) {
            const cache = await caches.open(API_CACHE_NAME);
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.log('[SW] Red falló, buscando cache API');

        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }

        return new Response(
            JSON.stringify({ success: false, offline: true, message: 'Sin conexión' }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
    }
}

// Sincronización en background
self.addEventListener('sync', event => {
    console.log('[SW] Sync event:', event.tag);

    if (event.tag === 'sync-salidas') {
        event.waitUntil(syncSalidas());
    }
});

async function syncSalidas() {
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({ type: 'SYNC_REQUIRED' });
    });
}

// Mensajes desde la app
self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    // Forzar recarga del cache
    if (event.data?.type === 'REFRESH_CACHE') {
        event.waitUntil(
            caches.delete(CACHE_NAME).then(() => {
                return caches.open(CACHE_NAME).then(cache => {
                    return cache.addAll(PRECACHE_ASSETS);
                });
            })
        );
    }
});

console.log('[SW] Script cargado - versión', CACHE_VERSION);
