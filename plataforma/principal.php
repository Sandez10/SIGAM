<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';

// Redirigir si no hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../");
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Traer usuario y nombre de logia
    $stmt = $conn->prepare("
        SELECT u.usr, u.rol, u.logia, u.password_reset_required, l.logia AS logia_tex 
        FROM usuarios u 
        LEFT JOIN logias l ON u.logia = l.clave_logia 
        WHERE u.usrId = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        session_destroy();
        header("Location: ../");
        exit;
    }

    // Actualizar datos de sesión
    $_SESSION['user_name'] = $user['usr'];
    $_SESSION['rol'] = $user['rol'];
    $_SESSION['password_reset_required'] = $user['password_reset_required'];
    $_SESSION['logia'] = $user['logia'];
    $_SESSION['logia_tex'] = $user['logia_tex'];

    if ((int)$user['password_reset_required'] === 1) {
        header("Location: reset_password.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error en principal.php: " . $e->getMessage());
    header("Location: ../");
    exit;
}
$permisos = [
    'fullmaester' => ['secretaria', 'usuarios', 'tesoreria', 'actas', 'configuracion', 'reportes'],
    'venerable'   => ['secretaria', 'tesoreria', 'actas'],
    'secretario'  => ['secretaria', 'reportes'],
    'tesorero'    => ['tesoreria', 'generar-reportes']
];

$rol_actual = $_SESSION['rol'] ?? '';

// Evitar notices si viniera un rol extraño
if (!isset($permisos[$rol_actual])) {
    $permisos[$rol_actual] = [];
}
// Variable temporal solo para mostrar título en el saludo
switch ($_SESSION['rol']) {
    case 'venerable':
        $titulo_rol = 'Venerable Maestro';
        break;
    case 'secretario':
        $titulo_rol = 'Secretario';
        break;
    case 'tesorero':
        $titulo_rol = 'Tesorero';
        break;
    case 'fullmaester':
        $titulo_rol = 'Gran Fullmaester del Oriente';
        break;
    default:
        $titulo_rol = $_SESSION['rol'];
        break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SIGAM | Panel Principal</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/principal01.css"> 
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    /* Mini detalle visual solo para fullmaester */
    .fullmaester-badge {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #92400e;
      padding: .35rem .75rem;
      border-radius: 9999px;
      font-weight: 600;
      box-shadow: 0 5px 18px rgba(0,0,0,.08), inset 0 1px rgba(255,255,255,.5);
    }
    .floating {
      animation: float 6s ease-in-out infinite;
    }
    .floating.delay-1 { animation-delay: .8s }
    .floating.delay-2 { animation-delay: 1.6s }
    @keyframes float {
      0% { transform: translateY(0) }
      50% { transform: translateY(-10px) }
      100% { transform: translateY(0) }
    }
    /* Pequeño glow a las tarjetas al hover */
    .glass-card { transition: box-shadow .2s ease, transform .2s ease; }
    .glass-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,.08); transform: translateY(-2px); }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px) }
      to { opacity: 1; transform: translateY(0) }
    }
  </style>
</head>
<body class="min-h-screen">

