<?php
/**
 * E-mails transacionais + notificações dentro do site. Toda mensagem entra na fila (tabela emails_fila) e é enviada ao fim da
 * requisição (ou pelo cron/expirar_reservas.php). Sem SMTP/sendmail no servidor, fica como "falhou" e pode ser reenviada em Admin → E-mails.
 */
const EMAIL_TENTATIVAS_MAX = 5;

function siteUrl() {
    $c = trim(config('site_url')); if ($c !== '') return rtrim($c, '/');
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) return '';
    $dir = preg_replace('~/(admin|guia|api)$~', '', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));
    return (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir;
}
function emailTpl($titulo, array $linhas, $btnUrl = '', $btnTxt = '') {
    $h = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:auto;color:#2b3a42;line-height:1.5">'
       . '<div style="background:#1f7a6d;color:#fff;padding:14px 20px;border-radius:12px 12px 0 0;font-size:20px"><strong>' . e(SITE_NOME) . '</strong> <span style="opacity:.8;font-size:13px">' . e(SITE_SLOGAN) . '</span></div>'
       . '<div style="border:1px solid #e8dfd0;border-top:0;padding:20px;border-radius:0 0 12px 12px"><h3 style="margin-top:0">' . e($titulo) . '</h3>';
    foreach ($linhas as $l) $h .= '<p>' . $l . '</p>';
    if ($btnUrl !== '') $h .= '<p><a href="' . e($btnUrl) . '" style="background:#f0925b;color:#fff;padding:10px 18px;border-radius:24px;text-decoration:none;font-weight:bold;display:inline-block">' . e($btnTxt) . '</a></p>';
    return $h . '<hr style="border:0;border-top:1px solid #eee"><p style="font-size:12px;color:#777">Você recebeu este e-mail por ter uma conta na ' . e(SITE_NOME) . '. Dúvidas: ' . e(config('suporte_email')) . '</p></div></div>';
}
/** Notificação exibida no sino do site (página notificacoes.php). */
function criarNotificacao($usuarioId, $titulo, $mensagem = null, $url = null) {
    global $conn;
    try { $conn->prepare('INSERT INTO notificacoes (usuario_id,titulo,mensagem,url) VALUES (?,?,?,?)')->execute([$usuarioId, mb_substr($titulo, 0, 150), $mensagem === null ? null : mb_substr($mensagem, 0, 255), $url]); } catch (Throwable $e) {}
}
/** Monta e enfileira o e-mail de um evento (e cria a notificação interna, quando há usuário). $d: dados em texto puro (escapados aqui). */
function enviarEvento($evento, $para, array $d, $usuarioId = null) {
    $u = siteUrl(); $n = e($d['nome'] ?? ''); $v = isset($d['valor']) ? brl($d['valor']) : ''; $rel = ''; $notif = null;
    switch ($evento) {
        case 'boas_vindas': $a = 'Bem-vindo(a) à ' . SITE_NOME; $rel = 'passeios.php'; $h = emailTpl('Sua conta foi criada', ["Olá, $n! Que bom ter você aqui.", 'Explore viagens nacionais e internacionais, monte seu roteiro e calcule o custo da viagem.'], $u . '/' . $rel, 'Explorar viagens'); break;
        case 'verificar_email': $a = 'Confirme seu e-mail — ' . SITE_NOME; $h = emailTpl('Confirme seu e-mail', ["Olá, $n! Falta só um passo: confirme que este e-mail é seu para poder reservar viagens. O link vale por 48 horas."], $d['url'], 'Confirmar meu e-mail'); break;
        case 'reserva_criada': $a = 'Reserva #' . $d['id'] . ' — pague em até ' . PRAZO_PAGAMENTO_HORAS . 'h'; $rel = 'pagamento.php?id=' . (int)$d['id']; $notif = 'Pague até ' . $d['prazo'] . ' para confirmar a reserva de ' . $d['titulo'] . '.';
            $h = emailTpl('Vaga reservada!', ["Olá, $n! Reservamos " . (int)$d['qtd'] . ' vaga(s) em <strong>' . e($d['titulo']) . '</strong> (saída em ' . dataBr($d['data']) . ").", "Total: <strong>$v</strong>" . (!empty($d['desconto']) ? ' (desconto de ' . brl($d['desconto']) . ' aplicado)' : '') . '.', 'Pague até <strong>' . e($d['prazo']) . '</strong> para não perder a vaga. Depois do prazo, a reserva expira.'], $u . '/' . $rel, 'Pagar agora'); break;
        case 'pagamento_confirmado': $a = 'Pagamento confirmado — reserva #' . $d['id']; $rel = 'minhas_reservas.php'; $notif = 'Sua reserva de ' . $d['titulo'] . ' está confirmada. O voucher já está disponível.';
            $h = emailTpl('Pagamento confirmado ✅', ["Olá, $n! Recebemos o pagamento de <strong>$v</strong> da sua viagem <strong>" . e($d['titulo']) . '</strong> (saída em ' . dataBr($d['data']) . ').', 'Sua reserva está confirmada. Você pode imprimir o voucher na área "Minhas reservas".'], $u . '/' . $rel, 'Ver minhas reservas'); break;
        case 'pagamento_recusado': $a = 'Não conseguimos confirmar seu pagamento — reserva #' . $d['id']; $rel = 'pagamento.php?id=' . (int)$d['id']; $notif = 'Pagamento não confirmado: ' . $d['motivo'];
            $h = emailTpl('Pagamento não confirmado', ["Olá, $n! Não localizamos o pagamento da reserva de <strong>" . e($d['titulo']) . '</strong>.', 'Motivo: ' . e($d['motivo']), 'Você ganhou um novo prazo de ' . PRAZO_PAGAMENTO_HORAS . ' horas para pagar e informar novamente.'], $u . '/' . $rel, 'Ver instruções de pagamento'); break;
        case 'reserva_expirada': $a = 'Sua reserva #' . $d['id'] . ' expirou'; $rel = 'suporte.php'; $notif = 'A reserva de ' . $d['titulo'] . ' expirou por falta de pagamento.';
            $h = emailTpl('Reserva expirada', ["Olá, $n! O prazo para pagar a reserva de <strong>" . e($d['titulo']) . '</strong> terminou e a vaga foi liberada.', 'Por isso, novas reservas ficam bloqueadas por ' . BLOQUEIO_HORAS . ' horas. Em caso de dúvida, fale com o suporte.'], $u . '/' . $rel, 'Falar com o suporte'); break;
        case 'vaga_liberada': $a = 'Abriu uma vaga em ' . $d['titulo']; $rel = 'passeio.php?id=' . (int)$d['passeio_id']; $notif = 'Surgiu vaga em ' . $d['titulo'] . ' (' . dataBr($d['data']) . '). Reserve rápido!';
            $h = emailTpl('Uma vaga foi liberada! 🎉', ["Olá, $n! Surgiu vaga em <strong>" . e($d['titulo']) . '</strong> na saída de ' . dataBr($d['data']) . '. Reserve rápido: outras pessoas também podem estar na fila.'], $u . '/' . $rel, 'Reservar agora'); break;
        case 'chamado_respondido': $a = 'Resposta ao seu chamado: ' . $d['assunto']; $rel = 'suporte.php'; $notif = 'Respondemos o seu chamado: ' . $d['assunto'];
            $h = emailTpl('Respondemos sua mensagem', ["Olá, $n! Sobre \"" . e($d['assunto']) . '":', nl2br(e($d['resposta']))], $u . '/' . $rel, 'Abrir o suporte'); break;
        case 'reset_senha': $a = 'Redefinição de senha — ' . SITE_NOME; $h = emailTpl('Criar nova senha', ["Olá, $n! Recebemos um pedido para redefinir sua senha. O link vale por 1 hora.", 'Se não foi você, ignore este e-mail.'], $d['url'], 'Criar nova senha'); break;
        default: return false;
    }
    if ($usuarioId && $notif !== null) criarNotificacao($usuarioId, $a, $notif, $rel);
    return enviarEmail($para, $a, $h, $evento, $usuarioId);
}
function enviarEmail($para, $assunto, $html, $tipo = 'geral', $usuarioId = null) {
    global $conn; $para = trim((string)$para);
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    try {
        $conn->prepare('INSERT INTO emails_fila (usuario_id,para,assunto,corpo,tipo) VALUES (?,?,?,?,?)')
             ->execute([$usuarioId, $para, mb_substr(str_replace(["\r", "\n"], ' ', $assunto), 0, 200), $html, mb_substr($tipo, 0, 40)]);
    } catch (Throwable $e) { return false; }
    static $registrado = false;
    if (!$registrado && PHP_SAPI !== 'cli') { $registrado = true; register_shutdown_function('processarFilaAoFinal'); }
    return true;
}
function processarFilaAoFinal() { if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); processarFilaEmails(5, false); }
function tentarEnvio(PDO $c, array $m) {
    if (!EMAIL_ATIVO) return false;
    $from = filter_var(config('email_remetente'), FILTER_VALIDATE_EMAIL) ?: 'nao-responder@localhost';
    $h = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: " . SITE_NOME . " <$from>\r\n";
    $ok = @mail($m['para'], '=?UTF-8?B?' . base64_encode($m['assunto']) . '?=', $m['corpo'], $h);
    $c->prepare('UPDATE emails_fila SET status=?, tentativas=tentativas+1, erro=?, enviado_em=? WHERE id=?')
      ->execute([$ok ? 'enviado' : 'falhou', $ok ? null : 'mail() retornou falso (servidor sem SMTP/sendmail configurado?)', $ok ? date('Y-m-d H:i:s') : null, $m['id']]);
    return $ok;
}
/** Envia pendentes. $incluirFalhas=true também tenta de novo os que falharam (admin/cron). */
function processarFilaEmails($limite = 5, $incluirFalhas = false) {
    global $conn; if (!EMAIL_ATIVO) return 0;
    try {
        $w = $incluirFalhas ? "status IN ('pendente','falhou') AND tentativas < " . EMAIL_TENTATIVAS_MAX : "status='pendente' AND tentativas=0";
        $n = 0; foreach ($conn->query("SELECT * FROM emails_fila WHERE $w ORDER BY id LIMIT " . (int)$limite)->fetchAll() as $m) if (tentarEnvio($conn, $m)) $n++;
        return $n;
    } catch (Throwable $e) { return 0; }
}
