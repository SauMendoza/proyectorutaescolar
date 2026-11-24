<?php
// Ficha del acudiente. Datos de AcudienteController::ficha():
// $acudiente, $estudiantes, $parentescos, $parentescosUsados, $mensajeExito, $errorParentesco
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$nombreCompleto = $acudiente['nombre_acu'] . ' ' . $acudiente['apellido_acu'];
$idsParentesco = array_column($parentescos, 'id_parentesco', 'nombre');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($nombreCompleto) ?> · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/app.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/acudientes.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <?php if ($mensajeExito): ?>
            <p class="aviso aviso-exito" role="status"><?= e($mensajeExito) ?></p>
        <?php endif; ?>

        <header class="panel ficha-cabecera">
            <span class="inicial-grande"><?= e(mb_strtoupper(mb_substr($acudiente['nombre_acu'], 0, 1))) ?></span>
            <div>
                <p class="etiqueta"><a href="<?= e($base) ?>/acudientes">Acudientes</a> · Ficha del acudiente</p>
                <h1><?= e($nombreCompleto) ?></h1>
                <div class="insignias">
                    <span class="insignia mono">CC <?= e($acudiente['num_doc_acu']) ?></span>
                    <span class="insignia"><?= (int) $acudiente['estudiantes'] ?> estudiante<?= (int) $acudiente['estudiantes'] === 1 ? '' : 's' ?> a cargo</span>
                    <?php if ($acudiente['telefono']): ?>
                        <span class="insignia insignia-ok">Celular registrado</span>
                    <?php else: ?>
                        <span class="insignia insignia-alerta">Sin celular</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($acudiente['telefono']): ?>
                <a class="boton boton-amarillo" href="tel:<?= e($acudiente['telefono']) ?>">Llamar <?= e($acudiente['telefono']) ?></a>
            <?php endif; ?>
            <button type="button" class="boton boton-borde" data-abrir-ventana="ventana-editar">Editar</button>
        </header>

        <div class="ficha-acudiente">
            <section class="panel">
                <p class="etiqueta">Datos</p>
                <p class="mono texto-suave tabla-nombre">tabla acudiente</p>
                <dl class="datos">
                    <div><dt>Documento</dt><dd class="mono"><?= e($acudiente['num_doc_acu']) ?></dd></div>
                    <div><dt>Nombre</dt><dd><?= e($acudiente['nombre_acu']) ?></dd></div>
                    <div><dt>Apellido</dt><dd><?= e($acudiente['apellido_acu']) ?></dd></div>
                    <div><dt>Cel. emergencia</dt><dd class="mono"><?= $acudiente['telefono'] ? e($acudiente['telefono']) : '<span class="texto-alerta">Sin registrar</span>' ?></dd></div>
                </dl>
            </section>

            <section class="panel">
                <p class="etiqueta">Estudiantes a cargo</p>
                <?php if (count($estudiantes) === 0): ?>
                    <p class="texto-suave">Todavía no tiene estudiantes a cargo. Se asigna desde "Nuevo estudiante".</p>
                <?php else: ?>
                    <div class="a-cargo">
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <?php $esPrincipal = $estudiante['tipo_acudiente'] === 'PRINCIPAL'; ?>
                            <article class="estudiante-tarjeta">
                                <span class="insignia <?= $esPrincipal ? 'insignia-principal' : 'insignia-suplente' ?>"><?= $esPrincipal ? 'Principal' : 'Suplente' ?></span>
                                <a href="<?= e($base) ?>/estudiantes/ficha?documento=<?= e(urlencode($estudiante['num_doc_estu'])) ?>">
                                    <strong><?= e($estudiante['estudiante']) ?></strong>
                                </a>
                                <span class="mono texto-suave"><?= e($estudiante['tipo_doc_estu'] . ' ' . $estudiante['num_doc_estu']) ?> · <?= e($estudiante['curso']) ?></span>
                                <span><?= e($estudiante['parada']) ?> · <?= e($estudiante['ruta']) ?></span>
                                <span class="parentesco-linea">Parentesco: <strong><?= e($estudiante['parentesco']) ?></strong></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <p class="etiqueta">Parentescos</p>
                <ul class="catalogo" aria-label="Catálogo de parentescos">
                    <?php foreach ($parentescos as $parentesco): ?>
                        <?php $usado = in_array($parentesco['nombre'], $parentescosUsados, true); ?>
                        <li class="<?= $usado ? 'usado' : '' ?>" <?= $usado ? 'title="Lo usa este acudiente"' : '' ?>><?= e($parentesco['nombre']) ?></li>
                    <?php endforeach; ?>
                </ul>

                <form class="agregar-parentesco" action="<?= e($base) ?>/acudientes/parentesco" method="POST">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="documento" value="<?= e($acudiente['num_doc_acu']) ?>">
                    <label class="buscador">
                        <span class="visualmente-oculto">Nuevo parentesco</span>
                        <input type="text" name="nombre" placeholder="Nuevo parentesco (ej. Padrino)" maxlength="30" required>
                    </label>
                    <button type="submit" class="boton boton-borde boton-chico">+ Agregar</button>
                </form>
                <?php if ($errorParentesco): ?>
                    <p class="texto-alerta" role="alert"><?= e($errorParentesco) ?></p>
                <?php endif; ?>
            </section>

            <section class="panel panel-oscuro">
                <p class="etiqueta">Cómo se guarda · acudiente_estudiante</p>
                <?php if (count($estudiantes) === 0): ?>
                    <p class="texto-suave">Sin filas todavía.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-codigo">
                            <thead>
                                <tr><th>num_doc_acu</th><th>num_doc_estu</th><th>parentesco</th><th>tipo</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                    <tr>
                                        <td><?= e($acudiente['num_doc_acu']) ?></td>
                                        <td><?= e($estudiante['num_doc_estu']) ?></td>
                                        <td><?= e(($idsParentesco[$estudiante['parentesco']] ?? '?') . ' · ' . $estudiante['parentesco']) ?></td>
                                        <td class="<?= $estudiante['tipo_acudiente'] === 'PRINCIPAL' ? 'texto-amarillo' : '' ?>"><?= e(strtolower($estudiante['tipo_acudiente'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php
    $editado = $edicion['datos'] ?? [
        'nombre' => $acudiente['nombre_acu'], 'apellido' => $acudiente['apellido_acu'], 'telefono' => $acudiente['telefono'],
    ];
    ?>
    <dialog class="ventana" id="ventana-editar" aria-labelledby="titulo-editar" <?= $edicion ? 'data-abrir' : '' ?>>
        <form action="<?= e($base) ?>/acudientes/editar" method="POST">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="documento" value="<?= e($acudiente['num_doc_acu']) ?>">
            <div class="ventana-cabecera">
                <div>
                    <p class="etiqueta">CC <?= e($acudiente['num_doc_acu']) ?></p>
                    <h2 id="titulo-editar">Editar acudiente</h2>
                </div>
                <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
            </div>

            <?php if ($edicion): ?>
                <div class="aviso aviso-error" role="alert">
                    Revisa estos datos:
                    <ul>
                        <?php foreach ($edicion['errores'] as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="campos">
                <label>Nombre
                    <input type="text" name="nombre" value="<?= e($editado['nombre']) ?>" required maxlength="100" autocomplete="off">
                </label>
                <label>Apellido
                    <input type="text" name="apellido" value="<?= e($editado['apellido']) ?>" required maxlength="100" autocomplete="off">
                </label>
                <label class="campo-completo">Celular de emergencia (opcional)
                    <input type="tel" name="telefono" value="<?= e($editado['telefono']) ?>" maxlength="20" placeholder="301 100 0001" autocomplete="off">
                </label>
            </div>
            <p class="nota">Sus estudiantes a cargo no cambian. El parentesco se cambia desde la ficha del estudiante.</p>

            <div class="ventana-acciones">
                <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                <button type="submit" class="boton boton-amarillo">Guardar cambios</button>
            </div>
        </form>
    </dialog>

    <script src="<?= \App\Core\Url::recurso('/js/acudientes.js') ?>"></script>
</body>
</html>
