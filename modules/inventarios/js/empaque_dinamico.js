/**
 * =====================================================
 * MATERIAS PRIMAS - INGRESO DINÃMICO
 * Sistema ERP Hermen v2.1.0
 * VERSIÃ“N FINAL - RUTAS AJUSTADAS
 * =====================================================
 */

// ========================================
// CONFIGURACIÃ“N DE RUTAS
// ========================================

// const BASE_URL_API = window.location.origin + '/mes_hermen/api'; // Ya declarado en materias_primas.js

// ========================================
// VARIABLES GLOBALES
// ========================================

// Variables compartidas con materias_primas.js
window.tiposIngresoConfig = window.tiposIngresoConfig || {};
let tiposIngresoConfig = window.tiposIngresoConfig;
let tipoIngresoActual = null;
let motivosCache = {};
let areasProduccion = [];
let proveedoresData = [];
let productosData = [];
let categoriasData = [];
let subcategoriasData = [];
let unidadesMedida = [];
let usuariosAutorizados = [];
// let lineasIngreso = []; //

/**
 * ConfiguraciÃ³n de columnas por tipo de ingreso
 * Define quÃ© columnas mostrar para cada tipo
 */
const COLUMNAS_CONFIG = {
    // COMPRA A PROVEEDOR - Complejo con factura/IVA
    'COMPRA': {
        conFactura: {
            columnas: [
                { id: 'num', label: '#', width: '40px', align: 'center' },
                { id: 'producto', label: 'PRODUCTO', width: '280px' },
                { id: 'unidad', label: 'UNID.', width: '50px', align: 'center' },
                { id: 'cantidad', label: 'CANTIDAD', width: '90px', input: true, bg: '#fff3cd' },
                { id: 'valor_total', label: 'VALOR TOTAL', width: '110px', input: true, bg: '#fff3cd' },
                { id: 'costo_doc', label: 'COSTO UNIT.<br>DOC.', width: '100px', calculado: true },
                { id: 'iva', label: 'IVA 13%', width: '90px', calculado: true, bg: '#fff9e6' },
                { id: 'costo_item', label: 'COSTO<br>ITEM', width: '90px', calculado: true },
                { id: 'costo_neto', label: 'COSTO UNIT.<br>- IVA', width: '100px', calculado: true, bg: '#d4edda' },
                { id: 'acciones', label: '', width: '50px' }
            ]
        },
        sinFactura: {
            columnas: [
                { id: 'num', label: '#', width: '40px', align: 'center' },
                { id: 'producto', label: 'PRODUCTO', width: '350px' },
                { id: 'unidad', label: 'UNID.', width: '60px', align: 'center' },
                { id: 'cantidad', label: 'CANTIDAD', width: '120px', input: true, bg: '#fff3cd' },
                { id: 'valor_total', label: 'VALOR TOTAL', width: '140px', input: true, bg: '#fff3cd' },
                { id: 'costo_unitario', label: 'COSTO UNITARIO', width: '140px', calculado: true, bg: '#d4edda' },
                { id: 'acciones', label: '', width: '50px' }
            ]
        }
    },

    // INVENTARIO INICIAL - Simplificado (solo cantidad y costo)
    'INICIAL': {
        columnas: [
            { id: 'num', label: '#', width: '40px', align: 'center' },
            { id: 'producto', label: 'PRODUCTO', width: '400px' },
            { id: 'unidad', label: 'UNID.', width: '70px', align: 'center' },
            { id: 'cantidad', label: 'CANTIDAD<br>INICIAL', width: '120px', input: true, bg: '#e3f2fd' },
            { id: 'costo_unitario', label: 'COSTO<br>UNITARIO', width: '120px', input: true, bg: '#e3f2fd' },
            { id: 'valor_total', label: 'VALOR<br>TOTAL', width: '140px', calculado: true, bg: '#f1f8e9' },
            { id: 'acciones', label: '', width: '50px' }
        ]
    },

    // DEVOLUCIÃ“N DE PRODUCCIÃ“N - Simplificado (solo cantidad devuelta)
    'DEVOLUCION_PROD': {
        columnas: [
            { id: 'num', label: '#', width: '40px', align: 'center' },
            { id: 'producto', label: 'PRODUCTO', width: '400px' },
            { id: 'unidad', label: 'UNID.', width: '70px', align: 'center' },
            { id: 'cantidad', label: 'CANTIDAD<br>DEVUELTA', width: '120px', input: true, bg: '#fff3e0' },
            { id: 'costo_unitario', label: 'COSTO<br>PROMEDIO', width: '140px', calculado: true, readonly: true },
            { id: 'valor_total', label: 'VALOR<br>TOTAL', width: '140px', calculado: true, bg: '#f1f8e9' },
            { id: 'acciones', label: '', width: '50px' }
        ]
    },

    // AJUSTE POSITIVO - Similar a inventario inicial
    'AJUSTE_POS': {
        columnas: [
            { id: 'num', label: '#', width: '40px', align: 'center' },
            { id: 'producto', label: 'PRODUCTO', width: '400px' },
            { id: 'unidad', label: 'UNID.', width: '70px', align: 'center' },
            { id: 'cantidad', label: 'CANTIDAD AJUSTE', width: '120px', input: true, bg: '#e8f5e9' },
            { id: 'costo_promedio', label: 'COSTO PROMEDIO', width: '140px', calculado: true, readonly: true },
            { id: 'valor_total', label: 'VALOR TOTAL', width: '140px', calculado: true, bg: '#f1f8e9' },
            { id: 'acciones', label: '', width: '50px' }
        ]
    }
};


