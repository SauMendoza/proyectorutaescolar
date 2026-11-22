-- =====================================================================
-- Objetos de la base de datos ruta_escolar_v3
-- Funciones, triggers (disparadores), vistas (dos con CTE), índices y
-- procedimientos almacenados (dos con transacción y uno con CTE recursivo).
--
-- Ejecutar DESPUÉS de ruta_escolar_v3.sql. Se puede volver a ejecutar:
-- cada objeto se borra antes de crearlo otra vez.
-- Probado en MariaDB 10.4 (XAMPP).
-- =====================================================================

USE ruta_escolar_v3;


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

-- Asistencias de un día: casi todas las consultas filtran jornada por fecha y recorrido.
DROP INDEX IF EXISTS idx_jornada_fecha_tipo ON jornada;
CREATE INDEX idx_jornada_fecha_tipo ON jornada (fecha, tipo_recorrido);

-- Paradas de una ruta en orden de recorrido (por km).
DROP INDEX IF EXISTS idx_parada_ruta_km ON parada;
CREATE INDEX idx_parada_ruta_km ON parada (fk_id_ruta, km_referencia);

-- Listados de estudiantes ordenados y buscados por apellido y nombre.
DROP INDEX IF EXISTS idx_estudiante_apellido_nombre ON estudiante;
CREATE INDEX idx_estudiante_apellido_nombre ON estudiante (apellido, nombre);


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
