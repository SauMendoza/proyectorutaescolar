<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Database\Connection;
use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Ruta;
use DateTime;
use PDO;

// Panel de inicio. Cada rol ve el suyo:
// - Administrador: cifras del día, una tarjeta por ruta, "Para atender" y la semana.
// - Auxiliar: lo mismo, pero solo de su ruta y con la lista de quién falta por subir.
class HomeController
{
    private const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    private const MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                           'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function index()
    {
        Auth::requireLogin();

        $conexion = $this->conectar();
        $modeloAsistencia = new Asistencia($conexion);
        $modeloRuta = new Ruta($conexion);
        $usuario = $_SESSION['usuario'];
        $hoy = date('Y-m-d');

        //Encabezado: "Viernes 20 de noviembre · 13:05" y el saludo según la hora
        $fechaTexto = self::DIAS[(int) date('N')] . ' ' . date('j') . ' de ' . self::MESES[(int) date('n')] . ' · ' . date('H:i');
        $hora = (int) date('G');
        $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

        if ($usuario['rol'] === 'administrador') {
            $rutas = array_map(
                fn($ruta) => $this->resumenRuta($ruta, $modeloAsistencia, $hoy),
                $modeloRuta->obtenerTodas()
            );

            $total = array_sum(array_column($rutas, 'total'));
            $conIda = array_sum(array_column($rutas, 'ida'));
            $conRegreso = array_sum(array_column($rutas, 'regreso'));
            $estadisticas = (new Estudiante($conexion))->obtenerEstadisticas();
            $rutasSinAuxiliar = count(array_filter($rutas, fn($r) => !$r['auxiliares']));
            $semana = $this->semana($modeloAsistencia, null);

            require __DIR__ . '/../Views/panel/admin.php';
            return;
        }

        //Auxiliar: solo su ruta
        $ruta = $modeloRuta->obtenerPorId((int) ($usuario['id_ruta'] ?? 0));
        $resumen = $ruta ? $this->resumenRuta($ruta, $modeloAsistencia, $hoy) : null;
        $semana = $resumen ? $this->semana($modeloAsistencia, (int) $ruta['id_ruta']) : [];

        require __DIR__ . '/../Views/panel/auxiliar.php';
    }

    //Cifras de hoy de una ruta: cuántos subieron, cuántos bajaron y quién falta
    private function resumenRuta(array $ruta, Asistencia $modelo, string $hoy): array
    {
        $estudiantes = $modelo->obtenerDeRuta((int) $ruta['id_ruta'], $hoy);
        $horasIda = array_filter(array_column($estudiantes, 'hora_ida'));
        $horasRegreso = array_filter(array_column($estudiantes, 'hora_regreso'));

        //"Ruta 1 · Vía Paraguachón" se muestra como "Ruta 1" y "Vía Paraguachón"
        $partes = explode(' · ', $ruta['nombre'], 2);

        return $ruta + [
            'etiqueta' => count($partes) === 2 ? $partes[0] : 'Ruta ' . $ruta['id_ruta'],
            'titulo' => count($partes) === 2 ? $partes[1] : $ruta['nombre'],
            'total' => count($estudiantes),
            'ida' => count($horasIda),
            'regreso' => count($horasRegreso),
            'ultima_ida' => $horasIda ? substr(max($horasIda), 0, 5) : null,
            'ultima_regreso' => $horasRegreso ? substr(max($horasRegreso), 0, 5) : null,
            'faltan_ida' => array_values(array_filter($estudiantes, fn($e) => $e['hora_ida'] === null)),
            'faltan_regreso' => array_values(array_filter($estudiantes, fn($e) => $e['hora_ida'] !== null && $e['hora_regreso'] === null)),
            'sin_acudiente' => count(array_filter($estudiantes, fn($e) => !$e['acudiente'])),
        ];
    }

    //Porcentaje de estudiantes que subieron (ida) y bajaron (regreso) de lunes a viernes de esta semana
    //(los porcentajes los calcula el procedimiento sp_resumen_semana en la base de datos)
    private function semana(Asistencia $modelo, ?int $idRuta): array
    {
        $lunes = new DateTime('monday this week');
        $porcentajes = $modelo->porcentajeSemana($lunes->format('Y-m-d'), $idRuta);
        $hoy = date('Y-m-d');

        $dias = [];
        foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie'] as $i => $nombre) {
            $fecha = (clone $lunes)->modify('+' . $i . ' days')->format('Y-m-d');
            $dias[] = [
                'nombre' => $fecha === $hoy ? 'Hoy' : $nombre,
                'fecha' => $fecha,
                'ida' => $porcentajes[$fecha]['IDA'] ?? 0,
                'regreso' => $porcentajes[$fecha]['REGRESO'] ?? 0,
                'hoy' => $fecha === $hoy,
                'futuro' => $fecha > $hoy,
            ];
        }
        return $dias;
    }

    //Crea la conexión PDO con la configuración del proyecto
    private function conectar(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        return Connection::connect($config);
    }
}
