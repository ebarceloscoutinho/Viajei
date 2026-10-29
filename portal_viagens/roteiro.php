<?php
require_once __DIR__ . '/inc/app.php';

function carregarRoteiro(PDO $c, $id) { $st = $c->prepare('SELECT * FROM roteiros WHERE id=?'); $st->execute([$id]); return $st->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirLogin(); csrfVerificar();
    $acao = $_POST['acao'] ?? ''; $uid = usuario()['id'];
    $rid = (int)($_POST['roteiro_id'] ?? $_POST['id'] ?? 0); $r = carregarRoteiro($conn, $rid);
    if (!$r) redirecionar('roteiros.php');

    if ($acao === 'copiar') {   // copia um roteiro público/modelo para a conta do usuário
        if (!$r['publico']) redirecionar('roteiros.php');
        $conn->beginTransaction();
        $conn->prepare('INSERT INTO roteiros (usuario_id,titulo,destino,dias) VALUES (?,?,?,?)')->execute([$uid, $r['titulo'] . ' (cópia)', $r['destino'], $r['dias']]);
        $novo = (int)$conn->lastInsertId();
        $conn->prepare('INSERT INTO roteiro_itens (roteiro_id,dia,horario,passeio_id,nota) SELECT ?,dia,horario,passeio_id,nota FROM roteiro_itens WHERE roteiro_id=?')->execute([$novo, $rid]);
        $conn->commit(); flash('Roteiro copiado! Agora é só adaptar.'); redirecionar('roteiro.php?id=' . $novo);
    }
    if ((int)$r['usuario_id'] !== $uid) { http_response_code(403); exit('Você não pode editar este roteiro.'); }

    if ($acao === 'add_item') {
        $dia = max(1, min(30, (int)($_POST['dia'] ?? 1))); $pid = (int)($_POST['passeio_id'] ?? 0) ?: null;
        $nota = trim($_POST['nota'] ?? ''); $hor = preg_match('/^\d{2}:\d{2}$/', $_POST['horario'] ?? '') ? $_POST['horario'] : null;
        if (!$pid && $nota === '') { flash('Escolha um passeio ou escreva uma nota.', 'danger'); redirecionar('roteiro.php?id=' . $rid); }
        $conn->prepare('INSERT INTO roteiro_itens (roteiro_id,dia,horario,passeio_id,nota) VALUES (?,?,?,?,?)')->execute([$rid, $dia, $hor, $pid, $nota ?: null]);
        if ($dia > $r['dias']) $conn->prepare('UPDATE roteiros SET dias=? WHERE id=?')->execute([$dia, $rid]);
        flash('Adicionado ao roteiro.');
    } elseif ($acao === 'del_item') {
        $conn->prepare('DELETE FROM roteiro_itens WHERE id=? AND roteiro_id=?')->execute([(int)$_POST['item_id'], $rid]);
    } elseif ($acao === 'compartilhar') {
        $conn->prepare('UPDATE roteiros SET token_compartilhar=COALESCE(token_compartilhar,?) WHERE id=?')->execute([bin2hex(random_bytes(16)), $rid]); flash('Link de compartilhamento ativado.');
    } elseif ($acao === 'descompartilhar') {
        $conn->prepare('UPDATE roteiros SET token_compartilhar=NULL WHERE id=?')->execute([$rid]); flash('Compartilhamento desativado.');
    } elseif ($acao === 'excluir') {
        $conn->prepare('DELETE FROM roteiros WHERE id=?')->execute([$rid]); flash('Roteiro excluído.'); redirecionar('roteiros.php');
    }
    redirecionar('roteiro.php?id=' . $rid);
}

