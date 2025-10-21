<?php
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../lib/tcpdf/tcpdf.php';

class MatrizPDF extends TCPDF
{
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 15);
        $this->Cell(0, 10, 'Matriz de Coherencia Curricular - UTEM', 0, 1, 'C');
        $this->Ln(5);
    }
}

if (!isset($_GET['id'])) {
    header('Location: matrices.php');
    exit;
}

$db = new Database();
$conexion = $db->conectar();
$matriz = new MatrizCoherencia($conexion);
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);

$matrizData = $matriz->obtenerPorId($_GET['id']);
if (!$matrizData) {
    header('Location: matrices.php');
    exit;
}

$asignaturaData = $asignatura->obtenerPorId($matrizData['asignatura_id']);
// Obtener carrera a partir de la asignatura
$carreraData = $carrera->obtenerPorId($asignaturaData['carrera_id']);

// Crear PDF
$pdf = new MatrizPDF('L', 'mm', 'A4');
$pdf->SetCreator('UTEM');
$pdf->SetAuthor('UTEM');
$pdf->SetTitle('Matriz de Coherencia - ' . $carreraData['nombre']);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Información de la asignatura
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Ln(5);

// Crear tabla
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('helvetica', 'B', 9);

// Headers
$headers = array('Dominio', 'Competencia', 'Resultados de Aprendizaje', 'Carrera');
$w = array(40, 40, 100, 100);

foreach ($headers as $i => $header) {
    $pdf->Cell($w[$i], 7, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Datos
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell($w[0], 7, $matrizData['dominio'], 1, 'L', false, 0);
$pdf->MultiCell($w[1], 7, $matrizData['competencia'], 1, 'L', false, 0);
$pdf->MultiCell($w[2], 7, $matrizData['resultado_aprendizaje'], 1, 'L', false, 0);
$pdf->MultiCell($w[3], 7, $carreraData['nombre'], 1, 'L', false, 1);

$pdf->Ln(10);

// Segunda tabla
$pdf->SetFont('helvetica', 'B', 9);
$headers2 = array('Criterios de Logro', 'Contenidos', 'Metodologías', 'Estrategias');
$w2 = array(70, 70, 70, 70);

foreach ($headers2 as $i => $header) {
    $pdf->Cell($w2[$i], 7, $header, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell($w2[0], 7, $matrizData['criterios_logro'], 1, 'L', false, 0);
$pdf->MultiCell($w2[1], 7, $matrizData['contenidos'], 1, 'L', false, 0);
$pdf->MultiCell($w2[2], 7, $matrizData['metodologias'], 1, 'L', false, 0);
$pdf->MultiCell($w2[3], 7, ($matrizData['estrategias'] ?? ''), 1, 'L', false, 1);

// Output PDF
$pdf->Output('matriz_coherencia.pdf', 'D');
