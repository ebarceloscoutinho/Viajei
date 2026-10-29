<?php
require_once __DIR__ . '/inc/app.php';
exigirLogin();
$lista = buscarPasseios($conn, 'AND p.id IN (SELECT passeio_id FROM favoritos WHERE usuario_id=?)', [usuario()['id']], 'p.id DESC');
topo('Favoritos');
?>
<h1 class="h3 mb-4">♥ Meus favoritos</h1>
<?php if (!$lista): ?><div class="alert alert-info">Você ainda não favoritou nada. Toque no ♡ dentro de uma viagem para guardá-la aqui.</div>
<?php else: ?><div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"><?php foreach ($lista as $p) echo cardPasseio($p); ?></div><?php endif;
rodape();
