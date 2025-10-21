<?php
// asignaturas.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Asignatura.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

$db = new Database();
$asignatura = new Asignatura($db->conectar());
$asignaturas = $asignatura->obtenerTodas();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Actividades Curriculares - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1>Actividades Curriculares</h1>
                </div>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                    <a href="agregar_asignatura.php" class="btn btn-primary">Agregar Nueva Actividad Curricular</a>
                </div>
            </div>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Carrera</th>
                    <th>Semestre</th>
                    <th>Duración</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($asignaturas as $a): ?>
                    <tr>
                        <td><?php echo $a['id']; ?></td>
                        <td><?php echo htmlspecialchars($a['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($a['nombre_carrera']); ?></td>
                        <td><?php echo $a['semestre']; ?></td>
                        <td><?php echo $a['duracion_semanas']; ?> semanas</td>
                        <td>
                            <a href="editar_asignatura.php?id=<?php echo $a['id']; ?>"
                                class="btn btn-warning btn-sm">Editar</a>
                            <a href="eliminar_asignatura.php?id=<?php echo $a['id']; ?>"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('¿Estás seguro de eliminar esta actividad curricular?')">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>