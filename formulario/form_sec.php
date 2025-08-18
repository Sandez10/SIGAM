<?php
require_once '../sesiones_conexiones/sesion_config.php'; 
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';

$database = Database::getInstance();
$connection = $database->getConnection();
$datosLogia = obtenerDatosLogia();

if($datosLogia === null){
    die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}

$logia_registro = $datosLogia['logia'];
$clave_logia = $datosLogia['clave_logia'];
$oriente = $datosLogia['oriente'];

// Verificar si estamos en modo edición
$modo_edicion = isset($_GET['editar']) && !empty($_GET['id']);
$registro_actual = null;

if ($modo_edicion) {
    $id_registro = intval($_GET['id']);
    
    // Obtener datos del registro para edición
    $stmt = $connection->prepare("SELECT r.*, im.*, c.tipo_cargo, c.es_dignatario,
               GROUP_CONCAT(pm.fecha) as past_master_fechas,
               pm.periodo
        FROM registros r
        LEFT JOIN informacion_masonica im ON r.id = im.id_registro
        LEFT JOIN cargos c ON r.id = c.id_registro
        LEFT JOIN past_masters pm ON r.id = pm.id_registro
        WHERE r.id = ?
        GROUP BY r.id
    ");
    
    $stmt->bind_param("i", $id_registro);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $registro_actual = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = 'Registro no encontrado';
        header('Location: secretaria/');
        exit();
    }
}

// Obtener lista de registros para el selector
$stmt_lista = $connection->prepare("SELECT id, nombre_completo, clave_logia FROM registros WHERE clave_logia = ? ORDER BY nombre_completo");
$stmt_lista->bind_param("i", $clave_logia);
//$stmt_lista = $connection->prepare("SELECT id, nombre_completo FROM registros ORDER BY nombre_completo");
$stmt_lista->execute();
$lista_registros = $stmt_lista->get_result()->fetch_all(MYSQLI_ASSOC);

// Buffer de salida
ob_start();

// Manejo de mensajes flash
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

// Redirigir si no hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../");
    exit;
}

