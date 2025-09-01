<?php
// obtener_registro.php - Versión optimizada

require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';

/**
 * Obtiene datos básicos del registro para listados
 */
function obtenerListadoHermanos($logia_registro) {
    $database = Database::getInstance();
    $conn = $database->getConnection();
    
    $hermanos = [];
    $estados_hermano = [
        0 => 'Baja',
        1 => 'Activo',
        2 => 'Libre de la Orden', 
        3 => 'Desplomado'
    ];
    
    $grados_masonicos = [
        'aprendiz' => 'Aprendiz',
        'companero' => 'Compañero',
        'maestro' => 'Maestro',
        'libre de la orde' => 'Libre de la Orden'
    ];

    $query = "SELECT r.id, r.nombre_completo, r.estado_hermano, im.grado_masonico
              FROM registros r
              INNER JOIN informacion_masonica im ON im.id_registro = r.id
              WHERE r.logia = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $logia_registro);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($hermano = $result->fetch_assoc()) {
        $hermano['estado_textual'] = $estados_hermano[$hermano['estado_hermano']] ?? 'Desconocido';
        $hermano['grado_textual'] = $grados_masonicos[strtolower($hermano['grado_masonico'])] ?? ucfirst($hermano['grado_masonico']);
        $hermanos[] = $hermano;
    }
    
    $stmt->close();
    return $hermanos;
}

/**
 * Obtiene todos los datos de un registro específico
 */
function obtenerDatosRegistroCompleto($id_registro, $logia_registro) {
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        if (!isset($_SESSION['user_id'])) {
            throw new RuntimeException("Acceso no autorizado");
        }

        $id = filter_var($id_registro, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw new InvalidArgumentException("ID de registro no válido");
        }

        $database = Database::getInstance();
        $conn = $database->getConnection();

        // 1. Verificar pertenencia a logia primero (más eficiente)
        $stmt = $conn->prepare("SELECT logia FROM registros WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $registro_logia = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$registro_logia || $registro_logia['logia'] !== $logia_registro) {
            throw new RuntimeException("El registro no pertenece a tu logia");
        }

        // 2. Obtener datos principales 
        $query = "SELECT r.* FROM registros r WHERE r.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $response['data']['registro'] = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        // 3. Obtener información masónica (incluyendo estado)
        $stmt = $conn->prepare("SELECT *, 
            CASE estado_hermano 
                WHEN 0 THEN 'Baja' 
                WHEN 1 THEN 'Activo'
                WHEN 2 THEN 'Libre de la Orden'
                WHEN 3 THEN 'Desplomado'
                ELSE 'Desconocido'
            END as estado_textual
            FROM informacion_masonica WHERE id_registro = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['data']['info_masonica'] = $result->fetch_assoc() ?: [];
        $stmt->close();

        // Obtener grado textual directamente desde la consulta
        if (!empty($response['data']['info_masonica']['grado_masonico'])) {
            $grado = strtolower($response['data']['info_masonica']['grado_masonico']);
            $grados = ['aprendiz' => 'Aprendiz', 'companero' => 'Compañero', 'maestro' => 'Maestro'];
            $response['data']['info_masonica']['grado_textual'] = $grados[$grado] ?? ucfirst($response['data']['info_masonica']['grado_masonico']);
        }

        // [Resto de tus consultas para cargos, past_masters, etc...]
        
        $response['success'] = true;

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Error en obtenerDatosRegistroCompleto: " . $e->getMessage());
    }

    return $response;
}

/**
 * Función para manejar la salida JSON (opcional)
 */
function enviarRespuestaJson($response) {
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode($response);
    exit;
}