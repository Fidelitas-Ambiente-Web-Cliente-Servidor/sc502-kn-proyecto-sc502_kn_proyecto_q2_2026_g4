<?php
// Se incluye el archivo que establece la conexión con la base de datos.
require_once __DIR__ . "/config/autenticacion.php";
require_once __DIR__ . "/config/conexion.php";
// Variable utilizada para mostrar mensajes al usuario.
$mensaje = "";

// Función para mostrar datos de forma segura dentro del HTML.
function escaparDoctor($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Se procesan las acciones enviadas desde los formularios.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        // Acciones para agregar o editar un doctor.
        if ($accion == "agregar" || $accion == "editar") {
            // Se reciben y validan los datos del formulario.
            $idEspecialidad = filter_var(
                $_POST["id_especialidad"] ?? null,
                FILTER_VALIDATE_INT
            );
            $nombre = trim($_POST["nombre"] ?? "");
            $apellido = trim($_POST["apellido"] ?? "");
            $telefono = trim($_POST["telefono"] ?? "");
            $correo = trim($_POST["correo"] ?? "");

            $correoValido = filter_var($correo, FILTER_VALIDATE_EMAIL);
            $telefonoValido = preg_match('/^[0-9+\-() ]{7,20}$/', $telefono);

            if (
                $idEspecialidad === false ||
                $idEspecialidad < 1 ||
                $nombre == "" ||
                $apellido == "" ||
                strlen($nombre) > 100 ||
                strlen($apellido) > 100 ||
                !$telefonoValido ||
                $correoValido === false
            ) {
                $mensaje = "Revise los datos ingresados en el formulario.";
            } elseif ($accion == "agregar") {
                // Consulta preparada para registrar un nuevo doctor.
                $sql = "INSERT INTO doctores
                            (id_especialidad, nombre, apellido, telefono, correo)
                        VALUES (?, ?, ?, ?, ?)";

                $consulta = $conexion->prepare($sql);
                $consulta->bind_param(
                    "issss",
                    $idEspecialidad,
                    $nombre,
                    $apellido,
                    $telefono,
                    $correo
                );
                $consulta->execute();
                $consulta->close();

                $mensaje = "Doctor registrado correctamente.";
            } else {
                // Para editar, se valida el identificador del doctor.
                $idDoctor = filter_var(
                    $_POST["id_doctor"] ?? null,
                    FILTER_VALIDATE_INT
                );

                if ($idDoctor === false || $idDoctor < 1) {
                    $mensaje = "El doctor seleccionado no es válido.";
                } else {
                    // Consulta preparada para actualizar el doctor seleccionado.
                    $sql = "UPDATE doctores
                            SET id_especialidad = ?,
                                nombre = ?,
                                apellido = ?,
                                telefono = ?,
                                correo = ?
                            WHERE id_doctor = ?";

                    $consulta = $conexion->prepare($sql);
                    $consulta->bind_param(
                        "issssi",
                        $idEspecialidad,
                        $nombre,
                        $apellido,
                        $telefono,
                        $correo,
                        $idDoctor
                    );
                    $consulta->execute();
                    $consulta->close();

                    $mensaje = "Doctor actualizado correctamente.";
                }
            }
        }

        // Acción para eliminar un doctor.
        if ($accion == "eliminar") {
            $idDoctor = filter_var(
                $_POST["id_doctor"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idDoctor === false || $idDoctor < 1) {
                $mensaje = "El doctor seleccionado no es válido.";
            } else {
                // La eliminación se realiza mediante una consulta preparada.
                $consulta = $conexion->prepare(
                    "DELETE FROM doctores WHERE id_doctor = ?"
                );
                $consulta->bind_param("i", $idDoctor);
                $consulta->execute();
                $consulta->close();

                $mensaje = "Doctor eliminado correctamente.";
            }
        }
    } catch (mysqli_sql_exception $error) {
        // El error técnico se registra sin mostrar detalles internos al usuario.
        error_log($error->getMessage());

        if ((int) $error->getCode() === 1062) {
            $mensaje = "Ya existe un doctor registrado con ese correo.";
        } elseif ((int) $error->getCode() === 1451) {
            $mensaje = "No se puede eliminar porque el doctor tiene información relacionada.";
        } else {
            $mensaje = "No fue posible completar la operación.";
        }
    }
}

// Variable que almacenará los datos del doctor seleccionado para editar.
$doctorEditar = null;

// Se consulta el doctor cuando la URL contiene el parámetro editar.
if (isset($_GET["editar"])) {
    $idEditar = filter_var($_GET["editar"], FILTER_VALIDATE_INT);

    if ($idEditar !== false && $idEditar > 0) {
        $consultaEditar = $conexion->prepare(
            "SELECT id_doctor, id_especialidad, nombre, apellido, telefono, correo
             FROM doctores
             WHERE id_doctor = ?"
        );
        $consultaEditar->bind_param("i", $idEditar);
        $consultaEditar->execute();
        $resultadoEditar = $consultaEditar->get_result();

        if ($resultadoEditar->num_rows > 0) {
            $doctorEditar = $resultadoEditar->fetch_assoc();
        }

        $consultaEditar->close();
    }
}

