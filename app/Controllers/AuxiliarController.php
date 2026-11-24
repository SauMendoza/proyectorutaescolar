<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Database\Connection;
use App\Models\Auxiliar;
use DateTime;
use PDO;
use PDOException;

// Módulo "Auxiliares" (solo administrador): lista de auxiliares y creación de cuentas nuevas.
// Solo la administración crea cuentas; la contraseña se guarda cifrada y no se puede ver después.
class AuxiliarController
{
    //Lista de auxiliares a la izquierda y formulario "Nuevo auxiliar" a la derecha
    public function listado()
    {
        Auth::requireRole('administrador');

        $modelo = new Auxiliar($this->conectar());
        $auxiliares = $modelo->obtenerTodos();
        $rutas = $modelo->obtenerRutas();

        $formulario = $_SESSION['nuevo_auxiliar'] ?? ['errores' => [], 'datos' => []];
        $mensajeExito = $_SESSION['auxiliar_guardado'] ?? null;
        $errorContrasena = $_SESSION['contrasena_auxiliar'] ?? null;
        unset($_SESSION['nuevo_auxiliar'], $_SESSION['auxiliar_guardado'], $_SESSION['contrasena_auxiliar']);

        require __DIR__ . '/../Views/auxiliar/registro.php';
    }

    //Crea la cuenta del auxiliar (usuario + auxiliar)
    public function guardar()
    {
        Auth::requireRole('administrador');

        $texto = fn($campo) => is_string($_POST[$campo] ?? null) ? trim(preg_replace('/\s+/', ' ', $_POST[$campo])) : '';
        $datos = [
            'nombre' => $texto('nombre'),
            'apellido' => $texto('apellido'),
            'documento' => preg_replace('/\D/', '', $texto('documento')),
            'telefono' => $texto('telefono'),
            'fecha_ingreso' => $texto('fecha_ingreso'),
            'correo' => mb_strtolower($texto('correo')),
            'contrasena' => is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '',
            'cambiar_contrasena' => isset($_POST['cambiar_contrasena']),
            'id_ruta' => $texto('id_ruta'),
        ];

        $conexion = $this->conectar();
        $modelo = new Auxiliar($conexion);
        $idsRutas = array_map('intval', array_column($modelo->obtenerRutas(), 'id_ruta'));
        $errores = [];

        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 100) {
            $errores[] = 'Escribe el nombre (máximo 100 caracteres).';
        }
        if ($datos['apellido'] === '' || mb_strlen($datos['apellido']) > 100) {
            $errores[] = 'Escribe los apellidos (máximo 100 caracteres).';
        }
        if (!preg_match('/^\d{5,20}$/', $datos['documento'])) {
            $errores[] = 'El documento debe tener solo números (entre 5 y 20).';
        } elseif ($modelo->existeDocumento($datos['documento'])) {
            $errores[] = 'Ya hay un usuario con ese documento.';
        }
        if (!preg_match('/^[\d\s+]{7,20}$/', $datos['telefono'])) {
            $errores[] = 'Escribe un celular válido (solo números).';
        }
        $fecha = DateTime::createFromFormat('!Y-m-d', $datos['fecha_ingreso']);
        if ($datos['fecha_ingreso'] === '') {
            $datos['fecha_ingreso'] = date('Y-m-d');
        } elseif (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_ingreso']) {
            $errores[] = 'La fecha de ingreso no es válida.';
        }
        if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 150) {
            $errores[] = 'Escribe un correo válido.';
        } elseif ($modelo->existeCorreo($datos['correo'])) {
            $errores[] = 'Ese correo ya lo usa otro usuario.';
        }
        if (mb_strlen($datos['contrasena']) < 8 || mb_strlen($datos['contrasena']) > 72) {
            $errores[] = 'La contraseña temporal debe tener entre 8 y 72 caracteres.';
        }

        //"0" o vacío = Ninguna (sin ruta)
        $idRuta = filter_var($datos['id_ruta'], FILTER_VALIDATE_INT);
        if ($idRuta === false || $idRuta === 0) {
            $datos['id_ruta'] = null;
        } elseif (in_array($idRuta, $idsRutas, true)) {
            $datos['id_ruta'] = $idRuta;
        } else {
            $errores[] = 'La ruta elegida no existe.';
        }

        if (!$errores) {
            try {
                $modelo->registrar($datos);
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errores[] = $e->getCode() === '23000'
                    ? 'El correo o el documento ya están registrados.'
                    : 'No fue posible crear la cuenta. Intenta de nuevo.';
            }
        }

        if ($errores) {
            //Se devuelve lo escrito para no repetirlo, menos la contraseña
            unset($datos['contrasena']);
            $_SESSION['nuevo_auxiliar'] = ['errores' => $errores, 'datos' => $datos];
        } else {
            $_SESSION['auxiliar_guardado'] = 'Cuenta de ' . $datos['nombre'] . ' ' . $datos['apellido']
                . ' creada. Su usuario es ' . $datos['correo'] . '.';
        }

        header('Location: ' . \App\Core\Url::base() . '/auxiliares');
        exit;
    }

    //Pone una contraseña nueva a un auxiliar. La actual no se puede ver: está cifrada.
    public function cambiarContrasena()
    {
        Auth::requireRole('administrador');

        $id = (int) filter_var($_POST['id_usuario'] ?? '', FILTER_VALIDATE_INT);
        //Se quitan los espacios de los extremos, igual que al iniciar sesión
        $nueva = is_string($_POST['contrasena'] ?? null) ? trim($_POST['contrasena']) : '';
        $repetir = is_string($_POST['repetir'] ?? null) ? trim($_POST['repetir']) : '';

        $error = null;
        if (mb_strlen($nueva) < 8 || strlen($nueva) > 72) {
            $error = 'La contraseña debe tener entre 8 y 72 caracteres.';
        } elseif ($nueva !== $repetir) {
            $error = 'Las dos contraseñas no coinciden.';
        } elseif (!(new Auxiliar($this->conectar()))->cambiarContrasena($id, $nueva, isset($_POST['cambiar_contrasena']))) {
            $error = 'No se encontró el auxiliar.';
        }

        if ($error) {
            $_SESSION['contrasena_auxiliar'] = ['id' => $id, 'error' => $error];
        } else {
            $_SESSION['auxiliar_guardado'] = 'Contraseña cambiada.';
        }
        header('Location: ' . \App\Core\Url::base() . '/auxiliares');
        exit;
    }

    //Responde en JSON si un correo está libre (para el aviso "Disponible" del formulario)
    public function correo()
    {
        Auth::requireRole('administrador');
        header('Content-Type: application/json; charset=utf-8');

        $correo = mb_strtolower(trim((string) ($_GET['correo'] ?? '')));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['valido' => false, 'disponible' => false]);
            return;
        }

        $disponible = !(new Auxiliar($this->conectar()))->existeCorreo($correo);
        echo json_encode(['valido' => true, 'disponible' => $disponible]);
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
