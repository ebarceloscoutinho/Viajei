<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/graficos.php';
exigirAdmin('financeiro'); $adm = adminLogado()['id'];
$per = (int)($_GET['per'] ?? 6); if (!in_array($per, [3, 6, 12], true)) $per = 6;
$fc = (int)($_GET['cat'] ?? 0); $fs = $_GET['st'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'novo') {
        $cat = (int)($_POST['categoria_id'] ?? 0); $v = round((float)str_replace(',', '.', $_POST['valor'] ?? 0), 2); $d = $_POST['data_lanc'] ?? '';
        $ok = $conn->prepare('SELECT 1 FROM categorias_financeiras WHERE id=?'); $ok->execute([$cat]);
        if (!$ok->fetch() || $v <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || trim($_POST['descricao'] ?? '') === '') flash('Preencha data, categoria, descrição e um valor maior que zero.', 'danger');
        else { $conn->prepare('INSERT INTO lancamentos (data_lanc,categoria_id,descricao,valor,pago,criado_por) VALUES (?,?,?,?,?,?)')->execute([$d, $cat, mb_substr(trim($_POST['descricao']), 0, 200), $v, isset($_POST['pago']) ? 1 : 0, $adm]); flash('Lançamento registrado.'); }
    } elseif ($acao === 'pagar') { $conn->prepare('UPDATE lancamentos SET pago=1, data_lanc=CURDATE() WHERE id=?')->execute([$id]); flash('Marcado como realizado (pago).');
    } elseif ($acao === 'excluir') { $conn->prepare('DELETE FROM lancamentos WHERE id=? AND (reserva_id IS NULL OR pago=0)')->execute([$id]); flash('Lançamento excluído.'); }
    redirecionar('financeiro.php?per=' . $per);
}
$ini = date('Y-m-01', strtotime('-' . ($per - 1) . ' months'));
$cats = $conn->query('SELECT c.*, g.nome AS grupo_nome FROM categorias_financeiras c JOIN grupos_financeiros g ON g.codigo=c.grupo ORDER BY c.natureza DESC, c.id')->fetchAll();

$G = ['receita' => 0, 'deducao' => 0, 'custo_direto' => 0, 'operacional' => 0];
$st = $conn->prepare('SELECT c.grupo, SUM(l.valor) v FROM lancamentos l JOIN categorias_financeiras c ON c.id=l.categoria_id WHERE l.pago=1 AND l.data_lanc>=? GROUP BY c.grupo'); $st->execute([$ini]);
foreach ($st->fetchAll() as $r) $G[$r['grupo']] = (float)$r['v'];
$receita = $G['receita']; $despTotal = $G['deducao'] + $G['custo_direto'] + $G['operacional'];
$lucroBruto = $receita - $G['deducao'] - $G['custo_direto']; $lucroLiq = $lucroBruto - $G['operacional'];
$margem = $receita > 0 ? $lucroLiq / $receita * 100 : 0;
$st = $conn->prepare("SELECT SUM(l.valor) FROM lancamentos l JOIN categorias_financeiras c ON c.id=l.categoria_id WHERE l.pago=0 AND c.natureza='despesa'"); $st->execute(); $aPagar = (float)$st->fetchColumn();

// série mensal
$mm = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']; $meses = [];
for ($i = 0; $i < $per; $i++) { $t = strtotime($ini . " +$i months"); $meses[date('Y-m', $t)] = ['rot' => $mm[(int)date('n', $t) - 1] . '/' . date('y', $t), 'receita' => 0, 'despesa' => 0, 'lucro' => 0]; }
$st = $conn->prepare("SELECT DATE_FORMAT(l.data_lanc,'%Y-%m') ym, c.grupo, SUM(l.valor) v FROM lancamentos l JOIN categorias_financeiras c ON c.id=l.categoria_id WHERE l.pago=1 AND l.data_lanc>=? GROUP BY ym, c.grupo"); $st->execute([$ini]);
foreach ($st->fetchAll() as $r) if (isset($meses[$r['ym']])) $meses[$r['ym']][$r['grupo'] === 'receita' ? 'receita' : 'despesa'] += (float)$r['v'];
foreach ($meses as &$m) $m['lucro'] = $m['receita'] - $m['despesa']; unset($m);

// ganhos e perdas por categoria
$st = $conn->prepare('SELECT c.nome, c.natureza, g.nome AS grupo, SUM(l.valor) v, COUNT(*) n FROM lancamentos l JOIN categorias_financeiras c ON c.id=l.categoria_id JOIN grupos_financeiros g ON g.codigo=c.grupo WHERE l.pago=1 AND l.data_lanc>=? GROUP BY c.id ORDER BY c.natureza="receita" DESC, v DESC'); $st->execute([$ini]);
$porCat = $st->fetchAll(); $rosca = [];
foreach ($porCat as $r) if ($r['natureza'] === 'despesa') $rosca[$r['nome']] = (float)$r['v'];

