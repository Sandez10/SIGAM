
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

    <!-- Main Content -->
    <main class="content">
      <!-- Header -->
      <header class="header glass-card">
        <div>
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">Reportes</h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
        </div>
        <div class="flex gap-3">
          <button class="hamburger" title="Abrir menú" aria-label="Abrir menú de navegación">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <a href="../plataforma/principal.php" class="btn btn-secondary" title="Volver al inicio">
            <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Regresar
          </a>
        </div>
      </header>
      <!-- Report Section -->
    <!-- Formulario para subir reportes -->
    <div class="glass-card p-4 mb-6">
      <h3 class="text-lg font-semibold mb-4">Elegir Reporte</h3>
      <form id="uploadForm" method="POST" enctype="multipart/form-data">
        <div id="uploadWizard">
          <!-- Paso 1: Selección de archivo -->
        </div>
      </form>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</body>
</html>