<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../lib/tcpdf/tcpdf.php';
verificarSesion();

if (!isset($_GET['id'])) {
   header('Location: mallas.php');
   exit();
}

$db = new Database();
$conexion = $db->conectar();

// Obtener datos de la carrera
$carrera_id = $_GET['id'];
$stmt = $conexion->prepare("SELECT * FROM carreras WHERE id = ?");
$stmt->execute([$carrera_id]);
$carrera = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener asignaturas
$stmt = $conexion->prepare("SELECT * FROM asignaturas WHERE carrera_id = ? ORDER BY semestre");
$stmt->execute([$carrera_id]);
$asignaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear PDF
class MYPDF extends TCPDF {
   public function Header() {
       $this->SetFont('helvetica', 'B', 12);
       $this->Cell(0, 10, 'Universidad Tecnológica Metropolitana', 0, false, 'C', 0);
       $this->Ln(10);
   }
}

$pdf = new MYPDF('L', 'mm', 'A3', true, 'UTF-8', false);
$pdf->SetCreator('UTEM');
$pdf->SetAuthor('UTEM');
$pdf->SetTitle($carrera['nombre'] . ' - Malla Curricular');
$pdf->SetMargins(15, 25, 15);
$pdf->AddPage();

// Título de la malla
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, mb_strtoupper($carrera['nombre']), 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'MALLA CURRICULAR ' . $carrera['anio'], 0, 1, 'C');
$pdf->Cell(0, 10, 'JORNADA ' . mb_strtoupper($carrera['jornada']), 0, 1, 'C');
$pdf->Ln(10);

// Organizar asignaturas por semestre
$malla = [];
foreach ($asignaturas as $asignatura) {
   $malla[$asignatura['semestre']][] = $asignatura;
}

// Configuración de celdas
$cellWidth = 60;
$cellHeight = 30;
$marginX = 15;
$marginY = $pdf->GetY();
$maxColumns = 5;

// Calcular páginas y dibujar malla
$pageCount = ceil($carrera['duracion_semestres'] / $maxColumns);
$semesterCount = 0;
$currentY = $marginY;

for ($page = 0; $page < $pageCount; $page++) {
   if ($page > 0) {
       $pdf->AddPage();
       $currentY = $pdf->GetY();
   }
   
   $currentX = $marginX;
   $columnCount = 0;
   
   for ($col = 0; $col < $maxColumns && $semesterCount < $carrera['duracion_semestres']; $col++) {
       $i = $semesterCount + 1;
       
       $pdf->SetXY($currentX, $currentY);
       $pdf->SetFont('helvetica', 'B', 11);
       $pdf->Cell($cellWidth, 10, 'SEMESTRE ' . $i, 0, 1, 'C');
       
       $pdf->SetFont('helvetica', '', 10);
       if (isset($malla[$i])) {
           foreach ($malla[$i] as $asignatura) {
               $pdf->SetXY($currentX, $pdf->GetY());
               $pdf->MultiCell($cellWidth, $cellHeight,
                   $asignatura['nombre'] . "\n" .
                   $asignatura['duracion_semanas'] . " semanas",
                   1, 'C');
               $pdf->Ln(2);
           }
       }
       
       $currentX += $cellWidth + 5;
       $pdf->SetY($currentY);
       $semesterCount++;
   }
}

$pdf->Output($carrera['nombre'] . '_malla_' . $carrera['anio'] . '.pdf', 'D');
?>