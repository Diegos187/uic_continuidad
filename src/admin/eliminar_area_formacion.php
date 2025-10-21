<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/AreaFormacion.php';
require_once '../../includes/functions.php';

verificarSesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = new Database();
$areaModel = new AreaFormacion($db->conectar());

if ($id > 0) {
    $areaModel->eliminar($id);
}

header('Location: areas_formacion.php');
exit;
