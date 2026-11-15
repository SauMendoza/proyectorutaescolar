<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

class HomeController
{
    public function index()
    {
        Auth::requireRole("administrador");

        echo "Hola mundo";

        echo '
            <form action="' . htmlspecialchars(Url::to('/logout'), ENT_QUOTES, 'UTF-8') . '" method="POST">
                ' . Csrf::field() . '
                <button type="submit">Cerrar sesión</button>
            </form>
        ';
    }
}
