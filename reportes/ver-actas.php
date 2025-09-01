<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Verificar sesión
if (!isset($_SESSION['user_name'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No hay sesión de usuario activa']);
    exit;
}

// Crear tabla reportes si no existe
try {
    $result = $conn->query("SHOW TABLES LIKE 'reportes'");
    if ($result->num_rows == 0) {
        $createTableSQL = "CREATE TABLE reportes (
            id_reportes INT AUTO_INCREMENT PRIMARY KEY,
            usuario_registro INT NOT NULL,
            logia VARCHAR(50) NOT NULL,
            clave_logia VARCHAR(50) NOT NULL,
            evidencia LONGBLOB NOT NULL,
            tipo_evidencia VARCHAR(50) NOT NULL,
            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_logia_clave (logia, clave_logia),
            INDEX idx_usuario (usuario_registro),
            INDEX idx_fecha (fecha_registro)
        )";
        $conn->query($createTableSQL);
    }
} catch (Exception $e) {
    error_log("Error al verificar/crear tabla reportes: " . $e->getMessage());
}

// Datos de logia
$datosLogia = obtenerDatosLogia();
if ($datosLogia === null) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron obtener los datos de la logia']);
    exit;
}

$logia_registro = $datosLogia['logia'];
$clave_logia   = $datosLogia['clave_logia'];
$usuario_actual = $_SESSION['user_id'];
$rol_usuario    = strtolower($_SESSION['rol'] ?? 'secretario');

// Permisos del menú (igual que tenías)
$permisos = [
    'fullmaester' => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],
    'venerable'   => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],
    'secretario'  => ['inicio', 'secretaria', 'actas', 'salir'],
    'tesorero'    => ['inicio', 'tesoreria', 'reportes', 'salir']
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
        if (!isset($items[$clave])) continue;
        $item = $items[$clave];
        $extra = $item['extra_class'] ?? '';
        $html .= '<li><a href="' . $item['url'] . '" class="block p-2 rounded-lg hover:bg-[var(--border-color)] ' . $extra . '">' . $item['label'] . '</a></li>';
    }
    return $html;
}

