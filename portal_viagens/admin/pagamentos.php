<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('pagamentos'); $adm = adminLogado()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $id = (int)($_POST['id'] ?? 0); $acao = $_POST['acao'] ?? '';
    try {
        $conn->beginTransaction();
        $st = $conn->prepare("SELECT pg.*, r.status AS rstatus, r.valor_total AS rtotal, p.titulo, p.guia_id, s.data_saida AS dsaida, r.usuario_id AS uid, u.nome AS unome, u.email AS uemail FROM pagamentos pg JOIN reservas r ON r.id=pg.reserva_id JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id JOIN usuarios u ON u.id=r.usuario_id WHERE pg.id=? AND pg.status='informado' FOR UPDATE");
        $st->execute([$id]); $pg = $st->fetch();
        if (!$pg || $pg['rstatus'] !== 'em_analise') throw new Exception('Este pagamento já foi tratado ou a reserva mudou.');
        if ($acao === 'confirmar') {
            $conn->prepare("UPDATE pagamentos SET status='confirmado', decidido_em=NOW(), decidido_por=? WHERE id=?")->execute([$adm, $id]);
            $conn->prepare("UPDATE reservas SET status='confirmada' WHERE id=?")->execute([$pg['reserva_id']]);
            // financeiro: registra a venda e o repasse previsto ao guia
            lancar($conn, FIN_VENDA, 'Venda – ' . $pg['titulo'] . ' (reserva #' . $pg['reserva_id'] . ')', $pg['rtotal'], 1, $pg['reserva_id'], $adm);
            if ($pg['guia_id']) lancar($conn, FIN_GUIA, 'Repasse ao guia (previsto) – reserva #' . $pg['reserva_id'], $pg['rtotal'] * GUIA_PERCENTUAL, 0, $pg['reserva_id'], $adm);
            flash('Pagamento confirmado e reserva garantida.');
            $ev = ['pagamento_confirmado', ['nome' => $pg['unome'], 'titulo' => $pg['titulo'], 'data' => $pg['dsaida'], 'valor' => $pg['rtotal'], 'id' => $pg['reserva_id']]];
        } else {   // recusar: volta a aguardar pagamento com novo prazo
            $motivo = trim($_POST['motivo'] ?? '') ?: 'Não localizamos o pagamento. Confira os dados e informe novamente.';
            $conn->prepare("UPDATE pagamentos SET status='recusado', motivo=?, decidido_em=NOW(), decidido_por=? WHERE id=?")->execute([mb_substr($motivo, 0, 255), $adm, $id]);
            $conn->prepare("UPDATE reservas SET status='aguardando_pagamento', prazo_pagamento=DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id=?")->execute([PRAZO_PAGAMENTO_HORAS, $pg['reserva_id']]);
            flash('Pagamento recusado. O cliente ganhou novo prazo para pagar.', 'warning');
            $ev = ['pagamento_recusado', ['nome' => $pg['unome'], 'titulo' => $pg['titulo'], 'motivo' => $motivo, 'id' => $pg['reserva_id']]];
        }
        $conn->commit();
        if (!empty($ev)) enviarEvento($ev[0], $pg['uemail'], $ev[1], $pg['uid']);
    } catch (Exception $ex) { if ($conn->inTransaction()) $conn->rollBack(); flash($ex->getMessage(), 'danger'); }
    redirecionar('pagamentos.php');
}
$aba = $_GET['aba'] ?? 'analise';
$base = 'SELECT r.id AS rid, r.qtd, r.valor_total, r.prazo_pagamento, TIMESTAMPDIFF(MINUTE, NOW(), r.prazo_pagamento) AS min_rest, u.nome, u.email, u.telefone, u.bloqueado_ate, p.titulo, s.data_saida';
$join = ' FROM reservas r JOIN usuarios u ON u.id=r.usuario_id JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id';
if ($aba === 'analise') $rows = $conn->query($base . ', pg.id AS pgid, pg.metodo, pg.comprovante, pg.created_at AS pago_em' . $join . " JOIN pagamentos pg ON pg.reserva_id=r.id AND pg.status='informado' ORDER BY pg.created_at")->fetchAll();
elseif ($aba === 'aguardando') $rows = $conn->query($base . $join . " WHERE r.status='aguardando_pagamento' ORDER BY r.prazo_pagamento")->fetchAll();
elseif ($aba === 'confirmados') $rows = $conn->query($base . ', pg.metodo, pg.comprovante, pg.decidido_em' . $join . " JOIN pagamentos pg ON pg.reserva_id=r.id AND pg.status='confirmado' ORDER BY pg.decidido_em DESC LIMIT 100")->fetchAll();
else $rows = $conn->query($base . $join . " WHERE r.status='expirada' ORDER BY r.id DESC LIMIT 100")->fetchAll();
$cont = $conn->query("SELECT (SELECT COUNT(*) FROM pagamentos WHERE status='informado') a, (SELECT COUNT(*) FROM reservas WHERE status='aguardando_pagamento') b")->fetch();
admTopo('Pagamentos (admin)', 'pagamentos');
$abas = ['analise' => 'A confirmar (' . $cont['a'] . ')', 'aguardando' => 'Aguardando pagamento (' . $cont['b'] . ')', 'confirmados' => 'Confirmados', 'expirados' => 'Expirados'];
?>
<h1 class="h4 mb-3">Pagamentos</h1>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($abas as $k => $v): ?><a class="chip <?= $aba === $k ? 'ativo' : '' ?>" href="?aba=<?= $k ?>"><?= e($v) ?></a><?php endforeach; ?></div>
<?php if (!$rows): ?><div class="alert alert-info">Nada por aqui.</div><?php endif; ?>
<?php foreach ($rows as $r): ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3 align-items-center">
  <div class="col-md-5"><strong><?= e($r['nome']) ?></strong><br><small class="text-body-secondary"><?= e($r['email']) ?> <?= e($r['telefone']) ?></small>
    <div class="mt-1"><?= e($r['titulo']) ?><br><small>Saída <?= dataBr($r['data_saida']) ?> · <?= (int)$r['qtd'] ?> pessoa(s) · Reserva #<?= (int)$r['rid'] ?></small></div></div>
  <div class="col-md-3"><div class="h5 text-primary mb-0"><?= brl($r['valor_total']) ?></div>
    <?php if ($aba === 'analise'): ?><small><?= e(strtoupper($r['metodo'])) ?> · código <code><?= e($r['comprovante']) ?></code><br>informado em <?= e(date('d/m H:i', strtotime($r['pago_em']))) ?></small>
    <?php elseif ($aba === 'aguardando'): ?><small class="<?= $r['min_rest'] < 180 ? 'text-danger' : '' ?>">restam <?= tempoRestante($r['min_rest']) ?></small>
    <?php elseif ($aba === 'confirmados'): ?><small><?= e(strtoupper($r['metodo'])) ?> · <code><?= e($r['comprovante']) ?></code></small>
    <?php else: ?><small>cliente bloqueado até <?= $r['bloqueado_ate'] ? e(date('d/m H:i', strtotime($r['bloqueado_ate']))) : '—' ?></small><?php endif; ?></div>
  <div class="col-md-4"><?php if ($aba === 'analise'): ?>
    <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$r['pgid'] ?>">
      <input class="form-control form-control-sm" name="motivo" maxlength="255" placeholder="Motivo (se for recusar)">
      <div class="d-flex gap-2"><button class="btn btn-sm btn-success flex-fill" name="acao" value="confirmar" onclick="return confirm('Confirmar que o pagamento caiu na conta?')">✅ Confirmar</button>
      <button class="btn btn-sm btn-outline-danger flex-fill" name="acao" value="recusar">Recusar</button></div></form><?php endif; ?></div>
</div></div></div>
<?php endforeach; admRodape();
