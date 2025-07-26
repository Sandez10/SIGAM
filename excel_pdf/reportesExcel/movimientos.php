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
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : 2024;

// Crear instancia de Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Establecer propiedades del documento
$spreadsheet->getProperties()
    ->setCreator("Sistema Masónico")
    ->setTitle("Informe de Tesorería Trimestral")
    ->setDescription("Informe trimestral de tesorería de la Logia Benito Juárez 10");

// 1. ENCABEZADO MASÓNICO
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', "A.L. G. D. G. A. D. U.");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 2. TÍTULO DEL DOCUMENTO
$sheet->mergeCells('A2:D2');
$sheet->setCellValue('A2', "Respetable Gran Logia del Estado de Guerrero");
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 3. INFORMACIÓN DE LA LOGIA
$sheet->setCellValue('A3', "AA. LL.: AA.: MMas:");
$sheet->setCellValue('B3', "Confederación de Grandes Logias Regulares de los Estados Unidos Mexicanos");
$sheet->mergeCells('B3:D3');

$sheet->setCellValue('A4', "Ejercicio Masónico");
$sheet->setCellValue('B4', $anio);
$sheet->mergeCells('B4:D4');

$sheet->setCellValue('A5', "Reso Log Simb.");
$sheet->setCellValue('B5', "Benito Juárez 10 No. 12");
$sheet->mergeCells('B5:D5');

// 4. FECHA Y ASUNTO
$sheet->mergeCells('A6:D6');
$sheet->setCellValue('A6', "Oriente de Zihuatanejo, Gro., a 25 julio, 2025 E : V");
$sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->mergeCells('A7:D7');
$sheet->setCellValue('A7', "Asunto: Informe de Tesorería del Trimestre $trimestre de $anio");
$sheet->getStyle('A7')->getFont()->setBold(true);

// 5. SALUDO
$sheet->mergeCells('A8:D8');
$sheet->setCellValue('A8', "S.T.U.");
$sheet->getStyle('A8')->getFont()->setBold(true);

$sheet->mergeCells('A9:D9');
$sheet->setCellValue('A9', "V. II. Sintredo Macial Resendiz");
$sheet->getStyle('A9')->getFont()->setBold(true);

$sheet->mergeCells('A10:D10');
$sheet->setCellValue('A10', "Gran Tesorero de la Nuy Resp . Gr. Log. del Estado de Guerrero.");
$sheet->getStyle('A10')->getFont()->setBold(true);

$sheet->mergeCells('A11:D11');
$sheet->setCellValue('A11', "P r e s e n t e");
$sheet->getStyle('A11')->getFont()->setBold(true);

$sheet->mergeCells('A12:D12');
$sheet->setCellValue('A12', "Por medio del presente me permito informar los movimientos trimestrales administrativos de tesorería generados al interior de esta Respetable Logia Simbólica por la cantidad de:");

// 6. TABLA DE DATOS
$sheet->mergeCells('A13:D13');
$sheet->setCellValue('A13', "$0.00 (CERO PESOS 00/100 M.N.)");
$sheet->getStyle('A13')->getFont()->setBold(true);
$sheet->getStyle('A13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Encabezados de tabla
$sheet->setCellValue('A14', 'Concepto');
$sheet->setCellValue('B14', 'Cantidad');
$sheet->setCellValue('C14', 'Subtotal');
$sheet->setCellValue('D14', '');

// Datos de la tabla
$data = [
    ['Número de hermanos Aprendices', 0, 0],
    ['Número de Hermanos Compañeros', 0, 0],
    ['Número de Hermanos Maestros', 0, 0],
    ['Número de Hermanos miembros libres de la Orden', 0, 0],
    ['Número de Hermanos cubiertos por seguro Masónico', 0, 0],
    ['Iniciaciones (si la hubo)', 0, 0],
    ['Aumentos de Salario (si la hubo)', 0, 0],
    ['Exaltaciones (si la hubo)', 0, 0],
    ['Regularizaciones o Afiliaciones (si la hubo)', 0, 0],
    ['Total', 0, 0]
];

$row = 15;
foreach ($data as $item) {
    $sheet->setCellValue('A'.$row, $item[0]);
    $sheet->setCellValue('B'.$row, $item[1]);
    $sheet->setCellValue('C'.$row, $item[2]);
    $row++;
}

// Estilo para la tabla
$tableStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
    ],
];

$sheet->getStyle('A14:D'.($row-1))->applyFromArray($tableStyle);

// Total de miembros
$sheet->mergeCells('A'.$row.':B'.$row);
$sheet->setCellValue('A'.$row, 'Total de miembros activos (incluir todos los hermanos que cubrieron Cápitas y Miembros Libres de la Orden)');
$sheet->setCellValue('C'.$row, 0);
$row++;

$sheet->mergeCells('A'.$row.':B'.$row);
$sheet->setCellValue('A'.$row, 'Total de miembros desplomados');
$sheet->setCellValue('C'.$row, 0);
$row++;

$sheet->mergeCells('A'.$row.':B'.$row);
$sheet->setCellValue('A'.$row, 'Total de miembros dados de baja');
$sheet->setCellValue('C'.$row, 0);

// Ajustar anchos de columnas
$sheet->getColumnDimension('A')->setWidth(60);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(5);

// 7. GENERAR Y DESCARGAR ARCHIVO
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Informe_Tesoreria_Trim'.$trimestre.'_'.$anio.'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;