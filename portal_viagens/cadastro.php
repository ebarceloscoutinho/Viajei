<?php
require_once __DIR__ . '/inc/app.php';
if (usuario()) redirecionar('index.php');
if (ajuste('sys_cadastro_aberto') !== '1') { topo('Criar conta'); echo '<div class="text-center py-5"><div class="display-4">🔒</div><h1 class="h4 mt-2">Cadastros temporariamente fechados</h1><p class="text-body-secondary">Volte em breve ou fale com a gente pelo <a href="suporte.php">suporte</a>.</p></div>'; rodape(); exit; }
$erro = ''; $v = ['nome' => '', 'email' => '', 'telefone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($v['nome'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $erro = 'Informe nome e um e-mail válido.';
    elseif (strlen($senha) < 8) $erro = 'A senha deve ter pelo menos 8 caracteres.';
    elseif ($senha !== ($_POST['senha2'] ?? '')) $erro = 'As senhas não conferem.';
    elseif (empty($_POST['aceite'])) $erro = 'Para criar a conta, aceite os Termos de uso e a Política de Privacidade.';
    else {
        $st = $conn->prepare('SELECT 1 FROM usuarios WHERE email=?'); $st->execute([$v['email']]);
        if ($st->fetch()) $erro = 'Este e-mail já está cadastrado.';
        else {
            // Cadastro público cria sempre um Viajante (tipo 1) — nunca confie em tipo vindo do formulário
            $conn->prepare('INSERT INTO usuarios (nome,email,telefone,tipo_user,senha,aceite_termos_em) VALUES (?,?,?,1,?,NOW())')
                 ->execute([$v['nome'], $v['email'], $v['telefone'] ?: null, password_hash($senha, PASSWORD_DEFAULT)]);
            $novoId = (int)$conn->lastInsertId();
            enviarEvento('boas_vindas', $v['email'], ['nome' => $v['nome']], $novoId);
            $link = enviarVerificacao($conn, $novoId, $v['email'], $v['nome']);
            if (MODO_DEMO_EMAIL) $_SESSION['demo_link'] = ['url' => $link];
            flash('Conta criada! Enviamos um e-mail para você confirmar o endereço. Faça login para continuar.'); redirecionar('login.php');
        }
    }
}
topo('Criar conta');
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
  <h1 class="h3 mb-4 text-center">Criar conta</h1>
  <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" class="vstack gap-3"><?= csrfCampo() ?>
    <input class="form-control" name="nome" placeholder="Nome completo" value="<?= e($v['nome']) ?>" required>
    <input class="form-control" type="email" name="email" placeholder="E-mail" value="<?= e($v['email']) ?>" required>
    <input class="form-control" name="telefone" placeholder="Telefone (opcional)" value="<?= e($v['telefone']) ?>">
    <input class="form-control" type="password" name="senha" placeholder="Senha (mín. 8 caracteres)" required autocomplete="new-password">
    <input class="form-control" type="password" name="senha2" placeholder="Repita a senha" required autocomplete="new-password">
    <div class="form-check"><input class="form-check-input" type="checkbox" name="aceite" id="aceite" value="1" required><label class="form-check-label small" for="aceite">Li e aceito os <a href="termos.php" target="_blank">Termos de uso</a> e a <a href="privacidade.php" target="_blank">Política de Privacidade</a>.</label></div>
    <button class="btn btn-primary btn-lg">Criar conta</button>
  </form>
</div></div>
<?php rodape();
