<?php
define('BASE', '../'); define('AREA', 'guia');
require_once __DIR__ . '/../inc/guia.php';
exigirGuia(); $gid = guiaLogado()['guia_id'];
$k = $conn->prepare("SELECT
  (SELECT COUNT(*) FROM passeios WHERE guia_id=? AND ativo=1) AS viagens,
  (SELECT COUNT(*) FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE p.guia_id=? AND s.data_saida>=CURDATE()) AS saidas,
  (SELECT COALESCE(SUM(r.qtd),0) FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE p.guia_id=? AND s.data_saida>=CURDATE() AND r.status='confirmada') AS passageiros,
  (SELECT ROUND(AVG(a.nota),1) FROM avaliacoes a JOIN passeios p ON p.id=a.passeio_id WHERE p.guia_id=? AND a.visivel=1) AS nota,
  (SELECT COUNT(*) FROM avaliacoes a JOIN passeios p ON p.id=a.passeio_id WHERE p.guia_id=? AND a.visivel=1) AS n_aval"); $k->execute([$gid, $gid, $gid, $gid, $gid]); $k = $k->fetch();
$st = $conn->prepare("SELECT s.id, s.data_saida, s.vagas_total, s.vagas_disponiveis, p.titulo, p.destino, p.pais, p.escopo,
  COALESCE((SELECT SUM(r.qtd) FROM reservas r WHERE r.saida_id=s.id AND r.status='confirmada'),0) AS confirmados,
  COALESCE((SELECT SUM(r.qtd) FROM reservas r WHERE r.saida_id=s.id AND r.status IN ('aguardando_pagamento','em_analise')),0) AS pendentes
  FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE p.guia_id=? AND s.data_saida>=CURDATE() ORDER BY s.data_saida LIMIT 30"); $st->execute([$gid]); $saidas = $st->fetchAll();
$pas = [];
if ($saidas) {
    $ids = array_column($saidas, 'id'); $in = implode(',', array_fill(0, count($ids), '?'));
    $x = $conn->prepare("SELECT r.saida_id, r.qtd, u.nome, u.telefone FROM reservas r JOIN usuarios u ON u.id=r.usuario_id WHERE r.status='confirmada' AND r.saida_id IN ($in) ORDER BY u.nome"); $x->execute($ids);
    foreach ($x->fetchAll() as $r) $pas[$r['saida_id']][] = $r;
}
$av = $conn->prepare('SELECT a.nota,a.comentario,a.created_at,p.titulo,u.nome FROM avaliacoes a JOIN passeios p ON p.id=a.passeio_id JOIN usuarios u ON u.id=a.usuario_id WHERE p.guia_id=? AND a.visivel=1 ORDER BY a.created_at DESC LIMIT 6'); $av->execute([$gid]); $av = $av->fetchAll();
$mv = $conn->prepare('SELECT id,titulo,escopo FROM passeios WHERE guia_id=? AND ativo=1 ORDER BY escopo,id'); $mv->execute([$gid]); $mv = $mv->fetchAll();
guiaTopo('Painel do guia', 'index');
?>
<h1 class="h4 mb-1">Olá, <?= e(explode(' ', guiaLogado()['nome'])[0]) ?>! 👋</h1><p class="text-body-secondary">Suas próximas saídas e os passageiros confirmados.</p>
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
  <?php foreach ([['Viagens que conduzo', $k['viagens']], ['Próximas saídas', $k['saidas']], ['Passageiros confirmados', $k['passageiros']], ['Nota média', $k['n_aval'] ? number_format($k['nota'], 1, ',', '') . ' ★ (' . $k['n_aval'] . ')' : '—']] as [$t, $v]): ?>
  <div class="col"><div class="card kpi h-100"><div class="card-body"><div class="small text-body-secondary"><?= e($t) ?></div><div class="h4 mb-0"><?= e($v) ?></div></div></div></div><?php endforeach; ?></div>
<h2 class="h5">Próximas saídas</h2>
<?php if (!$saidas): ?><div class="alert alert-info">Nenhuma saída futura nas suas viagens.</div><?php endif; ?>
<div class="vstack gap-3 mb-4"><?php foreach ($saidas as $s): ?><div class="card"><div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2"><div><strong><?= dataBr($s['data_saida']) ?></strong> · <?= e($s['titulo']) ?><div class="small text-body-secondary"><?= e($s['escopo'] === 'internacional' ? $s['pais'] : $s['destino']) ?></div></div>
  <div class="text-end"><span class="badge text-bg-success"><?= (int)$s['confirmados'] ?> confirmados</span> <?php if ($s['pendentes']): ?><span class="badge text-bg-warning"><?= (int)$s['pendentes'] ?> aguardando pagamento</span><?php endif; ?> <span class="badge text-bg-light border"><?= (int)$s['vagas_disponiveis'] ?>/<?= (int)$s['vagas_total'] ?> vagas livres</span></div></div>
  <?php if (!empty($pas[$s['id']])): ?><details class="mt-2"><summary class="small text-primary" style="cursor:pointer">Ver passageiros confirmados</summary><table class="table table-sm mt-2 mb-0"><tbody><?php foreach ($pas[$s['id']] as $r): ?><tr><td><?= e($r['nome']) ?></td><td><?= (int)$r['qtd'] ?> pessoa(s)</td><td><?= e($r['telefone'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table><div class="form-text">Use estes dados só para a operação da viagem (LGPD).</div></details><?php endif; ?></div></div><?php endforeach; ?></div>
<div class="row g-4"><div class="col-lg-6"><h2 class="h5">Avaliações recentes</h2><?php foreach ($av as $a): ?><div class="border-bottom py-2"><?= estrelas($a['nota']) ?> <strong><?= e($a['nome']) ?></strong> <small class="text-body-secondary">em <?= e($a['titulo']) ?> · <?= dataBr($a['created_at']) ?></small><?php if ($a['comentario']): ?><div class="small"><?= e($a['comentario']) ?></div><?php endif; ?></div><?php endforeach; ?><?php if (!$av): ?><p class="text-body-secondary">Ainda sem avaliações.</p><?php endif; ?></div>
  <div class="col-lg-6"><h2 class="h5">Minhas viagens</h2><div class="d-flex flex-wrap gap-2"><?php foreach ($mv as $v): ?><a class="chip" target="_blank" href="../passeio.php?id=<?= (int)$v['id'] ?>"><?= $v['escopo'] === 'internacional' ? '🌍' : '🇧🇷' ?> <?= e($v['titulo']) ?></a><?php endforeach; ?></div></div></div>
<?php guiaRodape();