// Verificar si el rol es admin
if ($_SESSION['rol'] !== 'superadmin') {
    echo "<script>
        alert('No tienes permiso para acceder a esta página.');
        history.back();
    </script>";
    exit();
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta name="description" content="Registro de Secretaría para SIGAM - Gran Logia del Estado de Guerrero" />
  <title><?php echo $modo_edicion ? 'Editar' : 'Registro de'; ?> Secretaría - SIGAM</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/formulario_sec.css" />
  <link rel="stylesheet" href="../css/formulario_edit.css" />
    <link rel="stylesheet" href="../css/menu.css" />
</head>
<body>
  <!-- Decorative Elements -->
  <div class="fixed bottom-0 left-20 w-full h-32 floating"></div>
  <div class="fixed top-1/4 right-10 w-20 h-20 rounded-full floating delay-1"></div>
  <div class="fixed top-1/3 left-40 w-16 h-16 rounded-full floating delay-2"></div>
  <div class="fixed bottom-1/4 right-1/4 w-24 h-24 rounded-full floating delay-3"></div>
  <div class="container">
  <?php include '../configuracion/panel_menu.php'; ?>
<!-- Overlay -->


    <!-- Main Content -->
    <main class="content">
      <!-- Header -->
      <header class="header glass-card">
        <div>
          <h1 class="text-2xl font-bold text-[var(--primary-color)]">
            <?php echo $modo_edicion ? 'Editar Registro' : 'Registro de Secretaría'; ?>
          </h1>
          <p class="text-sm text-[var(--text-light)]">Gran Logia del Estado de Guerrero</p>
          <?php if ($modo_edicion): ?>
            <div class="mt-2">
              <span class="edit-indicator">
                ✏️ Editando: <?php echo htmlspecialchars($registro_actual['nombre_completo']); ?>
              </span>
            </div>
          <?php endif; ?>
        </div>

      </header>
      <!-- Selector de Modo -->
      <div class="modo-selector glass-card">
        <div class="modo-tabs">
          <div class="modo-tab <?php echo !$modo_edicion ? 'active' : ''; ?>" onclick="cambiarModo('nuevo')">
            ➕ Nuevo Registro
          </div>
          <div class="modo-tab <?php echo $modo_edicion ? 'active' : ''; ?>" onclick="cambiarModo('editar')">
            ✏️ Editar Registro
          </div>
        </div>
        
        <div id="selector-registro" style="<?php echo !$modo_edicion ? 'display: none;' : ''; ?>">
          <label class="block mb-2 font-medium">Seleccionar registro para editar:</label>
          <select class="registro-selector" id="lista-registros" onchange="cargarRegistro()">
            <option value="">-- Selecciona un hermano --</option>
            <?php foreach ($lista_registros as $reg): ?>
              <option value="<?php echo $reg['id']; ?>" <?php echo ($modo_edicion && $reg['id'] == $id_registro) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($reg['nombre_completo']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Form -->
      <div class="glass-card p-6">
        <h3 class="text-xl font-semibold mb-6">
          <?php echo $modo_edicion ? 'Editar Información' : 'Nueva Información'; ?>
        </h3>
        
        <form action="<?php echo $modo_edicion ? '../actualizar/' : '../guardar/'; ?>" 
              method="POST" id="tesoreriaForm" class="grid grid-cols-1 gap-8 text-sm" enctype="multipart/form-data" novalidate>
          
          <!-- Campos ocultos -->
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" id="logia" name="logia" value="<?php echo !empty($logia_registro) ? $logia_registro : ''; ?>">
          <input type="hidden" id="clave_logia" name="clave_logia" value="<?php echo !empty($clave_logia) ? $clave_logia : ''; ?>">
          <input type="hidden" id="oriente" name="oriente" value="<?php echo !empty($oriente) ? $oriente : ''; ?>">

          <?php if ($modo_edicion): ?>
            <input type="hidden" name="id_registro" value="<?php echo $id_registro; ?>">
          <?php endif; ?>
          
          <!-- Información General -->
          <div class="collapsible">
            <div class="collapsible-header">
              <h4 class="font-medium">Información General</h4>
              <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div class="collapsible-content open">
              <div class="grid grid-cols-2 gap-6 p-4">
                <div class="group relative">
                  <label for="nombre" class="block mb-1 font-medium">Nombre Completo</label>
                  <input type="text" id="nombreid" name="nombre_completo" class="w-full glass-card" 
                         placeholder="Ingresar el Nombre Completo" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['nombre_completo']) : ''; ?>" required>
                  <p class="error-message hidden" id="nombre-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Nombre completo del hermano</span>
                </div>
                
                <div class="group relative">
                  <label for="estado" class="block mb-1 font-medium">Estado</label>
                  <select id="estado_hermano" name="estado_hermano" class="w-full" required>
                    <option value="">Selecciona el tipo de Estado</option>
                    <option value="1" <?php echo ($modo_edicion && $registro_actual['estado_hermano'] == '1') ? 'selected' : ''; ?>>Activo</option>
                    <option value="2" <?php echo ($modo_edicion && $registro_actual['estado_hermano'] == '2') ? 'selected' : ''; ?>>Libre de la Orden</option>
                    <option value="3" <?php echo ($modo_edicion && $registro_actual['estado_hermano'] == '3') ? 'selected' : ''; ?>>Desplomado</option>
                    <option value="0" <?php echo ($modo_edicion && $registro_actual['estado_hermano'] == '0') ? 'selected' : ''; ?>>Baja</option> 
                  </select>
                  <p class="error-message hidden" id="estado-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Estado actual del hermano</span>
                </div>
                
                <div class="group relative">
                  <label for="domicilio" class="block mb-1 font-medium">Domicilio</label>
                  <input id="domicilioid" type="text" name="domicilio" class="w-full glass-card" 
                         placeholder="Ingresar un Domicilio" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['domicilio']) : ''; ?>" required />
                  <p class="error-message hidden" id="domicilio-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Domicilio actual</span>
                </div>
                
                <div class="group relative">
                  <label for="nacionalidad" class="block mb-1 font-medium">Nacionalidad</label>
                  <input id="nacionalidad" type="text" name="nacionalidad" class="w-full glass-card" 
                         placeholder="Ingresar Nacionalidad" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['nacionalidad']) : ''; ?>" required />
                  <p class="error-message hidden" id="nacionalidad-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Nacionalidad del hermano</span>
                </div>
                
                <div class="group relative">
                  <label for="estado_civil" class="block mb-1 font-medium">Estado Civil</label>
                  <input id="estado_civil" type="text" name="estado_civil" class="w-full glass-card" 
                         placeholder="Ingresar el Estado Civil" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['estado_civil']) : ''; ?>" required />
                  <p class="error-message hidden" id="estado_civil-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Estado civil actual</span>
                </div>
                
                <div class="group relative">
                  <label for="ocupacion" class="block mb-1 font-medium">Ocupación</label>
                  <input id="ocupacion" type="text" name="ocupacion" class="w-full glass-card" 
                         placeholder="Ingresar la Ocupación Actual" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['ocupacion']) : ''; ?>" required />
                  <p class="error-message hidden" id="ocupacion-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Ocupación profesional</span>
                </div>
                
                <div class="group relative">
                  <label for="religion" class="block mb-1 font-medium">Religión</label>
                  <input id="religion" type="text" name="religion" class="w-full glass-card" 
                         placeholder="Ingresar la religión" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['religion']) : ''; ?>" required />
                  <p class="error-message hidden" id="religion-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Religión que profesa</span>
                </div>
                
                <div class="group relative">
                  <label for="numero_contacto" class="block mb-1 font-medium">Número de contacto</label>
                  <input id="numero_contacto" type="text" name="numero_contacto" class="w-full glass-card" 
                         placeholder="Ingresar un Número de Contacto" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['numero_contacto']) : ''; ?>" required />
                  <p class="error-message hidden" id="numero_contacto-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Número de teléfono principal</span>
                </div>
                
                <div class="group relative">
                  <label for="numero_emergencia" class="block mb-1 font-medium">Número de Emergencia</label>
                  <input id="numero_emergencia" type="text" name="numero_emergencia" class="w-full glass-card" 
                         placeholder="Ingresar un Número de Emergencia" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['numero_emergencia']) : ''; ?>" required />
                  <p class="error-message hidden" id="numero_emergencia-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Número de contacto de emergencia</span>
                </div>
                
                <div class="group relative">
                  <label for="correo_electronico" class="block mb-1 font-medium">Correo Electrónico</label>
                  <input id="correo_electronico" type="email" name="correo_electronico" class="w-full glass-card" 
                         placeholder="Ingresa un Correo Electrónico" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['correo_electronico']) : ''; ?>" required />
                  <p class="error-message hidden" id="correo_electronico-error">Este campo es obligatorio</p>
                  <span class="tooltip top-[-2.5rem] left-1/2 transform -translate-x-1/2">Correo electrónico válido</span>
                </div>
                
                <div class="group relative">
                  <label class="block mb-1 font-medium">Fotografía</label>
                  
                  <?php if ($modo_edicion && !empty($registro_actual['fotografia'])): ?>
                    <div class="mb-3">
                      <p class="text-sm text-gray-600 mb-2">Fotografía actual:</p>
                      <img src="data:image/jpeg;base64,<?php echo base64_encode($registro_actual['fotografia']); ?>" 
                           class="preview-image-edit" alt="Foto actual">
                      <div class="mt-2">
                        <label class="inline-flex items-center">
                          <input type="checkbox" name="eliminar_foto" value="1" class="mr-2">
                          <span class="text-sm">Eliminar foto actual</span>
                        </label>
                      </div>
                    </div>
                  <?php endif; ?>
                  
                  <div id="photo-options" class="flex gap-2 mb-2">
                    <button type="button" id="btn-camera" class="glass-card flex-1 py-2 hidden md:hidden">
                      📸 Tomar foto
                    </button>
                    <button type="button" id="btn-gallery" class="glass-card flex-1 py-2">
                      🖼️ <?php echo $modo_edicion ? 'Cambiar foto' : 'Galería'; ?>
                    </button>
                  </div>
                  
                  <input id="foto-input" type="file" name="foto" accept="image/*" class="hidden">
                  
                  <div id="preview-container" class="mt-2 hidden">
                    <img id="preview-image" class="max-w-[150px] rounded-md border border-gray-300" src="#" alt="Vista previa">
                    <button type="button" id="btn-remove" class="text-red-500 text-sm mt-1">✕ Eliminar foto</button>
                  </div>
                  
                  <p class="error-message hidden" id="foto-error">Error en la imagen</p>
                </div>
                <div class="group relative">
                  <label for="observaciones" class="block mb-1 font-medium">Observaciones</label>
                  <textarea id="observaciones" name="observaciones" class="w-full glass-card" rows="4" placeholder="Agregar observaciones..."><?php echo $modo_edicion ? htmlspecialchars($registro_actual['observaciones']) : ''; ?></textarea>
                </div>
                <div class="group relative">
  <label class="block mb-1 font-medium">Firma Digital</label>

  <button type="button" onclick="abrirModalFirma()" class="btn btn-primary text-sm">Agregar Firma</button>
  <!-- Campo oculto donde se guardará la firma en base64 -->
  <input type="hidden" name="firma_base64" id="firma_base64">

  <!-- Vista previa de firma -->
  <div id="firmaPreview" class="mt-2 hidden">
    <p class="text-sm mb-1">Vista previa:</p>
    <img id="firmaPreviewImg" src="" class="w-40 border rounded" alt="Firma digital">
  </div>

  <?php if ($modo_edicion && !empty($registro_actual['firma_path'])): ?>
    <div class="mt-4">
      <p class="text-sm">Firma actual:</p>
      <img src="../firmas/<?php echo htmlspecialchars($registro_actual['firma_path']); ?>" alt="Firma actual" class="w-40 border rounded">
      <label class="inline-flex items-center mt-2">
        <input type="checkbox" name="eliminar_firma" value="1" class="mr-2">
        <span class="text-sm">Eliminar firma actual</span>
      </label>
    </div>
  <?php endif; ?>
