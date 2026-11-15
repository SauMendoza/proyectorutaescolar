-- Datos de prueba FICTICIOS (no usar datos reales de estudiantes en pruebas).
-- Ejecutar después de ruta_escolar_v3.sql y procedimientos_almacenados_ruta.sql.
-- Los usuarios (administrador/auxiliar) NO se crean aquí: usa database/crear_admin.php.
USE ruta_escolar_v3;

INSERT INTO curso (nombre) VALUES ('Transición'), ('Primero'), ('Segundo'), ('Tercero');

INSERT INTO conductor (num_doc_conductor, nombres, apellidos, telefono) VALUES
    (1000000001, 'Carlos', 'Prueba Uno', '3000000001'),
    (1000000002, 'Luis',   'Prueba Dos', '3000000002');

INSERT INTO ruta (nombre, fk_num_doc_conductor) VALUES
    ('Ruta Norte', 1000000001),
    ('Ruta Sur',   1000000002);

INSERT INTO parada (nombre, comunidad, km_referencia, fk_id_ruta) VALUES
    ('Parada Central',  'Comunidad A', 2.50, 1),
    ('Parada El Cruce', 'Comunidad B', 5.00, 1),
    ('Parada La Loma',  'Comunidad C', 3.20, 2);

INSERT INTO parentesco (nombre) VALUES ('Madre'), ('Padre'), ('Abuelo/a'), ('Tío/a');

INSERT INTO estudiante
    (num_doc_estu, tipo_doc_estu, nombre, segundo_nombre, apellido, segundo_apellido, fecha_nacimiento, edad, fk_id_curso, fk_id_parada)
VALUES
    ('E0001', 'TI', 'Ana',   NULL,   'Ejemplo', 'Uno',  '2016-03-10', 10, 2, 1),
    ('E0002', 'TI', 'Pedro', 'Luis', 'Ejemplo', 'Dos',  '2015-07-22', 11, 3, 2),
    ('E0003', 'RC', 'Sofía', NULL,   'Ejemplo', 'Tres', '2019-01-05', 7,  1, 3);

INSERT INTO acudiente (num_doc_acu, nombre_acu, apellido_acu, telefono) VALUES
    ('A0001', 'María', 'Ejemplo', '3100000001'),
    ('A0002', 'José',  'Ejemplo', '3100000002');

INSERT INTO acudiente_estudiante (fk_num_doc_acu, fk_num_doc_estu, fk_id_parentesco, tipo_acudiente) VALUES
    ('A0001', 'E0001', 1, 'PRINCIPAL'),
    ('A0002', 'E0002', 2, 'PRINCIPAL'),
    ('A0001', 'E0003', 1, 'PRINCIPAL');
