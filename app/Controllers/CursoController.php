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
        $edicion = $_SESSION['editar_curso'] ?? null;
        $errorCurso = $_SESSION['curso_error'] ?? null;
        unset($_SESSION['nuevo_curso'], $_SESSION['curso_guardado'], $_SESSION['editar_curso'], $_SESSION['curso_error']);

        require __DIR__ . '/../Views/cursos/listado.php';
    }

    //Crea un curso nuevo y lo deja elegido
    public function guardar()
    {
        Auth::requireRole('administrador');

        $nombre = $this->nombreRecibido();
        $modelo = new Curso($this->conectar());
        $error = $this->validarNombre($modelo, $nombre);

        if ($error) {
            $_SESSION['nuevo_curso'] = ['error' => $error, 'nombre' => $nombre];
            header('Location: ' . \App\Core\Url::base() . '/cursos');
            exit;
        }

        $idCurso = $modelo->crear($nombre);
        $_SESSION['curso_guardado'] = 'Curso "' . $nombre . '" creado.';
        header('Location: ' . \App\Core\Url::base() . '/cursos?curso=' . $idCurso);
        exit;
    }

    //Cambia el nombre de un curso
    public function actualizar()
    {
        Auth::requireRole('administrador');

        $modelo = new Curso($this->conectar());
        $curso = $this->cursoRecibido($modelo);
        $idCurso = (int) $curso['id_curso'];
        $nombre = $this->nombreRecibido();
        $error = $this->validarNombre($modelo, $nombre, $idCurso);

        if ($error) {
            $_SESSION['editar_curso'] = ['error' => $error, 'nombre' => $nombre];
        } else {
            $modelo->cambiarNombre($idCurso, $nombre);
            $_SESSION['curso_guardado'] = 'Cambios guardados: "' . $nombre . '".';
        }

        header('Location: ' . \App\Core\Url::base() . '/cursos?curso=' . $idCurso);
        exit;
    }

    //Elimina un curso que no tenga estudiantes
    public function eliminar()
    {
        Auth::requireRole('administrador');

        $modelo = new Curso($this->conectar());
        $curso = $this->cursoRecibido($modelo);
        $idCurso = (int) $curso['id_curso'];

        if ((int) $curso['estudiantes'] > 0 || !$modelo->eliminar($idCurso)) {
            $_SESSION['curso_error'] = 'No se borró "' . $curso['nombre'] . '": tiene estudiantes. Pásalos primero a otro curso.';
            header('Location: ' . \App\Core\Url::base() . '/cursos?curso=' . $idCurso);
            exit;
        }

        $_SESSION['curso_guardado'] = 'Se borró el curso "' . $curso['nombre'] . '".';
        header('Location: ' . \App\Core\Url::base() . '/cursos');
        exit;
    }

    //Curso enviado en el formulario; si no existe, vuelve a la lista con un aviso
    private function cursoRecibido(Curso $modelo): array
    {
        $idCurso = filter_var($_POST['id_curso'] ?? '', FILTER_VALIDATE_INT);
        $curso = $idCurso ? $modelo->buscar($idCurso) : null;

        if (!$curso) {
            $_SESSION['curso_error'] = 'No se encontró el curso.';
            header('Location: ' . \App\Core\Url::base() . '/cursos');
            exit;
        }
        return $curso;
    }

    private function nombreRecibido(): string
    {
        return is_string($_POST['nombre'] ?? null) ? trim(preg_replace('/\s+/', ' ', $_POST['nombre'])) : '';
    }

    //Mensaje de error del nombre, o null si sirve
    private function validarNombre(Curso $modelo, string $nombre, int $exceptoId = 0): ?string
    {
        if ($nombre === '' || mb_strlen($nombre) > 50) {
            return 'Escribe el nombre del curso (máximo 50 caracteres).';
        }
        return $modelo->existeNombre($nombre, $exceptoId) ? 'Ya existe un curso con ese nombre.' : null;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
