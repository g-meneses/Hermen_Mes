/**
 * =====================================================
 * MATERIAS PRIMAS - INGRESO DINÁMICO
 * Sistema ERP Hermen v2.1.0
 * VERSIÓN FINAL - RUTAS AJUSTADAS
 * =====================================================
 */

// ========================================
// CONFIGURACIÓN DE RUTAS
// ========================================

const BASE_URL_API = window.location.origin + '/mes_hermen/api';

// ========================================
// VARIABLES GLOBALES
// ========================================

let tiposIngresoConfig = {};
let tipoIngresoActual = null;
let motivosCache = {};
let areasProduccion = [];
let proveedoresData = [];
let productosData = [];
let categoriasData = [];
let subcategoriasData = [];
let unidadesMedida = [];
let lineasIngreso = [];

// ========================================
// FUNCIONES DE UTILIDAD
// ========================================

function mostrarAlerta(mensaje, tipo = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: tipo === 'error' ? 'error' : tipo === 'success' ? 'success' : 'info',
            title: tipo === 'error' ? 'Error' : tipo === 'success' ? 'Éxito' : 'Información',
            text: mensaje,
            confirmButtonText: 'OK'
        });
    } else {
        alert(mensaje);
    }
}

function logError(contexto, error) {
    console.error(`❌ Error en ${contexto}:`, error);
    if (error.response) {
        console.error('Response:', error.response);
    }
}

// ========================================
// INICIALIZACIÓN
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando módulo de Materias Primas...');
    console.log('📁 API Base URL:', BASE_URL_API);
    
    cargarTiposIngreso();
    cargarAreasProduccion();
    cargarUnidadesMedida();
});

/**
 * Cargar tipos de ingreso disponibles
 */
async function cargarTiposIngreso() {
    try {
        const url = `${BASE_URL_API}/tipos_ingreso.php?action=list`;
        console.log('🔄 Cargando tipos desde:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            data.tipos.forEach(tipo => {
                tiposIngresoConfig[tipo.id_tipo_ingreso] = tipo;
            });
            
            const select = document.getElementById('ingresoTipoIngreso');
            if (select) {
                select.innerHTML = '<option value="">Seleccione tipo de ingreso...</option>';
                data.tipos.forEach(tipo => {
                    select.innerHTML += `
                        <option value="${tipo.id_tipo_ingreso}" data-codigo="${tipo.codigo}">
                            ${tipo.nombre}
                        </option>
                    `;
                });
            }
            
            console.log('✅ Tipos de ingreso cargados:', Object.keys(tiposIngresoConfig).length);
        } else {
            logError('cargarTiposIngreso', data.message);
        }
    } catch (error) {
        logError('cargarTiposIngreso', error);
        
        if (error.message.includes('404')) {
            console.error('⚠️  ARCHIVO NO ENCONTRADO');
            console.log('📁 Verifica que tipos_ingreso.php esté en: C:\\xampp\\htdocs\\mes_hermen\\api\\tipos_ingreso.php');
        }
    }
}

/**
 * Cargar áreas de producción
 */
async function cargarAreasProduccion() {
    try {
        const url = `${BASE_URL_API}/tipos_ingreso.php?action=areas`;
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            areasProduccion = data.areas;
            console.log('✅ Áreas de producción cargadas:', areasProduccion.length);
        }
    } catch (error) {
        logError('cargarAreasProduccion', error);
    }
}

/**
 * Cargar unidades de medida
 */
async function cargarUnidadesMedida() {
    try {
        const url = `${BASE_URL_API}/unidades_medida.php?action=list`;
        const response = await fetch(url);
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                unidadesMedida = data.unidades;
                console.log('✅ Unidades de medida cargadas:', unidadesMedida.length);
            }
        } else {
            console.warn('⚠️  Endpoint de unidades no disponible, usando valores por defecto');
            unidadesMedida = [
                {id_unidad: 1, codigo: 'KG', nombre: 'Kilogramo', abreviatura: 'kg'},
                {id_unidad: 2, codigo: 'MT', nombre: 'Metro', abreviatura: 'm'},
                {id_unidad: 3, codigo: 'UND', nombre: 'Unidad', abreviatura: 'und'},
                {id_unidad: 4, codigo: 'LT', nombre: 'Litro', abreviatura: 'lt'}
            ];
        }
    } catch (error) {
        logError('cargarUnidadesMedida', error);
        unidadesMedida = [
            {id_unidad: 1, codigo: 'KG', nombre: 'Kilogramo', abreviatura: 'kg'},
            {id_unidad: 2, codigo: 'MT', nombre: 'Metro', abreviatura: 'm'},
            {id_unidad: 3, codigo: 'UND', nombre: 'Unidad', abreviatura: 'und'}
        ];
    }
}

