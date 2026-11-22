-- =====================================================================
-- Base de datos ruta_escolar_v3 (RutaSegura)
-- Estructura completa de las tablas + datos de ejemplo.
--
-- Orden para instalar:
--   1. Este archivo (ruta_escolar_v3.sql)  -> tablas y datos
--   2. objetos_bd.sql                      -> funciones, triggers, vistas,
--                                             índices y procedimientos
--
-- ¡Cuidado! Borra la base de datos ruta_escolar_v3 si ya existe.
-- Probado en MariaDB 10.4 (XAMPP).
-- =====================================================================

DROP DATABASE IF EXISTS ruta_escolar_v3;

CREATE DATABASE ruta_escolar_v3 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ruta_escolar_v3;

-- ===========================
-- RUTAS
-- ===========================

CREATE TABLE curso (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE conductor (
    num_doc_conductor INT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20)
);

CREATE TABLE ruta (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    fk_num_doc_conductor INT NOT NULL UNIQUE,

    CONSTRAINT fk_ruta_conductor
        FOREIGN KEY (fk_num_doc_conductor)
        REFERENCES conductor(num_doc_conductor),

    UNIQUE (id_ruta, fk_num_doc_conductor)
);

CREATE TABLE parada (
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

CREATE TABLE estudiante (
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

CREATE TABLE acudiente (
    num_doc_acu VARCHAR(20) PRIMARY KEY,
    nombre_acu VARCHAR(100) NOT NULL,
    apellido_acu VARCHAR(100) NOT NULL,
    telefono VARCHAR(20)
);

CREATE TABLE parentesco (
    id_parentesco INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE acudiente_estudiante (
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

CREATE TABLE usuario (
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

CREATE TABLE administrador (
    fk_id_usuario INT PRIMARY KEY,

    CONSTRAINT fk_admin_usuario
        FOREIGN KEY (fk_id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE CASCADE
);

CREATE TABLE auxiliar (
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

CREATE TABLE jornada (
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

CREATE TABLE asistencia (
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

-- =====================================================================
-- DATOS DE EJEMPLO
-- Dos cuentas de prueba (administrador y auxiliar de la Ruta 1) con la
-- contraseña ya cifrada. Cámbialas antes de usar el sistema en un servidor real.
-- Las asistencias no se incluyen: se crean al usar la aplicación.
-- =====================================================================
-- Cursos
INSERT INTO curso VALUES
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

-- Conductores
INSERT INTO conductor VALUES
(17800100,'José Luis','Epieyú','300 200 0001'),
(17800200,'Carlos','Pushaina','300 200 0002');

-- Rutas
INSERT INTO ruta VALUES
(1,'Ruta 1 · Vía Paraguachón',17800100),
(2,'Ruta 2 · Carraipía',17800200);

-- Paradas
INSERT INTO parada VALUES
(1,'Entrada Comunidad A','Comunidad A',3.50,1),
(2,'Cruce El Molino','El Molino',6.20,1),
(3,'Puente Seco','Puente Seco',9.80,1),
(4,'Escuela Carraipía','Carraipía',12.40,2);

-- Estudiantes
INSERT INTO estudiante VALUES
('1120001001','TI','María','José','Uriana','Epinayú','2012-01-01',14,6,1),
('1120001002','TI','Luis','Ángel','Ipuana','Gouriyú','2012-01-01',14,9,1),
('1120001003','TI','Juan','David','Arpushana','Gómez','2012-01-01',14,6,3),
('1120001004','TI','Mateo',NULL,'Epieyú','Cotes','2012-01-01',14,6,2),
('1120001005','TI','Andrés','Felipe','Brito','Iguarán','2012-01-01',14,4,4),
('1120001006','TI','Daniela',NULL,'Mejía','Cotes','2012-01-01',14,2,2);

-- Acudientes
INSERT INTO acudiente VALUES
('40100100','Rosa','Epinayú','301 100 0001'),
('40100200','Pedro','Uriana','301 100 0002'),
('40100300','Carmen','Gouriyú','301 100 0003'),
('40100400','Ramón','Iguarán','301 100 0004'),
('40100500','Luz','Cotes',NULL);

-- Parentescos
INSERT INTO parentesco VALUES
(1,'Padre'),
(2,'Madre'),
(3,'Abuelo'),
(4,'Abuela'),
(5,'Tío'),
(6,'Tía'),
(7,'Hermano(a)'),
(8,'Otro');

-- Acudientes de cada estudiante
INSERT INTO acudiente_estudiante VALUES
('40100100','1120001001',2,'PRINCIPAL'),
('40100100','1120001002',6,'SUPLENTE'),
('40100200','1120001001',1,'SUPLENTE'),
('40100300','1120001002',2,'PRINCIPAL'),
('40100400','1120001005',1,'PRINCIPAL'),
('40100500','1120001004',2,'PRINCIPAL'),
('40100500','1120001006',2,'PRINCIPAL');

-- Usuarios (contraseña cifrada)
INSERT INTO usuario VALUES
(1,NULL,'Ana','Pimienta','admin@rutasegura.test',NULL,NULL,NULL,NULL,'$2y$10$hdAkqJX.tWwFVvUKy8MvXOBIvqMX.9AegQJB9fYAVVZgzagxV1.UG',0),
(2,NULL,'Jorge','Ballesteros','auxiliar@rutasegura.test',NULL,NULL,NULL,NULL,'$2y$10$b2zBXJr/wWVIsybjOa30YeRQ/Yfi4lpzs5psWWeZYcBbhxELOwwRa',0);

-- Administrador
INSERT INTO administrador VALUES
(1);

-- Auxiliar de la Ruta 1
INSERT INTO auxiliar VALUES
(2,1);
