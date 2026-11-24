<?php
// Ficha del estudiante. Datos que llegan desde EstudianteController::ficha():
// $ficha, $acudientes, $paradas, $dias, $conIda, $conRegreso, $habilesHastaHoy, $uso, $ultimosRegistros
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}

$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
          'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$nombreCompleto = trim(implode(' ', array_filter([
    $ficha['nombre'], $ficha['segundo_nombre'], $ficha['apellido'], $ficha['segundo_apellido'],
])));
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
    <link rel="stylesheet" href="<?= \App\Core\Url::recurso('/css/estudiantes.css') ?>">
</head>
<body>
    <?php require __DIR__ . '/../components/lateral.php'; ?>

    <main class="contenido">
        <?php if ($mensajeExito): ?>
            <p class="aviso aviso-exito" role="status"><?= e($mensajeExito) ?></p>
        <?php endif; ?>

        <header class="panel ficha-cabecera">
            <span class="inicial-grande"><?= e(mb_strtoupper(mb_substr($ficha['nombre'], 0, 1))) ?></span>
            <div>
                <p class="etiqueta"><a href="<?= e($base) ?>/estudiantes">Estudiantes</a> · Ficha del estudiante</p>
                <h1><?= e($nombreCompleto) ?></h1>
                <div class="insignias">
                    <span class="insignia mono"><?= e($ficha['tipo_doc_estu'] . ' ' . $ficha['num_doc_estu']) ?></span>
                    <span class="insignia">Curso <?= e($ficha['curso']) ?></span>
                    <span class="insignia"><?= $ficha['edad'] !== null ? e($ficha['edad']) . ' años' : 'Sin fecha de nacimiento' ?></span>
                </div>
            </div>
            <?php if ($esAdmin): ?>
                <button type="button" class="boton boton-amarillo" data-abrir-ventana="ventana-editar">Editar</button>
            <?php endif; ?>
        </header>

        <div class="ficha">
            <div>
                <section class="panel">
                    <p class="etiqueta">Transporte</p>
                    <dl class="datos">
                        <div><dt>Ruta</dt><dd><?= e($ficha['ruta']) ?></dd></div>
                        <div><dt>Conductor</dt><dd><?= e($ficha['conductor']) ?></dd></div>
                        <div>
                            <dt>Celular del conductor</dt>
                            <dd class="mono">
                                <?php if ($ficha['telefono_conductor']): ?>
                                    <a href="tel:<?= e($ficha['telefono_conductor']) ?>"><?= e($ficha['telefono_conductor']) ?></a>
                                <?php else: ?>
                                    <span class="texto-suave">Sin registrar</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div><dt>Parada</dt><dd><?= e($ficha['parada']) ?> · <?= e($ficha['comunidad']) ?></dd></div>
                    </dl>

                    <!-- Recorrido: el colegio y las paradas de la ruta por kilómetro -->
                    <ol class="recorrido" aria-label="Recorrido de la ruta">
                        <li class="colegio"><strong>Colegio</strong><small>km 0</small></li>
                        <?php foreach ($paradas as $numero => $parada): ?>
                            <?php $esActual = (int) $parada['id_parada'] === (int) $ficha['id_parada']; ?>
                            <li class="<?= $esActual ? 'actual' : '' ?>" <?= $esActual ? 'aria-current="step"' : '' ?>>
                                <?= e($parada['nombre']) ?>
                                <small>Parada <?= $numero + 1 ?><?= $parada['km_referencia'] !== null ? ' · km ' . e((float) $parada['km_referencia']) : '' ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <section class="panel">
                    <p class="etiqueta">Acudientes</p>
                    <?php if (count($acudientes) === 0): ?>
                        <p class="texto-alerta">Este estudiante no tiene acudientes registrados.</p>
                    <?php else: ?>
                        <div class="acudientes-ficha">
                            <?php foreach ($acudientes as $acudiente): ?>
                                <?php $esPrincipal = $acudiente['tipo_acudiente'] === 'PRINCIPAL'; ?>
                                <article class="acudiente-tarjeta">
                                    <span class="insignia <?= $esPrincipal ? 'insignia-principal' : 'insignia-suplente' ?>">
                                        <?= $esPrincipal ? 'Principal' : 'Suplente' ?>
                                    </span>
                                    <strong>
                                        <a href="<?= e($base) ?>/acudientes/ficha?documento=<?= e(urlencode($acudiente['num_doc_acu'])) ?>">
                                            <?= e($acudiente['nombre_acu'] . ' ' . $acudiente['apellido_acu']) ?>
                                        </a>
                                    </strong>
                                    <span class="texto-suave">
                                        <?= e($acudiente['parentesco']) ?> · CC <?= e($acudiente['num_doc_acu']) ?>
                                        <?php if ((int) $acudiente['otros'] > 0): ?>
                                            · también es acudiente de <?= (int) $acudiente['otros'] ?> estudiante<?= (int) $acudiente['otros'] > 1 ? 's' : '' ?> más
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($acudiente['telefono']): ?>
                                        <a class="telefono" href="tel:<?= e($acudiente['telefono']) ?>"><?= e($acudiente['telefono']) ?></a>
                                    <?php else: ?>
                                        <span class="vacio">Sin celular</span>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                            <?php if (count($acudientes) === 1): ?>
                                <article class="acudiente-tarjeta">
                                    <span class="insignia insignia-alerta">Suplente</span>
                                    <span class="vacio">Sin acudiente suplente registrado.</span>
                                </article>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div>
                <!-- Carné: el QR lleva el documento; el auxiliar lo escanea en el bus -->
                <section class="panel carne" id="carne">
                    <p class="etiqueta">Carné · código QR</p>
                    <div class="carne-cuerpo">
                        <div class="carne-qr" data-qr="<?= e($ficha['num_doc_estu']) ?>" role="img"
                             aria-label="Código QR con el documento <?= e($ficha['num_doc_estu']) ?>"></div>
                        <div class="carne-datos">
                            <span class="carne-colegio">RutaSegura · I.E. N° 4</span>
                            <strong><?= e($nombreCompleto) ?></strong>
                            <span class="mono"><?= e($ficha['tipo_doc_estu'] . ' ' . $ficha['num_doc_estu']) ?></span>
                            <span>Curso <?= e($ficha['curso']) ?></span>
                            <span><?= e($ficha['ruta']) ?></span>
                            <span><?= e($ficha['parada']) ?></span>
                        </div>
                    </div>
                    <p class="texto-suave carne-ayuda">El auxiliar lo escanea en el bus para registrar la subida y la bajada.</p>
                    <button type="button" class="boton boton-borde carne-imprimir" data-imprimir-carne>Imprimir carné</button>
                </section>

                <section class="panel">
                    <p class="etiqueta">Asistencia · <?= e($meses[(int) date('n')] . ' ' . date('Y')) ?></p>
                    <div class="mini-estadisticas">
                        <article class="tarjeta"><span>Con ida</span><strong><?= $conIda ?> / <?= $habilesHastaHoy ?></strong></article>
                        <article class="tarjeta"><span>Regreso</span><strong><?= $conRegreso ?> / <?= $habilesHastaHoy ?></strong></article>
                        <article class="tarjeta"><span>Uso</span><strong><?= $uso ?> %</strong></article>
                    </div>

                    <ol class="calendario" aria-label="Días hábiles del mes">
                        <?php foreach (['L', 'M', 'X', 'J', 'V'] as $letra): ?>
                            <li class="dia-semana" aria-hidden="true"><?= $letra ?></li>
                        <?php endforeach; ?>
                        <?php foreach ($dias as $dia): ?>
                            <?php if ($dia === null): ?>
                                <li aria-hidden="true"></li>
                            <?php else: ?>
                                <li class="dia<?= $dia['hoy'] ? ' hoy-dia' : '' ?><?= $dia['futuro'] ? ' futuro' : '' ?>"
                                    title="Día <?= $dia['numero'] ?>: ida <?= $dia['ida'] ? 'sí' : 'no' ?>, regreso <?= $dia['regreso'] ? 'sí' : 'no' ?>">
                                    <?= $dia['numero'] ?>
                                    <span class="puntos">
                                        <i class="<?= $dia['ida'] ? 'p-ida' : '' ?>"></i>
                                        <i class="<?= $dia['regreso'] ? 'p-regreso' : '' ?>"></i>
                                    </span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                    <div class="leyenda">
                        <span><span class="puntos"><i class="p-ida"></i></span> Ida</span>
                        <span><span class="puntos"><i class="p-regreso"></i></span> Regreso</span>
                        <span><span class="puntos"><i></i></span> No abordó</span>
                    </div>
                </section>

                <section class="panel">
                    <p class="etiqueta">Últimos registros</p>
                    <?php if (count($ultimosRegistros) === 0): ?>
                        <p class="texto-suave">Todavía no hay registros de asistencia.</p>
                    <?php else: ?>
                        <ul class="registros">
                            <?php foreach ($ultimosRegistros as $registro): ?>
                                <li>
                                    <span class="mono"><?= e(date('d/m', strtotime($registro['fecha']))) ?> <?= e(substr($registro['hora'], 0, 5)) ?></span>
                                    <div>
                                        <small><?= e($registro['usuario']) ?><?= $registro['es_admin'] ? ' · Manual · Administración' : '' ?></small>
                                    </div>
                                    <span class="chip <?= $registro['tipo_recorrido'] === 'IDA' ? 'chip-ida' : 'chip-regreso' ?>">
                                        <?= $registro['tipo_recorrido'] === 'IDA' ? 'Ida' : 'Regreso' ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <?php if ($esAdmin): ?>
        <?php
        //Lo que se muestra en "Editar": lo que se escribió (si hubo errores) o lo guardado
        $datos = $edicion['datos'] ?? [
            'tipo_documento' => $ficha['tipo_doc_estu'],
            'nombre' => $ficha['nombre'], 'segundo_nombre' => $ficha['segundo_nombre'],
            'apellido' => $ficha['apellido'], 'segundo_apellido' => $ficha['segundo_apellido'],
            'fecha_nacimiento' => $ficha['fecha_nacimiento'],
            'curso' => $ficha['id_curso'], 'parada' => $ficha['id_parada'],
        ];
        if (!$edicion) {
            foreach ($acudientes as $acudiente) {
                $prefijo = strtolower($acudiente['tipo_acudiente']);
                $datos[$prefijo . '_documento'] = $acudiente['num_doc_acu'];
                $datos[$prefijo . '_parentesco'] = $acudiente['fk_id_parentesco'];
            }
        }
        $dato = fn(string $campo): string => (string) ($datos[$campo] ?? '');
        $marcado = fn(string $campo, $valor): string => $dato($campo) === (string) $valor ? 'selected' : '';
        ?>
        <dialog class="ventana" id="ventana-editar" aria-labelledby="titulo-editar" <?= $edicion ? 'data-abrir' : '' ?>>
            <form action="<?= e($base) ?>/estudiantes/editar" method="POST">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="documento" value="<?= e($ficha['num_doc_estu']) ?>">
                <div class="ventana-cabecera">
                    <div>
                        <p class="etiqueta">Documento <?= e($ficha['num_doc_estu']) ?></p>
                        <h2 id="titulo-editar">Editar estudiante</h2>
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
                    <label>Primer nombre *
                        <input type="text" name="nombre" value="<?= e($dato('nombre')) ?>" required maxlength="45">
                    </label>
                    <label>Segundo nombre
                        <input type="text" name="segundo_nombre" value="<?= e($dato('segundo_nombre')) ?>" maxlength="45">
                    </label>
                    <label>Primer apellido *
                        <input type="text" name="apellido" value="<?= e($dato('apellido')) ?>" required maxlength="45">
                    </label>
                    <label>Segundo apellido
                        <input type="text" name="segundo_apellido" value="<?= e($dato('segundo_apellido')) ?>" maxlength="45">
                    </label>
                    <label>Tipo de documento *
                        <select name="tipo_documento" required>
                            <option value="">Elige…</option>
                            <?php foreach ($tiposDocumento as $sigla => $nombre): ?>
                                <option value="<?= e($sigla) ?>" <?= $marcado('tipo_documento', $sigla) ?>><?= e($sigla . ' · ' . $nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Fecha de nacimiento *
                        <input type="date" name="fecha_nacimiento" value="<?= e($dato('fecha_nacimiento')) ?>" required max="<?= date('Y-m-d') ?>">
                    </label>
                    <label>Curso *
                        <select name="curso" required>
                            <option value="">Elige…</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?= (int) $curso['id_curso'] ?>" <?= $marcado('curso', $curso['id_curso']) ?>><?= e($curso['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Ruta y parada *
                        <select name="parada" required>
                            <option value="">Elige…</option>
                            <?php foreach ($rutas as $ruta): ?>
                                <optgroup label="<?= e($ruta['nombre']) ?>">
                                    <?php foreach ($todasParadas as $parada): ?>
                                        <?php if ((int) $parada['fk_id_ruta'] === (int) $ruta['id_ruta']): ?>
                                            <option value="<?= (int) $parada['id_parada'] ?>" <?= $marcado('parada', $parada['id_parada']) ?>><?= e($parada['nombre']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?php foreach (['principal' => 'Acudiente principal *', 'suplente' => 'Acudiente suplente'] as $prefijo => $titulo): ?>
                        <label><?= $titulo ?>
                            <select name="<?= $prefijo ?>_documento" <?= $prefijo === 'principal' ? 'required' : '' ?>>
                                <option value=""><?= $prefijo === 'principal' ? 'Elige…' : 'Ninguno' ?></option>
                                <?php foreach ($todosAcudientes as $opcion): ?>
                                    <option value="<?= e($opcion['num_doc_acu']) ?>" <?= $marcado($prefijo . '_documento', $opcion['num_doc_acu']) ?>><?= e($opcion['nombre_acu'] . ' ' . $opcion['apellido_acu'] . ' · ' . $opcion['num_doc_acu']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Parentesco
                            <select name="<?= $prefijo ?>_parentesco" <?= $prefijo === 'principal' ? 'required' : '' ?>>
                                <option value="">Elige…</option>
                                <?php foreach ($parentescos as $parentesco): ?>
                                    <option value="<?= (int) $parentesco['id_parentesco'] ?>" <?= $marcado($prefijo . '_parentesco', $parentesco['id_parentesco']) ?>><?= e($parentesco['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="nota">El documento no se cambia: es el que lleva el QR del carné. ¿El acudiente no está en la lista? Créalo primero en Acudientes.</p>

                <div class="ventana-acciones">
                    <button type="button" class="boton boton-peligro" data-cerrar-ventana data-abrir-ventana="ventana-borrar">Borrar estudiante</button>
                    <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                    <button type="submit" class="boton boton-amarillo">Guardar cambios</button>
                </div>
            </form>
        </dialog>

        <dialog class="ventana" id="ventana-borrar" aria-labelledby="titulo-borrar" <?= $errorBorrar ? 'data-abrir' : '' ?>>
            <form action="<?= e($base) ?>/estudiantes/eliminar" method="POST" autocomplete="off">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="documento" value="<?= e($ficha['num_doc_estu']) ?>">
                <div class="ventana-cabecera">
                    <div>
                        <p class="etiqueta etiqueta-alerta">No se puede deshacer</p>
                        <h2 id="titulo-borrar">Borrar a <?= e($nombreCompleto) ?></h2>
                    </div>
                    <button type="button" class="cerrar-ventana" data-cerrar-ventana aria-label="Cerrar">×</button>
                </div>

                <?php if ($errorBorrar): ?>
                    <p class="aviso aviso-error" role="alert"><?= e($errorBorrar) ?></p>
                <?php endif; ?>

                <ul>
                    <li>Se borran sus datos y <strong><?= $totalAsistencias ?> asistencia<?= $totalAsistencias === 1 ? '' : 's' ?></strong>.</li>
                    <li>Se quita su vínculo con <strong><?= count($acudientes) ?> acudiente<?= count($acudientes) === 1 ? '' : 's' ?></strong>. Los acudientes no se borran.</li>
                </ul>

                <div class="campos">
                    <label class="campo-completo">Para confirmar, escribe su documento: <?= e($ficha['num_doc_estu']) ?>
                        <input type="text" name="confirmacion" required maxlength="20" class="mono">
                    </label>
                </div>

                <div class="ventana-acciones">
                    <button type="button" class="boton boton-borde" data-cerrar-ventana>Cancelar</button>
                    <button type="submit" class="boton boton-peligro">Borrar definitivamente</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <script src="<?= \App\Core\Url::recurso('/js/qrcode.js') ?>"></script>
    <script src="<?= \App\Core\Url::recurso('/js/estudiantes.js') ?>"></script>
</body>
</html>
