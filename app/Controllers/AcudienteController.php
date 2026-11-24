<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Database\Connection;
use App\Models\Acudiente;
use App\Models\Estudiante;
use PDO;

// Módulo de acudientes (solo administrador): listado, ficha, nuevo acudiente y parentescos.
class AcudienteController
{
    //Listado de acudientes con los estudiantes que tienen a cargo
    public function listado()
    {
        Auth::requireRole('administrador');

        $conexion = $this->conectar();
        $modelo = new Acudiente($conexion);
        $acudientes = $modelo->obtenerTodos();

        //Se agrupan los estudiantes a cargo por documento del acudiente
        $aCargo = [];
        foreach ($modelo->obtenerEstudiantesACargo() as $relacion) {
            $aCargo[$relacion['fk_num_doc_acu']][] = $relacion;
        }

        $sinSuplente = (new Estudiante($conexion))->obtenerEstadisticas()['sin_suplente'];

        //Errores y datos del formulario "Nuevo acudiente" cuando algo salió mal
        $formulario = $_SESSION['nuevo_acudiente'] ?? ['errores' => [], 'datos' => []];
        unset($_SESSION['nuevo_acudiente']);

        require __DIR__ . '/../Views/acudientes/listado.php';
    }

    //Ficha de un acudiente: datos, estudiantes a cargo y parentescos
    public function ficha()
    {
        Auth::requireRole('administrador');

        $documento = trim($_GET['documento'] ?? '');
        $conexion = $this->conectar();
        $modelo = new Acudiente($conexion);
        $acudiente = $documento === '' ? null : $modelo->buscarPorDocumento($documento);

        if ($acudiente === null) {
            http_response_code(404);
            echo '404 - No se encontró el acudiente.';
            exit;
        }

        $estudiantes = $modelo->obtenerEstudiantesACargo($documento);
        $parentescos = $modelo->obtenerParentescos();
        $parentescosUsados = array_unique(array_column($estudiantes, 'parentesco'));

        $mensajeExito = $_SESSION['acudiente_guardado'] ?? null;
        $errorParentesco = $_SESSION['error_parentesco'] ?? null;
        $edicion = $_SESSION['editar_acudiente'] ?? null;
        unset($_SESSION['acudiente_guardado'], $_SESSION['error_parentesco'], $_SESSION['editar_acudiente']);
        if ($edicion && $edicion['datos']['documento'] !== $acudiente['num_doc_acu']) {
            $edicion = null;
        }

        require __DIR__ . '/../Views/acudientes/ficha.php';
    }

    //Guarda un acudiente nuevo (formulario de la ventana "Nuevo acudiente")
    public function guardar()
    {
        Auth::requireRole('administrador');

        $datos = [];
        foreach (['documento', 'nombre', 'apellido', 'telefono'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }

        $modelo = new Acudiente($this->conectar());
        $errores = [];

        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $datos['documento'])) {
            $errores['documento'] = 'El documento debe tener entre 4 y 20 letras o números.';
        } elseif ($modelo->buscarPorDocumento($datos['documento'])) {
            $errores['documento'] = 'Ya hay un acudiente con ese documento.';
        }
        $errores += $this->validarDatos($datos);

        if ($errores) {
            $_SESSION['nuevo_acudiente'] = ['errores' => $errores, 'datos' => $datos];
            header('Location: ' . \App\Core\Url::base() . '/acudientes');
            exit;
        }

        $modelo->crear($datos['documento'], $datos['nombre'], $datos['apellido'], $datos['telefono'] ?: null);

        $_SESSION['acudiente_guardado'] = 'Acudiente registrado. Para asignarlo a un estudiante usa "Nuevo estudiante".';
        header('Location: ' . \App\Core\Url::base() . '/acudientes/ficha?documento=' . urlencode($datos['documento']));
        exit;
    }

    //Guarda los cambios de un acudiente (ventana "Editar" de la ficha). Sus estudiantes no cambian.
    public function actualizar()
    {
        Auth::requireRole('administrador');

        $datos = [];
        foreach (['documento', 'nombre', 'apellido', 'telefono'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }

        $modelo = new Acudiente($this->conectar());
        if ($datos['documento'] === '' || !$modelo->buscarPorDocumento($datos['documento'])) {
            http_response_code(404);
            echo '404 - No se encontró el acudiente.';
            exit;
        }

        $errores = $this->validarDatos($datos);
        if ($errores) {
            $_SESSION['editar_acudiente'] = ['errores' => $errores, 'datos' => $datos];
        } else {
            $modelo->actualizar($datos['documento'], $datos['nombre'], $datos['apellido'], $datos['telefono'] ?: null);
            $_SESSION['acudiente_guardado'] = 'Cambios guardados.';
        }

        header('Location: ' . \App\Core\Url::base() . '/acudientes/ficha?documento=' . urlencode($datos['documento']));
        exit;
    }

    //Revisa nombre, apellido y celular (al crear y al editar)
    private function validarDatos(array $datos): array
    {
        $errores = [];
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 100) {
            $errores['nombre'] = 'Escribe el nombre (máximo 100 caracteres).';
        }
        if ($datos['apellido'] === '' || mb_strlen($datos['apellido']) > 100) {
            $errores['apellido'] = 'Escribe el apellido (máximo 100 caracteres).';
        }
        if ($datos['telefono'] !== '' && !preg_match('/^[0-9 +()-]{7,20}$/', $datos['telefono'])) {
            $errores['telefono'] = 'Revisa el celular: solo números, entre 7 y 20.';
        }

        return $errores;
    }

    //Agrega un parentesco al catálogo y vuelve a la ficha del acudiente
    public function guardarParentesco()
    {
        Auth::requireRole('administrador');

        $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
        $documento = is_string($_POST['documento'] ?? null) ? trim($_POST['documento']) : '';
        $modelo = new Acudiente($this->conectar());

        if (!preg_match('/^[\p{L} ()]{2,30}$/u', $nombre)) {
            $_SESSION['error_parentesco'] = 'El parentesco debe tener entre 2 y 30 letras.';
        } elseif ($modelo->existeParentesco($nombre)) {
            $_SESSION['error_parentesco'] = 'Ese parentesco ya existe.';
        } else {
            $modelo->crearParentesco(mb_strtoupper(mb_substr($nombre, 0, 1)) . mb_substr($nombre, 1));
            $_SESSION['acudiente_guardado'] = 'Parentesco "' . $nombre . '" agregado.';
        }

        header('Location: ' . \App\Core\Url::base() . '/acudientes/ficha?documento=' . urlencode($documento));
        exit;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
