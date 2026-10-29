<?php
require_once __DIR__ . '/inc/app.php';
exigirLogin(); $uid = usuario()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $id = (int)($_POST['id'] ?? 0);
    try {
        $conn->beginTransaction();
        if (($_POST['acao'] ?? '') === 'sair_espera') {
            $conn->prepare("UPDATE lista_espera SET status='cancelado' WHERE id=? AND usuario_id=?")->execute([$id, $uid]);
            flash('Você saiu da lista de espera.');
        } else {
            $st = $conn->prepare("SELECT r.*, s.data_saida FROM reservas r JOIN saidas s ON s.id=r.saida_id WHERE r.id=? AND r.usuario_id=? AND r.status IN ('aguardando_pagamento','em_analise','confirmada') FOR UPDATE");
            $st->execute([$id, $uid]); $r = $st->fetch();
            if (!$r) throw new Exception('Reserva não encontrada.');
            $dias = (strtotime($r['data_saida']) - strtotime(date('Y-m-d'))) / 86400;
            $arrependimento = (time() - strtotime($r['created_at'])) / 86400 <= DIAS_ARREPENDIMENTO && $dias >= 1;   // direito de arrependimento
            $taxa = ($r['status'] === 'confirmada' && $dias < DIAS_SEM_TAXA && !$arrependimento) ? round($r['valor_total'] * TAXA_CANCELAMENTO, 2) : 0;
            $conn->prepare("UPDATE reservas SET status='cancelada', taxa_cancelamento=? WHERE id=?")->execute([$taxa, $id]);
            if ($r['status'] === 'confirmada') {   // financeiro: cancela repasse previsto ao guia e prevê o reembolso (valor pago menos a taxa)
                $conn->prepare('DELETE FROM lancamentos WHERE reserva_id=? AND categoria_id=? AND pago=0')->execute([$id, FIN_GUIA]);
                lancar($conn, FIN_REEMBOLSO, 'Reembolso – reserva #' . $id . ($taxa > 0 ? ' (descontada a taxa)' : ''), $r['valor_total'] - $taxa, 0, $id);
            }
            $conn->prepare("UPDATE pagamentos SET status='recusado', motivo='Reserva cancelada pelo cliente' WHERE reserva_id=? AND status='informado'")->execute([$id]);
            $conn->prepare('UPDATE saidas SET vagas_disponiveis = vagas_disponiveis + ? WHERE id=?')->execute([$r['qtd'], $r['saida_id']]);
            notificarFila($conn, $r['saida_id']);
            $msg = 'Reserva cancelada.' . ($taxa > 0 ? ' Taxa de cancelamento: ' . brl($taxa) . '.' : ' Sem taxa.');
            if (in_array($r['status'], ['em_analise', 'confirmada'], true)) $msg .= ' Se você já pagou, a agência entrará em contato sobre o reembolso.';
            flash($msg, $taxa > 0 ? 'warning' : 'success');
        }
        $conn->commit();
    } catch (Exception $ex) { if ($conn->inTransaction()) $conn->rollBack(); flash($ex->getMessage(), 'danger'); }
    redirecionar('minhas_reservas.php');
}
$st = $conn->prepare('SELECT r.*, s.data_saida, p.id AS pid, p.titulo, p.destino, p.emoji, TIMESTAMPDIFF(MINUTE, NOW(), r.prazo_pagamento) AS min_rest
    FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE r.usuario_id=? ORDER BY r.id DESC');
$st->execute([$uid]); $reservas = $st->fetchAll();
$st = $conn->prepare("SELECT w.*, s.data_saida, p.id AS pid, p.titulo FROM lista_espera w JOIN saidas s ON s.id=w.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE w.usuario_id=? AND w.status<>'cancelado' ORDER BY w.id DESC");
$st->execute([$uid]); $espera = $st->fetchAll();
$bl = $conn->prepare('SELECT bloqueado_ate FROM usuarios WHERE id=? AND bloqueado_ate > NOW()'); $bl->execute([$uid]); $bloq = $bl->fetchColumn();
topo('Minhas reservas');
?>
<h1 class="h3 mb-4">Minhas reservas</h1>
<?php if ($bloq): ?><div class="alert alert-danger">🚫 Novas reservas bloqueadas até <strong><?= date('d/m/Y H:i', strtotime($bloq)) ?></strong>, pois uma reserva anterior expirou sem pagamento.</div><?php endif; ?>
<?php if (!$reservas): ?><div class="alert alert-info">Você ainda não tem reservas. <a href="passeios.php">Explore as viagens</a>.</div><?php else: ?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Viagem</th><th>Saída</th><th>Pessoas</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($reservas as $r): $ativa = in_array($r['status'], ['aguardando_pagamento', 'em_analise', 'confirmada'], true); ?><tr>
  <td><?= e($r['emoji']) ?> <a href="passeio.php?id=<?= (int)$r['pid'] ?>"><?= e($r['titulo']) ?></a><br><small class="text-body-secondary"><?= e($r['destino']) ?></small></td>
  <td><?= dataBr($r['data_saida']) ?></td><td><?= (int)$r['qtd'] ?></td><td><?= brl($r['valor_total']) ?><?= $r['desconto'] > 0 ? '<br><small class="text-success">cupom −' . brl($r['desconto']) . '</small>' : '' ?></td>
  <td><?= statusReserva($r['status']) ?>
    <?= $r['status'] === 'aguardando_pagamento' ? '<br><small class="text-danger">restam ' . tempoRestante($r['min_rest']) . '</small>' : '' ?>
    <?= $r['status'] === 'cancelada' && $r['taxa_cancelamento'] > 0 ? '<br><small>Taxa ' . brl($r['taxa_cancelamento']) . '</small>' : '' ?></td>
  <td class="text-nowrap">
    <?php if ($r['status'] === 'aguardando_pagamento'): ?><a class="btn btn-sm btn-accent" href="pagamento.php?id=<?= (int)$r['id'] ?>">Pagar</a><?php elseif ($r['status'] === 'confirmada'): ?><a class="btn btn-sm btn-outline-success" href="voucher.php?id=<?= (int)$r['id'] ?>">Voucher</a><?php elseif ($r['status'] === 'em_analise'): ?><a class="btn btn-sm btn-outline-primary" href="pagamento.php?id=<?= (int)$r['id'] ?>">Detalhes</a><?php endif; ?>
    <?php if ($ativa && $r['data_saida'] >= date('Y-m-d')): ?><form method="post" class="d-inline" onsubmit="return confirm('Cancelar esta reserva?')"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Cancelar</button></form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if ($espera): ?><h2 class="h5 mt-5">Lista de espera</h2><ul class="list-group">
<?php foreach ($espera as $w): ?><li class="list-group-item d-flex justify-content-between align-items-center">
  <span><a href="passeio.php?id=<?= (int)$w['pid'] ?>"><?= e($w['titulo']) ?></a> — <?= dataBr($w['data_saida']) ?>
  <?= $w['status'] === 'notificado' ? '<span class="badge text-bg-warning ms-2">Vaga liberada! Reserve agora</span>' : '<span class="badge text-bg-secondary ms-2">Aguardando</span>' ?></span>
  <form method="post"><?= csrfCampo() ?><input type="hidden" name="acao" value="sair_espera"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn btn-sm btn-link text-danger">Sair da fila</button></form></li><?php endforeach; ?></ul><?php endif;
rodape();
