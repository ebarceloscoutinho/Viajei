<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/totp.php';
if (adminLogado()) redirecionar('index.php');
$erro = ''; $pend = $_SESSION['adm_pend'] ?? null; if ($pend && $pend['exp'] < time()) { unset($_SESSION['adm_pend']); $pend = null; }

function concluirLoginAdmin(PDO $c, array $u) {
    session_regenerate_id(true); unset($_SESSION['adm_pend']);
    $_SESSION['adm'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'tipo' => (int)$u['tipo_user']];
    $_SESSION['tema_adm'] = array_filter(['modo' => $u['tema_modo'], 'cor' => $u['tema_cor'], 'fonte' => $u['tema_fonte']]);
    registrarAcesso($c, $u['email'], true, $u['id'], 'admin'); redirecionar('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    $f = $conn->prepare("SELECT COUNT(*) FROM logs_acesso WHERE area='admin' AND sucesso=0 AND ip=? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $f->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
    if ($f->fetchColumn() >= 8) { $erro = 'Muitas tentativas. Aguarde 15 minutos.'; }
    elseif ($pend && isset($_POST['codigo'])) {   // 2ª etapa: código do app autenticador
        $st = $conn->prepare('SELECT * FROM usuarios WHERE id=?'); $st->execute([$pend['id']]); $u = $st->fetch();
        if ($u && totpVerificar((string)$u['totp_secret'], $_POST['codigo'])) concluirLoginAdmin($conn, $u);
        registrarAcesso($conn, $pend['email'], false, $pend['id'], 'admin'); $erro = 'Código inválido. Confira o horário do celular e tente de novo.';
    } else {
        $email = trim($_POST['email'] ?? ''); $senha = $_POST['senha'] ?? '';
        $st = $conn->prepare('SELECT * FROM usuarios WHERE email=? LIMIT 1'); $st->execute([$email]); $u = $st->fetch();
        if ($u && in_array((int)$u['tipo_user'], [TIPO_ADMIN, TIPO_FINANCEIRO, TIPO_ATENDIMENTO, TIPO_CATALOGO], true) && $u['status'] === 'ativo' && password_verify($senha, $u['senha'])) {
            if ($u['totp_ativo'] && $u['totp_secret']) { $pend = $_SESSION['adm_pend'] = ['id' => (int)$u['id'], 'email' => $u['email'], 'exp' => time() + 300]; }
            else concluirLoginAdmin($conn, $u);
        } else { registrarAcesso($conn, $email, false, $u['id'] ?? null, 'admin'); $erro = 'Credenciais inválidas ou sem permissão de administrador.'; }
    }
}
echo '<!doctype html><html lang="pt-BR" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Acesso administrativo · ' . e(SITE_NOME) . '</title><link rel="icon" href="../assets/img/favicon.png">'
   . headTema() . '<script src="../assets/js/tema.js"></script><link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../css/site.css?v=' . CSS_V . '" rel="stylesheet"></head>';
?>
<body class="d-flex align-items-center" style="min-height:100vh"><div class="container"><div class="row justify-content-center"><div class="col-sm-8 col-md-5 col-lg-4">
  <div class="text-center mb-4"><img src="../assets/img/logo.svg" width="56" height="56" alt=""><h1 class="h4 mt-2 mb-0"><?= e(SITE_NOME) ?></h1><div class="text-body-secondary">Acesso administrativo</div></div>
  <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
  <?php if ($pend): ?>
  <form method="post" class="vstack gap-2"><?= csrfCampo() ?><p class="small text-body-secondary mb-1">🔐 Verificação em duas etapas: digite o código de 6 dígitos do seu app autenticador.</p>
    <input class="form-control form-control-lg text-center" name="codigo" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" placeholder="000000" required autofocus>
    <button class="btn btn-primary btn-lg">Confirmar</button></form>
  <?php else: ?>
  <form method="post" class="vstack gap-2"><?= csrfCampo() ?>
    <input class="form-control" type="email" name="email" placeholder="E-mail" required autofocus autocomplete="username">
    <input class="form-control" type="password" name="senha" placeholder="Senha" required autocomplete="current-password">
    <button class="btn btn-primary btn-lg">Entrar</button></form>
  <p class="text-center text-body-secondary small mt-3">Demo: admin@portalviagens.com / viagem123</p><?php endif; ?>
  <p class="text-center small mt-2"><a class="text-body-secondary" href="../index.php">← Voltar ao site</a></p>
</div></div></div><script src="../assets/dist/js/bootstrap.bundle.min.js"></script></body></html>
