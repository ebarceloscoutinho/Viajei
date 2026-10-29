<?php
/** Calcula o orçamento. $var = variação (%) da moeda do destino frente ao real (cenário de câmbio). */
function calcularCusto(array $p, array $in, $var) {
    $M = ['hosp' => ['economico' => 1, 'conforto' => 1.9, 'luxo' => 4.0], 'alimE' => ['economico' => 1, 'conforto' => 1.5, 'luxo' => 2.8],
          'alimP' => ['cozinho' => 0.7, 'misto' => 1, 'restaurantes' => 1.4], 'trans' => ['publico' => 1, 'misto' => 1.6, 'aluguel' => 2.5],
          'lazerI' => ['leve' => 0.6, 'moderado' => 1, 'intenso' => 1.8], 'lazerE' => ['economico' => 1, 'conforto' => 1.3, 'luxo' => 2.0], 'voo' => ['economico' => 1, 'conforto' => 1.3, 'luxo' => 3.0]];
    $fx = $p['cotacao_brl'] * (1 + $var / 100); $d = $in['dias']; $n = $in['pessoas']; $e = $in['estilo']; $quartos = ceil($n / 2);
    $loc = [   // itens em moeda local (sofrem com o câmbio)
        'Moradia' => $p['hospedagem_dia'] * $d * $quartos * $M['hosp'][$e],
        'Alimentação' => $p['alimentacao_dia'] * $d * $n * $M['alimE'][$e] * $M['alimP'][$in['alim']],
        'Transporte local' => $p['transporte_dia'] * $d * $n * $M['trans'][$in['trans']],
        'Lazer e passeios' => $p['lazer_dia'] * $d * $n * $M['lazerI'][$in['lazer']] * $M['lazerE'][$e],
    ];
    $L = [];
    foreach ($loc as $k => $v) $L[$k] = ['local' => $v, 'brl' => $v * $fx];
    $L['Passagem aérea'] = ['local' => null, 'brl' => $p['passagem_brl'] * $n * $M['voo'][$e]];
    $L['Seguro viagem'] = ['local' => null, 'brl' => $in['seguro'] ? $p['seguro_dia_brl'] * $d * $n : 0];
    $vistoLocal = $p['visto'] * $n;
    $L['Documentação (visto e passaporte)'] = ['local' => $vistoLocal, 'brl' => $vistoLocal * $fx + $in['passaportes'] * TAXA_PASSAPORTE_BRL];
    $sub = array_sum(array_column($L, 'brl'));
    $L['Reserva de emergência (10%)'] = ['local' => null, 'brl' => $sub * 0.10];
    return $L;
}
