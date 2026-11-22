-- =====================================================================
-- RutaSegura · Base de datos para Azure Database for MySQL (MySQL 8)
--
-- Un solo archivo con todo lo que usa la página:
--   PARTE A. Tablas (13) según el diagrama E-R
--   PARTE B. Actualización de una base vieja (agrega lo que le falte)
--   PARTE C. Datos base: cursos y parentescos (los formularios los necesitan)
--   PARTE D. Funciones, vistas (con CTE), triggers, índices y
--            procedimientos almacenados (con transacciones y CTE recursivo)
--
-- No borra nada: se puede ejecutar sobre una base vacía, sobre una base
-- vieja del proyecto o volver a ejecutar sin perder datos.
--
-- Cómo ejecutarlo (necesita un cliente que entienda DELIMITER):
--   mysql -h <servidor>.mysql.database.azure.com -u <usuario> -p --ssl-mode=REQUIRED < ruta_escolar_azure.sql
--   o abrirlo en MySQL Workbench y ejecutar todo (rayo).
--   El editor de consultas del portal de Azure no sirve (no entiende DELIMITER).
--
-- Si sale "You do not have the SUPER privilege and binary logging is enabled":
--   Azure > servidor MySQL > Parámetros del servidor > log_bin_trust_function_creators = ON
--
-- Después: crear el primer administrador con
--   php database/crear_admin.php correo@dominio.com Nombre Apellido
-- La página pone la hora de Colombia en cada conexión (SET time_zone = '-05:00').
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ruta_escolar_v3 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ruta_escolar_v3;

-- Todo (tablas, funciones y procedimientos) con la misma intercalación. Si la base
-- se creó desde el portal con la de MySQL 8 (utf8mb4_0900_ai_ci), las funciones
-- chocarían con las columnas ("Illegal mix of collations").
ALTER DATABASE ruta_escolar_v3 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;


-- #####################################################################
-- PARTE A. TABLAS
-- #####################################################################

-- ===========================
-- RUTAS
-- ===========================

