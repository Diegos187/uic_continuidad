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
$sqlFilas = "SELECT mc.id as mc_id, mc.asignatura_id, mc.area_formacion_id, mc.perfil_egreso_id, mc.perfil_egreso_detalle_id,
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

// Preparar una sola hoja con todas las áreas lado a lado
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Matriz');

// Encabezado principal
$sheet->setCellValue('A1', 'MATRIZ DE TRIBUTACIÓN');
$sheet->setCellValue('A3', 'Semestre');
$sheet->setCellValue('B3', 'Actividad Curricular');

// Construir estructuras por área: nombre, dominios, árbol y mapas de columnas
$areasInfo = []; // areaId => ['nombre','perfilId','dominios'=>[detId=>name],'estructura'=>[...],'colStart'=>int,'colEnd'=>int,'critColMap'=>[]]
$colIndex = 3; // C
foreach ($porArea as $areaId => $filasArea) {
    // Nombre área y perfil
    $areaNombre = '';
    $perfilId = null;
    foreach ($filasArea as $fa) { $perfilId = (int)($fa['perfil_egreso_id'] ?? 0); break; }
    if ($areaId > 0) {
        $stmtArea = $cn->prepare('SELECT nombre FROM areas_formacion WHERE id = :id LIMIT 1');
        $stmtArea->bindValue(':id', $areaId, PDO::PARAM_INT);
        $stmtArea->execute();
        $areaNombre = (string)($stmtArea->fetchColumn() ?: 'Área');
    } else { $areaNombre = 'Área (sin especificar)'; }

    // Dominios por área
    $dominios = [];
    if ($perfilId) {
        $stmtDom = $cn->prepare('SELECT id, dominio FROM perfiles_egreso_detalle WHERE perfil_egreso_id = :pid AND area_formacion_id = :aid ORDER BY id');
        $stmtDom->bindValue(':pid', $perfilId, PDO::PARAM_INT);
        $stmtDom->bindValue(':aid', $areaId, PDO::PARAM_INT);
        $stmtDom->execute();
        foreach ($stmtDom->fetchAll(PDO::FETCH_ASSOC) as $d) { $dominios[(int)$d['id']] = $d['dominio'] ?? 'Dominio'; }
    }

    // Estructura árbol por dominio
    $estructuraPorDominio = [];
    foreach (array_keys($dominios) as $detalleId) {
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
        $estructura = [];
        foreach ($rowsTree as $rt) {
            $cid = (int)$rt['competencia_id'];
            $rid = (int)$rt['resultado_id'];
            $critid = (int)$rt['criterio_id'];
            if (!isset($estructura[$cid])) { $estructura[$cid] = ['codigo'=>$rt['competencia_codigo'],'descripcion'=>$rt['competencia_descripcion'],'resultados'=>[]]; }
            if (!isset($estructura[$cid]['resultados'][$rid])) { $estructura[$cid]['resultados'][$rid] = ['codigo'=>$rt['resultado_codigo'],'descripcion'=>$rt['resultado_descripcion'],'criterios'=>[]]; }
            $estructura[$cid]['resultados'][$rid]['criterios'][$critid] = ['codigo'=>$rt['criterio_codigo'],'descripcion'=>$rt['criterio_descripcion']];
        }
        $estructuraPorDominio[$detalleId] = $estructura;
    }

    // Construir columnas agrupadas y mapas
    $critColMap = []; // crit_id => abs col
    $areaStart = $colIndex;
    foreach ($estructuraPorDominio as $detId => $estructura) {
        $startDom = $colIndex;
        foreach ($estructura as $cid => $comp) {
            $startComp = $colIndex;
            foreach ($comp['resultados'] as $rid => $res) {
                $startRes = $colIndex;
                foreach ($res['criterios'] as $critid => $crit) {
                    $critColMap[$critid] = $colIndex;
                    $sheet->setCellValue(colLetter($colIndex) . '6', $crit['codigo']);
                    $colIndex++;
                }
                if ($colIndex - 1 >= $startRes) {
                    $sheet->mergeCells(colLetter($startRes) . '5:' . colLetter($colIndex - 1) . '5');
                    $sheet->setCellValue(colLetter($startRes) . '5', $res['codigo']);
                }
            }
            if ($colIndex - 1 >= $startComp) {
                $sheet->mergeCells(colLetter($startComp) . '4:' . colLetter($colIndex - 1) . '4');
                $sheet->setCellValue(colLetter($startComp) . '4', $comp['codigo']);
            }
        }
        if ($colIndex - 1 >= $startDom) {
            $sheet->mergeCells(colLetter($startDom) . '3:' . colLetter($colIndex - 1) . '3');
            $sheet->setCellValue(colLetter($startDom) . '3', $dominios[$detId] ?? 'Dominio');
        }
    }
    $areaEnd = $colIndex - 1;
    // Área en fila 2 sobre sus columnas
    if ($areaEnd >= $areaStart) {
        $sheet->mergeCells(colLetter($areaStart) . '2:' . colLetter($areaEnd) . '2');
        $sheet->setCellValue(colLetter($areaStart) . '2', $areaNombre);
    }

    $areasInfo[$areaId] = [
        'nombre' => $areaNombre,
        'perfilId' => $perfilId,
        'dominios' => $dominios,
        'estructura' => $estructuraPorDominio,
        'colStart' => $areaStart,
        'colEnd' => $areaEnd,
        'critColMap' => $critColMap
    ];
}

// Estilos encabezado multi-nivel global
$maxColLetter = colLetter(max(3, $colIndex - 1));
$sheet->getStyle('A3:' . $maxColLetter . '6')->applyFromArray([
    'font' => ['bold' => true, 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E9EEF5']]
]);
// Dar estilo fila 2 nombres de áreas
foreach ($areasInfo as $ai) {
    if ($ai['colEnd'] >= $ai['colStart']) {
        $sheet->getStyle(colLetter($ai['colStart']) . '2:' . colLetter($ai['colEnd']) . '2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E9EEF5']]
        ]);
    }
}

// Ajustar anchos
$sheet->getColumnDimension('A')->setWidth(11);
$sheet->getColumnDimension('B')->setWidth(28);
for ($c = 3; $c < $colIndex; $c++) { $sheet->getColumnDimension(colLetter($c))->setWidth(4.2); }

// Render de filas: todas las asignaturas independientemente del área
$row = 7;
foreach ($filas as $fa) {
    $sheet->setCellValue('A' . $row, $fa['semestre']);
    $sheet->setCellValue('B' . $row, $fa['asignatura_nombre']);
    $mcid = (int)$fa['mc_id'];
    $areaId = (int)$fa['area_formacion_id'];
    $marcados = $criteriosMarcadosPorFila[$mcid] ?? [];
    $ai = $areasInfo[$areaId] ?? null;
    if ($ai) {
        foreach ($marcados as $critid) {
            if (isset($ai['critColMap'][$critid])) {
                $colL = colLetter($ai['critColMap'][$critid]);
                $sheet->setCellValue($colL . $row, 'X');
            }
        }
    }
    $sheet->getStyle('A' . $row . ':' . $maxColLetter . $row)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $row++;
}

// Congelar cabecera
$sheet->freezePane('C7');

$writer = new Xlsx($spreadsheet);
$filename = 'Matriz_Tributacion_' . preg_replace('/[^A-Za-z0-9]/','_', ($matrizInfo['nombre'] ?? 'matriz')) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save('php://output');
exit();
