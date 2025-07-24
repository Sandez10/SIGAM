<?php
require '../vendor/autoload.php';
require_once '../../database/conexion.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Obtener parámetros
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : null;
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : null;

// Validar parámetros
if (!$trimestre || !$anio) {
    die("Trimestre y año son obligatorios");
}

// Configurar meses por trimestre
$meses = [
    1 => ['Ene', 'Mar'],
    2 => ['Abr', 'Jun'],
    3 => ['Jul', 'Sep'],
    4 => ['Oct', 'Dic']
];

if (!isset($meses[$trimestre])) {
    die("Trimestre inválido");
}

// Crear instancia de Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Establecer propiedades del documento
$spreadsheet->getProperties()
    ->setCreator("Sistema SIGAM")
    ->setTitle("Reporte de Tesorería")
    ->setDescription("Reporte trimestral de tesorería");

// 1. ENCABEZADO DEL REPORTE
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', "GRAN LOGIA DEL ESTADO DE GUERRERO");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:I2');
$sheet->setCellValue('A2', "RELACIÓN DE PAGOS - TESORERÍA");
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A3:I3');
$sheet->setCellValue('A3', "Trimestre $trimestre ({$meses[$trimestre][0]} - {$meses[$trimestre][1]}) del año $anio");
$sheet->getStyle('A3')->getFont()->setBold(true);
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 2. ENCABEZADOS DE COLUMNAS
$sheet->setCellValue('A5', 'No');
$sheet->setCellValue('B5', 'Nombre del hermano');
$sheet->setCellValue('C5', 'Grado');
$sheet->setCellValue('D5', 'Capitas');
$sheet->setCellValue('E5', 'Seguro');
$sheet->setCellValue('F5', 'Iniciación');
$sheet->setCellValue('G5', 'Exaltación');
$sheet->setCellValue('H5', 'Afiliación');
$sheet->setCellValue('I5', 'Total');

// Estilo para encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$sheet->getStyle('A5:I5')->applyFromArray($headerStyle);

// 3. OBTENER DATOS DE LA BASE DE DATOS
$db = Database::getInstance();
$conn = $db->getConnection();

$mesInicio = sprintf("%02d", ($trimestre - 1) * 3 + 1);
$mesFin = sprintf("%02d", $trimestre * 3);

$query = "SELECT 
            r.id as id_hermano,
            r.nombre_completo as nombre_hermano,
            im.grado_masonico as grado,
            SUM(t.capitas) as capitas,
            SUM(t.seguro) as seguro,
            SUM(t.iniciacion) as iniciacion,
            SUM(t.exaltacion) as exaltacion,
            SUM(t.afiliacion) as afiliacion,
            SUM(t.total) as total
          FROM tesoreria t
          JOIN registros r ON t.id_hermano = r.id
          JOIN informacion_masonica im ON r.id = im.id_registro
          WHERE YEAR(t.fecha_registro) = ?
          AND MONTH(t.fecha_registro) BETWEEN ? AND ?
          GROUP BY r.id, r.nombre_completo, im.grado_masonico
          ORDER BY r.nombre_completo";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmt->execute();
$result = $stmt->get_result();

// 4. LLENAR DATOS EN LA HOJA
$rowNumber = 6;
$totalCapitas = 0;
$totalSeguro = 0;
$totalIniciacion = 0;
$totalExaltacion = 0;
$totalAfiliacion = 0;
$totalGeneral = 0;

while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue('A'.$rowNumber, $rowNumber - 5);
    $sheet->setCellValue('B'.$rowNumber, $row['nombre_hermano']);
    $sheet->setCellValue('C'.$rowNumber, ucfirst($row['grado']));
    $sheet->setCellValue('D'.$rowNumber, $row['capitas']);
    $sheet->setCellValue('E'.$rowNumber, $row['seguro']);
    $sheet->setCellValue('F'.$rowNumber, $row['iniciacion']);
    $sheet->setCellValue('G'.$rowNumber, $row['exaltacion']);
    $sheet->setCellValue('H'.$rowNumber, $row['afiliacion']);
    $sheet->setCellValue('I'.$rowNumber, $row['total']);
    
    // Formato de números corregido
    $sheet->getStyle('D'.$rowNumber.':I'.$rowNumber)
          ->getNumberFormat()
          ->setFormatCode('"$"#,##0.00');
    
    // Sumar a totales
    $totalCapitas += $row['capitas'];
    $totalSeguro += $row['seguro'];
    $totalIniciacion += $row['iniciacion'];
    $totalExaltacion += $row['exaltacion'];
    $totalAfiliacion += $row['afiliacion'];
    $totalGeneral += $row['total'];
    
    $rowNumber++;
}

// 5. FILA DE TOTALES
$sheet->setCellValue('C'.$rowNumber, 'TOTAL GENERAL');
$sheet->setCellValue('D'.$rowNumber, $totalCapitas);
$sheet->setCellValue('E'.$rowNumber, $totalSeguro);
$sheet->setCellValue('F'.$rowNumber, $totalIniciacion);
$sheet->setCellValue('G'.$rowNumber, $totalExaltacion);
$sheet->setCellValue('H'.$rowNumber, $totalAfiliacion);
$sheet->setCellValue('I'.$rowNumber, $totalGeneral);

// Estilo para totales
$totalStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]]
];

$sheet->getStyle('A'.$rowNumber.':I'.$rowNumber)->applyFromArray($totalStyle);
$sheet->getStyle('D'.$rowNumber.':I'.$rowNumber)
      ->getNumberFormat()
      ->setFormatCode('"$"#,##0.00');

// 6. AJUSTAR ANCHO DE COLUMNAS
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(12);
$sheet->getColumnDimension('H')->setWidth(12);
$sheet->getColumnDimension('I')->setWidth(12);

// 7. FIRMAS
$rowNumber += 2;
$sheet->mergeCells('B'.$rowNumber.':D'.$rowNumber);
$sheet->setCellValue('B'.$rowNumber, 'Fraternalmente');
$sheet->getStyle('B'.$rowNumber)->getFont()->setBold(true);

$sheet->mergeCells('F'.$rowNumber.':H'.$rowNumber);
$sheet->setCellValue('F'.$rowNumber, 'Vo. Bo. El Venerable Maestro');
$sheet->getStyle('F'.$rowNumber)->getFont()->setBold(true);

// 8. GENERAR Y DESCARGAR ARCHIVO
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Tesoreria_Trim'.$trimestre.'_'.$anio.'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;