CREATE TABLE IF NOT EXISTS curso (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS conductor (
    num_doc_conductor INT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS ruta (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    fk_num_doc_conductor INT NOT NULL UNIQUE,

    CONSTRAINT fk_ruta_conductor
        FOREIGN KEY (fk_num_doc_conductor)
        REFERENCES conductor(num_doc_conductor),

    UNIQUE (id_ruta, fk_num_doc_conductor)
);

CREATE TABLE IF NOT EXISTS parada (
    id_parada INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    comunidad VARCHAR(100) NOT NULL,
    km_referencia DECIMAL(6,2),
    fk_id_ruta INT NOT NULL,

    CONSTRAINT fk_parada_ruta
        FOREIGN KEY (fk_id_ruta)
        REFERENCES ruta(id_ruta)
);

-- ===========================
-- ESTUDIANTES Y ACUDIENTES
-- ===========================

CREATE TABLE IF NOT EXISTS estudiante (
    num_doc_estu VARCHAR(20) PRIMARY KEY,
    tipo_doc_estu VARCHAR(20) NOT NULL,
    nombre VARCHAR(45) NOT NULL,
    segundo_nombre VARCHAR(45),
    apellido VARCHAR(45) NOT NULL,
    segundo_apellido VARCHAR(45),
    fecha_nacimiento DATE NOT NULL,
    edad INT NOT NULL, -- la calcula el trigger de objetos_bd.sql

    fk_id_curso INT NOT NULL,
    fk_id_parada INT NOT NULL,

    CONSTRAINT fk_estudiante_curso
        FOREIGN KEY (fk_id_curso)
        REFERENCES curso(id_curso),

    CONSTRAINT fk_estudiante_parada
        FOREIGN KEY (fk_id_parada)
        REFERENCES parada(id_parada)
);

CREATE TABLE IF NOT EXISTS acudiente (
    num_doc_acu VARCHAR(20) PRIMARY KEY,
    nombre_acu VARCHAR(100) NOT NULL,
    apellido_acu VARCHAR(100) NOT NULL,
    telefono VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS parentesco (
    id_parentesco INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS acudiente_estudiante (
    fk_num_doc_acu VARCHAR(20) NOT NULL,
    fk_num_doc_estu VARCHAR(20) NOT NULL,
    fk_id_parentesco INT NOT NULL,
    tipo_acudiente ENUM('PRINCIPAL','SUPLENTE') NOT NULL,

    PRIMARY KEY (fk_num_doc_acu, fk_num_doc_estu),

    CONSTRAINT fk_ae_acudiente
        FOREIGN KEY (fk_num_doc_acu)
        REFERENCES acudiente(num_doc_acu),

    CONSTRAINT fk_ae_estudiante
        FOREIGN KEY (fk_num_doc_estu)
        REFERENCES estudiante(num_doc_estu),

    CONSTRAINT fk_ae_parentesco
        FOREIGN KEY (fk_id_parentesco)
        REFERENCES parentesco(id_parentesco),

    -- Un estudiante solo puede tener un principal y un suplente.
    UNIQUE (fk_num_doc_estu, tipo_acudiente)
);

-- ===========================
-- USUARIOS DEL SISTEMA
-- ===========================

CREATE TABLE IF NOT EXISTS usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(20) UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    salario DECIMAL(12,2),
    fecha_ingreso DATE,
    ultimo_ingreso DATETIME,               -- se guarda al iniciar sesión
    contrasena VARCHAR(255) NOT NULL,      -- cifrada con password_hash
    cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0 -- 1 = debe cambiarla al entrar
);

CREATE TABLE IF NOT EXISTS administrador (
    fk_id_usuario INT PRIMARY KEY,

    CONSTRAINT fk_admin_usuario
        FOREIGN KEY (fk_id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS auxiliar (
    fk_id_usuario INT PRIMARY KEY,
    fk_id_ruta INT NULL, -- NULL = todavía sin ruta asignada

    CONSTRAINT fk_aux_usuario
        FOREIGN KEY (fk_id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE CASCADE,

    CONSTRAINT fk_aux_ruta
        FOREIGN KEY (fk_id_ruta)
        REFERENCES ruta(id_ruta),

    UNIQUE (fk_id_usuario, fk_id_ruta)
);

-- ===========================
-- ASISTENCIAS
-- ===========================

CREATE TABLE IF NOT EXISTS jornada (
    id_jornada INT AUTO_INCREMENT PRIMARY KEY,
    fk_id_ruta INT NOT NULL,
    fk_id_usuario INT NOT NULL,
    tipo_recorrido ENUM('IDA','REGRESO') NOT NULL,
    fecha DATE NOT NULL DEFAULT (CURRENT_DATE),

    CONSTRAINT fk_jornada_ruta
        FOREIGN KEY (fk_id_ruta)
        REFERENCES ruta(id_ruta),

    CONSTRAINT fk_jornada_usuario
        FOREIGN KEY (fk_id_usuario)
        REFERENCES usuario(id_usuario)
);

CREATE TABLE IF NOT EXISTS asistencia (
    id_asistencia INT AUTO_INCREMENT PRIMARY KEY,
    fk_num_doc_estu VARCHAR(20) NOT NULL,
    fk_jornada INT NOT NULL,
    hora TIME NOT NULL DEFAULT (CURRENT_TIME),
    estado ENUM('REGISTRADO','PENDIENTE') DEFAULT 'PENDIENTE',

    CONSTRAINT fk_asistencia_estudiante
        FOREIGN KEY (fk_num_doc_estu)
        REFERENCES estudiante(num_doc_estu),

    CONSTRAINT fk_asistencia_jornada
        FOREIGN KEY (fk_jornada)
        REFERENCES jornada(id_jornada),

    UNIQUE (fk_num_doc_estu, fk_jornada)
);

-- #####################################################################
-- PARTE B. ACTUALIZACIÓN DE UNA BASE VIEJA
-- Si las tablas ya existían con la versión anterior del proyecto, agrega las
-- columnas nuevas de usuario y permite auxiliares sin ruta. En una base nueva no cambia nada.
-- #####################################################################

DROP PROCEDURE IF EXISTS sp_agregar_columna;

DELIMITER //
CREATE PROCEDURE sp_agregar_columna(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_definicion VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna
    ) THEN
        SET @sql_columna = CONCAT('ALTER TABLE ', p_tabla, ' ADD COLUMN ', p_columna, ' ', p_definicion);
        PREPARE sentencia FROM @sql_columna;
        EXECUTE sentencia;
        DEALLOCATE PREPARE sentencia;
    END IF;
END //
DELIMITER ;

CALL sp_agregar_columna('usuario', 'dni', 'VARCHAR(20) NULL UNIQUE AFTER id_usuario');
CALL sp_agregar_columna('usuario', 'telefono', 'VARCHAR(20) NULL AFTER correo');
CALL sp_agregar_columna('usuario', 'salario', 'DECIMAL(12,2) NULL AFTER telefono');
CALL sp_agregar_columna('usuario', 'fecha_ingreso', 'DATE NULL AFTER salario');
CALL sp_agregar_columna('usuario', 'ultimo_ingreso', 'DATETIME NULL AFTER fecha_ingreso');
CALL sp_agregar_columna('usuario', 'cambiar_contrasena', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER contrasena');

DROP PROCEDURE sp_agregar_columna;

-- Un auxiliar puede quedar sin ruta (NULL = "Ninguna")
ALTER TABLE auxiliar MODIFY fk_id_ruta INT NULL;


-- #####################################################################
-- PARTE C. DATOS BASE
-- INSERT IGNORE: si ya existen, no se repiten.
-- #####################################################################

-- Cursos
INSERT IGNORE INTO curso VALUES
(1,'Primero A'),
(2,'Segundo A'),
(3,'Tercero A'),
(4,'Cuarto A'),
(5,'Quinto A'),
(6,'Sexto A'),
(7,'Séptimo A'),
(8,'Octavo A'),
(9,'Octavo B'),
(10,'Noveno A'),
(11,'Décimo A'),
(12,'Undécimo A');

-- Parentescos de los acudientes
INSERT IGNORE INTO parentesco VALUES
(1,'Padre'),
(2,'Madre'),
(3,'Abuelo'),
(4,'Abuela'),
(5,'Tío'),
(6,'Tía'),
(7,'Hermano(a)'),
(8,'Otro');


-- #####################################################################
-- PARTE D. FUNCIONES, VISTAS, TRIGGERS, ÍNDICES Y PROCEDIMIENTOS
-- #####################################################################


-- =====================================================================
-- 1. FUNCIONES
-- =====================================================================

DROP FUNCTION IF EXISTS fn_calcular_edad;
DROP FUNCTION IF EXISTS fn_ruta_estudiante;
DROP FUNCTION IF EXISTS fn_porcentaje_asistencia;

DELIMITER //

-- Edad en años cumplidos a partir de la fecha de nacimiento.
-- La usan los triggers de estudiante para que la edad nunca quede vieja.
CREATE FUNCTION fn_calcular_edad(p_fecha_nacimiento DATE)
RETURNS INT
NOT DETERMINISTIC
NO SQL
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, p_fecha_nacimiento, CURDATE());
END //

-- Ruta a la que pertenece un estudiante (por su parada). NULL si no existe.
CREATE FUNCTION fn_ruta_estudiante(p_documento VARCHAR(20))
RETURNS INT
NOT DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_ruta INT DEFAULT NULL;

    SELECT p.fk_id_ruta INTO v_ruta
    FROM estudiante e
    INNER JOIN parada p ON p.id_parada = e.fk_id_parada
    WHERE e.num_doc_estu = p_documento;

    RETURN v_ruta;
END //

-- Porcentaje de estudiantes de una ruta que tienen registro de IDA o REGRESO en una fecha.
-- Con p_id_ruta = NULL cuenta todas las rutas. Lee la vista vista_asistencia_por_dia.
CREATE FUNCTION fn_porcentaje_asistencia(p_id_ruta INT, p_fecha DATE, p_tipo VARCHAR(10))
RETURNS INT
NOT DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_registrados INT DEFAULT 0;

    SELECT COUNT(*) INTO v_total
    FROM estudiante e
    INNER JOIN parada p ON p.id_parada = e.fk_id_parada
    WHERE p_id_ruta IS NULL OR p.fk_id_ruta = p_id_ruta;

    IF v_total = 0 THEN
        RETURN 0;
    END IF;

    SELECT COALESCE(SUM(IF(p_tipo = 'IDA', v.con_ida, v.con_regreso)), 0) INTO v_registrados
    FROM vista_asistencia_por_dia v
    WHERE v.fecha = p_fecha AND (p_id_ruta IS NULL OR v.id_ruta = p_id_ruta);

    RETURN ROUND(v_registrados * 100 / v_total);
END //

DELIMITER ;


-- =====================================================================
-- 2. VISTAS
-- =====================================================================

-- Estudiantes con curso, parada, ruta, acudiente principal y la hora de ida y
-- regreso de HOY. Usa un CTE (registros_hoy) en lugar de repetir la subconsulta.
-- La usa el módulo Estudiantes (listado y estadísticas).
CREATE OR REPLACE VIEW vista_panel_asistencia_diaria AS
WITH registros_hoy AS (
    SELECT a.fk_num_doc_estu, j.tipo_recorrido, a.hora
    FROM asistencia a
    INNER JOIN jornada j ON j.id_jornada = a.fk_jornada
    WHERE j.fecha = CURRENT_DATE AND a.estado = 'REGISTRADO'
)
SELECT
    e.num_doc_estu AS documento,
    e.tipo_doc_estu AS tipo_documento,
    CONCAT_WS(' ', e.apellido, e.segundo_apellido, e.nombre, e.segundo_nombre) AS estudiante,
    c.nombre AS curso,
    p.nombre AS parada,
    r.nombre AS ruta,
    CONCAT_WS(' ', acu.nombre_acu, acu.apellido_acu) AS acudiente_principal,
    par.nombre AS parentesco_acudiente,
    ida.hora AS hora_ida,
    regreso.hora AS hora_regreso
FROM estudiante e
INNER JOIN curso c ON e.fk_id_curso = c.id_curso
INNER JOIN parada p ON e.fk_id_parada = p.id_parada
INNER JOIN ruta r ON p.fk_id_ruta = r.id_ruta
LEFT JOIN acudiente_estudiante ae
    ON e.num_doc_estu = ae.fk_num_doc_estu AND ae.tipo_acudiente = 'PRINCIPAL'
LEFT JOIN acudiente acu ON ae.fk_num_doc_acu = acu.num_doc_acu
LEFT JOIN parentesco par ON ae.fk_id_parentesco = par.id_parentesco
LEFT JOIN registros_hoy ida
    ON ida.fk_num_doc_estu = e.num_doc_estu AND ida.tipo_recorrido = 'IDA'
LEFT JOIN registros_hoy regreso
    ON regreso.fk_num_doc_estu = e.num_doc_estu AND regreso.tipo_recorrido = 'REGRESO';

-- Tarjetas de rutas: conductor, auxiliar y estudiantes por parada.
CREATE OR REPLACE VIEW vista_tarjetas_rutas AS
SELECT r.nombre AS Ruta,
       c.nombres AS Conductor,
       u.nombre AS Auxiliar,
       p.nombre AS Parada,
       COUNT(e.num_doc_estu) AS Estudiantes
FROM ruta r
JOIN conductor c ON r.fk_num_doc_conductor = c.num_doc_conductor
LEFT JOIN auxiliar a ON r.id_ruta = a.fk_id_ruta
LEFT JOIN usuario u ON a.fk_id_usuario = u.id_usuario
JOIN parada p ON r.id_ruta = p.fk_id_ruta
LEFT JOIN estudiante e ON p.id_parada = e.fk_id_parada
GROUP BY r.id_ruta, c.num_doc_conductor, u.id_usuario, p.id_parada
ORDER BY p.km_referencia DESC;

-- Auxiliares con su ruta (NULL = sin ruta) y su último ingreso.
-- La usa el módulo Auxiliares.
CREATE OR REPLACE VIEW vista_auxiliares AS
SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.dni, u.telefono,
       u.fecha_ingreso, u.ultimo_ingreso, u.cambiar_contrasena,
       a.fk_id_ruta AS id_ruta, r.nombre AS ruta
FROM auxiliar a
INNER JOIN usuario u ON u.id_usuario = a.fk_id_usuario
LEFT JOIN ruta r ON r.id_ruta = a.fk_id_ruta;

-- Cuántos estudiantes subieron (ida) y bajaron (regreso) por día y por ruta.
-- Usa un CTE (conteo) para contar una vez por recorrido y luego pasar a columnas.
-- La usa fn_porcentaje_asistencia (gráfica "Esta semana" del Panel).
CREATE OR REPLACE VIEW vista_asistencia_por_dia AS
WITH conteo AS (
    SELECT j.fecha, j.fk_id_ruta, j.tipo_recorrido,
           COUNT(DISTINCT a.fk_num_doc_estu) AS estudiantes
    FROM jornada j
    INNER JOIN asistencia a ON a.fk_jornada = j.id_jornada
    WHERE a.estado = 'REGISTRADO'
    GROUP BY j.fecha, j.fk_id_ruta, j.tipo_recorrido
)
SELECT c.fecha,
       c.fk_id_ruta AS id_ruta,
       r.nombre AS ruta,
       SUM(IF(c.tipo_recorrido = 'IDA', c.estudiantes, 0)) AS con_ida,
       SUM(IF(c.tipo_recorrido = 'REGRESO', c.estudiantes, 0)) AS con_regreso
FROM conteo c
INNER JOIN ruta r ON r.id_ruta = c.fk_id_ruta
GROUP BY c.fecha, c.fk_id_ruta, r.nombre;


-- =====================================================================
-- 3. TRIGGERS (DISPARADORES)
-- =====================================================================

DROP TRIGGER IF EXISTS trg_estudiante_edad_insert;
DROP TRIGGER IF EXISTS trg_estudiante_edad_update;
DROP TRIGGER IF EXISTS trg_asistencia_misma_ruta;
DROP TRIGGER IF EXISTS trg_jornada_sin_fecha_futura;

DELIMITER //

-- La edad se calcula sola al crear un estudiante...
CREATE TRIGGER trg_estudiante_edad_insert
BEFORE INSERT ON estudiante
FOR EACH ROW
BEGIN
    SET NEW.edad = fn_calcular_edad(NEW.fecha_nacimiento);
END //

-- ...y al cambiarle la fecha de nacimiento (o cualquier dato).
CREATE TRIGGER trg_estudiante_edad_update
BEFORE UPDATE ON estudiante
FOR EACH ROW
BEGIN
    SET NEW.edad = fn_calcular_edad(NEW.fecha_nacimiento);
END //

-- Un estudiante solo puede quedar registrado en una jornada de SU ruta.
CREATE TRIGGER trg_asistencia_misma_ruta
BEFORE INSERT ON asistencia
FOR EACH ROW
BEGIN
    DECLARE v_ruta_jornada INT;

    SELECT fk_id_ruta INTO v_ruta_jornada
    FROM jornada
    WHERE id_jornada = NEW.fk_jornada;

    IF fn_ruta_estudiante(NEW.fk_num_doc_estu) <> v_ruta_jornada THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El estudiante no pertenece a la ruta de esta jornada.';
    END IF;
END //

-- No se pueden abrir recorridos con fecha futura.
CREATE TRIGGER trg_jornada_sin_fecha_futura
BEFORE INSERT ON jornada
FOR EACH ROW
BEGIN
    IF NEW.fecha > CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede registrar asistencia de una fecha futura.';
    END IF;
END //

DELIMITER ;


-- =====================================================================
-- 4. ÍNDICES
-- (los de llaves primarias, foráneas y UNIQUE ya los crea MySQL solo)
-- =====================================================================

-- MySQL (Azure) no tiene "DROP INDEX IF EXISTS". Este procedimiento de ayuda crea el
-- índice solo si todavía no existe, así el archivo se puede ejecutar más de una vez.
DROP PROCEDURE IF EXISTS sp_crear_indice;

DELIMITER //
CREATE PROCEDURE sp_crear_indice(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64), IN p_columnas VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice
    ) THEN
        SET @sql_indice = CONCAT('CREATE INDEX ', p_indice, ' ON ', p_tabla, ' (', p_columnas, ')');
        PREPARE sentencia FROM @sql_indice;
        EXECUTE sentencia;
        DEALLOCATE PREPARE sentencia;
    END IF;
END //
DELIMITER ;

-- Asistencias de un día: casi todas las consultas filtran jornada por fecha y recorrido.
CALL sp_crear_indice('jornada', 'idx_jornada_fecha_tipo', 'fecha, tipo_recorrido');

-- Paradas de una ruta en orden de recorrido (por km).
CALL sp_crear_indice('parada', 'idx_parada_ruta_km', 'fk_id_ruta, km_referencia');

-- Listados de estudiantes ordenados y buscados por apellido y nombre.
CALL sp_crear_indice('estudiante', 'idx_estudiante_apellido_nombre', 'apellido, nombre');

DROP PROCEDURE sp_crear_indice;


-- =====================================================================
-- 5. PROCEDIMIENTOS ALMACENADOS
-- =====================================================================

DROP PROCEDURE IF EXISTS obtener_rol_usuario;
DROP PROCEDURE IF EXISTS sp_registrar_asistencia;
DROP PROCEDURE IF EXISTS sp_crear_auxiliar;
DROP PROCEDURE IF EXISTS sp_resumen_semana;

DELIMITER //

-- Rol (administrador / auxiliar) y ruta de un usuario. Lo usa el inicio de sesión.
CREATE PROCEDURE obtener_rol_usuario(IN p_id_usuario INT)
BEGIN
    SELECT
        u.id_usuario,
        CASE
            WHEN a.fk_id_usuario IS NOT NULL THEN 'administrador'
            WHEN aux.fk_id_usuario IS NOT NULL THEN 'auxiliar'
            ELSE NULL
        END AS rol,
        aux.fk_id_ruta
    FROM usuario u
    LEFT JOIN administrador a ON a.fk_id_usuario = u.id_usuario
    LEFT JOIN auxiliar aux ON aux.fk_id_usuario = u.id_usuario
    WHERE u.id_usuario = p_id_usuario
    LIMIT 1;
END //

-- Registra que un estudiante subió (IDA) o bajó (REGRESO), en una TRANSACCIÓN:
-- busca o crea la jornada del usuario y guarda la asistencia; si algo falla, deshace todo.
-- Devuelve una fila: resultado = registrado | ya_registrado | desconocido, y la hora.
-- La usan el registro del auxiliar, el escáner QR y el registro manual del administrador.
CREATE PROCEDURE sp_registrar_asistencia(
    IN p_documento VARCHAR(20),
    IN p_id_usuario INT,
    IN p_tipo VARCHAR(10),
    IN p_fecha DATE,
    IN p_hora TIME
)
proc: BEGIN
    DECLARE v_ruta INT;
    DECLARE v_jornada INT DEFAULT NULL;
    DECLARE v_hora_existente TIME DEFAULT NULL;

    -- Ante cualquier error se deshace la transacción y se avisa a PHP
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET v_ruta = fn_ruta_estudiante(p_documento);
    IF v_ruta IS NULL THEN
        SELECT 'desconocido' AS resultado, NULL AS hora;
        LEAVE proc;
    END IF;

    START TRANSACTION;

    -- ¿Ya tiene registro de ese recorrido ese día? (FOR UPDATE evita dobles lecturas del QR)
    SELECT a.hora INTO v_hora_existente
    FROM asistencia a
    INNER JOIN jornada j ON j.id_jornada = a.fk_jornada
    WHERE a.fk_num_doc_estu = p_documento AND a.estado = 'REGISTRADO'
      AND j.tipo_recorrido = p_tipo AND j.fecha = p_fecha
    LIMIT 1
    FOR UPDATE;

    IF v_hora_existente IS NOT NULL THEN
        ROLLBACK;
        SELECT 'ya_registrado' AS resultado, v_hora_existente AS hora;
        LEAVE proc;
    END IF;

    -- Jornada de este usuario para la ruta, el recorrido y el día (se crea si no existe)
    SELECT id_jornada INTO v_jornada
    FROM jornada
    WHERE fk_id_ruta = v_ruta AND fk_id_usuario = p_id_usuario
      AND tipo_recorrido = p_tipo AND fecha = p_fecha
    LIMIT 1;

    IF v_jornada IS NULL THEN
        INSERT INTO jornada (fk_id_ruta, fk_id_usuario, tipo_recorrido, fecha)
        VALUES (v_ruta, p_id_usuario, p_tipo, p_fecha);
        SET v_jornada = LAST_INSERT_ID();
    END IF;

    INSERT INTO asistencia (fk_num_doc_estu, fk_jornada, hora, estado)
    VALUES (p_documento, v_jornada, p_hora, 'REGISTRADO');

    COMMIT;
    SELECT 'registrado' AS resultado, p_hora AS hora;
END //

-- Crea la cuenta de un auxiliar en una TRANSACCIÓN: usuario + auxiliar.
-- La contraseña llega ya cifrada desde PHP (password_hash). p_id_ruta NULL = sin ruta.
-- Devuelve el id del usuario creado.
CREATE PROCEDURE sp_crear_auxiliar(
    IN p_dni VARCHAR(20),
    IN p_nombre VARCHAR(100),
    IN p_apellido VARCHAR(100),
    IN p_correo VARCHAR(150),
    IN p_telefono VARCHAR(20),
    IN p_fecha_ingreso DATE,
    IN p_contrasena VARCHAR(255),
    IN p_cambiar_contrasena TINYINT,
    IN p_id_ruta INT
)
BEGIN
    DECLARE v_id_usuario INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    INSERT INTO usuario (dni, nombre, apellido, correo, telefono, fecha_ingreso, contrasena, cambiar_contrasena)
    VALUES (p_dni, p_nombre, p_apellido, p_correo, p_telefono, p_fecha_ingreso, p_contrasena, p_cambiar_contrasena);
    SET v_id_usuario = LAST_INSERT_ID();

    INSERT INTO auxiliar (fk_id_usuario, fk_id_ruta)
    VALUES (v_id_usuario, p_id_ruta);

    COMMIT;
    SELECT v_id_usuario AS id_usuario;
END //

-- Porcentaje de ida y regreso de lunes a viernes de una semana (gráfica del Panel).
-- Un CTE RECURSIVO arma los 5 días y, para cada uno, fn_porcentaje_asistencia calcula el %.
-- p_id_ruta NULL = todas las rutas (administrador).
CREATE PROCEDURE sp_resumen_semana(IN p_lunes DATE, IN p_id_ruta INT)
BEGIN
    WITH RECURSIVE dias AS (
        SELECT p_lunes AS fecha, 0 AS numero
        UNION ALL
        SELECT fecha + INTERVAL 1 DAY, numero + 1
        FROM dias
        WHERE numero < 4
    )
    SELECT fecha,
           fn_porcentaje_asistencia(p_id_ruta, fecha, 'IDA') AS ida,
           fn_porcentaje_asistencia(p_id_ruta, fecha, 'REGRESO') AS regreso
    FROM dias
    ORDER BY fecha;
END //

DELIMITER ;
