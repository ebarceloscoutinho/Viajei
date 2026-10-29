<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('avaliacoes');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $id = (int)($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'excluir') { $conn->prepare('DELETE FROM avaliacoes WHERE id=?')->execute([$id]); flash('Avaliação excluída.'); }
    else { $conn->prepare('UPDATE avaliacoes SET visivel = 1 - visivel WHERE id=?')->execute([$id]); flash('Visibilidade alterada.'); }
    redirecionar('avaliacoes.php' . (isset($_GET['v']) ? '?v=' . $_GET['v'] : ''));
}
$v = $_GET['v'] ?? '';
$st = $conn->query('SELECT a.*, u.nome, p.titulo FROM avaliacoes a JOIN usuarios u ON u.id=a.usuario_id JOIN passeios p ON p.id=a.passeio_id ' . ($v === 'ocultas' ? 'WHERE a.visivel=0 ' : ($v === 'visiveis' ? 'WHERE a.visivel=1 ' : '')) . 'ORDER BY a.id DESC LIMIT 200');
$lista = $st->fetchAll(); $med = $conn->query('SELECT ROUND(AVG(nota),2) m, COUNT(*) n FROM avaliacoes WHERE visivel=1')->fetch();
admTopo('Avaliações', 'avaliacoes');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-0">Moderação de avaliações</h1><small class="text-body-secondary">Média geral: <?= $med['m'] ? number_format($med['m'], 2, ',', '') : '—' ?> ★ (<?= (int)$med['n'] ?> visíveis)</small></div>
  <div class="d-flex gap-2"><a class="chip <?= $v === '' ? 'ativo' : '' ?>" href="?">Todas</a><a class="chip <?= $v === 'visiveis' ? 'ativo' : '' ?>" href="?v=visiveis">Visíveis</a><a class="chip <?= $v === 'ocultas' ? 'ativo' : '' ?>" href="?v=ocultas">Ocultas</a></div></div>
<div class="vstack gap-2"><?php foreach ($lista as $a): ?><div class="card <?= $a['visivel'] ? '' : 'opacity-50' ?>"><div class="card-body py-2 d-flex justify-content-between gap-3">
  <div style="min-width:0"><?= estrelas($a['nota']) ?> <strong><?= e($a['nome']) ?></strong> <small class="text-body-secondary">em <?= e($a['titulo']) ?> · <?= dataBr($a['created_at']) ?></small><?php if ($a['comentario']): ?><p class="mb-0 small"><?= e($a['comentario']) ?></p><?php endif; ?></div>
  <form method="post" class="d-flex gap-1 align-items-start"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <button class="btn btn-sm btn-outline-secondary"><?= $a['visivel'] ? 'Ocultar' : 'Exibir' ?></button><button class="btn btn-sm btn-outline-danger" name="acao" value="excluir" onclick="return confirm('Excluir definitivamente?')">🗑</button></form></div></div><?php endforeach; ?>
<?php if (!$lista): ?><div class="alert alert-info">Nenhuma avaliação.</div><?php endif; ?></div>
<?php admRodape();
