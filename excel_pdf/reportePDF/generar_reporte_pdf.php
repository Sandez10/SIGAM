<?php
/**
 * Generador de Reportes de Tesorería - Gran Logia del Estado de Guerrero
 * 
 * Este sistema genera reportes trimestrales de tesorería en formato PDF
 * con información detallada de movimientos por hermano y estado de membresía.
 * 
 * @author Sandez01
 * @version 2.1 Professional
 * @created 2025
 */

// =====================================================================================
// CONFIGURACIÓN INICIAL DEL SISTEMA
// =====================================================================================

// Configuración de PHP para manejo de errores y salida
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Inclusión de dependencias necesarias
require '../vendor/autoload.php';
require_once '../../database/conexion.php';
require 'fpdf186/fpdf.php';

// =====================================================================================
// CONSTANTES DE CONFIGURACIÓN DEL DOCUMENTO
// =====================================================================================

// Configuración de márgenes y espaciado
define('DOCUMENT_MARGIN', 15);
define('TABLE_MARGIN', 20);

// Configuración de fuentes
define('TITLE_FONT_SIZE', 18);
define('SUBTITLE_FONT_SIZE', 14);
define('HEADER_FONT_SIZE', 12);
define('BODY_FONT_SIZE', 10);
define('TABLE_FONT_SIZE', 9);
define('FOOTER_FONT_SIZE', 8);

// Configuración de espaciado
define('ROW_HEIGHT', 8);
define('LINE_SPACING', 6);
define('SECTION_SPACING', 12);

// Paleta de colores institucional mejorada
define('PRIMARY_BLUE', [25, 65, 120]);          // Azul más elegante
define('SECONDARY_BLUE', [173, 192, 225]);      // Azul claro para fondos
define('ACCENT_GOLD', [184, 134, 11]);          // Dorado para acentos
define('WHITE', [255, 255, 255]);               // Blanco
define('BLACK', [0, 0, 0]);                     // Negro
define('GRAY_LIGHT', [248, 249, 250]);          // Gris muy claro
define('GRAY_MEDIUM', [230, 231, 232]);         // Gris medio
define('SUCCESS_GREEN', [40, 167, 69]);         // Verde para totales positivos

// =====================================================================================
// CLASE PRINCIPAL PARA GENERACIÓN DE PDF
// =====================================================================================

class TreasuryReportPDF extends FPDF
{
    private $columnWidths = [];
    private $columnAlignments = [];
    private $reportTitle = '';
    private $reportPeriod = '';
    private $logoPath = '';
    private $watermarkText = '';
    
    /**
     * Constructor de la clase
     */
    public function __construct()
    {
        parent::__construct('P', 'mm', 'Letter');
        $this->SetAutoPageBreak(true, DOCUMENT_MARGIN + 8);
        $this->SetMargins(DOCUMENT_MARGIN, DOCUMENT_MARGIN, DOCUMENT_MARGIN);
        $this->watermarkText = 'CONFIDENCIAL'; //CAMBIAR O ELIMINAR EN UN FUTURO
    }
    
public function Header()
{
    // Establecer posición inicial del encabezado
    $this->SetY(DOCUMENT_MARGIN); // <-- Esto lo sube a donde comienzan los márgenes

    // Degradado superior elegante
    $this->drawGradientHeader();

    // Línea decorativa dorada
    $this->SetDrawColor(...ACCENT_GOLD);
    $this->SetLineWidth(0.8);
    $this->Line(DOCUMENT_MARGIN, $this->GetY() + 8, $this->w - DOCUMENT_MARGIN, $this->GetY() + 8);

    // Marca de agua discreta
    $this->addWatermark();

    // Elimina o reduce este espacio vertical
    $this->Ln(1); // O incluso comenta esta línea si no hace falta espacio extra
}

    
    /**
     * Dibuja un degradado elegante en la parte superior
     */
    private function drawGradientHeader()
    {
        $startColor = PRIMARY_BLUE;
        $endColor = [60, 95, 150]; // Azul más claro
        
        for ($i = 0; $i < 5; $i++) {
            $alpha = $i / 4;
            $r = $startColor[0] + ($endColor[0] - $startColor[0]) * $alpha;
            $g = $startColor[1] + ($endColor[1] - $startColor[1]) * $alpha;
            $b = $startColor[2] + ($endColor[2] - $startColor[2]) * $alpha;
            
            $this->SetFillColor($r, $g, $b);
            $this->Rect(DOCUMENT_MARGIN, DOCUMENT_MARGIN + $i, $this->w - (2 * DOCUMENT_MARGIN), 1, 'F');
        }
    }
    
    /**
     * Añade marca de agua discreta
     */
    private function addWatermark()
    {
        $this->SetFont('Arial', 'B', 45);
        $this->SetTextColor(240, 240, 240);
        $this->SetXY(50, 120);
        $this->Rotate(45);
        $this->Cell(0, 0, utf8_decode($this->watermarkText), 0, 0, 'C');
        $this->Rotate(0);
        $this->SetTextColor(...BLACK);
    }
    
    /**
     * Función para rotar texto
     */
    private function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        
        if ($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }
    
    private $angle = 0;
    
