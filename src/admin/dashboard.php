<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
verificarSesion();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>UTEM - Sistema de Gestión de Mallas Curriculares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../public/css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <h1>Bienvenido al Sistema de Gestión de Mallas Curriculares</h1>
                <h6>Elija una opción:</h6>
                <br>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Gestión de Carreras</h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Agregar, editar y eliminar carreras
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="../../src/admin/carreras.php" class="btn btn-primary">
                                    Acceder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Gestión de Actividades Curriculares</h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Agregar, editar y eliminar actividades curriculares
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="../../src/admin/asignaturas.php" class="btn btn-primary">
                                    Acceder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Gestión de Matrices de Coherencia Curricular</h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Agregar, editar y eliminar matrices de coherencia curricular para cada carrera registrada
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="../../src/admin/matrices.php" class="btn btn-primary">
                                    Acceder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Generar Mallas</h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Generar mallas curriculares para cada carrera registrada en base a la información existente
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="../../src/admin/mallas.php" class="btn btn-primary">
                                    Acceder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Almacén de documentos</h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Carga y descarga documentos relevantes para la gestión de mallas curriculares
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="../../src/admin/archivos.php" class="btn btn-primary">
                                    Acceder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Gestión de Usuarios</h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Administrar usuarios del sistema
                                </h6>
                            </div>
                            <div class="col-auto">
                                <a href="../../src/admin/usuarios.php" class="btn btn-primary">
                                    Acceder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>