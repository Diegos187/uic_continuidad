<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Matriz.php';
require_once '../../includes/functions.php';

verificarSesion();

header('Content-Type: application/json');

$db = new Database();
$conexion = $db->conectar();
$matrizModel = new Matriz($conexion);

$response = [
    'success' => false,
    'message' => '',
    'error' => ''
];

try {
    // Obtener parámetros
    $matriz_id = isset($_POST['matriz_id']) ? (int)$_POST['matriz_id'] : null;
    $version_id = isset($_POST['version_id']) ? (int)$_POST['version_id'] : null;

    if (!$matriz_id || !$version_id) {
        $response['error'] = 'Parámetros inválidos';
        echo json_encode($response);
        exit;
    }

    // Verificar que la matriz existe
    $matriz = $matrizModel->obtenerPorId($matriz_id);
    if (!$matriz) {
        $response['error'] = 'Matriz no encontrada';
        echo json_encode($response);
        exit;
    }

    // Actualizar la matriz para que use la versión especificada
    $sql = "UPDATE matrices SET version_id = :version_id WHERE id = :matriz_id";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':version_id', $version_id, PDO::PARAM_INT);
    $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Versión restablecida correctamente';
    } else {
        $response['error'] = 'No se pudo actualizar la versión';
    }
} catch (Exception $e) {
    error_log('Error en restablecer_version.php: ' . $e->getMessage());
    $response['error'] = 'Error del servidor: ' . $e->getMessage();
}

echo json_encode($response);
