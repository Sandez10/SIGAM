<?php
header('Content-Type: application/json');
require_once '../database/conexion.php';
require_once '../sesiones_conexiones/logia.php';
$datosLogia = obtenerDatosLogia();

if ($datosLogia === null) {
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

// Asignar variables
$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];


$db = Database::getInstance();
$conn = $db->getConnection();

$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : null;
$anio = isset($_GET['anio']) ? intval($_GET['anio']) : null;

if (!$trimestre || !$anio) {
    echo json_encode([
        'success' => false,
        'message' => 'Trimestre y año son obligatorios'
    ]);
    exit;
}

$meses = [
    1 => ['01', '03'], // Enero - Marzo
    2 => ['04', '06'], // Abril - Junio
    3 => ['07', '09'], // Julio - Septiembre
    4 => ['10', '12']  // Octubre - Diciembre
];

if (!isset($meses[$trimestre])) {
    echo json_encode([
        'success' => false,
        'message' => 'Trimestre inválido'
    ]);
    exit;
}

$mesInicio = $meses[$trimestre][0];
$mesFin = $meses[$trimestre][1];

// Consulta para pagos de tesorería
$queryPagos = "SELECT 
                tes.id_hermano,
                tes.nombre_hermano,
                tes.grado,
                tes.logia,
                tes.capitas,
                tes.seguro,
                tes.iniciacion,
                tes.afiliacion,
                tes.exaltacion,
                tes.total
              FROM tesoreria tes
              WHERE YEAR(tes.fecha_registro) = ?
              AND MONTH(tes.fecha_registro) BETWEEN ? AND ?
              AND tes.clave_logia = ?";

// Consulta para hermanos desplomados (estado = 3)
$queryDesplomados = "SELECT nombre_completo, grado_masonico 
                    FROM registros r
                    JOIN informacion_masonica im ON r.id = im.id_registro
                    WHERE r.estado_hermano = 3
                    AND r.logia = (SELECT logia FROM usuarios WHERE usrId = ?)";

// Consulta para hermanos de baja (estado = 0)
$queryBajas = "SELECT nombre_completo, grado_masonico 
              FROM registros r
              JOIN informacion_masonica im ON r.id = im.id_registro
              WHERE r.estado_hermano = 0
              AND r.logia = (SELECT logia FROM usuarios WHERE usrId = ?)";

$response = ['success' => false, 'data' => [], 'desplomados' => [], 'bajas' => []];

// 1. Obtener pagos
$stmt = $conn->prepare($queryPagos);
if ($stmt) {
    $stmt->bind_param("iiis", $anio, $mesInicio, $mesFin, $clave_logia);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['capitas'] = floatval($row['capitas']);
        $row['seguro'] = floatval($row['seguro']);
        $row['iniciacion'] = floatval($row['iniciacion']);
        $row['afiliacion'] = floatval($row['afiliacion']);
        $row['exaltacion'] = floatval($row['exaltacion']);
        $row['total'] = floatval($row['total']);
        $response['data'][] = $row;
    }
    $stmt->close();
    $response['success'] = true;
}

// 2. Obtener hermanos desplomados
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare($queryDesplomados);
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['desplomados'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// 3. Obtener hermanos de baja
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare($queryBajas);
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['bajas'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

echo json_encode($response);
?>