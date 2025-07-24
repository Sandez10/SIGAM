// Function to show toast notifications
//all_usr.js
function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Load users and stats
async function loadUsers() {
    const search = document.getElementById('searchUsers').value;
    const role = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;
    
    try {
        const response = await fetch(`usuarios_api.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&status=${encodeURIComponent(status)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        
        if (response.ok) {
            // Update stats
            document.getElementById('totalUsers').textContent = data.stats.totalUsers;
            document.getElementById('activeUsers').textContent = data.stats.activeUsers;
            document.getElementById('lastUpdate').textContent = data.stats.lastUpdate || '-';
            
            // Update table
            const tbody = document.querySelector('#userTable tbody');
            tbody.innerHTML = '';
            if (data.users.length === 0) {
                document.getElementById('noUsers').classList.remove('hidden');
            } else {
                document.getElementById('noUsers').classList.add('hidden');
                data.users.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.usr}</td>
                        <td>${user.logia}</td>
                        <td>${user.rol}</td>
                        <td>${user.estado === '1' ? 'Activo' : (user.estado === '0' ? 'Inactivo' : 'Suspendido')}</td>
                        <td>
                            <button class="btn btn-primary edit-btn" data-id="${user.usrId}">Editar</button>
                            <button class="btn btn-danger delete-btn" data-id="${user.usrId}" data-name="${user.usr}">Eliminar</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        } else {
            showToast(data.error, 'error');
        }
    } catch (error) {
        showToast('Error al cargar usuarios', 'error');
    }
}

// Create user
document.getElementById('createUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = {
        usr: document.getElementById('createUsuario').value,
        clave: document.getElementById('createClave').value,
        rol: document.getElementById('createRol').value,
        estado: document.getElementById('createEstado').value,
        logia: document.getElementById('createLogia').value
    };

    try {
        const response = await fetch('usuarios_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (response.ok) {
            showToast('Usuario creado exitosamente', 'success');
            document.getElementById('createUserForm').reset();
            loadUsers(); // Refresh user list
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error al crear usuario', 'error');
    }
});

// Edit user
document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = {
        usrId: document.getElementById('editUserId').value,
        usr: document.getElementById('editNombreCompleto').value,
        clave: document.getElementById('editPassword').value,
        rol: document.getElementById('editRol').value,
        estado: document.getElementById('editEstado').value,
        logia: document.getElementById('editLogia').value
    };

    try {
        const response = await fetch('usuarios_api.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (response.ok) {
            showToast('Usuario actualizado exitosamente', 'success');
            document.getElementById('editModal').classList.remove('show');
            loadUsers(); // Refresh user list
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error al actualizar usuario', 'error');
    }
});

// Delete user
document.getElementById('confirmDelete').addEventListener('click', async () => {
    const confirmInput = document.getElementById('confirmDeleteInput').value;
    if (confirmInput !== 'eliminar') {
        showToast('Debes escribir "eliminar" para confirmar', 'error');
        return;
    }

    const usrId = document.getElementById('confirmDelete').dataset.usrId; // Store usrId in button dataset
    try {
        const response = await fetch('usuarios_api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usrId })
        });
        const result = await response.json();
        if (response.ok) {
            showToast('Usuario eliminado exitosamente', 'success');
            document.getElementById('deleteModal').classList.remove('show');
            loadUsers(); // Refresh user list
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error al eliminar usuario', 'error');
    }
});

// Event delegation for edit and delete buttons
document.querySelector('#userTable tbody').addEventListener('click', async (e) => {
    if (e.target.classList.contains('edit-btn')) {
        const usrId = e.target.dataset.id;
        try {
            const response = await fetch(`usuarios_api.php?usrId=${usrId}`, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.users.length > 0) {
                const user = data.users[0];
                document.getElementById('editUserId').value = user.usrId;
                document.getElementById('editNombreCompleto').value = user.usr;
                document.getElementById("editLogia").value = user.logia;
                document.getElementById('editRol').value = user.rol;
                document.getElementById("editEstado").value = user.estado;
                // Si tienes campo de teléfono
                if (document.getElementById("editTelefono")) {
                    document.getElementById("editTelefono").value = user.telefono || '';
                }
                document.getElementById('editModal').classList.add('show');
            } else {
                showToast(data.error || 'Usuario no encontrado', 'error');
            }
        } catch (error) {
            showToast('Error al cargar datos del usuario', 'error');
        }
    } else if (e.target.classList.contains('delete-btn')) {
        document.getElementById('deleteUserName').textContent = e.target.dataset.name;
        document.getElementById('deleteUserLogia').textContent = ''; // Puedes cambiarlo si tienes logia
        document.getElementById('confirmDelete').dataset.usrId = e.target.dataset.id;
        document.getElementById('deleteModal').classList.add('show');
    }
});



// Load users on page load
document.addEventListener('DOMContentLoaded', loadUsers);

// Filter and search events
document.getElementById('searchUsers').addEventListener('input', loadUsers);
document.getElementById('filterRole').addEventListener('change', loadUsers);
document.getElementById('filterStatus').addEventListener('change', loadUsers);
document.getElementById('resetFilters').addEventListener('click', () => {
    document.getElementById('searchUsers').value = '';
    document.getElementById('filterRole').value = '';
    document.getElementById('filterStatus').value = '';
    loadUsers();
});
// Mostrar pestaña de "Crear Usuario" al hacer clic en el botón "Nuevo Usuario"
document.getElementById('openCreateModal').addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    document.querySelector('[data-tab="create"]').classList.add('active');
    document.getElementById('create').classList.add('active');
});
// Cerrar modal de edición
document.getElementById('closeEditModal').addEventListener('click', () => {
    document.getElementById('editModal').classList.remove('show');
});

// Cerrar modal de eliminación
document.getElementById('closeDeleteModal').addEventListener('click', () => {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('confirmDeleteInput').value = '';
});
