<?php
// ============================================================================
// movieminto_registro_tes.php  (Frontend adaptado al CSS dado)
//  - Listado de movimientos por logia
//  - Búsqueda + paginación
//  - Editar (modal) y Eliminar con SweetAlert2
//  - Combo buscable (input + lista) para seleccionar hermano al editar
// ============================================================================

require_once '../sesiones_conexiones/sesion_config.php';
require_once '../sesiones_conexiones/logia.php';
require_once '../database/conexion.php';

// Seguridad de sesión
if (!isset($_SESSION['user_id'])) {
  header("Location: ../");
  exit;
}

// Seguridad por rol (opcional, igual que en tus otras páginas)
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'secretario') {
  echo "<script>alert('No tienes permiso para acceder a esta página.'); history.back();</script>";
  exit();
}

// Conexión
$db = Database::getInstance();
$conn = $db->getConnection();
mysqli_set_charset($conn, 'utf8mb4');

// Datos de logia del usuario
$datosLogia = obtenerDatosLogia();
if ($datosLogia === null) {
  die("No se pudieron obtener los datos de la logia. Revisa los logs de error.");
}
$clave_logia = (int)($datosLogia['clave_logia'] ?? 0);
$oriente     = $datosLogia['oriente'] ?? '';
$logia       = $datosLogia['logia'] ?? '';

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Utilidades
function procesar($v, $tipo='string') {
  $v = is_array($v) ? $v : trim((string)$v);
  switch ($tipo) {
    case 'int': return (filter_var($v, FILTER_VALIDATE_INT) !== false) ? (int)$v : null;
    case 'float': return (filter_var($v, FILTER_VALIDATE_FLOAT) !== false) ? (float)$v : 0.0;
    default: return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }
}

// Helpers de datos
function obtenerHermanoPorId(mysqli $conn, int $id): ?array {
  $sql = "SELECT r.id, r.nombre_completo,
          CASE r.estado_hermano
              WHEN 1 THEN 'Activo'
              WHEN 0 THEN 'Baja'
              WHEN 2 THEN 'Libre de orden'
              WHEN 3 THEN 'Desplomado'
              ELSE 'No especificado'
          END AS estado_textual,
          COALESCE(im.grado_masonico, 'No especificado') AS grado_textual
          FROM registros r
          LEFT JOIN informacion_masonica im ON im.id_registro = r.id
          WHERE r.id = ? LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) return null;
  $st->bind_param("i", $id);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();
  return $row ?: null;
}

function obtenerHermanosPorClave(mysqli $conn, int $claveLogia): array {
  $sql = "SELECT r.id, r.nombre_completo,
          CASE r.estado_hermano
              WHEN 1 THEN 'Activo'
              WHEN 0 THEN 'Baja'
              WHEN 2 THEN 'Libre de orden'
              WHEN 3 THEN 'Desplomado'
              ELSE 'No especificado'
          END AS estado_textual,
          COALESCE(im.grado_masonico, 'No especificado') AS grado_textual
          FROM registros r
          LEFT JOIN informacion_masonica im ON im.id_registro = r.id
          WHERE r.clave_logia = ?
          ORDER BY r.nombre_completo ASC";
  $st = $conn->prepare($sql);
  if (!$st) return [];
  $st->bind_param("i", $claveLogia);
  $st->execute();
  $res = $st->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $st->close();
  return $rows ?: [];
}

function actualizarEstadoHermano(mysqli $conn, int $idHermano, int $estado): bool {
  $st = $conn->prepare("UPDATE registros SET estado_hermano = ? WHERE id = ?");
  if (!$st) return false;
  $st->bind_param("ii", $estado, $idHermano);
  $ok = $st->execute();
  $st->close();
  return $ok;
}

