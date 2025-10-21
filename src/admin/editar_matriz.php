<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$matrizData = $matriz->obtenerPorId($id);

if (!$matrizData) {
    header('Location: matrices.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datos = [
        'dominio' => limpiarDatos($_POST['dominio']),
        'competencia' => limpiarDatos($_POST['competencia']),
        'resultado_aprendizaje' => limpiarDatos($_POST['resultado_aprendizaje']),
        'criterios_logro' => limpiarDatos($_POST['criterios_logro']),
        'contenidos' => limpiarDatos($_POST['contenidos']),
        'bibliografia' => limpiarDatos($_POST['bibliografia']),
        'metodologias' => limpiarDatos($_POST['metodologias']),
        'estrategias' => limpiarDatos($_POST['estrategias']),
        'sct_chile' => (int)limpiarDatos($_POST['sct_chile'])
    ];

    if ($matriz->actualizar($id, $datos)) {
        $success = "Matriz de coherencia actualizada exitosamente.";
        $matrizData = $matriz->obtenerPorId($id);
    } else {
        $error = "Error al actualizar la matriz de coherencia.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Editar Matriz de Coherencia - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Editar Matriz de Coherencia Curricular</h2>
                            <a href="matrices.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="dominio" class="form-label">Dominio</label>
                                    <input type="text" class="form-control" id="dominio" name="dominio"
                                        value="<?php echo htmlspecialchars($matrizData['dominio']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="competencia" class="form-label">Competencia</label>
                                    <input type="text" class="form-control" id="competencia" name="competencia"
                                        value="<?php echo htmlspecialchars($matrizData['competencia']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="resultado_aprendizaje" class="form-label">Resultados de Aprendizaje</label>
                                <textarea class="form-control" id="resultado_aprendizaje" name="resultado_aprendizaje"
                                    rows="3" required><?php echo htmlspecialchars($matrizData['resultado_aprendizaje']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="criterios_logro" class="form-label">Criterios de Logro</label>
                                <textarea class="form-control" id="criterios_logro" name="criterios_logro"
                                    rows="3" required><?php echo htmlspecialchars($matrizData['criterios_logro']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="contenidos" class="form-label">Contenidos/Saberes</label>
                                <textarea class="form-control" id="contenidos" name="contenidos"
                                    rows="3"><?php echo htmlspecialchars($matrizData['contenidos']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="bibliografia" class="form-label">Bibliografía</label>
                                <textarea class="form-control" id="bibliografia" name="bibliografia"
                                    rows="3"><?php echo htmlspecialchars($matrizData['bibliografia']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="metodologias" class="form-label">Metodologías Activas</label>
                                <textarea class="form-control" id="metodologias" name="metodologias"
                                    rows="3"><?php echo htmlspecialchars($matrizData['metodologias']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="estrategias" class="form-label">Estrategias</label>
                                <textarea class="form-control" id="estrategias" name="estrategias"
                                    rows="3"><?php echo htmlspecialchars($matrizData['estrategias'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="sct_chile" class="form-label">SCT-Chile</label>
                                <input type="number" class="form-control" id="sct_chile" name="sct_chile"
                                    value="<?php echo htmlspecialchars($matrizData['sct_chile']); ?>" min="0">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Actualizar Matriz</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>