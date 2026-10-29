<?php
require_once __DIR__ . '/inc/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar('passeios.php');
exigirLogin(); csrfVerificar();
$uid = usuario()['id']; $pid = (int)($_POST['passeio_id'] ?? 0); $nota = (int)($_POST['nota'] ?? 0); $com = trim($_POST['comentario'] ?? '');
$st = $conn->prepare("SELECT COUNT(*) FROM reservas r JOIN saidas s ON s.id=r.saida_id WHERE r.usuario_id=? AND s.passeio_id=? AND r.status='confirmada'" . (AVALIACAO_EXIGE_VIAGEM_REALIZADA ? ' AND s.data_saida <= CURDATE()' : ''));
$st->execute([$uid, $pid]);
if (!$st->fetchColumn()) { flash('Só quem tem reserva confirmada' . (AVALIACAO_EXIGE_VIAGEM_REALIZADA ? ' de uma viagem já realizada' : '') . ' pode avaliar.', 'danger'); }
elseif ($nota < 1 || $nota > 5) { flash('Escolha uma nota de 1 a 5.', 'danger'); }
else {
    $conn->prepare('INSERT INTO avaliacoes (usuario_id,passeio_id,nota,comentario) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nota=VALUES(nota), comentario=VALUES(comentario), visivel=1')
         ->execute([$uid, $pid, $nota, $com === '' ? null : mb_substr($com, 0, 1000)]);
    flash('Obrigado pela avaliação!');
}
redirecionar('passeio.php?id=' . $pid . '#avaliacoes');
