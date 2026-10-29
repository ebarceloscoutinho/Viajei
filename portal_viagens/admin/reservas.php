<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('reservas');
$f = $_GET['status'] ?? '';
$validos = ['aguardando_pagamento', 'em_analise', 'confirmada', 'cancelada', 'expirada'];
$sql = 'SELECT r.*, u.nome, u.email, u.telefone, s.data_saida, p.titulo FROM reservas r JOIN usuarios u ON u.id=r.usuario_id JOIN saidas s ON s.id=r.saida_id JOIN passeios p ON p.id=s.passeio_id';
$params = [];
if (in_array($f, $validos, true)) { $sql .= ' WHERE r.status=?'; $params[] = $f; }
$cnt = $conn->prepare('SELECT COUNT(*) FROM reservas r' . (in_array($f, $validos, true) ? ' WHERE r.status=?' : '')); $cnt->execute($params); [$off, $pag, $pags] = paginar((int)$cnt->fetchColumn(), 50);
if (isset($_GET['csv'])) {
    $x = $conn->prepare($sql . ' ORDER BY r.id DESC'); $x->execute($params);
    csvSaida('reservas.csv', ['Reserva', 'Cliente', 'E-mail', 'Viagem', 'Saída', 'Pessoas', 'Total', 'Desconto', 'Status', 'Criada em'],
        array_map(fn($r) => [$r['id'], $r['nome'], $r['email'], $r['titulo'], date('d/m/Y', strtotime($r['data_saida'])), $r['qtd'], number_format($r['valor_total'], 2, ',', ''), number_format($r['desconto'], 2, ',', ''), $r['status'], $r['created_at']], $x->fetchAll()));
}
$st = $conn->prepare($sql . ' ORDER BY r.id DESC LIMIT ' . $off . ', 50'); $st->execute($params); $lista = $st->fetchAll();
admTopo('Reservas (admin)', 'reservas');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h4 mb-0">Reservas <a class="btn btn-sm btn-outline-secondary ms-2" href="?<?= e(http_build_query(array_merge($_GET, ['csv' => 1]))) ?>">⬇ CSV</a></h1>
  <div class="d-flex flex-wrap gap-2"><a class="chip <?= $f === '' ? 'ativo' : '' ?>" href="reservas.php">Todas</a>
  <?php foreach ($validos as $v): ?><a class="chip <?= $f === $v ? 'ativo' : '' ?>" href="?status=<?= $v ?>"><?= e(ucfirst(str_replace('_', ' ', $v))) ?></a><?php endforeach; ?></div></div>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>#</th><th>Cliente</th><th>Viagem</th><th>Saída</th><th>Pessoas</th><th>Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($lista as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['nome']) ?><br><small class="text-body-secondary"><?= e($r['email']) ?> <?= e($r['telefone']) ?></small></td><td><?= e($r['titulo']) ?></td><td><?= dataBr($r['data_saida']) ?></td><td><?= (int)$r['qtd'] ?></td><td><?= brl($r['valor_total']) ?></td>
  <td><?= statusReserva($r['status']) ?><?= $r['status'] === 'cancelada' && $r['taxa_cancelamento'] > 0 ? ' <small>taxa ' . brl($r['taxa_cancelamento']) . '</small>' : '' ?></td></tr><?php endforeach; ?>
<?php if (!$lista): ?><tr><td colspan="7" class="text-body-secondary">Nenhuma reserva.</td></tr><?php endif; ?></tbody></table></div>
<?= paginacaoHtml($pag, $pags) ?>
<?php admRodape();