<!-- Navigation -->
<nav class="nav-gradient text-white shadow-xl">
  <div class="container mx-auto px-6 py-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center shadow-lg">
            <img src="../img/sigam_transparente.png" alt="Logo SIGAM" class="w-10 h-10 object-contain">
          </div>
        </div>
        <div>
          <h1 class="text-xl font-bold">SIGAM</h1>
          <p class="text-xs opacity-90">Sistema Integral de Gestión Administrativa Masónica</p>
        </div>
      </div>
      <div class="flex items-center space-x-6">
        <div class="hidden md:flex items-center space-x-2 bg-white/20 px-4 py-2 rounded-full">
          <span class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
          <div class="w-8 h-8 rounded-full bg-white text-indigo-600 flex items-center justify-center font-bold shadow">
            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
          </div>
        </div>
        <a href="../salir/" class="flex items-center space-x-2 bg-white text-indigo-600 px-4 py-2 rounded-lg font-medium shadow hover:shadow-md transition-all">
          <i data-lucide="log-out" class="w-5 h-5"></i>
          <span>Salir</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="container mx-auto px-6 py-12">
  <div class="text-center max-w-3xl mx-auto">
    <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
      Hola, 
      <span class="text-indigo-600">
        <?php echo htmlspecialchars(strtoupper($titulo_rol)); ?>
      </span> de la RSL 
      <span class="text-indigo-600 font-semibold">
        <?php echo htmlspecialchars(strtoupper($_SESSION['logia_tex'])); echo " N° " . htmlspecialchars($_SESSION['logia']);?>
      </span>
    </h1>

    <?php if ($rol_actual === 'fullmaester'): ?>
      <div class="mt-4 flex justify-center">
        <div class="fullmaester-badge">
          <i data-lucide="crown" class="w-4 h-4"></i>
          Acceso total concedido
        </div>
      </div>
    <?php endif; ?>

    <p class="text-xl text-gray-600 mb-8 mt-6">
      Bienvenido al panel de control de SIGAM. ¿Qué deseas hacer hoy?
    </p>

    <div class="flex flex-wrap justify-center gap-4 mb-12">
      <div class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-full flex items-center">
        <i data-lucide="user" class="w-5 h-5 mr-2"></i>
        <span class="text-sm font-medium"><?php echo ucfirst($rol_actual); ?></span>
      </div>
      <div class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-full flex items-center">
        <i data-lucide="clock" class="w-5 h-5 mr-2"></i>
        <span class="text-sm font-medium"><?php echo date('d/m/Y'); ?></span>
      </div>
    </div>
  </div>
</section>

<!-- Features Grid -->
<section class="container mx-auto px-6 pb-16">
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

<!--SECCIÓN SECRETARIA-->
    <?php if (in_array('secretaria', $permisos[$rol_actual])): ?>
      <?php
        $url_secretaria = ($rol_actual === 'venerable') ? '../panel-secretaria/' : '../secretaria/';
      ?>
      <a href="<?php echo $url_secretaria; ?>" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-red-100 text-red-600 mx-auto group-hover:bg-red-600 group-hover:text-white">
          <img src="../icons/2plumas.svg" alt="Secretaría" class="w-6 h-6">
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">
          <?php echo ($rol_actual === 'secretario') ? "Registro de Secretaría" : "Secretaría"; ?>
        </h3>
        <p class="text-gray-600 mb-4">Registro y control de membresía</p>
        <div class="text-red-500 font-medium flex items-center justify-center group-hover:text-red-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2 text-current"></i>
        </div>
      </a>
    <?php endif; ?>

<!--SECCIÓN USUARIOS-->
    <?php if (in_array('usuarios', $permisos[$rol_actual])): ?>
      <!-- Usuarios -->
      <a href="../usuarios/" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-indigo-100 text-indigo-600 mx-auto group-hover:bg-indigo-600 group-hover:text-white">
          <i data-lucide="users" class="w-6 h-6"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Usuarios</h3>
        <p class="text-gray-600 mb-4">Administra usuarios del sistema</p>
        <div class="text-indigo-500 font-medium flex items-center justify-center group-hover:text-indigo-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
        </div>
      </a>
    <?php endif; ?>

<!--SECCIÓN TESORERÍA - SOLO UNA VEZ-->
    <?php if (in_array('tesoreria', $permisos[$rol_actual])): ?>
      <?php
        $url_tesoreria = ($rol_actual === 'venerable') ? '../panel-tesoreria/' : '../tesoreria/';
      ?>
      <a href="<?php echo $url_tesoreria; ?>" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-yellow-100 text-yellow-600 mx-auto group-hover:bg-yellow-600 group-hover:text-white">
          <img src="../icons/crossed-feathers.svg" alt="Tesorería" class="w-6 h-6">
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">
          <?php echo ($rol_actual === 'tesorero') ? "Registro de Tesorería" : "Tesorería"; ?>
        </h3>
        <p class="text-gray-600 mb-4">Registro y Control financiera</p>
        <div class="text-yellow-500 font-medium flex items-center justify-center group-hover:text-yellow-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
        </div>
      </a>
    <?php endif; ?>

