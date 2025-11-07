<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/Matriz.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);
$versionModel = new VersionMatriz($conexion);
$matrizModel = new Matriz($conexion);

$carreras = $carrera->obtenerTodas();

$matricesLista = [];

if (isset($_GET['carrera_id']) && $_GET['carrera_id'] !== '') {
    $matricesLista = $matrizModel->obtenerPorCarrera((int)$_GET['carrera_id']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Matrices de Coherencia - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .version-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
        }

        .version-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
            background-color: #fff;
        }

        .version-card.border-primary {
            border: 2px solid #0d6efd;
            background-color: #f0f6ff;
        }

        .version-card.border-primary:hover {
            box-shadow: 0 2px 12px rgba(13, 110, 253, 0.25);
        }

        .version-card h6 {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .version-card .text-muted {
            font-size: 0.9rem;
            color: #666 !important;
        }

        .version-card .btn-group {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-title {
            font-weight: 600;
            color: #333;
        }
    </style>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col">
                <h2>Matrices de Coherencia Curricular</h2>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-secondary me-2">Volver</a>
                <a href="agregar_matriz.php" class="btn btn-primary">Nueva Matriz</a>
                <a href="atributos.php" class="btn btn-secondary">Atributos del Perfil de Egreso</a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <select name="carrera_id" class="form-select" required>
                            <option value="">Seleccione una carrera</option>
                            <?php foreach ($carreras as $carr): ?>
                                <option value="<?php echo $carr['id']; ?>" <?php echo (isset($_GET['carrera_id']) && $_GET['carrera_id'] == $carr['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($carr['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($matricesLista)): ?>
            <?php foreach ($matricesLista as $m): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title">Matriz: <?php echo htmlspecialchars($m['nombre'] ?: ('Matriz #' . (int)$m['id'])); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    Versión actual: <?php echo htmlspecialchars($m['version_descripcion'] ?: ('Versión ' . (int)($m['version_numero'] ?? 'N/A'))); ?>
                                </h6>
                                <h6 class="card-subtitle mb-2 text-muted">Creada: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($m['fecha_creacion']))); ?></h6>
                                <?php
                                $filasVersionActual = $matriz->contarFilasPorVersion((int)$m['id'], (int)($m['version_id'] ?? 0));
                                ?>
                                <span class="badge text-bg-secondary">Filas: <?php echo $filasVersionActual; ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="btn-group" role="group">
                                    <a href="editar_matriz.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-warning" title="Editar matriz">Editar</a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Eliminar (próximamente)" disabled>Eliminar</button>
                                </div>
                                <div class="btn-group ms-2" role="group">
                                    <a href="#" class="btn btn-sm btn-outline-primary" title="Descargar Word (próximamente)">Word</a>
                                    <a href="generar_matriz_excel.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-outline-success" title="Descargar Excel">Excel</a>
                                    <a href="#" class="btn btn-sm btn-outline-danger" title="Descargar PDF (próximamente)">PDF</a>
                                </div>
                                <div class="btn-group ms-2" role="group">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#modalHistorialVersiones<?php echo (int)$m['id']; ?>" title="Ver historial de versiones">Historial de versiones</button>
                                    <a href="crear_nueva_version.php?matriz_id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-primary" title="Crear nueva versión">Nueva versión</a>
                                </div>
                            </div>
                        </div>
                        <!-- Las filas se mantienen asociadas internamente a la versión; se mostrarán en detalle más adelante -->
                    </div>
                </div>

                <!-- Modal de Historial de Versiones -->
                <div class="modal fade" id="modalHistorialVersiones<?php echo (int)$m['id']; ?>" tabindex="-1" aria-labelledby="labelHistorialVersiones<?php echo (int)$m['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="labelHistorialVersiones<?php echo (int)$m['id']; ?>">Historial de versiones</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <?php
                                $versiones = $versionModel->obtenerVersionesPorMatriz((int)$m['id']);
                                $versionActualId = (int)($m['version_id'] ?? 0);
                                if (!empty($versiones)):
                                ?>
                                    <div class="d-flex flex-column gap-3">
                                        <?php foreach ($versiones as $v):
                                            $esActual = $versionActualId === (int)$v['id'];
                                        ?>
                                            <div class="version-card<?php echo $esActual ? ' border-primary' : ''; ?>" <?php echo $esActual ? 'style="border: 2px solid #0d6efd; background-color: #f0f6ff;"' : ''; ?>>
                                                <div class="mb-3">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($v['descripcion'] ?: ('Versión ' . (int)$v['numero_version'])); ?>
                                                        <?php if ($esActual): ?>
                                                            <span class="badge bg-success">actual</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-0 text-muted">
                                                        Fecha de creación: <?php echo htmlspecialchars(date('d/m/Y', strtotime($v['fecha_creacion']))); ?>
                                                    </p>
                                                </div>
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-primary" title="Previsualizar versión">Previsualizar</button>
                                                    <?php if (!$esActual): ?>
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="restablecerVersion(<?php echo (int)$m['id']; ?>, <?php echo (int)$v['id']; ?>)" title="Restablecer versión">Restablecer versión</button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarVersion(<?php echo (int)$m['id']; ?>, <?php echo (int)$v['id']; ?>)" title="Eliminar versión">Eliminar</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">No hay versiones registradas para esta matriz.</div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                                <button type="button" class="btn btn-primary" title="Guardar nueva versión">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay matrices de coherencia registradas para esta carrera.</div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function restablecerVersion(matrizId, versionId) {
            Swal.fire({
                title: '¿Restablecer versión?',
                text: 'Esta versión se convertirá en la versión actual de la matriz.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, restablecer',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Realizar petición para restablecer
                    fetch('../../src/admin/restablecer_version.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                matriz_id: matrizId,
                                version_id: versionId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: '¡Éxito!',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonColor: '#0d6efd'
                                }).then(() => {
                                    // Recargar la página para actualizar la vista
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.error || 'No se pudo restablecer la versión',
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'Error en la comunicación con el servidor',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        });
                }
            });
        }

        function eliminarVersion(matrizId, versionId) {
            Swal.fire({
                title: '¿Eliminar versión?',
                text: 'Esta acción es irreversible. Se eliminará la versión y todas las filas asociadas.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Realizar petición para eliminar
                    fetch('../../src/admin/eliminar_version.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                matriz_id: matrizId,
                                version_id: versionId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: '¡Eliminado!',
                                    text: data.message,
                                    icon: 'success',
                                    confirmButtonColor: '#0d6efd'
                                }).then(() => {
                                    // Recargar la página para actualizar la vista
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.error || 'No se pudo eliminar la versión',
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'Error en la comunicación con el servidor',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        });
                }
            });
        }
    </script>
</body>

</html>