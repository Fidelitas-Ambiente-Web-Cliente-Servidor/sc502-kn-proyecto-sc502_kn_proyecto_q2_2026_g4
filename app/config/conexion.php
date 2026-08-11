<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servidor = "db";
$usuario = "root";
$contrasena = "root";
$baseDatos = "clinica_salud_local";

try {
    $conexion = new mysqli(
        $servidor,
        $usuario,
        $contrasena,
        $baseDatos
    );

    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $error) {
    die("No se pudo conectar con la base de datos: " . $error->getMessage());
}