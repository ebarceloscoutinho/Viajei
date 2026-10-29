<?php
/**
 * Webhook de pagamento: confirma a reserva automaticamente quando um gateway/automação avisa que o PIX foi pago.
 * POST JSON: {"reserva_id": 123, "valor": 189.90, "status": "pago", "referencia": "id-da-transacao"}
 * Cabeçalhos: X-Roteira-Timestamp (segundos Unix) e X-Roteira-Assinatura = hex(HMAC-SHA256(timestamp + "." + corpo, WEBHOOK_SEGREDO)).
 * Veja docs/integracao_pagamento.md. Desativado enquanto WEBHOOK_SEGREDO (inc/app.php) estiver vazio.
 */
define('BASE', ''); require_once __DIR__ . '/../inc/app.php';
header('Content-Type: application/json; charset=utf-8');
function resp($codigo, array $d) { http_response_code($codigo); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(405, ['erro' => 'use POST']);
if (WEBHOOK_SEGREDO === '') resp(503, ['erro' => 'webhook desativado']);
$corpo = file_get_contents('php://input'); $ts = (string)($_SERVER['HTTP_X_ROTEIRA_TIMESTAMP'] ?? ''); $ass = strtolower((string)($_SERVER['HTTP_X_ROTEIRA_ASSINATURA'] ?? ''));
if (!ctype_digit($ts) || abs(time() - (int)$ts) > 300) resp(401, ['erro' => 'timestamp inválido ou expirado']);
if (!hash_equals(webhookAssinatura($corpo, $ts, WEBHOOK_SEGREDO), $ass)) resp(401, ['erro' => 'assinatura inválida']);
$d = json_decode($corpo, true);
if (!is_array($d) || ($d['status'] ?? '') !== 'pago') resp(200, ['ok' => true, 'ignorado' => true]);
$rid = (int)($d['reserva_id'] ?? 0); $valor = (float)($d['valor'] ?? 0);
$ref = mb_substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string)($d['referencia'] ?? '')), 0, 60) ?: 'sem-ref';
$r = null;
try {
    $conn->beginTransaction();
    $st = $conn->prepare('SELECT r.*, p.titulo, p.guia_id, s.data_saida, u.nome AS unome, u.email AS uemail FROM reservas r JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id JOIN usuarios u ON u.id=r.usuario_id WHERE r.id=? FOR UPDATE');
    $st->execute([$rid]); $r = $st->fetch();
    if (!$r) { $conn->rollBack(); resp(404, ['erro' => 'reserva não encontrada']); }
    if ($r['status'] === 'confirmada') { $conn->rollBack(); resp(200, ['ok' => true, 'ja_confirmada' => true]); }   // idempotente
    if (!in_array($r['status'], ['aguardando_pagamento', 'em_analise'], true)) { $conn->rollBack(); resp(409, ['erro' => 'reserva ' . $r['status'] . ': tratar manualmente']); }
    if ($valor + 0.009 < (float)$r['valor_total']) { $conn->rollBack(); resp(422, ['erro' => 'valor menor que o devido']); }
    $conn->prepare("UPDATE pagamentos SET status='recusado', motivo='Substituído por confirmação automática' WHERE reserva_id=? AND status='informado'")->execute([$rid]);
    $conn->prepare("INSERT INTO pagamentos (reserva_id,metodo,valor,comprovante,status,decidido_em) VALUES (?,'pix',?,?,'confirmado',NOW())")->execute([$rid, $r['valor_total'], 'gateway:' . $ref]);
    $conn->prepare("UPDATE reservas SET status='confirmada' WHERE id=?")->execute([$rid]);
    lancar($conn, FIN_VENDA, 'Venda – ' . $r['titulo'] . ' (reserva #' . $rid . ', automático)', $r['valor_total'], 1, $rid);
    if ($r['guia_id']) lancar($conn, FIN_GUIA, 'Repasse ao guia (previsto) – reserva #' . $rid, $r['valor_total'] * GUIA_PERCENTUAL, 0, $rid);
    $conn->commit();
} catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); resp(500, ['erro' => 'falha interna']); }
enviarEvento('pagamento_confirmado', $r['uemail'], ['nome' => $r['unome'], 'titulo' => $r['titulo'], 'data' => $r['data_saida'], 'valor' => $r['valor_total'], 'id' => $rid], $r['usuario_id']);
resp(200, ['ok' => true]);
