<?php
define('BASE', '../'); define('AREA', 'guia');
require_once __DIR__ . '/../inc/guia.php';
unset($_SESSION['gui'], $_SESSION['tema_gui']);
redirecionar('login.php');