// ========================================
// FUNCIONES DE UTILIDAD
// ========================================

function mostrarAlerta(mensaje, tipo = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: tipo === 'error' ? 'error' : tipo === 'success' ? 'success' : 'info',
            title: tipo === 'error' ? 'Error' : tipo === 'success' ? 'Ã‰xito' : 'InformaciÃ³n',
            text: mensaje,
            confirmButtonText: 'OK'
        });
    } else {
        alert(mensaje);
    }
}

function logError(contexto, error) {
    console.error(`âŒ Error en ${contexto}:`, error);
    if (error.response) {
        console.error('Response:', error.response);
    }
}

// ========================================
// INICIALIZACIÃ“N
// ========================================

document.addEventListener('DOMContentLoaded', function () {
    console.log('ðŸš€ Inicializando mÃ³dulo de Material de Empaque...');
    console.log('ðŸ“ API Base URL:', BASE_URL_API);

    cargarTiposIngreso();
    cargarAreasProduccion();
    cargarUnidadesMedida();
    cargarUsuariosAutorizados();
});

/**
 * Cargar tipos de ingreso disponibles
 */
async function cargarTiposIngreso() {
    try {
        const url = `${BASE_URL_API}/tipos_ingreso.php?action=list`;
        console.log('ðŸ”„ Cargando tipos desde:', url);

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
                    if (tipo.codigo !== 'COMPRA') {
                        select.innerHTML += `
                            <option value="${tipo.id_tipo_ingreso}" data-codigo="${tipo.codigo}">
                                ${tipo.nombre}
                            </option>
                        `;
                    }
                });
            }

            console.log('âœ… Tipos de ingreso cargados:', Object.keys(tiposIngresoConfig).length);
        } else {
            logError('cargarTiposIngreso', data.message);
        }
    } catch (error) {
        logError('cargarTiposIngreso', error);

        if (error.message.includes('404')) {
            console.error('âš ï¸  ARCHIVO NO ENCONTRADO');
            console.log('ðŸ“ Verifica que tipos_ingreso.php estÃ© en: C:\\xampp\\htdocs\\mes_hermen\\api\\tipos_ingreso.php');
        }
    }
}

