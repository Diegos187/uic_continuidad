<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

$error = '';
$success = '';

// Obtener el ID de la asignatura
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: asignaturas.php');
    exit();
}

$db = new Database();
$asignatura = new Asignatura($db->conectar());
$carrera = new Carrera($db->conectar());

// Obtener todas las carreras para el select
$carreras = $carrera->obtenerTodas();

// Si es POST, procesar la actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datos = [
        'nombre' => limpiarDatos($_POST['nombre']),
        'carrera_id' => (int)limpiarDatos($_POST['carrera_id']),
        'semestre' => (int)limpiarDatos($_POST['semestre']),
        'duracion_semanas' => (int)limpiarDatos($_POST['duracion_semanas'])
    ];
    
    if ($asignatura->actualizar($id, $datos)) {
        $success = "Actividad curricular actualizada exitosamente.";
    } else {
        $error = "Error al actualizar la actividad curricular.";
    }
}

// Obtener datos actuales de la asignatura
$datos_asignatura = $asignatura->obtenerPorId($id);
if (!$datos_asignatura) {
    header('Location: asignaturas.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Actividad Curricular - UTEM</title>
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
                            <h1>Editar Actividad Curricular</h1>
                            <a href="asignaturas.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre de la Asignatura</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    value="<?php echo htmlspecialchars($datos_asignatura['nombre']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="carrera_id" class="form-label">Carrera</label>
                                <select class="form-control" id="carrera_id" name="carrera_id" required>
                                    <option value="">Seleccione una carrera</option>
                                    <?php foreach ($carreras as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"
                                            <?php echo ($datos_asignatura['carrera_id'] == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['nombre']); ?> - 
                                            <?php echo $c['jornada']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="semestre" class="form-label">Semestre</label>
                                <input type="number" class="form-control" id="semestre" 
                                    name="semestre"
                                    value="<?php echo $datos_asignatura['semestre']; ?>"
                                    min="1" max="10" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duracion_semanas" class="form-label">Duración en Semanas</label>
                                <input type="number" class="form-control" id="duracion_semanas" 
                                    name="duracion_semanas"
                                    value="<?php echo $datos_asignatura['duracion_semanas']; ?>"
                                    min="1" max="52" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Actualizar Actividad Curricular</button>
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