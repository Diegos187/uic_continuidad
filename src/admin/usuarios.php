<?php
// usuarios.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Usuario.php';
require_once '../../includes/functions.php';

verificarSesion();
$db = new Database();
$usuario = new Usuario($db->conectar());
$usuarios = $usuario->obtenerTodos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Usuarios - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                <h1>Usuarios</h1>
                </div>
                <div class="col-auto">
                <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                <a href="agregar_usuario.php" class="btn btn-primary">Agregar Nueva Usuario</a>
                </div>
            </div>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo $u['role'] == 1 ? 'Administrador' : 'Usuario'; ?></td>
                    <td>
                        <?php if ($_SESSION['usuario_id'] != $u['id']): ?>
                            <a href="editar_usuario.php?id=<?php echo $u['id']; ?>"
                               class="btn btn-warning btn-sm">Editar</a>
                            <a href="eliminar_usuario.php?id=<?php echo $u['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                Eliminar
                            </a>
                        <?php endif; ?>
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