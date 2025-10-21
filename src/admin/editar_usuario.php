<?php
// editar_usuario.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Usuario.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$usuario = new Usuario($db->conectar());

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $usuario->actualizar(
        $_POST['id'],
        $_POST['nombre'],
        $_POST['email'],
        $_POST['role']
    );

    if ($result) {
        header('Location: usuarios.php');
        exit;
    }
}

$user = $usuario->obtenerPorId($_GET['id']);
if (!$user || $user['id'] == $_SESSION['usuario_id']) {
    header('Location: usuarios.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Usuario - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Editar Usuario</h2>
            <a href="usuarios.php" class="btn btn-secondary">Volver</a>
        </div>
        <form method="POST" class="mt-4">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" 
                       value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select name="role" class="form-control" required>
                    <option value="1" <?php echo $user['role'] == 1 ? 'selected' : ''; ?>>
                        Administrador
                    </option>
                    <option value="2" <?php echo $user['role'] == 2 ? 'selected' : ''; ?>>
                        Encargado Curricular
                    </option>
                    <option value="3" <?php echo $user['role'] == 3 ? 'selected' : ''; ?>>
                        Comité de Diseño
                    </option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
    <br><br>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>