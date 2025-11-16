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

foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$sheet->getRowDimension(2)->setRowHeight(54);
/*$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);
$sheet->getColumnDimension('C')->setAutoSize(true);
$sheet->getColumnDimension('D')->setAutoSize(true);
$sheet->getColumnDimension('E')->setAutoSize(true);
$sheet->getColumnDimension('F')->setAutoSize(true);
$sheet->getColumnDimension('G')->setAutoSize(true);
$sheet->getColumnDimension('H')->setAutoSize(true);
$sheet->getColumnDimension('I')->setAutoSize(true);
$sheet->getColumnDimension('J')->setAutoSize(true);
$sheet->getColumnDimension('K')->setAutoSize(true);*/

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
    // Campos provenientes de obtenerPorCarrera():
    // - area_formacion_nombre (puede ser null)
    // - asignatura_nombre
    // - dominio, competencia, resultado_aprendizaje, criterios_logro,
    //   contenidos, metodologias, estrategias, sct_chile, bibliografia
    $sheet->setCellValue("A{$fila}", $ma['area_formacion_nombre'] ?? '');
    $sheet->setCellValue("B{$fila}", $ma['dominio']);
    $sheet->setCellValue("C{$fila}", $ma['competencia']);
    $sheet->setCellValue("D{$fila}", $ma['resultado_aprendizaje']);
    $sheet->setCellValue("E{$fila}", $ma['criterios_logro']);
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
