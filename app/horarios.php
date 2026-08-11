 <?php
// Se incluye el archivo que establece la conexión con la base de datos.
require_once __DIR__ . "/config/conexion.php";
// Variable utilizada para mostrar mensajes al usuario.
$mensaje = "";

// Días permitidos por el sistema.
$diasPermitidos = [
    "Lunes",
    "Martes",
    "Miércoles",
    "Jueves",
    "Viernes",
    "Sábado",
    "Domingo",
];

// Función para mostrar datos de forma segura dentro del HTML.
function escaparHorario($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Se procesan las acciones enviadas desde los formularios.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        // Acciones para agregar o editar un horario.
        if ($accion == "agregar" || $accion == "editar") {
            // Se reciben los datos enviados desde el formulario.
            $idDoctor = filter_var(
                $_POST["id_doctor"] ?? null,
                FILTER_VALIDATE_INT
            );
            $diaSemana = $_POST["dia_semana"] ?? "";
            $horaInicio = $_POST["hora_inicio"] ?? "";
            $horaFin = $_POST["hora_fin"] ?? "";
            $disponibleRecibido = $_POST["disponible"] ?? "";

            $horaInicioValida = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaInicio);
            $horaFinValida = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaFin);
            $disponibleValido = in_array($disponibleRecibido, ["0", "1"], true);
            $disponible = (int) $disponibleRecibido;

            // Para editar, se valida también el identificador del horario.
            $idHorario = 0;

            if ($accion == "editar") {
                $idHorario = filter_var(
                    $_POST["id_horario"] ?? null,
                    FILTER_VALIDATE_INT
                );
            }

            // Se comprueba que los datos sean válidos y que la hora final sea posterior.
            if (
                $idDoctor === false ||
                $idDoctor < 1 ||
                !in_array($diaSemana, $diasPermitidos, true) ||
                !$horaInicioValida ||
                !$horaFinValida ||
                $horaFin <= $horaInicio ||
                !$disponibleValido ||
                ($accion == "editar" && ($idHorario === false || $idHorario < 1))
            ) {
                $mensaje = "Revise los datos. La hora final debe ser posterior a la inicial.";
            } else {
                // Se verifica que el nuevo horario no se superponga con otro del doctor.
                if ($accion == "editar") {
                    $sqlConflicto = "SELECT id_horario
                                    FROM horarios_doctor
                                    WHERE id_doctor = ?
                                      AND dia_semana = ?
                                      AND hora_inicio < ?
                                      AND hora_fin > ?
                                      AND id_horario <> ?";

                    $consultaConflicto = $conexion->prepare($sqlConflicto);
                    $consultaConflicto->bind_param(
                        "isssi",
                        $idDoctor,
                        $diaSemana,
                        $horaFin,
                        $horaInicio,
                        $idHorario
                    );
                } else {
                    $sqlConflicto = "SELECT id_horario
                                    FROM horarios_doctor
                                    WHERE id_doctor = ?
                                      AND dia_semana = ?
                                      AND hora_inicio < ?
                                      AND hora_fin > ?";

                    $consultaConflicto = $conexion->prepare($sqlConflicto);
                    $consultaConflicto->bind_param(
                        "isss",
                        $idDoctor,
                        $diaSemana,
                        $horaFin,
                        $horaInicio
                    );
                }

                $consultaConflicto->execute();
                $resultadoConflicto = $consultaConflicto->get_result();
                $existeConflicto = $resultadoConflicto->num_rows > 0;
                $consultaConflicto->close();

                if ($existeConflicto) {
                    $mensaje = "El doctor ya tiene un horario que coincide con ese período.";
                } elseif ($accion == "agregar") {
                    // Consulta preparada para registrar un nuevo horario.
                    $sql = "INSERT INTO horarios_doctor
                                (id_doctor, dia_semana, hora_inicio, hora_fin, disponible)
                            VALUES (?, ?, ?, ?, ?)";

                    $consulta = $conexion->prepare($sql);
                    $consulta->bind_param(
                        "isssi",
                        $idDoctor,
                        $diaSemana,
                        $horaInicio,
                        $horaFin,
                        $disponible
                    );
                    $consulta->execute();
                    $consulta->close();

                    $mensaje = "Horario registrado correctamente.";
                } else {
                    // Consulta preparada para actualizar el horario seleccionado.
                    $sql = "UPDATE horarios_doctor
                            SET id_doctor = ?,
                                dia_semana = ?,
                                hora_inicio = ?,
                                hora_fin = ?,
                                disponible = ?
                            WHERE id_horario = ?";

                    $consulta = $conexion->prepare($sql);
                    $consulta->bind_param(
                        "isssii",
                        $idDoctor,
                        $diaSemana,
                        $horaInicio,
                        $horaFin,
                        $disponible,
                        $idHorario
                    );
                    $consulta->execute();
                    $consulta->close();

                    $mensaje = "Horario actualizado correctamente.";
                }
            }
        }

        // Acción para eliminar un horario.
        if ($accion == "eliminar") {
            $idHorario = filter_var(
                $_POST["id_horario"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idHorario === false || $idHorario < 1) {
                $mensaje = "El horario seleccionado no es válido.";
            } else {
                $consulta = $conexion->prepare(
                    "DELETE FROM horarios_doctor WHERE id_horario = ?"
                );
                $consulta->bind_param("i", $idHorario);
                $consulta->execute();
                $consulta->close();

                $mensaje = "Horario eliminado correctamente.";
            }
        }
    } catch (mysqli_sql_exception $error) {
        // El error técnico se registra sin mostrar detalles internos al usuario.
        error_log($error->getMessage());

        if ((int) $error->getCode() === 1062) {
            $mensaje = "Ese horario ya se encuentra registrado.";
        } elseif ((int) $error->getCode() === 1451) {
            $mensaje = "No se puede eliminar porque el horario tiene información relacionada.";
        } else {
            $mensaje = "No fue posible completar la operación.";
        }
    }
}

