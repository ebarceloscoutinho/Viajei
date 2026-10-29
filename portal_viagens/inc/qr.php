<?php
/**
 * QR Code (modo byte, correção de erro M, versões 1–10, até 213 bytes) em PHP puro — sem bibliotecas.
 * O algoritmo foi validado em Python contra um decodificador independente (OpenCV); este arquivo é o port linha a linha.
 * Rode `php tests/run.php` para conferir que a saída é idêntica à da versão validada.
 */
const QR_EC_M = [1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]], 6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]],
                 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]]];
const QR_ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

function qrGf() {
    static $t = null;
    if ($t === null) {
        $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0); $x = 1;
        for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        $t = [$exp, $log];
    }
    return $t;
}
function qrMul($a, $b) { if ($a === 0 || $b === 0) return 0; [$exp, $log] = qrGf(); return $exp[$log[$a] + $log[$b]]; }
function qrRsDiv($deg) {
    $r = array_fill(0, $deg, 0); $r[$deg - 1] = 1; $root = 1;
    for ($i = 0; $i < $deg; $i++) {
        for ($j = 0; $j < $deg; $j++) { $r[$j] = qrMul($r[$j], $root); if ($j + 1 < $deg) $r[$j] ^= $r[$j + 1]; }
        $root = qrMul($root, 2);
    }
    return $r;
}
function qrRsRem(array $data, $deg) {
    $gen = qrRsDiv($deg); $res = array_fill(0, $deg, 0);
    foreach ($data as $b) {
        $f = $b ^ array_shift($res); $res[] = 0;
        for ($i = 0; $i < $deg; $i++) $res[$i] ^= qrMul($gen[$i], $f);
    }
    return $res;
}
function qrNData($v) { $n = 0; foreach (QR_EC_M[$v][1] as $g) $n += $g[0] * $g[1]; return $n; }

