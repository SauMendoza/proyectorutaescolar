<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Database\Connection;
use App\Models\Curso;
use App\Models\Ruta;
use PDO;

// Módulo de cursos (solo administrador): lista de cursos y estudiantes de cada uno.
class CursoController
{
    //Lista de cursos a la izquierda y el curso elegido (?curso=ID) a la derecha
    public function listado()
    {
        Auth::requireRole('administrador');

        $conexion = $this->conectar();
        $modelo = new Curso($conexion);
        $cursos = $modelo->obtenerTodos();

        //Curso elegido: el de la URL o, si no hay, el primero de la lista
        $idElegido = filter_var($_GET['curso'] ?? '', FILTER_VALIDATE_INT);
        $cursoElegido = null;
        foreach ($cursos as $curso) {
            if ((int) $curso['id_curso'] === $idElegido) {
                $cursoElegido = $curso;
            }
        }
        $cursoElegido ??= $cursos[0] ?? null;

        $estudiantes = $cursoElegido ? $modelo->obtenerEstudiantes((int) $cursoElegido['id_curso']) : [];
        $paradasPorRuta = (new Ruta($conexion))->obtenerParadasPorRuta();

        //Rutas que tienen estudiantes en este curso (para las pestañas)
        $rutasCurso = array_unique(array_column($estudiantes, 'ruta', 'id_ruta'));
        ksort($rutasCurso);

        $formulario = $_SESSION['nuevo_curso'] ?? ['error' => null, 'nombre' => ''];
        $mensajeExito = $_SESSION['curso_guardado'] ?? null;
        unset($_SESSION['nuevo_curso'], $_SESSION['curso_guardado']);

        require __DIR__ . '/../Views/cursos/listado.php';
    }

    //Crea un curso nuevo y lo deja elegido
    public function guardar()
    {
        Auth::requireRole('administrador');

        $nombre = is_string($_POST['nombre'] ?? null) ? trim(preg_replace('/\s+/', ' ', $_POST['nombre'])) : '';
        $modelo = new Curso($this->conectar());

        $error = null;
        if ($nombre === '' || mb_strlen($nombre) > 50) {
            $error = 'Escribe el nombre del curso (máximo 50 caracteres).';
        } elseif ($modelo->existeNombre($nombre)) {
            $error = 'Ya existe un curso con ese nombre.';
        }

        if ($error) {
            $_SESSION['nuevo_curso'] = ['error' => $error, 'nombre' => $nombre];
            header('Location: /sistema_ruta_escolar/public/cursos');
            exit;
        }

        $idCurso = $modelo->crear($nombre);
        $_SESSION['curso_guardado'] = 'Curso "' . $nombre . '" creado.';
        header('Location: /sistema_ruta_escolar/public/cursos?curso=' . $idCurso);
        exit;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
