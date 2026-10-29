<?php
/**
 * Ajustes do painel (Admin → Configurações).
 * Os valores ficam na tabela `configuracoes` com chave "sys_*"; sem valor salvo, vale o padrão definido aqui.
 * Para criar um ajuste novo basta acrescentar uma linha em ajustesSchema() e usar ajuste('sys_minha_chave') onde precisar.
 */
function ajustesSchema() {
    $cores = ['verde' => 'Verde', 'azul' => 'Azul', 'roxo' => 'Roxo', 'rosa' => 'Rosa', 'laranja' => 'Laranja', 'grafite' => 'Grafite'];
    return [
        'identidade' => ['🏷️', 'Identidade do site', 'Nome e frase que aparecem no topo do site, nos e-mails e nas telas de login.', [
            'sys_site_nome'   => ['rotulo' => 'Nome do site', 'tipo' => 'texto', 'padrao' => 'Roteira', 'max' => 30],
            'sys_site_slogan' => ['rotulo' => 'Slogan', 'tipo' => 'texto', 'padrao' => 'Monte, reserve, viaje.', 'max' => 80],
        ]],
        'aparencia' => ['🎨', 'Aparência padrão', 'Como o site aparece para quem ainda não escolheu um tema próprio (cada pessoa pode trocar no botão 🎨).', [
            'sys_tema_cor'  => ['rotulo' => 'Cor principal', 'tipo' => 'escolha', 'padrao' => 'verde', 'opcoes' => $cores],
            'sys_tema_modo' => ['rotulo' => 'Modo', 'tipo' => 'escolha', 'padrao' => 'auto', 'opcoes' => ['auto' => 'Automático (segue o aparelho)', 'light' => 'Claro', 'dark' => 'Escuro']],
        ]],
        'aviso' => ['📣', 'Aviso no topo do site', 'Uma faixa de recado em todas as páginas: promoção, feriado, mudança de horário…', [
            'sys_aviso_ativo' => ['rotulo' => 'Mostrar o aviso', 'tipo' => 'chave', 'padrao' => '0'],
            'sys_aviso_texto' => ['rotulo' => 'Texto do aviso', 'tipo' => 'texto', 'padrao' => '', 'max' => 200, 'ajuda' => 'Ex.: Black Friday: 10% de desconto com o cupom VIAJA10.'],
            'sys_aviso_tipo'  => ['rotulo' => 'Estilo', 'tipo' => 'escolha', 'padrao' => 'info', 'opcoes' => ['info' => 'Informativo (azul)', 'success' => 'Boa notícia (verde)', 'warning' => 'Atenção (amarelo)', 'danger' => 'Urgente (vermelho)']],
        ]],
        'reservas' => ['🧾', 'Reservas e cancelamento', 'Prazos e taxas aplicados em todo o site (reserva, termos, voucher e perguntas frequentes se atualizam sozinhos).', [
            'sys_prazo_pagamento_horas' => ['rotulo' => 'Prazo para pagar após reservar', 'tipo' => 'numero', 'padrao' => '24', 'min' => 1, 'max' => 168, 'sufixo' => 'horas'],
            'sys_bloqueio_horas'        => ['rotulo' => 'Bloqueio de novas reservas após expirar sem pagar', 'tipo' => 'numero', 'padrao' => '72', 'min' => 0, 'max' => 720, 'sufixo' => 'horas', 'ajuda' => '0 = sem bloqueio.'],
            'sys_dias_arrependimento'   => ['rotulo' => 'Arrependimento: cancelar sem taxa após a compra', 'tipo' => 'numero', 'padrao' => '7', 'min' => 0, 'max' => 30, 'sufixo' => 'dias'],
            'sys_dias_sem_taxa'         => ['rotulo' => 'Cancelar sem taxa até X dias antes da saída', 'tipo' => 'numero', 'padrao' => '2', 'min' => 0, 'max' => 60, 'sufixo' => 'dias'],
            'sys_taxa_cancelamento'     => ['rotulo' => 'Taxa de cancelamento fora do prazo', 'tipo' => 'numero', 'padrao' => '20', 'min' => 0, 'max' => 100, 'sufixo' => '%'],
            'sys_avaliacao_exige_viagem' => ['rotulo' => 'Só avaliar depois da viagem realizada', 'tipo' => 'chave', 'padrao' => '1', 'ajuda' => 'Desligue em demonstrações para avaliar logo após a confirmação.'],
        ]],
        'pagamento' => ['💳', 'Dados de pagamento (PIX)', 'O que o cliente vê na tela de pagamento. Troque pelos dados reais da agência.', [
            'sys_pix_chave'          => ['rotulo' => 'Chave PIX', 'tipo' => 'texto', 'padrao' => 'pagamentos@portalviagens.exemplo', 'max' => 77],
            'sys_pix_favorecido'     => ['rotulo' => 'Nome do favorecido', 'tipo' => 'texto', 'padrao' => 'Roteira Viagens', 'max' => 25, 'ajuda' => 'Até 25 caracteres (limite do PIX).'],
            'sys_pix_cidade'         => ['rotulo' => 'Cidade do favorecido', 'tipo' => 'texto', 'padrao' => 'CAMPANHA', 'max' => 15, 'ascii' => true, 'ajuda' => 'Até 15 caracteres, sem acento (limite do PIX).'],
            'sys_dados_transferencia' => ['rotulo' => 'Dados para transferência bancária', 'tipo' => 'texto', 'padrao' => 'Banco 000 · Agência 0000 · Conta 00000-0 · CNPJ 00.000.000/0000-00', 'max' => 200],
            'sys_guia_percentual'    => ['rotulo' => 'Repasse previsto ao guia sobre cada venda', 'tipo' => 'numero', 'padrao' => '30', 'min' => 0, 'max' => 100, 'sufixo' => '%'],
        ]],
        'sistema' => ['🛠️', 'Funcionamento do site', 'Liga e desliga partes do site sem mexer em código.', [
            'sys_cadastro_aberto' => ['rotulo' => 'Permitir novos cadastros', 'tipo' => 'chave', 'padrao' => '1'],
            'sys_email_ativo'     => ['rotulo' => 'Enviar e-mails de verdade', 'tipo' => 'chave', 'padrao' => '1', 'ajuda' => 'Desligado, os e-mails ficam só na fila (Admin → E-mails). Útil em servidor local.'],
            'sys_manutencao'      => ['rotulo' => '🚧 Modo manutenção', 'tipo' => 'chave', 'padrao' => '0', 'ajuda' => 'Visitantes veem uma página de "voltamos já". Você (equipe logada) continua navegando normalmente.'],
            'sys_manutencao_msg'  => ['rotulo' => 'Mensagem da manutenção', 'tipo' => 'texto', 'padrao' => 'Estamos fazendo alguns ajustes e voltamos já. Obrigado pela paciência!', 'max' => 200],
        ]],
    ];
}
/** Todos os campos em uma lista plana: chave => definição. */
function ajustesCampos() {
    static $f = null;
    if ($f === null) { $f = []; foreach (ajustesSchema() as $g) foreach ($g[3] as $k => $d) $f[$k] = $d; }
    return $f;
}
/** Valor atual de um ajuste (o salvo no painel ou, se não houver, o padrão). */
function ajuste($chave) {
    $d = ajustesCampos()[$chave] ?? null; $padrao = (string)($d['padrao'] ?? '');
    $v = config($chave, null);
    if ($v === null || ($v === '' && $padrao !== '')) return $padrao;
    return (string)$v;
}
function ajusteAscii($s) {
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c',
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C']);
    return preg_replace('/[^A-Za-z0-9 ]/', '', $s);
}
/** Valida e normaliza o valor enviado pelo formulário para um campo. Retorna string pronta para gravar. */
function ajusteLimpar(array $d, $bruto) {
    $bruto = is_array($bruto) ? '' : (string)$bruto;
    switch ($d['tipo']) {
        case 'chave':  return $bruto === '1' ? '1' : '0';
        case 'escolha': return isset($d['opcoes'][$bruto]) ? $bruto : $d['padrao'];
        case 'numero': $n = is_numeric(str_replace(',', '.', $bruto)) ? (float)str_replace(',', '.', $bruto) : (float)$d['padrao'];
                       $n = max($d['min'], min($d['max'], $n)); return (string)(floor($n) == $n ? (int)$n : round($n, 2));
        default:
            $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $bruto));
            if (!empty($d['ascii'])) $s = strtoupper(ajusteAscii($s));
            return mb_substr($s, 0, $d['max'] ?? 255);
    }
}
/** Cores/modo padrão do tema para visitantes (só entra se for diferente do padrão de fábrica). */
function padraoTema() {
    $p = [];
    if (ajuste('sys_tema_cor') !== 'verde') $p['cor'] = ajuste('sys_tema_cor');
    if (ajuste('sys_tema_modo') !== 'auto') $p['modo'] = ajuste('sys_tema_modo');
    return $p;
}
/** Faixa de aviso do site (vazia se desligada). */
function avisoSite() {
    $t = trim(ajuste('sys_aviso_texto'));
    if (ajuste('sys_aviso_ativo') !== '1' || $t === '') return '';
    return '<div class="alert alert-' . e(ajuste('sys_aviso_tipo')) . ' text-center py-2 mb-3" role="status">' . e($t) . '</div>';
}
/** Modo manutenção: visitantes veem a página de aviso; equipe logada, login e APIs/cron passam normalmente. */
function verificarManutencao() {
    if (PHP_SAPI === 'cli' || AREA !== 'site' || ajuste('sys_manutencao') !== '1') return;
    if (!empty($_SESSION['adm']) || !empty($_SESSION['gui'])) return;
    $s = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('~/(api|cron)/~', $s) || in_array(basename($s), ['login.php', 'logout.php', 'manifest.php'], true)) return;
    http_response_code(503); header('Retry-After: 3600');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e(SITE_NOME) . ' · Voltamos já</title>'
       . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(160deg,#cfeee9,#fdf3e3);font-family:system-ui,sans-serif;color:#1f2d3a;text-align:center;padding:1.5rem}'
       . '.c{max-width:30rem;background:#fff;border-radius:1.5rem;padding:2.5rem 2rem;box-shadow:0 12px 40px rgba(20,40,60,.12)}h1{margin:.4rem 0;font-size:1.6rem}p{color:#5b6b7a;line-height:1.5}a{color:#0f766e;font-size:.85rem}</style></head>'
       . '<body><div class="c"><div style="font-size:3.2rem">🛠️</div><h1>' . e(SITE_NOME) . ' está em manutenção</h1><p>' . e(ajuste('sys_manutencao_msg')) . '</p><a href="' . e(BASE) . 'login.php">Sou da equipe</a></div></body></html>';
    exit;
}
