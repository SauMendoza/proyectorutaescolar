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

    public static function redirect(string $path): void
    {
        header('Location: ' . self::to($path));
        exit;
    }
}
