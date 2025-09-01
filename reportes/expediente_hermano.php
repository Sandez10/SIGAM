<?php
// expediente_hermanos.php
// ----------------------------------------------
// Página de búsqueda y vista previa del expediente
// ----------------------------------------------

// 1) Configuración inicial
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';

// 2) Toasts
if (isset($_SESSION['success_message'])) {
    $toast = ['type' => 'success','message' => $_SESSION['success_message']];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $toast = ['type' => 'error','message' => $_SESSION['error_message']];
    unset($_SESSION['error_message']);
}

// 3) Datos de logia
$datosLogia = obtenerDatosLogia();
if ($datosLogia === null) {
    die("No se pudieron obtener los datos de la logia.");
}
$logia_registro = $datosLogia['logia'];        // texto
$clave_logia    = $datosLogia['clave_logia'];  // *** varchar(255) ***
$oriente        = $datosLogia['oriente'] ?? '';

// 4) Helpers
function db() {
    return Database::getInstance()->getConnection();
}

/** Llena el select de hermanos de la logia (por clave_logia STRING) */
function obtenerListadoHermanos(string $clave_logia): array {
    $conn = db();
    $sql  = "SELECT id, nombre_completo
             FROM registros
             WHERE clave_logia = ?
             ORDER BY nombre_completo ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $clave_logia);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/** Mapea estado (si no viene de informacion_masonica, toma registros.estado_hermano) */
/** Mapea estado (si no viene de informacion_masonica, toma registros.estado_hermano) */
function mapEstado(?string $estado_im, ?int $estado_reg): string {
    if ($estado_im !== null && $estado_im !== '') return $estado_im;

    // Traducción personalizada de tinyint estado_hermano
    $map = [
        0 => 'Baja',
        1 => 'Activo',
        2 => 'Libre de la Orden',
        3 => 'Desplomado'  // Corregido: era 'Desplamado'
    ];
    return $map[$estado_reg] ?? 'No especificado';
}

/** Convierte datos LONGBLOB de fotografía a Data URI para mostrar en HTML */
function convertirFotografiaADataUri($fotografia_blob): string {
    if (empty($fotografia_blob)) {
        return '';
    }
    
    // Detectar el tipo MIME de la imagen
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($fotografia_blob);
    
    // Si no se puede detectar o no es una imagen, retornar vacío
    if (!$mime_type || !str_starts_with($mime_type, 'image/')) {
        return '';
    }
    
    // Convertir a base64 y crear Data URI
    $base64 = base64_encode($fotografia_blob);
    return "data:{$mime_type};base64,{$base64}";
}

/** Trae datos mínimos del expediente (id + clave_logia STRING) */
function obtenerDatosRegistroCompleto(int $id_hermano, string $clave_logia): array {
    $conn = db();
    $sql = "SELECT r.id, r.nombre_completo, r.domicilio,
                r.nacionalidad, r.estado_civil, r.ocupacion, r.religion, r.numero_contacto, r.numero_emergencia, r.correo_electronico,
                r.estado_hermano, r.fotografia, im.grado_masonico
            FROM registros r LEFT JOIN informacion_masonica im ON r.id = im.id_registro
            WHERE r.id = ? AND r.clave_logia = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    // id (i) + clave_logia (s)
    $stmt->bind_param("is", $id_hermano, $clave_logia);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // Convertir la fotografía LONGBLOB a Data URI
        $fotografia_uri = '';
        if (!empty($row['fotografia'])) {
            $fotografia_uri = convertirFotografiaADataUri($row['fotografia']);
        }
        
        return [
            'success' => true,
            'data' => [
                'info_personal' => [
                    'id'                => (int)$row['id'],
                    'nombre_completo'   => $row['nombre_completo'] ?? '',
                    'domicilio'         => $row['domicilio'] ?? '',
                    'nacionalidad'      => $row['nacionalidad'] ?? '',
                    'estado_civil'      => $row['estado_civil'] ?? '',
                    'ocupacion'         => $row['ocupacion'] ?? '',
                    'religion'          => $row['religion'] ?? '',
                    'telefono'          => $row['numero_contacto'] ?? '',
                    'telefono_emerg'    => $row['numero_emergencia'] ?? '',
                    'correo'            => $row['correo_electronico'] ?? '',
                    'fotografia'        => $fotografia_uri
                ],
                'info_masonica' => [
                    'grado_textual'  => $row['grado_masonico'] ?: 'No especificado',
                    'estado_textual' => mapEstado(null, isset($row['estado_hermano']) ? (int)$row['estado_hermano'] : null),
                ],
            ],
        ];
    }
    return ['success' => false, 'data' => []];
}

