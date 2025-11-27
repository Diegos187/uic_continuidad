<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Matriz.php';
require_once '../../src/models/Asignatura.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';
verificarSesion();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isset($_GET['id'])) {
    header('Location: matrices.php');
    exit();
}

$matriz_id = (int)$_GET['id'];

$db = new Database();
$cn = $db->conectar();
$matrizCoherenciaModel = new MatrizCoherencia($cn);
$matrizModel = new Matriz($cn);
$asignaturaModel = new Asignatura($cn);

$matrizInfo = $matrizModel->obtenerPorId($matriz_id);
if (!$matrizInfo) {
    header('Location: matrices.php');
    exit();
}
$version_id = (int)($matrizInfo['version_id'] ?? 0);

// Obtener filas de coherencia de la matriz + versión
$sqlFilas = "SELECT mc.id as mc_id, mc.asignatura_id, mc.area_formacion_id, mc.perfil_egreso_id,
                    a.semestre, a.nombre AS asignatura_nombre
             FROM matrices_coherencia mc
             JOIN asignaturas a ON a.id = mc.asignatura_id
             WHERE mc.matriz_id = :mid AND mc.version_id = :vid
             ORDER BY a.semestre ASC, a.nombre ASC";
$stmtFilas = $cn->prepare($sqlFilas);
$stmtFilas->bindValue(':mid', $matriz_id, PDO::PARAM_INT);
$stmtFilas->bindValue(':vid', $version_id, PDO::PARAM_INT);
$stmtFilas->execute();
$filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

if (empty($filas)) {
    header('Location: matrices.php');
    exit();
}

// Agrupar filas por área de formación
$porArea = [];
foreach ($filas as $f) {
    $areaId = (int)($f['area_formacion_id'] ?? 0);
    if (!isset($porArea[$areaId])) $porArea[$areaId] = [];
    $porArea[$areaId][] = $f;
}

// Obtener criterios marcados por fila (matriz_tributacion)
$sqlCrit = "SELECT mt.matriz_coherencia_id, mt.criterio_logro_id
            FROM matriz_tributacion mt
            JOIN matrices_coherencia mc ON mc.id = mt.matriz_coherencia_id
            WHERE mc.matriz_id = :mid AND mc.version_id = :vid";
$stmtCrit = $cn->prepare($sqlCrit);
$stmtCrit->bindValue(':mid', $matriz_id, PDO::PARAM_INT);
$stmtCrit->bindValue(':vid', $version_id, PDO::PARAM_INT);
$stmtCrit->execute();
$criteriosMarcadosRaw = $stmtCrit->fetchAll(PDO::FETCH_ASSOC);
$criteriosMarcadosPorFila = [];
foreach ($criteriosMarcadosRaw as $r) {
    $mcid = (int)$r['matriz_coherencia_id'];
    if (!isset($criteriosMarcadosPorFila[$mcid])) $criteriosMarcadosPorFila[$mcid] = [];
    $criteriosMarcadosPorFila[$mcid][] = (int)$r['criterio_logro_id'];
}

// Helpers
function colLetter($index) { // 1-based -> letters
    $str = '';
    while ($index > 0) {
        $index--;
        $str = chr(65 + ($index % 26)) . $str;
        $index = intdiv($index, 26);
    }
    return $str;
}

$spreadsheet = new Spreadsheet();
$sheetIndex = 0;

