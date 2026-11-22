<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Database\Connection;
use App\Models\Ruta;
use PDO;
use Throwable;

// Módulo de rutas. El administrador ve todas y las edita (nombre, conductor, auxiliar y paradas).
// El auxiliar solo ve su ruta ("Mi ruta") y no puede editarla.
class RutaController
{
    public function listado()
    {
        Auth::requireLogin();

        $usuario = $_SESSION['usuario'];
        $esAdmin = $usuario['rol'] === 'administrador';

        $conexion = $this->conectar();
        $modelo = new Ruta($conexion);

        $rutas = $esAdmin ? $modelo->obtenerTodas() : $modelo->obtenerTodas((int) ($usuario['id_ruta'] ?? 0));
        $paradasPorRuta = $modelo->obtenerParadasPorRuta();
        $conductores = $esAdmin ? $modelo->obtenerConductores() : [];
        $auxiliares = $esAdmin ? $modelo->obtenerAuxiliares() : [];

        //Si guardar() encontró errores, se vuelve a abrir la ventana con lo que se escribió
        $formulario = $_SESSION['formulario_ruta'] ?? null;
        $mensajeExito = $_SESSION['ruta_guardada'] ?? null;
        unset($_SESSION['formulario_ruta'], $_SESSION['ruta_guardada']);

        require __DIR__ . '/../Views/rutas/listado.php';
    }

    //Crea o edita una ruta con sus paradas, todo en una sola transacción
    public function guardar()
    {
        Auth::requireRole('administrador');

        $conexion = $this->conectar();
        $modelo = new Ruta($conexion);

        $texto = fn($valor) => is_string($valor) ? trim($valor) : '';
        $datos = [
            'id_ruta' => $texto($_POST['id_ruta'] ?? ''),
            'nombre' => $texto($_POST['nombre'] ?? ''),
            'conductor' => $texto($_POST['conductor'] ?? ''),
            'conductor_documento' => $texto($_POST['conductor_documento'] ?? ''),
            'conductor_nombres' => $texto($_POST['conductor_nombres'] ?? ''),
            'conductor_apellidos' => $texto($_POST['conductor_apellidos'] ?? ''),
            'conductor_telefono' => $texto($_POST['conductor_telefono'] ?? ''),
            'auxiliar' => $texto($_POST['auxiliar'] ?? ''),
            'paradas' => [],
        ];
        foreach (is_array($_POST['paradas'] ?? null) ? $_POST['paradas'] : [] as $parada) {
            if (!is_array($parada)) {
                continue;
            }
            $datos['paradas'][] = [
                'id_parada' => $texto($parada['id'] ?? ''),
                'nombre' => $texto($parada['nombre'] ?? ''),
                'comunidad' => $texto($parada['comunidad'] ?? ''),
                'km_referencia' => str_replace(',', '.', $texto($parada['km'] ?? '')),
                'quitar' => isset($parada['quitar']),
            ];
        }

        $errores = [];

        // ----- Ruta -----
        $idRuta = null;
        $rutaActual = null;
        if ($datos['id_ruta'] !== '') {
            $idRuta = filter_var($datos['id_ruta'], FILTER_VALIDATE_INT);
            $rutaActual = $idRuta ? $modelo->obtenerPorId($idRuta) : null;
            if (!$rutaActual) {
                $_SESSION['ruta_guardada'] = 'La ruta que intentaste editar ya no existe.';
                $this->volver();
            }
        }

        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 50) {
            $errores[] = 'Escribe el nombre de la ruta (máximo 50 caracteres).';
        } elseif ($modelo->existeNombre($datos['nombre'], $idRuta)) {
            $errores[] = 'Ya hay otra ruta con ese nombre.';
        }

        // ----- Conductor: uno libre, el actual de la ruta, o uno nuevo -----
        $conductorNuevo = $datos['conductor'] === 'nuevo';
        if ($conductorNuevo) {
            if (!preg_match('/^[0-9]{4,9}$/', $datos['conductor_documento'])) {
                $errores[] = 'El documento del conductor debe tener entre 4 y 9 números.';
            } elseif ($modelo->existeConductor($datos['conductor_documento'])) {
                $errores[] = 'Ya hay un conductor con ese documento; elígelo en la lista.';
            }
            if ($datos['conductor_nombres'] === '' || $datos['conductor_apellidos'] === ''
                || mb_strlen($datos['conductor_nombres']) > 100 || mb_strlen($datos['conductor_apellidos']) > 100) {
                $errores[] = 'Escribe los nombres y apellidos del conductor.';
            }
            if ($datos['conductor_telefono'] !== '' && !preg_match('/^[0-9 +()-]{7,20}$/', $datos['conductor_telefono'])) {
                $errores[] = 'Revisa el celular del conductor.';
            }
        } else {
            $libre = false;
            foreach ($modelo->obtenerConductores() as $conductor) {
                if ((string) $conductor['num_doc_conductor'] === $datos['conductor']) {
                    $libre = $conductor['id_ruta'] === null || (int) $conductor['id_ruta'] === $idRuta;
                }
            }
            if (!$libre) {
                $errores[] = 'Elige un conductor que no maneje otra ruta.';
            }
        }

