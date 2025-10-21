<?php
// editar_carrera.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

$error = '';
$success = '';

// Obtener el ID de la carrera
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: carreras.php');
    exit();
}

$db = new Database();
$carrera = new Carrera($db->conectar());

// Si es POST, procesar la actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datos = [
        'nombre' => limpiarDatos($_POST['nombre']),
        'jornada' => limpiarDatos($_POST['jornada']),
        'duracion_semestres' => (int)limpiarDatos($_POST['duracion_semestres']),
        'anio' => (int)limpiarDatos($_POST['anio'])
    ];
    
    if ($carrera->actualizar($id, $datos)) {
        $success = "Carrera actualizada exitosamente.";
    } else {
        $error = "Error al actualizar la carrera.";
    }
}

// Obtener datos actuales de la carrera
$datos_carrera = $carrera->obtenerPorId($id);
if (!$datos_carrera) {
    header('Location: carreras.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Carrera - UTEM</title>
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
                            <h1>Editar Carrera</h1>
                            <a href="carreras.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre de la Carrera</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    value="<?php echo htmlspecialchars($datos_carrera['nombre']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="jornada" class="form-label">Jornada</label>
                                <select class="form-control" id="jornada" name="jornada" required>
                                    <option value="Diurna" <?php echo ($datos_carrera['jornada'] == 'Diurna') ? 'selected' : ''; ?>>
                                        Diurna
                                    </option>
                                    <option value="Vespertina" <?php echo ($datos_carrera['jornada'] == 'Vespertina') ? 'selected' : ''; ?>>
                                        Vespertina
                                    </option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duracion_semestres" class="form-label">Duración en Semestres</label>
                                <input type="number" class="form-control" id="duracion_semestres" 
                                    name="duracion_semestres"
                                    value="<?php echo $datos_carrera['duracion_semestres']; ?>"
                                    min="1" max="12" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="anio" class="form-label">Año</label>
                                <input type="number" class="form-control" id="anio" 
                                    name="anio"
                                    value="<?php echo $datos_carrera['anio']; ?>"
                                    min="2020" max="2999" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Actualizar Carrera</button>
                                <a href="carreras.php" class="btn btn-secondary">Regresar</a>
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