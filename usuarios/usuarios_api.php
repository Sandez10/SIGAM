<?php
//usuarios_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../sesiones_conexiones/sesion_config.php'; // Configuración de sesión segura
require_once '../sesiones_conexiones/logia.php'; // Configuración de sesión segura
require_once '../database/conexion.php';

// Get database connection
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
case 'GET':
        // Manejar la solicitud de un único usuario por separado para mayor claridad
        if (isset($_GET["usrId"]) && !empty($_GET["usrId"])) {
            $usrId = $conn->real_escape_string($_GET["usrId"]);
            $query = "SELECT usrId, usr, rol, estado, logia FROM usuarios WHERE usrId = '$usrId'";
            
            try {
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    // Devolvemos un objeto JSON con una clave 'user' (singular)
                    echo json_encode(['user' => $user]);
                } else {
                    http_response_code(404 );
                    echo json_encode(['error' => 'Usuario no encontrado.']);
                }
            } catch (Exception $e) {
                http_response_code(500 );
                echo json_encode(['error' => 'Error al buscar el usuario: ' . $e->getMessage()]);
            }
            exit; // Salimos para no ejecutar el código de la lista completa
        }

        // Manejar la solicitud de la lista completa de usuarios
        $search = isset($_GET["search"]) ? $conn->real_escape_string($_GET["search"]) : "";
        $role = isset($_GET["role"]) ? $conn->real_escape_string($_GET["role"]) : "";
        $status = isset($_GET["status"]) ? $conn->real_escape_string($_GET["status"]) : "";

        // Seleccionamos explícitamente las columnas para evitar problemas con tipos de datos inesperados
        $query = "SELECT usrId, usr, rol, estado, logia FROM usuarios WHERE 1=1";

        if (!empty($search)) {
            $query .= " AND (usr LIKE '%$search%' OR logia LIKE '%$search%')";
        }
        if (!empty($role)) {
            $query .= " AND rol = '$role'";
        }
        if ($status !== "") {
            $query .= " AND estado = '$status'";
        }

        try {
            $result = $conn->query($query);
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }

            // Calcular estadísticas
            $statsQuery = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as active, MAX(ultimo_cambio) as last_update FROM usuarios");
            $stats = $statsQuery->fetch_assoc();

            echo json_encode([
                'users' => $users,
                'stats' => [
                    'totalUsers' => $stats['total'] ?? 0,
                    'activeUsers' => $stats['active'] ?? 0,
                    'lastUpdate' => $stats['last_update']
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500 );
            echo json_encode(['error' => 'Error al obtener la lista de usuarios: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Create new user
        $data = json_decode(file_get_contents('php://input'), true);
        
        $requiredFields = ['usr', 'clave', 'rol', 'estado'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit;
            }
        }

        // Validate inputs
        if (strlen($data['clave']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            exit;
        }

        try {
            // Check if username already exists
            $usr = $conn->real_escape_string($data['usr']);
            $result = $conn->query("SELECT COUNT(*) as count FROM usuarios WHERE usr = '$usr'");
            $row = $result->fetch_assoc();
            if ($row['count'] > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'El nombre de usuario ya existe']);
                exit;
            }

            $hashedPassword = password_hash($data['clave'], PASSWORD_DEFAULT);
            $rol = $conn->real_escape_string($data['rol']);
            $estado = $conn->real_escape_string($data['estado']);
            $logia = isset($data['logia']) ? $conn->real_escape_string($data['logia']) : '';

            $query = "INSERT INTO usuarios (usr, clave, rol, estado, logia, password_reset_required, ultimo_cambio) 
                     VALUES ('$usr', '$hashedPassword', '$rol', '$estado', '$logia', 1, NOW())";
            
            if ($conn->query($query)) {
                echo json_encode(['message' => 'Usuario creado exitosamente']);
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error creating user: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update user
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['usrId'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de usuario re']);
            exit;
        }

        try {
            $usrId = $conn->real_escape_string($data['usrId']);
            $updates = [];
            $query = "UPDATE usuarios SET ";

            if (isset($data['usr']) && !empty($data['usr'])) {
                $usr = $conn->real_escape_string($data['usr']);
                // Check if new username is unique (excluding current user)
                $result = $conn->query("SELECT COUNT(*) as count FROM usuarios WHERE usr = '$usr' AND usrId != '$usrId'");
                $row = $result->fetch_assoc();
                if ($row['count'] > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Este usuario ya existe']);
                    exit;
                }
                $updates[] = "usr = '$usr'";
            }

            if (isset($data['clave']) && !empty($data['clave'])) {
                if (strlen($data['clave']) < 8) {
                    http_response_code(400);
                    echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']);
                    exit;
                }
                $hashedPassword = password_hash($data['clave'], PASSWORD_DEFAULT);
                $updates[] = "clave = '$hashedPassword'";
                $updates[] = "password_reset_required = 1";
            }

            if (isset($data['rol']) && !empty($data['rol'])) {
                $rol = $conn->real_escape_string($data['rol']);
                $updates[] = "rol = '$rol'";
            }

            if (isset($data['estado']) && $data['estado'] !== '') {
                $estado = $conn->real_escape_string($data['estado']);
                $updates[] = "estado = '$estado'";
            }

            if (isset($data['logia'])) {
                $logia = $conn->real_escape_string($data['logia']);
                $updates[] = "logia = '$logia'";
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'Fallo al intentar actualizar']);
                exit;
            }

            $query .= implode(', ', $updates) . ", ultimo_cambio = NOW() WHERE usrId = '$usrId'";

            if ($conn->query($query)) {
                echo json_encode(['message' => 'Usuario Actualizado exitosamente']);
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error updating user: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete user
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['usrId'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de usuario requerido']);
            exit;
        }

        try {
            $usrId = $conn->real_escape_string($data['usrId']);
            $query = "DELETE FROM usuarios WHERE usrId = '$usrId'";
            
            if ($conn->query($query)) {
                if ($conn->affected_rows > 0) {
                    echo json_encode(['message' => 'User deleted successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Usuario no Encontrado']);
                }
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error deleting user: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}

// Close the connection
$db->close();
?>