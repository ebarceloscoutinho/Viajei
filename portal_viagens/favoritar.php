<?php
require_once __DIR__ . '/inc/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar('favoritos.php');
exigirLogin(); csrfVerificar();
$uid = usuario()['id']; $pid = (int)($_POST['passeio_id'] ?? 0);
$st = $conn->prepare('DELETE FROM favoritos WHERE usuario_id=? AND passeio_id=?'); $st->execute([$uid, $pid]);
if (!$st->rowCount()) $conn->prepare('INSERT IGNORE INTO favoritos (usuario_id,passeio_id) VALUES (?,?)')->execute([$uid, $pid]);
redirecionar(($_POST['voltar'] ?? '') === 'favoritos' ? 'favoritos.php' : 'passeio.php?id=' . $pid);
