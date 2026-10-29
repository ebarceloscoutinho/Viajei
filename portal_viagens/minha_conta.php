<?php
require_once __DIR__ . '/inc/app.php';
exigirLogin(); $uid = usuario()['id'];
$st = $conn->prepare('SELECT * FROM usuarios WHERE id=?'); $st->execute([$uid]); $u = $st->fetch();
if (!$u) { unset($_SESSION['user']); flash('Sua sessão expirou. Entre novamente.', 'warning'); redirecionar('login.php'); }   // conta removida/banco reimportado: evita 'array offset on false'
$_SESSION['user']['verificado'] = !empty($u['email_verificado_em']);

if (isset($_GET['exportar'])) {   // LGPD: portabilidade — baixa todos os dados da conta em JSON
    $q = function ($sql) use ($conn, $uid) { $x = $conn->prepare($sql); $x->execute([$uid]); return $x->fetchAll(); };
    $dados = ['gerado_em' => date('c'), 'conta' => array_diff_key($u, array_flip(['senha', 'totp_secret'])),
        'reservas' => $q('SELECT id,saida_id,qtd,valor_total,desconto,taxa_cancelamento,status,created_at FROM reservas WHERE usuario_id=?'),
        'lista_espera' => $q('SELECT saida_id,status,created_at FROM lista_espera WHERE usuario_id=?'),
        'avaliacoes' => $q('SELECT passeio_id,nota,comentario,created_at FROM avaliacoes WHERE usuario_id=?'),
        'favoritos' => $q('SELECT passeio_id,created_at FROM favoritos WHERE usuario_id=?'),
        'roteiros' => $q('SELECT id,titulo,destino,dias,data_inicio,created_at FROM roteiros WHERE usuario_id=?'),
        'chamados' => $q('SELECT assunto,mensagem,status,resposta,created_at FROM chamados WHERE usuario_id=?')];
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8'); header('Content-Disposition: attachment; filename="meus-dados.json"');
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? '';
    if ($acao === 'perfil') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') flash('Informe seu nome.', 'danger');
        else { $conn->prepare('UPDATE usuarios SET nome=?, telefone=? WHERE id=?')->execute([mb_substr($nome, 0, 150), mb_substr(trim($_POST['telefone'] ?? ''), 0, 20) ?: null, $uid]); $_SESSION['user']['nome'] = $nome; flash('Dados atualizados.'); }
    } elseif ($acao === 'senha') {
        $n = $_POST['nova'] ?? '';
        if (!password_verify($_POST['atual'] ?? '', $u['senha'])) flash('Senha atual incorreta.', 'danger');
        elseif (strlen($n) < 8 || $n !== ($_POST['nova2'] ?? '')) flash('A nova senha precisa ter 8+ caracteres e ser igual à confirmação.', 'danger');
        else { $conn->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($n, PASSWORD_DEFAULT), $uid]); flash('Senha alterada.'); }
    } elseif ($acao === 'reenviar_verificacao') {
        if (!empty($u['email_verificado_em'])) flash('Seu e-mail já está confirmado.');
        elseif (($_SESSION['ult_verif'] ?? 0) > time() - 60) flash('Aguarde um minuto para reenviar.', 'warning');
        else { $_SESSION['ult_verif'] = time(); $lk = enviarVerificacao($conn, $uid, $u['email'], $u['nome']); if (MODO_DEMO_EMAIL) $_SESSION['demo_link'] = ['url' => $lk]; flash('Enviamos um novo e-mail de confirmação.'); }
    } elseif ($acao === 'exclusao') {   // LGPD: pedido de exclusão vira chamado para a equipe tratar
        $conn->prepare('INSERT INTO chamados (usuario_id,nome,email,assunto,mensagem) VALUES (?,?,?,?,?)')->execute([$uid, $u['nome'], $u['email'], 'Solicitação de exclusão de dados (LGPD)', 'Solicito a exclusão da minha conta e dos meus dados pessoais, respeitadas as obrigações legais de guarda.']);
        flash('Pedido registrado. Nossa equipe responderá pelo suporte.');
    }
    redirecionar('minha_conta.php');
}
$demo = $_SESSION['demo_link'] ?? null; unset($_SESSION['demo_link']);
topo('Minha conta');
?>
<h1 class="h3 mb-4">Minha conta</h1>
<div class="card card-body mb-4" id="verificacao"><h2 class="h6 mb-1">✉️ E-mail</h2>
  <?php if ($u['email_verificado_em']): ?><div class="text-success small">Confirmado em <?= dataBr($u['email_verificado_em']) ?> ✅</div>
  <?php else: ?><div class="small mb-2">Seu e-mail ainda não foi confirmado — você só poderá reservar depois de confirmar.</div><form method="post"><?= csrfCampo() ?><button class="btn btn-sm btn-primary" name="acao" value="reenviar_verificacao">Reenviar e-mail de confirmação</button></form><?php endif; ?>
  <?php if ($demo): ?><div class="alert alert-warning small mt-2 mb-0"><strong>Modo demonstração:</strong> <a href="<?= e($demo['url']) ?>">confirmar meu e-mail agora</a></div><?php endif; ?></div>
<div class="row g-4"><div class="col-lg-6"><form method="post" class="card card-body vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="perfil"><h2 class="h5">Meus dados</h2>
  <input class="form-control" name="nome" value="<?= e($u['nome']) ?>" required><input class="form-control" value="<?= e($u['email']) ?>" disabled aria-label="E-mail"><input class="form-control" name="telefone" value="<?= e($u['telefone']) ?>" placeholder="Telefone"><button class="btn btn-primary">Salvar</button></form></div>
  <div class="col-lg-6"><form method="post" class="card card-body vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="senha"><h2 class="h5">Alterar senha</h2>
    <input class="form-control" type="password" name="atual" placeholder="Senha atual" required autocomplete="current-password"><input class="form-control" type="password" name="nova" placeholder="Nova senha (mín. 8)" required autocomplete="new-password"><input class="form-control" type="password" name="nova2" placeholder="Repita a nova senha" required autocomplete="new-password"><button class="btn btn-primary">Salvar senha</button></form></div>
  <div class="col-12"><div class="card card-body"><h2 class="h5">Privacidade e dados (LGPD)</h2><p class="small text-body-secondary mb-2">Você pode baixar uma cópia dos seus dados ou pedir a exclusão da conta. Veja a <a href="privacidade.php">Política de Privacidade</a>.</p>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="?exportar=1">⬇ Baixar meus dados (JSON)</a>
      <form method="post" onsubmit="return confirm('Pedir a exclusão da conta e dos dados?')"><?= csrfCampo() ?><button class="btn btn-outline-danger" name="acao" value="exclusao">Solicitar exclusão da conta</button></form></div></div></div></div>
<?php rodape();
