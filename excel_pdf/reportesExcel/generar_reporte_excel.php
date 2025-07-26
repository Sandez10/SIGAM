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

// Establecer propiedades del documento
$spreadsheet->getProperties()
    ->setCreator("Sistema SIGAM")
    ->setTitle("Reporte de Tesorería")
    ->setDescription("Reporte trimestral de tesorería");

// ** Hoja 1: Concentrado General **
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Concentrado General');

// 1. ENCABEZADO DEL REPORTE (Hoja 1)
$sheet1->mergeCells('A1:I1');
$sheet1->setCellValue('A1', "A.\G.\D.\G.\A.\D.\U.");
$sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A2:I2');
$sheet1->setCellValue('A2', "GRAN LOGIA DEL ESTADO DE GUERRERO");
$sheet1->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet1->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A3:I3');
$sheet1->setCellValue('A3', "Conferencia de Grandes Logias Regulares de la R.\E.\A.\A.");
$sheet1->getStyle('A3')->getFont()->setBold(true);
$sheet1->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A4:I4');
$sheet1->setCellValue('A4', "Pres. Simb. Rito Juárez 101 - No. 12");
$sheet1->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A5:I5');
$sheet1->setCellValue('A5', "Oriente de Zihuatanejo, Gro. a 25 julio 2025 E.\V.");
$sheet1->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A6:I6');
$sheet1->setCellValue('A6', "Asunto: Informe de Tesorería del Trimestre $trimestre de $anio");
$sheet1->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 2. ENCABEZADOS DE COLUMNAS (Hoja 1)
$sheet1->setCellValue('A8', 'No');
$sheet1->setCellValue('B8', 'Nombre del hermano');
$sheet1->setCellValue('C8', 'Grado');
$sheet1->setCellValue('D8', 'Capitas');
$sheet1->setCellValue('E8', 'Seguro');
$sheet1->setCellValue('F8', 'Iniciación');
$sheet1->setCellValue('G8', 'Exaltación');
$sheet1->setCellValue('H8', 'Afiliación');
$sheet1->setCellValue('I8', 'Total');

// Estilo para encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$sheet1->getStyle('A8:I8')->applyFromArray($headerStyle);

// 3. OBTENER DATOS DE LA BASE DE DATOS (Hoja 1)
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

// 4. LLENAR DATOS EN LA HOJA (Hoja 1)
$rowNumber = 9;
$totalCapitas = 0;
$totalSeguro = 0;
$totalIniciacion = 0;
$totalExaltacion = 0;
$totalAfiliacion = 0;
$totalGeneral = 0;

while ($row = $result->fetch_assoc()) {
    $sheet1->setCellValue('A'.$rowNumber, $rowNumber - 8);
    $sheet1->setCellValue('B'.$rowNumber, $row['nombre_hermano']);
    $sheet1->setCellValue('C'.$rowNumber, ucfirst($row['grado']));
    $sheet1->setCellValue('D'.$rowNumber, $row['capitas']);
    $sheet1->setCellValue('E'.$rowNumber, $row['seguro']);
    $sheet1->setCellValue('F'.$rowNumber, $row['iniciacion']);
    $sheet1->setCellValue('G'.$rowNumber, $row['exaltacion']);
    $sheet1->setCellValue('H'.$rowNumber, $row['afiliacion']);
    $sheet1->setCellValue('I'.$rowNumber, $row['total']);
    
    $sheet1->getStyle('D'.$rowNumber.':I'.$rowNumber)
           ->getNumberFormat()
           ->setFormatCode('"$"#,##0.00');
    
    $totalCapitas += $row['capitas'];
    $totalSeguro += $row['seguro'];
    $totalIniciacion += $row['iniciacion'];
    $totalExaltacion += $row['exaltacion'];
    $totalAfiliacion += $row['afiliacion'];
    $totalGeneral += $row['total'];
    
    $rowNumber++;
}

// 5. FILA DE TOTALES (Hoja 1)
$sheet1->setCellValue('C'.$rowNumber, 'TOTAL GENERAL');
$sheet1->setCellValue('D'.$rowNumber, $totalCapitas);
$sheet1->setCellValue('E'.$rowNumber, $totalSeguro);
$sheet1->setCellValue('F'.$rowNumber, $totalIniciacion);
$sheet1->setCellValue('G'.$rowNumber, $totalExaltacion);
$sheet1->setCellValue('H'.$rowNumber, $totalAfiliacion);
$sheet1->setCellValue('I'.$rowNumber, $totalGeneral);

$totalStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]]
];

