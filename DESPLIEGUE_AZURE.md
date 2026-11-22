# Despliegue en Azure App Service (PHP 8.x, Linux)

## 1. Base de datos
1. Crea un **Azure Database for MySQL – Flexible Server** (tiene costo aparte del App Service) y una base llamada `ruta_escolar_v3`.
2. En **Redes**, permite el acceso de servicios de Azure (o añade tu IP para cargar los scripts).
3. Ejecuta en este orden (cliente MySQL, Workbench o `mysql`):
   - `database/ruta_escolar_v3.sql`: tablas y datos de ejemplo. **Borra la base `ruta_escolar_v3` si ya existe**; si la base ya tiene datos reales, no lo ejecutes.
   - `database/objetos_bd.sql`: funciones, triggers, vistas (con CTE), índices y procedimientos con transacciones. La página los necesita. Se puede ejecutar más de una vez.
   - Ya no hacen falta `procedimientos_almacenados_ruta.sql`, `vistas_rutas.sql` ni `datos_prueba.sql`: todo está en los dos archivos anteriores.
   - Si la base de Azure ya existía con la estructura vieja y no quieres borrarla, ejecuta en su lugar `campos_usuario_er.sql`, `auxiliares_er.sql` y luego `objetos_bd.sql`.
4. Si al crear funciones o triggers aparece el error *"You do not have the SUPER privilege and binary logging is enabled"*, en el servidor MySQL ve a **Parámetros del servidor** y pon `log_bin_trust_function_creators = ON`.
5. La conexión usa la hora de Colombia (`SET time_zone = '-05:00'` en `app/Database/Connection.php`), así "hoy" es el mismo día en la página y en la base aunque Azure trabaje en UTC.

## 2. Primer administrador
Desde tu equipo, con un `.env` local apuntando a la base de Azure y `DB_SSL=true`:

    composer install
    php database/crear_admin.php correo@dominio.com Nombre Apellido

## 3. App Service
- Pila: **PHP 8.3**, Linux. Activa **autenticación básica SCM** (Configuración general).
- **Variables de entorno** (reemplazan al `.env`, que no se sube):
  `APP_ENV=production`, `APP_DEBUG=false`, `DB_HOST`, `DB_PORT=3306`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SSL=true`
- **Comando de inicio** (usa el archivo `default` de la raíz, que apunta a `public/`):

      cp /home/site/wwwroot/default /etc/nginx/sites-available/default && service nginx reload

- Activa **Solo HTTPS**.

## 4. Repositorio y workflow
Sube el contenido de esta carpeta a la **raíz** del repositorio y conéctalo desde el Centro de implementación. El workflow generado por Azure ejecuta `composer install`, por eso `vendor/` no se versiona.

El workflow solo despliega cuando hay cambios en la rama `main`. Lo que se sube a otra rama (por ejemplo `Reynier`) llega a Azure cuando se une a `main` con un pull request.

Todos los formularios POST llevan un token CSRF (`app/Core/Csrf.php`) y el `Router` lo revisa en cada POST. Si agregas un formulario nuevo, pon dentro `<?= \App\Core\Csrf::field() ?>`.

Si renombras `auxiliar.php` en un repositorio ya existente, Git ignora el cambio de mayúsculas; hazlo en dos pasos:

    git mv app/Models/auxiliar.php app/Models/tmp.php
    git mv app/Models/tmp.php app/Models/Auxiliar.php
