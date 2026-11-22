<?php

namespace App\Models;

use PDO;

// Consultas de rutas, paradas, conductores y auxiliares (tablas ruta, parada,
// conductor y auxiliar de ruta_escolar_v3).
class Ruta
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // Rutas con su conductor, sus auxiliares y cuántos estudiantes llevan.
    // Si se pasa $idRuta solo trae esa (la "Mi ruta" del auxiliar).
    public function obtenerTodas(?int $idRuta = null): array
    {
        $sql = "SELECT r.id_ruta, r.nombre,
                       co.num_doc_conductor, co.nombres AS conductor_nombres,
                       co.apellidos AS conductor_apellidos, co.telefono AS conductor_telefono,
                       (SELECT GROUP_CONCAT(CONCAT(u.nombre, ' ', u.apellido) SEPARATOR ', ')
                        FROM auxiliar a
                        INNER JOIN usuario u ON u.id_usuario = a.fk_id_usuario
                        WHERE a.fk_id_ruta = r.id_ruta) AS auxiliares,
                       (SELECT COUNT(*)
                        FROM estudiante e
                        INNER JOIN parada p ON p.id_parada = e.fk_id_parada
                        WHERE p.fk_id_ruta = r.id_ruta) AS estudiantes
                FROM ruta r
                INNER JOIN conductor co ON co.num_doc_conductor = r.fk_num_doc_conductor";

        if ($idRuta === null) {
            return $this->conexion->query($sql . ' ORDER BY r.id_ruta')->fetchAll(PDO::FETCH_ASSOC);
        }

        $consulta = $this->conexion->prepare($sql . ' WHERE r.id_ruta = :ruta');
        $consulta->execute([':ruta' => $idRuta]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    // Una ruta por id (o null si no existe).
    public function obtenerPorId(int $idRuta): ?array
    {
        $rutas = $this->obtenerTodas($idRuta);
        return $rutas[0] ?? null;
    }

    // Paradas de todas las rutas en orden de recorrido (km), con sus estudiantes.
    // Se devuelven agrupadas por id de ruta: [id_ruta => [parada, parada...]].
    public function obtenerParadasPorRuta(): array
    {
        $sql = 'SELECT p.id_parada, p.nombre, p.comunidad, p.km_referencia, p.fk_id_ruta,
                       COUNT(e.num_doc_estu) AS estudiantes
                FROM parada p
                LEFT JOIN estudiante e ON e.fk_id_parada = p.id_parada
                GROUP BY p.id_parada, p.nombre, p.comunidad, p.km_referencia, p.fk_id_ruta
                ORDER BY p.fk_id_ruta, p.km_referencia, p.id_parada';

        $agrupadas = [];
        foreach ($this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $parada) {
            $agrupadas[$parada['fk_id_ruta']][] = $parada;
        }
        return $agrupadas;
    }

    // Paradas de una sola ruta, en orden de recorrido.
    public function obtenerParadas(int $idRuta): array
    {
        return $this->obtenerParadasPorRuta()[$idRuta] ?? [];
    }

    // Conductores con la ruta que manejan (null si están libres).
    public function obtenerConductores(): array
    {
        $sql = 'SELECT co.num_doc_conductor, co.nombres, co.apellidos, co.telefono, r.id_ruta
                FROM conductor co
                LEFT JOIN ruta r ON r.fk_num_doc_conductor = co.num_doc_conductor
                ORDER BY co.nombres, co.apellidos';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Usuarios auxiliares con la ruta que tienen asignada.
    public function obtenerAuxiliares(): array
    {
        $sql = 'SELECT u.id_usuario, u.nombre, u.apellido, a.fk_id_ruta
                FROM auxiliar a
                INNER JOIN usuario u ON u.id_usuario = a.fk_id_usuario
                ORDER BY u.nombre, u.apellido';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Indica si otra ruta ya usa ese nombre.
    public function existeNombre(string $nombre, ?int $exceptoRuta = null): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT 1 FROM ruta WHERE nombre = :nombre AND id_ruta <> :ruta'
        );
        $consulta->execute([':nombre' => $nombre, ':ruta' => $exceptoRuta ?? 0]);
        return (bool) $consulta->fetchColumn();
    }

    // Indica si ya hay un conductor con ese documento.
    public function existeConductor(string $documento): bool
    {
        $consulta = $this->conexion->prepare('SELECT 1 FROM conductor WHERE num_doc_conductor = :documento');
        $consulta->execute([':documento' => $documento]);
        return (bool) $consulta->fetchColumn();
    }

    // Registra un conductor nuevo.
    public function crearConductor(string $documento, string $nombres, string $apellidos, ?string $telefono): void
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO conductor (num_doc_conductor, nombres, apellidos, telefono)
             VALUES (:documento, :nombres, :apellidos, :telefono)'
        );
        $consulta->execute([
            ':documento' => $documento,
            ':nombres' => $nombres,
            ':apellidos' => $apellidos,
            ':telefono' => $telefono,
        ]);
    }

    // Crea una ruta y devuelve su id.
    public function crear(string $nombre, string $documentoConductor): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO ruta (nombre, fk_num_doc_conductor) VALUES (:nombre, :conductor)'
        );
        $consulta->execute([':nombre' => $nombre, ':conductor' => $documentoConductor]);
        return (int) $this->conexion->lastInsertId();
    }

    // Cambia el nombre y el conductor de una ruta.
    public function actualizar(int $idRuta, string $nombre, string $documentoConductor): void
    {
        $consulta = $this->conexion->prepare(
            'UPDATE ruta SET nombre = :nombre, fk_num_doc_conductor = :conductor WHERE id_ruta = :ruta'
        );
        $consulta->execute([':nombre' => $nombre, ':conductor' => $documentoConductor, ':ruta' => $idRuta]);
    }

    // Pasa un auxiliar a otra ruta (o le da una si estaba sin ruta).
    public function asignarAuxiliar(int $idUsuario, int $idRuta): void
    {
        $consulta = $this->conexion->prepare(
            'UPDATE auxiliar SET fk_id_ruta = :ruta WHERE fk_id_usuario = :usuario'
        );
        $consulta->execute([':ruta' => $idRuta, ':usuario' => $idUsuario]);
    }

    // Agrega una parada al final o en el km indicado.
    public function crearParada(int $idRuta, string $nombre, string $comunidad, ?float $km): void
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO parada (nombre, comunidad, km_referencia, fk_id_ruta)
             VALUES (:nombre, :comunidad, :km, :ruta)'
        );
        $consulta->execute([':nombre' => $nombre, ':comunidad' => $comunidad, ':km' => $km, ':ruta' => $idRuta]);
    }

    // Cambia los datos de una parada de esa ruta.
    public function actualizarParada(int $idParada, int $idRuta, string $nombre, string $comunidad, ?float $km): void
    {
        $consulta = $this->conexion->prepare(
            'UPDATE parada SET nombre = :nombre, comunidad = :comunidad, km_referencia = :km
             WHERE id_parada = :parada AND fk_id_ruta = :ruta'
        );
        $consulta->execute([
            ':nombre' => $nombre,
            ':comunidad' => $comunidad,
            ':km' => $km,
            ':parada' => $idParada,
            ':ruta' => $idRuta,
        ]);
    }

    // Quita una parada solo si ningún estudiante la usa.
    public function eliminarParada(int $idParada, int $idRuta): void
    {
        $consulta = $this->conexion->prepare(
            'DELETE FROM parada
             WHERE id_parada = :parada AND fk_id_ruta = :ruta
               AND NOT EXISTS (SELECT 1 FROM estudiante WHERE fk_id_parada = :parada2)'
        );
        $consulta->execute([':parada' => $idParada, ':ruta' => $idRuta, ':parada2' => $idParada]);
    }
}
