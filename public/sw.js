/* Service worker mínimo: guarda só os arquivos estáticos do app.
   Dados de clientes, produtos e preços NUNCA ficam em cache aqui (evita preço velho). */
const VERSAO = 'fv-v2';
const ESTATICOS = ['/css/app.css', '/js/app.js', '/vendor/alpine.min.js', '/offline.html', '/icons/icon-192.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSAO).then(c => c.addAll(ESTATICOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(chaves => Promise.all(chaves.filter(k => k !== VERSAO).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  if (ESTATICOS.includes(url.pathname)) {
    e.respondWith(caches.match(req).then(c => c || fetch(req)));
  }
});
