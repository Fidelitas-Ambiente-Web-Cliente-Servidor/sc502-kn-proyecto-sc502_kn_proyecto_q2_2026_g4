<?php
// Se aplican opciones seguras a la cookie de sesión antes de iniciar la sesión.
ini_set("session.use_strict_mode", "1");

$conexionSegura = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => $conexionSegura,
    "httponly" => true,
    "samesite" => "Lax",
]);

session_start();

// Si ya existe una sesión administrativa, se evita mostrar nuevamente el login.
if (isset($_SESSION["admin_id"])) {
    header("Location: citas.php");
    exit;
}

require_once "config/conexion.php";

$mensaje = "";
$correoIngresado = "";

// Se procesa el formulario cuando se envía mediante POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correoIngresado = trim($_POST["correo"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if (
        filter_var($correoIngresado, FILTER_VALIDATE_EMAIL) === false ||
        $contrasena == ""
    ) {
        $mensaje = "Ingrese un correo y una contraseña válidos.";
    } else {
        try {
            // Se consulta únicamente el administrador activo asociado al correo.
            $consulta = $conexion->prepare(
                "SELECT id_admin, nombre, correo, contrasena, rol
                 FROM usuarios_admin
                 WHERE correo = ? AND activo = 1
                 LIMIT 1"
            );
            $consulta->bind_param("s", $correoIngresado);
            $consulta->execute();
            $administrador = $consulta->get_result()->fetch_assoc();
            $consulta->close();

            // Se compara la contraseña escrita con el hash almacenado.
            if (
                $administrador !== null &&
                password_verify($contrasena, $administrador["contrasena"])
            ) {
                // Se cambia el identificador de sesión después del acceso correcto.
                session_regenerate_id(true);

                $_SESSION["admin_id"] = (int) $administrador["id_admin"];
                $_SESSION["admin_nombre"] = $administrador["nombre"];
                $_SESSION["admin_correo"] = $administrador["correo"];
                $_SESSION["admin_rol"] = $administrador["rol"];
                $_SESSION["ultimo_acceso"] = time();

                header("Location: citas.php");
                exit;
            }

            // El mensaje no indica cuál de los dos datos fue incorrecto.
            $mensaje = "Correo o contraseña incorrectos.";
        } catch (mysqli_sql_exception $error) {
            error_log($error->getMessage());
            $mensaje = "No fue posible iniciar sesión. Inténtelo nuevamente.";
        }
    }
}

function escaparLogin($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Clínica Salud Local</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="css/servicios.css">

    <style>
        .pagina-login {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-color: #e9f5f8;
        }

        .tarjeta-login {
            width: 100%;
            max-width: 430px;
            padding: 32px;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .tarjeta-login h1 {
            color: #0b4f6c;
            font-size: 30px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <main class="pagina-login">
        <section class="card tarjeta-login">
            <div class="text-center mb-4">
                <h1>Clínica Salud Local</h1>
                <p class="text-muted mb-0">Acceso administrativo</p>
            </div>

            <?php if ($mensaje != "") { ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escaparLogin($mensaje); ?>
                </div>
            <?php } ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="correo" class="form-label">Correo electrónico</label>
                    <input
                        id="correo"
                        type="email"
                        name="correo"
                        class="form-control"
                        maxlength="150"
                        autocomplete="username"
                        required
                        value="<?php echo escaparLogin($correoIngresado); ?>"
                    >
                </div>

                <div class="mb-4">
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <input
                        id="contrasena"
                        type="password"
                        name="contrasena"
                        class="form-control"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-servicio">
                    Iniciar sesión
                </button>
            </form>
        </section>
    </main>
</body>
</html>