// ======================
//  API AJAX (POST)
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');

  // CSRF para AJAX
  if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
  ) {
    echo json_encode(['success'=>false, 'message'=>'Token CSRF inválido']);
    exit;
  }

  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'get') {
      $id = procesar($_POST['id'] ?? '', 'int');
      if (!$id) throw new Exception("ID inválido");

      $st = $conn->prepare("SELECT id, id_hermano, nombre_hermano, grado, capitas, seguro, iniciacion, afiliacion, exaltacion, regularizacion, estado_hermano
                            FROM tesoreria
                            WHERE id = ? AND clave_logia = ? LIMIT 1");
      if (!$st) throw new Exception("Error preparar GET: ".$conn->error);
      $st->bind_param("ii", $id, $clave_logia);
      $st->execute();
      $res = $st->get_result();
      $row = $res->fetch_assoc();
      $st->close();

      if (!$row) throw new Exception("No encontrado");
      echo json_encode(['success'=>true, 'data'=>$row]);
      exit;
    }

    if ($action === 'update') {
      $id = procesar($_POST['id'] ?? '', 'int');
      if (!$id) throw new Exception("ID inválido");

      $id_hermano = procesar($_POST['id_hermano'] ?? '', 'int');
      $estado     = procesar($_POST['estado'] ?? '', 'int');
      $grado      = procesar($_POST['grado'] ?? '');

      $capitas        = procesar($_POST['capitas'] ?? '0', 'float');
      $seguro         = procesar($_POST['seguro'] ?? '0', 'float');
      $iniciacion     = procesar($_POST['iniciacion'] ?? '0', 'float');
      $afiliacion     = procesar($_POST['afiliacion'] ?? '0', 'float');
      $exaltacion     = procesar($_POST['exaltacion'] ?? '0', 'float');
      $regularizacion = procesar($_POST['regularizacion'] ?? '0', 'float');

      if (!$id_hermano) throw new Exception("Seleccione un hermano");
      if ($estado === null || !in_array($estado, [0,1,2,3], true)) {
        throw new Exception("Estado inválido");
      }
      if ($grado === '') throw new Exception("Grado requerido");

      // validar que el movimiento pertenece a la logia
      $chk = $conn->prepare("SELECT id FROM tesoreria WHERE id = ? AND clave_logia = ? LIMIT 1");
      $chk->bind_param("ii", $id, $clave_logia);
      $chk->execute();
      $existe = $chk->get_result()->fetch_assoc();
      $chk->close();
      if (!$existe) throw new Exception("Movimiento no pertenece a tu logia");

      // nombre del hermano
      $hermano = obtenerHermanoPorId($conn, $id_hermano);
      if (!$hermano) throw new Exception("Hermano no encontrado");

      $nombre_hermano = $hermano['nombre_completo'];

      $st = $conn->prepare("UPDATE tesoreria
                            SET id_hermano=?, nombre_hermano=?, grado=?,
                                capitas=?, seguro=?, iniciacion=?, afiliacion=?, exaltacion=?, regularizacion=?,
                                estado_hermano=?
                            WHERE id=? AND clave_logia=?");
      if (!$st) throw new Exception("Error preparar UPDATE: ".$conn->error);
      $st->bind_param(
        "issddddddiii",
        $id_hermano, $nombre_hermano, $grado,
        $capitas, $seguro, $iniciacion, $afiliacion, $exaltacion, $regularizacion,
        $estado, $id, $clave_logia
      );
      if (!$st->execute()) {
        throw new Exception("Error ejecutando UPDATE: ".$st->error);
      }
      $st->close();

      // actualizar estado del hermano
      actualizarEstadoHermano($conn, $id_hermano, $estado);

      echo json_encode(['success'=>true, 'message'=>'Movimiento actualizado correctamente']);
      exit;
    }

    if ($action === 'delete') {
      $id = procesar($_POST['id'] ?? '', 'int');
      if (!$id) throw new Exception("ID inválido");

      $st = $conn->prepare("DELETE FROM tesoreria WHERE id = ? AND clave_logia = ? LIMIT 1");
      if (!$st) throw new Exception("Error preparar DELETE: ".$conn->error);
      $st->bind_param("ii", $id, $clave_logia);
      if (!$st->execute()) {
        throw new Exception("Error ejecutando DELETE: ".$st->error);
      }
      $af = $st->affected_rows;
      $st->close();

      if ($af === 0) throw new Exception("No se eliminó ningún registro (¿pertenece a tu logia?)");

      echo json_encode(['success'=>true, 'message'=>'Movimiento eliminado']);
      exit;
    }

    echo json_encode(['success'=>false, 'message'=>'Acción inválida']);
    exit;

  } catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    exit;
  }
}