</div>

              </div>
            </div>
          </div>

          <!-- Información Masónica -->
          <div class="collapsible">
            <div class="collapsible-header">
              <h4 class="font-medium">Información Masónica</h4>
              <svg class="w-5 h-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div class="collapsible-content open">
              <div class="grid grid-cols-2 gap-6 p-4">
                <div class="group relative">
                  <label for="grado_masonico" class="block mb-1 font-medium">Grado Masónico</label>
                  <select id="Tipo_grado" name="grado_masonico" class="w-full" required onchange="actualizarCampos()">
                    <option value="">Seleccione el tipo de Grado</option>
                    <option value="aprendiz" <?php echo ($modo_edicion && $registro_actual['grado_masonico'] == 'aprendiz') ? 'selected' : ''; ?>>Aprendiz</option>
                    <option value="companero" <?php echo ($modo_edicion && $registro_actual['grado_masonico'] == 'companero') ? 'selected' : ''; ?>>Compañero</option>
                    <option value="maestro" <?php echo ($modo_edicion && $registro_actual['grado_masonico'] == 'maestro') ? 'selected' : ''; ?>>Maestro</option>
                  </select>
                </div>

                <div class="group relative" id="tipo_ingreso_container">
                  <label for="tipo_ingreso" class="block mb-1 font-medium">Tipo de Ingreso</label>
                  <select id="tipo_ingreso" name="tipo_ingreso" class="w-full" required>
                    <option value="">Selecciona el tipo de Ingreso</option>
                    <option value="iniciacion" <?php echo ($modo_edicion && $registro_actual['tipo_ingreso'] == 'iniciacion') ? 'selected' : ''; ?>>Iniciación</option>
                    <option value="afiliacion" <?php echo ($modo_edicion && $registro_actual['tipo_ingreso'] == 'afiliacion') ? 'selected' : ''; ?>>Afiliación</option> 
                  </select>
                </div>

                <div class="group relative" id="fecha_inicio_container">
                  <label for="fecha_inicio" class="block mb-1 font-medium">Fecha de Iniciación</label>
                  <input type="date" name="fecha_inicio" id="fecha_inicio" 
                         value="<?php echo $modo_edicion ? $registro_actual['fecha_inicio'] : ''; ?>">
                </div>

                <div class="group relative hidden" id="salario_container">
                  <label for="fecha_aumento_salario" class="block mb-1 font-medium">Fecha de Aumento de Salario</label>
                  <input type="date" name="fecha_aumento_salario" id="fecha_aumento_salario" 
                         value="<?php echo $modo_edicion ? $registro_actual['fecha_aumento_salario'] : ''; ?>">
                </div>

                <div class="group relative hidden" id="fecha_exaltacion_container">
                  <label for="fecha_exaltacion" class="block mb-1 font-medium">Fecha de Exaltación</label>
                  <input type="date" name="fecha_exaltacion" id="fecha_exaltacion" 
                         value="<?php echo $modo_edicion ? $registro_actual['fecha_exaltacion'] : ''; ?>">
                </div>
                
                <div class="group relative hidden" id="fecha_afiliacion_container">
                  <label for="fecha_afiliacion" class="block mb-1 font-medium">Fecha de Afiliación</label>
                  <input type="date" name="fecha_afiliacion" id="fecha_afiliacion" 
                         value="<?php echo $modo_edicion ? $registro_actual['fecha_afiliacion'] : ''; ?>">
                </div>
                
                <div class="group relative hidden" id="dignatorio_oficial_container">
                  <label class="block mb-1 font-medium">¿Es Dignatario u Oficial?</label>
                  <div class="flex items-center gap-6">
                    <label class="inline-flex items-center">
                      <input type="radio" name="dignatario_oficial" id="dignatario_si" value="si" class="mr-1" 
                             <?php echo ($modo_edicion && $registro_actual['es_dignatario'] == 1) ? 'checked' : ''; ?>>
                      <span>Sí</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="radio" name="dignatario_oficial" id="dignatario_no" value="no" class="mr-1" 
                             <?php echo (!$modo_edicion || $registro_actual['es_dignatario'] != 1) ? 'checked' : ''; ?>>
                      <span>No</span>
                    </label>
                  </div>

                  <div id="dignatario_select_container" class="mt-3 <?php echo (!$modo_edicion || $registro_actual['es_dignatario'] != 1) ? 'hidden' : ''; ?>">
                    <select id="dignatario_select" name="dignatorio" class="w-full glass-card">
                      <option value="">Selecciona un cargo</option>
                      <option value="venerable_maestro" <?php echo ($modo_edicion && $registro_actual['tipo_cargo'] == 'venerable_maestro') ? 'selected' : ''; ?>>Venerable Maestro</option>
                      <option value="primer_vigilante" <?php echo ($modo_edicion && $registro_actual['tipo_cargo'] == 'primer_vigilante') ? 'selected' : ''; ?>>Primer Vigilante</option>
                      <option value="segundo_vigilante" <?php echo ($modo_edicion && $registro_actual['tipo_cargo'] == 'segundo_vigilante') ? 'selected' : ''; ?>>Segundo Vigilante</option>
                      <option value="orador" <?php echo ($modo_edicion && $registro_actual['tipo_cargo'] == 'orador') ? 'selected' : ''; ?>>Orador</option>
                      <option value="secretario" <?php echo ($modo_edicion && $registro_actual['tipo_cargo'] == 'secretario') ? 'selected' : ''; ?>>Secretario</option>
                      <option value="tesorero" <?php echo ($modo_edicion && $registro_actual['tipo_cargo'] == 'tesorero') ? 'selected' : ''; ?>>Tesorero</option>
                    </select>
                  </div>
                </div>
                
                <div class="group relative hidden" id="master_container">
                  <label class="block mb-1 font-medium">¿Past‑Master?</label>
                  <div class="flex items-center gap-6">
                    <label class="inline-flex items-center">
                      <input type="radio" name="past_master" id="past_master_si" value="si" class="mr-1" 
                             <?php echo ($modo_edicion && !empty($registro_actual['past_master_fechas'])) ? 'checked' : ''; ?>>
                      <span>Sí</span>
                    </label>
                    <label class="inline-flex items-center">
                      <input type="radio" name="past_master" id="past_master_no" value="no" class="mr-1" 
                             <?php echo (!$modo_edicion || empty($registro_actual['past_master_fechas'])) ? 'checked' : ''; ?>>
                      <span>No</span>
                    </label>
                  </div>

                  <div id="master_fechas_container" class="mt-3 <?php echo (!$modo_edicion || empty($registro_actual['past_master_fechas'])) ? 'hidden' : ''; ?>">
                    <div id="master_fechas_inputs" class="space-y-2">
                      <?php if ($modo_edicion && !empty($registro_actual['past_master_fechas'])): ?>
                        <?php $fechas = explode(',', $registro_actual['past_master_fechas']); ?>
                        <?php foreach ($fechas as $fecha): ?>
                          <div class="flex items-center gap-2 fecha-row">
                            <input type="date" name="past_master_fechas[]" class="w-full glass-card" value="<?php echo trim($fecha); ?>">
                            <button type="button" class="btn btn-error btn-del-fecha text-xs px-2">✕</button>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="flex items-center gap-2 fecha-row">
                          <input type="date" name="past_master_fechas[]" class="w-full glass-card">
                          <button type="button" class="btn btn-error btn-del-fecha text-xs px-2">✕</button>
                        </div>
                      <?php endif; ?>
                    </div>

                    <button type="button" id="add_master_fecha" class="btn btn-secondary text-xs mt-2">
                      + Agregar otra fecha
                    </button>
                  </div>
                </div>
                
                <div class="group relative hidden" id="periodo_container">
                  <label for="periodo_vm" class="block mb-1 font-medium">Periodo (s) de V∴M∴</label>
                  <input id="periodo_vm" type="text" name="periodo_vm" class="w-full glass-card" 
                         placeholder="Ingresa el periodo" 
                         value="<?php echo $modo_edicion ? htmlspecialchars($registro_actual['periodo']) : ''; ?>" />
                </div>
              </div>
            </div>
          </div>
          
          <!-- Botones de acción fijos -->
          <div class="form-actions">
            <div class="flex justify-end gap-4">
              <button type="reset" class="btn btn-secondary">
                <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Limpiar
              </button>
              <button type="submit" class="btn btn-primary">
                <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                <?php echo $modo_edicion ? 'Actualizar' : 'Guardar'; ?>
              </button>
            </div>
          </div>
        </form>
      </div>
    
      
      <div id="toast" class="toast" role="alert" aria-live="assertive"></div>
    </main>
  </div>