$sheet1->getStyle('A'.$rowNumber.':I'.$rowNumber)->applyFromArray($totalStyle);
$sheet1->getStyle('D'.$rowNumber.':I'.$rowNumber)
       ->getNumberFormat()
       ->setFormatCode('"$"#,##0.00');

// 6. AJUSTAR ANCHO DE COLUMNAS (Hoja 1)
$sheet1->getColumnDimension('A')->setWidth(5);
$sheet1->getColumnDimension('B')->setWidth(30);
$sheet1->getColumnDimension('C')->setWidth(15);
$sheet1->getColumnDimension('D')->setWidth(12);
$sheet1->getColumnDimension('E')->setWidth(12);
$sheet1->getColumnDimension('F')->setWidth(12);
$sheet1->getColumnDimension('G')->setWidth(12);
$sheet1->getColumnDimension('H')->setWidth(12);
$sheet1->getColumnDimension('I')->setWidth(12);

// ** Hoja 2: Reporte de Movimientos **
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Reporte de Movimientos');

// 1. ENCABEZADO DEL REPORTE (Hoja 2)
$sheet2->mergeCells('A1:C1');
$sheet2->setCellValue('A1', "A.\G.\D.\G.\A.\D.\U.");
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->mergeCells('A2:C2');
$sheet2->setCellValue('A2', "GRAN LOGIA DEL ESTADO DE GUERRERO");
$sheet2->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet2->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->mergeCells('A3:C3');
$sheet2->setCellValue('A3', "Conferencia de Grandes Logias Regulares de la R.\E.\A.\A.");
$sheet2->getStyle('A3')->getFont()->setBold(true);
$sheet2->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->mergeCells('A4:C4');
$sheet2->setCellValue('A4', "Pres. Simb. Rito Juárez 101 - No. 12");
$sheet2->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->mergeCells('A5:C5');
$sheet2->setCellValue('A5', "Oriente de Zihuatanejo, Gro. a 25 julio 2025 E.\V.");
$sheet2->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->mergeCells('A6:C6');
$sheet2->setCellValue('A6', "Asunto: Informe de Tesorería del Trimestre $trimestre de $anio");
$sheet2->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 2. ENCABEZADOS Y ESTRUCTURA (Hoja 2)
$sheet2->setCellValue('A8', 'V.H. Sublime Maestre Resendiz');
$sheet2->getStyle('A8')->getFont()->setBold(true);
$sheet2->setCellValue('A9', 'Gran Tesorero de la Muy Respetable Gr. Log. del Estado de Guerrero.');
$sheet2->getStyle('A9')->getFont()->setBold(true);
$sheet2->setCellValue('A10', 'Presente');

$sheet2->setCellValue('A12', 'Por medio del presente permito informar los movimientos trimestrales administrativos de tesorería generados al interior de esta Respetable Logia Simbólica por el candidato de');
$sheet2->setCellValue('A13', '$0.00 (CERO PESOS 00/100 M.N.)');

$sheet2->setCellValue('A15', 'NÚMERO DE HERMANOS');
$sheet2->getStyle('A15')->getFont()->setBold(true);
$sheet2->setCellValue('B15', 'Cantidad');
$sheet2->setCellValue('C15', 'Subtotal');

$sheet2->setCellValue('A16', 'Número de Hermanos Aprendices');
$sheet2->setCellValue('B16', '');
$sheet2->setCellValue('C16', '');
$sheet2->setCellValue('A17', 'Número de Hermanos Compañeros');
$sheet2->setCellValue('B17', '');
$sheet2->setCellValue('C17', '');
$sheet2->setCellValue('A18', 'Número de Hermanos Maestros libres de la Orden');
$sheet2->setCellValue('B18', '');
$sheet2->setCellValue('C18', '');
$sheet2->setCellValue('A19', 'Número de Hermanos cubiertos por seguro Masónico');
$sheet2->setCellValue('B19', '');
$sheet2->setCellValue('C19', '');
$sheet2->setCellValue('A20', 'Solicitudes al (la) hno(a)');
$sheet2->setCellValue('B20', '');
$sheet2->setCellValue('C20', '');
$sheet2->setCellValue('A21', 'Iniciaciones (si hubo)');
$sheet2->setCellValue('B21', '');
$sheet2->setCellValue('C21', '');
$sheet2->setCellValue('A22', 'Exaltaciones (si hubo)');
$sheet2->setCellValue('B22', '');
$sheet2->setCellValue('C22', '');
$sheet2->setCellValue('A23', 'Afiliaciones (si hubo)');
$sheet2->setCellValue('B23', '');
$sheet2->setCellValue('C23', '');
$sheet2->setCellValue('A24', 'Total de movimientos acreditados');
$sheet2->setCellValue('B24', '');
$sheet2->setCellValue('C24', '');

