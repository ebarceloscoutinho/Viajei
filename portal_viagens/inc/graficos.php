<?php
/** Gráficos em SVG puro (sem bibliotecas externas): funcionam offline, no claro/escuro e no celular. */
const CORES_GRAFICO = ['#0f766e', '#f97316', '#3b82f6', '#a855f7', '#ef4444', '#eab308', '#14b8a6', '#ec4899', '#64748b'];
function corGrafico($i) { return CORES_GRAFICO[$i % count(CORES_GRAFICO)]; }
function kfmt($v) { $a = abs($v); return ($v < 0 ? '-' : '') . ($a >= 1000 ? number_format($a / 1000, $a >= 10000 ? 0 : 1, ',', '.') . 'k' : number_format($a, 0, ',', '.')); }

/** Rosca: $dados = ['Rótulo' => valor]. As cores seguem a ordem de CORES_GRAFICO (use corGrafico($i) na legenda). */
function graficoRosca(array $dados, $centro = '', $sub = '') {
    $total = array_sum($dados);
    if ($total <= 0) return '<p class="text-body-secondary mb-0">Sem dados no período.</p>';
    $off = 25; $i = 0;
    $svg = '<svg viewBox="0 0 42 42" class="grafico" role="img" aria-label="Gráfico de rosca" style="max-width:260px;width:100%"><circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--bs-border-color)" stroke-width="6"/>';
    foreach ($dados as $k => $v) {
        $p = $v / $total * 100;
        $svg .= '<circle cx="21" cy="21" r="15.9155" fill="none" stroke="' . corGrafico($i++) . '" stroke-width="6" stroke-dasharray="' . round($p, 3) . ' ' . round(100 - $p, 3) . '" stroke-dashoffset="' . round($off, 3) . '"><title>' . e($k) . ': ' . brl($v) . ' (' . round($p) . '%)</title></circle>';
        $off -= $p;
    }
    return $svg . '<text x="21" y="21" text-anchor="middle" style="font-size:3.4px;font-weight:700">' . e($centro) . '</text><text x="21" y="25" text-anchor="middle" style="font-size:2.2px;opacity:.7">' . e($sub) . '</text></svg>';
}

/** Barras agrupadas por mês: $meses = [['rot'=>'mai/26','receita'=>x,'despesa'=>y,'lucro'=>z], ...] */
function graficoBarras(array $meses) {
    $W = 640; $H = 280; $ml = 46; $mt = 14; $mb = 30; $ph = $H - $mt - $mb; $n = max(1, count($meses));
    $max = 1; $min = 0;
    foreach ($meses as $m) { $max = max($max, $m['receita'], $m['despesa'], $m['lucro']); $min = min($min, $m['lucro']); }
    $span = $max - $min; $y0 = $mt + $max / $span * $ph; $gw = ($W - $ml - 8) / $n; $bw = min(30, $gw / 4);
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="grafico" role="img" aria-label="Receitas, despesas e lucro por mês" style="width:100%;height:auto">';
    for ($g = 0; $g <= 4; $g++) {
        $v = $min + $span * $g / 4; $y = $mt + (1 - $g / 4) * $ph;
        $svg .= '<line x1="' . $ml . '" x2="' . ($W - 4) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" stroke="var(--bs-border-color)" stroke-width="1"/><text x="' . ($ml - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . kfmt($v) . '</text>';
    }
    $svg .= '<line x1="' . $ml . '" x2="' . ($W - 4) . '" y1="' . round($y0, 1) . '" y2="' . round($y0, 1) . '" stroke="currentColor" stroke-opacity=".5"/>';
    foreach ($meses as $i => $m) {
        $x = $ml + $i * $gw + ($gw - 3 * $bw - 8) / 2;
        foreach ([['receita', '#16a34a'], ['despesa', '#dc2626'], ['lucro', '#2563eb']] as $j => [$k, $cor]) {
            $v = $m[$k]; $h = abs($v) / $span * $ph; $y = $v >= 0 ? $y0 - $h : $y0;
            $svg .= '<rect x="' . round($x + $j * ($bw + 4), 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max($h, 0.5), 1) . '" rx="3" fill="' . $cor . '"><title>' . e($m['rot']) . ' · ' . $k . ': ' . brl($v) . '</title></rect>';
        }
        $svg .= '<text x="' . round($ml + $i * $gw + $gw / 2 - 4, 1) . '" y="' . ($H - 10) . '" text-anchor="middle">' . e($m['rot']) . '</text>';
    }
    return $svg . '</svg>';
}
