<?php
// Rutas. Datos de RutaController::listado():
// $rutas, $paradasPorRuta, $conductores, $auxiliares, $esAdmin, $formulario, $mensajeExito
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

// "Ruta 1 · Vía Paraguachón" se muestra como etiqueta "Ruta 1" y título "Vía Paraguachón"
$partesNombre = function (array $ruta): array {
    $partes = explode(' · ', $ruta['nombre'], 2);
    return count($partes) === 2 ? $partes : ['Ruta ' . $ruta['id_ruta'], $ruta['nombre']];
};

$nombresRuta = array_column($rutas, 'nombre', 'id_ruta');
$totalEstudiantes = array_sum(array_column($rutas, 'estudiantes'));
$iconoBus = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="14" rx="3"/><path d="M4 10h16M8 17v3M16 17v3M8 13.5h.01M16 13.5h.01"/></svg>';

// Datos con los que se llena cada ventana: los de la base o, si hubo errores, lo que se escribió
$datosVentana = [];
if ($esAdmin) {
    foreach ($rutas as $ruta) {
        $auxiliaresRuta = array_values(array_filter($auxiliares, fn($a) => (int) $a['fk_id_ruta'] === (int) $ruta['id_ruta']));
        $datosVentana[$ruta['id_ruta']] = [
            'id_ruta' => $ruta['id_ruta'],
            'nombre' => $ruta['nombre'],
            'conductor' => (string) $ruta['num_doc_conductor'],
            'auxiliar' => '',
            'tiene_auxiliar' => count($auxiliaresRuta) > 0,
            'paradas' => $paradasPorRuta[$ruta['id_ruta']] ?? [],
            'errores' => [],
        ];
    }
    $datosVentana['nueva'] = [
        'id_ruta' => '', 'nombre' => '', 'conductor' => '', 'auxiliar' => '', 'tiene_auxiliar' => false,
        'paradas' => [['id_parada' => '', 'nombre' => '', 'comunidad' => '', 'km_referencia' => '', 'estudiantes' => 0]],
        'errores' => [],
    ];

    if ($formulario) {
        $clave = $formulario['datos']['id_ruta'] === '' ? 'nueva' : (int) $formulario['datos']['id_ruta'];
        if (isset($datosVentana[$clave])) {
            $estudiantesParada = array_column($datosVentana[$clave]['paradas'], 'estudiantes', 'id_parada');
            foreach ($formulario['datos']['paradas'] as &$parada) {
                $parada['estudiantes'] = $estudiantesParada[$parada['id_parada']] ?? 0;
                //Una parada con estudiantes no se puede quitar: vuelve a mostrarse
                if ($parada['estudiantes'] > 0) {
                    $parada['quitar'] = false;
                }
            }
            unset($parada);
            $datosVentana[$clave] = array_merge($datosVentana[$clave], $formulario['datos'], [
                'errores' => $formulario['errores'],
                'abrir' => true,
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esAdmin ? 'Rutas' : 'Mi ruta' ?> · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
    <link rel="stylesheet" href="<?= e($base) ?>/css/rutas.css">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta">Gestión de ruta escolar</p>
                <h1><?= $esAdmin ? 'Rutas' : 'Mi ruta' ?></h1>
                <p class="subtitulo"><?= count($rutas) ?> ruta<?= count($rutas) === 1 ? '' : 's' ?> · <?= (int) $totalEstudiantes ?> estudiantes</p>
            </div>
            <?php if ($esAdmin): ?>
                <button type="button" class="boton boton-borde" data-abrir-ventana="ventana-ruta-nueva">+ Nueva ruta</button>
            <?php endif; ?>
        </header>

        <?php if ($mensajeExito): ?>
            <p class="aviso aviso-exito" role="status"><?= e($mensajeExito) ?></p>
        <?php endif; ?>

        <?php if (count($rutas) === 0): ?>
            <p class="panel texto-suave">No tienes una ruta asignada. Pide a la administración que te asigne una.</p>
        <?php endif; ?>

        <div class="rutas">
            <?php foreach ($rutas as $ruta): ?>
                <?php [$etiquetaRuta, $tituloRuta] = $partesNombre($ruta); ?>
                <article class="panel ruta-tarjeta">
                    <header class="ruta-cabecera">
                        <span class="icono-bus"><?= $iconoBus ?></span>
                        <div>
                            <p class="etiqueta"><?= e($etiquetaRuta) ?></p>
                            <h2><?= e($tituloRuta) ?></h2>
                        </div>
                        <?php if ($esAdmin): ?>
                            <button type="button" class="boton boton-amarillo boton-chico" data-abrir-ventana="ventana-ruta-<?= (int) $ruta['id_ruta'] ?>">Editar</button>
                        <?php endif; ?>
                    </header>

                    <div class="personas">
                        <div class="persona">
                            <span>Conductor</span>
                            <strong><?= e($ruta['conductor_nombres'] . ' ' . $ruta['conductor_apellidos']) ?></strong>
                            <?php if ($ruta['conductor_telefono']): ?>
                                <a class="mono" href="tel:<?= e($ruta['conductor_telefono']) ?>"><?= e($ruta['conductor_telefono']) ?></a>
                            <?php endif; ?>
                        </div>
                        <div class="persona <?= $ruta['auxiliares'] ? '' : 'persona-alerta' ?>">
                            <span>Auxiliar</span>
                            <strong><?= $ruta['auxiliares'] ? e($ruta['auxiliares']) : 'Sin asignar' ?></strong>
                        </div>
                    </div>

                    <p class="etiqueta etiqueta-gris">Recorrido de ida</p>
                    <ol class="paradas">
                        <?php foreach ($paradasPorRuta[$ruta['id_ruta']] ?? [] as $numero => $parada): ?>
                            <li>
                                <span class="numero"><?= $numero + 1 ?></span>
                                <span class="parada-nombre">
                                    <?= e($parada['nombre']) ?>
                                    <small><?= e($parada['comunidad']) ?><?= $parada['km_referencia'] !== null ? ' · km ' . e((float) $parada['km_referencia']) : '' ?></small>
                                </span>
                                <span class="texto-suave"><?= (int) $parada['estudiantes'] ?> est.</span>
                            </li>
                        <?php endforeach; ?>
                        <li class="colegio">
                            <span class="numero">C</span>
                            <span class="parada-nombre">Colegio</span>
                        </li>
                    </ol>

                    <footer class="ruta-pie">
                        <span><strong><?= (int) $ruta['estudiantes'] ?></strong> estudiantes</span>
                        <a class="enlace" href="<?= e($base) ?>/estudiantes?ruta=<?= e(urlencode($ruta['nombre'])) ?>">Ver estudiantes →</a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

    <?php if ($esAdmin): ?>
        <?php foreach ($datosVentana as $clave => $datos): ?>
            <?php
            $esNueva = $clave === 'nueva';
            $idVentana = 'ventana-ruta-' . $clave;
            $conductorNuevo = $datos['conductor'] === 'nuevo';
            ?>
            <dialog class="ventana ventana-ruta" id="<?= e($idVentana) ?>" aria-labelledby="titulo-<?= e($idVentana) ?>" <?= !empty($datos['abrir']) ? 'data-abrir' : '' ?>>
                <form action="<?= e($base) ?>/rutas/guardar" method="POST" novalidate>
                    <input type="hidden" name="id_ruta" value="<?= e($datos['id_ruta']) ?>">

                    <div class="ventana-cabecera">
                        <span class="icono-bus"><?= $iconoBus ?></span>
                        <div class="ventana-titulo">
                            <p class="etiqueta"><?= $esNueva ? 'Nueva ruta' : 'Editar ' . e($partesNombre(['id_ruta' => $clave, 'nombre' => $nombresRuta[$clave]])[0]) ?></p>
                            <h2 id="titulo-<?= e($idVentana) ?>"><?= $esNueva ? 'Datos de la ruta' : e($partesNombre(['id_ruta' => $clave, 'nombre' => $nombresRuta[$clave]])[1]) ?></h2>
                        </div>
                        <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
                    </div>

                    <?php if ($datos['errores']): ?>
                        <div class="aviso aviso-error" role="alert">
                            Revisa estos datos:
                            <ul>
                                <?php foreach ($datos['errores'] as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="campos">
                        <label class="campo-completo">Nombre de la ruta *
                            <input type="text" name="nombre" value="<?= e($datos['nombre']) ?>" required maxlength="50" placeholder="Ruta 3 · Vía Uribia" autocomplete="off">
                        </label>

                        <div class="campo-grupo">
                            <label>Conductor *
                                <select name="conductor" class="elegir-conductor" required>
                                    <option value="">Elige un conductor</option>
                                    <?php foreach ($conductores as $conductor): ?>
                                        <?php
                                        $esDeEstaRuta = !$esNueva && (int) $conductor['id_ruta'] === (int) $clave;
                                        $ocupado = $conductor['id_ruta'] !== null && !$esDeEstaRuta;
                                        ?>
                                        <option value="<?= e($conductor['num_doc_conductor']) ?>"
                                            <?= (string) $conductor['num_doc_conductor'] === $datos['conductor'] ? 'selected' : '' ?>
                                            <?= $ocupado ? 'disabled' : '' ?>>
                                            <?= e($conductor['nombres'] . ' ' . $conductor['apellidos']) ?><?= $ocupado ? ' (maneja otra ruta)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="nuevo" <?= $conductorNuevo ? 'selected' : '' ?>>+ Registrar conductor</option>
                                </select>
                            </label>
                        </div>

                        <div class="campo-grupo">
                            <label>Auxiliar asignado
                                <select name="auxiliar">
                                    <option value=""><?= $datos['tiene_auxiliar'] ? 'Sin cambios' : 'Sin asignar' ?></option>
                                    <?php foreach ($auxiliares as $auxiliar): ?>
                                        <?php $enOtra = $esNueva || (int) $auxiliar['fk_id_ruta'] !== (int) $clave; ?>
                                        <option value="<?= (int) $auxiliar['id_usuario'] ?>" <?= (string) $auxiliar['id_usuario'] === (string) $datos['auxiliar'] ? 'selected' : '' ?>>
                                            <?= e($auxiliar['nombre'] . ' ' . $auxiliar['apellido']) ?><?php if (!$enOtra): ?> (ya asignado)<?php elseif ($auxiliar['fk_id_ruta'] === null): ?> (sin ruta)<?php else: ?> (pasa desde <?= e($partesNombre(['id_ruta' => $auxiliar['fk_id_ruta'], 'nombre' => $nombresRuta[$auxiliar['fk_id_ruta']] ?? ''])[0]) ?>)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <small class="texto-suave">Solo usuarios con rol Auxiliar</small>
                        </div>

                        <fieldset class="conductor-nuevo campo-completo" <?= $conductorNuevo ? '' : 'hidden' ?>>
                            <legend>Conductor nuevo</legend>
                            <div class="campos">
                                <label>Documento
                                    <input type="text" name="conductor_documento" value="<?= e($datos['conductor_documento'] ?? '') ?>" inputmode="numeric" maxlength="9" autocomplete="off">
                                </label>
                                <label>Celular (opcional)
                                    <input type="tel" name="conductor_telefono" value="<?= e($datos['conductor_telefono'] ?? '') ?>" maxlength="20" autocomplete="off">
                                </label>
                                <label>Nombres
                                    <input type="text" name="conductor_nombres" value="<?= e($datos['conductor_nombres'] ?? '') ?>" maxlength="100" autocomplete="off">
                                </label>
                                <label>Apellidos
                                    <input type="text" name="conductor_apellidos" value="<?= e($datos['conductor_apellidos'] ?? '') ?>" maxlength="100" autocomplete="off">
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <p class="titulo-paradas">Paradas en orden de recogida *</p>
                    <ol class="editar-paradas" data-siguiente="<?= count($datos['paradas']) ?>">
                        <?php foreach ($datos['paradas'] as $i => $parada): ?>
                            <?php $conEstudiantes = (int) $parada['estudiantes'] > 0; ?>
                            <li class="fila-parada" <?= !empty($parada['quitar']) ? 'hidden' : '' ?>>
                                <span class="numero"><?= $i + 1 ?></span>
                                <input type="hidden" name="paradas[<?= $i ?>][id]" value="<?= e($parada['id_parada']) ?>">
                                <input type="checkbox" class="quitar" name="paradas[<?= $i ?>][quitar]" value="1" hidden <?= !empty($parada['quitar']) ? 'checked' : '' ?>>
                                <input type="text" name="paradas[<?= $i ?>][nombre]" value="<?= e($parada['nombre']) ?>" placeholder="Nombre de la parada" aria-label="Nombre de la parada" maxlength="100">
                                <input type="text" name="paradas[<?= $i ?>][comunidad]" value="<?= e($parada['comunidad']) ?>" placeholder="Comunidad" aria-label="Comunidad" maxlength="100">
                                <input type="text" class="km" name="paradas[<?= $i ?>][km]" value="<?= e(is_numeric($parada['km_referencia']) ? (float) $parada['km_referencia'] : (string) $parada['km_referencia']) ?>" placeholder="km" aria-label="Kilómetro" inputmode="decimal" maxlength="7">
                                <span class="texto-suave est"><?= (int) $parada['estudiantes'] ?> est.</span>
                                <button type="button" class="quitar-parada" aria-label="Quitar parada"
                                    <?= $conEstudiantes ? 'disabled title="Tiene estudiantes: cámbialos de parada antes de quitarla"' : '' ?>>×</button>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <button type="button" class="agregar-parada">+ Agregar parada</button>
                    <p class="nota">El orden lo da el km desde el colegio. En el regreso (Colegio → Casa) el orden se invierte solo.</p>

                    <div class="ventana-acciones">
                        <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                        <button type="submit" class="boton boton-amarillo">Guardar ruta</button>
                    </div>
                </form>
            </dialog>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="<?= e($base) ?>/js/rutas.js"></script>
</body>
</html>
