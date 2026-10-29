<?php
require_once __DIR__ . '/inc/app.php';
if (usuario()) redirecionar('index.php');
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    $f = $conn->prepare("SELECT COUNT(*) FROM logs_acesso WHERE area='site' AND sucesso=0 AND ip=? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"); $f->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
    $bloq = $f->fetchColumn() >= 8;
    $email = trim($_POST['email'] ?? ''); $senha = $_POST['senha'] ?? '';
    $st = $conn->prepare('SELECT * FROM usuarios WHERE email=? LIMIT 1'); $st->execute([$email]); $u = $st->fetch();
    $ok = $u && $u['status'] === 'ativo' && password_verify($senha, $u['senha']);
    if ($bloq) {
        $erro = 'Muitas tentativas. Aguarde 15 minutos.';
    } elseif ($ok && (int)$u['tipo_user'] >= TIPO_GUIA) {   // equipe (guia/admin): entra pelo mesmo login e cai direto no painel certo
        $dest = entrarComoEquipe($conn, $u);
        if ($dest) redirecionar($dest);
        registrarAcesso($conn, $email, false, $u['id']);
        $erro = 'Esta conta da equipe não tem acesso a um painel.';
    } elseif ($ok) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'tipo' => (int)$u['tipo_user'], 'verificado' => !empty($u['email_verificado_em'])];
        $_SESSION['tema_site'] = array_filter(['modo' => $u['tema_modo'], 'cor' => $u['tema_cor'], 'fonte' => $u['tema_fonte']]);   // tema individual
        registrarAcesso($conn, $email, true, $u['id']);
        $dest = $_SESSION['voltar'] ?? 'index.php'; unset($_SESSION['voltar']);
        redirecionar($dest);
    } else {
        registrarAcesso($conn, $email, false, $u['id'] ?? null);
        $erro = 'E-mail ou senha inválidos (ou conta inativa).';
    }
}
$demo = $_SESSION['demo_link'] ?? null; unset($_SESSION['demo_link']);
topo('Entrar');
?>
<div class="row justify-content-center"><div class="col-md-5 col-lg-4">
  <h1 class="h3 mb-4 text-center">Entrar</h1>
  <?php if ($demo): ?><div class="alert alert-warning small"><strong>Modo demonstração:</strong> o servidor local pode não enviar e-mail. <a href="<?= e($demo['url']) ?>">Confirmar meu e-mail agora</a></div><?php endif; ?>
  <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
  <form method="post"><?= csrfCampo() ?>
    <div class="form-floating mb-2"><input class="form-control" type="email" name="email" id="email" placeholder="E-mail" required autofocus autocomplete="email"><label for="email">E-mail</label></div>
    <div class="input-group mb-3"><div class="form-floating flex-grow-1"><input class="form-control" type="password" name="senha" id="senha" placeholder="Senha" required autocomplete="current-password"><label for="senha">Senha</label></div>
      <button type="button" class="btn btn-outline-secondary" aria-label="Mostrar senha" onclick="const s=document.getElementById('senha');s.type=s.type==='password'?'text':'password'">👁</button></div>
    <button class="btn btn-primary w-100 btn-lg">Entrar</button>
  </form>
  <p class="text-center mt-3 small"><a href="esqueci_senha.php">Esqueci minha senha</a> · Não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
  <p class="text-center text-body-secondary small mb-1">Demo: viajante@teste.com / viagem123</p>
  <p class="text-center small"><a class="text-body-secondary" href="guia/login.php">Sou guia</a> · <a class="text-body-secondary" href="admin/login.php">Acesso administrativo</a><br><span class="text-body-secondary">Equipe (admin ou guia)? Entre com o seu e-mail acima: você vai direto ao painel.</span></p>
</div></div>
<?php rodape();
