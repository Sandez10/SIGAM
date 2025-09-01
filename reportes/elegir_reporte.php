<?php
// elegir_reportes.php
// --------------------------------------
// Rediseño: mostrar reportes según rol
// --------------------------------------
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';

// ---------------------------------------------------------------------
// 1) Obtener rol del usuario
// ---------------------------------------------------------------------
$rol = $_SESSION['rol'] ?? null;

// Fallback a DB si no está en sesión pero sí tenemos user_id
if ($rol === null && isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT rol FROM usuarios WHERE usrId = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $rol = $row['rol'] ?? null;
            $_SESSION['rol'] = $rol; // cache en sesión
        }
        $stmt->close();
    } catch (Throwable $e) {
        // Si falla la BD, seguimos con $rol = null
    }
}

// Normalizar rol a minúsculas (evita problemas de comparación)
$rol = is_string($rol) ? mb_strtolower(trim($rol)) : null;

// ---------------------------------------------------------------------
// 2) Catálogo de reportes
// ---------------------------------------------------------------------
$REPORTES = [
    'expediente_hermano' => [
        'titulo' => 'Expediente de Hermanos',
        'desc'   => 'Listado de expediente de hermanos',
        'url'    => '../reportes/expediente/',
        'icon'   => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    ],
    'actas' => [
        'titulo' => 'Subir Actas',
        'desc'   => 'Realizar una carga de acta.',
        'url'    => '../actas/',
        'icon'   => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 13l3-3 4 4 5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    'visualizacion_actas' => [
        'titulo' => 'ver Actas',
        'desc'   => 'Visualizar las actas que han sido cargadas.',
        'url'    => '../ver-actas/',
        'icon'   => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 13l3-3 4 4 5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    ],
    'reportes' => [
        'titulo' => 'Reporte de Tesorería',
        'desc'   => 'Concentrado general de movimientos.',
        'url'    => '../reportes/',
        'icon'   => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M2 20c0-3.333 2.667-5 6-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="16" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M22 20c0-3.333-2.667-5-6-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    ],

];

// ---------------------------------------------------------------------
// 3) Permisos por rol
// ---------------------------------------------------------------------
$PERMISOS = [
    'fullmaester' => array_keys($REPORTES), // todos
    'venerable' => array_keys($REPORTES), // todos
    'secretario' => ['expediente_hermano', 'actas','visualizacion_actas'],
    'tesorero' => ['reportes'],
];

// Si el rol no existe en el mapa, deja solo un conjunto seguro o vacío
$reportesPermitidos = $PERMISOS[$rol] ?? [];

// Construir arreglo final de tarjetas a mostrar
$tarjetas = [];
foreach ($reportesPermitidos as $slug) {
    if (isset($REPORTES[$slug])) {
        $tarjetas[] = $REPORTES[$slug] + ['slug' => $slug];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1 " />
  <meta name="description" content="Relación de Pagos para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Reportes - SIGAM</title>

  <!-- Dependencias -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/reportes.css"/>

  <style>
    :root {
      --primary-color:#0ea5e9;
      --border-color:#e5e7eb;
      --text-light:#6b7280;
      --error-color:#ef4444;
    }
    body { font-family: 'Roboto', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji'; }
    .container { display:flex; gap:1.5rem; max-width:1200px; margin:0 auto; padding:1.25rem; }
    .sidebar { width:260px; background: #ffffffaa; backdrop-filter: blur(6px); border:1px solid var(--border-color); border-radius:12px; padding:1rem; }
    .content { flex:1; }
    .glass-card { background:#ffffffcc; backdrop-filter: blur(8px); border:1px solid var(--border-color); border-radius:16px; padding:1rem; }
    .btn { display:inline-flex; align-items:center; gap:.5rem; border:1px solid var(--border-color); border-radius:10px; padding:.5rem .75rem; }
    .btn-secondary { background:white; }
    .hamburger { border:1px solid var(--border-color); border-radius:10px; padding:.4rem; background:white; }
    .report-card { transition: transform .15s ease, box-shadow .15s ease; }
    .report-card:hover { transform: translateY(-2px); box-shadow: 0 10px 18px rgba(0,0,0,.06); }
    .report-icon { background:#f1f5f9; border:1px dashed #e2e8f0; border-radius:12px; width:48px; height:48px; display:flex; align-items:center; justify-content:center; }
    .badge { font-size:.70rem; padding:.15rem .5rem; border-radius:999px; border:1px solid var(--border-color); color:#334155; background:#f8fafc; }
    .floating { background: radial-gradient(120px 120px at 30% 30%, rgba(14,165,233,.15), transparent 60%); filter: blur(6px); animation: float 8s ease-in-out infinite; }
    .delay-1 { animation-delay: 2s; }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)}}
  </style>
</head>
<body>
  <!-- Decorativo -->
  <div class="fixed top-1/4 right-10 w-16 h-16 rounded-full floating delay-1"></div>
  <div class="fixed bottom-1/4 right-20 w-20 h-20 rounded-full floating"></div>

  <div class="container">
    <!-- Sidebar -->
  <?php include '../configuracion/panel_menu.php'; ?>

    <!-- Contenido -->
    <main class="content">
      <!-- Header -->
      <header class="header glass-card mb-4 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">Reportes</h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
          <div class="mt-2">
            <span class="badge">Rol actual: <strong class="ml-1"><?php echo htmlspecialchars($rol ?? 'desconocido'); ?></strong></span>
          </div>
        </div>
        <div class="flex gap-3">
          <button class="hamburger" title="Abrir menú" aria-label="Abrir menú de navegación">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <a href="../plataforma/" class="btn btn-secondary" title="Volver al inicio">
            <svg class="inline-block w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Regresar
          </a>
        </div>
      </header>

      <!-- Sección Reportes (grid de tarjetas) -->
      <section class="glass-card">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Elegir apartado</h3>
          <div class="text-xs text-[var(--text-light)]">Solo puedes ver lo que tu rol permite</div>
        </div>

        <?php if (count($tarjetas) === 0): ?>
          <div class="p-6 border border-dashed rounded-xl text-center">
            <p class="text-sm text-[var(--text-light)] mb-2">No tienes reportes asignados para el rol <strong><?php echo htmlspecialchars($rol ?? 'desconocido'); ?></strong>.</p>
            <p class="text-sm">Si crees que es un error, contacta a un administrador.</p>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($tarjetas as $card): ?>
              <a href="<?php echo htmlspecialchars($card['url']); ?>" class="report-card block p-4 border rounded-2xl hover:border-[var(--primary-color)] focus:ring-2 focus:ring-[var(--primary-color)] focus:outline-none">
                <div class="flex items-start gap-3">
                  <div class="report-icon" aria-hidden="true"><?php echo $card['icon']; ?></div>
                  <div class="flex-1">
                    <div class="flex items-center justify-between">
                      <h4 class="font-semibold"><?php echo htmlspecialchars($card['titulo']); ?></h4>
                      <span class="badge"><?php echo htmlspecialchars($card['slug']); ?></span>
                    </div>
                    <p class="text-sm text-[var(--text-light)] mt-1"><?php echo htmlspecialchars($card['desc']); ?></p>
                    <div class="mt-3 inline-flex items-center text-[var(--primary-color)] text-sm">
                      Abrir
                      <svg class="w-4 h-4 ml-1" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M17 7H9M17 7v8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

    </main>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script>
    // Aquí podrías añadir tracking o una validación rápida antes de salir
    // por ejemplo, confirmar apertura en nueva pestaña para ciertos reportes.
    // Mantengo simple por ahora.
  </script>
</body>
</html>
