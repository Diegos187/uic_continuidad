<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Atributo.php';

$db = new Database();
$conn = $db->conectar();
$atributo = new Atributo($conn);

// Recibe asignatura_id (preferido) o carrera_id (compatibilidad)
$asignaturaId = isset($_GET['asignatura_id']) ? (int)$_GET['asignatura_id'] : null;
$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : null;

if ($asignaturaId && !$carreraId) {
    // Intentar resolver carrera_id desde asignatura
    $stmt = $conn->prepare('SELECT carrera_id FROM asignaturas WHERE id = :id');
    $stmt->bindParam(':id', $asignaturaId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $carreraId = (int)$row['carrera_id'];
    }
}

// Si no hay carrera explícita ni derivada, intentar usar directamente el valor recibido como carrera para compatibilidad
if (!$carreraId && $asignaturaId) {
    $carreraId = $asignaturaId;
}

// Preparar respuestas vacías por defecto
$dominios = $competencias = $resultados = $areas = $perfiles = $versiones = [];

if ($carreraId) {
    // Nuevas tablas: dominios y competencias; resultados se mantienen en atributos
    $dominios = $atributo->obtenerDominios($carreraId);
    $competencias = $atributo->obtenerCompetencias($carreraId);
    $resultados = $atributo->obtenerResultados($carreraId);

    // Áreas de formación
    $stmt = $conn->prepare('SELECT id, nombre AS descripcion FROM areas_formacion WHERE carrera_id = :carrera_id ORDER BY nombre ASC');
    $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Perfiles de egreso
    $stmt = $conn->prepare('SELECT id, descripcion FROM perfiles_egreso WHERE carrera_id = :carrera_id ORDER BY id DESC');
    $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
    $stmt->execute();
    $perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Versiones de matriz
    $stmt = $conn->prepare("SELECT id, CONCAT('Versión ', numero_version, IF(descripcion IS NULL OR descripcion = '', '', CONCAT(' — ', descripcion))) AS descripcion FROM versiones_matriz WHERE carrera_id = :carrera_id ORDER BY numero_version DESC");
    $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
    $stmt->execute();
    $versiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode([
    'dominios' => $dominios,
    'competencias' => $competencias,
    'resultados' => $resultados,
    'areas' => $areas,
    'perfiles' => $perfiles,
    'versiones' => $versiones
]);
