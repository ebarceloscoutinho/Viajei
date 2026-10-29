<?php
/**
 * Painel administrativo — área SEPARADA do site: login próprio (admin/login.php), sessão própria ($_SESSION['adm']) e layout próprio.
 * Administradores não entram pelo login do site e viajantes não entram aqui.
 */
if (!defined('BASE')) define('BASE', '../');
if (!defined('AREA')) define('AREA', 'admin');
require_once __DIR__ . '/app.php';

function adminLogado() { return $_SESSION['adm'] ?? null; }
/** Permissões por cargo: 3 Administrador (tudo) · 4 Financeiro · 5 Atendimento · 6 Catálogo. */
const PERM = ['index' => [3, 4, 5, 6], 'seguranca' => [3, 4, 5, 6], 'pagamentos' => [3, 4], 'financeiro' => [3, 4], 'precos' => [3, 4, 6], 'passeios' => [3, 6], 'guias' => [3, 4, 6], 'salario' => [3, 4],
              'reservas' => [3, 4, 5], 'avaliacoes' => [3, 5, 6], 'suporte' => [3, 5], 'cupons' => [3, 4, 6], 'usuarios' => [3], 'cambio' => [3, 6], 'emails' => [3, 5], 'migracoes' => [3], 'backup' => [3], 'configuracoes' => [3]];
function adminPode($chave) { $a = adminLogado(); return $a && in_array((int)($a['tipo'] ?? 0), PERM[$chave] ?? [3], true); }
function exigirAdmin($chave = 'index') {
    if (!adminLogado()) redirecionar(BASE . 'admin/login.php');
    if (!adminPode($chave)) { http_response_code(403); admTopo('Acesso negado', 'index'); echo '<div class="alert alert-warning">🔒 Seu cargo não tem acesso a esta área. Fale com um administrador.</div>'; admRodape(); exit; }
}

function admLinks($ativa) {
    global $conn; $n = pagamentosPendentes($conn);
    $itens = ['index' => ['📊', 'Dashboard'], 'pagamentos' => ['💳', 'Pagamentos'], 'financeiro' => ['💰', 'Financeiro'], 'precos' => ['🏷️', 'Preços'],
              'passeios' => ['🧭', 'Viagens e saídas'], 'guias' => ['🧑‍🏫', 'Guias'], 'reservas' => ['📋', 'Reservas'], 'avaliacoes' => ['⭐', 'Avaliações'], 'suporte' => ['🛟', 'Suporte'], 'cupons' => ['🎟️', 'Cupons'], 'usuarios' => ['👥', 'Usuários'], 'cambio' => ['💱', 'Câmbio e custos'], 'emails' => ['✉️', 'E-mails'], 'migracoes' => ['🧩', 'Migrações'], 'backup' => ['💾', 'Backup'], 'configuracoes' => ['⚙️', 'Configurações'], 'seguranca' => ['🔐', 'Segurança']];
    $abertos = (int)$conn->query("SELECT COUNT(*) FROM chamados WHERE status='aberto'")->fetchColumn();
    $h = '<ul class="nav nav-pills flex-column gap-1">';
    foreach ($itens as $k => [$ico, $txt]) if (adminPode($k)) $h .= '<li class="nav-item"><a class="nav-link d-flex justify-content-between' . ($k === $ativa ? ' active' : '') . '" href="' . $k . '.php"><span>' . $ico . ' ' . $txt . '</span>'
        . ($k === 'pagamentos' && $n ? '<span class="badge text-bg-danger">' . $n . '</span>' : '') . ($k === 'suporte' && $abertos ? '<span class="badge text-bg-warning">' . $abertos . '</span>' : '') . '</a></li>';
    return $h . '</ul>';
}
/** Cartão do usuário + botões "Ver o site" e "Sair" (rodapé do menu lateral e do menu do celular). */
function admUsuarioCard() {
    $a = adminLogado(); $nome = trim((string)($a['nome'] ?? 'Admin'));
    $cargos = [3 => 'Administrador', 4 => 'Financeiro', 5 => 'Atendimento', 6 => 'Catálogo'];
    $p = preg_split('/\s+/', $nome); $ini = mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    return '<div class="adm-user"><span class="adm-avatar" aria-hidden="true">' . e($ini) . '</span><div class="min-w-0"><div class="fw-semibold text-truncate">' . e($nome) . '</div><div class="small text-body-secondary">' . e($cargos[(int)($a['tipo'] ?? 3)] ?? 'Equipe') . '</div></div></div>'
         . '<div class="adm-acoes"><a class="adm-btn" href="' . BASE . 'index.php" target="_blank" rel="noopener"><span class="ico">🌐</span>Ver o site<span class="ext">↗</span></a>'
         . '<a class="adm-btn adm-sair" href="logout.php"><span class="ico">🚪</span>Sair</a></div>';
}
function admTopo($titulo, $ativa = '') {
    global $conn; $a = adminLogado(); $b = BASE; $n = adminPode('pagamentos') ? pagamentosPendentes($conn) : 0;
    echo '<!doctype html><html lang="pt-BR" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
       . '<meta name="robots" content="noindex,nofollow"><title>' . e($titulo) . ' · Admin ' . e(SITE_NOME) . '</title><link rel="icon" href="' . $b . 'assets/img/favicon.png">'
       . headTema() . "<script src=\"{$b}assets/js/tema.js\"></script><link href=\"{$b}assets/dist/css/bootstrap.min.css\" rel=\"stylesheet\"><link href=\"{$b}css/site.css?v=" . CSS_V . "\" rel=\"stylesheet\"></head><body>"
       . '<div class="d-flex"><aside class="adm-side d-none d-lg-flex flex-column p-3"><a class="d-flex align-items-center gap-2 mb-3 text-decoration-none text-body fw-bold" href="index.php"><img src="' . $b . 'assets/img/logo.svg" width="32" height="32" alt="">' . e(SITE_NOME) . ' <small class="text-body-secondary fw-normal">admin</small></a>'
       . admLinks($ativa) . '<div class="mt-auto pt-3">' . admUsuarioCard() . '</div></aside>'
       . '<div class="flex-grow-1" style="min-width:0"><header class="adm-top d-flex align-items-center gap-2 px-3 py-2"><button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#admMenu" aria-label="Menu">☰</button>'
       . '<strong class="me-auto text-truncate">' . e($titulo) . '</strong>' . temaMenu()
       . (adminPode('pagamentos') ? '<a class="btn btn-outline-secondary btn-sm position-relative" href="pagamentos.php" title="Pagamentos a confirmar" aria-label="Notificações">🔔' . ($n ? '<span class="badge text-bg-danger position-absolute top-0 start-100 translate-middle rounded-pill">' . $n . '</span>' : '') . '</a>' : '') . '</header>'
       . '<main class="p-3 p-lg-4">';
    if (!empty($_SESSION['flash'])) { [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-' . e($t) . ' alert-dismissible fade show">' . e($m) . '<button class="btn-close" data-bs-dismiss="alert"></button></div>'; }
    echo '<div class="offcanvas offcanvas-start" tabindex="-1" id="admMenu"><div class="offcanvas-header"><h5 class="offcanvas-title">' . e(SITE_NOME) . ' · Admin</h5><button class="btn-close" data-bs-dismiss="offcanvas"></button></div>'
       . '<div class="offcanvas-body d-flex flex-column">' . admLinks($ativa) . '<div class="mt-auto pt-3">' . admUsuarioCard() . '</div></div></div>';
}
function admRodape() {
    echo '</main></div></div><script src="' . BASE . 'assets/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
