<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('usuarios'); $eu = adminLogado()['id'];
$tipos = $conn->query('SELECT id,nome FROM tipos_usuario ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$status = $conn->query('SELECT codigo,nome FROM status_usuario ORDER BY codigo')->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? ''); $email = trim($_POST['email'] ?? ''); $tipo = (int)($_POST['tipo'] ?? 0); $senha = $_POST['senha'] ?? '';
        $ex = $conn->prepare('SELECT 1 FROM usuarios WHERE email=?'); $ex->execute([$email]);
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($tipos[$tipo])) flash('Informe nome, e-mail válido e o tipo.', 'danger');
        elseif ($ex->fetch()) flash('Já existe um usuário com este e-mail.', 'danger');
        elseif ($senha !== '' && strlen($senha) < 8) flash('A senha deve ter pelo menos 8 caracteres (ou deixe em branco para gerar uma).', 'danger');
        else {
            $gerada = $senha === '' ? senhaTemporaria() : null;
            $conn->prepare('INSERT INTO usuarios (nome,email,telefone,tipo_user,senha,email_verificado_em) VALUES (?,?,?,?,?,NOW())')->execute([$nome, $email, trim($_POST['telefone'] ?? '') ?: null, $tipo, password_hash($gerada ?? $senha, PASSWORD_DEFAULT)]);
            flash('Usuário criado (' . $tipos[$tipo] . ').' . ($gerada ? " Senha temporária: $gerada — anote agora, ela não será exibida de novo." : ''), $gerada ? 'warning' : 'success');
        }
    } elseif ($id === $eu && in_array($acao, ['editar'], true)) {
        flash('Você não pode alterar o próprio tipo ou status.', 'danger');
    } elseif ($acao === 'editar') {
        $tipo = (int)$_POST['tipo']; $stt = $_POST['status'] ?? '';
        if (isset($tipos[$tipo]) && isset($status[$stt])) { $conn->prepare('UPDATE usuarios SET tipo_user=?, status=? WHERE id=?')->execute([$tipo, $stt, $id]); flash('Usuário atualizado.'); }
    } elseif ($acao === 'liberar') {
        $conn->prepare('UPDATE usuarios SET bloqueado_ate=NULL WHERE id=?')->execute([$id]); flash('Reservas liberadas para este cliente.');
    } elseif ($acao === 'senha') {
        $t = senhaTemporaria(); $conn->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($t, PASSWORD_DEFAULT), $id]);
        flash("Nova senha temporária: $t — repasse ao usuário e peça para trocar (anote agora).", 'warning');
    }
    redirecionar('usuarios.php');
}
$q = trim($_GET['q'] ?? ''); $ft = (int)($_GET['tipo'] ?? 0);
$sql = 'SELECT u.*, (u.bloqueado_ate > NOW()) AS bloq FROM usuarios u WHERE 1=1'; $par = [];
if ($q !== '') { $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ?)'; array_push($par, "%$q%", "%$q%"); }
if ($ft) { $sql .= ' AND u.tipo_user=?'; $par[] = $ft; }
$st = $conn->prepare($sql . ' ORDER BY u.tipo_user DESC, u.nome'); $st->execute($par); $lista = $st->fetchAll();
admTopo('Usuários', 'usuarios');
?>
<div class="row g-4">
  <div class="col-lg-4"><div class="card"><div class="card-body"><h2 class="h6">Cadastrar usuário</h2>
    <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="criar">
      <input class="form-control" name="nome" placeholder="Nome completo" required><input class="form-control" type="email" name="email" placeholder="E-mail" required><input class="form-control" name="telefone" placeholder="Telefone (opcional)">
      <select class="form-select" name="tipo" required><?php foreach ($tipos as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
      <input class="form-control" type="text" name="senha" placeholder="Senha inicial (vazio = gerar automática)" autocomplete="off">
      <button class="btn btn-primary">Criar</button>
      <div class="form-text">Administradores entram em <code>/admin/login.php</code>. Guias e viajantes entram pelo login do site.</div></form></div></div></div>
  <div class="col-lg-8">
    <form class="row g-2 mb-3" method="get"><div class="col-7"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Buscar nome ou e-mail"></div>
      <div class="col-3"><select class="form-select form-select-sm" name="tipo"><option value="0">Todos</option><?php foreach ($tipos as $k => $v): ?><option value="<?= $k ?>" <?= $ft === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Filtrar</button></div></form>
    <div class="vstack gap-2"><?php foreach ($lista as $u): $meu = (int)$u['id'] === $eu; ?>
      <div class="card"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-start gap-2">
        <div style="min-width:0"><strong><?= e($u['nome']) ?></strong> <?= $meu ? '<span class="badge text-bg-light border">você</span>' : '' ?><br><small class="text-body-secondary text-break"><?= e($u['email']) ?> <?= e($u['telefone']) ?></small></div>
        <div class="text-end text-nowrap"><span class="badge text-bg-<?= $u['tipo_user'] >= 3 ? 'dark' : ($u['tipo_user'] == 2 ? 'info' : 'secondary') ?>"><?= e($tipos[$u['tipo_user']]) ?></span>
          <span class="badge text-bg-<?= $u['status'] === 'ativo' ? 'success' : 'warning' ?>"><?= e($status[$u['status']]) ?></span><?= $u['bloq'] ? ' <span class="badge text-bg-danger">reservas bloqueadas</span>' : '' ?></div></div>
        <details class="mt-1"><summary class="small text-primary" style="cursor:pointer">Gerenciar</summary>
          <div class="d-flex flex-wrap gap-2 align-items-end pt-2">
            <?php if (!$meu): ?><form method="post" class="d-flex gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="editar"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <select class="form-select form-select-sm" name="tipo"><?php foreach ($tipos as $k => $v): ?><option value="<?= $k ?>" <?= $u['tipo_user'] == $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
              <select class="form-select form-select-sm" name="status"><?php foreach ($status as $k => $v): ?><option value="<?= e($k) ?>" <?= $u['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Salvar</button></form><?php endif; ?>
            <?php if ($u['bloq']): ?><form method="post"><?= csrfCampo() ?><input type="hidden" name="acao" value="liberar"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-success">Liberar reservas</button></form><?php endif; ?>
            <form method="post" onsubmit="return confirm('Gerar nova senha temporária?')"><?= csrfCampo() ?><input type="hidden" name="acao" value="senha"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Redefinir senha</button></form>
          </div></details></div></div><?php endforeach; ?></div>
  </div>
</div>
<?php admRodape();
