<?php
require_once __DIR__ . '/inc/app.php';
require_once __DIR__ . '/inc/graficos.php';
require_once __DIR__ . '/inc/custo.php';

$paises = $conn->query("SELECT p.*, m.simbolo, m.nome AS moeda_nome, m.cotacao_brl, m.atualizado_em FROM paises p JOIN moedas m ON m.codigo=p.moeda ORDER BY p.nome='Brasil' DESC, p.nome")->fetchAll();
$q = $_GET; $calc = isset($q['calcular']);
$lim = fn($v, $a, $b) => max($a, min($b, $v));
$pais = $paises[0];
foreach ($paises as $x) if ($x['nome'] === ($q['pais'] ?? 'Brasil')) $pais = $x;
$in = [
    'dias' => $lim((int)($q['dias'] ?? 7), 1, 60), 'pessoas' => $lim((int)($q['pessoas'] ?? 2), 1, 10),
    'estilo' => in_array($q['estilo'] ?? '', ['economico', 'conforto', 'luxo'], true) ? $q['estilo'] : 'conforto',
    'alim' => in_array($q['alim'] ?? '', ['cozinho', 'misto', 'restaurantes'], true) ? $q['alim'] : 'misto',
    'trans' => in_array($q['trans'] ?? '', ['publico', 'misto', 'aluguel'], true) ? $q['trans'] : 'misto',
    'lazer' => in_array($q['lazer'] ?? '', ['leve', 'moderado', 'intenso'], true) ? $q['lazer'] : 'moderado',
    'seguro' => !$calc || isset($q['seguro']),
];
$in['passaportes'] = $pais['nome'] === 'Brasil' ? 0 : $lim((int)($q['passaportes'] ?? 0), 0, $in['pessoas']);
$cambio = $lim((float)str_replace(',', '.', $q['cambio'] ?? 0), -30, 40);

$L = $calc ? calcularCusto($pais, $in, $cambio) : [];
$total = array_sum(array_column($L, 'brl'));
topo('Quanto custa a viagem?');
$op = function ($nome, $val, $ico, $tit, $sub = '') use ($q, $in) {
    $ck = ($in[$nome] ?? '') === $val ? 'checked' : '';
    return '<div class="col-12 col-sm-4"><label class="opcao"><input type="radio" name="' . $nome . '" value="' . $val . '" ' . $ck . '><div class="fs-4">' . $ico . '</div><strong>' . $tit . '</strong><div class="small text-body-secondary">' . $sub . '</div></label></div>';
};
?>
<h1 class="h3">💰 Quanto custa a viagem?</h1>
<p class="text-body-secondary">Responda 4 perguntas rápidas e veja uma estimativa de moradia, alimentação, transporte, lazer, documentação e emergência — já considerando o câmbio do destino.</p>

