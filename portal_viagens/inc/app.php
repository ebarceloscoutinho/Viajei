<?php
/**
 * app.php — sessão, autenticação, CSRF, helpers e layout do site (marca em SITE_NOME).
 * Páginas dentro de /admin devem fazer define('BASE','../') antes de incluir este arquivo.
 */
if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        session_start();
    }
    if (!headers_sent()) {   // cabeçalhos de segurança
        header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
} elseif (!isset($_SESSION)) { $_SESSION = []; }
if (!defined('BASE')) define('BASE', '');
/** Versão do CSS = data de modificação do arquivo: o navegador (e o service worker) baixa de novo quando ele muda. */
if (!defined('CSS_V')) define('CSS_V', (int)@filemtime(dirname(__DIR__) . '/css/site.css') ?: 1);
if (!defined('AREA')) define('AREA', 'site');   // 'site' (viajantes) ou 'admin' — áreas com sessões separadas
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/pix.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/qr.php';

const TIPO_VIAJANTE = 1, TIPO_GUIA = 2, TIPO_ADMIN = 3, TIPO_FINANCEIRO = 4, TIPO_ATENDIMENTO = 5, TIPO_CATALOGO = 6;   // 3 a 6 = cargos do painel admin
const WEBHOOK_SEGREDO = '';        // segredo compartilhado do webhook de pagamento (vazio = webhook desativado)
const TAXA_PASSAPORTE_BRL = 257.25;                // valor de referência da emissão de passaporte (confirme na Polícia Federal)
const FIN_VENDA = 1, FIN_OUTRAS_RECEITAS = 2, FIN_REEMBOLSO = 3, FIN_GUIA = 4;   // ids de categorias_financeiras
// Recuperação de senha: em modo demo o link aparece na tela (servidor local não envia e-mail).
// DEIXE false EM PRODUÇÃO — com true, qualquer pessoa consegue redefinir a senha de qualquer conta.
const MODO_DEMO_EMAIL = true;
// Ajustes editáveis em Admin → Configurações (valores padrão em inc/ajustes.php): nome do site, prazos, taxas, PIX, e-mail…
require_once __DIR__ . '/ajustes.php';
define('SITE_NOME', ajuste('sys_site_nome'));
define('SITE_SLOGAN', ajuste('sys_site_slogan'));
define('TAXA_CANCELAMENTO', (float)ajuste('sys_taxa_cancelamento') / 100);   // cobrada se cancelar a menos de DIAS_SEM_TAXA dias da saída
define('DIAS_SEM_TAXA', (int)ajuste('sys_dias_sem_taxa'));
define('DIAS_ARREPENDIMENTO', (int)ajuste('sys_dias_arrependimento'));       // direito de arrependimento (confirme os prazos com seu advogado)
define('PRAZO_PAGAMENTO_HORAS', (int)ajuste('sys_prazo_pagamento_horas'));
define('BLOQUEIO_HORAS', (int)ajuste('sys_bloqueio_horas'));
define('GUIA_PERCENTUAL', (float)ajuste('sys_guia_percentual') / 100);       // repasse previsto ao guia sobre cada venda confirmada
define('AVALIACAO_EXIGE_VIAGEM_REALIZADA', ajuste('sys_avaliacao_exige_viagem') === '1');
define('EMAIL_ATIVO', ajuste('sys_email_ativo') === '1');
define('PIX_CHAVE', ajuste('sys_pix_chave'));
define('PIX_FAVORECIDO', ajuste('sys_pix_favorecido'));
define('PIX_CIDADE', ajuste('sys_pix_cidade'));
define('DADOS_TRANSFERENCIA', ajuste('sys_dados_transferencia'));
verificarManutencao();

