<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);

$asignaturas = $asignatura->obtenerTodas();
$carreras = $carrera->obtenerTodas();

$matrices = [];

if (isset($_GET['asignatura_id'])) {
    $matrices = $matriz->obtenerPorAsignatura($_GET['asignatura_id']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Matrices de Coherencia - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col">
                <h2>Matrices de Coherencia Curricular</h2>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                <a href="agregar_matriz.php" class="btn btn-primary">Nueva Matriz</a>
                <a href="atributos.php" class="btn btn-secondary">Atributos del Perfil de Egreso</a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <select name="asignatura_id" class="form-select">
                            <option value="">Seleccione una asignatura</option>
                            <?php foreach ($asignaturas as $asig): ?>
                                <option value="<?php echo $asig['id']; ?>"
                                    <?php echo (isset($_GET['asignatura_id']) && $_GET['asignatura_id'] == $asig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($asig['nombre'] . ' — ' . $asig['nombre_carrera']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($matrices)): ?>
            <?php foreach ($matrices as $matriz): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Dominio: <?php echo htmlspecialchars($matriz['dominio']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">Competencia: <?php echo htmlspecialchars($matriz['competencia']); ?></h6>
                            </div>
                            <div class="col-auto">
                                <a href="exportar_matriz.php?id=<?php echo $matriz['id']; ?>" class="btn btn-sm btn-success">Descargar PDF</a>
                                <a href="editar_matriz.php?id=<?php echo $matriz['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $matriz['id']; ?>)">Eliminar</button>
                            </div>
                        </div>
                        <p class="mt-3"><strong>Resultados de Aprendizaje:</strong> <?php echo nl2br(htmlspecialchars($matriz['resultado_aprendizaje'])); ?></p>
                        <p class="mt-3"><strong>Criterios de logro:</strong> <?php echo nl2br(htmlspecialchars($matriz['criterios_logro'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay matrices de coherencia registradas para esta asignatura.</div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarEliminar(id) {
            if (confirm('¿Está seguro de eliminar esta matriz de coherencia?')) {
                window.location.href = 'eliminar_matriz.php?id=' + id;
            }
        }
    </script>
</body>

</html>