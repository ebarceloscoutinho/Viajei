<?php
/** Painel do guia — área separada (login próprio em guia/login.php, sessão $_SESSION['gui']). Cada guia vê só as próprias viagens. */
if (!defined('BASE')) define('BASE', '../');
if (!defined('AREA')) define('AREA', 'guia');
require_once __DIR__ . '/app.php';
function guiaLogado() { return $_SESSION['gui'] ?? null; }
function exigirGuia() { if (!guiaLogado()) redirecionar(BASE . 'guia/login.php'); }
function guiaTopo($titulo, $ativa = '') {
    $b = BASE; $g = guiaLogado();
    echo '<!doctype html><html lang="pt-BR" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex,nofollow">'
       . '<title>' . e($titulo) . ' · Guia ' . e(SITE_NOME) . '</title><link rel="icon" href="' . $b . 'assets/img/favicon.png">' . headTema()
       . "<script src=\"{$b}assets/js/tema.js\"></script><link href=\"{$b}assets/dist/css/bootstrap.min.css\" rel=\"stylesheet\"><link href=\"{$b}css/site.css?v=" . CSS_V . "\" rel=\"stylesheet\"></head><body>"
       . '<nav class="navbar bg-body-tertiary border-bottom sticky-top"><div class="container"><a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php"><img src="' . $b . 'assets/img/logo.svg" width="30" height="30" alt="">' . e(SITE_NOME) . ' <small class="text-body-secondary fw-normal">guia</small></a>'
       . '<div class="d-flex align-items-center gap-2"><a class="btn btn-sm ' . ($ativa === 'index' ? 'btn-primary' : 'btn-outline-primary') . '" href="index.php">Painel</a><a class="btn btn-sm ' . ($ativa === 'perfil' ? 'btn-primary' : 'btn-outline-primary') . '" href="perfil.php">Meu perfil</a>' . temaMenu() . '<a class="btn btn-sm btn-outline-secondary" href="logout.php">Sair</a></div></div></nav><main class="container py-4">';
    if (!empty($_SESSION['flash'])) { [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-' . e($t) . ' alert-dismissible fade show">' . e($m) . '<button class="btn-close" data-bs-dismiss="alert"></button></div>'; }
}
function guiaRodape() { echo '</main><script src="' . BASE . 'assets/dist/js/bootstrap.bundle.min.js"></script></body></html>'; }
