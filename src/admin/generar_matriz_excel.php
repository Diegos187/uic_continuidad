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
    'C' => 50,  // Competencia
    'D' => 60,  // Resultados de aprendizaje
    'E' => 60,  // Criterios de logro
    'F' => 60,  // Contenidos/Saberes
    'G' => 20,  // Actividad curricular
    'H' => 12,  // SCT-Chile
    'I' => 50,  // Metodologías activas
    'J' => 30,  // Estrategias de evaluación
    'K' => 30,  // Bibliografía
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
    $domainTree = [];

    if ($mcid > 0) {
        $sqlDetalle = "SELECT ped.id AS dominio_id, ped.dominio AS dominio_nombre,
                               c.codigo AS comp_codigo, c.descripcion AS comp_desc,
                               ra.codigo AS ra_codigo, ra.descripcion AS ra_desc,
                               cl.codigo AS cl_codigo, cl.descripcion AS cl_desc
                        FROM matriz_tributacion mt
                        JOIN criterios_logro_ref cl ON cl.id = mt.criterio_logro_id
                        JOIN resultados_aprendizaje_ref ra ON ra.id = cl.resultado_aprendizaje_id
                        JOIN competencias_dominio c ON c.id = ra.competencia_dominio_id
                        JOIN perfiles_egreso_detalle ped ON ped.id = c.perfil_egreso_detalle_id
                        WHERE mt.matriz_coherencia_id = :mcid
                        ORDER BY ped.id, c.orden, ra.orden, cl.orden";
        $stmtDet = $conexion->prepare($sqlDetalle);
        $stmtDet->bindValue(':mcid', $mcid, \PDO::PARAM_INT);
        if ($stmtDet->execute()) {
            $rowsDet = $stmtDet->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rowsDet as $rd) {
                $dId = (int)$rd['dominio_id'];
                if (!isset($domainTree[$dId])) {
                    $domainTree[$dId] = [
                        'nombre' => $rd['dominio_nombre'] ?? 'Dominio',
                        'competencias' => []
                    ];
                }
                $compCode = $rd['comp_codigo'];
                $compDesc = $rd['comp_desc'];
                $raCode = $rd['ra_codigo'];
                $raDesc = $rd['ra_desc'];
                $clCode = $rd['cl_codigo'];
                $clDesc = $rd['cl_desc'];

                $compKey = $compCode; 
                if (!isset($domainTree[$dId]['competencias'][$compKey])) {
                    $domainTree[$dId]['competencias'][$compKey] = [
                        'codigo' => $compCode,
                        'descripcion' => $compDesc,
                        'resultados' => []
                    ];
                }
                $resKey = $raCode;
                if (!isset($domainTree[$dId]['competencias'][$compKey]['resultados'][$resKey])) {
                    $domainTree[$dId]['competencias'][$compKey]['resultados'][$resKey] = [
                        'codigo' => $raCode,
                        'descripcion' => $raDesc,
                        'criterios' => []
                    ];
                }
                $domainTree[$dId]['competencias'][$compKey]['resultados'][$resKey]['criterios'][$clCode] = [
                    'codigo' => $clCode,
                    'descripcion' => $clDesc
                ];
            }
        }
    }

    // Si no hay dominios (fila sin tributación), crear un grupo único usando campos planos.
    if (empty($domainTree)) {
        $nombreDominioPlano = '';
        if (!empty($ma['dominio'])) { $nombreDominioPlano = $ma['dominio']; }
        elseif (!empty($ma['dominios_lista'])) { $nombreDominioPlano = $ma['dominios_lista']; }
        elseif (!empty($ma['dominio_nombre'])) { $nombreDominioPlano = $ma['dominio_nombre']; }
        // Estructura plana: una competencia y un resultado, criterios como lista
        $domainTree[-1] = [
            'nombre' => $nombreDominioPlano !== '' ? $nombreDominioPlano : 'Sin dominio',
            'competencias' => []
        ];
        $compText = trim((string)($ma['competencia'] ?? ''));
        if ($compText !== '') {
            $domainTree[-1]['competencias'][$compText] = [
                'codigo' => $compText,
                'descripcion' => '',
                'resultados' => []
            ];
            $raText = trim((string)($ma['resultado_aprendizaje'] ?? ''));
            $domainTree[-1]['competencias'][$compText]['resultados'][$raText] = [
                'codigo' => $raText,
                'descripcion' => '',
                'criterios' => []
            ];
            $criteriosList = array_filter(array_map('trim', explode("\n", (string)($ma['criterios_logro'] ?? ''))));
            foreach ($criteriosList as $crit) {
                $domainTree[-1]['competencias'][$compText]['resultados'][$raText]['criterios'][$crit] = [
                    'codigo' => $crit,
                    'descripcion' => ''
                ];
            }
        }
    }

    $startRow = $fila;
    // Expandir por Dominio → Competencia → Resultado (una fila por RA)
    foreach ($domainTree as $dId => $dg) {
        $domainStart = $fila;
        $compStarts = [];
        foreach ($dg['competencias'] as $cKey => $comp) {
            $compStart = $fila;
            foreach ($comp['resultados'] as $rKey => $ra) {
                // Criterios del RA como líneas
                $critLines = [];
                foreach ($ra['criterios'] as $clKey => $cl) {
                    $desc = $cl['descripcion'] !== '' ? (' - ' . $cl['descripcion']) : '';
                    $critLines[] = $cl['codigo'] . $desc;
                }
                $critRaw = implode("\n", $critLines);

                // Escribir fila
                $sheet->setCellValue("A{$fila}", $ma['area_formacion_nombre'] ?? '');
                $sheet->setCellValue("B{$fila}", $dg['nombre']);
                $sheet->setCellValue("C{$fila}", $comp['codigo'] . ($comp['descripcion'] !== '' ? (' - ' . $comp['descripcion']) : ''));
                $sheet->setCellValue("D{$fila}", $ra['codigo'] . ($ra['descripcion'] !== '' ? (' - ' . $ra['descripcion']) : ''));
                $sheet->setCellValue("E{$fila}", $critRaw);
                $sheet->setCellValue("F{$fila}", $ma['contenidos']);
                $sheet->setCellValue("G{$fila}", $ma['asignatura_nombre']);
                $sheet->setCellValue("H{$fila}", $ma['sct_chile']);
                $sheet->setCellValue("I{$fila}", $ma['metodologias']);
                $sheet->setCellValue("J{$fila}", $ma['estrategias']);
                $sheet->setCellValue("K{$fila}", $ma['bibliografia']);

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
                $sheet->getRowDimension($fila)->setRowHeight(-1);

                foreach (['C'=>$comp['codigo'] . ($comp['descripcion'] !== '' ? (' - ' . $comp['descripcion']) : ''),
                          'D'=>$ra['codigo'] . ($ra['descripcion'] !== '' ? (' - ' . $ra['descripcion']) : ''),
                          'E'=>$critRaw] as $col=>$raw) {
                    if ($raw === '') continue;
                    $lines = explode("\n", $raw);
                    $rich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                    foreach ($lines as $i=>$line) {
                        if ($line === '') { $rich->createText("\n"); continue; }
                        $parts = explode(' - ', $line, 2);
                        $codePart = $parts[0];
                        $descPart = $parts[1] ?? '';
                        $codeRun = $rich->createTextRun($codePart);
                        $codeRun->getFont()->setBold(true);
                        if ($descPart !== '') {
                            $rich->createText(' - ' . $descPart);
                        }
                        if ($i < count($lines)-1) {
                            $rich->createText("\n");
                        }
                    }
                    $sheet->setCellValue("{$col}{$fila}", $rich);
                }

                $fila++;
            }
            $compStarts[] = [$compStart, $fila - 1];
        }
        $domainEnd = $fila - 1;

        // Unir Dominio (B) para todas las filas del dominio
        if ($domainEnd >= $domainStart) {
            $sheet->mergeCells("B{$domainStart}:B{$domainEnd}");
            $sheet->getStyle("B{$domainStart}:B{$domainEnd}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$domainStart}:B{$domainEnd}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
        // Unir Área de formación (A) también
        if ($domainEnd >= $domainStart) {
            $sheet->mergeCells("A{$domainStart}:A{$domainEnd}");
            $sheet->getStyle("A{$domainStart}:A{$domainEnd}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$domainStart}:A{$domainEnd}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }
        // Unir Competencia (C) por bloque
        foreach ($compStarts as [$cs, $ce]) {
            if ($ce >= $cs) {
                $sheet->mergeCells("C{$cs}:C{$ce}");
                $sheet->getStyle("C{$cs}:C{$ce}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            }
        }
        // Unir comunes F..K por todo el dominio
        if ($domainEnd >= $domainStart) {
            foreach (['F','G','H','I','J','K'] as $colMerge) {
                $sheet->mergeCells("{$colMerge}{$domainStart}:{$colMerge}{$domainEnd}");
                $sheet->getStyle("{$colMerge}{$domainStart}:{$colMerge}{$domainEnd}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            }
        }
    }
}

$writer = new Xlsx($spreadSheet);

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
