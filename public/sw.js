/* Nossa Finanças: service worker simples (RNF-08).
 *  - guarda os arquivos do app (CSS, JS, ícones) para abrir rápido;
 *  - páginas: tenta a internet primeiro; sem internet, mostra a última versão vista (o último mês carregado);
 *  - nunca guarda nem responde pedidos que gravam dados (POST do Livewire etc.): escrever exige internet;
 *  - ao Sair, a página manda "clear-cache" e apagamos tudo (as páginas guardadas têm dados do casal).
 */
const VERSION = 'nf-v1';
const ASSETS = `${VERSION}-assets`;
const PAGES = `${VERSION}-pages`;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(ASSETS).then((cache) => cache.addAll(['/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'clear-cache') {
        event.waitUntil(caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k)))));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return; // gravações e outros domínios seguem direto para a rede
    }

    // Arquivos do app: cache primeiro (têm hash no nome, então nunca ficam velhos)
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(caches.open(ASSETS).then(async (cache) => {
            const hit = await cache.match(request);
            if (hit) return hit;
            const response = await fetch(request);
            if (response.ok) cache.put(request, response.clone());
            return response;
        }));

        return;
    }

    // Páginas: rede primeiro; sem rede, a última versão vista
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).then((response) => {
            if (response.ok && !response.redirected) {
                const copy = response.clone();
                caches.open(PAGES).then((cache) => cache.put(request, copy));
            }
            return response;
        }).catch(async () => (await caches.match(request)) || new Response('<h1>Sem internet</h1><p>Abra o app com internet ao menos uma vez para ver o último mês aqui.</p>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
    }
});
