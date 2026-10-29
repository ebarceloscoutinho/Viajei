<?php
define('BASE', '../'); define('AREA', 'guia');
require_once __DIR__ . '/../inc/guia.php';
exigirGuia(); $g0 = guiaLogado(); $gid = $g0['guia_id'];
$st = $conn->prepare('SELECT * FROM guias WHERE id=?'); $st->execute([$gid]); $g = $st->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    if (($_POST['acao'] ?? '') === 'senha') {
        $us = $conn->prepare('SELECT senha FROM usuarios WHERE id=?'); $us->execute([$g0['id']]); $hash = $us->fetchColumn(); $n = $_POST['nova'] ?? '';
        if (!password_verify($_POST['atual'] ?? '', $hash)) flash('Senha atual incorreta.', 'danger');
        elseif (strlen($n) < 8 || $n !== ($_POST['nova2'] ?? '')) flash('A nova senha precisa ter 8+ caracteres e ser igual à confirmação.', 'danger');
        else { $conn->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($n, PASSWORD_DEFAULT), $g0['id']]); flash('Senha alterada.'); }
    } else {
        try {
            $foto = imagemDoForm('foto_arq', 'foto_url', 'guias', $g['foto']);
            $conn->prepare('UPDATE guias SET telefone=?, idiomas=?, bio=?, foto=? WHERE id=?')->execute([mb_substr(trim($_POST['telefone'] ?? ''), 0, 25) ?: null, mb_substr(trim($_POST['idiomas'] ?? ''), 0, 120) ?: null, trim($_POST['bio'] ?? '') ?: null, $foto, $gid]);
            flash('Perfil atualizado. Já aparece no site.');
        } catch (Exception $e) { flash($e->getMessage(), 'danger'); }
    }
    redirecionar('perfil.php');
}
$loc = $conn->prepare('SELECT local FROM guia_locais WHERE guia_id=? ORDER BY id'); $loc->execute([$gid]); $loc = $loc->fetchAll(PDO::FETCH_COLUMN);
guiaTopo('Meu perfil', 'perfil');
?>
<div class="row g-4"><div class="col-lg-7"><form method="post" enctype="multipart/form-data" class="card card-body vstack gap-3"><?= csrfCampo() ?><h1 class="h5 mb-0">Meu perfil público</h1>
  <div class="d-flex gap-3 align-items-center"><?= avatar($g['nome'], $g['foto'], 84) ?><div class="flex-fill"><label class="form-label small mb-0">Foto (JPG/PNG/WebP até 3 MB)</label><input class="form-control form-control-sm mb-1" type="file" name="foto_arq" accept="image/jpeg,image/png,image/webp"><input class="form-control form-control-sm" name="foto_url" placeholder="…ou URL da foto"></div></div>
  <div class="row g-2"><div class="col-md-6"><label class="form-label small mb-0">Telefone</label><input class="form-control" name="telefone" value="<?= e($g['telefone']) ?>"></div><div class="col-md-6"><label class="form-label small mb-0">Idiomas</label><input class="form-control" name="idiomas" value="<?= e($g['idiomas']) ?>"></div>
    <div class="col-12"><label class="form-label small mb-0">Apresentação</label><textarea class="form-control" name="bio" rows="4"><?= e($g['bio']) ?></textarea></div></div>
  <div class="small text-body-secondary">Locais de atuação (definidos pela administração): <?= e(implode(', ', $loc)) ?></div><button class="btn btn-primary">Salvar perfil</button></form></div>
  <div class="col-lg-5"><form method="post" class="card card-body vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="senha"><h2 class="h5">Alterar senha</h2><input class="form-control" type="password" name="atual" placeholder="Senha atual" required autocomplete="current-password"><input class="form-control" type="password" name="nova" placeholder="Nova senha (mín. 8)" required autocomplete="new-password"><input class="form-control" type="password" name="nova2" placeholder="Repita a nova senha" required autocomplete="new-password"><button class="btn btn-primary">Salvar senha</button></form></div></div>
<?php guiaRodape();
