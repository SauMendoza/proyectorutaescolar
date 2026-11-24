<?php

namespace App\Models;

use PDO;

class Estudiante
{
    //Atributo PDO
    private PDO $conexion;

    //Constructor que recibe un objeto PDO
    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }


    public function obtenerTodos(): array
    {
        $sql = 'SELECT *
                FROM estudiante
                LIMIT 5';
        //Variable que almacena la consulta que se hace a través de la conexión
        $consulta = $this->conexion->query($sql);
        //con fetcheAll() obtenemos todo lo que tiene la consulta sql como un array
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    //Llama a la vista SQL vista_panel_asistencia_diaria (database/vistas_rutas.sql)
    //Trae cada estudiante con su curso, parada, ruta, acudiente y las horas de hoy
    public function obtenerPanelAsistencia(): array
    {
        $sql = 'SELECT *
                FROM vista_panel_asistencia_diaria
                ORDER BY ruta, estudiante';
        $consulta = $this->conexion->query($sql);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    //Calcula las estadísticas de hoy a partir de la misma vista
    //Se usa DISTINCT para contar cada estudiante una sola vez
    public function obtenerEstadisticas(): array
    {
        $sql = 'SELECT
                    COUNT(DISTINCT documento) AS total,
                    COUNT(DISTINCT CASE WHEN hora_ida IS NOT NULL THEN documento END) AS ida,
                    COUNT(DISTINCT CASE WHEN hora_regreso IS NOT NULL THEN documento END) AS regreso
                FROM vista_panel_asistencia_diaria';
        $consulta = $this->conexion->query($sql);
        $estadisticas = $consulta->fetch(PDO::FETCH_ASSOC);

        //Estudiantes que todavía no tienen acudiente suplente
        $sql = "SELECT COUNT(*)
                FROM estudiante e
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM acudiente_estudiante ae
                    WHERE ae.fk_num_doc_estu = e.num_doc_estu
                      AND ae.tipo_acudiente = 'SUPLENTE'
                )";
        $estadisticas['sin_suplente'] = (int) $this->conexion->query($sql)->fetchColumn();

        //Estudiantes sin acudiente principal (para "Para atender" del panel)
        $sql = "SELECT COUNT(*)
                FROM estudiante e
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM acudiente_estudiante ae
                    WHERE ae.fk_num_doc_estu = e.num_doc_estu
                      AND ae.tipo_acudiente = 'PRINCIPAL'
                )";
        $estadisticas['sin_principal'] = (int) $this->conexion->query($sql)->fetchColumn();

        return $estadisticas;
    }

    //Indica si ya hay un estudiante con ese documento
    public function existe(string $documento): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT 1 FROM estudiante WHERE num_doc_estu = :documento'
        );
        $consulta->execute([':documento' => $documento]);
        return (bool) $consulta->fetchColumn();
    }

    //Cursos para el formulario de nuevo estudiante
    public function obtenerCursos(): array
    {
        $sql = 'SELECT id_curso, nombre FROM curso ORDER BY id_curso';
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    //Rutas con el nombre de su conductor
    public function obtenerRutas(): array
    {
        $sql = "SELECT r.id_ruta, r.nombre,
                       CONCAT_WS(' ', c.nombres, c.apellidos) AS conductor
                FROM ruta r
                INNER JOIN conductor c ON r.fk_num_doc_conductor = c.num_doc_conductor
                ORDER BY r.id_ruta";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    //Paradas ordenadas por recorrido, con la ruta a la que pertenecen
    public function obtenerParadas(): array
    {
        $sql = 'SELECT id_parada, nombre, comunidad, fk_id_ruta
                FROM parada
                ORDER BY fk_id_ruta, km_referencia';
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    //Indica si existe un registro con ese id en curso o parada
    public function existeEnCatalogo(string $tabla, int $id): bool
    {
        //Solo se permiten estas dos tablas para no armar SQL con texto libre
        $columnas = ['curso' => 'id_curso', 'parada' => 'id_parada'];
        $columna = $columnas[$tabla];

        $consulta = $this->conexion->prepare("SELECT 1 FROM $tabla WHERE $columna = :id");
        $consulta->execute([':id' => $id]);
        return (bool) $consulta->fetchColumn();
    }

    //Inserta el estudiante con los campos del esquema ruta_escolar_v3
    public function crear(array $datos): void
    {
        $sql = 'INSERT INTO estudiante
                    (num_doc_estu, tipo_doc_estu, nombre, segundo_nombre, apellido,
                     segundo_apellido, fecha_nacimiento, edad, fk_id_curso, fk_id_parada)
                VALUES
                    (:documento, :tipo, :nombre, :segundo_nombre, :apellido,
                     :segundo_apellido, :fecha, :edad, :curso, :parada)';

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':documento' => $datos['documento'],
            ':tipo' => $datos['tipo_documento'],
            ':nombre' => $datos['nombre'],
            ':segundo_nombre' => $datos['segundo_nombre'] ?: null,
            ':apellido' => $datos['apellido'],
            ':segundo_apellido' => $datos['segundo_apellido'] ?: null,
            ':fecha' => $datos['fecha_nacimiento'],
            ':edad' => $datos['edad'],
            ':curso' => $datos['curso'],
            ':parada' => $datos['parada'],
        ]);
    }

    //Cambia los datos de un estudiante. El documento no se cambia: es la llave de sus
    //acudientes y asistencias, y es lo que lleva el QR de su carné.
    public function actualizar(string $documento, array $datos): void
    {
        $sql = 'UPDATE estudiante
                SET tipo_doc_estu = :tipo, nombre = :nombre, segundo_nombre = :segundo_nombre,
                    apellido = :apellido, segundo_apellido = :segundo_apellido,
                    fecha_nacimiento = :fecha, edad = :edad, fk_id_curso = :curso, fk_id_parada = :parada
                WHERE num_doc_estu = :documento';

        $this->conexion->prepare($sql)->execute([
            ':tipo' => $datos['tipo_documento'],
            ':nombre' => $datos['nombre'],
            ':segundo_nombre' => $datos['segundo_nombre'] ?: null,
            ':apellido' => $datos['apellido'],
            ':segundo_apellido' => $datos['segundo_apellido'] ?: null,
            ':fecha' => $datos['fecha_nacimiento'],
            ':edad' => $datos['edad'],
            ':curso' => $datos['curso'],
            ':parada' => $datos['parada'],
            ':documento' => $documento,
        ]);
    }

    //Cuántas asistencias tiene (se avisa antes de borrarlo)
    public function contarAsistencias(string $documento): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM asistencia WHERE fk_num_doc_estu = :documento');
        $consulta->execute([':documento' => $documento]);
        return (int) $consulta->fetchColumn();
    }

    //Borra un estudiante en una transacción: primero sus asistencias y sus vínculos con
    //acudientes (así no quedan filas sueltas) y después el estudiante.
    //Los acudientes NO se borran: pueden representar a otros estudiantes.
    public function eliminar(string $documento): void
    {
        $this->conexion->beginTransaction();
        try {
            foreach ([
                'DELETE FROM asistencia WHERE fk_num_doc_estu = :documento',
                'DELETE FROM acudiente_estudiante WHERE fk_num_doc_estu = :documento',
                'DELETE FROM estudiante WHERE num_doc_estu = :documento',
            ] as $sql) {
                $this->conexion->prepare($sql)->execute([':documento' => $documento]);
            }
            $this->conexion->commit();
        } catch (\Throwable $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    //Datos completos de un estudiante para su ficha: curso, parada, ruta y conductor
    public function obtenerFicha(string $documento): ?array
    {
        $sql = "SELECT e.num_doc_estu, e.tipo_doc_estu, e.nombre, e.segundo_nombre,
                       e.apellido, e.segundo_apellido, e.fecha_nacimiento, e.edad,
                       c.id_curso, c.nombre AS curso,
                       p.id_parada, p.nombre AS parada, p.comunidad, p.km_referencia,
                       r.id_ruta, r.nombre AS ruta,
                       CONCAT_WS(' ', co.nombres, co.apellidos) AS conductor,
                       co.telefono AS telefono_conductor
                FROM estudiante e
                INNER JOIN curso c ON c.id_curso = e.fk_id_curso
                INNER JOIN parada p ON p.id_parada = e.fk_id_parada
                INNER JOIN ruta r ON r.id_ruta = p.fk_id_ruta
                INNER JOIN conductor co ON co.num_doc_conductor = r.fk_num_doc_conductor
                WHERE e.num_doc_estu = :documento";

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':documento' => $documento]);
        $ficha = $consulta->fetch(PDO::FETCH_ASSOC);

        return $ficha ?: null;
    }
}
