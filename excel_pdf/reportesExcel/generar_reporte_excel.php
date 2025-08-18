<?php
require '../vendor/autoload.php';
require_once '../../database/conexion.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener parámetros
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : null;
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : null;
$clave_logia =  isset($_GET['clave_logia']) ? intval($_GET['clave_logia']) : null;

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

// --- Datos de logia y oriente ---
$queryLogia = "SELECT logia, oriente FROM logias WHERE clave_logia = ? LIMIT 1";
$stmtLogia = $conn->prepare($queryLogia);
$stmtLogia->bind_param("s", $clave_logia);
$stmtLogia->execute();
$infoLogia = $stmtLogia->get_result()->fetch_assoc() ?: [];

$nombreLogia = $infoLogia['logia'] ?? 'Logia no Disponible';
$oriente     = $infoLogia['oriente'] ?? 'Zihuatanejo, Gro.';

// Fecha en español tipo: "25 julio 2025"
$mesesEs = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$hoy = new DateTime(); 
$fechaEV = $hoy->format('j') . ' ' . $mesesEs[$hoy->format('n') - 1] . ' ' . $hoy->format('Y') . ' E.\V.';



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

// <- aquí usamos los datos de la tabla logias
$sheet1->mergeCells('A4:I4');
$sheet1->setCellValue('A4', "Resp. Simb. {$nombreLogia}");
$sheet1->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A5:I5');
$sheet1->setCellValue('A5', "Oriente de {$oriente}, a {$fechaEV}");
$sheet1->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->mergeCells('A6:I6');
$sheet1->setCellValue('A6', "Asunto: Informe de Tesorería del Trimestre {$trimestre} de {$anio}");
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
          AND t.clave_logia = ?
          GROUP BY r.id, r.nombre_completo, im.grado_masonico
          ORDER BY r.nombre_completo";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $anio, $mesInicio, $mesFin,$clave_logia);
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

$rowNumber += 2; // Espacio después de la tabla de totales

// =============================
// LISTADO DE HERMANOS DESPLOMADOS
// =============================
$sheet1->mergeCells("A{$rowNumber}:C{$rowNumber}");
$sheet1->setCellValue("A{$rowNumber}", "Listado de los hermanos desplomados:");
$sheet1->getStyle("A{$rowNumber}")->getFont()->setBold(true)->getColor()->setRGB('305496');
$rowNumber++;

$sheet1->setCellValue("A{$rowNumber}", "Nombre del hermano");
$sheet1->setCellValue("C{$rowNumber}", "Grado masónico");
$sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getFont()->setBold(true);
$sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$rowNumber++;

// Consulta hermanos desplomados
$queryDesplomados = "SELECT r.nombre_completo, im.grado_masonico
                     FROM registros r
                     JOIN informacion_masonica im ON r.id = im.id_registro
                     WHERE r.estado_hermano = 3 AND r.clave_logia = ?";
$stmt = $conn->prepare($queryDesplomados);
$stmt->bind_param("i", $clave_logia);
$stmt->execute();
$resultDesplomados = $stmt->get_result();

$desplomadosCount = 0;
while ($row = $resultDesplomados->fetch_assoc()) {
    $sheet1->setCellValue("A{$rowNumber}", ++$desplomadosCount);
    $sheet1->setCellValue("B{$rowNumber}", $row['nombre_completo']);
    $sheet1->setCellValue("C{$rowNumber}", ucfirst($row['grado_masonico']));
    $sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $rowNumber++;
}

if ($desplomadosCount < 3) {
    for ($i = $desplomadosCount; $i < 3; $i++) {
        $sheet1->setCellValue("A{$rowNumber}", $i + 1);
        $sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $rowNumber++;
    }
}

$rowNumber += 1; // Espacio

