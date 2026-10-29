<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('index'); $fin = adminPode('financeiro');
$k = $conn->query("SELECT
  (SELECT COALESCE(SUM(valor_total),0) FROM reservas WHERE status='confirmada') AS receita,
  (SELECT COALESCE(SUM(valor_total),0) FROM reservas WHERE status='em_analise') AS a_confirmar,
  (SELECT COUNT(*) FROM reservas WHERE status='aguardando_pagamento') AS aguardando,
  (SELECT COUNT(*) FROM reservas WHERE status='confirmada') AS confirmadas,
  (SELECT COALESCE(SUM(taxa_cancelamento),0) FROM reservas WHERE status='cancelada') AS taxas,
  (SELECT COUNT(*) FROM passeios WHERE ativo=1) AS passeios,
  (SELECT COUNT(*) FROM usuarios WHERE tipo_user=1) AS viajantes,
  (SELECT COUNT(*) FROM usuarios WHERE bloqueado_ate > NOW()) AS bloqueados,
  (SELECT COUNT(*) FROM chamados WHERE status='aberto') AS chamados,
  (SELECT COUNT(*) FROM lista_espera WHERE status IN ('aguardando','notificado')) AS espera")->fetch();
$pend = adminPode('pagamentos') ? $conn->query("SELECT pg.created_at, pg.metodo, r.valor_total, u.nome, p.titulo FROM pagamentos pg JOIN reservas r ON r.id=pg.reserva_id JOIN usuarios u ON u.id=r.usuario_id JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE pg.status='informado' ORDER BY pg.created_at LIMIT 5")->fetchAll() : [];
$top = $fin ? $conn->query("SELECT p.titulo, SUM(r.qtd) AS pessoas, SUM(r.valor_total) AS valor FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE r.status='confirmada' GROUP BY p.id ORDER BY pessoas DESC LIMIT 5")->fetchAll() : [];
$logs = adminPode('usuarios') ? $conn->query('SELECT * FROM logs_acesso ORDER BY id DESC LIMIT 6')->fetchAll() : [];
admTopo('Painel admin', 'index');
$cards = [];
if ($fin) $cards = [['Receita confirmada', brl($k['receita'])], ['Em análise (a confirmar)', brl($k['a_confirmar'])], ['Taxas de cancelamento', brl($k['taxas'])]];
$cards = array_merge($cards, [['Aguardando pagamento', $k['aguardando']], ['Reservas confirmadas', $k['confirmadas']], ['Viagens ativas', $k['passeios']], ['Viajantes', $k['viajantes']], ['Clientes bloqueados', $k['bloqueados']], ['Na lista de espera', $k['espera']], ['Chamados abertos', $k['chamados']]]);
?>
<h1 class="h3 mb-1">Dashboard</h1><p class="text-body-secondary">Você está logado como <strong><?= e(['3' => 'Administrador', '4' => 'Financeiro', '5' => 'Atendimento', '6' => 'Catálogo'][adminLogado()['tipo']] ?? '') ?></strong>.</p>
<?php if (adminPode('pagamentos')): ?><?php if ($pend): ?><div class="alert alert-warning"><div class="d-flex justify-content-between"><strong>🔔 <?= count($pend) === 5 ? '5+' : count($pend) ?> pagamento(s) aguardando sua confirmação</strong><a href="pagamentos.php">Abrir →</a></div>
  <ul class="mb-0 mt-2 small"><?php foreach ($pend as $p): ?><li><?= e($p['nome']) ?> — <?= e($p['titulo']) ?> — <?= brl($p['valor_total']) ?> (<?= e(strtoupper($p['metodo'])) ?>, <?= e(date('d/m H:i', strtotime($p['created_at']))) ?>)</li><?php endforeach; ?></ul></div>
<?php else: ?><div class="alert alert-success">✅ Nenhum pagamento pendente de confirmação.</div><?php endif; ?><?php endif; ?>
<?php if (adminPode('suporte') && $k['chamados']): ?><div class="alert alert-info">🛟 <?= (int)$k['chamados'] ?> chamado(s) aberto(s) no suporte. <a href="suporte.php">Responder →</a></div><?php endif; ?>
<div class="row row-cols-2 row-cols-lg-3 g-3 mb-4"><?php foreach ($cards as [$t, $v]): ?><div class="col"><div class="card h-100 kpi"><div class="card-body"><div class="small text-body-secondary"><?= e($t) ?></div><div class="h4 text-primary mb-0"><?= e($v) ?></div></div></div></div><?php endforeach; ?></div>
<div class="row g-4">
  <?php if ($fin): ?><div class="col-lg-6"><h2 class="h6">Viagens mais vendidas (confirmadas)</h2><table class="table table-sm"><thead><tr><th>Viagem</th><th>Pessoas</th><th>Valor</th></tr></thead><tbody>
    <?php foreach ($top as $t): ?><tr><td><?= e($t['titulo']) ?></td><td><?= (int)$t['pessoas'] ?></td><td><?= brl($t['valor']) ?></td></tr><?php endforeach; ?>
    <?php if (!$top): ?><tr><td colspan="3" class="text-body-secondary">Sem vendas confirmadas ainda.</td></tr><?php endif; ?></tbody></table></div><?php endif; ?>
  <?php if ($logs): ?><div class="col-lg-6"><h2 class="h6">Últimos acessos</h2><table class="table table-sm"><thead><tr><th>E-mail</th><th></th><th>Área</th><th>Quando</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?><tr><td><?= e($l['email']) ?></td><td><?= $l['sucesso'] ? '✅' : '❌' ?></td><td><?= e($l['area']) ?></td><td><?= e($l['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php admRodape();
