<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
</head>
<body>
    <form action="<?= htmlspecialchars(\App\Core\Url::to('/logout'), ENT_QUOTES, 'UTF-8') ?>" method="post">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit">Cerrar Sesión</button>
    </form>
</body>
</html>
