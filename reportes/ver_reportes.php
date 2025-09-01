<?php
require_once '../sesiones_conexiones/sesion_config.php'; // Configuración de sesión segura
require_once '../sesiones_conexiones/logia.php'; // Configuración de logia
require_once '../database/conexion.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener datos de la logia
$datosLogia = obtenerDatosLogia();

if ($datosLogia === null) {
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

// Asignar variables
$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];

// CONSULTA PARA OBTENER AÑOS DISPONIBLES
$anios_disponibles = [];
$query = "SELECT DISTINCT YEAR(fecha_registro) AS anio FROM tesoreria ORDER BY anio DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $anios_disponibles[] = $row['anio'];
    }
    $result->free();
} else {
    error_log("Error en consulta de años: " . $conn->error);
    $anios_disponibles = [date('Y')]; // Año actual por defecto
}

// Obtener lista de hermanos
$hermanos = [];
if ($logia_registro) {
    $stmt = $conn->prepare("SELECT tes.id_hermano, tes.nombre_hermano 
                           FROM tesoreria tes
                           INNER JOIN registros r ON r.id = tes.id_hermano
                           WHERE r.logia = ?");
    $stmt->bind_param("s", $logia_registro);
    $stmt->execute();
    $result = $stmt->get_result();
    $hermanos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$rol_usuario = $_SESSION['rol'] ?? 'miembro'; // rol por defecto si no está definido


$permisos = [
    'venerable' => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],
    'secretario' => ['inicio', 'secretaria', 'salir'],
    'tesorero' => ['inicio', 'tesoreria', 'salir']
];
function generarMenu($rol, $permisos) {
    if (!isset($permisos[$rol])) {
        return '<li><span class="block p-2 text-red-500">Permisos no definidos</span></li>';
    }

    $items = [
        'inicio' => ['label' => 'Inicio', 'url' => '../plataforma/'],
        'usuarios' => ['label' => 'Usuarios', 'url' => '../usuarios/'],
        'tesoreria' => ['label' => 'Tesorería', 'url' => '../tesoreria/'],
        'secretaria' => ['label' => 'Secretaría', 'url' => '../secretaria/'],
        'actas' => ['label' => 'Actas', 'url' => '../actas/'],
        'reportes' => ['label' => 'Reportes', 'url' => '../reportes/'],
        'configuracion' => ['label' => 'Configuración', 'url' => '../'],
        'salir' => ['label' => 'Cerrar Sesión', 'url' => '../salir/', 'extra_class' => 'bg-[var(--error-color)] text-white mt-4'],
    ];

    $html = '';
    foreach ($permisos[$rol] as $clave) {
        if (!isset($items[$clave])) continue; // por si se agrega un permiso que no esté definido en items
        $item = $items[$clave];
        $extra = $item['extra_class'] ?? '';
        $html .= '<li><a href="' . $item['url'] . '" class="block p-2 rounded-lg hover:bg-[var(--border-color)] ' . $extra . '">' . $item['label'] . '</a></li>';
    }
    return $html;
}
// Verificar si el rol es admin
if ($_SESSION['rol'] === 'secretario') {
    echo "<script>
        alert('No tienes permiso para acceder a esta página.');
        history.back();
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="description" content="Relación de Pagos para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Relación de Pagos - SIGAM</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/reportes.css"/>
  <link rel="stylesheet" href="../css/container.css"/>
  <link rel="stylesheet" href="../css/menu.css"/>
</head>
<body>
  <!-- Decorative Elements -->
  <div class="fixed top-1/4 right-10 w-16 h-16 rounded-full floating delay-1"></div>
  <div class="fixed bottom-1/4 right-20 w-20 h-20 rounded-full floating"></div>

  <div class="container mx-auto px-4">
    <!-- Sidebar -->
<aside class="sidebar"> 
  <div class="flex items-center gap-3 mb-8">
    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-md">
      <img src="../img/sigam_transparente.png" alt="Logo SIGAM" class="w-10 h-10 object-contain">
    </div>
    <h2 class="text-lg font-bold text-[var(--primary-color)]">SIGAM</h2>
  </div>
  <p class="text-xs opacity-90">Sistema Integral de Gestión Administrativa</p>
  <nav>
    <ul class="space-y-2">
      <?= generarMenu($_SESSION['rol'], $permisos) ?>
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
    <div class="flex items-center gap-3 mb-8">
      <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-md">
        <img src="../img/sigam_transparente.png" alt="Logo SIGAM" class="w-10 h-10 object-contain">
      </div>
      <h2 class="text-lg font-bold text-[var(--primary-color)]">SIGAM</h2>
    </div>
    <p class="text-xs opacity-90 mb-6">Sistema Integral de Gestión Administrativa</p>

    <nav>
      <ul class="space-y-2">
        <?= generarMenu($_SESSION['rol'], $permisos) ?>
      </ul>
    </nav>
  </div>
</div>

<!-- Overlay -->
<div class="menu-overlay"></div>
    <!-- Main Content -->
    <main class="content">
      <!-- Header -->
      <header class="header glass-card">
        <div>
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">Relación de Pagos</h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
        </div>
        <div class="flex gap-3">
          <a href="../plataforma/principal.php" class="btn btn-secondary" title="Volver al inicio">
            <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Regresar
          </a>
        </div>
      </header>

      <!-- Report Section -->
      <div class="glass-card p-6 mt-6">
        <!-- Summary Card -->
        <div class="summary-card glass-card mb-6">
          <div class="summary-item">
            <h4 class="text-sm font-medium text-[var(--text-light)]">Total Pagos</h4>
            <p class="text-2xl font-bold text-[var(--primary-color)]" id="totalPayments">$0.00</p>
          </div>
          <div class="summary-item">
            <h4 class="text-sm font-medium text-[var(--text-light)]">Registros</h4>
            <p class="text-2xl font-bold text-[var(--primary-color)]" id="recordCount">0</p>
          </div>
        </div>

        <!-- Export Toolbar -->
        <div class="glass-card p-4 mb-6 sticky top-20 z-10">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Opciones de Exportación</h3>
            <div class="flex gap-3">
              <button id="exportCsv" class="btn btn-export" aria-label="Exportar a CSV">
                <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                EXCEL
              </button>
              <button id="exportPdf" class="btn btn-export" aria-label="Exportar a PDF">
                <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                PDF
              </button>
            </div>
          </div>
          <div class="progress" id="exportProgress">
            <div class="progress-bar" id="progressBar"></div>
          </div>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div class="group relative">
            <label for="trimestre" class="block mb-1 font-medium">Trimestre</label>
            <select id="trimestre" class="w-full glass-card p-2 rounded" required>
              <option value="">Seleccionar un Trimestre</option>
              <option value="1">Trimestre 1 (Ene-Mar)</option>
              <option value="2">Trimestre 2 (Abr-Jun)</option>
              <option value="3">Trimestre 3 (Jul-Sep)</option>
              <option value="4">Trimestre 4 (Oct-Dic)</option>
            </select>
            <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Seleccione el trimestre del reporte</span>
          </div>
          <div class="group relative">
            <label for="ejercicio_fiscal" class="block mb-1 font-medium">Año</label>
            <select class="w-full glass-card p-2 rounded" name="ejercicio_fiscal" id="ejercicio_fiscal" required aria-label="Selecciona el ejercicio fiscal">
              <option value="" disabled selected>Selecciona un año</option>
              <?php foreach ($anios_disponibles as $anio): ?>
                <option value="<?php echo htmlspecialchars($anio); ?>">
                  <?php echo htmlspecialchars($anio); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Seleccione el año del reporte</span>
          </div>
          <input type="hidden" id="clave_logia" name="clave_logia" value="<?php echo !empty($clave_logia) ? $clave_logia : ''; ?>">
        </div>
        <div class="flex justify-end mb-4">
          <button id="resetFilters" class="btn btn-secondary" aria-label="Restablecer filtros">
            <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 4v6h6M3 15a9 9 0 109-9"/>
            </svg>
            Restablecer
          </button>
        </div>

        <!-- Table -->
        <div class="table-responsive">
          <table id="tablaPagos" class="table dataTable table-striped align-middle text-center w-full">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Grado</th>
                <th>Capitas</th>
                <th>Seguro</th>
                <th>Iniciación</th>
                <th>Afiliación</th>
                <th>Exaltación</th>
                <th>total</th>
              </tr>
            </thead>
            <tbody>
              <!-- Los datos se llenarán dinámicamente con JavaScript -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Toast Notification -->
      <div id="toast" class="toast" role="alert" aria-live="assertive"></div>
    </main>
  </div>
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
  <script src="https://unpkg.com/lucide@0.4.0/dist/umd/lucide.min.js"></script>
  <script src="../js/reporte.js"></script>
  <script src="../js/menu_mobile.js"></script>
</body>
</html>