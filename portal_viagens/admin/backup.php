<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('backup');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); set_time_limit(0);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/sql; charset=UTF-8'); header('Content-Disposition: attachment; filename="backup-roteira-' . date('Ymd-His') . '.sql"');
    echo "-- Backup " . SITE_NOME . " — " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $t = str_replace('`', '', $t);
        echo "DROP TABLE IF EXISTS `$t`;\n" . $conn->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1] . ";\n";
        $lote = [];
        foreach ($conn->query("SELECT * FROM `$t`", PDO::FETCH_NUM) as $row) {
            $lote[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $conn->quote((string)$v), $row)) . ')';
            if (count($lote) >= 100) { echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $lote) . ";\n"; $lote = []; }
        }
        if ($lote) echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $lote) . ";\n";
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n"; exit;
}
admTopo('Backup', 'backup');
?>
<div class="card card-body" style="max-width:640px"><h2 class="h5">💾 Backup do banco de dados</h2>
  <p class="text-body-secondary">Baixa um arquivo <code>.sql</code> com <strong>todas as tabelas e dados</strong> (inclui e-mails, hashes de senha e salários dos guias: guarde em local seguro). Para restaurar, importe o arquivo no phpMyAdmin.</p>
  <form method="post"><?= csrfCampo() ?><button class="btn btn-primary">⬇ Gerar e baixar backup</button></form>
  <div class="form-text mt-2">Faça um backup antes de aplicar migrações e antes de atualizações do sistema. Em produção, agende também o backup do servidor.</div></div>
<?php admRodape();
