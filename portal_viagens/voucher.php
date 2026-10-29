<?php
require_once __DIR__ . '/inc/app.php';
exigirLogin(); $uid = usuario()['id']; $id = (int)($_GET['id'] ?? 0);
$st = $conn->prepare("SELECT r.*, s.data_saida, p.titulo, p.destino, p.pais, p.escopo, p.dias, p.atracoes, p.emoji, p.motivo, p.hue, p.imagem, g.nome AS guia, g.idiomas AS guia_idiomas, u.nome AS cliente, u.email,
  (SELECT MAX(pg.decidido_em) FROM pagamentos pg WHERE pg.reserva_id=r.id AND pg.status='confirmado') AS pago_em
  FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id JOIN usuarios u ON u.id=r.usuario_id LEFT JOIN guias g ON g.id=p.guia_id
  WHERE r.id=? AND r.usuario_id=? AND r.status='confirmada'");
$st->execute([$id, $uid]); $r = $st->fetch();
if (!$r) { flash('Voucher disponível apenas para reservas confirmadas.', 'warning'); redirecionar('minhas_reservas.php'); }
$codigo = 'RT-' . str_pad($r['id'], 6, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(sha1($r['id'] . '|' . $r['usuario_id'] . '|' . $r['created_at']), 0, 4));
topo('Voucher ' . $codigo);
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print"><a href="minhas_reservas.php">← Minhas reservas</a><button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / salvar em PDF</button></div>
<div class="card" style="max-width:760px;margin:auto"><?= capa($r['emoji'], $r['hue'], '', $r['motivo'], $r['imagem'], $r['escopo'] === 'internacional' ? $r['pais'] : 'Brasil') ?>
  <div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2"><div><div class="small text-body-secondary">VOUCHER DE VIAGEM</div><h1 class="h4 mb-0"><?= e($r['titulo']) ?></h1><div class="text-body-secondary"><?= e($r['destino']) ?><?= $r['escopo'] === 'internacional' ? ' · ' . e($r['pais']) : '' ?></div></div>
    <div class="text-end"><div class="small text-body-secondary">Código da reserva</div><div class="h5 font-monospace mb-0"><?= e($codigo) ?></div><div class="mt-2"><?= qrSvg($codigo . ' | ' . dataBr($r['data_saida']), 110, 'QR Code do voucher') ?></div></div></div><hr>
    <div class="row g-3"><div class="col-6 col-md-3"><div class="small text-body-secondary">Passageiro(a)</div><strong><?= e($r['cliente']) ?></strong></div><div class="col-6 col-md-3"><div class="small text-body-secondary">Saída</div><strong><?= dataBr($r['data_saida']) ?></strong></div>
      <div class="col-6 col-md-3"><div class="small text-body-secondary">Pessoas</div><strong><?= (int)$r['qtd'] ?></strong></div><div class="col-6 col-md-3"><div class="small text-body-secondary">Valor pago</div><strong><?= brl($r['valor_total']) ?></strong></div></div>
    <?php if ($r['guia']): ?><div class="mt-3"><div class="small text-body-secondary">Seu guia</div><strong><?= e($r['guia']) ?></strong> <span class="text-body-secondary">· <?= e($r['guia_idiomas']) ?></span></div><?php endif; ?>
    <?php if ($r['atracoes']): ?><div class="mt-3"><div class="small text-body-secondary">Atrações do roteiro</div><?= e(str_replace(';', ' · ', $r['atracoes'])) ?></div><?php endif; ?>
    <hr><div class="small text-body-secondary">Apresente este voucher (impresso ou no celular) ao guia. Cancelamento: sem taxa em até <?= DIAS_ARREPENDIMENTO ?> dias após a compra ou até <?= DIAS_SEM_TAXA ?> dias antes da saída; depois, taxa de <?= (int)(TAXA_CANCELAMENTO * 100) ?>%. <?= $r['escopo'] === 'internacional' ? 'Pacote terrestre: passagem aérea, vistos e documentos são de responsabilidade do viajante. ' : '' ?>Dúvidas: <?= e(config('suporte_telefone')) ?> · <?= e(config('suporte_email')) ?>.
      <?= $r['pago_em'] ? ' Pagamento confirmado em ' . dataBr($r['pago_em']) . '.' : '' ?></div></div></div>
<?php rodape();
