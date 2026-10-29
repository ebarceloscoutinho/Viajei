<?php
require_once __DIR__ . '/inc/app.php';
$q = trim($_GET['q'] ?? ''); $cat = (int)($_GET['categoria'] ?? 0); $ord = $_GET['ordem'] ?? 'data'; $pais = trim($_GET['pais'] ?? '');
$escopo = in_array($_GET['escopo'] ?? '', ['nacional', 'internacional'], true) ? $_GET['escopo'] : '';
$where = ''; $params = [];
if ($q !== '') { $where .= ' AND (p.titulo LIKE ? OR p.destino LIKE ? OR p.pais LIKE ? OR p.atracoes LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
if ($cat) { $where .= ' AND p.categoria_id = ?'; $params[] = $cat; }
if ($escopo) { $where .= ' AND p.escopo = ?'; $params[] = $escopo; }
if ($pais !== '') { $where .= ' AND p.pais = ?'; $params[] = $pais; }
$ordens = ['data' => 'ISNULL(proxima), proxima ASC', 'menor' => 'p.preco ASC', 'maior' => 'p.preco DESC', 'dias' => 'p.dias DESC', 'nota' => 'ISNULL(nota_media), nota_media DESC'];
$lista = buscarPasseios($conn, $where, $params, $ordens[$ord] ?? $ordens['data'], 200);
$cats = $conn->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();
$paises = $conn->query("SELECT DISTINCT pais FROM passeios WHERE escopo='internacional' AND ativo=1 ORDER BY pais")->fetchAll(PDO::FETCH_COLUMN);
$titulo = $escopo === 'nacional' ? '🇧🇷 Viagens nacionais' : ($escopo === 'internacional' ? '🌍 Viagens internacionais' : 'Todas as viagens e passeios');
topo($titulo);
?>
<h1 class="h3 mb-3"><?= $titulo ?></h1>
<div class="d-flex gap-2 mb-3">
  <a class="chip <?= $escopo === '' ? 'ativo' : '' ?>" href="passeios.php">Todas</a>
  <a class="chip <?= $escopo === 'nacional' ? 'ativo' : '' ?>" href="?escopo=nacional">🇧🇷 Nacionais</a>
  <a class="chip <?= $escopo === 'internacional' ? 'ativo' : '' ?>" href="?escopo=internacional">🌍 Internacionais</a>
</div>
<form class="row g-2 mb-4" method="get"><input type="hidden" name="escopo" value="<?= e($escopo) ?>">
  <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Destino, país ou atração (ex.: cachoeira, Paris)"></div>
  <?php if ($escopo !== 'nacional'): ?><div class="col-6 col-md-2"><select class="form-select" name="pais"><option value="">Todos os países</option>
    <?php foreach ($paises as $x): ?><option <?= $pais === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="col-6 col-md-2"><select class="form-select" name="categoria"><option value="0">Categorias</option>
    <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['icone'] . ' ' . $c['nome']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><select class="form-select" name="ordem">
    <option value="data">Próxima saída</option><option value="menor" <?= $ord === 'menor' ? 'selected' : '' ?>>Menor preço</option><option value="maior" <?= $ord === 'maior' ? 'selected' : '' ?>>Maior preço</option><option value="dias" <?= $ord === 'dias' ? 'selected' : '' ?>>Mais dias</option><option value="nota" <?= $ord === 'nota' ? 'selected' : '' ?>>Mais bem avaliadas</option></select></div>
  <div class="col-6 col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<p class="text-body-secondary small"><?= count($lista) ?> resultado(s)</p>
<?php if (!$lista): ?><div class="alert alert-info">Nada encontrado com esses filtros.</div>
<?php else: ?><div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"><?php foreach ($lista as $p) echo cardPasseio($p); ?></div><?php endif;
rodape();
