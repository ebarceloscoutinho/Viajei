<?php
// Salva a preferência de tema na conta de quem está logado (site ou admin).
require_once __DIR__ . '/../inc/app.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"ok":false}'); }
csrfVerificar();
$validos = ['modo' => ['auto', 'light', 'dark'], 'cor' => ['verde', 'azul', 'roxo', 'rosa', 'laranja', 'grafite'], 'fonte' => ['normal', 'grande']];
$chave = $_POST['chave'] ?? ''; $valor = $_POST['valor'] ?? '';
$area = $_POST['area'] ?? 'site'; $k = ['admin' => 'adm', 'guia' => 'gui'][$area] ?? 'user';
$id = $_SESSION[$k]['id'] ?? null;
if (!$id || !isset($validos[$chave]) || !in_array($valor, $validos[$chave], true)) { http_response_code(400); exit('{"ok":false}'); }
$col = 'tema_' . $chave;   // nome vindo da lista fixa acima
$conn->prepare("UPDATE usuarios SET $col=? WHERE id=?")->execute([$valor, $id]);
$_SESSION['tema_' . ($k === 'user' ? 'site' : $k)][$chave] = $valor;
echo '{"ok":true}';
