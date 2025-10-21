<?php
// editar_atributo.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Atributo.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$atributo = new Atributo($db->conectar());

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $atributo->actualizar(
        $_POST['id'],
        $_POST['tipo'],
        $_POST['descripcion'],
        $_POST['carrera_id']
    );

    if ($result) {
        header('Location: atributos.php');
        exit;
    }
}

$attr = $atributo->obtenerPorId($_GET['id']);
$carreras = $atributo->obtenerCarreras();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar Atributo - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Editar Atributo</h1>
            <a href="atributos.php" class="btn btn-secondary">Volver</a>
        </div>
        <form method="POST" class="mt-4">
            <input type="hidden" name="id" value="<?php echo $attr['id']; ?>">
            <div class="mb-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-control" required>
                    <option value="Dominio" <?php echo $attr['tipo'] == 'Dominio' ? 'selected' : ''; ?>>Dominio</option>
                    <option value="Competencia" <?php echo $attr['tipo'] == 'Competencia' ? 'selected' : ''; ?>>Competencia</option>
                    <option value="Resultado Aprendizaje" <?php echo $attr['tipo'] == 'Resultado Aprendizaje' ? 'selected' : ''; ?>>Resultado Aprendizaje</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" required><?php echo htmlspecialchars($attr['descripcion']); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Carrera</label>
                <select name="carrera_id" class="form-control" required>
                    <option value="">Seleccionar carrera</option>
                    <?php foreach ($carreras as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $attr['carrera_id'] == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo $c['nombre']; ?>
                    </option>
                    <?php endforeach; ?>
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