/**
 * CSRF (Cross-Site Request Forgery) koruması için global fetch interceptor.
 * Durum değiştiren (POST, PUT, DELETE, PATCH) tüm HTTP isteklerine
 * sayfanın <meta name="csrf-token"> etiketindeki belirteci otomatik olarak ekler.
 */
(function () {
    const originalFetch = window.fetch;
    window.fetch = function (resource, init = {}) {
        init = init || {};
        const method = (init.method || 'GET').toUpperCase();

        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const token = tokenMeta ? tokenMeta.getAttribute('content') : null;

            if (token) {
                if (!init.headers) {
                    init.headers = {};
                }

                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-TOKEN')) {
                        init.headers.set('X-CSRF-TOKEN', token);
                    }
                } else if (Array.isArray(init.headers)) {
                    const hasToken = init.headers.some(([k]) => k.toLowerCase() === 'x-csrf-token');
                    if (!hasToken) {
                        init.headers.push(['X-CSRF-TOKEN', token]);
                    }
                } else {
                    if (!init.headers['X-CSRF-TOKEN'] && !init.headers['x-csrf-token']) {
                        init.headers['X-CSRF-TOKEN'] = token;
                    }
                }
            }
        }

        return originalFetch.call(this, resource, init);
    };
})();
