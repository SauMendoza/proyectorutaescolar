<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Acudiente;
use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Ruta;
use App\Database\Connection;
use DateTime;
use PDO;
use PDOException;

class EstudianteController
{
    //Tipos de documento que acepta el formulario
    private const TIPOS_DOCUMENTO = [
        'TI' => 'Tarjeta de identidad',
        'RC' => 'Registro civil',
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
    ];

    //Paso del formulario donde está cada campo (para abrir el paso con el error)
    private const PASO_DE_CAMPO = [
        'tipo_documento' => 1, 'documento' => 1, 'nombre' => 1, 'segundo_nombre' => 1,
        'apellido' => 1, 'segundo_apellido' => 1, 'fecha_nacimiento' => 1, 'curso' => 1,
        'parada' => 2,
        'principal' => 3,
        'suplente' => 4,
    ];

    public function presentacion()
    {
        //Solo entran usuarios con sesión iniciada (administrador o auxiliar)
        Auth::requireLogin();

        // Crear el modelo y consultar la vista SQL y las estadísticas
        $modeloEstudiante = new Estudiante($this->conectar());
        $estudiantes = $modeloEstudiante->obtenerPanelAsistencia();
        $estadisticas = $modeloEstudiante->obtenerEstadisticas();

        //Opciones de los filtros: todas las rutas, paradas y cursos, aunque todavía no tengan estudiantes
        $rutasFiltro = $modeloEstudiante->obtenerRutas();
        $paradasFiltro = $modeloEstudiante->obtenerParadas();
        $cursosFiltro = $modeloEstudiante->obtenerCursos();

        //Solo el administrador ve el botón "+ Nuevo estudiante"
        $esAdmin = $_SESSION['usuario']['rol'] === 'administrador';

        //Mensaje que deja guardar() cuando el registro sale bien
        $mensajeExito = $_SESSION['estudiante_guardado'] ?? null;
        unset($_SESSION['estudiante_guardado']);

        // Cargar la vista y pasarle los estudiantes y las estadísticas
        require_once __DIR__ . '/../Views/estudiantes/presentacion.php';
    }

    //Ficha de un estudiante: datos, recorrido, acudientes y asistencia del mes
    public function ficha()
    {
        Auth::requireLogin();

        $documento = trim($_GET['documento'] ?? '');
        $conexion = $this->conectar();
        $ficha = $documento === '' ? null : (new Estudiante($conexion))->obtenerFicha($documento);

        if ($ficha === null) {
            http_response_code(404);
            echo '404 - No se encontró el estudiante.';
            exit;
        }

        $acudientes = (new Acudiente($conexion))->obtenerDeEstudiante($documento);
        $paradas = (new Ruta($conexion))->obtenerParadas((int) $ficha['id_ruta']);

        //Calendario del mes actual, solo de lunes a viernes
        $modeloAsistencia = new Asistencia($conexion);
        $hoy = date('Y-m-d');
        $primerDia = date('Y-m-01');
        $ultimoDia = date('Y-m-t');

        $registrosMes = [];
        foreach ($modeloAsistencia->obtenerDelEstudiante($documento, $primerDia, $ultimoDia) as $registro) {
            $registrosMes[$registro['fecha']][$registro['tipo_recorrido']] = true;
        }

        $dias = [];
        $habilesHastaHoy = 0;
        $conIda = 0;
        $conRegreso = 0;
        for ($fecha = new DateTime($primerDia); $fecha->format('Y-m-d') <= $ultimoDia; $fecha->modify('+1 day')) {
            $diaSemana = (int) $fecha->format('N'); // 1 = lunes ... 7 = domingo
            if ($diaSemana > 5) {
                continue;
            }

            $texto = $fecha->format('Y-m-d');
            //El primer día hábil se corre a su columna (L, M, X, J o V)
            if (count($dias) === 0) {
                for ($i = 1; $i < $diaSemana; $i++) {
                    $dias[] = null;
                }
            }

            $ida = isset($registrosMes[$texto]['IDA']);
            $regreso = isset($registrosMes[$texto]['REGRESO']);
            $dias[] = [
                'numero' => (int) $fecha->format('j'),
                'ida' => $ida,
                'regreso' => $regreso,
                'hoy' => $texto === $hoy,
                'futuro' => $texto > $hoy,
            ];

            if ($texto <= $hoy) {
                $habilesHastaHoy++;
                $conIda += $ida ? 1 : 0;
                $conRegreso += $regreso ? 1 : 0;
            }
        }

        $uso = $habilesHastaHoy > 0 ? round(($conIda + $conRegreso) * 100 / ($habilesHastaHoy * 2)) : 0;
        $ultimosRegistros = $modeloAsistencia->obtenerUltimosDelEstudiante($documento);

        //Datos de las ventanas "Editar" y "Borrar" (solo administrador)
        $esAdmin = $_SESSION['usuario']['rol'] === 'administrador';
        $mensajeExito = $_SESSION['ficha_aviso'] ?? null;
        $errorBorrar = $_SESSION['borrar_estudiante_error'] ?? null;
        $edicion = $_SESSION['editar_estudiante'] ?? null;
        unset($_SESSION['ficha_aviso'], $_SESSION['borrar_estudiante_error'], $_SESSION['editar_estudiante']);
        if ($edicion && $edicion['documento'] !== $ficha['num_doc_estu']) {
            $edicion = null;
        }

        if ($esAdmin) {
            $modeloEstudiante = new Estudiante($conexion);
            $modeloAcudiente = new Acudiente($conexion);
            $tiposDocumento = self::TIPOS_DOCUMENTO;
            $cursos = $modeloEstudiante->obtenerCursos();
            $rutas = $modeloEstudiante->obtenerRutas();
            $todasParadas = $modeloEstudiante->obtenerParadas();
            $parentescos = $modeloAcudiente->obtenerParentescos();
            $todosAcudientes = $modeloAcudiente->obtenerTodos();
            $totalAsistencias = $modeloEstudiante->contarAsistencias($ficha['num_doc_estu']);
        }

        require __DIR__ . '/../Views/estudiantes/ficha.php';
    }

