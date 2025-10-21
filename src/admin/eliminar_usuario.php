<?php
// eliminar_usuario.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Usuario.php';
require_once '../../includes/functions.php';

verificarSesion();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Evitar auto-eliminación
    if ($id == $_SESSION['usuario_id']) {
        header('Location: usuarios.php');
        exit;
    }
    
    $db = new Database();
    $usuario = new Usuario($db->conectar());
    $usuario->eliminar($id);
}

header('Location: usuarios.php');
exit;