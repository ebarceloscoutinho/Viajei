<?php
require_once __DIR__ . '/inc/app.php';
$id = (int)($_GET['id'] ?? 0);
$r = buscarPasseios($conn, 'AND p.id = ?', [$id]);
if (!$r) { http_response_code(404); topo('Não encontrado'); echo '<div class="alert alert-warning">Passeio não encontrado.</div>'; rodape(); exit; }
$p = $r[0];
$st = $conn->prepare('SELECT * FROM saidas WHERE passeio_id=? AND data_saida>=CURDATE() ORDER BY data_saida'); $st->execute([$id]); $saidas = $st->fetchAll();
$meus = [];
if (usuario()) { $st = $conn->prepare('SELECT id,titulo,dias FROM roteiros WHERE usuario_id=? ORDER BY id DESC'); $st->execute([usuario()['id']]); $meus = $st->fetchAll(); }
$fav = false; $podeAvaliar = false; $minha = null;
if (usuario()) {
    $uid = usuario()['id'];
    $x = $conn->prepare('SELECT 1 FROM favoritos WHERE usuario_id=? AND passeio_id=?'); $x->execute([$uid, $id]); $fav = (bool)$x->fetch();
    $x = $conn->prepare("SELECT COUNT(*) FROM reservas r JOIN saidas s ON s.id=r.saida_id WHERE r.usuario_id=? AND s.passeio_id=? AND r.status='confirmada'" . (AVALIACAO_EXIGE_VIAGEM_REALIZADA ? ' AND s.data_saida <= CURDATE()' : ''));
    $x->execute([$uid, $id]); $podeAvaliar = (bool)$x->fetchColumn();
    $x = $conn->prepare('SELECT * FROM avaliacoes WHERE usuario_id=? AND passeio_id=?'); $x->execute([$uid, $id]); $minha = $x->fetch();
}
$x = $conn->prepare('SELECT a.*, u.nome FROM avaliacoes a JOIN usuarios u ON u.id=a.usuario_id WHERE a.passeio_id=? AND a.visivel=1 ORDER BY a.created_at DESC LIMIT 20'); $x->execute([$id]); $avals = $x->fetchAll();
function nomeCurto($n) { $p = explode(' ', trim($n)); return $p[0] . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : ''); }
$GLOBALS['descricao'] = mb_strimwidth($p['titulo'] . ' — ' . $p['descricao'], 0, 155, '…');
topo($p['titulo']);
?>
<nav class="small mb-3"><a href="passeios.php">Passeios</a> / <?= e($p['destino']) ?></nav>
<div class="row g-4">
  <div class="col-lg-7">
    <?= capa($p['emoji'], $p['hue'], 'grande', $p['motivo'] ?? '', $p['imagem'] ?? '', $p['escopo'] === 'internacional' ? $p['pais'] : 'Brasil') ?>
    <div class="d-flex justify-content-between align-items-start gap-2 mt-3"><h1 class="h3 mb-0"><?= e($p['titulo']) ?></h1>
      <form method="post" action="favoritar.php"><?= csrfCampo() ?><input type="hidden" name="passeio_id" value="<?= $id ?>"><button class="btn btn-outline-danger btn-sm" title="Favoritar" aria-label="Favoritar"><?= $fav ? '♥' : '♡' ?></button></form></div>
    <?php if ((int)$p['n_aval'] > 0): ?><a class="small text-decoration-none" href="#avaliacoes"><?= estrelas($p['nota_media']) ?> <?= number_format($p['nota_media'], 1, ',', '') ?> · <?= (int)$p['n_aval'] ?> avaliação(ões)</a><?php endif; ?>
    <p class="text-body-secondary"><?= $p['escopo'] === 'internacional' ? '🌍 Internacional · ' . e($p['pais']) : '🇧🇷 Nacional' ?> · <?= e($p['icone'] . ' ' . $p['categoria']) ?> · 📍 <?= e($p['destino']) ?> · ⏱️ <?= (int)$p['dias'] > 1 ? (int)$p['dias'] . ' dias' : rtrim(rtrim(number_format($p['duracao_horas'], 1, ',', ''), '0'), ',') . ' h' ?><?= $p['guia'] ? ' · 🧑‍🏫 Guia: ' . e($p['guia']) : '' ?></p>
    <p><?= nl2br(e($p['descricao'])) ?></p>
    <?php if (trim((string)$p['atracoes']) !== ''): ?><h2 class="h6">Principais atrações</h2><ul class="row row-cols-1 row-cols-sm-2 list-unstyled g-1 mb-3"><?php foreach (array_filter(array_map('trim', explode(';', $p['atracoes']))) as $a): ?><li class="col">✔️ <?= e($a) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php $vid = youtubeId($p['video_id'] ?? ''); $busca = videoBusca(($p['escopo'] === 'internacional' ? $p['pais'] . ' ' : '') . $p['destino'] . ' turismo o que fazer'); ?>
    <h2 class="h6 mt-4">🎬 Conheça o destino</h2>
    <?php if ($vid): ?><div class="ratio ratio-16x9 rounded-4 overflow-hidden mb-2 shadow-sm"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($vid) ?>" title="Vídeo: <?= e($p['titulo']) ?>" loading="lazy" allow="encrypted-media; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div><?php endif; ?>
    <a class="btn btn-outline-danger btn-sm mb-3" target="_blank" rel="noopener" href="<?= e($busca) ?>">▶ <?= $vid ? 'Mais vídeos deste destino' : 'Assistir vídeos deste destino no YouTube' ?></a>
    <?php if ($p['guia']): ?><h2 class="h6 mt-3">🧑‍🏫 Seu guia</h2>
    <a class="card card-body flex-row gap-3 align-items-center text-decoration-none mb-3" href="equipe.php?id=<?= (int)$p['guia_id'] ?>"><?= avatar($p['guia'], $p['guia_foto'], 64) ?>
      <div><strong class="text-body"><?= e($p['guia']) ?></strong><div class="small text-body-secondary"><?= e($p['guia_idiomas']) ?></div><div class="small text-body-secondary"><?= e(mb_strimwidth((string)$p['guia_bio'], 0, 130, '…')) ?></div></div></a><?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <form method="post" action="comparar.php"><?= csrfCampo() ?><input type="hidden" name="acao" value="add"><input type="hidden" name="passeio_id" value="<?= $id ?>"><button class="btn btn-outline-secondary btn-sm">⚖ Comparar</button></form>
      <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($p['destino'] . ', ' . ($p['escopo'] === 'internacional' ? $p['pais'] : 'Brasil')) ?>">📍 Ver no mapa</a>
      <a class="btn btn-outline-primary btn-sm" href="quiz_custo.php?pais=<?= urlencode($p['pais']) ?>&dias=<?= max(1, (int)$p['dias']) ?>">💰 Calcular o custo total desta viagem</a>
    </div>
    <div class="alert alert-light border small"><strong>Pagamento:</strong> a vaga fica reservada por <?= PRAZO_PAGAMENTO_HORAS ?> horas para você pagar. Sem pagamento nem cancelamento nesse prazo, a reserva expira e novas reservas ficam bloqueadas por <?= BLOQUEIO_HORAS ?> horas.<br><strong>Cancelamento:</strong> grátis até <?= DIAS_SEM_TAXA ?> dias antes da saída (ou em até <?= DIAS_ARREPENDIMENTO ?> dias após a compra, por arrependimento); depois, taxa de <?= (int)(TAXA_CANCELAMENTO * 100) ?>% do valor pago.</div>
  </div>
  <div class="col-lg-5"><div class="reserva-fixa">
    <div class="card mb-3" id="reservar"><div class="card-body">
      <div class="d-flex justify-content-between align-items-baseline mb-3"><span class="text-body-secondary">por pessoa</span><span class="h3 text-primary mb-0"><?= brl($p['preco']) ?></span></div>
      <?php if (!$saidas): ?><div class="alert alert-secondary mb-0">Sem datas disponíveis no momento.</div>
      <?php else: ?>
      <form method="post" action="reservar.php"><?= csrfCampo() ?><input type="hidden" name="passeio_id" value="<?= $id ?>">
        <label class="form-label">Data da saída</label>
        <select class="form-select mb-3" name="saida_id" required>
          <?php foreach ($saidas as $s): ?><option value="<?= (int)$s['id'] ?>"><?= dataBr($s['data_saida']) ?> — <?= $s['vagas_disponiveis'] > 0 ? (int)$s['vagas_disponiveis'] . ' vagas' : 'LOTADO (lista de espera)' ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Pessoas</label><input class="form-control mb-3" type="number" name="qtd" min="1" max="10" value="1" required>
        <input class="form-control mb-3" name="cupom" maxlength="30" placeholder="Cupom de desconto (opcional)" autocomplete="off">
        <div class="d-grid gap-2"><button class="btn btn-accent" name="acao" value="reservar">Reservar e pagar</button>
        <button class="btn btn-outline-primary" name="acao" value="espera">Entrar na lista de espera</button></div>
        <div class="form-text">A lista de espera vale para a data escolhida acima.</div>
      </form><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body"><h2 class="h6">Adicionar a um roteiro</h2>
      <?php if (!usuario()): ?><p class="small mb-0"><a href="login.php">Entre</a> para montar seus roteiros.</p>
      <?php elseif (!$meus): ?><p class="small mb-0">Você ainda não tem roteiros. <a href="roteiros.php#novo">Crie o primeiro</a>.</p>
      <?php else: ?><form method="post" action="roteiro.php" class="row g-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="add_item"><input type="hidden" name="passeio_id" value="<?= $id ?>">
        <div class="col-8"><select class="form-select form-select-sm" name="roteiro_id"><?php foreach ($meus as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['titulo']) ?></option><?php endforeach; ?></select></div>
        <div class="col-4"><input class="form-control form-control-sm" type="number" name="dia" min="1" value="1" title="Dia"></div>
        <div class="col-12 d-grid"><button class="btn btn-sm btn-outline-primary">Adicionar ao roteiro</button></div></form><?php endif; ?>
    </div></div>
  </div></div>