/** Escreve os bits de formato (nível M + máscara). Se $F for passado, também marca as células como "função". */
function qrFormato(array &$G, $n, $mask, ?array &$F = null) {
    $r = $mask; for ($i = 0; $i < 10; $i++) $r = ($r << 1) ^ (($r >> 9) * 0x537);
    $bs = (($mask << 10) | $r) ^ 0x5412;
    $s = function ($x, $y, $b) use (&$G, &$F) { $G[$y][$x] = $b; if ($F !== null) $F[$y][$x] = 1; };
    $g = function ($i) use ($bs) { return ($bs >> $i) & 1; };
    for ($i = 0; $i < 6; $i++) $s(8, $i, $g($i));
    $s(8, 7, $g(6)); $s(8, 8, $g(7)); $s(7, 8, $g(8));
    for ($i = 9; $i < 15; $i++) $s(14 - $i, 8, $g($i));
    for ($i = 0; $i < 8; $i++) $s($n - 1 - $i, 8, $g($i));
    for ($i = 8; $i < 15; $i++) $s(8, $n - 15 + $i, $g($i));
    $s(8, $n - 8, 1);
}
function qrMascara($mk, $x, $y) {
    switch ($mk) {
        case 0: return ($x + $y) % 2 === 0;
        case 1: return $y % 2 === 0;
        case 2: return $x % 3 === 0;
        case 3: return ($x + $y) % 3 === 0;
        case 4: return (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0;
        case 5: return ($x * $y) % 2 + ($x * $y) % 3 === 0;
        case 6: return (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0;
        default: return (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0;
    }
}
function qrPenal(array $G, $n) {
    $p = 0;
    for ($pas = 0; $pas < 2; $pas++) {
        for ($a = 0; $a < $n; $a++) {
            $row = []; for ($b = 0; $b < $n; $b++) $row[] = $pas === 0 ? $G[$a][$b] : $G[$b][$a];
            $run = 1;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n && $row[$i] === $row[$i - 1]) $run++;
                else { if ($run >= 5) $p += 3 + $run - 5; $run = 1; }
            }
            $s = implode('', $row);
            $p += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
        }
    }
    for ($y = 0; $y < $n - 1; $y++) for ($x = 0; $x < $n - 1; $x++)
        if ($G[$y][$x] === $G[$y][$x + 1] && $G[$y][$x] === $G[$y + 1][$x] && $G[$y][$x] === $G[$y + 1][$x + 1]) $p += 3;
    $dark = 0; foreach ($G as $r) $dark += array_sum($r); $tot = $n * $n;
    return $p + (intdiv(abs($dark * 20 - $tot * 10) + $tot - 1, $tot) - 1) * 10;
}
/** Matriz de módulos (0/1) do QR do texto (UTF-8). */
function qrMatriz($texto) {
    $texto = (string)$texto; if ($texto === '') throw new Exception('Texto vazio para o QR Code.');
    $data = array_values(unpack('C*', $texto)); $L = count($data); $v = 0; $cc = 8;
    for ($i = 1; $i <= 10; $i++) { $c = $i < 10 ? 8 : 16; if (4 + $c + 8 * $L <= qrNData($i) * 8) { $v = $i; $cc = $c; break; } }
    if (!$v) throw new Exception('Texto grande demais para o QR Code (máximo de 213 bytes).');
    $bits = [];
    $put = function ($val, $n) use (&$bits) { for ($i = $n - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1; };
    $put(4, 4); $put($L, $cc); foreach ($data as $b) $put($b, 8);
    $cap = qrNData($v) * 8; $put(0, min(4, $cap - count($bits)));
    while (count($bits) % 8) $bits[] = 0;
    $pad = 0xEC; while (count($bits) < $cap) { $put($pad, 8); $pad ^= 0xEC ^ 0x11; }
    $cw = [];
    for ($i = 0; $i < count($bits); $i += 8) { $x = 0; for ($j = 0; $j < 8; $j++) $x = ($x << 1) | $bits[$i + $j]; $cw[] = $x; }
    [$ec, $gs] = QR_EC_M[$v]; $blocks = []; $k = 0;
    foreach ($gs as $g) for ($b = 0; $b < $g[0]; $b++) { $d = array_slice($cw, $k, $g[1]); $k += $g[1]; $blocks[] = [$d, qrRsRem($d, $ec)]; }
    $out = []; $maxd = 0; foreach ($blocks as $bl) $maxd = max($maxd, count($bl[0]));
    for ($i = 0; $i < $maxd; $i++) foreach ($blocks as $bl) if ($i < count($bl[0])) $out[] = $bl[0][$i];
    for ($i = 0; $i < $ec; $i++) foreach ($blocks as $bl) $out[] = $bl[1][$i];
    $dbits = []; foreach ($out as $b) for ($i = 7; $i >= 0; $i--) $dbits[] = ($b >> $i) & 1;

    $n = 17 + 4 * $v; $M = array_fill(0, $n, array_fill(0, $n, 0)); $F = $M;
    $setf = function ($x, $y, $d) use (&$M, &$F) { $M[$y][$x] = $d ? 1 : 0; $F[$y][$x] = 1; };
    for ($i = 0; $i < $n; $i++) { $setf(6, $i, $i % 2 === 0); $setf($i, 6, $i % 2 === 0); }
    $finder = function ($cx, $cy) use ($n, $setf) {
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $xx = $cx + $dx; $yy = $cy + $dy;
            if ($xx >= 0 && $xx < $n && $yy >= 0 && $yy < $n) { $d = max(abs($dx), abs($dy)); $setf($xx, $yy, $d !== 2 && $d !== 4); }
        }
    };
    $finder(3, 3); $finder($n - 4, 3); $finder(3, $n - 4);
    $ap = QR_ALIGN[$v]; $na = count($ap);
    for ($i = 0; $i < $na; $i++) for ($j = 0; $j < $na; $j++) {
        if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) continue;
        for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $setf($ap[$j] + $dx, $ap[$i] + $dy, max(abs($dx), abs($dy)) !== 1);
    }
    qrFormato($M, $n, 0, $F);
    if ($v >= 7) {
        $r = $v; for ($i = 0; $i < 12; $i++) $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
        $bs = ($v << 12) | $r;
        for ($i = 0; $i < 18; $i++) { $b = ($bs >> $i) & 1; $a = $n - 11 + $i % 3; $c = intdiv($i, 3); $setf($a, $c, $b); $setf($c, $a, $b); }
    }
    $i = 0; $right = $n - 1; $nb = count($dbits);
    while ($right >= 1) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $n; $vert++) for ($j = 0; $j < 2; $j++) {
            $x = $right - $j; $up = (($right + 1) & 2) === 0; $y = $up ? $n - 1 - $vert : $vert;
            if (!$F[$y][$x] && $i < $nb) { $M[$y][$x] = $dbits[$i]; $i++; }
        }
        $right -= 2;
    }
    $best = null; $bm = null;
    for ($mk = 0; $mk < 8; $mk++) {
        $G = $M;
        for ($y = 0; $y < $n; $y++) for ($x = 0; $x < $n; $x++) if (!$F[$y][$x] && qrMascara($mk, $x, $y)) $G[$y][$x] ^= 1;
        qrFormato($G, $n, $mk);
        $sc = qrPenal($G, $n);
        if ($best === null || $sc < $best) { $best = $sc; $bm = $G; }
    }
    return $bm;
}
/** QR em SVG (com margem branca de 4 módulos, legível mesmo no modo escuro). */
function qrSvg($texto, $px = 220, $rotulo = 'QR Code') {
    $m = qrMatriz($texto); $n = count($m); $q = 4; $d = '';
    for ($y = 0; $y < $n; $y++) {
        $x = 0;
        while ($x < $n) { if ($m[$y][$x]) { $s = $x; while ($x < $n && $m[$y][$x]) $x++; $d .= 'M' . ($s + $q) . ' ' . ($y + $q) . 'h' . ($x - $s) . 'v1h-' . ($x - $s) . 'z'; } else $x++; }
    }
    $t = $n + 2 * $q;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $t . ' ' . $t . '" width="' . (int)$px . '" height="' . (int)$px . '" shape-rendering="crispEdges" role="img" aria-label="' . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '" style="background:#fff;border-radius:.6rem;max-width:100%;height:auto"><rect width="' . $t . '" height="' . $t . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
}