/* ---------- helpers ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dataBr($d) { return $d ? date('d/m/Y', strtotime($d)) : ''; }
function redirecionar($url) { header('Location: ' . $url); exit; }
function flash($msg, $tipo = 'success') { $_SESSION['flash'] = [$msg, $tipo]; }

/* ---------- autenticação ---------- */
function usuario() { return $_SESSION['user'] ?? null; }
function exigirLogin() {
    if (!usuario()) { $_SESSION['voltar'] = $_SERVER['REQUEST_URI']; redirecionar(BASE . 'login.php'); }
}
function registrarAcesso(PDO $c, $email, $ok, $uid = null, $area = 'site') {
    $c->prepare('INSERT INTO logs_acesso (usuario_id,email,sucesso,area,ip) VALUES (?,?,?,?,?)')
      ->execute([$uid, $email, $ok ? 1 : 0, $area, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/* ---------- CSRF ---------- */
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfCampo() { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function csrfVerificar() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Requisição inválida (CSRF). Volte e tente novamente.');
    }
}


/* ---------- utilidades: fila de espera, cupons, paginação, CSV ---------- */
function senhaTemporaria() { return substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10); }
/** Gera o token de verificação e enfileira o e-mail. Retorna o link (útil no modo demonstração). */
function enviarVerificacao(PDO $c, $uid, $email, $nome) {
    $token = bin2hex(random_bytes(32));
    $c->prepare('UPDATE tokens_email SET usado=1 WHERE usuario_id=?')->execute([$uid]);
    $c->prepare('INSERT INTO tokens_email (usuario_id,token_hash,expira) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 48 HOUR))')->execute([$uid, hash('sha256', $token)]);
    $url = siteUrl() . '/verificar_email.php?token=' . $token;
    enviarEvento('verificar_email', $email, ['nome' => $nome, 'url' => $url], $uid);
    return $url;
}
/** Marca o 1º da lista de espera como notificado e enfileira o e-mail "vaga liberada". */
function notificarFila(PDO $c, $saidaId) {
    $st = $c->prepare("SELECT w.id, w.usuario_id, u.nome, u.email, p.id AS pid, p.titulo, s.data_saida FROM lista_espera w JOIN usuarios u ON u.id=w.usuario_id JOIN saidas s ON s.id=w.saida_id JOIN passeios p ON p.id=s.passeio_id WHERE w.saida_id=? AND w.status='aguardando' ORDER BY w.created_at LIMIT 1");
    $st->execute([$saidaId]); $w = $st->fetch(); if (!$w) return;
    $c->prepare("UPDATE lista_espera SET status='notificado' WHERE id=?")->execute([$w['id']]);
    enviarEvento('vaga_liberada', $w['email'], ['nome' => $w['nome'], 'titulo' => $w['titulo'], 'data' => $w['data_saida'], 'passeio_id' => $w['pid']], $w['usuario_id']);
}
function cupomValido(PDO $c, $codigo) {
    $st = $c->prepare("SELECT c.*, (SELECT COUNT(*) FROM reservas r WHERE r.cupom_id=c.id AND r.status IN ('aguardando_pagamento','em_analise','confirmada')) AS usos FROM cupons c WHERE c.codigo=? AND c.ativo=1 AND (c.validade IS NULL OR c.validade >= CURDATE())");
    $st->execute([strtoupper(trim($codigo))]); $cp = $st->fetch();
    return ($cp && ($cp['usos_max'] === null || (int)$cp['usos'] < (int)$cp['usos_max'])) ? $cp : null;
}
function paginar($total, $por = 40) {
    $pags = max(1, (int)ceil($total / $por)); $pag = min($pags, max(1, (int)($_GET['p'] ?? 1)));
    return [($pag - 1) * $por, $pag, $pags];
}
function paginacaoHtml($pag, $pags) {
    if ($pags <= 1) return '';
    $link = function ($n, $txt, $ativo = false, $off = false) { $q = $_GET; $q['p'] = $n; return '<li class="page-item' . ($ativo ? ' active' : '') . ($off ? ' disabled' : '') . '"><a class="page-link" href="?' . e(http_build_query($q)) . '">' . $txt . '</a></li>'; };
    $h = '<nav aria-label="Páginas"><ul class="pagination pagination-sm justify-content-center flex-wrap">' . $link($pag - 1, '‹', false, $pag <= 1);
    for ($i = max(1, $pag - 2); $i <= min($pags, $pag + 2); $i++) $h .= $link($i, $i, $i === $pag);
    return $h . $link($pag + 1, '›', false, $pag >= $pags) . '</ul></nav>';
}
/** Envia um CSV (separador ; e BOM UTF-8, abre direto no Excel BR) e encerra. Células que começam com = + - @ são neutralizadas. */
function csvSaida($arquivo, array $cabecalho, array $linhas) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $arquivo) . '"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    $seguro = fn($v) => (is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v;
    fputcsv($o, $cabecalho, ';'); foreach ($linhas as $l) fputcsv($o, array_map($seguro, $l), ';');
    fclose($o); exit;
}
function redimensionarImagem($caminho, $ext, $max = 1600) {   // reduz fotos grandes (usa GD, se disponível)
    if (!function_exists('imagecreatetruecolor')) return;
    [$w, $h] = @getimagesize($caminho) ?: [0, 0]; if ($w <= $max || $h <= 0) return;
    $abrir = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$ext] ?? null;
    if (!$abrir || !function_exists($abrir)) return;
    $src = @$abrir($caminho); if (!$src) return;
    $nh = (int)round($h * $max / $w); $dst = imagecreatetruecolor($max, $nh);
    if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $max, $nh, $w, $h);
    if ($ext === 'jpg') imagejpeg($dst, $caminho, 85); elseif ($ext === 'png') imagepng($dst, $caminho, 8); elseif (function_exists('imagewebp')) imagewebp($dst, $caminho, 85);
    imagedestroy($src); imagedestroy($dst);
}

