<?php
// Registro de asistencia del auxiliar (sirve en celular y en computador).
// Datos de AsistenciaController::registro():
// $ruta, $tipo, $estudiantes, $numeroParada, $aBordo, $campoHora, $horaCierre, $aviso, $usuario
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$dias = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoyTexto = $dias[(int) date('N')] . ' ' . date('j') . ' de ' . $meses[(int) date('n')];

$esIda = $tipo === 'IDA';
$verbo = $esIda ? 'Subió' : 'Bajó';
$campoAdmin = $esIda ? 'es_admin_ida' : 'es_admin_regreso';
$campoUsuario = $esIda ? 'id_usuario_ida' : 'id_usuario_regreso';
$total = count($estudiantes);
$porcentaje = $total > 0 ? round($aBordo * 100 / $total) : 0;
$cerrado = $horaCierre !== null; // cerrado: solo se puede ver
$faltan = array_filter($estudiantes, fn($e) => $e[$campoHora] === null);
$textoRuta = $ruta ? 'Ruta ' . (int) $ruta['id_ruta'] . ' · ' . ($esIda ? 'Ida' : 'Regreso') : '';
$iconoQr = '<svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/app.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/asistencia.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido registro-auxiliar">
        <header class="cabecera">
            <div>
                <p class="etiqueta"><?= $ruta ? e($ruta['nombre']) : 'Sin ruta asignada' ?></p>
                <h1>Asistencia de hoy</h1>
                <p class="subtitulo"><?= e($hoyTexto) ?></p>
            </div>
            <div class="cabecera-acciones">
                <nav class="pestanas pestanas-recorrido" aria-label="Recorrido">
                    <a href="<?= e($base) ?>/asistencia/registro?tipo=IDA" class="<?= $esIda ? 'activo' : '' ?>" <?= $esIda ? 'aria-current="page"' : '' ?>>Ida</a>
                    <a href="<?= e($base) ?>/asistencia/registro?tipo=REGRESO" class="<?= $esIda ? '' : 'activo' ?>" <?= $esIda ? '' : 'aria-current="page"' ?>>Regreso</a>
                </nav>
                <?php if ($ruta): ?>
                    <a class="boton boton-borde" href="<?= e($base) ?>/asistencia/exportar">Exportar a Excel</a>
                <?php endif; ?>
                <?php if ($ruta && $total > 0 && !$cerrado): ?>
                    <button type="button" class="boton boton-borde solo-computador" data-abrir-escaner><?= $iconoQr ?> Escanear con cámara</button>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($aviso): ?>
            <p class="aviso <?= $aviso['tipo'] === 'exito' ? 'aviso-exito' : 'aviso-error' ?>" role="status"><?= e($aviso['texto']) ?></p>
        <?php endif; ?>

        <?php if (!$ruta): ?>
            <p class="panel texto-suave">No tienes una ruta asignada. Pide a la administración que te asigne una.</p>
        <?php else: ?>
            <?php if ($cerrado): ?>
                <p class="aviso aviso-cerrado" role="status">Recorrido de <?= $esIda ? 'ida' : 'regreso' ?> cerrado a las <?= e($horaCierre) ?>. Para corregir algo, pide a administración.</p>
            <?php endif; ?>

            <section class="barra-progreso panel">
                <strong><?= $aBordo ?> de <?= $total ?> <?= $esIda ? 'a bordo' : 'bajaron' ?></strong>
                <span class="progreso" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $total ?>" aria-valuenow="<?= $aBordo ?>" aria-label="Avance del recorrido">
                    <span style="width: <?= $porcentaje ?>%"></span>
                </span>
                <label class="buscador">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" id="buscar-estudiante" placeholder="Buscar estudiante" aria-label="Buscar estudiante">
                </label>
            </section>

            <?php if ($total === 0): ?>
                <p class="panel texto-suave">Tu ruta no tiene estudiantes.</p>
            <?php endif; ?>

            <ul class="lista-registro" id="lista-registro">
                <?php $paradaAnterior = null; ?>
                <?php foreach ($estudiantes as $estudiante): ?>
                    <?php if ($estudiante['id_parada'] !== $paradaAnterior): ?>
                        <?php $paradaAnterior = $estudiante['id_parada']; ?>
                        <li class="grupo-parada">Parada <?= (int) ($numeroParada[$estudiante['id_parada']] ?? 0) ?> · <?= e($estudiante['parada']) ?></li>
                    <?php endif; ?>
                    <?php
                    $hora = $estudiante[$campoHora];
                    $registradoPorMi = $hora !== null && (int) $estudiante[$campoUsuario] === (int) $usuario['id_usuario'];
                    ?>
                    <li class="fila-registro <?= $hora ? 'a-bordo' : 'pendiente' ?>" data-buscar="<?= e($estudiante['estudiante'] . ' ' . $estudiante['documento']) ?>">
                        <span class="registro-nombre">
                            <strong><?= e($estudiante['estudiante']) ?></strong>
                            <span class="texto-suave registro-detalle"><span class="solo-computador">· </span><?= e($estudiante['curso']) ?><span class="solo-celular"> · <?= $hora ? e(substr($hora, 0, 5)) : 'pendiente' ?></span></span>
                        </span>

                        <span class="registro-acudiente">
                            <?php if ($estudiante['acudiente']): ?>
                                <?= e($estudiante['acudiente']) ?>
                                <?php if ($estudiante['telefono_acudiente']): ?>
                                    · <a class="mono" href="tel:<?= e($estudiante['telefono_acudiente']) ?>"><?= e($estudiante['telefono_acudiente']) ?></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="texto-alerta">Sin acudiente</span>
                            <?php endif; ?>
                        </span>

                        <?php if ($hora === null && $cerrado): ?>
                            <span class="boton-marcado boton-ajeno" title="El recorrido está cerrado">
                                <span class="texto-boton">No <?= $esIda ? 'subió' : 'bajó' ?></span>
                                <span class="circulo circulo-vacio" aria-label="Sin registro"></span>
                            </span>
                        <?php elseif ($hora === null): ?>
                            <form action="<?= e($base) ?>/asistencia/marcar" method="POST">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="documento" value="<?= e($estudiante['documento']) ?>">
                                <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                                <button type="submit" class="boton-marcar" aria-label="Marcar <?= $esIda ? 'subida' : 'bajada' ?> de <?= e($estudiante['estudiante']) ?>">
                                    <span class="texto-boton">Marcar <?= $esIda ? 'subida' : 'bajada' ?></span>
                                    <span class="circulo" aria-hidden="true"></span>
                                </button>
                            </form>
                        <?php elseif ($registradoPorMi && !$cerrado): ?>
                            <form action="<?= e($base) ?>/asistencia/deshacer" method="POST"
                                  data-confirmar="¿Deshacer el registro de <?= e($estudiante['estudiante']) ?>?">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="documento" value="<?= e($estudiante['documento']) ?>">
                                <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                                <button type="submit" class="boton-marcado" title="Tocar para deshacer" aria-label="<?= $verbo ?> <?= e(substr($hora, 0, 5)) ?>. Tocar para deshacer">
                                    <span class="texto-boton"><?= $verbo ?> <?= e(substr($hora, 0, 5)) ?></span>
                                    <span class="circulo" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9"/></svg></span>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="boton-marcado boton-ajeno" title="<?= $registradoPorMi ? 'El recorrido está cerrado' : 'Lo registró ' . ($estudiante[$campoAdmin] ? 'la administración' : 'otro auxiliar') ?>">
                                <span class="texto-boton"><?= $estudiante[$campoAdmin] ? 'Manual' : $verbo ?> <?= e(substr($hora, 0, 5)) ?></span>
                                <span class="circulo" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9"/></svg></span>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="sin-resultados" id="sin-resultados" hidden>Ningún estudiante coincide con la búsqueda.</p>

            <footer class="panel pie-registro">
                <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <span>Solo tu ruta y solo hoy. Para corregir días anteriores, pide a administración.</span>
                <button type="button" class="boton boton-marfil" data-abrir-ventana="ventana-resumen"><?= $cerrado ? 'Ver resumen' : 'Cerrar recorrido' ?></button>
            </footer>

            <?php if ($total > 0 && !$cerrado): ?>
                <!-- En el celular el escáner queda siempre a mano, abajo -->
                <button type="button" class="boton boton-amarillo boton-escanear solo-celular" data-abrir-escaner><?= $iconoQr ?> Escanear QR</button>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php if ($ruta): ?>
        <!-- Resumen al llegar: quién no subió y a quién avisar -->
        <dialog class="ventana ventana-resumen" id="ventana-resumen" aria-labelledby="titulo-resumen">
            <div class="ventana-cabecera">
                <div>
                    <p class="etiqueta"><?= $esIda ? 'Llegada al colegio' : 'Fin del regreso' ?></p>
                    <h2 id="titulo-resumen">Resumen de <?= $esIda ? 'ida' : 'regreso' ?></h2>
                </div>
                <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
            </div>

            <div class="resumen-conteo">
                <article class="tarjeta"><span><?= $esIda ? 'Subieron' : 'Bajaron' ?></span><strong><?= $aBordo ?></strong></article>
                <article class="tarjeta tarjeta-alerta"><span>No <?= $esIda ? 'subieron' : 'bajaron' ?></span><strong><?= count($faltan) ?></strong></article>
            </div>

            <?php if (count($faltan) > 0): ?>
                <p class="etiqueta etiqueta-avisar">Avisar al acudiente</p>
                <ul class="lista-avisar">
                    <?php foreach ($faltan as $estudiante): ?>
                        <li>
                            <span>
                                <strong><?= e($estudiante['estudiante']) ?></strong>
                                <small><?= e($estudiante['parada']) ?><?= $estudiante['acudiente'] ? ' · ' . e($estudiante['acudiente']) : '' ?></small>
                                <?php if (!$estudiante['telefono_acudiente']): ?>
                                    <small class="texto-alerta">No tiene <?= $estudiante['acudiente'] ? 'celular' : 'acudiente' ?> registrado.</small>
                                <?php endif; ?>
                            </span>
                            <?php if ($estudiante['telefono_acudiente']): ?>
                                <a class="llamar" href="tel:<?= e($estudiante['telefono_acudiente']) ?>" aria-label="Llamar a <?= e($estudiante['acudiente']) ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="aviso aviso-exito">Todos los estudiantes quedaron registrados.</p>
            <?php endif; ?>

            <?php if ($cerrado): ?>
                <div class="ventana-acciones">
                    <button type="button" class="boton boton-amarillo" data-cerrar-ventana>Listo</button>
                </div>
            <?php else: ?>
                <form action="<?= e($base) ?>/asistencia/cerrar" method="POST">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                    <p class="nota">Al cerrar ya no podrás marcar, escanear ni deshacer este recorrido hoy.</p>
                    <div class="ventana-acciones">
                        <button type="button" class="boton boton-borde" data-cerrar-ventana>Seguir marcando</button>
                        <button type="submit" class="boton boton-amarillo">Cerrar recorrido</button>
                    </div>
                </form>
            <?php endif; ?>
        </dialog>
    <?php endif; ?>

    <?php if ($ruta && $total > 0 && !$cerrado): ?>
        <!-- Escáner del carné: lee el QR con la cámara y muestra la ficha del estudiante -->
        <dialog class="escaner" id="escaner" aria-labelledby="titulo-escaner"
                data-escanear="<?= e($base) ?>/asistencia/escanear" data-deshacer="<?= e($base) ?>/asistencia/deshacer"
                data-tipo="<?= e($tipo) ?>" data-ruta="<?= (int) $ruta['id_ruta'] ?>">
            <div class="marca-agua" aria-hidden="true" style="background-image: url('<?= e($base) ?>/img/escudo.jpg')"></div>

            <div class="escaner-contenido">
                <header class="escaner-barra">
                    <div>
                        <h2 id="titulo-escaner">Escanear carné</h2>
                        <span><?= e($textoRuta) ?></span>
                    </div>
                    <button type="button" class="boton-redondo" data-cerrar-escaner aria-label="Cerrar escáner">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>

                <div class="visor">
                    <div class="marco">
                        <video id="escaner-video" playsinline muted aria-label="Cámara"></video>
                        <svg class="qr-dibujo" viewBox="0 0 250 250" aria-hidden="true">
                            <g fill="#FBFBF4" fill-opacity=".55">
                                <rect x="65" y="65" width="38" height="38" rx="4"/><rect x="147" y="65" width="38" height="38" rx="4"/>
                                <rect x="65" y="147" width="38" height="38" rx="4"/><rect x="140" y="140" width="16" height="16"/>
                                <rect x="164" y="150" width="21" height="11"/><rect x="150" y="168" width="11" height="17"/>
                                <rect x="170" y="170" width="15" height="15"/><rect x="118" y="75" width="15" height="15"/>
                            </g>
                        </svg>
                        <svg class="esquinas" viewBox="0 0 250 250" aria-hidden="true">
                            <g fill="none" stroke="#F4D21F" stroke-width="7" stroke-linecap="round">
                                <path d="M6 54 V26 a20 20 0 0 1 20 -20 H54"/><path d="M196 6 H224 a20 20 0 0 1 20 20 V54"/>
                                <path d="M244 196 V224 a20 20 0 0 1 -20 20 H196"/><path d="M54 244 H26 a20 20 0 0 1 -20 -20 V196"/>
                            </g>
                        </svg>
                        <div class="laser"></div>
                    </div>
                    <p class="visor-texto" id="visor-texto" aria-live="polite">Apunta al código del carné</p>
                    <label class="boton boton-borde foto-carne" id="foto-carne" hidden>
                        Tomar foto del carné
                        <input type="file" accept="image/*" capture="environment" class="visualmente-oculto">
                    </label>
                </div>

                <footer class="escaner-pie">
                    <span id="escaner-contador"><?= $aBordo ?> de <?= $total ?> <?= $esIda ? 'a bordo' : 'bajaron' ?></span>
                    <button type="button" class="boton-texto" data-buscar-nombre>Buscar por nombre</button>
                </footer>
            </div>

            <!-- Ficha que sube al leer un carné: verde se registró, amarillo ya estaba, rojo no se registró -->
            <div class="hoja-fondo" id="hoja-fondo" hidden>
                <article class="hoja" id="hoja" aria-live="assertive"></article>
            </div>
        </dialog>
        <script src="<?= \App\Core\Url::recurso('/js/jsQR.js') ?>"></script>
    <?php endif; ?>

    <script src="<?= \App\Core\Url::recurso('/js/asistencia.js') ?>"></script>
</body>
</html>
