<?php

// Lee de $_ENV (archivo .env) o de las variables de entorno reales (Azure).
$env = static function (string $clave, $defecto = null) {
    $valor = $_ENV[$clave] ?? getenv($clave);
    return ($valor === false || $valor === null || $valor === '') ? $defecto : $valor;
};

return [
    'name'  => $env('APP_NAME', 'Sistema Ruta Escolar'),
    'env'   => $env('APP_ENV', 'production'),
    'debug' => filter_var($env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
];
