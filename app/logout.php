<?php

// Inicia la sesión que actualmente utiliza el administrador.
session_start();

// Elimina todas las variables almacenadas en la sesión.
$_SESSION = [];
session_unset();

// Elimina la cookie de sesión del navegador.
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        "",
        time() - 3600,
        "/"
    );
}

// Destruye definitivamente la sesión en el servidor.
session_destroy();

// Regresa al formulario de inicio de sesión.
header("Location: /login.php?sesion=cerrada");
exit;