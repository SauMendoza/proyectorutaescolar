<?php

namespace App\Models;

use PDO;

// Consultas de la tabla curso y de los estudiantes de cada curso.
class Curso
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // Cursos con la cantidad de estudiantes (en el orden en que se crearon).
    public function obtenerTodos(): array
    {
        $sql = 'SELECT c.id_curso, c.nombre, COUNT(e.num_doc_estu) AS estudiantes
                FROM curso c
                LEFT JOIN estudiante e ON e.fk_id_curso = c.id_curso
                GROUP BY c.id_curso, c.nombre
                ORDER BY c.id_curso';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Estudiantes de un curso con su ruta, parada, conductor y acudiente principal.
    public function obtenerEstudiantes(int $idCurso): array
    {
        $sql = "SELECT e.num_doc_estu AS documento, e.tipo_doc_estu AS tipo_documento,
                       CONCAT_WS(' ', e.nombre, e.segundo_nombre, e.apellido, e.segundo_apellido) AS estudiante,
                       e.edad, p.id_parada, p.nombre AS parada, p.comunidad, p.km_referencia,
                       r.id_ruta, r.nombre AS ruta,
                       CONCAT_WS(' ', co.nombres, co.apellidos) AS conductor,
                       CONCAT_WS(' ', acu.nombre_acu, acu.apellido_acu) AS acudiente,
                       acu.telefono AS telefono_acudiente, pa.nombre AS parentesco
                FROM estudiante e
                INNER JOIN parada p ON p.id_parada = e.fk_id_parada
                INNER JOIN ruta r ON r.id_ruta = p.fk_id_ruta
                INNER JOIN conductor co ON co.num_doc_conductor = r.fk_num_doc_conductor
                LEFT JOIN acudiente_estudiante ae
                       ON ae.fk_num_doc_estu = e.num_doc_estu AND ae.tipo_acudiente = 'PRINCIPAL'
                LEFT JOIN acudiente acu ON acu.num_doc_acu = ae.fk_num_doc_acu
                LEFT JOIN parentesco pa ON pa.id_parentesco = ae.fk_id_parentesco
                WHERE e.fk_id_curso = :curso
                ORDER BY e.apellido, e.nombre";

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':curso' => $idCurso]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Indica si ya existe un curso con ese nombre (sin importar mayúsculas).
    public function existeNombre(string $nombre): bool
    {
        $consulta = $this->conexion->prepare('SELECT 1 FROM curso WHERE LOWER(nombre) = LOWER(:nombre)');
        $consulta->execute([':nombre' => $nombre]);
        return (bool) $consulta->fetchColumn();
    }

    // Crea un curso y devuelve su id.
    public function crear(string $nombre): int
    {
        $consulta = $this->conexion->prepare('INSERT INTO curso (nombre) VALUES (:nombre)');
        $consulta->execute([':nombre' => $nombre]);
        return (int) $this->conexion->lastInsertId();
    }
}
