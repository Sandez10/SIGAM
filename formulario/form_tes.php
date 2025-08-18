<?php
// Configuración inicial
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';
require_once '../registros_b/obtener_registro.php';
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

// Obtener conexión
$database = Database::getInstance();
$conn = $database->getConnection();

// Obtener datos de la logía
$datosLogia = obtenerDatosLogia();

if ($datosLogia === null) {
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

// Asignar variables
$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];
$oriente = $datosLogia['oriente'];
// Depuración [Eliminar en producción].
error_log("Logia obtenida: ".$logia_registro);
error_log("Clave logia obtenida: ".$clave_logia);
error_log("Clave logia obtenida: ".$oriente);

// Obtener logia primero
$datosLogia = obtenerDatosLogia();
$logia_registro = $datosLogia['logia'] ?? null;

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
        <div class="flex gap-3">
        </div>
      </header>

      <!-- Form -->
      <div class="glass-card p-6 mt-6">
        <h3 class="text-xl font-semibold mb-6">Movimiento de Tesorería</h3>
          <form action="../guardar-tesoreria/" method="POST" id="tesoreriaForm" class="grid grid-cols-1 gap-8 text-sm" enctype="multipart/form-data" novalidate>
          <!-- Logia Details -->
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <!-- Campo Logia -->

          <div class="collapsible">
            <div class="collapsible-header">
              <h4 class="font-medium">Información General</h4>
              <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div class="collapsible-content open">
              <div class="grid grid-cols-2 gap-6 p-4">
            <div class="group relative">
                <label for="logia" class="block mb-1 font-medium">Log.˙.</label>
                <input type="text" id="logia" name="logia" 
                      value="<?php echo htmlspecialchars($logia_registro ?? ''); ?>" 
                      class="w-full glass-card" readonly>
            </div>
            <div class="group relative">
                <label for="oriente" class="block mb-1 font-medium">Or.˙.</label>
                <input type="text" id="oriente" name="oriente" 
                      value="<?php echo htmlspecialchars($oriente ?? ''); ?>" 
                      class="w-full glass-card" readonly>
                <p class="error-message hidden" id="logia-error">Este campo es obligatorio</p>
            </div>
            <div class="group relative hidden">
                <label for="clave_logia" class="block mb-1 font-medium">Clave Logia</label>
                <input type="text" id="clave_logia" name="clave_logia" 
                      value="<?php echo htmlspecialchars($clave_logia ?? ''); ?>" 
                      class="w-full glass-card" readonly>
                <p class="error-message hidden" id="logia-error">Este campo es obligatorio</p>
            </div>
            <!-- Campo Nombre del Hermano con búsqueda dinámica (select) -->
            <div class="group relative">
                <label for="id_hermano" class="block mb-1 font-medium">Nombre del H.˙.</label>
                <select id="id_hermano" name="id_hermano"class="w-full glass-card">
                    <option value="">Seleccionar hermano...</option>
                    <?php foreach ($hermanos as $hermano): ?>
                    <option value="<?= $hermano['id'] ?>">
                        <?= htmlspecialchars($hermano['nombre_completo']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="group relative mb-4">
                    <label for="grado" class="block mb-1 font-medium">Grado Masónico</label>
                    <input type="text" id="grado" name="grado" class="w-full glass-card" readonly>
                </div>

                <div class="group relative">
                  <label for="estado" class="block mb-1 font-medium">Estado Administrativo</label>
                  <select id="estado" name="estado" class="w-full glass-card">
                    <option value="">Selecciona el estado...</option>
                    <option value="1">Activo</option>
                    <option value="0">Baja</option>
                    <option value="3">Desplomado</option>
                    <option value="2">Libre de orden</option>
                  </select>
                </div>
                <div class="group relative">
                  <label for="inciacion" class="block mb-1 font-medium">Iniciación</label>
                  <select id="iniciacion" name="iniciacion" class="w-full glass-card">
                    <option value="">Seleccione la cantidad de iniación</option>
                    <option value="350">$350.00</option>
                  </select>
                </div>
                <div class="group relative">
                  <label for="capitas" class="block mb-1 font-medium">Capitas</label>
                  <select id="capitas" name="capitas" class="w-full glass-card">
                    <option value="">Seleccione la cantidad de Capitas</option>
                    <option value="100">$100.00</option>
                    <option value="200">$200.00</option>
                    <option value="300">$300.00</option>                    
                  </select>
                </div>
                <div class="group relative">
                  <label for="Salario" class="block mb-1 font-medium">Salario</label>
                  <select id="iniciacion" name="iniciacion" class="w-full glass-card">
                    <option value="">Seleccione el Salario</option>
                    <option value="450">$450.00</option>
                  </select>
                </div>
                <div class="group relative">
                  <label for="Seguro" class="block mb-1 font-medium">Seguro Mas.˙.</label>
                  <select id="seguro" name="seguro" class="w-full glass-card">
                    <option value="">Seleccione la cantidad para el seguro</option>
                    <option value="30">$30.00</option>
                  </select>
                </div>
<!--                <div class="group relative">
                  <label for="oriente" class="block mb-1 font-medium">Iniciación</label>
                  <input type="number" id ="iniciacion" name="iniciacion" class="w-full glass-card" placeholder="Ingresar la cantidad de capitas" required />
                  <p class="error-message hidden" id="oriente-error">Este campo es obligatorio</p>
                </div>
                <div class="group relative">
                  <label for="oriente" class="block mb-1 font-medium">Capitas</label>
                  <input type="number" id ="capitas" name="capitas" class="w-full glass-card" placeholder="Ingresar la cantidad de capitas" required />
                  <p class="error-message hidden" id="oriente-error">Este campo es obligatorio</p>
                </div>
              <div id="grupo_aumento_salario" class="group relative hidden">
                <label for="aumento_salario" class="block mb-1 font-medium">Aumento de Salario</label>
                <input type="number" id="aumento_salario" name="aumento_salario" class="w-full glass-card" placeholder="Ingresar la cantidad de Aumento de salario" />
                <p class="error-message hidden" id="oriente-error">Este campo es obligatorio</p>
              </div>

                <div class="group relative">
                  <label for="oriente" class="block mb-1 font-medium">Seguro Mas.˙.</label>
                  <input id="seguro" type="number" name="seguro" class="w-full glass-card" placeholder="Ingresa el cantidad de Seguro" required />
                  <p class="error-message hidden" id="oriente-error">Este campo es obligatorio</p>
                </div>-->
                <div class="group relative">
                  <label for="afiliacion" class="block mb-1 font-medium">Afiliación</label>
                  <input id="afiliacion" type="number" name="afiliacion" class="w-full glass-card" placeholder="Ingresa la cantidad de Afiliación" required />
                  <p class="error-message hidden" id="oriente-error">Este campo es obligatorio</p>
                </div>
<div id="grupo_exaltacion" class="group relative hidden">
  <label for="exaltacion" class="block mb-1 font-medium">Exaltación</label>
  <input type="date" id="exaltacion" name="exaltacion" class="w-full glass-card" />
  <p class="error-message hidden" id="exaltacion-error">Este campo es obligatorio</p>
</div>

              </div>
            </div>
          </div>
          <div class="flex justify-end gap-4 mt-6">
            <button type="reset" class="btn btn-secondary">
              <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              Limpiar
            </button>
            <button type="submit" class="btn btn-primary">
              <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
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
<!-- Script para mostrar vista previa -->
<script src="../js/foto_galeria.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="../js/info.js"> </script>
  <script><script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Inicializar select de hermanos con búsqueda dinámica
    const hermanoSelect = new TomSelect('#id_hermano', {
        placeholder: 'Buscar hermano...',
        create: false,
        sortField: {
            field: "text",
            direction: "asc"
        },
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

    // Manejar cambio de hermano
    document.getElementById('id_hermano').addEventListener('change', function() {
        const hermanoId = this.value;
        const gradoInput = document.getElementById('grado');

        if (hermanoId) {
            const hermanos = <?php echo json_encode($hermanos); ?>;
            const hermanoSeleccionado = hermanos.find(h => h.id == hermanoId);

            if (hermanoSeleccionado) {
                gradoInput.value = hermanoSeleccionado.grado_textual || '';

                const estadoInput = document.getElementById('estado');
                const estadoTextual = (hermanoSeleccionado.estado_textual || '').toLowerCase();

                let estadoValue = '';
                switch (estadoTextual) {
                  case 'activo': estadoValue = '1'; break;
                  case 'baja': estadoValue = '0'; break;
                  case 'libre de la orden':
                  case 'libre de orden': estadoValue = '2'; break;
                  case 'desplomado': estadoValue = '3'; break;
                }

                estadoInput.value = estadoValue;

                // NUEVO BLOQUE: Mostrar u ocultar el campo "Aumento de Salario"
                const grupoAumento = document.getElementById('grupo_aumento_salario');
                const aumentoInput = document.getElementById('aumento_salario');
                const grado = (hermanoSeleccionado.grado_textual || '').toLowerCase();

                if (grupoAumento && aumentoInput) {
                    if (grado === 'aprendiz') {
                        grupoAumento.classList.remove('hidden');
                        aumentoInput.disabled = false;
                    } else {
                        grupoAumento.classList.add('hidden');
                        aumentoInput.value = '';
                        aumentoInput.disabled = true;
                    }
                }
                // Mostrar u ocultar el campo "Exaltación"
                const grupoExaltacion = document.getElementById('grupo_exaltacion');
                const exaltacionInput = document.getElementById('exaltacion');

                if (grupoExaltacion && exaltacionInput) {
                    if (grado === 'compañero') {
                        grupoExaltacion.classList.remove('hidden');
                        exaltacionInput.disabled = false;
                    } else {
                        grupoExaltacion.classList.add('hidden');
                        exaltacionInput.value = '';
                        exaltacionInput.disabled = true;
                    }
                }
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

        document.dispatchEvent(new CustomEvent('hermanoCambiado', {
            detail: { hermanoId, hermano: hermanoSeleccionado }
        }));
    });

    // Cargar hermano desde URL al inicio
    const urlParams = new URLSearchParams(window.location.search);
    const idFromUrl = urlParams.get('id');
    if (idFromUrl) {
        const select = document.getElementById('id_hermano');
        select.value = idFromUrl;
        select.dispatchEvent(new Event('change'));
    }
  });

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

</body>
</html>