/* ---------- financeiro / avaliações ---------- */
function lancar(PDO $c, $cat, $desc, $valor, $pago = 1, $reserva = null, $por = null) {
    if ($valor <= 0) return;
    $c->prepare('INSERT INTO lancamentos (data_lanc,categoria_id,descricao,valor,pago,reserva_id,criado_por) VALUES (CURDATE(),?,?,?,?,?,?)')
      ->execute([$cat, mb_substr($desc, 0, 200), round($valor, 2), $pago ? 1 : 0, $reserva, $por]);
}
function estrelas($n) { $n = (int)round($n); return '<span class="estrelas" aria-label="' . $n . ' de 5">' . str_repeat('★', $n) . str_repeat('☆', 5 - $n) . '</span>'; }

/* ---------- reservas: status, expiração e notificações ---------- */
function statusReserva($s) {
    $m = ['aguardando_pagamento' => ['warning', 'Aguardando pagamento'], 'em_analise' => ['info', 'Pagamento em análise'],
          'confirmada' => ['success', 'Confirmada'], 'cancelada' => ['secondary', 'Cancelada'], 'expirada' => ['dark', 'Expirada']];
    [$c, $t] = $m[$s] ?? ['secondary', $s];
    return '<span class="badge text-bg-' . $c . '">' . e($t) . '</span>';
}
function pagamentosPendentes(PDO $c) { return (int)$c->query("SELECT COUNT(*) FROM pagamentos WHERE status='informado'")->fetchColumn(); }
function tempoRestante($min) { $min = max(0, (int)$min); return floor($min / 60) . 'h ' . ($min % 60) . 'min'; }
/** Reservas sem pagamento informado após o prazo: expiram, liberam vagas e bloqueiam o usuário temporariamente. */
function expirarReservas(PDO $c) {
    try {
        if (!(int)$c->query("SELECT COUNT(*) FROM reservas WHERE status='aguardando_pagamento' AND prazo_pagamento < NOW()")->fetchColumn()) return;
        $c->beginTransaction();
        $lista = $c->query("SELECT id,usuario_id,saida_id,qtd FROM reservas WHERE status='aguardando_pagamento' AND prazo_pagamento < NOW() FOR UPDATE")->fetchAll();
        foreach ($lista as $r) {
            $c->prepare("UPDATE reservas SET status='expirada' WHERE id=?")->execute([$r['id']]);
            $c->prepare('UPDATE saidas SET vagas_disponiveis = vagas_disponiveis + ? WHERE id=?')->execute([$r['qtd'], $r['saida_id']]);
            $c->prepare('UPDATE usuarios SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id=?')->execute([BLOQUEIO_HORAS, $r['usuario_id']]);
            notificarFila($c, $r['saida_id']);
        }
        $c->commit();
        foreach ($lista as $r) {
            $x = $c->prepare('SELECT u.nome,u.email,p.titulo FROM usuarios u, saidas s JOIN passeios p ON p.id=s.passeio_id WHERE u.id=? AND s.id=?'); $x->execute([$r['usuario_id'], $r['saida_id']]);
            if ($i = $x->fetch()) enviarEvento('reserva_expirada', $i['email'], ['nome' => $i['nome'], 'titulo' => $i['titulo'], 'id' => $r['id']], $r['usuario_id']);
        }
    } catch (Throwable $e) { if ($c->inTransaction()) $c->rollBack(); }
}
expirarReservas($conn);

