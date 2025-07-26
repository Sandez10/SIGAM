document.getElementById("trimestre").addEventListener("change", cargarDatos);
document.getElementById("ejercicio_fiscal").addEventListener("change", cargarDatos);
document.getElementById("resetFilters").addEventListener("click", resetearFiltros);
document.getElementById("exportCsv").addEventListener("click", exportarExcel);
document.getElementById("exportPdf").addEventListener("click", exportarPdf);

let tableData = [];

function cargarDatos() {
    const trimestre = document.getElementById("trimestre").value;
    const anio = document.getElementById("ejercicio_fiscal").value;
    console.log("Enviando solicitud con:", { trimestre, anio }); // Depuración

    if (!trimestre || !anio) {
        console.log("Faltan filtros");
        return;
    }

    fetch(`../datos-api/?trimestre=${trimestre}&anio=${anio}`)
        .then(res => {
            console.log("Estado HTTP:", res.status, res.statusText); // Depuración
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            console.log("Respuesta API:", data); // Depuración
            if (data.success) {
                tableData = data.data;

                if ($.fn.DataTable.isDataTable('#tablaPagos')) {
                    $('#tablaPagos').DataTable().clear().destroy();
                }

                $('#tablaPagos').DataTable({
                    data: tableData,
                    columns: [
                        { title: 'ID', data: 'id_hermano' },
                        { title: 'Nombre', data: 'nombre_hermano' },
                        { title: 'Grado', data: 'grado' },
                        { title: 'Capitas', data: 'capitas', render: $.fn.dataTable.render.number(',', '.', 2, '$') },
                        { title: 'Seguro', data: 'seguro', render: $.fn.dataTable.render.number(',', '.', 2, '$') },
                        { title: 'Iniciación', data: 'iniciacion', render: $.fn.dataTable.render.number(',', '.', 2, '$') },
                        { title: 'Afiliación', data: 'afiliacion', render: $.fn.dataTable.render.number(',', '.', 2, '$') },
                        { title: 'Exaltación', data: 'exaltacion', render: $.fn.dataTable.render.number(',', '.', 2, '$') },
                        { title: 'total', data: 'total', render: $.fn.dataTable.render.number(',', '.', 2, '$') },
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json'
                    },
                    pageLength: 10,
                    responsive: true
                });

                const totalPagos = tableData.reduce((acc, row) => {
                    return acc +
                        parseFloat(row.capitas || 0) +
                        parseFloat(row.seguro || 0) +
                        parseFloat(row.iniciacion || 0) +
                        parseFloat(row.afiliacion || 0) +
                        parseFloat(row.exaltacion || 0);
                }, 0);

                document.getElementById("totalPayments").textContent = `$${totalPagos.toFixed(2)}`;
                document.getElementById("recordCount").textContent = tableData.length;

                mostrarToast("Datos cargados correctamente", "success");
            } else {
                mostrarToast("Error al obtener datos: " + data.message, "error");
            }
        })
        .catch(err => {
            console.error("Error de fetch:", err.message);
            mostrarToast("Error al conectar con el servidor: " + err.message, "error");
        });
}

function resetearFiltros() {
    document.getElementById("trimestre").value = "";
    document.getElementById("ejercicio_fiscal").value = "";
    if ($.fn.DataTable.isDataTable('#tablaPagos')) {
        $('#tablaPagos').DataTable().clear().destroy();
    }
    document.getElementById("totalPayments").textContent = "$0.00";
    document.getElementById("recordCount").textContent = "0";
    mostrarToast("Filtros restablecidos", "info");
}
function exportarExcel() {
    const trimestre = document.getElementById("trimestre").value;
    const anio = document.getElementById("ejercicio_fiscal").value;
    
    if (!trimestre || !anio) {
        mostrarToast("Seleccione trimestre y año", "error");
        return;
    }
    
    // Redirigir a la URL que genera el Excel
    window.location.href = `../excel_pdf/reportesExcel/generar_reporte_excel.php?trimestre=${trimestre}&anio=${anio}`;
    
    // Opcional: Mostrar mensaje de que se está generando
    mostrarToast("Generando reporte Excel...", "info");
}   

function exportarPdf() {
    if (!tableData.length) {
        mostrarToast("No hay datos para exportar", "error");
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Relación de Pagos - Gran Logia del Estado de Guerrero", 14, 20);
    doc.text(`Trimestre: ${document.getElementById("trimestre").value} - Año: ${document.getElementById("ejercicio_fiscal").value}`, 14, 30);
    doc.autoTable({
        head: [['ID', 'Nombre', 'Grado', 'Logia', 'Capitas', 'Seguro', 'Iniciación', 'Afiliación', 'Exaltación', 'Regularización', 'total']],
        body: tableData.map(row => [
            row.id_hermano,
            row.nombre_hermano,
            row.grado,
            row.logia,
            `$${parseFloat(row.capitas || 0).toFixed(2)}`,
            `$${parseFloat(row.seguro || 0).toFixed(2)}`,
            `$${parseFloat(row.iniciacion || 0).toFixed(2)}`,
            `$${parseFloat(row.afiliacion || 0).toFixed(2)}`,
            `$${parseFloat(row.exaltacion || 0).toFixed(2)}`,
            `$${parseFloat(row.afiliacion_regularizacion || 0).toFixed(2)}`,
            `$${parseFloat(row.total || 0).toFixed(2)}`
        ]),
        startY: 40,
        theme: 'grid',
        styles: { fontSize: 8 }
    });
    doc.save(`Reporte_Tesoreria_${document.getElementById("trimestre").value}_${document.getElementById("ejercicio_fiscal").value}.pdf`);
    mostrarToast("Reporte exportado a PDF", "success");
}

function mostrarToast(mensaje, tipo) {
    const toast = document.getElementById("toast");
    toast.textContent = mensaje;
    toast.className = `toast show ${tipo}`;
    setTimeout(() => {
        toast.className = "toast";
    }, 3000);
}