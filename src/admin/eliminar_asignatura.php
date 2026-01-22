<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../includes/functions.php';

verificarSesion();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirigir('asignaturas.php');
}

$id = (int)$_GET['id'];
$db = new Database();
$conn = $db->conectar();
$asignatura = new Asignatura($conn);

$asignatura_actual = $asignatura->obtenerPorId($id);
if (!$asignatura_actual) {
    $_SESSION['mensaje'] = "La asignatura no existe.";
    $_SESSION['tipo_mensaje'] = "error";
    redirigir('asignaturas.php');
}

if (!empty($_GET['preview']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $stmt = $conn->prepare("SELECT mc.id AS fila_id, COALESCE(m.nombre, CONCAT('Matriz ', mc.matriz_id)) AS matriz_nombre
                                 FROM matrices_coherencia mc
                                 LEFT JOIN matrices m ON m.id = mc.matriz_id
                                 WHERE mc.asignatura_id = :aid
                                 ORDER BY mc.id ASC
                                 LIMIT 20");
        $stmt->bindValue(':aid', $id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'inUse' => !empty($rows),
            'usos' => $rows,
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['inUse' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$resultado = $asignatura->eliminar($id);

if ($resultado) {
    $_SESSION['mensaje'] = "Actividad curricular eliminada exitosamente.";
    $_SESSION['tipo_mensaje'] = "success";
} else {
    $_SESSION['mensaje'] = "Error al eliminar la actividad curricular. Por favor, inténtalo nuevamente.";
    $_SESSION['tipo_mensaje'] = "error";
}

redirigir('asignaturas.php');
?>