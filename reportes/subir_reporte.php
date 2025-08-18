<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_name'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No hay sesión de usuario activa']);
    exit;
}

// Obtener datos de la logía
$datosLogia = obtenerDatosLogia();

if ($datosLogia === null) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron obtener los datos de la logia']);
    exit;
}

// Asignar variables
$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];
$usuario_registro = $_SESSION['user_id']; // Obtenemos el ID del usuario activo

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['evidencia'])) {
    // Definir tipos MIME permitidos
    $allowed_mime_types = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-excel' => 'xls',
        'text/csv' => 'csv',
        'application/csv' => 'csv',
        'text/x-csv' => 'csv',
        'text/comma-separated-values' => 'csv',
    ];

    $file_type = $_FILES['evidencia']['type'];
    $file_name = $_FILES['evidencia']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validar tipo de archivo
    if (!array_key_exists($file_type, $allowed_mime_types) || 
        !in_array($file_ext, ['pdf', 'xlsx', 'xls', 'csv'])) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Solo se permiten archivos PDF, Excel (XLSX/XLS) y CSV']);
        exit;
    }
    
    // Validar tamaño máximo: 15MB
    if ($_FILES['evidencia']['size'] > 15728640) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'El archivo no debe exceder los 15MB']);
        exit;
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
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Archivo subido exitosamente']);
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Error al subir el reporte: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

$rol_usuario = $_SESSION['rol'] ?? 'miembro'; // rol por defecto si no está definido

