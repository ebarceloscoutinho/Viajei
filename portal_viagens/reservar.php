<?php
require_once __DIR__ . '/inc/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar('passeios.php');
exigirLogin(); csrfVerificar();
$uid = usuario()['id']; $saidaId = (int)($_POST['saida_id'] ?? 0); $qtd = max(1, min(10, (int)($_POST['qtd'] ?? 1)));
$acao = $_POST['acao'] ?? 'reservar'; $passeioId = (int)($_POST['passeio_id'] ?? 0);
try {
    $conn->beginTransaction();
    if ($acao !== 'espera') {   // usuário com reserva expirada sem pagamento fica bloqueado temporariamente
        $b = $conn->prepare('SELECT bloqueado_ate FROM usuarios WHERE id=? AND bloqueado_ate > NOW()'); $b->execute([$uid]);
        if ($x = $b->fetchColumn()) throw new Exception('Novas reservas bloqueadas até ' . date('d/m/Y H:i', strtotime($x)) . ': uma reserva anterior expirou sem pagamento.');
    }
    if ($acao !== 'espera') {   // e-mail precisa estar confirmado para reservar
        $vv = $conn->prepare('SELECT email_verificado_em FROM usuarios WHERE id=?'); $vv->execute([$uid]);
        if (!$vv->fetchColumn()) throw new Exception('Confirme seu e-mail antes de reservar: enviamos um link para a sua caixa de entrada (em "Minha conta" você pode reenviar).');
    }
    $st = $conn->prepare('SELECT s.*, p.preco, p.titulo FROM saidas s JOIN passeios p ON p.id=s.passeio_id WHERE s.id=? AND s.data_saida>=CURDATE() FOR UPDATE');
    $st->execute([$saidaId]); $s = $st->fetch();
    if (!$s) throw new Exception('Saída não encontrada ou já realizada.');

    if ($acao === 'espera') {
        $ja = $conn->prepare("SELECT 1 FROM lista_espera WHERE saida_id=? AND usuario_id=? AND status IN ('aguardando','notificado')");
        $ja->execute([$saidaId, $uid]);
        if ($ja->fetch()) throw new Exception('Você já está na lista de espera desta saída.');
        $conn->prepare('INSERT INTO lista_espera (saida_id,usuario_id) VALUES (?,?)')->execute([$saidaId, $uid]);
        $conn->commit(); flash('Você entrou na lista de espera. Avisaremos por aqui e por e-mail se uma vaga abrir.'); redirecionar('minhas_reservas.php');
    }
    if ($s['vagas_disponiveis'] < $qtd) throw new Exception('Vagas insuficientes (restam ' . (int)$s['vagas_disponiveis'] . '). Entre na lista de espera.');
    $total = round($s['preco'] * $qtd, 2); $desc = 0; $cupomId = null; $cod = trim($_POST['cupom'] ?? '');
    if ($cod !== '') {
        $cp = cupomValido($conn, $cod);
        if (!$cp) throw new Exception('Cupom inválido, vencido ou esgotado.');
        $desc = descontoCupom($cp, $total); $cupomId = (int)$cp['id'];
    }
    $conn->prepare("INSERT INTO reservas (usuario_id,saida_id,qtd,valor_total,status,prazo_pagamento,cupom_id,desconto) VALUES (?,?,?,?, 'aguardando_pagamento', DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?)")
         ->execute([$uid, $saidaId, $qtd, $total - $desc, PRAZO_PAGAMENTO_HORAS, $cupomId, $desc]);
    $rid = (int)$conn->lastInsertId();
    $conn->prepare('UPDATE saidas SET vagas_disponiveis = vagas_disponiveis - ? WHERE id=?')->execute([$qtd, $saidaId]);
    $pz = $conn->prepare("SELECT DATE_FORMAT(prazo_pagamento, '%d/%m/%Y às %H:%i') FROM reservas WHERE id=?"); $pz->execute([$rid]); $prazo = $pz->fetchColumn();
    $conn->commit();
    $us = $conn->prepare('SELECT nome,email FROM usuarios WHERE id=?'); $us->execute([$uid]); $us = $us->fetch();
    enviarEvento('reserva_criada', $us['email'], ['nome' => $us['nome'], 'id' => $rid, 'titulo' => $s['titulo'], 'data' => $s['data_saida'], 'qtd' => $qtd, 'valor' => $total - $desc, 'desconto' => $desc, 'prazo' => $prazo], $uid);
    flash('Vaga reservada por ' . PRAZO_PAGAMENTO_HORAS . ' horas' . ($desc > 0 ? ' (cupom aplicado: −' . brl($desc) . ')' : '') . '! Faça o pagamento para confirmar.', 'warning');
    redirecionar('pagamento.php?id=' . $rid);
} catch (Exception $ex) {
    if ($conn->inTransaction()) $conn->rollBack();
    flash($ex->getMessage(), 'danger'); redirecionar('passeio.php?id=' . $passeioId);
}
