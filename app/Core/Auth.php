<?php

namespace App\Core;

class Auth
{
    
    //Comprueba si existe una sesión autenticada.
    
    // Si el usuario tiene una contraseña temporal, lo manda a cambiarla antes de seguir
    // (menos en la propia pantalla de cambio, que llama con $permitirTemporal = true).
    public static function requireLogin(bool $permitirTemporal = false): void
    {
        // Evitamos que el navegador almacene páginas protegidas.
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header('Content-Type: text/html');


        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . \App\Core\Url::base() . '/login');
            exit;
        }

        if (!$permitirTemporal && !empty($_SESSION['usuario']['cambiar_contrasena'])) {
            header('Location: ' . \App\Core\Url::base() . '/cambiar-contrasena');
            exit;
        }
    }

    //Comprueba si el usuario tiene el rol requerido.
     
    public static function requireRole(string $rol): void
    {
        self::requireLogin();

        if ($_SESSION['usuario']['rol'] !== $rol) {
            http_response_code(403);
            echo '403 - No tienes permiso para acceder a esta página.';
            exit;
        }
    }
}
