<?php
// Aplica migrações pendentes:  php cron/migrar.php   (faça backup antes!)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente linha de comando.'); }
define('BASE', ''); require_once __DIR__ . '/../inc/app.php'; require_once __DIR__ . '/../inc/migrar.php';
$r = aplicarMigracoes($conn, __DIR__ . '/../database/migrations');
if (!$r) { echo "Nenhuma migração pendente.\n"; exit(0); }
foreach ($r as $arq => $x) echo $arq . ': ' . (isset($x['ok']) ? 'aplicada' : 'ERRO — ' . $x['erro']) . "\n";
exit(count(array_filter($r, fn($x) => isset($x['erro']))) ? 1 : 0);
