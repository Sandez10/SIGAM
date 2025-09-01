<?php
// Configuración inicial
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';
require_once '../registros_b/obtener_registro.php';

// Manejo de mensajes de sesión
if (isset($_SESSION['success_message'])) {
    $toast = [
        'type' => 'success',
        'message' => $_SESSION['success_message']
    ];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $toast = [
        'type' => 'error',
        'message' => $_SESSION['error_message']
    ];
    unset($_SESSION['error_message']);
}

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Obtener conexión
$database = Database::getInstance();
$conn = $database->getConnection();

// Si el rol no tiene permiso, salir
if (($_SESSION['rol'] ?? '') === 'secretario') {
    echo "<script>
        alert('No tienes permiso para acceder a esta página.');
        history.back();
    </script>";
    exit();
}

// Obtener datos de la logía
$datosLogia = obtenerDatosLogia();

if ($datosLogia === null) {
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

// Asignar variables
$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];
$oriente = $datosLogia['oriente'];

// Obtener listado de hermanos (para selección)
$hermanos = obtenerListadoHermanos($logia_registro);

// Procesar hermano seleccionado
$grado_seleccionado = 'No seleccionado';
$estado_hermano_textual = 'No seleccionado';
$registro_completo = [];

if (isset($_GET['id'])) {
    $id_hermano = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id_hermano) {
        $resultado = obtenerDatosRegistroCompleto($id_hermano, $logia_registro);
        
        if ($resultado['success']) {
            $registro_completo = $resultado['data'];
            $grado_seleccionado = $registro_completo['info_masonica']['grado_textual'] ?? 'No especificado';
            $estado_hermano_textual = $registro_completo['info_masonica']['estado_textual'] ?? 'No especificado';
        }
    }
}

// Función para opciones de catálogo (desde tu código original)
function opcionesCatalogo($conn, $categoria) {
  if ($categoria === 'capitas') {
    // Solo filas del TRIMESTRE vigente y marcamos el mes actual
    $sql = "SELECT id, descripcion, monto,
             (mes_inicio = MONTH(CURDATE())) AS es_actual
      FROM catalogo_precios WHERE categoria = ?
        AND vigente = 1 AND periodo = CONCAT('T', QUARTER(CURDATE()))
      ORDER BY mes_inicio";
  } else {
    $sql = "SELECT id, descripcion, monto, 0 AS es_actual
      FROM catalogo_precios WHERE categoria = ? AND vigente = 1 ORDER BY orden";
  }

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $categoria);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return $rows;
}

$opts_iniciacion = opcionesCatalogo($conn, 'iniciacion');
$opts_capitas    = opcionesCatalogo($conn, 'capitas');
$opts_salario    = opcionesCatalogo($conn, 'salario');
$opts_seguro     = opcionesCatalogo($conn, 'seguro');
$opts_afiliacion = opcionesCatalogo($conn, 'afiliacion');
$opts_exaltacion = opcionesCatalogo($conn, 'exaltacion');
$opts_regularizacion = opcionesCatalogo($conn, 'regularizacion');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Registro de Tesorería para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Registro de Tesorería - SIGAM</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/formulario_tes.css" />
  <link rel="stylesheet" href="../css/menu.css" />
