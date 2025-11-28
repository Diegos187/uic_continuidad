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
                                class="btn btn-danger btn-sm btn-delete-asignatura"
                                data-url="eliminar_asignatura.php?id=<?php echo $a['id']; ?>">
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('.btn-delete-asignatura');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('data-url') || this.getAttribute('href');
                    if (!url) return;
                    if (typeof Swal === 'undefined') {
                        if (confirm('Al eliminar esta actividad curricular se eliminarán también sus relaciones asociadas (matrices, referencias). ¿Desea continuar?')) {
                            window.location.href = url;
                        }
                        return;
                    }
                    Swal.fire({
                        title: 'Eliminar actividad curricular',
                        html: 'Esta acción <b>eliminará la actividad curricular</b> y sus asociaciones relevantes. No se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = url;
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>