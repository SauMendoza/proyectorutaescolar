<?php
// Auxiliares (solo administrador). Datos de AuxiliarController::listado():
// $auxiliares, $rutas, $formulario (errores y lo que se escribió), $mensajeExito
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$datos = $formulario['datos'] + [
    'nombre' => '', 'apellido' => '', 'documento' => '', 'telefono' => '',
    'fecha_ingreso' => date('Y-m-d'), 'correo' => '', 'cambiar_contrasena' => true,
    'id_ruta' => $rutas[0]['id_ruta'] ?? null,
];
$rutaElegida = (int) ($datos['id_ruta'] ?? 0);

//"Entró hoy 06:02", "Entró ayer 18:40", "Entró el 12/11 · 07:15" o "Aún no ha entrado"
$textoIngreso = function (?string $fechaHora): string {
    if (!$fechaHora) {
        return 'Aún no ha entrado';
    }
    $momento = strtotime($fechaHora);
    $dia = date('Y-m-d', $momento);
    $hora = date('H:i', $momento);
    if ($dia === date('Y-m-d')) {
        return 'Entró hoy ' . $hora;
    }
    if ($dia === date('Y-m-d', strtotime('-1 day'))) {
        return 'Entró ayer ' . $hora;
    }
    return 'Entró el ' . date('d/m', $momento) . ' · ' . $hora;
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auxiliares · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/app.css">
    <link rel="stylesheet" href="<?= e($base) ?>/css/auxiliares.css">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <header class="cabecera">
            <div>
                <p class="etiqueta">Usuarios</p>
                <h1>Auxiliares</h1>
            </div>
        </header>

        <?php if ($mensajeExito): ?>
            <p class="aviso aviso-exito" role="status"><?= e($mensajeExito) ?></p>
        <?php endif; ?>

        <div class="auxiliares">
            <section class="lista-auxiliares" aria-label="Auxiliares registrados">
                <?php if (!$auxiliares): ?>
                    <p class="panel texto-suave">Todavía no hay auxiliares. Crea el primero con el formulario.</p>
                <?php endif; ?>

                <?php foreach ($auxiliares as $auxiliar): ?>
                    <article class="panel tarjeta-auxiliar">
                        <div class="auxiliar-cabecera">
                            <span class="inicial inicial-azul" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($auxiliar['nombre'], 0, 1))) ?></span>
                            <div>
                                <strong><?= e($auxiliar['nombre'] . ' ' . $auxiliar['apellido']) ?></strong>
                                <span class="texto-suave"><?= e($auxiliar['correo']) ?></span>
                            </div>
                        </div>
                        <p class="auxiliar-pie">
                            <?php if ($auxiliar['id_ruta']): ?>
                                <strong class="texto-amarillo" title="<?= e($auxiliar['ruta']) ?>">Ruta <?= (int) $auxiliar['id_ruta'] ?></strong>
                            <?php else: ?>
                                <strong class="texto-alerta">Sin ruta</strong>
                            <?php endif; ?>
                            <span class="texto-suave">
                                <?= e($textoIngreso($auxiliar['ultimo_ingreso'])) ?>
                                <?php if ($auxiliar['cambiar_contrasena']): ?>
                                    · <span title="En su próximo ingreso debe poner una contraseña propia">contraseña temporal</span>
                                <?php endif; ?>
                            </span>
                        </p>
                    </article>
                <?php endforeach; ?>

                <p class="nota-candado">
                    <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <span>Solo administración crea cuentas. La contraseña se guarda cifrada y nadie la puede ver después.</span>
                </p>
            </section>

            <!-- Nuevo auxiliar: guarda en usuario y auxiliar -->
            <form class="panel formulario-auxiliar" id="formulario-auxiliar" action="<?= e($base) ?>/auxiliares/nuevo" method="POST" autocomplete="off">
                <p class="etiqueta">Nuevo auxiliar</p>

                <?php if ($formulario['errores']): ?>
                    <div class="aviso aviso-error" role="alert">
                        Revisa estos datos:
                        <ul>
                            <?php foreach ($formulario['errores'] as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="campos">
                    <label>Nombre *
                        <input type="text" name="nombre" value="<?= e($datos['nombre']) ?>" required maxlength="100" placeholder="Ej. Martín">
                    </label>
                    <label>Apellidos *
                        <input type="text" name="apellido" value="<?= e($datos['apellido']) ?>" required maxlength="100" placeholder="Ej. Gómez Fince">
                    </label>
                    <label>Documento *
                        <input type="text" name="documento" class="mono" value="<?= e($datos['documento']) ?>" required inputmode="numeric" pattern="[0-9 ]{5,24}" maxlength="24" placeholder="1120 456 789">
                    </label>
                    <label>Celular *
                        <input type="tel" name="telefono" class="mono" value="<?= e($datos['telefono']) ?>" required pattern="[0-9 +]{7,20}" maxlength="20" placeholder="301 100 0010">
                    </label>
                    <label>Fecha de ingreso
                        <input type="date" name="fecha_ingreso" class="mono" value="<?= e($datos['fecha_ingreso']) ?>">
                    </label>
                </div>

                <p class="etiqueta etiqueta-seccion">Acceso</p>

                <div class="campos">
                    <label class="campo-completo" for="correo-auxiliar">Correo · será su usuario *</label>
                    <div class="campo-completo entrada-icono" id="caja-correo">
                        <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input type="email" name="correo" id="correo-auxiliar" value="<?= e($datos['correo']) ?>" required maxlength="150"
                               placeholder="nombre@ejemplo.com" data-revisar="<?= e($base) ?>/auxiliares/correo" aria-describedby="estado-correo">
                        <span class="estado-correo" id="estado-correo" aria-live="polite"></span>
                    </div>

                    <label class="campo-completo" for="contrasena-auxiliar">Contraseña temporal *</label>
                    <div class="campo-completo fila-contrasena">
                        <div class="entrada-icono">
                            <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            <input type="password" name="contrasena" id="contrasena-auxiliar" required minlength="8" maxlength="72"
                                   autocomplete="new-password" class="mono" aria-describedby="fuerza-texto">
                            <button type="button" class="boton-texto" id="mostrar-contrasena" aria-pressed="false">Mostrar</button>
                        </div>
                        <button type="button" class="boton boton-borde" id="generar-contrasena">Generar</button>
                    </div>
                    <div class="campo-completo fuerza" aria-hidden="true">
                        <i></i><i></i><i></i><i></i>
                    </div>
                    <p class="campo-completo fuerza-texto texto-suave" id="fuerza-texto">Mínimo 8 caracteres.</p>

                    <label class="campo-completo casilla">
                        <input type="checkbox" name="cambiar_contrasena" <?= $datos['cambiar_contrasena'] ? 'checked' : '' ?>>
                        <span>Pedir cambio de contraseña en su primer ingreso</span>
                    </label>
                </div>

                <fieldset class="ruta-asignada">
                    <legend>Ruta asignada</legend>
                    <div class="pestanas pestanas-amarillas">
                        <?php foreach ($rutas as $ruta): ?>
                            <label title="<?= e($ruta['nombre']) ?>">
                                <input type="radio" name="id_ruta" value="<?= (int) $ruta['id_ruta'] ?>" <?= $rutaElegida === (int) $ruta['id_ruta'] ? 'checked' : '' ?>>
                                <span>Ruta <?= (int) $ruta['id_ruta'] ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label>
                            <input type="radio" name="id_ruta" value="0" <?= $rutaElegida === 0 ? 'checked' : '' ?>>
                            <span>Ninguna</span>
                        </label>
                    </div>
                </fieldset>

                <div class="ventana-acciones">
                    <button type="reset" class="boton boton-borde">Cancelar</button>
                    <button type="submit" class="boton boton-amarillo">Crear auxiliar</button>
                </div>
            </form>
        </div>
    </main>

    <script src="<?= e($base) ?>/js/auxiliares.js"></script>
</body>
</html>
