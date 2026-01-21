<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/AreaFormacion.php';
require_once '../../includes/functions.php';

verificarSesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = new Database();
$conn = $db->conectar();
$areaModel = new AreaFormacion($conn);

if ($id > 0) {
    // Verificar usos en perfiles de egreso y asociaciones de carrera
    try {
        // Usos en perfiles de egreso (dominios del área)
        $stmtPerfiles = $conn->prepare('SELECT c.nombre AS carrera_nombre, p.id AS perfil_id, p.descripcion AS perfil_desc
                                         FROM perfiles_egreso_detalle ped
                                         JOIN perfiles_egreso p ON p.id = ped.perfil_egreso_id
                                         JOIN carreras c ON c.id = p.carrera_id
                                         WHERE ped.area_formacion_id = :aid
                                         ORDER BY c.nombre, p.id');
        $stmtPerfiles->bindValue(':aid', $id, PDO::PARAM_INT);
        $stmtPerfiles->execute();
        $usosPerfiles = $stmtPerfiles->fetchAll(PDO::FETCH_ASSOC);

        // Asociaciones directas de área a carrera
        $stmtCarr = $conn->prepare('SELECT DISTINCT c.nombre AS carrera_nombre
                                     FROM carrera_area_formacion caf
                                     JOIN carreras c ON c.id = caf.carrera_id
                                     WHERE caf.area_formacion_id = :aid
                                     ORDER BY c.nombre');
        $stmtCarr->bindValue(':aid', $id, PDO::PARAM_INT);
        $stmtCarr->execute();
        $carrerasAsociadas = $stmtCarr->fetchAll(PDO::FETCH_ASSOC);

        // Si está en uso, construir mensaje y bloquear eliminación
        if (!empty($usosPerfiles) || !empty($carrerasAsociadas)) {
            $partes = [];
            if (!empty($usosPerfiles)) {
                $items = array_map(function($r){
                    $car = trim($r['carrera_nombre']);
                    $pid = (int)$r['perfil_id'];
                    $pdesc = trim($r['perfil_desc']);
                    $pdescShort = mb_substr($pdesc, 0, 80);
                    return "$car en perfil #$pid (" . ($pdescShort !== '' ? $pdescShort : 'sin descripción') . ")";
                }, $usosPerfiles);
                // Evitar mensajes extremadamente largos
                $items = array_slice($items, 0, 10);
                $partes[] = 'usada por ' . implode('; ', $items);
                if (count($usosPerfiles) > 10) {
                    $partes[] = '(+' . (count($usosPerfiles) - 10) . ' más)';
                }
            }
            // Carreras asociadas sin perfil
            if (!empty($carrerasAsociadas)) {
                $nombres = array_map(function($r){ return trim($r['carrera_nombre']); }, $carrerasAsociadas);
                $nombres = array_unique($nombres);
                $nombres = array_slice($nombres, 0, 10);
            }

            $msg = 'No es posible eliminar el área de formación: ' . implode(' | ', $partes) . '. Desvincule o actualice los perfiles antes de eliminar.';
            header('Location: areas_formacion.php?error=' . urlencode($msg));
            exit;
        }

        // Si no está en uso, eliminar
        $areaModel->eliminar($id);
    } catch (Exception $e) {
        header('Location: areas_formacion.php?error=' . urlencode('Error al eliminar el área: ' . $e->getMessage()));
        exit;
    }
}

header('Location: areas_formacion.php');
exit;
