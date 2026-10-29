<?php
require_once __DIR__ . '/inc/app.php';
$_SESSION = []; session_destroy();
redirecionar('index.php');