/**
 * Cargar Ã¡reas de producciÃ³n
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
            console.log('âœ… Ãreas de producciÃ³n cargadas:', areasProduccion.length);
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
                console.log('âœ… Unidades de medida cargadas:', unidadesMedida.length);
            }
        } else {
            console.warn('âš ï¸  Endpoint de unidades no disponible, usando valores por defecto');
            unidadesMedida = [
                { id_unidad: 1, codigo: 'KG', nombre: 'Kilogramo', abreviatura: 'kg' },
                { id_unidad: 2, codigo: 'MT', nombre: 'Metro', abreviatura: 'm' },
                { id_unidad: 3, codigo: 'UND', nombre: 'Unidad', abreviatura: 'und' },
                { id_unidad: 4, codigo: 'LT', nombre: 'Litro', abreviatura: 'lt' }
            ];
        }
    } catch (error) {
        logError('cargarUnidadesMedida', error);
        unidadesMedida = [
            { id_unidad: 1, codigo: 'KG', nombre: 'Kilogramo', abreviatura: 'kg' },
            { id_unidad: 2, codigo: 'MT', nombre: 'Metro', abreviatura: 'm' },
            { id_unidad: 3, codigo: 'UND', nombre: 'Unidad', abreviatura: 'und' }
        ];
    }
}

// ========================================
// APERTURA DEL MODAL
// ========================================

async function abrirModalIngreso() {
    console.log('ðŸ“– Abriendo modal de ingreso...');

    await cargarProveedores();
    await cargarCategoriasIngreso();
    resetearFormularioIngreso();

    // â­ NO GENERAR NÃšMERO AQUÃ
    numerosDocumentoCache = {};
    const docInput = document.getElementById('ingresoDocumento');
    if (docInput) {
        docInput.value = '';
        docInput.placeholder = 'Seleccione tipo de ingreso...';
        docInput.disabled = true;
    }

    const hoy = new Date().toISOString().split('T')[0];
    const fechaInput = document.getElementById('ingresoFecha');
    if (fechaInput) fechaInput.value = hoy;

    const modal = document.getElementById('modalIngreso');
    if (modal) modal.classList.add('show');
}

async function obtenerSiguienteNumero(tipo = null) {
    if (!tipo) {
        const selectTipo = document.getElementById('ingresoTipoIngreso');
        if (selectTipo && selectTipo.value) {
            const tC = tiposIngresoConfig[selectTipo.value];
            tipo = tC ? tC.codigo : null;
        }
    }
    if (!tipo) return;

    const docInput = document.getElementById('ingresoDocumento');
    if (numerosDocumentoCache[tipo]) {
        if (docInput) {
            docInput.value = numerosDocumentoCache[tipo];
            docInput.disabled = false;
        }
        return;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    try {
        if (docInput) {
            docInput.value = 'â³ Generando...';
            docInput.disabled = true;
        }

        const url = ${BASE_URL_API}/ingresos_emp.php?action=siguiente_numero&tipo=;
        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

        if (response.status === 401) throw new Error('SESIÃ“N_EXPIRADA');

        const data = await response.json();

        if (data.success && docInput) {
            numerosDocumentoCache[tipo] = data.numero;
            docInput.value = data.numero;
            docInput.disabled = false;
        } else {
            if (data.message && (data.message.includes('autorizado') || data.message.includes('SesiÃ³n'))) throw new Error('SESIÃ“N_EXPIRADA');
            throw new Error(data.message || 'Error servidor');
        }
    } catch (error) {
        clearTimeout(timeoutId);
        if (error.name === 'AbortError') {
            ejecutarFallbackLocalIngreso(tipo, 'Timeout');
        } else if (error.message === 'SESIÃ“N_EXPIRADA') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'SesiÃ³n Expirada', text: 'Por favor reingrese.', icon: 'warning' }).then(() => { window.location.href = 'index.php'; });
            }
            if (docInput) { docInput.value = ''; docInput.disabled = true; }
        } else {
            ejecutarFallbackLocalIngreso(tipo, error.message);
        }
    } finally {
        if (docInput && docInput.value === 'â³ Generando...') { docInput.value = ''; docInput.disabled = false; }
    }
}

function ejecutarFallbackLocalIngreso(tipo, motivo) {
    const docInput = document.getElementById('ingresoDocumento');
    if (!docInput) return;
    const codigosMovimiento = { 'COMPRA': 'C', 'INICIAL': 'I', 'DEVOLUCION_PROD': 'R', 'AJUSTE_POS': 'A' };
    const hoy = new Date();
    const fechaStr = hoy.toISOString().split('T')[0].replace(/-/g, '');
    const rand = Math.floor(Math.random() * 100).toString().padStart(2, '0');
    const movCode = codigosMovimiento[tipo] || 'X';
    const numero = 'IN-EMP-' + movCode + '-' + fechaStr + '-' + rand;
    numerosDocumentoCache[tipo] = numero;
    docInput.value = numero;
    docInput.disabled = false;
    console.warn('Fallback IN EMP:', numero);
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
        // 'ingresoProveedor': '',  â† âŒ QUITAR ESTA LÃNEA (no resetear proveedor aquÃ­)
        'ingresoReferencia': ''
    };

    Object.keys(campos).forEach(id => {
        const campo = document.getElementById(id);
        if (campo) campo.value = campos[id];
    });

    // Resetear proveedor solo si NO hay datos cargados
    const selectProveedor = document.getElementById('ingresoProveedor');
    if (selectProveedor && proveedoresData.length === 0) {
        selectProveedor.value = '';
    }

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
        console.error('âŒ ConfiguraciÃ³n no encontrada para tipo:', tipoId);
        return;
    }

    tipoIngresoActual = config;
    console.log('ðŸ”„ Cambiando a tipo:', config.nombre, config);

    // 1. PROVEEDOR
    mostrarSeccion('seccionProveedor', config.requiere_proveedor);
    setRequired('ingresoProveedor', config.requiere_proveedor);

    // â­ NUEVO: Poblar proveedores si es requerido
    if (config.requiere_proveedor) {
        poblarSelectProveedores();
    }

    // 2. FACTURA
    mostrarSeccion('seccionFactura', config.requiere_factura);
    if (!config.requiere_factura) {
        const checkbox = document.getElementById('ingresoConFactura');
        if (checkbox) checkbox.checked = false;
    }

    // 3. ÃREA DE PRODUCCIÃ“N
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

    // 5. AUTORIZACIÃ“N
    if (config.requiere_autorizacion && config.codigo !== 'AJUSTE_POS') {
        mostrarSeccionAutorizacion();
    } else {
        mostrarSeccion('seccionAutorizacion', false);
        setRequired('ingresoAutorizadoPor', false);
    }

    // 6. UBICACIÃ“N (para inventario inicial)
    mostrarSeccion('seccionUbicacion', config.codigo === 'INICIAL');

    // 7. CONFIGURAR OBSERVACIONES
    configurarObservaciones(config);

    // 8. ACTUALIZAR ENCABEZADOS DE TABLA
    actualizarEncabezadosTabla(config);

    // â­ 9. REGENERAR NÃšMERO DE DOCUMENTO CON EL NUEVO TIPO
    await obtenerSiguienteNumero(config.codigo);
}

// â­ NUEVA FUNCIÃ“N: Poblar select de proveedores
function poblarSelectProveedores() {
    const selectProveedor = document.getElementById('ingresoProveedor');

    if (!selectProveedor) {
        console.error('âŒ Select de proveedor no encontrado');
        return;
    }

    console.log('ðŸ“‹ Poblando select de proveedores...', proveedoresData.length);

    // Limpiar opciones actuales
    selectProveedor.innerHTML = '<option value="">Seleccione proveedor...</option>';

    // Verificar que hay proveedores cargados
    if (!proveedoresData || proveedoresData.length === 0) {
        console.warn('âš ï¸ No hay proveedores para mostrar');
        return;
    }

    // Agregar cada proveedor
    proveedoresData.forEach(prov => {
        const nombre = prov.nombre_comercial || prov.razon_social || 'Sin nombre';
        const option = document.createElement('option');
        option.value = prov.id_proveedor;
        option.textContent = nombre;
        option.dataset.tipo = prov.tipo || 'LOCAL';
        option.dataset.moneda = prov.moneda || 'BOB';
        option.dataset.pago = prov.condicion_pago || 'CONTADO';
        selectProveedor.appendChild(option);
    });

    console.log('âœ… Select poblado con', selectProveedor.options.length - 1, 'proveedores');
}

function mostrarSeccion(seccionId, mostrar) {
    const seccion = document.getElementById(seccionId);
    if (!seccion) {
        console.warn(`âš ï¸  SecciÃ³n ${seccionId} no encontrada`);
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
        selectArea.innerHTML = '<option value="">Seleccione Ã¡rea...</option>';
        areasProduccion.forEach(area => {
            selectArea.innerHTML += `<option value="${area.id_area}">${area.nombre}</option>`;
        });
    }
}



async function mostrarSeccionMotivo(tipoId) {
    console.log('ðŸ”„ Cargando motivos para tipo:', tipoId);

    // Cargar motivos si no estÃ¡n en cachÃ©
    if (!motivosCache[tipoId]) {
        try {
            const url = `${BASE_URL_API}/tipos_ingreso.php?action=motivos&tipo_id=${tipoId}`;
            console.log('ðŸ“¡ URL motivos:', url);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            console.log('ðŸ“¦ Respuesta motivos:', data);

            if (data.success) {
                motivosCache[tipoId] = data.motivos;
                console.log('âœ… Motivos cargados:', data.motivos.length);
            } else {
                console.error('âŒ Error en respuesta:', data.message);
                motivosCache[tipoId] = [];
            }
        } catch (error) {
            console.error('âŒ Error al cargar motivos:', error);
            motivosCache[tipoId] = [];
        }
    } else {
        console.log('ðŸ“¦ Motivos desde cachÃ©:', motivosCache[tipoId].length);
    }

    // Mostrar secciÃ³n
    mostrarSeccion('seccionMotivo', true);
    setRequired('ingresoMotivo', true);

    // Poblar select
    const selectMotivo = document.getElementById('ingresoMotivo');
    if (selectMotivo) {
        selectMotivo.innerHTML = '<option value="">Seleccione motivo...</option>';

        const motivos = motivosCache[tipoId] || [];

        if (motivos.length > 0) {
            motivos.forEach(motivo => {
                const option = document.createElement('option');
                option.value = motivo.id_motivo;
                option.textContent = motivo.descripcion;
                if (motivo.requiere_detalle) {
                    option.dataset.requiereDetalle = '1';
                }
                selectMotivo.appendChild(option);
            });
            console.log('âœ… Select poblado con', motivos.length, 'motivos');
        } else {
            console.warn('âš ï¸ No hay motivos para este tipo');
            // Agregar opciÃ³n temporal
            selectMotivo.innerHTML += '<option value="0">Sin motivo especificado (temporal)</option>';
        }
    } else {
        console.error('âŒ Select ingresoMotivo no encontrado en el DOM');
    }
}


function configurarObservaciones(config) {
    const obsField = document.getElementById('ingresoObservaciones');
    if (!obsField) return;

    setRequired('ingresoObservaciones', config.observaciones_obligatorias);

    if (config.observaciones_obligatorias && config.minimo_caracteres_obs > 0) {
        obsField.setAttribute('minlength', config.minimo_caracteres_obs);
        obsField.placeholder = `Observaciones (mÃ­nimo ${config.minimo_caracteres_obs} caracteres) *`;
    } else {
        obsField.removeAttribute('minlength');
        obsField.placeholder = 'Observaciones opcionales...';
    }
}


function actualizarEncabezadosTabla(config) {
    const thead = document.getElementById('theadIngreso');
    if (!thead) return;

    console.log('ðŸ“‹ Actualizando encabezados para tipo:', config.codigo);

    // Obtener configuraciÃ³n de columnas para este tipo
    const tipoConfig = COLUMNAS_CONFIG[config.codigo];

    if (!tipoConfig) {
        console.warn('âš ï¸ No hay configuraciÃ³n de columnas para:', config.codigo);
        return;
    }

    // Determinar quÃ© set de columnas usar
    let columnasConfig;

    if (config.codigo === 'COMPRA') {
        // Para compras, revisar si hay factura
        const checkFactura = document.getElementById('ingresoConFactura');
        const conFactura = checkFactura && checkFactura.checked;
        columnasConfig = conFactura ? tipoConfig.conFactura : tipoConfig.sinFactura;
    } else {
        // Para otros tipos, usar la configuraciÃ³n Ãºnica
        columnasConfig = tipoConfig;
    }

    // Generar HTML de encabezados
    const columnas = columnasConfig.columnas;

    thead.innerHTML = `
        <tr>
            ${columnas.map(col => {
        let style = `width:${col.width};`;
        if (col.align) style += ` text-align:${col.align};`;
        // if (col.bg) style += ` background:${col.bg}; color: #000000 !important; font-weight: 800 !important;`;
        if (col.calculado) style += ' font-size:0.75rem;';

        return `<th style="${style}">${col.label}</th>`;
    }).join('')}
        </tr>
    `;

    console.log('âœ… Encabezados actualizados con', columnas.length, 'columnas');
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
    try {
        const url = `${BASE_URL_API}/proveedores.php?action=list`;
        console.log('ðŸ”„ Cargando proveedores desde:', url);

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.proveedores) {
            proveedoresData = data.proveedores;
            console.log('âœ… Proveedores cargados:', proveedoresData.length);

            // Poblar select de proveedores
            const selectProveedor = document.getElementById('ingresoProveedor');
            if (selectProveedor) {
                selectProveedor.innerHTML = '<option value="">Seleccione proveedor...</option>';
                proveedoresData.forEach(prov => {
                    const nombre = prov.nombre_comercial || prov.razon_social;
                    selectProveedor.innerHTML += `
                        <option value="${prov.id_proveedor}" 
                                data-tipo="${prov.tipo}" 
                                data-moneda="${prov.moneda || 'BOB'}" 
                                data-pago="${prov.condicion_pago || 'CONTADO'}">
                            ${nombre}
                        </option>
                    `;
                });
            }
        } else {
            console.warn('âš ï¸ No se encontraron proveedores');
            proveedoresData = [];
        }
    } catch (error) {
        logError('cargarProveedores', error);
        proveedoresData = [];
    }
}

async function cargarCategoriasIngreso() {
    try {
        const url = `${BASE_URL_API}/centro_inventarios.php?action=categorias&tipo_id=${window.TIPO_INVENTARIO_ID || 3}`;
        console.log('ðŸ”„ Cargando categorÃ­as desde:', url);

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.categorias) {
            categoriasData = data.categorias;
            console.log('âœ… CategorÃ­as cargadas:', categoriasData.length);

            // Poblar select de filtro de categorÃ­as
            const selectCat = document.getElementById('ingresoFiltroCat');
            if (selectCat) {
                selectCat.innerHTML = '<option value="">Todas las categorÃ­as</option>';
                categoriasData.forEach(cat => {
                    selectCat.innerHTML += `<option value="${cat.id_categoria}">${cat.nombre}</option>`;
                });
            }
        } else {
            console.warn('âš ï¸ No se encontraron categorÃ­as');
            categoriasData = [];
        }
    } catch (error) {
        logError('cargarCategoriasIngreso', error);
        categoriasData = [];
    }
}

/**
 * Filtrar proveedores por tipo (LOCAL o IMPORTACIÃ“N)
 */
