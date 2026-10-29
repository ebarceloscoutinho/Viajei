<?php
require_once __DIR__ . '/inc/app.php';
$id = (int)($_GET['id'] ?? 0);
$sel = "SELECT g.*, (SELECT GROUP_CONCAT(l.local ORDER BY l.id SEPARATOR '|') FROM guia_locais l WHERE l.guia_id=g.id) AS locais,
        (SELECT COUNT(*) FROM passeios p WHERE p.guia_id=g.id AND p.ativo=1) AS n_viagens FROM guias g WHERE g.ativo=1";
if ($id) {
    $st = $conn->prepare($sel . ' AND g.id=?'); $st->execute([$id]); $g = $st->fetch();
    if (!$g) { http_response_code(404); topo('Guia'); echo '<div class="alert alert-warning">Guia não encontrado.</div>'; rodape(); exit; }
    $viagens = buscarPasseios($conn, 'AND p.guia_id=?', [$id], 'p.escopo, p.id', 60);
    topo($g['nome']);
?>
<nav class="small mb-3"><a href="equipe.php">Nossos guias</a> / <?= e($g['nome']) ?></nav>
<div class="card card-body flex-md-row gap-4 align-items-md-center mb-4"><div class="text-center"><?= avatar($g['nome'], $g['foto'], 120) ?></div>
  <div><h1 class="h3 mb-1"><?= e($g['nome']) ?></h1><div class="text-body-secondary mb-2">🗣️ <?= e($g['idiomas']) ?> · <?= (int)$g['n_viagens'] ?> viagem(ns)</div>
    <p class="mb-2"><?= nl2br(e($g['bio'])) ?></p>
    <div class="d-flex flex-wrap gap-1"><?php foreach (array_filter(explode('|', (string)$g['locais'])) as $l): ?><span class="badge text-bg-light border">📍 <?= e($l) ?></span><?php endforeach; ?></div></div></div>
<h2 class="h5 mb-3">Viagens com <?= e(explode(' ', $g['nome'])[0]) ?></h2>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"><?php foreach ($viagens as $p) echo cardPasseio($p); ?></div>
<?php if (!$viagens): ?><div class="alert alert-info">Este guia ainda não tem viagens publicadas.</div><?php endif;
    rodape(); exit;
}
$q = trim($_GET['q'] ?? '');
$st = $conn->prepare($sel . ' AND (g.nome LIKE ? OR EXISTS (SELECT 1 FROM guia_locais l WHERE l.guia_id=g.id AND l.local LIKE ?)) ORDER BY g.nome'); $st->execute(["%$q%", "%$q%"]); $lista = $st->fetchAll();
topo('Nossos guias');
?>
<h1 class="h3 mb-1">🧑‍🏫 Nossos guias locais</h1>
<p class="text-body-secondary">Profissionais que conhecem cada destino de perto. Busque por nome ou lugar (ex.: Itália, Bahia, Japão).</p>
<form class="mb-4" method="get"><div class="input-group" style="max-width:480px"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nome ou destino"><button class="btn btn-primary">Buscar</button></div></form>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"><?php foreach ($lista as $g): ?>
  <div class="col"><a class="card h-100 card-hover card-body text-decoration-none" href="equipe.php?id=<?= (int)$g['id'] ?>"><div class="d-flex gap-3 align-items-center mb-2"><?= avatar($g['nome'], $g['foto'], 68) ?>
    <div><strong class="text-body"><?= e($g['nome']) ?></strong><div class="small text-body-secondary">🗣️ <?= e($g['idiomas']) ?></div></div></div>
    <p class="small text-body-secondary mb-2"><?= e(mb_strimwidth((string)$g['bio'], 0, 120, '…')) ?></p>
    <div class="mt-auto d-flex flex-wrap gap-1"><?php foreach (array_slice(array_filter(explode('|', (string)$g['locais'])), 0, 4) as $l): ?><span class="badge text-bg-light border"><?= e($l) ?></span><?php endforeach; ?><span class="badge text-bg-primary ms-auto"><?= (int)$g['n_viagens'] ?> viagens</span></div></a></div><?php endforeach; ?></div>
<?php if (!$lista): ?><div class="alert alert-info">Nenhum guia encontrado.</div><?php endif;
rodape();
