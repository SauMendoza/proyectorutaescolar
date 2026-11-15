<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes</title>
</head>
<body>

    <h1>Listado de estudiantes</h1>

    <?php if (count($estudiantes) > 0): ?>

        <div style="overflow-x: auto;">
            <table border="1">
                <thead>
                    <tr>
                        <?php foreach (array_keys($estudiantes[0]) as $columna): ?>
                            <th><?= htmlspecialchars($columna, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <?php foreach ($estudiante as $dato): ?>
                                <td><?= htmlspecialchars((string) $dato, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <p>No hay estudiantes registrados.</p>

    <?php endif; ?>

    <form action="<?= htmlspecialchars(\App\Core\Url::to('/logout'), ENT_QUOTES, 'UTF-8') ?>" method="POST">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit">Cerrar sesión</button>
    </form>

</body>
</html>
