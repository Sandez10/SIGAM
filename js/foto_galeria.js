  // Detección de dispositivo
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  const fotoInput = document.getElementById('foto-input');
  const btnCamera = document.getElementById('btn-camera');
  const btnGallery = document.getElementById('btn-gallery');

  // Mostrar u ocultar botón de cámara según el dispositivo
  if (isMobile) {
    btnCamera.classList.remove('hidden');
    
    // Configurar cámara
    btnCamera.addEventListener('click', () => {
      fotoInput.setAttribute('capture', 'environment');
      fotoInput.click();
    });
  }

  // Configurar galería/selector de archivos
  btnGallery.addEventListener('click', () => {
    fotoInput.removeAttribute('capture'); // Eliminar atributo para selección normal
    fotoInput.click();
  });

  // Vista previa (igual que antes)
  fotoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(event) {
        document.getElementById('preview-image').src = event.target.result;
        document.getElementById('preview-container').classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    }
  });

  // Eliminar foto
  document.getElementById('btn-remove').addEventListener('click', () => {
    fotoInput.value = '';
    document.getElementById('preview-image').src = '#';
    document.getElementById('preview-container').classList.add('hidden');
  });