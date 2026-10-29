<?php
require_once __DIR__ . '/inc/app.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirLogin(); csrfVerificar();
    $titulo = trim($_POST['titulo'] ?? ''); $destino = trim($_POST['destino'] ?? '');
    $dias = max(1, min(30, (int)($_POST['dias'] ?? 3))); $ini = $_POST['data_inicio'] ?? '';
    if ($titulo === '' || $destino === '') { flash('Informe título e destino.', 'danger'); redirecionar('roteiros.php#novo'); }
    $conn->prepare('INSERT INTO roteiros (usuario_id,titulo,destino,dias,data_inicio) VALUES (?,?,?,?,?)')
         ->execute([usuario()['id'], $titulo, $destino, $dias, preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini) ? $ini : null]);
    redirecionar('roteiro.php?id=' . $conn->lastInsertId());
}
$modelos = $conn->query('SELECT * FROM roteiros WHERE publico=1 ORDER BY id DESC')->fetchAll();
$meus = [];
if (usuario()) { $st = $conn->prepare('SELECT r.*, (SELECT COUNT(*) FROM roteiro_itens i WHERE i.roteiro_id=r.id) AS itens FROM roteiros r WHERE usuario_id=? ORDER BY id DESC'); $st->execute([usuario()['id']]); $meus = $st->fetchAll(); }
topo('Roteiros');
?>
<h1 class="h3 mb-4">Roteiros de viagem</h1>
<div class="row g-4">
  <div class="col-lg-7">
    <h2 class="h5" id="meus">Meus roteiros</h2>
    <?php if (!usuario()): ?><div class="alert alert-info"><a href="login.php">Entre</a> ou <a href="cadastro.php">crie uma conta</a> para montar seus roteiros.</div>
    <?php elseif (!$meus): ?><div class="alert alert-info">Você ainda não tem roteiros. Crie um ao lado ou copie um modelo abaixo.</div>
    <?php else: ?><div class="list-group mb-4"><?php foreach ($meus as $r): ?>
      <a class="list-group-item list-group-item-action" href="roteiro.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['titulo']) ?></strong><br><small class="text-body-secondary"><?= e($r['destino']) ?> · <?= (int)$r['dias'] ?> dias · <?= (int)$r['itens'] ?> atividades</small></a><?php endforeach; ?></div><?php endif; ?>
    <h2 class="h5 mt-4">Modelos prontos</h2>
    <div class="list-group"><?php foreach ($modelos as $r): ?>
      <a class="list-group-item list-group-item-action" href="roteiro.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['titulo']) ?></strong><br><small class="text-body-secondary"><?= e($r['destino']) ?> · <?= (int)$r['dias'] ?> dias</small></a><?php endforeach; ?></div>
  </div>
  <div class="col-lg-5"><div class="card" id="novo"><div class="card-body">
    <h2 class="h5">Novo roteiro</h2>
    <form method="post" class="vstack gap-2"><?= csrfCampo() ?>
      <input class="form-control" name="titulo" placeholder="Título (ex.: Férias em Paraty)" required>
      <input class="form-control" name="destino" placeholder="Destino" required>
      <div class="row g-2"><div class="col-5"><input class="form-control" type="number" name="dias" min="1" max="30" value="3" title="Quantidade de dias"></div>
        <div class="col-7"><input class="form-control" type="date" name="data_inicio" title="Data de início (opcional)"></div></div>
      <button class="btn btn-primary">Criar e começar a montar</button>
    </form></div></div></div>
</div>
<?php rodape();
