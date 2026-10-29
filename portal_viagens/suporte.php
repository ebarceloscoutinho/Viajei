<?php
require_once __DIR__ . '/inc/app.php';
$u = usuario(); $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    $nome = trim($_POST['nome'] ?? ''); $email = trim($_POST['email'] ?? ''); $ass = trim($_POST['assunto'] ?? ''); $msg = trim($_POST['mensagem'] ?? '');
    $_SESSION['chamados'] = array_filter($_SESSION['chamados'] ?? [], fn($t) => $t > time() - 3600);
    if (!empty($_POST['site'])) { /* honeypot: robô */ }
    elseif (count($_SESSION['chamados']) >= 5) flash('Você enviou muitas mensagens seguidas. Tente novamente em instantes.', 'warning');
    elseif ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $ass === '' || mb_strlen($msg) < 10) flash('Preencha nome, e-mail válido, assunto e uma mensagem com pelo menos 10 caracteres.', 'danger');
    else {
        $conn->prepare('INSERT INTO chamados (usuario_id,nome,email,assunto,mensagem) VALUES (?,?,?,?,?)')->execute([$u['id'] ?? null, mb_substr($nome, 0, 120), $email, mb_substr($ass, 0, 150), mb_substr($msg, 0, 3000)]);
        $_SESSION['chamados'][] = time(); flash('Mensagem enviada! Responderemos o mais breve possível.'); redirecionar('suporte.php');
    }
}
$meus = [];
if ($u) { $st = $conn->prepare('SELECT * FROM chamados WHERE usuario_id=? ORDER BY id DESC LIMIT 10'); $st->execute([$u['id']]); $meus = $st->fetchAll(); }
$zap = soDigitos(config('suporte_whatsapp')); if ($zap !== '' && strlen($zap) <= 11) $zap = '55' . $zap;
topo('Suporte');
?>
<h1 class="h3 mb-1">🛟 Suporte</h1><p class="text-body-secondary">Estamos aqui para ajudar com reservas, pagamentos e dúvidas de viagem.</p>
<div class="row g-3 mb-4">
  <div class="col-md-6 col-lg-3"><div class="card card-body contato-card"><span class="ico">📞</span><div><div class="small text-body-secondary">Telefone</div><strong><?= e(config('suporte_telefone')) ?></strong></div></div></div>
  <div class="col-md-6 col-lg-3"><div class="card card-body contato-card"><span class="ico">💬</span><div><div class="small text-body-secondary">WhatsApp</div><?php if ($zap): ?><a class="fw-bold" href="https://wa.me/<?= e($zap) ?>" target="_blank" rel="noopener"><?= e(config('suporte_whatsapp')) ?></a><?php endif; ?></div></div></div>
  <div class="col-md-6 col-lg-3"><div class="card card-body contato-card"><span class="ico">✉️</span><div style="min-width:0"><div class="small text-body-secondary">E-mail (Gmail)</div><a class="fw-bold text-break" href="mailto:<?= e(config('suporte_email')) ?>"><?= e(config('suporte_email')) ?></a></div></div></div>
  <div class="col-md-6 col-lg-3"><div class="card card-body contato-card"><span class="ico">📸</span><div><div class="small text-body-secondary">Instagram</div><strong><?= e(config('suporte_instagram')) ?></strong></div></div></div>
  <div class="col-md-6"><div class="card card-body contato-card"><span class="ico">🕘</span><div><div class="small text-body-secondary">Horário de atendimento</div><strong><?= e(config('suporte_horario')) ?></strong></div></div></div>
  <div class="col-md-6"><div class="card card-body contato-card"><span class="ico">📍</span><div><div class="small text-body-secondary">Endereço</div><strong><?= e(config('suporte_endereco')) ?></strong></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-6"><h2 class="h5">Envie uma mensagem</h2>
    <form method="post" class="card card-body vstack gap-2"><?= csrfCampo() ?>
      <input type="text" name="site" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <input class="form-control" name="nome" placeholder="Seu nome" value="<?= e($u['nome'] ?? '') ?>" required><input class="form-control" type="email" name="email" placeholder="Seu e-mail" required>
      <input class="form-control" name="assunto" placeholder="Assunto" maxlength="150" required><textarea class="form-control" name="mensagem" rows="5" placeholder="Como podemos ajudar?" maxlength="3000" required></textarea>
      <button class="btn btn-primary">Enviar mensagem</button></form>
    <?php if ($meus): ?><h2 class="h6 mt-4">Meus chamados</h2><div class="vstack gap-2"><?php foreach ($meus as $c): ?><div class="card card-body py-2"><div class="d-flex justify-content-between"><strong><?= e($c['assunto']) ?></strong><span class="badge text-bg-<?= $c['status'] === 'respondido' ? 'success' : 'warning' ?>"><?= e($c['status']) ?></span></div>
      <div class="small text-body-secondary"><?= e($c['mensagem']) ?></div><?php if ($c['resposta']): ?><div class="small mt-1 border-top pt-1">💬 <strong>Resposta:</strong> <?= nl2br(e($c['resposta'])) ?></div><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></div>
  <div class="col-lg-6"><h2 class="h5">Perguntas frequentes</h2><div class="accordion faq" id="faq">
    <?php $faq = [
      ['Como funciona o pagamento?', 'Ao reservar, a vaga fica garantida por ' . PRAZO_PAGAMENTO_HORAS . ' horas. Pague por PIX ou transferência, informe o código da transação e aguarde a confirmação da agência.'],
      ['E se eu não pagar a tempo?', 'A reserva expira, as vagas voltam ao sistema e novas reservas ficam bloqueadas por ' . BLOQUEIO_HORAS . ' horas. Você pode cancelar antes, sem custo, se ainda não tiver pago.'],
      ['Posso cancelar uma reserva paga?', 'Sim. Em até ' . DIAS_ARREPENDIMENTO . ' dias após a compra (direito de arrependimento) ou até ' . DIAS_SEM_TAXA . ' dias antes da saída não há taxa; depois disso, cobramos ' . (int)(TAXA_CANCELAMENTO * 100) . '% do valor. O reembolso é tratado pela equipe.'],
      ['O que está incluso nos pacotes internacionais?', 'Roteiro terrestre com guia. A passagem aérea não está inclusa; use o quiz "Quanto custa?" para estimar o custo total da viagem.'],
      ['Como escolho o guia?', 'Cada viagem já traz o guia responsável, com perfil e idiomas. Veja a equipe completa em "Nossos guias".'],
      ['Vocês fazem roteiros sob medida?', 'Fazemos! Envie uma mensagem com destino, datas e número de pessoas.']]; ?>
    <?php foreach ($faq as $i => [$q, $a]): ?><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#f<?= $i ?>"><?= e($q) ?></button></h3>
      <div id="f<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faq"><div class="accordion-body small"><?= e($a) ?></div></div></div><?php endforeach; ?></div></div>
</div>
<?php rodape();
