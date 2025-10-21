<?php
// subir_archivo.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Archivo.php';
require_once '../../includes/functions.php';

verificarSesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo'];
    $descripcion = $_POST['descripcion'];
    $maxSize = 7 * 1024 * 1024; // 7MB en bytes
    
    if ($archivo['size'] > $maxSize) {
        header('Location: archivos.php?error=El archivo excede el límite de 7MB');
        exit;
    }
    
    if ($archivo['type'] === 'application/pdf') {
        $contenido = base64_encode(file_get_contents($archivo['tmp_name']));
        $db = new Database();
        $archivoModel = new Archivo($db->conectar());
        
        if ($archivoModel->subir($archivo['name'], $descripcion, $archivo['type'], $contenido, $_SESSION['usuario_id'])) {
            header('Location: archivos.php?mensaje=Archivo subido exitosamente');
            exit;
        }
    }
    
    header('Location: archivos.php?error=Error al subir archivo. Solo se permiten archivos PDF');
    exit;
}