// lançamentos (tabela)
$sql = 'SELECT l.*, c.nome AS cat, c.natureza FROM lancamentos l JOIN categorias_financeiras c ON c.id=l.categoria_id WHERE l.data_lanc>=?'; $par = [$ini];
if ($fc) { $sql .= ' AND l.categoria_id=?'; $par[] = $fc; }
if ($fs === 'previsto') $sql .= ' AND l.pago=0'; elseif ($fs === 'realizado') $sql .= ' AND l.pago=1';
if (isset($_GET['csv'])) {
    $x = $conn->prepare($sql . ' ORDER BY l.data_lanc, l.id'); $x->execute($par);
    csvSaida('lancamentos.csv', ['Data', 'Categoria', 'Natureza', 'Descrição', 'Valor', 'Realizado'],
        array_map(fn($l) => [date('d/m/Y', strtotime($l['data_lanc'])), $l['cat'], $l['natureza'], $l['descricao'], number_format($l['valor'], 2, ',', ''), $l['pago'] ? 'sim' : 'não'], $x->fetchAll()));
}
$st = $conn->prepare($sql . ' ORDER BY l.data_lanc DESC, l.id DESC LIMIT 150'); $st->execute($par); $lanc = $st->fetchAll();
$sg = fn($v) => ($v < 0 ? '−' : '') . brl(abs($v));
admTopo('Financeiro', 'financeiro');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h4 mb-0">Resultado financeiro</h1>
  <div class="d-flex gap-2"><?php foreach ([3, 6, 12] as $p): ?><a class="chip <?= $per === $p ? 'ativo' : '' ?>" href="?per=<?= $p ?>">Últimos <?= $p ?> meses</a><?php endforeach; ?></div></div>

<div class="row row-cols-2 row-cols-xl-5 g-3 mb-4">
  <div class="col"><div class="card kpi pos h-100"><div class="card-body"><div class="small text-body-secondary">Receita bruta</div><div class="h5 mb-0"><?= brl($receita) ?></div></div></div></div>
  <div class="col"><div class="card kpi h-100"><div class="card-body"><div class="small text-body-secondary">Lucro bruto</div><div class="h5 mb-0"><?= $sg($lucroBruto) ?></div><small class="text-body-secondary">após reembolsos e custos diretos</small></div></div></div>
  <div class="col"><div class="card kpi neg h-100"><div class="card-body"><div class="small text-body-secondary">Despesas totais</div><div class="h5 mb-0"><?= brl($despTotal) ?></div></div></div></div>
  <div class="col"><div class="card kpi <?= $lucroLiq >= 0 ? 'pos' : 'neg' ?> h-100"><div class="card-body"><div class="small text-body-secondary">Lucro líquido</div><div class="h5 mb-0 <?= $lucroLiq >= 0 ? 'text-success' : 'text-danger' ?>"><?= $sg($lucroLiq) ?></div><small class="text-body-secondary">margem <?= number_format($margem, 1, ',', '') ?>%</small></div></div></div>
  <div class="col"><div class="card kpi h-100"><div class="card-body"><div class="small text-body-secondary">A pagar (previsto)</div><div class="h5 mb-0"><?= brl($aPagar) ?></div></div></div></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body"><h2 class="h6">Receitas, despesas e lucro líquido por mês</h2><?= graficoBarras(array_values($meses)) ?>
    <div class="d-flex gap-3 small justify-content-center flex-wrap"><span><span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:#16a34a"></span>Receitas</span><span><span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:#dc2626"></span>Despesas</span><span><span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:#2563eb"></span>Lucro líquido</span></div></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body text-center"><h2 class="h6">Despesas por categoria</h2><?= graficoRosca($rosca, brl($despTotal), 'em despesas') ?>
    <ul class="list-unstyled small text-start mt-2 mb-0"><?php $i = 0; foreach ($rosca as $n => $v): ?><li class="d-flex justify-content-between"><span><span class="d-inline-block rounded-circle me-1" style="width:9px;height:9px;background:<?= corGrafico($i++) ?>"></span><?= e($n) ?></span><span><?= brl($v) ?></span></li><?php endforeach; ?></ul></div></div></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-5"><div class="card h-100"><div class="card-body"><h2 class="h6">Demonstrativo do período</h2>
    <table class="table table-sm mb-0"><tbody>
      <tr><td>Receita bruta</td><td class="text-end"><?= brl($receita) ?></td></tr>
      <tr><td class="text-body-secondary">(−) Reembolsos</td><td class="text-end text-danger"><?= brl($G['deducao']) ?></td></tr>
      <tr><td class="text-body-secondary">(−) Custos diretos (guias, fornecedores)</td><td class="text-end text-danger"><?= brl($G['custo_direto']) ?></td></tr>
      <tr class="fw-bold"><td>= Lucro bruto</td><td class="text-end"><?= $sg($lucroBruto) ?></td></tr>
      <tr><td class="text-body-secondary">(−) Despesas operacionais</td><td class="text-end text-danger"><?= brl($G['operacional']) ?></td></tr>
      <tr class="fw-bold"><td>= Lucro líquido</td><td class="text-end <?= $lucroLiq >= 0 ? 'text-success' : 'text-danger' ?>"><?= $sg($lucroLiq) ?></td></tr></tbody></table></div></div></div>
  <div class="col-lg-7"><div class="card h-100"><div class="card-body"><h2 class="h6">Ganhos e perdas por categoria</h2>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Categoria</th><th>Grupo</th><th class="text-end">Lanç.</th><th class="text-end">Valor</th></tr></thead><tbody>
    <?php foreach ($porCat as $r): $rec = $r['natureza'] === 'receita'; ?><tr><td><?= e($r['nome']) ?></td><td class="small text-body-secondary"><?= e($r['grupo']) ?></td><td class="text-end"><?= (int)$r['n'] ?></td><td class="text-end fw-semibold <?= $rec ? 'text-success' : 'text-danger' ?>"><?= $rec ? '+' : '−' ?><?= brl($r['v']) ?></td></tr><?php endforeach; ?>
    <?php if (!$porCat): ?><tr><td colspan="4" class="text-body-secondary">Sem lançamentos no período.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
