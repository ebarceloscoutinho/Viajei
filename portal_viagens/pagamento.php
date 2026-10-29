<?php
require_once __DIR__ . '/inc/app.php';
exigirLogin(); $uid = usuario()['id']; $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
function carregarReserva(PDO $c, $id, $uid) {
    $st = $c->prepare('SELECT r.*, s.data_saida, p.titulo, p.destino, p.emoji, TIMESTAMPDIFF(MINUTE, NOW(), r.prazo_pagamento) AS min_rest
        FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE r.id=? AND r.usuario_id=?');
    $st->execute([$id, $uid]); return $st->fetch();
}
$r = carregarReserva($conn, $id, $uid);
if (!$r) { flash('Reserva não encontrada.', 'danger'); redirecionar('minhas_reservas.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    $metodo = $_POST['metodo'] ?? ''; $comp = trim($_POST['comprovante'] ?? '');
    if ($r['status'] !== 'aguardando_pagamento' || $r['min_rest'] < 0) { flash('Esta reserva não está mais aguardando pagamento.', 'danger'); redirecionar('minhas_reservas.php'); }
    if (!in_array($metodo, ['pix', 'transferencia'], true) || strlen($comp) < 4) { flash('Escolha a forma de pagamento e informe o código da transação.', 'danger'); redirecionar("pagamento.php?id=$id"); }
    $conn->beginTransaction();
    $conn->prepare('INSERT INTO pagamentos (reserva_id,metodo,valor,comprovante) VALUES (?,?,?,?)')->execute([$id, $metodo, $r['valor_total'], mb_substr($comp, 0, 120)]);
    $conn->prepare("UPDATE reservas SET status='em_analise' WHERE id=?")->execute([$id]);
    $conn->commit();
    flash('Pagamento informado! Sua vaga continua reservada até a confirmação da agência.', 'info'); redirecionar("pagamento.php?id=$id");
}
$st = $conn->prepare('SELECT * FROM pagamentos WHERE reserva_id=? ORDER BY id DESC LIMIT 1'); $st->execute([$id]); $pg = $st->fetch();
topo('Pagamento');
?>
<div class="row justify-content-center"><div class="col-lg-7">
  <h1 class="h3 mb-3">Pagamento da reserva #<?= (int)$r['id'] ?></h1>
  <div class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center">
    <div><?= e($r['emoji']) ?> <strong><?= e($r['titulo']) ?></strong><br><small class="text-body-secondary"><?= e($r['destino']) ?> · saída em <?= dataBr($r['data_saida']) ?> · <?= (int)$r['qtd'] ?> pessoa(s)</small></div>
    <div class="text-end"><div class="h4 text-primary mb-0"><?= brl($r['valor_total']) ?></div><?php if ($r['desconto'] > 0): ?><small class="text-success">cupom −<?= brl($r['desconto']) ?></small><?php endif; ?><?= statusReserva($r['status']) ?></div></div></div>

  <?php if ($r['status'] === 'aguardando_pagamento'): ?>
    <?php if ($pg && $pg['status'] === 'recusado'): ?><div class="alert alert-danger"><strong>Pagamento não confirmado.</strong> <?= e($pg['motivo'] ?: 'Confira os dados e informe novamente.') ?> Você ganhou um novo prazo de <?= PRAZO_PAGAMENTO_HORAS ?> horas.</div><?php endif; ?>
    <div class="alert alert-warning">⏳ Pague em até <strong><?= tempoRestante($r['min_rest']) ?></strong> (até <?= date('d/m H:i', strtotime($r['prazo_pagamento'])) ?>). Sem pagamento nem cancelamento, a reserva expira e você fica <?= BLOQUEIO_HORAS ?>h sem poder reservar outra viagem.</div>
    <div class="card mb-3"><div class="card-body"><h2 class="h6">1) Faça o pagamento de <?= brl($r['valor_total']) ?></h2>
      <p class="mb-1"><strong>PIX</strong> — chave: <code><?= e(PIX_CHAVE) ?></code> (<?= e(PIX_FAVORECIDO) ?>)</p>
      <?php $payload = pixPayload(PIX_CHAVE, PIX_FAVORECIDO, PIX_CIDADE, $r['valor_total'], 'RT' . str_pad($r['id'], 6, '0', STR_PAD_LEFT)); ?>
      <div class="mb-2"><div class="small text-body-secondary">PIX copia e cola (já com o valor exato e o código da reserva):</div><textarea class="form-control form-control-sm font-monospace" id="pixcode" rows="3" readonly><?= e($payload) ?></textarea>
        <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="navigator.clipboard.writeText(document.getElementById('pixcode').value).then(()=>this.textContent='Copiado ✓')">📋 Copiar código PIX</button>
        <div class="text-center my-3"><?= qrSvg($payload, 210, 'QR Code do PIX') ?><div class="small text-body-secondary mt-1">ou aponte a câmera do app do seu banco para o QR Code</div></div></div>
      <p class="mb-0"><strong>Transferência</strong> — <?= e(DADOS_TRANSFERENCIA) ?></p></div></div>
    <div class="card"><div class="card-body"><h2 class="h6">2) Informe que pagou</h2>
      <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $id ?>">
        <select class="form-select" name="metodo" required><option value="pix">PIX</option><option value="transferencia">Transferência bancária</option></select>
        <input class="form-control" name="comprovante" maxlength="120" placeholder="Código/ID da transação (ex.: E0000...) ou nº do comprovante" required>
        <button class="btn btn-accent">Já paguei — avisar a agência</button>
        <div class="form-text">Nunca digite dados de cartão aqui. A agência confere o pagamento e confirma sua reserva.</div></form></div></div>
  <?php elseif ($r['status'] === 'em_analise'): ?>
    <div class="alert alert-info">🔎 Pagamento informado (<?= e(strtoupper($pg['metodo'] ?? '')) ?> · código <?= e($pg['comprovante'] ?? '') ?>). Sua vaga está <strong>reservada</strong> enquanto a agência confirma.</div>
  <?php elseif ($r['status'] === 'confirmada'): ?>
    <div class="alert alert-success">✅ Pagamento confirmado! Boa viagem. Você pode cancelar em <em>Minhas reservas</em> (sujeito à política de cancelamento).</div>
  <?php else: ?><div class="alert alert-secondary">Esta reserva está <?= $r['status'] === 'expirada' ? 'expirada' : 'cancelada' ?>.</div><?php endif; ?>
  <a href="minhas_reservas.php">← Minhas reservas</a>
</div></div>
<?php rodape();
