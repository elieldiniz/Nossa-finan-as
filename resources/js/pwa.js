/** PWA: registra o service worker e trata "Sair" e "sem internet". */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });

    // Sair apaga as páginas guardadas (têm dados do casal)
    document.addEventListener('submit', (event) => {
        const action = event.target?.getAttribute?.('action') || '';
        if (action.endsWith('/logout')) {
            navigator.serviceWorker.controller?.postMessage('clear-cache');
            caches?.keys().then((keys) => keys.forEach((k) => caches.delete(k)));
        }
    }, true);
}

// Sem internet: só leitura. Os botões que gravam ficam desativados (o aviso fica no layout).
const sync = () => document.documentElement.toggleAttribute('data-offline', !navigator.onLine);
window.addEventListener('online', sync);
window.addEventListener('offline', sync);
sync();
