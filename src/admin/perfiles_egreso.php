<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/PerfilEgreso.php';
require_once '../../includes/functions.php';

verificarSesion();

$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : 0;
if (!$carreraId) {
    header('Location: carreras.php');
    exit;
}

$db = new Database();
$conn = $db->conectar();
$carreraModel = new Carrera($conn);
$perfilModel = new PerfilEgreso($conn);

$carrera = $carreraModel->obtenerPorId($carreraId);
if (!$carrera) {
    header('Location: carreras.php');
    exit;
}

$perfiles = $perfilModel->obtenerPorCarrera($carreraId);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Perfil de Egreso - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .descripcion-cell {
            white-space: pre-wrap;
        }
    </style>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1>Perfil de egreso (<?php echo htmlspecialchars($carrera['nombre']); ?>)</h1>
                </div>
                <div class="col-auto">
                    <a href="carreras.php" class="btn btn-secondary me-2">Volver</a>
                    <a href="agregar_perfil_egreso.php?carrera_id=<?php echo $carreraId; ?>" class="btn btn-primary">Agregar nuevo perfil de egreso</a>
                </div>
            </div>
        </div>

        <?php if (empty($perfiles)) : ?>
            <div class="alert alert-info">No hay perfiles de egreso registrados para esta carrera.</div>
        <?php else : ?>
            <div class="card">
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descripción</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perfiles as $p): ?>
                                <tr>
                                    <td><?php echo (int)$p['id']; ?></td>
                                    <td class="descripcion-cell"><?php echo nl2br(htmlspecialchars($p['descripcion'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                                    <td>
                                        <a href="editar_perfil_egreso.php?id=<?php echo (int)$p['id']; ?>&carrera_id=<?php echo $carreraId; ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <a href="eliminar_perfil_egreso.php?id=<?php echo (int)$p['id']; ?>&carrera_id=<?php echo $carreraId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desea eliminar este perfil de egreso?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>