<?php

namespace App\Models;

use PDO;

// Consultas de asistencia y jornada (quién subió al bus, a qué hora y quién lo registró).
// Una jornada es un recorrido (IDA o REGRESO) de una ruta en una fecha, hecho por un usuario.
class Asistencia
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // Estudiantes de una ruta con sus registros de ida y regreso en una fecha.
    // También trae el acudiente principal para poder llamarlo.
    // es_admin_ida / es_admin_regreso = 1 cuando el registro lo hizo la administración (manual).
    // Usa un CTE (registros): los registros del día se leen una vez y se unen dos veces (ida y regreso).
    public function obtenerDeRuta(int $idRuta, string $fecha): array
    {
        $sql = "WITH registros AS (
                    SELECT a.id_asistencia, a.fk_num_doc_estu, a.hora, j.tipo_recorrido, u.id_usuario,
                           CONCAT(u.nombre, ' ', u.apellido) AS usuario,
                           EXISTS (SELECT 1 FROM administrador ad WHERE ad.fk_id_usuario = u.id_usuario) AS es_admin
                    FROM asistencia a
                    INNER JOIN jornada j ON j.id_jornada = a.fk_jornada
                    INNER JOIN usuario u ON u.id_usuario = j.fk_id_usuario
                    WHERE a.estado = 'REGISTRADO' AND j.fecha = :fecha
                )
                SELECT e.num_doc_estu AS documento, e.tipo_doc_estu AS tipo_documento,
                       CONCAT_WS(' ', e.nombre, e.apellido) AS estudiante,
                       c.nombre AS curso, p.id_parada, p.nombre AS parada, p.km_referencia,
                       CONCAT_WS(' ', acu.nombre_acu, acu.apellido_acu) AS acudiente,
                       acu.telefono AS telefono_acudiente, pa.nombre AS parentesco,
                       ida.id_asistencia AS id_ida, ida.hora AS hora_ida,
                       ida.usuario AS usuario_ida, ida.es_admin AS es_admin_ida, ida.id_usuario AS id_usuario_ida,
                       reg.id_asistencia AS id_regreso, reg.hora AS hora_regreso,
                       reg.usuario AS usuario_regreso, reg.es_admin AS es_admin_regreso, reg.id_usuario AS id_usuario_regreso
                FROM estudiante e
                INNER JOIN curso c ON c.id_curso = e.fk_id_curso
                INNER JOIN parada p ON p.id_parada = e.fk_id_parada
                LEFT JOIN acudiente_estudiante ae
                       ON ae.fk_num_doc_estu = e.num_doc_estu AND ae.tipo_acudiente = 'PRINCIPAL'
                LEFT JOIN acudiente acu ON acu.num_doc_acu = ae.fk_num_doc_acu
                LEFT JOIN parentesco pa ON pa.id_parentesco = ae.fk_id_parentesco
                LEFT JOIN registros ida
                       ON ida.fk_num_doc_estu = e.num_doc_estu AND ida.tipo_recorrido = 'IDA'
                LEFT JOIN registros reg
                       ON reg.fk_num_doc_estu = e.num_doc_estu AND reg.tipo_recorrido = 'REGRESO'
                WHERE p.fk_id_ruta = :ruta
                ORDER BY p.km_referencia, p.id_parada, e.apellido, e.nombre";

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':fecha' => $fecha, ':ruta' => $idRuta]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Porcentaje de estudiantes que subieron (IDA) y bajaron (REGRESO) de lunes a viernes.
    // Lo calcula el procedimiento sp_resumen_semana (CTE recursivo + fn_porcentaje_asistencia).
    // $idRuta null = todas las rutas. Devuelve [fecha => ['IDA' => %, 'REGRESO' => %]].
    public function porcentajeSemana(string $lunes, ?int $idRuta = null): array
    {
        $consulta = $this->conexion->prepare('CALL sp_resumen_semana(:lunes, :ruta)');
        $consulta->execute([':lunes' => $lunes, ':ruta' => $idRuta]);

        $dias = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $dias[$fila['fecha']] = ['IDA' => (int) $fila['ida'], 'REGRESO' => (int) $fila['regreso']];
        }
        $consulta->closeCursor();
        return $dias;
    }

    // Nombre de los auxiliares de una ruta (para el encabezado de Asistencias).
    public function obtenerAuxiliaresDeRuta(int $idRuta): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT CONCAT(u.nombre, ' ', u.apellido)
             FROM auxiliar a
             INNER JOIN usuario u ON u.id_usuario = a.fk_id_usuario
             WHERE a.fk_id_ruta = :ruta"
        );
        $consulta->execute([':ruta' => $idRuta]);
        return $consulta->fetchAll(PDO::FETCH_COLUMN);
    }

    // Registros de un estudiante entre dos fechas (para el calendario de su ficha).
    public function obtenerDelEstudiante(string $documento, string $desde, string $hasta): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT j.fecha, j.tipo_recorrido, a.hora
             FROM asistencia a
             INNER JOIN jornada j ON j.id_jornada = a.fk_jornada
             WHERE a.fk_num_doc_estu = :documento AND a.estado = 'REGISTRADO'
               AND j.fecha BETWEEN :desde AND :hasta"
        );
        $consulta->execute([':documento' => $documento, ':desde' => $desde, ':hasta' => $hasta]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Últimos registros de un estudiante con el usuario que los hizo.
    public function obtenerUltimosDelEstudiante(string $documento, int $limite = 6): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT j.fecha, j.tipo_recorrido, a.hora,
                    CONCAT(u.nombre, ' ', u.apellido) AS usuario,
                    EXISTS (SELECT 1 FROM administrador ad WHERE ad.fk_id_usuario = u.id_usuario) AS es_admin
             FROM asistencia a
             INNER JOIN jornada j ON j.id_jornada = a.fk_jornada
             INNER JOIN usuario u ON u.id_usuario = j.fk_id_usuario
             WHERE a.fk_num_doc_estu = :documento AND a.estado = 'REGISTRADO'
             ORDER BY j.fecha DESC, a.hora DESC
             LIMIT " . (int) $limite
        );
        $consulta->execute([':documento' => $documento]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Guarda la subida (IDA) o bajada (REGRESO) del estudiante con el procedimiento
    // sp_registrar_asistencia: en una transacción busca o crea la jornada del usuario y guarda
    // la asistencia. Si el estudiante ya tenía registro de ese recorrido ese día, no guarda nada.
    // Devuelve ['resultado' => registrado | ya_registrado | desconocido, 'hora' => 'HH:MM:SS' | null].
    public function registrar(string $documento, int $idUsuario, string $tipo, string $fecha, string $hora): array
    {
        $consulta = $this->conexion->prepare('CALL sp_registrar_asistencia(:documento, :usuario, :tipo, :fecha, :hora)');
        $consulta->execute([
            ':documento' => $documento,
            ':usuario' => $idUsuario,
            ':tipo' => $tipo,
            ':fecha' => $fecha,
            ':hora' => $hora,
        ]);

        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();
        return $fila;
    }

    // Hora en que se cerró un recorrido (HH:MM) o null si sigue abierto.
    public function horaCierre(int $idRuta, string $tipo, string $fecha): ?string
    {
        $consulta = $this->conexion->prepare(
            'SELECT MAX(hora_cierre) FROM jornada
             WHERE fk_id_ruta = :ruta AND tipo_recorrido = :tipo AND fecha = :fecha'
        );
        $consulta->execute([':ruta' => $idRuta, ':tipo' => $tipo, ':fecha' => $fecha]);
        $hora = $consulta->fetchColumn();
        return $hora ? substr($hora, 0, 5) : null;
    }

    // Cierra el recorrido de la ruta en esa fecha. Si nadie subió todavía no hay jornada: se crea.
    public function cerrarRecorrido(int $idRuta, int $idUsuario, string $tipo, string $fecha, string $hora): void
    {
        if ($this->horaCierre($idRuta, $tipo, $fecha) !== null) {
            return;
        }

        $consulta = $this->conexion->prepare(
            'UPDATE jornada SET hora_cierre = :hora
             WHERE fk_id_ruta = :ruta AND tipo_recorrido = :tipo AND fecha = :fecha'
        );
        $consulta->execute([':hora' => $hora, ':ruta' => $idRuta, ':tipo' => $tipo, ':fecha' => $fecha]);

        if ($consulta->rowCount() === 0) {
            $this->conexion->prepare(
                'INSERT INTO jornada (fk_id_ruta, fk_id_usuario, tipo_recorrido, fecha, hora_cierre)
                 VALUES (:ruta, :usuario, :tipo, :fecha, :hora)'
            )->execute([':ruta' => $idRuta, ':usuario' => $idUsuario, ':tipo' => $tipo, ':fecha' => $fecha, ':hora' => $hora]);
        }
    }

    // Reabre un recorrido (administración).
    public function reabrirRecorrido(int $idRuta, string $tipo, string $fecha): void
    {
        $this->conexion->prepare(
            'UPDATE jornada SET hora_cierre = NULL
             WHERE fk_id_ruta = :ruta AND tipo_recorrido = :tipo AND fecha = :fecha'
        )->execute([':ruta' => $idRuta, ':tipo' => $tipo, ':fecha' => $fecha]);
    }

    // Borra un registro hecho por error. Solo lo puede borrar quien lo hizo y solo el mismo día.
    public function deshacer(string $documento, string $tipo, string $fecha, int $idUsuario): void
    {
        $consulta = $this->conexion->prepare(
            'DELETE a
             FROM asistencia a
             INNER JOIN jornada j ON j.id_jornada = a.fk_jornada
             WHERE a.fk_num_doc_estu = :documento AND j.tipo_recorrido = :tipo
               AND j.fecha = :fecha AND j.fk_id_usuario = :usuario'
        );
        $consulta->execute([':documento' => $documento, ':tipo' => $tipo, ':fecha' => $fecha, ':usuario' => $idUsuario]);
    }
}
