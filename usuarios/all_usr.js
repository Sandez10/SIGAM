// all_usr.js (Actualizado y Corregido)

// Función para mostrar notificaciones tipo "toast"
function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type}`; // Limpia clases anteriores y establece la nueva
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Carga la lista de usuarios y las estadísticas desde la API
async function loadUsers() {
    const search = document.getElementById('searchUsers').value;
    const role = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;
    const loadingSpinner = document.getElementById('loadingSpinner');
    const userTableBody = document.querySelector('#userTable tbody');

    loadingSpinner.classList.remove('hidden'); // Muestra el spinner
    userTableBody.innerHTML = ''; // Limpia la tabla antes de cargar

    try {
        const response = await fetch(`usuarios_api.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&status=${encodeURIComponent(status)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();

        if (response.ok) {
            // Actualizar estadísticas
            document.getElementById('totalUsers').textContent = data.stats.totalUsers;
            document.getElementById('activeUsers').textContent = data.stats.activeUsers;
            document.getElementById('lastUpdate').textContent = data.stats.lastUpdate || '-';

            // Actualizar tabla
            if (data.users.length === 0) {
                document.getElementById('noUsers').classList.remove('hidden');
            } else {
                document.getElementById('noUsers').classList.add('hidden');
                data.users.forEach(user => {
                    const row = document.createElement('tr');
                    // Define el texto y la clase para el estado del usuario
                    let statusText, statusClass;
                    switch (user.estado) {
                        case '1': statusText = 'Activo'; statusClass = 'status-active'; break;
                        case '0': statusText = 'Inactivo'; statusClass = 'status-inactive'; break;
                        case '2': statusText = 'Suspendido'; statusClass = 'status-suspended'; break; // Asumiendo una clase para suspendido
                        default: statusText = 'Desconocido'; statusClass = '';
                    }

                    row.innerHTML = `
                        <td>${user.usr}</td>
                        <td>${user.logia || 'N/A'}</td>
                        <td>${user.rol}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-secondary edit-btn" data-id="${user.usrId}">Editar</button>
                            <button class="btn btn-danger delete-btn" data-id="${user.usrId}" data-name="${user.usr}" data-logia="${user.logia || 'N/A'}">Eliminar</button>
                        </td>
                    `;
                    userTableBody.appendChild(row);
                });
            }
        } else {
            showToast(data.error || 'Error al cargar los datos.', 'error');
        }
    } catch (error) {
        console.error('Error en loadUsers:', error);
        showToast('Hubo un error de conexión al cargar los usuarios.', 'error');
    } finally {
        loadingSpinner.classList.add('hidden'); // Oculta el spinner al finalizar
    }
}

// --- MANEJADORES DE EVENTOS ---

// Evento para el formulario de CREAR usuario
document.getElementById('createUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = {
        usr: document.getElementById('createUsuario').value,
        clave: document.getElementById('createClave').value,
        rol: document.getElementById('createRol').value,
        estado: document.getElementById('createEstado').value,
        logia: document.getElementById('logia').value
    };

    if (!formData.usr || !formData.clave || !formData.rol || !formData.estado || !formData.logia) {
        showToast('Todos los campos son obligatorios.', 'error');
        return;
    }

    try {
        const response = await fetch('usuarios_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (response.ok) {
            showToast('Usuario creado exitosamente.', 'success');
            document.getElementById('createModal').classList.remove('show'); // Cierra el modal
            document.getElementById('createUserForm').reset();
            document.getElementById('logia-search').value = '';
            loadUsers();
        } else {
            showToast(result.error || 'Error desconocido.', 'error');
        }
    } catch (error) {
        showToast('Hubo un error de conexión al crear el usuario.', 'error');
    }
});