<div id="modalFirma" class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50 hidden">
  <div class="w-full h-full flex flex-col justify-between bg-white p-4">
    <h2 class="text-xl font-bold text-center mb-2">Firme en el área inferior</h2>
    <canvas id="firmaCanvas" class="border rounded-md flex-grow bg-white"></canvas>

    <div class="flex justify-between mt-4 gap-2">
      <button onclick="limpiarFirma()" class="btn btn-secondary"> Limpiar</button>
      <button onclick="cerrarModalFirma()" class="btn btn-success"> Guardar Firma</button>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
<script>
let signaturePad;
let canvas;

function abrirModalFirma() {
  const modal = document.getElementById('modalFirma');
  modal.classList.remove('hidden');

  canvas = document.getElementById('firmaCanvas');
  ajustarCanvasAlDispositivo();

  signaturePad = new SignaturePad(canvas);
}

function cerrarModalFirma() {
  const modal = document.getElementById('modalFirma');

  if (!signaturePad.isEmpty()) {
    const base64 = signaturePad.toDataURL('image/png');
    document.getElementById('firma_base64').value = base64;

    // Mostrar vista previa
    const imgPreview = document.getElementById('firmaPreviewImg');
    imgPreview.src = base64;
    document.getElementById('firmaPreview').classList.remove('hidden');
  }

  signaturePad.clear();
  modal.classList.add('hidden');
}

