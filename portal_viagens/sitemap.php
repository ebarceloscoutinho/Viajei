<?php
require_once __DIR__ . '/inc/app.php';
$u = siteUrl(); header('Content-Type: application/xml; charset=UTF-8');
$urls = ['index.php', 'passeios.php', 'passeios.php?escopo=nacional', 'passeios.php?escopo=internacional', 'roteiros.php', 'equipe.php', 'guias.php', 'quiz_custo.php', 'suporte.php', 'privacidade.php', 'termos.php'];
foreach ($conn->query('SELECT id FROM passeios WHERE ativo=1')->fetchAll(PDO::FETCH_COLUMN) as $i) $urls[] = 'passeio.php?id=' . $i;
foreach ($conn->query('SELECT id FROM guias WHERE ativo=1')->fetchAll(PDO::FETCH_COLUMN) as $i) $urls[] = 'equipe.php?id=' . $i;
foreach ($conn->query('SELECT id FROM guias_viagem')->fetchAll(PDO::FETCH_COLUMN) as $i) $urls[] = 'guia.php?id=' . $i;
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $x) echo '  <url><loc>' . htmlspecialchars($u . '/' . $x, ENT_XML1) . "</loc></url>\n";
echo '</urlset>';