$sheet2->setCellValue('A26', 'Total de miembros activos (hijos todos los hermanos que cubren)');
$sheet2->getStyle('A26')->getFont()->setBold(true);
$sheet2->setCellValue('B26', 'Cantidad');
$sheet2->setCellValue('C26', 'Subtotal');
$sheet2->setCellValue('A27', 'Capitas y Miembros libres de la Orden');
$sheet2->setCellValue('B27', '');
$sheet2->setCellValue('C27', '');
$sheet2->setCellValue('A28', 'Total de miembros depositados');
$sheet2->getStyle('A28')->getFont()->setBold(true);
$sheet2->setCellValue('B28', 'Cantidad');
$sheet2->setCellValue('C28', 'Subtotal');
$sheet2->setCellValue('A29', 'Total de miembros dados de baja');
$sheet2->getStyle('A29')->getFont()->setBold(true);
$sheet2->setCellValue('B29', 'Cantidad');
$sheet2->setCellValue('C29', 'Subtotal');

// Estilo para encabezados y datos
$sheet2->getStyle('A8:A10')->getFont()->setBold(true);
$sheet2->getStyle('A15')->getFont()->setBold(true);
$sheet2->getStyle('A26')->getFont()->setBold(true);
$sheet2->getStyle('A28:A29')->getFont()->setBold(true);
$sheet2->getStyle('B15:C15')->applyFromArray($headerStyle);
$sheet2->getStyle('B26:C26')->applyFromArray($headerStyle);
$sheet2->getStyle('B28:C28')->applyFromArray($headerStyle);
$sheet2->getStyle('B29:C29')->applyFromArray($headerStyle);

// 3. OBTENER DATOS PARA REPORTES
$mesInicio = sprintf("%02d", ($trimestre - 1) * 3 + 1);
$mesFin = sprintf("%02d", $trimestre * 3);

// Aprendices
$queryAprendices = "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.total) as subtotal 
                    FROM tesoreria t 
                    JOIN registros r ON t.id_hermano = r.id 
                    JOIN informacion_masonica im ON r.id = im.id_registro 
                    WHERE im.grado_masonico = 'Aprendiz' 
                    AND YEAR(t.fecha_registro) = ? 
                    AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtAprendices = $conn->prepare($queryAprendices);
$stmtAprendices->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtAprendices->execute();
$resultAprendices = $stmtAprendices->get_result();
$aprendices = $resultAprendices->fetch_assoc();
$aprendicesCount = $aprendices['cantidad'];
$aprendicesSubtotal = $aprendices['subtotal'];

// Compañeros
$queryCompaneros = "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.total) as subtotal 
                    FROM tesoreria t 
                    JOIN registros r ON t.id_hermano = r.id 
                    JOIN informacion_masonica im ON r.id = im.id_registro 
                    WHERE im.grado_masonico = 'Compañero' 
                    AND YEAR(t.fecha_registro) = ? 
                    AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtCompaneros = $conn->prepare($queryCompaneros);
$stmtCompaneros->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtCompaneros->execute();
$resultCompaneros = $stmtCompaneros->get_result();
$companeros = $resultCompaneros->fetch_assoc();
$companerosCount = $companeros['cantidad'];
$companerosSubtotal = $companeros['subtotal'];

// Maestros
$queryMaestros = "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.total) as subtotal 
                  FROM tesoreria t 
                  JOIN registros r ON t.id_hermano = r.id 
                  JOIN informacion_masonica im ON r.id = im.id_registro 
                  WHERE im.grado_masonico = 'Maestro' 
                  AND YEAR(t.fecha_registro) = ? 
                  AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtMaestros = $conn->prepare($queryMaestros);
$stmtMaestros->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtMaestros->execute();
$resultMaestros = $stmtMaestros->get_result();
$maestros = $resultMaestros->fetch_assoc();
$maestrosCount = $maestros['cantidad'];
$maestrosSubtotal = $maestros['subtotal'];

// Seguro Masónico
$querySeguro = "SELECT COUNT(DISTINCT t.id_hermano) as cantidad, SUM(t.total) as subtotal 
                FROM tesoreria t 
                WHERE t.seguro > 0 
                AND YEAR(t.fecha_registro) = ? 
                AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtSeguro = $conn->prepare($querySeguro);
$stmtSeguro->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtSeguro->execute();
$resultSeguro = $stmtSeguro->get_result();
$seguro = $resultSeguro->fetch_assoc();
$seguroCount = $seguro['cantidad'];
$seguroSubtotal = $seguro['subtotal'];

