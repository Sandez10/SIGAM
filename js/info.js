// Archivo: info_mejorado.js
// JavaScript mejorado para el formulario de secretaría con funcionalidad de edición

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes
    initializeCollapsibles();
    initializeFormValidation();
    initializeDynamicFields();
    initializeDignatarioLogic();
    initializePastMasterLogic();
    initializeFloatingButton();
    
    // Si estamos en modo edición, cargar los datos apropiados
    if (document.querySelector('input[name="id_registro"]')) {
        loadEditModeData();
    }
});


// Función para inicializar secciones colapsables
function initializeCollapsibles() {
    const collapsibleHeaders = document.querySelectorAll('.collapsible-header');
    
    collapsibleHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            const icon = this.querySelector('svg');
            
            if (content.classList.contains('open')) {
                content.classList.remove('open');
                icon.style.transform = 'rotate(-90deg)';
            } else {
                content.classList.add('open');
                icon.style.transform = 'rotate(0deg)';
            }
        });
    });
}

// Función para validación del formulario
function initializeFormValidation() {
    const form = document.getElementById('tesoreriaForm');
    const inputs = form.querySelectorAll('input[required], select[required]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearError);
    });
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            showValidationErrors();
        }
    });
}

// Función para validar un campo individual
function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    const errorElement = field.parentNode.querySelector('.error-message');
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'Este campo es obligatorio');
        return false;
    }
    
    // Validaciones específicas
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Ingrese un correo electrónico válido');
            return false;
        }
    }
    
    if (field.name === 'numero_contacto' || field.name === 'numero_emergencia') {
        const phoneRegex = /^[0-9]{10}$/;
        if (value && !phoneRegex.test(value.replace(/\s/g, ''))) {
            showFieldError(field, 'Ingrese un número de teléfono válido (10 dígitos)');
            return false;
        }
    }
    
    hideFieldError(field);
    return true;
}

// Función para mostrar error en un campo
function showFieldError(field, message) {
    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }
    field.classList.add('border-red-500');
}

// Función para ocultar error en un campo
function hideFieldError(field) {
    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) {
        errorElement.classList.add('hidden');
    }
    field.classList.remove('border-red-500');
}

// Función para limpiar errores al escribir
function clearError(e) {
    hideFieldError(e.target);
}

// Función para validar todo el formulario
function validateForm() {
    const form = document.getElementById('tesoreriaForm');
    const inputs = form.querySelectorAll('input[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField({target: input})) {
            isValid = false;
        }
    });
    
    return isValid;
}

// Función para mostrar errores de validación
function showValidationErrors() {
    Swal.fire({
        icon: 'error',
        title: 'Errores en el formulario',
        text: 'Por favor, corrija los errores marcados en rojo antes de continuar.',
        confirmButtonColor: '#d33'
    });
}

// Función para inicializar campos dinámicos según el grado
function initializeDynamicFields() {
    const gradoSelect = document.getElementById('Tipo_grado');
    if (gradoSelect) {
        gradoSelect.addEventListener('change', actualizarCampos);
    }
}

// Función para actualizar campos según el grado masónico
function actualizarCampos() {
    const grado = document.getElementById('Tipo_grado').value;
    
    // Contenedores de campos
    const salarioContainer = document.getElementById('salario_container');
    const exaltacionContainer = document.getElementById('fecha_exaltacion_container');
    const afiliacionContainer = document.getElementById('fecha_afiliacion_container');
    const dignatarioContainer = document.getElementById('dignatorio_oficial_container');
    const masterContainer = document.getElementById('master_container');
    const periodoContainer = document.getElementById('periodo_container');
    
    // Ocultar todos los campos primero
    [salarioContainer, exaltacionContainer, afiliacionContainer, 
     dignatarioContainer, masterContainer, periodoContainer].forEach(container => {
        if (container) container.classList.add('hidden');
    });
    
    // Mostrar campos según el grado
    switch(grado) {
        case 'aprendiz':
            // Solo campos básicos
            break;
            
        case 'companero':
            if (salarioContainer) salarioContainer.classList.remove('hidden');
            break;
            
        case 'maestro':
            if (salarioContainer) salarioContainer.classList.remove('hidden');
            if (exaltacionContainer) exaltacionContainer.classList.remove('hidden');
            if (dignatarioContainer) dignatarioContainer.classList.remove('hidden');
            if (masterContainer) masterContainer.classList.remove('hidden');
            if (periodoContainer) periodoContainer.classList.remove('hidden');
            break;
    }
    
    // Mostrar campo de afiliación si el tipo de ingreso es afiliación
    const tipoIngreso = document.getElementById('tipo_ingreso');
    if (tipoIngreso && tipoIngreso.value === 'afiliacion' && afiliacionContainer) {
        afiliacionContainer.classList.remove('hidden');
    }
}

// Función para inicializar lógica de dignatario
function initializeDignatarioLogic() {
    const dignatarioSi = document.getElementById('dignatario_si');
    const dignatarioNo = document.getElementById('dignatario_no');
    const selectContainer = document.getElementById('dignatario_select_container');
    
    if (dignatarioSi && dignatarioNo && selectContainer) {
        dignatarioSi.addEventListener('change', function() {
            if (this.checked) {
                selectContainer.classList.remove('hidden');
            }
        });
        
        dignatarioNo.addEventListener('change', function() {
            if (this.checked) {
                selectContainer.classList.add('hidden');
                document.getElementById('dignatario_select').value = '';
            }
        });
    }
}

