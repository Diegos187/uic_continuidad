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

        // Verificar uso del perfil en matrices de coherencia
        $stmtUso = $conn->prepare('SELECT mc.id AS fila_id, COALESCE(m.nombre, CONCAT("Matriz ", mc.matriz_id)) AS matriz_nombre,
                                          COALESCE(a.nombre, "") AS asignatura_nombre
                                   FROM matrices_coherencia mc
                                   LEFT JOIN matrices m ON m.id = mc.matriz_id
                                   LEFT JOIN asignaturas a ON a.id = mc.asignatura_id
                                   WHERE mc.perfil_egreso_id = :pid
                                   ORDER BY mc.id ASC
                                   LIMIT 10');
        $stmtUso->bindValue(':pid', $id, PDO::PARAM_INT);
        $stmtUso->execute();
        $usos = $stmtUso->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($usos)) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            $detalles = array_map(function($r){
                $mat = trim($r['matriz_nombre']);
                $fila = (int)$r['fila_id'];
                $asig = trim($r['asignatura_nombre']);
                return $asig !== '' ? "$mat (fila #$fila, $asig)" : "$mat (fila #$fila)";
            }, $usos);
            $detalleStr = implode('; ', $detalles);
            $msg = 'No es posible eliminar el perfil: está vinculado a matrices de coherencia (' . $detalleStr . '). Cree una nueva versión en lugar de eliminar.';
            header('Location: perfiles_egreso.php?carrera_id=' . $carreraId . '&error=' . urlencode($msg));
            exit;
        }

        // Si no está en uso, eliminar con seguridad
        $perfilModel->eliminar($id);
        $conn->commit();
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
}
    header('Location: perfiles_egreso.php?carrera_id=' . $carreraId);
    exit;
