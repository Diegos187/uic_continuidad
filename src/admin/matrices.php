<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);
$versionModel = new VersionMatriz($conexion);

$carreras = $carrera->obtenerTodas();

$versiones = [];

if (isset($_GET['carrera_id']) && $_GET['carrera_id'] !== '') {
    $versiones = $versionModel->obtenerPorCarrera((int)$_GET['carrera_id']);
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
                        <select name="carrera_id" class="form-select" required>
                            <option value="">Seleccione una carrera</option>
                            <?php foreach ($carreras as $carr): ?>
                                <option value="<?php echo $carr['id']; ?>" <?php echo (isset($_GET['carrera_id']) && $_GET['carrera_id'] == $carr['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($carr['nombre']); ?>
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

        <?php if (!empty($versiones)): ?>
            <?php foreach ($versiones as $version): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Matriz: <?php echo htmlspecialchars($version['descripcion'] ?: ('Versión ' . (int)$version['numero_version'])); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">Versión #<?php echo (int)$version['numero_version']; ?> — <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($version['fecha_creacion']))); ?></h6>
                                <span class="badge text-bg-secondary">Filas: <?php echo (int)($version['filas_count'] ?? 0); ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="btn-group" role="group">
                                    <a href="#" class="btn btn-sm btn-warning" title="Editar (próximamente)">Editar</a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Eliminar (próximamente)" disabled>Eliminar</button>
                                </div>
                                <div class="btn-group ms-2" role="group">
                                    <a href="#" class="btn btn-sm btn-outline-primary" title="Descargar Word (próximamente)">Word</a>
                                    <a href="generar_matriz_excel.php?id=<?php echo (int)$version['id']; ?>" class="btn btn-sm btn-outline-success" title="Descargar Excel">Excel</a>
                                    <a href="#" class="btn btn-sm btn-outline-danger" title="Descargar PDF (próximamente)">PDF</a>
                                </div>
                                <div class="btn-group ms-2" role="group">
                                    <a href="#" class="btn btn-sm btn-secondary" title="Historial de versiones (próximamente)">Historial de versiones</a>
                                    <a href="#" class="btn btn-sm btn-primary" title="Nueva versión (próximamente)">Nueva versión</a>
                                </div>
                            </div>
                        </div>
                        <!-- Las filas se mantienen asociadas internamente a la versión; se mostrarán en detalle más adelante -->
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay matrices de coherencia registradas para esta carrera.</div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Botones sin funcionalidad aún (placeholders)
    </script>
</body>

</html>