<?php
require_once __DIR__ . '/inc/app.php';
topo('Início');
$cats = $conn->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();
$futuro = ' AND EXISTS (SELECT 1 FROM saidas s WHERE s.passeio_id=p.id AND s.data_saida>=CURDATE())';
$nac = buscarPasseios($conn, "AND p.escopo='nacional' AND p.dias>1" . $futuro, [], 'RAND()', 3);
$intl = buscarPasseios($conn, "AND p.escopo='internacional'" . $futuro, [], 'RAND()', 3);
$alta = ['Itália', 'França', 'Japão', 'Portugal', 'Estados Unidos', 'Grécia', 'Egito', 'Peru', 'Austrália'];
$in = implode(',', array_fill(0, count($alta), '?'));
$st = $conn->prepare("SELECT pais, motivo, emoji, hue, imagem FROM passeios WHERE escopo='internacional' AND ativo=1 AND pais IN ($in)"); $st->execute($alta);
$destAlta = []; foreach ($st->fetchAll() as $r) $destAlta[$r['pais']] = $r;
$guias = $conn->query("SELECT g.*, (SELECT GROUP_CONCAT(l.local ORDER BY l.id SEPARATOR ', ') FROM guia_locais l WHERE l.guia_id=g.id) AS locais FROM guias g WHERE g.ativo=1 ORDER BY RAND() LIMIT 6")->fetchAll();
$roteiros = $conn->query('SELECT r.*, (SELECT COUNT(*) FROM roteiro_itens i WHERE i.roteiro_id=r.id) AS itens FROM roteiros r WHERE r.usuario_id IS NULL AND r.publico=1 LIMIT 3')->fetchAll();
$dicas = $conn->query('SELECT * FROM guias_viagem ORDER BY created_at DESC LIMIT 3')->fetchAll();
?>
<section class="hero text-center mb-5">
  <span class="eyebrow">Viagens · Roteiros · Guias locais</span>
  <h1 class="display-5">Para onde a sua próxima história vai?</h1>
  <p class="lead mb-4">Monte o roteiro, reserve passeios com guias de confiança e saiba quanto a viagem vai custar.</p>
  <form class="row g-2 justify-content-center" action="passeios.php" method="get">
    <div class="col-12 col-md-6"><input class="form-control form-control-lg" name="q" placeholder="Paris, Lençóis Maranhenses, Japão..." aria-label="Buscar destino"></div>
    <div class="col-12 col-md-auto"><button class="btn btn-accent btn-lg w-100">Buscar viagens</button></div>
  </form>
  <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center"><a class="btn btn-light btn-sm" href="passeios.php?escopo=nacional">🇧🇷 Nacionais</a><a class="btn btn-light btn-sm" href="passeios.php?escopo=internacional">🌍 Internacionais</a><a class="btn btn-light btn-sm" href="quiz_custo.php">💰 Quanto custa?</a></div>
  <?= skylineHero() ?>
</section>

<div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
  <?php foreach ($cats as $c): ?><a class="chip" href="passeios.php?categoria=<?= (int)$c['id'] ?>"><?= e($c['icone']) ?> <?= e($c['nome']) ?></a><?php endforeach; ?>
</div>

<h2 class="h4 mb-2">Destinos em alta</h2>
<div class="faixa mb-4"><?php foreach ($alta as $n): if (empty($destAlta[$n])) continue; $d = $destAlta[$n]; ?>
  <a href="passeios.php?escopo=internacional&pais=<?= urlencode($n) ?>"><?= capa($d['emoji'], $d['hue'], 'mini', $d['motivo'], $d['imagem']) ?><div class="legenda"><?= e($n) ?></div></a><?php endforeach; ?></div>

<div class="d-flex justify-content-between align-items-end mb-3"><h2 class="h4 mb-0">🇧🇷 Destaques no Brasil</h2><a href="passeios.php?escopo=nacional">Ver todos →</a></div>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5"><?php foreach ($nac as $p) echo cardPasseio($p); ?></div>
<div class="d-flex justify-content-between align-items-end mb-3"><h2 class="h4 mb-0">🌍 Pelo mundo</h2><a href="passeios.php?escopo=internacional">Ver todos →</a></div>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5"><?php foreach ($intl as $p) echo cardPasseio($p); ?></div>

<div class="d-flex justify-content-between align-items-end mb-2"><h2 class="h4 mb-0">Conheça nossos guias</h2><a href="equipe.php">Ver equipe →</a></div>
<div class="faixa faixa-guias mb-5"><?php foreach ($guias as $g): ?>
  <a class="card card-body text-center" href="equipe.php?id=<?= (int)$g['id'] ?>"><div class="mb-2"><?= avatar($g['nome'], $g['foto'], 72) ?></div><strong><?= e($g['nome']) ?></strong><div class="small text-body-secondary text-truncate"><?= e($g['locais']) ?></div></a><?php endforeach; ?></div>

<div class="row g-4 mb-5 align-items-center">
  <div class="col-md-6"><h2 class="h4">Montador de roteiros</h2>
    <p>Crie um itinerário por dia, encaixe passeios do catálogo, adicione notas (hotel, restaurantes, deslocamentos) e veja o custo estimado. Prefere começar de um modelo? Copie um roteiro pronto e adapte.</p>
    <a class="btn btn-primary" href="roteiros.php">Montar meu roteiro</a></div>
  <div class="col-md-6"><div class="list-group">
    <?php foreach ($roteiros as $r): ?><a class="list-group-item list-group-item-action" href="roteiro.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['titulo']) ?></strong><br><small class="text-body-secondary"><?= e($r['destino']) ?> · <?= (int)$r['dias'] ?> dias · <?= (int)$r['itens'] ?> atividades</small></a><?php endforeach; ?>
  </div></div>
</div>

<div class="d-flex justify-content-between align-items-end mb-3"><h2 class="h4 mb-0">Dicas de destino</h2><a href="guias.php">Ver todas →</a></div>
<div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
  <?php foreach ($dicas as $g): ?><div class="col"><a class="card h-100 card-hover text-decoration-none" href="guia.php?id=<?= (int)$g['id'] ?>"><?= capa($g['emoji'], $g['hue']) ?>
    <div class="card-body"><div class="small text-body-secondary"><?= e($g['destino']) ?></div><h3 class="h6 text-body"><?= e($g['titulo']) ?></h3><p class="small text-body-secondary mb-0"><?= e($g['resumo']) ?></p></div></a></div><?php endforeach; ?>
</div>

<div class="card card-body flex-md-row align-items-md-center justify-content-between gap-3 text-center text-md-start">
  <div><h2 class="h5 mb-1">🛟 Precisa de ajuda?</h2><div class="text-body-secondary">Fale com a gente: <?= e(config('suporte_telefone')) ?> · <?= e(config('suporte_email')) ?></div></div>
  <a class="btn btn-primary" href="suporte.php">Ir para o suporte</a></div>
<?php rodape();
