<?php
define('BASE', '../'); define('AREA', 'guia');
require_once __DIR__ . '/../inc/guia.php';
if (guiaLogado()) redirecionar('index.php');
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    $f = $conn->prepare("SELECT COUNT(*) FROM logs_acesso WHERE area='guia' AND sucesso=0 AND ip=? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"); $f->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
    if ($f->fetchColumn() >= 8) $erro = 'Muitas tentativas. Aguarde 15 minutos.';
    else {
        $email = trim($_POST['email'] ?? ''); $st = $conn->prepare('SELECT u.*, g.id AS guia_id FROM usuarios u JOIN guias g ON g.usuario_id=u.id AND g.ativo=1 WHERE u.email=? AND u.tipo_user=2 LIMIT 1');
        $st->execute([$email]); $u = $st->fetch();
        if ($u && $u['status'] === 'ativo' && password_verify($_POST['senha'] ?? '', $u['senha'])) {
            session_regenerate_id(true);
            $_SESSION['gui'] = ['id' => (int)$u['id'], 'nome' => $u['nome'], 'guia_id' => (int)$u['guia_id']];
            $_SESSION['tema_gui'] = array_filter(['modo' => $u['tema_modo'], 'cor' => $u['tema_cor'], 'fonte' => $u['tema_fonte']]);
            registrarAcesso($conn, $email, true, $u['id'], 'guia'); redirecionar('index.php');
        }
        registrarAcesso($conn, $email, false, $u['id'] ?? null, 'guia'); $erro = 'Credenciais inválidas ou guia sem acesso ao painel.';
    }
}
echo '<!doctype html><html lang="pt-BR" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Painel do guia · ' . e(SITE_NOME) . '</title><link rel="icon" href="../assets/img/favicon.png">'
   . headTema() . '<script src="../assets/js/tema.js"></script><link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../css/site.css?v=' . CSS_V . '" rel="stylesheet"></head>';
?>
<body class="d-flex align-items-center" style="min-height:100vh"><div class="container"><div class="row justify-content-center"><div class="col-sm-8 col-md-5 col-lg-4">
  <div class="text-center mb-4"><img src="../assets/img/logo.svg" width="56" height="56" alt=""><h1 class="h4 mt-2 mb-0"><?= e(SITE_NOME) ?></h1><div class="text-body-secondary">Painel do guia</div></div>
  <?php if ($erro): ?><div class="alert alert-danger"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input class="form-control" type="email" name="email" placeholder="E-mail" required autofocus autocomplete="username"><input class="form-control" type="password" name="senha" placeholder="Senha" required autocomplete="current-password"><button class="btn btn-primary btn-lg">Entrar</button></form>
  <p class="text-center text-body-secondary small mt-3">Demo: marina@portalviagens.com / viagem123</p><p class="text-center small"><a class="text-body-secondary" href="../index.php">← Voltar ao site</a></p>
</div></div></div></body></html>
