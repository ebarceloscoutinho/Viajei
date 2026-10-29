<?php
require_once __DIR__ . '/inc/app.php';
$token = $_GET['token'] ?? $_POST['token'] ?? ''; $erro = '';
$st = $conn->prepare('SELECT * FROM reset_senha WHERE token_hash=? AND usado=0 AND expira > NOW()'); $st->execute([hash('sha256', $token)]); $rs = $st->fetch();
if ($rs && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $s1 = $_POST['senha'] ?? '';
    if (strlen($s1) < 8) $erro = 'A senha deve ter pelo menos 8 caracteres.';
    elseif ($s1 !== ($_POST['senha2'] ?? '')) $erro = 'As senhas não conferem.';
    else {
        $conn->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($s1, PASSWORD_DEFAULT), $rs['usuario_id']]);
        $conn->prepare('UPDATE reset_senha SET usado=1 WHERE usuario_id=?')->execute([$rs['usuario_id']]);
        flash('Senha alterada! Faça login com a nova senha.'); redirecionar('login.php');
    }
}
topo('Nova senha');
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
  <h1 class="h3 mb-3 text-center">Criar nova senha</h1>
  <?php if (!$rs): ?><div class="alert alert-danger">Link inválido ou expirado. <a href="esqueci_senha.php">Solicite um novo</a>.</div>
  <?php else: ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" class="vstack gap-3"><?= csrfCampo() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <input class="form-control" type="password" name="senha" placeholder="Nova senha (mín. 8 caracteres)" required autocomplete="new-password">
      <input class="form-control" type="password" name="senha2" placeholder="Repita a nova senha" required autocomplete="new-password">
      <button class="btn btn-primary">Salvar nova senha</button></form>
  <?php endif; ?>
</div></div>
<?php rodape();
