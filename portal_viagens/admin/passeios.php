<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
exigirAdmin('passeios');
$NOMES = ['colosseu' => 'Coliseu', 'eiffel' => 'Torre Eiffel', 'cristo' => 'Cristo Redentor', 'acucar' => 'Pão de Açúcar', 'piramide' => 'Pirâmides', 'taj' => 'Mesquita / Taj Mahal', 'pagoda' => 'Pagode / templo asiático', 'fuji' => 'Monte Fuji', 'montanha' => 'Montanhas', 'praia' => 'Praia e coqueiro', 'cataratas' => 'Cachoeira / cataratas', 'dunas' => 'Dunas', 'castelo' => 'Castelo', 'ponte' => 'Ponte', 'opera' => 'Ópera de Sydney', 'estatua' => 'Estátua da Liberdade', 'burj' => 'Arranha-céu / torre', 'skyline' => 'Cidade (skyline)', 'moinho' => 'Moinho', 'fiorde' => 'Fiorde', 'safari' => 'Safári (acácia)', 'muralha' => 'Muralha', 'igreja' => 'Igreja histórica', 'machu' => 'Terraços andinos', 'partenon' => 'Templo clássico', 'aurora' => 'Aurora boreal', 'floresta' => 'Floresta', 'roda' => 'Roda-gigante (parque)', 'vulcao' => 'Vulcão', 'veleiro' => 'Veleiro'];
$mot = motivos();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerificar(); $acao = $_POST['acao'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'salvar') {
        try {
            $atual = null;
            if ($id) { $x = $conn->prepare('SELECT imagem FROM passeios WHERE id=?'); $x->execute([$id]); $atual = $x->fetchColumn() ?: null; }
            $titulo = trim($_POST['titulo'] ?? ''); $destino = trim($_POST['destino'] ?? '');
            if ($titulo === '' || $destino === '') throw new Exception('Título e destino são obrigatórios.');
            $escopo = ($_POST['escopo'] ?? '') === 'internacional' ? 'internacional' : 'nacional';
            $pais = $escopo === 'nacional' ? 'Brasil' : trim($_POST['pais'] ?? ''); if ($pais === '') throw new Exception('Informe o país da viagem internacional.');
            $vtxt = trim($_POST['video'] ?? ''); $vid = youtubeId($vtxt);
            if ($vtxt !== '' && !$vid) throw new Exception('Link do YouTube inválido. Cole o endereço do vídeo (youtube.com/watch?v=… ou youtu.be/…).');
            $g = (int)($_POST['guia_id'] ?? 0);
            if ($g) { $x = $conn->prepare('SELECT 1 FROM guias WHERE id=?'); $x->execute([$g]); if (!$x->fetch()) $g = 0; }
            $m = $_POST['motivo'] ?? ''; $m = isset($mot[$m]) ? $m : null;
            $imagem = imagemDoForm('imagem_arq', 'imagem_url', 'passeios', $atual);
            $d = [(int)$_POST['categoria_id'], $g ?: null, mb_substr($titulo, 0, 200), mb_substr($destino, 0, 120), trim($_POST['descricao'] ?? ''), max(0.5, (float)$_POST['duracao_horas']),
                  max(0, round((float)str_replace(',', '.', $_POST['preco'] ?? 0), 2)), mb_substr(trim($_POST['emoji'] ?? '') ?: '🌎', 0, 30), ((int)$_POST['hue'] % 360 + 360) % 360, $escopo, mb_substr($pais, 0, 80),
                  max(1, (int)$_POST['dias']), trim($_POST['atracoes'] ?? '') ?: null, $m, $imagem, $vid, isset($_POST['ativo']) ? 1 : 0];
            if ($id) { $conn->prepare('UPDATE passeios SET categoria_id=?,guia_id=?,titulo=?,destino=?,descricao=?,duracao_horas=?,preco=?,emoji=?,hue=?,escopo=?,pais=?,dias=?,atracoes=?,motivo=?,imagem=?,video_id=?,ativo=? WHERE id=?')->execute([...$d, $id]); }
            else { $conn->prepare('INSERT INTO passeios (categoria_id,guia_id,titulo,destino,descricao,duracao_horas,preco,emoji,hue,escopo,pais,dias,atracoes,motivo,imagem,video_id,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($d); $id = (int)$conn->lastInsertId(); }
            $chk = $conn->prepare('SELECT 1 FROM paises WHERE nome=?'); $chk->execute([$pais]);
            flash($id ? 'Viagem salva. Agora cadastre as datas de saída abaixo.' : 'Viagem salva.');
            if ($escopo === 'internacional' && !$chk->fetch()) flash('Viagem salva, mas o país "' . $pais . '" ainda não está no quiz de custo. Cadastre em Câmbio e custos.', 'warning');
            redirecionar("passeios.php?editar=$id");
        } catch (Exception $ex) { flash($ex->getMessage(), 'danger'); redirecionar('passeios.php' . ($id ? "?editar=$id" : '?novo=1')); }
    } elseif ($acao === 'ativar') { $conn->prepare('UPDATE passeios SET ativo = 1 - ativo WHERE id=?')->execute([$id]); flash('Status alterado.');
    } elseif ($acao === 'add_saida') {
        $vagas = max(1, (int)$_POST['vagas']); $data = $_POST['data_saida'] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { $conn->prepare('INSERT INTO saidas (passeio_id,data_saida,vagas_total,vagas_disponiveis) VALUES (?,?,?,?)')->execute([$id, $data, $vagas, $vagas]); flash('Saída adicionada.'); }
        redirecionar("passeios.php?editar=$id");
    } elseif ($acao === 'del_saida') {
        $n = $conn->prepare("SELECT COUNT(*) FROM reservas WHERE saida_id=? AND status IN ('aguardando_pagamento','em_analise','confirmada')"); $n->execute([(int)$_POST['saida_id']]);
        if ($n->fetchColumn() > 0) flash('Não é possível excluir: há reservas ativas nesta saída.', 'danger');
        else { $conn->prepare('DELETE FROM saidas WHERE id=? AND passeio_id=?')->execute([(int)$_POST['saida_id'], $id]); flash('Saída removida.'); }
        redirecionar("passeios.php?editar=$id");
    }
    redirecionar('passeios.php');
}
$cats = $conn->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();
$guias = $conn->query('SELECT id,nome FROM guias WHERE ativo=1 ORDER BY nome')->fetchAll();
$paises = $conn->query('SELECT nome FROM paises ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);
$ed = ['id' => 0, 'categoria_id' => 0, 'guia_id' => 0, 'titulo' => '', 'destino' => '', 'descricao' => '', 'duracao_horas' => 4, 'preco' => '', 'emoji' => '🌎', 'hue' => 190, 'escopo' => 'nacional', 'pais' => 'Brasil', 'dias' => 1, 'atracoes' => '', 'motivo' => 'praia', 'imagem' => '', 'video_id' => '', 'ativo' => 1];
$saidas = []; $editando = !empty($_GET['editar']) || !empty($_GET['novo']);
if (!empty($_GET['editar'])) {
    $st = $conn->prepare('SELECT * FROM passeios WHERE id=?'); $st->execute([(int)$_GET['editar']]); $ed = $st->fetch() ?: $ed;
    $st = $conn->prepare('SELECT * FROM saidas WHERE passeio_id=? ORDER BY data_saida'); $st->execute([$ed['id']]); $saidas = $st->fetchAll();
}
$q = trim($_GET['q'] ?? ''); $fe = $_GET['esc'] ?? '';
$sql = 'SELECT p.*, c.nome AS categoria, g.nome AS guia, (SELECT COUNT(*) FROM saidas s WHERE s.passeio_id=p.id AND s.data_saida>=CURDATE()) AS saidas FROM passeios p JOIN categorias c ON c.id=p.categoria_id LEFT JOIN guias g ON g.id=p.guia_id WHERE 1=1'; $par = [];
if ($q !== '') { $sql .= ' AND (p.titulo LIKE ? OR p.destino LIKE ? OR p.pais LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%"); }
if (in_array($fe, ['nacional', 'internacional'], true)) { $sql .= ' AND p.escopo=?'; $par[] = $fe; }
$cnt = $conn->prepare('SELECT COUNT(*) FROM (' . $sql . ') t'); $cnt->execute($par); [$off, $pag, $pags] = paginar((int)$cnt->fetchColumn(), 30);
$st = $conn->prepare($sql . ' ORDER BY p.id DESC LIMIT ' . $off . ', 30'); $st->execute($par); $lista = $st->fetchAll();
admTopo('Viagens e saídas', 'passeios');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h4 mb-0">Viagens</h1><?php if (!$editando): ?><a class="btn btn-primary" href="?novo=1">➕ Nova viagem</a><?php else: ?><a class="btn btn-outline-secondary" href="passeios.php">← Voltar à lista</a><?php endif; ?></div>

<?php if ($editando): ?>
<div class="row g-4">
  <div class="col-xl-7"><form method="post" enctype="multipart/form-data" class="card card-body vstack gap-3"><?= csrfCampo() ?><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>">
    <h2 class="h5 mb-0"><?= $ed['id'] ? 'Editar viagem' : 'Nova viagem' ?></h2>
    <div class="row g-2"><div class="col-12"><label class="form-label small mb-0">Título</label><input class="form-control" name="titulo" value="<?= e($ed['titulo']) ?>" required placeholder="Ex.: Roma, Florença e Veneza"></div>
      <div class="col-md-6"><label class="form-label small mb-0">Destino / cidades</label><input class="form-control" name="destino" value="<?= e($ed['destino']) ?>" required placeholder="Cidade - UF ou cidades visitadas"></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-0">Alcance</label><select class="form-select" name="escopo"><option value="nacional">🇧🇷 Nacional</option><option value="internacional" <?= $ed['escopo'] === 'internacional' ? 'selected' : '' ?>>🌍 Internacional</option></select></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-0">País</label><input class="form-control" name="pais" list="lista-paises" value="<?= e($ed['pais']) ?>"><datalist id="lista-paises"><?php foreach ($paises as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist></div></div>
    <div class="row g-2"><div class="col-6 col-md-3"><label class="form-label small mb-0">Preço p/ pessoa (R$)</label><input class="form-control" type="number" step="0.01" min="0" name="preco" value="<?= e($ed['preco']) ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-0">Dias</label><input class="form-control" type="number" min="1" name="dias" value="<?= (int)$ed['dias'] ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-0">Duração (h)</label><input class="form-control" type="number" step="0.5" min="0.5" name="duracao_horas" value="<?= e($ed['duracao_horas']) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-0">Categoria</label><select class="form-select" name="categoria_id" required><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $ed['categoria_id'] ? 'selected' : '' ?>><?= e($c['icone'] . ' ' . $c['nome']) ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label small mb-0">Guia responsável <a class="small" href="guias.php">(gerenciar guias)</a></label><select class="form-select" name="guia_id"><option value="0">— Sem guia —</option><?php foreach ($guias as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $g['id'] == $ed['guia_id'] ? 'selected' : '' ?>><?= e($g['nome']) ?></option><?php endforeach; ?></select></div></div>
    <div><label class="form-label small mb-0">Descrição</label><textarea class="form-control" name="descricao" rows="3"><?= e($ed['descricao']) ?></textarea></div>
    <div><label class="form-label small mb-0">Atrações (separe por ponto e vírgula)</label><textarea class="form-control" name="atracoes" rows="2" placeholder="Coliseu;Vaticano;Fontana di Trevi"><?= e($ed['atracoes']) ?></textarea></div>
    <fieldset class="border rounded-3 p-3"><legend class="small fw-semibold float-none w-auto px-2 mb-0">🖼️ Imagem da capa</legend>
      <div class="row g-2"><div class="col-md-6"><label class="form-label small mb-0">Silhueta do ponto turístico</label><select class="form-select" name="motivo" id="f-motivo"><?php foreach ($NOMES as $k => $n): ?><option value="<?= $k ?>" <?= $ed['motivo'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label small mb-0">Emojis do lugar</label><input class="form-control" name="emoji" id="f-emoji" value="<?= e($ed['emoji']) ?>" maxlength="30" placeholder="🍕🏛️🍝"></div>
        <div class="col-12"><label class="form-label small mb-0">Cor do céu: <output id="f-hue-v"><?= (int)$ed['hue'] ?></output></label><input class="form-range" type="range" name="hue" id="f-hue" min="0" max="359" value="<?= (int)$ed['hue'] ?>"></div>
        <div class="col-md-6"><label class="form-label small mb-0">Enviar foto (JPG/PNG/WebP até 3 MB)</label><input class="form-control" type="file" name="imagem_arq" accept="image/jpeg,image/png,image/webp"></div>
        <div class="col-md-6"><label class="form-label small mb-0">…ou URL de uma imagem</label><input class="form-control" name="imagem_url" placeholder="https://..." value="<?= preg_match('~^https?://~', (string)$ed['imagem']) ? e($ed['imagem']) : '' ?>"></div>
        <?php if ($ed['imagem']): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="remover_imagem_arq" value="1" id="rm"><label class="form-check-label small" for="rm">Remover a foto atual (volta para a ilustração)</label></div></div><?php endif; ?></div>
      <div class="form-text">Use só fotos que você tem direito de usar. Sem foto, o site mostra a ilustração com a silhueta e os emojis.</div></fieldset>
    <fieldset class="border rounded-3 p-3"><legend class="small fw-semibold float-none w-auto px-2 mb-0">🎬 Vídeo de demonstração (YouTube)</legend>
      <input class="form-control" name="video" placeholder="Cole o link do vídeo (youtube.com/watch?v=… ou youtu.be/…)" value="<?= $ed['video_id'] ? e('https://youtu.be/' . $ed['video_id']) : '' ?>">
      <div class="form-text"><a target="_blank" rel="noopener" href="<?= e(videoBusca(trim($ed['pais'] . ' ' . $ed['destino']) . ' turismo o que fazer')) ?>">Procurar vídeos deste destino no YouTube ↗</a> — escolha um vídeo, copie o endereço e cole aqui. Ele aparecerá na página da viagem.</div></fieldset>
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="ativo" id="ativo" <?= $ed['ativo'] ? 'checked' : '' ?>><label class="form-check-label" for="ativo">Viagem ativa (visível no site)</label></div>
    <button class="btn btn-primary btn-lg">Salvar viagem</button></form></div>
  <div class="col-xl-5"><div class="card card-body mb-3"><h2 class="h6">Prévia do cartão</h2><div style="max-width:340px" id="previa"><?= capa($ed['emoji'], $ed['hue'], '', $ed['motivo'], $ed['imagem'], $ed['escopo'] === 'internacional' ? $ed['pais'] : 'Brasil') ?></div><div class="form-text">A silhueta, os emojis e a cor atualizam ao lado enquanto você edita.</div></div>
    <?php if ($ed['id']): ?><div class="card card-body"><h2 class="h6">Saídas (datas e vagas)</h2>
      <?php foreach ($saidas as $s): ?><form method="post" class="d-flex justify-content-between align-items-center border-bottom py-1"><?= csrfCampo() ?><input type="hidden" name="acao" value="del_saida"><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><input type="hidden" name="saida_id" value="<?= (int)$s['id'] ?>">
        <span><?= dataBr($s['data_saida']) ?> — <?= (int)$s['vagas_disponiveis'] ?>/<?= (int)$s['vagas_total'] ?> vagas</span><button class="btn btn-sm btn-link text-danger">excluir</button></form><?php endforeach; ?>
      <?php if (!$saidas): ?><p class="small text-body-secondary mb-2">Nenhuma saída cadastrada — a viagem aparece como "Sem datas".</p><?php endif; ?>
      <form method="post" class="row g-2 mt-2"><?= csrfCampo() ?><input type="hidden" name="acao" value="add_saida"><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>">
        <div class="col-6"><input class="form-control form-control-sm" type="date" name="data_saida" required></div><div class="col-3"><input class="form-control form-control-sm" type="number" name="vagas" min="1" value="12"></div><div class="col-3 d-grid"><button class="btn btn-sm btn-outline-primary">Adicionar</button></div></form></div><?php endif; ?></div>
</div>
<script>
// prévia ao vivo da cor e dos emojis (a silhueta e a foto aparecem ao salvar)
(function () { var h = document.getElementById('f-hue'), em = document.getElementById('f-emoji'), p = document.querySelector('#previa .capa');
  function up() { document.getElementById('f-hue-v').textContent = h.value; p.style.setProperty('--c1', 'hsl(' + h.value + ' 60% 70%)'); p.style.setProperty('--c2', 'hsl(' + ((+h.value + 40) % 360) + ' 80% 88%)'); var e = p.querySelector('.emojis'); if (!e) { e = document.createElement('span'); e.className = 'emojis'; p.appendChild(e); } e.textContent = ''; var txt = em.value.replace(/[\u{1F4CD}\u{1F4CC}]/gu, ''), it = window.Intl && Intl.Segmenter ? Array.from(new Intl.Segmenter().segment(txt), function (s) { return s.segment; }) : Array.from(txt); it.filter(function (g) { return g.trim(); }).slice(0, 3).forEach(function (g) { var b = document.createElement('b'); b.textContent = g; e.appendChild(b); }); }
  h.oninput = em.oninput = up; })();
</script>
<?php else: ?>
<form class="row g-2 mb-3" method="get"><div class="col-7 col-md-5"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Buscar título, destino ou país"></div>
  <div class="col-5 col-md-3"><select class="form-select form-select-sm" name="esc"><option value="">Todas</option><option value="nacional" <?= $fe === 'nacional' ? 'selected' : '' ?>>Nacionais</option><option value="internacional" <?= $fe === 'internacional' ? 'selected' : '' ?>>Internacionais</option></select></div><div class="col-12 col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filtrar</button></div></form>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Viagem</th><th>Guia</th><th>Preço</th><th>Saídas</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $p): ?><tr><td><span class="fs-5"><?= e(mb_substr($p['emoji'], 0, 2)) ?></span> <?= e($p['titulo']) ?><br><small class="text-body-secondary"><?= e($p['escopo'] === 'internacional' ? $p['pais'] : $p['destino']) ?> · <?= e($p['categoria']) ?> · <?= (int)$p['dias'] ?>d<?= $p['video_id'] ? ' · 🎬' : '' ?><?= $p['imagem'] ? ' · 🖼️' : '' ?></small></td>
  <td class="small"><?= e($p['guia'] ?: '—') ?></td><td><?= brl($p['preco']) ?></td><td><?= (int)$p['saidas'] ?></td><td><?= $p['ativo'] ? '<span class="badge text-bg-success">Ativa</span>' : '<span class="badge text-bg-secondary">Inativa</span>' ?></td>
  <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int)$p['id'] ?>">Editar</a>
    <form method="post" class="d-inline"><?= csrfCampo() ?><input type="hidden" name="acao" value="ativar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $p['ativo'] ? 'Desativar' : 'Ativar' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?= paginacaoHtml($pag, $pags) ?>
<?php endif; ?>
<?php admRodape();
