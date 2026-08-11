<?php
// Se incluye el archivo que establece la conexión con la base de datos.
require_once __DIR__ . "/config/autenticacion.php";
require_once __DIR__ . "/config/conexion.php";
// Variable utilizada para mostrar mensajes al usuario.
$mensaje = "";

// Función para mostrar datos de forma segura dentro del HTML.
function escaparPaciente($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Se procesan las acciones enviadas desde los formularios.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        // Acciones para agregar o editar un paciente.
        if ($accion == "agregar" || $accion == "editar") {
            // Se reciben los datos enviados desde el formulario.
            $nombre = trim($_POST["nombre"] ?? "");
            $apellido = trim($_POST["apellido"] ?? "");
            $cedula = trim($_POST["cedula"] ?? "");
            $telefono = trim($_POST["telefono"] ?? "");
            $correo = trim($_POST["correo"] ?? "");
            $fechaNacimiento = $_POST["fecha_nacimiento"] ?? "";
            $contrasena = $_POST["contrasena"] ?? "";

            // Se validan la cédula, el teléfono, el correo y la fecha.
            $cedulaValida = preg_match('/^[A-Za-z0-9-]{6,20}$/', $cedula);
            $telefonoValido = preg_match('/^[0-9+\-() ]{7,20}$/', $telefono);
            $correoValido = filter_var($correo, FILTER_VALIDATE_EMAIL);
            $fecha = DateTime::createFromFormat("Y-m-d", $fechaNacimiento);
            $fechaValida = $fecha !== false &&
                $fecha->format("Y-m-d") === $fechaNacimiento &&
                $fechaNacimiento <= date("Y-m-d");

            // Para editar, se valida también el identificador del paciente.
            $idPaciente = 0;

            if ($accion == "editar") {
                $idPaciente = filter_var(
                    $_POST["id_paciente"] ?? null,
                    FILTER_VALIDATE_INT
                );
            }

            // La contraseña es obligatoria al registrar y opcional al editar.
            $contrasenaValida = $accion == "agregar"
                ? strlen($contrasena) >= 8
                : ($contrasena == "" || strlen($contrasena) >= 8);

            if (
                $nombre == "" ||
                $apellido == "" ||
                strlen($nombre) > 100 ||
                strlen($apellido) > 100 ||
                !$cedulaValida ||
                !$telefonoValido ||
                $correoValido === false ||
                !$fechaValida ||
                !$contrasenaValida ||
                ($accion == "editar" && ($idPaciente === false || $idPaciente < 1))
            ) {
                $mensaje = "Revise los datos. La contraseña debe tener al menos 8 caracteres.";
            } elseif ($accion == "agregar") {
                // La contraseña se cifra antes de almacenarse en la base de datos.
                $contrasenaCifrada = password_hash($contrasena, PASSWORD_DEFAULT);

                // Consulta preparada para registrar un nuevo paciente.
                $sql = "INSERT INTO pacientes
                            (nombre, apellido, cedula, telefono, correo, fecha_nacimiento, contrasena)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

                $consulta = $conexion->prepare($sql);
                $consulta->bind_param(
                    "sssssss",
                    $nombre,
                    $apellido,
                    $cedula,
                    $telefono,
                    $correo,
                    $fechaNacimiento,
                    $contrasenaCifrada
                );
                $consulta->execute();
                $consulta->close();

                $mensaje = "Paciente registrado correctamente.";
            } else {
                if ($contrasena != "") {
                    // Si se escribió una nueva contraseña, se cifra y actualiza.
                    $contrasenaCifrada = password_hash($contrasena, PASSWORD_DEFAULT);

                    $sql = "UPDATE pacientes
                            SET nombre = ?,
                                apellido = ?,
                                cedula = ?,
                                telefono = ?,
                                correo = ?,
                                fecha_nacimiento = ?,
                                contrasena = ?
                            WHERE id_paciente = ?";

                    $consulta = $conexion->prepare($sql);
                    $consulta->bind_param(
                        "sssssssi",
                        $nombre,
                        $apellido,
                        $cedula,
                        $telefono,
                        $correo,
                        $fechaNacimiento,
                        $contrasenaCifrada,
                        $idPaciente
                    );
                } else {
                    // Si el campo está vacío, se conserva la contraseña actual.
                    $sql = "UPDATE pacientes
                            SET nombre = ?,
                                apellido = ?,
                                cedula = ?,
                                telefono = ?,
                                correo = ?,
                                fecha_nacimiento = ?
                            WHERE id_paciente = ?";

                    $consulta = $conexion->prepare($sql);
                    $consulta->bind_param(
                        "ssssssi",
                        $nombre,
                        $apellido,
                        $cedula,
                        $telefono,
                        $correo,
                        $fechaNacimiento,
                        $idPaciente
                    );
                }

                $consulta->execute();
                $consulta->close();

                $mensaje = "Paciente actualizado correctamente.";
            }
        }

        // Acción para eliminar un paciente.
        if ($accion == "eliminar") {
            $idPaciente = filter_var(
                $_POST["id_paciente"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idPaciente === false || $idPaciente < 1) {
                $mensaje = "El paciente seleccionado no es válido.";
            } else {
                $consulta = $conexion->prepare(
                    "DELETE FROM pacientes WHERE id_paciente = ?"
                );
                $consulta->bind_param("i", $idPaciente);
                $consulta->execute();
                $consulta->close();

                $mensaje = "Paciente eliminado correctamente.";
            }
        }
    } catch (mysqli_sql_exception $error) {
        // El error técnico se registra sin mostrar detalles internos al usuario.
        error_log($error->getMessage());

        if ((int) $error->getCode() === 1062) {
            $mensaje = "La cédula o el correo ya se encuentran registrados.";
        } elseif ((int) $error->getCode() === 1451) {
            $mensaje = "No se puede eliminar porque el paciente tiene citas relacionadas.";
        } else {
            $mensaje = "No fue posible completar la operación.";
        }
    }
}

