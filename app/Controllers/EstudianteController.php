<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Database\Connection;
use App\Models\Estudiante;
use Throwable;

class EstudianteController
{
    public function presentacion()
    {
        // Datos de menores: solo usuarios autenticados.
        Auth::requireLogin();

        try {
            // Obtener la configuración de la base de datos
            $config = require __DIR__ . '/../../config/database.php';

            // Crear la conexión PDO
            $conexion = Connection::connect($config);

            // Crear el modelo y consultar los estudiantes
            $modeloEstudiante = new Estudiante($conexion);
            $estudiantes = $modeloEstudiante->obtenerTodos();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo 'No fue posible cargar el listado de estudiantes.';
            return;
        }

        // Cargar la vista y pasarle los estudiantes
        require __DIR__ . '/../Views/estudiantes/presentacion.php';
    }
}
