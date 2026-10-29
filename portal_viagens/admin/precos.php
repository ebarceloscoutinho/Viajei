<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('precos'); $adm = adminLogado()['id'];

function mudarPreco(PDO $c, $id, $antigo, $novo, $adm) {
    if (round($antigo, 2) === round($novo, 2) || $novo < 0) return 0;
    $c->prepare('UPDATE passeios SET preco=? WHERE id=?')->execute([$novo, $id]);
    $c->prepare('INSERT INTO historico_precos (passeio_id,preco_antigo,preco_novo,usuario_id) VALUES (?,?,?,?)')->execute([$id, $antigo, $novo, $adm]);
    return 1;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $n = 0;
    $conn->beginTransaction();
    if (($_POST['acao'] ?? '') === 'salvar') {
        $atuais = $conn->query('SELECT id,preco FROM passeios')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (($_POST['preco'] ?? []) as $id => $v) if (isset($atuais[$id])) $n += mudarPreco($conn, (int)$id, (float)$atuais[$id], round((float)str_replace(',', '.', $v), 2), $adm);
    } else {   // reajuste percentual em lote
        $pct = max(-90, min(300, (float)str_replace(',', '.', $_POST['percentual'] ?? '0'))); $esc = $_POST['escopo'] ?? 'todos';
        $sql = 'SELECT id,preco FROM passeios' . (in_array($esc, ['nacional', 'internacional'], true) ? ' WHERE escopo=' . $conn->quote($esc) : '');
        foreach ($conn->query($sql)->fetchAll() as $p) $n += mudarPreco($conn, $p['id'], (float)$p['preco'], round($p['preco'] * (1 + $pct / 100), 2), $adm);
    }
    $conn->commit(); flash($n . ' preço(s) atualizado(s). Reservas já feitas mantêm o valor original.'); redirecionar('precos.php' . (isset($_POST['volta']) ? '?' . http_build_query(['escopo' => $_POST['volta']]) : ''));
}
$escopo = in_array($_GET['escopo'] ?? '', ['nacional', 'internacional'], true) ? $_GET['escopo'] : ''; $q = trim($_GET['q'] ?? '');
$sql = 'SELECT id,titulo,destino,pais,escopo,dias,preco,ativo FROM passeios WHERE 1=1'; $par = [];
if ($escopo) { $sql .= ' AND escopo=?'; $par[] = $escopo; }
if ($q !== '') { $sql .= ' AND (titulo LIKE ? OR pais LIKE ? OR destino LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%"); }
$st = $conn->prepare($sql . ' ORDER BY escopo, pais, titulo'); $st->execute($par); $lista = $st->fetchAll();
$hist = $conn->query('SELECT h.*, p.titulo, u.nome FROM historico_precos h JOIN passeios p ON p.id=h.passeio_id LEFT JOIN usuarios u ON u.id=h.usuario_id ORDER BY h.id DESC LIMIT 8')->fetchAll();
admTopo('Preços (admin)', 'precos');
?>
<h1 class="h4 mb-3">Regular preços</h1>
<div class="row g-4">
  <div class="col-lg-8">
    <form class="row g-2 mb-3" method="get"><div class="col-5"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Buscar viagem/país"></div>
      <div class="col-4"><select class="form-select form-select-sm" name="escopo"><option value="">Todas</option><option value="nacional" <?= $escopo === 'nacional' ? 'selected' : '' ?>>Nacionais</option><option value="internacional" <?= $escopo === 'internacional' ? 'selected' : '' ?>>Internacionais</option></select></div>
      <div class="col-3"><button class="btn btn-sm btn-outline-primary w-100">Filtrar</button></div></form>
    <form method="post"><?= csrfCampo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="volta" value="<?= e($escopo) ?>">
      <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Viagem</th><th style="width:150px">Preço p/ pessoa (R$)</th></tr></thead><tbody>
      <?php foreach ($lista as $p): ?><tr><td><?= e($p['titulo']) ?> <?= $p['ativo'] ? '' : '<span class="badge text-bg-secondary">inativo</span>' ?><br><small class="text-body-secondary"><?= $p['escopo'] === 'internacional' ? '🌍 ' . e($p['pais']) : '🇧🇷 ' . e($p['destino']) ?> · <?= (int)$p['dias'] ?>d</small></td>
        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="preco[<?= (int)$p['id'] ?>]" value="<?= e($p['preco']) ?>"></td></tr><?php endforeach; ?></tbody></table></div>
      <button class="btn btn-primary">Salvar alterações</button></form>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body"><h2 class="h6">Reajuste em lote</h2>
      <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="reajuste">
        <select class="form-select form-select-sm" name="escopo"><option value="todos">Todas as viagens</option><option value="nacional">Só nacionais</option><option value="internacional">Só internacionais</option></select>
        <div class="input-group input-group-sm"><input class="form-control" type="number" step="0.1" name="percentual" placeholder="Ex.: 10 ou -5" required><span class="input-group-text">%</span></div>
        <button class="btn btn-outline-primary btn-sm" onclick="return confirm('Aplicar o reajuste?')">Aplicar</button></form></div></div>
    <div class="card"><div class="card-body"><h2 class="h6">Últimas alterações</h2>
      <?php foreach ($hist as $h): ?><div class="small border-bottom py-1"><?= e($h['titulo']) ?><br><span class="text-body-secondary"><?= brl($h['preco_antigo']) ?> → <strong><?= brl($h['preco_novo']) ?></strong> · <?= e($h['nome']) ?> · <?= e(date('d/m H:i', strtotime($h['created_at']))) ?></span></div><?php endforeach; ?>
      <?php if (!$hist): ?><span class="small text-body-secondary">Nenhuma alteração ainda.</span><?php endif; ?></div></div>
  </div>
</div>
<?php admRodape();
