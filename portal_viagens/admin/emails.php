<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('emails');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar();
    if (($_POST['acao'] ?? '') === 'processar') { $n = processarFilaEmails(50, true); flash($n . ' e-mail(s) enviado(s) agora.', $n ? 'success' : 'warning'); }
    elseif (($_POST['acao'] ?? '') === 'reenviar') {
        $st = $conn->prepare('SELECT * FROM emails_fila WHERE id=?'); $st->execute([(int)$_POST['id']]); $m = $st->fetch();
        if ($m) flash(tentarEnvio($conn, $m) ? 'Enviado.' : 'Ainda não foi possível enviar: o servidor não tem e-mail configurado.', 'warning');
    }
    redirecionar('emails.php' . (isset($_GET['st']) ? '?st=' . urlencode($_GET['st']) : ''));
}
$fs = $_GET['st'] ?? ''; $where = in_array($fs, ['pendente', 'enviado', 'falhou'], true) ? ' WHERE status=' . $conn->quote($fs) : '';
[$off, $pag, $pags] = paginar((int)$conn->query('SELECT COUNT(*) FROM emails_fila' . $where)->fetchColumn(), 25);
$lista = $conn->query('SELECT * FROM emails_fila' . $where . ' ORDER BY id DESC LIMIT ' . $off . ', 25')->fetchAll();
$c = $conn->query("SELECT status, COUNT(*) n FROM emails_fila GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
admTopo('E-mails', 'emails');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div class="d-flex gap-2 flex-wrap"><?php foreach (['' => 'Todos', 'pendente' => 'Pendentes', 'enviado' => 'Enviados', 'falhou' => 'Falharam'] as $k => $v): ?><a class="chip <?= $fs === $k ? 'ativo' : '' ?>" href="?st=<?= $k ?>"><?= $v ?><?= $k ? ' (' . (int)($c[$k] ?? 0) . ')' : '' ?></a><?php endforeach; ?></div>
  <form method="post"><?= csrfCampo() ?><button class="btn btn-outline-primary btn-sm" name="acao" value="processar">📤 Tentar enviar pendentes e falhas</button></form></div>
<?php if (!empty($c['falhou'])): ?><div class="alert alert-warning small">Há e-mails que falharam: o servidor (por exemplo, XAMPP local) não tem SMTP/sendmail configurado. Configure em <code>php.ini</code> (<code>sendmail_path</code> ou <code>SMTP</code>) ou use um serviço de hospedagem com e-mail; depois use o botão acima.</div><?php endif; ?>
<div class="vstack gap-2"><?php foreach ($lista as $m): ?><div class="card"><div class="card-body py-2"><div class="d-flex justify-content-between gap-2 flex-wrap"><div style="min-width:0"><strong><?= e($m['assunto']) ?></strong><div class="small text-body-secondary text-break">para <?= e($m['para']) ?> · <?= e($m['tipo']) ?> · <?= e($m['created_at']) ?></div></div>
  <div class="text-end"><span class="badge text-bg-<?= $m['status'] === 'enviado' ? 'success' : ($m['status'] === 'falhou' ? 'danger' : 'warning') ?>"><?= e($m['status']) ?></span> <small class="text-body-secondary"><?= (int)$m['tentativas'] ?> tent.</small>
    <?php if ($m['status'] !== 'enviado'): ?><form method="post" class="d-inline"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline-secondary" name="acao" value="reenviar">Reenviar</button></form><?php endif; ?></div></div>
  <?php if ($m['erro']): ?><div class="small text-danger"><?= e($m['erro']) ?></div><?php endif; ?>
  <details class="mt-1"><summary class="small text-primary" style="cursor:pointer">Ver mensagem</summary><iframe sandbox title="Prévia" class="w-100 border rounded mt-2" style="height:340px;background:#fff" srcdoc="<?= e($m['corpo']) ?>"></iframe></details></div></div><?php endforeach; ?>
<?php if (!$lista): ?><div class="alert alert-info">Nenhum e-mail na fila.</div><?php endif; ?></div>
<?= paginacaoHtml($pag, $pags) ?>
<?php admRodape();
