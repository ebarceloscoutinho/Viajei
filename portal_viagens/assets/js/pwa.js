// Registro do service worker e botão "Instalar app".
(function () {
  if ('serviceWorker' in navigator) window.addEventListener('load', function () { navigator.serviceWorker.register(window.PV_SW || 'sw.js').catch(function () {}); });
  var ev = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); ev = e;
    document.querySelectorAll('.btn-instalar').forEach(function (b) { b.classList.remove('d-none'); });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.btn-instalar'); if (!b || !ev) return;
    ev.prompt(); ev.userChoice.finally(function () { ev = null; document.querySelectorAll('.btn-instalar').forEach(function (x) { x.classList.add('d-none'); }); });
  });
  window.addEventListener('appinstalled', function () { document.querySelectorAll('.btn-instalar').forEach(function (x) { x.classList.add('d-none'); }); });
})();
