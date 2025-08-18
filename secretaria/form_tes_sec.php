<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Verificar si es una petición AJAX para buscar hermanos
if (isset($_GET['q'])) {
    $query = $_GET['q'];
    $datosLogia = obtenerDatosLogia();
    
    if($datosLogia === null){
        http_response_code(500);
        echo json_encode(['error' => 'No se pudieron obtener los datos de la logia']);
        exit;
    }
    
    $clave_logia = $datosLogia['clave_logia'];
    
    // Consulta con JOIN y búsqueda
    $sql = "SELECT r.id, r.nombre_completo, im.grado_masonico 
            FROM registros r LEFT JOIN informacion_masonica im ON r.id = im.id_registro
            WHERE r.clave_logia = ? AND r.nombre_completo LIKE ?";
    
    $stmt = $conn->prepare($sql);
    $searchTerm = '%' . $query . '%';
    $stmt->bind_param("ss", $clave_logia, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $text = $row['nombre_completo'] . ' (' . ($row['grado_masonico'] ?? 'Sin grado') . ')';
        $data[] = [
            'value' => $row['nombre_completo'],
            'text' => $text
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Código para mostrar la página normal
$datosLogia = obtenerDatosLogia();

if($datosLogia === null){
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];
$oriente = $datosLogia['oriente'];

$anio = date('Y');
$trimestre = ceil(date('n') / 3); // trimestre actual basado en el mes
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="description" content="Registro de Secretaría para SIGAM - Gran Logia del Estado de Guerrero" />
  <title>Secretaría - SIGAM</title>
  <link rel="stylesheet" href="../css/menu.css" />
  <link rel="stylesheet" href="../../css/form_tes_sec.css" />
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

  <!-- Fondos animados -->
  <div class="fixed bottom-0 left-20 w-full h-32 floating"></div>
  <div class="fixed top-1/4 right-10 w-20 h-20 rounded-full floating delay-1"></div>
  <div class="fixed top-1/3 left-40 w-16 h-16 rounded-full floating delay-2"></div>
  <div class="fixed bottom-1/4 right-1/4 w-24 h-24 rounded-full floating delay-3"></div>

  <div class="container">
    <?php include '../configuracion/panel_menu.php'; ?>

    <main class="content">
      <header class="header glass-card">
        <div>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
        </div>
      </header>

      <!-- Formulario -->
      <form action="/sigam/secretaria/alta/r/" method="POST" class="p-4">
        <input type="hidden" name="clave_logia" value="<?= htmlspecialchars($clave_logia) ?>">
        <input type="hidden" name="anio" value="<?= htmlspecialchars($anio) ?>">
        <input type="hidden" name="trimestre" value="<?= htmlspecialchars($trimestre) ?>">

        <div class="glass-card p-4 space-y-6">
          <h3 class="text-lg font-semibold">Registro de Tesorero y Secretario</h3>

          <div>
            <label for="nombre_tesorero" class="block font-medium mb-1">Tesorero:</label>
            <select id="nombre_tesorero" name="nombre_tesorero" class="glass-card w-full" placeholder="Buscar hermano..." required></select>
          </div>

          <div>
            <label for="nombre_secretario" class="block font-medium mb-1">Secretario:</label>
            <select id="nombre_secretario" name="nombre_secretario" class="glass-card w-full" placeholder="Buscar hermano..." required></select>
          </div>

          <div class="flex justify-end gap-4">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <button type="reset" class="btn btn-secondary">Limpiar</button>
          </div>
        </div>
      </form>
    </main>
  </div>

  <div class="menu-overlay"></div>

  <!-- Scripts -->
  <script src="../js/foto_galeria.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="../js/menu_mobile.js"></script>

  <script>
document.addEventListener('DOMContentLoaded', function() {
    const initTomSelect = (selectorId, defaultValue = '') => {
        new TomSelect(selectorId, {
            valueField: "value",
            labelField: "text", 
            searchField: "text",
            load: function(query, callback) {
                if (!query.length) return callback();
                
                fetch("<?= $_SERVER['PHP_SELF'] ?>?q=" + encodeURIComponent(query))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        callback(data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        callback();
                    });
            },
            onInitialize: function() {
                if (defaultValue) {
                    this.addOption({ value: defaultValue, text: defaultValue });
                    this.setValue(defaultValue);
                }
            }
        });
    };

    // Inicializar los selectores
    initTomSelect('#nombre_tesorero');
    initTomSelect('#nombre_secretario');
});
  </script>

  <?php if (isset($toast)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
          icon: '<?= $toast['type'] ?>',
          title: '<?= $toast['type'] === 'success' ? 'Éxito' : 'Error' ?>',
          text: '<?= addslashes($toast['message']) ?>',
          confirmButtonColor: '<?= $toast['type'] === 'success' ? '#3085d6' : '#d33' ?>',
          timer: <?= $toast['type'] === 'success' ? '3000' : '5000' ?>
        });
      });
    </script>
  <?php endif; ?>
</body>
</html>