    /**
     * Configuración del pie de página mejorado
     */
    public function Footer()
    {
        $this->SetY(-25);
        
        // Rectángulo de fondo elegante
        $this->SetFillColor(...GRAY_LIGHT);
        $this->Rect(DOCUMENT_MARGIN, $this->GetY() - 2, $this->w - (2 * DOCUMENT_MARGIN), 20, 'F');
        
        // Línea decorativa superior
        $this->SetDrawColor(...PRIMARY_BLUE);
        $this->SetLineWidth(0.5);
        $this->Line(DOCUMENT_MARGIN, $this->GetY() - 1, $this->w - DOCUMENT_MARGIN, $this->GetY() - 1);
        
        $this->Ln(2);
        
        // Información del pie de página en dos líneas
        $this->SetFont('Arial', 'B', FOOTER_FONT_SIZE);
        $this->SetTextColor(...PRIMARY_BLUE);
        
        // Primera línea: Información del documento
        $fechaGeneracion = $this->formatDateToSpanish(date('Y-m-d'));
        $docInfo = sprintf('Documento generado el %s - Página %d', $fechaGeneracion, $this->PageNo());
        $this->Cell(0, 4, utf8_decode($docInfo), 0, 1, 'C');
        
        // Segunda línea: Información de confidencialidad
        $this->SetFont('Arial', 'I', FOOTER_FONT_SIZE - 1);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 4, utf8_decode('Documento confidencial - Solo para uso interno de la logia'), 0, 0, 'C');
        
