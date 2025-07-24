document.addEventListener('DOMContentLoaded', function() {
  // Manejar el envío del formulario
  document.getElementById('tesoreriaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validar campos requeridos
    const requiredFields = [
      'nombre_completo', 'domicilio', 'nacionalidad', 'estado_civil',
      'ocupacion', 'religion', 'numero_contacto', 'numero_emergencia',
      'correo_electronico', 'grado_masonico', 'tipo_ingreso'
    ];
    
    let isValid = true;
    requiredFields.forEach(field => {
      const element = document.querySelector(`[name="${field}"]`);
      if (!element || !element.value.trim()) {
        element.classList.add('border-red-500');
        isValid = false;
      } else {
        element.classList.remove('border-red-500');
      }
    });
    
    if (!isValid) {
      alert('Por favor complete todos los campos requeridos');
      return;
    }
    
    // Crear FormData
    const formData = new FormData(this);
    
    // Agregar fechas dinámicas de Past Master
    document.querySelectorAll('input[name="past_master_fechas[]"]').forEach(input => {
      if (input.value) formData.append('past_master_fechas[]', input.value);
    });
    
    // Enviar datos al servidor
    fetch('../reportes/guardar_registro.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Registro guardado con éxito');
        // this.reset(); // Opcional: resetear formulario
      } else {
        throw new Error(data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error: ' + error.message);
    });
  });
});