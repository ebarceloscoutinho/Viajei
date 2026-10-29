<?php
/** Funções puras (sem banco nem sessão): usadas pelo site e pelos testes (php tests/run.php). */
/** Extrai o ID de 11 caracteres de um link do YouTube (watch, youtu.be, embed, shorts) ou de um ID puro. */
function youtubeId($s) {
    $s = trim((string)$s); if ($s === '') return null;
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $s)) return $s;
    return preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^&\s]*&)*v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $s, $m) ? $m[1] : null;
}

function videoBusca($termo) { return 'https://www.youtube.com/results?search_query=' . urlencode($termo); }

function soDigitos($t) { return preg_replace('/\D+/', '', (string)$t); }

function descontoCupom(array $cp, $total) {   // nunca deixa a reserva abaixo de R$ 1,00
    $d = $cp['tipo'] === 'percentual' ? round($total * $cp['valor'] / 100, 2) : (float)$cp['valor'];
    return round(max(0, min($d, $total - 1)), 2);
}

/** Divide um script SQL em instruções (respeita aspas, ignora comentários -- # e bloco). */
function sqlDividir($sql) {
    $out = []; $cur = ''; $q = ''; $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $ch = $sql[$i];
        if ($q !== '') {
            $cur .= $ch;
            if ($ch === '\\' && $i + 1 < $n) { $cur .= $sql[++$i]; }
            elseif ($ch === $q) { if ($i + 1 < $n && $sql[$i + 1] === $q) { $cur .= $sql[++$i]; } else { $q = ''; } }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $q = $ch; $cur .= $ch; continue; }
        if ($ch === '-' && $i + 1 < $n && $sql[$i + 1] === '-' && ($i + 2 >= $n || ctype_space($sql[$i + 2]))) { while ($i < $n && $sql[$i] !== "\n") $i++; $cur .= "\n"; continue; }
        if ($ch === '#') { while ($i < $n && $sql[$i] !== "\n") $i++; $cur .= "\n"; continue; }
        if ($ch === '/' && $i + 1 < $n && $sql[$i + 1] === '*') { $j = strpos($sql, '*/', $i + 2); $i = $j === false ? $n : $j + 1; continue; }
        if ($ch === ';') { $s = trim($cur); if ($s !== '') $out[] = $s; $cur = ''; continue; }
        $cur .= $ch;
    }
    $s = trim($cur); if ($s !== '') $out[] = $s;
    return $out;
}
/** Assinatura HMAC-SHA256 do webhook de pagamento: hmac(timestamp . "." . corpo_bruto, segredo). */
function webhookAssinatura($corpo, $timestamp, $segredo) { return hash_hmac('sha256', $timestamp . '.' . $corpo, $segredo); }