        // Línea decorativa dorada en la esquina
        $this->SetDrawColor(...ACCENT_GOLD);
        $this->SetLineWidth(1);
        $this->Line($this->w - 30, $this->h - 5, $this->w - DOCUMENT_MARGIN, $this->h - 5);
    }
    
    /**
     * Genera el encabezado institucional mejorado
     */
    public function addInstitutionalHeader($quarter, $year)
    {
        // Conectar a la base de datos
        $db = Database::getInstance();
        $connection = $db->getConnection();
        $lodgeKey = isset($_GET['clave_logia']) ? (int)$_GET['clave_logia'] : null;

        // Obtener información de la logia
        $query = "SELECT logia, oriente FROM logias WHERE clave_logia = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("i", $lodgeKey);
        $stmt->execute();
        $result = $stmt->get_result();

        $logiaData = $result->fetch_assoc();
        $logiaName = $logiaData['logia'] ?? 'Logia Desconocida';
        $oriente = $logiaData['oriente'] ?? 'Oriente Desconocido';

        $this->reportPeriod = "Trimestre $quarter de $year";

        // Caja decorativa para el encabezado
        $this->SetFillColor(...SECONDARY_BLUE);
        $this->SetDrawColor(...PRIMARY_BLUE);

        $startY = 40; // Puedes subir o bajar
        $this->SetY($startY);
        $this->Rect(DOCUMENT_MARGIN, $startY, $this->w - (2 * DOCUMENT_MARGIN), 35, 'FD');

//        $this->Rect(DOCUMENT_MARGIN, $this->GetY(), $this->w - (2 * DOCUMENT_MARGIN), 35, 'FD');
        
        $startY = $this->GetY() + 3;

        // Título principal con estilo mejorado
        $this->SetFont('Arial', 'B', TITLE_FONT_SIZE);
        $this->SetTextColor(...PRIMARY_BLUE);
        $this->SetXY(DOCUMENT_MARGIN + 5, $startY);
        $this->Cell(0, 8, 'A.G.D.G.A.D.U.', 0, 1, 'C');

        // Subtítulo institucional
        $this->SetFont('Arial', 'B', SUBTITLE_FONT_SIZE + 1);
        $this->Cell(0, 7, utf8_decode('GRAN LOGIA DEL ESTADO DE GUERRERO'), 0, 1, 'C');

        // Información adicional con mejor tipografía
        $this->SetFont('Arial', 'B', HEADER_FONT_SIZE - 1);
        $this->SetTextColor(...BLACK);
        $this->Cell(0, 5, utf8_decode('Conferencia de Grandes Logias Regulares de la R.E.A.A.'), 0, 1, 'C');

        $this->SetFont('Arial', '', BODY_FONT_SIZE + 1);
        $this->Cell(0, 5, utf8_decode("Resp. Simb. $logiaName - No. $lodgeKey"), 0, 1, 'C');

        // Fecha y lugar con estilo
        $currentDate = $this->formatDateToSpanish(date('Y-m-d'));
        $this->SetFont('Arial', 'I', BODY_FONT_SIZE);
        $this->Cell(0, 4, utf8_decode("$oriente, Gro. a $currentDate E.V."), 0, 1, 'C');

        // Línea separadora dentro de la caja
        $this->SetDrawColor(...ACCENT_GOLD);
        $this->SetLineWidth(0.5);
        $this->Line(DOCUMENT_MARGIN + 20, $this->GetY() + 1, $this->w - DOCUMENT_MARGIN - 20, $this->GetY() + 1);

        // Asunto del documento destacado
        $this->Ln(3);
        $this->SetFont('Arial', 'B', HEADER_FONT_SIZE + 1);
        $this->SetTextColor(...ACCENT_GOLD);
        $this->Cell(0, 6, utf8_decode("INFORME DE TESORERÍA - {$this->reportPeriod}"), 0, 1, 'C');

        $this->SetY($startY + 37);
        $this->addVerticalSpace(SECTION_SPACING);
    }
    
    /**
     * Crea una sección con título mejorado
     */
    public function addSectionTitle($title, $icon = ''){
        $this->SetFont('Arial', 'B', SUBTITLE_FONT_SIZE);
        $this->SetTextColor(...WHITE);
        
        // Fondo degradado para el título
        $this->SetFillColor(...PRIMARY_BLUE);
        $this->SetDrawColor(...ACCENT_GOLD);
        $this->SetLineWidth(0.5);
        
        // Rectángulo principal
        $this->Rect(DOCUMENT_MARGIN, $this->GetY(), $this->w - (2 * DOCUMENT_MARGIN), 10, 'FD');
        
        // Acento lateral dorado
        $this->SetFillColor(...ACCENT_GOLD);
        $this->Rect(DOCUMENT_MARGIN, $this->GetY(), 4, 10, 'F');
        
        // Texto del título
        $this->SetXY(DOCUMENT_MARGIN + 8, $this->GetY() + 2);
        $titleText = $icon ? "$icon $title" : $title;
        $this->Cell(0, 6, utf8_decode($titleText), 0, 1, 'L');
        
        $this->SetTextColor(...BLACK);
        $this->addVerticalSpace(8);
    }
    
    /**
     * Configura las propiedades de la tabla mejorada
     */
    public function setupTable($widths, $alignments = []){
        $this->columnWidths = $widths;
        $this->columnAlignments = $alignments ?: array_fill(0, count($widths), 'L');
        
        // Centra la tabla en la página completa (no restar márgenes)
        $totalWidth = array_sum($widths);
        $pageWidth = $this->w;
        $offset = max(0, ($pageWidth - $totalWidth) / 2);
        
        $this->SetLeftMargin($offset);
        $this->SetX($offset); // Reposicionar el cursor en el nuevo margen
    }
  
    /**
     * Crea el encabezado de la tabla con diseño profesional
     */
    public function createTableHeader($headers){
        $this->SetFont('Arial', 'B', TABLE_FONT_SIZE + 1);
        $this->SetFillColor(...PRIMARY_BLUE);
        $this->SetTextColor(...WHITE);
        $this->SetDrawColor(...PRIMARY_BLUE);
        $this->SetLineWidth(0.3);
        $this->addTableRow($headers, true);
        $this->SetTextColor(...BLACK);        
        // Línea decorativa debajo del encabezado
        $startX = ($this->w - array_sum($this->columnWidths)) / 2;
        $this->SetDrawColor(...ACCENT_GOLD);
        $this->SetLineWidth(1);
        $this->Line($startX, $this->GetY(), $startX + $totalWidth, $this->GetY());
//        $this->Ln(1);
        $this->SetX($startX);
    }
    
    /**
     * Añade una fila a la tabla con diseño mejorado
     */
    public function addTableRow($data, $isHeader = false, $isTotal = false){
        static $rowFill = false;

        if ($isHeader) {
            $this->SetFont('Arial', 'B', TABLE_FONT_SIZE + 1);
            $this->SetFillColor(...PRIMARY_BLUE);
            $this->SetTextColor(...WHITE);
        } elseif ($isTotal) {
            $this->SetFont('Arial', 'B', TABLE_FONT_SIZE + 1);
            $this->SetFillColor(...SUCCESS_GREEN);
            $this->SetTextColor(...WHITE);
        } else {
            $this->SetFont('Arial', '', TABLE_FONT_SIZE);
            if ($rowFill) {
                $this->SetFillColor(...GRAY_LIGHT);
            } else {
                $this->SetFillColor(...WHITE);
            }
            $this->SetTextColor(...BLACK);
        }

        $rowHeight = $this->calculateRowHeight($data);
        $this->checkPageBreak($rowHeight);

        $totalWidth = array_sum($this->columnWidths);
        $startX = ($this->w - array_sum($this->columnWidths)) / 2;
        $x = $startX;
        $y = $this->GetY();

        // Dibujar bordes y contenido
        $this->SetDrawColor(...GRAY_MEDIUM);
        $this->SetLineWidth(0.1);

        for ($i = 0; $i < count($data); $i++) {
            $width = $this->columnWidths[$i];
            $align = $this->columnAlignments[$i] ?? 'L';

            // Fondo de la celda
            $this->Rect($x, $y, $width, $rowHeight, 'F');
            
            // Borde de la celda
            if (!$isHeader) {
                $this->Rect($x, $y, $width, $rowHeight);
            }

            // Contenido de la celda con padding
            $this->SetXY($x + 1, $y + 1);
            $this->MultiCell($width - 2, LINE_SPACING, utf8_decode($data[$i]), 0, $align);
            $x += $width;
        }

        $this->SetXY($startX, $y + $rowHeight);

        if (!$isHeader && !$isTotal) {
            $rowFill = !$rowFill;
        }
    }
    
    /**
     * Calcula la altura necesaria para una fila
     */
    private function calculateRowHeight($data){
        $maxLines = 1;
        for ($i = 0; $i < count($data); $i++) {
            $lines = $this->calculateLines($this->columnWidths[$i], $data[$i]);
            $maxLines = max($maxLines, $lines);
        }
        return max(ROW_HEIGHT, $maxLines * LINE_SPACING + 2); // +2 para padding
    }
    
    /**
     * Calcula el número de líneas necesarias para un texto
     */
    private function calculateLines($width, $text){
        if (empty($text)) return 1;
        
        $textWidth = $this->GetStringWidth($text);
        $availableWidth = $width - 4; // Padding de 2mm a cada lado
        
        return max(1, ceil($textWidth / $availableWidth));
    }
    
    /**
     * Verifica si es necesario un salto de página
     */
    private function checkPageBreak($height){
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
            
            // Reposiciona la tabla después del salto
            $totalWidth = array_sum($this->columnWidths);
            $pageWidth = $this->w;
            $offset = max(0, ($pageWidth - $totalWidth) / 2);
            $this->SetLeftMargin($offset);
            $this->SetX($offset);

        }
    }
    
    /**
     * Añade espacio vertical
     */
    public function addVerticalSpace($space){
        $this->Ln($space);
    }
    
    /**
     * Convierte fecha al formato español
     */
    private function formatDateToSpanish($date){
        $months = [
            'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
            'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
            'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
            'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
        ];
        
        $timestamp = strtotime($date);
        $englishMonth = date('F', $timestamp);
        $spanishMonth = $months[$englishMonth];
        
        return date('d', $timestamp) . ' de ' . $spanishMonth . ' de ' . date('Y', $timestamp);
    }
    
    /**
     * Añade texto de introducción con diseño mejorado
     */
    public function addIntroductoryText($totalAmount){
        // Caja para el destinatario
        $this->SetFillColor(...GRAY_LIGHT);
        $this->SetDrawColor(...PRIMARY_BLUE);
        $this->Rect(DOCUMENT_MARGIN, $this->GetY(), $this->w - (2 * DOCUMENT_MARGIN), 22, 'FD');
        
        $this->Ln(3);
        $this->SetFont('Arial', 'B', HEADER_FONT_SIZE);
        $this->SetTextColor(...PRIMARY_BLUE);
        $this->Cell(0, 6, utf8_decode('V.H. Sublime Maestro Reséndiz'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', BODY_FONT_SIZE + 1);
        $this->SetTextColor(...BLACK);
        $this->Cell(0, 5, utf8_decode('Gran Tesorero de la Muy Respetable Gr. Log. del Estado de Guerrero'), 0, 1, 'L');
        $this->Cell(0, 5, 'Presente', 0, 1, 'L');
        $this->addVerticalSpace(10);
        
        // Párrafo principal con mejor formato
        $this->SetFont('Arial', '', BODY_FONT_SIZE + 1);
        $this->SetTextColor(...BLACK);
        $formattedAmount = '$' . number_format($totalAmount, 2, '.', ',');        
        // Destacar el monto
        $text = "Por medio del presente, me permito informar los movimientos trimestrales administrativos de tesorería generados al interior de esta Respetable Logia Simbólica, correspondientes al {$this->reportPeriod}, por un monto total de {$formattedAmount}.";
        $this->MultiCell(0, LINE_SPACING + 1, utf8_decode($text), 0, 'J');
    }
    
    /**
     * Añade una caja de resumen ejecutivo
     */
    public function addExecutiveSummary($data){
        $this->addSectionTitle('RESUMEN EJECUTIVO');
        
        // Caja con estadísticas clave
        $this->SetFillColor(245, 248, 255);
        $this->SetDrawColor(...PRIMARY_BLUE);
        $this->Rect(DOCUMENT_MARGIN, $this->GetY(), $this->w - (2 * DOCUMENT_MARGIN), 30, 'FD');
        
        $startY = $this->GetY() + 5;
        
        // Estadísticas en columnas
        $colWidth = ($this->w - (2 * DOCUMENT_MARGIN)) / 3;
        
        $this->SetFont('Arial', 'B', BODY_FONT_SIZE + 1);
        $this->SetTextColor(...PRIMARY_BLUE);
        
        // Columna 1: Total de hermanos
        $this->SetXY(DOCUMENT_MARGIN + 5, $startY);
        $this->Cell($colWidth, 5, 'HERMANOS ACTIVOS', 0, 1, 'C');
        $this->SetFont('Arial', 'B', SUBTITLE_FONT_SIZE);
        $this->SetTextColor(...SUCCESS_GREEN);
        $this->SetX(DOCUMENT_MARGIN + 5);
        $this->Cell($colWidth, 8, $data['active_brothers'] ?? '0', 0, 1, 'C');
        
        // Columna 2: Ingresos totales
        $this->SetXY(DOCUMENT_MARGIN + 5 + $colWidth, $startY);
        $this->SetFont('Arial', 'B', BODY_FONT_SIZE + 1);
        $this->SetTextColor(...PRIMARY_BLUE);
        $this->Cell($colWidth, 5, 'INGRESOS TOTALES', 0, 1, 'C');
        $this->SetFont('Arial', 'B', SUBTITLE_FONT_SIZE);
        $this->SetTextColor(...SUCCESS_GREEN);
        $this->SetX(DOCUMENT_MARGIN + 5 + $colWidth);
        $this->Cell($colWidth, 8, '$' . number_format($data['total_income'] ?? 0, 2), 0, 1, 'C');
        
        // Columna 3: Estado general
        $this->SetXY(DOCUMENT_MARGIN + 5 + (2 * $colWidth), $startY);
        $this->SetFont('Arial', 'B', BODY_FONT_SIZE + 1);
        $this->SetTextColor(...PRIMARY_BLUE);
        $this->Cell($colWidth, 5, 'PERIODO', 0, 1, 'C');
        $this->SetFont('Arial', 'B', HEADER_FONT_SIZE);
        $this->SetTextColor(...ACCENT_GOLD);
        $this->SetX(DOCUMENT_MARGIN + 5 + (2 * $colWidth));
        $this->Cell($colWidth, 8, $this->reportPeriod, 0, 1, 'C');
        
        $this->SetY($startY + 32);
        $this->addVerticalSpace(5);
    }
}

// =====================================================================================
// CLASE PARA MANEJO DE DATOS
// =====================================================================================

class TreasuryDataManager
{
    private $connection;
    
    public function __construct($dbConnection)
    {
        $this->connection = $dbConnection;
    }
    
    /**
     * Obtiene los datos detallados de movimientos por hermano
     */
    public function getBrotherMovements($year, $startMonth, $endMonth, $lodgeKey)
    {
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
        AND t.estado_hermano IN (0, 1, 2, 3)
        AND t.clave_logia = ?
        GROUP BY r.id, r.nombre_completo, im.grado_masonico
        ORDER BY r.nombre_completo";
        
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            throw new Exception("Error en la consulta: " . $this->connection->error);
        }
        
        $stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
        $stmt->execute();
        
        return $stmt->get_result();
    }
    
    /**
     * Obtiene estadísticas detalladas por grado y tipo de movimiento
     */
    public function getBrotherStats($year, $startMonth, $endMonth, $lodgeKey)
    {
        $queries = [
            'aprendices' => [
                'query' => "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.capitas) as subtotal 
                           FROM tesoreria t 
                           JOIN registros r ON t.id_hermano = r.id 
                           JOIN informacion_masonica im ON r.id = im.id_registro 
                           WHERE im.grado_masonico = 'Aprendiz' 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Número de Hermanos Aprendices'
            ],
            'companeros' => [
                'query' => "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.capitas) as subtotal 
                           FROM tesoreria t 
                           JOIN registros r ON t.id_hermano = r.id 
                           JOIN informacion_masonica im ON r.id = im.id_registro 
                           WHERE im.grado_masonico = 'companero' 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Número de Hermanos Compañeros'
            ],
            'maestros' => [
                'query' => "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.capitas) as subtotal 
                           FROM tesoreria t 
                           JOIN registros r ON t.id_hermano = r.id 
                           JOIN informacion_masonica im ON r.id = im.id_registro 
                           WHERE im.grado_masonico = 'maestro   ' 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Número de Hermanos Maestros'
            ],
            'Hermanos Libre de la Orden' => [
                'query' => "SELECT COUNT(DISTINCT r.id) as cantidad, SUM(t.total) as subtotal 
                           FROM tesoreria t 
                           JOIN registros r ON t.id_hermano = r.id 
                           JOIN informacion_masonica im ON r.id = im.id_registro 
                           WHERE t.estado_hermano = 2 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Número de Hermanos miembros libres de la Orden'
            ],
            'seguro' => [
                'query' => "SELECT COUNT(DISTINCT t.id_hermano) as cantidad, SUM(t.seguro) as subtotal 
                           FROM tesoreria t 
                           WHERE t.seguro > 0 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Número de Hermanos cubiertos por seguro Masónico'
            ],
            'iniciacion' => [
                'query' => "SELECT COUNT(*) as cantidad, SUM(t.iniciacion) as subtotal 
                           FROM tesoreria t 
                           WHERE t.iniciacion > 0 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Iniciaciones (si hubo)'
            ],
            'aumento' => [
                'query' => "SELECT COUNT(*) as cantidad, SUM(t.iniciacion) as subtotal 
                           FROM tesoreria t 
                           WHERE t.iniciacion > 0 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Aumentos de Salario (si hubo)'
            ],
            'exaltacion' => [
                'query' => "SELECT COUNT(*) as cantidad, SUM(t.exaltacion) as subtotal 
                           FROM tesoreria t 
                           WHERE t.exaltacion > 0 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Exaltaciones (si hubo)'
            ],
            'afiliacion' => [
                'query' => "SELECT COUNT(*) as cantidad, SUM(t.afiliacion) as subtotal 
                           FROM tesoreria t 
                           WHERE t.afiliacion > 0 
                           AND YEAR(t.fecha_registro) = ? 
                           AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                           AND t.clave_logia = ?",
                'label' => 'Afiliaciones (si hubo)'
            ]
        ];
        
        $results = [];
        foreach ($queries as $key => $info) {
            $stmt = $this->connection->prepare($info['query']);
            $stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $results[$key] = [
                'label' => $info['label'],
                'cantidad' => $data['cantidad'] ?? 0,
                'subtotal' => $data['subtotal'] ?? 0
            ];
        }
        
        return $results;
    }
    
    /**
     * Obtiene estadísticas de membresía
     */
    public function getMembershipStats($year, $startMonth, $endMonth, $lodgeKey)
    {
        $query = "SELECT 
            estado_hermano,
            COUNT(*) AS cantidad
        FROM tesoreria
        WHERE grado IN ('Aprendiz', 'Maestro', 'Companero')
        AND YEAR(fecha_registro) = ?
        AND MONTH(fecha_registro) BETWEEN ? AND ?
        AND estado_hermano IN (0, 1, 3, 2)
        AND clave_logia = ?
        GROUP BY estado_hermano";
        
        $stmt = $this->connection->prepare($query);
        $stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $stats = ['active' => 0, 'collapsed' => 0, 'dismissed' => 0, 'free' => 0];
        
        while ($row = $result->fetch_assoc()) {
            switch ($row['estado_hermano']) {
                case 0: $stats['dismissed'] = $row['cantidad']; break;
                case 1: $stats['active'] = $row['cantidad']; break;
                case 3: $stats['collapsed'] = $row['cantidad']; break;
                case 2: $stats['free'] = $row['cantidad']; break;
            }
        }
        
        return $stats;
    }
    
    /**
     * Obtiene datos para el resumen ejecutivo
     */
    public function getExecutiveSummaryData($year, $startMonth, $endMonth, $lodgeKey)
    {
        // Total de hermanos activos
        $activeQuery = "SELECT COUNT(DISTINCT r.id) as total_active 
                       FROM tesoreria t 
                       JOIN registros r ON t.id_hermano = r.id 
                       WHERE YEAR(t.fecha_registro) = ? 
                       AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                       AND t.estado_hermano IN (1,2,3) 
                       AND t.clave_logia = ?";
        
        $stmt = $this->connection->prepare($activeQuery);
        $stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
        $stmt->execute();
        $activeResult = $stmt->get_result()->fetch_assoc();
        
        // Total de ingresos
        $incomeQuery = "SELECT SUM(t.total) as total_income 
                       FROM tesoreria t 
                       WHERE YEAR(t.fecha_registro) = ? 
                       AND MONTH(t.fecha_registro) BETWEEN ? AND ?
                       AND t.clave_logia = ?";
        
        $stmt = $this->connection->prepare($incomeQuery);
        $stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
        $stmt->execute();
        $incomeResult = $stmt->get_result()->fetch_assoc();
        
        return [
            'active_brothers' => $activeResult['total_active'] ?? 0,
            'total_income' => $incomeResult['total_income'] ?? 0
        ];
    }
}