<?php if ($calc): ?>
<div class="card mb-4"><div class="card-body">
  <div class="row g-4 align-items-center">
    <div class="col-md-5 text-center"><?= graficoRosca(array_map(fn($x) => $x['brl'], array_filter($L, fn($x) => $x['brl'] > 0)), brl($total), 'total estimado') ?></div>
    <div class="col-md-7">
      <h2 class="h5"><?= $pais['nome'] === 'Brasil' ? '🇧🇷 Brasil' : '🌍 ' . e($pais['nome']) ?> · <?= $in['dias'] ?> dias · <?= $in['pessoas'] ?> pessoa(s)</h2>
      <div class="display-6 text-primary fw-bold"><?= brl($total) ?></div>
      <div class="text-body-secondary mb-3"><?= brl($total / $in['pessoas']) ?> por pessoa · <?= brl($total / $in['pessoas'] / $in['dias']) ?> por pessoa/dia</div>
      <table class="table table-sm mb-0"><tbody>
      <?php $i = 0; foreach ($L as $nome => $v): ?><tr><td><span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:<?= $v['brl'] > 0 ? corGrafico($i++) : 'transparent' ?>"></span><?= e($nome) ?></td>
        <td class="text-end text-body-secondary small"><?= $v['local'] && $pais['moeda'] !== 'BRL' ? e($pais['simbolo']) . ' ' . number_format($v['local'], 0, ',', '.') : '' ?></td><td class="text-end"><?= brl($v['brl']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div>
  </div></div></div>

<?php if ($pais['moeda'] !== 'BRL'): ?>
<div class="card mb-4"><div class="card-body"><h2 class="h6">📈 E se a moeda se valorizar?</h2>
  <p class="small text-body-secondary mb-2">Cotação de referência: 1 <?= e($pais['moeda']) ?> = <?= brl($pais['cotacao_brl']) ?> (atualizada em <?= dataBr($pais['atualizado_em']) ?>). Cenário atual escolhido: <strong><?= $cambio >= 0 ? '+' : '' ?><?= $cambio ?>%</strong>. Passagem, seguro e passaporte são em reais e não mudam.</p>
  <div class="table-responsive"><table class="table table-sm text-center mb-0"><thead><tr><?php foreach ([-10, 0, 10, 20] as $v): ?><th><?= $v > 0 ? '+' : '' ?><?= $v ?>%</th><?php endforeach; ?></tr></thead><tbody><tr>
    <?php $base = array_sum(array_column(calcularCusto($pais, $in, 0), 'brl'));
    foreach ([-10, 0, 10, 20] as $v): $t = array_sum(array_column(calcularCusto($pais, $in, $v), 'brl')); ?><td><?= brl($t) ?><br><small class="<?= $t > $base ? 'text-danger' : ($t < $base ? 'text-success' : 'text-body-secondary') ?>"><?= $t == $base ? 'base' : ($t > $base ? '+' : '−') . brl(abs($t - $base)) ?></small></td><?php endforeach; ?></tr></tbody></table></div></div></div>
<?php endif; ?>
<div class="alert alert-light border small">📄 <strong>Documentação:</strong> <?= e($pais['visto_obs'] ?: '—') ?>. Estimativa de referência — confirme as exigências no consulado ou site oficial. Valores de exemplo, não são oferta nem cotação em tempo real. Cotações de referência: ExchangeRate-API (exchangerate-api.com).</div>
<div class="d-flex gap-2 flex-wrap mb-4"><a class="btn btn-accent" href="passeios.php?<?= $pais['nome'] === 'Brasil' ? 'escopo=nacional' : 'escopo=internacional&pais=' . urlencode($pais['nome']) ?>">Ver pacotes <?= $pais['nome'] === 'Brasil' ? 'nacionais' : 'para ' . e($pais['nome']) ?></a><button class="btn btn-outline-secondary" onclick="window.print()">🖨️ Imprimir</button></div>
<h2 class="h5">Ajustar respostas</h2>
<?php endif; ?>

<form method="get" id="quiz" class="no-print"><input type="hidden" name="calcular" value="1">
  <div class="progress mb-3" style="height:6px" id="quiz-barra" hidden><div class="progress-bar" style="width:25%"></div></div>

  <section class="quiz-passo mb-4"><h2 class="h5">1. Para onde, quantos dias e quantas pessoas?</h2><div class="row g-3">
    <div class="col-12"><label class="form-label">Destino</label><select class="form-select" name="pais"><?php foreach ($paises as $x): ?><option <?= $x['nome'] === $pais['nome'] ? 'selected' : '' ?>><?= e($x['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label">Dias</label><input class="form-control" type="number" name="dias" min="1" max="60" value="<?= $in['dias'] ?>"></div>
    <div class="col-6"><label class="form-label">Pessoas</label><input class="form-control" type="number" name="pessoas" min="1" max="10" value="<?= $in['pessoas'] ?>"></div>
    <div class="col-12"><label class="form-label">Quantos precisam emitir passaporte? <span class="text-body-secondary small">(só viagens internacionais)</span></label><input class="form-control" type="number" name="passaportes" min="0" max="10" value="<?= $in['passaportes'] ?>"></div></div></section>

  <section class="quiz-passo mb-4"><h2 class="h5">2. Qual o estilo da viagem?</h2><div class="row g-2">
    <?= $op('estilo', 'economico', '🎒', 'Econômico', 'Hostel/hotel simples, voo promocional') ?><?= $op('estilo', 'conforto', '🏨', 'Conforto', 'Bom hotel, refeições variadas') ?><?= $op('estilo', 'luxo', '💎', 'Luxo', 'Hotel premium, classe executiva') ?></div></section>

  <section class="quiz-passo mb-4"><h2 class="h5">3. Como você come e se locomove?</h2>
    <div class="small fw-semibold mb-1">Alimentação</div><div class="row g-2 mb-3"><?= $op('alim', 'cozinho', '🥪', 'Cozinho/lanches', 'Mercado e comida simples') ?><?= $op('alim', 'misto', '🍝', 'Misto', 'Um pouco de cada') ?><?= $op('alim', 'restaurantes', '🍷', 'Restaurantes', 'Quase sempre fora') ?></div>
    <div class="small fw-semibold mb-1">Transporte local</div><div class="row g-2"><?= $op('trans', 'publico', '🚇', 'Público', 'Metrô, ônibus, a pé') ?><?= $op('trans', 'misto', '🚕', 'Misto', 'Público + apps/táxi') ?><?= $op('trans', 'aluguel', '🚗', 'Carro/transfer', 'Aluguel ou motorista') ?></div></section>

  <section class="quiz-passo mb-4"><h2 class="h5">4. Lazer, seguro e câmbio</h2>
    <div class="small fw-semibold mb-1">Ritmo de passeios</div><div class="row g-2 mb-3"><?= $op('lazer', 'leve', '🌿', 'Leve', 'Poucos passeios pagos') ?><?= $op('lazer', 'moderado', '🎟️', 'Moderado', 'Um por dia') ?><?= $op('lazer', 'intenso', '🎢', 'Intenso', 'Agenda cheia') ?></div>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="seguro" name="seguro" value="1" <?= $in['seguro'] ? 'checked' : '' ?>><label class="form-check-label" for="seguro">Incluir seguro viagem</label></div>
    <label class="form-label" for="cambio">Variação prevista da moeda do destino: <strong><output id="cambio-v"><?= $cambio ?></output>%</strong></label>
    <input class="form-range" type="range" id="cambio" name="cambio" min="-30" max="40" step="1" value="<?= $cambio ?>" oninput="document.getElementById('cambio-v').textContent=this.value">
    <div class="form-text">Positivo = a moeda do destino fica mais cara em reais (viagem sai mais cara). Ignorado no Brasil.</div></section>

  <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary d-none" id="quiz-voltar">Voltar</button><button type="button" class="btn btn-primary flex-fill d-none" id="quiz-prox">Próximo</button><button class="btn btn-accent flex-fill" id="quiz-calc">Calcular custo total</button></div>
</form>
<script>
// Assistente em passos (progressivo: sem JavaScript, todas as perguntas aparecem de uma vez)
(function () {
  var ps = [].slice.call(document.querySelectorAll('.quiz-passo')), i = 0, b = document.getElementById('quiz-barra');
  var prox = document.getElementById('quiz-prox'), volt = document.getElementById('quiz-voltar'), calc = document.getElementById('quiz-calc');
  if (<?= $calc ? 'true' : 'false' ?>) return;            // já calculado: mostra tudo para ajustar
  b.hidden = false; prox.classList.remove('d-none');
  function mostrar() {
    ps.forEach(function (p, k) { p.hidden = k !== i; });
    b.firstElementChild.style.width = ((i + 1) / ps.length * 100) + '%';
    volt.classList.toggle('d-none', i === 0); prox.classList.toggle('d-none', i === ps.length - 1); calc.classList.toggle('d-none', i !== ps.length - 1);
  }
  prox.onclick = function () { if (i < ps.length - 1) { i++; mostrar(); window.scrollTo({ top: 0, behavior: 'smooth' }); } };
  volt.onclick = function () { if (i > 0) { i--; mostrar(); } };
  mostrar();
})();
</script>
<?php rodape();
