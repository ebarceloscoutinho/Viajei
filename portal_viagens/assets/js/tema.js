// Tema individual: modo, cor e tamanho do texto. Logado = salvo na conta (vale em qualquer aparelho); visitante = só neste navegador.
(function () {
  var d = document.documentElement, S = window.PV_TEMA || {}, pad = { modo: 'auto', cor: 'verde', fonte: 'normal' };
  if (S.pad) for (var kk in S.pad) pad[kk] = S.pad[kk];
  var mq = window.matchMedia('(prefers-color-scheme: dark)');
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem('pv_' + k); localStorage.setItem('pv_' + k, v); } catch (e) {} }
  function get(k) { return (S.prefs && S.prefs[k]) || ls(k) || pad[k]; }
  function aplicar() {
    var m = get('modo');
    d.setAttribute('data-bs-theme', (m === 'dark' || (m === 'auto' && mq.matches)) ? 'dark' : 'light');
    d.setAttribute('data-cor', get('cor')); d.setAttribute('data-fonte', get('fonte'));
    var t = document.querySelector('meta[name=theme-color]');
    if (t) t.setAttribute('content', getComputedStyle(d).getPropertyValue('--brand').trim() || '#0f766e');
  }
  function marcar() {
    document.querySelectorAll('[data-tema]').forEach(function (b) { var p = b.dataset.tema.split(':'); b.classList.toggle('active', get(p[0]) === p[1]); });
  }
  function salvar(k, v) {
    ls(k, v);
    if (!S.url) return;
    S.prefs = S.prefs || {}; S.prefs[k] = v;
    var fd = new FormData(); fd.append('csrf', S.csrf); fd.append('area', S.area); fd.append('chave', k); fd.append('valor', v);
    fetch(S.url, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }).catch(function () {});
  }
  aplicar();
  if (mq.addEventListener) mq.addEventListener('change', aplicar);
  document.addEventListener('DOMContentLoaded', function () {
    aplicar(); marcar();
    document.querySelectorAll('[data-tema]').forEach(function (b) {
      b.addEventListener('click', function () { var p = b.dataset.tema.split(':'); salvar(p[0], p[1]); aplicar(); marcar(); });
    });
  });
})();
