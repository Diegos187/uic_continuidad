<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/PerfilEgreso.php';
require_once '../../src/models/PerfilEgresoDetalle.php';
require_once '../../includes/functions.php';

verificarSesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : 0;

if ($id <= 0 || $carreraId <= 0) {
    header('Location: perfiles_egreso.php?carrera_id=' . max(0, $carreraId));
    exit;
}

$db = new Database();
$conn = $db->conectar();
$perfilModel = new PerfilEgreso($conn);
new PerfilEgresoDetalle($conn);

try {
    $perfil = $perfilModel->obtenerPorId($id);
    if (!$perfil || (int)$perfil['carrera_id'] !== $carreraId) {
        header('Location: perfiles_egreso.php?carrera_id=' . $carreraId);
        exit;
    }

    $conn->beginTransaction();

    $stmtDelMatrices = $conn->prepare('DELETE FROM matrices_coherencia WHERE perfil_egreso_id = :pid');
    $stmtDelMatrices->bindValue(':pid', $id, PDO::PARAM_INT);
    $stmtDelMatrices->execute();

    $perfilModel->eliminar($id);

    $conn->commit();
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
}

header('Location: perfiles_egreso.php?carrera_id=' . $carreraId);
exit;
