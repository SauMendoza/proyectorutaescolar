<?php
// Crea (o asigna rol de administrador a) un usuario desde la línea de comandos.
// Uso:  php database/crear_admin.php correo@dominio.com Nombre Apellido
// La contraseña se pide por teclado (no queda en el historial) o puede venir en ADMIN_PASSWORD.

if (PHP_SAPI !== 'cli') {
    exit("Este script solo se ejecuta desde la línea de comandos.\n");
}

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();

use App\Database\Connection;

[$script, $correo, $nombre, $apellido] = array_pad($argv, 4, null);

if (!$correo || !$nombre || !$apellido || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("Uso: php database/crear_admin.php correo@dominio.com Nombre Apellido\n");
}

$contrasena = getenv('ADMIN_PASSWORD') ?: '';
if ($contrasena === '') {
    fwrite(STDOUT, 'Contraseña (mínimo 10 caracteres): ');
    system('stty -echo 2>/dev/null');
    $contrasena = rtrim((string) fgets(STDIN), "\r\n");
    system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}

if (strlen($contrasena) < 10) {
    exit("La contraseña debe tener al menos 10 caracteres.\n");
}

$config = require __DIR__ . '/../config/database.php';
$pdo = Connection::connect($config);

try {
    $pdo->beginTransaction();

    $insUsuario = $pdo->prepare(
        'INSERT INTO usuario (nombre, apellido, correo, contrasena) VALUES (:n, :a, :c, :h)'
    );
    $insUsuario->execute([
        ':n' => $nombre,
        ':a' => $apellido,
        ':c' => $correo,
        ':h' => password_hash($contrasena, PASSWORD_DEFAULT),
    ]);

    $idUsuario = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO administrador (fk_id_usuario) VALUES (:id)')
        ->execute([':id' => $idUsuario]);

    $pdo->commit();
    echo "Administrador creado: $correo (id $idUsuario)\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('Error: ' . $e->getMessage() . "\n");
}
