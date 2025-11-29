<?php
// atributos.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Atributo.php';
require_once '../../includes/functions.php';

verificarSesion();
$db = new Database();
$atributo = new Atributo($db->conectar());
$atributos = $atributo->obtenerTodos();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Atributos - UTEM</title>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1>Atributos</h1>
                </div>
                <div class="col-auto">
                    <a href="matrices.php" class="btn btn-secondary me-2">Volver</a>
                    <a href="agregar_atributo.php" class="btn btn-primary">Agregar Nuevo Atributo</a>
                </div>
            </div>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Carrera</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($atributos as $a): ?>
                    <tr>
                        <td><?php echo $a['id']; ?></td>
                        <td><?php echo $a['tipo']; ?></td>
                        <td><?php echo htmlspecialchars($a['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($a['nombre_asignatura'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a href="editar_atributo.php?id=<?php echo $a['id']; ?>"
                                class="btn btn-warning btn-sm">Editar</a>
                            <a href="eliminar_atributo.php?id=<?php echo $a['id']; ?>"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('¿Estás seguro de eliminar este atributo?')">
                                Eliminar
                            </a>
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