// ========================================
// APERTURA DEL MODAL
// ========================================

async function abrirModalIngreso() {
    console.log('📖 Abriendo modal de ingreso...');
    
    resetearFormularioIngreso();
    await obtenerSiguienteNumero();
    
    const hoy = new Date().toISOString().split('T')[0];
    const fechaInput = document.getElementById('ingresoFecha');
    if (fechaInput) fechaInput.value = hoy;
    
    await cargarProveedores();
    await cargarCategoriasIngreso();
    
    const modal = document.getElementById('modalIngreso');
    if (modal) modal.classList.add('show');
}

async function obtenerSiguienteNumero() {
    try {
        const url = `${BASE_URL_API}/ingresos_mp.php?action=siguiente_numero`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            const docInput = document.getElementById('ingresoDocumento');
            if (docInput) docInput.value = data.numero;
        }
    } catch (error) {
        logError('obtenerSiguienteNumero', error);
    }
}

function resetearFormularioIngreso() {
    const selectTipo = document.getElementById('ingresoTipoIngreso');
    if (selectTipo) selectTipo.value = '';
    tipoIngresoActual = null;
    
    const campos = {
        'ingresoDocumento': '',
        'ingresoFecha': '',
        'ingresoObservaciones': '',
        'ingresoTipoProveedor': 'TODOS',
        'ingresoProveedor': '',
        'ingresoReferencia': ''
    };
    
    Object.keys(campos).forEach(id => {
        const campo = document.getElementById(id);
        if (campo) campo.value = campos[id];
    });
    
    const checkbox = document.getElementById('ingresoConFactura');
    if (checkbox) checkbox.checked = false;
    
    const camposDinamicos = [
        'ingresoArea', 'ingresoResponsableEntrega', 'ingresoMotivo', 
        'ingresoAutorizadoPor', 'ingresoUbicacion', 'ingresoResponsableConteo'
    ];
    camposDinamicos.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) campo.value = '';
    });
    
    lineasIngreso = [];
    const tbody = document.getElementById('ingresoLineasBody');
    if (tbody) tbody.innerHTML = '';
    
    ocultarTodasLasSecciones();
    actualizarTotalesIngreso();
}

function ocultarTodasLasSecciones() {
    const secciones = [
        'seccionProveedor', 'seccionFactura', 'seccionArea',
        'seccionMotivo', 'seccionAutorizacion', 'seccionUbicacion'
    ];
    
    secciones.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) elemento.classList.add('d-none');
    });
    
    const infoBox = document.getElementById('infoProveedorBox');
    if (infoBox) infoBox.style.display = 'none';
    
    const rowIVA = document.getElementById('rowIVA');
    if (rowIVA) rowIVA.style.display = 'none';
}

// ========================================
// CAMBIO DE TIPO DE INGRESO
// ========================================

async function cambiarTipoIngreso() {
    const selectTipo = document.getElementById('ingresoTipoIngreso');
    if (!selectTipo) return;
    
    const tipoId = parseInt(selectTipo.value);
    
    if (!tipoId) {
        ocultarTodasLasSecciones();
        tipoIngresoActual = null;
        return;
    }
    
    const config = tiposIngresoConfig[tipoId];
    if (!config) {
        console.error('❌ Configuración no encontrada para tipo:', tipoId);
        return;
    }
    
    tipoIngresoActual = config;
    console.log('🔄 Cambiando a tipo:', config.nombre, config);
    
    // 1. PROVEEDOR
    mostrarSeccion('seccionProveedor', config.requiere_proveedor);
    setRequired('ingresoProveedor', config.requiere_proveedor);
    
    // 2. FACTURA
    mostrarSeccion('seccionFactura', config.requiere_factura);
    if (!config.requiere_factura) {
        const checkbox = document.getElementById('ingresoConFactura');
        if (checkbox) checkbox.checked = false;
    }
    
    // 3. ÁREA DE PRODUCCIÓN
    if (config.requiere_area_produccion) {
        await mostrarSeccionArea();
    } else {
        mostrarSeccion('seccionArea', false);
    }
    
    // 4. MOTIVO
    if (config.requiere_motivo) {
        await mostrarSeccionMotivo(tipoId);
    } else {
        mostrarSeccion('seccionMotivo', false);
    }
    
    // 5. AUTORIZACIÓN
    if (config.requiere_autorizacion) {
        mostrarSeccion('seccionAutorizacion', true);
        setRequired('ingresoAutorizadoPor', true);
    } else {
        mostrarSeccion('seccionAutorizacion', false);
        setRequired('ingresoAutorizadoPor', false);
    }
    
    // 6. UBICACIÓN (para inventario inicial)
    mostrarSeccion('seccionUbicacion', config.codigo === 'INICIAL');
    
    // 7. CONFIGURAR OBSERVACIONES
    configurarObservaciones(config);
    
    // 8. ACTUALIZAR ENCABEZADOS DE TABLA
    actualizarEncabezadosTabla(config);
}