/* ---------- consultas / componentes reutilizáveis ---------- */
function buscarPasseios(PDO $c, $where = '', array $params = [], $order = 'p.id DESC', $limit = 100) {
    $sql = "SELECT p.*, cat.nome AS categoria, cat.icone, g.nome AS guia, g.foto AS guia_foto, g.idiomas AS guia_idiomas, g.bio AS guia_bio,
              (SELECT MIN(s.data_saida) FROM saidas s WHERE s.passeio_id=p.id AND s.data_saida>=CURDATE()) AS proxima,
              (SELECT COALESCE(SUM(s.vagas_disponiveis),0) FROM saidas s WHERE s.passeio_id=p.id AND s.data_saida>=CURDATE()) AS vagas,
              (SELECT ROUND(AVG(a.nota),1) FROM avaliacoes a WHERE a.passeio_id=p.id AND a.visivel=1) AS nota_media,
              (SELECT COUNT(*) FROM avaliacoes a WHERE a.passeio_id=p.id AND a.visivel=1) AS n_aval,
              (SELECT s.vagas_disponiveis FROM saidas s WHERE s.passeio_id=p.id AND s.data_saida>=CURDATE() ORDER BY s.data_saida LIMIT 1) AS prox_vagas
            FROM passeios p JOIN categorias cat ON cat.id=p.categoria_id LEFT JOIN guias g ON g.id=p.guia_id
            WHERE p.ativo=1 $where ORDER BY $order LIMIT " . (int)$limit;
    $st = $c->prepare($sql); $st->execute($params); return $st->fetchAll();
}
function motivos() { static $m = null; return $m ??= require __DIR__ . '/motivos.php'; }
/** Caminho de imagem (uploads/...) ou URL http(s) -> URL utilizável na página. */
function urlImagem($c) { $c = trim((string)$c); if ($c === '') return ''; return preg_match('~^https?://~i', $c) ? $c : BASE . ltrim($c, '/'); }
function paletas() { static $p = null; return $p ??= require __DIR__ . '/paletas.php'; }
/** Cores do chão da capa (gramado, areia, mar, neve). */
function solos() {
    return ['grama' => ['#9ad98f', '#56ad66'], 'areia' => ['#f8dc9a', '#e6b265'], 'mar' => ['#8bdcf5', '#3f9fd8'], 'neve' => ['#ffffff', '#d2e5f5']];
}
/** Variáveis CSS (--m1..--m4, --g1/--g2) com as cores do ponto turístico; vazio se o motivo não existir. */
function coresMotivo($motivo) {
    $pal = paletas();
    if (!isset($pal[$motivo])) return '';
    $p = $pal[$motivo]; $solos = solos(); $s = $solos[$p[4]] ?? $solos['grama'];
    return '--m1:' . $p[0] . ';--m2:' . $p[1] . ';--m3:' . $p[2] . ';--m4:' . $p[3] . ';--g1:' . $s[0] . ';--g2:' . $s[1] . ';';
}
/** Emojis do lugar, cada um num "adesivo" redondo (até 3). Alfinetes 📍📌 são ignorados: não dizem nada sobre o destino. */
function emojisCapa($emoji) {
    $txt = str_replace(["\u{1F4CD}", "\u{1F4CC}"], '', (string)$emoji);
    preg_match_all('/\X/u', $txt, $m);
    $itens = [];
    foreach ($m[0] as $g) { if (trim($g) !== '') $itens[] = '<b>' . e($g) . '</b>'; if (count($itens) === 3) break; }
    return $itens ? '<span class="emojis">' . implode('', $itens) . '</span>' : '';
}
/**
 * Capa ilustrada: céu pastel + sol e nuvens + ponto turístico colorido + gramado/areia/mar + emojis do lugar.
 * Se houver foto, ela cobre a ilustração.
 */
