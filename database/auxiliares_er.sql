-- Cambios para el módulo "Auxiliares" (usuarios auxiliares).
-- 1) Un auxiliar puede quedar sin ruta ("Ninguna"): fk_id_ruta pasa a aceptar NULL.
-- 2) usuario.ultimo_ingreso guarda la fecha y hora del último inicio de sesión ("Entró hoy 06:02").
-- 3) usuario.cambiar_contrasena = 1 obliga a poner una contraseña propia en el próximo ingreso.
-- Ejecutar una sola vez sobre la base ruta_escolar_v3 (después de campos_usuario_er.sql).

USE ruta_escolar_v3;

ALTER TABLE auxiliar
    MODIFY fk_id_ruta INT(11) NULL;

ALTER TABLE usuario
    ADD COLUMN ultimo_ingreso DATETIME NULL AFTER fecha_ingreso,
    ADD COLUMN cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0 AFTER contrasena;
