<?php
require_once __DIR__ . '/inc/app.php';
$q = trim($_GET['q'] ?? '');
$st = $conn->prepare('SELECT * FROM guias_viagem WHERE titulo LIKE ? OR destino LIKE ? ORDER BY created_at DESC'); $st->execute(["%$q%", "%$q%"]); $lista = $st->fetchAll();
topo('Guias de viagem');
?>
<h1 class="h3 mb-3">Guias de viagem</h1>
<form class="mb-4" method="get"><div class="input-group" style="max-width:480px"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por destino ou tema"><button class="btn btn-primary">Buscar</button></div></form>
<?php if (!$lista): ?><div class="alert alert-info">Nenhum guia encontrado.</div><?php endif; ?>
<div class="row row-cols-1 row-cols-md-3 g-4"><?php foreach ($lista as $g): ?><div class="col"><a class="card h-100 card-hover text-decoration-none" href="guia.php?id=<?= (int)$g['id'] ?>"><?= capa($g['emoji'], $g['hue']) ?>
  <div class="card-body"><div class="small text-body-secondary"><?= e($g['destino']) ?></div><h2 class="h6 text-body"><?= e($g['titulo']) ?></h2><p class="small text-body-secondary mb-0"><?= e($g['resumo']) ?></p></div></a></div><?php endforeach; ?></div>
<?php rodape();
