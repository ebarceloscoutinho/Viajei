<?php
/** Manifest do app instalável (PWA): parte de manifest.json, mas o nome e a cor vêm de Admin → Configurações. */
require_once __DIR__ . '/inc/app.php';
$m = json_decode((string)@file_get_contents(__DIR__ . '/manifest.json'), true) ?: [];
$cores = ['verde' => '#0f766e', 'azul' => '#1d4ed8', 'roxo' => '#7c3aed', 'rosa' => '#be185d', 'laranja' => '#c2410c', 'grafite' => '#334155'];
$m['name'] = SITE_NOME . ' — Viagens e Roteiros';
$m['short_name'] = mb_substr(SITE_NOME, 0, 12);
$m['theme_color'] = $cores[ajuste('sys_tema_cor')] ?? '#0f766e';
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
