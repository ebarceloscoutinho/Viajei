<?php
// Agende a cada 5–10 min:  */10 * * * * php /caminho/cron/expirar_reservas.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente linha de comando.'); }
define('BASE', ''); require_once __DIR__ . '/../inc/app.php';
expirarReservas($conn); $n = processarFilaEmails(50, true);
echo date('c') . " — expiração verificada; e-mails enviados: $n\n";
