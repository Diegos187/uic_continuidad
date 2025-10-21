<?php
// eliminar_asignatura.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Asignatura.php';
require_once '../../includes/functions.php';

// Verificar sesión
verificarSesion();

// Verificar si se proporcionó un ID de asignatura
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirigir('asignaturas.php');
}

$id = (int)$_GET['id'];
$db = new Database();
$asignatura = new Asignatura($db->conectar());

// Verificar primero si la asignatura existe
$asignatura_actual = $asignatura->obtenerPorId($id);
if (!$asignatura_actual) {
    $_SESSION['mensaje'] = "La asignatura no existe.";
    $_SESSION['tipo_mensaje'] = "error";
    redirigir('asignaturas.php');
}

// Intentar eliminar la asignatura
$resultado = $asignatura->eliminar($id);

if ($resultado) {
    // Redirigir con mensaje de éxito
    $_SESSION['mensaje'] = "Actividad curricular eliminada exitosamente.";
    $_SESSION['tipo_mensaje'] = "success";
} else {
    // Mostrar error
    $_SESSION['mensaje'] = "Error al eliminar la actividad curricular. Por favor, inténtalo nuevamente.";
    $_SESSION['tipo_mensaje'] = "error";
}

redirigir('asignaturas.php');
?>