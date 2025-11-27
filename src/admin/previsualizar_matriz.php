<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/Matriz.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$matrizModel = new Matriz($conexion);
$versionModel = new VersionMatriz($conexion);
$matrizCoherenciaModel = new MatrizCoherencia($conexion);

$matriz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;

if (!$matriz_id) {
    header('Location: matrices.php');
    exit;
}

$matriz = $matrizModel->obtenerPorId($matriz_id);

if (!$matriz) {
    header('Location: matrices.php');
    exit;
}

// Si no se especifica versión, usar la versión actual
if (!$version_id) {
    $version_id = (int)$matriz['version_id'];
}

$version = $versionModel->obtenerPorId($version_id);
if (!$version) {
    header('Location: matrices.php');
    exit;
}

// Obtener las filas de la matriz
$filas = $matrizCoherenciaModel->obtenerPorMatrizYVersion($matriz_id, $version_id);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Previsualizar Matriz - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .preview-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0861c4 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .preview-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .preview-header p {
            margin-bottom: 0;
            opacity: 0.95;
        }

        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .view-toggle .btn {
            flex: 1;
        }

        .row-card {
            background: white;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .row-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .row-card-header {
            background: #f8f9fa;
            padding: 1rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }

        .row-card-header:hover {
            background: #f0f1f3;
        }

        .row-card-number {
            background: #0d6efd;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .row-card-title {
            flex: 1;
            font-weight: 600;
            color: #212529;
            margin: 0;
            min-width: 0;
        }

        .row-card-summary {
            color: #6c757d;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-top: 0.3rem;
        }

        .row-card-toggle {
            color: #0d6efd;
            font-weight: 600;
            margin-left: 1rem;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .row-card.collapsed .row-card-toggle {
            transform: rotate(180deg);
        }

        .row-card-body {
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
            display: none;
        }

        .row-card.show .row-card-body {
            display: block;
        }

        .field-group {
            margin-bottom: 1.5rem;
        }

        .field-group:last-child {
            margin-bottom: 0;
        }

        .field-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
            display: block;
        }

        .field-value {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 4px;
            border-top: 3px solid #0d6efd;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
            color: #212529;
            font-size: 0.95rem;
            min-height: auto;
            display: block;
        }

        .field-value.empty {
            color: #adb5bd;
            font-style: italic;
            border-top-color: #dee2e6;
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .field-row:last-child {
            margin-bottom: 0;
        }

        /* Campos principales más amplios */
        .field-row.wide {
            grid-template-columns: 1fr;
        }

        .field-row.wide .field-group {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .field-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .row-card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .row-card-title {
                margin-top: 0.5rem;
            }
        }

        .matrix-table {
            background: white;
            border-radius: 8px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .matrix-table table {
            margin-bottom: 0;
            font-size: 0.9rem;
            border-collapse: collapse;
        }

        .matrix-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 0.75rem;
            vertical-align: middle;
            white-space: normal;
            text-align: left;
        }

        .matrix-table td {
            padding: 0.75rem;
            vertical-align: top;
            border-bottom: 1px solid #dee2e6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        .matrix-table tr:last-child td {
            border-bottom: none;
        }

        .row-number {
            background-color: #f8f9fa;
            font-weight: 600;
            width: 40px;
            min-width: 40px;
            text-align: center;
            white-space: nowrap;
        }

        .cell-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.4;
            font-size: 0.9rem;
        }

        /* Anchos de columnas similares a Excel */
        .matrix-table td:nth-child(2),
        .matrix-table th:nth-child(2) {
            width: 160px;
            min-width: 160px;
        }

        .matrix-table td:nth-child(3),
        .matrix-table th:nth-child(3) {
            width: 240px;
            min-width: 240px;
        }

        .matrix-table td:nth-child(4),
        .matrix-table th:nth-child(4) {
            width: 240px;
            min-width: 240px;
        }

        .matrix-table td:nth-child(5),
        .matrix-table th:nth-child(5) {
            width: 240px;
            min-width: 240px;
        }

        .matrix-table td:nth-child(6),
        .matrix-table th:nth-child(6) {
            width: 180px;
            min-width: 180px;
        }

        .matrix-table td:nth-child(7),
        .matrix-table th:nth-child(7) {
            width: 180px;
            min-width: 180px;
        }

        .matrix-table td:nth-child(8),
        .matrix-table th:nth-child(8) {
            width: 240px;
            min-width: 240px;
        }

        .matrix-table td:nth-child(9),
        .matrix-table th:nth-child(9) {
            width: 150px;
            min-width: 150px;
        }

        .matrix-table td:nth-child(10),
        .matrix-table th:nth-child(10) {
            width: 150px;
            min-width: 150px;
        }

        .matrix-table td:nth-child(11),
        .matrix-table th:nth-child(11) {
            width: 150px;
            min-width: 150px;
        }

        .matrix-table td:nth-child(12),
        .matrix-table th:nth-child(12) {
            width: 80px;
            min-width: 80px;
            text-align: center;
        }

        .empty-state {
            background: white;
            border-radius: 8px;
            padding: 3rem;
            text-align: center;
            color: #6c757d;
        }

        .info-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .info-section .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .info-section .info-value {
            color: #212529;
            font-size: 1.1rem;
        }

        .btn-group-action {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        #cards-view {
            display: none;
        }

        #cards-view.active {
            display: block;
        }

        #table-view {
            display: block;
        }

        #table-view.hidden {
            display: none;
        }

        @media print {
            body {
                background-color: white;
            }

            .preview-header {
                page-break-after: avoid;
            }

            .btn-group-action,
            .view-toggle {
                display: none;
            }

            .row-card-body {
                display: block !important;
            }

            .row-card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="preview-header">
        <div class="container">
            <h1><?php echo htmlspecialchars($matriz['nombre'] ?: ('Matriz #' . $matriz_id)); ?></h1>
            <p>Previsualización de Matriz de Coherencia Curricular</p>
        </div>
    </div>

    <div class="container">
        <div class="info-section">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="info-label">Versión:</div>
                    <div class="info-value"><?php echo htmlspecialchars($version['descripcion'] ?: ('Versión ' . (int)$version['numero_version'])); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Fecha de creación:</div>
                    <div class="info-value"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($version['fecha_creacion']))); ?></div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-label">Total de filas:</div>
                    <div class="info-value"><?php echo count($filas); ?> filas</div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group-action">

                        <a href="matrices.php?carrera_id=<?php echo urlencode($matriz['carrera_id']); ?>" class="btn btn-sm btn-secondary">
                            Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($filas)): ?>
            <!-- Vista Tarjetas / Tabla Toggle -->
            <div class="view-toggle">
                <button type="button" class="btn btn-outline-primary active" id="btn-cards-view" onclick="cambiarVista('cards')">
                    📋 Vista Detallada
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-table-view" onclick="cambiarVista('table')">
                    📊 Vista Tabla
                </button>
            </div>

            <!-- Vista en Tarjetas (Predeterminada) -->
            <div id="cards-view" class="active">
                <?php foreach ($filas as $index => $fila): ?>
                    <div class="row-card collapsed">
                        <div class="row-card-header" onclick="this.parentElement.classList.toggle('show'); this.parentElement.classList.toggle('collapsed');">
                            <div style="display: flex; align-items: flex-start; flex: 1; min-width: 0;">
                                <div class="row-card-number"><?php echo $index + 1; ?></div>
                                <div style="min-width: 0; flex: 1;">
                                    <div class="row-card-title">
                                        <?php
                                        if (!empty($fila['resultado_aprendizaje'])) {
                                            echo htmlspecialchars(substr($fila['resultado_aprendizaje'], 0, 60));
                                            if (strlen($fila['resultado_aprendizaje']) > 60) echo '...';
                                        } else {
                                            echo 'Sin resultado de aprendizaje';
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($fila['criterios_logro'])): ?>
                                        <div class="row-card-summary">
                                            <?php echo htmlspecialchars(substr($fila['criterios_logro'], 0, 100)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="row-card-toggle">▼</span>
                        </div>

                        <div class="row-card-body">
                            <!-- Primera fila: Actividad y SCT -->
                            <div class="field-row">
                                <div class="field-group">
                                    <span class="field-label">Actividad Curricular</span>
                                    <div class="field-value <?php echo empty($fila['asignatura_nombre']) ? 'empty' : ''; ?>"><?php echo !empty($fila['asignatura_nombre']) ? htmlspecialchars($fila['asignatura_nombre']) : 'No especificada'; ?></div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">SCT Chile</span>
                                    <div class="field-value <?php echo empty($fila['sct_chile']) ? 'empty' : ''; ?>"><?php echo !empty($fila['sct_chile']) ? htmlspecialchars($fila['sct_chile']) : 'N/A'; ?></div>
                                </div>
                            </div>

                            <!-- Segunda fila: Resultado de Aprendizaje (ancho completo) -->
                            <div class="field-row wide">
                                <div class="field-group">
                                    <span class="field-label">Resultado de Aprendizaje</span>
                                    <div class="field-value <?php echo empty($fila['resultado_aprendizaje']) ? 'empty' : ''; ?>"><?php echo !empty($fila['resultado_aprendizaje']) ? htmlspecialchars($fila['resultado_aprendizaje']) : 'No especificado'; ?></div>
                                </div>
                            </div>

                            <!-- Tercera fila: Criterios de Logro (ancho completo) -->
                            <div class="field-row wide">
                                <div class="field-group">
                                    <span class="field-label">Criterios de Logro</span>
                                    <div class="field-value <?php echo empty($fila['criterios_logro']) ? 'empty' : ''; ?>"><?php echo !empty($fila['criterios_logro']) ? htmlspecialchars($fila['criterios_logro']) : 'No especificados'; ?></div>
                                </div>
                            </div>

                            <!-- Cuarta fila: Dominio y Competencia -->
                            <div class="field-row">
                                <div class="field-group">
                                    <span class="field-label">Dominio</span>
                                    <div class="field-value <?php echo empty($fila['dominio']) && empty($fila['dominio_nombre']) && empty($fila['dominios_lista']) ? 'empty' : ''; ?>"><?php
                                        $textoDominio = '';
                                        if (!empty($fila['dominio'])) {
                                            $textoDominio = $fila['dominio'];
                                        } elseif (!empty($fila['dominios_lista'])) {
                                            $textoDominio = $fila['dominios_lista'];
                                        } elseif (!empty($fila['dominio_nombre'])) {
                                            $textoDominio = $fila['dominio_nombre'];
                                        }
                                        echo $textoDominio !== '' ? nl2br(htmlspecialchars($textoDominio)) : 'No especificado';
                                    ?></div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Competencia</span>
                                    <div class="field-value <?php echo empty($fila['competencia']) ? 'empty' : ''; ?>"><?php echo !empty($fila['competencia']) ? htmlspecialchars($fila['competencia']) : 'No especificada'; ?></div>
                                </div>
                            </div>

                            <!-- Quinta fila: Contenido/Saberes (ancho completo) -->
                            <div class="field-row wide">
                                <div class="field-group">
                                    <span class="field-label">Contenido/Saberes</span>
                                    <div class="field-value <?php echo empty($fila['contenidos']) ? 'empty' : ''; ?>"><?php echo !empty($fila['contenidos']) ? htmlspecialchars($fila['contenidos']) : 'No especificados'; ?></div>
                                </div>
                            </div>

                            <!-- Sexta fila: Bibliografía, Metodologías y Estrategias -->
                            <div class="field-row">
                                <div class="field-group">
                                    <span class="field-label">Bibliografía</span>
                                    <div class="field-value <?php echo empty($fila['bibliografia']) ? 'empty' : ''; ?>"><?php echo !empty($fila['bibliografia']) ? htmlspecialchars($fila['bibliografia']) : 'No especificada'; ?></div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Metodologías</span>
                                    <div class="field-value <?php echo empty($fila['metodologias']) ? 'empty' : ''; ?>"><?php echo !empty($fila['metodologias']) ? htmlspecialchars($fila['metodologias']) : 'No especificadas'; ?></div>
                                </div>
                            </div>

                            <!-- Séptima fila: Estrategias (ancho completo) -->
                            <div class="field-row wide">
                                <div class="field-group">
                                    <span class="field-label">Estrategias</span>
                                    <div class="field-value <?php echo empty($fila['estrategias']) ? 'empty' : ''; ?>"><?php echo !empty($fila['estrategias']) ? htmlspecialchars($fila['estrategias']) : 'No especificadas'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Vista en Tabla -->
            <div id="table-view" class="hidden">
                <div class="matrix-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="row-number">#</th>
                                <th>Actividad Curricular</th>
                                <th>Resultado de Aprendizaje</th>
                                <th>Criterios de Logro</th>
                                <th>Dominio</th>
                                <th>Competencia</th>
                                <th>Contenido/Saberes</th>
                                <th>Bibliografía</th>
                                <th>Metodologías</th>
                                <th>Estrategias</th>
                                <th>SCT Chile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $index => $fila): ?>
                                <tr>
                                    <td class="row-number"><?php echo $index + 1; ?></td>
                                    <td><?php echo !empty($fila['asignatura_nombre']) ? htmlspecialchars($fila['asignatura_nombre']) : ((!empty($fila['asignatura_id']) ? htmlspecialchars($fila['asignatura_id']) : '')); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($fila['resultado_aprendizaje'] ?? '')); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($fila['criterios_logro'] ?? '')); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars((($fila['dominio'] ?? '') !== '' ? $fila['dominio'] : (($fila['dominios_lista'] ?? '') !== '' ? $fila['dominios_lista'] : ($fila['dominio_nombre'] ?? ''))))); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($fila['competencia'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($fila['contenidos'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($fila['bibliografia'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($fila['metodologias'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($fila['estrategias'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($fila['sct_chile'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h5 class="mb-3">No hay filas registradas</h5>
                <p>Esta matriz de coherencia no contiene filas. Edítala para agregar contenido.</p>
            </div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <a href="matrices.php?carrera_id=<?php echo urlencode($matriz['carrera_id']); ?>" class="btn btn-secondary">
                Volver a la lista de matrices
            </a>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cambiarVista(vista) {
            const cardsView = document.getElementById('cards-view');
            const tableView = document.getElementById('table-view');
            const btnCards = document.getElementById('btn-cards-view');
            const btnTable = document.getElementById('btn-table-view');

            if (vista === 'cards') {
                cardsView.classList.add('active');
                cardsView.classList.remove('hidden');
                tableView.classList.remove('active');
                tableView.classList.add('hidden');
                btnCards.classList.add('active');
                btnCards.classList.remove('btn-outline-secondary');
                btnCards.classList.add('btn-outline-primary');
                btnTable.classList.remove('active');
                btnTable.classList.add('btn-outline-secondary');
                btnTable.classList.remove('btn-outline-primary');
                localStorage.setItem('previewVista', 'cards');
            } else if (vista === 'table') {
                cardsView.classList.remove('active');
                cardsView.classList.add('hidden');
                tableView.classList.add('active');
                tableView.classList.remove('hidden');
                btnCards.classList.remove('active');
                btnCards.classList.add('btn-outline-secondary');
                btnCards.classList.remove('btn-outline-primary');
                btnTable.classList.add('active');
                btnTable.classList.remove('btn-outline-secondary');
                btnTable.classList.add('btn-outline-primary');
                localStorage.setItem('previewVista', 'table');
            }
        }

        // Restaurar vista guardada al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const vistaGuardada = localStorage.getItem('previewVista') || 'cards';
            cambiarVista(vistaGuardada);
        });
    </script>
</body>

</html>