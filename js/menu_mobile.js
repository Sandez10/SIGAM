  document.addEventListener('DOMContentLoaded', function () {
  const hamburger = document.querySelector('.hamburger');
  const mobileMenu = document.getElementById('mobile-menu');
  const closeBtn = document.querySelector('.close-menu');
  const overlay = document.querySelector('.menu-overlay');
  const menuLinks = document.querySelectorAll('#mobile-menu nav a');

  function toggleMenu(forceClose = false) {
    const isOpen = mobileMenu.classList.contains('active');

    if (forceClose || isOpen) {
      mobileMenu.classList.remove('active');
      overlay.classList.remove('active');
      document.body.classList.remove('menu-open');
      hamburger.classList.remove('is-active');
    } else {
      mobileMenu.classList.add('active');
      overlay.classList.add('active');
      document.body.classList.add('menu-open');
      hamburger.classList.add('is-active');
    }
  }

  hamburger?.addEventListener('click', e => {
    e.stopPropagation();
    toggleMenu();
  });

  closeBtn?.addEventListener('click', toggleMenu);
  overlay?.addEventListener('click', toggleMenu);

  // Cerrar al hacer clic en links
  menuLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 1024) toggleMenu();
    });
  });

  // Cerrar con Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') toggleMenu(true);
  });

  // Cerrar al redimensionar a desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 1024) toggleMenu(true);
  });
});