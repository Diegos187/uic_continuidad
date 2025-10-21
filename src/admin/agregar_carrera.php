<?php
// agregar_carrera.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

$error = '';
$success = '';
$nombre = $jornada = '';
$duracion_semestres = 0;
$anio = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $jornada = limpiarDatos($_POST['jornada']);
    $duracion_semestres = (int)limpiarDatos($_POST['duracion_semestres']);
    $anio = (int)limpiarDatos($_POST['anio']);
    
    $db = new Database();
    $carrera = new Carrera($db->conectar());
    
    $datos = [
        'nombre' => $nombre,
        'jornada' => $jornada,
        'duracion_semestres' => $duracion_semestres,
        'anio' => $anio
    ];
    
    $resultado = $carrera->crear($datos);
    if ($resultado) {
        $success = "Carrera creada exitosamente.";
        // Limpiar datos del formulario
        $nombre = $jornada = '';
        $duracion_semestres = 0;
    } else {
        $error = "Error al crear la carrera.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Agregar Carrera - UTEM</title>
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
                            <h1>Agregar Nueva Carrera</h1>
                            <a href="carreras.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre de la Carrera</label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                    value="<?php echo $nombre ?? ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="jornada" class="form-label">Jornada</label>
                                <select class="form-control" id="jornada" name="jornada" required>
                                    <option value="">Seleccione una jornada</option>
                                    <option value="Diurna" <?php echo ($jornada == 'Diurna') ? 'selected' : ''; ?>>Diurna</option>
                                    <option value="Vespertina" <?php echo ($jornada == 'Vespertina') ? 'selected' : ''; ?>>Vespertina</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duracion_semestres" class="form-label">Duración en Semestres</label>
                                <input type="number" class="form-control" id="duracion_semestres" name="duracion_semestres"
                                    value="<?php echo $duracion_semestres ?? '8'; ?>"
                                    min="1" max="12" required>
                            </div>

                            <div class="mb-3">
                                <label for="anio" class="form-label">Año</label>
                                <input type="number" class="form-control" id="anio" name="anio"
                                    value="<?php echo $anio ?? '2020'; ?>"
                                    min="2000" max="2999" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Crear Carrera</button>
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