// Variable que almacenará el horario seleccionado para editar.
$horarioEditar = null;

// Se consulta el horario cuando la URL contiene el parámetro editar.
if (isset($_GET["editar"])) {
    $idEditar = filter_var($_GET["editar"], FILTER_VALIDATE_INT);

    if ($idEditar !== false && $idEditar > 0) {
        $consultaEditar = $conexion->prepare(
            "SELECT id_horario, id_doctor, dia_semana, hora_inicio, hora_fin, disponible
             FROM horarios_doctor
             WHERE id_horario = ?"
        );
        $consultaEditar->bind_param("i", $idEditar);
        $consultaEditar->execute();
        $resultadoEditar = $consultaEditar->get_result();

        if ($resultadoEditar->num_rows > 0) {
            $horarioEditar = $resultadoEditar->fetch_assoc();
        }

        $consultaEditar->close();
    }
}

// Se consultan los doctores para llenar el selector del formulario.
$sqlDoctores = "SELECT doctores.id_doctor,
                       doctores.nombre,
                       doctores.apellido,
                       especialidades.nombre_especialidad
                FROM doctores
                INNER JOIN especialidades
                    ON doctores.id_especialidad = especialidades.id_especialidad
                ORDER BY doctores.apellido ASC, doctores.nombre ASC";

$resultadoDoctores = $conexion->query($sqlDoctores);

// Se consultan los horarios con los datos del doctor y su especialidad.
$sqlHorarios = "SELECT horarios_doctor.*,
                       doctores.nombre,
                       doctores.apellido,
                       especialidades.nombre_especialidad
                FROM horarios_doctor
                INNER JOIN doctores
                    ON horarios_doctor.id_doctor = doctores.id_doctor
                INNER JOIN especialidades
                    ON doctores.id_especialidad = especialidades.id_especialidad
                ORDER BY FIELD(
                    horarios_doctor.dia_semana,
                    'Lunes', 'Martes', 'Miércoles', 'Jueves',
                    'Viernes', 'Sábado', 'Domingo'
                ), horarios_doctor.hora_inicio ASC";

