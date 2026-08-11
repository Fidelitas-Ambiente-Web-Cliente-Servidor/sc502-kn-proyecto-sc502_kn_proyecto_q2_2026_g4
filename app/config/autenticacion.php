<?php

// Este archivo protege las páginas administrativas de la clínica.
// Debe incluirse al inicio del archivo, antes de mostrar cualquier HTML.

ini_set("session.use_strict_mode", "1");

// La cookie solo se marca como segura cuando el sitio utiliza HTTPS.
$conexionSegura = !empty($_SERVER["HTTPS"])
    && $_SERVER["HTTPS"] !== "off";

// Configuración de seguridad para la cookie de sesión.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $conexionSegura,
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_start();
}

// Evita que el navegador guarde páginas privadas en la caché.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// La sesión se cierra después de 30 minutos sin actividad.
$tiempoMaximoInactividad = 30 * 60;
$horaActual = time();

// Si no existe un administrador autenticado, se envía al inicio de sesión.
if (empty($_SESSION["admin_id"])) {
    header("Location: /login.php");
    exit;
}

// Comprueba si la sesión superó el tiempo permitido de inactividad.
if (
    isset($_SESSION["ultimo_acceso"])
    && ($horaActual - (int) $_SESSION["ultimo_acceso"]) > $tiempoMaximoInactividad
) {
    $_SESSION = [];

    // Elimina también la cookie de sesión del navegador.
    if (ini_get("session.use_cookies")) {
        $parametrosCookie = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $parametrosCookie["path"],
            $parametrosCookie["domain"],
            $parametrosCookie["secure"],
            $parametrosCookie["httponly"]
        );
    }

    session_destroy();

    header("Location: /login.php?sesion=expirada");
    exit;
}

// Actualiza el momento de la última actividad del usuario.
$_SESSION["ultimo_acceso"] = $horaActual;