function filtrarProveedoresIngreso() {
    const tipoEl = document.getElementById('ingresoTipoProveedor');
    const tipo = (tipoEl && tipoEl.value) || 'TODOS';
    const select = document.getElementById('ingresoProveedor');

    if (!select) return;

    console.log('ðŸ”„ Filtrando proveedores por tipo:', tipo);

    // Limpiar select
    select.innerHTML = '<option value="">Seleccione proveedor...</option>';

    // Verificar que hay proveedores
    if (!proveedoresData || proveedoresData.length === 0) {
        console.warn('âš ï¸ No hay proveedores para filtrar');
        return;
    }

    // Filtrar segÃºn tipo
    let proveedoresFiltrados = proveedoresData;

    if (tipo !== 'TODOS') {
        proveedoresFiltrados = proveedoresData.filter(p => p.tipo === tipo);
    }

    console.log(`ðŸ“‹ Proveedores filtrados: ${proveedoresFiltrados.length} de ${proveedoresData.length}`);

    // Agregar cada proveedor filtrado
    proveedoresFiltrados.forEach(prov => {
        const nombre = prov.nombre_comercial || prov.razon_social || 'Sin nombre';
        const option = document.createElement('option');
        option.value = prov.id_proveedor;
        option.textContent = nombre;
        option.dataset.tipo = prov.tipo || 'LOCAL';
        option.dataset.moneda = prov.moneda || 'BOB';
        option.dataset.pago = prov.condicion_pago || 'CONTADO';
        select.appendChild(option);
    });

    console.log('âœ… Filtro aplicado');
}

