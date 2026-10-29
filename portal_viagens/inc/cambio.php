<?php
/** Atualiza moedas.cotacao_brl pela API pública open.er-api.com (sem chave; atualiza 1x/dia; atribuição: exchangerate-api.com). */
function atualizarCotacoes(PDO $c) {
    $url = 'https://open.er-api.com/v6/latest/BRL'; $json = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'Roteira/1.0']);
        $json = curl_exec($ch); curl_close($ch);
    }
    if (!$json) $json = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: Roteira/1.0\r\n"]]));
    $d = $json ? json_decode($json, true) : null;
    if (!is_array($d) || empty($d['rates']) || !is_array($d['rates'])) throw new Exception('Não foi possível obter as cotações agora (sem internet no servidor ou serviço indisponível).');
    $up = $c->prepare('UPDATE moedas SET cotacao_brl=? WHERE codigo=?'); $n = 0;
    foreach ($c->query("SELECT codigo FROM moedas WHERE codigo<>'BRL'")->fetchAll(PDO::FETCH_COLUMN) as $cod) {
        $r = (float)($d['rates'][$cod] ?? 0);   // quantas unidades da moeda valem 1 BRL
        if ($r > 0) { $up->execute([round(1 / $r, 8), $cod]); $n++; }
    }
    return $n;
}
