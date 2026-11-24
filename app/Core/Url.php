<?php

namespace App\Core;

class Url
{
    // Carpeta base desde la que se sirve index.php ('' si está en la raíz del sitio).
    public static function base(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        return rtrim($dir, '/');
    }

    // Construye una URL interna: Url::to('/login') -> /login (o /carpeta/login en local).
    public static function to(string $path = '/'): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }

    // Dirección de un archivo de public/ con su versión: /js/rutas.js?v=1764000000
    // Al cambiar el archivo cambia la versión y el navegador deja de usar la copia vieja.
    public static function recurso(string $path): string
    {
        $archivo = dirname(__DIR__, 2) . '/public' . $path;
        $version = is_file($archivo) ? '?v=' . filemtime($archivo) : '';
        return htmlspecialchars(self::to($path), ENT_QUOTES, 'UTF-8') . $version;
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . self::to($path));
        exit;
    }
}