foreach ($porArea as $areaId => $filasArea) {
    if ($sheetIndex === 0) {
        $sheet = $spreadsheet->getActiveSheet();
    } else {
        $sheet = $spreadsheet->createSheet($sheetIndex);
    }
    $sheetIndex++;

    // Datos del área (nombre y dominio)
    $areaNombre = '';
    $dominioTexto = '';
    $perfilId = null;
    foreach ($filasArea as $fa) { // tomar primera que tenga datos
        $perfilId = (int)($fa['perfil_egreso_id'] ?? 0);
        break;
    }
    if ($areaId > 0) {
        $stmtArea = $cn->prepare('SELECT nombre FROM areas_formacion WHERE id = :id LIMIT 1');
        $stmtArea->bindValue(':id', $areaId, PDO::PARAM_INT);
        $stmtArea->execute();
        $areaNombre = (string)($stmtArea->fetchColumn() ?: 'Área');
    } else {
        $areaNombre = 'Área (sin especificar)';
    }

    // Obtener detalle perfil_egreso_detalle para dominio
    $detalleId = null;
    if ($perfilId) {
        $stmtDet = $cn->prepare('SELECT id, dominio FROM perfiles_egreso_detalle WHERE perfil_egreso_id = :pid AND area_formacion_id = :aid LIMIT 1');
        $stmtDet->bindValue(':pid', $perfilId, PDO::PARAM_INT);
        $stmtDet->bindValue(':aid', $areaId, PDO::PARAM_INT);
        $stmtDet->execute();
        $rowDet = $stmtDet->fetch(PDO::FETCH_ASSOC);
        if ($rowDet) {
            $detalleId = (int)$rowDet['id'];
            $dominioTexto = $rowDet['dominio'] ?? '';
        }
    }

    // Estructura jerárquica competencias->resultados->criterios
    $estructura = [];
    if ($detalleId) {
        $sqlTree = "SELECT c.id AS competencia_id, c.codigo AS competencia_codigo, c.descripcion AS competencia_descripcion,
                           ra.id AS resultado_id, ra.codigo AS resultado_codigo, ra.descripcion AS resultado_descripcion,
                           cl.id AS criterio_id, cl.codigo AS criterio_codigo, cl.descripcion AS criterio_descripcion
                    FROM competencias_dominio c
                    JOIN resultados_aprendizaje_ref ra ON ra.competencia_dominio_id = c.id
                    JOIN criterios_logro_ref cl ON cl.resultado_aprendizaje_id = ra.id
                    WHERE c.perfil_egreso_detalle_id = :det
                    ORDER BY c.orden, ra.orden, cl.orden";
        $stmtTree = $cn->prepare($sqlTree);
        $stmtTree->bindValue(':det', $detalleId, PDO::PARAM_INT);
        $stmtTree->execute();
        $rowsTree = $stmtTree->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsTree as $rt) {
            $cid = (int)$rt['competencia_id'];
            $rid = (int)$rt['resultado_id'];
            $critid = (int)$rt['criterio_id'];
            if (!isset($estructura[$cid])) {
                $estructura[$cid] = [
                    'codigo' => $rt['competencia_codigo'],
                    'descripcion' => $rt['competencia_descripcion'],
                    'resultados' => []
                ];
            }
            if (!isset($estructura[$cid]['resultados'][$rid])) {
                $estructura[$cid]['resultados'][$rid] = [
                    'codigo' => $rt['resultado_codigo'],
                    'descripcion' => $rt['resultado_descripcion'],
                    'criterios' => []
                ];
            }
            $estructura[$cid]['resultados'][$rid]['criterios'][$critid] = [
                'codigo' => $rt['criterio_codigo'],
                'descripcion' => $rt['criterio_descripcion']
            ];
        }
    }

    // Sanitizar título de la hoja (Excel no permite: \\/:*?[] ni >31 chars)
    $rawTitle = $areaNombre !== '' ? $areaNombre : 'Area';
    // Reemplazar caracteres prohibidos por '-'
    $safeTitle = preg_replace('/[\\\\\/\:\*\?\[\]]/', '-', $rawTitle);
    // Quitar dobles guiones consecutivos
    $safeTitle = preg_replace('/-+/', '-', $safeTitle);
    $safeTitle = trim($safeTitle, '- ');
    // Limitar longitud a 25 para dejar margen si agregamos sufijo
    if (mb_strlen($safeTitle) > 25) {
        $safeTitle = mb_substr($safeTitle, 0, 25);
    }
    // Evitar títulos duplicados
    $finalTitle = $safeTitle;
    // Si ya existe una hoja con ese título, añadir índice
    foreach ($spreadsheet->getWorksheetIterator() as $ws) {
        if ($ws->getTitle() === $finalTitle) {
            $finalTitle = $safeTitle . '-' . $sheetIndex;
            break;
        }
    }
    // Garantizar longitud final <=31
    if (mb_strlen($finalTitle) > 31) {
        $finalTitle = mb_substr($finalTitle, 0, 31);
    }
    $sheet->setTitle($finalTitle);

    // Encabezado principal
    $sheet->setCellValue('A1', 'MATRIZ DE TRIBUTACIÓN - ' . $areaNombre);
    if ($dominioTexto) {
        $sheet->setCellValue('A2', 'Dominio: ' . $dominioTexto);
    }

    // Columnas base
    $sheet->setCellValue('A3', 'Semestre');
    $sheet->setCellValue('B3', 'Actividad Curricular');

    // Construir mapping criterio -> columna
    $colIndex = 3; // C
    $criterioColMap = []; // criterio_id => colIndex
    $competenciaRanges = []; // cid => [start,end]
    $resultadoRanges = []; // rid => [start,end]

    foreach ($estructura as $cid => $comp) {
        $startComp = $colIndex;
        foreach ($comp['resultados'] as $rid => $res) {
            $startRes = $colIndex;
            foreach ($res['criterios'] as $critid => $crit) {
                $criterioColMap[$critid] = $colIndex;
                $sheet->setCellValue(colLetter($colIndex) . '5', $crit['codigo']);
                $colIndex++;
            }
            $resultadoRanges[$rid] = [$startRes, $colIndex - 1];
            // Merged cell for resultado (row4)
            $sheet->mergeCells(colLetter($startRes) . '4:' . colLetter($colIndex - 1) . '4');
            $sheet->setCellValue(colLetter($startRes) . '4', $res['codigo']);
        }
        $competenciaRanges[$cid] = [$startComp, $colIndex - 1];
        // Merged cell for competencia (row3)
        $sheet->mergeCells(colLetter($startComp) . '3:' . colLetter($colIndex - 1) . '3');
        $sheet->setCellValue(colLetter($startComp) . '3', $comp['codigo']);
    }

    // Estilos encabezado multi-nivel
    $maxColLetter = colLetter($colIndex - 1);
    $sheet->getStyle('A3:' . $maxColLetter . '5')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E9EEF5']]
    ]);

    // Ajustar ancho base y criterios
    $sheet->getColumnDimension('A')->setWidth(11);
    $sheet->getColumnDimension('B')->setWidth(28);
    for ($c = 3; $c < $colIndex; $c++) {
        $sheet->getColumnDimension(colLetter($c))->setWidth(4.2); // criterio grid
    }

    // Fila datos comienza en row6
    $row = 6;
    foreach ($filasArea as $fa) {
        $sheet->setCellValue('A' . $row, $fa['semestre']);
        $sheet->setCellValue('B' . $row, $fa['asignatura_nombre']);
        $mcid = (int)$fa['mc_id'];
        $marcados = $criteriosMarcadosPorFila[$mcid] ?? [];
        foreach ($marcados as $critid) {
            if (isset($criterioColMap[$critid])) {
                $colL = colLetter($criterioColMap[$critid]);
                $sheet->setCellValue($colL . $row, 'X');
            }
        }
        // Estilo fila
        $sheet->getStyle('A' . $row . ':' . $maxColLetter . $row)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row++;
    }

    // Congelar panel para cabecera
    $sheet->freezePane('C6');
}

$writer = new Xlsx($spreadsheet);
$filename = 'Matriz_Tributacion_' . preg_replace('/[^A-Za-z0-9]/','_', ($matrizInfo['nombre'] ?? 'matriz')) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save('php://output');
exit();