</div>
<section class="mt-5" id="avaliacoes"><h2 class="h5">Avaliações</h2>
  <?php if ($podeAvaliar): ?><form method="post" action="avaliar.php" class="card card-body mb-3 vstack gap-2"><?= csrfCampo() ?><input type="hidden" name="passeio_id" value="<?= $id ?>">
    <strong><?= $minha ? 'Editar minha avaliação' : 'Avalie esta viagem' ?></strong>
    <select class="form-select" name="nota" required><option value="">Sua nota</option><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?= $n ?>" <?= $minha && (int)$minha['nota'] === $n ? 'selected' : '' ?>><?= str_repeat('★', $n) . str_repeat('☆', 5 - $n) ?> — <?= $n ?></option><?php endfor; ?></select>
    <textarea class="form-control" name="comentario" rows="3" maxlength="1000" placeholder="Conte como foi (opcional)"><?= e($minha['comentario'] ?? '') ?></textarea>
    <button class="btn btn-primary">Publicar avaliação</button></form>
  <?php elseif (usuario()): ?><p class="small text-body-secondary">Você poderá avaliar depois de <?= AVALIACAO_EXIGE_VIAGEM_REALIZADA ? 'realizar esta viagem' : 'confirmar uma reserva' ?>.</p><?php endif; ?>
  <?php foreach ($avals as $a): ?><div class="border-bottom py-2"><?= estrelas($a['nota']) ?> <strong class="ms-1"><?= e(nomeCurto($a['nome'])) ?></strong> <small class="text-body-secondary">· <?= dataBr($a['created_at']) ?></small>
    <?php if ($a['comentario']): ?><p class="mb-0"><?= nl2br(e($a['comentario'])) ?></p><?php endif; ?></div><?php endforeach; ?>
  <?php if (!$avals): ?><p class="text-body-secondary">Ainda sem avaliações.</p><?php endif; ?></section>
<?php if ($saidas): ?><div class="reserve-bar d-lg-none"><div><div class="small text-body-secondary">por pessoa</div><strong class="text-primary"><?= brl($p['preco']) ?></strong></div><a class="btn btn-accent" href="#reservar">Reservar</a></div><?php endif; ?>
<?php rodape();
