<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ExcelAsistencia;
use App\Database\Connection;
use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Ruta;
use DateTime;
use PDO;
use PDOException;

// Asistencia en el bus.
// - El auxiliar marca en "Registro" quién sube (ida) o baja (regreso) hoy en su ruta.
// - El administrador ve "Asistencias" por fecha y ruta, y puede hacer registros manuales.
// Cada registro queda en una jornada (ruta + recorrido + fecha + usuario que registra).
class AsistenciaController
{
    private const CERRADO = 'El recorrido ya está cerrado. Para corregir algo, pide a administración.';
    private const TIPOS = ['IDA' => 'Ida', 'REGRESO' => 'Regreso'];

    // ----- Auxiliar -----

    //Lista de estudiantes de la ruta del auxiliar para marcar la subida o la bajada de hoy
    public function registro()
    {
        Auth::requireRole('auxiliar');

        $usuario = $_SESSION['usuario'];
        $idRuta = (int) ($usuario['id_ruta'] ?? 0);
        $tipo = isset(self::TIPOS[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : 'IDA';

        $conexion = $this->conectar();
        $modeloRuta = new Ruta($conexion);
        $ruta = $modeloRuta->obtenerPorId($idRuta);

        //Número de cada parada según el recorrido (Parada 1, Parada 2...)
        $numeroParada = [];
        foreach ($modeloRuta->obtenerParadas($idRuta) as $indice => $parada) {
            $numeroParada[$parada['id_parada']] = $indice + 1;
        }

        $modelo = new Asistencia($conexion);
        $estudiantes = $ruta ? $modelo->obtenerDeRuta($idRuta, date('Y-m-d')) : [];
        $horaCierre = $ruta ? $modelo->horaCierre($idRuta, $tipo, date('Y-m-d')) : null;

        //En el regreso el recorrido va del colegio a la casa: las paradas se invierten
        if ($tipo === 'REGRESO') {
            $estudiantes = $this->invertirParadas($estudiantes);
        }

        $campoHora = $tipo === 'IDA' ? 'hora_ida' : 'hora_regreso';
        $aBordo = count(array_filter($estudiantes, fn($e) => $e[$campoHora] !== null));

        $aviso = $_SESSION['asistencia_aviso'] ?? null;
        unset($_SESSION['asistencia_aviso']);

        require __DIR__ . '/../Views/asistencia/registro.php';
    }

    //Marca que el estudiante subió (ida) o bajó (regreso) ahora mismo
    public function marcar()
    {
        Auth::requireRole('auxiliar');

        [$documento, $tipo] = $this->leerDocumentoYTipo();
        $idRuta = (int) ($_SESSION['usuario']['id_ruta'] ?? 0);
        $hoy = date('Y-m-d');

        $conexion = $this->conectar();
        $modelo = new Asistencia($conexion);
        $estudiante = (new Estudiante($conexion))->obtenerFicha($documento);

        if (!$estudiante || (int) $estudiante['id_ruta'] !== $idRuta) {
            $this->avisar('error', 'Ese estudiante no pertenece a tu ruta.');
        } elseif ($modelo->horaCierre($idRuta, $tipo, $hoy) !== null) {
            $this->avisar('error', self::CERRADO);
        } else {
            $registro = $modelo->registrar($documento, (int) $_SESSION['usuario']['id_usuario'], $tipo, $hoy, date('H:i:s'));

            if ($registro['resultado'] === 'ya_registrado') {
                $this->avisar('error', $estudiante['nombre'] . ' ya tiene registro de ' . strtolower(self::TIPOS[$tipo]) . ' hoy.');
            } else {
                $this->avisar('exito', $estudiante['nombre'] . ' ' . $estudiante['apellido']
                    . ($tipo === 'IDA' ? ' subió a las ' : ' bajó a las ') . substr($registro['hora'], 0, 5) . '.');
            }
        }

        $this->ir('/asistencia/registro?tipo=' . $tipo);
    }

    //Quita un registro de hoy hecho por este mismo auxiliar (por si se marcó por error).
    //Desde el escáner responde en JSON; desde la lista vuelve al registro.
    public function deshacer()
    {
        Auth::requireRole('auxiliar');

        [$documento, $tipo] = $this->leerDocumentoYTipo();
        $modelo = new Asistencia($this->conectar());

        if ($modelo->horaCierre((int) ($_SESSION['usuario']['id_ruta'] ?? 0), $tipo, date('Y-m-d')) !== null) {
            if ($this->pideJson()) {
                $this->responderJson(['ok' => false, 'mensaje' => self::CERRADO], 409);
            }
            $this->avisar('error', self::CERRADO);
            $this->ir('/asistencia/registro?tipo=' . $tipo);
        }

        $modelo->deshacer($documento, $tipo, date('Y-m-d'), (int) $_SESSION['usuario']['id_usuario']);

        if ($this->pideJson()) {
            $this->responderJson(['ok' => true] + $this->conteoDeHoy($modelo, $tipo));
        }

        $this->avisar('exito', 'Registro deshecho.');
        $this->ir('/asistencia/registro?tipo=' . $tipo);
    }

    //Cierra el recorrido de hoy: desde aquí el auxiliar ya no puede marcar, escanear ni deshacer
    public function cerrar()
    {
        Auth::requireRole('auxiliar');

        $tipo = is_string($_POST['tipo'] ?? null) && isset(self::TIPOS[$_POST['tipo']]) ? $_POST['tipo'] : null;
        $idRuta = (int) ($_SESSION['usuario']['id_ruta'] ?? 0);
        if ($tipo === null || $idRuta === 0) {
            $this->avisar('error', 'No se pudo cerrar el recorrido.');
            $this->ir('/asistencia/registro');
        }

        (new Asistencia($this->conectar()))->cerrarRecorrido(
            $idRuta, (int) $_SESSION['usuario']['id_usuario'], $tipo, date('Y-m-d'), date('H:i:s')
        );
        $this->avisar('exito', 'Recorrido de ' . strtolower(self::TIPOS[$tipo]) . ' cerrado.');
        $this->ir('/asistencia/registro?tipo=' . $tipo);
    }

    //Escáner QR: recibe el documento leído del carné y responde en JSON qué pasó.
    //resultado: registrado (verde), ya_registrado (amarillo), otra_ruta o desconocido (rojo)
    public function escanear()
    {
        Auth::requireRole('auxiliar');

        $documento = is_string($_POST['documento'] ?? null) ? trim($_POST['documento']) : '';
        $tipo = is_string($_POST['tipo'] ?? null) && isset(self::TIPOS[$_POST['tipo']]) ? $_POST['tipo'] : null;
        $idRuta = (int) ($_SESSION['usuario']['id_ruta'] ?? 0);
        $hoy = date('Y-m-d');

        if ($tipo === null || $idRuta === 0) {
            $this->responderJson(['resultado' => 'error', 'mensaje' => 'No tienes una ruta asignada.'], 400);
        }

        $conexion = $this->conectar();
        $modelo = new Asistencia($conexion);
        if ($modelo->horaCierre($idRuta, $tipo, $hoy) !== null) {
            $this->responderJson(['resultado' => 'cerrado', 'mensaje' => self::CERRADO] + $this->conteoDeHoy($modelo, $tipo));
        }

        $ficha = preg_match('/^[A-Za-z0-9-]{1,20}$/', $documento) ? (new Estudiante($conexion))->obtenerFicha($documento) : null;

        if (!$ficha) {
            $this->responderJson(['resultado' => 'desconocido', 'documento' => mb_substr($documento, 0, 40)]
                + $this->conteoDeHoy($modelo, $tipo));
        }

        $datos = [
            'documento' => $ficha['num_doc_estu'],
            'tipo_documento' => $ficha['tipo_doc_estu'],
            'nombre' => trim($ficha['nombre'] . ' ' . $ficha['apellido']),
            'curso' => $ficha['curso'],
            'parada' => $ficha['parada'],
            'comunidad' => $ficha['comunidad'],
            'ruta' => $ficha['ruta'],
            'id_ruta' => (int) $ficha['id_ruta'],
        ];

        if ((int) $ficha['id_ruta'] !== $idRuta) {
            $this->responderJson(['resultado' => 'otra_ruta'] + $datos + $this->conteoDeHoy($modelo, $tipo));
        }

        //Número de parada ("1 de 3") y acudiente principal, para la ficha que ve el auxiliar
        $paradas = (new Ruta($conexion))->obtenerParadas($idRuta);
        foreach ($paradas as $indice => $parada) {
            if ((int) $parada['id_parada'] === (int) $ficha['id_parada']) {
                $datos['numero_parada'] = $indice + 1;
            }
        }
        $datos['total_paradas'] = count($paradas);

        $campoHora = $tipo === 'IDA' ? 'hora_ida' : 'hora_regreso';
        $fila = null;
        foreach ($modelo->obtenerDeRuta($idRuta, $hoy) as $estudiante) {
            if ($estudiante['documento'] === $ficha['num_doc_estu']) {
                $fila = $estudiante;
            }
        }
        $datos['acudiente'] = $fila['acudiente'] ?? null;
        $datos['parentesco'] = $fila['parentesco'] ?? null;
        $datos['telefono'] = $fila['telefono_acudiente'] ?? null;

        if ($fila && $fila[$campoHora] !== null) {
            $this->responderJson(['resultado' => 'ya_registrado', 'hora' => substr($fila[$campoHora], 0, 5)]
                + $datos + $this->conteoDeHoy($modelo, $tipo));
        }

        //Si dos lecturas del mismo carné llegan casi juntas, el procedimiento guarda solo la primera
        //y a la segunda le responde ya_registrado con la hora de la primera
        $registro = $modelo->registrar($ficha['num_doc_estu'], (int) $_SESSION['usuario']['id_usuario'], $tipo, $hoy, date('H:i:s'));

        $this->responderJson(['resultado' => $registro['resultado'], 'hora' => substr($registro['hora'], 0, 5)]
            + $datos + $this->conteoDeHoy($modelo, $tipo));
    }

    // ----- Administrador -----

    //Asistencias de una fecha, por ruta (ruta=0 muestra todas)
    public function listado()
    {
        Auth::requireRole('administrador');

        $fecha = $this->fechaValida($_GET['fecha'] ?? '') ?? date('Y-m-d');

        $conexion = $this->conectar();
        $modeloAsistencia = new Asistencia($conexion);
        $rutas = (new Ruta($conexion))->obtenerTodas();

        $idsRutas = array_map('intval', array_column($rutas, 'id_ruta'));
        $idRuta = filter_var($_GET['ruta'] ?? '', FILTER_VALIDATE_INT);
        if ($idRuta === false || ($idRuta !== 0 && !in_array($idRuta, $idsRutas, true))) {
            $idRuta = $idsRutas[0] ?? 0;
        }

        $rutasMostrar = array_filter($rutas, fn($ruta) => $idRuta === 0 || (int) $ruta['id_ruta'] === $idRuta);
        [
            'estudiantes' => $estudiantes,
            'auxiliaresRuta' => $auxiliaresRuta,
            'total' => $total,
            'conIda' => $conIda,
            'conRegreso' => $conRegreso,
            'pendientes' => $pendientes,
        ] = $this->datosDelDia($rutasMostrar, $fecha, $modeloAsistencia);

        //Recorridos cerrados por el auxiliar (la administración los puede reabrir)
        $cierres = [];
        foreach ($rutasMostrar as $ruta) {
            foreach (self::TIPOS as $tipo => $nombreTipo) {
                $hora = $modeloAsistencia->horaCierre((int) $ruta['id_ruta'], $tipo, $fecha);
                if ($hora !== null) {
                    $cierres[] = ['id_ruta' => (int) $ruta['id_ruta'], 'ruta' => $ruta['nombre'], 'tipo' => $tipo, 'nombre' => $nombreTipo, 'hora' => $hora];
                }
            }
        }

        $soloLectura = false;
        $formulario = $_SESSION['asistencia_manual'] ?? null;
        $aviso = $_SESSION['asistencia_aviso'] ?? null;
        unset($_SESSION['asistencia_manual'], $_SESSION['asistencia_aviso']);

        require __DIR__ . '/../Views/asistencia/listado.php';
    }

    //Reabre un recorrido cerrado por error
    public function reabrir()
    {
        Auth::requireRole('administrador');

        $tipo = is_string($_POST['tipo'] ?? null) && isset(self::TIPOS[$_POST['tipo']]) ? $_POST['tipo'] : null;
        $fecha = $this->fechaValida(is_string($_POST['fecha'] ?? null) ? $_POST['fecha'] : '');
        $idRuta = (int) filter_var($_POST['id_ruta'] ?? '', FILTER_VALIDATE_INT);
        $filtro = (int) filter_var($_POST['ruta'] ?? '', FILTER_VALIDATE_INT);

        if ($tipo === null || $fecha === null || $idRuta === 0) {
            $this->avisar('error', 'No se pudo reabrir el recorrido.');
            $this->ir('/asistencias');
        }

        (new Asistencia($this->conectar()))->reabrirRecorrido($idRuta, $tipo, $fecha);
        $this->avisar('exito', 'Recorrido de ' . strtolower(self::TIPOS[$tipo]) . ' reabierto.');
        $this->ir('/asistencias?fecha=' . $fecha . '&ruta=' . $filtro);
    }

    //Excel de una fecha y una ruta (ruta=0 = todas)
    public function exportar()
    {
        Auth::requireRole('administrador');

        $fecha = $this->fechaValida(is_string($_GET['fecha'] ?? null) ? $_GET['fecha'] : '') ?? date('Y-m-d');
        $idRuta = (int) filter_var($_GET['ruta'] ?? '', FILTER_VALIDATE_INT);
        $conexion = $this->conectar();
        $rutas = array_values(array_filter(
            (new Ruta($conexion))->obtenerTodas(),
            fn($ruta) => $idRuta === 0 || (int) $ruta['id_ruta'] === $idRuta
        ));
        $this->descargarExcel($conexion, $rutas, $fecha, $idRuta === 0);
    }

    //Excel del auxiliar: siempre y solo su ruta
    public function exportarMiRuta()
    {
        Auth::requireRole('auxiliar');

        $fecha = $this->fechaValida(is_string($_GET['fecha'] ?? null) ? $_GET['fecha'] : '') ?? date('Y-m-d');
        $conexion = $this->conectar();
        $idRuta = (int) ($_SESSION['usuario']['id_ruta'] ?? 0);
        $rutas = $idRuta === 0 ? [] : (new Ruta($conexion))->obtenerTodas($idRuta);
        $this->descargarExcel($conexion, $rutas, $fecha, false);
    }

    private function descargarExcel(PDO $conexion, array $rutas, string $fecha, bool $todas): void
    {
        if (!$rutas) {
            http_response_code(404);
            echo '404 - No hay una ruta para exportar.';
            exit;
        }

        $datos = $this->datosDelDia($rutas, $fecha, new Asistencia($conexion));
        $nombreRuta = $todas ? 'Todas las rutas' : $rutas[0]['nombre'];
        $contenido = (new ExcelAsistencia())->crear($datos['estudiantes'], $nombreRuta, $datos['auxiliaresRuta'], $fecha, $todas);

        $archivo = 'RutaSegura_Asistencias_' . $fecha . ($todas ? '_todas' : '_ruta' . (int) $rutas[0]['id_ruta']) . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . strlen($contenido));
        echo $contenido;
        exit;
    }

    //Historial del auxiliar: la misma vista de Asistencias, solo de su ruta y sin poder cambiar nada
    public function historial()
    {
        Auth::requireRole('auxiliar');

        $fecha = $this->fechaValida($_GET['fecha'] ?? '') ?? date('Y-m-d');
        $idRuta = (int) ($_SESSION['usuario']['id_ruta'] ?? 0);

        $conexion = $this->conectar();
        $modeloAsistencia = new Asistencia($conexion);
        $rutas = (new Ruta($conexion))->obtenerTodas($idRuta);
        [
            'estudiantes' => $estudiantes,
            'auxiliaresRuta' => $auxiliaresRuta,
            'total' => $total,
            'conIda' => $conIda,
            'conRegreso' => $conRegreso,
            'pendientes' => $pendientes,
        ] = $this->datosDelDia($rutas, $fecha, $modeloAsistencia);

        //Accesos rápidos a los últimos días hábiles
        $diasRecientes = [];
        for ($dia = new DateTime('today'); count($diasRecientes) < 5; $dia->modify('-1 day')) {
            if ((int) $dia->format('N') <= 5) {
                $diasRecientes[] = $dia->format('Y-m-d');
            }
        }

        $cierres = [];
        $soloLectura = true;
        $formulario = null;
        $aviso = null;

        require __DIR__ . '/../Views/asistencia/listado.php';
    }

    //Estudiantes de las rutas indicadas con sus registros del día y los totales
    private function datosDelDia(array $rutas, string $fecha, Asistencia $modelo): array
    {
        $estudiantes = [];
        $auxiliaresRuta = [];
        foreach ($rutas as $ruta) {
            foreach ($modelo->obtenerDeRuta((int) $ruta['id_ruta'], $fecha) as $fila) {
                $fila['ruta'] = $ruta['nombre'];
                $estudiantes[] = $fila;
            }
            $auxiliaresRuta = array_merge($auxiliaresRuta, $modelo->obtenerAuxiliaresDeRuta((int) $ruta['id_ruta']));
        }

        return [
            'estudiantes' => $estudiantes,
            'auxiliaresRuta' => $auxiliaresRuta,
            'total' => count($estudiantes),
            'conIda' => count(array_filter($estudiantes, fn($e) => $e['hora_ida'] !== null)),
            'conRegreso' => count(array_filter($estudiantes, fn($e) => $e['hora_regreso'] !== null)),
            //Pendientes: subieron en la ida y todavía no tienen regreso
            'pendientes' => count(array_filter($estudiantes, fn($e) => $e['hora_ida'] !== null && $e['hora_regreso'] === null)),
        ];
    }

    //Registro manual hecho por la administración (queda a nombre del administrador)
    public function manual()
    {
        Auth::requireRole('administrador');

        $texto = fn($valor) => is_string($valor) ? trim($valor) : '';
        $datos = [
            'documento' => $texto($_POST['documento'] ?? ''),
            'tipo' => $texto($_POST['tipo'] ?? ''),
            'hora' => $texto($_POST['hora'] ?? ''),
            'fecha' => $texto($_POST['fecha'] ?? ''),
            'ruta' => $texto($_POST['ruta'] ?? ''),
        ];
        $volver = '/asistencias?fecha=' . urlencode($datos['fecha']) . '&ruta=' . urlencode($datos['ruta']);

        $conexion = $this->conectar();
        $modelo = new Asistencia($conexion);
        $estudiante = $datos['documento'] === '' ? null : (new Estudiante($conexion))->obtenerFicha($datos['documento']);
        $fecha = $this->fechaValida($datos['fecha']);

        $error = null;
        if (!$estudiante) {
            $error = 'Elige un estudiante.';
        } elseif (!isset(self::TIPOS[$datos['tipo']])) {
            $error = 'Elige si es ida o regreso.';
        } elseif (!$fecha) {
            $error = 'La fecha no es válida (no puede ser futura).';
        } elseif (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $datos['hora'])) {
            $error = 'Escribe la hora en formato 24 horas, por ejemplo 06:30.';
        } elseif ($fecha === date('Y-m-d') && $datos['hora'] > date('H:i')) {
            $error = 'La hora no puede ser posterior a la hora actual.';
        }

        if ($error) {
            $_SESSION['asistencia_manual'] = ['error' => $error, 'datos' => $datos];
            $this->ir($volver);
        }

        //El procedimiento guarda jornada y asistencia en una transacción (si algo falla, deshace todo)
        try {
            $registro = $modelo->registrar($datos['documento'], (int) $_SESSION['usuario']['id_usuario'], $datos['tipo'], $fecha, $datos['hora'] . ':00');
        } catch (PDOException $excepcion) {
            $_SESSION['asistencia_manual'] = ['error' => 'No se pudo guardar el registro. Intenta de nuevo.', 'datos' => $datos];
            $this->ir($volver);
        }

        if ($registro['resultado'] === 'ya_registrado') {
            $_SESSION['asistencia_manual'] = [
                'error' => 'Ese estudiante ya tiene registro de ' . strtolower(self::TIPOS[$datos['tipo']]) . ' en esa fecha.',
                'datos' => $datos,
            ];
            $this->ir($volver);
        }

        $this->avisar('exito', 'Registro manual guardado: ' . $estudiante['nombre'] . ' ' . $estudiante['apellido']
            . ' · ' . self::TIPOS[$datos['tipo']] . ' ' . $datos['hora'] . '.');
        $this->ir($volver);
    }

