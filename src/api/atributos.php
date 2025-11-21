<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/CompetenciaDominio.php';
require_once '../../src/models/ResultadoAprendizajeRef.php';
require_once '../../src/models/CriterioLogroRef.php';

$db = new Database();
$conn = $db->conectar();

header('Content-Type: application/json');

// Parámetros
$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : null;
$perfilId  = isset($_GET['perfil_id']) ? (int)$_GET['perfil_id'] : null;
$areaId    = isset($_GET['area_id']) ? (int)$_GET['area_id'] : null;
$action    = isset($_GET['action']) ? trim($_GET['action']) : '';

// Arrays de IDs - manejar correctamente arrays y valores únicos
$competenciaIds = [];
if (isset($_GET['competencia_ids'])) {
    $val = $_GET['competencia_ids'];
    if (is_array($val)) {
        foreach ($val as $v) {
            $competenciaIds[] = (int)$v;
        }
    } else {
        $competenciaIds[] = (int)$val;
    }
}

$resultadoIds = [];
if (isset($_GET['resultado_ids'])) {
    $val = $_GET['resultado_ids'];
    if (is_array($val)) {
        foreach ($val as $v) {
            $resultadoIds[] = (int)$v;
        }
    } else {
        $resultadoIds[] = (int)$val;
    }
}

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

    // 2) Competencias por área (nuevo)
    if ($perfilId && $areaId && $action === 'competencias') {
        $competenciaModel = new CompetenciaDominio($conn);
        $competencias = $competenciaModel->obtenerPorDetalle($perfilId, $areaId);
        echo json_encode(['competencias' => $competencias]);
        exit;
    }

    // 3) Resultados por competencias seleccionadas (nuevo)
    if ($perfilId && !empty($competenciaIds) && $action === 'resultados') {
        $resultadoModel = new ResultadoAprendizajeRef($conn);
        $resultados = [];

        foreach ($competenciaIds as $cid) {
            $res = $resultadoModel->obtenerPorCompetencia((int)$cid);
            // Agregar ID de competencia a cada resultado (sin referencia &)
            foreach ($res as $r) {
                $r['competencia_dominio_id'] = (int)$cid;
                $resultados[] = $r;
            }
        }

        // Eliminar duplicados por ID
        $resultadosUnicos = [];
        $ids = [];
        foreach ($resultados as $r) {
            if (!in_array($r['id'], $ids)) {
                $resultadosUnicos[] = $r;
                $ids[] = $r['id'];
            }
        }

        echo json_encode(['resultados' => $resultadosUnicos, 'debug' => ['competenciaIds' => $competenciaIds, 'totalResultados' => count($resultadosUnicos)]]);
        exit;
    }

    // 4) Criterios por resultados seleccionados (nuevo)
    if ($perfilId && !empty($resultadoIds) && $action === 'criterios') {
        $criterioModel = new CriterioLogroRef($conn);
        $resultadoModel = new ResultadoAprendizajeRef($conn);
        $competenciaModel = new CompetenciaDominio($conn);
        $criterios = [];

        foreach ($resultadoIds as $rid) {
            $crit = $criterioModel->obtenerPorResultado((int)$rid);
            // Obtener datos del resultado para incluir en cada criterio
            $resultadoData = $resultadoModel->obtenerPorId((int)$rid);

            // Obtener datos de la competencia
            $competenciaData = [];
            if (isset($resultadoData['competencia_dominio_id'])) {
                $competenciaData = $competenciaModel->obtenerPorId((int)$resultadoData['competencia_dominio_id']);
            }

            // Agregar ID de resultado y datos del resultado y competencia a cada criterio (sin referencia &)
            foreach ($crit as $c) {
                $c['resultado_aprendizaje_ref_id'] = (int)$rid;
                $c['resultado_codigo'] = $resultadoData['codigo'] ?? '';
                $c['resultado_descripcion'] = $resultadoData['descripcion'] ?? '';
                $c['competencia_codigo'] = $competenciaData['codigo'] ?? '';
                $c['competencia_descripcion'] = $competenciaData['descripcion'] ?? '';
                $criterios[] = $c;
            }
        }

        // Eliminar duplicados por ID
        $criteriosUnicos = [];
        $ids = [];
        foreach ($criterios as $c) {
            if (!in_array($c['id'], $ids)) {
                $criteriosUnicos[] = $c;
                $ids[] = $c['id'];
            }
        }
        echo json_encode(['criterios' => $criteriosUnicos]);
        exit;
    }    // 5) Áreas por perfil
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

    // 6) Perfiles y versiones por carrera
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
    echo json_encode(['perfiles' => [], 'versiones' => [], 'areas' => [], 'detalle' => null, 'competencias' => [], 'resultados' => [], 'criterios' => []]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en API', 'detalle' => $e->getMessage()]);
}
