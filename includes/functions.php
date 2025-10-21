<?php
function limpiarDatos($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}

function mostrarMensaje($mensaje, $tipo = 'info') {
    $alertClass = match($tipo) {
        'error' => 'alert-danger',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        default => 'alert-info'
    };

    return "<div class='alert {$alertClass}' role='alert'>{$mensaje}</div>";
}

function redirigir($url) {
    header("Location: {$url}");
    exit();
}

function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        redirigir('../auth/login.php');
    }
}