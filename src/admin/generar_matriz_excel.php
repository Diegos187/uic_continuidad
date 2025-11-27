<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/Matriz.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';
verificarSesion();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_GET['id'])) {
    header('Location: mallas.php');
    exit();
}

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);
$asignatura = new Asignatura($conexion);
$carreraModel = new Carrera($conexion);
$matrizModel = new Matriz($conexion);

// Obtener datos de la matriz seleccionada y su carrera
$matriz_id = (int)($_GET['id']);
$matrizInfo = $matrizModel->obtenerPorId($matriz_id);

// Si no existe la matriz, redirigir
if (!$matrizInfo) {
    header('Location: matrices.php');
    exit();
}

// Obtener la versión actual de la matriz
$version_id = (int)($matrizInfo['version_id'] ?? 0);

// Obtener filas de coherencia asociadas a esta matriz Y su versión actual
$filasMatriz = $matriz->obtenerPorMatrizYVersion($matriz_id, $version_id);

$spreadSheet = new Spreadsheet();
$sheet = $spreadSheet->getActiveSheet();
$sheet->setTitle('Malla Curricular');

$sheet->getStyle('A1')->getFont()->setBold(TRUE);

$sheet->getStyle('A2:K2')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 11,
        'name' => 'Calibri',
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],

    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => [
            'argb' => 'D9E2F3',
        ],
    ],
]);

// Establecer anchos fijos para las columnas
$columnWidths = [
    'A' => 20,  // Área de formación
    'B' => 35,  // Dominio
    'C' => 35,  // Competencia
    'D' => 60,  // Resultados de aprendizaje
    'E' => 60,  // Criterios de logro
    'F' => 60,  // Contenidos/Saberes
    'G' => 20,  // Actividad curricular
    'H' => 12,  // SCT-Chile
    'I' => 35,  // Metodologías activas
    'J' => 25,  // Estrategias de evaluación
    'K' => 25,  // Bibliografía
];

foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$sheet->getRowDimension(2)->setRowHeight(54);

$sheet->setCellValue('A1', 'MATRIZ DE COHERENCIA CURRICULAR');
$sheet->setCellValue('A2', 'ÁREA DE FORMACIÓN');
$sheet->setCellValue('B2', 'DOMINIO');
$sheet->setCellValue('C2', 'COMPETENCIA');
$sheet->setCellValue('D2', 'RESULTADOS DE APRENDIZAJE');
$sheet->setCellValue('E2', 'CRITERIOS DE LOGRO');
$sheet->setCellValue('F2', 'CONTENIDOS/SABERES');
$sheet->setCellValue('G2', 'ACTIVIDAD CURRICULAR');
$sheet->setCellValue('H2', 'SCT-CHILE');
$sheet->setCellValue('I2', 'METODOLOGÍAS ACTIVAS CENTRADAS EN EL ESTUDIANTADO');
$sheet->setCellValue('J2', 'ESTRATEGIAS DE EVALUACIÓN');
$sheet->setCellValue('K2', 'BIBLIOGRAFÍA');

