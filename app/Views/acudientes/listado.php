<?php
// Listado de acudientes. Datos de AcudienteController::listado():
// $acudientes, $aCargo, $sinSuplente, $formulario (errores y datos de "Nuevo acudiente")
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$errores = $formulario['errores'];
$valor = fn($campo) => $formulario['datos'][$campo] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acudientes · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/app.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/acudientes.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta">Gestión de ruta escolar</p>
                <h1>Acudientes</h1>
                <p class="subtitulo">
                    <?= count($acudientes) ?> registrados
                    <?php if ($sinSuplente > 0): ?>
                        · <span class="texto-alerta"><?= (int) $sinSuplente ?> estudiante<?= $sinSuplente > 1 ? 's' : '' ?> sin suplente</span>
                    <?php endif; ?>
                </p>
            </div>
            <button type="button" class="boton boton-amarillo" data-abrir-ventana="ventana-acudiente">+ Nuevo acudiente</button>
        </header>

        <section class="filtros" aria-label="Filtros">
            <label class="buscador">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="filtro-texto" placeholder="Buscar acudiente o estudiante" aria-label="Buscar acudiente o estudiante">
            </label>
            <div class="pestanas-linea" role="group" aria-label="Tipo de acudiente">
                <button type="button" class="activo" data-tipo="" aria-pressed="true">Todos</button>
                <button type="button" data-tipo="PRINCIPAL" aria-pressed="false">Principales</button>
                <button type="button" data-tipo="SUPLENTE" aria-pressed="false">Suplentes</button>
            </div>
        </section>

        <section class="panel-tabla">
            <?php if (count($acudientes) > 0): ?>
                <div class="tabla-contenedor">
                    <table id="tabla-acudientes">
                        <thead>
                            <tr>
                                <th>Acudiente</th>
                                <th>Celular</th>
                                <th>Estudiante a cargo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($acudientes as $acudiente): ?>
                                <?php
                                $relaciones = $aCargo[$acudiente['num_doc_acu']] ?? [];
                                $tipos = array_unique(array_column($relaciones, 'tipo_acudiente'));
                                $buscar = $acudiente['nombre_acu'] . ' ' . $acudiente['apellido_acu'] . ' ' . $acudiente['num_doc_acu']
                                    . ' ' . implode(' ', array_column($relaciones, 'estudiante'));
                                ?>
                                <tr data-buscar="<?= e($buscar) ?>" data-tipos="<?= e(implode(' ', $tipos)) ?>">
                                    <td>
                                        <a href="<?= e($base) ?>/acudientes/ficha?documento=<?= e(urlencode($acudiente['num_doc_acu'])) ?>">
                                            <strong><?= e($acudiente['nombre_acu'] . ' ' . $acudiente['apellido_acu']) ?></strong>
                                        </a>
                                        <small class="mono">CC <?= e($acudiente['num_doc_acu']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($acudiente['telefono']): ?>
                                            <a class="mono" href="tel:<?= e($acudiente['telefono']) ?>"><?= e($acudiente['telefono']) ?></a>
                                        <?php else: ?>
                                            <span class="texto-alerta">Sin celular</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (count($relaciones) === 0): ?>
                                            <span class="texto-suave">Sin estudiantes a cargo</span>
                                        <?php else: ?>
                                            <?php $primera = $relaciones[0]; ?>
                                            <?= e($primera['estudiante']) ?> ·
                                            <?php if ($primera['tipo_acudiente'] === 'PRINCIPAL'): ?>
                                                <span class="texto-amarillo"><?= e($primera['parentesco']) ?></span>
                                            <?php else: ?>
                                                <span class="texto-suave"><?= e($primera['parentesco']) ?>, suplente</span>
                                            <?php endif; ?>
                                            <?php if (count($relaciones) > 1): ?>
                                                <span class="texto-suave">+<?= count($relaciones) - 1 ?> más</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="sin-resultados" id="sin-resultados" hidden>Ningún acudiente coincide con la búsqueda.</p>
                <footer class="pie-tabla">
                    <span id="resumen-tabla">Mostrando <?= count($acudientes) ?> acudientes</span>
                    <nav class="paginas" id="paginas" aria-label="Páginas"></nav>
                </footer>
            <?php else: ?>
                <p class="sin-resultados">No hay acudientes registrados.</p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Ventana "Nuevo acudiente": guarda en la tabla acudiente -->
    <dialog class="ventana" id="ventana-acudiente" aria-labelledby="titulo-ventana" <?= $errores ? 'data-abrir' : '' ?>>
        <form action="<?= e($base) ?>/acudientes/nuevo" method="POST">
            <?= \App\Core\Csrf::field() ?>
            <div class="ventana-cabecera">
                <div>
                    <p class="etiqueta">Tabla acudiente</p>
                    <h2 id="titulo-ventana">Nuevo acudiente</h2>
                </div>
                <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
            </div>

            <?php if ($errores): ?>
                <div class="aviso aviso-error" role="alert">
                    Revisa estos datos:
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="campos">
                <label class="campo-completo">Documento
                    <input type="text" name="documento" value="<?= e($valor('documento')) ?>" required maxlength="20" pattern="[A-Za-z0-9]{4,20}" inputmode="numeric" autocomplete="off">
                </label>
                <label>Nombre
                    <input type="text" name="nombre" value="<?= e($valor('nombre')) ?>" required maxlength="100" autocomplete="off">
                </label>
                <label>Apellido
                    <input type="text" name="apellido" value="<?= e($valor('apellido')) ?>" required maxlength="100" autocomplete="off">
                </label>
                <label class="campo-completo">Celular de emergencia (opcional)
                    <input type="tel" name="telefono" value="<?= e($valor('telefono')) ?>" maxlength="20" placeholder="301 100 0001" autocomplete="off">
                </label>
            </div>
            <p class="nota">Después de crearlo, asígnalo a un estudiante desde "Nuevo estudiante" buscándolo por documento.</p>

            <div class="ventana-acciones">
                <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                <button type="submit" class="boton boton-amarillo">Guardar acudiente</button>
            </div>
        </form>
    </dialog>

    <script src="<?= \App\Core\Url::recurso('/js/acudientes.js') ?>"></script>
</body>
</html>
