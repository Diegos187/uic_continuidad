<?php
// agregar_usuario.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Usuario.php';
require_once '../../includes/functions.php';

verificarSesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $usuario = new Usuario($db->conectar());
    
    $result = $usuario->registro(
        $_POST['nombre'],
        $_POST['email'],
        $_POST['password'],
        $_POST['role']
    );

    if ($result) {
        header('Location: usuarios.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Agregar Usuario - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Agregar Usuario</h2>
            <a href="usuarios.php" class="btn btn-secondary">Volver</a>
        </div>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select name="role" class="form-control" required>
                    <option value="1">Administrador</option>
                    <option value="2">Encargado Curricular</option>
                    <option value="3">Comité de Diseño</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
    <br><br>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>