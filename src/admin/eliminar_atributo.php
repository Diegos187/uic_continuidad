<?php
// eliminar_atributo.php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Atributo.php';
require_once '../../includes/functions.php';

verificarSesion();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $db = new Database();
    $atributo = new Atributo($db->conectar());
    $atributo->eliminar($id);
}

header('Location: atributos.php');
exit;