<?php
require_once __DIR__ . '/inc/app.php';
$st = $conn->prepare('SELECT * FROM guias_viagem WHERE id=?'); $st->execute([(int)($_GET['id'] ?? 0)]); $g = $st->fetch();
if (!$g) { http_response_code(404); topo('Guia'); echo '<div class="alert alert-warning">Guia não encontrado.</div>'; rodape(); exit; }
$rel = buscarPasseios($conn, 'AND p.destino = ?', [$g['destino']], 'p.id', 3);   // passeios do mesmo destino
topo($g['titulo']);
?>
<div class="row justify-content-center"><div class="col-lg-8">
  <nav class="small mb-3"><a href="guias.php">Guias de viagem</a> / <?= e($g['destino']) ?></nav>
  <?= capa($g['emoji'], $g['hue'], 'grande') ?>
  <h1 class="h2 mt-3"><?= e($g['titulo']) ?></h1><p class="lead text-body-secondary"><?= e($g['resumo']) ?></p>
  <div class="fs-6"><?= nl2br(e($g['conteudo'])) ?></div>
  <?php if ($rel): ?><h2 class="h5 mt-5">Passeios em <?= e($g['destino']) ?></h2><div class="row row-cols-1 row-cols-md-2 g-3"><?php foreach ($rel as $p) echo cardPasseio($p); ?></div><?php endif; ?>
</div></div>
<?php rodape();