/**
 * Actualizar informaciÃ³n del proveedor seleccionado
 */
function actualizarInfoProveedor() {
    const select = document.getElementById('ingresoProveedor');
    const box = document.getElementById('infoProveedorBox');

    if (!select || !box) return;

    if (!select.value) {
        box.style.display = 'none';
        return;
    }

    const opt = select.options[select.selectedIndex];
    const tipo = opt.dataset.tipo;
    const moneda = opt.dataset.moneda;
    const pago = opt.dataset.pago;

    const tipoElement = document.getElementById('infoProveedorTipo');
    if (tipoElement) {
        tipoElement.className = `badge-tipo ${tipo === 'LOCAL' ? 'local' : 'import'}`;
        tipoElement.textContent = tipo === 'LOCAL' ? 'ðŸ‡§ðŸ‡´ LOCAL' : 'ðŸŒŽ IMPORTACIÃ“N';
    }

    const monedaElement = document.getElementById('infoProveedorMoneda');
    if (monedaElement) {
        monedaElement.className = `badge-moneda ${moneda === 'USD' ? 'usd' : 'bob'}`;
        monedaElement.textContent = moneda || 'BOB';
    }

    const pagoElement = document.getElementById('infoProveedorPago');
    if (pagoElement) {
        pagoElement.textContent = `Pago: ${pago || 'N/A'}`;
    }

    box.style.display = 'flex';
}

