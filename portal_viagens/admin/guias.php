<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('guias');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'salvar') {
        try {
            $nome = trim($_POST['nome'] ?? ''); if ($nome === '') throw new Exception('Informe o nome do guia.');
            $email = trim($_POST['email'] ?? ''); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('E-mail inválido.');
            $atual = null; if ($id) { $x = $conn->prepare('SELECT foto FROM guias WHERE id=?'); $x->execute([$id]); $atual = $x->fetchColumn() ?: null; }
            $foto = imagemDoForm('foto_arq', 'foto_url', 'guias', $atual);
            $adm = $_POST['data_admissao'] ?? ''; $adm = preg_match('/^\d{4}-\d{2}-\d{2}$/', $adm) ? $adm : null;
            $uid = (int)($_POST['usuario_id'] ?? 0) ?: null;
            $salario = 0.0;
            if (adminPode('salario')) $salario = max(0, round((float)str_replace(',', '.', $_POST['salario'] ?? 0), 2));
            elseif ($id) { $x = $conn->prepare('SELECT salario FROM guias WHERE id=?'); $x->execute([$id]); $salario = (float)$x->fetchColumn(); }
            $d = [$uid, mb_substr($nome, 0, 120), $foto, $email ?: null, mb_substr(trim($_POST['telefone'] ?? ''), 0, 25) ?: null, mb_substr(trim($_POST['idiomas'] ?? ''), 0, 120) ?: null, trim($_POST['bio'] ?? '') ?: null, $salario, $adm, isset($_POST['ativo']) ? 1 : 0];
            if ($id) $conn->prepare('UPDATE guias SET usuario_id=?,nome=?,foto=?,email=?,telefone=?,idiomas=?,bio=?,salario=?,data_admissao=?,ativo=? WHERE id=?')->execute([...$d, $id]);
            else { $conn->prepare('INSERT INTO guias (usuario_id,nome,foto,email,telefone,idiomas,bio,salario,data_admissao,ativo) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($d); $id = (int)$conn->lastInsertId(); }
            $conn->prepare('DELETE FROM guia_locais WHERE guia_id=?')->execute([$id]);
            $ins = $conn->prepare('INSERT INTO guia_locais (guia_id,local) VALUES (?,?)'); $vistos = [];
            foreach (preg_split('/[\r\n,;]+/', $_POST['locais'] ?? '') as $l) { $l = mb_substr(trim($l), 0, 80); if ($l !== '' && !isset($vistos[mb_strtolower($l)]) && count($vistos) < 40) { $vistos[mb_strtolower($l)] = 1; $ins->execute([$id, $l]); } }
            if (!empty($_POST['criar_acesso'])) {   // cria a conta de login do guia (painel do guia)
                $ex = $conn->prepare('SELECT 1 FROM usuarios WHERE email=?'); $ex->execute([$email]);
                if ($email === '' || $ex->fetch()) { flash('Guia salvo, mas o acesso não foi criado: informe um e-mail que ainda não tenha conta.', 'warning'); redirecionar('guias.php'); }
                $t = senhaTemporaria();
                $conn->prepare('INSERT INTO usuarios (nome,email,telefone,tipo_user,senha,email_verificado_em) VALUES (?,?,?,2,?,NOW())')->execute([mb_substr($nome, 0, 150), $email, mb_substr(trim($_POST['telefone'] ?? ''), 0, 20) ?: null, password_hash($t, PASSWORD_DEFAULT)]);
                $conn->prepare('UPDATE guias SET usuario_id=? WHERE id=?')->execute([(int)$conn->lastInsertId(), $id]);
                flash("Guia salvo. Acesso ao painel do guia criado — login: $email · senha temporária: $t (anote agora; o guia entra em /guia/login.php).", 'warning'); redirecionar('guias.php');
            }
            flash('Guia salvo.'); redirecionar('guias.php');
        } catch (Exception $ex) { flash($ex->getMessage(), 'danger'); redirecionar('guias.php' . ($id ? "?editar=$id" : '?novo=1')); }
    } elseif ($acao === 'ativar') { $conn->prepare('UPDATE guias SET ativo = 1 - ativo WHERE id=?')->execute([$id]); flash('Status alterado.');
    } elseif ($acao === 'excluir') {
        $n = $conn->prepare('SELECT COUNT(*) FROM passeios WHERE guia_id=?'); $n->execute([$id]);
        if ($n->fetchColumn() > 0) flash('Este guia ainda responde por viagens. Reatribua as viagens ou apenas desative o guia.', 'danger');
        else { $conn->prepare('DELETE FROM guias WHERE id=?')->execute([$id]); flash('Guia excluído.'); }
    }
    redirecionar('guias.php');
}
$ed = ['id' => 0, 'usuario_id' => 0, 'nome' => '', 'foto' => '', 'email' => '', 'telefone' => '', 'idiomas' => '', 'bio' => '', 'salario' => '', 'data_admissao' => date('Y-m-d'), 'ativo' => 1]; $locais = ''; $editando = !empty($_GET['editar']) || !empty($_GET['novo']);
if (!empty($_GET['editar'])) {
    $st = $conn->prepare('SELECT * FROM guias WHERE id=?'); $st->execute([(int)$_GET['editar']]); $ed = $st->fetch() ?: $ed;
    $st = $conn->prepare('SELECT local FROM guia_locais WHERE guia_id=? ORDER BY id'); $st->execute([$ed['id']]); $locais = implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
}
$contas = $conn->query('SELECT id,nome FROM usuarios WHERE tipo_user=2 ORDER BY nome')->fetchAll(PDO::FETCH_KEY_PAIR);
$q = trim($_GET['q'] ?? '');
$st = $conn->prepare("SELECT g.*, (SELECT COUNT(*) FROM passeios p WHERE p.guia_id=g.id) AS n_viagens, (SELECT GROUP_CONCAT(l.local ORDER BY l.id SEPARATOR '|') FROM guia_locais l WHERE l.guia_id=g.id) AS locais
  FROM guias g WHERE g.nome LIKE ? OR EXISTS (SELECT 1 FROM guia_locais l WHERE l.guia_id=g.id AND l.local LIKE ?) ORDER BY g.ativo DESC, g.nome"); $st->execute(["%$q%", "%$q%"]); $lista = $st->fetchAll();
$k = $conn->query('SELECT COUNT(*) n, COALESCE(SUM(salario),0) folha FROM guias WHERE ativo=1')->fetch();
admTopo('Guias', 'guias');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h4 mb-0">Guias</h1><?php if (!$editando): ?><a class="btn btn-primary" href="?novo=1">➕ Novo guia</a><?php else: ?><a class="btn btn-outline-secondary" href="guias.php">← Voltar à lista</a><?php endif; ?></div>
<?php if ($editando): ?>
<form method="post" enctype="multipart/form-data" class="card card-body vstack gap-3" style="max-width:820px"><?= csrfCampo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>">
  <h2 class="h5 mb-0"><?= $ed['id'] ? 'Editar guia' : 'Novo guia' ?></h2>
  <div class="d-flex gap-3 align-items-center"><?= avatar($ed['nome'] ?: 'Novo guia', $ed['foto'], 84) ?><div class="flex-fill"><label class="form-label small mb-0">Foto (JPG/PNG/WebP até 3 MB)</label><input class="form-control form-control-sm mb-1" type="file" name="foto_arq" accept="image/jpeg,image/png,image/webp"><input class="form-control form-control-sm" name="foto_url" placeholder="…ou URL da foto (https://...)" value="<?= preg_match('~^https?://~', (string)$ed['foto']) ? e($ed['foto']) : '' ?>">
    <?php if ($ed['foto']): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remover_foto_arq" value="1" id="rmf"><label class="form-check-label small" for="rmf">Remover foto atual</label></div><?php endif; ?></div></div>
  <div class="row g-2"><div class="col-md-8"><label class="form-label small mb-0">Nome completo</label><input class="form-control" name="nome" value="<?= e($ed['nome']) ?>" required></div>
    <?php if (adminPode('salario')): ?><div class="col-md-4"><label class="form-label small mb-0">Salário mensal (R$)</label><input class="form-control" type="number" step="0.01" min="0" name="salario" value="<?= e($ed['salario']) ?>" required></div><?php endif; ?>
    <div class="col-md-6"><label class="form-label small mb-0">E-mail</label><input class="form-control" type="email" name="email" value="<?= e($ed['email']) ?>"></div>
    <div class="col-md-6"><label class="form-label small mb-0">Telefone</label><input class="form-control" name="telefone" value="<?= e($ed['telefone']) ?>"></div>
    <div class="col-md-8"><label class="form-label small mb-0">Idiomas</label><input class="form-control" name="idiomas" value="<?= e($ed['idiomas']) ?>" placeholder="Português, Inglês, Italiano"></div>
    <div class="col-md-4"><label class="form-label small mb-0">Admissão</label><input class="form-control" type="date" name="data_admissao" value="<?= e($ed['data_admissao']) ?>"></div>
    <div class="col-12"><label class="form-label small mb-0">Locais de atuação (separe por vírgula ou linha)</label><textarea class="form-control" name="locais" rows="2" placeholder="Itália, Malta, Grécia"><?= e($locais) ?></textarea></div>
    <div class="col-12"><label class="form-label small mb-0">Apresentação (aparece no site)</label><textarea class="form-control" name="bio" rows="3"><?= e($ed['bio']) ?></textarea></div>
    <div class="col-md-7"><label class="form-label small mb-0">Conta de login vinculada (opcional)</label><select class="form-select" name="usuario_id"><option value="0">— Nenhuma —</option><?php foreach ($contas as $i => $n): ?><option value="<?= (int)$i ?>" <?= $i == $ed['usuario_id'] ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="criar_acesso" id="ca" value="1"><label class="form-check-label small" for="ca">Criar acesso ao painel do guia (o e-mail acima vira o login e uma senha temporária é gerada)</label></div></div>
    <div class="col-md-5 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="ativo" id="a" <?= $ed['ativo'] ? 'checked' : '' ?>><label class="form-check-label" for="a">Guia ativo</label></div></div></div>
  <div class="form-text">O salário é confidencial: aparece só para Administrador e Financeiro, nunca no site.</div>
  <button class="btn btn-primary btn-lg">Salvar guia</button></form>
<?php else: ?>
<div class="row row-cols-1 row-cols-md-3 g-3 mb-4"><div class="col"><div class="card kpi"><div class="card-body"><div class="small text-body-secondary">Guias ativos</div><div class="h4 mb-0"><?= (int)$k['n'] ?></div></div></div></div>
<?php if (adminPode('salario')): ?><div class="col"><div class="card kpi"><div class="card-body"><div class="small text-body-secondary">Folha mensal (ativos)</div><div class="h4 mb-0"><?= brl($k['folha']) ?></div></div></div></div><?php endif; ?>
<?php if (adminPode('salario')): ?><div class="col"><div class="card kpi"><div class="card-body"><div class="small text-body-secondary">Média salarial</div><div class="h4 mb-0"><?= brl($k['n'] ? $k['folha'] / $k['n'] : 0) ?></div></div></div></div><?php endif; ?></div>
<form class="mb-3" method="get"><div class="input-group input-group-sm" style="max-width:420px"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar nome ou local"><button class="btn btn-outline-primary">Buscar</button></div></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Guia</th><th>Locais</th><th>Viagens</th><?php if (adminPode('salario')): ?><th>Salário</th><?php endif; ?><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $g): ?><tr class="<?= $g['ativo'] ? '' : 'opacity-50' ?>"><td><div class="d-flex gap-2 align-items-center"><?= avatar($g['nome'], $g['foto'], 44) ?><div><strong><?= e($g['nome']) ?></strong><br><small class="text-body-secondary"><?= e($g['idiomas']) ?></small></div></div></td>
  <td style="max-width:260px"><?php foreach (array_slice(array_filter(explode('|', (string)$g['locais'])), 0, 5) as $l): ?><span class="badge text-bg-light border"><?= e($l) ?></span> <?php endforeach; ?></td>
  <td><?= (int)$g['n_viagens'] ?></td><?php if (adminPode('salario')): ?><td class="text-nowrap"><?= brl($g['salario']) ?></td><?php endif; ?><td><?= $g['ativo'] ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td>
  <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int)$g['id'] ?>">Editar</a>
    <form method="post" class="d-inline"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-sm btn-outline-secondary" name="acao" value="ativar"><?= $g['ativo'] ? 'Desativar' : 'Ativar' ?></button>
      <button class="btn btn-sm btn-link text-danger" name="acao" value="excluir" onclick="return confirm('Excluir este guia?')" aria-label="Excluir">🗑</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php admRodape();
