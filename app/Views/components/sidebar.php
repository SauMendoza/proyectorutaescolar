<?php

// SCRIPT_NAME: Contiene la ruta de la unicación actual
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');

//Obtenemos la ruta completa solicitada por el usuario
//PHP_URL_PATH: Devuelve la ruta unicamente después del dominio
$rutaSolicitada = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);

//Con substr le quitamos una parte de la ruta, eliminamos $base
$rutaActual = substr($rutaSolicitada,strlen($base));

//Normalizamos la ruta actual
$rutaActual= '/'.trim($rutaActual,'/');

//Íconos del menú (dibujos SVG en línea)
$iconos = [
    'panel' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/>',
    'registro' => '<path d="m5 12 5 5L20 7"/>',
    'historial' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'asistencias' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    'estudiantes' => '<path d="M2 9 12 4l10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
    'acudientes' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 15c3 0 5 2 5 5"/>',
    'cursos' => '<path d="M3 5h7a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H3z"/><path d="M21 5h-7a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h7z"/>',
    'rutas' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
    'auxiliares' => '<circle cx="12" cy="7" r="4"/><path d="M12 11v10M12 16h3M12 19h2"/>',
];

//El menú cambia según el rol de la sesión
$rol = $_SESSION['usuario']['rol'] ?? '';

if ($rol === 'auxiliar') {
    $menu = [
        ['/', 'Panel', 'panel'],
        ['/asistencia/registro', 'Registro', 'registro'],
        ['/asistencia/historial', 'Historial', 'historial'],
        ['/rutas', 'Mi ruta', 'rutas'],
    ];
} else {
    $menu = [
        ['/', 'Panel', 'panel'],
        ['/asistencias', 'Asistencias', 'asistencias'],
        ['/estudiantes', 'Estudiantes', 'estudiantes'],
        ['/acudientes', 'Acudientes', 'acudientes'],
        ['/cursos', 'Cursos', 'cursos'],
        ['/rutas', 'Rutas', 'rutas'],
        ['/auxiliares', 'Auxiliares', 'auxiliares'],
    ];
}
?>


<aside>
    <nav>
        <ul>
            <?php foreach ($menu as [$rutaMenu, $textoMenu, $iconoMenu]): ?>
                <?php
                //"/" solo se marca exacto; los demás también en sus subpáginas (ej. /estudiantes/nuevo)
                $activo = $rutaMenu === '/'
                    ? $rutaActual === '/'
                    : ($rutaActual === $rutaMenu || str_starts_with($rutaActual, $rutaMenu . '/'));
                ?>
                <li>
                    <a href="<?= $base . $rutaMenu ?>"
                    class="<?= $activo ? 'activo' : '' ?>"
                    <?= $activo ? 'aria-current="page"' : '' ?>
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $iconos[$iconoMenu] ?></svg>
                        <?= $textoMenu ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
