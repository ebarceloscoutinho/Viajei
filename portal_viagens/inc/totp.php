<?php
/** Verificação em duas etapas (TOTP, RFC 6238): compatível com Google Authenticator, Authy, Microsoft Authenticator etc. */
const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
function base32Encode($bin) {
    $bits = ''; foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $o = ''; foreach (str_split($bits, 5) as $ch) $o .= B32[bindec(str_pad($ch, 5, '0'))];
    return $o;
}
function base32Decode($s) {
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s)); $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos(B32, $c)), 5, '0', STR_PAD_LEFT);
    $o = ''; foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $o .= chr(bindec($b));
    return $o;
}
function totpCodigo($segredo, $t = null, $digitos = 6) {
    $cnt = intdiv($t ?? time(), 30);
    $h = hash_hmac('sha1', pack('N2', $cnt >> 32, $cnt & 0xFFFFFFFF), base32Decode($segredo), true);
    $o = ord($h[19]) & 0xF;
    $v = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($v % (10 ** $digitos)), $digitos, '0', STR_PAD_LEFT);
}
function totpVerificar($segredo, $codigo, $t = null) {   // aceita 1 janela de 30s antes/depois (relógios levemente diferentes)
    $codigo = preg_replace('/\D/', '', (string)$codigo);
    if (strlen($codigo) !== 6 || $segredo === '') return false;
    $t = $t ?? time();
    for ($i = -1; $i <= 1; $i++) if (hash_equals(totpCodigo($segredo, $t + 30 * $i), $codigo)) return true;
    return false;
}
function totpNovoSegredo() { return base32Encode(random_bytes(20)); }
function totpUri($segredo, $conta, $emissor) { return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta) . '?secret=' . $segredo . '&issuer=' . rawurlencode($emissor) . '&digits=6&period=30'; }