</div>

<div class="card mb-4"><div class="card-body"><h2 class="h6">Novo lançamento</h2>
  <form method="post" class="row g-2 align-items-end"><?= csrfCampo() ?><input type="hidden" name="acao" value="novo">
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Data</label><input class="form-control form-control-sm" type="date" name="data_lanc" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Categoria</label><select class="form-select form-select-sm" name="categoria_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $c['natureza'] === 'receita' ? '＋' : '－' ?> <?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 col-md-3"><label class="form-label small mb-0">Descrição</label><input class="form-control form-control-sm" name="descricao" maxlength="200" placeholder="Ex.: Pagamento do guia – Bonito" required></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Valor (R$)</label><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" name="valor" required></div>
    <div class="col-6 col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="pago" id="pago" checked><label class="form-check-label small" for="pago">Já realizado</label></div><button class="btn btn-sm btn-primary w-100 mt-1">Adicionar</button></div></form>
  <div class="form-text">Confirmações de pagamento e cancelamentos geram lançamentos automaticamente. O repasse ao guia entra como previsto (<?= (int)(GUIA_PERCENTUAL * 100) ?>% da venda) até você marcar como pago.</div></div></div>

<div class="card"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h2 class="h6 mb-0">Lançamentos <a class="btn btn-sm btn-outline-secondary ms-1" href="?<?= e(http_build_query(array_merge($_GET, ['csv' => 1, 'per' => $per]))) ?>">⬇ CSV</a></h2>
  <form class="d-flex gap-2" method="get"><input type="hidden" name="per" value="<?= $per ?>"><select class="form-select form-select-sm" name="cat"><option value="0">Todas as categorias</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fc === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
    <select class="form-select form-select-sm" name="st"><option value="">Todos</option><option value="realizado" <?= $fs === 'realizado' ? 'selected' : '' ?>>Realizados</option><option value="previsto" <?= $fs === 'previsto' ? 'selected' : '' ?>>Previstos</option></select><button class="btn btn-sm btn-outline-primary">OK</button></form></div>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th class="text-end">Valor</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($lanc as $l): $rec = $l['natureza'] === 'receita'; ?><tr><td class="text-nowrap"><?= dataBr($l['data_lanc']) ?></td><td><?= e($l['cat']) ?></td><td><?= e($l['descricao']) ?></td>
    <td class="text-end text-nowrap fw-semibold <?= $rec ? 'text-success' : 'text-danger' ?>"><?= $rec ? '+' : '−' ?><?= brl($l['valor']) ?></td>
    <td><?= $l['pago'] ? '<span class="badge text-bg-success">Realizado</span>' : '<span class="badge text-bg-warning">Previsto</span>' ?></td>
    <td class="text-nowrap"><?php if (!$l['pago']): ?><form method="post" class="d-inline"><?= csrfCampo() ?><input type="hidden" name="acao" value="pagar"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline-success"><?= $rec ? 'Recebido' : 'Pago' ?></button></form><?php endif; ?>
      <?php if (!$l['reserva_id'] || !$l['pago']): ?><form method="post" class="d-inline" onsubmit="return confirm('Excluir lançamento?')"><?= csrfCampo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-link text-danger" aria-label="Excluir">🗑</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  <?php if (!$lanc): ?><tr><td colspan="6" class="text-body-secondary">Nenhum lançamento.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php admRodape();
