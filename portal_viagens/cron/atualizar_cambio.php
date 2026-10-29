<?php
// Agende 1x/dia:  0 8 * * * php /caminho/cron/atualizar_cambio.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente linha de comando.'); }
define('BASE', ''); require_once __DIR__ . '/../inc/app.php'; require_once __DIR__ . '/../inc/cambio.php';
try { echo date('c') . ' — cotações atualizadas: ' . atualizarCotacoes($conn) . "\n"; } catch (Exception $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
