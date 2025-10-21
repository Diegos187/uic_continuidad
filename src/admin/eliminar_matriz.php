<?php
// eliminar_matriz.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../includes/functions.php';

// Verificar sesión
verificarSesion();

// Verificar si se proporcionó un ID de matriz
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirigir('matrices.php');
}

$id = (int)$_GET['id'];
$db = new Database();
$matriz = new MatrizCoherencia($db->conectar());

// Verificar primero si la carrera existe
$matriz_actual = $matriz->obtenerPorId($id);
if (!$matriz_actual) {
    $_SESSION['mensaje'] = "La matriz no existe.";
    $_SESSION['tipo_mensaje'] = "error";
    redirigir('matrices.php');
}

// Intentar eliminar la matriz
// Nota: La eliminación en cascada de asignaturas está manejada por la base de datos (ON DELETE CASCADE)
$resultado = $matriz->eliminar($id);

if ($resultado) {
    // Redirigir con mensaje de éxito
    $_SESSION['mensaje'] = "La matriz ha sido eliminada exitosamente.";
    $_SESSION['tipo_mensaje'] = "success";
} else {
    // Mostrar error
    $_SESSION['mensaje'] = "Error al eliminar la matriz. Por favor, inténtalo nuevamente.";
    $_SESSION['tipo_mensaje'] = "error";
}

redirigir('matrices.php');
?>