<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Asignatura.php';
require_once '../../includes/functions.php';

verificarSesion();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirigir('asignaturas.php');
}

$id = (int)$_GET['id'];
$db = new Database();
$asignatura = new Asignatura($db->conectar());

$asignatura_actual = $asignatura->obtenerPorId($id);
if (!$asignatura_actual) {
    $_SESSION['mensaje'] = "La asignatura no existe.";
    $_SESSION['tipo_mensaje'] = "error";
    redirigir('asignaturas.php');
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