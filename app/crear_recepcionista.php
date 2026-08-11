<?php

// Este archivo es temporal y solo puede ejecutarlo un administrador.
require_once __DIR__ . "/config/solo_admin.php";
require_once __DIR__ . "/config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Datos de la cuenta de prueba.
$nombre = "Recepcionista Clínica";
$correo = "recepcion@clinica.test";
$contrasenaTemporal = "Recepcion1234";
$rol = "Recepcionista";
$activo = 1;

try {
    // La contraseña nunca se guarda directamente en la base de datos.
    $contrasenaCifrada = password_hash(
        $contrasenaTemporal,
        PASSWORD_DEFAULT
    );

    $consulta = $conexion->prepare(
        "INSERT INTO usuarios_admin
            (nombre, correo, contrasena, rol, activo)
         VALUES (?, ?, ?, ?, ?)"
    );

    $consulta->bind_param(
        "ssssi",
        $nombre,
        $correo,
        $contrasenaCifrada,
        $rol,
        $activo
    );

    $consulta->execute();
    $consulta->close();

    $mensaje = "La cuenta de recepcionista fue creada correctamente.";
    $tipoAlerta = "success";
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());

    if ((int) $error->getCode() === 1062) {
        $mensaje = "Ya existe una cuenta registrada con ese correo.";
        $tipoAlerta = "warning";
    } else {
        $mensaje = "No fue posible crear la cuenta de recepcionista.";
        $tipoAlerta = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear recepcionista</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 650px;">
            <div class="card-body p-4">
                <h1 class="h3 mb-4">Creación de recepcionista</h1>

                <div class="alert alert-<?= htmlspecialchars($tipoAlerta) ?>">
                    <?= htmlspecialchars($mensaje) ?>
                </div>

                <?php if ($tipoAlerta === "success"): ?>
                    <p><strong>Nombre:</strong> Recepcionista Clínica</p>
                    <p><strong>Correo:</strong> recepcion@clinica.test</p>
                    <p><strong>Contraseña temporal:</strong> Recepcion1234</p>
                    <p><strong>Rol:</strong> Recepcionista</p>
                <?php endif; ?>

                <div class="alert alert-danger mb-0">
                    Elimina <strong>crear_recepcionista.php</strong> después de
                    crear la cuenta.
                </div>
            </div>
        </div>
    </main>
</body>
</html>
