<?php
// Panel lateral compartido: logo, menú por rol (sidebar.php) y usuario con "Salir".
// Lo incluyen todas las pantallas del panel. Necesita la sesión iniciada.
$baseLateral = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$usuarioLateral = $_SESSION['usuario'];
$detalleLateral = $usuarioLateral['rol'] === 'auxiliar' && !empty($usuarioLateral['id_ruta'])
    ? 'Auxiliar · Ruta ' . $usuarioLateral['id_ruta']
    : ucfirst($usuarioLateral['rol']);
?>
<div class="lateral<?= $usuarioLateral['rol'] === 'auxiliar' ? ' lateral-auxiliar' : '' ?>">
    <div class="marca">
        <img src="<?= htmlspecialchars($baseLateral, ENT_QUOTES, 'UTF-8') ?>/img/escudo.jpg" alt="Escudo de la I.E. N° 4">
        <div>
            <strong>Ruta<span>Segura</span></strong>
            <small>I.E. N° 4</small>
        </div>
    </div>

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="perfil">
        <span class="inicial"><?= htmlspecialchars(mb_strtoupper(mb_substr($usuarioLateral['nombre'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
        <div>
            <strong><?= htmlspecialchars($usuarioLateral['nombre'] . ' ' . mb_substr($usuarioLateral['apellido'], 0, 1) . '.', ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($detalleLateral, ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <form action="<?= htmlspecialchars($baseLateral, ENT_QUOTES, 'UTF-8') ?>/logout" method="POST">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="salir" title="Cerrar sesión">Salir</button>
        </form>
    </div>
</div>
