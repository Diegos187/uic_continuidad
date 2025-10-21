<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Archivo.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$archivo = new Archivo($db->conectar());
$archivos = $archivo->obtenerTodos();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de Archivos - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h1>Gestión de Archivos</h1>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Subir nuevo archivo</h5>
            </div>
            <div class="card-body">
                <form action="subir_archivo.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Archivo PDF</label>
                            <input type="file" 
                            name="archivo" 
                            class="form-control" 
                            accept=".pdf" 
                            required 
                            onchange="validateFileSize(this)"
                            max="7340032">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" required></input>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Subir</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Archivos almacenados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Subido por</th>
                                <th>Fecha y hora</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archivos as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($a['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars($a['usuario_nombre']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></td>
                                    <td>
                                        <a href="ver_archivo.php?id=<?php echo $a['id']; ?>" 
                                           class="btn btn-info btn-sm" 
                                           target="_blank">Ver</a>
                                        <a href="eliminar_archivo.php?id=<?php echo $a['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('¿Eliminar este archivo?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <br>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function validateFileSize(input) {
        const maxSize = 7 * 1024 * 1024; // 7MB en bytes
        if (input.files[0].size > maxSize) {
            Swal.fire({
                title: 'Error',
                text: 'El archivo excede el límite de 7MB',
                icon: 'error'
            });
            input.value = '';
        }
    }
    </script>
    <script>
    // Verificamos si hay parámetros en la URL
    const urlParams = new URLSearchParams(window.location.search);
    const mensaje = urlParams.get('mensaje');
    const error = urlParams.get('error');

    if (mensaje) {
        Swal.fire({
            title: '¡Éxito!',
            text: mensaje,
            icon: 'success',
            confirmButtonText: 'Ok'
        });
        // Limpia la URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    if (error) {
        Swal.fire({
            title: 'Error',
            text: error,
            icon: 'error',
            confirmButtonText: 'Ok'
        });
        // Limpia la URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Confirmación para eliminar
    document.querySelectorAll('a[href*="eliminar_archivo.php"]').forEach(link => {
        link.onclick = function(e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Estás seguro?',
                text: "No podrás revertir esta acción",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = this.href;
                }
            })
        };
    });
</script>
</body>
</html>