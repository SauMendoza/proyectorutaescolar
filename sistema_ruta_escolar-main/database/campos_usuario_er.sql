-- Campos del diagrama E-R que faltaban en la tabla usuario de ruta_escolar_v3.
-- Se agregan como opcionales (NULL) para no romper los usuarios que ya existen
-- ni el registro de auxiliares, que todavía no los pide.
-- Ejecutar una sola vez sobre la base ruta_escolar_v3.

USE ruta_escolar_v3;

ALTER TABLE usuario
    ADD COLUMN dni VARCHAR(20) NULL UNIQUE AFTER id_usuario,
    ADD COLUMN telefono VARCHAR(20) NULL AFTER correo,
    ADD COLUMN salario DECIMAL(12,2) NULL AFTER telefono,
    ADD COLUMN fecha_ingreso DATE NULL AFTER salario;
