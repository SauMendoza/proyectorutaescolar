<?php

namespace App\Core;

// Protección contra CSRF: un token por sesión que viaja en un campo oculto.
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // HTML del campo oculto para pegar dentro de cada formulario POST.
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    // Detiene la petición si el token no coincide.
    public static function verify(): void
    {
        $enviado = $_POST['csrf_token'] ?? '';
        $guardado = $_SESSION['csrf_token'] ?? '';

        if ($guardado === '' || !is_string($enviado) || !hash_equals($guardado, $enviado)) {
            http_response_code(403);
            echo 'La sesión expiró o la solicitud no es válida. Recarga la página e inténtalo de nuevo.';
            exit;
        }
    }
}
