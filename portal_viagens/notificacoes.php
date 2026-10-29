<?php
require_once __DIR__ . '/inc/app.php';
exigirLogin(); $uid = usuario()['id'];
if (isset($_GET['ir'])) {   // marca como lida e segue para a página da notificação (só caminhos internos)
    $st = $conn->prepare('SELECT * FROM notificacoes WHERE id=? AND usuario_id=?'); $st->execute([(int)$_GET['ir'], $uid]); $n = $st->fetch();
    if ($n) { $conn->prepare('UPDATE notificacoes SET lida=1 WHERE id=?')->execute([$n['id']]); if ($n['url'] && preg_match('~^[a-z_]+\.php(\?[A-Za-z0-9_=&%.-]*)?$~', $n['url'])) redirecionar($n['url']); }
    redirecionar('notificacoes.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfVerificar(); $conn->prepare('UPDATE notificacoes SET lida=1 WHERE usuario_id=?')->execute([$uid]); redirecionar('notificacoes.php'); }
$st = $conn->prepare('SELECT * FROM notificacoes WHERE usuario_id=? ORDER BY id DESC LIMIT 50'); $st->execute([$uid]); $lista = $st->fetchAll();
topo('Notificações');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">🔔 Notificações</h1><form method="post"><?= csrfCampo() ?><button class="btn btn-sm btn-outline-secondary">Marcar todas como lidas</button></form></div>
<?php if (!$lista): ?><div class="alert alert-info">Você não tem notificações. Avisos sobre reservas, pagamentos e vagas liberadas aparecem aqui.</div><?php endif; ?>
<div class="list-group"><?php foreach ($lista as $n): ?><a class="list-group-item list-group-item-action <?= $n['lida'] ? '' : 'fw-semibold border-start border-4 border-primary' ?>" href="notificacoes.php?ir=<?= (int)$n['id'] ?>">
  <div class="d-flex justify-content-between"><span><?= e($n['titulo']) ?></span><small class="text-body-secondary"><?= e(date('d/m H:i', strtotime($n['created_at']))) ?></small></div><div class="small text-body-secondary fw-normal"><?= e($n['mensagem']) ?></div></a><?php endforeach; ?></div>
<?php rodape();
