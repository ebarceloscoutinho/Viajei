<?php
require_once __DIR__ . '/inc/app.php';
$_SESSION['comparar'] = array_values(array_unique(array_filter(array_map('intval', $_SESSION['comparar'] ?? []))));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? ''; $pid = (int)($_POST['passeio_id'] ?? 0);
    if ($acao === 'add' && $pid) {
        if (in_array($pid, $_SESSION['comparar'], true)) flash('Essa viagem já está na comparação.', 'info');
        elseif (count($_SESSION['comparar']) >= 3) flash('Você pode comparar até 3 viagens. Remova uma para adicionar outra.', 'warning');
        else $_SESSION['comparar'][] = $pid;
    } elseif ($acao === 'remover') $_SESSION['comparar'] = array_values(array_diff($_SESSION['comparar'], [$pid]));
    elseif ($acao === 'limpar') $_SESSION['comparar'] = [];
    redirecionar('comparar.php');
}
$ids = $_SESSION['comparar']; $lista = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?')); $rows = [];
    foreach (buscarPasseios($conn, "AND p.id IN ($in)", $ids) as $p) $rows[$p['id']] = $p;
    foreach ($ids as $i) if (isset($rows[$i])) $lista[] = $rows[$i];
}
$menor = $lista ? min(array_map(fn($p) => (float)$p['preco'], $lista)) : 0;
topo('Comparar viagens');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">⚖ Comparar viagens</h1><?php if ($lista): ?><form method="post"><?= csrfCampo() ?><button class="btn btn-sm btn-outline-secondary" name="acao" value="limpar">Limpar</button></form><?php endif; ?></div>
<?php if (count($lista) < 2): ?><div class="alert alert-info">Adicione pelo menos 2 viagens (até 3) usando o botão <strong>⚖ Comparar</strong> na página de cada viagem. <a href="passeios.php">Ver viagens</a></div><?php endif; ?>
<?php if ($lista): ?><div class="table-responsive"><table class="table align-top" style="min-width:560px"><thead><tr><th style="width:130px"></th>
  <?php foreach ($lista as $p): ?><th style="min-width:200px"><?= capa($p['emoji'], $p['hue'], 'mini', $p['motivo'] ?? '', $p['imagem'] ?? '') ?><div class="mt-2"><a href="passeio.php?id=<?= (int)$p['id'] ?>"><?= e($p['titulo']) ?></a></div>
    <form method="post" class="mt-1"><?= csrfCampo() ?><input type="hidden" name="passeio_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" name="acao" value="remover">remover</button></form></th><?php endforeach; ?></tr></thead><tbody>
  <?php $linhas = [
      'Destino' => fn($p) => e($p['escopo'] === 'internacional' ? $p['pais'] . ' · ' . $p['destino'] : $p['destino']),
      'Categoria' => fn($p) => e($p['icone'] . ' ' . $p['categoria']),
      'Duração' => fn($p) => (int)$p['dias'] > 1 ? (int)$p['dias'] . ' dias' : 'Day tour',
      'Preço por pessoa' => fn($p) => '<strong class="text-primary">' . brl($p['preco']) . '</strong>' . ((float)$p['preco'] === $menor && count($GLOBALS['lista']) > 1 ? ' <span class="badge text-bg-success">menor preço</span>' : ''),
      'Preço por dia' => fn($p) => brl($p['preco'] / max(1, (int)$p['dias'])),
      'Próxima saída' => fn($p) => $p['proxima'] ? dataBr($p['proxima']) : '—',
      'Vagas' => fn($p) => $p['proxima'] ? ((int)$p['vagas'] > 0 ? (int)$p['vagas'] . ' disponíveis' : 'Lotado') : '—',
      'Guia' => fn($p) => e($p['guia'] ?: '—'),
      'Avaliação' => fn($p) => (int)$p['n_aval'] ? estrelas($p['nota_media']) . ' ' . number_format($p['nota_media'], 1, ',', '') . ' (' . (int)$p['n_aval'] . ')' : 'sem avaliações',
      'Atrações' => fn($p) => $p['atracoes'] ? '<ul class="ps-3 mb-0 small">' . implode('', array_map(fn($a) => '<li>' . e(trim($a)) . '</li>', array_filter(explode(';', $p['atracoes'])))) . '</ul>' : '—']; ?>
  <?php foreach ($linhas as $nome => $fn): ?><tr><th class="text-body-secondary small"><?= e($nome) ?></th><?php foreach ($lista as $p): ?><td><?= $fn($p) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  <tr><th></th><?php foreach ($lista as $p): ?><td><a class="btn btn-accent btn-sm" href="passeio.php?id=<?= (int)$p['id'] ?>#reservar">Ver e reservar</a></td><?php endforeach; ?></tr></tbody></table></div><?php endif; ?>
<?php rodape();
