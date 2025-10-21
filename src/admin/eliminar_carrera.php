<?php
// eliminar_carrera.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

// Verificar sesión
verificarSesion();

// Verificar si se proporcionó un ID de carrera
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirigir('carreras.php');
}

$id = (int)$_GET['id'];
$db = new Database();
$carrera = new Carrera($db->conectar());

// Verificar primero si la carrera existe
$carrera_actual = $carrera->obtenerPorId($id);
if (!$carrera_actual) {
    $_SESSION['mensaje'] = "La carrera no existe.";
    $_SESSION['tipo_mensaje'] = "error";
    redirigir('carreras.php');
}

// Intentar eliminar la carrera
// Nota: La eliminación en cascada de asignaturas está manejada por la base de datos (ON DELETE CASCADE)
$resultado = $carrera->eliminar($id);

if ($resultado) {
    // Redirigir con mensaje de éxito
    $_SESSION['mensaje'] = "Carrera y todas sus asignaturas asociadas han sido eliminadas exitosamente.";
    $_SESSION['tipo_mensaje'] = "success";
} else {
    // Mostrar error
    $_SESSION['mensaje'] = "Error al eliminar la carrera. Por favor, inténtalo nuevamente.";
    $_SESSION['tipo_mensaje'] = "error";
}

redirigir('carreras.php');
?>