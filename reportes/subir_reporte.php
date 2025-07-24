<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_name'])) {
    die("Error: No hay sesión de usuario activa");
}

// Obtener datos de la logía
$datosLogia = obtenerDatosLogia();

if ($datosLogia === null) {
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

// Asignar variables
$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];
$usuario_registro = $_SESSION['user_id']; // Obtenemos el ID del usuario activo

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['evidencia'])) {
    $allowed_mime_types = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xls'
    ];
    
    $file_type = $_FILES['evidencia']['type'];
    $file_name = $_FILES['evidencia']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validar tipo de archivo
    if (!array_key_exists($file_type, $allowed_mime_types) || 
        !in_array($file_ext, ['pdf', 'xlsx', 'xls'])) {
        die("Error: Solo se permiten archivos PDF y Excel (XLSX/XLS)");
    }
    
    // Validar tamaño (ejemplo: máximo 5MB)
    if ($_FILES['evidencia']['size'] > 5242880) {
        die("Error: El archivo no debe exceder 5MB");
    }
    
    // Leer el contenido del archivo
    $evidencia = file_get_contents($_FILES['evidencia']['tmp_name']);
    $tipo_evidencia = $allowed_mime_types[$file_type];
    
    // Insertar en la base de datos
    $stmt = $conn->prepare("INSERT INTO reportes 
                          (usuario_registro, logia, clave_logia, evidencia, tipo_evidencia) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $usuario_registro, $logia_registro, $clave_logia, $evidencia, $tipo_evidencia);
    
    if ($stmt->execute()) {
        $mensaje = "Reporte subido correctamente";
    } else {
        $mensaje = "Error al subir el reporte: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1 " />
  <meta name="description" content="Relación de Pagos para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Relación de Pagos - SIGAM</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/reportes.css"/>
  <link rel="stylesheet" href="../css/menu.css"/>
    <style>
    .wizard-step {
      transition: all 0.3s ease;
    }
    .hidden {
      display: none;
    }
    #confirmationDetails {
      background-color: rgba(249, 250, 251, 0.7);
      border-radius: 0.5rem;
    }
  </style>
