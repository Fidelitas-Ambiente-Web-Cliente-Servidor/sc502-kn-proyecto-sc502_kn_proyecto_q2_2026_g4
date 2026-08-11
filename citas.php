<?php
// Se incluye el archivo que establece la conexión con la base de datos.
require_once "config/autenticacion.php";
require_once "config/conexion.php";

// Mensaje recibido después de una operación exitosa o generado por un error.
$mensaje = trim($_GET["mensaje"] ?? "");

$estadosPermitidos = ["Programada", "Confirmada", "Atendida", "Cancelada"];

$diasSemana = [
    1 => "Lunes",
    2 => "Martes",
    3 => "Miércoles",
    4 => "Jueves",
    5 => "Viernes",
    6 => "Sábado",
    7 => "Domingo",
];

// Función para mostrar datos de forma segura dentro del HTML.
function escaparCita($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

// Redirige después de una operación exitosa para evitar registros duplicados al recargar.
function redirigirCitas($mensaje)
{
    header("Location: citas.php?" . http_build_query(["mensaje" => $mensaje]));
    exit;
}

function claseEstadoCita($estado)
{
    $clases = [
        "Programada" => "bg-primary",
        "Confirmada" => "bg-info text-dark",
        "Atendida" => "bg-success",
        "Cancelada" => "bg-secondary",
    ];

    return $clases[$estado] ?? "bg-secondary";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        // Acciones para agregar o editar una cita.
        if ($accion == "agregar" || $accion == "editar") {
            // Se reciben los datos del formulario.
            $idPaciente = filter_var(
                $_POST["id_paciente"] ?? null,
                FILTER_VALIDATE_INT
            );
            $idDoctor = filter_var(
                $_POST["id_doctor"] ?? null,
                FILTER_VALIDATE_INT
            );
            $idServicio = filter_var(
                $_POST["id_servicio"] ?? null,
                FILTER_VALIDATE_INT
            );
            $fecha = $_POST["fecha"] ?? "";
            $hora = $_POST["hora"] ?? "";
            $estado = $_POST["estado"] ?? "";
            $observaciones = trim($_POST["observaciones"] ?? "");

            $idCita = 0;

            if ($accion == "editar") {
                $idCita = filter_var(
                    $_POST["id_cita"] ?? null,
                    FILTER_VALIDATE_INT
                );
            }

            $fechaObjeto = DateTime::createFromFormat("Y-m-d", $fecha);
            $fechaValida = $fechaObjeto !== false &&
                $fechaObjeto->format("Y-m-d") === $fecha;
            $horaValida = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora);

            // Se validan los campos principales.
            if (
                $idPaciente === false ||
                $idPaciente < 1 ||
                $idDoctor === false ||
                $idDoctor < 1 ||
                $idServicio === false ||
                $idServicio < 1 ||
                !$fechaValida ||
                !$horaValida ||
                !in_array($estado, $estadosPermitidos, true) ||
                strlen($observaciones) > 1000 ||
                ($accion == "editar" && ($idCita === false || $idCita < 1))
            ) {
                $mensaje = "Revise los datos ingresados en el formulario.";
            } elseif (
                $fecha < date("Y-m-d") &&
                in_array($estado, ["Programada", "Confirmada"], true)
            ) {
                $mensaje = "No se puede programar una cita en una fecha pasada.";
            } else {
                // Se consulta la especialidad y duración del servicio seleccionado.
                $consultaServicio = $conexion->prepare(
                    "SELECT id_especialidad, duracion
                     FROM servicio_medico
                     WHERE id_servicio = ? AND estado = 'Activo'"
                );
                $consultaServicio->bind_param("i", $idServicio);
                $consultaServicio->execute();
                $servicio = $consultaServicio->get_result()->fetch_assoc();
                $consultaServicio->close();

                // Se consulta la especialidad del doctor seleccionado.
                $consultaDoctor = $conexion->prepare(
                    "SELECT id_especialidad
                     FROM doctores
                     WHERE id_doctor = ?"
                );
                $consultaDoctor->bind_param("i", $idDoctor);
                $consultaDoctor->execute();
                $doctor = $consultaDoctor->get_result()->fetch_assoc();
                $consultaDoctor->close();

                if ($servicio === null || $doctor === null) {
                    $mensaje = "El doctor o el servicio seleccionado no existe.";
                } elseif (
                    (int) $servicio["id_especialidad"] !==
                    (int) $doctor["id_especialidad"]
                ) {
                    $mensaje = "El doctor no atiende la especialidad del servicio seleccionado.";
                } else {
                    // Se calcula la hora final utilizando la duración del servicio.
                    $duracion = (int) $servicio["duracion"];
                    $inicioCita = DateTime::createFromFormat(
                        "Y-m-d H:i",
                        $fecha . " " . $hora
                    );
                    $finCita = clone $inicioCita;
                    $finCita->modify("+" . $duracion . " minutes");

                    $horaInicioSql = $inicioCita->format("H:i:s");
                    $horaFinSql = $finCita->format("H:i:s");
                    $diaSemana = $diasSemana[(int) $inicioCita->format("N")];

                    if ($finCita->format("Y-m-d") !== $fecha) {
                        $mensaje = "La duración del servicio supera el final del día.";
                    } elseif ($estado != "Cancelada") {
                        // Se verifica que la cita esté dentro de un horario disponible.
                        $consultaHorario = $conexion->prepare(
                            "SELECT id_horario
                             FROM horarios_doctor
                             WHERE id_doctor = ?
                               AND dia_semana = ?
                               AND disponible = 1
                               AND hora_inicio <= ?
                               AND hora_fin >= ?
                             LIMIT 1"
                        );
                        $consultaHorario->bind_param(
                            "isss",
                            $idDoctor,
                            $diaSemana,
                            $horaInicioSql,
                            $horaFinSql
                        );
                        $consultaHorario->execute();
                        $horarioDisponible =
                            $consultaHorario->get_result()->num_rows > 0;
                        $consultaHorario->close();

                        if (!$horarioDisponible) {
                            $mensaje = "El doctor no tiene un horario disponible para ese día y hora.";
                        } else {
                            // Se comprueba que el doctor no tenga otra cita superpuesta.
                            $sqlConflictoDoctor = "SELECT citas.id_cita
                                                  FROM citas
                                                  INNER JOIN servicio_medico
                                                     ON citas.id_servicio = servicio_medico.id_servicio
                                                  WHERE citas.id_doctor = ?
                                                    AND citas.fecha = ?
                                                    AND citas.estado <> 'Cancelada'
                                                    AND citas.id_cita <> ?
                                                    AND citas.hora < ?
                                                    AND ADDTIME(
                                                        citas.hora,
                                                        SEC_TO_TIME(servicio_medico.duracion * 60)
                                                    ) > ?
                                                  LIMIT 1";

                            $consultaConflicto = $conexion->prepare($sqlConflictoDoctor);
                            $consultaConflicto->bind_param(
                                "isiss",
                                $idDoctor,
                                $fecha,
                                $idCita,
                                $horaFinSql,
                                $horaInicioSql
                            );
                            $consultaConflicto->execute();
                            $doctorOcupado =
                                $consultaConflicto->get_result()->num_rows > 0;
                            $consultaConflicto->close();

                            // Se comprueba que el paciente tampoco tenga otra cita a esa hora.
                            $sqlConflictoPaciente = "SELECT citas.id_cita
                                                    FROM citas
                                                    INNER JOIN servicio_medico
                                                       ON citas.id_servicio = servicio_medico.id_servicio
                                                    WHERE citas.id_paciente = ?
                                                      AND citas.fecha = ?
                                                      AND citas.estado <> 'Cancelada'
                                                      AND citas.id_cita <> ?
                                                      AND citas.hora < ?
                                                      AND ADDTIME(
                                                          citas.hora,
                                                          SEC_TO_TIME(servicio_medico.duracion * 60)
                                                      ) > ?
                                                    LIMIT 1";

                            $consultaConflicto = $conexion->prepare($sqlConflictoPaciente);
                            $consultaConflicto->bind_param(
                                "isiss",
                                $idPaciente,
                                $fecha,
                                $idCita,
                                $horaFinSql,
                                $horaInicioSql
                            );
                            $consultaConflicto->execute();
                            $pacienteOcupado =
                                $consultaConflicto->get_result()->num_rows > 0;
                            $consultaConflicto->close();

                            if ($doctorOcupado) {
                                $mensaje = "El doctor ya tiene otra cita durante ese período.";
                            } elseif ($pacienteOcupado) {
                                $mensaje = "El paciente ya tiene otra cita durante ese período.";
                            }
                        }
                    }

                    // Si no se generó ningún error, se registra o actualiza la cita.
                    if ($mensaje == "") {
                        if ($accion == "agregar") {
                            $sql = "INSERT INTO citas
                                        (id_paciente, id_doctor, id_servicio, fecha, hora, estado, observaciones)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";

                            $consulta = $conexion->prepare($sql);
                            $consulta->bind_param(
                                "iiissss",
                                $idPaciente,
                                $idDoctor,
                                $idServicio,
                                $fecha,
                                $horaInicioSql,
                                $estado,
                                $observaciones
                            );
                            $consulta->execute();
                            $consulta->close();

                            redirigirCitas("Cita registrada correctamente.");
                        } else {
                            $sql = "UPDATE citas
                                    SET id_paciente = ?,
                                        id_doctor = ?,
                                        id_servicio = ?,
                                        fecha = ?,
                                        hora = ?,
                                        estado = ?,
                                        observaciones = ?
                                    WHERE id_cita = ?";

                            $consulta = $conexion->prepare($sql);
                            $consulta->bind_param(
                                "iiissssi",
                                $idPaciente,
                                $idDoctor,
                                $idServicio,
                                $fecha,
                                $horaInicioSql,
                                $estado,
                                $observaciones,
                                $idCita
                            );
                            $consulta->execute();
                            $consulta->close();

                            redirigirCitas("Cita actualizada correctamente.");
                        }
                    }
                }
            }
        } elseif ($accion == "cancelar") {
            // Acción rápida para cancelar una cita sin eliminar su historial.
            $idCita = filter_var(
                $_POST["id_cita"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idCita === false || $idCita < 1) {
                $mensaje = "La cita seleccionada no es válida.";
            } else {
                $consulta = $conexion->prepare(
                    "UPDATE citas SET estado = 'Cancelada' WHERE id_cita = ?"
                );
                $consulta->bind_param("i", $idCita);
                $consulta->execute();
                $consulta->close();

                redirigirCitas("Cita cancelada correctamente.");
            }
        } elseif ($accion == "eliminar") {
            // Acción para eliminar definitivamente una cita.
            $idCita = filter_var(
                $_POST["id_cita"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idCita === false || $idCita < 1) {
                $mensaje = "La cita seleccionada no es válida.";
            } else {
                $consulta = $conexion->prepare(
                    "DELETE FROM citas WHERE id_cita = ?"
                );
                $consulta->bind_param("i", $idCita);
                $consulta->execute();
                $consulta->close();

                redirigirCitas("Cita eliminada correctamente.");
            }
        }
    } catch (mysqli_sql_exception $error) {
        // El error técnico se registra sin mostrar detalles internos al usuario.
        error_log($error->getMessage());
        $mensaje = "No fue posible completar la operación.";
    }
}

// Variable que almacenará la cita seleccionada para editar.
$citaEditar = null;

if (isset($_GET["editar"])) {
    $idEditar = filter_var($_GET["editar"], FILTER_VALIDATE_INT);

    if ($idEditar !== false && $idEditar > 0) {
        $consultaEditar = $conexion->prepare(
            "SELECT id_cita, id_paciente, id_doctor, id_servicio,
                    fecha, hora, estado, observaciones
             FROM citas
             WHERE id_cita = ?"
        );
        $consultaEditar->bind_param("i", $idEditar);
        $consultaEditar->execute();
        $citaEditar = $consultaEditar->get_result()->fetch_assoc();
        $consultaEditar->close();
    }
}

// Datos utilizados para llenar los selectores del formulario.
$resultadoPacientes = $conexion->query(
    "SELECT id_paciente, nombre, apellido, cedula
     FROM pacientes
     ORDER BY apellido ASC, nombre ASC"
);

$resultadoDoctores = $conexion->query(
    "SELECT doctores.id_doctor,
            doctores.id_especialidad,
            doctores.nombre,
            doctores.apellido,
            especialidades.nombre_especialidad
     FROM doctores
     INNER JOIN especialidades
        ON doctores.id_especialidad = especialidades.id_especialidad
     ORDER BY doctores.apellido ASC, doctores.nombre ASC"
);

$resultadoServicios = $conexion->query(
    "SELECT servicio_medico.id_servicio,
            servicio_medico.id_especialidad,
            servicio_medico.nombre_servicio,
            servicio_medico.duracion,
            especialidades.nombre_especialidad
     FROM servicio_medico
     INNER JOIN especialidades
        ON servicio_medico.id_especialidad = especialidades.id_especialidad
     WHERE servicio_medico.estado = 'Activo'
     ORDER BY servicio_medico.nombre_servicio ASC"
);

// Se consultan las citas con toda la información relacionada.
$resultadoCitas = $conexion->query(
    "SELECT citas.*,
            pacientes.nombre AS paciente_nombre,
            pacientes.apellido AS paciente_apellido,
            doctores.nombre AS doctor_nombre,
            doctores.apellido AS doctor_apellido,
            servicio_medico.nombre_servicio,
            servicio_medico.duracion,
            especialidades.nombre_especialidad
     FROM citas
     INNER JOIN pacientes ON citas.id_paciente = pacientes.id_paciente
     INNER JOIN doctores ON citas.id_doctor = doctores.id_doctor
     INNER JOIN servicio_medico ON citas.id_servicio = servicio_medico.id_servicio
     INNER JOIN especialidades
        ON servicio_medico.id_especialidad = especialidades.id_especialidad
     ORDER BY citas.fecha ASC, citas.hora ASC"
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citas Médicas</title>

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
         <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
            <form action="/logout.php" method="POST">
                <button type="submit" class="btn btn-outline-light">
                    Cerrar sesión
                </button>
            </form>
        </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Encabezado del módulo de citas. -->
    <header class="encabezado">
        <div class="container text-center">
            <h1>Citas Médicas</h1>
            <p>
                Módulo para programar y administrar las reservas médicas de la clínica.
            </p>
        </div>
    </header>

    <main class="container my-5">
        <?php if ($mensaje != "") { ?>
            <div class="alert alert-info text-center">
                <?php echo escaparCita($mensaje); ?>
            </div>
        <?php } ?>

        <!-- Formulario para registrar y editar citas. -->
        <section class="contenedor-formulario mb-5">
            <h2>
                <?php echo $citaEditar != null ? "Editar cita" : "Registrar nueva cita"; ?>
            </h2>

            <form method="POST" action="citas.php">
                <?php if ($citaEditar != null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input
                        type="hidden"
                        name="id_cita"
                        value="<?php echo (int) $citaEditar["id_cita"]; ?>"
                    >
                <?php } else { ?>
                    <input type="hidden" name="accion" value="agregar">
                <?php } ?>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Paciente</label>
                        <select
                            id="id_paciente"
                            name="id_paciente"
                            class="form-select"
                            required
                        >
                            <option value="">Seleccione un paciente</option>
                            <?php while ($paciente = $resultadoPacientes->fetch_assoc()) { ?>
                                <option
                                    value="<?php echo (int) $paciente["id_paciente"]; ?>"
                                    <?php
                                        echo (
                                            $citaEditar != null &&
                                            $citaEditar["id_paciente"] == $paciente["id_paciente"]
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php
                                        echo escaparCita(
                                            $paciente["nombre"] . " " .
                                            $paciente["apellido"] . " - " .
                                            $paciente["cedula"]
                                        );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Doctor</label>
                        <select
                            id="id_doctor"
                            name="id_doctor"
                            class="form-select"
                            required
                        >
                            <option value="">Seleccione un doctor</option>
                            <?php while ($doctor = $resultadoDoctores->fetch_assoc()) { ?>
                                <option
                                    value="<?php echo (int) $doctor["id_doctor"]; ?>"
                                    data-especialidad="<?php
                                        echo (int) $doctor["id_especialidad"];
                                    ?>"
                                    <?php
                                        echo (
                                            $citaEditar != null &&
                                            $citaEditar["id_doctor"] == $doctor["id_doctor"]
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php
                                        echo escaparCita(
                                            $doctor["nombre"] . " " .
                                            $doctor["apellido"] . " - " .
                                            $doctor["nombre_especialidad"]
                                        );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Servicio</label>
                        <select
                            id="id_servicio"
                            name="id_servicio"
                            class="form-select"
                            required
                        >
                            <option value="">Seleccione un servicio</option>
                            <?php while ($servicio = $resultadoServicios->fetch_assoc()) { ?>
                                <option
                                    value="<?php echo (int) $servicio["id_servicio"]; ?>"
                                    data-especialidad="<?php
                                        echo (int) $servicio["id_especialidad"];
                                    ?>"
                                    data-duracion="<?php
                                        echo (int) $servicio["duracion"];
                                    ?>"
                                    <?php
                                        echo (
                                            $citaEditar != null &&
                                            $citaEditar["id_servicio"] == $servicio["id_servicio"]
                                        ) ? "selected" : "";
                                    ?>
                                >
                                    <?php
                                        echo escaparCita(
                                            $servicio["nombre_servicio"] . " - " .
                                            $servicio["duracion"] . " min"
                                        );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fecha</label>
                        <input
                            id="fecha"
                            type="date"
                            name="fecha"
                            class="form-control"
                            required
                            value="<?php echo escaparCita($citaEditar["fecha"] ?? ""); ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora de inicio</label>
                        <input
                            id="hora"
                            type="time"
                            name="hora"
                            class="form-control"
                            required
                            value="<?php
                                echo escaparCita(
                                    isset($citaEditar["hora"])
                                        ? substr($citaEditar["hora"], 0, 5)
                                        : ""
                                );
                            ?>"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select" required>
                            <?php foreach ($estadosPermitidos as $estadoDisponible) { ?>
                                <option
                                    value="<?php echo escaparCita($estadoDisponible); ?>"
                                    <?php
                                        $estadoActual = $citaEditar["estado"] ?? "Programada";
                                        echo $estadoActual == $estadoDisponible ? "selected" : "";
                                    ?>
                                >
                                    <?php echo escaparCita($estadoDisponible); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            maxlength="1000"
                        ><?php
                            echo escaparCita($citaEditar["observaciones"] ?? "");
                        ?></textarea>
                    </div>
                </div>

                <!-- Resumen generado con JavaScript antes de guardar la cita. -->
                <div id="resumenCita" class="alert alert-secondary d-none">
                    <h4>Resumen de la cita</h4>

                    <p>
                        <strong>Paciente:</strong>
                        <span id="resumenPaciente"></span>
                    </p>

                    <p>
                        <strong>Servicio:</strong>
                        <span id="resumenServicio"></span>
                    </p>

                    <p>
                        <strong>Doctor:</strong>
                        <span id="resumenDoctor"></span>
                    </p>

                    <p>
                        <strong>Fecha:</strong>
                        <span id="resumenFecha"></span>
                    </p>

                    <p>
                        <strong>Horario:</strong>
                        <span id="resumenHorario"></span>
                    </p>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php echo $citaEditar != null ? "Guardar cambios" : "Agregar cita"; ?>
                </button>

                <?php if ($citaEditar != null) { ?>
                    <a href="citas.php" class="btn btn-secondary">Cancelar edición</a>
                <?php } ?>
            </form>
        </section>

        <!-- Listado de citas registradas. -->
        <section>
            <h2 class="mb-4">Citas registradas</h2>

            <?php if ($resultadoCitas->num_rows == 0) { ?>
                <div class="alert alert-secondary">No hay citas registradas.</div>
            <?php } else { ?>
                <div class="row g-4">
                    <?php while ($cita = $resultadoCitas->fetch_assoc()) { ?>
                        <?php
                            $horaFinal = date(
                                "H:i",
                                strtotime($cita["hora"]) + ((int) $cita["duracion"] * 60)
                            );
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card tarjeta-servicio h-100">
                                <div class="card-body">
                                    <h3><?php echo escaparCita($cita["nombre_servicio"]); ?></h3>

                                    <p>
                                        <strong>Paciente:</strong>
                                        <?php
                                            echo escaparCita(
                                                $cita["paciente_nombre"] . " " .
                                                $cita["paciente_apellido"]
                                            );
                                        ?>
                                    </p>

                                    <p>
                                        <strong>Doctor:</strong>
                                        <?php
                                            echo escaparCita(
                                                $cita["doctor_nombre"] . " " .
                                                $cita["doctor_apellido"]
                                            );
                                        ?>
                                    </p>

                                    <p>
                                        <strong>Especialidad:</strong>
                                        <?php echo escaparCita($cita["nombre_especialidad"]); ?>
                                    </p>

                                    <p>
                                        <strong>Fecha:</strong>
                                        <?php echo escaparCita(date("d/m/Y", strtotime($cita["fecha"]))); ?>
                                    </p>

                                    <p>
                                        <strong>Horario:</strong>
                                        <?php
                                            echo escaparCita(
                                                substr($cita["hora"], 0, 5) . " - " . $horaFinal
                                            );
                                        ?>
                                    </p>

                                    <p>
                                        <strong>Estado:</strong>
                                        <span class="badge <?php echo claseEstadoCita($cita["estado"]); ?>">
                                            <?php echo escaparCita($cita["estado"]); ?>
                                        </span>
                                    </p>

                                    <?php if (trim($cita["observaciones"] ?? "") != "") { ?>
                                        <p>
                                            <strong>Observaciones:</strong>
                                            <?php echo escaparCita($cita["observaciones"]); ?>
                                        </p>
                                    <?php } ?>

                                    <a
                                        href="citas.php?editar=<?php echo (int) $cita["id_cita"]; ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        Editar
                                    </a>

                                    <?php if ($cita["estado"] != "Cancelada") { ?>
                                        <form method="POST" action="citas.php" class="d-inline">
                                            <input type="hidden" name="accion" value="cancelar">
                                            <input
                                                type="hidden"
                                                name="id_cita"
                                                value="<?php echo (int) $cita["id_cita"]; ?>"
                                            >
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                Cancelar cita
                                            </button>
                                        </form>
                                    <?php } ?>

                                    <form
                                        method="POST"
                                        action="citas.php"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea eliminar definitivamente esta cita?');"
                                    >
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input
                                            type="hidden"
                                            name="id_cita"
                                            value="<?php echo (int) $cita["id_cita"]; ?>"
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            const $paciente = $("#id_paciente");
            const $servicio = $("#id_servicio");
            const $doctor = $("#id_doctor");
            const $fecha = $("#fecha");
            const $hora = $("#hora");

            // Calcula la hora final utilizando la duración del servicio.
            function calcularHoraFinal(horaInicio, duracion) {
                if (!horaInicio || !duracion) {
                    return "";
                }

                const partes = horaInicio.split(":");
                const horas = Number(partes[0]);
                const minutos = Number(partes[1]);
                const totalMinutos = horas * 60 + minutos + Number(duracion);

                const horaFinal = Math.floor(totalMinutos / 60) % 24;
                const minutoFinal = totalMinutos % 60;

                return String(horaFinal).padStart(2, "0") +
                    ":" +
                    String(minutoFinal).padStart(2, "0");
            }

            // Oculta los doctores que no pertenecen a la especialidad del servicio.
            function filtrarDoctores(reiniciarSeleccion) {
                const especialidadServicio = String(
                    $servicio.find("option:selected").data("especialidad") || ""
                );

                const doctorSeleccionado = String($doctor.val() || "");

                $doctor.find("option").each(function () {
                    const $opcion = $(this);

                    if ($opcion.val() === "") {
                        $opcion.prop("hidden", false);
                        $opcion.prop("disabled", false);
                        return;
                    }

                    const especialidadDoctor = String(
                        $opcion.data("especialidad") || ""
                    );

                    const coincide = especialidadServicio !== "" &&
                        especialidadServicio === especialidadDoctor;

                    $opcion.prop("hidden", !coincide);
                    $opcion.prop("disabled", !coincide);
                });

                if (reiniciarSeleccion) {
                    $doctor.val("");
                } else {
                    $doctor.val(doctorSeleccionado);
                }

                actualizarResumen();
            }

            // Construye el resumen utilizando los valores actuales del formulario.
            function actualizarResumen() {
                const opcionPaciente = $paciente.find("option:selected");
                const opcionServicio = $servicio.find("option:selected");
                const opcionDoctor = $doctor.find("option:selected");

                const pacienteSeleccionado = opcionPaciente.val() !== "";
                const servicioSeleccionado = opcionServicio.val() !== "";
                const doctorSeleccionado = opcionDoctor.val() !== "";
                const fecha = $fecha.val();
                const horaInicio = $hora.val();
                const duracion = Number(opcionServicio.data("duracion") || 0);
                const horaFinal = calcularHoraFinal(horaInicio, duracion);

                if (
                    !pacienteSeleccionado ||
                    !servicioSeleccionado ||
                    !doctorSeleccionado ||
                    fecha === "" ||
                    horaInicio === ""
                ) {
                    $("#resumenCita").addClass("d-none");
                    return;
                }

                $("#resumenPaciente").text(opcionPaciente.text().trim());
                $("#resumenServicio").text(opcionServicio.text().trim());
                $("#resumenDoctor").text(opcionDoctor.text().trim());
                $("#resumenFecha").text(fecha);
                $("#resumenHorario").text(
                    horaInicio + " - " + horaFinal + " (" + duracion + " minutos)"
                );

                $("#resumenCita").removeClass("d-none");
            }

            // Al cambiar el servicio se actualizan los doctores compatibles.
            $servicio.on("change", function () {
                filtrarDoctores(true);
            });

            // Los demás cambios actualizan el resumen en tiempo real.
            $paciente.on("change", actualizarResumen);
            $doctor.on("change", actualizarResumen);
            $fecha.on("change", actualizarResumen);
            $hora.on("change", actualizarResumen);

            // Conserva las selecciones existentes cuando se está editando una cita.
            filtrarDoctores(false);
            actualizarResumen();
        });
    </script>
</body>
</html>