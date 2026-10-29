// Service Worker da Roteira: páginas dinâmicas (sessão, pagamentos) sempre vêm da rede.
// CSS e JS: rede primeiro (cache só quando está offline), para nunca ficar preso numa versão antiga do visual.
// Imagens e fontes: cache primeiro (mudam raramente) e atualizam em segundo plano.
const V = 'roteira-v2';   // aumente este número se mudar a lista abaixo; versões antigas do cache são apagadas ao ativar
const ESTATICOS = ['offline.html', 'css/site.css', 'assets/dist/css/bootstrap.min.css', 'assets/dist/js/bootstrap.bundle.min.js',
                   'assets/js/tema.js', 'assets/js/pwa.js', 'assets/img/logo.svg', 'assets/img/icon-192.png'];
self.addEventListener('install', e => e.waitUntil(caches.open(V).then(c => c.addAll(ESTATICOS)).then(() => self.skipWaiting())));
self.addEventListener('activate', e => e.waitUntil(
  caches.keys().then(ks => Promise.all(ks.filter(k => k !== V).map(k => caches.delete(k)))).then(() => self.clients.claim())));
const guardar = (r, res) => { if (res && res.ok) { const cp = res.clone(); caches.open(V).then(c => c.put(r, cp)); } return res; };
self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || /\/(admin|api)\//.test(u.pathname)) return;
  if (r.mode === 'navigate') { e.respondWith(fetch(r).catch(() => caches.match('offline.html'))); return; }
  if (/\.(css|js)$/.test(u.pathname)) {   // rede primeiro
    e.respondWith(fetch(r).then(res => guardar(r, res)).catch(() => caches.match(r, { ignoreSearch: true })));
    return;
  }
  if (/\.(png|svg|jpg|jpeg|webp|woff2?)$/.test(u.pathname)) {   // cache primeiro
    e.respondWith(caches.match(r).then(hit => {
      const rede = fetch(r).then(res => guardar(r, res)).catch(() => hit);
      return hit || rede;
    }));
  }
});
