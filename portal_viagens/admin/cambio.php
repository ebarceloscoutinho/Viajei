<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('cambio');
require_once __DIR__ . '/../inc/cambio.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    if (($_POST['acao'] ?? '') === 'auto') {
        try { $n = atualizarCotacoes($conn); flash($n . ' cotações atualizadas pela internet.'); } catch (Exception $e) { flash($e->getMessage(), 'danger'); }
    } elseif (($_POST['acao'] ?? '') === 'novo_pais') {
        $n = fn($k) => max(0, (float)str_replace(',', '.', $_POST[$k] ?? 0));
        $nome = trim($_POST['nome'] ?? ''); $ex = $conn->prepare('SELECT 1 FROM paises WHERE nome=?'); $ex->execute([$nome]);
        $mo = $conn->prepare('SELECT 1 FROM moedas WHERE codigo=?'); $mo->execute([$_POST['moeda'] ?? '']);
        if ($nome === '' || $ex->fetch() || !$mo->fetch()) flash('Informe um nome ainda não cadastrado e uma moeda válida.', 'danger');
        else {
            $conn->prepare('INSERT INTO paises (nome,moeda,hospedagem_dia,alimentacao_dia,transporte_dia,lazer_dia,seguro_dia_brl,passagem_brl,visto,visto_obs) VALUES (?,?,?,?,?,?,?,?,?,?)')
                 ->execute([$nome, $_POST['moeda'], $n('h'), $n('a'), $n('t'), $n('l'), $n('s'), $n('v'), $n('vi'), mb_substr(trim($_POST['obs'] ?? ''), 0, 160) ?: null]);
            flash('País adicionado ao quiz. Use o mesmo nome no campo "País" ao cadastrar as viagens dele.');
        }
    } elseif (($_POST['acao'] ?? '') === 'moedas') {
        $u = $conn->prepare('UPDATE moedas SET cotacao_brl=? WHERE codigo=? AND cotacao_brl<>?');
        foreach (($_POST['cot'] ?? []) as $cod => $v) { $v = (float)str_replace(',', '.', $v); if ($v > 0) $u->execute([$v, $cod, $v]); }
        flash('Cotações atualizadas. O quiz de custo já usa os novos valores.');
    } else {
        $u = $conn->prepare('UPDATE paises SET hospedagem_dia=?, alimentacao_dia=?, transporte_dia=?, lazer_dia=?, seguro_dia_brl=?, passagem_brl=?, visto=?, visto_obs=? WHERE id=?');
        foreach (($_POST['p'] ?? []) as $id => $r) {
            $n = fn($k) => max(0, (float)str_replace(',', '.', $r[$k] ?? 0));
            $u->execute([$n('h'), $n('a'), $n('t'), $n('l'), $n('s'), $n('v'), $n('vi'), mb_substr(trim($r['obs'] ?? ''), 0, 160) ?: null, (int)$id]);
        }
        flash('Custos de referência atualizados.');
    }
    redirecionar('cambio.php');
}
$moedas = $conn->query("SELECT * FROM moedas WHERE codigo<>'BRL' ORDER BY nome")->fetchAll();
$paises = $conn->query('SELECT p.*, m.simbolo FROM paises p JOIN moedas m ON m.codigo=p.moeda ORDER BY p.nome="Brasil" DESC, p.nome')->fetchAll();
admTopo('Câmbio e custos', 'cambio');
?>
<p class="text-body-secondary">Base do quiz <strong>"Quanto custa a viagem?"</strong>. As cotações são <u>valores de exemplo</u>: atualize manualmente com frequência. Custos de hospedagem, alimentação, transporte, lazer e visto ficam na <strong>moeda local</strong> — quando a cotação muda, o orçamento em reais muda junto.</p>
<form method="post" class="mb-3"><?= csrfCampo() ?><input type="hidden" name="acao" value="auto"><button class="btn btn-outline-primary">🔄 Atualizar cotações pela internet</button> <span class="form-text">Fonte: ExchangeRate-API (exchangerate-api.com), atualização diária. Você ainda pode ajustar manualmente.</span></form>
<form method="post" class="card mb-4"><?= csrfCampo() ?><input type="hidden" name="acao" value="moedas"><div class="card-body"><h2 class="h6">Cotações (1 unidade da moeda = quantos reais)</h2>
  <div class="row g-2"><?php foreach ($moedas as $m): ?><div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-0"><?= e($m['codigo']) ?> · <?= e($m['nome']) ?></label><input class="form-control form-control-sm" type="number" step="0.00000001" min="0" name="cot[<?= e($m['codigo']) ?>]" value="<?= e(rtrim(rtrim($m['cotacao_brl'], '0'), '.')) ?>"></div><?php endforeach; ?></div>
  <button class="btn btn-primary mt-3">Salvar cotações</button></div></form>
