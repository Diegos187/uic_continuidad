<?php
// carreras.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

$db = new Database();
$carrera = new Carrera($db->conectar());
$carreras = $carrera->obtenerTodas();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Carreras - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1>Carreras</h1>
                </div>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                    <a href="agregar_carrera.php" class="btn btn-primary">Agregar Nueva Carrera</a>
                </div>
            </div>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Jornada</th>
                    <th>Duración</th>
                    <th>Año</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carreras as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                        <td><?php echo $c['jornada']; ?></td>
                        <td><?php echo $c['duracion_semestres']; ?> semestres</td>
                        <td><?php echo $c['anio']; ?></td>
                        <td>
                            <a href="editar_carrera.php?id=<?php echo $c['id']; ?>"
                                class="btn btn-warning btn-sm">Editar</a>
                            <a href="perfiles_egreso.php?carrera_id=<?php echo $c['id']; ?>"
                                class="btn btn-primary btn-sm">Perfil de Egreso</a>
                            <a href="eliminar_carrera.php?id=<?php echo $c['id']; ?>"
                                class="btn btn-danger btn-sm btn-delete-carrera"
                                data-url="eliminar_carrera.php?id=<?php echo $c['id']; ?>">
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
            const links = document.querySelectorAll('.btn-delete-carrera');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.getAttribute('data-url') || this.getAttribute('href');
                    if (!url) return;
                    if (typeof Swal === 'undefined') {
                        if (confirm('Al eliminar esta carrera se eliminarán también todas las asignaturas asociadas. ¿Desea continuar?')) {
                            window.location.href = url;
                        }
                        return;
                    }
                    Swal.fire({
                        title: 'Eliminar carrera',
                        html: 'Esta acción <b>eliminará también todas las asignaturas</b> asociadas a esta carrera. Esta operación no se puede deshacer.',
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