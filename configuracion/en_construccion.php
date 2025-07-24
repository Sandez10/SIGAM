<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Estamos trabajando</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .spin-slow {
      animation: spin 8s linear infinite;
    }
    .spin-reverse {
      animation: spin-reverse 10s linear infinite;
    }
    @keyframes spin-reverse {
      from { transform: rotate(360deg); }
      to { transform: rotate(0deg); }
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">

  <div class="bg-white shadow-xl rounded-3xl p-10 max-w-md text-center animate-fade-in">
    <!-- Icono animado -->
    <div class="relative w-24 h-24 mx-auto mb-6">
      <svg class="absolute w-24 h-24 text-yellow-500 opacity-80 spin-slow" fill="currentColor" viewBox="0 0 20 20">
        <path d="M13.94 2.34a8 8 0 012.83 2.83l2.12-.71.71 2.12-2.12.71a8 8 0 010 3.3l2.12.71-.71 2.12-2.12-.71a8 8 0 01-2.83 2.83l.71 2.12-2.12.71-.71-2.12a8 8 0 01-3.3 0l-.71 2.12-2.12-.71.71-2.12a8 8 0 01-2.83-2.83l-2.12.71-.71-2.12 2.12-.71a8 8 0 010-3.3l-2.12-.71.71-2.12 2.12.71a8 8 0 012.83-2.83l-.71-2.12 2.12-.71.71 2.12a8 8 0 013.3 0l.71-2.12 2.12.71-.71 2.12z"/>
      </svg>
      <svg class="absolute w-16 h-16 top-4 left-4 text-yellow-300 spin-reverse" fill="currentColor" viewBox="0 0 20 20">
        <path d="M13.94 2.34a8 8 0 012.83 2.83l2.12-.71.71 2.12-2.12.71a8 8 0 010 3.3l2.12.71-.71 2.12-2.12-.71a8 8 0 01-2.83 2.83l.71 2.12-2.12.71-.71-2.12a8 8 0 01-3.3 0l-.71 2.12-2.12-.71.71-2.12a8 8 0 01-2.83-2.83l-2.12.71-.71-2.12 2.12-.71a8 8 0 010-3.3l-2.12-.71.71-2.12 2.12.71a8 8 0 012.83-2.83l-.71-2.12 2.12-.71.71 2.12a8 8 0 013.3 0l.71-2.12 2.12.71-.71 2.12z"/>
      </svg>
    </div>

    <!-- Mensaje UX -->
    <h1 class="text-2xl font-bold text-gray-800 mb-2">¡Estamos trabajando en esto!</h1>
    <p class="text-gray-600 text-sm mb-4">
      Esta sección está en proceso de mejoras para brindarte una mejor experiencia. 
      Pronto estará disponible.
    </p>

    <button onclick="history.back()"
      class="mt-4 px-5 py-2 rounded-full bg-yellow-500 hover:bg-yellow-600 text-white text-sm transition shadow-lg">
      Volver atrás
    </button>
  </div>

  <style>
    .animate-fade-in {
      animation: fadeIn 0.6s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>

</body>
</html>
