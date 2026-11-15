# Despliegue en Azure App Service (PHP 8.x, Linux)

## 1. Base de datos
1. Crea un **Azure Database for MySQL – Flexible Server** (tiene costo aparte del App Service) y una base llamada `ruta_escolar_v3`.
2. En **Redes**, permite el acceso de servicios de Azure (o añade tu IP para cargar los scripts).
3. Ejecuta en este orden (cliente MySQL, Workbench o `mysql`):
   - `database/ruta_escolar_v3.sql` (omite la línea `CREATE DATABASE` si ya la creaste)
   - `database/procedimientos_almacenados_ruta.sql`
   - `database/vistas_rutas.sql`
   - `database/datos_prueba.sql` (solo para pruebas; son datos ficticios)

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

Si renombras `auxiliar.php` en un repositorio ya existente, Git ignora el cambio de mayúsculas; hazlo en dos pasos:

    git mv app/Models/auxiliar.php app/Models/tmp.php
    git mv app/Models/tmp.php app/Models/Auxiliar.php
