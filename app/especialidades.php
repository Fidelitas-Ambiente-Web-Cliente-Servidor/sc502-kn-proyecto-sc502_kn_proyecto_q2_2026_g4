<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once "config/conexion.php";

function escaparEspecialidad($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function redirigirEspecialidades($tipo, $mensaje)
{
    $parametros = http_build_query([
        "tipo" => $tipo,
        "mensaje" => $mensaje,
    ]);

    header("Location: especialidades.php?" . $parametros);
    exit;
}

$mensaje = trim($_GET["mensaje"] ?? "");
$tipoMensaje = $_GET["tipo"] ?? "info";

if (!in_array($tipoMensaje, ["success", "danger", "warning", "info"], true)) {
    $tipoMensaje = "info";
}

$especialidadEditar = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        if ($accion === "agregar" || $accion === "editar") {
            $nombre = trim($_POST["nombre_especialidad"] ?? "");
            $descripcion = trim($_POST["descripcion"] ?? "");

            if ($nombre === "" || $descripcion === "") {
                throw new InvalidArgumentException(
                    "El nombre y la descripción son obligatorios."
                );
            }

            if (strlen($nombre) > 100) {
                throw new InvalidArgumentException(
                    "El nombre no puede superar los 100 caracteres."
                );
            }

            if ($accion === "agregar") {
                $consulta = $conexion->prepare(
                    "INSERT INTO especialidades (nombre_especialidad, descripcion)
                     VALUES (?, ?)"
                );
                $consulta->bind_param("ss", $nombre, $descripcion);
                $consulta->execute();
                $consulta->close();

                redirigirEspecialidades(
                    "success",
                    "Especialidad agregada correctamente."
                );
            }

            $idEspecialidad = filter_var(
                $_POST["id_especialidad"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idEspecialidad === false || $idEspecialidad < 1) {
                throw new InvalidArgumentException(
                    "El identificador de la especialidad no es válido."
                );
            }

            $consulta = $conexion->prepare(
                "UPDATE especialidades
                 SET nombre_especialidad = ?, descripcion = ?
                 WHERE id_especialidad = ?"
            );
            $consulta->bind_param("ssi", $nombre, $descripcion, $idEspecialidad);
            $consulta->execute();
            $consulta->close();

            redirigirEspecialidades(
                "success",
                "Especialidad actualizada correctamente."
            );
        }

        if ($accion === "eliminar") {
            $idEspecialidad = filter_var(
                $_POST["id_especialidad"] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($idEspecialidad === false || $idEspecialidad < 1) {
                throw new InvalidArgumentException(
                    "El identificador de la especialidad no es válido."
                );
            }

            $consulta = $conexion->prepare(
                "DELETE FROM especialidades WHERE id_especialidad = ?"
            );
            $consulta->bind_param("i", $idEspecialidad);
            $consulta->execute();
            $filasEliminadas = $consulta->affected_rows;
            $consulta->close();

            if ($filasEliminadas === 0) {
                redirigirEspecialidades(
                    "warning",
                    "La especialidad indicada ya no existe."
                );
            }

            redirigirEspecialidades(
                "success",
                "Especialidad eliminada correctamente."
            );
        }

        throw new InvalidArgumentException("La acción solicitada no es válida.");
    } catch (InvalidArgumentException $error) {
        $mensaje = $error->getMessage();
        $tipoMensaje = "danger";
    } catch (mysqli_sql_exception $error) {
        error_log($error->getMessage());

        if ((int) $error->getCode() === 1062) {
            $mensaje = "Ya existe una especialidad con ese nombre.";
        } elseif ((int) $error->getCode() === 1451) {
            $mensaje = "No se puede eliminar porque la especialidad está en uso.";
        } else {
            $mensaje = "No fue posible completar la operación.";
        }

        $tipoMensaje = "danger";
    }
}

if (isset($_GET["editar"])) {
    $idEditar = filter_var($_GET["editar"], FILTER_VALIDATE_INT);

    if ($idEditar === false || $idEditar < 1) {
        $mensaje = "La especialidad seleccionada no es válida.";
        $tipoMensaje = "danger";
    } else {
        try {
            $consulta = $conexion->prepare(
                "SELECT id_especialidad, nombre_especialidad, descripcion
                 FROM especialidades
                 WHERE id_especialidad = ?"
            );
            $consulta->bind_param("i", $idEditar);
            $consulta->execute();
            $especialidadEditar = $consulta->get_result()->fetch_assoc();
            $consulta->close();

            if ($especialidadEditar === null) {
                $mensaje = "La especialidad seleccionada no existe.";
                $tipoMensaje = "warning";
            }
        } catch (mysqli_sql_exception $error) {
            error_log($error->getMessage());
            $mensaje = "No fue posible consultar la especialidad.";
            $tipoMensaje = "danger";
        }
    }
}

$especialidades = [];

try {
    $resultado = $conexion->query(
        "SELECT id_especialidad, nombre_especialidad, descripcion
         FROM especialidades
         ORDER BY nombre_especialidad ASC"
    );
    $especialidades = $resultado->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    $mensaje = "No fue posible consultar las especialidades.";
    $tipoMensaje = "danger";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Especialidades Médicas</title>

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

    <header class="encabezado">
        <div class="container text-center">
            <h1>Especialidades Médicas</h1>
            <p>
                Módulo para registrar, consultar, modificar y eliminar las
                especialidades disponibles en la clínica.
            </p>
        </div>
    </header>

    <main class="container my-5">
        <?php if ($mensaje !== "") { ?>
            <div class="alert alert-<?php echo escaparEspecialidad($tipoMensaje); ?> text-center">
                <?php echo escaparEspecialidad($mensaje); ?>
            </div>
        <?php } ?>

        <section class="contenedor-formulario mb-5">
            <h2>
                <?php echo $especialidadEditar !== null
                    ? "Editar especialidad"
                    : "Registrar nueva especialidad"; ?>
            </h2>

            <form method="POST" action="especialidades.php">
                <?php if ($especialidadEditar !== null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input
                        type="hidden"
                        name="id_especialidad"
                        value="<?php echo (int) $especialidadEditar["id_especialidad"]; ?>"
                    >
                <?php } else { ?>
                    <input type="hidden" name="accion" value="agregar">
                <?php } ?>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label for="nombre_especialidad" class="form-label">
                            Nombre de la especialidad
                        </label>
                        <input
                            id="nombre_especialidad"
                            type="text"
                            name="nombre_especialidad"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?php
                                echo escaparEspecialidad(
                                    $especialidadEditar["nombre_especialidad"] ?? ""
                                );
                            ?>"
                        >
                    </div>

                    <div class="col-md-7 mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea
                            id="descripcion"
                            name="descripcion"
                            class="form-control"
                            rows="3"
                            required
                        ><?php
                            echo escaparEspecialidad(
                                $especialidadEditar["descripcion"] ?? ""
                            );
                        ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php echo $especialidadEditar !== null
                        ? "Guardar cambios"
                        : "Agregar especialidad"; ?>
                </button>

                <?php if ($especialidadEditar !== null) { ?>
                    <a href="especialidades.php" class="btn btn-secondary">
                        Cancelar edición
                    </a>
                <?php } ?>
            </form>
        </section>

        <section>
            <h2 class="mb-4">Especialidades disponibles</h2>

            <?php if (empty($especialidades)) { ?>
                <div class="alert alert-secondary">
                    No hay especialidades registradas.
                </div>
            <?php } else { ?>
                <div class="row g-4">
                    <?php foreach ($especialidades as $especialidad) { ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card tarjeta-servicio h-100">
                                <div class="card-body">
                                    <h3>
                                        <?php
                                            echo escaparEspecialidad(
                                                $especialidad["nombre_especialidad"]
                                            );
                                        ?>
                                    </h3>

                                    <p>
                                        <?php
                                            echo escaparEspecialidad(
                                                $especialidad["descripcion"]
                                            );
                                        ?>
                                    </p>

                                    <a
                                        href="especialidades.php?editar=<?php
                                            echo (int) $especialidad["id_especialidad"];
                                        ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        action="especialidades.php"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Desea eliminar esta especialidad?');"
                                    >
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input
                                            type="hidden"
                                            name="id_especialidad"
                                            value="<?php
                                                echo (int) $especialidad["id_especialidad"];
                                            ?>"
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

    <footer class="pie-pagina text-center">
        <p>© 2026 Clínica Salud Local - Sistema de Gestión y Reserva de Citas</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>