</head>
<body>
  <!-- Decorative Elements -->
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
          <li><a href="../plataforma/principal.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Inicio</a></li>
          <li><a href="../usuarios/all_usuarios.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Usuarios</a></li>
          <li><a href="../formulario/form_tes.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Tesorería</a></li>
          <li><a href="../formulario/form_sec.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Secretaría</a></li>
          <li><a href="logout.php" class="block p-2 rounded-lg bg-[var(--error-color)] text-white mt-4">Cerrar Sesión</a></li>
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
        <img src="../img/sigam_transparente.png" alt="Logo SIGAM" class="w-10 h-10 object-contain">
      </div>
      <h2 class="text-lg font-bold text-[var(--primary-color)]">SIGAM</h2>
    </div>
    <p class="text-xs opacity-90 mb-6">Sistema Integral de Gestión Administrativa</p>

    <nav>
      <ul class="space-y-2">
        <li><a href="../plataforma/principal.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Inicio</a></li>
        <li><a href="../usuarios/all_usuarios.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Usuarios</a></li>
        <li><a href="form_tes.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Tesorería</a></li>
        <li><a href="../reportes/ver_reportes.php" class="block p-2 rounded-lg hover:bg-[var(--border-color)]">Reportes</a></li>
        <li><a href="../sesiones_conexiones/logout.php" class="block p-2 rounded-lg bg-[var(--error-color)] text-white mt-4">Cerrar Sesión</a></li>
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
            <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Regresar
          </a>
        </div>
      </header>
      <!-- Report Section -->
    <!-- Formulario para subir reportes -->
    <div class="glass-card p-4 mb-6">
      <h3 class="text-lg font-semibold mb-4">Subir Reporte</h3>
      <form id="uploadForm" method="POST" enctype="multipart/form-data">
        <div id="uploadWizard">
          <!-- Paso 1: Selección de archivo -->
          <div id="step1" class="wizard-step">
            <h4 class="text-md font-semibold mb-4">Paso 1: Seleccionar archivo</h4>
            <div class="group relative">
              <label for="evidencia" class="block mb-1 font-medium">Archivo (PDF o Excel)</label>
              <input type="file" id="evidencia" name="evidencia" accept=".pdf,.xlsx,.xls" 
                     class="w-full glass-card" required>
              <p class="text-xs text-gray-500 mt-1">Formatos permitidos: PDF, XLSX, XLS (Máx. 10MB)</p>
            </div>
            <button id="nextBtn" type="button" class="btn btn-primary mt-4">Siguiente</button>
          </div>
          
          <!-- Paso 2: Confirmación -->
          <div id="step2" class="wizard-step hidden">
            <h4 class="text-md font-semibold mb-4">Paso 2: Confirmar detalles</h4>
            <div id="confirmationDetails" class="mb-4 p-4 bg-gray-50 rounded-lg">
              <!-- Detalles del archivo se mostrarán aquí -->
            </div>
            <div class="flex gap-2">
              <button id="backBtn" type="button" class="btn btn-secondary">Atrás</button>
              <button type="submit" class="btn btn-primary">Subir Reporte</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  
  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-[var(--primary-color)] mx-auto mb-4"></div>
      <p class="font-medium">Subiendo archivo...</p>
      <p class="text-sm text-gray-500 mt-1" id="uploadProgress">Por favor espere</p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="../js/menu_mobile.js"> </script>
  <script>
  // Lógica del wizard
  document.getElementById('nextBtn').addEventListener('click', function() {
    const fileInput = document.getElementById('evidencia');
    const file = fileInput.files[0];
    const allowedTypes = ['application/pdf', 
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel'];
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!file) {
      alert('Por favor seleccione un archivo');
      return;
    }
    
    // Validar tipo de archivo
    if (!allowedTypes.includes(file.type) && 
        !['.pdf', '.xlsx', '.xls'].some(ext => file.name.toLowerCase().endsWith(ext))) {
      alert('Solo se permiten archivos PDF y Excel (XLSX/XLS)');
      return;
    }
    
    // Validar tamaño
    if (file.size > maxSize) {
      alert('El archivo no debe exceder 10MB');
      return;
    }
    
    // Mostrar detalles en el paso 2
    const fileType = file.name.split('.').pop().toUpperCase();
    document.getElementById('confirmationDetails').innerHTML = `
      <div class="flex items-center mb-3">
        <svg class="w-8 h-8 mr-3 ${fileType === 'PDF' ? 'text-red-500' : 'text-green-500'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        <div>
          <p class="font-medium">${file.name}</p>
          <p class="text-sm text-gray-600">${fileType} - ${(file.size / (1024 * 1024)).toFixed(2)} MB</p>
        </div>
      </div>
      <p class="text-sm text-gray-700">¿Estás seguro de subir este archivo?</p>
    `;
    
    // Cambiar pasos
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
  });

  document.getElementById('backBtn').addEventListener('click', function() {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step1').classList.remove('hidden');
  });

  // Validación del formulario en el cliente
  document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('evidencia');
    const file = fileInput.files[0];
    const allowedTypes = ['application/pdf', 
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel'];
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!file) {
      alert('Por favor seleccione un archivo');
      e.preventDefault();
      return;
    }
    
    if (!allowedTypes.includes(file.type) && 
        !['.pdf', '.xlsx', '.xls'].some(ext => file.name.toLowerCase().endsWith(ext))) {
      alert('Solo se permiten archivos PDF y Excel (XLSX/XLS)');
      e.preventDefault();
      return;
    }
    
    if (file.size > maxSize) {
      alert('El archivo no debe exceder 10MB');
      e.preventDefault();
      return;
    }
    
    // Mostrar loading overlay
    document.getElementById('loadingOverlay').classList.remove('hidden');
    
    // Opcional: Aquí podrías agregar lógica AJAX para mostrar progreso
     document.getElementById('uploadProgress').textContent = 'Subiendo 0%';
  });
  </script>
</body>
</html>