<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// safeLoad: en local lee .env; en Azure (sin .env) usa las variables de entorno del App Service.
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Cookie de sesión más segura.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
