<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Matriz.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../includes/functions.php';

verificarSesion();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

if (!isset($_POST['matriz_id']) || !isset($_POST['version_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit();
}

try {
    $db = new Database();
    $conexion = $db->conectar();
    $matriz_id = (int)$_POST['matriz_id'];
    $version_id = (int)$_POST['version_id'];

    // Validar que la versión existe y pertenece a la matriz
    $versionModel = new VersionMatriz($conexion);
    $versionInfo = $conexion->prepare("SELECT * FROM versiones_matriz WHERE id = :id AND matriz_id = :matriz_id");
    $versionInfo->bindParam(':id', $version_id, PDO::PARAM_INT);
    $versionInfo->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
    $versionInfo->execute();
    $version = $versionInfo->fetch(PDO::FETCH_ASSOC);

    if (!$version) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'La versión no existe o no pertenece a esta matriz']);
        exit();
    }

    // Verificar si esta es la versión actual
    $matrizModel = new Matriz($conexion);
    $matrizInfo = $conexion->prepare("SELECT version_id FROM matrices WHERE id = :id");
    $matrizInfo->bindParam(':id', $matriz_id, PDO::PARAM_INT);
    $matrizInfo->execute();
    $matriz = $matrizInfo->fetch(PDO::FETCH_ASSOC);

    if ($matriz && $matriz['version_id'] == $version_id) {
        // Si es la versión actual, no permitir eliminar
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar la versión actual. Restablece otra versión primero.']);
        exit();
    }

    // Iniciar transacción
    $conexion->beginTransaction();

    // 1. Eliminar todas las filas de matrices_coherencia asociadas a esta versión
    $deleteFilas = $conexion->prepare("DELETE FROM matrices_coherencia WHERE matriz_id = :matriz_id AND version_id = :version_id");
    $deleteFilas->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
    $deleteFilas->bindParam(':version_id', $version_id, PDO::PARAM_INT);
    if (!$deleteFilas->execute()) {
        throw new PDOException('Error al eliminar filas de matrices_coherencia');
    }

    // 2. Eliminar la versión de versiones_matriz
    $deleteVersion = $conexion->prepare("DELETE FROM versiones_matriz WHERE id = :id AND matriz_id = :matriz_id");
    $deleteVersion->bindParam(':id', $version_id, PDO::PARAM_INT);
    $deleteVersion->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
    if (!$deleteVersion->execute()) {
        throw new PDOException('Error al eliminar la versión');
    }

    // Confirmar transacción
    $conexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Versión eliminada correctamente'
    ]);
    exit();
} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al eliminar versión: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en el servidor: ' . $e->getMessage()]);
    exit();
}