// Se consultan las especialidades para llenar el selector del formulario.
$sqlEspecialidades = "SELECT id_especialidad, nombre_especialidad
                      FROM especialidades
                      ORDER BY nombre_especialidad ASC";

$resultadoEspecialidades = $conexion->query($sqlEspecialidades);

// Se consultan los doctores junto con el nombre de su especialidad.
$sqlDoctores = "SELECT doctores.*, especialidades.nombre_especialidad
                FROM doctores
                INNER JOIN especialidades
                    ON doctores.id_especialidad = especialidades.id_especialidad
                ORDER BY doctores.apellido ASC, doctores.nombre ASC";

$resultadoDoctores = $conexion->query($sqlDoctores);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctores</title>

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
                    <li class="nav-item"><a href="servicios.php" class="nav-link">Servicios</a></li>
                    <li class="nav-item"><a href="especialidades.php" class="nav-link">Especialidades</a></li>
                    <li class="nav-item"><a href="doctores.php" class="nav-link">Doctores</a></li>
                    <li class="nav-item"><a href="horarios.php" class="nav-link">Horarios</a></li>
                    <li class="nav-item"><a href="citas.php" class="nav-link active">Citas</a></li>
                    <li class="nav-item"><a href="pacientes.php" class="nav-link">Pacientes</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Encabezado del módulo de doctores. -->
    <header class="encabezado">
        <div class="container text-center">
            <h1>Doctores</h1>
            <p>
                Módulo para registrar y administrar los profesionales de salud
                disponibles en la clínica.
            </p>
        </div>
    </header>

    <main class="container my-5">
        <!-- Mensajes generados por las operaciones del módulo. -->
        <?php if ($mensaje != "") { ?>
            <div class="alert alert-info text-center">
                <?php echo escaparDoctor($mensaje); ?>
            </div>
        <?php } ?>

        <!-- Formulario utilizado para registrar y editar doctores. -->
        <section class="contenedor-formulario mb-5">
            <h2>
                <?php
                    echo $doctorEditar != null
                        ? "Editar doctor"
                        : "Registrar nuevo doctor";
                ?>
            </h2>

            <form method="POST" action="doctores.php">
                <?php if ($doctorEditar != null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input
                        type="hidden"
                        name="id_doctor"
                        value="<?php echo (int) $doctorEditar["id_doctor"]; ?>"
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
                                echo escaparDoctor($doctorEditar["nombre"] ?? "");
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
                                echo escaparDoctor($doctorEditar["apellido"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Especialidad</label>
                        <select name="id_especialidad" class="form-select" required>
                            <option value="">Seleccione una especialidad</option>

                            <?php while ($especialidad = $resultadoEspecialidades->fetch_assoc()) { ?>
                                <option
                                    value="<?php echo (int) $especialidad["id_especialidad"]; ?>"
                                    <?php
                                        echo (
                                            $doctorEditar != null &&
                                            $doctorEditar["id_especialidad"] ==
                                            $especialidad["id_especialidad"]
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php
                                        echo escaparDoctor(
                                            $especialidad["nombre_especialidad"]
                                        );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
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
                                echo escaparDoctor($doctorEditar["telefono"] ?? "");
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            name="correo"
                            class="form-control"
                            maxlength="150"
                            required
                            value="<?php
                                echo escaparDoctor($doctorEditar["correo"] ?? "");
                            ?>"
                        >
                    </div>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php
                        echo $doctorEditar != null
                            ? "Guardar cambios"
                            : "Agregar doctor";
                    ?>
                </button>

                <?php if ($doctorEditar != null) { ?>
                    <a href="doctores.php" class="btn btn-secondary">
                        Cancelar edición
                    </a>
                <?php } ?>
            </form>
        </section>

        <!-- Listado de doctores obtenidos desde la base de datos. -->
        <section>
            <h2 class="mb-4">Doctores registrados</h2>

            <?php if ($resultadoDoctores->num_rows == 0) { ?>
                <div class="alert alert-secondary">
                    No hay doctores registrados.
                </div>
            <?php } else { ?>
                <div class="row g-4">
                    <?php while ($doctor = $resultadoDoctores->fetch_assoc()) { ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card tarjeta-servicio h-100">
                                <div class="card-body">
                                    <h3>
                                        <?php
                                            echo escaparDoctor(
                                                $doctor["nombre"] . " " . $doctor["apellido"]
                                            );
                                        ?>
                                    </h3>

                                    <p>
                                        <strong>Especialidad:</strong>
                                        <?php echo escaparDoctor($doctor["nombre_especialidad"]); ?>
                                    </p>

                                    <p>
                                        <strong>Teléfono:</strong>
                                        <?php echo escaparDoctor($doctor["telefono"]); ?>
                                    </p>

                                    <p>
                                        <strong>Correo:</strong>
                                        <?php echo escaparDoctor($doctor["correo"]); ?>
                                    </p>

                                    <a
                                        href="doctores.php?editar=<?php
                                            echo (int) $doctor["id_doctor"];
                                        ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        action="doctores.php"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea eliminar este doctor?');"
                                    >
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input
                                            type="hidden"
                                            name="id_doctor"
                                            value="<?php echo (int) $doctor["id_doctor"]; ?>"
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