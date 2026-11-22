<?php
// Ruta base del proyecto para enlazar el CSS, el JS y los formularios
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Escapa un texto antes de mostrarlo en el HTML
if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

// Muestra la hora como 07:15; si no hay registro, devuelve null
function hora($valor): ?string
{
    return $valor ? substr($valor, 0, 5) : null;
}

// Opciones de los filtros: todas las rutas, paradas y cursos de la base de datos
// (también los que aún no tienen estudiantes, como una ruta recién creada).
// Los filtros comparan por nombre, igual que las columnas de la tabla.
$opcionesRuta = array_column($rutasFiltro, 'nombre');
$opcionesCurso = array_column($cursosFiltro, 'nombre');
$nombresRuta = array_column($rutasFiltro, 'nombre', 'id_ruta');
$opcionesParada = [];
foreach ($paradasFiltro as $parada) {
    $opcionesParada[$parada['nombre']] = $nombresRuta[$parada['fk_id_ruta']] ?? '';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
    <link rel="stylesheet" href="<?= e($base) ?>/css/estudiantes.css">
</head>
<body>
    <!-- Panel lateral: logo, menú por rol y usuario -->
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta">Gestión de ruta escolar</p>
                <h1>Estudiantes</h1>
            </div>
            <?php if ($esAdmin): ?>
                <a class="boton boton-amarillo" href="<?= e($base) ?>/estudiantes/nuevo">+ Nuevo estudiante</a>
            <?php endif; ?>
        </header>

        <?php if ($mensajeExito): ?>
            <p class="aviso aviso-exito" role="status"><?= e($mensajeExito) ?></p>
        <?php endif; ?>

        <!-- Estadísticas calculadas desde vista_panel_asistencia_diaria -->
        <section class="estadisticas" aria-label="Estadísticas de hoy">
            <article class="tarjeta">
                <span>Estudiantes</span>
                <strong><?= e($estadisticas['total']) ?></strong>
            </article>
            <article class="tarjeta">
                <span>Abordaron hoy · ida</span>
                <strong><?= e($estadisticas['ida']) ?></strong>
            </article>
            <article class="tarjeta">
                <span>Abordaron hoy · regreso</span>
                <strong><?= e($estadisticas['regreso']) ?></strong>
            </article>
            <article class="tarjeta tarjeta-alerta">
                <span>Sin acudiente suplente</span>
                <strong><?= e($estadisticas['sin_suplente']) ?></strong>
            </article>
        </section>

        <!-- Filtros: los aplica public/js/estudiantes.js sin recargar la página -->
        <section class="filtros" aria-label="Filtros">
            <label class="buscador">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="filtro-texto" placeholder="Buscar por nombre o documento" aria-label="Buscar por nombre o documento">
            </label>
            <select id="filtro-ruta" aria-label="Filtrar por ruta">
                <option value="">Todas las rutas</option>
                <?php foreach ($opcionesRuta as $ruta): ?>
                    <option value="<?= e($ruta) ?>"><?= e($ruta) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filtro-parada" aria-label="Filtrar por parada">
                <option value="">Todas las paradas</option>
                <?php foreach ($opcionesParada as $parada => $ruta): ?>
                    <option value="<?= e($parada) ?>" data-ruta="<?= e($ruta) ?>"><?= e($parada) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filtro-curso" aria-label="Filtrar por curso">
                <option value="">Todos los cursos</option>
                <?php foreach ($opcionesCurso as $curso): ?>
                    <option value="<?= e($curso) ?>"><?= e($curso) ?></option>
                <?php endforeach; ?>
            </select>
        </section>

        <!-- Tabla con los datos de la vista SQL -->
        <section class="panel-tabla">
            <?php if (count($estudiantes) > 0): ?>
                <div class="tabla-contenedor">
                    <table id="tabla-estudiantes">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Parada · Ruta</th>
                                <th>Acudiente</th>
                                <th>Hoy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $estudiante): ?>
                                <tr data-buscar="<?= e($estudiante['estudiante'] . ' ' . $estudiante['documento']) ?>"
                                    data-ruta="<?= e($estudiante['ruta']) ?>"
                                    data-parada="<?= e($estudiante['parada']) ?>"
                                    data-curso="<?= e($estudiante['curso']) ?>">
                                    <td>
                                        <a href="<?= e($base) ?>/estudiantes/ficha?documento=<?= e(urlencode($estudiante['documento'])) ?>"><strong><?= e($estudiante['estudiante']) ?></strong></a>
                                        <small class="mono"><?= e($estudiante['tipo_documento']) ?> <?= e($estudiante['documento']) ?></small>
                                    </td>
                                    <td><?= e($estudiante['curso']) ?></td>
                                    <td>
                                        <?= e($estudiante['parada']) ?>
                                        <small><?= e($estudiante['ruta']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($estudiante['acudiente_principal'] !== null && $estudiante['acudiente_principal'] !== ''): ?>
                                            <?= e($estudiante['acudiente_principal']) ?>
                                            <small><?= e($estudiante['parentesco_acudiente']) ?></small>
                                        <?php else: ?>
                                            <span class="texto-alerta">Sin registrar</span>
                                            <small>Falta acudiente</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hoy">
                                        <?php if (hora($estudiante['hora_ida'])): ?>
                                            <span class="chip chip-ida">Ida <?= e(hora($estudiante['hora_ida'])) ?></span>
                                        <?php else: ?>
                                            <span class="chip chip-falta">Ida</span>
                                        <?php endif; ?>
                                        <?php if (hora($estudiante['hora_regreso'])): ?>
                                            <span class="chip chip-regreso">Reg <?= e(hora($estudiante['hora_regreso'])) ?></span>
                                        <?php else: ?>
                                            <span class="chip chip-falta">Regreso</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="sin-resultados" id="sin-resultados" hidden>Ningún estudiante coincide con la búsqueda.</p>
                <footer class="pie-tabla">
                    <span id="resumen-tabla">Mostrando <?= count($estudiantes) ?> estudiantes</span>
                    <nav class="paginas" id="paginas" aria-label="Páginas"></nav>
                </footer>
            <?php else: ?>
                <p class="sin-resultados">No hay estudiantes registrados.</p>
            <?php endif; ?>
        </section>
    </main>

    <script src="<?= e($base) ?>/js/estudiantes.js"></script>
</body>
</html>
