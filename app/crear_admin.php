<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once "config/conexion.php";

$nombre = "Administrador Principal";
$correo = "admin@clinica.test";
$contrasena = "Admin1234";
$rol = "Administrador";

$contrasenaCifrada = password_hash(
    $contrasena,
    PASSWORD_DEFAULT
);

try {
    $consulta = $conexion->prepare(
        "INSERT INTO usuarios_admin
            (nombre, correo, contrasena, rol, activo)
         VALUES (?, ?, ?, ?, 1)"
    );

    $consulta->bind_param(
        "ssss",
        $nombre,
        $correo,
        $contrasenaCifrada,
        $rol
    );

    $consulta->execute();
    $consulta->close();

    echo "Administrador creado correctamente.";
} catch (mysqli_sql_exception $error) {

    if ((int) $error->getCode() === 1062) {
        echo "El administrador ya se encuentra registrado.";
    } else {
        error_log($error->getMessage());
        echo "No fue posible crear el administrador.";
    }
}