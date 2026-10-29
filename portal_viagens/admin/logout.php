<?php
define('BASE', '../'); define('AREA', 'admin');
require_once __DIR__ . '/../inc/admin.php';
unset($_SESSION['adm'], $_SESSION['tema_adm']);
redirecionar('login.php');
