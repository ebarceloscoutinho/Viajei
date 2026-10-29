<?php
/**
 * Testes das regras que não dependem de banco. Rode no terminal:   php tests/run.php
 * Os valores esperados foram calculados por uma implementação independente (e o TOTP confere com os vetores oficiais da RFC 6238).
 */
if (PHP_SAPI !== 'cli') exit('Execute pelo terminal: php tests/run.php');
const TAXA_PASSAPORTE_BRL = 257.25;
require __DIR__ . '/../inc/util.php'; require __DIR__ . '/../inc/qr.php'; require __DIR__ . '/../inc/pix.php'; require __DIR__ . '/../inc/totp.php'; require __DIR__ . '/../inc/custo.php';
$ok = 0; $falhas = [];
function t($nome, $cond) { global $ok, $falhas; if ($cond) $ok++; else $falhas[] = $nome; }
function perto($a, $b, $tol = 0.01) { return abs($a - $b) <= $tol; }

// YouTube
t('youtubeId: watch', youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
t('youtubeId: youtu.be com tempo', youtubeId('https://youtu.be/dQw4w9WgXcQ?t=10') === 'dQw4w9WgXcQ');
t('youtubeId: parâmetro antes do v', youtubeId('https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
t('youtubeId: embed', youtubeId('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
t('youtubeId: ID puro', youtubeId('dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
t('youtubeId: recusa outro site', youtubeId('https://example.com/watch?v=dQw4w9WgXcQ') === null);
t('youtubeId: recusa script', youtubeId('javascript:alert(1)') === null);
t('soDigitos', soDigitos('(35) 99999-0000') === '35999990000');

// Cupom
t('cupom 10% de 1000', descontoCupom(['tipo' => 'percentual', 'valor' => 10], 1000) === 100.0);
t('cupom fixo maior que o total fica em total-1', descontoCupom(['tipo' => 'valor', 'valor' => 2000], 1000) === 999.0);
t('cupom fixo menor que o total', descontoCupom(['tipo' => 'valor', 'valor' => 100], 500) === 100.0);

// PIX
t('CRC16 padrão ("123456789" = 29B1)', pixCrc16('123456789') === '29B1');
t('PIX copia e cola', pixPayload('pagamentos@portalviagens.exemplo', 'Roteira Viagens', 'Campanha', 189.90, 'RT000042') === '00020126540014br.gov.bcb.pix0132pagamentos@portalviagens.exemplo5204000053039865406189.905802BR5915ROTEIRA VIAGENS6008CAMPANHA62120508RT0000426304C5E9');
t('PIX remove acentos', pixLimpa('São José', 25) === 'SAO JOSE');
t('PIX sem valor omite o campo 54', strpos(pixPayload('a@b.com', 'X', 'Y', 0, 'T1'), '5406') === false);

// 2FA (TOTP) — vetores da RFC 6238 (segredo "12345678901234567890")
$seg = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
t('base32 ida e volta', base32Decode(base32Encode('12345678901234567890')) === '12345678901234567890');
t('base32 do segredo da RFC', base32Encode('12345678901234567890') === $seg);
foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $tempo => $cod) t("TOTP t=$tempo", totpCodigo($seg, $tempo) === $cod);
t('TOTP aceita a janela anterior', totpVerificar($seg, '287082', 59 + 30));
t('TOTP recusa código errado', !totpVerificar($seg, '000000', 59));
t('TOTP recusa lixo', !totpVerificar($seg, 'abc', 59));

// Custo da viagem (quiz)
$pais = ['cotacao_brl' => 5.4, 'hospedagem_dia' => 100, 'alimentacao_dia' => 50, 'transporte_dia' => 10, 'lazer_dia' => 20, 'seguro_dia_brl' => 30, 'passagem_brl' => 5000, 'visto' => 0];
$in = ['dias' => 7, 'pessoas' => 2, 'estilo' => 'conforto', 'alim' => 'misto', 'trans' => 'misto', 'lazer' => 'moderado', 'seguro' => true, 'passaportes' => 0];
$base = calcularCusto($pais, $in, 0); $total = array_sum(array_column($base, 'brl'));
t('custo total esperado (R$ 32.391,92)', perto($total, 32391.92));
$sub = $total - $base['Reserva de emergência (10%)']['brl'];
t('emergência = 10% do subtotal', perto($base['Reserva de emergência (10%)']['brl'], $sub * 0.10));
t('moeda valorizando +10% encarece', array_sum(array_column(calcularCusto($pais, $in, 10), 'brl')) > $total);
t('passagem não sofre com câmbio', calcularCusto($pais, $in, 30)['Passagem aérea']['brl'] === $base['Passagem aérea']['brl']);
t('mais dias custa mais', array_sum(array_column(calcularCusto($pais, ['dias' => 14] + $in, 0), 'brl')) > $total);
t('sem seguro custa menos', array_sum(array_column(calcularCusto($pais, ['seguro' => false] + $in, 0), 'brl')) < $total);
t('passaporte entra na documentação', perto(calcularCusto($pais, ['passaportes' => 2] + $in, 0)['Documentação (visto e passaporte)']['brl'], 2 * 257.25));


// QR Code — saída idêntica à da versão validada por um decodificador independente (OpenCV)
function qrHash($t) { return substr(sha1(implode('', array_map(fn($r) => implode('', $r), qrMatriz($t)))), 0, 12); }
t('QR: URL curta (v2)', qrHash('https://roteira.exemplo/qr') === 'c50111d3918d');
t('QR: PIX copia e cola (v8)', qrHash('00020126540014br.gov.bcb.pix0132pagamentos@portalviagens.exemplo5204000053039865406189.905802BR5915ROTEIRA VIAGENS6008CAMPANHA62120508RT0000426304C5E9') === '01b200a9110e');
t('QR: link do autenticador 2FA (v8)', qrHash('otpauth://totp/Roteira%3Aadmin%40portalviagens.com?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Roteira&digits=6&period=30') === 'cb544b728265');
t('QR: 14 bytes (v1, 21x21)', count(qrMatriz(str_repeat('x', 14))) === 21 && qrHash(str_repeat('x', 14)) === '885798ae0a31');
t('QR: 150 bytes', qrHash(str_repeat('z', 150)) === 'ca2a62f78388');
t('QR: 213 bytes (v10, contador de 16 bits)', qrHash(str_repeat('w', 213)) === '256a7cc13163');
t('QR: recusa texto grande demais', (function () { try { qrMatriz(str_repeat('x', 214)); return false; } catch (Exception $e) { return true; } })());
t('QR: SVG gerado', strpos(qrSvg('teste'), '<svg') === 0);

// Migrações: divisor de SQL
$parts = sqlDividir("-- c1; x\nINSERT INTO t VALUES ('a;b','it''s'); /* c; */ SELECT 1; # nota; \nSELECT \"x;y\";");
t('sqlDividir: 3 instruções', count($parts) === 3);
t('sqlDividir: preserva ; dentro de aspas', $parts[0] === "INSERT INTO t VALUES ('a;b','it''s')" && $parts[2] === 'SELECT "x;y"');
t('sqlDividir: migração 001 = 9 instruções', count(sqlDividir(file_get_contents(__DIR__ . '/../database/migrations/001_cargos_verificacao_notificacoes_compartilhar.sql'))) === 9);

// Webhook de pagamento: assinatura HMAC
t('webhook: assinatura HMAC-SHA256', webhookAssinatura('{"reserva_id":1,"valor":10.5}', '1700000000', 'segredo') === '46fac9ab0fc3b508df31842b6af5c2a5e2ff9d216f47e33807f4cb752ce63176');
t('webhook: corpo adulterado muda a assinatura', webhookAssinatura('{"reserva_id":1,"valor":0.5}', '1700000000', 'segredo') !== '46fac9ab0fc3b508df31842b6af5c2a5e2ff9d216f47e33807f4cb752ce63176');

echo "\n" . ($ok + count($falhas)) . " testes: $ok ok, " . count($falhas) . " falhas\n";
foreach ($falhas as $f) echo "  ✗ $f\n";
exit($falhas ? 1 : 0);
