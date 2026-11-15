<?php

// Lee de $_ENV (archivo .env) o de las variables de entorno reales (Azure).
$env = static function (string $clave, $defecto = null) {
    $valor = $_ENV[$clave] ?? getenv($clave);
    return ($valor === false || $valor === null || $valor === '') ? $defecto : $valor;
};

return [
    'host'     => $env('DB_HOST', 'ruta-server.mysql.database.azure.com'),
    'port'     => $env('DB_PORT', '3306'),
    'name'     => $env('DB_NAME', 'ruta_escolar_v3'),
    'user'     => $env('DB_USER', 'tomy'),
    'password' => $env('DB_PASSWORD', 'Badbunny2026'),
    'charset'  => 'utf8mb4',
    // Azure Database for MySQL exige SSL: poner DB_SSL=true en las variables de entorno.
    'ssl'      => filter_var($env('DB_SSL', false), FILTER_VALIDATE_BOOLEAN),
    'ssl_ca'   => $env('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt'),
];