// Variable que almacenará los datos del paciente seleccionado para editar.
$pacienteEditar = null;

// Se consulta el paciente cuando la URL contiene el parámetro editar.
if (isset($_GET["editar"])) {
    $idEditar = filter_var($_GET["editar"], FILTER_VALIDATE_INT);

    if ($idEditar !== false && $idEditar > 0) {
        // La contraseña no se consulta ni se envía nuevamente al formulario.
        $consultaEditar = $conexion->prepare(
            "SELECT id_paciente, nombre, apellido, cedula, telefono, correo, fecha_nacimiento
             FROM pacientes
             WHERE id_paciente = ?"
        );
        $consultaEditar->bind_param("i", $idEditar);
        $consultaEditar->execute();
        $resultadoEditar = $consultaEditar->get_result();

        if ($resultadoEditar->num_rows > 0) {
            $pacienteEditar = $resultadoEditar->fetch_assoc();
        }

        $consultaEditar->close();
    }
}

// Se consultan los pacientes sin incluir sus contraseñas.
$sqlPacientes = "SELECT id_paciente,
                        nombre,
                        apellido,
                        cedula,
                        telefono,
                        correo,
                        fecha_nacimiento
                 FROM pacientes
                 ORDER BY apellido ASC, nombre ASC";

$resultadoPacientes = $conexion->query($sqlPacientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacientes</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="css/servicios.css">
</head>

<body>
    <!-- Menú principal de navegación. -->
    <nav class="navbar navbar-expand-lg navbar-dark menu-principal">
        <div class="container">
            <a class="navbar-brand fw-bold" href="servicios.php">
                Clínica Salud Local
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menu"
                aria-controls="menu"
                aria-expanded="false"
                aria-label="Mostrar navegación"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="servicios.php" class="nav-link">Servicios</a>
                    </li>
                    <li class="nav-item">
                        <a href="especialidades.php" class="nav-link">Especialidades</a>
                    </li>
                    <li class="nav-item">
                        <a href="doctores.php" class="nav-link">Doctores</a>
                    </li>
                    <li class="nav-item">
                        <a href="horarios.php" class="nav-link">Horarios</a>
                    </li>
                    <li class="nav-item"><a href="#" class="nav-link">Citas</a></li>
                    <li class="nav-item">
                        <a href="pacientes.php" class="nav-link active">Pacientes</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Encabezado del módulo de pacientes. -->
    <header class="encabezado">
        <div class="container text-center">
            <h1>Pacientes</h1>
            <p>
                Módulo para registrar y administrar la información general
                de los pacientes de la clínica.
            </p>
        </div>
    </header>

    <main class="container my-5">
        <!-- Mensajes generados por las operaciones del módulo. -->
        <?php if ($mensaje != "") { ?>
            <div class="alert alert-info text-center">
                <?php echo escaparPaciente($mensaje); ?>
            </div>
        <?php } ?>

        <!-- Formulario utilizado para registrar y editar pacientes. -->
        <section class="contenedor-formulario mb-5">
            <h2>
                <?php
                    echo $pacienteEditar != null
                        ? "Editar paciente"
                        : "Registrar nuevo paciente";
                ?>
            </h2>

            <form method="POST" action="pacientes.php">
                <?php if ($pacienteEditar != null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input
                        type="hidden"
                        name="id_paciente"
                        value="<?php echo (int) $pacienteEditar["id_paciente"]; ?>"
                    >
                <?php } else { ?>
                    <input type="hidden" name="accion" value="agregar">
                <?php } ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre</label>
                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?php
                                echo escaparPaciente($pacienteEditar["nombre"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellido</label>
                        <input
                            type="text"
                            name="apellido"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?php
                                echo escaparPaciente($pacienteEditar["apellido"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cédula o identificación</label>
                        <input
                            type="text"
                            name="cedula"
                            class="form-control"
                            minlength="6"
                            maxlength="20"
                            pattern="[A-Za-z0-9-]{6,20}"
                            required
                            value="<?php
                                echo escaparPaciente($pacienteEditar["cedula"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input
                            type="tel"
                            name="telefono"
                            class="form-control"
                            maxlength="20"
                            pattern="[0-9+() \-]{7,20}"
                            required
                            value="<?php
                                echo escaparPaciente($pacienteEditar["telefono"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input
                            type="date"
                            name="fecha_nacimiento"
                            class="form-control"
                            max="<?php echo date("Y-m-d"); ?>"
                            required
                            value="<?php
                                echo escaparPaciente(
                                    $pacienteEditar["fecha_nacimiento"] ?? ""
                                );
                            ?>"
                        >
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            name="correo"
                            class="form-control"
                            maxlength="150"
                            required
                            value="<?php
                                echo escaparPaciente($pacienteEditar["correo"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <?php
                                echo $pacienteEditar != null
                                    ? "Nueva contraseña (opcional)"
                                    : "Contraseña";
                            ?>
                        </label>
                        <input
                            type="password"
                            name="contrasena"
                            class="form-control"
                            minlength="8"
                            autocomplete="new-password"
                            <?php echo $pacienteEditar == null ? "required" : ""; ?>
                        >
                        <div class="form-text">
                            Debe contener al menos 8 caracteres.
                            <?php if ($pacienteEditar != null) { ?>
                                Déjela vacía para conservar la contraseña actual.
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php
                        echo $pacienteEditar != null
                            ? "Guardar cambios"
                            : "Agregar paciente";
                    ?>
                </button>

                <?php if ($pacienteEditar != null) { ?>
                    <a href="pacientes.php" class="btn btn-secondary">
                        Cancelar edición
                    </a>
                <?php } ?>
            </form>
        </section>

        <!-- Listado de pacientes obtenidos desde la base de datos. -->
        <section>
            <h2 class="mb-4">Pacientes registrados</h2>

            <?php if ($resultadoPacientes->num_rows == 0) { ?>
                <div class="alert alert-secondary">
                    No hay pacientes registrados.
                </div>
            <?php } else { ?>
                <div class="row g-4">
                    <?php while ($paciente = $resultadoPacientes->fetch_assoc()) { ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card tarjeta-servicio h-100">
                                <div class="card-body">
                                    <h3>
                                        <?php
                                            echo escaparPaciente(
                                                $paciente["nombre"] . " " .
                                                $paciente["apellido"]
                                            );
                                        ?>
                                    </h3>

                                    <p>
                                        <strong>Identificación:</strong>
                                        <?php echo escaparPaciente($paciente["cedula"]); ?>
                                    </p>

                                    <p>
                                        <strong>Teléfono:</strong>
                                        <?php echo escaparPaciente($paciente["telefono"]); ?>
                                    </p>

                                    <p>
                                        <strong>Correo:</strong>
                                        <?php echo escaparPaciente($paciente["correo"]); ?>
                                    </p>

                                    <p>
                                        <strong>Fecha de nacimiento:</strong>
                                        <?php
                                            echo escaparPaciente(
                                                date(
                                                    "d/m/Y",
                                                    strtotime($paciente["fecha_nacimiento"])
                                                )
                                            );
                                        ?>
                                    </p>

                                    <a
                                        href="pacientes.php?editar=<?php
                                            echo (int) $paciente["id_paciente"];
                                        ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        action="pacientes.php"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea eliminar este paciente?');"
                                    >
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input
                                            type="hidden"
                                            name="id_paciente"
                                            value="<?php echo (int) $paciente["id_paciente"]; ?>"
                                        >
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </main>

    <!-- Pie de página del sistema. -->
    <footer class="pie-pagina text-center">
        <p>© 2026 Clínica Salud Local - Sistema de Gestión y Reserva de Citas</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>