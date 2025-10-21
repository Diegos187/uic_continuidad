<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Atributo.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$atributo = new Atributo($db->conectar());
$carreras = $atributo->obtenerCarreras();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $atributo->crear(
        $_POST['tipo'],
        $_POST['descripcion'],
        $_POST['carrera_id']
    );

    if ($result) {
        header('Location: atributos.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Agregar Atributo - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Agregar Atributo</h1>
            <a href="atributos.php" class="btn btn-secondary">Volver</a>
        </div>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-control" required>
                    <option value="Dominio">Dominio</option>
                    <option value="Competencia">Competencia</option>
                    <option value="Resultado Aprendizaje">Resultado de Aprendizaje</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Carrera</label>
                <select name="carrera_id" class="form-control" required>
                    <option value="">Seleccionar carrera a asociar</option>
                    <?php foreach ($carreras as $a): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo $a['nombre']; ?></option>
                    <?php endforeach; ?>
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