function capa($emoji, $hue, $classe = '', $motivo = '', $imagem = '', $selo = '') {
    $h = (int)$hue; $h2 = ($h + 40) % 360; $m = motivos();
    $svg = isset($m[$motivo]) ? '<svg class="cena" viewBox="0 0 120 70" preserveAspectRatio="xMidYMax meet" aria-hidden="true">' . $m[$motivo] . '</svg>' : '';
    $u = urlImagem($imagem);
    $img = $u ? '<img class="foto" src="' . e($u) . '" alt="" loading="lazy" onerror="this.remove()">' : '';
    return '<div class="capa ' . e($classe) . '" style="--c1:hsl(' . $h . ' 60% 70%);--c2:hsl(' . $h2 . ' 80% 88%);' . coresMotivo($motivo) . '"><i class="sol"></i><i class="nuvem n1"></i><i class="nuvem n2"></i>'
         . $svg . $img . emojisCapa($emoji) . ($selo !== '' ? '<span class="selo">' . e($selo) . '</span>' : '') . '</div>';
}
/** Avatar do guia: foto enviada ou círculo com as iniciais. */
function avatar($nome, $foto = '', $px = 64) {
    $u = urlImagem($foto);
    if ($u) return '<img class="avatar" src="' . e($u) . '" width="' . $px . '" height="' . $px . '" alt="' . e($nome) . '" loading="lazy">';
    $p = preg_split('/\s+/', trim((string)$nome)); $ini = mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    return '<span class="avatar" role="img" aria-label="' . e($nome) . '" style="width:' . $px . 'px;height:' . $px . 'px;font-size:' . round($px * .38) . 'px;background:hsl(' . (crc32($nome) % 360) . ' 42% 52%)">' . e($ini) . '</span>';
}
/** Salva imagem enviada (JPG/PNG/WebP até 3 MB) em uploads/<pasta>/ com nome aleatório. Retorna o caminho ou null se nada foi enviado. */
function salvarImagem($campo, $pasta) {
    if (empty($_FILES[$campo]['name']) || ($_FILES[$campo]['error'] ?? 0) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024) throw new Exception('Imagem inválida ou maior que 3 MB.');
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) throw new Exception('Use uma imagem JPG, PNG ou WebP.');
    $dir = dirname(__DIR__) . '/uploads/' . $pasta; if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nome = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nome")) throw new Exception('Não foi possível salvar a imagem (verifique a permissão da pasta uploads).');
    redimensionarImagem("$dir/$nome", $ext);
    return 'uploads/' . $pasta . '/' . $nome;
}
/** Valor final do campo de imagem: arquivo enviado > URL digitada > valor atual (ou null se pediu para remover). */
function imagemDoForm($arq, $urlCampo, $pasta, $atual = null) {
    if (!empty($_POST['remover_' . $arq])) return null;
    if ($novo = salvarImagem($arq, $pasta)) return $novo;
    $url = trim($_POST[$urlCampo] ?? '');
    if ($url !== '') {
        if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) throw new Exception('URL de imagem inválida (use http:// ou https://).');
        return mb_substr($url, 0, 255);
    }
    return $atual;
}
/** Valor de configuracoes (contatos do suporte etc.). */
function config($chave, $padrao = '') {
    static $c = null; global $conn;
    if ($c === null) { try { $c = $conn->query('SELECT chave,valor FROM configuracoes')->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $e) { $c = []; } }
    return $c[$chave] ?? $padrao;
}
function skylineHero() {   // faixa de monumentos famosos no rodapé do hero
    $m = motivos(); $x = 0; $o = '<svg class="hero-skyline" viewBox="0 0 840 70" preserveAspectRatio="xMidYMax slice" aria-hidden="true">';
    foreach (['cristo', 'colosseu', 'eiffel', 'piramide', 'fuji', 'opera', 'taj'] as $k) { $o .= '<g transform="translate(' . $x . ' 0)" style="' . coresMotivo($k) . '">' . $m[$k] . '</g>'; $x += 120; }
    return $o . '</svg>';
}
function cardPasseio(array $p) {
    $lotado = $p['proxima'] && (int)$p['vagas'] === 0;
    $ultimas = !$lotado && $p['proxima'] && (int)$p['prox_vagas'] >= 1 && (int)$p['prox_vagas'] <= 3;
    $badge = !$p['proxima'] ? '<span class="badge text-bg-secondary">Sem datas</span>'
           : ($lotado ? '<span class="badge text-bg-danger">Lotado</span>'
           : ($ultimas ? '<span class="badge text-bg-warning">🔥 Últimas vagas</span>' : '<span class="badge text-bg-success">' . (int)$p['vagas'] . ' vagas</span>'));
    $intl = ($p['escopo'] ?? '') === 'internacional';
    $dur = (int)$p['dias'] > 1 ? (int)$p['dias'] . ' dias' : 'Day tour';
    $nota = (int)$p['n_aval'] > 0 ? '<div class="small">' . estrelas($p['nota_media']) . ' <span class="text-body-secondary">' . number_format($p['nota_media'], 1, ',', '') . ' (' . (int)$p['n_aval'] . ')</span></div>' : '';
    return '<div class="col"><a class="card h-100 card-hover text-decoration-none" href="' . BASE . 'passeio.php?id=' . (int)$p['id'] . '">'
        . capa($p['emoji'], $p['hue'], '', $p['motivo'] ?? '', $p['imagem'] ?? '', $intl ? $p['pais'] : 'Brasil')
        . '<div class="card-body d-flex flex-column"><div class="small text-body-secondary mb-1">' . ($intl ? '🌍 ' : '🇧🇷 ') . e($intl ? $p['pais'] : $p['destino']) . ' · ' . e($p['icone']) . ' ' . e($p['categoria']) . ' · ' . $dur . '</div>'
        . '<h3 class="h6 card-title text-body">' . e($p['titulo']) . '</h3>' . $nota
        . '<div class="mt-auto d-flex justify-content-between align-items-center pt-2"><strong class="text-primary">' . brl($p['preco']) . '</strong>' . $badge . '</div></div></a></div>';
}