<details class="card mb-4"><summary class="card-body fw-semibold" style="cursor:pointer">➕ Cadastrar país novo no quiz</summary><div class="card-body pt-0">
  <form method="post" class="row g-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="novo_pais">
    <div class="col-12 col-md-4"><input class="form-control form-control-sm" name="nome" placeholder="Nome do país (igual ao das viagens)" required></div>
    <div class="col-6 col-md-2"><select class="form-select form-select-sm" name="moeda"><?php foreach ($conn->query('SELECT codigo FROM moedas ORDER BY codigo')->fetchAll(PDO::FETCH_COLUMN) as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
    <?php foreach (['h' => 'Quarto/noite', 'a' => 'Comida/dia', 't' => 'Transp./dia', 'l' => 'Lazer/dia', 's' => 'Seguro/dia R$', 'v' => 'Passagem R$', 'vi' => 'Visto'] as $k => $r): ?><div class="col-6 col-md-2"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="<?= $k ?>" placeholder="<?= $r ?>" required></div><?php endforeach; ?>
    <div class="col-12 col-md-4"><input class="form-control form-control-sm" name="obs" maxlength="160" placeholder="Observação sobre visto/documentos"></div>
    <div class="col-12"><button class="btn btn-sm btn-primary">Adicionar país</button> <span class="form-text">A moeda precisa existir na lista de cotações acima.</span></div></form></div></details>
<form method="post" class="card"><?= csrfCampo() ?><input type="hidden" name="acao" value="paises"><div class="card-body"><h2 class="h6">Custos de referência por país (padrão econômico)</h2>
  <div class="table-responsive"><table class="table table-sm align-middle" style="min-width:900px"><thead><tr><th>País</th><th>Quarto/noite</th><th>Comida/dia</th><th>Transp./dia</th><th>Lazer/dia</th><th>Seguro/dia R$</th><th>Passagem R$</th><th>Visto</th><th>Observação</th></tr></thead><tbody>
  <?php foreach ($paises as $p): $i = (int)$p['id']; ?><tr><td><strong><?= e($p['nome']) ?></strong><br><small class="text-body-secondary"><?= e($p['moeda']) ?></small></td>
    <?php foreach (['h' => 'hospedagem_dia', 'a' => 'alimentacao_dia', 't' => 'transporte_dia', 'l' => 'lazer_dia', 's' => 'seguro_dia_brl', 'v' => 'passagem_brl', 'vi' => 'visto'] as $k => $c): ?>
      <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="p[<?= $i ?>][<?= $k ?>]" value="<?= e($p[$c] + 0) ?>" style="min-width:90px"></td><?php endforeach; ?>
    <td><input class="form-control form-control-sm" name="p[<?= $i ?>][obs]" maxlength="160" value="<?= e($p['visto_obs']) ?>" style="min-width:220px"></td></tr><?php endforeach; ?></tbody></table></div>
  <button class="btn btn-primary">Salvar custos</button></div></form>
<?php admRodape();