// Evento para el formulario de EDITAR usuario
document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault(); // Previene que la página se recargue

    // Recolecta los datos del formulario de edición
    const formData = {
        usrId: document.getElementById('editUserId').value,
        usr: document.getElementById('editNombreCompleto').value,
        clave: document.getElementById('editPassword').value, // La API maneja si está vacío
        rol: document.getElementById('editRol').value,
        estado: document.getElementById('editEstado').value,
        logia: document.getElementById('editLogia').value,
    };

    // Validación simple
    if (!formData.usrId || !formData.usr || !formData.rol || formData.estado === "" || !formData.logia) {
        showToast('Los campos requeridos no pueden estar vacíos.', 'error');
        return;
    }

    try {
        const response = await fetch('usuarios_api.php', {
            method: 'PUT', // Usa el método PUT para actualizar
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok) {
            showToast('Usuario actualizado exitosamente.', 'success');
            document.getElementById('editModal').classList.remove('show'); // Cierra el modal
            loadUsers(); // Recarga la lista de usuarios para ver los cambios
        } else {
            showToast(result.error || 'No se pudo actualizar el usuario.', 'error');
        }
    } catch (error) {
        console.error('Error al actualizar usuario:', error);
        showToast('Hubo un error de conexión al actualizar el usuario.', 'error');
    }
});

// Evento para el botón de confirmar ELIMINACIÓN
document.getElementById('confirmDelete').addEventListener('click', async () => {
    const confirmInput = document.getElementById('confirmDeleteInput');
    if (confirmInput.value.toLowerCase() !== 'eliminar') {
        showToast('Debes escribir "eliminar" para confirmar.', 'error');
        return;
    }

    const usrId = document.getElementById('confirmDelete').dataset.usrId;
    try {
        const response = await fetch('usuarios_api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usrId })
        });
        const result = await response.json();
        if (response.ok) {
            showToast('Usuario eliminado exitosamente.', 'success');
            document.getElementById('deleteModal').classList.remove('show');
            confirmInput.value = ''; // Limpiar input
            loadUsers(); // Recargar la lista de usuarios
        } else {
            showToast(result.error || 'Error desconocido.', 'error');
        }
    } catch (error) {
        console.error('Error al eliminar usuario:', error);
        showToast('Hubo un error de conexión al eliminar el usuario.', 'error');
    }
});

// Delegación de eventos para botones EDITAR y ELIMINAR en la tabla
document.querySelector('#userTable tbody').addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
        const usrId = editBtn.dataset.id;
        try {
            const response = await fetch(`usuarios_api.php?usrId=${usrId}`);
            const data = await response.json();
            if (response.ok && data.user) {
                const user = data.user;
                document.getElementById('editUserId').value = user.usrId;
                document.getElementById('editNombreCompleto').value = user.usr;
                document.getElementById("editLogia").value = user.logia;
                document.getElementById('editRol').value = user.rol;
                document.getElementById("editEstado").value = user.estado;
                document.getElementById('editModal').classList.add('show');
            } else {
                showToast(data.error || 'Usuario no encontrado.', 'error');
            }
        } catch (error) {
            showToast('No se pudieron cargar los datos del usuario.', 'error');
        }
    }
    
    const deleteBtn = e.target.closest('.delete-btn');
    if (deleteBtn) {
        document.getElementById('deleteUserName').textContent = deleteBtn.dataset.name;
        document.getElementById('deleteUserLogia').textContent = deleteBtn.dataset.logia;
        document.getElementById('confirmDelete').dataset.usrId = deleteBtn.dataset.id;
        document.getElementById('deleteModal').classList.add('show');
    }
});

// --- Controles de la Interfaz (Filtros, Pestañas, Modales) ---

// Filtros y búsqueda
document.getElementById('searchUsers').addEventListener('input', loadUsers);
document.getElementById('filterRole').addEventListener('change', loadUsers);
document.getElementById('filterStatus').addEventListener('change', loadUsers);
document.getElementById('resetFilters').addEventListener('click', () => {
    document.getElementById('searchUsers').value = '';
    document.getElementById('filterRole').value = '';
    document.getElementById('filterStatus').value = '';
    loadUsers();
});

// Sistema de pestañas
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
    });
});

// Botón "Nuevo Usuario" que activa la pestaña de creación
document.getElementById('openCreateModal').addEventListener('click', () => {
    document.querySelector('.tab[data-tab="create"]').click();
});

// Botones para cerrar modales
document.getElementById('closeEditModal').addEventListener('click', () => {
    document.getElementById('editModal').classList.remove('show');
});

// CORRECCIÓN 2: Se usa el ID correcto 'cancelDelete' en lugar de 'closeDeleteModal'
document.getElementById('cancelDelete').addEventListener('click', () => {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('confirmDeleteInput').value = '';
});

// Carga inicial de usuarios cuando el DOM está listo
document.addEventListener('DOMContentLoaded', loadUsers);