// =============================
// LISTADO DE HERMANOS DADOS DE BAJA
// =============================
$sheet1->mergeCells("A{$rowNumber}:C{$rowNumber}");
$sheet1->setCellValue("A{$rowNumber}", "Listado de los hermanos dados de baja:");
$sheet1->getStyle("A{$rowNumber}")->getFont()->setBold(true)->getColor()->setRGB('305496');
$rowNumber++;

$sheet1->setCellValue("A{$rowNumber}", "Nombre del hermano");
$sheet1->setCellValue("C{$rowNumber}", "Grado masónico");
$sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getFont()->setBold(true);
$sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$rowNumber++;

// Consulta hermanos dados de baja
$queryBaja = "SELECT r.nombre_completo, im.grado_masonico
              FROM registros r
              JOIN informacion_masonica im ON r.id = im.id_registro
              WHERE r.estado_hermano = 0 AND r.clave_logia = ?";
$stmt = $conn->prepare($queryBaja);
$stmt->bind_param("i", $clave_logia);
$stmt->execute();
$resultBaja = $stmt->get_result();

$bajaCount = 0;
while ($row = $resultBaja->fetch_assoc()) {
    $sheet1->setCellValue("A{$rowNumber}", ++$bajaCount);
    $sheet1->setCellValue("B{$rowNumber}", $row['nombre_completo']);
    $sheet1->setCellValue("C{$rowNumber}", ucfirst($row['grado_masonico']));
    $sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $rowNumber++;
}

