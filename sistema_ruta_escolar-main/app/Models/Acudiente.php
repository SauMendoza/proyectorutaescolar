<?php

namespace App\Models;

use PDO;

class Acudiente
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // Busca un acudiente por documento y cuenta cuántos estudiantes representa.
    // Devuelve null si no existe.
    public function buscarPorDocumento(string $documento): ?array
    {
        $sql = 'SELECT a.num_doc_acu, a.nombre_acu, a.apellido_acu, a.telefono,
                       COUNT(ae.fk_num_doc_estu) AS estudiantes
                FROM acudiente a
                LEFT JOIN acudiente_estudiante ae ON ae.fk_num_doc_acu = a.num_doc_acu
                WHERE a.num_doc_acu = :documento
                GROUP BY a.num_doc_acu, a.nombre_acu, a.apellido_acu, a.telefono';

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':documento' => $documento]);
        $acudiente = $consulta->fetch(PDO::FETCH_ASSOC);

        return $acudiente ?: null;
    }

    // Catálogo de parentescos (Padre, Madre, Abuelo...) para el formulario.
    public function obtenerParentescos(): array
    {
        $sql = 'SELECT id_parentesco, nombre
                FROM parentesco
                ORDER BY id_parentesco';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Registra un acudiente nuevo en la tabla acudiente.
    public function crear(string $documento, string $nombre, string $apellido, ?string $telefono): void
    {
        $sql = 'INSERT INTO acudiente (num_doc_acu, nombre_acu, apellido_acu, telefono)
                VALUES (:documento, :nombre, :apellido, :telefono)';

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':documento' => $documento,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => $telefono,
        ]);
    }

    // Une el acudiente con el estudiante en acudiente_estudiante.
    // $tipo es PRINCIPAL o SUPLENTE.
    public function asignar(string $documentoAcudiente, string $documentoEstudiante, int $idParentesco, string $tipo): void
    {
        $sql = 'INSERT INTO acudiente_estudiante
                    (fk_num_doc_acu, fk_num_doc_estu, fk_id_parentesco, tipo_acudiente)
                VALUES (:acudiente, :estudiante, :parentesco, :tipo)';

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([
            ':acudiente' => $documentoAcudiente,
            ':estudiante' => $documentoEstudiante,
            ':parentesco' => $idParentesco,
            ':tipo' => $tipo,
        ]);
    }

    // Acudientes de un estudiante (principal primero) con su parentesco.
    // "otros" cuenta a cuántos estudiantes más representa.
    public function obtenerDeEstudiante(string $documentoEstudiante): array
    {
        $sql = 'SELECT a.num_doc_acu, a.nombre_acu, a.apellido_acu, a.telefono,
                       ae.tipo_acudiente, pa.nombre AS parentesco,
                       (SELECT COUNT(*) FROM acudiente_estudiante x
                        WHERE x.fk_num_doc_acu = a.num_doc_acu
                          AND x.fk_num_doc_estu <> :estudiante1) AS otros
                FROM acudiente_estudiante ae
                INNER JOIN acudiente a ON a.num_doc_acu = ae.fk_num_doc_acu
                INNER JOIN parentesco pa ON pa.id_parentesco = ae.fk_id_parentesco
                WHERE ae.fk_num_doc_estu = :estudiante2
                ORDER BY ae.tipo_acudiente';

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':estudiante1' => $documentoEstudiante, ':estudiante2' => $documentoEstudiante]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Todos los acudientes, ordenados por apellido.
    public function obtenerTodos(): array
    {
        $sql = 'SELECT num_doc_acu, nombre_acu, apellido_acu, telefono
                FROM acudiente
                ORDER BY apellido_acu, nombre_acu';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Estudiantes a cargo de cada acudiente (todas las relaciones).
    // Si se pasa un documento, solo trae las de ese acudiente.
    public function obtenerEstudiantesACargo(?string $documentoAcudiente = null): array
    {
        $sql = "SELECT ae.fk_num_doc_acu, ae.tipo_acudiente, pa.nombre AS parentesco,
                       e.num_doc_estu, e.tipo_doc_estu,
                       CONCAT_WS(' ', e.nombre, e.segundo_nombre, e.apellido, e.segundo_apellido) AS estudiante,
                       c.nombre AS curso, p.nombre AS parada, r.nombre AS ruta
                FROM acudiente_estudiante ae
                INNER JOIN parentesco pa ON pa.id_parentesco = ae.fk_id_parentesco
                INNER JOIN estudiante e ON e.num_doc_estu = ae.fk_num_doc_estu
                INNER JOIN curso c ON c.id_curso = e.fk_id_curso
                INNER JOIN parada p ON p.id_parada = e.fk_id_parada
                INNER JOIN ruta r ON r.id_ruta = p.fk_id_ruta";

        if ($documentoAcudiente === null) {
            $sql .= ' ORDER BY ae.tipo_acudiente, e.apellido';
            return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql .= ' WHERE ae.fk_num_doc_acu = :documento ORDER BY ae.tipo_acudiente, e.apellido';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':documento' => $documentoAcudiente]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Indica si ya existe un parentesco con ese nombre (sin importar mayúsculas).
    public function existeParentesco(string $nombre): bool
    {
        $consulta = $this->conexion->prepare('SELECT 1 FROM parentesco WHERE LOWER(nombre) = LOWER(:nombre)');
        $consulta->execute([':nombre' => $nombre]);
        return (bool) $consulta->fetchColumn();
    }

    // Agrega un parentesco nuevo al catálogo.
    public function crearParentesco(string $nombre): void
    {
        $consulta = $this->conexion->prepare('INSERT INTO parentesco (nombre) VALUES (:nombre)');
        $consulta->execute([':nombre' => $nombre]);
    }
}
