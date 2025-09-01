<?php
/**
 * /sigam/excel_pdf/reportePDF/descargar_expediente.php
 * Genera el PDF del expediente de un hermano (id por GET).
 * Tablas usadas: registros, informacion_masonica
 */

ob_start();                         // evita "headers already sent"
error_reporting(E_ALL);
ini_set('display_errors', 1);

$ROOT = dirname(__DIR__, 2);        // C:\xampp\htdocs\SIGAM
require_once $ROOT . '/sesiones_conexiones/sesion_config.php';
require_once $ROOT . '/database/conexion.php';
require 'fpdf186/fpdf.php';

/* ============================================================
   Helpers
   ============================================================ */

function db() { return Database::getInstance()->getConnection(); }

/** Convierte UTF-8 -> ISO-8859-1 (FPDF) sin warnings (reemplaza utf8_decode) */
function enc($s): string {
    if ($s === null) return '';
    return mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');
}

/** Fecha YYYY-MM-DD -> dd/mm/YYYY */
function fechaMX($v){ return ($v && $v!=='0000-00-00') ? date('d/m/Y', strtotime($v)) : ''; }

/** 0/1/2/3 -> texto */
function mapEstado(?int $estado): string {
  $map = [0=>'Baja', 1=>'Activo', 2=>'Libre de la Orden', 3=>'Desplomado'];
  return $map[$estado] ?? 'No especificado';
}

/* ============================================================
   Validación de entrada
   ============================================================ */

if (empty($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
  http_response_code(400); die('ID de hermano no válido.');
}
$id = (int)$_GET['id'];

/* ============================================================
   Cargar expediente (ajustado a tu esquema)
   ============================================================ */

$sql = "SELECT 
          r.id, r.nombre_completo, r.domicilio, r.nacionalidad, r.estado_civil,
          r.ocupacion, r.religion, r.numero_contacto, r.numero_emergencia,
          r.correo_electronico, r.estado_hermano, r.fotografia,
          r.logia, r.oriente, r.clave_logia,
          im.grado_masonico, im.tipo_ingreso,
          im.fecha_inicio, im.fecha_aumento_salario,
          im.fecha_exaltacion, im.fecha_afiliacion,
          r.observaciones
        FROM registros r
        LEFT JOIN informacion_masonica im ON im.id_registro = r.id
        WHERE r.id = ?
        LIMIT 1";
$stmt = db()->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$exp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exp) { http_response_code(404); die('No se encontró el hermano.'); }

/* ============================================================
   PDF
   ============================================================ */

class PDF extends FPDF {
  private $logia; private $oriente;
  function __construct($logia, $oriente){ parent::__construct('P','mm','Letter'); $this->logia=$logia; $this->oriente=$oriente; }
  function Header(){
    $this->SetFont('Arial','B',10);
    $this->Cell(0,6,enc("A.'.G.'.D.'.G.'.A.'.D.'.U'."),0,1,'C');
    $this->SetFont('Arial','B',12);
    $this->Cell(0,7,enc('EXPEDIENTE MASÓNICO INDIVIDUAL'),0,1,'C');
    $this->SetFont('Arial','',9);
    $this->Cell(0,5,enc('Log.˙. '.$this->logia.'   —   Or.˙. '.$this->oriente),0,1,'C');
    $this->Line(10, 28, 206, 28);
    $this->Ln(4);
  }
  function Footer(){
    $this->SetY(-15);
    $this->SetFont('Arial','I',8);
    $this->Cell(0,10,enc('Página ').$this->PageNo().'/{nb}',0,0,'C');
  }
  function barra($t){
    $this->SetFillColor(23,92,145);
    $this->SetTextColor(255,255,255);
    $this->SetFont('Arial','B',10);
    $this->Cell(0,7,enc($t),0,1,'L',true);
    $this->SetTextColor(0,0,0); $this->Ln(1);
  }
  function campo($label,$value){
    $this->SetFont('Arial','B',9);  $this->Cell(55,6,enc($label),0,0,'L');
    $this->SetFont('Arial','',9);   $this->MultiCell(0,6,enc((string)$value),0,'L');
  }
}

$pdf = new PDF($exp['logia'] ?? '', $exp['oriente'] ?? '');
$pdf->AliasNbPages();
$pdf->SetMargins(10,10,10);
$pdf->AddPage();

/* Fotografía (longblob) a la derecha si existe */
if (!empty($exp['fotografia'])) {
    $imgData = $exp['fotografia'];

    // Guardar con extensión JPG
    $tmp = tempnam(sys_get_temp_dir(), 'foto_') . '.jpg';
    file_put_contents($tmp, $imgData);

    $y = $pdf->GetY();
    $pdf->Image($tmp, 170, $y, 30, 0, 'JPG'); // fuerza a JPG

    @unlink($tmp);
}


/* Información general */
$pdf->barra('INFORMACIÓN GENERAL');
$pdf->campo('Nombre completo:',      $exp['nombre_completo'] ?? '');
$pdf->campo('Domicilio:',            $exp['domicilio'] ?? '');
$pdf->campo('Nacionalidad:',         $exp['nacionalidad'] ?? '');
$pdf->campo('Estado civil:',         $exp['estado_civil'] ?? '');
$pdf->campo('Ocupación:',            $exp['ocupacion'] ?? '');
$pdf->campo('Religión:',             $exp['religion'] ?? '');
$pdf->campo('Teléfono:',             $exp['numero_contacto'] ?? '');
$pdf->campo('Teléfono emergencia:',  $exp['numero_emergencia'] ?? '');
$pdf->campo('Correo electrónico:',   $exp['correo_electronico'] ?? '');
$pdf->Ln(1);

/* Información masónica */
$pdf->barra('INFORMACIÓN MASÓNICA');
$pdf->campo('Grado masónico:',       $exp['grado_masonico'] ?? '');
$pdf->campo('Tipo de ingreso:',      $exp['tipo_ingreso'] ?? '');
$pdf->campo('Estado:',               mapEstado(isset($exp['estado_hermano']) ? (int)$exp['estado_hermano'] : null));
$pdf->campo('Fecha de inicio:',      fechaMX($exp['fecha_inicio'] ?? ''));
$pdf->campo('Fecha de aumento de salario:', fechaMX($exp['fecha_aumento_salario'] ?? ''));
$pdf->campo('Fecha de exaltación:',  fechaMX($exp['fecha_exaltacion'] ?? ''));
$pdf->campo('Fecha de afiliación:',  fechaMX($exp['fecha_afiliacion'] ?? ''));
$pdf->Ln(1);

/* Observaciones */
if (!empty($exp['observaciones'])) {
  $pdf->barra('OBSERVACIONES');
  $pdf->SetFont('Arial','',9);
  $pdf->MultiCell(0,6,enc($exp['observaciones']),0,'L');
  $pdf->Ln(1);
}

/* Ne-varietur */
$pdf->barra('NE-VARIETUR');
$pdf->Cell(0,20,'',1,1);

/* Salida */
$fname = 'Expediente_' . preg_replace('/[^a-zA-Z0-9]/','_', $exp['nombre_completo'] ?? ('ID_'.$id)) . '.pdf';
ob_end_clean();                    // limpia cualquier salida previa
$pdf->Output('I', $fname);
exit;