// =====================================================================================
// FUNCIONES AUXILIARES MEJORADAS
// =====================================================================================

/**
 * Valida los parámetros de entrada con mensajes mejorados
 */
function validateInputParameters()
{
    $errors = [];
    
    $quarter = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : null;
    $year = isset($_GET['anio']) ? (int)$_GET['anio'] : null;
    $lodgeKey = isset($_GET['clave_logia']) ? (int)$_GET['clave_logia'] : null;
    
    if (!$quarter || $quarter < 1 || $quarter > 4) {
        $errors[] = "El trimestre debe ser un número entre 1 y 4 (recibido: " . ($quarter ?? 'null') . ")";
    }
    
    if (!$year || $year < 2020 || $year > date('Y') + 1) {
        $errors[] = "El año debe estar entre 2020 y " . (date('Y') + 1) . " (recibido: " . ($year ?? 'null') . ")";
    }
    
    if (!$lodgeKey || $lodgeKey <= 0) {
        $errors[] = "La clave de logia debe ser un número positivo (recibido: " . ($lodgeKey ?? 'null') . ")";
    }
    
    if (!empty($errors)) {
        ob_end_clean();
        
        // Generar página de error profesional
        generateErrorPage($errors);
        exit;
    }
    
    return [$quarter, $year, $lodgeKey];
}

