<?php
/** PIX "copia e cola" (BR Code estático) com valor exato e identificador da reserva. Padrão EMV do Banco Central. */
function pixCrc16($s) {   // CRC16/CCITT-FALSE
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $crc ^= ord($s[$i]) << 8;
        for ($b = 0; $b < 8; $b++) $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}
function pixTlv($id, $v) { return $id . str_pad((string)strlen($v), 2, '0', STR_PAD_LEFT) . $v; }
function pixLimpa($s, $max) {
    $s = strtr((string)$s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','ç'=>'c',
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C']);
    return substr(strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', $s)), 0, $max);
}
function pixPayload($chave, $nome, $cidade, $valor, $txid) {
    $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', (string)$txid), 0, 25) ?: '***';
    $conta = pixTlv('00', 'br.gov.bcb.pix') . pixTlv('01', trim($chave));
    $p = pixTlv('00', '01') . pixTlv('26', $conta) . pixTlv('52', '0000') . pixTlv('53', '986')
       . ($valor > 0 ? pixTlv('54', number_format($valor, 2, '.', '')) : '') . pixTlv('58', 'BR')
       . pixTlv('59', pixLimpa($nome, 25)) . pixTlv('60', pixLimpa($cidade, 15)) . pixTlv('62', pixTlv('05', $txid)) . '6304';
    return $p . pixCrc16($p);
}
