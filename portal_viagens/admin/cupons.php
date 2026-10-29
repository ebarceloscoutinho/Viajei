<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('cupons');
$tipos = $conn->query('SELECT codigo,nome FROM tipos_cupom ORDER BY codigo')->fetchAll(PDO::FETCH_KEY_PAIR);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'salvar') {
        $cod = strtoupper(trim($_POST['codigo'] ?? '')); $tipo = $_POST['tipo'] ?? ''; $val = round((float)str_replace(',', '.', $_POST['valor'] ?? 0), 2);
        $vd = $_POST['validade'] ?? ''; $vd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $vd) ? $vd : null; $um = (int)($_POST['usos_max'] ?? 0) ?: null;
        if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $cod) || !isset($tipos[$tipo]) || $val <= 0 || ($tipo === 'percentual' && $val > 100)) flash('Confira: código (3–30 letras/números), tipo e valor (percentual até 100).', 'danger');
        else {
            try {
                if ($id) $conn->prepare('UPDATE cupons SET codigo=?,tipo=?,valor=?,validade=?,usos_max=?,ativo=? WHERE id=?')->execute([$cod, $tipo, $val, $vd, $um, isset($_POST['ativo']) ? 1 : 0, $id]);
                else $conn->prepare('INSERT INTO cupons (codigo,tipo,valor,validade,usos_max,ativo) VALUES (?,?,?,?,?,?)')->execute([$cod, $tipo, $val, $vd, $um, isset($_POST['ativo']) ? 1 : 0]);
                flash('Cupom salvo.');
            } catch (PDOException $e) { flash($e->getCode() === '23000' ? 'Já existe um cupom com esse código.' : 'Erro ao salvar o cupom.', 'danger'); }
        }
    } elseif ($acao === 'ativar') { $conn->prepare('UPDATE cupons SET ativo = 1 - ativo WHERE id=?')->execute([$id]);
    } elseif ($acao === 'excluir') { $conn->prepare('DELETE FROM cupons WHERE id=?')->execute([$id]); flash('Cupom excluído (as reservas que o usaram mantêm o desconto).'); }
    redirecionar('cupons.php');
}
$ed = ['id' => 0, 'codigo' => '', 'tipo' => 'percentual', 'valor' => '', 'validade' => '', 'usos_max' => '', 'ativo' => 1];
if (!empty($_GET['editar'])) { $st = $conn->prepare('SELECT * FROM cupons WHERE id=?'); $st->execute([(int)$_GET['editar']]); $ed = $st->fetch() ?: $ed; }
$lista = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM reservas r WHERE r.cupom_id=c.id AND r.status IN ('aguardando_pagamento','em_analise','confirmada')) AS usos, (SELECT COALESCE(SUM(r.desconto),0) FROM reservas r WHERE r.cupom_id=c.id AND r.status IN ('aguardando_pagamento','em_analise','confirmada')) AS total_desc FROM cupons c ORDER BY c.id DESC")->fetchAll();
admTopo('Cupons', 'cupons');
?>
<div class="row g-4"><div class="col-lg-4"><div class="card card-body"><h2 class="h6"><?= $ed['id'] ? 'Editar cupom' : 'Novo cupom' ?></h2>
  <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>">
    <input class="form-control text-uppercase" name="codigo" value="<?= e($ed['codigo']) ?>" placeholder="CODIGO10" required maxlength="30">
    <div class="row g-2"><div class="col-6"><select class="form-select" name="tipo"><?php foreach ($tipos as $k => $v): ?><option value="<?= e($k) ?>" <?= $ed['tipo'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><input class="form-control" type="number" step="0.01" min="0.01" name="valor" value="<?= e($ed['valor']) ?>" placeholder="Valor" required></div></div>
    <label class="form-label small mb-0">Validade (opcional)</label><input class="form-control" type="date" name="validade" value="<?= e($ed['validade']) ?>">
    <input class="form-control" type="number" min="1" name="usos_max" value="<?= e($ed['usos_max']) ?>" placeholder="Limite de usos (vazio = ilimitado)">
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="ativo" id="a" <?= $ed['ativo'] ? 'checked' : '' ?>><label class="form-check-label" for="a">Ativo</label></div>
    <button class="btn btn-primary">Salvar cupom</button><?php if ($ed['id']): ?><a class="btn btn-link" href="cupons.php">Novo</a><?php endif; ?></form></div></div>
  <div class="col-lg-8"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Código</th><th>Desconto</th><th>Validade</th><th>Usos</th><th>Descontado</th><th></th></tr></thead><tbody>
  <?php foreach ($lista as $c): ?><tr class="<?= $c['ativo'] ? '' : 'opacity-50' ?>"><td><code><?= e($c['codigo']) ?></code></td><td><?= $c['tipo'] === 'percentual' ? rtrim(rtrim(number_format($c['valor'], 2, ',', ''), '0'), ',') . '%' : brl($c['valor']) ?></td><td><?= $c['validade'] ? dataBr($c['validade']) : '—' ?></td><td><?= (int)$c['usos'] ?><?= $c['usos_max'] ? ' / ' . (int)$c['usos_max'] : '' ?></td><td><?= brl($c['total_desc']) ?></td>
    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int)$c['id'] ?>">Editar</a>
      <form method="post" class="d-inline"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-secondary" name="acao" value="ativar"><?= $c['ativo'] ? 'Pausar' : 'Ativar' ?></button><button class="btn btn-sm btn-link text-danger" name="acao" value="excluir" onclick="return confirm('Excluir este cupom?')" aria-label="Excluir">🗑</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php admRodape();