function limpiarFirma() {
  signaturePad.clear();
}

function ajustarCanvasAlDispositivo() {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  canvas.width = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  canvas.getContext("2d").scale(ratio, ratio);
}
</script>

  <script>
    // Mostrar toast si existe
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

    // Funciones para cambiar modo
    function cambiarModo(modo) {
      if (modo === 'nuevo') {
        window.location.href = '../secretaria/';
      } else {
        document.getElementById('selector-registro').style.display = 'block';
      }
    }

    function cargarRegistro() {
      const select = document.getElementById('lista-registros');
      const id = select.value;
      if (id) {
        window.location.href = `?editar=1&id=${id}`;
      }
    }

    // Mostrar botón flotante al hacer scroll
    window.addEventListener('scroll', function() {
      const floatingBtn = document.getElementById('floating-save');
      if (window.scrollY > 300) {
        floatingBtn.classList.add('show');
      } else {
        floatingBtn.classList.remove('show');
      }
    });

    // Inicializar campos según el grado al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
      <?php if ($modo_edicion): ?>
        actualizarCampos();
      <?php endif; ?>
    });
  </script>

  <!-- Scripts existentes -->
  <script src="../js/foto_galeria.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="../js/info.js"></script>
  <script src="../js/menu_mobile.js"></script>
</body>
</html>

