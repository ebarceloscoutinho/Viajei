<?php
require_once __DIR__ . '/inc/app.php';
$link = ''; $enviado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $email = trim($_POST['email'] ?? ''); $enviado = true;
    $st = $conn->prepare("SELECT id,nome FROM usuarios WHERE email=? AND status='ativo'"); $st->execute([$email]); $u = $st->fetch();
    if ($u) {
        $token = bin2hex(random_bytes(32));
        $conn->prepare('UPDATE reset_senha SET usado=1 WHERE usuario_id=?')->execute([$u['id']]);
        $conn->prepare('INSERT INTO reset_senha (usuario_id,token_hash,expira) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$u['id'], hash('sha256', $token)]);
        $url = siteUrl() . '/nova_senha.php?token=' . $token;
        enviarEvento('reset_senha', $email, ['nome' => $u['nome'], 'url' => $url], $u['id']);
        if (MODO_DEMO_EMAIL) $link = $url;
    }
}
topo('Esqueci minha senha');
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
  <h1 class="h3 mb-3 text-center">Esqueci minha senha</h1>
  <?php if ($enviado): ?><div class="alert alert-success">Se o e-mail estiver cadastrado, enviamos um link de redefinição válido por 1 hora.</div>
    <?php if ($link): ?><div class="alert alert-warning small"><strong>Modo demonstração</strong> (o servidor local pode não enviar e-mail):<br><a href="<?= e($link) ?>">Clique aqui para criar uma nova senha</a></div><?php endif; ?>
  <?php endif; ?>
  <form method="post" class="vstack gap-3"><?= csrfCampo() ?>
    <input class="form-control" type="email" name="email" placeholder="Seu e-mail cadastrado" required autofocus>
    <button class="btn btn-primary">Enviar link de redefinição</button>
  </form>
  <p class="text-center mt-3 small"><a href="login.php">← Voltar ao login</a></p>
</div></div>
<?php rodape();
