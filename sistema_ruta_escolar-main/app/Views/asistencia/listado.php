<?php
// Asistencias (administrador) e Historial (auxiliar, $soloLectura = true).
// Datos de AsistenciaController::listado() / historial():
// $fecha, $rutas, $idRuta, $estudiantes, $auxiliaresRuta, $total, $conIda, $conRegreso,
// $pendientes, $formulario, $aviso, $soloLectura y, en el historial, $diasRecientes
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$esHoy = $fecha === date('Y-m-d');
$verTodas = !$soloLectura && $idRuta === 0;
$accionFecha = $soloLectura ? '/asistencia/historial' : '/asistencias';

// Quién hizo cada registro: la administración (manual) o el auxiliar
$registro = function (array $fila, string $tipo): ?string {
    $usuario = $fila['usuario_' . $tipo];
    if ($usuario === null) {
        return null;
    }
    return $fila['es_admin_' . $tipo] ? 'manual' : 'Auxiliar · ' . $usuario;
};

// Valores para la ventana de registro manual (los que se enviaron si hubo error)
$manual = $formulario['datos'] ?? ['documento' => '', 'tipo' => 'IDA', 'hora' => $esHoy ? date('H:i') : '06:00'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $soloLectura ? 'Historial' : 'Asistencias' ?> · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
    <link rel="stylesheet" href="<?= e($base) ?>/css/asistencia.css">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta"><?= $soloLectura ? e($rutas[0]['nombre'] ?? 'Sin ruta asignada') : 'Gestión de ruta escolar' ?></p>
                <h1><?= $soloLectura ? 'Historial' : 'Asistencias' ?></h1>
                <?php if ($soloLectura): ?>
                    <p class="subtitulo">Solo consulta. Para corregir un registro, pide a administración.</p>
                <?php endif; ?>
            </div>
            <?php if (!$soloLectura): ?>
                <button type="button" class="boton boton-amarillo" data-abrir-ventana="ventana-manual" <?= $total === 0 ? 'disabled' : '' ?>>+ Registro manual</button>
            <?php endif; ?>
        </header>

        <?php if ($aviso): ?>
            <p class="aviso <?= $aviso['tipo'] === 'exito' ? 'aviso-exito' : 'aviso-error' ?>" role="status"><?= e($aviso['texto']) ?></p>
        <?php endif; ?>

        <section class="filtros-asistencia">
            <form class="elegir-fecha" action="<?= e($base . $accionFecha) ?>" method="GET">
                <?php if (!$soloLectura): ?>
                    <input type="hidden" name="ruta" value="<?= (int) $idRuta ?>">
                <?php endif; ?>
                <label>
                    <span class="visualmente-oculto">Fecha</span>
                    <input type="date" name="fecha" value="<?= e($fecha) ?>" max="<?= e(date('Y-m-d')) ?>" class="campo mono">
                </label>
                <noscript><button type="submit" class="boton boton-borde boton-chico">Ver</button></noscript>
            </form>

            <?php if ($soloLectura): ?>
                <nav class="pestanas pestanas-amarillas" aria-label="Días recientes">
                    <?php foreach ($diasRecientes as $dia): ?>
                        <a href="<?= e($base) ?>/asistencia/historial?fecha=<?= e($dia) ?>" class="<?= $dia === $fecha ? 'activo' : '' ?>" <?= $dia === $fecha ? 'aria-current="page"' : '' ?>>
                            <?= $dia === date('Y-m-d') ? 'Hoy' : e(date('d/m', strtotime($dia))) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php else: ?>
            <nav class="pestanas pestanas-amarillas" aria-label="Ruta">
                <a href="<?= e($base) ?>/asistencias?fecha=<?= e($fecha) ?>&amp;ruta=0" class="<?= $verTodas ? 'activo' : '' ?>">Todas</a>
                <?php foreach ($rutas as $ruta): ?>
                    <?php $activa = (int) $ruta['id_ruta'] === $idRuta; ?>
                    <a href="<?= e($base) ?>/asistencias?fecha=<?= e($fecha) ?>&amp;ruta=<?= (int) $ruta['id_ruta'] ?>"
                       class="<?= $activa ? 'activo' : '' ?>" title="<?= e($ruta['nombre']) ?>" <?= $activa ? 'aria-current="page"' : '' ?>>Ruta <?= (int) $ruta['id_ruta'] ?></a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <p class="auxiliar-ruta">
                Auxiliar<?= count($auxiliaresRuta) > 1 ? 'es' : '' ?>:
                <strong><?= $auxiliaresRuta ? e(implode(', ', $auxiliaresRuta)) : 'Sin asignar' ?></strong>
            </p>
        </section>

        <section class="resumen-dia">
            <article class="panel resumen-ida">
                <span class="texto-amarillo"><strong>Ida</strong></span>
                <span><strong><?= $conIda ?> de <?= $total ?></strong> subieron</span>
            </article>
            <article class="panel resumen-regreso">
                <span class="texto-azul"><strong>Regreso</strong></span>
                <span><strong><?= $conRegreso ?> de <?= $conIda ?></strong> bajaron</span>
                <?php if ($pendientes > 0): ?>
                    <span class="texto-alerta"><?= $pendientes ?> pendiente<?= $pendientes > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </article>
        </section>

        <section class="panel-tabla">
            <?php if ($total === 0): ?>
                <p class="sin-resultados">No hay estudiantes en esta ruta.</p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Parada</th>
                                <th>Ida</th>
                                <th>Regreso</th>
                                <th>Registró</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $fila): ?>
                                <?php
                                $registros = array_unique(array_filter([$registro($fila, 'ida'), $registro($fila, 'regreso')]));
                                $esManual = in_array('manual', $registros, true);
                                $faltaTipo = $fila['hora_ida'] === null ? 'IDA' : ($fila['hora_regreso'] === null ? 'REGRESO' : null);
                                ?>
                                <tr class="<?= $esManual ? 'fila-manual' : '' ?>">
                                    <td>
                                        <a href="<?= e($base) ?>/estudiantes/ficha?documento=<?= e(urlencode($fila['documento'])) ?>"><strong><?= e($fila['estudiante']) ?></strong></a>
                                        <small><?= e($fila['curso']) ?></small>
                                    </td>
                                    <td>
                                        <?= e($fila['parada']) ?>
                                        <?php if ($verTodas): ?><small><?= e($fila['ruta']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fila['hora_ida']): ?>
                                            <span class="hora-ida mono"><?= e(substr($fila['hora_ida'], 0, 5)) ?></span>
                                        <?php else: ?>
                                            <span class="texto-alerta">No subió</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fila['hora_regreso']): ?>
                                            <span class="hora-regreso mono"><?= e(substr($fila['hora_regreso'], 0, 5)) ?></span>
                                        <?php elseif ($fila['hora_ida']): ?>
                                            <span class="texto-alerta">Pendiente</span>
                                        <?php else: ?>
                                            <span class="texto-suave">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php foreach ($registros as $quien): ?>
                                            <?php if ($quien === 'manual'): ?>
                                                <span class="registro-quien"><span class="insignia insignia-principal">Manual</span> Administración</span>
                                            <?php else: ?>
                                                <span class="registro-quien texto-suave"><?= e($quien) ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if ($faltaTipo && !$soloLectura): ?>
                                            <button type="button" class="enlace" data-abrir-ventana="ventana-manual"
                                                    data-documento="<?= e($fila['documento']) ?>" data-tipo="<?= $faltaTipo ?>">
                                                Registrar a mano
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php if (!$soloLectura): ?>
    <!-- Ventana de registro manual: queda a nombre del administrador -->
    <dialog class="ventana ventana-manual" id="ventana-manual" aria-labelledby="titulo-manual" <?= $formulario ? 'data-abrir' : '' ?>>
        <form action="<?= e($base) ?>/asistencias/manual" method="POST">
            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
            <input type="hidden" name="ruta" value="<?= (int) $idRuta ?>">

            <div class="ventana-cabecera">
                <div>
                    <p class="etiqueta">Registro manual · <?= e(date('d/m/Y', strtotime($fecha))) ?></p>
                    <h2 id="titulo-manual">Registrar a mano</h2>
                </div>
                <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
            </div>

            <?php if (!empty($formulario['error'])): ?>
                <p class="aviso aviso-error" role="alert"><?= e($formulario['error']) ?></p>
            <?php endif; ?>

            <div class="campos">
                <label class="campo-completo">Estudiante
                    <select name="documento" required>
                        <option value="">Elige un estudiante</option>
                        <?php foreach ($estudiantes as $fila): ?>
                            <option value="<?= e($fila['documento']) ?>" <?= $fila['documento'] === $manual['documento'] ? 'selected' : '' ?>>
                                <?= e($fila['estudiante']) ?> · <?= e($fila['parada']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <fieldset class="tipo-manual">
                    <legend>Recorrido</legend>
                    <div class="pestanas">
                        <?php foreach (['IDA' => 'Ida', 'REGRESO' => 'Regreso'] as $valor => $texto): ?>
                            <label>
                                <input type="radio" name="tipo" value="<?= $valor ?>" <?= $manual['tipo'] === $valor ? 'checked' : '' ?>>
                                <span><?= $texto ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <label>Hora
                    <input type="time" name="hora" value="<?= e($manual['hora']) ?>" required class="campo mono">
                </label>
            </div>
            <p class="nota">El registro queda a nombre de la administración y se marca como "Manual".</p>

            <div class="ventana-acciones">
                <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                <button type="submit" class="boton boton-amarillo">Guardar registro</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <script src="<?= e($base) ?>/js/asistencia.js"></script>
</body>
</html>
