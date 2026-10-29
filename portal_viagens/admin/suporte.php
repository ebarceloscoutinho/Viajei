<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('suporte');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? '';
    if ($acao === 'contatos') {
        $u = $conn->prepare('UPDATE configuracoes SET valor=? WHERE chave=?');
        foreach (($_POST['cfg'] ?? []) as $chave => $v) $u->execute([mb_substr(trim($v), 0, 255), $chave]);
        flash('Contatos atualizados. Já aparecem no site (rodapé e página de suporte).'); redirecionar('suporte.php?aba=contatos');
    } elseif ($acao === 'responder') {
        $id = (int)($_POST['id'] ?? 0); $resp = trim($_POST['resposta'] ?? ''); $stt = ($_POST['status'] ?? '') === 'fechado' ? 'fechado' : 'respondido';
        if ($resp === '') flash('Escreva a resposta.', 'danger');
        else { $conn->prepare('UPDATE chamados SET resposta=?, status=?, respondido_em=NOW() WHERE id=?')->execute([mb_substr($resp, 0, 3000), $stt, $id]);
            $cc = $conn->prepare('SELECT nome,email,assunto,usuario_id FROM chamados WHERE id=?'); $cc->execute([$id]);
            if ($c1 = $cc->fetch()) enviarEvento('chamado_respondido', $c1['email'], ['nome' => $c1['nome'], 'assunto' => $c1['assunto'], 'resposta' => $resp], $c1['usuario_id']);
            flash('Resposta registrada. O cliente logado a vê em "Suporte". Para quem não tem conta, responda também por e-mail.'); }
        redirecionar('suporte.php');
    }
}
$aba = ($_GET['aba'] ?? '') === 'contatos' ? 'contatos' : 'chamados'; $fs = $_GET['st'] ?? 'aberto';
$cfg = $conn->query("SELECT * FROM configuracoes WHERE LEFT(chave,4) <> 'sys_' ORDER BY rotulo")->fetchAll();
$sql = 'SELECT * FROM chamados' . (in_array($fs, ['aberto', 'respondido', 'fechado'], true) ? ' WHERE status=' . $conn->quote($fs) : '') . ' ORDER BY id DESC LIMIT 100';
$chamados = $conn->query($sql)->fetchAll();
admTopo('Suporte', 'suporte');
?>
<div class="d-flex gap-2 mb-3"><a class="chip <?= $aba === 'chamados' ? 'ativo' : '' ?>" href="?">Chamados</a><a class="chip <?= $aba === 'contatos' ? 'ativo' : '' ?>" href="?aba=contatos">Contatos exibidos no site</a></div>
<?php if ($aba === 'contatos'): ?>
<form method="post" class="card card-body vstack gap-2" style="max-width:640px"><?= csrfCampo() ?><input type="hidden" name="acao" value="contatos">
  <?php foreach ($cfg as $c): ?><div><label class="form-label small mb-0"><?= e($c['rotulo']) ?></label><input class="form-control" name="cfg[<?= e($c['chave']) ?>]" value="<?= e($c['valor']) ?>" maxlength="255"></div><?php endforeach; ?>
  <button class="btn btn-primary mt-2">Salvar contatos</button><div class="form-text">Troque os valores de exemplo (e-mail, telefone, WhatsApp) pelos reais da agência.</div></form>
<?php else: ?>
<div class="d-flex gap-2 mb-3 flex-wrap"><?php foreach (['aberto' => 'Abertos', 'respondido' => 'Respondidos', 'fechado' => 'Fechados', '' => 'Todos'] as $k => $v): ?><a class="chip <?= $fs === $k ? 'ativo' : '' ?>" href="?st=<?= $k ?>"><?= $v ?></a><?php endforeach; ?></div>
<?php foreach ($chamados as $c): ?><div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-2 flex-wrap"><div><strong><?= e($c['assunto']) ?></strong><div class="small text-body-secondary"><?= e($c['nome']) ?> · <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a> · <?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?><?= $c['usuario_id'] ? ' · cliente cadastrado' : '' ?></div></div>
  <span class="badge text-bg-<?= $c['status'] === 'aberto' ? 'warning' : ($c['status'] === 'respondido' ? 'success' : 'secondary') ?> align-self-start"><?= e($c['status']) ?></span></div>
  <p class="mt-2 mb-2"><?= nl2br(e($c['mensagem'])) ?></p>
  <?php if ($c['resposta']): ?><div class="border-start border-3 ps-2 small mb-2"><strong>Resposta:</strong> <?= nl2br(e($c['resposta'])) ?></div><?php endif; ?>
  <form method="post" class="row g-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="responder"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="col-md-8"><textarea class="form-control form-control-sm" name="resposta" rows="2" placeholder="Escreva a resposta..."><?= e($c['resposta']) ?></textarea></div>
    <div class="col-md-4 d-flex gap-2"><button class="btn btn-sm btn-primary flex-fill" name="status" value="respondido">Responder</button><button class="btn btn-sm btn-outline-secondary" name="status" value="fechado">Fechar</button></div></form></div></div><?php endforeach; ?>
<?php if (!$chamados): ?><div class="alert alert-info">Nenhum chamado nesta lista.</div><?php endif; ?>
<?php endif; ?>
<?php admRodape();