// Función para inicializar lógica de Past Master
function initializePastMasterLogic() {
    const pastMasterSi = document.getElementById('past_master_si');
    const pastMasterNo = document.getElementById('past_master_no');
    const fechasContainer = document.getElementById('master_fechas_container');
    const addFechaBtn = document.getElementById('add_master_fecha');
    
    if (pastMasterSi && pastMasterNo && fechasContainer) {
        pastMasterSi.addEventListener('change', function() {
            if (this.checked) {
                fechasContainer.classList.remove('hidden');
            }
        });
        
        pastMasterNo.addEventListener('change', function() {
            if (this.checked) {
                fechasContainer.classList.add('hidden');
                // Limpiar fechas
                const fechaInputs = fechasContainer.querySelectorAll('input[type="date"]');
                fechaInputs.forEach(input => input.value = '');
            }
        });
    }
    
    // Agregar nueva fecha
    if (addFechaBtn) {
        addFechaBtn.addEventListener('click', function() {
            const fechasInputs = document.getElementById('master_fechas_inputs');
            const nuevaFila = document.createElement('div');
            nuevaFila.className = 'flex items-center gap-2 fecha-row';
            nuevaFila.innerHTML = `
                <input type="date" name="past_master_fechas[]" class="w-full glass-card">
                <button type="button" class="btn btn-error btn-del-fecha text-xs px-2">✕</button>
            `;
            fechasInputs.appendChild(nuevaFila);
            
            // Agregar evento para eliminar
            nuevaFila.querySelector('.btn-del-fecha').addEventListener('click', function() {
                nuevaFila.remove();
            });
        });
    }
    
    // Eliminar fechas existentes
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-del-fecha')) {
            const fechaRows = document.querySelectorAll('.fecha-row');
            if (fechaRows.length > 1) {
                e.target.closest('.fecha-row').remove();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: 'Debe mantener al menos una fecha.',
                    confirmButtonColor: '#3085d6'
                });
            }
        }
    });
}

// Función para inicializar botón flotante
function initializeFloatingButton() {
    const floatingBtn = document.getElementById('floating-save');
    
    if (floatingBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                floatingBtn.classList.add('show');
            } else {
                floatingBtn.classList.remove('show');
            }
        });
    }
}

// Función para cargar datos en modo edición
function loadEditModeData() {
    // Actualizar campos dinámicos basados en los datos cargados
    setTimeout(() => {
        actualizarCampos();
        
        // Verificar estado de dignatario
        const dignatarioSi = document.getElementById('dignatario_si');
        const selectContainer = document.getElementById('dignatario_select_container');
        if (dignatarioSi && dignatarioSi.checked && selectContainer) {
            selectContainer.classList.remove('hidden');
        }
        
        // Verificar estado de Past Master
        const pastMasterSi = document.getElementById('past_master_si');
        const fechasContainer = document.getElementById('master_fechas_container');
        if (pastMasterSi && pastMasterSi.checked && fechasContainer) {
            fechasContainer.classList.remove('hidden');
        }
    }, 100);
}

// Función para confirmar eliminación de registro (para uso futuro)
function confirmarEliminacion(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Deseas eliminar el registro de ${nombre}? Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí iría la lógica para eliminar el registro
            window.location.href = `../registros_b/eliminar_registro.php?id=${id}`;
        }
    });
}

// Función para limpiar formulario con confirmación
function limpiarFormulario() {
    Swal.fire({
        title: '¿Limpiar formulario?',
        text: 'Se perderán todos los datos ingresados.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('tesoreriaForm').reset();
            // Ocultar campos dinámicos
            actualizarCampos();
        }
    });
}

// Función para autoguardado (opcional)
function autoGuardar() {
    const formData = new FormData(document.getElementById('tesoreriaForm'));
    const data = Object.fromEntries(formData.entries());
    
    // Guardar en localStorage
    localStorage.setItem('form_sec_autosave', JSON.stringify(data));
    
    // Mostrar indicador de guardado
    const indicator = document.createElement('div');
    indicator.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg z-50';
    indicator.textContent = '✓ Autoguardado';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 2000);
}

// Función para recuperar autoguardado
function recuperarAutoguardado() {
    const saved = localStorage.getItem('form_sec_autosave');
    if (saved) {
        const data = JSON.parse(saved);
        
        Swal.fire({
            title: 'Datos guardados encontrados',
            text: '¿Deseas recuperar los datos guardados automáticamente?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, recuperar',
            cancelButtonText: 'No, empezar nuevo'
        }).then((result) => {
            if (result.isConfirmed) {
                // Cargar datos guardados
                Object.keys(data).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field) {
                        field.value = data[key];
                    }
                });
                
                // Actualizar campos dinámicos
                actualizarCampos();
            } else {
                // Limpiar autoguardado
                localStorage.removeItem('form_sec_autosave');
            }
        });
    }
}

// Exportar funciones para uso global
window.actualizarCampos = actualizarCampos;
window.confirmarEliminacion = confirmarEliminacion;
window.limpiarFormulario = limpiarFormulario;