function obtenerConfiguracionColumnas() {
    if (!tipoIngresoActual) {
        console.warn('âš ï¸ No hay tipo de ingreso seleccionado');
        return null;
    }

    const tipoConfig = COLUMNAS_CONFIG[tipoIngresoActual.codigo];
    if (!tipoConfig) return null;

    if (tipoIngresoActual.codigo === 'COMPRA') {
        const conFactura = document.getElementById('ingresoConFactura')?.checked;
        return conFactura ? tipoConfig.conFactura : tipoConfig.sinFactura;
    }

    return tipoConfig;
}

async function cargarUsuariosAutorizados() {
    try {
        const url = `${BASE_URL_API}/tipos_ingreso.php?action=usuarios_autorizacion`;
        console.log('ðŸ”„ Cargando usuarios autorizados desde:', url);

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        console.log('ðŸ“¦ Respuesta usuarios:', data);

        if (data.success) {
            usuariosAutorizados = data.usuarios;
            console.log('âœ… Usuarios autorizados cargados:', usuariosAutorizados.length);
        } else {
            console.error('âŒ Error:', data.message);
            // Usar mock local
            usuariosAutorizados = [
                { id_usuario: 1, nombre: 'Gary Meneses', rol: 'Gerente' }
            ];
        }
    } catch (error) {
        console.error('âŒ Error al cargar usuarios:', error);
        // Usar mock local
        usuariosAutorizados = [
            { id_usuario: 1, nombre: 'Gary Meneses', rol: 'Gerente' }
        ];
    }
}