</head>
<body>
  <!-- Decorative Elements -->
  <div class="fixed bottom-0 left-20 w-full h-32 floating"></div>
  <div class="fixed top-1/4 right-10 w-20 h-20 rounded-full floating delay-1"></div>
  <div class="fixed top-1/3 left-40 w-16 h-16 rounded-full floating delay-2"></div>
  <div class="fixed bottom-1/4 right-1/4 w-24 h-24 rounded-full floating delay-3"></div>
  <div class="container">
    <!-- Sidebar -->
    <?php include '../configuracion/panel_menu.php'?>

    <!-- Overlay -->
    <div class="menu-overlay"></div>

    <!-- Main Content -->
    <main class="content">
      <!-- Header -->
      <header class="header glass-card">
        <div>
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">Registro de Movimiento de Tesorería</h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
        </div>
        <div class="flex gap-3"></div>
      </header>

      <!-- Form -->
      <div class="glass-card p-6 mt-6">
        <h3 class="text-xl font-semibold mb-6">Movimiento de Tesorería</h3>
        <form action="../guardar-tesoreria/" method="POST" id="tesoreriaForm" class="grid grid-cols-1 gap-8 text-sm" enctype="multipart/form-data" novalidate>
          <!-- Token CSRF -->
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="collapsible">
            <div class="collapsible-header">
              <h4 class="font-medium">Información General</h4>
              <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div class="collapsible-content open">
              <div class="grid grid-cols-2 gap-6 p-4">

                <!-- 1. Log -->
                <div class="group relative">
                    <label for="logia" class="block mb-1 font-medium">Log.˙.</label>
                    <input type="text" id="logia" name="logia" 
                           value="<?php echo htmlspecialchars($logia_registro ?? ''); ?>" 
                           class="w-full glass-card" readonly>
                </div>

                <!-- 2. Or -->
                <div class="group relative">
                    <label for="oriente" class="block mb-1 font-medium">Or.˙.</label>
                    <input type="text" id="oriente" name="oriente" 
                           value="<?php echo htmlspecialchars($oriente ?? ''); ?>" 
                           class="w-full glass-card" readonly>
                    <p class="error-message hidden" id="logia-error">Este campo es obligatorio</p>
                </div>

                <!-- Campo oculto con la clave de la logia -->
                <div class="group relative hidden">
                    <label for="clave_logia" class="block mb-1 font-medium">Clave Logia</label>
                    <input type="text" id="clave_logia" name="clave_logia" 
                           value="<?php echo htmlspecialchars($clave_logia ?? ''); ?>" 
                           class="w-full glass-card" readonly>
                </div>

                <!-- 3. Nombre del H (span completo) -->
                <div class="group relative col-span-2">
                    <label for="id_hermano" class="block mb-1 font-medium">Nombre del H.˙.</label>
                    <select id="id_hermano" name="id_hermano" class="w-full glass-card">
                        <option value="">Seleccionar hermano...</option>
                        <?php foreach ($hermanos as $hermano): ?>
                        <option value="<?= htmlspecialchars($hermano['id']) ?>"
                                <?= (isset($_GET['id']) && $_GET['id'] == $hermano['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($hermano['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Grado Masónico -->
                <div class="group relative">
                    <label for="grado" class="block mb-1 font-medium">Grado Masónico</label>
                    <input type="text" id="grado" name="grado" class="w-full glass-card" 
                           value="<?= htmlspecialchars($grado_seleccionado) ?>" readonly>
                </div>

                <!-- 5. Estado Administrativo -->
                <div class="group relative">
                    <label for="estado" class="block mb-1 font-medium">Estado Administrativo</label>
                    <select id="estado" name="estado" class="w-full glass-card">
                        <option value="">Selecciona el estado...</option>
                        <option value="1" <?= (isset($registro_completo['info_masonica']['estado_hermano']) && $registro_completo['info_masonica']['estado_hermano'] == '1') ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= (isset($registro_completo['info_masonica']['estado_hermano']) && $registro_completo['info_masonica']['estado_hermano'] == '0') ? 'selected' : '' ?>>Baja</option>
                        <option value="3" <?= (isset($registro_completo['info_masonica']['estado_hermano']) && $registro_completo['info_masonica']['estado_hermano'] == '3') ? 'selected' : '' ?>>Desplomado</option>
                        <option value="2" <?= (isset($registro_completo['info_masonica']['estado_hermano']) && $registro_completo['info_masonica']['estado_hermano'] == '2') ? 'selected' : '' ?>>Libre de orden</option>
                    </select>
                </div>

                <!-- 6. Capitas -->
                <div class="group relative">
                    <label for="capitas" class="block mb-1 font-medium">Capitas</label>
                    <select id="capitas" name="capitas_id" class="w-full glass-card">
                        <option value="">Seleccione la cantidad de Capitas</option>
                        <?php foreach ($opts_capitas as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>" <?= !empty($o['es_actual']) ? 'selected' : '' ?>>
                            $<?= number_format($o['monto'], 2) ?><?= !empty($o['es_actual']) ? ' (actual)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 7. Seguro Mas -->
                <div class="group relative">
                    <label for="seguro" class="block mb-1 font-medium">Seguro Mas.˙.</label>
                    <select id="seguro" name="seguro_id" class="w-full glass-card">
                        <option value="">Seleccione la cantidad para el seguro</option>
                        <?php foreach ($opts_seguro as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>">
                            $<?= number_format($o['monto'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 8. Iniciación -->
                <div class="group relative">
                    <label for="iniciacion" class="block mb-1 font-medium">Iniciación</label>
                    <select id="iniciacion" name="iniciacion_id" class="w-full glass-card">
                        <option value="">Seleccione la cantidad de iniciación</option>
                        <?php foreach ($opts_iniciacion as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>">
                            $<?= number_format($o['monto'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 9. Aumento Salario -->
                <div class="group relative">
                    <label for="salario" class="block mb-1 font-medium">Aumento de Salario</label>
                    <select id="salario" name="salario_id" class="w-full glass-card">
                        <option value="">Seleccione el Salario</option>
                        <?php foreach ($opts_salario as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>">
                            $<?= number_format($o['monto'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 10. Exaltación -->
                <div class="group relative">
                    <label for="exaltacion" class="block mb-1 font-medium">Exaltación</label>
                    <select id="exaltacion" name="exaltacion_id" class="w-full glass-card">
                        <option value="">Seleccione la cantidad para la exaltación</option>
                        <?php foreach ($opts_exaltacion as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>">
                            $<?= number_format($o['monto'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 11. Afiliación -->
                <div class="group relative">
                    <label for="afiliacion" class="block mb-1 font-medium">Afiliación</label>
                    <select id="afiliacion" name="afiliacion_id" class="w-full glass-card">
                        <option value="">Seleccione la cantidad para la Afiliación</option>
                        <?php foreach ($opts_afiliacion as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>">
                            $<?= number_format($o['monto'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 12. Regularización -->
                <div class="group relative">
                    <label for="regularizacion" class="block mb-1 font-medium">Regularización</label>
                    <select id="regularizacion" name="regularizacion_id" class="w-full glass-card">
                        <option value="">Seleccione la cantidad para la Regularización</option>
                        <?php foreach ($opts_regularizacion as $o): ?>
                        <option value="<?= htmlspecialchars($o['id']) ?>">
                            $<?= number_format($o['monto'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

              </div>
            </div>
          </div>

          <div class="flex justify-end gap-4 mt-6">
            <button type="reset" class="btn btn-secondary">
              <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4"/></svg>
              Limpiar
            </button>
            <button type="submit" class="btn btn-primary">
              <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
              Guardar
            </button>
          </div>
        </form>
      </div>
      <div id="toast" class="toast" role="alert" aria-live="assertive"></div>
    </main>
  </div>

  <script src="../js/menu_mobile.js"></script>
  <script>
  <?php if (isset($toast)): ?>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: '<?= $toast['type'] ?>',
        title: '<?= $toast['type'] === 'success' ? 'Éxito' : 'Error' ?>',
        text: '<?= addslashes($toast['message']) ?>',
        confirmButtonColor: '<?= $toast['type'] === 'success' ? '#3085d6' : '#d33' ?>',
        timer: <?= $toast['type'] === 'success' ? '3000' : '5000' ?>
      });
    });
  <?php endif; ?>
  </script>

  <!-- Scripts -->
  <script src="../js/foto_galeria.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="../js/info.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Inicializar select de hermanos con búsqueda dinámica
      const hermanoSelect = new TomSelect('#id_hermano', {
          placeholder: 'Buscar hermano...',
          create: false,
          sortField: { field: "text", direction: "asc" },
          searchField: ['text'],
          dropdownParent: 'body', 
          render: {
              option: function(data, escape) {
                  return `<div class="flex items-center p-2 hover:bg-blue-600">${escape(data.text)}</div>`;
              },
              item: function(data, escape) {
                  return `<div class="inline-flex items-center bg-blue-500 text-white rounded px-2 py-1 mr-1">${escape(data.text)}</div>`;
              },
              no_results: function(data, escape) {
                  return `<div class="p-2 text-gray-400">No se encontraron resultados</div>`;
              }
          },
          onInitialize: function() {
              this.dropdown.classList.add('z-50');
          }
      });

      // Cargar datos de hermanos en JavaScript (tu lógica original)
      const hermanos = <?php echo json_encode($hermanos); ?>;

      // Manejar cambio de hermano (tu lógica original que funcionaba)
      document.getElementById('id_hermano').addEventListener('change', function() {
          const hermanoId = this.value;
          const gradoInput = document.getElementById('grado');
          const estadoInput = document.getElementById('estado');

          if (hermanoId) {
              const hermanoSeleccionado = hermanos.find(h => h.id == hermanoId);

              if (hermanoSeleccionado) {
                  // Llenar grado
                  gradoInput.value = hermanoSeleccionado.grado_textual || '';

                  // Llenar estado
                  const estadoHermano = hermanoSeleccionado.estado_hermano;
                  estadoInput.value = estadoHermano || '';

                  // Actualizar URL
                  const nuevaURL = new URL(window.location.href);
                  nuevaURL.searchParams.set('id', hermanoId);
                  window.history.pushState({}, '', nuevaURL);
              }
          } else {
              gradoInput.value = '';
              estadoInput.value = '';

              const nuevaURL = new URL(window.location.href);
              nuevaURL.searchParams.delete('id');
              window.history.pushState({}, '', nuevaURL);
          }
      });

      // Cargar hermano desde URL al inicio (tu lógica original)
      const urlParams = new URLSearchParams(window.location.search);
      const idFromUrl = urlParams.get('id');
      if (idFromUrl) {
          const select = document.getElementById('id_hermano');
          select.value = idFromUrl;
          select.dispatchEvent(new Event('change'));
      }
    });
  </script>
</body>
</html>