<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/totp.php';
exigirAdmin('seguranca'); $eu = adminLogado()['id'];
$st = $conn->prepare('SELECT * FROM usuarios WHERE id=?'); $st->execute([$eu]); $u = $st->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? '';
    if ($acao === 'senha') {
        $n = $_POST['nova'] ?? '';
        if (!password_verify($_POST['atual'] ?? '', $u['senha'])) flash('Senha atual incorreta.', 'danger');
        elseif (strlen($n) < 10) flash('Para administradores, use pelo menos 10 caracteres.', 'danger');
        elseif ($n !== ($_POST['nova2'] ?? '')) flash('A confirmação não confere.', 'danger');
        else { $conn->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($n, PASSWORD_DEFAULT), $eu]); flash('Senha alterada.'); }
    } elseif ($acao === 'ativar_2fa') {
        $seg = $_SESSION['totp_novo'] ?? '';
        if ($seg !== '' && totpVerificar($seg, $_POST['codigo'] ?? '')) { $conn->prepare('UPDATE usuarios SET totp_secret=?, totp_ativo=1 WHERE id=?')->execute([$seg, $eu]); unset($_SESSION['totp_novo']); flash('Verificação em duas etapas ativada! No próximo login será pedido o código.'); }
        else flash('Código incorreto. Confira se a chave foi digitada certa no app e se o horário do celular está automático.', 'danger');
    } elseif ($acao === 'desativar_2fa') {
        if (password_verify($_POST['atual'] ?? '', $u['senha']) && totpVerificar((string)$u['totp_secret'], $_POST['codigo'] ?? '')) { $conn->prepare('UPDATE usuarios SET totp_secret=NULL, totp_ativo=0 WHERE id=?')->execute([$eu]); flash('Verificação em duas etapas desativada.', 'warning'); }
        else flash('Senha ou código incorretos.', 'danger');
    } elseif ($acao === 'novo_segredo') { unset($_SESSION['totp_novo']); }
    redirecionar('seguranca.php');
}
if (!$u['totp_ativo'] && empty($_SESSION['totp_novo'])) $_SESSION['totp_novo'] = totpNovoSegredo();
$seg = $_SESSION['totp_novo'] ?? '';
$logs = $conn->prepare("SELECT * FROM logs_acesso WHERE email=? AND area='admin' ORDER BY id DESC LIMIT 8"); $logs->execute([$u['email']]); $logs = $logs->fetchAll();
admTopo('Segurança', 'seguranca');
?>
<div class="row g-4">
  <div class="col-lg-6"><div class="card card-body"><h2 class="h5">🔐 Verificação em duas etapas</h2>
    <?php if ($u['totp_ativo']): ?><div class="alert alert-success py-2">Ativada. O login do painel pede um código do seu app autenticador.</div>
      <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="desativar_2fa"><input class="form-control" type="password" name="atual" placeholder="Senha atual" required autocomplete="current-password"><input class="form-control" name="codigo" inputmode="numeric" placeholder="Código atual do app" required autocomplete="off"><button class="btn btn-outline-danger">Desativar</button></form>
    <?php else: ?><p class="small text-body-secondary">Protege o painel mesmo se a senha vazar. Use Google Authenticator, Microsoft Authenticator, Authy ou similar.</p>
      <ol class="small"><li>No app, escolha <em>Adicionar conta → Inserir chave de configuração</em>.</li><li>Nome: <strong><?= e(SITE_NOME) ?></strong> · Chave (tipo baseado em tempo):</li></ol>
      <div class="p-2 rounded border text-center fs-5 font-monospace user-select-all mb-2"><?= e(trim(chunk_split($seg, 4, ' '))) ?></div>
      <div class="text-center mb-2"><?= qrSvg(totpUri($seg, $u['email'], SITE_NOME), 200, 'QR Code do autenticador') ?><div class="small text-body-secondary mt-1">Escaneie este QR Code com o app autenticador (ou digite a chave acima)</div></div>
      <details class="small mb-3"><summary>Prefere o link otpauth?</summary><code class="text-break user-select-all"><?= e(totpUri($seg, $u['email'], SITE_NOME)) ?></code></details>
      <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="ativar_2fa"><input class="form-control" name="codigo" inputmode="numeric" placeholder="Código de 6 dígitos mostrado no app" required autocomplete="off"><button class="btn btn-primary">Confirmar e ativar</button></form>
      <form method="post" class="mt-2"><?= csrfCampo() ?><button class="btn btn-sm btn-link" name="acao" value="novo_segredo">Gerar outra chave</button></form><?php endif; ?></div></div>
  <div class="col-lg-6"><div class="card card-body mb-3"><h2 class="h5">Alterar minha senha</h2>
    <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="senha"><input class="form-control" type="password" name="atual" placeholder="Senha atual" required autocomplete="current-password"><input class="form-control" type="password" name="nova" placeholder="Nova senha (mín. 10 caracteres)" required autocomplete="new-password"><input class="form-control" type="password" name="nova2" placeholder="Repita a nova senha" required autocomplete="new-password"><button class="btn btn-primary">Salvar senha</button></form></div>
    <div class="card card-body"><h2 class="h6">Últimos acessos à minha conta</h2><table class="table table-sm mb-0"><tbody><?php foreach ($logs as $l): ?><tr><td><?= $l['sucesso'] ? '✅' : '❌' ?></td><td><?= e($l['ip']) ?></td><td><?= e($l['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php admRodape();
