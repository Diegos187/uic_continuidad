<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/Matriz.php';
require_once '../../includes/functions.php';

verificarSesion();

header('Content-Type: application/json');

$db = new Database();
$conexion = $db->conectar();
$matrizModel = new Matriz($conexion);
$versionModel = new VersionMatriz($conexion);
$matrizCoherenciaModel = new MatrizCoherencia($conexion);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$matriz_id = isset($_POST['matriz_id']) ? (int)$_POST['matriz_id'] : 0;
$tipo_eliminacion = isset($_POST['tipo_eliminacion']) ? $_POST['tipo_eliminacion'] : '';

if (!$matriz_id || !$tipo_eliminacion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros incompletos']);
    exit;
}

// Obtener información de la matriz
$matriz = $matrizModel->obtenerPorId($matriz_id);
if (!$matriz) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Matriz no encontrada']);
    exit;
}

try {
    if ($tipo_eliminacion === 'completa') {
        // Eliminar toda la matriz y todas sus versiones
        $versiones = $versionModel->obtenerVersionesPorMatriz($matriz_id);

        foreach ($versiones as $version) {
            $filas = $matrizCoherenciaModel->obtenerPorMatrizYVersion($matriz_id, (int)$version['id']);
            foreach ($filas as $fila) {
                $matrizCoherenciaModel->eliminar((int)$fila['id']);
            }
        }

        foreach ($versiones as $version) {
            $versionModel->eliminar((int)$version['id']);
        }

        $matrizModel->eliminar($matriz_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Matriz eliminada correctamente con todas sus versiones'
        ]);
        exit;
    } elseif ($tipo_eliminacion === 'version') {
        // Eliminar solo la versión actual
        $version_id = isset($_POST['version_id']) ? (int)$_POST['version_id'] : 0;

        if (!$version_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Versión no especificada']);
            exit;
        }

        $versiones = $versionModel->obtenerVersionesPorMatriz($matriz_id);

        if (count($versiones) <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No se puede eliminar la única versión. Elimina la matriz completa en su lugar.']);
            exit;
        }

        $filas = $matrizCoherenciaModel->obtenerPorMatrizYVersion($matriz_id, $version_id);
        foreach ($filas as $fila) {
            $matrizCoherenciaModel->eliminar((int)$fila['id']);
        }

        $versionModel->eliminar($version_id);

        $versionesRestantes = $versionModel->obtenerVersionesPorMatriz($matriz_id);
        if (!empty($versionesRestantes)) {
            $nuevaVersionActual = $versionesRestantes[0]; 

            $updateSql = "UPDATE matrices SET version_id = :version_id WHERE id = :matriz_id";
            $stmt = $conexion->prepare($updateSql);
            $stmt->bindParam(':version_id', $nuevaVersionActual['id'], PDO::PARAM_INT);
            $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Versión eliminada correctamente. Se seleccionó automáticamente la versión anterior.'
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de eliminación no válido']);
        exit;
    }
} catch (Exception $e) {
    error_log('Error en eliminar_matriz.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
    exit;
}
