<?php
// Ruta base del proyecto para enlazar el CSS, el JS y el formulario
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Escapa un texto antes de mostrarlo en el HTML
if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

// Valor que se había escrito antes (cuando el formulario vuelve con errores)
function anterior(array $datos, string $campo): string
{
    return e($datos[$campo] ?? '');
}

// Marca la opción elegida antes en un select o radio
function elegido(array $datos, string $campo, $valor, string $atributo = 'selected'): string
{
    return (string) ($datos[$campo] ?? '') === (string) $valor ? $atributo : '';
}

// Las paradas se agrupan por ruta para el select del paso 2
$paradasPorRuta = [];
foreach ($paradas as $parada) {
    $paradasPorRuta[$parada['fk_id_ruta']][] = $parada;
}


$pasos = [
    1 => 'Estudiante',
    2 => 'Transporte',
    3 => 'Acudiente principal',
    4 => 'Acudiente suplente',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo estudiante · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
    <link rel="stylesheet" href="<?= e($base) ?>/css/estudiantes.css">
    <!-- Con JavaScript se muestra un paso a la vez; sin él, el formulario sale completo -->
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
    <!-- Panel lateral: logo, menú por rol y usuario -->
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta"><a href="<?= e($base) ?>/estudiantes">Estudiantes</a></p>
                <h1>Nuevo estudiante</h1>
                <p class="subtitulo">Los campos con * son obligatorios</p>
            </div>
        </header>

        <?php if ($errores): ?>
            <div class="aviso aviso-error" role="alert">
                <strong>Revisa estos datos:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Indicador de pasos -->
        <ol class="pasos" id="pasos">
            <?php foreach ($pasos as $numero => $titulo): ?>
                <li>
                    <button type="button" data-ir="<?= $numero ?>">
                        <span class="numero"><?= $numero ?></span><?= e($titulo) ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="registro">
            <form class="panel-form" id="form-nuevo" action="<?= e($base) ?>/estudiantes/nuevo" method="POST"
                  data-base="<?= e($base) ?>" data-paso-inicial="<?= (int) $pasoInicial ?>" novalidate>

                <!-- Paso 1: estudiante -->
                <section class="paso" data-paso="1">
                    <p class="etiqueta">Paso 1 de 4</p>
                    <h2>Estudiante</h2>
                    <div class="barras" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

                    <div class="campos">
                        <label>Tipo de documento *
                            <select name="tipo_documento" required>
                                <option value="">Elige…</option>
                                <?php foreach ($tiposDocumento as $sigla => $nombre): ?>
                                    <option value="<?= e($sigla) ?>" <?= elegido($datos, 'tipo_documento', $sigla) ?>><?= e($sigla . ' · ' . $nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Número de documento *
                            <input type="text" name="documento" value="<?= anterior($datos, 'documento') ?>" required
                                   pattern="[A-Za-z0-9]{4,20}" maxlength="20" inputmode="numeric" class="mono">
                        </label>
                        <label>Primer nombre *
                            <input type="text" name="nombre" value="<?= anterior($datos, 'nombre') ?>" required maxlength="45">
                        </label>
                        <label>Segundo nombre
                            <input type="text" name="segundo_nombre" value="<?= anterior($datos, 'segundo_nombre') ?>" maxlength="45">
                        </label>
                        <label>Primer apellido *
                            <input type="text" name="apellido" value="<?= anterior($datos, 'apellido') ?>" required maxlength="45">
                        </label>
                        <label>Segundo apellido
                            <input type="text" name="segundo_apellido" value="<?= anterior($datos, 'segundo_apellido') ?>" maxlength="45">
                        </label>
                        <label>Fecha de nacimiento *
                            <input type="date" name="fecha_nacimiento" value="<?= anterior($datos, 'fecha_nacimiento') ?>" required max="<?= date('Y-m-d') ?>">
                        </label>
                        <label>Curso *
                            <select name="curso" required>
                                <option value="">Elige…</option>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?= e($curso['id_curso']) ?>" <?= elegido($datos, 'curso', $curso['id_curso']) ?>><?= e($curso['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </section>

                <!-- Paso 2: transporte -->
                <section class="paso" data-paso="2">
                    <p class="etiqueta">Paso 2 de 4</p>
                    <h2>Transporte</h2>
                    <div class="barras" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

                    <div class="campos">
                        <label>Ruta *
                            <select name="ruta" id="campo-ruta" required>
                                <option value="">Elige…</option>
                                <?php foreach ($rutas as $ruta): ?>
                                    <option value="<?= e($ruta['id_ruta']) ?>" data-conductor="<?= e($ruta['conductor']) ?>" <?= elegido($datos, 'ruta', $ruta['id_ruta']) ?>><?= e($ruta['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Parada *
                            <select name="parada" id="campo-parada" required>
                                <option value="">Elige…</option>
                                <?php foreach ($rutas as $ruta): ?>
                                    <optgroup label="<?= e($ruta['nombre']) ?>" data-ruta="<?= e($ruta['id_ruta']) ?>">
                                        <?php foreach ($paradasPorRuta[$ruta['id_ruta']] ?? [] as $i => $parada): ?>
                                            <option value="<?= e($parada['id_parada']) ?>"
                                                    data-orden="<?= $i + 1 ?> de <?= count($paradasPorRuta[$ruta['id_ruta']]) ?>"
                                                    <?= elegido($datos, 'parada', $parada['id_parada']) ?>><?= e($parada['nombre'] . ' · ' . $parada['comunidad']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <p class="nota">Las paradas salen en el orden del recorrido de ida.</p>
                </section>

                <!-- Pasos 3 y 4: acudientes principal y suplente -->
                <?php foreach (['principal' => 3, 'suplente' => 4] as $tipo => $numero): ?>
                    <section class="paso" data-paso="<?= $numero ?>">
                        <p class="etiqueta">Paso <?= $numero ?> de 4</p>
                        <h2>Acudiente <?= $tipo ?></h2>
                        <div class="barras" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

                        <div class="acudiente" data-tipo="<?= $tipo ?>" data-obligatorio="<?= $tipo === 'principal' ? '1' : '0' ?>">
                            <label>Documento del acudiente <?= $tipo === 'principal' ? '*' : '(opcional)' ?>
                                <span class="buscar-acudiente">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                    <input type="text" name="<?= $tipo ?>_documento" value="<?= anterior($datos, $tipo . '_documento') ?>"
                                           maxlength="20" inputmode="numeric" class="mono" autocomplete="off">
                                    <button type="button" class="boton boton-borde" data-accion="buscar">Buscar</button>
                                </span>
                            </label>

                            <!-- Si el acudiente ya existe, aparece esta tarjeta -->
                            <div class="encontrado" hidden>
                                <span class="inicial"></span>
                                <div>
                                    <strong class="encontrado-nombre"></strong>
                                    <small class="encontrado-detalle"></small>
                                </div>
                                <button type="button" class="boton boton-amarillo" data-accion="usar">Usar</button>
                            </div>

                            <!-- Si no existe, se piden sus datos para crearlo -->
                            <div class="campos campos-nuevo">
                                <label>Nombre *
                                    <input type="text" name="<?= $tipo ?>_nombre" value="<?= anterior($datos, $tipo . '_nombre') ?>" maxlength="100">
                                </label>
                                <label>Apellido *
                                    <input type="text" name="<?= $tipo ?>_apellido" value="<?= anterior($datos, $tipo . '_apellido') ?>" maxlength="100">
                                </label>
                                <label>Celular
                                    <input type="tel" name="<?= $tipo ?>_telefono" value="<?= anterior($datos, $tipo . '_telefono') ?>" maxlength="20" class="mono">
                                </label>
                            </div>

                            <fieldset class="parentescos">
                                <legend>Parentesco con el estudiante <?= $tipo === 'principal' ? '*' : '' ?></legend>
                                <div class="opciones">
                                    <?php foreach ($parentescos as $parentesco): ?>
                                        <label>
                                            <input type="radio" name="<?= $tipo ?>_parentesco" value="<?= e($parentesco['id_parentesco']) ?>"
                                                   data-nombre="<?= e($parentesco['nombre']) ?>"
                                                   <?= elegido($datos, $tipo . '_parentesco', $parentesco['id_parentesco'], 'checked') ?>>
                                            <span><?= e($parentesco['nombre']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <p class="nota nota-alerta">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 21h20L12 3z"/><path d="M12 10v5M12 18v.01"/></svg>
                                <?= $tipo === 'principal'
                                    ? 'Si el documento no existe, aparecen los campos para crear el acudiente.'
                                    : 'Déjalo vacío si el estudiante no tiene acudiente suplente.' ?>
                            </p>
                        </div>
                    </section>
                <?php endforeach; ?>

                <p class="error-paso" id="error-paso" role="alert" hidden></p>

                <footer class="acciones">
                    <button type="button" class="boton boton-borde" data-accion="atras">Atrás</button>
                    <button type="button" class="boton boton-amarillo" data-accion="siguiente">Continuar</button>
                    <button type="submit" class="boton boton-amarillo" data-accion="guardar">Guardar estudiante</button>
                </footer>
            </form>

            <!-- Resumen de lo que se va a guardar (lo llena public/js/estudiantes.js) -->
            <aside class="resumen" aria-label="Resumen">
                <p class="etiqueta">Resumen</p>
                <dl class="datos">
                    <div class="resumen-estudiante"><dt>Estudiante</dt><dd id="r-estudiante">—</dd></div>
                    <div><dt>Documento</dt><dd id="r-documento" class="mono">—</dd></div>
                    <div><dt>Nacimiento · curso</dt><dd id="r-nacimiento">—</dd></div>
                    <div><dt>Ruta</dt><dd id="r-ruta">—</dd></div>
                    <div><dt>Parada</dt><dd id="r-parada">—</dd></div>
                    <div><dt>Conductor</dt><dd id="r-conductor">—</dd></div>
                    <div><dt>Principal</dt><dd id="r-principal">Pendiente</dd></div>
                    <div><dt>Suplente</dt><dd id="r-suplente">Pendiente</dd></div>
                </dl>
            </aside>
        </div>
    </main>

    <script src="<?= e($base) ?>/js/estudiantes.js"></script>
</body>
</html>
