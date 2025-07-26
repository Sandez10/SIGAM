<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - SIGAM</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/index.css"> 

</head>
<body class="d-flex justify-content-center align-items-center min-vh-100 p-3">
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="brand-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
          </svg>
        </div>
        <h1>SIGAM</h1>
        <p>Sistema Integral de Gestión Administrativo</p>
      </div>
      <div class="login-body">
<form id="loginForm" action="login" method="POST" autocomplete="off">
          <div class="mb-4">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Ingresa tu usuario" required autofocus />
          </div>
          <div class="mb-4 password-container">
            <label for="clave" class="form-label">Contraseña</label>
            <input type="password" name="clave" id="clave" class="form-control" placeholder="Ingresa tu contraseña" required />
            <i class="toggle-password bi bi-eye-fill" data-target="clave"></i>
          </div>
          <button type="submit" class="btn btn-login w-100 mb-3" id="loginButton">
            <span id="buttonText">Acceder al sistema</span>
          </button>
        </form>
        <div class="footer-links">
          © 2025 - SIGAM
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle password visibility
    document.querySelector('.toggle-password').addEventListener('click', function() {
      const target = this.getAttribute('data-target');
      const input = document.getElementById(target);
      const icon = this;
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-fill');
        icon.classList.add('bi-eye-slash-fill');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash-fill');
        icon.classList.add('bi-eye-fill');
      }
    });



document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const button = document.getElementById('loginButton');
    const buttonText = document.getElementById('buttonText');
    
    // Mostrar estado de carga
    button.disabled = true;
    buttonText.textContent = 'Verificando...';
    
    // Enviar datos via AJAX
    fetch(form.action, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito antes de redirigir
            Swal.fire({
                title: '¡Bienvenido!',
                text: 'Redirigiendo...',
                icon: 'success',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                window.location.href = data.redirect;
            });
        } else {
            // Mostrar error con SweetAlert
            Swal.fire({
                title: 'Error',
                text: data.message,
                icon: 'error',
                confirmButtonText: 'Entendido'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: 'Error al comunicarse con el servidor',
            icon: 'error',
            confirmButtonText: 'Entendido'
        });
    })
    .finally(() => {
        // Restaurar estado del botón
        button.disabled = false;
        buttonText.textContent = 'Acceder al sistema';
    });
});

// Manejar mensajes de error en URL (para compatibilidad con versiones anteriores)
const urlParams = new URLSearchParams(window.location.search);
const errorMessage = urlParams.get('error');
if (errorMessage) {
    Swal.fire({
        title: 'Error',
        text: errorMessage,
        icon: 'error',
        confirmButtonText: 'Entendido'
    });
    
    // Limpiar el parámetro de la URL
    window.history.replaceState({}, document.title, window.location.pathname);
}
    
  </script>
</body>
</html>