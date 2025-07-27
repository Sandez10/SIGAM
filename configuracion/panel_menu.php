<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';

// Definir la URL base para rutas consistentes
define('BASE_URL', '/sigam/'); // Ajusta según la raíz de tu proyecto

// Redirigir si no hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    if (!$conn) {
        error_log("Error: No se pudo conectar a la base de datos en panel_menu.php");
        header("Location: " . BASE_URL);
        exit;
    }

    $stmt = $conn->prepare("SELECT usr, rol, logia, password_reset_required FROM usuarios WHERE usrId = ?");
    if (!$stmt) {
        error_log("Error: No se pudo preparar la consulta en panel_menu.php: " . $conn->error);
        header("Location: " . BASE_URL);
        exit;
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("Error: No se encontró el usuario con usrId {$_SESSION['user_id']} en panel_menu.php");
        session_destroy();
        header("Location: " . BASE_URL);
        exit;
    }

    // Actualizar datos de sesión
    $_SESSION['user_name'] = $user['usr'];
    $_SESSION['rol'] = $user['rol'];
    $_SESSION['password_reset_required'] = $user['password_reset_required'];
    $_SESSION['logia'] = $user['logia'];

    if ($user['password_reset_required'] == 1) {
        header("Location: " . BASE_URL . "reset_password.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error en panel_menu.php: " . $e->getMessage());
    header("Location: " . BASE_URL);
    exit;
}

// Definir permisos por rol
$permisos = [
    'superadmin' => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],
    'administrador' => ['inicio', 'actas', 'salir'],
    'miembro' => ['inicio', 'tesoreria', 'actas', 'salir']
];

// Definir elementos del menú con sus URLs
$menu_items = [
    'Inicio' => ['url' => BASE_URL . 'plataforma/', 'permiso' => 'inicio'],
    'Secretaría' => ['url' => BASE_URL . 'secretaria/', 'permiso' => 'secretaria'],
    'Usuarios' => ['url' => BASE_URL . 'usuarios/', 'permiso' => 'usuarios'],
    'Tesorería' => ['url' => BASE_URL . 'tesoreria/', 'permiso' => 'tesoreria'],
    'Actas' => ['url' => BASE_URL . 'actas/', 'permiso' => 'actas'],
    'Reportes' => ['url' => BASE_URL . 'reportes/', 'permiso' => 'reportes'],
    'Configuración' => ['url' => BASE_URL . 'configuracion/', 'permiso' => 'configuracion'],
    'Cerrar Sesión' => ['url' => BASE_URL . 'salir/', 'permiso' => 'salir']
];

// Obtener la página actual para resaltar el enlace activo
$current_page = $_SERVER['REQUEST_URI'];

// Función para generar los elementos del menú según permisos
function generate_menu_items($items, $current_page, $rol_actual, $permisos, $is_mobile = false) {
    $output = '';
    $allowed_sections = isset($permisos[$rol_actual]) ? $permisos[$rol_actual] : [];
    
    foreach ($items as $label => $data) {
        if (in_array($data['permiso'], $allowed_sections)) {
            $is_active = (strpos($current_page, parse_url($data['url'], PHP_URL_PATH)) !== false) ? 'active' : '';
            $classes = "block p-2 rounded-lg hover:bg-[var(--border-color)] $is_active";
            if ($is_mobile && $label === 'Cerrar Sesión') {
                $classes .= " bg-[var(--error-color)] text-white mt-4";
            }
            $output .= "<li><a href=\"{$data['url']}\" class=\"$classes\">$label</a></li>";
        }
    }
    return $output;
}
?>

<!-- Versión Desktop (visible en pantallas grandes) -->
<aside class="sidebar hidden lg:block">
    <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-md">
            <img src="<?php echo BASE_URL; ?>img/sigam_transparente.png" alt="Logo SIGAM" class="w-10 h-10 object-contain">
        </div>
        <h2 class="text-lg font-bold text-[var(--primary-color)]">SIGAM</h2>
    </div>
    <p class="text-xs opacity-90">Sistema Integral de Gestión Administrativa</p>
    <nav>
        <ul class="space-y-2">
            <?php echo generate_menu_items($menu_items, $current_page, $_SESSION['rol'], $permisos); ?>
        </ul>
    </nav>
</aside>

<!-- Botón Hamburguesa -->
<div class="lg:hidden fixed top-4 left-4 z-40">
    <button class="hamburger" title="Abrir menú" aria-label="Abrir menú">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<!-- Menú Móvil -->
<div id="mobile-menu" class="mobile-menu lg:hidden">
    <div class="p-6 pt-16 relative">
        <button class="absolute top-4 right-4 p-2 close-menu" aria-label="Cerrar menú">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <!-- Contenido -->
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-md">
                <img src="<?php echo BASE_URL; ?>img/sigam_transparente.png" alt="Logo SIGAM" class="w-10 h-10 object-contain">
            </div>
            <h2 class="text-lg font-bold text-[var(--primary-color)]">SIGAM</h2>
        </div>
        <p class="text-xs opacity-90 mb-6">Sistema Integral de Gestión Administrativa</p>
        <nav>
            <ul class="space-y-2">
                <?php echo generate_menu_items($menu_items, $current_page, $_SESSION['rol'], $permisos, true); ?>
            </ul>
        </nav>
    </div>
</div>

<!-- Overlay -->
<div class="menu-overlay"></div>

<style>
    .mobile-menu {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: white;
        z-index: 30;
    }
    .mobile-menu.active {
        display: block;
    }
    .menu-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 20;
    }
    .menu-overlay.active {
        display: block;
    }
    .active {
        background-color: var(--primary-color);
        color: white;
        font-weight: bold;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const hamburger = document.querySelector('.hamburger');
        const mobileMenu = document.getElementById('mobile-menu');
        const closeMenu = document.querySelector('.close-menu');
        const overlay = document.querySelector('.menu-overlay');

        if (hamburger && mobileMenu && closeMenu && overlay) {
            hamburger.addEventListener('click', () => {
                mobileMenu.classList.add('active');
                overlay.classList.add('active');
            });
            closeMenu.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                overlay.classList.remove('active');
            });
            overlay.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                overlay.classList.remove('active');
            });
        }
    });
</script>