<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php'; require_once __DIR__ . '/../inc/migrar.php';
exigirAdmin('migracoes'); $dir = __DIR__ . '/../database/migrations';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $r = aplicarMigracoes($conn, $dir);
    if (!$r) flash('Nenhuma migração pendente.', 'info');
    else foreach ($r as $arq => $x) { if (isset($x['erro'])) { flash("Falha em $arq: " . $x['erro'], 'danger'); break; } else flash("Migração aplicada: $arq"); }
    redirecionar('migracoes.php');
}
$lista = migracoesStatus($conn, $dir); $pend = count(array_filter($lista, fn($m) => !$m['aplicada_em']));
admTopo('Migrações', 'migracoes');
?>
<p class="text-body-secondary">Migrações evoluem a estrutura do banco <strong>sem apagar dados</strong>. Faça um <a href="backup.php">backup</a> antes de aplicar.</p>
<table class="table table-sm align-middle" style="max-width:720px"><thead><tr><th>Arquivo</th><th>Situação</th></tr></thead><tbody>
<?php foreach ($lista as $m): ?><tr><td><code><?= e($m['arquivo']) ?></code></td><td><?= $m['aplicada_em'] ? '<span class="badge text-bg-success">Aplicada</span> <small class="text-body-secondary">' . e($m['aplicada_em']) . '</small>' : '<span class="badge text-bg-warning">Pendente</span>' ?></td></tr><?php endforeach; ?>
<?php if (!$lista): ?><tr><td colspan="2" class="text-body-secondary">Nenhum arquivo em database/migrations.</td></tr><?php endif; ?></tbody></table>
<form method="post" onsubmit="return confirm('Aplicar as migrações pendentes? Você fez backup?')"><?= csrfCampo() ?><button class="btn btn-primary" <?= $pend ? '' : 'disabled' ?>>Aplicar <?= $pend ?> pendente(s)</button></form>
<?php admRodape();
