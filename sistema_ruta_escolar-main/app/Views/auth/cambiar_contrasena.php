<?php
// Primer ingreso con contraseña temporal: el usuario pone una propia antes de entrar.
// Usa los estilos del inicio de sesión. Datos de LoginController::cambiarContrasena(): $error, $nombre
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

if (!function_exists('e')) {
    function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A3D1F">
    <title>Cambiar contraseña · RutaSegura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Plus+Jakarta+Sans:wght@400..800&display=swap">
    <link rel="stylesheet" href="<?= e($base) ?>/css/login.css">
</head>
<body>
    <div class="franja" aria-hidden="true"></div>
    <div class="resplandor" aria-hidden="true"></div>

    <header class="encabezado">
        <img class="escudo" src="<?= e($base) ?>/img/escudo.jpg" alt="Escudo de la Institución Educativa N° 4">
        <div class="institucion">
            <strong>Institución Educativa N° 4</strong>
            <span>San José Campestre · Maicao</span>
        </div>
    </header>

    <main class="centro">
        <form class="tarjeta" action="<?= e($base) ?>/cambiar-contrasena" method="POST">
            <p class="eyebrow">Primer ingreso</p>
            <h1>Hola, <?= e($nombre) ?></h1>
            <p class="subtitulo">Tu contraseña es temporal. Pon una propia para seguir.</p>
            <div class="barras" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

            <?php if ($error): ?>
                <div class="alerta" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="campo">
                <label for="actual">Contraseña temporal</label>
                <div class="entrada">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" id="actual" name="actual" autocomplete="current-password" required autofocus>
                </div>
            </div>

            <div class="campo">
                <label for="nueva">Contraseña nueva · mínimo 8 caracteres</label>
                <div class="entrada">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" id="nueva" name="nueva" autocomplete="new-password" required minlength="8" maxlength="72">
                </div>
            </div>

            <div class="campo">
                <label for="repetir">Repite la contraseña nueva</label>
                <div class="entrada">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" id="repetir" name="repetir" autocomplete="new-password" required minlength="8" maxlength="72">
                </div>
            </div>

            <button type="submit" class="boton">Guardar y entrar</button>
        </form>

        <form action="<?= e($base) ?>/logout" method="POST" class="salir-cambio">
            <button type="submit" class="mostrar">Salir sin cambiarla</button>
        </form>
    </main>
</body>
</html>
