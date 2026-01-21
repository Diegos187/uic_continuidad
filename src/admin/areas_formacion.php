<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/AreaFormacion.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$areaModel = new AreaFormacion($db->conectar());
$areas = $areaModel->obtenerTodas();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Áreas de formación - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        .descripcion-col {
            max-width: 520px;
        }
    </style>
</head>

<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1>Áreas de formación</h1>
                </div>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                    <a href="agregar_area_formacion.php" class="btn btn-primary">Agregar Área</a>
                </div>
            </div>
        </div>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 260px;">Nombre</th>
                    <th class="descripcion-col">Descripción</th>
                    <th style="width: 240px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($areas as $a): ?>
                    <tr>
                        <td><?php echo $a['id']; ?></td>
                        <td><?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($a['descripcion'] ?? '')); ?></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="editar_area_formacion.php?id=<?php echo $a['id']; ?>">Editar</a>
                            <a class="btn btn-danger btn-sm" href="eliminar_area_formacion.php?id=<?php echo $a['id']; ?>">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>