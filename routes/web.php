<?php

    use App\Controllers\EstudianteController;
    use App\Controllers\HomeController;
    use App\Controllers\LoginController;
    use App\Controllers\RegistroController;
    use App\Controllers\AuxiliarController;
    use App\Controllers\AcudienteController;
    use App\Controllers\CursoController;
    use App\Controllers\RutaController;
    use App\Controllers\AsistenciaController;

    $router->get('/', [
        HomeController::class,
        'index'
    ]);

    $router->get('/estudiantes', [
        EstudianteController::class,
        'presentacion'
    ]);

    // Formulario de nuevo estudiante (solo administrador)
    $router->get('/estudiantes/nuevo', [
        EstudianteController::class,
        'nuevo'
    ]);

    $router->post('/estudiantes/nuevo', [
        EstudianteController::class,
        'guardar'
    ]);

    // Ficha de un estudiante (?documento=)
    $router->get('/estudiantes/ficha', [
        EstudianteController::class,
        'ficha'
    ]);

    // Editar y borrar un estudiante (solo administrador)
    $router->post('/estudiantes/editar', [
        EstudianteController::class,
        'actualizar'
    ]);

    $router->post('/estudiantes/eliminar', [
        EstudianteController::class,
        'eliminar'
    ]);

    // Busca un acudiente por documento y responde en JSON
    $router->get('/estudiantes/acudiente', [
        EstudianteController::class,
        'buscarAcudiente'
    ]);

    $router->get('/login', [
    LoginController::class,
    'inicio_login'
    ]);

    $router->post('/login',[
    LoginController::class,
    'autenticate'
    ]);

    // Ruta para cerrar sesión

    $router->post('/logout',[
        LoginController::class,
        'logout'
    ]);

    // Primer ingreso: el usuario cambia la contraseña temporal por una propia
    $router->get('/cambiar-contrasena', [
        LoginController::class,
        'cambiarContrasena'
    ]);

    $router->post('/cambiar-contrasena', [
        LoginController::class,
        'guardarContrasena'
    ]);

    // Auxiliares: lista y cuentas nuevas (solo administrador)
    $router->get('/auxiliares', [
        AuxiliarController::class,
        'listado'
    ]);

    $router->post('/auxiliares/nuevo', [
        AuxiliarController::class,
        'guardar'
    ]);

    // El administrador pone una contraseña nueva a un auxiliar
    $router->post('/auxiliares/contrasena', [
        AuxiliarController::class,
        'cambiarContrasena'
    ]);

    // Responde en JSON si un correo está libre
    $router->get('/auxiliares/correo', [
        AuxiliarController::class,
        'correo'
    ]);

    // Acudientes (solo administrador)
    $router->get('/acudientes', [
        AcudienteController::class,
        'listado'
    ]);

    $router->get('/acudientes/ficha', [
        AcudienteController::class,
        'ficha'
    ]);

    $router->post('/acudientes/nuevo', [
        AcudienteController::class,
        'guardar'
    ]);

    $router->post('/acudientes/editar', [
        AcudienteController::class,
        'actualizar'
    ]);

    $router->post('/acudientes/parentesco', [
        AcudienteController::class,
        'guardarParentesco'
    ]);

    // Cursos (solo administrador)
    $router->get('/cursos', [
        CursoController::class,
        'listado'
    ]);

    $router->post('/cursos/nuevo', [
        CursoController::class,
        'guardar'
    ]);

    // Rutas: el administrador ve y edita todas; el auxiliar ve solo la suya
    $router->get('/rutas', [
        RutaController::class,
        'listado'
    ]);

    $router->post('/rutas/guardar', [
        RutaController::class,
        'guardar'
    ]);

    // Registro de asistencia en el bus (auxiliar)
    $router->get('/asistencia/registro', [
        AsistenciaController::class,
        'registro'
    ]);

    $router->post('/asistencia/marcar', [
        AsistenciaController::class,
        'marcar'
    ]);

    $router->post('/asistencia/deshacer', [
        AsistenciaController::class,
        'deshacer'
    ]);

    // El auxiliar cierra el recorrido de hoy: ya no se puede marcar ni deshacer
    $router->post('/asistencia/cerrar', [
        AsistenciaController::class,
        'cerrar'
    ]);

    // El administrador reabre un recorrido cerrado por error
    $router->post('/asistencias/reabrir', [
        AsistenciaController::class,
        'reabrir'
    ]);

    // Excel de asistencias: el administrador elige ruta; el auxiliar solo la suya
    $router->get('/asistencias/exportar', [
        AsistenciaController::class,
        'exportar'
    ]);

    $router->get('/asistencia/exportar', [
        AsistenciaController::class,
        'exportarMiRuta'
    ]);

    // Escáner QR del carné: registra y responde en JSON
    $router->post('/asistencia/escanear', [
        AsistenciaController::class,
        'escanear'
    ]);

    // Historial de la ruta del auxiliar (solo lectura)
    $router->get('/asistencia/historial', [
        AsistenciaController::class,
        'historial'
    ]);

    // Asistencias por fecha y ruta, y registro manual (administrador)
    $router->get('/asistencias', [
        AsistenciaController::class,
        'listado'
    ]);

    $router->post('/asistencias/manual', [
        AsistenciaController::class,
        'manual'
    ]);

?>
