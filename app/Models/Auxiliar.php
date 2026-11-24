<?php

namespace App\Models;

use PDO;

// Usuarios auxiliares: lista con su ruta y su último ingreso, y registro de cuentas nuevas.
// La cuenta queda en dos tablas: usuario (datos y contraseña cifrada) y auxiliar (ruta asignada).
class Auxiliar
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // Auxiliares con su ruta (null = sin ruta) y la fecha y hora de su último ingreso (vista vista_auxiliares).
    public function obtenerTodos(): array
    {
        $sql = 'SELECT * FROM vista_auxiliares ORDER BY nombre, apellido';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Rutas existentes para elegir la ruta asignada.
    public function obtenerRutas(): array
    {
        $sql = 'SELECT id_ruta, nombre FROM ruta ORDER BY id_ruta';

        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Indica si un correo ya lo usa otro usuario (administrador o auxiliar).
    public function existeCorreo(string $correo): bool
    {
        $consulta = $this->conexion->prepare('SELECT 1 FROM usuario WHERE correo = :correo');
        $consulta->execute([':correo' => $correo]);
        return (bool) $consulta->fetchColumn();
    }

    // Indica si un documento ya lo usa otro usuario.
    public function existeDocumento(string $documento): bool
    {
        $consulta = $this->conexion->prepare('SELECT 1 FROM usuario WHERE dni = :dni');
        $consulta->execute([':dni' => $documento]);
        return (bool) $consulta->fetchColumn();
    }

    // Pone una contraseña nueva (cifrada) a un auxiliar. La actual no se lee ni se muestra.
    // El JOIN con auxiliar impide cambiar la de un administrador. Devuelve false si no es un auxiliar.
    public function cambiarContrasena(int $idUsuario, string $contrasena, bool $pedirCambio): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuario u
             INNER JOIN auxiliar a ON a.fk_id_usuario = u.id_usuario
             SET u.contrasena = :hash, u.cambiar_contrasena = :cambiar
             WHERE u.id_usuario = :id'
        );
        $consulta->execute([
            ':hash' => password_hash($contrasena, PASSWORD_DEFAULT),
            ':cambiar' => $pedirCambio ? 1 : 0,
            ':id' => $idUsuario,
        ]);
        return $consulta->rowCount() > 0;
    }

    // Crea la cuenta con el procedimiento sp_crear_auxiliar: en una transacción guarda el usuario
    // y luego su fila en auxiliar. Si algo falla, no queda nada guardado (y PHP recibe el error).
    // La contraseña se cifra aquí antes de enviarla.
    // $datos: nombre, apellido, documento, telefono, fecha_ingreso, correo, contrasena,
    //         cambiar_contrasena (bool), id_ruta (null = sin ruta)
    public function registrar(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'CALL sp_crear_auxiliar(:dni, :nombre, :apellido, :correo, :telefono, :fecha, :contrasena, :cambiar, :ruta)'
        );
        $consulta->execute([
            ':dni' => $datos['documento'],
            ':nombre' => $datos['nombre'],
            ':apellido' => $datos['apellido'],
            ':correo' => $datos['correo'],
            ':telefono' => $datos['telefono'],
            ':fecha' => $datos['fecha_ingreso'],
            ':contrasena' => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
            ':cambiar' => $datos['cambiar_contrasena'] ? 1 : 0,
            ':ruta' => $datos['id_ruta'],
        ]);

        $idUsuario = (int) $consulta->fetchColumn();
        $consulta->closeCursor();
        return $idUsuario;
    }
}
