document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formCambioContrasena');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthIndicator = document.querySelector('.password-strength');
    const matchIndicator = document.querySelector('.password-match');

    // Validación en tiempo real
    newPassword.addEventListener('input', function() {
        const strength = checkPasswordStrength(this.value);
        updateStrengthIndicator(strength);
        checkPasswordMatch();
    });

    confirmPassword.addEventListener('input', checkPasswordMatch);

    // Validación al enviar el formulario
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            showToast('Por favor corrige los errores', 'error');
        }
    });

    function checkPasswordStrength(password) {
        let strength = 0;
        
        // Longitud mínima
        if (password.length >= 8) strength += 1;
        
        // Contiene números
        if (/\d/.test(password)) strength += 1;
        
        // Contiene mayúsculas
        if (/[A-Z]/.test(password)) strength += 1;
        
        // Contiene caracteres especiales
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;
        
        return strength;
    }

    function updateStrengthIndicator(strength) {
        const colors = ['#ef233c', '#ff9f1c', '#ffbf69', '#2ecc71'];
        strengthIndicator.style.width = `${strength * 25}%`;
        strengthIndicator.style.backgroundColor = colors[strength - 1] || '#e0e0e0';
    }

    function checkPasswordMatch() {
        if (!newPassword.value || !confirmPassword.value) return;
        
        if (newPassword.value === confirmPassword.value) {
            matchIndicator.style.width = '100%';
            matchIndicator.style.backgroundColor = '#2ecc71';
        } else {
            matchIndicator.style.width = '100%';
            matchIndicator.style.backgroundColor = '#ef233c';
        }
    }

    function validateForm() {
        let isValid = true;
        
        // Validar nueva contraseña
        if (newPassword.value.length < 8) {
            showError(newPassword, 'Mínimo 8 caracteres');
            isValid = false;
        }
        
        // Validar coincidencia
        if (newPassword.value !== confirmPassword.value) {
            showError(confirmPassword, 'Las contraseñas no coinciden');
            isValid = false;
        }
        
        return isValid;
    }

    function showError(element, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = message;
        errorElement.style.color = '#ef233c';
        errorElement.style.fontSize = '0.875rem';
        errorElement.style.marginTop = '5px';
        
        // Eliminar mensajes anteriores
        const existingError = element.parentNode.querySelector('.error-message');
        if (existingError) existingError.remove();
        
        element.parentNode.appendChild(errorElement);
        element.style.borderColor = '#ef233c';
    }
});