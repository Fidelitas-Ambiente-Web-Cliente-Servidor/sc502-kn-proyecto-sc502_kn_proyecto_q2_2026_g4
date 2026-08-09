<?php

$servidor = "db";
$usuario = "root";
$contrasena = "root";
$baseDatos = "clinica_salud_local";

$conexion = new mysqli($servidor, $usuario, $contrasena, $baseDatos);

if ($conexion->connect_error) {
    die("Error de conexión con la base de datos: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");
?>