/**
 * Entrada única da equipe: guia/administrador que digita e-mail e senha no login NORMAL do site vai direto para o painel certo.
 * Mantém as sessões separadas ($_SESSION['gui'] / ['adm']) e o 2FA do admin. Retorna a URL de destino, ou null se a conta não tem acesso ao painel.
 */
function entrarComoEquipe(PDO $c, array $u) {
    $tipo = (int)$u['tipo_user'];
    $tema = array_filter(['modo' => $u['tema_modo'] ?? null, 'cor' => $u['tema_cor'] ?? null, 'fonte' => $u['tema_fonte'] ?? null]);
    if ($tipo === TIPO_GUIA) {
        $g = $c->prepare('SELECT id FROM guias WHERE usuario_id=? AND ativo=1 LIMIT 1'); $g->execute([$u['id']]); $gid = (int)$g->fetchColumn();
        if (!$gid) return null;
        session_regenerate_id(true);
        $_SESSION['gui'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'guia_id' => $gid]; $_SESSION['tema_gui'] = $tema;
        registrarAcesso($c, $u['email'], true, $u['id'], 'guia');
        return 'guia/index.php';
    }
    if (!in_array($tipo, [TIPO_ADMIN, TIPO_FINANCEIRO, TIPO_ATENDIMENTO, TIPO_CATALOGO], true)) return null;
    if (!empty($u['totp_ativo']) && !empty($u['totp_secret'])) {   // 2FA: o código é pedido na tela do admin
        $_SESSION['adm_pend'] = ['id' => (int)$u['id'], 'email' => $u['email'], 'exp' => time() + 300];
        return 'admin/login.php';
    }
    session_regenerate_id(true);
    $_SESSION['adm'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'tipo' => $tipo]; $_SESSION['tema_adm'] = $tema;
    registrarAcesso($c, $u['email'], true, $u['id'], 'admin');
    return 'admin/index.php';
}

