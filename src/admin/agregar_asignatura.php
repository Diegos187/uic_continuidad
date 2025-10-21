<?php
// agregar_asignatura.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

$error = '';
$success = '';
$nombre = '';
$carrera_id = 0;
$semestre = 0;
$duracion_semanas = 0;

// Obtener lista de carreras para el select
$db = new Database();
$carreraModel = new Carrera($db->conectar());
$carreras = $carreraModel->obtenerTodas();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $carrera_id = (int)limpiarDatos($_POST['carrera_id']);
    $semestre = (int)limpiarDatos($_POST['semestre']);
    $duracion_semanas = (int)limpiarDatos($_POST['duracion_semanas']);
    
    $asignatura = new Asignatura($db->conectar());
    
    $datos = [
        'nombre' => $nombre,
        'carrera_id' => $carrera_id,
        'semestre' => $semestre,
        'duracion_semanas' => $duracion_semanas
    ];
    
    $resultado = $asignatura->crear($datos);
    if ($resultado) {
        $success = "Actividad curricular creada exitosamente.";
        // Limpiar datos del formulario
        $nombre = '';
        $carrera_id = 0;
        $semestre = 0;
        $duracion_semanas = 0;
    } else {
        $error = "Error al crear la actividad curricular.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Agregar Actividad Curricular - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1>Agregar Nueva Actividad Curricular</h1>
                            <a href="asignaturas.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre de la Asignatura</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    value="<?php echo $nombre ?? ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="carrera_id" class="form-label">Carrera</label>
                                <select class="form-control" id="carrera_id" name="carrera_id" required>
                                    <option value="">Seleccione una carrera</option>
                                    <?php foreach ($carreras as $carrera): ?>
                                        <option value="<?php echo $carrera['id']; ?>"
                                            <?php echo ($carrera_id == $carrera['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($carrera['nombre']); ?> - 
                                            <?php echo $carrera['jornada']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="semestre" class="form-label">Semestre</label>
                                <input type="number" class="form-control" id="semestre" 
                                    name="semestre"
                                    value="<?php echo $semestre; ?>"
                                    min="1" max="12" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duracion_semanas" class="form-label">Duración en Semanas</label>
                                <input type="number" class="form-control" id="duracion_semanas" name="duracion_semanas"
                                    value="<?php echo $duracion_semanas ?? '16'; ?>"
                                    min="1" max="52" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Crear Actividad Curricular</button>
                                <a href="asignaturas.php" class="btn btn-secondary">Regresar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>