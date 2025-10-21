<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();

$query = "SELECT * FROM carreras ORDER BY nombre";
$stmt = $conexion->prepare($query);
$stmt->execute();
$carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Mallas Curriculares - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                <h2>Mallas Curriculares</h2>
                </div>
                <div class="col-auto">
                <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                </div>
            </div>
        </div>

        <?php if (!empty($carreras)): ?>
            <?php foreach ($carreras as $carrera): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title"><?php echo htmlspecialchars($carrera['nombre']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Jornada: <?php echo htmlspecialchars($carrera['jornada']); ?> | 
                                    Duración: <?php echo htmlspecialchars($carrera['duracion_semestres']); ?> semestres |
                                    Año: <?php echo htmlspecialchars($carrera['anio']); ?>
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="generar_malla.php?id=<?php echo $carrera['id']; ?>" class="btn btn-primary">
                                    Descargar Malla
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay carreras registradas.</div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>