$fila = 3;
foreach ($filasMatriz as $ma) {
    $mcid = (int)($ma['id'] ?? 0);
    $dominioEstructurado = '';
    $competenciasEstructuradas = '';
    $resultadosEstructurados = '';
    $criteriosEstructurados = '';

    if ($mcid > 0) {
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
            if (!empty($rowsDet)) {
                $byDomain = [];
                foreach ($rowsDet as $rd) {
                    $dId = (int)$rd['dominio_id'];
                    if (!isset($byDomain[$dId])) {
                        $byDomain[$dId] = [
                            'nombre' => $rd['dominio_nombre'] ?? 'Dominio',
                            'competencias' => [],
                            'resultados' => [],
                            'criterios' => []
                        ];
                    }
                    $compKey = $rd['comp_codigo'] . ' - ' . $rd['comp_desc'];
                    $raKey = $rd['ra_codigo'] . ' - ' . $rd['ra_desc'];
                    $critKey = $rd['cl_codigo'] . ' - ' . $rd['cl_desc'];
                    $byDomain[$dId]['competencias'][$compKey] = true;
                    $byDomain[$dId]['resultados'][$raKey] = true;
                    $byDomain[$dId]['criterios'][$critKey] = true;
                }
                // Construir textos multi-bloque
                $domParts = [];
                $compParts = [];
                $raParts = [];
                $critParts = [];
                foreach ($byDomain as $d) {
                    $domParts[] = $d['nombre'];
                    $compParts[] = '[' . $d['nombre'] . "]\n" . implode("\n", array_keys($d['competencias']));
                    $raParts[] = '[' . $d['nombre'] . "]\n" . implode("\n", array_keys($d['resultados']));
                    $critParts[] = '[' . $d['nombre'] . "]\n" . implode("\n", array_keys($d['criterios']));
                }
                $dominioEstructurado = implode("\n\n", $domParts);
                $competenciasEstructuradas = implode("\n\n", $compParts);
                $resultadosEstructurados = implode("\n\n", $raParts);
                $criteriosEstructurados = implode("\n\n", $critParts);
            }
        }
    }
    // Campos provenientes de obtenerPorCarrera():
    // - area_formacion_nombre (puede ser null)
    // - asignatura_nombre
    // - dominio, competencia, resultado_aprendizaje, criterios_logro,
    //   contenidos, metodologias, estrategias, sct_chile, bibliografia
    $sheet->setCellValue("A{$fila}", $ma['area_formacion_nombre'] ?? '');
    $dominioExcel = '';
    if (!empty($ma['dominio'])) {
        $dominioExcel = $ma['dominio'];
    } elseif (!empty($ma['dominios_lista'])) {
        $dominioExcel = $ma['dominios_lista'];
    } elseif (!empty($ma['dominio_nombre'])) {
        $dominioExcel = $ma['dominio_nombre'];
    }
    $sheet->setCellValue("B{$fila}", $dominioEstructurado !== '' ? $dominioEstructurado : $dominioExcel);
    $sheet->setCellValue("C{$fila}", $competenciasEstructuradas !== '' ? $competenciasEstructuradas : $ma['competencia']);
    $sheet->setCellValue("D{$fila}", $resultadosEstructurados !== '' ? $resultadosEstructurados : $ma['resultado_aprendizaje']);
    $sheet->setCellValue("E{$fila}", $criteriosEstructurados !== '' ? $criteriosEstructurados : $ma['criterios_logro']);
    $sheet->setCellValue("F{$fila}", $ma['contenidos']);
    $sheet->setCellValue("G{$fila}", $ma['asignatura_nombre']);
    $sheet->setCellValue("H{$fila}", $ma['sct_chile']);
    $sheet->setCellValue("I{$fila}", $ma['metodologias']);
    $sheet->setCellValue("J{$fila}", $ma['estrategias']);
    $sheet->setCellValue("K{$fila}", $ma['bibliografia']);

    // Aplicar estilo a las filas de datos
    $sheet->getStyle("A{$fila}:K{$fila}")->applyFromArray([
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
    ]);

    // Ajustar la altura de la fila según el contenido
    $sheet->getRowDimension($fila)->setRowHeight(-1);

    $fila++;
}

$writer = new Xlsx($spreadSheet);

// Proteger acceso cuando no exista la carrera (fetch puede devolver false)
// Nombre de archivo usando nombre de carrera y de la matriz
$carreraNombre = (is_array($matrizInfo) && isset($matrizInfo['carrera_nombre']) && $matrizInfo['carrera_nombre'] !== '')
    ? $matrizInfo['carrera_nombre']
    : ('Carrera_' . (int)$matrizInfo['carrera_id']);
$matrizNombre = (is_array($matrizInfo) && isset($matrizInfo['nombre']) && $matrizInfo['nombre'] !== '')
    ? $matrizInfo['nombre']
    : ('Matriz_' . $matriz_id);

$filename = 'Matriz_Coherencia_Curricular_' . preg_replace('/[^A-Za-z0-9]/', '_', $carreraNombre . '_' . $matrizNombre) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save('php://output');
exit();
