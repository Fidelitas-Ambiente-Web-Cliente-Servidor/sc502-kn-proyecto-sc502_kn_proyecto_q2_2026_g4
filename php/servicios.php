<?php
require_once "config/conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Acción para registrar un nuevo servicio médico
    if (isset($_POST["accion"]) && $_POST["accion"] == "agregar") {

        // Se reciben los datos enviados desde el formulario
        $nombre = $_POST["nombre_servicio"];
        $descripcion = $_POST["descripcion"];
        $costo = $_POST["costo"];
        $estado = $_POST["estado"];

        // Consulta SQL para insertar un nuevo servicio
        $sql = "INSERT INTO servicio_medico (nombre_servicio, descripcion, costo, estado)
                VALUES ('$nombre', '$descripcion', '$costo', '$estado')";

        // Se ejecuta la consulta y se guarda el mensaje correspondiente
        if ($conexion->query($sql) === TRUE) {
            $mensaje = "Servicio médico agregado correctamente.";
        } else {
            $mensaje = "Error al agregar el servicio: " . $conexion->error;
        }
    }

    // Acción para actualizar un servicio médico existente
    if (isset($_POST["accion"]) && $_POST["accion"] == "editar") {

        // Se reciben los datos del servicio a modificar
        $id = $_POST["id_servicio"];
        $nombre = $_POST["nombre_servicio"];
        $descripcion = $_POST["descripcion"];
        $costo = $_POST["costo"];
        $estado = $_POST["estado"];

        // Consulta SQL para actualizar la información
        $sql = "UPDATE servicio_medico 
                SET nombre_servicio = '$nombre',
                    descripcion = '$descripcion',
                    costo = '$costo',
                    estado = '$estado'
                WHERE id_servicio = $id";

        // Se ejecuta la consulta
        if ($conexion->query($sql) === TRUE) {
            $mensaje = "Servicio médico actualizado correctamente.";
        } else {
            $mensaje = "Error al actualizar el servicio: " . $conexion->error;
        }
    }
}

if (isset($_GET["eliminar"])) {

    $idEliminar = $_GET["eliminar"];

    $sql = "DELETE FROM servicio_medico WHERE id_servicio = $idEliminar";

    if ($conexion->query($sql) === TRUE) {
        $mensaje = "Servicio médico eliminado correctamente.";
    } else {
        $mensaje = "Error al eliminar el servicio: " . $conexion->error;
    }
}

$servicioEditar = null;

if (isset($_GET["editar"])) {

    $idEditar = $_GET["editar"];

    $sqlEditar = "SELECT * FROM servicio_medico WHERE id_servicio = $idEditar";

    $resultadoEditar = $conexion->query($sqlEditar);

    if ($resultadoEditar->num_rows > 0) {
        $servicioEditar = $resultadoEditar->fetch_assoc();
    }
}

$sqlServicios = "SELECT * FROM servicio_medico ORDER BY id_servicio ASC";

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

    <nav class="navbar navbar-expand-lg navbar-dark menu-principal">
        <div class="container">
            <a class="navbar-brand fw-bold" href="servicios.php">Clínica Salud Local</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="servicios.php" class="nav-link active">Servicios</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">Doctores</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">Citas</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">Pacientes</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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

        <?php if ($mensaje != "") { ?>
            <div class="alert alert-info text-center">
                <?php echo $mensaje; ?>
            </div>
        <?php } ?>

        <section class="contenedor-formulario mb-5">
            <h2>
                <?php 
                    if ($servicioEditar != null) {
                        echo "Editar servicio médico";
                    } else {
                        echo "Registrar nuevo servicio médico";
                    }
                ?>
            </h2>

            <form method="POST" action="servicios.php">

                <?php if ($servicioEditar != null) { ?>
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_servicio" value="<?php echo $servicioEditar['id_servicio']; ?>">
                <?php } else { ?>
                    <input type="hidden" name="accion" value="agregar">
                <?php } ?>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del servicio</label>
                        <input 
                            type="text" 
                            name="nombre_servicio" 
                            class="form-control"
                            required
                            value="<?php echo $servicioEditar != null ? $servicioEditar['nombre_servicio'] : ''; ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Costo aproximado</label>
                        <input 
                            type="number" 
                            name="costo" 
                            class="form-control"
                            required
                            value="<?php echo $servicioEditar != null ? $servicioEditar['costo'] : ''; ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select" required>
                            <option value="Activo" <?php echo ($servicioEditar != null && $servicioEditar['estado'] == 'Activo') ? 'selected' : ''; ?>>
                                Activo
                            </option>
                            <option value="Inactivo" <?php echo ($servicioEditar != null && $servicioEditar['estado'] == 'Inactivo') ? 'selected' : ''; ?>>
                                Inactivo
                            </option>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea 
                            name="descripcion" 
                            class="form-control" 
                            rows="3" 
                            required><?php echo $servicioEditar != null ? $servicioEditar['descripcion'] : ''; ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-servicio">
                    <?php echo $servicioEditar != null ? 'Guardar cambios' : 'Agregar servicio'; ?>
                </button>

                <?php if ($servicioEditar != null) { ?>
                    <a href="servicios.php" class="btn btn-secondary">Cancelar edición</a>
                <?php } ?>
            </form>
        </section>

        <section>
            <h2 class="mb-4">Servicios disponibles</h2>

            <div class="row g-4">

                <?php while ($servicio = $resultadoServicios->fetch_assoc()) { ?>

                    <div class="col-md-6 col-lg-4">
                        <div class="card tarjeta-servicio h-100">
                            <div class="card-body">

                                <h3><?php echo $servicio["nombre_servicio"]; ?></h3>

                                <p><?php echo $servicio["descripcion"]; ?></p>

                                <p class="precio">
                                    Costo aproximado: ₡<?php echo number_format($servicio["costo"], 2); ?>
                                </p>

                                <p>
                                    Estado:
                                    <span class="badge bg-info">
                                        <?php echo $servicio["estado"]; ?>
                                    </span>
                                </p>

                                <button 
                                    class="btn btn-servicio mb-2"
                                    onclick="solicitarCita('<?php echo $servicio["nombre_servicio"]; ?>')">
                                    Solicitar cita
                                </button>

                                <a 
                                    href="servicios.php?editar=<?php echo $servicio['id_servicio']; ?>" 
                                    class="btn btn-warning btn-sm">
                                    Editar
                                </a>

                                <a 
                                    href="servicios.php?eliminar=<?php echo $servicio['id_servicio']; ?>" 
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirmarEliminacion();">
                                    Eliminar
                                </a>
                            </div>
                        </div>
                    </div>

                <?php } ?>

            </div>
        </section>
    </main>

    <section class="informacion-final">
        <div class="container text-center">
            <h2>Información importante</h2>
            <p>
                Este módulo permite administrar los servicios médicos que posteriormente
                estarán disponibles para el proceso de reserva de citas.
            </p>
        </div>
    </section>

    <footer class="pie-pagina text-center">
        <p>© 2026 Clínica Salud Local - Sistema de Gestión y Reserva de Citas</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="js/servicios.js"></script>

</body>
</html>