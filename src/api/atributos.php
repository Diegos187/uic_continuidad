<?php
session_start();
require_once '../../config/database.php';

$db = new Database();
$conn = $db->conectar();

header('Content-Type: application/json');

// Parámetros
$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : null;
$perfilId  = isset($_GET['perfil_id']) ? (int)$_GET['perfil_id'] : null;
$areaId    = isset($_GET['area_id']) ? (int)$_GET['area_id'] : null;
$action    = isset($_GET['action']) ? trim($_GET['action']) : '';

try {
    // 1) Detalle dominio/competencia por perfil+área
    if ($perfilId && $areaId && ($action === 'detalle' || $action === 'detail')) {
        $stmt = $conn->prepare('SELECT dominio, competencia FROM perfiles_egreso_detalle WHERE perfil_egreso_id = :pid AND area_formacion_id = :aid ORDER BY id ASC LIMIT 1');
        $stmt->bindValue(':pid', $perfilId, PDO::PARAM_INT);
        $stmt->bindValue(':aid', $areaId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['dominio' => '', 'competencia' => ''];
        echo json_encode(['detalle' => $row]);
        exit;
    }

    // 2) Áreas por perfil
    if ($perfilId && ($action === 'areas' || $action === 'areas_por_perfil' || empty($action))) {
        $stmt = $conn->prepare('SELECT DISTINCT af.id, af.nombre AS descripcion, 
                                       ped.dominio, ped.competencia
                                 FROM perfiles_egreso_detalle ped
                                 INNER JOIN areas_formacion af ON af.id = ped.area_formacion_id
                                 WHERE ped.perfil_egreso_id = :pid
                                 ORDER BY af.nombre ASC');
        $stmt->bindValue(':pid', $perfilId, PDO::PARAM_INT);
        $stmt->execute();
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['areas' => $areas]);
        exit;
    }

    // 3) Perfiles y versiones por carrera
    if ($carreraId) {
        // Perfiles
        $stmt = $conn->prepare('SELECT id, descripcion FROM perfiles_egreso WHERE carrera_id = :cid ORDER BY id DESC');
        $stmt->bindValue(':cid', $carreraId, PDO::PARAM_INT);
        $stmt->execute();
        $perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Versiones
        $stmt = $conn->prepare("SELECT id, CONCAT('Versión ', numero_version, IF(descripcion IS NULL OR descripcion = '', '', CONCAT(' — ', descripcion))) AS descripcion FROM versiones_matriz WHERE carrera_id = :cid ORDER BY numero_version DESC");
        $stmt->bindValue(':cid', $carreraId, PDO::PARAM_INT);
        $stmt->execute();
        $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'perfiles' => $perfiles,
            'versiones' => $versiones
        ]);
        exit;
    }

    // Default vacío
    echo json_encode(['perfiles' => [], 'versiones' => [], 'areas' => [], 'detalle' => null]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en API', 'detalle' => $e->getMessage()]);
}
