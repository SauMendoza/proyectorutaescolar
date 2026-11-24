<?php
// Panel del administrador. Datos de HomeController::index():
// $fechaTexto, $saludo, $rutas, $total, $conIda, $conRegreso, $estadisticas, $rutasSinAuxiliar, $semana
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$noSubieron = $total - $conIda;
$porcentaje = fn($parte, $todo) => $todo > 0 ? round($parte * 100 / $todo) : 0;

// "Para atender": solo lo que tiene algo pendiente
$pendientes = array_filter([
    ['cantidad' => $noSubieron, 'texto' => 'No subieron hoy', 'enlace' => '/asistencias?ruta=0', 'accion' => 'Ver', 'alerta' => true],
    ['cantidad' => $estadisticas['sin_principal'], 'texto' => 'Sin acudiente principal', 'enlace' => '/estudiantes', 'accion' => 'Ver', 'alerta' => true],
    ['cantidad' => $estadisticas['sin_suplente'], 'texto' => 'Sin acudiente suplente', 'enlace' => '/acudientes', 'accion' => 'Ver', 'alerta' => false],
    ['cantidad' => $rutasSinAuxiliar, 'texto' => $rutasSinAuxiliar === 1 ? 'Ruta sin auxiliar' : 'Rutas sin auxiliar', 'enlace' => '/rutas', 'accion' => 'Asignar', 'alerta' => false],
], fn($item) => $item['cantidad'] > 0);
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
                <h1><?= e($saludo) ?>, coordinación</h1>
            </div>
            <a class="boton boton-borde" href="<?= e($base) ?>/asistencias?ruta=0">Ver asistencias de hoy</a>
        </header>

        <section class="cifras" aria-label="Cifras de hoy">
            <article class="tarjeta">
                <span>Estudiantes con ruta</span>
                <strong><?= $total ?></strong>
            </article>
            <article class="tarjeta">
                <span>Subieron hoy · ida</span>
                <strong><?= $conIda ?> <small>/ <?= $total ?></small></strong>
            </article>
            <article class="tarjeta">
                <span>Bajaron hoy · regreso</span>
                <strong><?= $conRegreso ?> <small>/ <?= $conIda ?></small></strong>
            </article>
            <article class="tarjeta <?= $noSubieron > 0 ? 'tarjeta-alerta' : '' ?>">
                <span>No subieron hoy</span>
                <strong><?= $noSubieron ?></strong>
            </article>
        </section>

        <div class="panel-medio">
            <?php foreach ($rutas as $ruta): ?>
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
            <?php endforeach; ?>

            <section class="panel atender">
                <p class="etiqueta etiqueta-alerta">Para atender</p>
                <?php if (count($pendientes) === 0): ?>
                    <p class="texto-suave">Todo en orden por ahora.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($pendientes as $item): ?>
                            <li>
                                <span class="contador <?= $item['alerta'] ? 'contador-alerta' : '' ?>"><?= (int) $item['cantidad'] ?></span>
                                <span class="atender-texto"><?= e($item['texto']) ?></span>
                                <a class="enlace" href="<?= e($base . $item['enlace']) ?>"><?= e($item['accion']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel semana">
            <header>
                <p class="etiqueta">Esta semana</p>
                <p class="leyenda-semana"><i class="cuadro-ida"></i> Ida <i class="cuadro-regreso"></i> Regreso · % de estudiantes con ruta</p>
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
    </main>
</body>
</html>
