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
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);
$asignatura = new Asignatura($conexion);
$carreraModel = new Carrera($conexion);
$matrizModel = new Matriz($conexion);

// Obtener datos de la matriz seleccionada y su carrera
$matriz_id = (int)($_GET['id']);
$matrizInfo = $matrizModel->obtenerPorId($matriz_id);

if (!$matrizInfo) {
    header('Location: matrices.php');
    exit();
}

$phpWord = new PhpWord();

// Configurar sección en orientación horizontal (landscape)
$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'marginTop' => 720,
    'marginBottom' => 720,
    'marginLeft' => 720,
    'marginRight' => 720,
]);

$section->addParagraph('MATRIZ DE COHERENCIA CURRICULAR', ['bold' => true, 'size' => 14]);

// Obtener filas de la matriz
$version_id = (int)($matrizInfo['version_id'] ?? 0);
$filasMatriz = $matriz->obtenerPorMatrizYVersion($matriz_id, $version_id);

// Crear estilo de tabla con bordes
$phpWord->addTableStyle('TablaMatriz', [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
]);

use PhpOffice\PhpWord\Style\Border;

$table = $section->addTable('TablaMatriz');

// Agregar encabezado
$encabezadoFila = $table->addRow();
$encabezados = [
    'ÁREA DE FORMACIÓN',
    'DOMINIO',
    'COMPETENCIA',
    'RESULTADOS DE APRENDIZAJE',
    'CRITERIOS DE LOGRO',
    'CONTENIDOS/SABERES',
    'ACTIVIDAD CURRICULAR',
    'SCT-CHILE',
    'METODOLOGÍAS ACTIVAS CENTRADAS EN EL ESTUDIANTADO',
    'ESTRATEGIAS DE EVALUACIÓN',
    'BIBLIOGRAFÍA'
];

foreach ($encabezados as $encabezado) {
    $cell = $encabezadoFila->addCell(1500);
    $cell->addText($encabezado, ['bold' => true, 'size' => 11, 'color' => '000000']);
}

// Agregar filas de datos
foreach ($filasMatriz as $fila) {
    $row = $table->addRow();
    $row->addCell(1500)->addText($fila['area_formacion_nombre'] ?? '');
    $row->addCell(1500)->addText($fila['dominio'] ?? '');
    $row->addCell(1500)->addText($fila['competencia'] ?? '');
    $row->addCell(1500)->addText($fila['resultado_aprendizaje'] ?? '');
    $row->addCell(1500)->addText($fila['criterios_logro'] ?? '');
    $row->addCell(1500)->addText($fila['contenidos'] ?? '');
    $row->addCell(1500)->addText($fila['asignatura_nombre'] ?? '');
    $row->addCell(1500)->addText($fila['sct_chile'] ?? '');
    $row->addCell(1500)->addText($fila['metodologias'] ?? '');
    $row->addCell(1500)->addText($fila['estrategias'] ?? '');
    $row->addCell(1500)->addText($fila['bibliografia'] ?? '');
}

$writer = IOFactory::createWriter($phpWord, 'Word2007');

$carreraNombre = $matrizInfo['carrera_nombre'] ?? 'Carrera_'.$matrizInfo['carrera_id'];
$matrizNombre = $matrizInfo['nombre'] ?? 'Matriz_' . $matriz_id;

$nombreArchivo = 'Matriz_Coherencia_Curricular_' . preg_replace('/[^A-Za-z0-9]/', '_', $carreraNombre . '_' . $matrizNombre) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
$writer->save('php://output');
exit();

?>