    // ----- Ayudas -----

    //Lee documento y tipo (IDA/REGRESO) del formulario; si faltan, vuelve al registro
    private function leerDocumentoYTipo(): array
    {
        $documento = is_string($_POST['documento'] ?? null) ? trim($_POST['documento']) : '';
        $tipo = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '';

        if ($documento === '' || !isset(self::TIPOS[$tipo])) {
            $this->avisar('error', 'No se pudo leer el registro. Intenta de nuevo.');
            $this->ir('/asistencia/registro');
        }
        return [$documento, $tipo];
    }

    //Devuelve la fecha si es Y-m-d válida y no es futura; si no, null
    private function fechaValida(string $texto): ?string
    {
        $fecha = DateTime::createFromFormat('!Y-m-d', $texto);
        if (!$fecha || $fecha->format('Y-m-d') !== $texto || $texto > date('Y-m-d')) {
            return null;
        }
        return $texto;
    }

    //Pone las paradas en orden inverso sin desordenar a los estudiantes de cada parada
    private function invertirParadas(array $estudiantes): array
    {
        $porParada = [];
        foreach ($estudiantes as $estudiante) {
            $porParada[$estudiante['id_parada']][] = $estudiante;
        }
        $grupos = array_values(array_reverse($porParada, true));
        return $grupos ? array_merge(...$grupos) : [];
    }

    //Cuántos estudiantes de la ruta del auxiliar ya tienen registro hoy en ese recorrido
    private function conteoDeHoy(Asistencia $modelo, string $tipo): array
    {
        $estudiantes = $modelo->obtenerDeRuta((int) ($_SESSION['usuario']['id_ruta'] ?? 0), date('Y-m-d'));
        $campoHora = $tipo === 'IDA' ? 'hora_ida' : 'hora_regreso';
        return [
            'a_bordo' => count(array_filter($estudiantes, fn($e) => $e[$campoHora] !== null)),
            'total' => count($estudiantes),
        ];
    }

    //El escáner pide las respuestas en JSON (cabecera Accept)
    private function pideJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    private function responderJson(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function avisar(string $tipo, string $texto): void
    {
        $_SESSION['asistencia_aviso'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function ir(string $ruta): void
    {
        header('Location: ' . \App\Core\Url::base() . $ruta);
        exit;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