// Iniciaciones
$queryIniciacion = "SELECT COUNT(*) as cantidad, SUM(t.total) as subtotal 
                    FROM tesoreria t 
                    WHERE t.iniciacion > 0 
                    AND YEAR(t.fecha_registro) = ? 
                    AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtIniciacion = $conn->prepare($queryIniciacion);
$stmtIniciacion->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtIniciacion->execute();
$resultIniciacion = $stmtIniciacion->get_result();
$iniciacion = $resultIniciacion->fetch_assoc();
$iniciacionCount = $iniciacion['cantidad'];
$iniciacionSubtotal = $iniciacion['subtotal'];

// Exaltaciones
$queryExaltacion = "SELECT COUNT(*) as cantidad, SUM(t.total) as subtotal 
                    FROM tesoreria t 
                    WHERE t.exaltacion > 0 
                    AND YEAR(t.fecha_registro) = ? 
                    AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtExaltacion = $conn->prepare($queryExaltacion);
$stmtExaltacion->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtExaltacion->execute();
$resultExaltacion = $stmtExaltacion->get_result();
$exaltacion = $resultExaltacion->fetch_assoc();
$exaltacionCount = $exaltacion['cantidad'];
$exaltacionSubtotal = $exaltacion['subtotal'];

// Afiliaciones
$queryAfiliacion = "SELECT COUNT(*) as cantidad, SUM(t.total) as subtotal 
                    FROM tesoreria t 
                    WHERE t.afiliacion > 0 
                    AND YEAR(t.fecha_registro) = ? 
                    AND MONTH(t.fecha_registro) BETWEEN ? AND ?";
$stmtAfiliacion = $conn->prepare($queryAfiliacion);
$stmtAfiliacion->bind_param("iii", $anio, $mesInicio, $mesFin);
$stmtAfiliacion->execute();
$resultAfiliacion = $stmtAfiliacion->get_result();
$afiliacion = $resultAfiliacion->fetch_assoc();
$afiliacionCount = $afiliacion['cantidad'];
$afiliacionSubtotal = $afiliacion['subtotal'];

// 4. LLENAR DATOS EN LA HOJA (Hoja 2)
$sheet2->setCellValue('B16', $aprendicesCount);
$sheet2->setCellValue('C16', $aprendicesSubtotal);
$sheet2->getStyle('C16')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B17', $companerosCount);
$sheet2->setCellValue('C17', $companerosSubtotal);
$sheet2->getStyle('C17')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B18', $maestrosCount);
$sheet2->setCellValue('C18', $maestrosSubtotal);
$sheet2->getStyle('C18')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B19', $seguroCount);
$sheet2->setCellValue('C19', $seguroSubtotal);
$sheet2->getStyle('C19')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B20', 0); // Solicitudes al hno(a), asumido 0 por falta de datos
$sheet2->setCellValue('C20', 0);
$sheet2->getStyle('C20')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B21', $iniciacionCount);
$sheet2->setCellValue('C21', $iniciacionSubtotal);
$sheet2->getStyle('C21')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B22', $exaltacionCount);
$sheet2->setCellValue('C22', $exaltacionSubtotal);
$sheet2->getStyle('C22')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B23', $afiliacionCount);
$sheet2->setCellValue('C23', $afiliacionSubtotal);
$sheet2->getStyle('C23')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$totalMovimientosSubtotal = $iniciacionSubtotal + $exaltacionSubtotal + $afiliacionSubtotal;
$sheet2->setCellValue('B24', $iniciacionCount + $exaltacionCount + $afiliacionCount);
$sheet2->setCellValue('C24', $totalMovimientosSubtotal);
$sheet2->getStyle('C24')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B27', 0); // Capitas y Miembros libres, asumido 0 por falta de datos
$sheet2->setCellValue('C27', 0);
$sheet2->getStyle('C27')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B28', 0); // Total de miembros depositados, asumido 0 por falta de datos
$sheet2->setCellValue('C28', 0);
$sheet2->getStyle('C28')->getNumberFormat()->setFormatCode('"$"#,##0.00');

$sheet2->setCellValue('B29', 0); // Total de miembros dados de baja, asumido 0 por falta de datos
$sheet2->setCellValue('C29', 0);
$sheet2->getStyle('C29')->getNumberFormat()->setFormatCode('"$"#,##0.00');

// 5. AJUSTAR ANCHO DE COLUMNAS (Hoja 2)
$sheet2->getColumnDimension('A')->setWidth(40);
$sheet2->getColumnDimension('B')->setWidth(12);
$sheet2->getColumnDimension('C')->setWidth(15);

// 6. GENERAR Y DESCARGAR ARCHIVO
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Tesoreria_Trim'.$trimestre.'_'.$anio.'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>