<?php
require_once 'sesion_config.php';
require_once '../database/conexion.php';

// Verificar sesión
if (!isset($_SESSION['temp_user']) && !isset($_SESSION['user_id'])) {
    header("Location: ../?error=Acceso no autorizado");
    exit;
}

$user_id = $_SESSION['temp_user']['usrId'] ?? $_SESSION['user_id'];
$required_change = isset($_SESSION['temp_user']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Obtener y validar datos
        $current_pass = trim($_POST['current_password'] ?? '');
        $new_pass = trim($_POST['new_password'] ?? '');
        $confirm_pass = trim($_POST['confirm_password'] ?? '');

        // Validaciones básicas
        if (empty($new_pass) || empty($confirm_pass)) {
            throw new Exception("Todos los campos son obligatorios");
        }

        if ($new_pass !== $confirm_pass) {
            throw new Exception("Las contraseñas no coinciden");
        }

        if (strlen($new_pass) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres");
        }

        // Validar complejidad
        if (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
            throw new Exception("Debe contener mayúsculas, minúsculas y números");
        }

        // Si no es cambio requerido, validar contraseña actual
        if (!$required_change) {
            if (empty($current_pass)) {
                throw new Exception("Debes ingresar tu contraseña actual");
            }

            $stmt = $conn->prepare("SELECT clave FROM usuarios WHERE usrId = ?");
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($current_pass, $user['clave'])) {
                throw new Exception("Contraseña actual incorrecta");
            }

            // Verificar que no sea la misma contraseña
            if (password_verify($new_pass, $user['clave'])) {
                throw new Exception("La nueva contraseña debe ser diferente");
            }
        }

        // Actualizar contraseña
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET clave = ?, password_reset_required = 0, ultimo_cambio = NOW() WHERE usrId = ?");
        $stmt->bind_param("ss", $hashed_password, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar la contraseña");
        }

        // Registrar en archivo log
        $log_message = sprintf(
            "[%s] Usuario %s cambió su contraseña. IP: %s\n",
            date('Y-m-d H:i:s'),
            $user_id,
            $_SERVER['REMOTE_ADDR']
        );
        file_put_contents('../logs/password_changes.log', $log_message, FILE_APPEND);

        // Completar login si era cambio requerido
        if ($required_change) {
            $_SESSION['user_id'] = $_SESSION['temp_user']['usrId'];
            $_SESSION['user_name'] = $_SESSION['temp_user']['usr'];
            $_SESSION['rol'] = $_SESSION['temp_user']['rol'];
            unset($_SESSION['temp_user']);
        }

        $_SESSION['success'] = "Contraseña actualizada exitosamente";
        header("Location: ../plataforma/principal.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: cambiar_contrasena.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --danger: #ef233c;
            --success: #2ecc71;
            --gray: #f8f9fa;
        }
        
        body {
            background-color: var(--gray);
            font-family: 'Segoe UI', sans-serif;
        }
        
        .password-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #777;
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #fdecea;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="password-container">
            <h2 class="text-center mb-4"><?= $required_change ? 'Establecer nueva contraseña' : 'Cambiar contraseña' ?></h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form id="formCambioContrasena" method="POST">
                <?php if (!$required_change): ?>
                    <div class="form-group">
                        <label for="current_password">Contraseña actual</label>
                        <div class="input-with-icon">
                            <input type="password" id="current_password" name="current_password" required>
                            <i class="fas fa-eye toggle-password" data-target="current_password"></i>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="new_password">Nueva contraseña</label>
                    <div class="input-with-icon">
                        <input type="password" id="new_password" name="new_password" required 
                               pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                        <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                    </div>
                    <small class="text-muted">Mínimo 8 caracteres con mayúsculas, minúsculas y números</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar nueva contraseña</label>
                    <div class="input-with-icon">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span class="submit-text">Cambiar contraseña</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Mostrar/ocultar contraseña
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Validación básica del formulario
        document.getElementById('formCambioContrasena').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password');
            const confirmPass = document.getElementById('confirm_password');
            
            if (newPass.value !== confirmPass.value) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
            }
        });
    </script>
</body>
</html>