$u = usuario(); $tk = trim($_GET['t'] ?? ''); $porToken = false;   // link de compartilhamento (somente leitura)
if (preg_match('/^[a-f0-9]{32}$/', $tk)) { $st0 = $conn->prepare('SELECT * FROM roteiros WHERE token_compartilhar=?'); $st0->execute([$tk]); $r = $st0->fetch(); $id = $r ? (int)$r['id'] : 0; $porToken = (bool)$r; }
else { $id = (int)($_GET['id'] ?? 0); $r = carregarRoteiro($conn, $id); }
if (!$r || (!$r['publico'] && !$porToken && (!$u || ($u['id'] != $r['usuario_id'] && $u['tipo'] < TIPO_ADMIN)))) { http_response_code(404); topo('Roteiro'); echo '<div class="alert alert-warning">Roteiro não encontrado.</div>'; rodape(); exit; }
$pode = $u && (int)$r['usuario_id'] === $u['id'];
$st = $conn->prepare('SELECT i.*, p.titulo, p.preco, p.emoji, p.duracao_horas FROM roteiro_itens i LEFT JOIN passeios p ON p.id=i.passeio_id WHERE i.roteiro_id=? ORDER BY i.dia, ISNULL(i.horario), i.horario, i.id');
$st->execute([$id]); $porDia = []; $total = 0;
foreach ($st->fetchAll() as $i) { $porDia[$i['dia']][] = $i; $total += (float)$i['preco']; }
$catalogo = $pode ? $conn->query('SELECT id,titulo,destino FROM passeios WHERE ativo=1 ORDER BY destino,titulo')->fetchAll() : [];
topo($r['titulo']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
  <div><h1 class="h3 mb-1"><?= e($r['titulo']) ?></h1><div class="text-body-secondary">📍 <?= e($r['destino']) ?> · <?= (int)$r['dias'] ?> dias<?= $r['data_inicio'] ? ' · início em ' . dataBr($r['data_inicio']) : '' ?></div></div>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">🖨️ Imprimir</button>
    <?php if ($u && !$pode && $r['publico']): ?><form method="post"><?= csrfCampo() ?><input type="hidden" name="acao" value="copiar"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-accent btn-sm">Usar como base</button></form><?php endif; ?>
    <?php if ($pode): ?><form method="post" onsubmit="return confirm('Excluir este roteiro?')"><?= csrfCampo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-danger btn-sm">Excluir</button></form><?php endif; ?>
  </div>
</div>
<?php if (!$u && $r['publico']): ?><div class="alert alert-info no-print"><a href="login.php">Entre</a> para copiar este modelo e personalizar.</div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-8">
    <?php for ($d = 1; $d <= $r['dias']; $d++): ?>
      <div class="mb-4"><h2 class="h5 dia-titulo">Dia <?= $d ?><?= $r['data_inicio'] ? ' · ' . date('d/m', strtotime($r['data_inicio'] . ' +' . ($d - 1) . ' day')) : '' ?></h2>
      <?php if (empty($porDia[$d])): ?><p class="text-body-secondary small ms-3">Nada planejado ainda.</p><?php else: ?><ul class="list-group">
        <?php foreach ($porDia[$d] as $i): ?><li class="list-group-item d-flex justify-content-between align-items-center">
          <div><span class="text-body-secondary me-2"><?= e($i['horario'] ?: '—') ?></span>
            <?php if ($i['passeio_id']): ?><?= e($i['emoji']) ?> <a href="passeio.php?id=<?= (int)$i['passeio_id'] ?>"><?= e($i['titulo']) ?></a> <span class="badge text-bg-light border"><?= brl($i['preco']) ?></span>
            <?php else: ?>📝 <?= e($i['nota']) ?><?php endif; ?></div>
          <?php if ($pode): ?><form method="post" class="no-print"><?= csrfCampo() ?><input type="hidden" name="acao" value="del_item"><input type="hidden" name="roteiro_id" value="<?= $id ?>"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-link text-danger">remover</button></form><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></div>
    <?php endfor; ?>
  </div>
  <div class="col-lg-4">
    <?php if ($pode): ?><div class="card mb-3 no-print"><div class="card-body"><h2 class="h6">🔗 Compartilhar</h2>
      <?php if ($r['token_compartilhar']): $lk = siteUrl() . '/roteiro.php?t=' . $r['token_compartilhar']; ?><input class="form-control form-control-sm mb-2" value="<?= e($lk) ?>" readonly onclick="this.select()" aria-label="Link do roteiro">
        <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/?text=<?= urlencode('Olha o nosso roteiro: ' . $lk) ?>">WhatsApp</a><form method="post"><?= csrfCampo() ?><input type="hidden" name="acao" value="descompartilhar"><input type="hidden" name="roteiro_id" value="<?= $id ?>"><button class="btn btn-sm btn-outline-secondary">Desativar link</button></form></div>
      <?php else: ?><p class="small text-body-secondary">Gere um link só de leitura para mostrar o roteiro a quem viaja com você.</p><form method="post"><?= csrfCampo() ?><input type="hidden" name="acao" value="compartilhar"><input type="hidden" name="roteiro_id" value="<?= $id ?>"><button class="btn btn-sm btn-primary">Gerar link</button></form><?php endif; ?></div></div><?php endif; ?>
    <div class="card mb-3"><div class="card-body"><div class="text-body-secondary small">Custo estimado de passeios (por pessoa)</div><div class="h3 text-primary mb-0"><?= brl($total) ?></div></div></div>
    <?php if ($pode): ?><div class="card no-print"><div class="card-body"><h2 class="h6">Adicionar ao roteiro</h2>
      <form method="post" class="vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="add_item"><input type="hidden" name="roteiro_id" value="<?= $id ?>">
        <div class="row g-2"><div class="col-6"><input class="form-control" type="number" name="dia" min="1" max="30" value="1" title="Dia"></div><div class="col-6"><input class="form-control" type="time" name="horario"></div></div>
        <select class="form-select" name="passeio_id"><option value="0">— Passeio do catálogo (opcional) —</option><?php foreach ($catalogo as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['destino'] . ' · ' . $c['titulo']) ?></option><?php endforeach; ?></select>
        <input class="form-control" name="nota" maxlength="255" placeholder="ou uma nota (hotel, restaurante, deslocamento...)">
        <button class="btn btn-primary">Adicionar</button></form></div></div><?php endif; ?>
  </div>
</div>
<?php rodape();