/**
 * Genera una página de error profesional
 */
function generateErrorPage($errors)
{
    $pdf = new TreasuryReportPDF();
    $pdf->AddPage();
    
    // Título de error
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(220, 53, 69); // Rojo profesional
    $pdf->Cell(0, 10, utf8_decode('ERROR EN LA GENERACIÓN DEL REPORTE'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Lista de errores
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'Se encontraron los siguientes errores:', 0, 1);
    $pdf->Ln(5);
    
    foreach ($errors as $i => $error) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(10, 6, ($i + 1) . '.', 0, 0);
        $pdf->MultiCell(0, 6, utf8_decode($error), 0, 'L');
        $pdf->Ln(2);
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 6, utf8_decode('Por favor, verifique los parámetros y vuelva a intentar.'), 0, 1, 'C');
    
    $pdf->Output('D', 'Error_Reporte_Tesoreria.pdf');
}

/**
 * Calcula los meses del trimestre
 */
function calculateQuarterMonths($quarter)
{
    $startMonth = ($quarter - 1) * 3 + 1;
    $endMonth = $quarter * 3;
    return [$startMonth, $endMonth];
}

/**
 * Formatea el grado masónico con consistencia
 */
function formatMasonicDegree($degree)
{
    $degree = trim(ucfirst(strtolower($degree)));
    $degree = str_replace('companero', 'Compañero', $degree);
    $degree = str_replace('maestro', 'Maestro', $degree);
    return $degree;
}