// 5) Llenar select
$hermanos = obtenerListadoHermanos($clave_logia);

// 6) Procesar búsqueda
$registro_completo = null;
if (!empty($_GET['id_hermano'])) {
    $id_hermano = (int)$_GET['id_hermano'];
    $resultado  = obtenerDatosRegistroCompleto($id_hermano, $clave_logia);
    if (!empty($resultado['success'])) {
        $registro_completo = $resultado['data'];
    } else {
        error_log("No se encontró expediente: id={$id_hermano}, clave_logia={$clave_logia}");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Expediente de Hermanos - SIGAM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/menu.css">
  <link rel="stylesheet" href="/SIGAM/css/expediente_hermanos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="container">
  <?php include '../configuracion/panel_menu.php'; ?>
  <main class="content">
    <header class="header glass-card">
      <div>
        <h1 class="text-2xl font-bold text-[var(--primary-color)]">Expediente de Hermanos</h1>
        <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
      </div>
    </header>

    <!-- Formulario búsqueda -->
    <div class="glass-card p-6 mt-6">
      <form method="GET" class="grid grid-cols-1 gap-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block mb-1 font-medium">Log.˙.</label>
            <input class="w-full glass-card" value="<?= htmlspecialchars($logia_registro) ?>" readonly>
          </div>
          <div>
            <label class="block mb-1 font-medium">Or.˙.</label>
            <input class="w-full glass-card" value="<?= htmlspecialchars($oriente) ?>" readonly>
          </div>
          <div class="hidden">
            <label class="block mb-1 font-medium">Clave Logia</label>
            <input class="w-full glass-card" value="<?= htmlspecialchars($clave_logia) ?>" readonly>
          </div>
        </div>

        <div>
          <label for="id_hermano" class="block mb-1 font-medium">Nombre del H.˙.</label>
          <select id="id_hermano" name="id_hermano" class="w-full glass-card">
            <option value="">Seleccionar hermano...</option>
            <?php foreach ($hermanos as $h): ?>
              <option value="<?= (int)$h['id'] ?>" <?= (isset($_GET['id_hermano']) && (int)$_GET['id_hermano'] === (int)$h['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($h['nombre_completo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex justify-end gap-4">
          <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="btn btn-secondary">Limpiar</a>
          <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
      </form>
    </div>

    <!-- Resultado -->
<!-- Resultado -->
    <?php if ($registro_completo): ?>
      <div class="glass-card p-6 mt-6">
        <h2 class="text-xl font-bold mb-4">Datos del Hermano</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Fotografía -->
          <div class="lg:col-span-1 flex justify-center">
            <?php if (!empty($registro_completo['info_personal']['fotografia'])): ?>
              <div class="w-48 h-48 border-2 border-gray-300 rounded-lg overflow-hidden shadow-lg">
                <img src="<?= htmlspecialchars($registro_completo['info_personal']['fotografia']) ?>" 
                     alt="Fotografía del hermano" 
                     class="w-full h-full object-cover"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyIiBoZWlnaHQ9IjE5MiIgdmlld0JveD0iMCAwIDE5MiAxOTIiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxOTIiIGhlaWdodD0iMTkyIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik05NiA5NkM4MC41MzYgOTYgNjggODMuNDY0IDY4IDY4QzY4IDUyLjUzNiA4MC41MzYgNDAgOTYgNDBDMTExLjQ2NCA0MCAxMjQgNTIuNTM2IDEyNCA2OEMxMjQgODMuNDY0IDExMS40NjQgOTYgOTYgOTZaIiBmaWxsPSIjOUNBM0FGIi8+CjxwYXRoIGQ9Ik05NiAxMTJDNzIuODA0IDExMiA1NCAzMC4xOTYgNTQgMTUzQzU0IDE2OC40NjQgNjYuNTM2IDE4MCA4MiAxODBIMTEwQzEyNS40NjQgMTgwIDEzOCAxNjguNDY0IDEzOCAxNTNDMTM4IDEzMC4xOTYgMTE5LjE5NiAxMTIgOTYgMTEyWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4K'; this.onerror=null;">
              </div>
            <?php else: ?>
              <div class="w-48 h-48 border-2 border-gray-300 rounded-lg flex items-center justify-center bg-gray-100">
                <div class="text-center text-gray-500">
                  <i class="fa-solid fa-user text-4xl mb-2"></i>
                  <p class="text-sm">Sin fotografía</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Datos del hermano -->
          <div class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <p><strong>Nombre:</strong> <?= htmlspecialchars($registro_completo['info_personal']['nombre_completo']) ?></p>
              <p><strong>Grado:</strong> <?= htmlspecialchars($registro_completo['info_masonica']['grado_textual']) ?></p>
              <p><strong>Estado:</strong> <?= htmlspecialchars($registro_completo['info_masonica']['estado_textual']) ?></p>
              <p><strong>Domicilio:</strong> <?= htmlspecialchars($registro_completo['info_personal']['domicilio']) ?></p>
              <p><strong>Nacionalidad:</strong> <?= htmlspecialchars($registro_completo['info_personal']['nacionalidad']) ?></p>
              <p><strong>Estado civil:</strong> <?= htmlspecialchars($registro_completo['info_personal']['estado_civil']) ?></p>
              <p><strong>Ocupación:</strong> <?= htmlspecialchars($registro_completo['info_personal']['ocupacion']) ?></p>
              <p><strong>Religión:</strong> <?= htmlspecialchars($registro_completo['info_personal']['religion']) ?></p>
              <p><strong>Teléfono:</strong> <?= htmlspecialchars($registro_completo['info_personal']['telefono']) ?></p>
              <p><strong>Emergencia:</strong> <?= htmlspecialchars($registro_completo['info_personal']['telefono_emerg']) ?></p>
              <p class="md:col-span-2"><strong>Correo:</strong> <?= htmlspecialchars($registro_completo['info_personal']['correo']) ?></p>
            </div>
          </div>
        </div>

        <div class="mt-6">
<a href="/sigam/excel_pdf/reportePDF/descargar_expediente.php?id=<?= urlencode((string)$registro_completo['info_personal']['id']) ?>"
   class="btn btn-danger inline-flex items-center gap-2">
  <i class="fa-solid fa-file-pdf"></i> Descargar información
</a>

        </div>
      </div>
    <?php elseif (isset($_GET['id_hermano']) && $_GET['id_hermano'] !== ''): ?>
      <div class="glass-card p-6 mt-6 text-red-600">
        No se encontró información del hermano seleccionado.
      </div>
    <?php endif; ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
  new TomSelect('#id_hermano', {
    placeholder: 'Buscar hermano...',
    create: false,
    searchField: ['text'],
    sortField: { field: 'text', direction: 'asc' },
    dropdownParent: 'body'
  });
</script>

<?php if (isset($toast)): ?>
<script>
Swal.fire({
  icon: '<?= $toast['type'] ?>',
  title: '<?= $toast['type']==='success' ? 'Éxito' : 'Error' ?>',
  text: '<?= addslashes($toast['message']) ?>',
  timer: <?= $toast['type']==='success' ? 3000 : 5000 ?>,
  showConfirmButton: false
});
</script>
<?php endif; ?>
</body>
</html>
