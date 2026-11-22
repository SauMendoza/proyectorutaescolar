<?php
// namespace para que composer y el autoload identifiquen la clase
namespace App\Database;

use PDO;

class Connection
{
    // Recibe el array de config/database.php y devuelve una instancia PDO.
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $opciones = [
            // Los errores de PDO se comunican mediante excepciones.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Las filas se devuelven como arreglos asociativos (clave => valor).
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Consultas preparadas reales en MySQL.
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // La base de datos usa la hora de Colombia (UTC-5) para CURDATE(), NOW() y las vistas de "hoy".
        // Azure trabaja en UTC: sin esto, después de las 7 p. m. la base ya estaría en "mañana".
        $atributoInicio = class_exists(\Pdo\Mysql::class)
            ? \Pdo\Mysql::ATTR_INIT_COMMAND
            : \PDO::MYSQL_ATTR_INIT_COMMAND;
        $opciones[$atributoInicio] = "SET time_zone = '-05:00'";

        // Conexión cifrada (necesaria en Azure Database for MySQL).
        if (!empty($config['ssl'])) {
            $atributoSsl = class_exists(\Pdo\Mysql::class)
            ? \Pdo\Mysql::ATTR_SSL_CA
            : \PDO::MYSQL_ATTR_SSL_CA;
            $opciones[$atributoSsl] = $config['ssl_ca'];
        }

        return new PDO($dsn, $config['user'], $config['password'], $opciones);
    }
}
