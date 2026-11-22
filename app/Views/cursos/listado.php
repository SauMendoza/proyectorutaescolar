<?php
// Cursos. Datos de CursoController::listado():
// $cursos, $cursoElegido, $estudiantes, $paradasPorRuta, $rutasCurso, $formulario, $mensajeExito
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$totalEstudiantes = count($estudiantes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
    <link rel="stylesheet" href="<?= e($base) ?>/css/cursos.css">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta">Gestión de ruta escolar</p>
                <h1>Cursos</h1>
            </div>
            <button type="button" class="boton boton-amarillo" data-abrir-ventana="ventana-curso">+ Nuevo curso</button>
        </header>

        <?php if ($mensajeExito): ?>
            <p class="aviso aviso-exito" role="status"><?= e($mensajeExito) ?></p>
        <?php endif; ?>

        <div class="cursos">
            <!-- Lista de cursos: cada uno es un enlace con ?curso=ID -->
            <nav class="panel lista-cursos" aria-label="Cursos">
                <p class="lista-titulo"><span>ID · Curso</span><span>Est.</span></p>
                <ul>
                    <?php foreach ($cursos as $curso): ?>
                        <?php $elegido = $cursoElegido && $curso['id_curso'] === $cursoElegido['id_curso']; ?>
                        <li>
                            <a href="<?= e($base) ?>/cursos?curso=<?= (int) $curso['id_curso'] ?>"
                               class="<?= $elegido ? 'activo' : '' ?>" <?= $elegido ? 'aria-current="page"' : '' ?>>
                                <span class="mono"><?= str_pad((string) $curso['id_curso'], 2, '0', STR_PAD_LEFT) ?></span>
                                <strong><?= e($curso['nombre']) ?></strong>
                                <span><?= (int) $curso['estudiantes'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <section class="panel detalle-curso">
                <?php if (!$cursoElegido): ?>
                    <p class="texto-suave">Todavía no hay cursos. Crea el primero con "+ Nuevo curso".</p>
                <?php else: ?>
                    <header class="detalle-cabecera">
                        <div>
                            <p class="etiqueta">ID <?= str_pad((string) $cursoElegido['id_curso'], 2, '0', STR_PAD_LEFT) ?> · <span id="conteo-curso"><?= $totalEstudiantes ?> de <?= $totalEstudiantes ?></span> estudiantes</p>
                            <h2><?= e($cursoElegido['nombre']) ?></h2>
                        </div>
                        <?php if (count($rutasCurso) > 1): ?>
                            <div class="pestanas pestanas-amarillas" role="group" aria-label="Filtrar por ruta" data-total="<?= $totalEstudiantes ?>">
                                <button type="button" class="activo" data-ruta="" aria-pressed="true">Todas</button>
                                <?php foreach ($rutasCurso as $idRuta => $nombreRuta): ?>
                                    <button type="button" data-ruta="<?= (int) $idRuta ?>" aria-pressed="false" title="<?= e($nombreRuta) ?>">Ruta <?= (int) $idRuta ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <?php if ($totalEstudiantes === 0): ?>
                        <p class="texto-suave">Este curso no tiene estudiantes.</p>
                    <?php endif; ?>

                    <?php foreach ($estudiantes as $numero => $estudiante): ?>
                        <?php
                        $paradas = $paradasPorRuta[$estudiante['id_ruta']] ?? [];
                        $numeroParada = 0;
                        foreach ($paradas as $indice => $parada) {
                            if ((int) $parada['id_parada'] === (int) $estudiante['id_parada']) {
                                $numeroParada = $indice + 1;
                            }
                        }
                        ?>
                        <details class="estudiante-curso" data-ruta="<?= (int) $estudiante['id_ruta'] ?>" <?= $numero === 0 ? 'open' : '' ?>>
                            <summary>
                                <span class="avatar" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                                </span>
                                <span class="nombre">
                                    <strong><?= e($estudiante['estudiante']) ?></strong>
                                    <small class="mono"><?= e($estudiante['tipo_documento'] . ' ' . $estudiante['documento']) ?></small>
                                </span>
                                <span class="texto-suave">Parada <?= $numeroParada ?></span>
                            </summary>

                            <div class="estudiante-cuerpo">
                                <dl class="datos-iconos">
                                    <div><dt>Ruta</dt><dd><?= e($estudiante['ruta']) ?></dd></div>
                                    <div><dt>Parada</dt><dd><?= e($estudiante['parada']) ?></dd></div>
                                    <div><dt>Conductor</dt><dd><?= e($estudiante['conductor']) ?></dd></div>
                                    <div>
                                        <dt>Acudiente</dt>
                                        <dd>
                                            <?php if ($estudiante['acudiente']): ?>
                                                <?= e($estudiante['acudiente']) ?> · <?= e($estudiante['parentesco']) ?>
                                                <?php if ($estudiante['telefono_acudiente']): ?>
                                                    <a class="mono texto-amarillo" href="tel:<?= e($estudiante['telefono_acudiente']) ?>"><?= e($estudiante['telefono_acudiente']) ?></a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="texto-alerta">Sin acudiente</span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                </dl>

                                <ol class="recorrido" aria-label="Recorrido de la ruta">
                                    <li class="colegio">Colegio</li>
                                    <?php foreach ($paradas as $indice => $parada): ?>
                                        <?php $esActual = $indice + 1 === $numeroParada; ?>
                                        <li class="<?= $esActual ? 'actual' : '' ?>" title="<?= e($parada['nombre']) ?>">Parada <?= $indice + 1 ?></li>
                                    <?php endforeach; ?>
                                </ol>

                                <a class="enlace ficha-enlace" href="<?= e($base) ?>/estudiantes/ficha?documento=<?= e(urlencode($estudiante['documento'])) ?>">Ficha completa →</a>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Ventana "Nuevo curso": guarda en la tabla curso -->
    <dialog class="ventana ventana-chica" id="ventana-curso" aria-labelledby="titulo-curso" <?= $formulario['error'] ? 'data-abrir' : '' ?>>
        <form action="<?= e($base) ?>/cursos/nuevo" method="POST">
            <?= \App\Core\Csrf::field() ?>
            <div class="ventana-cabecera">
                <div>
                    <p class="etiqueta">Tabla curso</p>
                    <h2 id="titulo-curso">Nuevo curso</h2>
                </div>
                <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
            </div>

            <?php if ($formulario['error']): ?>
                <p class="aviso aviso-error" role="alert"><?= e($formulario['error']) ?></p>
            <?php endif; ?>

            <div class="campos">
                <label class="campo-completo">Nombre del curso
                    <input type="text" name="nombre" value="<?= e($formulario['nombre']) ?>" required maxlength="50" placeholder="Ej. Sexto B" autocomplete="off">
                </label>
            </div>

            <div class="ventana-acciones">
                <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                <button type="submit" class="boton boton-amarillo">Crear curso</button>
            </div>
        </form>
    </dialog>

    <script src="<?= e($base) ?>/js/cursos.js"></script>
</body>
</html>