        // ----- Auxiliar (opcional) -----
        $idAuxiliar = null;
        if ($datos['auxiliar'] !== '') {
            $idAuxiliar = filter_var($datos['auxiliar'], FILTER_VALIDATE_INT);
            $ids = array_map('intval', array_column($modelo->obtenerAuxiliares(), 'id_usuario'));
            if (!$idAuxiliar || !in_array($idAuxiliar, $ids, true)) {
                $errores[] = 'Elige un auxiliar de la lista.';
                $idAuxiliar = null;
            }
        }

        // ----- Paradas -----
        $paradasActuales = [];
        foreach ($idRuta ? $modelo->obtenerParadas($idRuta) : [] as $parada) {
            $paradasActuales[(int) $parada['id_parada']] = $parada;
        }

        $quedan = 0;
        foreach ($datos['paradas'] as $parada) {
            //Se numera igual que en la ventana: solo cuentan las paradas que no se quitan
            $etiqueta = 'Parada ' . ($quedan + 1);
            $idParada = $parada['id_parada'] === '' ? null : (int) $parada['id_parada'];

            if ($idParada !== null && !isset($paradasActuales[$idParada])) {
                $errores[] = $etiqueta . ': no pertenece a esta ruta.';
                continue;
            }
            if ($parada['quitar']) {
                if ($idParada !== null && (int) $paradasActuales[$idParada]['estudiantes'] > 0) {
                    $errores[] = $etiqueta . ': no se puede quitar porque tiene estudiantes.';
                }
                continue;
            }

            $quedan++;
            if ($parada['nombre'] === '' || $parada['comunidad'] === ''
                || mb_strlen($parada['nombre']) > 100 || mb_strlen($parada['comunidad']) > 100) {
                $errores[] = $etiqueta . ': escribe el nombre y la comunidad.';
            }
            if ($parada['km_referencia'] !== ''
                && (!is_numeric($parada['km_referencia']) || $parada['km_referencia'] < 0 || $parada['km_referencia'] > 9999)) {
                $errores[] = $etiqueta . ': el km debe ser un número entre 0 y 9999.';
            }
        }
        if ($quedan === 0) {
            $errores[] = 'La ruta necesita al menos una parada.';
        }

        if ($errores) {
            $_SESSION['formulario_ruta'] = ['errores' => $errores, 'datos' => $datos];
            $this->volver();
        }

        // ----- Guardar todo junto -----
        try {
            $conexion->beginTransaction();

            if ($conductorNuevo) {
                $modelo->crearConductor(
                    $datos['conductor_documento'],
                    $datos['conductor_nombres'],
                    $datos['conductor_apellidos'],
                    $datos['conductor_telefono'] ?: null
                );
                $documentoConductor = $datos['conductor_documento'];
            } else {
                $documentoConductor = $datos['conductor'];
            }

            if ($idRuta) {
                $modelo->actualizar($idRuta, $datos['nombre'], $documentoConductor);
            } else {
                $idRuta = $modelo->crear($datos['nombre'], $documentoConductor);
            }

            if ($idAuxiliar) {
                $modelo->asignarAuxiliar($idAuxiliar, $idRuta);
            }

            foreach ($datos['paradas'] as $parada) {
                $idParada = $parada['id_parada'] === '' ? null : (int) $parada['id_parada'];
                $km = $parada['km_referencia'] === '' ? null : (float) $parada['km_referencia'];

                if ($parada['quitar']) {
                    if ($idParada) {
                        $modelo->eliminarParada($idParada, $idRuta);
                    }
                } elseif ($idParada) {
                    $modelo->actualizarParada($idParada, $idRuta, $parada['nombre'], $parada['comunidad'], $km);
                } else {
                    $modelo->crearParada($idRuta, $parada['nombre'], $parada['comunidad'], $km);
                }
            }

            $conexion->commit();
        } catch (Throwable $error) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $_SESSION['formulario_ruta'] = [
                'errores' => ['No se pudo guardar la ruta. Intenta de nuevo.'],
                'datos' => $datos,
            ];
            $this->volver();
        }

        $_SESSION['ruta_guardada'] = 'Ruta "' . $datos['nombre'] . '" guardada.';
        $this->volver();
    }

    private function volver(): void
    {
        header('Location: ' . \App\Core\Url::base() . '/rutas');
        exit;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