if ($bajaCount < 3) {
    for ($i = $bajaCount; $i < 3; $i++) {
        $sheet1->setCellValue("A{$rowNumber}", $i + 1);
        $sheet1->getStyle("A{$rowNumber}:C{$rowNumber}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $rowNumber++;
    }
}

$rowNumber += 2; // Espacio antes de firmas

// =============================
// FIRMAS (con nombres desde BD)
// =============================
$queryFirmas = "SELECT nombre_tesorero, nombre_secretario
                FROM tabla_tesorero_secretario
                WHERE clave_logia = ? AND anio = ? AND trimestre = ?
                LIMIT 1";
$stmtFirmas = $conn->prepare($queryFirmas);
$stmtFirmas->bind_param("iii", $clave_logia, $anio, $trimestre);
$stmtFirmas->execute();
$resFirmas = $stmtFirmas->get_result();
$dataFirmas = $resFirmas->fetch_assoc();

$nombreTesorero  = $dataFirmas['nombre_tesorero']  ?? 'V\. H\. __________________________';
$nombreSecretario = $dataFirmas['nombre_secretario'] ?? 'V\. H\. __________________________';

// Encabezados de firmas
$sheet1->setCellValue("B{$rowNumber}", "Vo. Bo.");
$sheet1->setCellValue("G{$rowNumber}", "Vo. Bo.");
$rowNumber++;

$sheet1->setCellValue("B{$rowNumber}", "Tesorero");
$sheet1->setCellValue("G{$rowNumber}", "Secretario");
$rowNumber += 2;

// Nombres (con línea superior)
$sheet1->setCellValue("B{$rowNumber}", $nombreTesorero);
$sheet1->setCellValue("G{$rowNumber}", $nombreSecretario);

// Estilos firma
$sheet1->getStyle("B".($rowNumber-3).":G{$rowNumber}")
       ->getFont()->getColor()->setRGB('305496');
$sheet1->getStyle("B{$rowNumber}:G{$rowNumber}")
       ->getFont()->setBold(true);

// Línea superior a los nombres
$sheet1->getStyle("B{$rowNumber}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet1->getStyle("G{$rowNumber}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);


// ==============================
// HOJA 2: REPORTE DE MOVIMIENTOS
// ==============================
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Reporte de Movimientos');

// Encabezado (usa datos de logias y fecha que ya calculaste)
$sheet2->mergeCells('A1:C1')->setCellValue('A1', "A.\G.\D.\G.\A.\D.\U.");
$sheet2->mergeCells('A2:C2')->setCellValue('A2', "GRAN LOGIA DEL ESTADO DE GUERRERO");
$sheet2->mergeCells('A3:C3')->setCellValue('A3', "Conferencia de Grandes Logias Regulares de la R.\E.\A.\A.");
$sheet2->mergeCells('A4:C4')->setCellValue('A4', "Resp. Simb. {$nombreLogia}");
$sheet2->mergeCells('A5:C5')->setCellValue('A5', "Oriente de {$oriente}, a {$fechaEV}");
$sheet2->mergeCells('A6:C6')->setCellValue('A6', "Asunto: Informe de Tesorería del Trimestre {$trimestre} de {$anio}");

foreach (range(1,6) as $r) {
  $sheet2->getStyle("A{$r}:C{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
  if ($r <= 3) $sheet2->getStyle("A{$r}")->getFont()->setBold(true);
  if ($r == 1) $sheet2->getStyle("A{$r}")->getFont()->setSize(14);
  if ($r == 2) $sheet2->getStyle("A{$r}")->getFont()->setSize(12);
}

// Intro
$sheet2->setCellValue('A8', 'V.H. Sublime Maestre Resendiz')->getStyle('A8')->getFont()->setBold(true);
$sheet2->setCellValue('A9', 'Gran Tesorero de la Muy Respetable Gr. Log. del Estado de Guerrero.')->getStyle('A9')->getFont()->setBold(true);
$sheet2->setCellValue('A10', 'Presente');
$sheet2->setCellValue('A12', 'Por medio del presente permito informar los movimientos trimestrales administrativos de tesorería generados al interior de esta Respetable Logia Simbólica por el candidato de');

// Monto del informe (usa el total del trimestre si ya lo tienes en $totalGeneral)
$sheet2->setCellValue('A13', '$' . number_format((float)($totalGeneral ?? 0), 2, '.', ',') . ' (CERO PESOS 00/100 M.N.)');

// Encabezados tabla principal
$sheet2->setCellValue('A15', 'NÚMERO DE HERMANOS')->getStyle('A15')->getFont()->setBold(true);
$sheet2->setCellValue('B15', 'Cantidad');
$sheet2->setCellValue('C15', 'Subtotal');
$sheet2->getStyle('B15:C15')->applyFromArray($headerStyle);

// Orden solicitado
$sheet2->setCellValue('A16', 'Número de Hermanos Aprendices');
$sheet2->setCellValue('A17', 'Número de Hermanos Compañeros');
$sheet2->setCellValue('A18', 'Número de Hermanos Maestros');
$sheet2->setCellValue('A19', 'Número de Hermanos miembros libres de la Orden');
$sheet2->setCellValue('A20', 'Número de Hermanos cubiertos por seguro Masónico');
$sheet2->setCellValue('A21', 'Iniciaciones (si la hubo)');
$sheet2->setCellValue('A22', 'Aumentos de Salario (si la hubo)');
$sheet2->setCellValue('A23', 'Exaltaciones (si la hubo)');
$sheet2->setCellValue('A24', 'Regularizaciones o Afiliaciones (si la hubo)');

// ========= Consultas por rango de fechas =========
// Helper: trae cantidad/subtotal por evento en tesoreria
function fetchEvento(mysqli $conn, int $clave_logia, string $desde, string $hasta, string $campo): array {
  $sql = "
    SELECT COUNT(*) AS cantidad, COALESCE(SUM(t.total),0) AS subtotal
    FROM tesoreria t
    WHERE t.clave_logia = ?
      AND t.fecha_registro >= ?
      AND t.fecha_registro <  ?
      AND {$campo} > 0
  ";
  $st = $conn->prepare($sql);
  $st->bind_param('iss', $clave_logia, $desde, $hasta);
  $st->execute();
  return $st->get_result()->fetch_assoc() ?: ['cantidad'=>0,'subtotal'=>0];
}

// Grados: DISTINCT hermanos y subtotal por capitas del trimestre (ajusta a total si prefieres)
$sqlGrado = "
  SELECT COUNT(DISTINCT r.id) AS cantidad, COALESCE(SUM(t.capitas),0) AS subtotal
  FROM tesoreria t
  JOIN registros r ON t.id_hermano = r.id
  JOIN informacion_masonica im ON r.id = im.id_registro
  WHERE t.clave_logia = ?
    AND t.fecha_registro >= ?
    AND t.fecha_registro <  ?
    AND im.grado_masonico = ?
";
$st = $conn->prepare($sqlGrado);
$grado = 'Aprendiz';
$st->bind_param('isss', $clave_logia, $desde, $hasta, $grado); $st->execute();
$apr = $st->get_result()->fetch_assoc() ?: ['cantidad'=>0,'subtotal'=>0];

$grado = 'Compañero';
$st = $conn->prepare($sqlGrado);
$st->bind_param('isss', $clave_logia, $desde, $hasta, $grado); $st->execute();
$cmp = $st->get_result()->fetch_assoc() ?: ['cantidad'=>0,'subtotal'=>0];

$grado = 'Maestro';
$st = $conn->prepare($sqlGrado);
$st->bind_param('isss', $clave_logia, $desde, $hasta, $grado); $st->execute();
$mst = $st->get_result()->fetch_assoc() ?: ['cantidad'=>0,'subtotal'=>0];

// Miembros libres (padron estado_hermano = 2)
$st = $conn->prepare("SELECT COUNT(*) AS n FROM registros WHERE clave_logia = ? AND estado_hermano = 2");
$st->bind_param('i', $clave_logia); $st->execute();
$libresCnt = (int)($st->get_result()->fetch_assoc()['n'] ?? 0);
$libresSub = 0; // si quieres sumar capitas de libres, dime cómo lo registras y lo cambiamos

// Eventos
$seg = fetchEvento($conn, $clave_logia, $desde, $hasta, 't.seguro');          // Seguro
$ini = fetchEvento($conn, $clave_logia, $desde, $hasta, 't.iniciacion');      // Iniciaciones
$aum = fetchEvento($conn, $clave_logia, $desde, $hasta, 't.aumento_salario'); // Aumentos de salario (si NO tienes esta columna, cambia a la que uses)
$exa = fetchEvento($conn, $clave_logia, $desde, $hasta, 't.exaltacion');      // Exaltaciones
$afi = fetchEvento($conn, $clave_logia, $desde, $hasta, 't.afiliacion');      // Afiliaciones
$reg = fetchEvento($conn, $clave_logia, $desde, $hasta, 't.regularizacion');  // Regularizaciones (si no existe, deja 0)

// Regularizaciones o Afiliaciones (sumados)
$regAfiCantidad = ($afi['cantidad'] ?? 0) + ($reg['cantidad'] ?? 0);
$regAfiSubtotal = ($afi['subtotal'] ?? 0) + ($reg['subtotal'] ?? 0);

// ========= Volcado de datos =========
$sheet2->setCellValue('B16', $apr['cantidad']); $sheet2->setCellValue('C16', $apr['subtotal']);
$sheet2->setCellValue('B17', $cmp['cantidad']); $sheet2->setCellValue('C17', $cmp['subtotal']);
$sheet2->setCellValue('B18', $mst['cantidad']); $sheet2->setCellValue('C18', $mst['subtotal']);
$sheet2->setCellValue('B19', $libresCnt);       $sheet2->setCellValue('C19', $libresSub);
$sheet2->setCellValue('B20', $seg['cantidad']); $sheet2->setCellValue('C20', $seg['subtotal']);
$sheet2->setCellValue('B21', $ini['cantidad']); $sheet2->setCellValue('C21', $ini['subtotal']);
$sheet2->setCellValue('B22', $aum['cantidad']); $sheet2->setCellValue('C22', $aum['subtotal']);
$sheet2->setCellValue('B23', $exa['cantidad']); $sheet2->setCellValue('C23', $exa['subtotal']);
$sheet2->setCellValue('B24', $regAfiCantidad);  $sheet2->setCellValue('C24', $regAfiSubtotal);

// Formatos y bordes
foreach (range(16,24) as $r) {
  $sheet2->getStyle("C{$r}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
}
$sheet2->getStyle('A15:C24')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

// Bloque “activos / desplomados / bajas” (opcional si lo sigues mostrando abajo)
$sheet2->setCellValue('A26', 'Total de miembros activos (Capitas y Miembros libres de la Orden)')->getStyle('A26')->getFont()->setBold(true);
$sheet2->setCellValue('B26', 'Cantidad'); $sheet2->setCellValue('C26', 'Subtotal');
$sheet2->getStyle('B26:C26')->applyFromArray($headerStyle);

// Activos = estado 1 (activos) + 2 (libres)
$st = $conn->prepare("SELECT COUNT(*) AS n FROM registros WHERE clave_logia = ? AND estado_hermano IN (1,2)");
$st->bind_param('i', $clave_logia); $st->execute();
$activosCnt = (int)($st->get_result()->fetch_assoc()['n'] ?? 0);

// Subtotal activos: capitas del trimestre
$st = $conn->prepare("SELECT COALESCE(SUM(t.capitas),0) AS sub FROM tesoreria t WHERE t.clave_logia = ? AND t.fecha_registro >= ? AND t.fecha_registro < ?");
$st->bind_param('iss', $clave_logia, $desde, $hasta); $st->execute();
$activosSub = (float)($st->get_result()->fetch_assoc()['sub'] ?? 0);

$sheet2->setCellValue('B27', $activosCnt);
$sheet2->setCellValue('C27', $activosSub);
$sheet2->getStyle('C27')->getNumberFormat()->setFormatCode('"$"#,##0.00');
$sheet2->getStyle('A27:C27')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

$sheet2->setCellValue('A28', 'Total de miembros desplomados')->getStyle('A28')->getFont()->setBold(true);
$sheet2->setCellValue('B28', 'Cantidad'); $sheet2->setCellValue('C28', 'Subtotal');
$sheet2->getStyle('B28:C28')->applyFromArray($headerStyle);

$st = $conn->prepare("SELECT COUNT(*) AS n FROM registros WHERE clave_logia = ? AND estado_hermano = 3");
$st->bind_param('i', $clave_logia); $st->execute();
$desplomadosCnt = (int)($st->get_result()->fetch_assoc()['n'] ?? 0);
$sheet2->setCellValue('B29', $desplomadosCnt);
$sheet2->setCellValue('C29', 0);
$sheet2->getStyle('C29')->getNumberFormat()->setFormatCode('"$"#,##0.00');
$sheet2->getStyle('A29:C29')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

$sheet2->setCellValue('A30', 'Total de miembros dados de baja')->getStyle('A30')->getFont()->setBold(true);
$sheet2->setCellValue('B30', 'Cantidad'); $sheet2->setCellValue('C30', 'Subtotal');
$sheet2->getStyle('B30:C30')->applyFromArray($headerStyle);

$st = $conn->prepare("SELECT COUNT(*) AS n FROM registros WHERE clave_logia = ? AND estado_hermano = 0");
$st->bind_param('i', $clave_logia); $st->execute();
$bajasCnt = (int)($st->get_result()->fetch_assoc()['n'] ?? 0);
$sheet2->setCellValue('B31', $bajasCnt);
$sheet2->setCellValue('C31', 0);
$sheet2->getStyle('C31')->getNumberFormat()->setFormatCode('"$"#,##0.00');
$sheet2->getStyle('A31:C31')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

// Anchos
$sheet2->getColumnDimension('A')->setWidth(54);
$sheet2->getColumnDimension('B')->setWidth(14);
$sheet2->getColumnDimension('C')->setWidth(16);


// 6. GENERAR Y DESCARGAR ARCHIVO
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Tesoreria_Trim'.$trimestre.'_'.$anio.'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>