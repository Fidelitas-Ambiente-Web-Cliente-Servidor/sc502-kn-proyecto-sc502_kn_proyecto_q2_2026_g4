<?php
// Se incluye el archivo que crea la conexión con la base de datos.
require_once __DIR__ . "/config/autenticacion.php";
require_once __DIR__ . "/config/conexion.php";

// Variable utilizada para mostrar mensajes de éxito o error en la página.
$mensaje = "";

// Función para mostrar información de forma segura dentro del HTML.
function escaparServicio($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Se procesan únicamente las solicitudes enviadas mediante los formularios.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // La acción permite identificar si se agregará, editará o eliminará un servicio.
    $accion = $_POST["accion"] ?? "";

    try {
        // Acciones para registrar o actualizar un servicio médico.
        if ($accion == "agregar" || $accion == "editar") {
            // Se reciben y validan los datos enviados desde el formulario.
            $nombre = trim($_POST["nombre_servicio"] ?? "");
            $descripcion = trim($_POST["descripcion"] ?? "");
            $costo = filter_var($_POST["costo"] ?? null, FILTER_VALIDATE_FLOAT);
            $estado = $_POST["estado"] ?? "";
            $idEspecialidad = filter_var(
                $_POST["id_especialidad"] ?? null,
                FILTER_VALIDATE_INT
            );
            $duracion = filter_var(
                $_POST["duracion"] ?? null,
                FILTER_VALIDATE_INT
            );

            // Se comprueba que todos los datos obligatorios sean válidos.
            if (
                $nombre == "" ||
                $descripcion == "" ||
                $costo === false ||
                $costo < 0 ||
                $idEspecialidad === false ||
                $idEspecialidad < 1 ||
                $duracion === false ||
                $duracion < 5 ||
                !in_array($estado, ["Activo", "Inactivo"], true)
            ) {
                $mensaje = "Revise los datos ingresados en el formulario.";
            } elseif ($accion == "agregar") {
                // Consulta preparada para insertar un nuevo servicio médico.
                $sql = "INSERT INTO servicio_medico
                            (id_especialidad, nombre_servicio, descripcion, duracion, costo, estado)
                        VALUES (?, ?, ?, ?, ?, ?)";

                // Se prepara, enlaza y ejecuta la consulta de inserción.
                $consulta = $conexion->prepare($sql);
                $consulta->bind_param(
                    "issids",
                    $idEspecialidad,
                    $nombre,
                    $descripcion,
                    $duracion,
                    $costo,
                    $estado
                );
                $consulta->execute();
                $consulta->close();

                $mensaje = "Servicio médico agregado correctamente.";
            } else {
                // Para editar, se valida el identificador del servicio seleccionado.
                $id = filter_var(
                    $_POST["id_servicio"] ?? null,
                    FILTER_VALIDATE_INT
                );

                if ($id === false || $id < 1) {
                    $mensaje = "El servicio seleccionado no es válido.";
                } else {
                    // Consulta preparada para actualizar el servicio existente.
                    $sql = "UPDATE servicio_medico
                            SET id_especialidad = ?,
                                nombre_servicio = ?,
                                descripcion = ?,
                                duracion = ?,
                                costo = ?,
                                estado = ?
                            WHERE id_servicio = ?";

                    // Se enlazan los valores y se ejecuta la actualización.
                    $consulta = $conexion->prepare($sql);
                    $consulta->bind_param(
                        "issidsi",
                        $idEspecialidad,
                        $nombre,
                        $descripcion,
                        $duracion,
                        $costo,
                        $estado,
                        $id
                    );
                    $consulta->execute();
                    $consulta->close();

                    $mensaje = "Servicio médico actualizado correctamente.";
                }
            }
        }

        // Acción para eliminar un servicio médico.
        if ($accion == "eliminar") {
            // Se valida que el identificador recibido sea un número entero válido.
            $idEliminar = filter_var(
                $_POST["id_servicio"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idEliminar === false || $idEliminar < 1) {
                $mensaje = "El servicio seleccionado no es válido.";
            } else {
                // Consulta preparada para eliminar el registro seleccionado.
                $consulta = $conexion->prepare(
                    "DELETE FROM servicio_medico WHERE id_servicio = ?"
                );
                $consulta->bind_param("i", $idEliminar);
                $consulta->execute();
                $consulta->close();

                $mensaje = "Servicio médico eliminado correctamente.";
            }
        }
    } catch (mysqli_sql_exception $error) {
        // El error técnico se registra y al usuario se le muestra un mensaje general.
        error_log($error->getMessage());
        $mensaje = "No fue posible completar la operación.";
    }
}

// Variable que almacenará los datos del servicio seleccionado para editar.
$servicioEditar = null;

// Se consulta el servicio cuando la URL contiene el parámetro editar.
if (isset($_GET["editar"])) {
    $idEditar = filter_var($_GET["editar"], FILTER_VALIDATE_INT);

    if ($idEditar !== false && $idEditar > 0) {
        // Consulta preparada para obtener únicamente el servicio seleccionado.
        $consultaEditar = $conexion->prepare(
            "SELECT * FROM servicio_medico WHERE id_servicio = ?"
        );
        $consultaEditar->bind_param("i", $idEditar);
        $consultaEditar->execute();
        $resultadoEditar = $consultaEditar->get_result();

        if ($resultadoEditar->num_rows > 0) {
            $servicioEditar = $resultadoEditar->fetch_assoc();
        }

        $consultaEditar->close();
    }
}

// Se consultan las especialidades para llenar el selector del formulario.
$sqlEspecialidades = "SELECT id_especialidad, nombre_especialidad
                      FROM especialidades
                      ORDER BY nombre_especialidad ASC";

$resultadoEspecialidades = $conexion->query($sqlEspecialidades);

// Se consultan los servicios junto con el nombre de su especialidad relacionada.
$sqlServicios = "SELECT servicio_medico.*, especialidades.nombre_especialidad
                 FROM servicio_medico
                 LEFT JOIN especialidades
                    ON servicio_medico.id_especialidad = especialidades.id_especialidad
                 ORDER BY servicio_medico.id_servicio ASC";

$resultadoServicios = $conexion->query($sqlServicios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios Médicos</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <!-- Encabezado principal del módulo de servicios médicos. -->
    <header class="encabezado">
        <div class="container text-center">
            <h1>Servicios Médicos</h1>
            <p>
                Módulo para registrar, consultar, modificar y eliminar los servicios médicos
                disponibles en la clínica local.
            </p>
        </div>
    </header>

    <main class="container my-5">
        <!-- Mensaje generado después de agregar, editar o eliminar un servicio. -->
        <?php if ($mensaje != "") { ?>
            <div class="alert alert-info text-center">
                <?php echo escaparServicio($mensaje); ?>
            </div>
        <?php } ?>

        <!-- Formulario utilizado tanto para registrar como para editar servicios. -->
        <section class="contenedor-formulario mb-5">
            <h2>
                <?php
                    echo $servicioEditar != null
                        ? "Editar servicio médico"
                        : "Registrar nuevo servicio médico";
                ?>
            </h2>

            <form method="POST" action="servicios.php">
                <!-- La acción del formulario cambia según se esté agregando o editando. -->
                <?php if ($servicioEditar != null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input
                        type="hidden"
                        name="id_servicio"
                        value="<?php echo (int) $servicioEditar["id_servicio"]; ?>"
                    >
                <?php } else { ?>
                    <input type="hidden" name="accion" value="agregar">
                <?php } ?>

                <div class="row">
                    <!-- Nombre que identificará el servicio médico. -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del servicio</label>
                        <input
                            type="text"
                            name="nombre_servicio"
                            class="form-control"
                            required
                            value="<?php
                                echo escaparServicio(
                                    $servicioEditar["nombre_servicio"] ?? ""
                                );
                            ?>"
                        >
                    </div>

                    <!-- Especialidad obtenida desde la tabla especialidades. -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Especialidad</label>
                        <select name="id_especialidad" class="form-select" required>
                            <option value="">Seleccione una especialidad</option>

                            <?php while ($especialidad = $resultadoEspecialidades->fetch_assoc()) { ?>
                                <option
                                    value="<?php echo (int) $especialidad["id_especialidad"]; ?>"
                                    <?php
                                        echo (
                                            $servicioEditar != null &&
                                            $servicioEditar["id_especialidad"] ==
                                            $especialidad["id_especialidad"]
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php
                                        echo escaparServicio(
                                            $especialidad["nombre_especialidad"]
                                        );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Duración aproximada de la consulta en minutos. -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Duración en minutos</label>
                        <input
                            type="number"
                            name="duracion"
                            class="form-control"
                            min="5"
                            step="5"
                            required
                            value="<?php
                                echo escaparServicio(
                                    $servicioEditar["duracion"] ?? "30"
                                );
                            ?>"
                        >
                    </div>

                    <!-- Costo aproximado del servicio. -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Costo aproximado</label>
                        <input
                            type="number"
                            name="costo"
                            class="form-control"
                            min="0"
                            step="0.01"
                            required
                            value="<?php
                                echo escaparServicio(
                                    $servicioEditar["costo"] ?? ""
                                );
                            ?>"
                        >
                    </div>

                    <!-- Estado que permite indicar si el servicio está disponible. -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select" required>
                            <option
                                value="Activo"
                                <?php
                                    echo (
                                        $servicioEditar == null ||
                                        $servicioEditar["estado"] == "Activo"
                                    ) ? "selected" : "";
                                ?>
                            >
                                Activo
                            </option>
                            <option
                                value="Inactivo"
                                <?php
                                    echo (
                                        $servicioEditar != null &&
                                        $servicioEditar["estado"] == "Inactivo"
                                    ) ? "selected" : "";
                                ?>
                            >
                                Inactivo
                            </option>
                        </select>
                    </div>

                    <!-- Descripción general del servicio médico. -->
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea
                            name="descripcion"
                            class="form-control"
                            rows="3"
                            required
                        ><?php
                            echo escaparServicio(
                                $servicioEditar["descripcion"] ?? ""
                            );
                        ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php
                        echo $servicioEditar != null
                            ? "Guardar cambios"
                            : "Agregar servicio";
                    ?>
                </button>

                <?php if ($servicioEditar != null) { ?>
                    <a href="servicios.php" class="btn btn-secondary">Cancelar edición</a>
                <?php } ?>
            </form>
        </section>

        <!-- Listado de servicios obtenidos desde la base de datos. -->
        <section>
            <h2 class="mb-4">Servicios disponibles</h2>

            <div class="row g-4">
                <!-- Se crea una tarjeta por cada servicio registrado. -->
                <?php while ($servicio = $resultadoServicios->fetch_assoc()) { ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card tarjeta-servicio h-100">
                            <div class="card-body">
                                <h3><?php echo escaparServicio($servicio["nombre_servicio"]); ?></h3>

                                <p><?php echo escaparServicio($servicio["descripcion"]); ?></p>

                                <p>
                                    <strong>Especialidad:</strong>
                                    <?php
                                        echo escaparServicio(
                                            $servicio["nombre_especialidad"] ?? "Sin asignar"
                                        );
                                    ?>
                                </p>

                                <p>
                                    <strong>Duración:</strong>
                                    <?php echo (int) $servicio["duracion"]; ?> minutos
                                </p>

                                <p class="precio">
                                    Costo aproximado: ₡<?php
                                        echo number_format((float) $servicio["costo"], 2);
                                    ?>
                                </p>

                                <p>
                                    Estado:
                                    <span class="badge bg-info">
                                        <?php echo escaparServicio($servicio["estado"]); ?>
                                    </span>
                                </p>

                                <button
                                    type="button"
                                    class="btn btn-servicio mb-2"
                                    data-servicio="<?php
                                        echo escaparServicio($servicio["nombre_servicio"]);
                                    ?>"
                                    onclick="solicitarCita(this.dataset.servicio)"
                                >
                                    Solicitar cita
                                </button>

                                <a
                                    href="servicios.php?editar=<?php
                                        echo (int) $servicio["id_servicio"];
                                    ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Editar
                                </a>

                                <!-- La eliminación se envía mediante POST y solicita confirmación. -->
                                <form
                                    method="POST"
                                    action="servicios.php"
                                    class="d-inline"
                                    onsubmit="return confirm('¿Desea eliminar este servicio?');"
                                >
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input
                                        type="hidden"
                                        name="id_servicio"
                                        value="<?php echo (int) $servicio["id_servicio"]; ?>"
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
        </section>
    </main>

    <!-- Información complementaria sobre el módulo. -->
    <section class="informacion-final">
        <div class="container text-center">
            <h2>Información importante</h2>
            <p>
                Este módulo permite administrar los servicios médicos que posteriormente
                estarán disponibles para el proceso de reserva de citas.
            </p>
        </div>
    </section>

    <!-- Pie de página del sistema. -->
    <footer class="pie-pagina text-center">
        <p>© 2026 Clínica Salud Local - Sistema de Gestión y Reserva de Citas</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/servicios.js"></script>
</body>
</html>