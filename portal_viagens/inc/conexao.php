<?php
// Conexão PDO (mesmo padrão do Bibliotecão). Ajuste usuário/senha ao seu ambiente.
$host = 'localhost'; $db = 'portal_viagens'; $user = 'root'; $pass = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $conn->exec("SET time_zone = '-03:00'");   // mesmo fuso do PHP (Brasília)
} catch (PDOException $e) {
    exit('Falha na conexão com o banco: ' . htmlspecialchars($e->getMessage()));
}
