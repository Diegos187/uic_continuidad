<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/AreaFormacion.php';
require_once '../../includes/functions.php';

verificarSesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = new Database();
$areaModel = new AreaFormacion($db->conectar());
$area = $areaModel->obtenerPorId($id);
if (!$area) {
    header('Location: areas_formacion.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre === '') {
        $error = 'El nombre es obligatorio';
    } else {
        if ($areaModel->actualizar($id, $nombre, $descripcion !== '' ? $descripcion : null)) {
            header('Location: areas_formacion.php');
            exit;
        } else {
            $error = 'No se pudo actualizar el registro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Editar Área de formación</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="container-fluid mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1>Editar Área de formación</h1>
                </div>
                <div class="col-auto">
                    <a href="areas_formacion.php" class="btn btn-secondary">Volver</a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" class="card p-3">
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($area['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="5" style="resize: vertical;"><?php echo htmlspecialchars($area['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>