$permisos = [
    'superadmin' => ['inicio', 'secretaria', 'usuarios', 'tesoreria', 'actas', 'reportes', 'configuracion', 'salir'],
    'administrador' => ['inicio', 'actas', 'salir'],
    'miembro' => ['inicio', 'tesoreria', 'actas', 'salir']
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="description" content="Relación de Pagos para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Relación de Pagos - SIGAM</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
    #progressBar {
      transition: width 0.3s ease-in-out;
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
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">Subir reportes</h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
        </div>

      </header>

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
                <input 
                  type="file" 
                  id="evidencia" 
                  name="evidencia" 
                  accept=".pdf,.xlsx,.xls,.csv"
                  class="w-full glass-card" 
                  required
                >
                <p class="text-xs text-gray-500 mt-1">Formatos permitidos: PDF, XLSX, XLS, CSV (Máx. 15MB)</p>
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
    </main>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
      <div class="bg-white p-6 rounded-lg shadow-xl text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-[var(--primary-color)] mx-auto mb-4" id="spinner"></div>
        <p class="font-medium">Subiendo archivo...</p>
        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-4">
          <div id="progressBar" class="bg-[var(--primary-color)] h-2.5 rounded-full" style="width: 0%"></div>
        </div>
        <p class="text-sm text-gray-500 mt-1" id="uploadProgress">0%</p>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/menu_mobile.js"></script>
  <script>
    // Verificar sesión y datos de logia al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
      fetch('', { method: 'GET' })
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: data.error,
              confirmButtonText: 'Aceptar',
              confirmButtonColor: '#2563eb'
            }).then(() => {
              window.location.href = '../salir/';
            });
          }
        })
        .catch(error => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al verificar la sesión',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#2563eb'
          });
        });
    });

    // Lógica del wizard
    document.getElementById('nextBtn').addEventListener('click', function() {
      const fileInput = document.getElementById('evidencia');
      const file = fileInput.files[0];
      const allowedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
        'text/x-csv',
        'text/comma-separated-values'
      ];
      const maxSize = 15 * 1024 * 1024; // 15MB
      
      if (!file) {
        Swal.fire({
          icon: 'warning',
          title: 'Advertencia',
          text: 'Por favor seleccione un archivo',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#2563eb'
        });
        return;
      }
      
      // Validar tipo de archivo
      if (!allowedTypes.includes(file.type) && 
          !['.pdf', '.xlsx', '.xls', '.csv'].some(ext => file.name.toLowerCase().endsWith(ext))) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Solo se permiten archivos PDF, Excel (XLSX/XLS) y CSV',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#2563eb'
        });
        return;
      }
      
      // Validar tamaño
      if (file.size > maxSize) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'El archivo no debe exceder los 15MB',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#2563eb'
        });
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

    // Manejo del formulario con AJAX
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
      e.preventDefault(); // Evitar el envío predeterminado del formulario

      const fileInput = document.getElementById('evidencia');
      const file = fileInput.files[0];
      const allowedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
        'text/x-csv',
        'text/comma-separated-values'
      ];
      const maxSize = 15 * 1024 * 1024; // 15MB
      
      if (!file) {
        Swal.fire({
          icon: 'warning',
          title: 'Advertencia',
          text: 'Por favor seleccione un archivo',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#2563eb'
        });
        return;
      }
      
      if (!allowedTypes.includes(file.type) && 
          !['.pdf', '.xlsx', '.xls', '.csv'].some(ext => file.name.toLowerCase().endsWith(ext))) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Solo se permiten archivos PDF, Excel (XLSX/XLS) y CSV',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#2563eb'
        });
        return;
      }
      
      if (file.size > maxSize) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'El archivo no debe exceder los 15MB',
          confirmButtonText: 'Aceptar',
          confirmButtonColor: '#2563eb'
        });
        return;
      }
      
      // Mostrar overlay de carga
      const loadingOverlay = document.getElementById('loadingOverlay');
      const progressBar = document.getElementById('progressBar');
      const uploadProgress = document.getElementById('uploadProgress');
      const spinner = document.getElementById('spinner');
      loadingOverlay.classList.remove('hidden');
      uploadProgress.textContent = '0%';
      progressBar.style.width = '0%';

      // Crear FormData para enviar el archivo
      const formData = new FormData(this);

      // Configurar XMLHttpRequest
      const xhr = new XMLHttpRequest();

      // Forzar progreso inicial para archivos pequeños
      let lastProgressUpdate = 0;
      const minProgressInterval = 100; // Mínimo intervalo para actualizar progreso (ms)

      xhr.upload.addEventListener('progress', function(event) {
        if (event.lengthComputable) {
          const now = Date.now();
          if (now - lastProgressUpdate >= minProgressInterval) {
            const percentComplete = Math.round((event.loaded / event.total) * 100);
            progressBar.style.width = percentComplete + '%';
            uploadProgress.textContent = percentComplete + '%';
            lastProgressUpdate = now;
          }
        }
      });

      // Manejar carga completa
      xhr.upload.addEventListener('load', function() {
        progressBar.style.width = '100%';
        uploadProgress.textContent = '100%';
      });

      // Manejar respuesta del servidor
      xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          // Ocultar spinner
          spinner.classList.add('hidden');
          
          try {
            const response = JSON.parse(xhr.responseText);
            
            if (xhr.status === 200 && response.message) {
              // Mostrar mensaje de éxito
              uploadProgress.textContent = 'Archivo subido exitosamente';
              setTimeout(() => {
                loadingOverlay.classList.add('hidden');
                Swal.fire({
                  icon: 'success',
                  title: 'Éxito',
                  text: 'Archivo subido exitosamente',
                  confirmButtonText: 'Aceptar',
                  confirmButtonColor: '#2563eb'
                });
                // Reiniciar formulario y wizard
                document.getElementById('uploadForm').reset();
                document.getElementById('step2').classList.add('hidden');
                document.getElementById('step1').classList.remove('hidden');
                // Restablecer barra de progreso
                progressBar.style.width = '0%';
                uploadProgress.textContent = '0%';
                spinner.classList.remove('hidden');
              }, 1000);
            } else {
              // Mostrar mensaje de error
              uploadProgress.textContent = 'Hubo un error al subir el archivo';
              setTimeout(() => {
                loadingOverlay.classList.add('hidden');
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: response.error || 'Hubo un error al subir el archivo',
                  confirmButtonText: 'Aceptar',
                  confirmButtonColor: '#2563eb'
                });
                // Restablecer barra de progreso
                progressBar.style.width = '0%';
                uploadProgress.textContent = '0%';
                spinner.classList.remove('hidden');
              }, 1000);
            }
          } catch (e) {
            // Error al parsear JSON
            uploadProgress.textContent = 'Hubo un error al subir el archivo';
            setTimeout(() => {
              loadingOverlay.classList.add('hidden');
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error inesperado al procesar la respuesta del servidor',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#2563eb'
              });
              // Restablecer barra de progreso
              progressBar.style.width = '0%';
              uploadProgress.textContent = '0%';
              spinner.classList.remove('hidden');
            }, 1000);
          }
        }
      };

      // Manejar errores de red
      xhr.onerror = function() {
        spinner.classList.add('hidden');
        uploadProgress.textContent = 'Hubo un error al subir el archivo';
        setTimeout(() => {
          loadingOverlay.classList.add('hidden');
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión al subir el archivo',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#2563eb'
          });
          // Restablecer barra de progreso
          progressBar.style.width = '0%';
          uploadProgress.textContent = '0%';
          spinner.classList.remove('hidden');
        }, 1000);
      };

      // Enviar solicitud
      xhr.open('POST', '', true); // '' usa la URL actual
      xhr.send(formData);
    });
  </script>
</body>
</html>