$resultadoHorarios = $conexion->query($sqlHorarios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios de Doctores</title>

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

    <!-- Encabezado del módulo de horarios. -->
    <header class="encabezado">
        <div class="container text-center">
            <h1>Horarios de Doctores</h1>
            <p>
                Módulo para administrar los días y las horas de atención
                de los profesionales de la clínica.
            </p>
        </div>
    </header>

    <main class="container my-5">
        <!-- Mensajes generados por las operaciones del módulo. -->
        <?php if ($mensaje != "") { ?>
            <div class="alert alert-info text-center">
                <?php echo escaparHorario($mensaje); ?>
            </div>
        <?php } ?>

        <!-- Formulario utilizado para registrar y editar horarios. -->
        <section class="contenedor-formulario mb-5">
            <h2>
                <?php
                    echo $horarioEditar != null
                        ? "Editar horario"
                        : "Registrar nuevo horario";
                ?>
            </h2>

            <form method="POST" action="horarios.php">
                <?php if ($horarioEditar != null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input
                        type="hidden"
                        name="id_horario"
                        value="<?php echo (int) $horarioEditar["id_horario"]; ?>"
                    >
                <?php } else { ?>
                    <input type="hidden" name="accion" value="agregar">
                <?php } ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Doctor</label>
                        <select name="id_doctor" class="form-select" required>
                            <option value="">Seleccione un doctor</option>

                            <?php while ($doctor = $resultadoDoctores->fetch_assoc()) { ?>
                                <option
                                    value="<?php echo (int) $doctor["id_doctor"]; ?>"
                                    <?php
                                        echo (
                                            $horarioEditar != null &&
                                            $horarioEditar["id_doctor"] == $doctor["id_doctor"]
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php
                                        echo escaparHorario(
                                            $doctor["nombre"] . " " .
                                            $doctor["apellido"] . " - " .
                                            $doctor["nombre_especialidad"]
                                        );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Día de la semana</label>
                        <select name="dia_semana" class="form-select" required>
                            <option value="">Seleccione un día</option>

                            <?php foreach ($diasPermitidos as $dia) { ?>
                                <option
                                    value="<?php echo escaparHorario($dia); ?>"
                                    <?php
                                        echo (
                                            $horarioEditar != null &&
                                            $horarioEditar["dia_semana"] == $dia
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php echo escaparHorario($dia); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora de inicio</label>
                        <input
                            type="time"
                            name="hora_inicio"
                            class="form-control"
                            required
                            value="<?php
                                echo escaparHorario(
                                    isset($horarioEditar["hora_inicio"])
                                        ? substr($horarioEditar["hora_inicio"], 0, 5)
                                        : "08:00"
                                );
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora de finalización</label>
                        <input
                            type="time"
                            name="hora_fin"
                            class="form-control"
                            required
                            value="<?php
                                echo escaparHorario(
                                    isset($horarioEditar["hora_fin"])
                                        ? substr($horarioEditar["hora_fin"], 0, 5)
                                        : "17:00"
                                );
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Disponibilidad</label>
                        <select name="disponible" class="form-select" required>
                            <option
                                value="1"
                                <?php
                                    echo (
                                        $horarioEditar == null ||
                                        (int) $horarioEditar["disponible"] === 1
                                    ) ? "selected" : "";
                                ?>
                            >
                                Disponible
                            </option>
                            <option
                                value="0"
                                <?php
                                    echo (
                                        $horarioEditar != null &&
                                        (int) $horarioEditar["disponible"] === 0
                                    ) ? "selected" : "";
                                ?>
                            >
                                No disponible
                            </option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php
                        echo $horarioEditar != null
                            ? "Guardar cambios"
                            : "Agregar horario";
                    ?>
                </button>

                <?php if ($horarioEditar != null) { ?>
                    <a href="horarios.php" class="btn btn-secondary">
                        Cancelar edición
                    </a>
                <?php } ?>
            </form>
        </section>

        <!-- Listado de horarios obtenidos desde la base de datos. -->
        <section>
            <h2 class="mb-4">Horarios registrados</h2>

            <?php if ($resultadoHorarios->num_rows == 0) { ?>
                <div class="alert alert-secondary">
                    No hay horarios registrados.
                </div>
            <?php } else { ?>
                <div class="row g-4">
                    <?php while ($horario = $resultadoHorarios->fetch_assoc()) { ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card tarjeta-servicio h-100">
                                <div class="card-body">
                                    <h3>
                                        <?php
                                            echo escaparHorario(
                                                $horario["nombre"] . " " . $horario["apellido"]
                                            );
                                        ?>
                                    </h3>

                                    <p>
                                        <strong>Especialidad:</strong>
                                        <?php
                                            echo escaparHorario(
                                                $horario["nombre_especialidad"]
                                            );
                                        ?>
                                    </p>

                                    <p>
                                        <strong>Día:</strong>
                                        <?php echo escaparHorario($horario["dia_semana"]); ?>
                                    </p>

                                    <p>
                                        <strong>Horario:</strong>
                                        <?php
                                            echo escaparHorario(
                                                substr($horario["hora_inicio"], 0, 5) .
                                                " - " .
                                                substr($horario["hora_fin"], 0, 5)
                                            );
                                        ?>
                                    </p>

                                    <p>
                                        <strong>Estado:</strong>
                                        <span class="badge <?php
                                            echo (int) $horario["disponible"] === 1
                                                ? "bg-success"
                                                : "bg-secondary";
                                        ?>">
                                            <?php
                                                echo (int) $horario["disponible"] === 1
                                                    ? "Disponible"
                                                    : "No disponible";
                                            ?>
                                        </span>
                                    </p>

                                    <a
                                        href="horarios.php?editar=<?php
                                            echo (int) $horario["id_horario"];
                                        ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        action="horarios.php"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea eliminar este horario?');"
                                    >
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input
                                            type="hidden"
                                            name="id_horario"
                                            value="<?php echo (int) $horario["id_horario"]; ?>"
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