<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Archivo.php';
require_once '../../includes/functions.php';

verificarSesion();

if (isset($_GET['id'])) {
    $db = new Database();
    $archivoModel = new Archivo($db->conectar());
    
    if ($archivoModel->eliminar($_GET['id'])) {
        header('Location: archivos.php?mensaje=Archivo eliminado exitosamente');
        exit;
    }
}

header('Location: archivos.php?error=Error al eliminar archivo');
exit;
?>