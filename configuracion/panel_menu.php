<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';

define('BASE_URL', '/sigam/');

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT usr, rol, logia, password_reset_required FROM usuarios WHERE usrId = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        session_destroy();
        header("Location: " . BASE_URL);
        exit;
    }

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

$permisos = [
    'fullmaester' => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],//vista de todas las logias
    'venerable' => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],//Este es para los miembros de alto rango de su misma LOGIA
    'secretario' => ['inicio','secretaria', 'actas', 'salir'], //Este es para los SECRETARIOS
    'tesorero' => ['inicio', 'tesoreria','reportes', 'salir'] //este es para los TESOREROS
];

$menu_items = [
    'Inicio' => ['url' => BASE_URL . 'plataforma/', 'permiso' => 'inicio'],
    'Secretaría' => [
        'permiso' => 'secretaria',
        'submenu' => [
//            'Alta de Tesorero/Secretario' => BASE_URL . 'configuracion/',
            'Registro de Secretaría' => BASE_URL . 'secretaria/',
//            'Lista de Registros' => BASE_URL . 'configuracion/',
            'Alta de Dignatarios/Oficiales' => BASE_URL . 'secretaria/alta/',
//            'Lista de Registros' => BASE_URL . 'secretaria/listado_tes_sec.php',
//            'Visualizar Reportes' => BASE_URL . 'mostrar_reportes/'
//            'Actas' => BASE_URL . 'actas/',
//            'Visualizar Reportes' => BASE_URL . 'reportes-generales/',
//            'Expediente de Hermanos' => BASE_URL . 'reportes/expediente/'
        ]
    ],
    'Usuarios' => ['url' => BASE_URL . 'usuarios/', 'permiso' => 'usuarios'],
    'Tesorería' => ['url' => BASE_URL . 'tesoreria/', 'permiso' => 'tesoreria'],
    'Reportes' => ['url' => BASE_URL . 'reportes-generales/', 'permiso' => 'reportes'],
    'Configuración' => ['url' => BASE_URL . 'configuracion/', 'permiso' => 'configuracion'],
    'Cerrar Sesión' => ['url' => BASE_URL . 'salir/', 'permiso' => 'salir']
];

$current_page = $_SERVER['REQUEST_URI'];

function generate_menu_items($items, $current_page, $rol, $permisos, $is_mobile = false) {
    $output = '';
    $allowed = $permisos[$rol] ?? [];

    foreach ($items as $label => $data) {
        if (!in_array($data['permiso'], $allowed)) continue;

        if (isset($data['submenu'])) {
            $output .= "<li class='relative'>";
            $output .= "<details class='w-full'>";
            $output .= "<summary class='p-2 cursor-pointer hover:bg-[var(--border-color)] rounded-lg'>$label ▾</summary>";
            $output .= "<ul class='ml-4 mt-1 space-y-1'>";
            foreach ($data['submenu'] as $sublabel => $url) {
                $active = strpos($current_page, parse_url($url, PHP_URL_PATH)) !== false ? 'active' : '';
                $output .= "<li><a href=\"$url\" class=\"block p-2 rounded hover:bg-[var(--border-color)] $active\">$sublabel</a></li>";
            }
            $output .= "</ul></details></li>";
        } else {
            $active = strpos($current_page, parse_url($data['url'], PHP_URL_PATH)) !== false ? 'active' : '';
            $classes = "block p-2 rounded-lg hover:bg-[var(--border-color)] $active";
            if ($is_mobile && $label === 'Cerrar Sesión') {
                $classes .= " bg-[var(--error-color)] text-white mt-4";
            }
            $output .= "<li><a href=\"{$data['url']}\" class=\"$classes\">$label</a></li>";
        }
    }
    return $output;
}
?>

<!-- Sidebar (desktop) -->
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

<!-- Botón hamburguesa (mobile) -->
<div class="lg:hidden fixed top-4 left-4 z-50">
    <button id="hamburger-btn" class="hamburger bg-white p-2 rounded-lg shadow-md border" aria-label="Abrir menú" type="button">
        <svg class="w-6 h-6 transition-transform text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<!-- Menú móvil -->