<!--SECCIÓN VISUALIZAR ACTAS/REPORTES-->
    <?php if (in_array('secretaria', $permisos[$rol_actual])): ?>
      <?php
        $url_actas = ($rol_actual === 'secretario') ? '../Actas/' : '../reportes-generales/';
        $titulo = ($rol_actual === 'secretario' ) ? "Actas" : "Visualizar Actas/Reportes";
        $descripcion = ($rol_actual === 'secretario') ? "Subir reportes en PDF" : "Mostrar actas/reportes en PDF";
      ?>
      <a href="<?= $url_actas ?>" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-red-100 text-red-600 mx-auto group-hover:bg-red-600 group-hover:text-white">
          <i data-lucide="file-text" class="w-6 h-6"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2"><?= $titulo ?></h3>
        <p class="text-gray-600 mb-4"><?= $descripcion ?></p>
        <div class="text-red-500 font-medium flex items-center justify-center group-hover:text-red-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2 text-current"></i>
        </div>
      </a>
    <?php endif; ?>

<!--SECCIÓN REPORTES-->
    <?php if (in_array('reportes', $permisos[$rol_actual])): ?>
      <a href="../Ver-actas/" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-purple-100 text-purple-600 mx-auto group-hover:bg-purple-600 group-hover:text-white">
          <i data-lucide="file-text" class="w-6 h-6"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Mostrar Actas</h3>
        <p class="text-gray-600 mb-4">Mostrar reportes en PDF/Excel</p>
        <div class="text-purple-500 font-medium flex items-center justify-center group-hover:text-purple-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
        </div>
      </a>
    <?php endif; ?>

<!--SECCIÓN GENERAR REPORTES TESORERÍA-->
    <?php if (in_array('generar-reportes', $permisos[$rol_actual])): ?>
      <a href="../reporte-tesoreria/" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-yellow-100 text-yellow-600 mx-auto group-hover:bg-yellow-600 group-hover:text-white">
          <i data-lucide="bar-chart-3" class="w-6 h-6"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Generar Reportes</h3>
        <p class="text-gray-600 mb-4">Generar reportes de tesorería</p>
        <div class="text-yellow-500 font-medium flex items-center justify-center group-hover:text-yellow-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
        </div>
      </a>
    <?php endif; ?>

<!--SECCIÓN CONFIGURACIÓN-->
    <?php if (in_array('configuracion', $permisos[$rol_actual])): ?>
      <a href="../configuracion/" class="glass-card p-8 text-center group">
        <div class="feature-icon bg-green-100 text-green-600 mx-auto group-hover:bg-green-600 group-hover:text-white">
          <i data-lucide="settings" class="w-6 h-6"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Configuración</h3>
        <p class="text-gray-600 mb-4">Ajustes del sistema</p>
        <div class="text-green-500 font-medium flex items-center justify-center group-hover:text-green-600">
          <span>Acceder</span>
          <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
        </div>
      </a>
    <?php endif; ?>

  </div>
</section>

<!-- Floating Elements -->
<div class="fixed bottom-0 left-0 w-full h-48 bg-gradient-to-t from-indigo-50 to-transparent z-0"></div>
<div class="fixed top-1/4 right-10 w-16 h-16 rounded-full bg-blue-200 opacity-20 floating z-0"></div>
<div class="fixed top-1/3 left-20 w-24 h-24 rounded-full bg-indigo-200 opacity-20 floating delay-1 z-0"></div>
<div class="fixed bottom-1/4 right-1/4 w-20 h-20 rounded-full bg-purple-200 opacity-20 floating delay-2 z-0"></div>

<script>
  lucide.createIcons();

  // Efecto de carga para las tarjetas
  document.querySelectorAll('.glass-card').forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.animation = `fadeInUp 0.5s ease-out ${index * 0.08}s forwards`;
  });
</script>

</body>
</html>