// ====================== AJAX ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'get_reportes': {
                $params = [];
                $tipos  = '';
                $sql = "SELECT r.id_reportes, r.fecha_registro, r.tipo_evidencia, r.usuario_registro,
                               u.usr AS nombre_usuario
                        FROM reportes r
                        LEFT JOIN usuarios u ON r.usuario_registro = u.usrId
                        WHERE 1=1";

                // SIEMPRE acotado a la logia/clave del usuario actual (para todos los roles)
                if (!empty($logia_registro)) {
                    $sql .= " AND r.logia = ?";
                    $params[] = $logia_registro; $tipos .= 's';
                }
                if (!empty($clave_logia)) {
                    $sql .= " AND r.clave_logia = ?";
                    $params[] = $clave_logia; $tipos .= 's';
                }

                // Filtros
                if (!empty($_POST['fecha_inicio'])) {
                    $sql .= " AND DATE(r.fecha_registro) >= ?";
                    $params[] = $_POST['fecha_inicio']; $tipos .= 's';
                }
                if (!empty($_POST['fecha_fin'])) {
                    $sql .= " AND DATE(r.fecha_registro) <= ?";
                    $params[] = $_POST['fecha_fin']; $tipos .= 's';
                }
                if (!empty($_POST['tipo_archivo'])) {
                    $sql .= " AND r.tipo_evidencia = ?";
                    $params[] = $_POST['tipo_archivo']; $tipos .= 's';
                }
                // Búsqueda por usuario visible solo para fullmaester (opcional)
                if ($rol_usuario === 'fullmaester' && !empty($_POST['usuario'])) {
                    $sql .= " AND u.usr LIKE ?";
                    $params[] = '%' . $_POST['usuario'] . '%'; $tipos .= 's';
                }

                // IMPORTANTE: Ya NO limitamos por usuario_registro para secretario/tesorero/venerable
                // (Antes lo hacía; ahora se elimina para habilitar ver/descargar de toda la logia)

                $sql .= " ORDER BY r.fecha_registro DESC";

                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Error al preparar consulta: " . $conn->error);
                if (!empty($params)) $stmt->bind_param($tipos, ...$params);
                if (!$stmt->execute()) throw new Exception("Error al ejecutar consulta: " . $stmt->error);

                $result = $stmt->get_result();
                $reportes = [];
                while ($row = $result->fetch_assoc()) {
                    $reportes[] = [
                        'id' => $row['id_reportes'],
                        'fecha_subida' => $row['fecha_registro'],
                        'tipo_evidencia' => $row['tipo_evidencia'],
                        'usuario_registro' => $row['usuario_registro'],
                        'nombre_usuario' => $row['nombre_usuario'],
                        'apellido_usuario' => '' // si más tarde agregas apellidos, lo llenas
                    ];
                }
                echo json_encode(['success' => true, 'data' => $reportes]);
                break;
            }

            case 'download_reporte': {
                if (!isset($_POST['id'])) { echo json_encode(['error' => 'ID de reporte requerido']); exit; }
                $id = intval($_POST['id']);

                // Todos los roles válidos pueden descargar dentro de su logia/clave
                // (fullmaester/venerable/secretario/tesorero)
                $sql = "SELECT evidencia, tipo_evidencia FROM reportes
                        WHERE id_reportes = ? AND logia = ? AND clave_logia = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $id, $logia_registro, $clave_logia);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    echo json_encode([
                        'success'   => true,
                        'file_data' => base64_encode($row['evidencia']),
                        'file_type' => $row['tipo_evidencia']
                    ]);
                } else {
                    echo json_encode(['error' => 'Archivo no encontrado']);
                }
                break;
            }

            case 'delete_reporte': {
                if (!isset($_POST['id'])) { echo json_encode(['error' => 'ID de reporte requerido']); exit; }
                $id = intval($_POST['id']);

                // SOLO fullmaester y venerable pueden eliminar
                if ($rol_usuario === 'fullmaester') {
                    // Puede eliminar cualquiera (si quieres acotar por logia, cambia aquí)
                    $sql = "DELETE FROM reportes WHERE id_reportes = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id);
                } elseif ($rol_usuario === 'venerable') {
                    // Elimina solo dentro de su logia/clave
                    $sql = "DELETE FROM reportes WHERE id_reportes = ? AND logia = ? AND clave_logia = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $id, $logia_registro, $clave_logia);
                } else {
                    echo json_encode(['error' => 'No tienes permisos para eliminar este reporte']);
                    exit;
                }

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Reporte eliminado exitosamente']);
                } else {
                    echo json_encode(['error' => 'No se pudo eliminar el reporte']);
                }
                break;
            }

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        error_log("Error en ver_reportes.php: " . $e->getMessage());
        echo json_encode(['error' => 'Error interno del servidor']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="description" content="Ver Reportes para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Actas | SIGAM</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="../css/reportes.css"/>
  <link rel="stylesheet" href="../css/menu.css"/>
  <style>
    .filter-section { transition: all 0.3s ease; }
    .table-container { max-height: 600px; overflow-y: auto; }
    .file-icon { width: 24px; height: 24px; }
    .action-btn { padding: 0.25rem 0.5rem; margin: 0.125rem; border-radius: 0.25rem; font-size: 0.75rem; transition: all 0.2s; }
    .btn-download { background-color: #10b981; color: white; }
    .btn-download:hover { background-color: #059669; }
    .btn-delete { background-color: #ef4444; color: white; }
    .btn-delete:hover { background-color: #dc2626; }
    .btn-view { background-color: #2563eb; color: white; }
    .btn-view:hover { background-color: #1d4ed8; }
    .stats-card { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border:1px solid rgba(255,255,255,0.2); border-radius: .75rem; padding: 1.5rem; text-align:center; }

    /* Modal preview */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index: 100; }
    .modal { width: 90%; max-width: 900px; background: #fff; border-radius: 12px; overflow: hidden; display:flex; flex-direction:column; }
    .modal-header { padding: .75rem 1rem; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; }
    .modal-body { height: 75vh; }
    .modal-footer { padding: .75rem 1rem; border-top: 1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:.5rem; }
    .modal iframe { width: 100%; height: 100%; border: 0; }
    .hidden { display:none; }
  </style>
</head>
<body>
  <!-- Decorativos -->
  <div class="fixed top-1/4 right-10 w-16 h-16 rounded-full floating delay-1"></div>
  <div class="fixed bottom-1/4 right-20 w-20 h-20 rounded-full floating"></div>

  <div class="container">
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
          <?= generarMenu($rol_usuario, $permisos) ?>
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
            <?= generarMenu($rol_usuario, $permisos) ?>
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
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">Ver Actas</h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
        </div>
        <div class="flex gap-2">
          <a href="../actas/" class="btn btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Subir Reporte
          </a>
        </div>
      </header>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="stats-card">
          <div class="text-2xl font-bold text-[var(--primary-color)]" id="totalReportes">0</div>
          <div class="text-sm text-[var(--text-light)]">Total Reportes</div>
        </div>
        <div class="stats-card">
          <div class="text-2xl font-bold text-green-600" id="reportesPDF">0</div>
          <div class="text-sm text-[var(--text-light)]">Archivos PDF</div>
        </div>
        <div class="stats-card">
          <div class="text-2xl font-bold text-blue-600" id="reportesExcel">0</div>
          <div class="text-sm text-[var(--text-light)]">Archivos Excel</div>
        </div>
        <div class="stats-card">
          <div class="text-2xl font-bold text-purple-600" id="reportesCSV">0</div>
          <div class="text-sm text-[var(--text-light)]">Archivos CSV</div>
        </div>
      </div>

      <!-- Filtros -->
      <div class="glass-card p-4 mb-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold">Filtros de búsqueda</h3>
          <button id="toggleFilters" class="btn btn-secondary btn-sm">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            Filtros
          </button>
        </div>
        
        <div id="filterSection" class="filter-section">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="group">
              <label for="fechaInicio" class="block mb-1 font-medium">Fecha Inicio</label>
              <input type="date" id="fechaInicio" class="w-full glass-card">
            </div>
            <div class="group">
              <label for="fechaFin" class="block mb-1 font-medium">Fecha Fin</label>
              <input type="date" id="fechaFin" class="w-full glass-card">
            </div>
            <div class="group">
              <label for="tipoArchivo" class="block mb-1 font-medium">Tipo de Archivo</label>
              <select id="tipoArchivo" class="w-full glass-card">
                <option value="">Todos los tipos</option>
                <option value="pdf">PDF</option>
                <option value="xlsx">Excel (XLSX)</option>
                <option value="xls">Excel (XLS)</option>
                <option value="csv">CSV</option>
              </select>
            </div>
            <?php if ($rol_usuario === 'fullmaester'): ?>
            <div class="group">
              <label for="filtroUsuario" class="block mb-1 font-medium">Usuario</label>
              <input type="text" id="filtroUsuario" placeholder="Buscar por nombre..." class="w-full glass-card">
            </div>
            <?php endif; ?>
          </div>
          <div class="flex gap-2 mt-4">
            <button id="aplicarFiltros" class="btn btn-primary">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              Buscar
            </button>
            <button id="limpiarFiltros" class="btn btn-secondary">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              Limpiar
            </button>
          </div>
        </div>
      </div>

      <!-- Tabla -->
      <div class="glass-card">
        <div class="p-4 border-b border-[var(--border-color)]">
          <h3 class="text-lg font-semibold">Reportes Subidos</h3>
        </div>
        <div class="table-container">
          <table id="reportesTable" class="w-full">
            <thead class="bg-gray-50 sticky top-0">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                <?php if ($rol_usuario === 'fullmaester'): ?>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                <?php endif; ?>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <tbody id="reportesTableBody" class="bg-white divide-y divide-gray-200"></tbody>
          </table>
        </div>
        <div id="noResults" class="p-8 text-center text-gray-500 hidden">
          <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <p class="text-lg font-medium">No se encontraron reportes</p>
          <p class="text-sm">Intenta ajustar los filtros de búsqueda</p>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal de Previsualización -->
  <div id="previewOverlay" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h4 id="previewTitle" class="font-semibold text-gray-800">Previsualización</h4>
        <button id="closePreview" class="p-2 rounded hover:bg-gray-100" aria-label="Cerrar">
          ✕
        </button>
      </div>
      <div class="modal-body" id="previewBody">
        <!-- PDF -->
        <iframe id="previewFrame" class="hidden"></iframe>
        <!-- Mensaje para no-PDF -->
        <div id="previewMessage" class="h-full p-6 flex items-center justify-center text-center text-gray-600 hidden">
          <div>
            <p class="mb-2">Este tipo de archivo no puede previsualizarse aquí.</p>
            <p>Usa el botón <strong>Descargar</strong> para abrirlo en tu equipo.</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btnEliminarModal" class="action-btn btn-delete hidden">Eliminar</button>
        <button id="btnDescargarModal" class="action-btn btn-download">Descargar</button>
        <button id="btnCerrarModal" class="action-btn btn-view" style="background:#6b7280">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- Loading -->
  <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-[var(--primary-color)] mx-auto mb-4"></div>
      <p class="font-medium">Cargando...</p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/menu_mobile.js"></script>
  <script>
    // ======= Config permisos en JS =======
    const ROLE = "<?= htmlspecialchars($rol_usuario) ?>";
    const CAN_DELETE = <?= in_array($rol_usuario, ['fullmaester','venerable']) ? 'true' : 'false' ?>;
    const SHOW_USER_COLUMN = <?= $rol_usuario === 'fullmaester' ? 'true' : 'false' ?>;

    let reportesData = [];
    let currentPreview = { id: null, tipo: null, blobUrl: null };

    document.addEventListener('DOMContentLoaded', function() {
      cargarReportes();

      document.getElementById('aplicarFiltros').addEventListener('click', cargarReportes);
      document.getElementById('limpiarFiltros').addEventListener('click', limpiarFiltros);
      document.getElementById('toggleFilters').addEventListener('click', toggleFiltros);

      document.getElementById('fechaInicio').addEventListener('change', cargarReportes);
      document.getElementById('fechaFin').addEventListener('change', cargarReportes);
      document.getElementById('tipoArchivo').addEventListener('change', cargarReportes);

      <?php if ($rol_usuario === 'fullmaester'): ?>
      let timeoutId;
      document.getElementById('filtroUsuario').addEventListener('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(cargarReportes, 500);
      });
      <?php endif; ?>

      // Modal events
      document.getElementById('closePreview').onclick = closePreview;
      document.getElementById('btnCerrarModal').onclick = closePreview;
      document.getElementById('btnDescargarModal').onclick = () => {
        if (currentPreview.id && currentPreview.tipo) descargarReporte(currentPreview.id, currentPreview.tipo);
      };
      document.getElementById('btnEliminarModal').onclick = () => {
        if (currentPreview.id) eliminarReporte(currentPreview.id);
      };
    });

    function toggleFiltros() {
      const filterSection = document.getElementById('filterSection');
      const isHidden = filterSection.style.display === 'none';
      if (isHidden) { filterSection.style.display = 'block'; setTimeout(() => filterSection.style.opacity = '1', 10); }
      else { filterSection.style.opacity = '0'; setTimeout(() => filterSection.style.display = 'none', 300); }
    }

    function limpiarFiltros() {
      document.getElementById('fechaInicio').value = '';
      document.getElementById('fechaFin').value = '';
      document.getElementById('tipoArchivo').value = '';
      <?php if ($rol_usuario === 'fullmaester'): ?> document.getElementById('filtroUsuario').value = ''; <?php endif; ?>
      cargarReportes();
    }

    function cargarReportes() {
      const loadingOverlay = document.getElementById('loadingOverlay');
      loadingOverlay.classList.remove('hidden');

      const formData = new FormData();
      formData.append('action', 'get_reportes');
      formData.append('fecha_inicio', document.getElementById('fechaInicio').value);
      formData.append('fecha_fin', document.getElementById('fechaFin').value);
      formData.append('tipo_archivo', document.getElementById('tipoArchivo').value);
      <?php if ($rol_usuario === 'fullmaester'): ?>
      formData.append('usuario', document.getElementById('filtroUsuario').value);
      <?php endif; ?>

      fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          loadingOverlay.classList.add('hidden');
          if (data.success) {
            reportesData = data.data;
            mostrarReportes(reportesData);
            actualizarEstadisticas(reportesData);
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al cargar los reportes', confirmButtonColor: '#2563eb' });
          }
        })
        .catch(err => {
          loadingOverlay.classList.add('hidden');
          Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#2563eb' });
        });
    }

    function mostrarReportes(reportes) {
      const tbody = document.getElementById('reportesTableBody');
      const noResults = document.getElementById('noResults');

      if (reportes.length === 0) {
        tbody.innerHTML = '';
        noResults.classList.remove('hidden');
        return;
      }
      noResults.classList.add('hidden');

      tbody.innerHTML = reportes.map(reporte => {
        const fecha = new Date(reporte.fecha_subida).toLocaleString('es-MX', { hour12:false });
        const tipoIcon = getFileIcon(reporte.tipo_evidencia);
        const tipoColor = getFileColor(reporte.tipo_evidencia);

        // botones según permisos
        const btnVer = `
          <button onclick="verReporte(${reporte.id}, '${reporte.tipo_evidencia}')" class="action-btn btn-view" title="Ver">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>`;

        const btnDesc = `
          <button onclick="descargarReporte(${reporte.id}, '${reporte.tipo_evidencia}')" class="action-btn btn-download" title="Descargar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </button>`;

        const btnDel = CAN_DELETE ? `
          <button onclick="eliminarReporte(${reporte.id})" class="action-btn btn-delete" title="Eliminar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>` : '';

        // click en nombre abre preview si es PDF
        const nombreClickable = (reporte.tipo_evidencia === 'pdf')
          ? `<button class="text-blue-600 hover:underline" onclick="verReporte(${reporte.id}, 'pdf')">Reporte_${reporte.id}.pdf</button>`
          : `Reporte_${reporte.id}.${reporte.tipo_evidencia}`;

        return `
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center">${tipoIcon}<span class="ml-2 text-sm font-medium">${nombreClickable}</span></div>
            </td>
            <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${tipoColor}">${reporte.tipo_evidencia.toUpperCase()}</span></td>
            ${SHOW_USER_COLUMN ? `<td class="px-4 py-3 text-sm text-gray-900">${reporte.nombre_usuario || 'N/A'}</td>` : ''}
            <td class="px-4 py-3 text-sm text-gray-500">${fecha}</td>
            <td class="px-4 py-3">
              <div class="flex">${btnVer}${btnDesc}${btnDel}</div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function actualizarEstadisticas(reportes) {
      const total = reportes.length;
      const pdf   = reportes.filter(r => r.tipo_evidencia === 'pdf').length;
      const excel = reportes.filter(r => ['xlsx','xls'].includes(r.tipo_evidencia)).length;
      const csv   = reportes.filter(r => r.tipo_evidencia === 'csv').length;
      document.getElementById('totalReportes').textContent = total;
      document.getElementById('reportesPDF').textContent = pdf;
      document.getElementById('reportesExcel').textContent = excel;
      document.getElementById('reportesCSV').textContent = csv;
    }

    function getFileIcon(tipo) {
      const icons = {
        'pdf': `<svg class="file-icon text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`,
        'xlsx': `<svg class="file-icon text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
        'xls':  `<svg class="file-icon text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
        'csv':  `<svg class="file-icon text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`
      };
      return icons[tipo] || icons['pdf'];
    }

    function getFileColor(tipo) {
      const colors = {
        'pdf': 'bg-red-100 text-red-800',
        'xlsx': 'bg-green-100 text-green-800',
        'xls': 'bg-green-100 text-green-800',
        'csv': 'bg-blue-100 text-blue-800'
      };
      return colors[tipo] || colors['pdf'];
    }

    // ======= PREVIEW + DESCARGA =======
    function verReporte(id, tipo) {
      // Reutilizamos el endpoint de download para obtener el blob y previsualizar
      const formData = new FormData();
      formData.append('action', 'download_reporte');
      formData.append('id', id);

      const loadingOverlay = document.getElementById('loadingOverlay');
      loadingOverlay.classList.remove('hidden');

      fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          loadingOverlay.classList.add('hidden');
          if (data.success) {
            const byteCharacters = atob(data.file_data);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i);
            const byteArray = new Uint8Array(byteNumbers);
            const mimeTypes = {
              'pdf': 'application/pdf',
              'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
              'xls':  'application/vnd.ms-excel',
              'csv':  'text/csv'
            };
            const blob = new Blob([byteArray], { type: mimeTypes[tipo] || 'application/octet-stream' });
            const url = URL.createObjectURL(blob);

            // Guardamos estado actual para botones del modal
            currentPreview = { id, tipo, blobUrl: url };

            // Mostrar modal
            openPreviewModal(tipo, url, id);
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo obtener el archivo', confirmButtonColor: '#2563eb' });
          }
        })
        .catch(err => {
          loadingOverlay.classList.add('hidden');
          Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión', confirmButtonColor: '#2563eb' });
        });
    }

    function openPreviewModal(tipo, blobUrl, id) {
      const overlay = document.getElementById('previewOverlay');
      const frame   = document.getElementById('previewFrame');
      const msg     = document.getElementById('previewMessage');
      const title   = document.getElementById('previewTitle');
      const btnDel  = document.getElementById('btnEliminarModal');

      title.textContent = `Reporte_${id}.${tipo}`;
      if (tipo === 'pdf') {
        frame.src = blobUrl;
        frame.classList.remove('hidden');
        msg.classList.add('hidden');
      } else {
        frame.classList.add('hidden');
        frame.src = 'about:blank';
        msg.classList.remove('hidden');
      }

      // Mostrar/ocultar botón eliminar según permisos
      if (CAN_DELETE) btnDel.classList.remove('hidden'); else btnDel.classList.add('hidden');

      overlay.style.display = 'flex';
    }

    function closePreview() {
      const overlay = document.getElementById('previewOverlay');
      const frame   = document.getElementById('previewFrame');
      overlay.style.display = 'none';
      frame.src = 'about:blank';
      if (currentPreview.blobUrl) {
        URL.revokeObjectURL(currentPreview.blobUrl);
        currentPreview = { id:null, tipo:null, blobUrl:null };
      }
    }

    function descargarReporte(id, tipo) {
      // Si ya tenemos blobUrl de la preview, úsalo; si no, vuelve a pedirlo
      if (currentPreview.id === id && currentPreview.blobUrl) {
        triggerDownload(currentPreview.blobUrl, `Reporte_${id}.${tipo}`);
        return;
      }
      const formData = new FormData();
      formData.append('action', 'download_reporte');
      formData.append('id', id);

      const loadingOverlay = document.getElementById('loadingOverlay');
      loadingOverlay.classList.remove('hidden');

      fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          loadingOverlay.classList.add('hidden');
          if (data.success) {
            const byteCharacters = atob(data.file_data);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i);
            const byteArray = new Uint8Array(byteNumbers);
            const mimeTypes = {
              'pdf': 'application/pdf',
              'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
              'xls':  'application/vnd.ms-excel',
              'csv':  'text/csv'
            };
            const blob = new Blob([byteArray], { type: mimeTypes[tipo] || 'application/octet-stream' });
            const url = URL.createObjectURL(blob);
            triggerDownload(url, `Reporte_${id}.${tipo}`);
            URL.revokeObjectURL(url);
            Swal.fire({ icon: 'success', title: 'Descarga exitosa', text: 'El archivo se ha descargado correctamente', timer: 1600, showConfirmButton: false });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al descargar el archivo', confirmButtonColor: '#2563eb' });
          }
        })
        .catch(err => {
          loadingOverlay.classList.add('hidden');
          Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#2563eb' });
        });
    }

    function triggerDownload(url, filename) {
      const a = document.createElement('a');
      a.style.display = 'none';
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    function eliminarReporte(id) {
      Swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_reporte');
          formData.append('id', id);

          const loadingOverlay = document.getElementById('loadingOverlay');
          loadingOverlay.classList.remove('hidden');

          fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
              loadingOverlay.classList.add('hidden');
              if (data.success) {
                closePreview();
                Swal.fire({ icon: 'success', title: 'Eliminado', text: 'El reporte ha sido eliminado', timer: 1600, showConfirmButton: false });
                cargarReportes();
              } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo eliminar', confirmButtonColor: '#2563eb' });
              }
            })
            .catch(err => {
              loadingOverlay.classList.add('hidden');
              Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#2563eb' });
            });
        }
      });
    }
  </script>
</body>
</html>
