<?php
require_once __DIR__ . '/inc/app.php';
$token = (string)($_GET['token'] ?? '');
$st = $conn->prepare('SELECT * FROM tokens_email WHERE token_hash=? AND usado=0 AND expira > NOW()'); $st->execute([hash('sha256', $token)]); $t = $st->fetch();
if ($t) {
    $conn->prepare('UPDATE usuarios SET email_verificado_em=NOW() WHERE id=? AND email_verificado_em IS NULL')->execute([$t['usuario_id']]);
    $conn->prepare('UPDATE tokens_email SET usado=1 WHERE usuario_id=?')->execute([$t['usuario_id']]);
    if (!empty($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$t['usuario_id']) $_SESSION['user']['verificado'] = true;
    flash('E-mail confirmado! Agora você já pode reservar viagens. ✅'); redirecionar(usuario() ? 'passeios.php' : 'login.php');
}
topo('Confirmar e-mail');
?>
<div class="text-center py-5"><div class="display-3">✉️</div><h1 class="h4 mt-3">Link inválido ou expirado</h1><p class="text-body-secondary">Entre na sua conta e peça um novo e-mail de confirmação em <em>Minha conta</em>.</p><a class="btn btn-primary" href="<?= usuario() ? 'minha_conta.php#verificacao' : 'login.php' ?>">Continuar</a></div>
<?php rodape();