/**
 * Formatea números monetarios de manera consistente
 */
function formatCurrency($amount, $showCurrency = true)
{
    $formatted = number_format($amount, 2, '.', ',');
    return $showCurrency ? '$' . $formatted : $formatted;
}


function logError($message, $context = [])
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];
    
    error_log(json_encode($logEntry));
}

// =====================================================================================
// PROCESO PRINCIPAL DE GENERACIÓN DEL REPORTE
// =====================================================================================

try {
    // Validar parámetros de entrada
    list($quarter, $year, $lodgeKey) = validateInputParameters();
    list($startMonth, $endMonth) = calculateQuarterMonths($quarter);
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $connection = $db->getConnection();
    $dataManager = new TreasuryDataManager($connection);
    
    // Crear el PDF
    $pdf = new TreasuryReportPDF();
    
    // Obtener datos para resumen ejecutivo
    $executiveSummary = $dataManager->getExecutiveSummaryData($year, $startMonth, $endMonth, $lodgeKey);
        
    // ================================================================================
    // PÁGINA 4: ESTADÍSTICAS Y ANÁLISIS
    // ================================================================================
    //buscar
    $pdf->AddPage();
    $pdf->addInstitutionalHeader($quarter, $year);
    $query = "SELECT SUM(total) AS suma_total
          FROM tesoreria
          WHERE YEAR(fecha_registro) = ?
          AND MONTH(fecha_registro) BETWEEN ? AND ?
          AND clave_logia = ?";
          $stmt = $connection->prepare($query); // <-- usa $query, no $queryDesplomados
          $stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
          $stmt->execute();
          $result = $stmt->get_result();
          $row = $result->fetch_assoc();
          $total = $row['suma_total'] ?? 0;
          $pdf->addIntroductoryText($total);

    
    // SECCIÓN: NÚMERO DE HERMANOS
    $pdf->addSectionTitle('ESTADÍSTICAS DE HERMANOS');
    
    // Obtener estadísticas detalladas por grado
    $brotherStats = $dataManager->getBrotherStats($year, $startMonth, $endMonth, $lodgeKey);
    
    // Configurar tabla de estadísticas
    $statsWidths = [115, 25, 35];
    $statsAlignments = ['L', 'C', 'R'];
    $pdf->setupTable($statsWidths, $statsAlignments);
    
    $pdf->createTableHeader(['Descripción', 'Cantidad', 'Subtotal']);
    
    $totalMovimientos = 0;
    $totalSubtotal = 0;
    
    foreach ($brotherStats as $stat) {
        $subtotalFormatted = formatCurrency($stat['subtotal']);
        $pdf->addTableRow([$stat['label'], $stat['cantidad'], $subtotalFormatted]);
        $totalMovimientos += $stat['cantidad'];
        $totalSubtotal += $stat['subtotal'];
    }
    
    // Añadir fila de solicitudes
    $pdf->addTableRow(['Solicitudes pendientes', 0, formatCurrency(0)]);
    
    // Fila de total
    $totalRow = [
        'TOTAL DE MOVIMIENTOS ACREDITADOS',
        $totalMovimientos,
        formatCurrency($totalSubtotal)
    ];
    $pdf->addTableRow($totalRow, false, true);
    $pdf->AddPage();
    $pdf->addInstitutionalHeader($quarter, $year);
    // SECCIÓN: ESTADO DE MEMBRESÍA
    $pdf->addSectionTitle('ESTADO DE MEMBRESÍA');
    // Obtener estadísticas de membresía
    $membershipStats = $dataManager->getMembershipStats($year, $startMonth, $endMonth, $lodgeKey);
    $membershipWidths = [140, 35];
    $membershipAlignments = ['L', 'C'];
    $pdf->setupTable($membershipWidths, $membershipAlignments);
    
    $pdf->createTableHeader(['Descripción', 'Cantidad']);
    
    // Datos de membresía con cálculos mejorados
    $activeTotal = $membershipStats['active'] + $membershipStats['free'];
    $membershipData = [
        'Miembros activos (cápitas cubiertas + libres de la orden)' => $activeTotal,
        'Miembros desplomados' => $membershipStats['collapsed'],
        'Miembros dados de baja' => $membershipStats['dismissed'],
        'TOTAL DE MIEMBROS REGISTRADOS' => $activeTotal + $membershipStats['collapsed'] + $membershipStats['dismissed']
    ];
    $isTotal = false;
    foreach ($membershipData as $description => $count) {
        $isTotal = (strpos($description, 'TOTAL') === 0);
        $pdf->addTableRow([$description, $count], false, $isTotal);
    }
        // ================================================================================
    // PÁGINA 1: RESUMEN EJECUTIVO Y DETALLE DE MOVIMIENTOS
    // ================================================================================
    
    $pdf->AddPage();
    $pdf->addInstitutionalHeader($quarter, $year);
    
    // Añadir resumen ejecutivo
    $pdf->addExecutiveSummary($executiveSummary);
    
    $pdf->addSectionTitle('DETALLE DE MOVIMIENTOS POR HERMANO');
    
    // Configurar tabla de movimientos con diseño mejorado
    $columnWidths = [12, 55, 22, 18, 18, 18, 18, 18, 22];
    $alignments = ['C', 'L', 'C', 'R', 'R', 'R', 'R', 'R', 'R'];
    $pdf->setupTable($columnWidths, $alignments);
    
    // Encabezados de la tabla
    $headers = [
        'No.', 'Nombre del Hermano', 'Grado', 'Cápitas', 'Seguro',
        'Iniciación', 'Exaltación', 'Afiliación', 'Total'
    ];
    $pdf->createTableHeader($headers);
    
    // Obtener y procesar datos
    $result = $dataManager->getBrotherMovements($year, $startMonth, $endMonth, $lodgeKey);
    
    $rowNumber = 1;
    $totals = [
        'capitas' => 0, 'seguro' => 0, 'iniciacion' => 0,
        'exaltacion' => 0, 'afiliacion' => 0, 'total' => 0
    ];
    
    $hasData = false;
    while ($row = $result->fetch_assoc()) {
        $hasData = true;
        $degree = formatMasonicDegree($row['grado']);
        
        $rowData = [
            $rowNumber,
            $row['nombre_hermano'],
            $degree,
            formatCurrency($row['capitas']),
            formatCurrency($row['seguro']),
            formatCurrency($row['iniciacion']),
            formatCurrency($row['exaltacion']),
            formatCurrency($row['afiliacion']),
            formatCurrency($row['total'])
        ];
        
        $pdf->addTableRow($rowData);
        
        // Acumular totales
        foreach ($totals as $key => &$total) {
            $total += $row[$key];
        }
        
        $rowNumber++;
    }
    
    if (!$hasData) {
        $pdf->addTableRow([
            '0', 'NO HAY MOVIMIENTOS REGISTRADOS EN ESTE PERIODO', 
            'N/A', '$0.00', '$0.00', '$0.00', '$0.00', '$0.00', '$0.00'
        ]);
    }
    
    // Fila de totales mejorada
    $totalRow = [
        '', '', 'TOTAL GENERAL',
        formatCurrency($totals['capitas']),
        formatCurrency($totals['seguro']),
        formatCurrency($totals['iniciacion']),
        formatCurrency($totals['exaltacion']),
        formatCurrency($totals['afiliacion']),
        formatCurrency($totals['total'])
    ];
    $pdf->addTableRow($totalRow, false, true);
    
// ================================================================================
// PÁGINA 2: HERMANOS DESPLOMADOS Y DADOS DE BAJA (JUNTOS)
// ================================================================================
$pdf->AddPage();
$pdf->addInstitutionalHeader($quarter, $year);

// ========== SECCIÓN: HERMANOS DESPLOMADOS ==========
$pdf->addSectionTitle('HERMANOS DESPLOMADOS');

$columnWidths = [15, 105, 45];
$alignments = ['C', 'L', 'C'];
$pdf->setupTable($columnWidths, $alignments);
$headers = ['No.', 'Nombre del Hermano', 'Grado'];
$pdf->createTableHeader($headers);

// Obtener datos de hermanos desplomados
$queryDesplomados = "SELECT 
    r.nombre_completo as nombre_hermano,
    im.grado_masonico as grado
FROM tesoreria t
JOIN registros r ON t.id_hermano = r.id
JOIN informacion_masonica im ON r.id = im.id_registro
WHERE YEAR(t.fecha_registro) = ?
AND MONTH(t.fecha_registro) BETWEEN ? AND ?
AND t.estado_hermano = 3
AND t.clave_logia = ?
GROUP BY r.id, r.nombre_completo, im.grado_masonico
ORDER BY r.nombre_completo";

$stmt = $connection->prepare($queryDesplomados);
$stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
$stmt->execute();
$result = $stmt->get_result();

$rowNumber = 1;
$hasCollapsed = false;
while ($row = $result->fetch_assoc()) {
    $hasCollapsed = true;
    $degree = formatMasonicDegree($row['grado']);
    $pdf->addTableRow([$rowNumber, $row['nombre_hermano'], $degree]);
    $rowNumber++;
}

if (!$hasCollapsed) {
    $pdf->addTableRow(['0', 'NO HAY HERMANOS DESPLOMADOS EN ESTE PERIODO', 'N/A']);
}

// ========== SECCIÓN: HERMANOS DADOS DE BAJA ==========
$pdf->addVerticalSpace(SECTION_SPACING); // Espacio entre secciones
$pdf->addSectionTitle('HERMANOS DADOS DE BAJA');

$pdf->setupTable($columnWidths, $alignments);
$pdf->createTableHeader($headers);

$queryBaja = "SELECT 
    r.nombre_completo as nombre_hermano,
    im.grado_masonico as grado
FROM tesoreria t
JOIN registros r ON t.id_hermano = r.id
JOIN informacion_masonica im ON r.id = im.id_registro
WHERE YEAR(t.fecha_registro) = ?
AND MONTH(t.fecha_registro) BETWEEN ? AND ?
AND t.estado_hermano = 0
AND t.clave_logia = ?
GROUP BY r.id, r.nombre_completo, im.grado_masonico
ORDER BY r.nombre_completo";

$stmt = $connection->prepare($queryBaja);
$stmt->bind_param("iiii", $year, $startMonth, $endMonth, $lodgeKey);
$stmt->execute();
$result = $stmt->get_result();

$rowNumber = 1;
$hasDismissed = false;
while ($row = $result->fetch_assoc()) {
    $hasDismissed = true;
    $degree = formatMasonicDegree($row['grado']);
    $pdf->addTableRow([$rowNumber, $row['nombre_hermano'], $degree]);
    $rowNumber++;
}

if (!$hasDismissed) {
    $pdf->addTableRow(['0', 'NO HAY HERMANOS DADOS DE BAJA EN ESTE PERIODO', 'N/A']);
}

    // ================================================================================
    // PÁGINA 5: NOTAS Y FIRMAS
    // ================================================================================
    $queryFirmas = "SELECT nombre_tesorero, nombre_secretario 
                FROM tabla_tesorero_secretario 
                WHERE clave_logia = ? AND anio = ? AND trimestre = ? 
                LIMIT 1";
    $stmt = $connection->prepare($queryFirmas);
    $stmt->bind_param("iii", $lodgeKey, $year, $quarter);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $nombreTesorero = $data['nombre_tesorero'] ?? 'TESORERO DE LA LOGIA';
    $nombreSecretario = $data['nombre_secretario'] ?? 'SECRETARIO DE LA LOGIA';

// Página de firmas
$pdf->AddPage();
$pdf->addInstitutionalHeader($quarter, $year);

// Consulta nombres ya la hiciste correctamente antes
// Ya tienes: $nombreTesorero, $nombreSecretario

$pdf->addSectionTitle('FIRMAS Y VALIDACIONES');

// Línea base
$signatureY = $pdf->GetY() + 20;

$pdf->SetDrawColor(...GRAY_MEDIUM);
$pdf->SetLineWidth(0.5);

// Firma del tesorero
$pdf->Line(30, $signatureY, 100, $signatureY);
$pdf->SetXY(30, $signatureY + 3);
$pdf->SetFont('Arial', 'B', BODY_FONT_SIZE - 1);
$pdf->Cell(70, 5, utf8_decode($nombreTesorero), 0, 2, 'C');

// Firma del secretario
$pdf->Line(120, $signatureY, 190, $signatureY);
$pdf->SetXY(120, $signatureY + 3);
$pdf->Cell(70, 5, utf8_decode($nombreSecretario), 0, 2, 'C');

$pdf->addVerticalSpace(30);

// Sección de notas
$pdf->addSectionTitle('NOTAS ADICIONALES');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(...BLACK);

$pdf->MultiCell(0, LINE_SPACING, utf8_decode('Los montos reflejados en este reporte corresponden únicamente al período especificado.'), 0, 'L');
$pdf->Ln(2);
$pdf->MultiCell(0, LINE_SPACING, utf8_decode('Las clasificaciones de hermanos se basan en el estado registrado al momento de la consulta.'), 0, 'L');
$pdf->Ln(2);
$pdf->MultiCell(0, LINE_SPACING, utf8_decode('Este documento es de carácter confidencial y de uso exclusivo para la administración de la logia.'), 0, 'L');


    // Fecha de firma
//    $pdf->SetXY(30, $signatureY + 15);
//    $pdf->SetFont('Arial', '', BODY_FONT_SIZE - 1);
//    $pdf->Cell(160, 5, 'Fecha: ____________________', 0, 0, 'C');
    
    // ================================================================================
    // GENERAR Y ENVIAR EL PDF
    // ================================================================================
    
    ob_end_clean();
    
    // Configurar headers para descarga
    header('Content-Type: application/pdf; charset=utf-8');
    header('Content-Disposition: attachment; filename="Reporte_Tesoreria_Profesional_T' . $quarter . '_' . $year . '_Logia_' . $lodgeKey . '.pdf"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    $filename = "Reporte_Tesoreria_Profesional_T{$quarter}_{$year}_Logia_{$lodgeKey}.pdf";
    $pdf->Output('D', $filename);
    
    // Log exitoso
    logError("Reporte generado exitosamente", [
        'quarter' => $quarter,
        'year' => $year,
        'lodge_key' => $lodgeKey,
        'total_amount' => $totals['total'],
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    
    // Log del error
    logError("Error al generar reporte", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quarter' => $quarter ?? null,
        'year' => $year ?? null,
        'lodge_key' => $lodgeKey ?? null
    ]);
    
    // Generar PDF de error
    try {
        generateErrorPage(["Error interno: " . $e->getMessage()]);
    } catch (Exception $pdfError) {
        http_response_code(500);
        die("Error crítico al generar el reporte. Por favor, contacte al administrador del sistema.");
    }
}

exit;
?>