function mostrarSeccion(seccionId, mostrar) {
    const seccion = document.getElementById(seccionId);
    if (!seccion) {
        console.warn(`⚠️  Sección ${seccionId} no encontrada`);
        return;
    }
    
    if (mostrar) {
        seccion.classList.remove('d-none');
    } else {
        seccion.classList.add('d-none');
    }
}

function setRequired(fieldId, required) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    if (required) {
        field.setAttribute('required', 'required');
    } else {
        field.removeAttribute('required');
    }
}

async function mostrarSeccionArea() {
    mostrarSeccion('seccionArea', true);
    setRequired('ingresoArea', true);
    
    const selectArea = document.getElementById('ingresoArea');
    if (selectArea && areasProduccion.length > 0) {
        selectArea.innerHTML = '<option value="">Seleccione área...</option>';
        areasProduccion.forEach(area => {
            selectArea.innerHTML += `<option value="${area.id_area}">${area.nombre}</option>`;
        });
    }
}

async function mostrarSeccionMotivo(tipoId) {
    if (!motivosCache[tipoId]) {
        try {
            const url = `${BASE_URL_API}/tipos_ingreso.php?action=motivos&tipo_id=${tipoId}`;
            const response = await fetch(url);
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    motivosCache[tipoId] = data.motivos;
                }
            }
        } catch (error) {
            logError('mostrarSeccionMotivo', error);
        }
    }
    
    mostrarSeccion('seccionMotivo', true);
    setRequired('ingresoMotivo', true);
    
    const selectMotivo = document.getElementById('ingresoMotivo');
    if (selectMotivo && motivosCache[tipoId]) {
        selectMotivo.innerHTML = '<option value="">Seleccione motivo...</option>';
        motivosCache[tipoId].forEach(motivo => {
            selectMotivo.innerHTML += `
                <option value="${motivo.id_motivo}" data-requiere-detalle="${motivo.requiere_detalle}">
                    ${motivo.descripcion}
                </option>
            `;
        });
    }
}

function configurarObservaciones(config) {
    const obsField = document.getElementById('ingresoObservaciones');
    if (!obsField) return;
    
    setRequired('ingresoObservaciones', config.observaciones_obligatorias);
    
    if (config.observaciones_obligatorias && config.minimo_caracteres_obs > 0) {
        obsField.setAttribute('minlength', config.minimo_caracteres_obs);
        obsField.placeholder = `Observaciones (mínimo ${config.minimo_caracteres_obs} caracteres) *`;
    } else {
        obsField.removeAttribute('minlength');
        obsField.placeholder = 'Observaciones opcionales...';
    }
}

function actualizarEncabezadosTabla(config) {
    const thead = document.getElementById('theadIngreso');
    if (!thead) return;
    
    const conFactura = config.permite_iva && document.getElementById('ingresoConFactura')?.checked;
    
    if (conFactura) {
        thead.innerHTML = `
            <tr>
                <th class="col-producto">Producto</th>
                <th class="col-unidad">Unidad</th>
                <th class="col-cantidad">Cantidad</th>
                <th class="col-costo">Costo Unit. (con IVA)</th>
                <th class="col-iva">IVA 13%</th>
                <th class="col-total">Subtotal</th>
                <th class="col-acciones"></th>
            </tr>
        `;
    } else {
        thead.innerHTML = `
            <tr>
                <th class="col-producto">Producto</th>
                <th class="col-unidad">Unidad</th>
                <th class="col-cantidad">Cantidad</th>
                <th class="col-costo">Costo Unitario</th>
                <th class="col-total">Subtotal</th>
                <th class="col-acciones"></th>
            </tr>
        `;
    }
}

function actualizarTotalesIngreso() {
    const totalNeto = document.getElementById('ingresoTotalNeto');
    const totalIVA = document.getElementById('ingresoIVA');
    const totalFinal = document.getElementById('ingresoTotal');
    
    if (totalNeto) totalNeto.textContent = 'Bs. 0.00';
    if (totalIVA) totalIVA.textContent = 'Bs. 0.00';
    if (totalFinal) totalFinal.textContent = 'Bs. 0.00';
}

async function cargarProveedores() {
    console.log('Cargando proveedores...');
}

async function cargarCategoriasIngreso() {
    console.log('Cargando categorías...');
}

console.log('✅ Módulo de tipos de ingreso dinámico cargado - VERSIÓN FINAL');
console.log('📁 API Base:', BASE_URL_API);