    //Muestra el formulario de nuevo estudiante
    public function nuevo()
    {
        Auth::requireRole('administrador');

        $conexion = $this->conectar();
        $modeloEstudiante = new Estudiante($conexion);
        $modeloAcudiente = new Acudiente($conexion);

        $tiposDocumento = self::TIPOS_DOCUMENTO;
        $cursos = $modeloEstudiante->obtenerCursos();
        $rutas = $modeloEstudiante->obtenerRutas();
        $paradas = $modeloEstudiante->obtenerParadas();
        $parentescos = $modeloAcudiente->obtenerParentescos();

        //Si guardar() encontró errores, vuelven aquí con lo que se había escrito
        $errores = $_SESSION['nuevo_estudiante']['errores'] ?? [];
        $datos = $_SESSION['nuevo_estudiante']['datos'] ?? [];
        unset($_SESSION['nuevo_estudiante']);

        $pasoInicial = 1;
        if ($errores) {
            $pasoInicial = self::PASO_DE_CAMPO[array_key_first($errores)] ?? 1;
        }

        require_once __DIR__ . '/../Views/estudiantes/nuevo.php';
    }

    //Recibe el formulario y guarda estudiante, acudientes y parentescos
    public function guardar()
    {
        Auth::requireRole('administrador');

        $datos = array_map(
            fn($valor) => is_string($valor) ? trim($valor) : '',
            $_POST
        );

        $conexion = $this->conectar();
        $modeloEstudiante = new Estudiante($conexion);
        $modeloAcudiente = new Acudiente($conexion);

        $errores = [];

        // ----- Pasos 1 y 2: estudiante y transporte -----
        $documento = $datos['documento'] ?? '';
        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $documento)) {
            $errores['documento'] = 'El documento debe tener entre 4 y 20 letras o números.';
        } elseif ($modeloEstudiante->existe($documento)) {
            $errores['documento'] = 'Ya hay un estudiante registrado con ese documento.';
        }

        [$edad, $curso, $parada] = $this->validarDatos($datos, $modeloEstudiante, $errores);

        // ----- Pasos 3 y 4: acudientes -----
        $parentescos = array_column($modeloAcudiente->obtenerParentescos(), 'id_parentesco');
        $principal = $this->validarAcudiente('principal', true, $datos, $parentescos, $modeloAcudiente, $errores);
        $suplente = $this->validarAcudiente('suplente', false, $datos, $parentescos, $modeloAcudiente, $errores);

        if ($principal && $suplente && $principal['documento'] === $suplente['documento']) {
            $errores['suplente'] = 'El suplente debe ser una persona distinta al principal.';
        }

        if ($errores) {
            $this->volverAlFormulario($errores, $datos);
        }

        // ----- Guardar todo junto: si algo falla, no queda nada a medias -----
        try {
            $conexion->beginTransaction();

            $modeloEstudiante->crear([
                'documento' => $documento,
                'tipo_documento' => $datos['tipo_documento'],
                'nombre' => $datos['nombre'],
                'segundo_nombre' => $datos['segundo_nombre'] ?? '',
                'apellido' => $datos['apellido'],
                'segundo_apellido' => $datos['segundo_apellido'] ?? '',
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'edad' => $edad,
                'curso' => $curso,
                'parada' => $parada,
            ]);

            foreach (['PRINCIPAL' => $principal, 'SUPLENTE' => $suplente] as $tipo => $acudiente) {
                if (!$acudiente) {
                    continue;
                }
                if ($acudiente['nuevo']) {
                    $modeloAcudiente->crear(
                        $acudiente['documento'],
                        $acudiente['nombre'],
                        $acudiente['apellido'],
                        $acudiente['telefono']
                    );
                }
                $modeloAcudiente->asignar($acudiente['documento'], $documento, $acudiente['parentesco'], $tipo);
            }

            $conexion->commit();
        } catch (PDOException $e) {
            $conexion->rollBack();
            error_log($e->getMessage());

            $mensaje = $e->getCode() === '23000'
                ? 'El estudiante o el acudiente ya se registró. Revisa los documentos.'
                : 'No fue posible guardar el estudiante. Intenta de nuevo.';
            $this->volverAlFormulario(['documento' => $mensaje], $datos);
        }

        $_SESSION['estudiante_guardado'] = 'Se registró a ' . $datos['nombre'] . ' ' . $datos['apellido'] . '.';
        header('Location: ' . \App\Core\Url::base() . '/estudiantes');
        exit;
    }

    //Guarda los cambios de un estudiante y sus acudientes (ventana "Editar" de la ficha)
    public function actualizar()
    {
        Auth::requireRole('administrador');

        $datos = array_map(fn($valor) => is_string($valor) ? trim($valor) : '', $_POST);
        $documento = $datos['documento'] ?? '';

        $conexion = $this->conectar();
        $modeloEstudiante = new Estudiante($conexion);
        $modeloAcudiente = new Acudiente($conexion);

        if ($documento === '' || !$modeloEstudiante->existe($documento)) {
            http_response_code(404);
            echo '404 - No se encontró el estudiante.';
            exit;
        }

        $errores = [];
        [$edad, $curso, $parada] = $this->validarDatos($datos, $modeloEstudiante, $errores);

        $parentescos = array_column($modeloAcudiente->obtenerParentescos(), 'id_parentesco');
        $principal = $this->validarAcudiente('principal', true, $datos, $parentescos, $modeloAcudiente, $errores);
        $suplente = $this->validarAcudiente('suplente', false, $datos, $parentescos, $modeloAcudiente, $errores);

        //Aquí solo se eligen acudientes ya registrados
        foreach (['principal' => $principal, 'suplente' => $suplente] as $prefijo => $acudiente) {
            if ($acudiente && $acudiente['nuevo']) {
                $errores[$prefijo] = 'El acudiente elegido no está registrado.';
            }
        }
        if ($principal && $suplente && $principal['documento'] === $suplente['documento']) {
            $errores['suplente'] = 'El suplente debe ser una persona distinta al principal.';
        }

        if (!$errores) {
            //Datos y vínculos se guardan juntos: si algo falla, el estudiante conserva sus acudientes
            try {
                $conexion->beginTransaction();
                $modeloEstudiante->actualizar($documento, [
                    'tipo_documento' => $datos['tipo_documento'],
                    'nombre' => $datos['nombre'],
                    'segundo_nombre' => $datos['segundo_nombre'] ?? '',
                    'apellido' => $datos['apellido'],
                    'segundo_apellido' => $datos['segundo_apellido'] ?? '',
                    'fecha_nacimiento' => $datos['fecha_nacimiento'],
                    'edad' => $edad,
                    'curso' => $curso,
                    'parada' => $parada,
                ]);
                $modeloAcudiente->desvincularDeEstudiante($documento);
                foreach (['PRINCIPAL' => $principal, 'SUPLENTE' => $suplente] as $tipo => $acudiente) {
                    if ($acudiente) {
                        $modeloAcudiente->asignar($acudiente['documento'], $documento, $acudiente['parentesco'], $tipo);
                    }
                }
                $conexion->commit();
                $_SESSION['ficha_aviso'] = 'Cambios guardados.';
            } catch (PDOException $e) {
                $conexion->rollBack();
                error_log($e->getMessage());
                $errores['general'] = 'No fue posible guardar los cambios. Intenta de nuevo.';
            }
        }

        if ($errores) {
            $_SESSION['editar_estudiante'] = ['documento' => $documento, 'errores' => $errores, 'datos' => $datos];
        }
        header('Location: ' . \App\Core\Url::base() . '/estudiantes/ficha?documento=' . urlencode($documento));
        exit;
    }

    //Borra un estudiante. Hay que escribir su documento para confirmar.
    public function eliminar()
    {
        Auth::requireRole('administrador');

        $documento = is_string($_POST['documento'] ?? null) ? trim($_POST['documento']) : '';
        $confirmacion = is_string($_POST['confirmacion'] ?? null) ? trim($_POST['confirmacion']) : '';
        $modelo = new Estudiante($this->conectar());
        $ficha = $documento === '' ? null : $modelo->obtenerFicha($documento);

        if ($ficha === null) {
            http_response_code(404);
            echo '404 - No se encontró el estudiante.';
            exit;
        }

        $error = null;
        if ($confirmacion !== $ficha['num_doc_estu']) {
            $error = 'El documento escrito no coincide. No se borró nada.';
        } else {
            try {
                $modelo->eliminar($ficha['num_doc_estu']);
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'No fue posible borrar el estudiante. No se borró nada.';
            }
        }

        if ($error) {
            $_SESSION['borrar_estudiante_error'] = $error;
            header('Location: ' . \App\Core\Url::base() . '/estudiantes/ficha?documento=' . urlencode($documento));
            exit;
        }

        $_SESSION['estudiante_guardado'] = 'Se borró a ' . $ficha['nombre'] . ' ' . $ficha['apellido'] . '. Sus acudientes siguen registrados.';
        header('Location: ' . \App\Core\Url::base() . '/estudiantes');
        exit;
    }

    //Responde en JSON si un acudiente ya existe (lo usa el formulario al buscar)
    public function buscarAcudiente()
    {
        Auth::requireRole('administrador');

        header('Content-Type: application/json; charset=utf-8');

        $documento = trim($_GET['documento'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $documento)) {
            echo json_encode(['encontrado' => false]);
            return;
        }

        $acudiente = (new Acudiente($this->conectar()))->buscarPorDocumento($documento);

        if (!$acudiente) {
            echo json_encode(['encontrado' => false]);
            return;
        }

        echo json_encode([
            'encontrado' => true,
            'nombre' => $acudiente['nombre_acu'] . ' ' . $acudiente['apellido_acu'],
            'telefono' => $acudiente['telefono'],
            'estudiantes' => (int) $acudiente['estudiantes'],
        ]);
    }

    //Revisa los datos personales y de transporte (los usan guardar() y actualizar()).
    //Devuelve [edad, curso, parada].
    private function validarDatos(array $datos, Estudiante $modeloEstudiante, array &$errores): array
    {
        if (!isset(self::TIPOS_DOCUMENTO[$datos['tipo_documento'] ?? ''])) {
            $errores['tipo_documento'] = 'Elige el tipo de documento.';
        }

        foreach (['nombre' => 'el primer nombre', 'apellido' => 'el primer apellido'] as $campo => $texto) {
            if (($datos[$campo] ?? '') === '') {
                $errores[$campo] = 'Escribe ' . $texto . '.';
            }
        }
        foreach (['nombre', 'segundo_nombre', 'apellido', 'segundo_apellido'] as $campo) {
            if (mb_strlen($datos[$campo] ?? '') > 45) {
                $errores[$campo] = 'Máximo 45 caracteres.';
            }
        }

        $fecha = DateTime::createFromFormat('!Y-m-d', $datos['fecha_nacimiento'] ?? '');
        $edad = null;
        if (!$fecha || $fecha->format('Y-m-d') !== ($datos['fecha_nacimiento'] ?? '')) {
            $errores['fecha_nacimiento'] = 'Escribe una fecha de nacimiento válida.';
        } else {
            $hoy = new DateTime('today');
            $edad = $fecha->diff($hoy)->y;
            if ($fecha > $hoy || $edad > 25) {
                $errores['fecha_nacimiento'] = 'Revisa la fecha de nacimiento.';
            }
        }

        $curso = filter_var($datos['curso'] ?? '', FILTER_VALIDATE_INT);
        if (!$curso || !$modeloEstudiante->existeEnCatalogo('curso', $curso)) {
            $errores['curso'] = 'Elige un curso.';
        }

        $parada = filter_var($datos['parada'] ?? '', FILTER_VALIDATE_INT);
        if (!$parada || !$modeloEstudiante->existeEnCatalogo('parada', $parada)) {
            $errores['parada'] = 'Elige la ruta y la parada.';
        }

        return [$edad, $curso, $parada];
    }

    //Revisa los datos de un acudiente (principal o suplente).
    //Si el documento ya existe se reutiliza; si no, se pide nombre y apellido.
    private function validarAcudiente(string $prefijo, bool $obligatorio, array $datos, array $parentescos, Acudiente $modelo, array &$errores): ?array
    {
        $documento = $datos[$prefijo . '_documento'] ?? '';
        $nombreCampo = $prefijo === 'principal' ? 'acudiente principal' : 'acudiente suplente';

        if ($documento === '') {
            if ($obligatorio) {
                $errores[$prefijo] = 'Escribe el documento del ' . $nombreCampo . '.';
            }
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $documento)) {
            $errores[$prefijo] = 'El documento del ' . $nombreCampo . ' debe tener entre 4 y 20 letras o números.';
            return null;
        }

        $parentesco = filter_var($datos[$prefijo . '_parentesco'] ?? '', FILTER_VALIDATE_INT);
        if (!$parentesco || !in_array($parentesco, $parentescos)) {
            $errores[$prefijo] = 'Elige el parentesco del ' . $nombreCampo . '.';
            return null;
        }

        $acudiente = [
            'documento' => $documento,
            'parentesco' => $parentesco,
            'nuevo' => false,
        ];

        if ($modelo->buscarPorDocumento($documento)) {
            return $acudiente;
        }

        //No existe: hay que crearlo
        $nombre = $datos[$prefijo . '_nombre'] ?? '';
        $apellido = $datos[$prefijo . '_apellido'] ?? '';
        $telefono = $datos[$prefijo . '_telefono'] ?? '';

        if ($nombre === '' || $apellido === '' || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
            $errores[$prefijo] = 'Escribe el nombre y el apellido del ' . $nombreCampo . '.';
            return null;
        }
        if ($telefono !== '' && !preg_match('/^[0-9 +()-]{7,20}$/', $telefono)) {
            $errores[$prefijo] = 'Revisa el teléfono del ' . $nombreCampo . '.';
            return null;
        }

        return array_merge($acudiente, [
            'nuevo' => true,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono === '' ? null : $telefono,
        ]);
    }

    //Vuelve al formulario mostrando los errores y lo que se había escrito
    private function volverAlFormulario(array $errores, array $datos): void
    {
        $_SESSION['nuevo_estudiante'] = ['errores' => $errores, 'datos' => $datos];
        header('Location: ' . \App\Core\Url::base() . '/estudiantes/nuevo');
        exit;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
