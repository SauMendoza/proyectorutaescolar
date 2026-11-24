<?php
// Panel del auxiliar: lo mismo que el del administrador, pero solo de su ruta.
// Datos de HomeController::index(): $fechaTexto, $saludo, $usuario, $ruta, $resumen, $semana
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$porcentaje = fn($parte, $todo) => $todo > 0 ? round($parte * 100 / $todo) : 0;

// Mientras falte gente en la ida se muestra quién no ha subido; después, quién no ha bajado
if ($resumen) {
    $enIda = $resumen['ida'] < $resumen['total'] && $resumen['regreso'] === 0;
    $faltan = $enIda ? $resumen['faltan_ida'] : $resumen['faltan_regreso'];
    $tituloFaltan = $enIda ? 'Faltan por subir' : 'Faltan por bajar';
}
$iconoTelefono = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/app.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/panel.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta"><?= e($fechaTexto) ?></p>
                <h1><?= e($saludo) ?>, <?= e($usuario['nombre']) ?></h1>
            </div>
            <?php if ($resumen): ?>
                <a class="boton boton-amarillo" href="<?= e($base) ?>/asistencia/registro?tipo=<?= $enIda ? 'IDA' : 'REGRESO' ?>">Ir al registro</a>
            <?php endif; ?>
        </header>

        <?php if (!$resumen): ?>
            <p class="panel texto-suave">No tienes una ruta asignada. Pide a la administración que te asigne una.</p>
        <?php else: ?>
            <section class="cifras" aria-label="Cifras de hoy en tu ruta">
                <article class="tarjeta">
                    <span>Estudiantes en mi ruta</span>
                    <strong><?= $resumen['total'] ?></strong>
                </article>
                <article class="tarjeta">
                    <span>Subieron hoy · ida</span>
                    <strong><?= $resumen['ida'] ?> <small>/ <?= $resumen['total'] ?></small></strong>
                </article>
                <article class="tarjeta">
                    <span>Bajaron hoy · regreso</span>
                    <strong><?= $resumen['regreso'] ?> <small>/ <?= $resumen['ida'] ?></small></strong>
                </article>
                <article class="tarjeta <?= count($resumen['faltan_ida']) > 0 ? 'tarjeta-alerta' : '' ?>">
                    <span>No han subido hoy</span>
                    <strong><?= count($resumen['faltan_ida']) ?></strong>
                </article>
            </section>

            <div class="panel-medio">
                <?php $ruta = $resumen; ?>
                <?php
                if ($ruta['regreso'] > 0) {
                    $estado = $ruta['regreso'] < $ruta['ida'] ? ['Regreso en curso', 'estado-regreso'] : ['Regreso terminado', 'estado-listo'];
                } elseif ($ruta['ida'] > 0) {
                    $estado = $ruta['ida'] < $ruta['total'] ? ['Ida en curso', 'estado-ida'] : ['Ida completa', 'estado-listo'];
                } else {
                    $estado = ['Sin registros hoy', 'estado-vacio'];
                }
                ?>
                <article class="panel tarjeta-ruta">
                    <header>
                        <p class="etiqueta"><?= e($ruta['etiqueta']) ?></p>
                        <span class="estado <?= $estado[1] ?>"><?= e($estado[0]) ?></span>
                    </header>
                    <h2><?= e($ruta['titulo']) ?></h2>

                    <div class="avance">
                        <p>
                            <span>Ida<?= $ruta['ultima_ida'] ? ' · último ' . e($ruta['ultima_ida']) : '' ?></span>
                            <strong><?= $ruta['ida'] ?> / <?= $ruta['total'] ?></strong>
                        </p>
                        <span class="barra"><span class="barra-ida" style="width: <?= $porcentaje($ruta['ida'], $ruta['total']) ?>%"></span></span>
                    </div>
                    <div class="avance">
                        <p>
                            <span>Regreso<?= $ruta['ultima_regreso'] ? ' · último ' . e($ruta['ultima_regreso']) : '' ?></span>
                            <strong><?= $ruta['regreso'] ?> / <?= $ruta['ida'] ?></strong>
                        </p>
                        <span class="barra"><span class="barra-regreso" style="width: <?= $porcentaje($ruta['regreso'], $ruta['ida']) ?>%"></span></span>
                    </div>

                    <p class="equipo">
                        <?php if ($ruta['auxiliares']): ?>
                            <span><?= e($ruta['auxiliares']) ?></span>
                        <?php else: ?>
                            <span class="texto-alerta">Sin auxiliar</span>
                        <?php endif; ?>
                        · <span class="texto-suave"><?= e($ruta['conductor_nombres'] . ' ' . $ruta['conductor_apellidos']) ?></span>
                    </p>
                </article>

                <section class="panel atender">
                    <p class="etiqueta etiqueta-alerta"><?= e($tituloFaltan) ?></p>
                    <?php if (count($faltan) === 0): ?>
                        <p class="texto-suave">Nadie pendiente por ahora.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach (array_slice($faltan, 0, 5) as $estudiante): ?>
                                <li>
                                    <span class="atender-texto">
                                        <strong><?= e($estudiante['estudiante']) ?></strong>
                                        <small><?= e($estudiante['parada']) ?> · <?= $estudiante['acudiente'] ? e($estudiante['acudiente']) : '<span class="texto-alerta">Sin acudiente</span>' ?></small>
                                    </span>
                                    <?php if ($estudiante['telefono_acudiente']): ?>
                                        <a class="llamar-chico" href="tel:<?= e($estudiante['telefono_acudiente']) ?>" aria-label="Llamar a <?= e($estudiante['acudiente']) ?>"><?= $iconoTelefono ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($faltan) > 5): ?>
                            <a class="enlace" href="<?= e($base) ?>/asistencia/registro?tipo=<?= $enIda ? 'IDA' : 'REGRESO' ?>">+<?= count($faltan) - 5 ?> más · Ver registro</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>

            <section class="panel semana">
                <header>
                    <p class="etiqueta">Esta semana</p>
                    <p class="leyenda-semana"><i class="cuadro-ida"></i> Ida <i class="cuadro-regreso"></i> Regreso · % de estudiantes de mi ruta</p>
                </header>
                <ol class="grafico" aria-label="Asistencia de lunes a viernes">
                    <?php foreach ($semana as $dia): ?>
                        <li class="<?= $dia['hoy'] ? 'dia-hoy' : '' ?>">
                            <span class="barras-dia" aria-hidden="true">
                                <span class="columna columna-ida" style="height: <?= $dia['ida'] ?>%"></span>
                                <?php if ($dia['hoy'] && $dia['regreso'] === 0): ?>
                                    <span class="columna columna-pendiente"></span>
                                <?php else: ?>
                                    <span class="columna columna-regreso" style="height: <?= $dia['regreso'] ?>%"></span>
                                <?php endif; ?>
                            </span>
                            <span class="dia-texto">
                                <?= e($dia['nombre']) ?> ·
                                <?= $dia['futuro'] ? '—' : $dia['ida'] . ' / ' . ($dia['hoy'] && $dia['regreso'] === 0 ? '…' : $dia['regreso']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
