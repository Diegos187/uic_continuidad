<?php
// ver_archivo.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Archivo.php';
require_once '../../includes/functions.php';

verificarSesion();

if (isset($_GET['id'])) {
    $db = new Database();
    $archivoModel = new Archivo($db->conectar());
    $archivo = $archivoModel->obtener($_GET['id']);
    
    if ($archivo) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $archivo['nombre'] . '"');
        echo base64_decode($archivo['contenido']);
        exit;
    }
}

header('Location: archivos.php?error=Archivo no encontrado');
exit;
?>