// ========================================
// VERIFICAR mostrarSeccionAutorizacion()
// ========================================

function mostrarSeccionAutorizacion() {
    console.log('ðŸ”„ Mostrando secciÃ³n de autorizaciÃ³n');

    mostrarSeccion('seccionAutorizacion', true);
    setRequired('ingresoAutorizadoPor', true);

    // Poblar select de usuarios
    const selectAutoriza = document.getElementById('ingresoAutorizadoPor');

    if (!selectAutoriza) {
        console.error('âŒ Select ingresoAutorizadoPor no encontrado');
        return;
    }

    selectAutoriza.innerHTML = '<option value="">Seleccione quiÃ©n autoriza...</option>';

    if (usuariosAutorizados && usuariosAutorizados.length > 0) {
        usuariosAutorizados.forEach(usuario => {
            const option = document.createElement('option');
            option.value = usuario.id_usuario;
            option.textContent = `${usuario.nombre} - ${usuario.rol}`;
            selectAutoriza.appendChild(option);
        });
        console.log('âœ… Select autorizaciÃ³n poblado con', usuariosAutorizados.length, 'usuarios');
    } else {
        console.warn('âš ï¸ No hay usuarios autorizados cargados');
        // Agregar opciÃ³n temporal
        selectAutoriza.innerHTML += '<option value="1">Administrador (temporal)</option>';
    }
}



// Exponer funciones globalmente para que el HTML pueda llamarlas
window.obtenerConfiguracionColumnas = obtenerConfiguracionColumnas;
window.cambiarTipoIngreso = cambiarTipoIngreso;
window.poblarSelectProveedores = poblarSelectProveedores;
window.abrirModalIngreso = abrirModalIngreso;
window.actualizarInfoProveedor = actualizarInfoProveedor;
window.filtrarProveedoresIngreso = filtrarProveedoresIngreso;
window.mostrarSeccionMotivo = mostrarSeccionMotivo;
window.mostrarSeccionAutorizacion = mostrarSeccionAutorizacion;

console.log('âœ… Sistema de configuraciÃ³n de columnas cargado');
console.log('   Tipos configurados:', Object.keys(COLUMNAS_CONFIG).join(', '));
console.log('âœ… Funciones expuestas globalmente');
console.log('   - cambiarTipoIngreso');
console.log('   - poblarSelectProveedores');
console.log('   - abrirModalIngreso');
console.log('   - actualizarInfoProveedor');
console.log('   - filtrarProveedoresIngreso');
console.log('âœ… MÃ³dulo de tipos de ingreso dinÃ¡mico cargado - VERSIÃ“N FINAL');
console.log('   - mostrarSeccionMotivo');
console.log('   - mostrarSeccionAutorizacion');
console.log('ðŸ“ API Base:', BASE_URL_API);