// ======================
//  Vista (GET)
// ======================
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

// total
if ($q !== '') {
  $like = "%$q%";
  $ct = $conn->prepare("SELECT COUNT(*) c FROM tesoreria WHERE clave_logia=? AND nombre_hermano LIKE ?");
  $ct->bind_param("is", $clave_logia, $like);
} else {
  $ct = $conn->prepare("SELECT COUNT(*) c FROM tesoreria WHERE clave_logia=?");
  $ct->bind_param("i", $clave_logia);
}
$ct->execute();
$total = (int)$ct->get_result()->fetch_assoc()['c'];
$ct->close();

$totalPages = max(1, (int)ceil($total / $limit));

// datos
if ($q !== '') {
  $st = $conn->prepare("SELECT id, id_hermano, nombre_hermano, grado,
                        capitas, seguro, iniciacion, afiliacion, exaltacion, regularizacion,
                        estado_hermano, fecha_registro
                        FROM tesoreria
                        WHERE clave_logia=? AND nombre_hermano LIKE ?
                        ORDER BY fecha_registro DESC
                        LIMIT ?, ?");
  $st->bind_param("isii", $clave_logia, $like, $offset, $limit);
} else {
  $st = $conn->prepare("SELECT id, id_hermano, nombre_hermano, grado,
                        capitas, seguro, iniciacion, afiliacion, exaltacion, regularizacion,
                        estado_hermano, fecha_registro
                        FROM tesoreria
                        WHERE clave_logia=?
                        ORDER BY fecha_registro DESC
                        LIMIT ?, ?");
  $st->bind_param("iii", $clave_logia, $offset, $limit);
}
$st->execute();
$res = $st->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$st->close();

// hermanos (para el combo de edición)
$hermanos = obtenerHermanosPorClave($conn, $clave_logia);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Movimientos de Tesorería</title>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="../css/movimientos-tesoreria.css?v=1">

  <!-- Estilos del tema proporcionado -->

</head>
<body>
  <div class="container">
    <?php include '../configuracion/panel_menu.php'; ?>

    <main class="content">
      <header class="header">
        <div>
          <h1 style="margin:0; font-size:1.4rem; color:var(--primary-color);">Movimientos de Tesorería</h1>
          <div style="color:var(--text-light); font-size:.9rem;">
            Log. <?php echo htmlspecialchars($logia); ?> — Or. <?php echo htmlspecialchars($oriente); ?>
          </div>
        </div>
        <a class="btn-primary" href="../tesoreria/">← Capturar movimientos</a>
      </header>

      <section class="glass-card" style="padding:1.25rem; margin-top:1rem;">
        <form method="GET" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; justify-content:space-between;">
          <div style="display:flex; gap:.5rem; flex:1;">
            <input type="text" name="q" placeholder="Buscar por nombre del H∴" value="<?php echo htmlspecialchars($q); ?>">
            <button class="btn-primary" type="submit">Buscar</button>
          </div>
          <div style="font-size:.9rem; color:var(--text-light);">
            Resultados: <?php echo $total; ?> · Página <?php echo $page; ?> / <?php echo $totalPages; ?>
          </div>
        </form>

        <div style="overflow-x:auto; margin-top:1rem;">
          <table class="tbl">
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Hermano</th>
                <th>Grado</th>
                <th>Capitas</th>
                <th>Seguro</th>
                <th>Iniciación</th>
                <th>Afiliación</th>
                <th>Exaltación</th>
                <th>Regularización</th>
                <th>Total</th>
                <th>Estado</th>
                <th style="text-align:center;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="13" style="text-align:center; color:var(--text-light); padding:1.2rem;">Sin movimientos</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r):
                  $totalR = (float)$r['capitas'] + (float)$r['seguro'] + (float)$r['iniciacion'] + (float)$r['afiliacion'] + (float)$r['exaltacion'] + (float)$r['regularizacion'];
                ?>
                <tr id="row-<?php echo (int)$r['id']; ?>">
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo htmlspecialchars($r['fecha_registro']); ?></td>
                  <td class="nombre_hermano"><?php echo htmlspecialchars($r['nombre_hermano']); ?></td>
                  <td class="grado"><?php echo htmlspecialchars($r['grado']); ?></td>
                  <td class="capitas" data-val="<?php echo (float)$r['capitas']; ?>">$<?php echo number_format((float)$r['capitas'], 2); ?></td>
                  <td class="seguro" data-val="<?php echo (float)$r['seguro']; ?>">$<?php echo number_format((float)$r['seguro'], 2); ?></td>
                  <td class="iniciacion" data-val="<?php echo (float)$r['iniciacion']; ?>">$<?php echo number_format((float)$r['iniciacion'], 2); ?></td>
                  <td class="afiliacion" data-val="<?php echo (float)$r['afiliacion']; ?>">$<?php echo number_format((float)$r['afiliacion'], 2); ?></td>
                  <td class="exaltacion" data-val="<?php echo (float)$r['exaltacion']; ?>">$<?php echo number_format((float)$r['exaltacion'], 2); ?></td>
                  <td class="regularizacion" data-val="<?php echo (float)$r['regularizacion']; ?>">$<?php echo number_format((float)$r['regularizacion'], 2); ?></td>
                  <td class="total" style="font-weight:600;">$<?php echo number_format($totalR, 2); ?></td>
                  <td class="estado" data-val="<?php echo (int)$r['estado_hermano']; ?>">
                    <?php
                      switch ((int)$r['estado_hermano']) {
                        case 1: echo "Activo"; break;
                        case 0: echo "Baja"; break;
                        case 2: echo "Libre de orden"; break;
                        case 3: echo "Desplomado"; break;
                        default: echo "No espec.";
                      }
                    ?>
                  </td>
                  <td style="text-align:center;">
                    <button class="btn-secondary btnEdit" data-id="<?php echo (int)$r['id']; ?>">Editar</button>
                    <button class="btn-primary btnDelete" data-id="<?php echo (int)$r['id']; ?>">Eliminar</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex; align-items:center; gap:.5rem; margin-top:1rem;">
          <?php
            $base = '?';
            if ($q !== '') $base .= 'q='.urlencode($q).'&';
          ?>
          <a class="btn-secondary <?php echo $page<=1?'disabled':''; ?>" href="<?php echo $base.'page='.max(1,$page-1); ?>" style="<?php echo $page<=1?'pointer-events:none; opacity:.5;':''; ?>">« Anterior</a>
          <span style="padding:0 .5rem;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
          <a class="btn-secondary <?php echo $page>=$totalPages?'disabled':''; ?>" href="<?php echo $base.'page='.min($totalPages,$page+1); ?>" style="<?php echo $page>=$totalPages?'pointer-events:none; opacity:.5;':''; ?>">Siguiente »</a>
        </div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <!-- Modal de edición (usa .modal y .modal-content del CSS) -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="margin:0;">Editar movimiento</h3>
        <button id="editClose" class="btn-secondary">✕</button>
      </div>

      <form id="editForm" style="display:grid; grid-template-columns: 1fr 1fr; gap: .8rem;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">

        <!-- Hermano (combo) -->
        <div class="combo" style="grid-column: 1 / -1;">
          <label class="block mb-1">Hermano</label>
          <input type="text" id="edit_hermano_input" placeholder="Escribe para buscar...">
          <input type="hidden" name="id_hermano" id="edit_hermano_id">
          <div class="combo-menu" id="edit_hermano_menu"></div>
          <div id="edit_hermano_nombre" style="font-size:.85rem; color:var(--text-light); margin-top:.25rem;"></div>
        </div>

        <div>
          <label class="block mb-1">Grado</label>
          <input type="text" name="grado" id="edit_grado" required>
        </div>

        <div>
          <label class="block mb-1">Estado</label>
          <select name="estado" id="edit_estado" required>
            <option value="">Selecciona...</option>
            <option value="1">Activo</option>
            <option value="0">Baja</option>
            <option value="2">Libre de orden</option>
            <option value="3">Desplomado</option>
          </select>
        </div>

        <?php
          // helper para input monto
          function monto_input($name, $label) {
            echo '<div><label class="block mb-1">'.$label.'</label>'.
                 '<input type="number" step="0.01" min="0" class="monto" name="'.$name.'" id="edit_'.$name.'"></div>';
          }
          monto_input('capitas', 'Capitas');
          monto_input('seguro', 'Seguro');
          monto_input('iniciacion', 'Iniciación');
          monto_input('afiliacion', 'Afiliación');
          monto_input('exaltacion', 'Exaltación');
          monto_input('regularizacion', 'Regularización');
        ?>

        <div style="grid-column: 1 / -1; text-align:right; margin-top:.5rem;">
          <button type="button" id="editCancel" class="btn-secondary">Cancelar</button>
          <button type="submit" class="btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  // Datos para combo de hermanos en edición
  const HERMANOS = <?php echo json_encode($hermanos, JSON_UNESCAPED_UNICODE); ?>;
  const CSRF = "<?php echo htmlspecialchars($csrf); ?>";

  function fmt(n) { n = parseFloat(n||0); return '$' + n.toFixed(2); }
  function toast(icon, title, text='', timer=2200) {
    Swal.fire({ icon, title, text, timer, toast:true, position:'top-end', showConfirmButton:false });
  }
  function confirmDlg(title, text) {
    return Swal.fire({ icon:'warning', title, text, showCancelButton:true, confirmButtonText:'Sí', cancelButtonText:'No' });
  }

  // --- Combo buscable (modal) ---
  const modal = document.getElementById('editModal');
  const editForm = document.getElementById('editForm');
  const editClose = document.getElementById('editClose');
  const editCancel = document.getElementById('editCancel');

  const editId = document.getElementById('edit_id');
  const inHermano = document.getElementById('edit_hermano_input');
  const hidHermano = document.getElementById('edit_hermano_id');
  const menuHermano = document.getElementById('edit_hermano_menu');
  const lblHermano = document.getElementById('edit_hermano_nombre');

  const inGrado  = document.getElementById('edit_grado');
  const inEstado = document.getElementById('edit_estado');

  const inCapitas = document.getElementById('edit_capitas');
  const inSeguro  = document.getElementById('edit_seguro');
  const inIni     = document.getElementById('edit_iniciacion');
  const inAfi     = document.getElementById('edit_afiliacion');
  const inExa     = document.getElementById('edit_exaltacion');
  const inReg     = document.getElementById('edit_regularizacion');

  function openModal() { modal.classList.add('show'); }
  function closeModal() { modal.classList.remove('show'); }

  editClose.addEventListener('click', closeModal);
  editCancel.addEventListener('click', closeModal);

  function buildMenu(elMenu, items, onPick) {
    elMenu.innerHTML = '';
    if (!items || items.length === 0) {
      const d = document.createElement('div');
      d.className = 'combo-empty'; d.textContent = 'Sin resultados';
      elMenu.appendChild(d); return;
    }
    for (const it of items) {
      const d = document.createElement('div');
      d.className = 'combo-item';
      d.textContent = it.nombre_completo;
      d.dataset.id = it.id;
      d.addEventListener('click', () => onPick(it));
      elMenu.appendChild(d);
    }
  }
  function filterHermanos(q) {
    const qq = (q||'').toLowerCase().trim();
    if (!qq) return HERMANOS.slice(0,120);
    return HERMANOS.filter(h => (h.nombre_completo||'').toLowerCase().includes(qq)).slice(0,120);
  }
  function pickHermano(h) {
    inHermano.value = h.nombre_completo;
    hidHermano.value = h.id;
    lblHermano.textContent = `Grado: ${h.grado_textual || ''} · Estado: ${h.estado_textual || ''}`;
    menuHermano.classList.remove('open');
    if (!inGrado.value) inGrado.value = h.grado_textual || '';
  }

  inHermano.addEventListener('focus', () => {
    buildMenu(menuHermano, filterHermanos(inHermano.value), pickHermano);
    menuHermano.classList.add('open');
  });
  inHermano.addEventListener('input', () => {
    // si escribe, limpiamos hidden
    hidHermano.value = '';
    lblHermano.textContent = '';
    buildMenu(menuHermano, filterHermanos(inHermano.value), pickHermano);
    menuHermano.classList.add('open');
  });
  document.addEventListener('click', (e) => {
    const combo = document.querySelector('#editModal .combo');
    if (combo && !combo.contains(e.target)) {
      menuHermano.classList.remove('open');
    }
  });

  // Cargar movimiento para edición
  async function fetchMovimiento(id) {
    const fd = new FormData();
    fd.append('ajax','1'); fd.append('action','get'); fd.append('id', id); fd.append('csrf_token', CSRF);
    const r = await fetch(location.href, { method:'POST', body: fd });
    return r.json();
  }

  // Guardar edición
  editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!hidHermano.value) { toast('error','Selecciona un hermano'); return; }
    if (!inGrado.value) { toast('error','El grado es requerido'); return; }
    if (!inEstado.value) { toast('error','Selecciona el estado'); return; }

    const fd = new FormData(editForm);
    Swal.fire({ title:'Guardando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
    const r = await fetch(location.href, { method:'POST', body: fd });
    const j = await r.json();
    Swal.close();

    if (!j.success) {
      Swal.fire({ icon:'error', title:'Error', text:j.message || 'No se pudo actualizar' });
      return;
    }

    // Actualizar fila en la tabla
    const row = document.getElementById('row-'+editId.value);
    if (row) {
      row.querySelector('.nombre_hermano').textContent = inHermano.value;
      row.querySelector('.grado').textContent = inGrado.value;

      const setCell = (cls, val) => {
        const c = row.querySelector('.'+cls);
        const num = parseFloat(val||0);
        c.dataset.val = num;
        c.textContent = fmt(num);
      };
      setCell('capitas', inCapitas.value);
      setCell('seguro', inSeguro.value);
      setCell('iniciacion', inIni.value);
      setCell('afiliacion', inAfi.value);
      setCell('exaltacion', inExa.value);
      setCell('regularizacion', inReg.value);

      // total
      const total = ['capitas','seguro','iniciacion','afiliacion','exaltacion','regularizacion']
        .map(cls => parseFloat(row.querySelector('.'+cls).dataset.val||0))
        .reduce((a,b)=>a+b,0);
      row.querySelector('.total').textContent = fmt(total);

      // estado textual
      const estMap = {1:'Activo',0:'Baja',2:'Libre de orden',3:'Desplomado'};
      const tdEst = row.querySelector('.estado');
      tdEst.dataset.val = inEstado.value;
      tdEst.textContent = estMap[inEstado.value] || 'No espec.';
    }

    closeModal();
    toast('success','Movimiento actualizado');
  });

  // Click en editar
  document.querySelectorAll('.btnEdit').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const j = await fetchMovimiento(id);
      if (!j.success) {
        Swal.fire({ icon:'error', title:'Error', text:j.message || 'No encontrado' });
        return;
      }
      const d = j.data;
      // set form
      editId.value = d.id;
      hidHermano.value = d.id_hermano;
      const hSel = HERMANOS.find(h => String(h.id) === String(d.id_hermano));
      inHermano.value = hSel ? hSel.nombre_completo : d.nombre_hermano;
      lblHermano.textContent = hSel ? `Grado: ${hSel.grado_textual || ''} · Estado: ${hSel.estado_textual || ''}` : '';

      inGrado.value  = d.grado || '';
      inEstado.value = d.estado_hermano;

      inCapitas.value = d.capitas ?? 0;
      inSeguro.value  = d.seguro ?? 0;
      inIni.value     = d.iniciacion ?? 0;
      inAfi.value     = d.afiliacion ?? 0;
      inExa.value     = d.exaltacion ?? 0;
      inReg.value     = d.regularizacion ?? 0;

      openModal();
    });
  });

  // Eliminar
  document.querySelectorAll('.btnDelete').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const c = await confirmDlg('¿Eliminar movimiento?', 'Esta acción no se puede deshacer.');
      if (!c.isConfirmed) return;

      const fd = new FormData();
      fd.append('ajax','1'); fd.append('action','delete'); fd.append('id', id); fd.append('csrf_token', CSRF);
      Swal.fire({ title:'Eliminando...', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
      const r = await fetch(location.href, { method:'POST', body: fd });
      const j = await r.json();
      Swal.close();

      if (!j.success) {
        Swal.fire({ icon:'error', title:'Error', text:j.message || 'No se pudo eliminar' });
        return;
      }

      const row = document.getElementById('row-'+id);
      if (row) row.remove();
      toast('success','Movimiento eliminado');
    });
  });
  </script>
</body>
</html>