<div id="mobile-menu" class="mobile-menu lg:hidden fixed inset-0 z-40 transform -translate-x-full transition-transform duration-300 ease-in-out">
    <div class="bg-white h-full w-80 max-w-sm shadow-lg">
        <div class="p-6 pt-16 relative h-full overflow-y-auto">
            <button id="close-menu-btn" class="absolute top-4 right-4 p-2 close-menu hover:bg-gray-100 rounded-lg" aria-label="Cerrar menú" type="button">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
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
</div>

<!-- Overlay -->
<div id="menu-overlay" class="menu-overlay fixed inset-0 bg-black bg-opacity-50 z-30 opacity-0 invisible transition-all duration-300 ease-in-out"></div>

<!-- Estilos CSS adicionales -->
<style>
/* Estilos para el menú móvil */
.mobile-menu.active {
    transform: translateX(0);
}

.menu-overlay.active {
    opacity: 1;
    visibility: visible;
}

.hamburger.is-active svg {
    transform: rotate(90deg);
}

body.menu-open {
    overflow: hidden;
}
</style>

<!-- Script de control del menú mejorado -->
<script>
// Función para inicializar el menú
function initializeHamburgerMenu() {
    console.log('Inicializando menú hamburguesa...');
    
    // Seleccionar elementos
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const closeMenuBtn = document.getElementById('close-menu-btn');
    const overlay = document.getElementById('menu-overlay');
    const body = document.body;

    // Verificar que todos los elementos existen
    if (!hamburgerBtn) {
        console.error('Botón hamburguesa no encontrado');
        return false;
    }
    if (!mobileMenu) {
        console.error('Menú móvil no encontrado');
        return false;
    }
    if (!closeMenuBtn) {
        console.error('Botón cerrar no encontrado');
        return false;
    }
    if (!overlay) {
        console.error('Overlay no encontrado');
        return false;
    }

    console.log('Todos los elementos encontrados, configurando eventos...');

    // Función para abrir el menú
    function openMenu() {
        console.log('Abriendo menú...');
        mobileMenu.classList.add('active');
        overlay.classList.add('active');
        hamburgerBtn.classList.add('is-active');
        body.classList.add('menu-open');
    }

    // Función para cerrar el menú
    function closeMenu() {
        console.log('Cerrando menú...');
        mobileMenu.classList.remove('active');
        overlay.classList.remove('active');
        hamburgerBtn.classList.remove('is-active');
        body.classList.remove('menu-open');
    }

    // Remover event listeners existentes para evitar duplicados
    const newHamburgerBtn = hamburgerBtn.cloneNode(true);
    const newCloseMenuBtn = closeMenuBtn.cloneNode(true);
    const newOverlay = overlay.cloneNode(true);
    
    hamburgerBtn.parentNode.replaceChild(newHamburgerBtn, hamburgerBtn);
    closeMenuBtn.parentNode.replaceChild(newCloseMenuBtn, closeMenuBtn);
    overlay.parentNode.replaceChild(newOverlay, overlay);

    // Event listeners en los nuevos elementos
    newHamburgerBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Click en hamburguesa');
        openMenu();
    });

    newCloseMenuBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Click en cerrar');
        closeMenu();
    });

    newOverlay.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Click en overlay');
        closeMenu();
    });

    // Cerrar con tecla Escape
    function handleEscape(e) {
        if (e.key === 'Escape') {
            closeMenu();
        }
    }
    
    // Remover listener anterior si existe
    document.removeEventListener('keydown', handleEscape);
    document.addEventListener('keydown', handleEscape);

    console.log('Menú hamburguesa inicializado correctamente');
    return true;
}

// Intentar inicializar inmediatamente
if (document.readyState === 'loading') {
    // DOM aún cargando
    document.addEventListener('DOMContentLoaded', initializeHamburgerMenu);
} else {
    // DOM ya cargado
    setTimeout(initializeHamburgerMenu, 100);
}

// También intentar cuando la ventana se carga completamente
window.addEventListener('load', function() {
    setTimeout(initializeHamburgerMenu, 200);
});
</script>