/* ---------- layout ---------- */
function headTema() {
    $k = ['admin' => 'adm', 'guia' => 'gui'][AREA] ?? 'user';
    $logado = !empty($_SESSION[$k]);
    $d = ['prefs' => $_SESSION['tema_' . ($k === 'user' ? 'site' : $k)] ?? null, 'pad' => AREA === 'site' ? padraoTema() : new stdClass, 'url' => $logado ? BASE . 'api/tema.php' : null, 'csrf' => csrf(), 'area' => AREA];
    return '<script>window.PV_TEMA=' . json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script>';
}
function temaMenu() {
    $cores = ['verde' => '#0f766e', 'azul' => '#1d4ed8', 'roxo' => '#7c3aed', 'rosa' => '#be185d', 'laranja' => '#c2410c', 'grafite' => '#334155'];
    $h = '<div class="dropdown"><button class="btn btn-outline-secondary btn-sm" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Personalizar tema" title="Meu tema">🎨</button>'
       . '<div class="dropdown-menu dropdown-menu-end p-3" style="min-width:250px"><div class="fw-semibold mb-2">Meu tema</div><div class="small fw-semibold mb-1">Modo</div><div class="btn-group btn-group-sm w-100 mb-3">'
       . '<button type="button" class="btn btn-outline-secondary" data-tema="modo:light">☀️ Claro</button><button type="button" class="btn btn-outline-secondary" data-tema="modo:auto">Auto</button><button type="button" class="btn btn-outline-secondary" data-tema="modo:dark">🌙 Escuro</button></div>'
       . '<div class="small fw-semibold mb-1">Cor</div><div class="d-flex gap-2 mb-3">';
    foreach ($cores as $k => $c) $h .= '<button type="button" class="swatch" data-tema="cor:' . $k . '" style="background:' . $c . '" title="' . ucfirst($k) . '"></button>';
    $logado = !empty($_SESSION[['admin' => 'adm', 'guia' => 'gui'][AREA] ?? 'user']);
    return $h . '</div><div class="small fw-semibold mb-1">Tamanho do texto</div><div class="btn-group btn-group-sm w-100 mb-2">'
       . '<button type="button" class="btn btn-outline-secondary" data-tema="fonte:normal">Normal</button><button type="button" class="btn btn-outline-secondary" data-tema="fonte:grande">Grande</button></div>'
       . '<div class="form-text">' . ($logado ? 'Salvo na sua conta: vale em qualquer aparelho.' : 'Entre na sua conta para salvar o tema em todos os aparelhos.') . '</div>'
       . '<button type="button" class="btn btn-sm btn-primary w-100 mt-2 btn-instalar d-none">📲 Instalar app</button></div></div>';
}
function topo($titulo = '') {
    global $conn;
    $u = usuario(); $b = BASE; $nome = SITE_NOME; $pg = basename($_SERVER['SCRIPT_NAME']);
    $titulo = $titulo ?: SITE_SLOGAN;
    echo '<!doctype html><html lang="pt-BR" data-bs-theme="light"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>' . e($titulo) . ' · ' . e($nome) . '</title><meta name="description" content="' . e($GLOBALS['descricao'] ?? (SITE_SLOGAN . ' Viagens nacionais e internacionais, roteiros, guias locais e custo total da viagem.')) . '">'
       . '<meta name="theme-color" content="#0f766e"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="' . e($nome) . '">'
       . "<link rel=\"manifest\" href=\"{$b}manifest.php\"><link rel=\"icon\" href=\"{$b}assets/img/favicon.png\"><link rel=\"apple-touch-icon\" href=\"{$b}assets/img/apple-touch-icon.png\">"
       . headTema() . "<script src=\"{$b}assets/js/tema.js\"></script>"
       . "<link href=\"{$b}assets/dist/css/bootstrap.min.css\" rel=\"stylesheet\"><link href=\"{$b}css/site.css?v=" . CSS_V . "\" rel=\"stylesheet\"></head><body><a class=\"visually-hidden-focusable btn btn-primary m-2\" href=\"#conteudo\">Pular para o conteúdo</a>"
       . '<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top"><div class="container">'
       . "<a class=\"navbar-brand fw-bold d-flex align-items-center gap-2\" href=\"{$b}index.php\"><img src=\"{$b}assets/img/logo.svg\" width=\"30\" height=\"30\" alt=\"\">" . e($nome) . '</a>'
       . '<div class="d-flex align-items-center gap-2 order-lg-last">' . temaMenu()
       . '<button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><span class="navbar-toggler-icon"></span></button></div>'
       . '<div class="collapse navbar-collapse" id="nav"><ul class="navbar-nav me-auto">'
       . "<li class=\"nav-item\"><a class=\"nav-link\" href=\"{$b}passeios.php?escopo=nacional\">🇧🇷 Nacionais</a></li>"
       . "<li class=\"nav-item\"><a class=\"nav-link\" href=\"{$b}passeios.php?escopo=internacional\">🌍 Internacionais</a></li>"
       . "<li class=\"nav-item\"><a class=\"nav-link\" href=\"{$b}roteiros.php\">Roteiros</a></li>"
       . "<li class=\"nav-item\"><a class=\"nav-link\" href=\"{$b}quiz_custo.php\">💰 Quanto custa?</a></li>"
       . "<li class=\"nav-item\"><a class=\"nav-link\" href=\"{$b}equipe.php\">Nossos guias</a></li><li class=\"nav-item\"><a class=\"nav-link\" href=\"{$b}suporte.php\">Suporte</a></li></ul><div class=\"d-flex gap-2 align-items-center py-2 py-lg-0\">";
    if (!empty($_SESSION['comparar'])) echo "<a class=\"btn btn-outline-secondary btn-sm\" href=\"{$b}comparar.php\">⚖ Comparar (" . count($_SESSION['comparar']) . ')</a>';
    if ($u) {
        $nn = 0; try { $qn = $conn->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id=? AND lida=0'); $qn->execute([$u['id']]); $nn = (int)$qn->fetchColumn(); } catch (Throwable $e) {}
        echo "<a class=\"btn btn-outline-secondary btn-sm position-relative\" href=\"{$b}notificacoes.php\" title=\"Notificações\" aria-label=\"Notificações\">🔔" . ($nn ? '<span class="badge text-bg-danger position-absolute top-0 start-100 translate-middle rounded-pill">' . $nn . '</span>' : '') . '</a>';
        echo '<div class="dropdown"><button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Olá, ' . e(explode(' ', $u['nome'])[0]) . '</button><ul class="dropdown-menu dropdown-menu-end">'
           . "<li><a class=\"dropdown-item\" href=\"{$b}minhas_reservas.php\">Minhas reservas</a></li>"
           . "<li><a class=\"dropdown-item\" href=\"{$b}favoritos.php\">♥ Favoritos</a></li>"
           . "<li><a class=\"dropdown-item\" href=\"{$b}minha_conta.php\">Minha conta</a></li>"
           . "<li><a class=\"dropdown-item\" href=\"{$b}roteiros.php#meus\">Meus roteiros</a></li>"
           . "<li><hr class=\"dropdown-divider\"></li><li><a class=\"dropdown-item\" href=\"{$b}logout.php\">Sair</a></li></ul></div>";
    } else {
        echo "<a class=\"btn btn-outline-primary btn-sm\" href=\"{$b}login.php\">Entrar</a><a class=\"btn btn-primary btn-sm\" href=\"{$b}cadastro.php\">Criar conta</a>";
    }
    echo '</div></div></div></nav><main class="container py-4" id="conteudo">' . avisoSite();
    if ($u && isset($u['verificado']) && !$u['verificado']) echo '<div class="alert alert-warning small">✉️ Confirme seu e-mail para poder reservar. <a href="' . $b . 'minha_conta.php#verificacao">Reenviar o e-mail de confirmação</a></div>';
    if (!empty($_SESSION['flash'])) {
        [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($t) . ' alert-dismissible fade show">' . e($m) . '<button class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}
function rodape() {
    $b = BASE; $pg = basename($_SERVER['SCRIPT_NAME']);
    $itens = [['index.php', '🏠', 'Início'], ['passeios.php', '🧭', 'Viagens'], ['roteiros.php', '🗺️', 'Roteiros'], ['quiz_custo.php', '💰', 'Custo'],
              [usuario() ? 'minhas_reservas.php' : 'login.php', '👤', usuario() ? 'Conta' : 'Entrar']];
    $zap = soDigitos(config('suporte_whatsapp')); if ($zap !== '' && strlen($zap) <= 11) $zap = '55' . $zap;
    echo '</main><footer class="rodape mt-5"><div class="container py-4"><div class="row g-4">'
       . '<div class="col-md-4"><div class="d-flex align-items-center gap-2 mb-2"><img src="' . $b . 'assets/img/logo.svg" width="32" height="32" alt=""><strong class="fs-5 titulo">' . e(SITE_NOME) . '</strong></div><p class="small text-body-secondary mb-2">' . e(SITE_SLOGAN) . ' Roteiros, passeios e guias locais para viajar com tranquilidade.</p>'
       . '<button type="button" class="btn btn-sm btn-outline-primary btn-instalar d-none">📲 Instalar app</button></div>'
       . '<div class="col-6 col-md-3"><div class="fw-semibold mb-2">Explorar</div><ul class="list-unstyled small vstack gap-1 mb-0"><li><a href="' . $b . 'passeios.php?escopo=nacional">Viagens nacionais</a></li><li><a href="' . $b . 'passeios.php?escopo=internacional">Viagens internacionais</a></li><li><a href="' . $b . 'roteiros.php">Roteiros</a></li><li><a href="' . $b . 'equipe.php">Nossos guias</a></li><li><a href="' . $b . 'guias.php">Dicas de destino</a></li><li><a href="' . $b . 'privacidade.php">Privacidade</a></li><li><a href="' . $b . 'termos.php">Termos de uso</a></li></ul></div>'
       . '<div class="col-6 col-md-5"><div class="fw-semibold mb-2">🛟 Suporte</div><ul class="list-unstyled small vstack gap-1 mb-0">'
       . '<li>📞 ' . e(config('suporte_telefone')) . '</li>' . ($zap ? '<li>💬 <a href="https://wa.me/' . e($zap) . '" target="_blank" rel="noopener">WhatsApp ' . e(config('suporte_whatsapp')) . '</a></li>' : '')
       . '<li>✉️ <a href="mailto:' . e(config('suporte_email')) . '">' . e(config('suporte_email')) . '</a></li><li>🕘 ' . e(config('suporte_horario')) . '</li><li><a href="' . $b . 'suporte.php">Abrir um chamado →</a></li><li class="pt-2">🔐 Equipe: <a href="' . $b . 'admin/login.php">Painel admin</a> · <a href="' . $b . 'guia/login.php">Painel do guia</a></li></ul></div>'
       . '</div><div class="small text-body-secondary text-center mt-4">© ' . date('Y') . ' ' . e(SITE_NOME) . ' · Projeto acadêmico, dados de exemplo fictícios.</div></div></footer>'
       . '<nav class="bottom-nav d-lg-none" aria-label="Navegação principal">';
    foreach ($itens as [$href, $ico, $txt]) echo '<a href="' . $b . $href . '" class="' . ($pg === $href || ($href === 'minhas_reservas.php' && in_array($pg, ['pagamento.php', 'favoritos.php'])) ? 'ativo' : '') . '"><span>' . $ico . '</span>' . $txt . '</a>';
    echo '</nav><script>window.PV_SW=' . json_encode($b . 'sw.js') . ';addEventListener("keydown",function(e){if(e.altKey&&e.shiftKey&&e.code==="KeyA"){location.href=' . json_encode($b . 'admin/login.php') . ';}});</script><script src="' . $b . 'assets/dist/js/bootstrap.bundle.min.js"></script><script src="' . $b . 'assets/js/pwa.js"></script></body></html>';
}
