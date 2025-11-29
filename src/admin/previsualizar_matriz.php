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
// Construir estructura jerárquica por dominios para cada fila: Dominio → Competencia → RA → CL
$detallesPorFila = [];
foreach ($filas as $f) {
    $mcid = (int)($f['id'] ?? 0);
    if ($mcid <= 0) { continue; }
    $sqlDetalle = "SELECT ped.id AS dominio_id, ped.dominio AS dominio_nombre,
                           c.id AS comp_id, c.codigo AS comp_codigo, c.descripcion AS comp_desc,
                           ra.id AS ra_id, ra.codigo AS ra_codigo, ra.descripcion AS ra_desc,
                           cl.id AS cl_id, cl.codigo AS cl_codigo, cl.descripcion AS cl_desc
                    FROM matriz_tributacion mt
                    JOIN criterios_logro_ref cl ON cl.id = mt.criterio_logro_id
                    JOIN resultados_aprendizaje_ref ra ON ra.id = cl.resultado_aprendizaje_id
                    JOIN competencias_dominio c ON c.id = ra.competencia_dominio_id
                    JOIN perfiles_egreso_detalle ped ON ped.id = c.perfil_egreso_detalle_id
                    WHERE mt.matriz_coherencia_id = :mcid
                    ORDER BY ped.id, c.orden, ra.orden, cl.orden";
    $stmtDet = $conexion->prepare($sqlDetalle);
    $stmtDet->bindValue(':mcid', $mcid, PDO::PARAM_INT);
    if ($stmtDet->execute()) {
        $rowsDet = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
        $byDomain = [];
        foreach ($rowsDet as $rd) {
            $dId = (int)$rd['dominio_id'];
            $cId = (int)$rd['comp_id'];
            $rId = (int)$rd['ra_id'];
            $clId = (int)$rd['cl_id'];
            
            if (!isset($byDomain[$dId])) {
                $byDomain[$dId] = [
                    'nombre' => $rd['dominio_nombre'] ?? 'Dominio',
                    'competencias' => []
                ];
            }
            if (!isset($byDomain[$dId]['competencias'][$cId])) {
                $byDomain[$dId]['competencias'][$cId] = [
                    'codigo' => $rd['comp_codigo'],
                    'descripcion' => $rd['comp_desc'],
                    'resultados' => []
                ];
            }
            if (!isset($byDomain[$dId]['competencias'][$cId]['resultados'][$rId])) {
                $byDomain[$dId]['competencias'][$cId]['resultados'][$rId] = [
                    'codigo' => $rd['ra_codigo'],
                    'descripcion' => $rd['ra_desc'],
                    'criterios' => []
                ];
            }
            $byDomain[$dId]['competencias'][$cId]['resultados'][$rId]['criterios'][$clId] = [
                'codigo' => $rd['cl_codigo'],
                'descripcion' => $rd['cl_desc']
            ];
        }
        $detallesPorFila[$mcid] = $byDomain;
    }
}
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
<?php
// Helper para decodificar entidades al renderizar y escapar para HTML
function dec($s) { return is_string($s) ? html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $s; }
function safe($s) { return htmlspecialchars(dec($s), ENT_QUOTES, 'UTF-8'); }
?>

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
            width: 240px;
            min-width: 240px;
        }

        .matrix-table td:nth-child(3),
        .matrix-table th:nth-child(3) {
            width: 380px;
            min-width: 380px;
        }

        .matrix-table td:nth-child(4),
        .matrix-table th:nth-child(4) {
            width: 380px;
            min-width: 380px;
        }

        .matrix-table td:nth-child(5),
        .matrix-table th:nth-child(5) {
            width: 280px;
            min-width: 280px;
        }

        .matrix-table td:nth-child(6),
        .matrix-table th:nth-child(6) {
            width: 300px;
            min-width: 300px;
        }

        .matrix-table td:nth-child(7),
        .matrix-table th:nth-child(7) {
            width: 300px;
            min-width: 300px;
        }

        .matrix-table td:nth-child(8),
        .matrix-table th:nth-child(8) {
            width: 300px;
            min-width: 300px;
        }

        .matrix-table td:nth-child(9),
        .matrix-table th:nth-child(9) {
            width: 220px;
            min-width: 220px;
        }

        .matrix-table td:nth-child(10),
        .matrix-table th:nth-child(10) {
            width: 220px;
            min-width: 220px;
        }

        .matrix-table td:nth-child(11),
        .matrix-table th:nth-child(11) {
            width: 160px;
            min-width: 160px;
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

        /* Estilos para jerarquía en vista detallada */
        .hierarchy-container {
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .hierarchy-competencia {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            border-radius: 4px;
        }

        .hierarchy-competencia-header {
            color: #0d6efd;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0d6efd;
        }

        .hierarchy-resultado {
            margin-bottom: 1rem;
            padding: 0.75rem;
            margin-left: 1rem;
            background-color: white;
            border-left: 3px solid #495057;
            border-radius: 3px;
        }

        .hierarchy-resultado-header {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .hierarchy-criterio {
            margin-left: 1.5rem;
            margin-bottom: 0.4rem;
            padding: 0.4rem 0.5rem;
            color: #6c757d;
            border-left: 2px solid #dee2e6;
            padding-left: 0.75rem;
            font-size: 0.85rem;
        }

        .hierarchy-criterio-code {
            color: #6c757d;
            font-weight: 600;
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
            <h1><?php echo htmlspecialchars($matriz['nombre'] ?: ('Matriz #' . $matriz_id), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Previsualización de Matriz de Coherencia Curricular</p>
        </div>
    </div>

    <div class="container">
        <div class="info-section">
            <div class="row mb-3 align-items-center">
                <div class="col-md-6">
                    <div class="info-label">Versión:</div>
                    <div class="info-value"><?php echo htmlspecialchars($version['descripcion'] ?: ('Versión ' . (int)$version['numero_version']), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="info-label">Fecha de creación:</div>
                            <div class="info-value"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($version['fecha_creacion']))); ?></div>
                        </div>
                        <div class="text-end">
                            <a href="matrices.php?carrera_id=<?php echo urlencode($matriz['carrera_id']); ?>" class="btn btn-lm btn-secondary">Volver</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-label">Total de filas:</div>
                    <div class="info-value"><?php echo count($filas); ?> filas</div>
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
                                            // Resumen de cabecera: Fila X — Área | primer Dominio | Actividad
                                            $areaTxt = isset($fila['area_formacion_nombre']) && $fila['area_formacion_nombre'] !== ''
                                                ? $fila['area_formacion_nombre'] : 'Área no especificada';
                                            $mcid_local = (int)($fila['id'] ?? 0);
                                            $groups_local = $detallesPorFila[$mcid_local] ?? [];
                                            $primerDominio = '';
                                            if (!empty($groups_local)) {
                                                $keys = array_keys($groups_local);
                                                $firstKey = reset($keys);
                                                if ($firstKey !== false && isset($groups_local[$firstKey]['nombre'])) {
                                                    $primerDominio = $groups_local[$firstKey]['nombre'];
                                                }
                                            }
                                            if ($primerDominio === '' && !empty($fila['dominio'])) {
                                                $primerDominio = $fila['dominio'];
                                            }
                                            if ($primerDominio === '') {
                                                $primerDominio = 'Dominio no especificado';
                                            }
                                            $actividadTxt = !empty($fila['asignatura_nombre'])
                                                ? $fila['asignatura_nombre']
                                                : (!empty($fila['asignatura_id']) ? ('ID ' . $fila['asignatura_id']) : 'Actividad no especificada');

                                            $trunc = function($t, $max=60){ $t = (string)$t; return strlen($t) > $max ? substr($t, 0, $max-1) . '…' : $t; };
                                            $areaTxt = $trunc($areaTxt, 50);
                                            $primerDominio = $trunc($primerDominio, 50);
                                            $actividadTxt = $trunc($actividadTxt, 50);

                                            echo 'Fila ' . ($index + 1) . ' — ' . htmlspecialchars($areaTxt) . ' | ' . htmlspecialchars($primerDominio) . ' | ' . htmlspecialchars($actividadTxt);
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <span class="row-card-toggle">▼</span>
                        </div>

                        <div class="row-card-body">
                            <?php
                            $mcid = (int)($fila['id'] ?? 0);
                            $groups = $detallesPorFila[$mcid] ?? null;
                            ?>

                            <!-- Fila fija: Actividad y SCT -->
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

                            <?php if ($groups && count($groups) > 0): ?>
                                <!-- Pestañas por Dominio -->
                                <?php $tabId = 'tabs-' . $mcid . '-' . ($index + 1); ?>
                                <ul class="nav nav-tabs" id="<?php echo $tabId; ?>" role="tablist" style="margin-bottom: 1rem;">
                                    <?php $first = true; foreach ($groups as $domId => $g): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?php echo $first ? 'active' : ''; ?>" id="<?php echo $tabId . '-dom-' . $domId; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $tabId . '-dom-' . $domId; ?>" type="button" role="tab" aria-controls="<?php echo $tabId . '-dom-' . $domId; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                                <?php echo htmlspecialchars($g['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        </li>
                                    <?php $first = false; endforeach; ?>
                                </ul>
                                <div class="tab-content" id="<?php echo $tabId; ?>-content">
                                    <?php $firstPane = true; foreach ($groups as $domId => $g): ?>
                                        <div class="tab-pane fade <?php echo $firstPane ? 'show active' : ''; ?>" id="<?php echo $tabId . '-dom-' . $domId; ?>" role="tabpanel" aria-labelledby="<?php echo $tabId . '-dom-' . $domId; ?>-tab">
                                            <?php if (!empty($g['competencias'])): ?>
                                                <div class="hierarchy-container">
                                                    <?php foreach ($g['competencias'] as $cId => $comp): ?>
                                                        <div class="hierarchy-competencia">
                                                            <div class="hierarchy-competencia-header">
                                                                <?php echo htmlspecialchars($comp['codigo'] . ' - ' . $comp['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                                                            </div>
                                                            <?php if (!empty($comp['resultados'])): ?>
                                                                <?php foreach ($comp['resultados'] as $rId => $ra): ?>
                                                                    <div class="hierarchy-resultado">
                                                                        <div class="hierarchy-resultado-header">
                                                                            <?php echo htmlspecialchars($ra['codigo'] . ' - ' . $ra['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                                                                        </div>
                                                                        <?php if (!empty($ra['criterios'])): ?>
                                                                            <?php foreach ($ra['criterios'] as $clId => $cl): ?>
                                                                                <div class="hierarchy-criterio">
                                                                                    <span class="hierarchy-criterio-code"><?php echo htmlspecialchars($cl['codigo'], ENT_QUOTES, 'UTF-8'); ?>:</span> <?php echo htmlspecialchars($cl['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php $firstPane = false; endforeach; ?>
                                </div>

                                <!-- Campos comunes -->
                                <div class="field-row wide">
                                    <div class="field-group">
                                        <span class="field-label">Contenido/Saberes</span>
                                        <div class="field-value <?php echo empty($fila['contenidos']) ? 'empty' : ''; ?>"><?php echo !empty($fila['contenidos']) ? htmlspecialchars($fila['contenidos']) : 'No especificados'; ?></div>
                                    </div>
                                </div>
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
                                <div class="field-row wide">
                                    <div class="field-group">
                                        <span class="field-label">Estrategias</span>
                                        <div class="field-value <?php echo empty($fila['estrategias']) ? 'empty' : ''; ?>"><?php echo !empty($fila['estrategias']) ? htmlspecialchars($fila['estrategias']) : 'No especificadas'; ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Fallback: layout original cuando no hay grupos por dominio -->
                                <div class="field-row wide">
                                    <div class="field-group">
                                        <span class="field-label">Resultado de Aprendizaje</span>
                                        <div class="field-value <?php echo empty($fila['resultado_aprendizaje']) ? 'empty' : ''; ?>"><?php echo !empty($fila['resultado_aprendizaje']) ? htmlspecialchars($fila['resultado_aprendizaje']) : 'No especificado'; ?></div>
                                    </div>
                                </div>
                                <div class="field-row wide">
                                    <div class="field-group">
                                        <span class="field-label">Criterios de Logro</span>
                                        <div class="field-value <?php echo empty($fila['criterios_logro']) ? 'empty' : ''; ?>"><?php echo !empty($fila['criterios_logro']) ? htmlspecialchars($fila['criterios_logro']) : 'No especificados'; ?></div>
                                    </div>
                                </div>
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
                                <div class="field-row wide">
                                    <div class="field-group">
                                        <span class="field-label">Contenido/Saberes</span>
                                        <div class="field-value <?php echo empty($fila['contenidos']) ? 'empty' : ''; ?>"><?php echo !empty($fila['contenidos']) ? htmlspecialchars($fila['contenidos']) : 'No especificados'; ?></div>
                                    </div>
                                </div>
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
                                <div class="field-row wide">
                                    <div class="field-group">
                                        <span class="field-label">Estrategias</span>
                                        <div class="field-value <?php echo empty($fila['estrategias']) ? 'empty' : ''; ?>"><?php echo !empty($fila['estrategias']) ? htmlspecialchars($fila['estrategias']) : 'No especificadas'; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
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
                                <th>ÁREA DE FORMACIÓN</th>
                                <th>DOMINIO</th>
                                <th>COMPETENCIA</th>
                                <th>RESULTADOS DE APRENDIZAJE</th>
                                <th>CRITERIOS DE LOGRO</th>
                                <th>CONTENIDOS/SABERES</th>
                                <th>ACTIVIDAD CURRICULAR</th>
                                <th>SCT-CHILE</th>
                                <th>METODOLOGÍAS ACTIVAS CENTRADAS EN EL ESTUDIANTADO</th>
                                <th>ESTRATEGIAS DE EVALUACIÓN</th>
                                <th>BIBLIOGRAFÍA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $index => $fila): ?>
                                <tr>
                                    <!-- Columna 1: # (Row Number) -->
                                    <td class="row-number"><?php echo $index + 1; ?></td>
                                    
                                    <!-- Columna 2: ÁREA DE FORMACIÓN -->
                                    <td><?php echo htmlspecialchars($fila['area_formacion_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    
                                    <!-- Columna 3: DOMINIO -->
                                    <td>
                                        <?php
                                        $mcid = (int)($fila['id'] ?? 0);
                                        $groups = $detallesPorFila[$mcid] ?? null;
                                        if ($groups) {
                                            echo nl2br(htmlspecialchars(implode("\n\n", array_map(function($g){return $g['nombre'];}, $groups))));
                                        } else {
                                            echo nl2br(htmlspecialchars((($fila['dominio'] ?? '') !== '' ? $fila['dominio'] : (($fila['dominios_lista'] ?? '') !== '' ? $fila['dominios_lista'] : ($fila['dominio_nombre'] ?? '')))));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Columna 4: COMPETENCIA -->
                                    <td>
                                        <?php
                                        $mcid = (int)($fila['id'] ?? 0);
                                        $groups = $detallesPorFila[$mcid] ?? null;
                                        if ($groups) {
                                            foreach ($groups as $g) {
                                                if (!empty($g['competencias'])) {
                                                    echo '<strong style="color: #0d6efd; display: block; margin-bottom: 0.5rem;">[' . htmlspecialchars($g['nombre']) . ']</strong>';
                                                    foreach ($g['competencias'] as $cId => $comp) {
                                                        echo '<div style="margin-bottom: 0.5rem; padding-left: 0.5rem; border-left: 2px solid #0d6efd;">';
                                                        echo htmlspecialchars($comp['codigo'] . ' - ' . $comp['descripcion']);
                                                        echo '</div>';
                                                    }
                                                }
                                            }
                                        } else {
                                            echo nl2br(htmlspecialchars($fila['competencia'] ?? ''));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Columna 5: RESULTADOS DE APRENDIZAJE -->
                                    <td>
                                        <?php
                                        $mcid = (int)($fila['id'] ?? 0);
                                        $groups = $detallesPorFila[$mcid] ?? null;
                                        if ($groups) {
                                            foreach ($groups as $g) {
                                                if (!empty($g['competencias'])) {
                                                    echo '<strong style="color: #0d6efd; display: block; margin-bottom: 0.5rem;">[' . htmlspecialchars($g['nombre']) . ']</strong>';
                                                    foreach ($g['competencias'] as $cId => $comp) {
                                                        echo '<div style="margin-bottom: 0.5rem; padding-left: 0.5rem; border-left: 2px solid #0d6efd;">';
                                                        echo '<div style="font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 0.3rem;">' . htmlspecialchars($comp['codigo']) . ':</div>';
                                                        if (!empty($comp['resultados'])) {
                                                            foreach ($comp['resultados'] as $rId => $ra) {
                                                                echo '<div style="margin-left: 0.5rem; margin-bottom: 0.25rem;">';
                                                                echo htmlspecialchars($ra['codigo'] . ' - ' . $ra['descripcion']);
                                                                echo '</div>';
                                                            }
                                                        }
                                                        echo '</div>';
                                                    }
                                                }
                                            }
                                        } else {
                                            echo nl2br(htmlspecialchars($fila['resultado_aprendizaje'] ?? ''));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Columna 6: CRITERIOS DE LOGRO -->
                                    <td>
                                        <?php
                                        $mcid = (int)($fila['id'] ?? 0);
                                        $groups = $detallesPorFila[$mcid] ?? null;
                                        if ($groups) {
                                            foreach ($groups as $g) {
                                                if (!empty($g['competencias'])) {
                                                    echo '<strong style="color: #0d6efd; display: block; margin-bottom: 0.5rem;">[' . htmlspecialchars($g['nombre']) . ']</strong>';
                                                    foreach ($g['competencias'] as $cId => $comp) {
                                                        echo '<div style="margin-bottom: 0.5rem; padding-left: 0.5rem; border-left: 2px solid #0d6efd;">';
                                                        echo '<div style="font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 0.3rem;">' . htmlspecialchars($comp['codigo']) . ':</div>';
                                                        if (!empty($comp['resultados'])) {
                                                            foreach ($comp['resultados'] as $rId => $ra) {
                                                                echo '<div style="margin-left: 0.5rem; margin-bottom: 0.3rem;">';
                                                                echo '<div style="font-size: 0.8rem; font-weight: 600; color: #666; margin-bottom: 0.15rem;">' . htmlspecialchars($ra['codigo']) . ':</div>';
                                                                if (!empty($ra['criterios'])) {
                                                                    foreach ($ra['criterios'] as $clId => $cl) {
                                                                        echo '<div style="margin-left: 0.5rem; font-size: 0.8rem; color: #6c757d; margin-bottom: 0.1rem;">';
                                                                        echo '• ' . htmlspecialchars($cl['codigo'] . ' - ' . $cl['descripcion']);
                                                                        echo '</div>';
                                                                    }
                                                                }
                                                                echo '</div>';
                                                            }
                                                        }
                                                        echo '</div>';
                                                    }
                                                }
                                            }
                                        } else {
                                            echo nl2br(htmlspecialchars($fila['criterios_logro'] ?? ''));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Columna 7: CONTENIDOS/SABERES -->
                                    <td><?php echo htmlspecialchars($fila['contenidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    
                                    <!-- Columna 8: ACTIVIDAD CURRICULAR -->
                                    <td><?php echo !empty($fila['asignatura_nombre']) ? htmlspecialchars($fila['asignatura_nombre']) : ((!empty($fila['asignatura_id']) ? htmlspecialchars($fila['asignatura_id']) : '')); ?></td>
                                    
                                    <!-- Columna 9: SCT-CHILE -->
                                    <td><?php echo htmlspecialchars($fila['sct_chile'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    
                                    <!-- Columna 10: METODOLOGÍAS ACTIVAS CENTRADAS EN EL ESTUDIANTADO -->
                                    <td><?php echo htmlspecialchars($fila['metodologias'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    
                                    <!-- Columna 11: ESTRATEGIAS DE EVALUACIÓN -->
                                    <td><?php echo htmlspecialchars($fila['estrategias'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    
                                    <!-- Columna 12: BIBLIOGRAFÍA -->
                                    <td><?php echo htmlspecialchars($fila['bibliografia'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
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