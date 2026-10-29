<?php
require_once __DIR__ . '/inc/app.php';
http_response_code(404); topo('Página não encontrada');
?>
<div class="text-center py-5"><div class="display-1">🧭</div><h1 class="h3 mt-3">Opa, esse caminho não existe</h1><p class="text-body-secondary">A página que você procurou não foi encontrada. Que tal explorar outro destino?</p>
  <a class="btn btn-primary" href="passeios.php">Ver viagens</a> <a class="btn btn-outline-primary" href="index.php">Ir para o início</a></div>
<?php rodape();
